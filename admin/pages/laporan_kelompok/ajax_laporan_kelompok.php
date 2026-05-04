<?php
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

// Cek Sesi Login
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

// Ambil nama kelompok admin dari session
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';
$nama_kelompok_login = $admin_kelompok;

// Mapping nama kelompok berdasarkan ID (Definisi manual)
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];

// Mencari kelompok_id berupa angka berdasarkan nama kelompok dari session
$kelompok_id_login = array_search($nama_kelompok_login, $DATA_KELOMPOK);

if (!$kelompok_id_login) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Data kelompok Anda tidak valid atau tidak ditemukan.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ==========================================================
// 1. GET DATA (Memuat Data Laporan ke Form)
// ==========================================================
if ($action === 'get_laporan') {
    $periode_id = (int)($_GET['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT id, status, checklist_musyawarah, data_kepengurusan, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ? AND kelompok_id = ? LIMIT 1");
        $stmt->bind_param("ii", $periode_id, $kelompok_id_login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Laporan belum di-generate oleh Admin Desa untuk periode ini.");
        }

        $row = $result->fetch_assoc();

        // --- AMBIL DATA KEPENGURUSAN ASLI DARI DATABASE JIKA MASIH KOSONG ---
        $kepengurusan_asli = [];
        if (!empty($row['data_kepengurusan'])) {
            $kepengurusan_asli = json_decode($row['data_kepengurusan'], true);
        } else {
            $nama_pengawas = '-';
            $nama_ketua = '-';
            $nama_wakil = '-';
            $arr_sekretaris = [];
            $arr_bendahara = [];
            $wali_kelas_asli = [];

            $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan, kelas FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Wali Kelas')");
            $q_pengurus->bind_param("s", $nama_kelompok_login);
            $q_pengurus->execute();
            $res_pengurus = $q_pengurus->get_result();

            if ($res_pengurus) {
                while ($p = $res_pengurus->fetch_assoc()) {
                    $jabatan = strtolower(trim($p['jabatan']));
                    if ($jabatan === 'pengawas') $nama_pengawas = $p['nama_pengurus'];
                    elseif ($jabatan === 'ketua') $nama_ketua = $p['nama_pengurus'];
                    elseif ($jabatan === 'wakil') $nama_wakil = $p['nama_pengurus'];
                    elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'bendahara') $arr_bendahara[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'wali kelas' && !empty($p['kelas'])) $wali_kelas_asli[$p['kelas']] = $p['nama_pengurus'];
                }
            }
            $q_pengurus->close();

            $kepengurusan_asli = [
                'pengawas' => $nama_pengawas,
                'ketua' => $nama_ketua,
                'wakil' => $nama_wakil,
                'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
                'bendahara' => !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-',
                'wali_kelas' => $wali_kelas_asli
            ];
        }

        $data_response = [
            'laporan_id' => $row['id'],
            'status' => $row['status'],
            'checklist' => $row['checklist_musyawarah'] ? json_decode($row['checklist_musyawarah'], true) : ['pjp' => false, 'unsur' => false],
            'detail_kelas' => $row['detail_kelas'] ? json_decode($row['detail_kelas'], true) : [],
            'permasalahan' => $row['permasalahan'] ? json_decode($row['permasalahan'], true) : [],
            'kepengurusan' => $kepengurusan_asli
        ];

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data_response]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. POST DATA (Simpan Draft / Final)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'simpan_laporan') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);
    $target_status = $_POST['status'] ?? 'DRAFT';

    $json_checklist = $_POST['checklist_musyawarah'] ?? '{}';
    $json_detail_kelas = $_POST['detail_kelas'] ?? '[]';
    $json_permasalahan = $_POST['permasalahan'] ?? '[]';

    $conn->begin_transaction();
    try {
        $cek = $conn->prepare("SELECT status, data_kepengurusan FROM laporan_pjp_kelompok WHERE id = ? AND kelompok_id = ?");
        $cek->bind_param("ii", $laporan_id, $kelompok_id_login);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) {
            throw new Exception("Laporan tidak valid atau Anda tidak memiliki akses.");
        }

        $row_cek = $res_cek->fetch_assoc();
        if ($row_cek['status'] === 'TTD_KETUA') {
            throw new Exception("Laporan sudah ditandatangani Ketua, tidak dapat diubah lagi.");
        }

        if (empty($row_cek['data_kepengurusan'])) {
            $nama_pengawas = '-';
            $nama_ketua = '-';
            $nama_wakil = '-';
            $arr_sekretaris = [];
            $arr_bendahara = [];
            $wali_kelas_asli = [];
            $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan, kelas FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Wali Kelas')");
            $q_pengurus->bind_param("s", $nama_kelompok_login);
            $q_pengurus->execute();
            $res_pengurus = $q_pengurus->get_result();
            if ($res_pengurus) {
                while ($p = $res_pengurus->fetch_assoc()) {
                    $jabatan = strtolower(trim($p['jabatan']));
                    if ($jabatan === 'pengawas') $nama_pengawas = $p['nama_pengurus'];
                    elseif ($jabatan === 'ketua') $nama_ketua = $p['nama_pengurus'];
                    elseif ($jabatan === 'wakil') $nama_wakil = $p['nama_pengurus'];
                    elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'bendahara') $arr_bendahara[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'wali kelas' && !empty($p['kelas'])) $wali_kelas_asli[$p['kelas']] = $p['nama_pengurus'];
                }
            }
            $json_kepengurusan = json_encode([
                'pengawas' => $nama_pengawas,
                'ketua' => $nama_ketua,
                'wakil' => $nama_wakil,
                'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
                'bendahara' => !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-',
                'wali_kelas' => $wali_kelas_asli
            ]);

            $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = ?, checklist_musyawarah = ?, detail_kelas = ?, permasalahan = ?, data_kepengurusan = ? WHERE id = ?");
            $stmt_update->bind_param("sssssi", $target_status, $json_checklist, $json_detail_kelas, $json_permasalahan, $json_kepengurusan, $laporan_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = ?, checklist_musyawarah = ?, detail_kelas = ?, permasalahan = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $target_status, $json_checklist, $json_detail_kelas, $json_permasalahan, $laporan_id);
        }

        $stmt_update->execute();
        $conn->commit();
        writeLog('UPDATE', "Admin Kelompok $kelompok_id_login menyimpan Laporan PJP (ID: $laporan_id) dengan status: $target_status");

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => $target_status === 'FINAL' ? 'Laporan berhasil difinalkan.' : 'Draft berhasil disimpan.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 3. POST DATA (Refresh Data Sistem Live)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'refresh_data') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $cek = $conn->prepare("SELECT periode_id, status, detail_kelas FROM laporan_pjp_kelompok WHERE id = ? AND kelompok_id = ?");
        $cek->bind_param("ii", $laporan_id, $kelompok_id_login);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) {
            throw new Exception("Laporan tidak valid atau Anda tidak memiliki akses.");
        }

        $row_cek = $res_cek->fetch_assoc();
        if ($row_cek['status'] !== 'DRAFT') {
            throw new Exception("Hanya laporan berstatus DRAFT yang dapat disinkronkan ulang dengan sistem.");
        }

        $periode_id = $row_cek['periode_id'];

        // 1. REFRESH KEPENGURUSAN
        $nama_pengawas = '-';
        $nama_ketua = '-';
        $nama_wakil = '-';
        $arr_sekretaris = [];
        $arr_bendahara = [];
        $wali_kelas_asli = [];
        $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan, kelas FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Wali Kelas')");
        $q_pengurus->bind_param("s", $nama_kelompok_login);
        $q_pengurus->execute();
        $res_pengurus = $q_pengurus->get_result();
        if ($res_pengurus) {
            while ($p = $res_pengurus->fetch_assoc()) {
                $jabatan = strtolower(trim($p['jabatan']));
                if ($jabatan === 'pengawas') $nama_pengawas = $p['nama_pengurus'];
                elseif ($jabatan === 'ketua') $nama_ketua = $p['nama_pengurus'];
                elseif ($jabatan === 'wakil') $nama_wakil = $p['nama_pengurus'];
                elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                elseif ($jabatan === 'bendahara') $arr_bendahara[] = $p['nama_pengurus'];
                elseif ($jabatan === 'wali kelas' && !empty($p['kelas'])) $wali_kelas_asli[$p['kelas']] = $p['nama_pengurus'];
            }
        }
        $json_kepengurusan = json_encode([
            'pengawas' => $nama_pengawas,
            'ketua' => $nama_ketua,
            'wakil' => $nama_wakil,
            'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
            'bendahara' => !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-',
            'wali_kelas' => $wali_kelas_asli
        ]);

        // 2. REFRESH DETAIL KELAS
        $detail_kelas_lama = json_decode($row_cek['detail_kelas'], true) ?: [];
        $detail_kelas_baru = [];

        foreach ($detail_kelas_lama as $kelas) {
            $nama_kelas = $kelas['nama_kelas'];

            // --- A. Hitung Jumlah Siswa ---
            $q_siswa = $conn->prepare("SELECT COUNT(id) as total FROM peserta WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'");
            $q_siswa->bind_param("ss", $nama_kelompok_login, $nama_kelas);
            $q_siswa->execute();
            $jml_siswa = $q_siswa->get_result()->fetch_assoc()['total'] ?? 0;
            $q_siswa->close();

            // --- B. Hitung Jumlah Guru (UPDATE: Menggunakan relasi tabel guru & pengampu) ---
            $q_guru = $conn->prepare("
                SELECT COUNT(DISTINCT g.id) as total 
                FROM guru g 
                LEFT JOIN pengampu p ON g.id = p.id_guru 
                WHERE g.deleted_at IS NULL 
                  AND LOWER(g.kelompok) = LOWER(?) 
                  AND LOWER(p.nama_kelas) = LOWER(?)
            ");
            $q_guru->bind_param("ss", $nama_kelompok_login, $nama_kelas);
            $q_guru->execute();
            $jml_guru = $q_guru->get_result()->fetch_assoc()['total'] ?? 0;
            $q_guru->close();

            // --- C. Hitung Persentase Kehadiran ---
            $q_hadir = $conn->prepare("
                SELECT 
                    SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                    SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                    SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                    COUNT(rp.status_kehadiran) as total_diisi
                FROM rekap_presensi rp
                JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
            ");
            $q_hadir->bind_param("iss", $periode_id, $nama_kelompok_login, $nama_kelas);
            $q_hadir->execute();
            $res_hadir = $q_hadir->get_result()->fetch_assoc();
            $q_hadir->close();

            if ((int)$res_hadir['total_diisi'] > 0) {
                $tot     = $res_hadir['total_diisi'];
                $p_hadir = round(($res_hadir['hadir'] / $tot) * 100);
                $p_izin  = round(($res_hadir['izin']  / $tot) * 100);
                $p_sakit = round(($res_hadir['sakit'] / $tot) * 100);
                $p_alpa  = round(($res_hadir['alpa']  / $tot) * 100);
            } else {
                // Tidak ada data presensi → null agar tidak dihitung sebagai pembagi
                $p_hadir = $p_izin = $p_sakit = $p_alpa = null;
            }

            // --- D. Hitung Ketercapaian Materi ---
            $q_target = $conn->prepare("
                SELECT id, kategori, total_volume, tipe_input 
                FROM target_pembelajaran 
                WHERE periode_id = ? AND (kelas = ? OR kelas = 'Semua') AND (kelompok = ? OR kelompok = 'Semua')
            ");
            $q_target->bind_param("iss", $periode_id, $nama_kelas, $nama_kelompok_login);
            $q_target->execute();
            $res_target = $q_target->get_result();

            $kategori_capaian = [];

            while ($t = $res_target->fetch_assoc()) {
                $cat = $t['kategori'];
                if (!isset($kategori_capaian[$cat])) {
                    $kategori_capaian[$cat] = ['target' => 0, 'realisasi' => 0];
                }

                $vol_target = (float)$t['total_volume'];
                if ($t['tipe_input'] == 'CHECKLIST') $vol_target = 1;

                $kategori_capaian[$cat]['target'] += $vol_target;

                $q_cap = $conn->prepare("SELECT SUM(jm.volume_capaian) as total FROM jurnal_materi jm JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id WHERE jm.target_id = ? AND jp.kelompok = ? AND jp.kelas = ?");
                $q_cap->bind_param("iss", $t['id'], $nama_kelompok_login, $nama_kelas);
                $q_cap->execute();
                $vol_cap = (float)($q_cap->get_result()->fetch_assoc()['total'] ?? 0);
                $q_cap->close();

                if ($vol_cap > $vol_target) $vol_cap = $vol_target;
                $kategori_capaian[$cat]['realisasi'] += $vol_cap;
            }
            $q_target->close();

            $ketercapaian_kategori_final = [];
            $total_persen_global         = 0;
            $count_kategori_valid        = 0;

            foreach ($kategori_capaian as $cat => $val) {
                if ($val['target'] > 0) {
                    // Ada target → hitung persentase dan ikutkan dalam rata-rata global
                    $pct = round(($val['realisasi'] / $val['target']) * 100);
                    $total_persen_global += $pct;
                    $count_kategori_valid++;
                } else {
                    // Tidak ada target → null agar tidak dihitung sebagai pembagi
                    $pct = null;
                }
                $ketercapaian_kategori_final[$cat] = $pct;
            }
            // Jika tidak ada satu pun kategori dengan target → global null
            $ketercapaian_global = $count_kategori_valid > 0
                ? round($total_persen_global / $count_kategori_valid)
                : null;

            // --- E. Hitung Tatap Muka (jumlah jadwal pada periode tsb per kelas) ---
            $q_tatap = $conn->prepare("
                SELECT COUNT(id) as total 
                FROM jadwal_presensi 
                WHERE periode_id = ? AND kelompok = ? AND kelas = ?
            ");
            $q_tatap->bind_param("iss", $periode_id, $nama_kelompok_login, $nama_kelas);
            $q_tatap->execute();
            $tatap_muka = (int)($q_tatap->get_result()->fetch_assoc()['total'] ?? 0);
            $q_tatap->close();

            // Gabungkan menjadi array detail kelas baru
            $detail_kelas_baru[] = [
                'nama_kelas' => $nama_kelas,
                'jml_siswa' => $jml_siswa,
                'jml_guru' => $jml_guru,
                'kehadiran' => ['hadir' => $p_hadir, 'izin' => $p_izin, 'sakit' => $p_sakit, 'alpa' => $p_alpa],
                'ketercapaian_global' => $ketercapaian_global,
                'ketercapaian_kategori' => $ketercapaian_kategori_final,
                'penyelenggara' => $kelas['penyelenggara'] ?? 'kelompok',
                'tatap_muka' => $tatap_muka
            ];
        }
        $json_detail_kelas = json_encode($detail_kelas_baru);

        $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET data_kepengurusan = ?, detail_kelas = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $json_kepengurusan, $json_detail_kelas, $laporan_id);
        $stmt_update->execute();

        $conn->commit();
        writeLog('UPDATE', "Admin Kelompok $kelompok_id_login me-refresh sinkronisasi data pada Laporan ID: $laporan_id");

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Data berhasil disinkronkan dari sistem.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
