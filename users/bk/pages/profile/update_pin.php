<?php
session_start();
require_once '../../../../config/config.php';
require_once '../../../../helpers/log_helper.php';

header('Content-Type: application/json');

// --- 1. CEK LOGIN (UNIVERSAL) ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login.']);
    exit;
}

$id_user = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'guru';
$tableName = ($role === 'guru') ? 'guru' : 'users';

// --- 2. AMBIL INPUT ---
$pin_lama = $_POST['pin_lama'] ?? '';
$pin_baru = $_POST['pin_baru'] ?? '';
$pin_konfirmasi = $_POST['pin_konfirmasi'] ?? '';

// --- 3. VALIDASI ---
if (empty($pin_lama) || empty($pin_baru)) {
    echo json_encode(['success' => false, 'message' => 'Semua kolom wajib diisi.']);
    exit;
}
if (!is_numeric($pin_baru) || strlen($pin_baru) !== 6) {
    echo json_encode(['success' => false, 'message' => 'PIN baru harus 6 digit angka.']);
    exit;
}
if ($pin_baru !== $pin_konfirmasi) {
    echo json_encode(['success' => false, 'message' => 'Konfirmasi PIN tidak cocok.']);
    exit;
}

// --- 4. CEK PIN LAMA ---
$stmt = $conn->prepare("SELECT pin FROM $tableName WHERE id = ?");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data) {
    echo json_encode(['success' => false, 'message' => 'Data user tidak ditemukan.']);
    exit;
}

if (!password_verify($pin_lama, $user_data['pin'])) {
    echo json_encode(['success' => false, 'message' => 'PIN Lama salah.']);
    exit;
}

// --- 5. UPDATE PIN ---
$hash_baru = password_hash($pin_baru, PASSWORD_DEFAULT);
$stmt_update = $conn->prepare("UPDATE $tableName SET pin = ? WHERE id = ?");
$stmt_update->bind_param("si", $hash_baru, $id_user);

if ($stmt_update->execute()) {
    writeLog('UPDATE', "Pengguna mengubah PIN Keamanan.");
    echo json_encode(['success' => true, 'message' => 'PIN berhasil diperbarui.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
}
