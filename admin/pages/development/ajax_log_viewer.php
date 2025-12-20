<?php
session_start();

// --- 1. Keamanan ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// --- 2. Tentukan Lokasi File Log ---
// Prioritas 1: Ambil dari setting PHP
$logFile = ini_get('error_log');

// Prioritas 2: Jika setting PHP kosong, coba cari manual (sesuaikan jika perlu)
if (empty($logFile)) {
    $logFile = __DIR__ . '/../../php_error.log'; // Contoh path manual
}

$action = $_POST['action'] ?? '';

// --- 3. Fungsi Helper: Baca File dari Belakang ---
function tailCustom($filepath, $lines = 50, $adaptive = true)
{
    $f = @fopen($filepath, "rb");
    if ($f === false) return false;

    // Set ukuran buffer
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
            echo json_encode(['success' => false, 'message' => 'File error_log tidak ditemukan di: ' . $logFile]);
            exit;
        }

        if (!is_readable($logFile)) {
            echo json_encode(['success' => false, 'message' => 'File log ada tapi tidak bisa dibaca (Permission Denied).']);
            exit;
        }

        // Baca 50 baris terakhir
        $rawContent = tailCustom($logFile, 50);
        $lines = explode("\n", $rawContent);
        $parsedLogs = [];

        // Parsing (Memecah teks jadi data)
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Regex sederhana untuk log standar PHP
            // Format: [Date Time] Type: Message in File on Line X
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
                // Jika format tidak dikenali, tampilkan mentah
                $parsedLogs[] = [
                    'raw' => true,
                    'content' => $line
                ];
            }
        }

        // Balik urutan agar yang terbaru ada di atas
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
            file_put_contents($logFile, ""); // Kosongkan file
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus. Cek izin file.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
        break;
}
