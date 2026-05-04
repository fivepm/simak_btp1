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
    // Ambil host bersih tanpa port
    $host = $_SERVER['HTTP_HOST'];
    $hostWithoutPort = explode(':', $host)[0]; // Hapus :443 atau :8080 jika ada

    // Deteksi environment otomatis
    if ($hostWithoutPort === 'localhost' || str_ends_with($hostWithoutPort, '.test') || str_ends_with($hostWithoutPort, '.local')) {
        // Mode development (localhost, simak.test, simak.local, dll)
        $rpId = $hostWithoutPort;
    } else {
        // Mode production — hardcode domain production kamu
        $rpId = 'simak.domain.com';
    }
    $WebAuthn = new \lbuchs\WebAuthn\WebAuthn('SIMAK', $rpId);
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $currentOrigin = $protocol . '://' . $hostWithoutPort;
    $WebAuthn->addAllowedOrigin($currentOrigin);

    // Siapkan identitas user untuk disimpan di perangkat
    $userId = (string)$_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    $userTingkat = $_SESSION['user_tingkat'];
    if($userRole === "guru" || $userRole === "superadmin"){
        $userName = $_SESSION['user_nama'] . " - " . ucwords($userRole)  ?? 'User';
    }else{
        $userName = $_SESSION['user_nama'] . " - " . ucwords($userRole) . " " . ucwords($userTingkat)  ?? 'User';
    }
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