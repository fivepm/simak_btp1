<?php
// === FILE: ajax_cek_wa.php ===
require_once '../../../config/config.php';

header('Content-Type: application/json');

// Tambahkan fungsi ini di dalam file helpers/fonnte_helper.php

function cekNomorWaTerdaftar($nomor_tujuan)
{
    $fonnte_token = $_ENV['FONNTE_TOKEN'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/validate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $nomor_tujuan,
            'countryCode' => '62' // Pastikan menyesuaikan kode negara, 62 untuk Indonesia
        ),
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $fonnte_token
        ),
    ));

    $response = curl_exec($curl);
    $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Fonnte mengembalikan status 200 jika berhasil, tapi kita harus cek detail JSON-nya
    if ($http_status == 200) {
        $data = json_decode($response, true);

        // Response Fonnte biasanya: {"status":true, "registered":["62812..."], "unregistered":[]}
        if (isset($data['status']) && $data['status'] == true) {
            // Cek apakah nomor masuk dalam daftar 'registered'
            if (isset($data['registered']) && in_array($nomor_tujuan, $data['registered'])) {
                return ['status' => true, 'pesan' => 'Nomor terdaftar di WhatsApp.'];
            } else {
                return ['status' => false, 'pesan' => 'Nomor tidak terdaftar di WhatsApp.'];
            }
        } else {
            return ['status' => false, 'pesan' => 'Gagal memvalidasi nomor dengan API Fonnte.'];
        }
    }

    return ['status' => false, 'pesan' => 'Terjadi kesalahan koneksi ke server Fonnte.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil nomor HP dari POST, pastikan hanya angka dan diawali kode negara
    $nomor_hp = preg_replace('/[^0-9]/', '', $_POST['nomor_hp'] ?? '');

    if (empty($nomor_hp)) {
        echo json_encode(['status' => false, 'pesan' => 'Nomor HP tidak boleh kosong.']);
        exit;
    }

    // Jika nomor diawali '0', ubah menjadi '62' (kode Indonesia)
    if (strpos($nomor_hp, '0') === 0) {
        $nomor_hp = '62' . substr($nomor_hp, 1);
    }

    // Panggil fungsi validasi
    $hasil_cek = cekNomorWaTerdaftar($nomor_hp);

    echo json_encode($hasil_cek);
} else {
    echo json_encode(['status' => false, 'pesan' => 'Metode tidak diizinkan.']);
}
