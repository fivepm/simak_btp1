<?php
// File ini dijalankan oleh Cron Job dari server, jadi perlu path lengkap
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../helpers/fonnte_helper.php';

// Karena file ini dijalankan langsung dari server (bukan via URL),
// kita tidak lagi memerlukan kunci rahasia.

$token = $_ENV['FONNTE_TOKEN'] ?? '';
if (empty($token)) {
    error_log("Fonnte Token tidak ditemukan di file .env");
    die("Token Fonnte tidak ditemukan.");
}

// Inisialisasi cURL untuk mengecek status device
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.fonnte.com/device",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_HTTPHEADER => [
        "Authorization: " . $token
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    error_log("Fonnte cURL Error: " . $err);
    die("cURL Error: " . $err);
}

$response_data = json_decode($response, true);

// Cek jika ada data dan status device
if (isset($response_data['device_status'])) {
    if ($response_data['device_status'] === 'connect') {
        // PERBAIKAN: Jika terhubung, kirim pesan "status aman" ke admin
        echo "Status Device: Terhubung (Connected). Mengirim notifikasi status aman...";

        $admin_numbers_string = $_ENV['ADMIN_WA_NUMBERS'] ?? '';
        if (!empty($admin_numbers_string)) {
            $admin_numbers = explode(',', $admin_numbers_string);

            $waktu_cek = date('d M Y, H:i:s');
            $pesan_notifikasi = "✅ Laporan Status SIMAK\n\nDevice Fonnte Anda berhasil dicek pada " . $waktu_cek . " dan dalam keadaan *TERHUBUNG*.\n\nSistem notifikasi berjalan normal.";

            // Kirim pesan ke setiap nomor admin
            foreach ($admin_numbers as $number) {
                kirimPesanFonnte(trim($number), $pesan_notifikasi, 10);
                echo "\nNotifikasi status aman dikirim ke " . trim($number);
            }
        } else {
            error_log("Nomor WA admin tidak diatur di file .env.");
            echo "\nNomor WA admin tidak diatur.";
        }
    } else {
        // Jika device terputus, cukup laporkan ke log dan tidak melakukan apa-apa
        echo "Status Device: Terputus (Disconnected). Tidak ada pesan yang dikirim.";
        error_log("Peringatan: Device Fonnte terputus.");
    }
} else {
    error_log("Gagal mendapatkan status device. Respon dari Fonnte: " . $response);
    echo "Gagal mendapatkan status device. Respon dari Fonnte: " . $response;
}
