<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

header('Content-Type: application/json');

// --- 1. Validasi Login ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// --- 2. Inisialisasi ---
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'guru';
$tableName = ($userRole === 'guru') ? 'guru' : 'users';
$target_dir = "../../../uploads/profiles/";

// Pastikan folder ada
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// --- 3. Proses Upload ---
if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {

    // Validasi Tipe File
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $file_type = mime_content_type($_FILES['foto_profil']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak valid (Hanya JPG/PNG/WEBP).']);
        exit;
    }

    // Buat Nama File Unik
    // Format: profile_ID_TIMESTAMP.png
    $extension = 'png'; // Output dari cropper.js biasanya png atau jpeg
    $new_filename = "profile_" . $userId . "_" . time() . "." . $extension;
    $target_file = $target_dir . $new_filename;

    // Hapus Foto Lama (Opsional tapi disarankan agar hemat storage)
    $stmt_old = $conn->prepare("SELECT foto_profil FROM $tableName WHERE id = ?");
    $stmt_old->bind_param("i", $userId);
    $stmt_old->execute();
    $res_old = $stmt_old->get_result();
    if ($row_old = $res_old->fetch_assoc()) {
        $old_file = $target_dir . $row_old['foto_profil'];
        if ($row_old['foto_profil'] !== 'default.png' && file_exists($old_file)) {
            unlink($old_file);
        }
    }
    $stmt_old->close();

    // Pindahkan File Baru
    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {

        // Update Database
        $stmt = $conn->prepare("UPDATE $tableName SET foto_profil = ? WHERE id = ?");
        $stmt->bind_param("si", $new_filename, $userId);

        if ($stmt->execute()) {
            $_SESSION['foto_profil'] = $new_filename; // Update session

            echo json_encode([
                'success' => true,
                'message' => 'Foto berhasil diperbarui.',
                'newImageUrl' => $target_file . '?v=' . time() // Tambah timestamp agar cache browser refresh
            ]);

            // Log Activity
            writeLog('UPDATE', "Pengguna memperbarui foto profil.");
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update database.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke server.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diunggah.']);
}
