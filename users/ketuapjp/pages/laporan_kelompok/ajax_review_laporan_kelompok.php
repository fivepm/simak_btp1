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

// Ambil nama kelompok ketua dari session
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];
$kelompok_id_login = array_search($admin_kelompok, $DATA_KELOMPOK);

if (!$kelompok_id_login) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Data kelompok Anda tidak valid atau tidak ditemukan.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

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
        $stmt = $conn->prepare("
            SELECT lk.*, p.nama_periode 
            FROM laporan_pjp_kelompok lk 
            JOIN periode p ON lk.periode_id = p.id 
            WHERE lk.periode_id = ? AND lk.kelompok_id = ? LIMIT 1
        ");
        $stmt->bind_param("ii", $periode_id, $kelompok_id_login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Data laporan tidak ditemukan atau Anda tidak memiliki akses.");
        }

        $row = $result->fetch_assoc();

        $data_response = [
            'laporan_id' => $row['id'],
            'nama_periode' => $row['nama_periode'],
            'status' => $row['status'],
            'ttd_at' => $row['ttd_at'] ? date('d M Y H:i', strtotime($row['ttd_at'])) : null,
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

// ==========================================================
// 2. POST DATA (Proses Tanda Tangan Ketua)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'tanda_tangan') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);
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

        // Pastikan laporan valid, milik kelompok ini, dan statusnya FINAL
        $cek = $conn->prepare("SELECT status FROM laporan_pjp_kelompok WHERE id = ? AND kelompok_id = ?");
        $cek->bind_param("ii", $laporan_id, $kelompok_id_login);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) {
            throw new Exception("Laporan tidak valid atau Anda tidak memiliki akses.");
        }

        $status_saat_ini = $res_cek->fetch_assoc()['status'];
        if ($status_saat_ini === 'TTD_KETUA') {
            throw new Exception("Laporan sudah ditandatangani sebelumnya.");
        } elseif ($status_saat_ini === 'DRAFT') {
            throw new Exception("Laporan masih DRAFT dan belum difinalisasi oleh Admin.");
        }

        // Lakukan Update Status dan Waktu TTD
        $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = 'TTD_KETUA', ttd_at = NOW() WHERE id = ?");
        $stmt_update->bind_param("i", $laporan_id);
        $stmt_update->execute();

        $conn->commit();
        writeLog('UPDATE', "Ketua Kelompok $kelompok_id_login menandatangani Laporan PJP (ID: $laporan_id)");

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Laporan berhasil ditandatangani dan disahkan.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 3. POST DATA (Tolak Laporan / Kembalikan ke Draft)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'tolak_laporan') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);

    $conn->begin_transaction();
    try {
        // Cek validasi kepemilikan dan status
        $cek = $conn->prepare("SELECT status FROM laporan_pjp_kelompok WHERE id = ? AND kelompok_id = ?");
        $cek->bind_param("ii", $laporan_id, $kelompok_id_login);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) {
            throw new Exception("Laporan tidak valid atau Anda tidak memiliki akses.");
        }

        $status_saat_ini = $res_cek->fetch_assoc()['status'];
        if ($status_saat_ini === 'TTD_KETUA') {
            throw new Exception("Laporan sudah terlanjur disahkan dan tidak bisa dibatalkan.");
        } elseif ($status_saat_ini === 'DRAFT') {
            throw new Exception("Laporan sudah berstatus DRAFT.");
        }

        // Lakukan Update Status kembali ke DRAFT
        $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = 'DRAFT' WHERE id = ?");
        $stmt_update->bind_param("i", $laporan_id);
        $stmt_update->execute();

        $conn->commit();
        writeLog('UPDATE', "Ketua Kelompok $kelompok_id_login menolak laporan PJP (ID: $laporan_id) untuk direvisi");

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Laporan telah dikembalikan ke Admin Kelompok untuk direvisi.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
