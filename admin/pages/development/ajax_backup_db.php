<?php
// --- PENTING: Buffer Output ---
ob_start();

session_start();
// Set time limit to unlimited untuk database besar
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once dirname(__DIR__, 3) . '/config/config.php';

// --- Fungsi Helper untuk kirim JSON yang BERSIH ---
function sendJsonResponse($data)
{
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- 1. Keamanan ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    sendJsonResponse(['success' => false, 'message' => 'Akses ditolak.']);
}

// Konfigurasi Folder Backup
$backupDir = dirname(__DIR__, 3) . '/storage/backups/';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$htaccessPath = $backupDir . '.htaccess';
if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, 'Deny from all');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- 2. Fungsi Helper Backup (PHP Native) ---
function backupDatabase($conn, $backupDir)
{
    // Ambil nama DB dari koneksi
    $dbName = '';
    if ($result = mysqli_query($conn, "SELECT DATABASE()")) {
        $row = mysqli_fetch_row($result);
        $dbName = $row[0];
    }

    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $filename = 'backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';
    $handle = fopen($backupDir . $filename, 'w+');

    if (!$handle) {
        throw new Exception("Gagal membuat file backup. Cek izin folder.");
    }

    fwrite($handle, "-- SIMAK Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Database: " . $dbName . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    foreach ($tables as $table) {
        $row2 = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE $table"));
        fwrite($handle, "\n\n" . $row2[1] . ";\n\n");
        $result = mysqli_query($conn, "SELECT * FROM $table");
        $numFields = mysqli_num_fields($result);
        while ($row = mysqli_fetch_row($result)) {
            $line = "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $numFields; $j++) {
                // PERBAIKAN: Cek apakah nilai NULL sebelum addslashes
                if (isset($row[$j])) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    $line .= '"' . $row[$j] . '"';
                } else {
                    $line .= 'NULL'; // Gunakan NULL standar SQL jika data kosong
                }

                if ($j < ($numFields - 1)) {
                    $line .= ',';
                }
            }
            $line .= ");\n";
            fwrite($handle, $line);
        }
    }
    fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;");
    fclose($handle);

    return $filename;
}

// --- 3. Switch Case Logic ---
switch ($action) {

    // A. BUAT BACKUP BARU (Updated dengan Log DB)
    case 'create_backup':
        try {
            // 1. Buat File Fisik
            $filename = backupDatabase($conn, $backupDir);

            // 2. Catat ke Database (Audit Trail)
            $adminId = $_SESSION['user_id'];
            $adminName = $_SESSION['user_nama'] ?? 'Admin';
            $fileSize = round(filesize($backupDir . $filename) / 1024, 2) . ' KB';

            $stmt = mysqli_prepare($conn, "INSERT INTO backup_logs (admin_id, admin_name, filename, file_size) VALUES (?, ?, ?, ?)");

            // PERBAIKAN DI SINI: "isss" (Integer, String, String, String)
            // Sebelumnya salah tulis "iss"
            mysqli_stmt_bind_param($stmt, "isss", $adminId, $adminName, $filename, $fileSize);

            if (!mysqli_stmt_execute($stmt)) {
                // Opsional: Jika insert log gagal, kita biarkan saja (jangan gagalkan backup fisik)
                // atau catat error ke error log server
                error_log("Gagal insert backup log: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            sendJsonResponse(['success' => true, 'filename' => $filename]);
        } catch (Exception $e) {
            sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // B. LIST FILE BACKUP
    case 'list_backups':
        $files = glob($backupDir . '*.sql');
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => round(filesize($file) / 1024, 2) . ' KB',
                'date' => date('d M Y H:i', filemtime($file)),
                'path' => $file
            ];
        }
        usort($backups, function ($a, $b) use ($backupDir) {
            return filemtime($backupDir . $b['filename']) - filemtime($backupDir . $a['filename']);
        });
        sendJsonResponse(['success' => true, 'data' => $backups]);
        break;

    // C. GET BACKUP LOGS (Hanya untuk Audit)
    case 'get_backup_logs':
        $logs = [];
        $query = mysqli_query($conn, "SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 50");

        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $logs[] = [
                    'admin_name' => $row['admin_name'],
                    'filename' => $row['filename'],
                    'size' => $row['file_size'],
                    'date' => date('d M Y H:i', strtotime($row['created_at']))
                ];
            }
            sendJsonResponse(['success' => true, 'data' => $logs]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Gagal mengambil log.']);
        }
        break;

    // D. HAPUS FILE
    case 'delete_backup':
        $filename = $_POST['filename'] ?? '';
        if (basename($filename) !== $filename || empty($filename)) {
            sendJsonResponse(['success' => false, 'message' => 'Nama file tidak valid.']);
        }
        $target = $backupDir . $filename;
        if (file_exists($target)) {
            unlink($target);
            sendJsonResponse(['success' => true]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'File tidak ditemukan.']);
        }
        break;

    // E. DOWNLOAD FILE
    case 'download_backup':
        ob_end_clean();
        $filename = $_GET['filename'] ?? '';
        if (basename($filename) !== $filename || empty($filename)) {
            die('Invalid filename.');
        }
        $target = $backupDir . $filename;
        if (file_exists($target)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($target) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($target));
            flush();
            readfile($target);
            exit;
        } else {
            die('File not found.');
        }
        break;

    default:
        sendJsonResponse(['success' => false, 'message' => 'Aksi tidak valid.']);
        break;
}
