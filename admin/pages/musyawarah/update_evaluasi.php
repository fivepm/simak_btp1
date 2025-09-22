<?php
// Pastikan path ke file koneksi ini benar
// Sesuaikan path jika direktori Anda berbeda
include '../../../config/config.php';

// Pastikan hanya request POST yang diproses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

// Atur header respons secara eksplisit ke JSON
header('Content-Type: application/json');
$response = [];

try {
    $id_poin = $_POST['id_poin'] ?? null;
    $status_evaluasi = $_POST['status_evaluasi'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $id_musyawarah = $_GET['id'] ?? null; // Ambil dari URL
    $valid_statuses = ['Terlaksana', 'Belum Terlaksana', 'Belum Dievaluasi'];

    if ($id_poin && $id_musyawarah && in_array($status_evaluasi, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE notulensi_poin SET status_evaluasi = ?, keterangan = ? WHERE id = ? AND id_musyawarah = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param("ssii", $status_evaluasi, $keterangan, $id_poin, $id_musyawarah);

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Evaluasi berhasil disimpan.'];
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception('Data yang dikirim tidak valid.');
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Cetak respons JSON dan hentikan script
echo json_encode($response);
$conn->close();
exit();
