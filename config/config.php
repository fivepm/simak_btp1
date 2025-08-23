<?php
// Memuat autoloader dari Composer
// Ini akan me-load semua library yang kita install, termasuk phpdotenv
require_once __DIR__ . '/../vendor/autoload.php';

// Inisialisasi library Dotenv
// __DIR__ memastikan path-nya selalu benar, di mana pun file ini dipanggil
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// --- PENGATURAN KONEKSI DATABASE ---
// Ambil nilai dari .env menggunakan $_ENV atau $_SERVER
$hostname    = $_ENV['DB_HOST'];
$database    = $_ENV['DB_DATABASE'];
$db_username = $_ENV['DB_USERNAME'];
$db_password = $_ENV['DB_PASSWORD'];

// Membuat koneksi ke database
$conn = new mysqli($hostname, $db_username, $db_password, $database);

// Cek koneksi
if ($conn->connect_error) {
    // Tampilkan pesan error dan hentikan script
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Opsional: Mengatur character set ke utf8mb4 untuk mendukung emoji dan karakter lainnya
$conn->set_charset("utf8mb4");
