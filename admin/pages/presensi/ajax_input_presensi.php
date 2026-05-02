<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';
require_once '../../helpers/wa_gateway.php';
require_once '../../helpers/whatsapp_helper.php';
require_once '../../helpers/template_helper.php';

header('Content-Type: application/json');

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

$jadwal = $conn->query("SELECT * FROM jadwal_presensi WHERE id = $jadwal_id")->fetch_assoc();
if (!$jadwal) {
    echo json_encode(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.']);
    exit;
}

$tanggal_jadwal = date("d M Y", strtotime($jadwal['tanggal']));
$kelas_jadwal = $jadwal['kelas'];
$kelompok_jadwal = $jadwal['kelompok'];
$periode_id = $jadwal['periode_id'] ?? 0;

/**
 * Mendekode nilai desimal halaman+baris kembali ke format manusiawi.
 * Frontend menyimpan:
 *   capaian_start = halamanMulai + (barisMulai - 1) * 0.1
 *   capaian_end   = halamanAkhir + barisAkhir * 0.1
 *
 * @param float $val    Nilai desimal yang disimpan
 * @param bool $isStart true untuk capaian_start, false untuk capaian_end
 * @return array ['halaman' => int, 'baris' => int]
 */
function decodeHalaman(float $val, bool $isStart = false): array {
    $halaman = (int)floor($val);
    $baris   = (int)round(($val - $halaman) * 10);
    if ($isStart) $baris += 1; // barisMulai disimpan sebagai (baris-1)*0.1
    return ['halaman' => $halaman, 'baris' => $baris];
}

/**
 * Format teks "Hal. X Baris Y" dari nilai desimal.
 */
function formatHalamanBaris(float $val, bool $isStart = false): string {
    $d = decodeHalaman($val, $isStart);
    return "Hal. {$d['halaman']} Baris {$d['baris']}";
}

try {
    // =======================================================================
    // 1. GET LIST KATEGORI
    // =======================================================================
    if ($action === 'get_kategori_list') {
        $sql = "SELECT DISTINCT kategori, tipe_input FROM target_pembelajaran 
                WHERE periode_id = ? 
                AND (kelas = ? OR kelas = 'Semua') 
                AND (kelompok = ? OR kelompok = 'Semua')
                ORDER BY kategori ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $periode_id, $kelas_jadwal, $kelompok_jadwal);
        $stmt->execute();
        $res = $stmt->get_result();

        $kategori = [];
        while ($row = $res->fetch_assoc()) {
            $kategori[] = $row;
        }

        echo json_encode(['status' => 'success', 'data' => $kategori]);
    }

    // =======================================================================
    // 2. GET LIST TARGET BY KATEGORI
    // =======================================================================
    elseif ($action === 'get_target_by_kategori') {
        $kategori_filter = $_POST['kategori'] ?? '';

        $sql = "SELECT * FROM target_pembelajaran 
                WHERE periode_id = ? 
                AND kategori = ?
                AND (kelas = ? OR kelas = 'Semua') 
                AND (kelompok = ? OR kelompok = 'Semua')
                ORDER BY judul_materi ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $periode_id, $kategori_filter, $kelas_jadwal, $kelompok_jadwal);
        $stmt->execute();
        $res = $stmt->get_result();

        $targets = [];
        while ($row = $res->fetch_assoc()) {
            $cek = $conn->query("SELECT id FROM jurnal_materi WHERE jadwal_id = $jadwal_id AND target_id = " . $row['id']);
            $row['is_filled_today'] = ($cek->num_rows > 0);
            $targets[] = $row;
        }

        echo json_encode(['status' => 'success', 'data' => $targets]);
    }

    // =======================================================================
    // 3. SIMPAN DETAIL MATERI (Kurikulum Utama)
    // =======================================================================
    elseif ($action === 'simpan_materi_detail') {
        $target_ids = $_POST['target_id'] ?? [];
        $tipe_input = $_POST['tipe_input'] ?? '';

        if (empty($target_ids)) throw new Exception('Materi/Target belum dipilih.');

        if (!is_array($target_ids)) $target_ids = [$target_ids];

        foreach ($target_ids as $target_id) {
            $q_target = $conn->query("SELECT * FROM target_pembelajaran WHERE id = $target_id")->fetch_assoc();
            if (!$q_target) continue;

            $nama_materi = $q_target['judul_materi'];
            $capaian_start = 0;
            $capaian_end = 0;
            $volume_capaian = 0;
            $catatan_tambahan = $_POST['catatan_tambahan'] ?? '';
            $log_detail = "";

            if ($tipe_input === 'RANGE') {
                $capaian_start = floatval($_POST['capaian_start']);
                $capaian_end   = floatval($_POST['capaian_end']);
                $satuan        = $q_target['satuan'] ?? '';

                if ($capaian_end < $capaian_start) {
                    throw new Exception("Error pada $nama_materi: Akhir tidak boleh lebih kecil dari awal.");
                }

                $batas_awal  = floatval($q_target['target_start']);
                $batas_akhir = floatval($q_target['target_end']);

                if ($satuan === 'Halaman') {
                    // Untuk satuan Halaman, validasi batas menggunakan angka halaman (floor)
                    $hal_start = decodeHalaman($capaian_start, true)['halaman'];
                    $hal_end   = decodeHalaman($capaian_end, false)['halaman'];

                    if ($hal_start < $batas_awal) {
                        throw new Exception("Error pada $nama_materi: Halaman awal ($hal_start) kurang dari target admin ($batas_awal).");
                    }
                    if ($hal_end > $batas_akhir) {
                        throw new Exception("Error pada $nama_materi: Halaman akhir ($hal_end) melebihi batas target admin ($batas_akhir).");
                    }

                    // Rumus: volume = capaian_end - capaian_start (sudah mengandung perhitungan baris)
                    // Contoh: Hal.1 Baris1 s/d Hal.3 Baris2 → (3.2 - 1.0) = 2.2 Halaman
                    $volume_capaian = round($capaian_end - $capaian_start, 1);

                    $log_detail = "(" . formatHalamanBaris($capaian_start, true) . " s/d " . formatHalamanBaris($capaian_end, false) . " = {$volume_capaian} Halaman)";
                } else {
                    // Satuan selain Halaman: validasi langsung, volume = end - start + 1
                    if ($capaian_start < $batas_awal) {
                        throw new Exception("Error pada $nama_materi: Awal ($capaian_start) kurang dari target admin ($batas_awal).");
                    }
                    if ($capaian_end > $batas_akhir) {
                        throw new Exception("Error pada $nama_materi: Akhir ($capaian_end) melebihi batas target admin ($batas_akhir).");
                    }

                    $volume_capaian = ($capaian_end - $capaian_start) + 1;
                    $log_detail = "(" . (float)$capaian_start . " - " . (float)$capaian_end . ")";
                }
            } elseif ($tipe_input === 'CHECKLIST') {
                $volume_capaian = 1;
                $cek = $conn->query("SELECT id FROM jurnal_materi WHERE jadwal_id=$jadwal_id AND target_id=$target_id");
                if ($cek->num_rows > 0) continue;
                $log_detail = "(Checklist)";
            } elseif ($tipe_input === 'MANUAL') {
                $volume_capaian = floatval($_POST['volume_manual']);
                $log_detail = "Volume: " . (float)$volume_capaian . " " . ($q_target['satuan'] ?? '');
            }

            $sql_ins = "INSERT INTO jurnal_materi (jadwal_id, target_id, capaian_start, capaian_end, volume_capaian, catatan_tambahan) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_ins);
            $stmt->bind_param("iiddds", $jadwal_id, $target_id, $capaian_start, $capaian_end, $volume_capaian, $catatan_tambahan);

            if ($stmt->execute()) {
                $deskripsi_log = "Menambahkan materi *$nama_materi* $log_detail pada jurnal $kelas_jadwal ($kelompok_jadwal).";
                writeLog('INSERT', $deskripsi_log);
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Materi berhasil disimpan.']);
    }

    // =======================================================================
    // 4. SIMPAN MATERI TAMBAHAN (GANTI NASEHAT)
    // =======================================================================
    elseif ($action === 'simpan_materi_tambahan') {
        $judul = trim($_POST['judul_materi'] ?? '');
        $pemateri = trim($_POST['pemateri'] ?? '');
        $ket = trim($_POST['keterangan'] ?? '');

        if (empty($judul)) throw new Exception('Judul materi tambahan wajib diisi.');
        if (empty($pemateri)) throw new Exception('Nama pemateri wajib diisi.');

        $stmt = $conn->prepare("INSERT INTO jurnal_tambahan (jadwal_id, judul_materi, pemateri, keterangan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $jadwal_id, $judul, $pemateri, $ket);

        if ($stmt->execute()) {
            writeLog('UPDATE', "Menambahkan materi tambahan *$judul* (Oleh: $pemateri) pada jurnal $kelas_jadwal.");
            echo json_encode(['status' => 'success', 'message' => 'Materi tambahan berhasil disimpan.']);
        } else {
            throw new Exception("Gagal menyimpan: " . $stmt->error);
        }
    }

    // =======================================================================
    // 5. HAPUS MATERI & TAMBAHAN
    // =======================================================================
    elseif ($action === 'hapus_materi_detail') {
        $jurnal_materi_id = $_POST['jurnal_materi_id'] ?? 0;

        $q_old = $conn->query("SELECT tp.judul_materi FROM jurnal_materi jm JOIN target_pembelajaran tp ON jm.target_id = tp.id WHERE jm.id = $jurnal_materi_id");
        $old_data = $q_old->fetch_assoc();
        $nama_terhapus = $old_data['judul_materi'] ?? 'Item';

        $stmt = $conn->prepare("DELETE FROM jurnal_materi WHERE id = ? AND jadwal_id = ?");
        $stmt->bind_param("ii", $jurnal_materi_id, $jadwal_id);

        if ($stmt->execute()) {
            writeLog('DELETE', "Menghapus materi *$nama_terhapus* dari jurnal $kelas_jadwal.");
            echo json_encode(['status' => 'success', 'message' => 'Item materi dihapus.']);
        } else {
            throw new Exception("Gagal menghapus item.");
        }
    } elseif ($action === 'hapus_materi_tambahan') {
        $id = $_POST['id'] ?? 0;

        $q_old = $conn->query("SELECT judul_materi FROM jurnal_tambahan WHERE id = $id");
        $old = $q_old->fetch_assoc();
        $judul_hapus = $old['judul_materi'] ?? 'Tambahan';

        $stmt = $conn->prepare("DELETE FROM jurnal_tambahan WHERE id = ? AND jadwal_id = ?");
        $stmt->bind_param("ii", $id, $jadwal_id);

        if ($stmt->execute()) {
            writeLog('DELETE', "Menghapus materi tambahan *$judul_hapus*.");
            echo json_encode(['status' => 'success', 'message' => 'Materi tambahan dihapus.']);
        } else {
            throw new Exception("Gagal menghapus.");
        }
    }

    // =======================================================================
    // 6. LOAD JURNAL HARI INI (GABUNGAN UTAMA + TAMBAHAN)
    // =======================================================================
    elseif ($action === 'load_jurnal_hari_ini') {
        $data = [];

        // A. Ambil Materi Kurikulum
        $sql = "SELECT jm.*, tp.judul_materi, tp.kategori, tp.tipe_input, tp.satuan 
                FROM jurnal_materi jm
                JOIN target_pembelajaran tp ON jm.target_id = tp.id
                WHERE jm.jadwal_id = ?
                ORDER BY jm.created_at ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $jadwal_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $teks_capaian = "";
            $v_start = (float)$row['capaian_start'];
            $v_end = (float)$row['capaian_end'];
            $v_vol = (float)$row['volume_capaian'];

            if ($row['tipe_input'] == 'RANGE') {
                if ($row['satuan'] === 'Halaman') {
                    $teks_start = formatHalamanBaris($v_start, true);
                    $teks_end   = formatHalamanBaris($v_end, false);
                    $teks_capaian = "{$teks_start} s/d {$teks_end} ({$v_vol} Halaman)";
                } else {
                    $teks_capaian = "{$row['satuan']} {$v_start} - {$v_end} ({$v_vol} {$row['satuan']})";
                }
            } elseif ($row['tipe_input'] == 'CHECKLIST') {
                $teks_capaian = "✔ Tercapai";
            } else {
                $teks_capaian = "{$v_vol} {$row['satuan']}";
            }
            $row['teks_capaian'] = $teks_capaian;
            $row['is_tambahan'] = false;
            $data[] = $row;
        }

        // B. Ambil Materi Tambahan (Tabel Baru)
        $sql_add = "SELECT * FROM jurnal_tambahan WHERE jadwal_id = ? ORDER BY created_at ASC";
        $stmt_add = $conn->prepare($sql_add);
        $stmt_add->bind_param("i", $jadwal_id);
        $stmt_add->execute();
        $res_add = $stmt_add->get_result();

        while ($row = $res_add->fetch_assoc()) {
            $data[] = [
                'id' => $row['id'],
                'kategori' => 'Tambahan',
                'judul_materi' => $row['judul_materi'],
                'teks_capaian' => "Oleh: " . $row['pemateri'],
                'catatan_tambahan' => $row['keterangan'],
                'is_tambahan' => true
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // =======================================================================
    // 7. SIMPAN JURNAL HEADER (WA UPDATE)
    // =======================================================================
    elseif ($action === 'simpan_jurnal') {
        $pengajar = $_POST['pengajar'] ?? '';
        if (empty($pengajar)) throw new Exception('Nama Pengajar wajib diisi.');

        $sql = "UPDATE jadwal_presensi SET pengajar=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $pengajar, $jadwal_id);

        if ($stmt->execute()) {
            writeLog('UPDATE', "Menyelesaikan *Jurnal Harian* ($pengajar) untuk kelompok *" . ucwords($kelompok_jadwal) . "*.");

            // --- GENERATE PESAN WA ---
            $resume_materi = "";

            // 1. Materi Kurikulum
            $q_res = $conn->query("SELECT jm.*, tp.judul_materi, tp.kategori, tp.satuan, tp.tipe_input 
                                   FROM jurnal_materi jm
                                   JOIN target_pembelajaran tp ON jm.target_id = tp.id
                                   WHERE jm.jadwal_id = $jadwal_id
                                   ORDER BY tp.kategori ASC, jm.id ASC");

            if ($q_res->num_rows > 0) {
                $current_cat = "";
                while ($m = $q_res->fetch_assoc()) {
                    if ($current_cat != $m['kategori']) {
                        $current_cat = $m['kategori'];
                        $resume_materi .= "\n📚 *[" . strtoupper($current_cat) . "]*\n";
                    }
                    $v_start = (float)$m['capaian_start'];
                    $v_end = (float)$m['capaian_end'];
                    $v_vol = (float)$m['volume_capaian'];

                    if ($m['tipe_input'] == 'RANGE') {
                        if ($m['satuan'] === 'Halaman') {
                            $teks_start = formatHalamanBaris($v_start, true);
                            $teks_end   = formatHalamanBaris($v_end, false);
                            $resume_materi .= "• " . $m['judul_materi'] . ": {$teks_start} s/d {$teks_end} ({$v_vol} Halaman)\n";
                        } else {
                            $resume_materi .= "• " . $m['judul_materi'] . ": " . $v_start . "-" . $v_end . "\n";
                        }
                    } elseif ($m['tipe_input'] == 'CHECKLIST') {
                        $resume_materi .= "• " . $m['judul_materi'] . "\n";
                    } else {
                        $resume_materi .= "• " . $m['judul_materi'] . ": " . $v_vol . " " . $m['satuan'] . "\n";
                    }
                    if (!empty($m['catatan_tambahan'])) {
                        $resume_materi .= "  _(Ket: " . $m['catatan_tambahan'] . ")_\n";
                    }
                }
            } else {
                $resume_materi = "_Belum ada materi kurikulum diinput_\n";
            }

            // 2. Materi Tambahan
            $q_add = $conn->query("SELECT * FROM jurnal_tambahan WHERE jadwal_id = $jadwal_id");
            if ($q_add->num_rows > 0) {
                $resume_materi .= "\n💡 *[MATERI TAMBAHAN]*\n";
                while ($add = $q_add->fetch_assoc()) {
                    $resume_materi .= "• " . $add['judul_materi'] . "\n";
                    $resume_materi .= "  Oleh: " . $add['pemateri'] . "\n";
                    if (!empty($add['keterangan'])) {
                        $resume_materi .= "  _(Ket: " . $add['keterangan'] . ")_\n";
                    }
                }
            }

            if (function_exists('getGroupId') && function_exists('kirimWhatsApp')) {
                $target_group_id = getGroupId($conn, $jadwal['kelompok'], $jadwal['kelas']);
                $data_untuk_pesan = [
                    '[nama]' => $pengajar,
                    '[tanggal]' => $tanggal_jadwal,
                    '[kelas]' => ucfirst($kelas_jadwal),
                    '[kelompok]' => ucfirst($kelompok_jadwal),
                    '[materi1]' => $resume_materi,
                    '[materi2]' => '',
                    '[materi3]' => ''
                ];
                $pesan_final = getFormattedMessage($conn, 'jurnal_harian', $kelas_jadwal, $kelompok_jadwal, $data_untuk_pesan);
                kirimWhatsApp($target_group_id, $pesan_final);
            }
            echo json_encode(['status' => 'success', 'message' => 'Jurnal disimpan & Notifikasi terkirim!']);
        } else {
            throw new Exception("Gagal update jurnal: " . $stmt->error);
        }
    } elseif ($action === 'simpan_kehadiran') {
        $kehadiran_data = $_POST['kehadiran'] ?? [];
        $keterangan_data = $_POST['keterangan'] ?? [];

        if (empty($kehadiran_data)) throw new Exception('Tidak ada data kehadiran yang dikirim.');

        $conn->begin_transaction();
        $sql = "UPDATE rekap_presensi SET status_kehadiran = ?, keterangan = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stat_counter = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];

        foreach ($kehadiran_data as $rekap_id => $status) {
            $keterangan = $keterangan_data[$rekap_id] ?? '';
            if (($status === 'Izin' || $status === 'Sakit') && empty($keterangan)) {
                throw new Exception("Keterangan wajib diisi untuk siswa yang Izin/Sakit.");
            }
            $stmt->bind_param("ssi", $status, $keterangan, $rekap_id);
            $stmt->execute();
            if (isset($stat_counter[$status])) $stat_counter[$status]++;
        }
        $conn->commit();
        $summary = [];
        foreach ($stat_counter as $k => $v) if ($v > 0) $summary[] = "$k: $v";
        writeLog('UPDATE', "Menyimpan Presensi $kelompok_jadwal ($tanggal_jadwal) - " . implode(", ", $summary));
        echo json_encode(['status' => 'success', 'message' => 'Data Kehadiran berhasil disimpan.']);
    } else {
        throw new Exception("Action tidak dikenal.");
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}