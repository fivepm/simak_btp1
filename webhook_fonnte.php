<?php

/**
 * ===================================================================
 * WEBHOOK HANDLER UNTUK FONNTE (VERSI DIPERBARUI)
 * ===================================================================
 * * Perubahan:
 * - Mampu menangani berbagai format kunci dari Fonnte (id/stateid, message/text, dll).
 * - Logika yang lebih kuat untuk membedakan pesan grup dan pribadi.
 * */

// Sesuaikan path ke file koneksi Anda
include __DIR__ . '/config/config.php';

function write_log($message)
{
    $log_file = __DIR__ . '/webhook_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] " . print_r($message, true) . "\n", FILE_APPEND);
}

$json_data = file_get_contents('php://input');
write_log("Request diterima: " . $json_data);
$data = json_decode($json_data, true);

if (empty($data)) {
    write_log("Request kosong atau tidak valid.");
    http_response_code(400);
    exit();
}

// ===================================================================
// LOGIKA UTAMA (LEBIH FLEKSIBEL)
// ===================================================================

// A. Jika ini adalah UPDATE STATUS (Acknowledgment)
if (isset($data['id'], $data['status']) || isset($data['stateid'], $data['state'])) {

    // Gunakan operator null coalescing (??) untuk mengambil nilai yang ada
    $fonnte_id = $data['id'] ?? $data['stateid'];
    $status_baru = $data['status'] ?? $data['state'];
    $status_db = 'Terkirim';

    switch (strtolower($status_baru)) { // Ubah ke huruf kecil untuk konsistensi
        case 'delivered':
        case 'receive': // Beberapa webhook mengirim 'receive'
            $status_db = 'Diterima';
            break;
        case 'read':
            $status_db = 'Dibaca';
            break;
        case 'failed':
            $status_db = 'Gagal';
            break;
    }

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
elseif (isset($data['message']) || isset($data['text']) || isset($data['body'])) {

    $isi_balasan = $data['message'] ?? $data['text'] ?? $data['body'];
    $timestamp_balasan = date('Y-m-d H:i:s');
    $nama_pengirim = $data['pushname'] ?? 'Tidak Diketahui';

    // Fonnte menggunakan 'chatId' untuk ID utama (bisa grup/pribadi)
    // dan 'sender' untuk nomor spesifik di dalam grup.
    $pengirim_utama = $data['chatId'] ?? $data['sender'];

    if (strpos($pengirim_utama, '@g.us') !== false) {
        // Pesan dari GRUP
        $id_grup = $pengirim_utama;
        $nomor_pengirim = $data['sender'] ?? $pengirim_utama; // nomor HP orang yang mengetik
    } else {
        // Pesan PRIBADI
        $id_grup = null;
        $nomor_pengirim = $pengirim_utama;
    }

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

$conn->close();

http_response_code(200);
echo "OK";
