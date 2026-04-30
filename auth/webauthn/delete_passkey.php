<?php
require_once '../../vendor/autoload.php';
require_once '../../config/config.php';
require_once '../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$userId = $_SESSION['user_id'];
$tipeUser = $_SESSION['user_role'];

// Tangkap ID spesifik yang dikirim oleh Javascript
$input = json_decode(file_get_contents('php://input'), true);
$passkeyId = $input['id'] ?? null;

if (!$passkeyId) {
    echo json_encode(['success' => false, 'message' => 'ID Perangkat tidak ditemukan.']);
    exit;
}

// Hapus hanya passkey dengan ID tersebut (ditambah validasi user_id agar aman dari Hacking)
$stmt = $conn->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ? AND tipe_user = ?");
$stmt->bind_param("iss", $passkeyId, $userId, $tipeUser);

if ($stmt->execute()) {
    if (function_exists('writeLog')) {
        writeLog('SECURITY', "Pengguna mencabut/menghapus sebuah akses Fast Login (Biometrik) untuk perangkat spesifik.");
    }
    echo json_encode(['success' => true, 'message' => 'Akses Fast Login perangkat tersebut telah dihapus.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data dari sistem.']);
}
$stmt->close();