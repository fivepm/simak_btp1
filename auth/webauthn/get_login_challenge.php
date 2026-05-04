<?php
// Pastikan path ke vendor autoload dan config benar
require_once '../../vendor/autoload.php';
require_once '../../config/config.php';

session_start();
// Set response sebagai JSON
header('Content-Type: application/json');

try {
    // Relying Party ID (Domain aplikasi kamu)
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
    
    // Inisialisasi WebAuthn (Nama Aplikasi, Domain)
    $WebAuthn = new \lbuchs\WebAuthn\WebAuthn('SIMAK', $rpId);
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $currentOrigin = $protocol . '://' . $hostWithoutPort;
    $WebAuthn->addAllowedOrigin($currentOrigin);
    
    // Generate argument challenge untuk proses Get (Login)
    // Parameter kosong berarti kita mengizinkan semua kredensial (Discoverable Credentials/Passkeys)
    $args = $WebAuthn->getGetArgs(null, 60, true);
    
    // Simpan challenge dalam bentuk binary ke session server untuk dicek nanti
    $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();
    
    // Konversi nilai binary dari challenge menjadi string base64url
    // agar bisa dibaca dan dikirim ke browser JavaScript
    $challengeBinary = $_SESSION['webauthn_challenge']->getBinaryString();
    $challengeBase64Url = rtrim(strtr(base64_encode($challengeBinary), '+/', '-_'), '=');
    
    echo json_encode([
        'success' => true,
        'challenge' => $challengeBase64Url
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal membuat challenge: ' . $e->getMessage()
    ]);
}