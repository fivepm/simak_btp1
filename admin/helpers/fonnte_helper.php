<?php

/**
 * Fungsi untuk mengirim pesan WhatsApp menggunakan API Fonnte.
 *
 * @param string $nomor_hp Nomor tujuan dengan format internasional (misal: 6281234567890).
 * @param string $pesan Isi pesan yang akan dikirim.
 * @return bool True jika berhasil, false jika gagal.
 */
function kirimPesanFonnte($nomor_hp, $pesan, $delay)
{
    // Ambil token dari environment variables
    $token = $_ENV['FONNTE_TOKEN'] ?? '';

    if (empty($token)) {
        // Jika token tidak ada, langsung gagalkan proses
        error_log("Fonnte Token tidak ditemukan di file .env");
        return false;
    }
    $pesan_wm = $pesan . "\n\n> Sistem PJP Banguntapan 1. Chat ini dikirimkan otomatis oleh sistem.";

    // Siapkan data untuk dikirim
    $payload = [
        'target' => $nomor_hp,
        'message' => $pesan_wm,
        'delay' => $delay,
        'countryCode' => '62', // Opsional, defaultnya 62
    ];

    // Inisialisasi cURL
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("Fonnte cURL Error: " . $err);
        return false;
    } else {
        // Anda bisa menambahkan logika untuk memeriksa detail respon jika perlu
        // $response_data = json_decode($response, true);
        // if ($response_data['status'] === true) { ... }
        return true;
    }
}
