<?php
// File ini dijalankan oleh Cron Job, jadi perlu path lengkap
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../helpers/fonnte_helper.php';

// Kunci rahasia untuk keamanan, pastikan ini sangat unik dan sulit ditebak
$kunci_rahasia = "GantiDenganKunciSuperRahasiaAnda12345";

if (!isset($_GET['secret']) || $_GET['secret'] !== $kunci_rahasia) {
    http_response_code(403);
    die("Akses ditolak.");
}

// Cari semua pesan yang statusnya 'pending' dan waktunya sudah tiba
$sql = "SELECT id, nomor_tujuan, isi_pesan FROM pesan_terjadwal WHERE status = 'pending' AND waktu_kirim <= NOW()";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($pesan = $result->fetch_assoc()) {
        $id_pesan = $pesan['id'];
        $nomor_tujuan = $pesan['nomor_tujuan'];
        $isi_pesan = $pesan['isi_pesan'];

        // Kirim pesan menggunakan Fonnte
        $berhasil = kirimPesanFonnte($nomor_tujuan, $isi_pesan, 10);

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
