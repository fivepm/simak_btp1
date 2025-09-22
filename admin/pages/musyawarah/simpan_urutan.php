<?php
// Pastikan path ke file koneksi ini benar
include '../../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

// Ambil data JSON yang dikirim dari JavaScript
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

$id_musyawarah = $data->id_musyawarah ?? null;
$urutan_ids = $data->urutan_ids ?? null;

if (!$id_musyawarah || !is_array($urutan_ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap atau format salah.']);
    exit();
}

// Mulai transaksi database untuk memastikan semua update berhasil atau tidak sama sekali
$conn->begin_transaction();

try {
    // Siapkan statement di luar loop untuk efisiensi
    $stmt = $conn->prepare("UPDATE kehadiran_musyawarah SET urutan = ? WHERE id = ? AND id_musyawarah = ?");

    foreach ($urutan_ids as $index => $id_peserta) {
        $urutan = $index; // Urutan dimulai dari 0
        $id_peserta = intval($id_peserta); // Pastikan integer

        $stmt->bind_param("iii", $urutan, $id_peserta, $id_musyawarah);
        if (!$stmt->execute()) {
            // Jika satu saja gagal, batalkan semua
            throw new Exception('Gagal mengupdate urutan untuk peserta ID ' . $id_peserta);
        }
    }

    // Jika semua berhasil, simpan perubahan
    $conn->commit();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Urutan berhasil disimpan.']);
} catch (Exception $e) {
    // Jika terjadi error, batalkan semua perubahan yang sudah terjadi
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
exit();
