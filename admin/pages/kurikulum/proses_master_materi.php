<?php
session_start();
require_once '../../../config/config.php';
// Tambahkan ini agar fungsi writeLog bisa dipakai
require_once '../../../helpers/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // --- TAMBAH DATA ---
        if ($action === 'tambah') {
            $nama = $_POST['nama_kategori'] ?? '';
            $tipe = $_POST['tipe_input'] ?? 'MANUAL';
            $satuan = $_POST['satuan_default'] ?? '';

            if (empty($nama)) throw new Exception("Nama mata pelajaran wajib diisi.");

            $stmt = $conn->prepare("INSERT INTO master_materi (nama_kategori, tipe_input, satuan_default) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nama, $tipe, $satuan);

            if ($stmt->execute()) {
                // LOG AKTIVITAS
                $log_desc = "Menambahkan *Materi Induk* baru `" . $nama . "` (Tipe: " . $tipe . ")";
                writeLog('INSERT', $log_desc);

                echo json_encode(['status' => 'success', 'message' => 'Mata pelajaran berhasil ditambahkan.']);
            } else {
                throw new Exception("Gagal simpan: " . $stmt->error);
            }
        }

        // --- EDIT DATA ---
        elseif ($action === 'edit') {
            $id = $_POST['id'] ?? 0;
            $nama = $_POST['nama_kategori'];
            $tipe = $_POST['tipe_input'];
            $satuan = $_POST['satuan_default'];

            if (empty($id)) throw new Exception("ID tidak ditemukan.");

            $stmt = $conn->prepare("UPDATE master_materi SET nama_kategori=?, tipe_input=?, satuan_default=? WHERE id=?");
            $stmt->bind_param("sssi", $nama, $tipe, $satuan, $id);

            if ($stmt->execute()) {
                // LOG AKTIVITAS
                $log_desc = "Memperbarui data *Materi Induk* `" . $nama . "`";
                writeLog('UPDATE', $log_desc);

                echo json_encode(['status' => 'success', 'message' => 'Data berhasil diperbarui.']);
            } else {
                throw new Exception("Gagal update database.");
            }
        }

        // --- HAPUS DATA ---
        elseif ($action === 'hapus') {
            $id = $_POST['id'] ?? 0;
            if (empty($id)) throw new Exception("ID tidak ditemukan.");

            // Ambil nama dulu sebelum dihapus untuk keperluan Log
            $q_cek = $conn->query("SELECT nama_kategori FROM master_materi WHERE id = $id");
            $data_lama = $q_cek->fetch_assoc();
            $nama_terhapus = $data_lama['nama_kategori'] ?? 'ID ' . $id;

            $stmt = $conn->prepare("DELETE FROM master_materi WHERE id=?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                // LOG AKTIVITAS
                $log_desc = "Menghapus *Materi Induk* `" . $nama_terhapus . "` dari bank data";
                writeLog('DELETE', $log_desc);

                echo json_encode(['status' => 'success', 'message' => 'Data dihapus.']);
            } else {
                throw new Exception("Gagal hapus data.");
            }
        } else {
            throw new Exception("Aksi tidak valid.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
