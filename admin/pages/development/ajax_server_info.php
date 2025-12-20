<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/config.php';

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

        // C. DISK USAGE (Folder Project)
        $path = dirname(__DIR__, 3); // Root folder
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedSpace = $totalSpace - $freeSpace;

        $data['disk_total'] = formatBytes($totalSpace);
        $data['disk_free'] = formatBytes($freeSpace);
        $data['disk_used'] = formatBytes($usedSpace);
        $data['disk_percent'] = round(($usedSpace / $totalSpace) * 100, 1);

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

    case 'check_ping':
        $target = $_POST['target'] ?? 'google';
        $host = ($target == 'fonnte') ? 'api.fonnte.com' : 'www.google.com';
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
