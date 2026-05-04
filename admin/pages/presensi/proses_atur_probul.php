<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // =======================================================
        // 1. GET DETAIL MATERI (Untuk Dropdown di Modal Tambah)
        // =======================================================
        if ($action === 'get_detail_option') {
            $master_id = $_POST['master_id'] ?? 0;

            $stmt = $conn->prepare("SELECT id, judul_detail, total_isi FROM master_materi_detail WHERE master_materi_id = ? ORDER BY id ASC");
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
            // ... (Logika simpan sama seperti sebelumnya) ...
            $periode_id = $_POST['periode_id'];
            $kelompok = $_POST['kelompok'];
            $kelas = $_POST['kelas'];
            $master_materi_id = $_POST['master_materi_id'];
            $detail_materi_id = $_POST['detail_materi_id'];
            $tipe_input = $_POST['tipe_input_hidden'];
            $satuan = $_POST['satuan_hidden'];

            $target_start = 0;
            $target_end = 0;
            $total_volume = 0;

            $q_master = $conn->query("SELECT nama_kategori FROM master_materi WHERE id = $master_materi_id")->fetch_assoc();
            $q_detail = $conn->query("SELECT judul_detail, total_isi FROM master_materi_detail WHERE id = $detail_materi_id")->fetch_assoc();

            if (!$q_detail) throw new Exception("Detail materi tidak ditemukan.");

            $nama_mapel = $q_master['nama_kategori'];
            $nama_detail = $q_detail['judul_detail'];
            $batas_maksimal = (float)$q_detail['total_isi'];

            $judul_final = $nama_mapel . " - " . $nama_detail;

            if ($tipe_input === 'RANGE') {
                $target_start = (float)$_POST['target_start'];
                $target_end = (float)$_POST['target_end'];

                if ($target_end < $target_start) throw new Exception("Nilai akhir tidak boleh lebih kecil dari nilai awal.");
                if ($batas_maksimal > 0 && $target_end > $batas_maksimal) throw new Exception("Target akhir melebihi batas maksimal materi ($batas_maksimal).");

                $total_volume = ($target_end - $target_start) + 1;
                $judul_final .= " ($target_start - $target_end)";
            } elseif ($tipe_input === 'MANUAL') {
                $target_volume = (float)$_POST['target_volume'];
                if ($batas_maksimal > 0 && $target_volume > $batas_maksimal) throw new Exception("Volume melebihi total isi materi ($batas_maksimal).");
                $total_volume = $target_volume;
                $judul_final .= " (Target: $total_volume $satuan)";
            } elseif ($tipe_input === 'CHECKLIST') {
                $total_volume = 1;
            }

            $sql = "INSERT INTO target_pembelajaran 
                    (periode_id, kelompok, kelas, kategori, judul_materi, tipe_input, satuan, target_start, target_end, total_volume, master_materi_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            // Perhatikan tipe data binding: d untuk decimal
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
                writeLog('INSERT', "Mengatur target *$nama_mapel* ($nama_detail) untuk kelompok *$kelompok* - kelas *$kelas* pada periode : ID $periode_id");
                echo json_encode(['status' => 'success', 'message' => 'Target berhasil disimpan.']);
            } else {
                throw new Exception("Gagal menyimpan: " . $stmt->error);
            }
        }

        // =======================================================
        // 3. EDIT TARGET (FITUR BARU)
        // =======================================================
        elseif ($action === 'edit_target') {
            $id = $_POST['id'];
            $judul_baru = $_POST['judul_materi'];
            $tipe_input = $_POST['tipe_input']; // RANGE / MANUAL / CHECKLIST

            $target_start = 0;
            $target_end = 0;
            $total_volume = 0;

            if ($tipe_input === 'RANGE') {
                $target_start = (float)$_POST['target_start'];
                $target_end = (float)$_POST['target_end'];

                if ($target_end < $target_start) throw new Exception("Nilai akhir tidak boleh lebih kecil dari awal.");

                $total_volume = ($target_end - $target_start) + 1;
            } elseif ($tipe_input === 'MANUAL') {
                $total_volume = (float)$_POST['target_volume'];
            } else {
                $total_volume = 1;
            }

            $stmt = $conn->prepare("UPDATE target_pembelajaran SET judul_materi=?, target_start=?, target_end=?, total_volume=? WHERE id=?");
            $stmt->bind_param("sdddi", $judul_baru, $target_start, $target_end, $total_volume, $id);

            if ($stmt->execute()) {
                writeLog('UPDATE', "Mengubah target probul ID $id");
                echo json_encode(['status' => 'success', 'message' => 'Target berhasil diperbarui.']);
            } else {
                throw new Exception("Gagal update database.");
            }
        }

        // =======================================================
        // 4. HAPUS TARGET
        // =======================================================
        elseif ($action === 'hapus_target') {
            $id = $_POST['id'];
            $cek = $conn->query("SELECT judul_materi, kelompok, kelas FROM target_pembelajaran WHERE id = $id")->fetch_assoc();

            $stmt = $conn->prepare("DELETE FROM target_pembelajaran WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                if ($cek) writeLog('DELETE', "Menghapus target *" . $cek['judul_materi'] . "* ($cek[kelompok] - $cek[kelas])");
                echo json_encode(['status' => 'success', 'message' => 'Target berhasil dihapus.']);
            } else {
                throw new Exception("Gagal menghapus.");
            }
        } else {
            throw new Exception("Action tidak valid.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
