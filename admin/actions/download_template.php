<?php
// File ini tidak memuat HTML apa pun, hanya logika PHP.

// Ambil tipe template yang diminta dari URL
$type = $_GET['type'] ?? '';

if ($type === 'peserta') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="template_peserta.csv"');
    $output = fopen('php://output', 'w');

    // Header untuk CSV
    fputcsv($output, [
        'kelompok',
        'nama_lengkap',
        'kelas',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir (YYYY-MM-DD)',
        'nomor_hp',
        'status',
        'nama_orang_tua',
        'nomor_hp_orang_tua'
    ]);

    // Contoh baris data
    fputcsv($output, [
        'bintaran',
        'Contoh Nama Peserta',
        'caberawit a',
        'Laki-laki',
        'Yogyakarta',
        '2018-01-15',
        '08123456789',
        'Aktif',
        'Nama Ayah',
        '08987654321'
    ]);

    fclose($output);
    exit;
}

// Jika tipe tidak dikenali, tampilkan pesan error
header("HTTP/1.0 404 Not Found");
echo "Template tidak ditemukan.";
exit;
