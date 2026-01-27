<?php
session_start();
require_once '../config/config.php';
require_once '../helpers/log_helper.php';

// Pastikan respon selalu JSON
header('Content-Type: application/json');

// Tangkap input JSON
$input = json_decode(file_get_contents('php://input'), true);
$response = ['success' => false, 'message' => 'Invalid request'];

// Fungsi Login Sukses
function loginSuccess($user)
{
    global $conn;

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nama'] = $user['nama'] ?? 'Pengguna';
    // Fallback jika nama_panggilan tidak ada di database
    $_SESSION['user_nama_panggilan'] = $user['nama_panggilan'] ?? $user['nama'];
    $_SESSION['user_role'] = $user['role'] ?? 'guru';
    $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
    $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? 'default.png';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['is_multi_kelas'] = false;

    // Reset Counter Gagal Login
    $table = ($user['role'] === 'guru') ? 'guru' : 'users';

    // Cek kolom failed_attempts ada atau tidak sebelum update (untuk menghindari error jika migrasi belum jalan)
    // Namun untuk performa, kita asumsikan sudah ada. Jika error, akan ditangkap di catch global (jika ada)
    // Untuk keamanan login_process, kita gunakan try-catch sederhana di sini atau biarkan silent fail untuk update log
    $stmt = $conn->prepare("UPDATE $table SET failed_attempts = 0, last_attempt = NULL WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
    }

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
                // Fallback jika tabel pengampu belum ada
                $_SESSION['user_kelas'] = $user['kelas'] ?? '';
                $redirect_url = 'users/guru/?page=dashboard';
            }
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

// Proses Login
if (isset($input['barcode'])) {
    $barcode = trim($input['barcode']);
    $pinInput = isset($input['pin']) ? trim($input['pin']) : null;
    $user = null;
    $tableSource = '';

    // 1. Cek di tabel USERS
    // Kita cek dulu apakah kolom-kolom baru (pin, failed_attempts) sudah ada atau belum
    // Agar query tidak crash, kita pilih * dulu atau pastikan kolomnya ada.
    // Untuk amannya, kita gunakan query lengkap tapi dengan error handling.

    $query_users = "SELECT id, nama, nama_panggilan, role, tingkat, kelompok, NULL as kelas, foto_profil, username, pin, failed_attempts, last_attempt FROM users WHERE barcode = ? LIMIT 1";
    $stmt = $conn->prepare($query_users);

    if (!$stmt) {
        // Jika gagal prepare (misal kolom pin belum ada), kirim pesan error spesifik
        echo json_encode(['success' => false, 'message' => 'DB Error (Users): ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        $tableSource = 'users';
    } else {
        // 2. Cek di tabel GURU
        // Perhatikan: deleted_at IS NULL
        $query_guru = "SELECT id, nama, nama_panggilan, 'guru' as role, tingkat, kelompok, kelas, foto_profil, username, pin, failed_attempts, last_attempt FROM guru WHERE barcode = ? AND deleted_at IS NULL LIMIT 1";

        $stmt2 = $conn->prepare($query_guru);

        if (!$stmt2) {
            // Jika gagal prepare (misal kolom deleted_at atau pin belum ada)
            echo json_encode(['success' => false, 'message' => 'DB Error (Guru): ' . $conn->error]);
            exit;
        }

        $stmt2->bind_param("s", $barcode);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($res2->num_rows === 1) {
            $user = $res2->fetch_assoc();
            $tableSource = 'guru';
        }
    }

    if ($user) {
        // Cek Rate Limiting
        $max_attempts = 5;
        $lockout_time = 10;

        $failed_attempts = $user['failed_attempts'] ?? 0; // Handle jika null

        if ($failed_attempts >= $max_attempts) {
            $last_attempt = strtotime($user['last_attempt']);
            $time_diff = (time() - $last_attempt) / 60;

            if ($time_diff < $lockout_time) {
                $wait = ceil($lockout_time - $time_diff);
                echo json_encode(['success' => false, 'message' => "Akun terkunci. Coba lagi dalam $wait menit."]);
                exit;
            } else {
                $conn->query("UPDATE $tableSource SET failed_attempts = 0 WHERE id = {$user['id']}");
                $user['failed_attempts'] = 0;
            }
        }

        // Logika PIN
        if ($pinInput === null) {
            // Minta PIN
            $response = [
                'success' => true,
                'require_pin' => true,
                'nama' => $user['nama'],
                'role' => strtoupper($user['role'] ?? 'GURU'),
                'message' => 'Silakan masukkan PIN.'
            ];
        } else {
            // Verifikasi PIN
            // Pastikan hash pin tidak kosong
            $db_pin = $user['pin'] ?? '';

            if (!empty($db_pin) && password_verify($pinInput, $db_pin)) {
                $response = loginSuccess($user);
            } else {
                // PIN Salah
                $attempts = ($user['failed_attempts'] ?? 0) + 1;
                $conn->query("UPDATE $tableSource SET failed_attempts = $attempts, last_attempt = NOW() WHERE id = {$user['id']}");

                $sisa = $max_attempts - $attempts;
                $msg = ($sisa > 0) ? "PIN Salah! Sisa: $sisa" : "Akun terkunci.";

                $response = ['success' => false, 'message' => $msg, 'require_pin' => ($sisa > 0)];
            }
        }
    } else {
        $response['message'] = 'Barcode tidak valid.';
    }
}

$conn->close();
echo json_encode($response);
