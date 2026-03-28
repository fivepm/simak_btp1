<?php
// === FILE BACKEND: ajax_grafik_ketercapaian.php ===
// Letakkan di folder: pages/report/ajax_grafik_ketercapaian.php
require_once '../../../config/config.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir.']);
    exit;
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';
$periode_id = $_GET['periode_id'] ?? null;

if (!$periode_id) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$kelompok_list = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$kelas_list = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
$kelompok_filter = ($ketuapjp_tingkat === 'kelompok' && in_array(strtolower($ketuapjp_kelompok), $kelompok_list)) ? [strtolower($ketuapjp_kelompok)] : $kelompok_list;

// Ambil Target Pembelajaran
$targets = [];
$res_t = $conn->query("SELECT id, kategori, LOWER(kelompok) as kelompok, LOWER(kelas) as kelas, total_volume, tipe_input FROM target_pembelajaran WHERE periode_id = $periode_id");
if ($res_t) while ($r = $res_t->fetch_assoc()) $targets[] = $r;

// Ambil Realisasi Capaian
$achievements = [];
$res_a = $conn->query("
    SELECT jm.target_id, LOWER(jp.kelompok) as kelompok, LOWER(jp.kelas) as kelas, SUM(jm.volume_capaian) as total
    FROM jurnal_materi jm JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id
    WHERE jp.periode_id = $periode_id
    GROUP BY jm.target_id, jp.kelompok, jp.kelas
");
if ($res_a) while ($r = $res_a->fetch_assoc()) $achievements[] = $r;

$grafik_data = [];
foreach ($kelompok_filter as $kel) {
    foreach ($kelas_list as $kls) {
        $cats = [];
        // Kumpulkan data per target
        foreach ($targets as $t) {
            if ($t['kelompok'] !== 'semua' && $t['kelompok'] !== $kel) continue;
            if ($t['kelas'] !== 'semua' && $t['kelas'] !== $kls) continue;

            $cat = $t['kategori'];
            if (!isset($cats[$cat])) $cats[$cat] = ['t' => 0, 'c' => 0];

            $vt = ($t['tipe_input'] == 'CHECKLIST') ? 1 : (float)$t['total_volume'];
            $cats[$cat]['t'] += $vt;

            $vc = 0;
            foreach ($achievements as $a) {
                if ($a['target_id'] == $t['id'] && $a['kelompok'] === $kel && $a['kelas'] === $kls) {
                    $vc += (float)$a['total'];
                }
            }
            if ($vc > $vt) $vc = $vt; // Capping agar tidak lebih dari 100%
            $cats[$cat]['c'] += $vc;
        }

        // Rata-rata persentase per kelas dan list kategori
        $kategori_list = [];
        $sum = 0;
        $cnt = 0;
        foreach ($cats as $catName => $c) {
            $pct = 0;
            if ($c['t'] > 0) {
                $pct = ($c['c'] / $c['t']) * 100;
                $sum += $pct;
            }
            $kategori_list[$catName] = round($pct, 1);
            $cnt++;
        }

        $avg = ($cnt > 0) ? round($sum / $cnt, 1) : null;
        $grafik_data[$kel][$kls] = [
            'rata_rata' => $avg,
            'kategori' => $kategori_list
        ];
    }
}

echo json_encode(['status' => 'success', 'data' => $grafik_data]);
