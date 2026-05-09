<?php
session_start();
require_once '../../config/config.php';
require_once '../../helpers/log_helper.php';

// Pastikan semua balasan dari file ini adalah JSON murni
header('Content-Type: application/json');

// Validasi akses (Cegah akses langsung dari URL)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guru') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Silakan login kembali.']);
    exit;
}

// Tangkap body JSON yang dikirimkan oleh Fetch API Javascript
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['kelas_tujuan'])) {
    $id_guru = $_SESSION['user_id'];
    $kelas_tujuan = $input['kelas_tujuan'];

    // VALIDASI KEAMANAN: Pastikan guru ini MEMANG mengajar kelas yang dipilih
    $stmt = $conn->prepare("SELECT id_pengampu FROM pengampu WHERE id_guru = ? AND nama_kelas = ?");
    $stmt->bind_param("is", $id_guru, $kelas_tujuan);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Valid! Set Session Kelas
        $_SESSION['user_kelas'] = $kelas_tujuan;

        // === CEK STATUS WALI KELAS ===
        $stmt_wk = $conn->prepare("
            SELECT wk.id_guru 
            FROM wali_kelas wk 
            JOIN kelas k ON wk.id_kelas = k.id 
            WHERE wk.id_guru = ? AND k.nama_kelas = ?
        ");
        $stmt_wk->bind_param("is", $id_guru, $kelas_tujuan);
        $stmt_wk->execute();
        
        if ($stmt_wk->get_result()->num_rows > 0) {
            $_SESSION['is_wali_kelas'] = true;
            // Ambil nama dan siapkan string role untuk animasi welcome
            $nama_guru = $_SESSION['user_nama'] ?? 'Guru';
            $tampilan_role = 'Wali Kelas ' . ucwords($kelas_tujuan);
        } else {
            $_SESSION['is_wali_kelas'] = false;
            // Ambil nama dan siapkan string role untuk animasi welcome
            $nama_guru = $_SESSION['user_nama'] ?? 'Guru';
            $tampilan_role = 'Guru Kelas ' . ucwords($kelas_tujuan);
        }
        $stmt_wk->close();
        // =============================

        // Log perpindahan (opsional, biar cctv tau)
        if (function_exists('writeLog')) {
            writeLog('LOGIN', "Guru memilih/berpindah ke kelas: " . ucwords($kelas_tujuan));
        }

        // Kirim response sukses ke Javascript beserta data profil
        echo json_encode([
            'status' => 'success', 
            'redirect_url' => '/users/guru/?page=dashboard', // URL relatif untuk diarahkan oleh JS
            'nama' => $nama_guru,
            'tampilan_role' => $tampilan_role
        ]);
        exit;
    } else {
        // Invalid (Data dimanipulasi)
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda tidak terdaftar sebagai pengampu di kelas ini.']);
        exit;
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid.']);
    exit;
}