<?php

/**
 * ===================================================================
 * WEBHOOK HANDLER UNTUK FONNTE
 * ===================================================================
 * * Tujuan:
 * File ini berfungsi sebagai endpoint (penerima) untuk data yang dikirim
 * oleh Fonnte secara otomatis (webhook).
 * * Cara Kerja:
 * 1. Menerima data mentah (raw POST data) dalam format JSON dari Fonnte.
 * 2. Memeriksa apakah data tersebut adalah update status ('ack') atau pesan balasan ('receive').
 * 3. Jika update status, perbarui status di tabel 'log_pesan_wa'.
 * 4. Jika pesan balasan, simpan balasan ke tabel 'balasan_wa'.
 * 5. File ini harus bisa diakses secara publik melalui URL.
 * */

// --- 1. PENGATURAN KONEKSI & FUNGSI BANTU ---

// Sesuaikan path ke file koneksi Anda
include 'config/config.php';

// Fungsi untuk menulis log ke file teks, sangat berguna untuk debugging
function write_log($message)
{
    $log_file = __DIR__ . '/webhook_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] " . print_r($message, true) . "\n", FILE_APPEND);
}

// --- 2. TANGKAP DAN PROSES DATA DARI FONNTE ---

// Ambil data mentah yang dikirim oleh Fonnte
$json_data = file_get_contents('php://input');

// Tulis log untuk setiap request yang masuk (untuk debugging)
write_log("Request diterima: " . $json_data);

// Ubah data JSON menjadi array PHP
$data = json_decode($json_data, true);

// Hentikan jika data tidak valid atau kosong
if (empty($data)) {
    write_log("Request kosong atau tidak valid.");
    http_response_code(400); // Bad Request
    exit();
}


// --- 3. LOGIKA UTAMA: MEMBEDAKAN JENIS NOTIFIKASI ---

// A. Jika ini adalah UPDATE STATUS (Acknowledgment)
if (isset($data['id'], $data['status'])) {

    $fonnte_id = $data['id'];
    $status_baru = $data['status']; // 'sent', 'delivered', 'read', 'failed'
    $status_db = 'Terkirim'; // Default

    // Konversi status dari Fonnte ke status yang kita gunakan di database
    switch ($status_baru) {
        case 'delivered':
            $status_db = 'Diterima';
            break;
        case 'read':
            $status_db = 'Dibaca';
            break;
        case 'failed':
            $status_db = 'Gagal';
            break;
    }

    // Update status di tabel log_pesan_wa
    $stmt = $conn->prepare("UPDATE log_pesan_wa SET status_kirim = ? WHERE fonnte_id = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $status_db, $fonnte_id);
        if ($stmt->execute()) {
            write_log("Update status berhasil untuk Fonnte ID: {$fonnte_id} -> {$status_db}");
        } else {
            write_log("Gagal eksekusi update untuk Fonnte ID: {$fonnte_id}. Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        write_log("Gagal prepare statement update status. Error: " . $conn->error);
    }
}

// B. Jika ini adalah PESAN BALASAN (Receive Message)
elseif (isset($data['sender'], $data['message'])) {

    // --- LOGIKA BARU UNTUK MEMBEDAKAN GRUP DAN PRIBADI ---
    $isi_balasan = $data['message'];
    $timestamp_balasan = date('Y-m-d H:i:s');
    $nama_pengirim = $data['pushname'] ?? 'Tidak Diketahui'; // Ambil nama tampilan

    // Cek apakah pesan berasal dari grup (ID grup diakhiri dengan @g.us)
    if (strpos($data['sender'], '@g.us') !== false) {
        // INI PESAN DARI GRUP
        $id_grup = $data['sender']; // ID grup
        $nomor_pengirim = $data['from']; // Nomor HP orang yang mengetik
    } else {
        // INI PESAN PRIBADI
        $id_grup = null; // Bukan dari grup
        $nomor_pengirim = $data['sender']; // Langsung nomor HP pengirim
    }
    // --- AKHIR LOGIKA BARU ---

    // Simpan pesan balasan dengan data yang lebih lengkap
    $stmt = $conn->prepare("INSERT INTO balasan_wa (nomor_pengirim, nama_pengirim, id_grup, isi_balasan, timestamp_balasan) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $nomor_pengirim, $nama_pengirim, $id_grup, $isi_balasan, $timestamp_balasan);
        if ($stmt->execute()) {
            write_log("Balasan dari {$nama_pengirim} ({$nomor_pengirim}) berhasil disimpan.");
        } else {
            write_log("Gagal eksekusi insert balasan. Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        write_log("Gagal prepare statement insert balasan. Error: " . $conn->error);
    }
} else {
    write_log("Format data tidak dikenali.");
}


// --- 4. SELESAI ---
$conn->close();

// Beri respons 'OK' ke Fonnte
http_response_code(200);
echo "OK";
