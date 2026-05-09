<?php
// ajax_pengaturan.php

// Sesuaikan path ini dengan letak file koneksi config.php milikmu
// (Sama seperti di file kelola bk)
require_once '../../../config/config.php'; 

// Set header agar output dibaca sebagai JSON oleh browser
header('Content-Type: application/json');

// Tangkap parameter 'action' 
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        // ==========================================
        // --- BAGIAN KELAS ---
        // ==========================================
        case 'get_kelas':
            $sql = "SELECT * FROM kelas ORDER BY id ASC";
            $result = $conn->query($sql);
            
            $data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'add_kelas':
            $nama_kelas = trim($_POST['nama_kelas'] ?? '');
            if (empty($nama_kelas)) {
                echo json_encode(['status' => 'error', 'message' => 'Nama kelas tidak boleh kosong!']);
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas) VALUES (?)");
            $stmt->bind_param("s", $nama_kelas);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Kelas berhasil ditambahkan!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan kelas.']);
            }
            $stmt->close();
            break;

        case 'delete_kelas':
            $id = $_POST['id'] ?? 0;
            if (empty($id)) {
                echo json_encode(['status' => 'error', 'message' => 'ID kelas tidak valid!']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Kelas berhasil dihapus!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus kelas.']);
            }
            $stmt->close();
            break;


        // ==========================================
        // --- BAGIAN KELOMPOK ---
        // ==========================================
        case 'get_kelompok':
            $sql = "SELECT * FROM kelompok ORDER BY id ASC";
            $result = $conn->query($sql);
            
            $data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'add_kelompok':
            $nama_kelompok = trim($_POST['nama_kelompok'] ?? '');
            if (empty($nama_kelompok)) {
                echo json_encode(['status' => 'error', 'message' => 'Nama kelompok tidak boleh kosong!']);
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO kelompok (nama_kelompok) VALUES (?)");
            $stmt->bind_param("s", $nama_kelompok);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Kelompok berhasil ditambahkan!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan kelompok.']);
            }
            $stmt->close();
            break;

        case 'delete_kelompok':
            $id = $_POST['id'] ?? 0;
            if (empty($id)) {
                echo json_encode(['status' => 'error', 'message' => 'ID kelompok tidak valid!']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM kelompok WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Kelompok berhasil dihapus!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus kelompok.']);
            }
            $stmt->close();
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid!']);
            break;
    }
} catch (Exception $e) {
    // Tangkap error jika terjadi masalah pada koneksi/query
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
?>