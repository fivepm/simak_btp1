<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Pastikan user sudah login sebelum bisa mendaftar
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

try {
    $rpId = $_SERVER['HTTP_HOST'];
    $WebAuthn = new \lbuchs\WebAuthn\WebAuthn('SIMAK', $rpId);

    // Siapkan identitas user untuk disimpan di perangkat
    $userId = (string)$_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    $userName = $_SESSION['user_nama'] . " - " . ucwords($userRole)  ?? 'User';
    $userDisplayName = $_SESSION['user_nama_panggilan'] ?? 'Pengguna';

    // Generate challenge (60 detik, cross-platform allowed, required verification)
    $args = $WebAuthn->getCreateArgs($userId, $userName, $userDisplayName, 60, true, "required", null);

    // Simpan challenge ke sesi server
    $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();

    // Konversi nilai binary menjadi base64url untuk Javascript
    $challengeBinary = $_SESSION['webauthn_challenge']->getBinaryString();
    $challengeBase64Url = rtrim(strtr(base64_encode($challengeBinary), '+/', '-_'), '=');
    $userIdBase64Url = rtrim(strtr(base64_encode($userId), '+/', '-_'), '=');

    echo json_encode([
        'success' => true,
        'challenge' => $challengeBase64Url,
        'user' => [
            'id' => $userIdBase64Url,
            'name' => $userName,
            'displayName' => $userDisplayName
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal membuat challenge: ' . $e->getMessage()]);
}