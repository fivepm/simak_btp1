<?php

/**
 * Mengirim pesan WhatsApp via CraftiveLabs
 * * @param string $nomor       Nomor tujuan (Format: 628xxx)
 * @param string $pesan       Isi pesan
 * @param string $apiToken    API Key / Token (Untuk Header Authorization)
 * @param string $sessionKey  Session Key Device (Untuk Body JSON)
 */
function kirimWhatsApp($nomor, $pesan)
{
    $apiToken = $_ENV['WA_API_TOKEN'];
    $sessionKey = $_ENV['WA_SESSION_KEY'];
    // 1. Endpoint
    $url = 'https://notify.craftivelabs.com/api/message/send';

    // 2. Data Body (Sesuai format JSON yang diminta sebelumnya)
    $pesan_wm = $pesan . "\n\n> SIMAK Banguntapan 1.";
    $data = [
        'session_key' => $sessionKey,
        'phone'       => $nomor,
        'message'     => $pesan_wm
    ];

    // 3. Inisialisasi cURL
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,

        // 4. HEADER PENTING (Disini perbaikannya)
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiToken, // Token masuk sini
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    // 5. Eksekusi
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    unset($ch);

    // 6. Cek Error Koneksi
    if ($result === false) {
        return [
            'status' => 'error',
            'message' => 'Koneksi Gagal: ' . $curlError
        ];
    }

    // 7. Decode JSON
    $response = json_decode($result, true);

    // Tambahkan info HTTP Code untuk debugging
    if (is_array($response)) {
        $response['http_code'] = $httpCode;
    } else {
        // Jaga-jaga jika respon bukan JSON
        return ['status' => 'error', 'http_code' => $httpCode, 'raw_response' => $result];
    }

    return $response;
}
