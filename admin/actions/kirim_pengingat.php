<?php
// File ini dijalankan oleh Cron Job, jadi perlu path lengkap
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../helpers/wa_gateway.php';

// Ambil waktu saat ini dalam format Y-m-d H:i:s sesuai zona waktu Asia/Jakarta
$waktu_sekarang = date('Y-m-d H:i:s');

// Cari semua pesan yang statusnya 'pending' dan waktunya sudah tiba
// PERBAIKAN: Gunakan waktu dari PHP, bukan NOW() dari MySQL
$sql = "SELECT id, nomor_tujuan, isi_pesan FROM pesan_terjadwal WHERE status = 'pending' AND waktu_kirim <= ?";
$stmt_select = $conn->prepare($sql);
$stmt_select->bind_param("s", $waktu_sekarang);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result && $result->num_rows > 0) {
    while ($pesan = $result->fetch_assoc()) {
        $id_pesan = $pesan['id'];
        $nomor_tujuan = $pesan['nomor_tujuan'];
        $isi_pesan = $pesan['isi_pesan'];

        // Kirim pesan
        $berhasil = kirimWhatsApp($nomor_tujuan, $isi_pesan);

        // Update status pesan di database
        $status_baru = $berhasil ? 'terkirim' : 'gagal';
        $stmt_update = $conn->prepare("UPDATE pesan_terjadwal SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $status_baru, $id_pesan);
        $stmt_update->execute();
    }
    echo "Proses pengiriman selesai. " . $result->num_rows . " pesan diproses.";
} else {
    echo "Tidak ada pesan untuk dikirim saat ini.";
}

$conn->close();
