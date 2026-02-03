<?php
session_start();
require_once '../../../config/config.php';
// Tambahkan helper log
require_once '../../../helpers/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

// Helper untuk ambil nama induk mapel (untuk log)
function getNamaMapel($conn, $id)
{
    $q = $conn->query("SELECT nama_kategori FROM master_materi WHERE id = $id");
    $r = $q->fetch_assoc();
    return $r['nama_kategori'] ?? '-';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // --- TAMBAH DETAIL ---
        if ($action === 'tambah') {
            $master_id = $_POST['master_materi_id'];
            $judul = $_POST['judul_detail'];
            $total = $_POST['total_isi'] ?? 0;
            $ket = $_POST['keterangan'] ?? '';

            if (empty($master_id) || empty($judul)) throw new Exception("Data wajib tidak lengkap.");

            $stmt = $conn->prepare("INSERT INTO master_materi_detail (master_materi_id, judul_detail, total_isi, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $master_id, $judul, $total, $ket);

            if ($stmt->execute()) {
                // LOG
                $nama_induk = getNamaMapel($conn, $master_id);
                $log_desc = "Menambahkan item materi `" . $judul . "` ke dalam materi induk *" . $nama_induk . "*";
                writeLog('INSERT', $log_desc);

                echo json_encode(['status' => 'success', 'message' => 'Detail materi berhasil ditambahkan.']);
            } else {
                throw new Exception("Gagal simpan: " . $stmt->error);
            }
        }

        // --- EDIT DETAIL ---
        elseif ($action === 'edit') {
            $id = $_POST['id'];
            $master_id = $_POST['master_materi_id'];
            $judul = $_POST['judul_detail'];
            $total = $_POST['total_isi'];
            $ket = $_POST['keterangan'];

            $stmt = $conn->prepare("UPDATE master_materi_detail SET master_materi_id=?, judul_detail=?, total_isi=?, keterangan=? WHERE id=?");
            $stmt->bind_param("isisi", $master_id, $judul, $total, $ket, $id);

            if ($stmt->execute()) {
                // LOG
                $nama_induk = getNamaMapel($conn, $master_id);
                $log_desc = "Memperbarui item materi `" . $judul . "` pada materi induk *" . $nama_induk . "*";
                writeLog('UPDATE', $log_desc);

                echo json_encode(['status' => 'success', 'message' => 'Data berhasil diperbarui.']);
            } else {
                throw new Exception("Gagal update.");
            }
        }

        // --- HAPUS DETAIL ---
        elseif ($action === 'hapus') {
            $id = $_POST['id'];

            // Ambil data sebelum hapus untuk log
            $q_cek = $conn->query("SELECT d.judul_detail, m.nama_kategori 
                                   FROM master_materi_detail d 
                                   JOIN master_materi m ON d.master_materi_id = m.id 
                                   WHERE d.id = $id");
            $data_lama = $q_cek->fetch_assoc();
            $judul_hapus = $data_lama['judul_detail'] ?? 'Item ID ' . $id;
            $induk_hapus = $data_lama['nama_kategori'] ?? '-';

            $stmt = $conn->prepare("DELETE FROM master_materi_detail WHERE id=?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                // LOG
                $log_desc = "Menghapus item materi `" . $judul_hapus . "` dari materi induk *" . $induk_hapus . "*";
                writeLog('DELETE', $log_desc);

                echo json_encode(['status' => 'success', 'message' => 'Data dihapus.']);
            } else {
                throw new Exception("Gagal hapus.");
            }
        } else {
            throw new Exception("Aksi tidak valid.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
}
