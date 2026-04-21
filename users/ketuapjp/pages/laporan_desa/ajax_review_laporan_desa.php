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

$action = $_REQUEST['action'] ?? '';
$DATA_KELOMPOK = [1 => 'bintaran', 2 => 'gedongkuning', 3 => 'jombor', 4 => 'sunten'];

// ==========================================================
// 1. GET DATA (Memuat Data Laporan Read-Only)
// ==========================================================
if ($action === 'get_laporan_review') {
    $periode_id = (int)($_GET['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    try {
        // Ambil Data Laporan Desa
        $stmt = $conn->prepare("
            SELECT ld.*, p.nama_periode 
            FROM laporan_pjp_desa ld 
            JOIN periode p ON ld.periode_id = p.id 
            WHERE ld.periode_id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $periode_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Laporan Desa belum dibuat oleh Admin Desa.");
        }

        $row = $result->fetch_assoc();

        // Ambil Data Detail Tiap Kelompok (Dari tabel laporan_pjp_kelompok, Tambahkan kolom status)
        $q_kel = $conn->prepare("SELECT kelompok_id, status, checklist_musyawarah, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ?");
        $q_kel->bind_param("i", $periode_id);
        $q_kel->execute();
        $res_kel = $q_kel->get_result();

        $raw_kelompok_data = [];
        while ($rk = $res_kel->fetch_assoc()) {
            $raw_kelompok_data[] = [
                'nama_kelompok' => $DATA_KELOMPOK[$rk['kelompok_id']] ?? 'Unknown',
                'status' => $rk['status'], // Mengambil status laporan kelompok
                'checklist' => json_decode($rk['checklist_musyawarah'], true) ?: ['pjp' => false, 'unsur' => false],
                'detail_kelas' => json_decode($rk['detail_kelas'], true) ?: [],
                'permasalahan' => json_decode($rk['permasalahan'], true) ?: []
            ];
        }
        $q_kel->close();

        $data_response = [
            'laporan_desa_id' => $row['id'],
            'nama_periode' => $row['nama_periode'],
            'status' => $row['status'],
            'ttd_at' => $row['ttd_at'] ? date('d M Y H:i', strtotime($row['ttd_at'])) : null,
            'kepengurusan' => json_decode($row['data_kepengurusan_desa'], true) ?: [],
            'rekap_kelompok' => json_decode($row['rekap_kelompok'], true) ?: [],
            'detail_tiap_kelompok' => $raw_kelompok_data
        ];

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data_response]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. POST DATA (Proses Tanda Tangan Ketua Desa)
// ==========================================================
elseif ($action === 'tanda_tangan') {
    $laporan_desa_id = (int)($_POST['laporan_desa_id'] ?? 0);
    $pin_input = $_POST['pin'] ?? '';
    $user_id = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
        if (empty($pin_input)) {
            throw new Exception("PIN tidak boleh kosong.");
        }

        // Verifikasi PIN Pengguna
        $q_user = $conn->prepare("SELECT pin FROM users WHERE id = ?");
        $q_user->bind_param("i", $user_id);
        $q_user->execute();
        $res_user = $q_user->get_result();

        if ($res_user->num_rows === 0) {
            throw new Exception("Data pengguna tidak ditemukan.");
        }

        $user_data = $res_user->fetch_assoc();
        $pin_db = $user_data['pin'];

        // Cek PIN (Mendukung hash bcrypt maupun plain text untuk fallback)
        if (!password_verify($pin_input, $pin_db) && $pin_input !== $pin_db) {
            throw new Exception("PIN yang Anda masukkan salah.");
        }

        $cek = $conn->prepare("SELECT status FROM laporan_pjp_desa WHERE id = ?");
        $cek->bind_param("i", $laporan_desa_id);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) throw new Exception("Laporan tidak valid.");

        $status_saat_ini = $res_cek->fetch_assoc()['status'];
        if ($status_saat_ini === 'TTD_KETUA') throw new Exception("Laporan sudah ditandatangani sebelumnya.");
        if ($status_saat_ini === 'DRAFT') throw new Exception("Laporan masih DRAFT dan belum difinalisasi oleh Admin Desa.");

        $stmt_update = $conn->prepare("UPDATE laporan_pjp_desa SET status = 'TTD_KETUA', ttd_at = NOW() WHERE id = ?");
        $stmt_update->bind_param("i", $laporan_desa_id);
        $stmt_update->execute();

        $conn->commit();
        writeLog('UPDATE', "Ketua PJP Desa mengesahkan Laporan Desa (ID: $laporan_desa_id)");

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Laporan Tingkat Desa berhasil disahkan.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 3. POST DATA (Tolak Laporan / Revisi Desa)
// ==========================================================
elseif ($action === 'tolak_laporan') {
    $laporan_desa_id = (int)($_POST['laporan_desa_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $cek = $conn->prepare("SELECT status FROM laporan_pjp_desa WHERE id = ?");
        $cek->bind_param("i", $laporan_desa_id);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) throw new Exception("Laporan tidak valid.");

        $status_saat_ini = $res_cek->fetch_assoc()['status'];
        if ($status_saat_ini === 'TTD_KETUA') throw new Exception("Laporan sudah terlanjur disahkan.");
        if ($status_saat_ini === 'DRAFT') throw new Exception("Laporan sudah berstatus DRAFT.");

        $stmt_update = $conn->prepare("UPDATE laporan_pjp_desa SET status = 'DRAFT', ttd_at = NULL WHERE id = ?");
        $stmt_update->bind_param("i", $laporan_desa_id);
        $stmt_update->execute();

        $conn->commit();
        writeLog('UPDATE', "Ketua PJP Desa menolak laporan Desa (ID: $laporan_desa_id) untuk direvisi oleh Admin");

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Laporan dikembalikan ke Admin Desa untuk direvisi.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
