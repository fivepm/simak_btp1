<?php
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$DATA_KELOMPOK = [1 => 'Bintaran', 2 => 'Gedongkuning', 3 => 'Jombor', 4 => 'Sunten'];

// ==========================================================
// 1. GET DATA (Merekap Data Kelompok & Memuat Form Desa)
// ==========================================================
if ($action === 'get_laporan_desa') {
    $periode_id = (int)($_GET['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    try {
        // --- A. VALIDASI SYARAT: Pastikan SEMUA (4) Kelompok sudah TTD ---
        // UPDATE: Ambil juga checklist_musyawarah untuk ditampilkan di detail tiap kelompok
        $q_cek = $conn->prepare("SELECT kelompok_id, status, checklist_musyawarah, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ?");
        $q_cek->bind_param("i", $periode_id);
        $q_cek->execute();
        $res_cek = $q_cek->get_result();

        if ($res_cek->num_rows < count($DATA_KELOMPOK)) {
            throw new Exception("Gagal. Belum semua kelompok dibuatkan laporannya di periode ini.");
        }

        $semua_detail_kelas = [];
        $semua_permasalahan = [];
        $raw_kelompok_data = []; // Untuk disajikan ke Expander Frontend

        while ($row = $res_cek->fetch_assoc()) {
            $nama_kel = $DATA_KELOMPOK[$row['kelompok_id']] ?? 'Unknown';
            if ($row['status'] !== 'TTD_KETUA') {
                throw new Exception("Laporan desa belum bisa dibuat. Laporan Kelompok {$nama_kel} belum ditandatangani oleh Ketuanya.");
            }

            $detail_k = json_decode($row['detail_kelas'], true) ?: [];
            $masalah_k = json_decode($row['permasalahan'], true) ?: [];
            $checklist_k = json_decode($row['checklist_musyawarah'], true) ?: ['pjp' => false, 'unsur' => false];

            $semua_detail_kelas[] = $detail_k;
            $semua_permasalahan[] = [
                'kelompok' => $nama_kel,
                'masalah' => $masalah_k
            ];

            // Simpan data mentah kelompok untuk ditampilkan utuh di Frontend
            $raw_kelompok_data[] = [
                'nama_kelompok' => $nama_kel,
                'checklist' => $checklist_k,
                'detail_kelas' => $detail_k,
                'permasalahan' => $masalah_k
            ];
        }
        $q_cek->close();

        // --- B. CEK APAKAH LAPORAN DESA SUDAH PERNAH DISIMPAN SEBELUMNYA ---
        $q_desa = $conn->prepare("SELECT id, status, data_kepengurusan_desa, rekap_kelompok FROM laporan_pjp_desa WHERE periode_id = ? LIMIT 1");
        $q_desa->bind_param("i", $periode_id);
        $q_desa->execute();
        $res_desa = $q_desa->get_result();

        $laporan_desa_id = null;
        $status_desa = 'DRAFT';
        $rekap_kelompok_final = [];
        $kepengurusan_desa = [];

        if ($res_desa->num_rows > 0) {
            $row_desa = $res_desa->fetch_assoc();
            $laporan_desa_id = $row_desa['id'];
            $status_desa = $row_desa['status'];
            $kepengurusan_desa = json_decode($row_desa['data_kepengurusan_desa'], true);
            $rekap_kelompok_final = json_decode($row_desa['rekap_kelompok'], true);
        } else {
            // Rekap dan Hitung Rata-rata Kelas Tingkat Desa
            $rekap_kelas = [];
            foreach ($semua_detail_kelas as $kelompok_kelas) {
                foreach ($kelompok_kelas as $kls) {
                    $nama = $kls['nama_kelas'];
                    if (!isset($rekap_kelas[$nama])) {
                        $rekap_kelas[$nama] = [
                            'nama_kelas' => $nama,
                            'jml_siswa' => 0,
                            'jml_guru' => 0,
                            'kehadiran' => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0],
                            'ketercapaian_global' => 0,
                            'ketercapaian_kategori' => [],
                            'count' => 0
                        ];
                    }
                    $rekap_kelas[$nama]['jml_siswa'] += (int)$kls['jml_siswa'];
                    $rekap_kelas[$nama]['jml_guru'] += (int)$kls['jml_guru'];
                    $rekap_kelas[$nama]['kehadiran']['hadir'] += (float)$kls['kehadiran']['hadir'];
                    $rekap_kelas[$nama]['kehadiran']['izin'] += (float)$kls['kehadiran']['izin'];
                    $rekap_kelas[$nama]['kehadiran']['sakit'] += (float)$kls['kehadiran']['sakit'];
                    $rekap_kelas[$nama]['kehadiran']['alpa'] += (float)$kls['kehadiran']['alpa'];
                    $rekap_kelas[$nama]['ketercapaian_global'] += (float)$kls['ketercapaian_global'];

                    foreach ($kls['ketercapaian_kategori'] as $kat => $val) {
                        if (!isset($rekap_kelas[$nama]['ketercapaian_kategori'][$kat])) $rekap_kelas[$nama]['ketercapaian_kategori'][$kat] = 0;
                        $rekap_kelas[$nama]['ketercapaian_kategori'][$kat] += (float)$val;
                    }
                    $rekap_kelas[$nama]['count']++;
                }
            }

            $hasil_rekap_kelas = [];
            foreach ($rekap_kelas as $r) {
                $c = $r['count'] > 0 ? $r['count'] : 1;
                $kat_avg = [];
                foreach ($r['ketercapaian_kategori'] as $kat => $val) {
                    $kat_avg[$kat] = round($val / $c);
                }
                $hasil_rekap_kelas[] = [
                    'nama_kelas' => $r['nama_kelas'],
                    'jml_siswa' => $r['jml_siswa'],
                    'jml_guru' => $r['jml_guru'],
                    'kehadiran' => [
                        'hadir' => round($r['kehadiran']['hadir'] / $c),
                        'izin' => round($r['kehadiran']['izin'] / $c),
                        'sakit' => round($r['kehadiran']['sakit'] / $c),
                        'alpa' => round($r['kehadiran']['alpa'] / $c)
                    ],
                    'ketercapaian_global' => round($r['ketercapaian_global'] / $c),
                    'ketercapaian_kategori' => $kat_avg
                ];
            }

            $rekap_kelompok_final = [
                'detail_kelas' => $hasil_rekap_kelas,
                'permasalahan' => $semua_permasalahan,
                'catatan_desa' => ''
            ];

            // Query Pengurus Tingkat Desa
            $nama_pembina = '-';
            $nama_ketua = '-';
            $nama_wakil = '-';
            $arr_sekretaris = [];
            $arr_bendahara = [];
            $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'desa' AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara')");
            $q_pengurus->execute();
            $res_pengurus = $q_pengurus->get_result();
            if ($res_pengurus) {
                while ($p = $res_pengurus->fetch_assoc()) {
                    $jabatan = strtolower(trim($p['jabatan']));
                    if ($jabatan === 'pengawas') $nama_pembina = $p['nama_pengurus'];
                    elseif ($jabatan === 'ketua') $nama_ketua = $p['nama_pengurus'];
                    elseif ($jabatan === 'wakil') $nama_wakil = $p['nama_pengurus'];
                    elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'bendahara') $arr_bendahara[] = $p['nama_pengurus'];
                }
            }
            $q_pengurus->close();

            $kepengurusan_desa = [
                'pembina' => $nama_pembina,
                'ketua' => $nama_ketua,
                'wakil' => $nama_wakil,
                'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
                'bendahara' => !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-'
            ];
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => [
            'laporan_desa_id' => $laporan_desa_id,
            'status' => $status_desa,
            'kepengurusan' => $kepengurusan_desa,
            'rekap_kelompok' => $rekap_kelompok_final,
            'detail_tiap_kelompok' => $raw_kelompok_data // <--- DATA BARU UNTUK UI EXPANDER
        ]]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. POST DATA (Simpan Laporan Desa)
// ==========================================================
elseif ($action === 'simpan_laporan_desa') {
    $periode_id = (int)($_POST['periode_id'] ?? 0);
    $laporan_desa_id = $_POST['laporan_desa_id'] ? (int)$_POST['laporan_desa_id'] : null;
    $target_status = $_POST['status'] ?? 'DRAFT';
    $json_kepengurusan = $_POST['kepengurusan'] ?? '{}';
    $json_rekap = $_POST['rekap_kelompok'] ?? '{}';

    $conn->begin_transaction();
    try {
        if ($laporan_desa_id) {
            $cek = $conn->query("SELECT status FROM laporan_pjp_desa WHERE id = $laporan_desa_id");
            if ($cek->fetch_assoc()['status'] === 'TTD_KETUA') {
                throw new Exception("Laporan sudah disahkan oleh Ketua Desa, tidak dapat diubah lagi.");
            }
            $stmt = $conn->prepare("UPDATE laporan_pjp_desa SET status = ?, data_kepengurusan_desa = ?, rekap_kelompok = ? WHERE id = ?");
            $stmt->bind_param("sssi", $target_status, $json_kepengurusan, $json_rekap, $laporan_desa_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO laporan_pjp_desa (periode_id, status, data_kepengurusan_desa, rekap_kelompok) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $periode_id, $target_status, $json_kepengurusan, $json_rekap);
            $stmt->execute();
        }
        $conn->commit();
        writeLog('UPDATE', "Admin Desa menyimpan Laporan PJP Desa (Periode ID: $periode_id) dengan status: $target_status");

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => $target_status === 'FINAL' ? 'Laporan Desa berhasil difinalkan.' : 'Draft Laporan Desa berhasil disimpan.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 3. POST DATA (Refresh Sinkronisasi Laporan Desa)
// ==========================================================
elseif ($action === 'refresh_data') {
    $laporan_desa_id = (int)($_POST['laporan_desa_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $cek = $conn->prepare("SELECT periode_id, status, rekap_kelompok FROM laporan_pjp_desa WHERE id = ?");
        $cek->bind_param("i", $laporan_desa_id);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) throw new Exception("Laporan desa tidak ditemukan.");
        $row_cek = $res_cek->fetch_assoc();
        if ($row_cek['status'] !== 'DRAFT') throw new Exception("Hanya laporan DRAFT yang dapat disinkronkan ulang.");

        $periode_id = $row_cek['periode_id'];
        $old_rekap = json_decode($row_cek['rekap_kelompok'], true) ?: [];
        $catatan_lama = $old_rekap['catatan_desa'] ?? '';

        $q_kel = $conn->prepare("SELECT kelompok_id, status, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ?");
        $q_kel->bind_param("i", $periode_id);
        $q_kel->execute();
        $res_kel = $q_kel->get_result();

        $semua_detail_kelas = [];
        $semua_permasalahan = [];

        while ($row = $res_kel->fetch_assoc()) {
            $nama_kel = $DATA_KELOMPOK[$row['kelompok_id']] ?? 'Unknown';
            if ($row['status'] !== 'TTD_KETUA') throw new Exception("Refresh dibatalkan. Kelompok ({$nama_kel}) tidak lagi berstatus TTD_KETUA.");
            $semua_detail_kelas[] = json_decode($row['detail_kelas'], true) ?: [];
            $semua_permasalahan[] = ['kelompok' => $nama_kel, 'masalah' => json_decode($row['permasalahan'], true) ?: []];
        }
        $q_kel->close();

        // Hitung ulang rekap desa
        $rekap_kelas = [];
        foreach ($semua_detail_kelas as $kelompok_kelas) {
            foreach ($kelompok_kelas as $kls) {
                $nama = $kls['nama_kelas'];
                if (!isset($rekap_kelas[$nama])) {
                    $rekap_kelas[$nama] = ['nama_kelas' => $nama, 'jml_siswa' => 0, 'jml_guru' => 0, 'kehadiran' => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0], 'ketercapaian_global' => 0, 'ketercapaian_kategori' => [], 'count' => 0];
                }
                $rekap_kelas[$nama]['jml_siswa'] += (int)$kls['jml_siswa'];
                $rekap_kelas[$nama]['jml_guru'] += (int)$kls['jml_guru'];
                $rekap_kelas[$nama]['kehadiran']['hadir'] += (float)$kls['kehadiran']['hadir'];
                $rekap_kelas[$nama]['kehadiran']['izin'] += (float)$kls['kehadiran']['izin'];
                $rekap_kelas[$nama]['kehadiran']['sakit'] += (float)$kls['kehadiran']['sakit'];
                $rekap_kelas[$nama]['kehadiran']['alpa'] += (float)$kls['kehadiran']['alpa'];
                $rekap_kelas[$nama]['ketercapaian_global'] += (float)$kls['ketercapaian_global'];
                foreach ($kls['ketercapaian_kategori'] as $kat => $val) {
                    if (!isset($rekap_kelas[$nama]['ketercapaian_kategori'][$kat])) $rekap_kelas[$nama]['ketercapaian_kategori'][$kat] = 0;
                    $rekap_kelas[$nama]['ketercapaian_kategori'][$kat] += (float)$val;
                }
                $rekap_kelas[$nama]['count']++;
            }
        }

        $hasil_rekap_kelas = [];
        foreach ($rekap_kelas as $r) {
            $c = $r['count'] > 0 ? $r['count'] : 1;
            $kat_avg = [];
            foreach ($r['ketercapaian_kategori'] as $kat => $val) $kat_avg[$kat] = round($val / $c);
            $hasil_rekap_kelas[] = [
                'nama_kelas' => $r['nama_kelas'],
                'jml_siswa' => $r['jml_siswa'],
                'jml_guru' => $r['jml_guru'],
                'kehadiran' => ['hadir' => round($r['kehadiran']['hadir'] / $c), 'izin' => round($r['kehadiran']['izin'] / $c), 'sakit' => round($r['kehadiran']['sakit'] / $c), 'alpa' => round($r['kehadiran']['alpa'] / $c)],
                'ketercapaian_global' => round($r['ketercapaian_global'] / $c),
                'ketercapaian_kategori' => $kat_avg
            ];
        }

        $rekap_kelompok_final = ['detail_kelas' => $hasil_rekap_kelas, 'permasalahan' => $semua_permasalahan, 'catatan_desa' => $catatan_lama];
        $json_rekap = json_encode($rekap_kelompok_final);

        // Tarik Ulang Pengurus Desa
        $nama_pembina = '-';
        $nama_ketua = '-';
        $nama_wakil = '-';
        $arr_sekretaris = [];
        $arr_bendahara = [];
        $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'desa' AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara')");
        $q_pengurus->execute();
        if ($res_pengurus = $q_pengurus->get_result()) {
            while ($p = $res_pengurus->fetch_assoc()) {
                $jabatan = strtolower(trim($p['jabatan']));
                if ($jabatan === 'pengawas') $nama_pembina = $p['nama_pengurus'];
                elseif ($jabatan === 'ketua') $nama_ketua = $p['nama_pengurus'];
                elseif ($jabatan === 'wakil') $nama_wakil = $p['nama_pengurus'];
                elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                elseif ($jabatan === 'bendahara') $arr_bendahara[] = $p['nama_pengurus'];
            }
        }
        $json_kepengurusan = json_encode(['pembina' => $nama_pembina, 'ketua' => $nama_ketua, 'wakil' => $nama_wakil, 'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-', 'bendahara' => !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-']);

        $stmt_update = $conn->prepare("UPDATE laporan_pjp_desa SET data_kepengurusan_desa = ?, rekap_kelompok = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $json_kepengurusan, $json_rekap, $laporan_desa_id);
        $stmt_update->execute();

        $conn->commit();
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Data rekap desa berhasil disinkronkan dengan laporan kelompok terbaru.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
