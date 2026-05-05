<?php
// Pastikan path ke vendor autoload dan config benar
require_once '../../vendor/autoload.php';
require_once '../../config/config.php';

session_start();
// Set response sebagai JSON
header('Content-Type: application/json');

try {
    // Relying Party ID (Domain aplikasi kamu)
    $rpId = parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST) ?: $_SERVER['HTTP_HOST'];
    
    // Inisialisasi WebAuthn (Nama Aplikasi, Domain)
    $WebAuthn = new \lbuchs\WebAuthn\WebAuthn('SIMAK', $rpId);
    
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
        'challenge' => $challengeBase64Url,
        'rpId' => $rpId
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal membuat challenge: ' . $e->getMessage()
    ]);
}