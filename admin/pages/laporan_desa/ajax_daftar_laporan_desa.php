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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$DATA_KELOMPOK = [1 => 'Bintaran', 2 => 'Gedongkuning', 3 => 'Jombor', 4 => 'Sunten'];
$DATA_KELAS    = ['PAUD', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];

// ==========================================================
// HELPER: Hitung rata-rata rekap desa dengan logika akurat
//   - jml_siswa, jml_guru, tatap_muka → SUM
//   - kehadiran %           → rata-rata (abaikan nilai null agar tidak jadi pembagi)
//   - ketercapaian          → rata-rata (abaikan nilai null agar tidak jadi pembagi)
// ==========================================================
function hitungRekapDesa(array $semua_detail_kelas, array $semua_permasalahan, string $catatan_desa = ''): array
{
    $rekap_kelas = [];

    foreach ($semua_detail_kelas as $kelompok_kelas) {
        foreach ($kelompok_kelas as $kls) {
            $nama = $kls['nama_kelas'];
            if (!isset($rekap_kelas[$nama])) {
                // Inisialisasi struktur rekap per kelas
                $rekap_kelas[$nama] = [
                    'nama_kelas'               => $nama,
                    'jml_siswa'                => 0,
                    'jml_guru'                 => 0,
                    'tatap_muka'               => 0,
                    'penyelenggara'            => $kls['penyelenggara'] ?? '',
                    'kehadiran'                => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0],
                    'ketercapaian_global'      => 0,
                    'ketercapaian_kategori'    => [],
                    'count_hadir'              => 0,
                    'count_capaian_global'     => 0,
                    'count_capaian_kategori'   => []
                ];
            }

            // Sum data kumulatif
            $rekap_kelas[$nama]['jml_siswa']  += (int)($kls['jml_siswa'] ?? 0);
            $rekap_kelas[$nama]['jml_guru']   += (int)($kls['jml_guru']  ?? 0);
            $rekap_kelas[$nama]['tatap_muka'] += (int)($kls['tatap_muka'] ?? 0);

            // --- LOGIKA KEHADIRAN (Hanya hitung pembagi jika tidak null) ---
            if (isset($kls['kehadiran']['hadir']) && $kls['kehadiran']['hadir'] !== null) {
                $rekap_kelas[$nama]['kehadiran']['hadir'] += (float)$kls['kehadiran']['hadir'];
                $rekap_kelas[$nama]['kehadiran']['izin']  += (float)$kls['kehadiran']['izin'];
                $rekap_kelas[$nama]['kehadiran']['sakit'] += (float)$kls['kehadiran']['sakit'];
                $rekap_kelas[$nama]['kehadiran']['alpa']  += (float)$kls['kehadiran']['alpa'];
                $rekap_kelas[$nama]['count_hadir']++;
            }

            // --- LOGIKA KETERCAPAIAN GLOBAL (Hanya hitung pembagi jika tidak null) ---
            if (isset($kls['ketercapaian_global']) && $kls['ketercapaian_global'] !== null) {
                $rekap_kelas[$nama]['ketercapaian_global'] += (float)$kls['ketercapaian_global'];
                $rekap_kelas[$nama]['count_capaian_global']++;
            }

            // --- LOGIKA KETERCAPAIAN KATEGORI (Hanya hitung pembagi jika tidak null) ---
            foreach (($kls['ketercapaian_kategori'] ?? []) as $kat => $val) {
                if (!isset($rekap_kelas[$nama]['ketercapaian_kategori'][$kat])) {
                    $rekap_kelas[$nama]['ketercapaian_kategori'][$kat] = 0;
                    $rekap_kelas[$nama]['count_capaian_kategori'][$kat] = 0;
                }
                if ($val !== null) {
                    $rekap_kelas[$nama]['ketercapaian_kategori'][$kat] += (float)$val;
                    $rekap_kelas[$nama]['count_capaian_kategori'][$kat]++;
                }
            }
        }
    }

    $hasil = [];
    foreach ($rekap_kelas as $r) {
        $ch = $r['count_hadir'];
        $cg = $r['count_capaian_global'];

        // Rata-rata ketercapaian per kategori (jika pembagi 0, jadikan null)
        $kat_avg = [];
        foreach ($r['ketercapaian_kategori'] as $kat => $val) {
            $ck = $r['count_capaian_kategori'][$kat] ?? 0;
            $kat_avg[$kat] = $ck > 0 ? round($val / $ck) : null;
        }

        $hasil[] = [
            'nama_kelas'             => $r['nama_kelas'],
            'penyelenggara'          => $r['penyelenggara'],
            'jml_siswa'              => $r['jml_siswa'],
            'jml_guru'               => $r['jml_guru'],
            'tatap_muka'             => $r['tatap_muka'],
            'kehadiran'              => [
                'hadir' => $ch > 0 ? round($r['kehadiran']['hadir'] / $ch) : null,
                'izin'  => $ch > 0 ? round($r['kehadiran']['izin']  / $ch) : null,
                'sakit' => $ch > 0 ? round($r['kehadiran']['sakit'] / $ch) : null,
                'alpa'  => $ch > 0 ? round($r['kehadiran']['alpa']  / $ch) : null,
            ],
            'ketercapaian_global'    => $cg > 0 ? round($r['ketercapaian_global'] / $cg) : null,
            'ketercapaian_kategori'  => $kat_avg,
            'ada_murid'              => $r['jml_siswa'] > 0,
        ];
    }

    return ['detail_kelas' => $hasil, 'permasalahan' => $semua_permasalahan, 'catatan_desa' => $catatan_desa];
}

// ==========================================================
// 1. GET LIST (Daftar periode untuk tabel)
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
        if (!$result) throw new Exception("Query Database Error: " . $conn->error);

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
// 2. GENERATE DRAFT LAPORAN DESA
//    Membuat laporan_pjp_desa dengan rata-rata dari laporan kelompok yg ada
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
        // Cegah duplikasi laporan desa
        $cek_desa = $conn->prepare("SELECT id FROM laporan_pjp_desa WHERE periode_id = ? LIMIT 1");
        $cek_desa->bind_param("i", $periode_id);
        $cek_desa->execute();
        if ($cek_desa->get_result()->num_rows > 0) {
            throw new Exception("Laporan desa untuk periode ini sudah pernah dibuat.");
        }
        $cek_desa->close();

        // Ambil semua laporan kelompok yang ada (tanpa syarat status)
        $q_kel = $conn->prepare("SELECT kelompok_id, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ?");
        $q_kel->bind_param("i", $periode_id);
        $q_kel->execute();
        $res_kel = $q_kel->get_result();

        if ($res_kel->num_rows === 0) {
            throw new Exception("Belum ada laporan kelompok yang dibuka untuk periode ini.");
        }

        $semua_detail_kelas = [];
        $semua_permasalahan = [];
        while ($row = $res_kel->fetch_assoc()) {
            $nama_kel = $DATA_KELOMPOK[$row['kelompok_id']] ?? 'Unknown';
            $semua_detail_kelas[] = json_decode($row['detail_kelas'], true) ?: [];
            $semua_permasalahan[] = ['kelompok' => $nama_kel, 'masalah' => json_decode($row['permasalahan'], true) ?: []];
        }
        $q_kel->close();

        // Hitung rekap dengan logika akurat
        $rekap = hitungRekapDesa($semua_detail_kelas, $semua_permasalahan);

        // Query Pengurus Tingkat Desa
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
                if ($jabatan === 'pengawas')       $nama_pembina = $p['nama_pengurus'];
                elseif ($jabatan === 'ketua')      $nama_ketua   = $p['nama_pengurus'];
                elseif ($jabatan === 'wakil')      $nama_wakil   = $p['nama_pengurus'];
                elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                elseif ($jabatan === 'bendahara')  $arr_bendahara[]  = $p['nama_pengurus'];
            }
        }
        $q_pengurus->close();

        $json_kepengurusan = json_encode([
            'pembina'    => $nama_pembina,
            'ketua'      => $nama_ketua,
            'wakil'      => $nama_wakil,
            'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
            'bendahara'  => !empty($arr_bendahara)  ? implode(', ', $arr_bendahara)  : '-'
        ]);
        $json_rekap = json_encode($rekap);

        $stmt = $conn->prepare("INSERT INTO laporan_pjp_desa (periode_id, status, data_kepengurusan_desa, rekap_kelompok) VALUES (?, 'DRAFT', ?, ?)");
        $stmt->bind_param("iss", $periode_id, $json_kepengurusan, $json_rekap);
        $stmt->execute();

        $conn->commit();
        writeLog('INSERT', "Admin Desa men-generate Laporan PJP Desa untuk periode ID: $periode_id");

        ob_clean();
        echo json_encode([
            'status'  => 'success',
            'message' => 'Laporan desa berhasil dibuat dengan rekap data terkini dari seluruh kelompok.'
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
