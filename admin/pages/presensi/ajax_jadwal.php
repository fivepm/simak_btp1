<?php
// === FILE BACKEND: ajax_jadwal.php ===
// Path config disesuaikan dengan struktur folder kamu
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';
// require_once '../../../helpers/fonnte_helper.php';
// require_once '../../../helpers/template_helper.php';

require_once '../../helpers/wa_gateway.php';
require_once '../../helpers/whatsapp_helper.php';
require_once '../../helpers/template_helper.php';

session_start();

// Set Header JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ==========================================================
// 1. GET DATA (Untuk Render Tabel)
// ==========================================================
if ($action === 'get_data') {
    $periode_id = (int)($_GET['periode_id'] ?? 0);
    $kelompok = $_GET['kelompok'] ?? '';
    $kelas = $_GET['kelas'] ?? '';

    if (!$periode_id || !$kelompok || !$kelas) {
        echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap.']);
        exit;
    }

    $response = ['status' => 'success', 'data' => ['jadwal_crud' => [], 'jadwal_rekap' => []]];

    // --- Data Tabel CRUD ---
    $sql_crud = "SELECT id, tanggal, jam_mulai, jam_selesai, pengajar FROM jadwal_presensi WHERE periode_id = ? AND kelompok = ? AND kelas = ? ORDER BY tanggal DESC, jam_mulai DESC";
    $stmt_crud = $conn->prepare($sql_crud);
    $stmt_crud->bind_param("iss", $periode_id, $kelompok, $kelas);
    $stmt_crud->execute();
    $res_crud = $stmt_crud->get_result();

    while ($row = $res_crud->fetch_assoc()) {
        $row['guru'] = [];
        $row['penasehat'] = [];

        // Ambil Guru
        $q_guru = $conn->query("SELECT g.id, g.nama FROM jadwal_guru jg JOIN guru g ON jg.guru_id = g.id WHERE jg.jadwal_id = {$row['id']}");
        while ($g = $q_guru->fetch_assoc()) $row['guru'][] = $g;

        // Ambil Penasehat
        $q_pen = $conn->query("SELECT p.id, p.nama FROM jadwal_penasehat jp JOIN penasehat p ON jp.penasehat_id = p.id WHERE jp.jadwal_id = {$row['id']}");
        while ($p = $q_pen->fetch_assoc()) $row['penasehat'][] = $p;

        $response['data']['jadwal_crud'][] = $row;
    }

    // --- Data Tabel REKAP ---
    $sql_rekap = "SELECT jp.id, jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.pengajar, 
                    GROUP_CONCAT(DISTINCT g.nama ORDER BY g.nama SEPARATOR ', ') as daftar_guru, 
                    GROUP_CONCAT(DISTINCT p.nama ORDER BY p.nama SEPARATOR ', ') as daftar_penasehat 
                  FROM jadwal_presensi jp 
                  LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id LEFT JOIN guru g ON jg.guru_id = g.id 
                  LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id LEFT JOIN penasehat p ON jn.penasehat_id = p.id 
                  WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? 
                  GROUP BY jp.id ORDER BY jp.tanggal ASC, jp.jam_mulai ASC";
    $stmt_rekap = $conn->prepare($sql_rekap);
    $stmt_rekap->bind_param("iss", $periode_id, $kelompok, $kelas);
    $stmt_rekap->execute();
    $res_rekap = $stmt_rekap->get_result();
    while ($row = $res_rekap->fetch_assoc()) {
        $response['data']['jadwal_rekap'][] = $row;
    }

    echo json_encode($response);
    exit;
}

// ==========================================================
// 2. PROSES POST (CRUD)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- TAMBAH JADWAL ---
    if ($action === 'tambah_jadwal') {
        $periode_id = $_POST['periode_id'];
        $kelompok = $_POST['kelompok'];
        $kelas = $_POST['kelas'];
        $tanggal = $_POST['tanggal'];
        $jam_mulai = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];

        if ($jam_mulai >= $jam_selesai) {
            echo json_encode(['status' => 'error', 'message' => 'Jam Selesai harus lebih dari Jam Mulai.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO jadwal_presensi (periode_id, kelompok, kelas, tanggal, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $periode_id, $kelompok, $kelas, $tanggal, $jam_mulai, $jam_selesai);
            $stmt->execute();
            $jadwal_id = $stmt->insert_id;

            // Generate Rekap Presensi Kosong
            $stmt_peserta = $conn->prepare("SELECT id FROM peserta WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'");
            $stmt_peserta->bind_param("ss", $kelompok, $kelas);
            $stmt_peserta->execute();
            $res_peserta = $stmt_peserta->get_result();
            if ($res_peserta->num_rows > 0) {
                $stmt_rekap = $conn->prepare("INSERT INTO rekap_presensi (jadwal_id, peserta_id) VALUES (?, ?)");
                while ($p = $res_peserta->fetch_assoc()) {
                    $stmt_rekap->bind_param("ii", $jadwal_id, $p['id']);
                    $stmt_rekap->execute();
                }
            }
            $conn->commit();
            writeLog('INSERT', "Menambahkan Jadwal ($kelompok - $kelas) tanggal $tanggal");
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- EDIT JADWAL ---
    if ($action === 'edit_jadwal') {
        $id = $_POST['jadwal_id'];
        $tanggal = $_POST['tanggal'];
        $jam_mulai = $_POST['jam_mulai'];
        $jam_selesai = $_POST['jam_selesai'];

        if ($jam_mulai >= $jam_selesai) {
            echo json_encode(['status' => 'error', 'message' => 'Jam Selesai harus lebih dari Jam Mulai.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE jadwal_presensi SET tanggal=?, jam_mulai=?, jam_selesai=? WHERE id=?");
        $stmt->bind_param("sssi", $tanggal, $jam_mulai, $jam_selesai, $id);
        if ($stmt->execute()) {
            // Kita biarkan Fonnte reminder terupdate manual (dihapus lalu diset lagi oleh user) 
            // Atau cukup update jadwalnya saja untuk menghindari bentrok logic Fonnte
            writeLog('UPDATE', "Edit Jadwal ID:$id menjadi $tanggal");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate jadwal.']);
        }
        exit;
    }

    // --- HAPUS JADWAL ---
    if ($action === 'hapus_jadwal') {
        $id = $_POST['jadwal_id'];
        $stmt = $conn->prepare("DELETE FROM jadwal_presensi WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Hapus juga pesan terjadwal yang terkait
            $conn->query("DELETE FROM pesan_terjadwal WHERE jadwal_id = $id");

            writeLog('DELETE', "Hapus Jadwal ID:$id");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus jadwal.']);
        }
        exit;
    }

    // --- TAMBAH GURU ---
    if ($action === 'tambah_guru_jadwal') {
        $jadwal_id = $_POST['jadwal_id'] ?? 0;
        $guru_id = $_POST['guru_id'] ?? 0;
        $jam_mulai_pengingat = $_POST['jam_mulai_pengingat'] ?? '';
        $jam_selesai_pengingat = $_POST['jam_selesai_pengingat'] ?? '';

        $conn->begin_transaction();
        try {
            // 1. Tambahkan guru ke jadwal
            $sql_insert = "INSERT INTO jadwal_guru (jadwal_id, guru_id) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ii", $jadwal_id, $guru_id);
            $stmt_insert->execute();

            // 2. Ambil data pesan
            $stmt_data = $conn->prepare("SELECT g.nama, g.nomor_wa, jp.tanggal, jp.kelas, jp.kelompok FROM guru g JOIN jadwal_presensi jp ON jp.id = ? WHERE g.id = ?");
            $stmt_data->bind_param("ii", $jadwal_id, $guru_id);
            $stmt_data->execute();
            $data_pesan = $stmt_data->get_result()->fetch_assoc();

            // 3. Buat pesan terjadwal Fonnte (SAMA PERSIS DENGAN atur_guru.php)
            if ($data_pesan && !empty($data_pesan['nomor_wa'])) {
                $jam_pengingat = 4; // Default
                $kelompok_jadwal = $data_pesan['kelompok'];
                $kelas_jadwal = $data_pesan['kelas'];

                // Cek aturan khusus
                $stmt_aturan = $conn->prepare("SELECT waktu_pengingat_jam FROM pengaturan_pengingat WHERE kelompok = ? AND kelas = ?");
                $stmt_aturan->bind_param("ss", $kelompok_jadwal, $kelas_jadwal);
                $stmt_aturan->execute();
                $result_aturan = $stmt_aturan->get_result();

                if ($result_aturan->num_rows > 0) {
                    $aturan = $result_aturan->fetch_assoc();
                    $jam_pengingat = (int)$aturan['waktu_pengingat_jam'];
                }
                $stmt_aturan->close();

                $waktu_kirim = date('Y-m-d H:i:s', strtotime($data_pesan['tanggal'] . ' ' . $jam_mulai_pengingat . " -{$jam_pengingat} hours"));

                $placeholders = [
                    '[nama]' => ucfirst($data_pesan['nama']),
                    '[kelas]' => ucfirst($data_pesan['kelas']),
                    '[kelompok]' => ucfirst($data_pesan['kelompok']),
                    '[tanggal]' => date('d M Y', strtotime($data_pesan['tanggal'])),
                    '[jam]' => $jam_mulai_pengingat . ' - ' . $jam_selesai_pengingat
                ];
                $pesan_final = getFormattedMessage($conn, 'pengingat_jadwal_guru', $data_pesan['kelas'], $data_pesan['kelompok'], $placeholders);

                $sql_pesan = "INSERT INTO pesan_terjadwal (jadwal_id, penerima_id, tipe_penerima, nomor_tujuan, isi_pesan, waktu_kirim, status) VALUES (?, ?, 'guru', ?, ?, ?, 'pending')";
                $stmt_pesan = $conn->prepare($sql_pesan);
                $stmt_pesan->bind_param("iisss", $jadwal_id, $guru_id, $data_pesan['nomor_wa'], $pesan_final, $waktu_kirim);
                $stmt_pesan->execute();
            }

            $conn->commit();
            writeLog('INSERT', "Mengatur Jadwal Guru ID: $guru_id untuk jadwal ID: $jadwal_id");
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Guru sudah ditugaskan atau terjadi error database.']);
        }
        exit;
    }

    // --- HAPUS GURU ---
    if ($action === 'hapus_guru_jadwal') {
        $jadwal_id = $_POST['jadwal_id'];
        $guru_id = $_POST['petugas_id'];
        $stmt = $conn->prepare("DELETE FROM jadwal_guru WHERE jadwal_id = ? AND guru_id = ?");
        $stmt->bind_param("ii", $jadwal_id, $guru_id);
        if ($stmt->execute()) {
            $conn->query("DELETE FROM pesan_terjadwal WHERE jadwal_id = $jadwal_id AND penerima_id = $guru_id AND tipe_penerima = 'guru'");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus guru.']);
        }
        exit;
    }

    // --- TAMBAH PENASEHAT ---
    if ($action === 'tambah_penasehat_jadwal') {
        $jadwal_id = $_POST['jadwal_id'] ?? 0;
        $pen_id = $_POST['penasehat_id'] ?? 0;
        $jam_mulai_pengingat = $_POST['jam_mulai_pengingat'] ?? '';

        $conn->begin_transaction();
        try {
            // 1. Insert ke jadwal_penasehat
            $stmt = $conn->prepare("INSERT INTO jadwal_penasehat (jadwal_id, penasehat_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $jadwal_id, $pen_id);
            $stmt->execute();

            // 2. Ambil data pesan
            $stmt_data = $conn->prepare("SELECT p.nama, p.nomor_wa, jp.tanggal, jp.kelas, jp.kelompok FROM penasehat p, jadwal_presensi jp WHERE jp.id = ? AND p.id = ?");
            $stmt_data->bind_param("ii", $jadwal_id, $pen_id);
            $stmt_data->execute();
            $data_pesan = $stmt_data->get_result()->fetch_assoc();

            // 3. Buat pesan terjadwal Fonnte (SAMA PERSIS DENGAN atur_penasehat.php)
            if ($data_pesan && !empty($data_pesan['nomor_wa'])) {
                $jam_pengingat = 4; // Default 4 jam
                $kelompok_jadwal = $data_pesan['kelompok'];
                $kelas_jadwal = $data_pesan['kelas'];

                $stmt_aturan = $conn->prepare("SELECT waktu_pengingat_jam FROM pengaturan_pengingat WHERE kelompok = ? AND kelas = ?");
                $stmt_aturan->bind_param("ss", $kelompok_jadwal, $kelas_jadwal);
                $stmt_aturan->execute();
                $result_aturan = $stmt_aturan->get_result();

                if ($result_aturan->num_rows > 0) {
                    $aturan = $result_aturan->fetch_assoc();
                    $jam_pengingat = (int)$aturan['waktu_pengingat_jam'];
                }
                $stmt_aturan->close();

                $waktu_kirim = date('Y-m-d H:i:s', strtotime($data_pesan['tanggal'] . ' ' . $jam_mulai_pengingat . " -{$jam_pengingat} hours"));

                $placeholders = [
                    '[nama]' => $data_pesan['nama'],
                    '[kelas]' => ucfirst($data_pesan['kelas']),
                    '[kelompok]' => ucfirst($data_pesan['kelompok']),
                    '[tanggal]' => date('d M Y', strtotime($data_pesan['tanggal'])),
                    '[jam]' => $jam_mulai_pengingat
                ];
                $pesan_final = getFormattedMessage($conn, 'pengingat_jadwal_penasehat', 'default', null, $placeholders);

                $sql_pesan = "INSERT INTO pesan_terjadwal (jadwal_id, penerima_id, tipe_penerima, nomor_tujuan, isi_pesan, waktu_kirim, status) VALUES (?, ?, 'penasehat', ?, ?, ?, 'pending')";
                $stmt_pesan = $conn->prepare($sql_pesan);
                $stmt_pesan->bind_param("iisss", $jadwal_id, $pen_id, $data_pesan['nomor_wa'], $pesan_final, $waktu_kirim);
                $stmt_pesan->execute();
            }

            $conn->commit();
            writeLog('INSERT', "Mengatur Jadwal Penasehat ID: $pen_id untuk jadwal ID: $jadwal_id");
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Penasehat sudah ditugaskan atau terjadi error database.']);
        }
        exit;
    }

    // --- HAPUS PENASEHAT ---
    if ($action === 'hapus_penasehat_jadwal') {
        $jadwal_id = $_POST['jadwal_id'];
        $pen_id = $_POST['petugas_id'];
        $stmt = $conn->prepare("DELETE FROM jadwal_penasehat WHERE jadwal_id = ? AND penasehat_id = ?");
        $stmt->bind_param("ii", $jadwal_id, $pen_id);
        if ($stmt->execute()) {
            $conn->query("DELETE FROM pesan_terjadwal WHERE jadwal_id = $jadwal_id AND penerima_id = $pen_id AND tipe_penerima = 'penasehat'");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus penasehat.']);
        }
        exit;
    }
}
