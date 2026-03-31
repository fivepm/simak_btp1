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

// Mengambil session user_kelompok sesuai standarmu
// Untuk Ketua PJP Desa, biasanya user_kelompok bernilai 'Desa' atau kosong, 
// tapi kita tangkap nilainya untuk log/validasi jika diperlukan.
$admin_kelompok = $_SESSION['user_kelompok'] ?? 'Desa';

$action = $_REQUEST['action'] ?? '';

// ==========================================================
// 1. GET DATA (Render Tabel Daftar Periode untuk Ketua Desa)
// ==========================================================
if ($action === 'get_list') {
    // Query mengambil data periode dan status laporan DESA
    $query = "
        SELECT 
            p.id as periode_id, 
            p.nama_periode, 
            p.tanggal_selesai,
            DATE_FORMAT(p.tanggal_selesai, '%d %b %Y') as tanggal_akhir_format,
            ld.id as laporan_desa_id,
            ld.status as status_desa,
            ld.updated_at
        FROM periode p
        LEFT JOIN laporan_pjp_desa ld ON p.id = ld.periode_id
        ORDER BY p.tanggal_selesai DESC
    ";

    try {
        $stmt = $conn->prepare($query);
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
