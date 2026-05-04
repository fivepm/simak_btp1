<?php
require_once '../../config/config.php';
require_once '../../vendor/autoload.php';
require_once '../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['clientDataJSON']) || !isset($input['attestationObject'])) {
    echo json_encode(['success' => false, 'message' => 'Data pendaftaran tidak lengkap dari perangkat.']);
    exit;
}

try {
    $rpId = parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST) ?: $_SERVER['HTTP_HOST'];
    $WebAuthn = new \lbuchs\WebAuthn\WebAuthn('SIMAK', $rpId);

    // Decode base64url ke format binary asli
    $clientDataJSON = base64_decode(strtr($input['clientDataJSON'], '-_', '+/'));
    $attestationObject = base64_decode(strtr($input['attestationObject'], '-_', '+/'));
    $challenge = $_SESSION['webauthn_challenge'] ?? '';

    // Validasi data kriptografi (Require user verification = true)
    $credential = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, 'required');

    // Ambil hasil Key
    $credentialId = $credential->credentialId;
    $credentialPublicKey = $credential->credentialPublicKey;

    // Convert ke base64 agar mudah disimpan di database text (MySQL)
    $credIdBase64 = rtrim(strtr(base64_encode($credentialId), '+/', '-_'), '=');
    $pubKeyBase64 = base64_encode($credentialPublicKey);

    // Siapkan data untuk insert
    $userId = $_SESSION['user_id'];
    $tipeUser = $_SESSION['user_role'];
    
    // TANGKAP NAMA PERANGKAT CUSTOM DARI INPUT USER
    $namaPerangkat = isset($input['deviceName']) && trim($input['deviceName']) !== '' 
                     ? trim(htmlspecialchars($input['deviceName'])) 
                     : 'Perangkat Tanpa Nama';

    // Eksekusi Insert ke Database
    $stmt = $conn->prepare("INSERT INTO user_passkeys (user_id, tipe_user, credential_id, public_key, nama_perangkat) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $userId, $tipeUser, $credIdBase64, $pubKeyBase64, $namaPerangkat);
    
    if ($stmt->execute()) {
        if (function_exists('writeLog')) {
            writeLog('SECURITY', "Pengguna mendaftarkan perangkat baru untuk Fast Login ($namaPerangkat).");
        }
        echo json_encode(['success' => true, 'message' => 'Perangkat berhasil didaftarkan untuk Fast Login.']);
    } else {
        throw new Exception("Gagal menyimpan kunci biometrik ke database.");
    }
    
    $stmt->close();

} catch (\WebAuthn\WebAuthnException $e) {
    echo json_encode(['success' => false, 'message' => 'Validasi keamanan ditolak perangkat.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}