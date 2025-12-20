<?php
session_start();

// --- 1. Keamanan ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// --- 2. LOGIKA PENCARIAN FILE LOG YANG LEBIH PINTAR ---

// Tentukan Root Folder Aplikasi (Naik 2 level dari folder 'pages/admin')
$appRoot = dirname(__DIR__, 3);

// Daftar kemungkinan lokasi file log (Prioritas dari atas ke bawah)
$possiblePaths = [
    // 1. Cek konfigurasi PHP (php.ini)
    ini_get('error_log'),

    // 2. Cek file standar cPanel di root aplikasi (Paling umum di Shared Hosting)
    $appRoot . '/error_log',

    // 3. Cek variasi nama lain
    $appRoot . '/php_error.log',
    $appRoot . '/logs/error.log',
    $appRoot . '/error.log',

    // 4. Cek folder parent (Kadang log ada di public_html utama, bukan di folder subdomain)
    dirname($appRoot) . '/error_log'
];

$logFile = null;

// Loop untuk mencari file yang benar-benar ADA dan bisa DIBACA
foreach ($possiblePaths as $path) {
    if (!empty($path) && file_exists($path) && is_readable($path) && !is_dir($path)) {
        $logFile = $path;
        break; // Ketemu! Berhenti mencari.
    }
}

// Jika masih null, kita paksa gunakan default cPanel di root, 
// nanti sistem akan bilang "File tidak ditemukan" tapi setidaknya path-nya benar.
if (!$logFile) {
    $logFile = $appRoot . '/error_log';
}

$action = $_POST['action'] ?? '';

// --- 3. Fungsi Helper: Baca File dari Belakang ---
function tailCustom($filepath, $lines = 50, $adaptive = true)
{
    // ... (KODE INI SAMA SEPERTI SEBELUMNYA, TIDAK PERLU DIUBAH) ...
    $f = @fopen($filepath, "rb");
    if ($f === false) return false;
    if (!$adaptive) $buffer = 4096;
    else $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
    fseek($f, -1, SEEK_END);
    if (fread($f, 1) != "\n") $lines -= 1;
    $output = '';
    $chunk = '';
    while (ftell($f) > 0 && $lines >= 0) {
        $seek = min(ftell($f), $buffer);
        fseek($f, -$seek, SEEK_CUR);
        $output = ($chunk = fread($f, $seek)) . $output;
        fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
        $lines -= substr_count($chunk, "\n");
    }
    while ($lines++ < 0) {
        $output = substr($output, strpos($output, "\n") + 1);
    }
    fclose($f);
    return trim($output);
}

// --- 4. Logika Switch Case ---
switch ($action) {

    case 'get_logs':
        if (!file_exists($logFile)) {
            // Berikan info path mana yang sedang dicari agar Anda bisa debug
            echo json_encode([
                'success' => false,
                'message' => 'File log belum terbentuk atau tidak ditemukan. <br><small class="text-gray-400">Path dicari: ' . $logFile . '</small>'
            ]);
            exit;
        }

        // ... (SISANYA SAMA SEPERTI KODE SEBELUMNYA) ...

        if (!is_readable($logFile)) {
            echo json_encode(['success' => false, 'message' => 'File log ada tapi tidak bisa dibaca (Permission Denied).']);
            exit;
        }

        // Baca 50 baris terakhir
        $rawContent = tailCustom($logFile, 50);

        // Cek jika file kosong
        if (empty($rawContent)) {
            echo json_encode([
                'success' => true,
                'path' => $logFile, // Kirim path agar tampil di frontend
                'size' => '0 KB',
                'data' => []
            ]);
            exit;
        }

        $lines = explode("\n", $rawContent);
        $parsedLogs = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            // Regex standar cPanel/Apache Error Log
            $pattern = '/^\[(.*?)\] (.*?): (.*?) in (.*?) on line (\d+)$/';

            if (preg_match($pattern, $line, $matches)) {
                $parsedLogs[] = [
                    'raw' => false,
                    'date' => $matches[1],
                    'type' => $matches[2],
                    'message' => $matches[3],
                    'file' => $matches[4],
                    'line' => $matches[5]
                ];
            } else {
                $parsedLogs[] = ['raw' => true, 'content' => $line];
            }
        }

        $parsedLogs = array_reverse($parsedLogs);

        echo json_encode([
            'success' => true,
            'path' => $logFile,
            'size' => round(filesize($logFile) / 1024, 2) . ' KB',
            'data' => $parsedLogs
        ]);
        break;

    case 'clear_logs':
        if (file_exists($logFile) && is_writable($logFile)) {
            file_put_contents($logFile, "");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus. Cek izin file.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
        break;
}
