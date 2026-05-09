<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

// ===== GUARD: Hanya user yang sudah login =====
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

// ===== AMBIL KELOMPOK & KELAS DARI SESSION GURU =====
// Sesuaikan key session dengan yang dipakai di sistem Anda
$sess_kelompok = $_SESSION['user_kelompok'] ?? '';
$sess_kelas    = $_SESSION['user_kelas']    ?? '';

if (empty($sess_kelompok) || empty($sess_kelas)) {
    echo json_encode(['status' => 'error', 'message' => 'Data kelompok atau kelas guru tidak ditemukan di sesi. Silakan login ulang.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak valid.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {

    // =======================================================
    // 1. GET DETAIL MATERI (Untuk Dropdown di Modal Tambah)
    // =======================================================
    if ($action === 'get_detail_option') {
        $master_id = (int)($_POST['master_id'] ?? 0);

        $stmt = $conn->prepare(
            "SELECT id, judul_detail, total_isi 
             FROM master_materi_detail 
             WHERE master_materi_id = ? 
             ORDER BY id ASC"
        );
        $stmt->bind_param("i", $master_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // =======================================================
    // 2. SIMPAN TARGET PEMBELAJARAN (BARU)
    // =======================================================
    elseif ($action === 'simpan_target') {

        $periode_id      = (int)($_POST['periode_id'] ?? 0);
        $master_materi_id = (int)($_POST['master_materi_id'] ?? 0);
        $detail_materi_id = (int)($_POST['detail_materi_id'] ?? 0);
        $tipe_input      = $_POST['tipe_input_hidden'] ?? '';
        $satuan          = $_POST['satuan_hidden'] ?? '';

        // Kelompok & kelas wajib dari session (tidak bisa disuntik dari POST)
        $kelompok = $sess_kelompok;
        $kelas    = $sess_kelas;

        if (!$periode_id || !$master_materi_id || !$detail_materi_id) {
            throw new Exception("Data tidak lengkap. Pastikan periode dan materi sudah dipilih.");
        }

        // Ambil data master & detail materi
        $q_master = $conn->query("SELECT nama_kategori FROM master_materi WHERE id = $master_materi_id")->fetch_assoc();
        $q_detail = $conn->query("SELECT judul_detail, total_isi FROM master_materi_detail WHERE id = $detail_materi_id")->fetch_assoc();

        if (!$q_master) throw new Exception("Materi induk tidak ditemukan.");
        if (!$q_detail) throw new Exception("Detail materi tidak ditemukan.");

        $nama_mapel    = $q_master['nama_kategori'];
        $nama_detail   = $q_detail['judul_detail'];
        $batas_maksimal = (float)$q_detail['total_isi'];

        $target_start = 0;
        $target_end   = 0;
        $total_volume = 0;
        $judul_final  = $nama_mapel . " - " . $nama_detail;

        if ($tipe_input === 'RANGE') {
            $target_start = (float)($_POST['target_start'] ?? 0);
            $target_end   = (float)($_POST['target_end'] ?? 0);

            if ($target_end < $target_start) {
                throw new Exception("Nilai akhir tidak boleh lebih kecil dari nilai awal.");
            }
            if ($batas_maksimal > 0 && $target_end > $batas_maksimal) {
                throw new Exception("Target akhir melebihi batas maksimal materi ($batas_maksimal).");
            }

            $total_volume = ($target_end - $target_start) + 1;
            $judul_final .= " ($target_start - $target_end)";

        } elseif ($tipe_input === 'MANUAL') {
            $target_volume = (float)($_POST['target_volume'] ?? 0);

            if ($batas_maksimal > 0 && $target_volume > $batas_maksimal) {
                throw new Exception("Volume melebihi total isi materi ($batas_maksimal).");
            }

            $total_volume = $target_volume;
            $judul_final .= " (Target: $total_volume $satuan)";

        } elseif ($tipe_input === 'CHECKLIST') {
            $total_volume = 1;

        } else {
            throw new Exception("Tipe input tidak valid.");
        }

        $sql = "INSERT INTO target_pembelajaran 
                    (periode_id, kelompok, kelas, kategori, judul_materi, tipe_input, satuan, target_start, target_end, total_volume, master_materi_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssssdddi",
            $periode_id,
            $kelompok,
            $kelas,
            $nama_mapel,
            $nama_detail,
            $tipe_input,
            $satuan,
            $target_start,
            $target_end,
            $total_volume,
            $master_materi_id
        );

        if ($stmt->execute()) {
            writeLog('INSERT', "Guru mengatur target *$nama_mapel* ($nama_detail) untuk kelompok *$kelompok* - kelas *$kelas* pada periode ID $periode_id");
            echo json_encode(['status' => 'success', 'message' => 'Target berhasil disimpan.']);
        } else {
            throw new Exception("Gagal menyimpan: " . $stmt->error);
        }
    }

    // =======================================================
    // 3. EDIT TARGET
    // =======================================================
    elseif ($action === 'edit_target') {
        $id         = (int)($_POST['id'] ?? 0);
        $judul_baru = $_POST['judul_materi'] ?? '';
        $tipe_input = $_POST['tipe_input'] ?? '';

        if (!$id) throw new Exception("ID target tidak valid.");

        // Pastikan target ini memang milik kelompok & kelas guru yang sedang login
        $cek_stmt = $conn->prepare("SELECT id FROM target_pembelajaran WHERE id = ? AND kelompok = ? AND kelas = ?");
        $cek_stmt->bind_param("iss", $id, $sess_kelompok, $sess_kelas);
        $cek_stmt->execute();
        if ($cek_stmt->get_result()->num_rows === 0) {
            throw new Exception("Anda tidak memiliki akses untuk mengubah target ini.");
        }

        $target_start = 0;
        $target_end   = 0;
        $total_volume = 0;

        if ($tipe_input === 'RANGE') {
            $target_start = (float)($_POST['target_start'] ?? 0);
            $target_end   = (float)($_POST['target_end'] ?? 0);

            if ($target_end < $target_start) {
                throw new Exception("Nilai akhir tidak boleh lebih kecil dari nilai awal.");
            }
            $total_volume = ($target_end - $target_start) + 1;

        } elseif ($tipe_input === 'MANUAL') {
            $total_volume = (float)($_POST['target_volume'] ?? 0);

        } else {
            // CHECKLIST
            $total_volume = 1;
        }

        $stmt = $conn->prepare(
            "UPDATE target_pembelajaran 
             SET judul_materi = ?, target_start = ?, target_end = ?, total_volume = ? 
             WHERE id = ? AND kelompok = ? AND kelas = ?"
        );
        $stmt->bind_param("sdddiss", $judul_baru, $target_start, $target_end, $total_volume, $id, $sess_kelompok, $sess_kelas);

        if ($stmt->execute()) {
            writeLog('UPDATE', "Guru mengubah target probul ID $id ($sess_kelompok - $sess_kelas)");
            echo json_encode(['status' => 'success', 'message' => 'Target berhasil diperbarui.']);
        } else {
            throw new Exception("Gagal memperbarui: " . $stmt->error);
        }
    }

    // =======================================================
    // 4. HAPUS TARGET
    // =======================================================
    elseif ($action === 'hapus_target') {
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) throw new Exception("ID target tidak valid.");

        // Pastikan target milik kelompok & kelas guru yang login
        $cek = $conn->prepare("SELECT judul_materi, kelompok, kelas FROM target_pembelajaran WHERE id = ? AND kelompok = ? AND kelas = ?");
        $cek->bind_param("iss", $id, $sess_kelompok, $sess_kelas);
        $cek->execute();
        $cek_data = $cek->get_result()->fetch_assoc();

        if (!$cek_data) {
            throw new Exception("Anda tidak memiliki akses untuk menghapus target ini.");
        }

        $stmt = $conn->prepare("DELETE FROM target_pembelajaran WHERE id = ? AND kelompok = ? AND kelas = ?");
        $stmt->bind_param("iss", $id, $sess_kelompok, $sess_kelas);

        if ($stmt->execute()) {
            writeLog('DELETE', "Guru menghapus target *" . $cek_data['judul_materi'] . "* ({$cek_data['kelompok']} - {$cek_data['kelas']})");
            echo json_encode(['status' => 'success', 'message' => 'Target berhasil dihapus.']);
        } else {
            throw new Exception("Gagal menghapus: " . $stmt->error);
        }
    }

    else {
        throw new Exception("Action tidak valid.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}