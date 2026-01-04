<?php
session_start();
require_once '../config/config.php';
require_once '../helpers/log_helper.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$response = ['success' => false, 'message' => 'Invalid request'];

function loginSuccess($user)
{
    global $conn; // Kita butuh koneksi database di dalam fungsi ini

    // Siapkan data sesi dasar
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nama'] = $user['nama'] ?? 'Pengguna';
    $_SESSION['user_nama_panggilan'] = $user['nama_panggilan'] ?? $user['nama'];
    $_SESSION['user_role'] = $user['role'] ?? 'guru';
    $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
    $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? 'default.png';
    $_SESSION['username'] = $user['username'] ?? '';

    // Default: Tidak ada multi kelas
    $_SESSION['is_multi_kelas'] = false;

    // Reset Counter Gagal Login di Database
    $table = ($user['role'] === 'guru') ? 'guru' : 'users';
    $stmt = $conn->prepare("UPDATE $table SET failed_attempts = 0, last_attempt = NULL WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();

    // Tentukan URL tujuan berdasarkan role
    $redirect_url = '';
    $tampilan_role = '';

    switch ($_SESSION['user_role']) {
        case 'admin':
            if ($_SESSION['user_tingkat'] == 'desa') {
                $tampilan_role = 'Admin Desa';
            } else {
                $tampilan_role = 'Admin Kelompok ' . ucwords($user['kelompok']);
            }
            $_SESSION['user_kelas'] = ''; // Admin tidak butuh kelas spesifik
            $redirect_url = 'admin/?page=dashboard';
            break;

        case 'superadmin':
            $tampilan_role = 'Developer';
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'admin/?page=dashboard';
            break;

        case 'ketua pjp':
            if ($_SESSION['user_tingkat'] == 'desa') {
                $tampilan_role = 'Ketua PJP Desa';
            } else {
                $tampilan_role = 'Ketua PJP Kelompok ' . ucwords($user['kelompok']);
            }
            $_SESSION['user_kelas'] = '';
            $redirect_url = 'users/ketuapjp/?page=dashboard';
            break;

        case 'guru':
            // --- LOGIKA BARU UNTUK GURU MULTI-KELAS ---

            // Cek tabel pengampu
            $stmt_cek = $conn->prepare("SELECT nama_kelas FROM pengampu WHERE id_guru = ?");
            $stmt_cek->bind_param("i", $user['id']);
            $stmt_cek->execute();
            $res_cek = $stmt_cek->get_result();
            $jumlah_kelas = $res_cek->num_rows;

            if ($jumlah_kelas > 1) {
                // KASUS A: Guru Mengajar > 1 Kelas
                $_SESSION['is_multi_kelas'] = true;
                $_SESSION['user_kelas'] = ''; // Belum diset, harus pilih dulu

                $tampilan_role = 'Guru (Pilih Kelas)';

                // Arahkan ke halaman pemilihan kelas KHUSUS
                $redirect_url = 'users/guru/pilih_kelas';
            } elseif ($jumlah_kelas == 1) {
                // KASUS B: Guru Hanya 1 Kelas
                $row_kelas = $res_cek->fetch_assoc();
                $_SESSION['user_kelas'] = $row_kelas['nama_kelas'];

                $tampilan_role = 'Guru Kelas ' . ucwords($_SESSION['user_kelas']) . ' - ' . ucwords($user['kelompok']);
                $redirect_url = 'users/guru/?page=dashboard';
            } else {
                // KASUS C: Data di pengampu kosong (fallback ke kolom kelas di tabel guru jika ada, atau error)
                $_SESSION['user_kelas'] = $user['kelas'] ?? '';
                $tampilan_role = 'Guru Kelas ' . ucwords($_SESSION['user_kelas']);
                $redirect_url = 'users/guru/?page=dashboard';
            }
            $stmt_cek->close();
            break;
    }

    writeLog('LOGIN', "Pengguna berhasil masuk ke sistem (*Login*). Role: $tampilan_role");

    return [
        'success' => true,
        'nama' => $_SESSION['user_nama'],
        'tampilan_role' => $tampilan_role,
        'redirect_url' => $redirect_url
    ];
}

// Cek metode login (barcode)
if (isset($input['barcode'])) {
    $barcode = trim($input['barcode']);
    $pinInput = isset($input['pin']) ? trim($input['pin']) : null;
    $user = null;
    $tableSource = '';

    // 1. Cek User (Barcode)
    $stmt = $conn->prepare("SELECT id, nama, nama_panggilan, role, tingkat, kelompok, NULL as kelas, foto_profil, username, pin, failed_attempts, last_attempt FROM users WHERE barcode = ? LIMIT 1");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        $tableSource = 'users';
    } else {
        $stmt2 = $conn->prepare("SELECT id, nama, nama_panggilan, 'guru' as role, tingkat, kelompok, kelas, foto_profil, username, pin, failed_attempts, last_attempt FROM guru WHERE barcode = ? LIMIT 1");
        $stmt2->bind_param("s", $barcode);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2->num_rows === 1) {
            $user = $res2->fetch_assoc();
            $tableSource = 'guru';
        }
    }

    if ($user) {
        // 2. Cek Status Terkunci (Rate Limiting)
        $max_attempts = 5;
        $lockout_time = 10; // menit

        if ($user['failed_attempts'] >= $max_attempts) {
            $last_attempt = strtotime($user['last_attempt']);
            $time_diff = (time() - $last_attempt) / 60; // dalam menit

            if ($time_diff < $lockout_time) {
                $wait = ceil($lockout_time - $time_diff);
                echo json_encode(['success' => false, 'message' => "Akun terkunci sementara karena 5x salah PIN. Coba lagi dalam $wait menit."]);
                exit;
            } else {
                // Reset jika waktu lockout sudah lewat
                $conn->query("UPDATE $tableSource SET failed_attempts = 0 WHERE id = {$user['id']}");
                $user['failed_attempts'] = 0;
            }
        }

        // 3. Logika Verifikasi PIN
        if ($pinInput === null) {
            // STEP 1: Barcode Valid, Minta PIN ke Frontend
            $response = [
                'success' => true,
                'require_pin' => true,
                'nama' => $user['nama'],
                'role' => strtoupper($user['role']),
                'message' => 'Silakan masukkan 6 digit PIN Anda.'
            ];
        } else {
            // STEP 2: Verifikasi PIN
            if (password_verify($pinInput, $user['pin'])) {
                // PIN Benar
                $response = loginSuccess($user);
            } else {
                // PIN Salah
                $attempts = $user['failed_attempts'] + 1;
                $conn->query("UPDATE $tableSource SET failed_attempts = $attempts, last_attempt = NOW() WHERE id = {$user['id']}");

                $sisa = $max_attempts - $attempts;
                $msg = ($sisa > 0)
                    ? "PIN Salah! Sisa percobaan: $sisa"
                    : "PIN Salah! Akun terkunci selama $lockout_time menit.";

                $response = ['success' => false, 'message' => $msg, 'require_pin' => ($sisa > 0)];
            }
        }
    } else {
        $response['message'] = 'Barcode tidak valid atau pengguna tidak ditemukan.';
    }
}

$conn->close();
echo json_encode($response);
