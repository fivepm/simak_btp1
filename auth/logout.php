<?php
// Mulai sesi untuk mengaksesnya
session_start();
require_once '../config/config.php';
require_once '../helpers/log_helper.php';
// Kita catat dulu sebelum session dihancurkan, agar tahu siapa yg logout
writeLog('LOGOUT', "Pengguna keluar dari sistem (*Logout*).");

// Hapus semua variabel sesi
$_SESSION = [];

// Hancurkan sesi
session_unset();
session_destroy();

// Hapus cookie sesi jika digunakan
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Berikan respons JSON bahwa logout berhasil
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Logout berhasil.']);
exit;
