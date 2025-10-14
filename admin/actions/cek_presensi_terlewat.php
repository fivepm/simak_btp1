<?php

/**
 * ===================================================================
 * SKRIP OTOMATIS PENGINGAT PRESENSI & JURNAL
 * ===================================================================
 * * Tujuan:
 * Skrip ini dijalankan oleh Cron Job untuk secara otomatis mengirim pengingat
 * kepada guru yang belum mengisi presensi atau jurnal 3 jam setelah
 * jadwal KBM selesai.
 * * Cara Kerja:
 * 1. Mencari jadwal yang waktu selesainya tepat 3 jam yang lalu.
 * 2. Memeriksa apakah ada presensi yang statusnya masih NULL ATAU jurnalnya kosong.
 * 3. Jika salah satu kondisi terpenuhi, skrip akan membuat pesan pengingat.
 * 4. Pesan tersebut dimasukkan ke tabel `pesan_terjadwal` untuk dikirim oleh
 * sistem broadcast Fonnte Anda.
 * * Pengaturan Cron Job di cPanel:
 * Atur untuk berjalan setiap 15 menit sekali (* /15 * * * *).
 * Perintah (Command):
 * /usr/local/bin/php /home/username_cpanel/public_html/path/ke/folder/cron/cek_presensi_terlewat.php
 * * (Sesuaikan path di atas dengan lokasi file ini di hosting Anda)
 * */

// --- 1. PENGATURAN AWAL & KONEKSI ---

// Atur zona waktu ke WIB agar NOW() sesuai
date_default_timezone_set('Asia/Jakarta');

// Sesuaikan path ke file koneksi Anda
include_once __DIR__ . '/../../config/koneksi.php';

echo "Memulai pengecekan jadwal terlewat pada: " . date('Y-m-d H:i:s') . "\n";

// --- 2. QUERY UTAMA UNTUK MENCARI JADWAL YANG PERLU DIINGATKAN ---

// Jendela waktu pengecekan (misal, Cron Job berjalan setiap 15 menit)
// Ini akan mencari jadwal yang waktu selesai + 3 jam-nya berada di antara
// 3 jam 15 menit yang lalu dan 3 jam yang lalu.
// $interval_cron = 30; // dalam menit

// $sql = "
//     SELECT
//         jp.id as jadwal_id,
//         jp.kelas,
//         jp.kelompok,
//         jp.jam_selesai,
//         g.nama as nama_guru,
//         g.nomor_wa,
//         jp.pengajar,
//         (SELECT COUNT(id) FROM rekap_presensi WHERE jadwal_id = jp.id AND status_kehadiran IS NULL) as jumlah_presensi_kosong
//     FROM
//         jadwal_presensi jp
//     JOIN
//         jadwal_guru jg ON jp.id = jg.jadwal_id
//     JOIN
//         guru g ON jg.guru_id = g.id
//     WHERE
//         NOW() BETWEEN 
//             DATE_ADD(TIMESTAMP(jp.tanggal, jp.jam_selesai), INTERVAL 3 HOUR)
//             AND 
//             DATE_ADD(TIMESTAMP(jp.tanggal, jp.jam_selesai), INTERVAL '3:{$interval_cron}' HOUR_MINUTE)
//         AND (
//             EXISTS (SELECT 1 FROM rekap_presensi rp WHERE rp.jadwal_id = jp.id AND rp.status_kehadiran IS NULL)
//             OR 
//             (jp.pengajar IS NULL OR jp.pengajar = '')
//         )
// ";

// --- 2. QUERY UTAMA YANG LEBIH CERDAS ---
$sql = "
    SELECT
        jp.id as jadwal_id,
        jp.kelas,
        jp.kelompok,
        jp.jam_selesai,
        g.nama as nama_guru,
        g.nomor_wa,
        jp.pengajar,
        (SELECT COUNT(id) FROM rekap_presensi WHERE jadwal_id = jp.id AND status_kehadiran IS NULL) as jumlah_presensi_kosong
    FROM
        jadwal_presensi jp
    JOIN
        jadwal_guru jg ON jp.id = jg.jadwal_id
    JOIN
        guru g ON jg.guru_id = g.id
    WHERE
        -- Kondisi 1: Waktu 'grace period' sudah lewat
        DATE_ADD(TIMESTAMP(jp.tanggal, jp.jam_selesai), INTERVAL 1 HOUR) <= NOW()
        
        -- Kondisi 2: Pengingat belum pernah dikirim untuk jadwal ini
        AND jp.status_pengingat = 'Belum Dikirim'
        
        -- Kondisi 3: Presensi ATAU Jurnal masih kosong
        AND (
            EXISTS (SELECT 1 FROM rekap_presensi rp WHERE rp.jadwal_id = jp.id AND rp.status_kehadiran IS NULL)
            OR 
            (jp.pengajar IS NULL OR jp.pengajar = '')
        );
";


$result = $conn->query($sql);

if (!$result) {
    die("Query gagal dieksekusi: " . $conn->error . "\n");
}

if ($result->num_rows === 0) {
    echo "Tidak ada jadwal yang perlu diingatkan saat ini.\n";
    $conn->close();
    exit();
}

echo "Ditemukan " . $result->num_rows . " jadwal yang perlu diingatkan. Memproses...\n";

// --- 3. PROSES HASIL DAN MASUKKAN KE ANTRIAN PESAN ---

// --- 3. PROSES HASIL, KIRIM PENGINGAT, DAN UPDATE STATUS ---
$stmt_insert = $conn->prepare("INSERT INTO pesan_terjadwal (target, pesan, status) VALUES (?, ?, 'pending')");
$stmt_update = $conn->prepare("UPDATE jadwal_presensi SET status_pengingat = 'Sudah Dikirim' WHERE id = ?");

$reminder_count = 0;
while ($row = $result->fetch_assoc()) {
    $presensi_belum_lengkap = ($row['jumlah_presensi_kosong'] > 0);
    $jurnal_kosong = empty($row['pengajar']);
    $bagian_yang_hilang = '';

    if ($presensi_belum_lengkap && $jurnal_kosong) {
        $bagian_yang_hilang = 'presensi dan jurnal';
    } elseif ($presensi_belum_lengkap) {
        $bagian_yang_hilang = 'presensi (belum lengkap)';
    } elseif ($jurnal_kosong) {
        $bagian_yang_hilang = 'jurnal';
    }

    // Hanya proses jika ada yang hilang dan guru punya nomor WA
    if (!empty($bagian_yang_hilang) && !empty($row['nomor_wa'])) {
        $nama_guru_formatted = htmlspecialchars($row['nama_guru']);
        $kelas_formatted = htmlspecialchars($row['kelas']);
        $kelompok_formatted = htmlspecialchars($row['kelompok']);
        $jam_selesai_formatted = date('H:i', strtotime($row['jam_selesai']));

        $pesan_ke_guru = "Yth. Bapak/Ibu {$nama_guru_formatted},
        \nSistem mendeteksi Anda belum mengisi *{$bagian_yang_hilang}* untuk jadwal KBM:
        \n*Kelas:* {$kelas_formatted}
        \n*Kelompok:* {$kelompok_formatted}
        \n*Waktu Selesai:* {$jam_selesai_formatted}
        \nMohon untuk segera melengkapinya..";

        // Masukkan ke tabel pesan_terjadwal
        $stmt_insert->bind_param("ss", $row['nomor_wa'], $pesan_ke_guru);
        if ($stmt_insert->execute()) {
            // JIKA BERHASIL, UPDATE STATUS PENGINGAT DI JADWAL PRESENSI
            $stmt_update->bind_param("i", $row['jadwal_id']);
            $stmt_update->execute();

            echo "Pengingat untuk {$nama_guru_formatted} (Jadwal ID: {$row['jadwal_id']}) berhasil dimasukkan ke antrean.\n";
            $reminder_count++;
        } else {
            echo "Gagal memasukkan pengingat untuk {$nama_guru_formatted}. Error: " . $stmt_insert->error . "\n";
        }
    }
}

// --- 4. SELESAI ---

$stmt_insert->close();
$conn->close();

echo "Proses selesai. Total pengingat yang dibuat: " . $reminder_count . "\n";
