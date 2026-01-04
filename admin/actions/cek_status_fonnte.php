<?php
// --- KONFIGURASI PATH ---
// Sesuaikan path ini dengan struktur folder Anda
require_once __DIR__ . '/../../config/config.php';

// Load helper WA Gateway yang sudah dibuat sebelumnya
// Pastikan file 'wa_gateway.php' ada di folder ../helpers/ atau sesuaikan path-nya
require_once __DIR__ . '/../helpers/wa_gateway.php';

// --- KONFIGURASI API ---
// Ambil dari .env atau Config. Jika tidak ada, fallback ke string kosong
$apiToken   = $_ENV['WA_API_TOKEN'] ?? ''; // Token akun (Bearer)
$sessionKey = $_ENV['WA_SESSION_KEY'] ?? ''; // Session Key Device

// Validasi Token
if (empty($apiToken) || empty($sessionKey)) {
    $msg = "WA Gateway Error: Token atau Session Key belum diatur di .env/config.";
    error_log($msg);
    die($msg);
}

// --- 1. CEK STATUS DEVICE ---
// Endpoint CraftiveLabs untuk cek detail device
$url = "https://notify.craftivelabs.com/api/device/" . $sessionKey;

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET", // Menggunakan GET, bukan POST
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $apiToken,
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

unset($curl);

if ($err) {
    error_log("WA Gateway cURL Error: " . $err);
    die("cURL Error: " . $err);
}

// Decode JSON
$response_data = json_decode($response, true);

// --- 2. LOGIKA NOTIFIKASI ---

// Cek apakah request sukses dan data status ada
if (isset($response_data['data']['status'])) {

    $statusDevice = $response_data['data']['status']; // Biasanya 'CONNECTED' atau 'DISCONNECTED'
    $quota        = $response_data['data']['remaining_quota'] ?? 0;

    if ($statusDevice === 'CONNECTED') {
        echo "Status Device: Terhubung (CONNECTED). Mengirim notifikasi status aman...";

        // Ambil nomor admin dari ENV
        $admin_numbers_string = $_ENV['ADMIN_WA_NUMBERS'] ?? '';

        if (!empty($admin_numbers_string)) {
            $admin_numbers = explode(',', $admin_numbers_string);
            $waktu_cek = date('d M Y, H:i:s');

            // Format Pesan Baru
            $pesan_notifikasi = "✅ *Laporan Status SIMAK*\n\n"
                . "Device WhatsApp Gateway berhasil dicek pada:\n"
                . "🕒 " . $waktu_cek . "\n\n"
                . "Status: *TERHUBUNG*\n"
                . "Sisa Kuota: " . $quota . " pesan\n\n"
                . "Sistem notifikasi berjalan normal.";

            // Kirim pesan ke setiap nomor admin menggunakan fungsi dari wa_gateway.php
            foreach ($admin_numbers as $number) {
                $number = trim($number);

                // Panggil fungsi kirimWhatsApp (dari wa_gateway.php)
                // Parameter: ($nomor, $pesan, $apiToken, $sessionKey)
                $kirim = kirimWhatsApp($number, $pesan_notifikasi);

                // Cek hasil kirim sederhana
                if (isset($kirim['meta']['code']) && $kirim['meta']['code'] == 200) {
                    echo "\n[OK] Notifikasi terkirim ke " . $number;
                } else {
                    echo "\n[FAIL] Gagal kirim ke " . $number;
                    error_log("Gagal kirim cron ke $number: " . json_encode($kirim));
                }
            }
        } else {
            error_log("Nomor WA admin (ADMIN_WA_NUMBERS) tidak diatur di file konfigurasi.");
            echo "\nNomor WA admin tidak diatur.";
        }
    } else {
        // Jika device DISCONNECTED
        echo "Status Device: " . $statusDevice . ". Tidak mengirim pesan notifikasi.";
        error_log("Peringatan: Device WA Gateway statusnya " . $statusDevice);
    }
} else {
    // Jika format respon tidak sesuai harapan (misal API Error 401/500)
    $errorMsg = "Gagal mendapatkan status device. HTTP Code: $httpCode. Respon: " . $response;
    error_log($errorMsg);
    echo $errorMsg;
}
