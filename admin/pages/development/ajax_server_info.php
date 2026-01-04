<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/config.php';

define('WA_API_TOKEN', $_ENV['WA_API_TOKEN']);
define('WA_SESSION_KEY', $_ENV['WA_SESSION_KEY']);
define('WA_TEST_NUMBER', $_ENV['GROUP_ID_UMUM']);

// --- 1. Keamanan ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$action = $_POST['action'] ?? '';

// --- Helper: Format Bytes ---
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// --- Helper: Hitung Ukuran Folder (Rekursif) ---
// Ini solusi untuk Shared Hosting agar mendapatkan angka penggunaan yang akurat
function getFolderSize($dir)
{
    $size = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
    } catch (Exception $e) {
        // Abaikan error permission jika ada folder yang tak bisa dibaca
        return 0;
    }
    return $size;
}

// --- Helper Baru: Call API CraftiveLabs ---
function callCraftiveApi($url, $method = 'GET', $data = [])
{
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . WA_API_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    unset($ch);

    if ($result === false) {
        return ['error' => true, 'message' => $error];
    }

    return ['error' => false, 'http_code' => $httpCode, 'response' => json_decode($result, true)];
}

switch ($action) {
    case 'get_stats':
        $data = [];

        // A. PHP & SERVER INFO
        $data['php_version'] = phpversion();
        $data['server_software'] = $_SERVER['SERVER_SOFTWARE'];
        $data['max_upload'] = ini_get('upload_max_filesize');
        $data['max_post'] = ini_get('post_max_size');
        $data['memory_limit'] = ini_get('memory_limit');
        $data['max_execution'] = ini_get('max_execution_time') . 's';

        // B. DATABASE SIZE
        $dbName = '';
        if ($res = mysqli_query($conn, "SELECT DATABASE()")) $dbName = mysqli_fetch_row($res)[0];

        $sqlSize = "SELECT SUM(data_length + index_length) AS size 
                    FROM information_schema.TABLES 
                    WHERE table_schema = '$dbName'";
        $resSize = mysqli_query($conn, $sqlSize);
        $rowSize = mysqli_fetch_assoc($resSize);
        $data['db_name'] = $dbName;
        $data['db_size'] = formatBytes($rowSize['size']);
        $data['mysql_version'] = mysqli_get_server_info($conn);

        // C. DISK USAGE (Disesuaikan untuk Shared Hosting)
        $path = dirname(__DIR__, 3); // Root folder aplikasi

        // 1. Tentukan Limit Manual (1 GB)
        // Ubah angka '1' di bawah ini jika paket hosting Anda berubah (misal jadi 2 GB)
        $limitGB = 0.5;
        $totalSpace = $limitGB * 1024 * 1024 * 1024; // Konversi GB ke Bytes

        // 2. Hitung Penggunaan Real (Scanning Folder)
        // Kita hitung ukuran folder root aplikasi + ukuran database (estimasi kasar total akun)
        $appSize = getFolderSize($path);
        $dbSizeRaw = $rowSize['size'] ?? 0;

        $usedSpace = $appSize;
        // Opsional: Tambahkan $dbSizeRaw jika database dihitung dalam kuota 1GB yang sama
        // $usedSpace += $dbSizeRaw; 

        $freeSpace = $totalSpace - $usedSpace;

        $data['disk_total'] = formatBytes($totalSpace);
        $data['disk_free'] = formatBytes($freeSpace);
        $data['disk_used'] = formatBytes($usedSpace);

        // Hitung persentase
        if ($totalSpace > 0) {
            $data['disk_percent'] = round(($usedSpace / $totalSpace) * 100, 1);
        } else {
            $data['disk_percent'] = 0;
        }

        // D. PHP EXTENSIONS CHECK
        $extensions = ['mysqli', 'gd', 'curl', 'mbstring', 'zip', 'openssl', 'json'];
        $extStatus = [];
        foreach ($extensions as $ext) {
            $extStatus[] = [
                'name' => $ext,
                'active' => extension_loaded($ext)
            ];
        }
        $data['extensions'] = $extStatus;

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // --- FITUR BARU 1: GET DEVICE INFO ---
    case 'get_device_info':
        $url = 'https://notify.craftivelabs.com/api/device/' . WA_SESSION_KEY;
        $api = callCraftiveApi($url, 'GET');

        if (!$api['error'] && $api['http_code'] == 200) {
            $resp = $api['response'];
            if (isset($resp['data'])) {
                echo json_encode(['success' => true, 'data' => $resp['data']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Format respon API tidak dikenali']);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal koneksi ke API (Code: ' . $api['http_code'] . ')'
            ]);
        }
        break;

    // --- FITUR BARU 2: KIRIM PESAN TES ---
    case 'send_test_wa':
        if (empty(WA_TEST_NUMBER) || strlen(WA_TEST_NUMBER) < 10) {
            echo json_encode(['success' => false, 'message' => 'Nomor tujuan tes belum diset di server.']);
            exit;
        }

        $url = 'https://notify.craftivelabs.com/api/message/send';
        $payload = [
            'session_key' => WA_SESSION_KEY,
            'phone'       => WA_TEST_NUMBER,
            'message'     => "*Server Test*\nSistem Server Info berhasil terhubung dengan Gateway WhatsApp.\nWaktu: " . date('Y-m-d H:i:s') . "\n\n> SIMAK Banguntapan 1."
        ];

        $api = callCraftiveApi($url, 'POST', $payload);

        if (!$api['error']) {
            $resp = $api['response'];
            // Cek sukses berdasarkan respon API (biasanya meta code 200)
            if (isset($resp['meta']['code']) && $resp['meta']['code'] == 200) {
                echo json_encode(['success' => true, 'message' => 'Pesan terkirim!']);
            } else {
                // Ambil pesan error dari API jika ada
                $errMsg = $resp['meta']['message'] ?? 'Gagal mengirim pesan.';
                echo json_encode(['success' => false, 'message' => $errMsg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Koneksi API Gagal.']);
        }
        break;

    case 'check_ping':
        $target = $_POST['target'] ?? 'google';
        // Tentukan Host berdasarkan target
        if ($target == 'craftive') {
            $host = 'notify.craftivelabs.com'; // Server WA Gateway Baru
        } else {
            $host = 'www.google.com';
        }
        $port = 80;
        $timeout = 5;

        $startTime = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $endTime = microtime(true);

        if ($fp) {
            $latency = round(($endTime - $startTime) * 1000); // ms
            fclose($fp);
            echo json_encode([
                'success' => true,
                'status' => 'Online',
                'latency' => $latency . 'ms',
                'class' => ($latency < 200) ? 'text-green-600' : 'text-yellow-600'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'status' => 'Offline / Timeout',
                'latency' => '-',
                'class' => 'text-red-600'
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
