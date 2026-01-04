<?php

/**
 * Mencari Group ID WhatsApp yang paling relevan berdasarkan prioritas.
 * Fungsi ini sekarang akan memeriksa environment untuk memilih ID yang benar.
 *
 * @param mysqli $conn Objek koneksi database.
 * @param string $kelompok Kelompok dari jadwal.
 * @param string $kelas Kelas dari jadwal.
 * @return string|null Group ID yang ditemukan, atau null jika tidak ada.
 */
function getGroupId($conn, $kelompok, $kelas)
{
    // Cek environment dari file .env
    $is_production = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');

    // Jika sedang development, kirim semua pesan ke satu grup tes
    if (!$is_production) {
        // Ambil ID grup tes dari .env
        return $_ENV['GROUP_ID_UMUM'] ?? null;
    }

    // --- Logika untuk Production (Hosting) ---

    // Prioritas 1: Paling spesifik (cocok kelompok & kelas)
    $stmt = $conn->prepare("SELECT group_id FROM grup_whatsapp WHERE kelompok = ? AND kelas = ? LIMIT 1");
    $stmt->bind_param("ss", $kelompok, $kelas);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['group_id'];
    }

    // Prioritas 2: Spesifik kelompok, umum kelas (kelas IS NULL)
    $stmt = $conn->prepare("SELECT group_id FROM grup_whatsapp WHERE kelompok = ? AND kelas IS NULL LIMIT 1");
    $stmt->bind_param("s", $kelompok);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['group_id'];
    }

    // Prioritas 3: Umum kelompok, spesifik kelas (kelompok IS NULL)
    $stmt = $conn->prepare("SELECT group_id FROM grup_whatsapp WHERE kelompok IS NULL AND kelas = ? LIMIT 1");
    $stmt->bind_param("s", $kelas);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['group_id'];
    }

    // Prioritas 4: Paling umum (kelompok & kelas IS NULL)
    $stmt = $conn->prepare("SELECT group_id FROM grup_whatsapp WHERE kelompok IS NULL AND kelas IS NULL LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['group_id'];
    }

    return null; // Tidak ada grup yang cocok ditemukan
}
