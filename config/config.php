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

function formatTanggalIndonesia($tanggal_db)
{
    // 1. Ubah string tanggal dari database menjadi format waktu (timestamp)
    $timestamp = strtotime($tanggal_db);

    // 2. Buat array untuk nama bulan dalam bahasa Indonesia
    $bulan_indonesia = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    // 3. Pecah tanggal, bulan, dan tahun dari timestamp
    $tanggal = date('d', $timestamp);
    $bulan = $bulan_indonesia[(int)date('m', $timestamp)]; // Ambil nama bulan dari array
    $tahun = date('Y', $timestamp);

    // 4. Gabungkan kembali menjadi format yang diinginkan
    return "$tanggal $bulan $tahun";
}

// Jika ingin tanpa angka 0 di depan tanggal
function formatTanggalIndonesiaTanpaNol($tanggal_db)
{
    $timestamp = strtotime($tanggal_db);
    $bulan_indonesia = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $tanggal = date('j', $timestamp); // Gunakan 'j' kecil
    $bulan = $bulan_indonesia[(int)date('n', $timestamp)]; // Gunakan 'n' kecil
    $tahun = date('Y', $timestamp);
    return "$tanggal $bulan $tahun";
}

/**
 * Mengubah format tanggal YYYY-MM-DD menjadi format Indonesia (contoh: 22 Oktober 2025).
 *
 * @param string|null $tanggal_db Tanggal dalam format YYYY-MM-DD atau NULL.
 * @return string Tanggal dalam format Indonesia atau string kosong jika input tidak valid.
 */
function formatTanggalLahirIndonesia($tanggal_db)
{
    // Kembalikan string kosong jika input kosong atau tidak valid
    if (empty($tanggal_db) || $tanggal_db === '0000-00-00') {
        return '';
    }

    try {
        // 1. Ubah string tanggal database menjadi objek DateTime
        // Ini lebih aman daripada strtotime untuk menangani format yang tidak pasti
        $date = new DateTime($tanggal_db);

        // 2. Buat array nama bulan dalam Bahasa Indonesia
        $bulan_indonesia = [
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        ];

        // 3. Ambil tanggal (tanpa 0 di depan), nomor bulan, dan tahun
        $tanggal = $date->format('j'); // 'j' = hari tanpa 0 di depan (1-31)
        $bulan = $bulan_indonesia[(int)$date->format('n')]; // 'n' = bulan tanpa 0 di depan (1-12)
        $tahun = $date->format('Y'); // 'Y' = tahun 4 digit

        // 4. Gabungkan menjadi format yang diinginkan
        return "$tanggal $bulan $tahun";
    } catch (Exception $e) {
        // Tangani jika format tanggal dari DB tidak valid
        // error_log("Error format tanggal: " . $e->getMessage()); // Opsional: catat error
        return ''; // Kembalikan string kosong jika error
    }
}
