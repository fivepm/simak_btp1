<?php
// Pastikan file ini di-include di index.php utama Anda
// require_once 'helpers/log_helper.php';

// Contoh 1: Bold (*)
// writeLog('UPDATE', "Mengubah data siswa *Budi Santoso* (NIS: 12345)");

// Contoh 2: Code/Highlight (`)
// writeLog('INSERT', "Menambah nilai mapel `Matematika` untuk Kelas `7A`");

// Contoh 3: Italic (_)
// writeLog('LOGIN', "User _admin_ganteng_ gagal login 3x");

// Hasilnya di tabel log admin akan terlihat tebal, berwarna, atau miring sesuai formatnya.

if (!function_exists('writeLog')) {

    // Perubahan: Kita hapus parameter $conn dari definisi fungsi
    function writeLog($actionType, $description)
    {

        // 1. Ambil $conn dari Scope Global secara otomatis
        global $conn;

        // 2. Cek apakah koneksi tersedia
        if (!$conn || !($conn instanceof mysqli)) {
            // Jika tidak ada koneksi DB, kita tidak bisa mencatat log ke DB.
            // Opsional: Catat ke error_log server agar ketahuan
            error_log("Gagal mencatat Activity Log: Koneksi database tidak ditemukan. Aksi: $actionType");
            return;
        }

        // 3. Ambil data User dari Session
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['user_nama'] ?? 'Guest/System';
        $role = $_SESSION['user_role'] ?? 'guest';
        if ($role == 'admin' || $role == 'ketua pjp') {
            $role = $role . " - " . $_SESSION['user_tingkat'];
        }

        // 4. Info Teknis
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // 5. Validasi Tipe Aksi
        $validTypes = ['LOGIN', 'LOGOUT', 'INSERT', 'UPDATE', 'DELETE', 'EXPORT', 'MAINTENANCE', 'OTHER'];
        if (!in_array($actionType, $validTypes)) {
            $actionType = 'OTHER';
        }

        // 6. Eksekusi Query
        $sql = "INSERT INTO activity_logs (user_id, user_name, role, action_type, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "issssss", $userId, $userName, $role, $actionType, $description, $ip, $agent);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}
