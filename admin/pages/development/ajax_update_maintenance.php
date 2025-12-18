<?php
// Selalu mulai session
session_start();

// --- PENTING: Sertakan Koneksi Database Anda ---
// Sesuaikan path ini ke file koneksi $conn Anda
require_once dirname(__DIR__, 3) . '/config/config.php';

// --- 1. Keamanan & Validasi Input ---

// Hanya Admin & Hanya request POST
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin' || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Pastikan koneksi ada
if (!isset($conn)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

// Ambil data JSON dari body request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['key']) || !isset($data['value'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Input tidak valid.']);
    exit;
}

// --- 2. Proses Database ---
$setting_key = $data['key'];
$setting_value = $data['value'];

// Sanitasi (meskipun kita akan pakai prepared statement)
$allowed_keys = ['maintenance_mode', 'nama_sekolah']; // Daftar putih
if (!in_array($setting_key, $allowed_keys)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Kunci pengaturan tidak dikenal.']);
    exit;
}

// Update Setting Utama (ON/OFF)
$sql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $setting_value, $setting_key);
$execute1 = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// --- LOGIKA BARU: SIMPAN PIN ---
// Jika ada data 'pin' yang dikirim (hanya saat mengaktifkan maintenance)
if ($execute1 && isset($data['pin']) && !empty($data['pin'])) {
    $pin = $data['pin'];

    // Kita gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk PIN
    // karena baris 'maintenance_pin' mungkin belum ada di DB
    $sql_pin = "INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_pin', ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt_pin = mysqli_prepare($conn, $sql_pin);
    mysqli_stmt_bind_param($stmt_pin, "ss", $pin, $pin);
    mysqli_stmt_execute($stmt_pin);
    mysqli_stmt_close($stmt_pin);
}

// --- (BARU) CATAT LOG RIWAYAT ---
// Kita hanya mencatat log jika yang diubah adalah 'maintenance_mode'
if ($execute1 && $setting_key == 'maintenance_mode') {
    $adminId = $_SESSION['user_id'];
    $adminName = $_SESSION['user_nama'] ?? 'Admin';
    $action = ($setting_value == 'true') ? 'activated' : 'deactivated';

    $sql_log = "INSERT INTO maintenance_logs (admin_id, admin_name, action) VALUES (?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iss", $adminId, $adminName, $action);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
}

if ($execute1) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pengaturan.']);
}

exit;
