<?php
require_once '../../../../config/config.php';
require_once '../../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

// Validasi session (Khusus Admin Desa)
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Definisi Standar Kelompok & Kelas SIMAK
$DATA_KELOMPOK = [
    1 => 'Bintaran',
    2 => 'Gedongkuning',
    3 => 'Jombor',
    4 => 'Sunten'
];

$DATA_KELAS = ['PAUD', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];

// ==========================================================
// 1. GET LIST (Untuk me-render 2 halaman tabel daftar periode)
// ==========================================================
if ($action === 'get_list') {
    $total_kelompok_hardcode = count($DATA_KELOMPOK);

    // Query ini akan mengambil seluruh daftar periode dan merekap status kelompok + desa
    $query = "
        SELECT 
            p.id, 
            p.nama_periode, 
            p.tanggal_selesai as tanggal_akhir,
            DATE_FORMAT(p.tanggal_selesai, '%d %b %Y') as tanggal_akhir_format,
            (SELECT COUNT(id) FROM laporan_pjp_kelompok WHERE periode_id = p.id) as cek_generated,
            $total_kelompok_hardcode as total_kelompok,
            (SELECT COUNT(id) FROM laporan_pjp_kelompok WHERE periode_id = p.id AND status = 'TTD_KETUA') as kelompok_selesai,
            (SELECT status FROM laporan_pjp_desa WHERE periode_id = p.id LIMIT 1) as status_desa
        FROM periode p
        ORDER BY p.tanggal_selesai DESC
    ";

    try {
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query Database Error: " . $conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            // is_generated bernilai true jika Admin Desa sudah pernah klik tombol "Generate Laporan"
            $row['is_generated'] = (int)$row['cek_generated'] > 0;
            $data[] = $row;
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
