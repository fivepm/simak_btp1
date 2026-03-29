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

$action = $_REQUEST['action'] ?? '';

// Definisi Data Manual
$DATA_KELOMPOK = [
    1 => 'Bintaran',
    2 => 'Gedongkuning',
    3 => 'Jombor',
    4 => 'Sunten'
];

// ==========================================================
// 1. GET DATA (Render Detail Periode & Progres Kelompok)
// ==========================================================
if ($action === 'get_detail') {
    $periode_id = (int)($_GET['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak ditemukan.']);
        exit;
    }

    try {
        // Ambil info periode
        $q_periode = $conn->prepare("SELECT id, nama_periode, DATE_FORMAT(tanggal_selesai, '%d %b %Y') as tgl_akhir FROM periode WHERE id = ?");
        $q_periode->bind_param("i", $periode_id);
        $q_periode->execute();
        $res_periode = $q_periode->get_result();

        if ($res_periode->num_rows === 0) {
            throw new Exception("Data periode tidak ditemukan.");
        }
        $info_periode = $res_periode->fetch_assoc();
        $q_periode->close();

        // Ambil data laporan kelompok
        $q_kelompok = $conn->prepare("SELECT id, kelompok_id, status, updated_at, ttd_at FROM laporan_pjp_kelompok WHERE periode_id = ? ORDER BY kelompok_id ASC");
        $q_kelompok->bind_param("i", $periode_id);
        $q_kelompok->execute();
        $res_kelompok = $q_kelompok->get_result();

        $laporan_kelompok = [];
        $semua_selesai = true;

        while ($row = $res_kelompok->fetch_assoc()) {
            $row['nama_kelompok'] = $DATA_KELOMPOK[$row['kelompok_id']] ?? 'Unknown';
            $row['tgl_update'] = date('d/m/Y H:i', strtotime($row['updated_at']));

            if ($row['status'] !== 'TTD_KETUA') {
                $semua_selesai = false;
            }
            $laporan_kelompok[] = $row;
        }
        $q_kelompok->close();

        // Cek status laporan desa
        $q_desa = $conn->prepare("SELECT status FROM laporan_pjp_desa WHERE periode_id = ? LIMIT 1");
        $q_desa->bind_param("i", $periode_id);
        $q_desa->execute();
        $res_desa = $q_desa->get_result();
        $status_desa = ($res_desa->num_rows > 0) ? $res_desa->fetch_assoc()['status'] : null;
        $q_desa->close();

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'data' => [
                'periode' => $info_periode,
                'laporan_kelompok' => $laporan_kelompok,
                'semua_kelompok_selesai' => $semua_selesai,
                'status_laporan_desa' => $status_desa
            ]
        ]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. KEMBALIKAN KE DRAFT (Tolak Laporan)
// ==========================================================
if ($action === 'tolak_laporan') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = 'DRAFT', ttd_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $laporan_id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Gagal mengupdate atau laporan tidak ditemukan.");
        }

        $conn->commit();
        writeLog('UPDATE', "Admin Desa menolak/mengembalikan laporan kelompok ID: $laporan_id menjadi DRAFT");

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Laporan berhasil dikembalikan ke status DRAFT.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
