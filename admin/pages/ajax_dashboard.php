<?php
// === FILE BACKEND: ajax_dashboard.php ===
// Letakkan di folder: pages/dashboard/ajax_dashboard.php
require_once '../../config/config.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir.']);
    exit;
}

$admin_role = $_SESSION['user_role'] ?? '';
$admin_level = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// --- 1. CARI PERIODE AKTIF ---
$periode_aktif_id = null;
$periode_aktif_nama = 'Tidak Ada Periode Aktif';

$query_current = "SELECT id, nama_periode FROM periode WHERE CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai LIMIT 1";
$res_cur = $conn->query($query_current);
if ($res_cur && $res_cur->num_rows > 0) {
    $row = $res_cur->fetch_assoc();
    $periode_aktif_id = $row['id'];
    $periode_aktif_nama = $row['nama_periode'];
} else {
    $query_next = "SELECT id, nama_periode FROM periode WHERE tanggal_mulai > CURDATE() ORDER BY tanggal_mulai ASC LIMIT 1";
    $res_next = $conn->query($query_next);
    if ($res_next && $res_next->num_rows > 0) {
        $row = $res_next->fetch_assoc();
        $periode_aktif_id = $row['id'];
        $periode_aktif_nama = $row['nama_periode'];
    } else {
        $query_last = "SELECT id, nama_periode FROM periode WHERE tanggal_selesai < CURDATE() ORDER BY tanggal_selesai DESC LIMIT 1";
        $res_last = $conn->query($query_last);
        if ($res_last && $res_last->num_rows > 0) {
            $row = $res_last->fetch_assoc();
            $periode_aktif_id = $row['id'];
            $periode_aktif_nama = $row['nama_periode'];
        }
    }
}

// Data List Standar
$kelompok_list = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$kelas_list = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
$kelompok_filter = ($admin_level === 'kelompok' && in_array(strtolower($admin_kelompok), $kelompok_list)) ? [strtolower($admin_kelompok)] : $kelompok_list;

// --- ARRAY DATA UTAMA ---
$data = [
    'periode_nama' => $periode_aktif_nama,
    'total_peserta' => 0,
    'peserta_l' => 0,
    'peserta_p' => 0,
    'peserta_summary' => [],
    'total_users' => 0,
    'users_summary' => [],
    'total_guru' => 0,
    'guru_summary' => [],
    'kehadiran' => ['global' => 0, 'kelompok' => [], 'kelas' => []],
    'materi' => ['global' => 0, 'kelompok' => [], 'kelas' => []],
    'jadwal_hari_ini' => 0,
    'jadwal_terlewat_kosong' => [],
    'jadwal_tanpa_pengajar' => [],
    'jadwal_akan_datang' => []
];

// Inisialisasi Nilai 0 untuk Peserta & Guru (Sesuai filter admin)
foreach ($kelompok_filter as $kel) {
    $data['peserta_summary'][$kel] = [];
    $data['guru_summary'][$kel] = [];
    foreach ($kelas_list as $kls) {
        $data['peserta_summary'][$kel][$kls] = ['l' => 0, 'p' => 0, 'total' => 0];
        $data['guru_summary'][$kel][$kls] = 0;
    }
}

$where_admin = ($admin_level === 'kelompok') ? " AND kelompok = '$admin_kelompok' " : "";
$where_admin_guru = ($admin_level === 'kelompok') ? " AND g.kelompok = '$admin_kelompok' " : "";
$where_admin_jp = ($admin_level === 'kelompok') ? " AND jp.kelompok = '$admin_kelompok' " : "";

try {
    // =========================================================
    // 2. AMBIL DATA PESERTA & ENTITAS PENGGUNA (MODE SUMMARY)
    // =========================================================

    // Aggregate Peserta
    $sql_peserta = "SELECT LOWER(kelompok) as kelompok, LOWER(kelas) as kelas, 
                           SUM(CASE WHEN jenis_kelamin = 'Laki-laki' THEN 1 ELSE 0 END) as l,
                           SUM(CASE WHEN jenis_kelamin = 'Perempuan' THEN 1 ELSE 0 END) as p,
                           COUNT(id) as total
                    FROM peserta WHERE status='Aktif' $where_admin 
                    GROUP BY kelompok, kelas";
    $res_p = $conn->query($sql_peserta);
    if ($res_p) {
        while ($row = $res_p->fetch_assoc()) {
            $kel = $row['kelompok'];
            $kls = $row['kelas'];
            $data['total_peserta'] += $row['total'];
            $data['peserta_l'] += $row['l'];
            $data['peserta_p'] += $row['p'];

            if (isset($data['peserta_summary'][$kel][$kls])) {
                $data['peserta_summary'][$kel][$kls] = [
                    'l' => (int)$row['l'],
                    'p' => (int)$row['p'],
                    'total' => (int)$row['total']
                ];
            }
        }
    }

    // Khusus Superadmin (Developer): Ambil Data Users & Guru
    if ($admin_role === 'superadmin') {

        // Inisialisasi Kategori Pengguna agar rapi
        $users_sum = [
            'Developer' => 0,
            'Admin Desa' => 0,
            'Admin Kelompok' => 0,
            'Ketua PJP Desa' => 0,
            'Ketua PJP Kelompok' => 0,
            'BK Desa' => 0,
            'BK Kelompok' => 0
        ];

        $sql_users = "SELECT LOWER(role) as role, LOWER(tingkat) as tingkat, COUNT(id) as total 
                      FROM users WHERE deleted_at IS NULL GROUP BY role, tingkat";
        $res_u = $conn->query($sql_users);
        if ($res_u) {
            while ($row = $res_u->fetch_assoc()) {
                $data['total_users'] += $row['total'];
                $r = $row['role'];
                $t = $row['tingkat'];
                if ($r === 'superadmin') $users_sum['Developer'] += $row['total'];
                else if ($r === 'admin') {
                    if ($t === 'desa') $users_sum['Admin Desa'] += $row['total'];
                    else $users_sum['Admin Kelompok'] += $row['total'];
                } else if ($r === 'ketua pjp') {
                    if ($t === 'desa') $users_sum['Ketua PJP Desa'] += $row['total'];
                    else $users_sum['Ketua PJP Kelompok'] += $row['total'];
                } else if ($r === 'bk') {
                    if ($t === 'desa') $users_sum['BK Desa'] += $row['total'];
                    else $users_sum['BK Kelompok'] += $row['total'];
                }
            }
        }
        $data['users_summary'] = $users_sum;
    }

    // Aggregate Guru (Sekarang ada filter $where_admin_guru agar aman untuk Admin Kelompok)
    $sql_guru = "SELECT LOWER(g.kelompok) as kelompok, LOWER(p.nama_kelas) as kelas, COUNT(DISTINCT g.id) as total 
                 FROM guru g 
                 LEFT JOIN pengampu p ON g.id = p.id_guru 
                 WHERE g.deleted_at IS NULL AND p.nama_kelas IS NOT NULL $where_admin_guru
                 GROUP BY g.kelompok, p.nama_kelas";
    $res_g = $conn->query($sql_guru);
    if ($res_g) {
        while ($row = $res_g->fetch_assoc()) {
            $kel = $row['kelompok'];
            $kls = $row['kelas'];
            if (isset($data['guru_summary'][$kel][$kls])) {
                $data['guru_summary'][$kel][$kls] += (int)$row['total'];
            }
        }
    }
    $res_gtot = $conn->query("SELECT COUNT(id) as t FROM guru WHERE deleted_at IS NULL " . str_replace("g.kelompok", "kelompok", $where_admin_guru));
    if ($res_gtot) $data['total_guru'] = $res_gtot->fetch_assoc()['t'];


    if ($periode_aktif_id) {
        // =========================================================
        // 3. HITUNG KEHADIRAN (HANYA 'Hadir')
        // =========================================================
        $absen_raw = [];
        $where_admin_alias = ($admin_level === 'kelompok') ? " AND p.kelompok = '$admin_kelompok' " : "";
        $sql_absen = "
            SELECT LOWER(p.kelompok) as kelompok, LOWER(p.kelas) as kelas, 
                   SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                   COUNT(rp.id) as total
            FROM rekap_presensi rp
            JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
            JOIN peserta p ON rp.peserta_id = p.id
            WHERE jp.periode_id = $periode_aktif_id AND rp.status_kehadiran != '' $where_admin_alias
            GROUP BY p.kelompok, p.kelas
        ";
        $res_absen = $conn->query($sql_absen);
        if ($res_absen) while ($r = $res_absen->fetch_assoc()) $absen_raw[] = $r;

        $h_glob = 0;
        $t_glob = 0;
        $h_kel = [];
        $t_kel = [];
        $h_kls = [];
        $t_kls = [];

        foreach ($absen_raw as $r) {
            $k = $r['kelompok'];
            $kls = $r['kelas'];
            $h = (int)$r['hadir'];
            $t = (int)$r['total'];

            $h_glob += $h;
            $t_glob += $t;
            if (!isset($h_kel[$k])) {
                $h_kel[$k] = 0;
                $t_kel[$k] = 0;
            }
            $h_kel[$k] += $h;
            $t_kel[$k] += $t;

            if (!isset($h_kls[$kls])) {
                $h_kls[$kls] = 0;
                $t_kls[$kls] = 0;
            }
            $h_kls[$kls] += $h;
            $t_kls[$kls] += $t;
        }

        $data['kehadiran']['global'] = ($t_glob > 0) ? round(($h_glob / $t_glob) * 100, 1) : 0;
        foreach ($kelompok_filter as $k) {
            $t = $t_kel[$k] ?? 0;
            $data['kehadiran']['kelompok'][$k] = ($t > 0) ? round((($h_kel[$k] ?? 0) / $t) * 100, 1) : 0;
        }
        foreach ($kelas_list as $k) {
            $t = $t_kls[$k] ?? 0;
            $data['kehadiran']['kelas'][$k] = ($t > 0) ? round((($h_kls[$k] ?? 0) / $t) * 100, 1) : 0;
        }

        // =========================================================
        // 4. HITUNG KETERCAPAIAN MATERI (TARGET VS REALISASI)
        // =========================================================
        $targets = [];
        $res_t = $conn->query("SELECT id, kategori, LOWER(kelompok) as kelompok, LOWER(kelas) as kelas, total_volume, tipe_input FROM target_pembelajaran WHERE periode_id = $periode_aktif_id");
        if ($res_t) while ($r = $res_t->fetch_assoc()) $targets[] = $r;

        $achievements = [];
        $res_a = $conn->query("
            SELECT jm.target_id, LOWER(jp.kelompok) as kelompok, LOWER(jp.kelas) as kelas, SUM(jm.volume_capaian) as total
            FROM jurnal_materi jm JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id
            WHERE jp.periode_id = $periode_aktif_id
            GROUP BY jm.target_id, jp.kelompok, jp.kelas
        ");
        if ($res_a) while ($r = $res_a->fetch_assoc()) $achievements[] = $r;

        function calc_prog($targets, $achievements, $f_kel, $f_kls)
        {
            $cats = [];
            foreach ($targets as $t) {
                if ($f_kel !== 'semua' && $t['kelompok'] !== 'semua' && $t['kelompok'] !== $f_kel) continue;
                if ($f_kls !== 'semua' && $t['kelas'] !== 'semua' && $t['kelas'] !== $f_kls) continue;

                $cat = $t['kategori'];
                if (!isset($cats[$cat])) $cats[$cat] = ['t' => 0, 'c' => 0];

                $vt = ($t['tipe_input'] == 'CHECKLIST') ? 1 : (float)$t['total_volume'];
                $cats[$cat]['t'] += $vt;

                $vc = 0;
                foreach ($achievements as $a) {
                    if ($a['target_id'] == $t['id']) {
                        if ($f_kel !== 'semua' && $a['kelompok'] !== $f_kel) continue;
                        if ($f_kls !== 'semua' && $a['kelas'] !== $f_kls) continue;
                        $vc += (float)$a['total'];
                    }
                }
                if ($vc > $vt) $vc = $vt; // Capping
                $cats[$cat]['c'] += $vc;
            }
            $sum = 0;
            $cnt = 0;
            foreach ($cats as $c) {
                if ($c['t'] > 0) $sum += ($c['c'] / $c['t']) * 100;
                $cnt++;
            }
            return ($cnt > 0) ? round($sum / $cnt, 1) : 0;
        }

        $f_kel_global = ($admin_level === 'kelompok') ? strtolower($admin_kelompok) : 'semua';
        $data['materi']['global'] = calc_prog($targets, $achievements, $f_kel_global, 'semua');
        foreach ($kelompok_filter as $k) {
            $data['materi']['kelompok'][$k] = calc_prog($targets, $achievements, $k, 'semua');
        }
        foreach ($kelas_list as $k) {
            $data['materi']['kelas'][$k] = calc_prog($targets, $achievements, $f_kel_global, $k);
        }

        // =========================================================
        // 5. JADWAL MENDESAK & SHORTCUTS
        // =========================================================
        $res_jadwal_hari_ini = $conn->query("SELECT COUNT(jp.id) as t FROM jadwal_presensi jp WHERE jp.tanggal = CURDATE() AND jp.periode_id = $periode_aktif_id $where_admin_jp");
        $data['jadwal_hari_ini'] = ($res_jadwal_hari_ini) ? $res_jadwal_hari_ini->fetch_assoc()['t'] : 0;

        $sql_terlewat = "
            SELECT jp.id, jp.tanggal, jp.kelas, jp.kelompok,
                (jp.pengajar IS NULL OR jp.pengajar = '') AS jurnal_kosong,
                EXISTS (SELECT 1 FROM rekap_presensi rp WHERE rp.jadwal_id = jp.id AND rp.status_kehadiran IS NULL) AS presensi_kosong
            FROM jadwal_presensi jp 
            WHERE TIMESTAMP(jp.tanggal, jp.jam_selesai) <= NOW() AND jp.periode_id = $periode_aktif_id $where_admin_jp
            AND ( EXISTS (SELECT 1 FROM rekap_presensi rp WHERE rp.jadwal_id = jp.id AND rp.status_kehadiran IS NULL) OR (jp.pengajar IS NULL OR jp.pengajar = '') )
            ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";
        $res_terlewat = $conn->query($sql_terlewat);
        if ($res_terlewat) while ($row = $res_terlewat->fetch_assoc()) {
            $row['keterangan_kosong'] = ($row['jurnal_kosong'] && $row['presensi_kosong']) ? 'Presensi & Jurnal' : (($row['presensi_kosong']) ? 'Presensi' : 'Jurnal');
            $data['jadwal_terlewat_kosong'][] = $row;
        }

        $sql_tanpa_guru = "SELECT jp.id, jp.tanggal, jp.kelas, jp.kelompok FROM jadwal_presensi jp LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id
            WHERE jg.jadwal_id IS NULL AND jp.periode_id = $periode_aktif_id $where_admin_jp ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";
        $res_tanpa = $conn->query($sql_tanpa_guru);
        if ($res_tanpa) while ($row = $res_tanpa->fetch_assoc()) $data['jadwal_tanpa_pengajar'][] = $row;

        $sql_akan_datang = "SELECT jp.id, jp.tanggal, jp.jam_mulai, jp.kelas, jp.kelompok, GROUP_CONCAT(DISTINCT g.nama SEPARATOR ', ') as daftar_guru
            FROM jadwal_presensi jp LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id LEFT JOIN guru g ON jg.guru_id = g.id
            WHERE jp.tanggal BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 DAY AND jp.periode_id = $periode_aktif_id $where_admin_jp
            GROUP BY jp.id ORDER BY jp.tanggal ASC, jp.jam_mulai ASC";
        $res_akan = $conn->query($sql_akan_datang);
        if ($res_akan) while ($row = $res_akan->fetch_assoc()) $data['jadwal_akan_datang'][] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
