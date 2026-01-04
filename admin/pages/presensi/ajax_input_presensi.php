<?php
session_start();
// Sesuaikan path ini dengan struktur folder Anda
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';
require_once '../../helpers/wa_gateway.php';
require_once '../../helpers/whatsapp_helper.php';
require_once '../../helpers/template_helper.php';

header('Content-Type: application/json');

// Cek Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis. Silakan login kembali.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';
$jadwal_id = isset($_POST['jadwal_id']) ? (int)$_POST['jadwal_id'] : 0;

if ($jadwal_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID Jadwal tidak valid.']);
    exit;
}

// Ambil data jadwal untuk keperluan log dan WA
$jadwal = $conn->query("SELECT * FROM jadwal_presensi WHERE id = $jadwal_id")->fetch_assoc();
if (!$jadwal) {
    echo json_encode(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.']);
    exit;
}

// Data Pendukung
$tanggal_jadwal = date("d M Y", strtotime($jadwal['tanggal']));
$kelas_jadwal = $jadwal['kelas'];
$kelompok_jadwal = $jadwal['kelompok'];

// =======================================================================
// KASUS 1: SIMPAN JURNAL
// =======================================================================
if ($action === 'simpan_jurnal') {
    $pengajar = $_POST['pengajar'] ?? '';
    $materi1 = $_POST['materi1'] ?? '';
    $materi2 = $_POST['materi2'] ?? '';
    $materi3 = $_POST['materi3'] ?? '';

    if (empty($pengajar)) {
        echo json_encode(['status' => 'error', 'message' => 'Nama Pengajar wajib diisi.']);
        exit;
    }

    $sql = "UPDATE jadwal_presensi SET pengajar=?, materi1=?, materi2=?, materi3=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $pengajar, $materi1, $materi2, $materi3, $jadwal_id);

    if ($stmt->execute()) {
        // --- CCTV LOG ---
        $jurnal_pertama = ($jadwal['pengajar'] == NULL);
        $log_action = $jurnal_pertama ? 'INSERT' : 'UPDATE';
        $log_desc = ($jurnal_pertama ? "Mengisi" : "Memperbarui") . " *Jurnal* kelompok *" . ucwords($kelompok_jadwal) . "* kelas *" . ucwords($kelas_jadwal) . "* pada tanggal `" . formatTanggalIndonesia($jadwal['tanggal']) . "`.";
        writeLog($log_action, $log_desc);

        // --- KIRIM WA (Proses ini akan membuat loading berjalan sampai selesai) ---
        // Pastikan fungsi helper getGroupId dan kirimWhatsApp tersedia/di-include via config atau helper
        if (function_exists('getGroupId') && function_exists('kirimWhatsApp')) {
            $target_group_id = getGroupId($conn, $jadwal['kelompok'], $jadwal['kelas']);

            $data_untuk_pesan = [
                '[nama]' => $pengajar,
                '[tanggal]' => $tanggal_jadwal,
                '[kelas]' => ucfirst($kelas_jadwal),
                '[kelompok]' => ucfirst($kelompok_jadwal),
                '[materi1]' => $materi1,
                '[materi2]' => $materi2,
                '[materi3]' => $materi3
            ];
            $pesan_final = getFormattedMessage($conn, 'jurnal_harian', $kelas_jadwal, $kelompok_jadwal, $data_untuk_pesan);

            kirimWhatsApp($target_group_id, $pesan_final);
        }

        echo json_encode(['status' => 'success', 'message' => 'Jurnal harian berhasil disimpan dan notifikasi terkirim.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan jurnal: ' . $stmt->error]);
    }
    $stmt->close();
}

// =======================================================================
// KASUS 2: SIMPAN PRESENSI
// =======================================================================
elseif ($action === 'simpan_kehadiran') {
    $kehadiran_data = $_POST['kehadiran'] ?? [];
    $keterangan_data = $_POST['keterangan'] ?? [];
    $nomor_hp_ortu_data = $_POST['nomor_hp_ortu'] ?? [];
    $kirim_wa_data = $_POST['kirim_wa'] ?? [];
    $nama_peserta_data = $_POST['nama_peserta'] ?? [];

    if (empty($kehadiran_data)) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada data kehadiran yang dikirim.']);
        exit;
    }

    // Cek Status Awal untuk Log
    $sample_id = array_key_first($kehadiran_data);
    $cek_query = $conn->query("SELECT status_kehadiran FROM rekap_presensi WHERE id = '$sample_id'");
    $existing_data = $cek_query->fetch_assoc();
    $is_first_input = is_null($existing_data['status_kehadiran']);
    $log_action = $is_first_input ? 'INSERT' : 'UPDATE';
    $log_verb = $is_first_input ? 'Mengisi' : 'Memperbarui';

    $conn->begin_transaction();
    try {
        $sql = "UPDATE rekap_presensi SET status_kehadiran = ?, keterangan = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stat_counter = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];

        foreach ($kehadiran_data as $rekap_id => $status) {
            $keterangan = $keterangan_data[$rekap_id] ?? '';
            $nomor_hp_ortu = $nomor_hp_ortu_data[$rekap_id] ?? '';
            $kirim_wa = $kirim_wa_data[$rekap_id] ?? '';
            $nama_peserta = $nama_peserta_data[$rekap_id] ?? 'Peserta';

            if (($status === 'Izin' || $status === 'Sakit') && empty($keterangan)) {
                throw new Exception("Keterangan wajib diisi untuk siswa yang Izin/Sakit.");
            }

            $stmt->bind_param("ssi", $status, $keterangan, $rekap_id);
            $stmt->execute();

            if (isset($stat_counter[$status])) {
                $stat_counter[$status]++;
            }

            // --- KIRIM WA ALPA ---
            if ($status === 'Alpa' && !empty($nomor_hp_ortu) && $kirim_wa === 'no') {
                if (function_exists('kirimWhatsApp')) {
                    $data_untuk_pesan = [
                        '[nama]' => $nama_peserta,
                        '[tanggal]' => $tanggal_jadwal,
                        '[kelas]' => ucfirst($kelas_jadwal),
                        '[kelompok]' => ucfirst($kelompok_jadwal)
                    ];
                    $pesan_final = getFormattedMessage($conn, 'notifikasi_alpa', $kelas_jadwal, $kelompok_jadwal, $data_untuk_pesan);

                    $berhasil = kirimWhatsApp($nomor_hp_ortu, $pesan_final);

                    if ($berhasil) {
                        $conn->query("UPDATE rekap_presensi SET kirim_wa = 'yes' WHERE id = $rekap_id");
                    }
                }
            }
        }

        $conn->commit();

        // Log CCTV
        $summary = [];
        foreach ($stat_counter as $k => $v) {
            if ($v > 0) $summary[] = "$k: $v";
        }
        $summary_text = implode(", ", $summary);
        $deskripsi_log = "$log_verb *Presensi* kelompok *" . ucwords($kelompok_jadwal) . "* kelas *" . ucwords($kelas_jadwal) . "* pada tanggal `" . $tanggal_jadwal . "` ($summary_text)";
        writeLog($log_action, $deskripsi_log);

        echo json_encode(['status' => 'success', 'message' => 'Data Kehadiran berhasil disimpan.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal.']);
}
