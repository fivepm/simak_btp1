<?php
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

// Mapping nama kelompok (Bisa disesuaikan jika sudah ada di tabel kelompok asli)
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];

$action = $_REQUEST['action'] ?? '';

// ==========================================================
// 1. GET DATA (Read-Only untuk Admin Desa)
// ==========================================================
if ($action === 'get_laporan_readonly') {
    $laporan_id = (int)($_GET['id'] ?? 0);

    if (!$laporan_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Laporan tidak valid.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            SELECT lk.*, p.nama_periode 
            FROM laporan_pjp_kelompok lk 
            JOIN periode p ON lk.periode_id = p.id 
            WHERE lk.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $laporan_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Data laporan tidak ditemukan.");
        }

        $row = $result->fetch_assoc();
        $nama_kelompok = $DATA_KELOMPOK[$row['kelompok_id']] ?? 'Unknown';

        // Parse data JSON
        $data_response = [
            'laporan_id' => $row['id'],
            'nama_periode' => $row['nama_periode'],
            'nama_kelompok' => $nama_kelompok,
            'status' => $row['status'],
            'checklist' => $row['checklist_musyawarah'] ? json_decode($row['checklist_musyawarah'], true) : ['pjp' => false, 'unsur' => false],
            'detail_kelas' => $row['detail_kelas'] ? json_decode($row['detail_kelas'], true) : [],
            'permasalahan' => $row['permasalahan'] ? json_decode($row['permasalahan'], true) : [],
            'kepengurusan' => $row['data_kepengurusan'] ? json_decode($row['data_kepengurusan'], true) : []
        ];

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data_response]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
