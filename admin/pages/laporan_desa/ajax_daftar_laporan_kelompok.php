<?php
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

// Validasi session (Khusus Admin Desa)
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Definisi Standar Kelompok & Kelas SIMAK
$DATA_KELOMPOK = [
    1 => 'Bintaran',
    2 => 'Gedongkuning',
    3 => 'Jombor',
    4 => 'Sunten'
];

$DATA_KELAS = ['PAUD', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];

// ==========================================================
// HELPER: Ambil data real dari DB untuk satu kelompok + periode
// Digunakan bersama oleh generate_draft
// ==========================================================
function fetchDataKelompok($conn, $periode_id, $nama_kelompok, $DATA_KELAS): array
{
    // --- KEPENGURUSAN ---
    $nama_pengawas = '-';
    $nama_ketua = '-';
    $nama_wakil = '-';
    $arr_sekretaris = [];
    $arr_bendahara = [];
    $wali_kelas_asli = [];

    $q_pengurus = $conn->prepare("
        SELECT nama_pengurus, jabatan, kelas 
        FROM kepengurusan 
        WHERE tingkat = 'kelompok' 
          AND kelompok = ? 
          AND jabatan IN ('Pengawas','Ketua','Wakil','Sekretaris','Bendahara','Wali Kelas')
    ");
    $q_pengurus->bind_param("s", $nama_kelompok);
    $q_pengurus->execute();
    $res_pengurus = $q_pengurus->get_result();
    if ($res_pengurus) {
        while ($p = $res_pengurus->fetch_assoc()) {
            $jabatan = strtolower(trim($p['jabatan']));
            if ($jabatan === 'pengawas')         $nama_pengawas = $p['nama_pengurus'];
            elseif ($jabatan === 'ketua')        $nama_ketua    = $p['nama_pengurus'];
            elseif ($jabatan === 'wakil')        $nama_wakil    = $p['nama_pengurus'];
            elseif ($jabatan === 'sekretaris')   $arr_sekretaris[] = $p['nama_pengurus'];
            elseif ($jabatan === 'bendahara')    $arr_bendahara[]  = $p['nama_pengurus'];
            elseif ($jabatan === 'wali kelas' && !empty($p['kelas']))
                $wali_kelas_asli[$p['kelas']] = $p['nama_pengurus'];
        }
    }
    $q_pengurus->close();

    $json_kepengurusan = json_encode([
        'pengawas'    => $nama_pengawas,
        'ketua'       => $nama_ketua,
        'wakil'       => $nama_wakil,
        'sekretaris'  => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
        'bendahara'   => !empty($arr_bendahara)  ? implode(', ', $arr_bendahara)  : '-',
        'wali_kelas'  => $wali_kelas_asli
    ]);

    // --- DETAIL PER KELAS ---
    $detail_kelas_baru = [];

    foreach ($DATA_KELAS as $nama_kelas) {
        if ($nama_kelas == "Remaja") {
            $penyelenggara = 'desa';
        } else {
            $penyelenggara = 'kelompok';
        }

        // A. Jumlah Siswa Aktif
        $q_siswa = $conn->prepare("
            SELECT COUNT(id) as total 
            FROM peserta 
            WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'
        ");
        $q_siswa->bind_param("ss", $nama_kelompok, $nama_kelas);
        $q_siswa->execute();
        $jml_siswa = (int)($q_siswa->get_result()->fetch_assoc()['total'] ?? 0);
        $q_siswa->close();

        // B. Jumlah Guru (via relasi tabel guru & pengampu)
        $q_guru = $conn->prepare("
            SELECT COUNT(DISTINCT g.id) as total 
            FROM guru g 
            LEFT JOIN pengampu p ON g.id = p.id_guru 
            WHERE g.deleted_at IS NULL 
              AND LOWER(g.kelompok) = LOWER(?) 
              AND LOWER(p.nama_kelas) = LOWER(?)
        ");
        $q_guru->bind_param("ss", $nama_kelompok, $nama_kelas);
        $q_guru->execute();
        $jml_guru = (int)($q_guru->get_result()->fetch_assoc()['total'] ?? 0);
        $q_guru->close();

        // C. Persentase Kehadiran
        $q_hadir = $conn->prepare("
            SELECT 
                SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN rp.status_kehadiran = 'Izin'  THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN rp.status_kehadiran = 'Alpa'  THEN 1 ELSE 0 END) as alpa,
                COUNT(rp.status_kehadiran) as total_diisi
            FROM rekap_presensi rp
            JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
            WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
        ");
        $q_hadir->bind_param("iss", $periode_id, $nama_kelompok, $nama_kelas);
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

        // D. Ketercapaian Materi per Kategori
        $q_target = $conn->prepare("
            SELECT id, kategori, total_volume, tipe_input 
            FROM target_pembelajaran 
            WHERE periode_id = ? 
              AND (kelas = ? OR kelas = 'Semua') 
              AND (kelompok = ? OR kelompok = 'Semua')
        ");
        $q_target->bind_param("iss", $periode_id, $nama_kelas, $nama_kelompok);
        $q_target->execute();
        $res_target = $q_target->get_result();

        $kategori_capaian = [];

        while ($t = $res_target->fetch_assoc()) {
            $cat = $t['kategori'];
            if (!isset($kategori_capaian[$cat])) {
                $kategori_capaian[$cat] = ['target' => 0, 'realisasi' => 0];
            }

            $vol_target = (float)$t['total_volume'];
            if ($t['tipe_input'] === 'CHECKLIST') $vol_target = 1;
            $kategori_capaian[$cat]['target'] += $vol_target;

            $q_cap = $conn->prepare("
                SELECT SUM(jm.volume_capaian) as total 
                FROM jurnal_materi jm 
                JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id 
                WHERE jm.target_id = ? AND jp.kelompok = ? AND jp.kelas = ?
            ");
            $q_cap->bind_param("iss", $t['id'], $nama_kelompok, $nama_kelas);
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

        // E. Tatap Muka — jumlah jadwal pada periode tsb per kelas per kelompok
        $q_tatap = $conn->prepare("
            SELECT COUNT(id) as total 
            FROM jadwal_presensi 
            WHERE periode_id = ? AND kelompok = ? AND kelas = ?
        ");
        $q_tatap->bind_param("iss", $periode_id, $nama_kelompok, $nama_kelas);
        $q_tatap->execute();
        $tatap_muka = (int)($q_tatap->get_result()->fetch_assoc()['total'] ?? 0);
        $q_tatap->close();

        $detail_kelas_baru[] = [
            'nama_kelas'             => $nama_kelas,
            'jml_siswa'              => $jml_siswa,
            'jml_guru'               => $jml_guru,
            'kehadiran'              => [
                'hadir' => $p_hadir,
                'izin'  => $p_izin,
                'sakit' => $p_sakit,
                'alpa'  => $p_alpa
            ],
            'ketercapaian_global'    => $ketercapaian_global,
            'ketercapaian_kategori'  => $ketercapaian_kategori_final,
            'penyelenggara'          => $penyelenggara,
            'tatap_muka'             => $tatap_muka
        ];
    }

    return [
        'json_kepengurusan' => $json_kepengurusan,
        'json_detail_kelas' => json_encode($detail_kelas_baru)
    ];
}

// ==========================================================
// 1. GET LIST (Untuk me-render tabel daftar periode)
// ==========================================================
if ($action === 'get_list') {
    $total_kelompok_hardcode = count($DATA_KELOMPOK);

    $query = "
        SELECT 
            p.id, 
            p.nama_periode, 
            p.tanggal_selesai as tanggal_akhir,
            DATE_FORMAT(p.tanggal_selesai, '%d %b %Y') as tanggal_akhir_format,
            (SELECT COUNT(id) FROM laporan_pjp_kelompok WHERE periode_id = p.id) as cek_generated,
            $total_kelompok_hardcode as total_kelompok,
            (SELECT COUNT(id) FROM laporan_pjp_kelompok WHERE periode_id = p.id AND status = 'TTD_KETUA') as kelompok_selesai,
            (SELECT status FROM laporan_pjp_desa WHERE periode_id = p.id LIMIT 1) as status_desa
        FROM periode p
        ORDER BY p.tanggal_selesai DESC
    ";

    try {
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query Database Error: " . $conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['is_generated'] = (int)$row['cek_generated'] > 0;
            $data[] = $row;
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. GENERATE DRAFT MASSAL KELOMPOK
//    Langsung ambil data real dari DB (bukan insert nilai 0)
// ==========================================================
elseif ($action === 'generate_draft') {
    $periode_id = (int)($_POST['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Mencegah Generate Ganda
        $cek = $conn->prepare("SELECT id FROM laporan_pjp_kelompok WHERE periode_id = ? LIMIT 1");
        $cek->bind_param("i", $periode_id);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            throw new Exception("Akses laporan untuk periode ini sudah pernah dibuat sebelumnya.");
        }
        $cek->close();

        // Insert per kelompok dengan data real dari DB
        $stmt = $conn->prepare("
            INSERT INTO laporan_pjp_kelompok 
                (periode_id, kelompok_id, status, data_kepengurusan, detail_kelas) 
            VALUES (?, ?, 'DRAFT', ?, ?)
        ");

        foreach ($DATA_KELOMPOK as $k_id => $nama_kelompok) {
            // Ambil data real (kepengurusan + detail kelas + tatap muka)
            $fetched = fetchDataKelompok($conn, $periode_id, $nama_kelompok, $DATA_KELAS);

            $stmt->bind_param(
                "iiss",
                $periode_id,
                $k_id,
                $fetched['json_kepengurusan'],
                $fetched['json_detail_kelas']
            );
            $stmt->execute();
        }

        $conn->commit();
        writeLog('INSERT', "Admin Desa me-generate akses draft laporan PJP untuk periode ID: $periode_id");

        ob_clean();
        echo json_encode([
            'status'  => 'success',
            'message' => 'Akses laporan PJP berhasil dibuka untuk seluruh kelompok dengan data terkini.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
