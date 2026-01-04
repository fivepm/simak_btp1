<?php

/**
 * Mengambil template pesan dari database dengan urutan prioritas, 
 * mengganti placeholder, dan mengembalikan pesan final.
 *
 * @param mysqli $conn Objek koneksi database.
 * @param string $tipe Tipe pesan (misal: 'notifikasi_alpa').
 * @param string $kelas Kelas yang relevan (misal: 'paud').
 * @param string $kelompok Kelompok yang relevan (misal: 'bintaran').
 * @param array $data Data untuk menggantikan placeholder (misal: ['[nama]' => 'Adi']).
 * @return string Pesan yang sudah diformat.
 */
function getFormattedMessage($conn, $tipe, $kelas, $kelompok, $data)
{
    $template_string = null;

    // Prioritas 1: Paling spesifik (tipe, kelas, kelompok)
    $stmt = $conn->prepare("SELECT template FROM template_pesan WHERE tipe_pesan = ? AND kelas = ? AND kelompok = ? LIMIT 1");
    $stmt->bind_param("sss", $tipe, $kelas, $kelompok);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $template_string = $result->fetch_assoc()['template'];
    }
    $stmt->close();

    // Prioritas 2: Spesifik kelompok, umum kelas (kelas = 'default')
    if (!$template_string) {
        $stmt = $conn->prepare("SELECT template FROM template_pesan WHERE tipe_pesan = ? AND kelas = 'default' AND kelompok = ? LIMIT 1");
        $stmt->bind_param("ss", $tipe, $kelompok);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $template_string = $result->fetch_assoc()['template'];
        }
        $stmt->close();
    }

    // Prioritas 3: Umum kelompok, spesifik kelas (kelompok IS NULL)
    if (!$template_string) {
        $stmt = $conn->prepare("SELECT template FROM template_pesan WHERE tipe_pesan = ? AND kelas = ? AND kelompok IS NULL LIMIT 1");
        $stmt->bind_param("ss", $tipe, $kelas);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $template_string = $result->fetch_assoc()['template'];
        }
        $stmt->close();
    }

    // Prioritas 4: Paling umum (kelas = 'default' dan kelompok IS NULL)
    if (!$template_string) {
        $stmt = $conn->prepare("SELECT template FROM template_pesan WHERE tipe_pesan = ? AND kelas = 'default' AND kelompok IS NULL LIMIT 1");
        $stmt->bind_param("s", $tipe);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $template_string = $result->fetch_assoc()['template'];
        }
        $stmt->close();
    }

    // Fallback terakhir jika tidak ada template sama sekali di database
    if (!$template_string) {
        return "Pemberitahuan untuk [nama] pada [tanggal].";
    }

    // Ganti semua placeholder dengan nilai yang sebenarnya
    $placeholders = array_keys($data);
    $values = array_values($data);

    return str_replace($placeholders, $values, $template_string);
}
