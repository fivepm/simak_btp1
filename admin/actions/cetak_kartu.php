<?php
// Memuat semua library yang dibutuhkan
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

// Menggunakan namespace yang benar untuk library versi terbaru
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

// Ambil ID guru dari URL
$guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
if ($guru_id === 0) {
    die("ID Guru tidak valid.");
}

// Ambil data guru dari database
$stmt = $conn->prepare("SELECT nama, kelompok, kelas, barcode FROM guru WHERE id = ?");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$result = $stmt->get_result();
$guru = $result->fetch_assoc();

if (!$guru) {
    die("Data guru tidak ditemukan.");
}

// --- PENGATURAN KARTU ---
// Lokasi file aset
if ($guru['kelas'] == 'paud') {
    $template_path = realpath(__DIR__ . '/../../assets/images/template_paud.png');
} else if ($guru['kelas'] == 'caberawit a') {
    $template_path = realpath(__DIR__ . '/../../assets/images/template_cbra.png');
} else if ($guru['kelas'] == 'caberawit b') {
    $template_path = realpath(__DIR__ . '/../../assets/images/template_cbrb.png');
} else if ($guru['kelas'] == 'pra remaja') {
    $template_path = realpath(__DIR__ . '/../../assets/images/template_praremaja.png');
} else if ($guru['kelas'] == 'remaja') {
    $template_path = realpath(__DIR__ . '/../../assets/images/template_remaja.png');
} else if ($guru['kelas'] == 'pra nikah') {
    $template_path = realpath(__DIR__ . '/../../assets/images/template_pranikah.png');
}
$font_path = realpath(__DIR__ . '/../../assets/fonts/ChauPhilomeneOne-Regular.ttf');

if (!$template_path || !$font_path) {
    die("File template atau font tidak ditemukan. Pastikan ada di folder /assets/.");
}

// Buat gambar dari template
$template_image = imagecreatefrompng($template_path);

// Tentukan warna (RGB)
if ($guru['kelas'] == 'paud') {
    $warna_teks = imagecolorallocate($template_image, 114, 30, 93); // Warna pink tua
} else if ($guru['kelas'] == 'caberawit a') {
    $warna_teks = imagecolorallocate($template_image, 2, 61, 33); // Warna hijau tua
} else if ($guru['kelas'] == 'caberawit b') {
    $warna_teks = imagecolorallocate($template_image, 82, 61, 0); // Warna kuning tua
} else if ($guru['kelas'] == 'pra remaja') {
    $warna_teks = imagecolorallocate($template_image, 2, 61, 33); // Warna hijau tua
} else if ($guru['kelas'] == 'remaja') {
    $warna_teks = imagecolorallocate($template_image, 0, 35, 82); // Warna biru tua
} else if ($guru['kelas'] == 'pra nikah') {
    $warna_teks = imagecolorallocate($template_image, 134, 41, 43); // Warna merah tua
}

// Tulis teks ke gambar (sesuaikan koordinat x, y jika perlu)
imagettftext($template_image, 20, 0, 185, 780, $warna_teks, $font_path, $guru['nama']);
imagettftext($template_image, 15, 0, 185, 808, $warna_teks, $font_path, ucfirst($guru['kelompok']) . ' - ' . ucfirst($guru['kelas']));

// 1. Hitung posisi untuk NAMA GURU
// $text_nama = $guru['nama'];
// $bbox_nama = imagettfbbox(7.3, 0, $font_path, $text_nama);
// $x_nama = ($image_width - $bbox_nama[2]) / 2;
// imagettftext($template_image, 7.3, 0, $x_nama, 754, $warna_teks, $font_path, $text_nama);

// 2. Hitung posisi untuk KELOMPOK - KELAS
// $text_kelompok = '- ' . ucfirst($guru['kelompok']) . ' - ' . ucfirst($guru['kelas']) . ' -';
// $bbox_kelompok = imagettfbbox(5.8, 0, $font_path, $text_kelompok);
// $x_kelompok = ($image_width - $bbox_kelompok[2]) / 2;
// imagettftext($template_image, 5.8, 0, $x_kelompok, 790, $warna_teks, $font_path, $text_kelompok);

// 1. Buat Builder untuk QR Code
$builder = new Builder(
    writer: new PngWriter(), // Penulis gambar PNG
    data: ($guru['barcode']), // Data yang akan dimasukkan ke QR Code
    encoding: new Encoding('UTF-8'), // Pengaturan encoding
    errorCorrectionLevel: ErrorCorrectionLevel::High, // Level koreksi error
    size: 300, // Ukuran QR Code dalam piksel
    margin: 10, // Margin QR Code
    roundBlockSizeMode: RoundBlockSizeMode::Margin, // Mode ukuran blok bulat
    validateResult: false
);

// 2. Hasilkan hasil dari builder
$result = $builder->build();
$qr_image = imagecreatefromstring($result->getString());

// Tempelkan QR Code ke template (sesuaikan koordinat x, y jika perlu)
imagecopy($template_image, $qr_image, 147, 342, 0, 0, imagesx($qr_image), imagesy($qr_image));

// --- HASIL AKHIR ---
// Atur header agar browser mengunduh gambar
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="kartu-akses-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $guru['nama']) . '.png"');

// Tampilkan gambar final ke browser
imagepng($template_image);

// Hapus gambar dari memori
imagedestroy($template_image);
imagedestroy($qr_image);
