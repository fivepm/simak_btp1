<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

// Set Header JSON
header('Content-Type: application/json');

// 1. KEAMANAN: Cek Login & Role Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Superadmin yang boleh melakukan ini.']);
    exit;
}

// 2. AMBIL INPUT
$target_id = $_POST['target_id'] ?? '';
$target_barcode = $_POST['target_barcode'] ?? '';
$target_role = $_POST['target_role'] ?? 'guru'; // 'guru' atau 'users'
$admin_pin = $_POST['admin_pin'] ?? '';
$superadmin_id = $_SESSION['user_id'];

// 3. VALIDASI INPUT
if (empty($target_id) || empty($admin_pin)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

// Tentukan Nama Tabel berdasarkan target role
// Jika targetnya adalah sesama admin/pengurus, gunakan tabel 'users'. Jika guru, gunakan 'guru'.
$target_table = ($target_role === 'guru') ? 'guru' : 'users';

// 4. VERIFIKASI PIN SUPERADMIN
// Ambil PIN Superadmin dari tabel users
$stmt_admin = $conn->prepare("SELECT pin FROM users WHERE id = ? LIMIT 1");
$stmt_admin->bind_param("i", $superadmin_id);
$stmt_admin->execute();
$res_admin = $stmt_admin->get_result();
$data_admin = $res_admin->fetch_assoc();

if (!$data_admin) {
    echo json_encode(['success' => false, 'message' => 'Akun Superadmin tidak ditemukan.']);
    exit;
}

// Cek kecocokan PIN
if (!password_verify($admin_pin, $data_admin['pin'])) {
    // Log percobaan gagal (Opsional)
    writeLog('SECURITY', "Percobaan reset PIN gagal: PIN Superadmin salah.");
    echo json_encode(['success' => false, 'message' => 'PIN Superadmin salah!']);
    exit;
}

// 5. LAKUKAN RESET PIN TARGET
// PIN Default: 123456
$default_pin = '354313';
$new_pin_hash = password_hash($default_pin, PASSWORD_DEFAULT);

// Ambil nama target untuk log sebelum direset
$stmt_target = $conn->prepare("SELECT nama FROM $target_table WHERE id = ?");
$stmt_target->bind_param("i", $target_id);
$stmt_target->execute();
$data_target = $stmt_target->get_result()->fetch_assoc();
$nama_target = $data_target['nama'] ?? 'Unknown';

// Update PIN dan Reset Counter Kegagalan Login
$stmt_update = $conn->prepare("UPDATE $target_table SET pin = ?, failed_attempts = 0 WHERE id = ? AND barcode = ?");
$stmt_update->bind_param("sis", $new_pin_hash, $target_id, $target_barcode);

if ($stmt_update->execute()) {
    // Log Aktivitas
    writeLog('UPDATE', "Superadmin mereset PIN untuk $target_role: $nama_target.");

    echo json_encode([
        'success' => true,
        'message' => "PIN untuk $nama_target berhasil direset."
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mereset PIN: ' . $conn->error]);
}

$conn->close();
