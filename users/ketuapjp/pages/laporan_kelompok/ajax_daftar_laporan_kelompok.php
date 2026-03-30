<?php
require_once '../../../../config/config.php';
require_once '../../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

// Cek Sesi Login
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

// 1. Ambil nama kelompok ketua dari session
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// 2. Mapping nama kelompok berdasarkan ID
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];

// 3. Cari kelompok_id berupa angka
$kelompok_id_login = array_search($admin_kelompok, $DATA_KELOMPOK);

// Validasi jika kelompok tidak ada
if (!$kelompok_id_login) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Data kelompok Anda tidak valid atau tidak ditemukan.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ==========================================================
// 1. GET DATA (Render Tabel Daftar Periode untuk Ketua Kelompok)
// ==========================================================
if ($action === 'get_list') {
    // Query mengambil data periode dan status laporan KHUSUS kelompok ketua ini
    $query = "
        SELECT 
            p.id as periode_id, 
            p.nama_periode, 
            p.tanggal_selesai,
            DATE_FORMAT(p.tanggal_selesai, '%d %b %Y') as tanggal_akhir_format,
            lk.id as laporan_kelompok_id,
            lk.status as status_laporan,
            lk.updated_at
        FROM periode p
        LEFT JOIN laporan_pjp_kelompok lk ON p.id = lk.periode_id AND lk.kelompok_id = ?
        ORDER BY p.tanggal_selesai DESC
    ";

    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $kelompok_id_login);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            throw new Exception("Query Database Error: " . $conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $stmt->close();
        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data: ' . $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
