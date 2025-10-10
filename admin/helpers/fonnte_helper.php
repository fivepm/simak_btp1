<?php

/**
 * Fungsi untuk mengirim pesan WhatsApp menggunakan API Fonnte.
 *
 * @param string $nomor_hp Nomor tujuan dengan format internasional (misal: 6281234567890).
 * @param string $pesan Isi pesan yang akan dikirim.
 * @return bool True jika berhasil, false jika gagal.
 */
function kirimPesanFonnte($nomor_hp, $pesan, $delay, $tipe_penerima = 'umum')
{
    // Pastikan Anda memiliki akses ke variabel koneksi database.
    global $conn;

    // Ambil token dari environment variables
    $token = $_ENV['FONNTE_TOKEN'] ?? 'TOKEN_ANDA'; // Ganti TOKEN_ANDA jika .env tidak digunakan

    if (empty($token)) {
        // Jika token tidak ada, langsung gagalkan proses
        error_log("Fonnte Token tidak ditemukan.");
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

    $response_json = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("Fonnte cURL Error: " . $err);
        return false;
    } else {
        // --- LOGIKA BARU UNTUK PENCATATAN LOG DIMULAI DI SINI ---

        // Ubah respons JSON dari Fonnte menjadi array PHP
        $response_data = json_decode($response_json, true);

        // Cek apakah pengiriman berhasil (ada 'id' di dalam respons)
        if (isset($response_data['id'])) {
            $fonnte_id = $response_data['id'];

            // SIMPAN JEJAK PENGIRIMAN KE DATABASE
            // Perhatikan kita menyimpan $pesan asli, bukan $pesan_wm dengan watermark
            $stmt = $conn->prepare("
                INSERT INTO log_pesan_wa (fonnte_id, nomor_tujuan, tipe_penerima, isi_pesan, status_kirim) 
                VALUES (?, ?, ?, ?, 'Terkirim')
            ");

            if ($stmt) {
                $stmt->bind_param("ssss", $fonnte_id, $nomor_hp, $tipe_penerima, $pesan);
                $stmt->execute();
                $stmt->close();
            }

            return true; // Kembalikan 'true' untuk menandakan proses berhasil
        } else {
            // Jika Fonnte mengembalikan status gagal
            error_log("Gagal mengirim pesan ke Fonnte. Target: {$nomor_hp}. Respons: " . $response_json);
            return false; // Kembalikan 'false' untuk menandakan proses gagal
        }
        // --- AKHIR LOGIKA BARU ---
    }
}
