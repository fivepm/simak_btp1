<?php
// PERBAIKAN: Atur zona waktu default untuk seluruh aplikasi
date_default_timezone_set('Asia/Jakarta');

// Memuat autoloader dari Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Inisialisasi library Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// --- PENGATURAN KONEKSI DATABASE ---
$hostname    = $_ENV['DB_HOST'];
$database    = $_ENV['DB_DATABASE'];
$db_username = $_ENV['DB_USERNAME'];
$db_password = $_ENV['DB_PASSWORD'];

// Membuat koneksi ke database
$conn = new mysqli($hostname, $db_username, $db_password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Mengatur character set
$conn->set_charset("utf8mb4");

// PERBAIKAN: Atur zona waktu untuk sesi koneksi database ini
$conn->query("SET time_zone = '+07:00'");

function format_hari_tanggal($tanggal)
{
    $hari_indonesia = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu'
    ];

    $nomor_hari = date('w', strtotime($tanggal));
    $nama_hari = $hari_indonesia[$nomor_hari];

    $tanggal_format = date('d M Y', strtotime($tanggal));

    return $nama_hari . ', ' . $tanggal_format;
}
