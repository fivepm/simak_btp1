<?php
// Pastikan path ke file koneksi ini benar dari lokasi file ini
include '../../../config/config.php'; // Sesuaikan path jika perlu


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
    $id_kehadiran = $_POST['id_kehadiran'] ?? null;
    $status_baru = $_POST['status'] ?? '';
    $id_musyawarah = $_GET['id'] ?? null;
    $valid_statuses = ['Hadir', 'Izin', 'Tanpa Keterangan'];

    if ($id_kehadiran && $id_musyawarah && in_array($status_baru, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE kehadiran_musyawarah SET status = ? WHERE id = ? AND id_musyawarah = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param("sii", $status_baru, $id_kehadiran, $id_musyawarah);

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Status berhasil diperbarui.'];
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception('Data tidak valid.');
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Cetak respons JSON dan hentikan script
echo json_encode($response);
$conn->close();
exit();
