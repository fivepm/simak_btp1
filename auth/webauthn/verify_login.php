<?php
require_once '../../config/config.php';
require_once '../../helpers/log_helper.php';
require_once '../../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

// Tangkap kiriman data JSON dari JavaScript
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['signature'])) {
    echo json_encode(['success' => false, 'message' => 'Data biometrik tidak lengkap.']);
    exit;
}

try {
    $rpId = $_SERVER['HTTP_HOST'];
    $WebAuthn = new \lbuchs\WebAuthn\WebAuthn('SIMAK', $rpId);

    // 1. CARI DATA KREDENSIAL DI DATABASE
    // $input['id'] adalah Credential ID dalam format base64url yang dikirim oleh HP
    $credentialId = $input['id'];
    
    $stmt = $conn->prepare("SELECT * FROM user_passkeys WHERE credential_id = ? LIMIT 1");
    $stmt->bind_param("s", $credentialId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 0) {
        throw new Exception("Kunci biometrik perangkat ini belum terdaftar di SIMAK.");
    }
    
    $passkey = $res->fetch_assoc();

    // 2. VERIFIKASI TANDA TANGAN DIGITAL DARI HP
    // Kita decode semua data dari format string base64url kembali ke wujud aslinya (binary string)
    $clientDataJSON = base64_decode(strtr($input['clientDataJSON'], '-_', '+/'));
    $authenticatorData = base64_decode(strtr($input['authenticatorData'], '-_', '+/'));
    $signature = base64_decode(strtr($input['signature'], '-_', '+/'));
    $userHandle = isset($input['userHandle']) && $input['userHandle'] !== null ? base64_decode(strtr($input['userHandle'], '-_', '+/')) : null;
    
    $challenge = $_SESSION['webauthn_challenge'] ?? '';

    // Public key di database disimpan sebagai base64, kita ubah kembali ke binary
    $credentialPublicKey = base64_decode($passkey['public_key']); 

    // library melakukan keajaiban kriptografi untuk memvalidasi sidik jari di sini:
    $WebAuthn->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $credentialPublicKey,
        $challenge,
        null,
        $userHandle
    );

    // Jika kode melewati baris processGet tanpa memunculkan throw/error, berarti SIDIK JARI VALID!

    // 3. AMBIL DATA PENGGUNA ASLI (Sama seperti logika login_process.php)
    $userId = $passkey['user_id'];
    $tipeUser = $passkey['tipe_user'];
    $table = ($tipeUser === 'guru') ? 'guru' : 'users';

    $query = "SELECT id, nama, nama_panggilan," . ($table === 'guru' ? '"guru" as role' : 'role') . ", tingkat, kelompok, " . ($table === 'guru' ? 'kelas' : 'NULL as kelas') . ", foto_profil, username FROM $table WHERE id = ? AND deleted_at IS NULL LIMIT 1";
    
    $stmt2 = $conn->prepare($query);
    $stmt2->bind_param("s", $userId);
    $stmt2->execute();
    $userRes = $stmt2->get_result();

    if ($userRes->num_rows === 0) {
        throw new Exception("Akun pengguna tidak ditemukan atau telah dinonaktifkan.");
    }

    $user = $userRes->fetch_assoc();

    // 4. BUAT SESSION LOGIN (Persis seperti login via QR + PIN)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nama'] = $user['nama'] ?? 'Pengguna';
    $_SESSION['user_nama_panggilan'] = $user['nama_panggilan'] ?? $user['nama'];
    $_SESSION['user_role'] = $user['role'] ?? 'guru';
    $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
    $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? 'default.png';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['is_multi_kelas'] = false;

    // Reset failed attempts di tabel utama
    $conn->query("UPDATE $table SET failed_attempts = 0, last_attempt = NULL, last_login = NOW() WHERE id = {$user['id']}");
    
    // Update last_used_at di tabel user_passkeys
    $conn->query("UPDATE user_passkeys SET last_used_at = NOW() WHERE id = {$passkey['id']}");

    // Logika penentuan Redirect
    $redirect_url = '';
    $tampilan_role = '';

    switch ($_SESSION['user_role']) {
        case 'admin':
            $tampilan_role = ($_SESSION['user_tingkat'] == 'desa') ? 'Admin Desa' : 'Admin Kelompok ' . ucwords($user['kelompok']);
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'admin/?page=dashboard';
            break;
        case 'superadmin':
            $tampilan_role = 'Developer';
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'admin/?page=dashboard';
            break;
        case 'ketua pjp':
            $tampilan_role = ($_SESSION['user_tingkat'] == 'desa') ? 'Ketua PJP Desa' : 'Ketua PJP Kelompok ' . ucwords($user['kelompok']);
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'users/ketuapjp/?page=dashboard';
            break;
        case 'bk':
            $tampilan_role = ($_SESSION['user_tingkat'] == 'desa') ? 'BK Desa' : 'BK Kelompok ' . ucwords($user['kelompok']);
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'users/bk/?page=dashboard';
            break;
        case 'pembina':
            $tampilan_role = ($_SESSION['user_tingkat'] == 'desa') ? 'Pembina Desa' : 'Pembina Kelompok ' . ucwords($user['kelompok']);
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'users/pembina/';
            break;
        case 'guru':
            // Cek Multi Kelas
            $stmt_cek = $conn->prepare("SELECT nama_kelas FROM pengampu WHERE id_guru = ?");
            if ($stmt_cek) {
                $stmt_cek->bind_param("i", $user['id']);
                $stmt_cek->execute();
                $res_cek = $stmt_cek->get_result();
                $jumlah_kelas = $res_cek->num_rows;

                if ($jumlah_kelas > 1) {
                    $_SESSION['is_multi_kelas'] = true;
                    $_SESSION['user_kelas'] = '';
                    $tampilan_role = 'Guru (Pilih Kelas)';
                    $redirect_url = 'users/guru/pilih_kelas';
                } elseif ($jumlah_kelas == 1) {
                    $row_kelas = $res_cek->fetch_assoc();
                    $_SESSION['user_kelas'] = $row_kelas['nama_kelas'];
                    $tampilan_role = 'Guru Kelas ' . ucwords($_SESSION['user_kelas']);
                    $redirect_url = 'users/guru/?page=dashboard';
                } else {
                    $_SESSION['user_kelas'] = $user['kelas'] ?? '';
                    $tampilan_role = 'Guru Kelas ' . ucwords($_SESSION['user_kelas']);
                    $redirect_url = 'users/guru/?page=dashboard';
                }
                $stmt_cek->close();
            } else {
                $_SESSION['user_kelas'] = $user['kelas'] ?? '';
                $redirect_url = 'users/guru/?page=dashboard';
            }
            break;
    }

    // Catat log aktivitas jika ada fungsi writeLog
    if (function_exists('writeLog')) {
        writeLog('LOGIN', "Pengguna berhasil masuk ke sistem (*Fast Login Biometrik*). Role: $tampilan_role");
    }

    echo json_encode([
        'success' => true,
        'nama' => $_SESSION['user_nama'],
        'tampilan_role' => $tampilan_role,
        'redirect_url' => $redirect_url
    ]);

} catch (\WebAuthn\WebAuthnException $e) {
    echo json_encode(['success' => false, 'message' => 'Autentikasi sidik jari tidak valid.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}