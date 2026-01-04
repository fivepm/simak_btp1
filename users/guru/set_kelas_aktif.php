<?php
session_start();
require_once '../../config/config.php';
require_once '../../helpers/log_helper.php';

// Validasi akses
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guru') {
    header("Location: ../../index");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kelas_tujuan'])) {
    $id_guru = $_SESSION['user_id'];
    $kelas_tujuan = $_POST['kelas_tujuan'];

    // VALIDASI KEAMANAN: Pastikan guru ini MEMANG mengajar kelas yang dipilih
    // (Mencegah user iseng ganti value HTML)
    $stmt = $conn->prepare("SELECT id_pengampu FROM pengampu WHERE id_guru = ? AND nama_kelas = ?");
    $stmt->bind_param("is", $id_guru, $kelas_tujuan);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Valid! Set Session
        $_SESSION['user_kelas'] = $kelas_tujuan;

        // Log perpindahan (opsional, biar cctv tau)
        writeLog('LOGIN', "Guru memilih/berpindah ke kelas: " . ucwords($kelas_tujuan));

        // Redirect ke Dashboard
        header("Location: /users/guru/?page=dashboard");
        exit;
    } else {
        // Invalid (Data dimanipulasi)
        echo "<script>alert('Akses ditolak. Anda tidak mengajar kelas ini.'); window.location='pilih_kelas.php';</script>";
    }
} else {
    header("Location: pilih_kelas");
}
