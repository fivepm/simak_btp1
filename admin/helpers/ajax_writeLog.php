<?php

/**
 * Fungsi: Endpoint khusus untuk mencatat log aktivitas dari Frontend (JS)
 */

session_start();

// Sesuaikan path ke config dan helper kamu (dirname 3x jika file ini di pages/development/)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/log_helper.php';

// Set Header JSON agar response valid
header('Content-Type: application/json');

// 1. Cek Login (Keamanan)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Login required']);
    exit;
}

// 2. Ambil Data POST
// Kita buat defaultnya 'OTHER' jika tidak dikirim
$logType = $_POST['log_type'] ?? 'OTHER';
$message = $_POST['message'] ?? 'Aktivitas tanpa keterangan';

// 3. Validasi Sederhana (Opsional)
// Pastikan pesan tidak kosong
if (trim($message) === '') {
    echo json_encode(['success' => false, 'message' => 'Message empty']);
    exit;
}

try {
    // 4. Panggil Helper writeLog
    // Asumsi fungsi writeLog($type, $message) sudah ada di log_helper.php
    writeLog($logType, $message);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Tangkap error jika ada masalah sistem
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
