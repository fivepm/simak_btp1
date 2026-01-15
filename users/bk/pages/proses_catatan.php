<?php
// Mulai session karena file ini diakses langsung via Fetch, tidak lewat index.php utama
session_start();

// Set header JSON agar browser tahu ini respon data, bukan HTML
header('Content-Type: application/json; charset=utf-8');

// Include konfigurasi database & helper log
// (Sesuaikan path '../../' tergantung struktur folder Anda)
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../helpers/log_helper.php';

// Cek Auth Sederhana
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'bk') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak atau sesi habis.']);
    exit;
}

// Inisialisasi Variabel Session
$bk_id = $_SESSION['user_id'];
// Tingkat BK (Desa/Kelompok) untuk validasi keamanan tambahan jika perlu
$bk_tingkat = $_SESSION['user_tingkat'] ?? 'desa';

// Helper Function: Ambil Data Siswa untuk Log
function getSiswaLogData($koneksi, $id)
{
    $q = $koneksi->prepare("SELECT nama_lengkap, kelas, kelompok FROM peserta WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    return $q->get_result()->fetch_assoc();
}

try {
    // Pastikan Request adalah POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed.");
    }

    $action = $_POST['action'] ?? '';
    $peserta_id_post = $_POST['peserta_id'] ?? '';

    // Default Redirect URL
    $redirect_url = '?page=catatan';
    if ($peserta_id_post) {
        $redirect_url .= '&peserta_id=' . $peserta_id_post;
    }

    // --- 1. TAMBAH CATATAN ---
    if ($action === 'tambah_catatan') {
        $tgl = $_POST['tanggal_catatan'] ?? '';
        $masalah = $_POST['permasalahan'] ?? '';
        $tl = $_POST['tindak_lanjut'] ?? '';

        if (empty($peserta_id_post) || empty($tgl) || empty($masalah)) {
            throw new Exception("Data wajib (Siswa, Tanggal, Masalah) tidak lengkap.");
        }

        $stmt = $conn->prepare("INSERT INTO catatan_bk (peserta_id, tanggal_catatan, permasalahan, tindak_lanjut, dicatat_oleh_user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $peserta_id_post, $tgl, $masalah, $tl, $bk_id);

        if ($stmt->execute()) {
            $s_log = getSiswaLogData($conn, $peserta_id_post);
            if ($s_log) {
                $deskripsi_log = "Menambah *Catatan Peserta Didik* `" . $s_log['nama_lengkap'] . "` (*" . ucwords($s_log['kelompok']) . "* - *" . ucwords($s_log['kelas']) . "*)";
                writeLog('INSERT', $deskripsi_log);
            }
            echo json_encode(['status' => 'success', 'message' => 'Catatan berhasil ditambahkan.', 'redirect' => $redirect_url]);
        } else {
            throw new Exception("Gagal menyimpan ke database: " . $stmt->error);
        }
    }

    // --- 2. EDIT CATATAN ---
    elseif ($action === 'edit_catatan') {
        $id_catatan = $_POST['catatan_id'] ?? '';
        $tgl = $_POST['tanggal_catatan'] ?? '';
        $masalah = $_POST['permasalahan'] ?? '';
        $tl = $_POST['tindak_lanjut'] ?? '';

        if (empty($id_catatan) || empty($tgl) || empty($masalah)) {
            throw new Exception("Data wajib tidak lengkap.");
        }

        $stmt = $conn->prepare("UPDATE catatan_bk SET tanggal_catatan=?, permasalahan=?, tindak_lanjut=? WHERE id=?");
        $stmt->bind_param("sssi", $tgl, $masalah, $tl, $id_catatan);

        if ($stmt->execute()) {
            $s_log = getSiswaLogData($conn, $peserta_id_post);
            if ($s_log) {
                $deskripsi_log = "Memperbarui *Catatan Peserta Didik* `" . $s_log['nama_lengkap'] . "` (*" . ucwords($s_log['kelompok']) . "* - *" . ucwords($s_log['kelas']) . "*)";
                writeLog('UPDATE', $deskripsi_log);
            }
            echo json_encode(['status' => 'success', 'message' => 'Data diperbarui.', 'redirect' => $redirect_url]);
        } else {
            throw new Exception("Gagal update database.");
        }
    }

    // --- 3. HAPUS CATATAN ---
    elseif ($action === 'hapus_catatan') {
        $id_catatan = $_POST['hapus_id'] ?? '';

        if (empty($id_catatan)) {
            throw new Exception("ID Catatan tidak ditemukan.");
        }

        // Ambil info log dulu sebelum hapus
        $q_info = $conn->prepare("SELECT p.nama_lengkap, p.kelas, p.kelompok FROM catatan_bk cb JOIN peserta p ON cb.peserta_id = p.id WHERE cb.id = ?");
        $q_info->bind_param("i", $id_catatan);
        $q_info->execute();
        $s_log = $q_info->get_result()->fetch_assoc();

        $stmt = $conn->prepare("DELETE FROM catatan_bk WHERE id=?");
        $stmt->bind_param("i", $id_catatan);

        if ($stmt->execute()) {
            if ($s_log) {
                $deskripsi_log = "Menghapus *Catatan Peserta Didik* `" . $s_log['nama_lengkap'] . "` (*" . ucwords($s_log['kelompok']) . "* - *" . ucwords($s_log['kelas']) . "*)";
                writeLog('DELETE', $deskripsi_log);
            }
            echo json_encode(['status' => 'success', 'message' => 'Data dihapus.', 'redirect' => $redirect_url]);
        } else {
            throw new Exception("Gagal menghapus data.");
        }
    } else {
        throw new Exception("Aksi tidak valid.");
    }
} catch (Exception $e) {
    // Tangkap error dan kirim sebagai JSON
    http_response_code(500); // Internal Server Error status
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
