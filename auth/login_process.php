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
    $_SESSION['user_role'] = $user['role'] ?? 'guru';
    $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
    $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? 'default.png';
    $_SESSION['username'] = $user['username'] ?? '';

    // Default: Tidak ada multi kelas
    $_SESSION['is_multi_kelas'] = false;

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
                $redirect_url = 'users/guru/pilih_kelas.php';
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
    $user = null;

    // Cek di tabel users dulu
    $stmt_user = $conn->prepare("SELECT id, nama, role, tingkat, kelompok, NULL as kelas, foto_profil, username FROM users WHERE barcode = ? LIMIT 1");
    $stmt_user->bind_param("s", $barcode);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();
        // Role sudah ada di database
    }
    $stmt_user->close();

    // Jika tidak ada, cek di tabel guru
    if (!$user) {
        $stmt_guru = $conn->prepare("SELECT id, nama, 'guru' as role, tingkat, kelompok, kelas, foto_profil, username FROM guru WHERE barcode = ? LIMIT 1");
        $stmt_guru->bind_param("s", $barcode);
        $stmt_guru->execute();
        $result_guru = $stmt_guru->get_result();
        if ($result_guru->num_rows === 1) {
            $user = $result_guru->fetch_assoc();
        }
        $stmt_guru->close();
    }

    if ($user) {
        $response = loginSuccess($user);
    } else {
        $response['message'] = 'Barcode tidak valid atau pengguna tidak ditemukan.';
    }
}

$conn->close();
echo json_encode($response);
