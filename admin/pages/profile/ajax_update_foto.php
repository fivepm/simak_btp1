<?php
// Selalu mulai session di file AJAX
session_start();

// --- PENTING: Sertakan Koneksi Database Anda ---
// Sesuaikan path ini dengan lokasi file koneksi $conn Anda.
// Asumsi ini adalah path dari root proyek Anda.
require_once '../../../config/config.php'; // Contoh path, mohon disesuaikan

// --- 1. Validasi Keamanan & Inisialisasi ---
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['user_id']) || !isset($conn)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak atau koneksi DB gagal.']);
    exit;
}

// --- 2. Inisialisasi Variabel ---
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';
$tableName = ($userRole == 'guru') ? 'guru' : 'users';

// Path fisik untuk menyimpan file (menggunakan __DIR__ untuk path absolut)
// Ini mengasumsikan folder 'assets' ada di root (dua level di atas folder 'profile')
$target_dir_physic = dirname(__DIR__, 3) . "/uploads/profiles/";

// Path URL relatif untuk dikirim kembali ke browser
$base_url_path = "../uploads/profiles/";

$pesan_error = '';
$pesan_sukses = '';
$upload_error = false;
$new_image_url = '';


// --- 3. Logika Upload File ---
if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['foto_profil'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];

    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
        $fileExt = 'png'; // Default ke png jika ekstensi tidak valid
    }
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($fileExt, $allowed)) {
        if ($fileSize < 5000000) { // Max 5MB

            // Pastikan direktori fisik ada
            if (!is_dir($target_dir_physic)) {
                mkdir($target_dir_physic, 0755, true);
            }

            // Ambil nama foto lama untuk dihapus
            $sql_foto = "SELECT foto_profil FROM $tableName WHERE id = ?";
            $stmt_foto = mysqli_prepare($conn, $sql_foto);
            mysqli_stmt_bind_param($stmt_foto, "i", $userId);
            mysqli_stmt_execute($stmt_foto);
            $result_foto = mysqli_stmt_get_result($stmt_foto);
            $row_foto = mysqli_fetch_assoc($result_foto);
            $old_foto = $row_foto['foto_profil'] ?? null;
            mysqli_stmt_close($stmt_foto);

            // Buat nama file baru yang unik
            $namaFileBaru = 'user_' . $userId . '_' . time() . '.' . $fileExt;
            $fileDestination = $target_dir_physic . $namaFileBaru; // Path fisik lengkap

            // Pindahkan file
            if (move_uploaded_file($fileTmpName, $fileDestination)) {

                // Update database
                $sql_upd_foto = "UPDATE $tableName SET foto_profil = ? WHERE id = ?";
                $stmt_upd_foto = mysqli_prepare($conn, $sql_upd_foto);
                mysqli_stmt_bind_param($stmt_upd_foto, "si", $namaFileBaru, $userId);

                if (mysqli_stmt_execute($stmt_upd_foto)) {
                    // Hapus file foto lama jika bukan default
                    if ($old_foto && $old_foto != 'default.png' && file_exists($target_dir_physic . $old_foto)) {
                        unlink($target_dir_physic . $old_foto);
                    }

                    $_SESSION['foto_profil'] = $namaFileBaru; // Update session
                    $pesan_sukses = "Foto profil berhasil diperbarui.";
                    $new_image_url = $base_url_path . $namaFileBaru; // Path URL
                } else {
                    $pesan_error = "Gagal update database: " . mysqli_error($conn);
                    $upload_error = true;
                }
                mysqli_stmt_close($stmt_upd_foto);
            } else {
                $pesan_error = "Gagal memindahkan file yang diupload. Cek izin folder: " . $target_dir_physic;
                $upload_error = true;
            }
        } else {
            $pesan_error = "Ukuran file terlalu besar (Max 5MB).";
            $upload_error = true;
        }
    } else {
        $pesan_error = "Format file tidak diizinkan.";
        $upload_error = true;
    }
} else {
    $pesan_error = "Tidak ada file yang diupload atau terjadi kesalahan: " . ($_FILES['foto_profil']['error'] ?? 'Unknown Error');
    $upload_error = true;
}

// --- 4. Kirim Respon JSON ---
header('Content-Type: application/json');
if ($upload_error) {
    echo json_encode([
        'success' => false,
        'message' => $pesan_error
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => $pesan_sukses,
        'newImageUrl' => $new_image_url . '?t=' . time() // Tambahkan cache-buster
    ]);
}
exit; // Wajib di-exit