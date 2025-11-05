<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$response = ['success' => false, 'message' => 'Invalid request'];

function loginSuccess($user)
{
    // Siapkan data sesi dengan nilai default untuk menangani semua kasus
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nama'] = $user['nama'] ?? 'Pengguna';
    $_SESSION['user_role'] = $user['role'] ?? 'guru';
    $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
    $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
    $_SESSION['user_kelas'] = $user['kelas'] ?? '';

    // Tentukan URL tujuan berdasarkan role
    $redirect_url = '';
    switch ($_SESSION['user_role']) {
        case 'admin':
            if ($_SESSION['user_tingkat'] == 'desa') {
                $tampilan_role = 'Admin Desa';
            } else {
                $tampilan_role = 'Admin Kelompok ' . ucwords($user['kelompok']);
            }
            $redirect_url = 'admin/';
            break;
        case 'superadmin':
            $tampilan_role = 'Super Admin';
            $redirect_url = 'admin/';
            break;
        case 'ketua pjp':
            if ($_SESSION['user_tingkat'] == 'desa') {
                $tampilan_role = 'Ketua PJP Desa';
            } else {
                $tampilan_role = 'Ketua PJP Kelompok ' . ucwords($user['kelompok']);
            }
            $redirect_url = 'users/ketuapjp/';
            break;
        case 'guru':
            $tampilan_role = 'Guru Kelas ' . ucwords($user['kelas']) . ' - ' . ucwords($user['kelompok']);
            $redirect_url = 'users/guru/';
            break;
    }

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
    $stmt_user = $conn->prepare("SELECT id, nama, role, tingkat, kelompok, NULL as kelas FROM users WHERE barcode = ? LIMIT 1");
    $stmt_user->bind_param("s", $barcode);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();
        $user['role'] = $user['role']; // Pastikan role ada
    }
    $stmt_user->close();

    // Jika tidak ada, cek di tabel guru
    if (!$user) {
        $stmt_guru = $conn->prepare("SELECT id, nama, 'guru' as role, tingkat, kelompok, kelas FROM guru WHERE barcode = ? LIMIT 1");
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
        $response['message'] = 'Barcode tidak valid.';
    }

    // Cek metode login (username & password)
} elseif (isset($input['username']) && isset($input['password'])) {
    $username = $input['username'];
    $password = $input['password'];
    $user = null;

    // Cek di tabel users dulu
    $stmt_user = $conn->prepare("SELECT id, nama, role, password, tingkat, kelompok, NULL as kelas FROM users WHERE username = ? LIMIT 1");
    $stmt_user->bind_param("s", $username);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $user_data = $result_user->fetch_assoc();
        if (password_verify($password, $user_data['password'])) {
            $user = $user_data;
        } else {
            $response['message'] = 'Password salah.';
        }
    }
    $stmt_user->close();

    // Jika tidak ada di users, cek di tabel guru
    if (!$user && !isset($response['message'])) {
        $stmt_guru = $conn->prepare("SELECT id, nama, 'guru' as role, password, tingkat, kelompok, kelas FROM guru WHERE username = ? LIMIT 1");
        $stmt_guru->bind_param("s", $username);
        $stmt_guru->execute();
        $result_guru = $stmt_guru->get_result();
        if ($result_guru->num_rows === 1) {
            $user_data = $result_guru->fetch_assoc();
            if (password_verify($password, $user_data['password'])) {
                $user = $user_data;
            } else {
                $response['message'] = 'Password salah.';
            }
        }
        $stmt_guru->close();
    }

    if ($user) {
        $response = loginSuccess($user);
    } elseif (!isset($response['message'])) {
        $response['message'] = 'Username tidak ditemukan.';
    }
}

$conn->close();
echo json_encode($response);
