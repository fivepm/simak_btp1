<?php
// Nyalakan output buffering untuk mencegah error PHP bocor ke file PDF
ob_start();

// Mundur 3 folder ke root (pages/export/export_laporan_desa.php -> root)
require_once '../../../../vendor/autoload.php';
require_once '../../../../config/config.php';
require_once '../../../../helpers/log_helper.php';

use Mpdf\Mpdf;

session_start();

// Header agar JavaScript bisa membaca nama file dari header HTTP
header("Access-Control-Expose-Headers: Content-Disposition");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

// 1. AMBIL DATA DARI POST MENGGUNAKAN PERIODE_ID
$periode_id = isset($_POST['periode_id']) ? (int)$_POST['periode_id'] : 0;

if ($periode_id == 0) {
    http_response_code(400);
    die("Kesalahan: ID Periode tidak valid.");
}

// Batasan Standar Kelompok yang Pasti (Mencegah Kelompok Hilang/Ganda)
$DATA_KELOMPOK = [
    1 => 'Bintaran',
    2 => 'Gedongkuning',
    3 => 'Jombor',
    4 => 'Sunten'
];

$id_admin = $_SESSION['user_id'];
$nama_admin = "Admin";
$q_admin = $conn->query("SELECT nama FROM users WHERE id = $id_admin");
if ($q_admin && $row_a = $q_admin->fetch_assoc()) {
    $nama_admin = $row_a['nama'];
}

// 2. AMBIL DATA LAPORAN DESA DARI DATABASE
$stmt = $conn->prepare("
    SELECT ld.*, p.nama_periode 
    FROM laporan_pjp_desa ld 
    JOIN periode p ON ld.periode_id = p.id 
    WHERE ld.periode_id = ? LIMIT 1
");
$stmt->bind_param("i", $periode_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die("Laporan Desa tidak ditemukan untuk periode ini.");
}

$row_desa = $result->fetch_assoc();
$nama_periode = $row_desa['nama_periode'];
$status_laporan = $row_desa['status'];
$ttd_at = $row_desa['ttd_at'] ? date('d M Y H:i', strtotime($row_desa['ttd_at'])) : '-';

// Parse JSON Data Desa
$kepengurusan_desa = json_decode($row_desa['data_kepengurusan_desa'], true) ?: [];
$rekap_kelompok_desa = json_decode($row_desa['rekap_kelompok'], true) ?: [];
$catatan_desa = $rekap_kelompok_desa['catatan_desa'] ?? '';

// 3. AMBIL DATA TTD KETUA PJP DESA DARI TABEL USERS
$nama_ketua_desa = "_______________________";
$ttd_ketua_desa_img = "";
$nama_pembina_desa = "_______________________";
$ttd_pembina_desa_img = "";

$q_ketua = $conn->query("SELECT nama, ttd FROM users WHERE role = 'ketua pjp' AND tingkat = 'desa' LIMIT 1");
if ($q_ketua && $row_ketua = $q_ketua->fetch_assoc()) {
    if (!empty($row_ketua['nama'])) {
        $nama_ketua_desa = $row_ketua['nama'];
    }
    if (!empty($row_ketua['ttd'])) {
        $ttd_path = __DIR__ . '/../../../../uploads/ttd/' . $row_ketua['ttd'];
        if (file_exists($ttd_path)) {
            $type = pathinfo($ttd_path, PATHINFO_EXTENSION);
            $data = file_get_contents($ttd_path);
            $ttd_ketua_desa_img = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
}

$q_pembina = $conn->query("SELECT nama, ttd FROM users WHERE role = 'pembina' AND tingkat = 'desa' LIMIT 1");
if ($q_pembina && $row_pembina = $q_pembina->fetch_assoc()) {
    if (!empty($row_pembina['nama'])) {
        $nama_pembina_desa = $row_pembina['nama'];
    }
    if (!empty($row_pembina['ttd'])) {
        $ttd_path = __DIR__ . '/../../../../uploads/ttd/' . $row_pembina['ttd'];
        if (file_exists($ttd_path)) {
            $type = pathinfo($ttd_path, PATHINFO_EXTENSION);
            $data = file_get_contents($ttd_path);
            $ttd_pembina_desa_img = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
}

// 4. INISIALISASI KERANGKA 4 KELOMPOK
$raw_kelompok_data = [];
foreach ($DATA_KELOMPOK as $id_kel => $nama_kel) {
    $raw_kelompok_data[$id_kel] = [
        'nama_kelompok' => $nama_kel,
        'status' => 'DRAFT',
        'checklist' => ['pjp' => false, 'unsur' => false],
        'detail_kelas' => [],
        'permasalahan' => [],
        'avg_hadir' => null,
        'avg_capaian' => null
    ];
}

// AMBIL DATA DETAIL TIAP KELOMPOK DARI DATABASE
$q_kel = $conn->prepare("SELECT kelompok_id, status, checklist_musyawarah, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ?");
$q_kel->bind_param("i", $periode_id);
$q_kel->execute();
$res_kel = $q_kel->get_result();

while ($rk = $res_kel->fetch_assoc()) {
    $k_id = $rk['kelompok_id'];
    if (isset($raw_kelompok_data[$k_id])) {
        $raw_kelompok_data[$k_id]['status'] = $rk['status'];
        $raw_kelompok_data[$k_id]['checklist'] = json_decode($rk['checklist_musyawarah'], true) ?: ['pjp' => false, 'unsur' => false];
        $raw_kelompok_data[$k_id]['detail_kelas'] = json_decode($rk['detail_kelas'], true) ?: [];
        $raw_kelompok_data[$k_id]['permasalahan'] = json_decode($rk['permasalahan'], true) ?: [];
    }
}
$q_kel->close();

writeLog('EXPORT', "Ekspor *Laporan PJP Desa* - Periode: $nama_periode");

// 5. KALKULASI RATA-RATA DESA & RENDER HTML KELOMPOK
$sumGrandHadir = 0;
$sumGrandCapaian = 0;
$validGroupHadirCount = 0;
$validGroupCapaianCount = 0;
$html_rincian_kelompok = '';

foreach ($raw_kelompok_data as &$k) {
    $totalHadir = 0;
    $totalCapaian = 0;
    $countKehadiran = 0;
    $countCapaian = 0;
    $totalSiswa = 0;
    $totalGuru = 0;

    $html_baris_kehadiran = '';
    $html_baris_materi = '';
    $count_kelas = count($k['detail_kelas']);

    if ($count_kelas > 0) {
        $no = 1;
        foreach ($k['detail_kelas'] as $kelas) {
            $jml_siswa = (int)($kelas['jml_siswa'] ?? 0);
            $jml_guru = (int)($kelas['jml_guru'] ?? 0);
            $tatap_muka = (int)($kelas['tatap_muka'] ?? 0);
            $nama_kelas = htmlspecialchars($kelas['nama_kelas'] ?? 'Unknown');

            $adaMurid = $jml_siswa > 0;
            $naCell = '<span style="color: #9ca3af; font-style: italic;">N/A</span>';
            $nama_kelas_html = $adaMurid ? '<b>' . $nama_kelas . '</b>' : $nama_kelas . ' <span style="font-size:7pt; color:#999; background:#eee; padding:2px 4px; border: 1px solid #ccc;">N/A</span>';

            $totalSiswa += $jml_siswa;
            $totalGuru += $jml_guru;

            // Logika Kehadiran
            $adaKehadiran = isset($kelas['kehadiran']['hadir']) && $kelas['kehadiran']['hadir'] !== null;
            if ($adaKehadiran) {
                $totalHadir += (float)$kelas['kehadiran']['hadir'];
                $countKehadiran++;
                $kehadiran_html = '<span style="color:green;">' . $kelas['kehadiran']['hadir'] . '%</span> | 
                                   <span style="color:blue;">' . $kelas['kehadiran']['izin'] . '%</span> | 
                                   <span style="color:#d97706;">' . $kelas['kehadiran']['sakit'] . '%</span> | 
                                   <span style="color:red;">' . $kelas['kehadiran']['alpa'] . '%</span>';
            } else {
                $kehadiran_html = $naCell;
            }

            // Logika Capaian Global
            $adaCapaian = isset($kelas['ketercapaian_global']) && $kelas['ketercapaian_global'] !== null;
            if ($adaCapaian) {
                $totalCapaian += (float)$kelas['ketercapaian_global'];
                $countCapaian++;
                $capaian_html = '<b>' . $kelas['ketercapaian_global'] . '%</b>';
            } else {
                $capaian_html = $naCell;
            }

            // Logika Kategori (Untuk tabel materi)
            $kategori_html = '';
            if (!empty($kelas['ketercapaian_kategori'])) {
                $kat_arr = [];
                foreach ($kelas['ketercapaian_kategori'] as $kat_name => $kat_val) {
                    $val_str = ($kat_val !== null) ? $kat_val . '%' : '<span style="color:#9ca3af; font-style:italic;">N/A</span>';
                    $kat_arr[] = "<b>$kat_name:</b> $val_str";
                }
                $kategori_html = implode('<br>', $kat_arr);
            } else {
                $kategori_html = '<i style="color:#999;">Belum ada data jurnal</i>';
            }

            // Generate Baris Tabel Kehadiran
            $html_baris_kehadiran .= '
            <tr>
                <td class="text-center">' . $no . '</td>
                <td>' . $nama_kelas_html . '</td>
                <td class="text-center">' . $jml_siswa . '</td>
                <td class="text-center">' . $jml_guru . '</td>
                <td class="text-center">' . $tatap_muka . 'x</td>
                <td class="text-center">' . $kehadiran_html . '</td>
            </tr>';

            // Generate Baris Tabel Ketercapaian Materi
            $html_baris_materi .= '
            <tr>
                <td class="text-center">' . $no . '</td>
                <td>' . $nama_kelas_html . '</td>
                <td class="text-center">' . $capaian_html . '</td>
                <td style="font-size: 8pt; line-height: 1.4;">' . $kategori_html . '</td>
            </tr>';

            $no++;
        }
    } else {
        $html_baris_kehadiran = '<tr><td colspan="6" class="text-center">Data kelas tidak tersedia.</td></tr>';
        $html_baris_materi = '<tr><td colspan="4" class="text-center">Data kelas tidak tersedia.</td></tr>';
    }

    // Kalkulasi Rata-rata per kelompok dengan menghindari Null Division
    $avgHadir = $countKehadiran > 0 ? round($totalHadir / $countKehadiran) : null;
    $avgCapaian = $countCapaian > 0 ? round($totalCapaian / $countCapaian) : null;

    $k['avg_hadir'] = $avgHadir;
    $k['avg_capaian'] = $avgCapaian;

    if ($avgHadir !== null) {
        $sumGrandHadir += $avgHadir;
        $validGroupHadirCount++;
    }
    if ($avgCapaian !== null) {
        $sumGrandCapaian += $avgCapaian;
        $validGroupCapaianCount++;
    }

    $strAvgHadir = $avgHadir !== null ? $avgHadir . '%' : '<span style="color:#9ca3af; font-style:italic;">N/A</span>';
    $strAvgCapaian = $avgCapaian !== null ? $avgCapaian . '%' : '<span style="color:#9ca3af; font-style:italic;">N/A</span>';

    $check_pjp = $k['checklist']['pjp'] ? '[ V ]' : '[ X ]';
    $check_unsur = $k['checklist']['unsur'] ? '[ V ]' : '[ X ]';

    $masalah_html = '<ul style="margin: 0; padding-left: 15px; font-size: 9pt;">';
    if (!empty($k['permasalahan'])) {
        foreach ($k['permasalahan'] as $m) {
            $masalah_html .= '<li>' . nl2br(htmlspecialchars($m)) . '</li>';
        }
    } else {
        $masalah_html .= '<li><i style="color:#777;">Tidak ada catatan masalah.</i></li>';
    }
    $masalah_html .= '</ul>';

    $status_kelompok = $k['status'] ?? 'DRAFT';
    $badge_status = '';
    if ($status_kelompok === 'TTD_KETUA') {
        $badge_status = '<span style="color: #166534; font-size: 8pt; font-weight: normal; padding: 2px 5px; border: 1px solid #166534; margin-left: 10px;">[SUDAH TTD]</span>';
    } elseif ($status_kelompok === 'FINAL') {
        $badge_status = '<span style="color: #1e40af; font-size: 8pt; font-weight: normal; padding: 2px 5px; border: 1px solid #1e40af; margin-left: 10px;">[MENUNGGU TTD]</span>';
    } else {
        $badge_status = '<span style="color: #854d0e; font-size: 8pt; font-weight: normal; padding: 2px 5px; border: 1px solid #854d0e; margin-left: 10px;">[DRAFT]</span>';
    }

    $html_rincian_kelompok .= '
    <div class="kelompok-box">
        <div class="kelompok-title">KELOMPOK ' . strtoupper($k['nama_kelompok']) . ' ' . $badge_status . '</div>
        <table width="100%" cellpadding="3">
            <tr>
                <td width="50%" valign="top">
                    <b>Ringkasan:</b><br>
                    Total Murid: ' . $totalSiswa . ' | Total Guru: ' . $totalGuru . '<br>
                    Rata-rata Hadir: <span style="color:green; font-weight:bold;">' . $strAvgHadir . '</span><br>
                    Materi Tercapai: <span style="color:blue; font-weight:bold;">' . $strAvgCapaian . '</span>
                </td>
                <td width="50%" valign="top">
                    <b>Daftar Musyawarah:</b><br>
                    ' . $check_pjp . ' Musyawarah PJP<br>
                    ' . $check_unsur . ' Musyawarah 5 Unsur
                </td>
            </tr>
        </table>
        <div style="margin-top: 5px; margin-bottom: 5px;"><b>Catatan Masalah:</b></div>
        ' . $masalah_html . '

        <div style="font-weight: bold; font-size: 9pt; margin-top: 15px; margin-bottom: 5px; color: #333;">1. Tabel Kehadiran Peserta Didik</div>
        <table class="table-data">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="25%">Nama Kelas</th>
                    <th width="10%">Murid</th>
                    <th width="10%">Guru</th>
                    <th width="15%">Pertemuan</th>
                    <th width="35%">Hadir|Izin|Sakit|Alpa</th>
                </tr>
            </thead>
            <tbody>' . $html_baris_kehadiran . '</tbody>
        </table>

        <div style="font-weight: bold; font-size: 9pt; margin-top: 15px; margin-bottom: 5px; color: #333;">2. Tabel Ketercapaian Materi</div>
        <table class="table-data">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="25%">Nama Kelas</th>
                    <th width="20%">Capaian Global</th>
                    <th width="50%">Rata-rata Per Kategori</th>
                </tr>
            </thead>
            <tbody>' . $html_baris_materi . '</tbody>
        </table>
    </div>';
}
unset($k);

// 6. GENERATE HTML UNTUK BAGIAN B (REKAP DESA)
$grandAvgHadir = $validGroupHadirCount > 0 ? round($sumGrandHadir / $validGroupHadirCount) : null;
$grandAvgCapaian = $validGroupCapaianCount > 0 ? round($sumGrandCapaian / $validGroupCapaianCount) : null;

$strGrandHadir = $grandAvgHadir !== null ? $grandAvgHadir . '%' : 'N/A';
$strGrandCapaian = $grandAvgCapaian !== null ? $grandAvgCapaian . '%' : 'N/A';

$html_grand_average = '
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-bottom: 15px;">
    <tr>
        <td width="50%" align="center">
            <div style="font-size: 11pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Rata-rata Kehadiran</div>
            <div style="font-size: 28pt; font-weight: 900; color: green;">' . $strGrandHadir . '</div>
        </td>
        <td width="50%" align="center">
            <div style="font-size: 11pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Rata-rata Ketercapaian Materi</div>
            <div style="font-size: 28pt; font-weight: 900; color: #2563eb;">' . $strGrandCapaian . '</div>
        </td>
    </tr>
</table>';

$html_perbandingan = '<table width="100%" border="0" cellpadding="5" cellspacing="0" style="margin-bottom: 15px;"><tr>';
$col_width = (100 / count($raw_kelompok_data));
foreach ($raw_kelompok_data as $kelData) {
    $valHadir = $kelData['avg_hadir'] !== null ? '<span style="color:green; font-weight:bold;">' . $kelData['avg_hadir'] . '%</span>' : '<span style="color:#9ca3af; font-weight:bold; font-style:italic;">N/A</span>';
    $valCapaian = $kelData['avg_capaian'] !== null ? '<span style="color:blue; font-weight:bold;">' . $kelData['avg_capaian'] . '%</span>' : '<span style="color:#9ca3af; font-weight:bold; font-style:italic;">N/A</span>';

    $html_perbandingan .= '
    <td width="' . $col_width . '%" align="center">
        <div style="font-weight:bold; color:#1e40af; margin-bottom: 5px; font-size:11pt;">' . strtoupper($kelData['nama_kelompok']) . '</div>
        <div style="font-size: 10pt; margin-bottom: 3px; color:#333;">Hadir: ' . $valHadir . '</div>
        <div style="font-size: 10pt; color:#333;">Tercapai: ' . $valCapaian . '</div>
    </td>';
}
$html_perbandingan .= '</tr></table>';

// Buat Tabel Kehadiran & Materi terpisah di Bagian Desa
$html_kelas_desa_kehadiran = '<table class="table-data">
    <thead>
        <tr>
            <th width="5%">No</th>
            <th width="25%">Nama Kelas</th>
            <th width="10%">Murid</th>
            <th width="10%">Guru</th>
            <th width="15%">Pertemuan</th>
            <th width="35%">Hadir | Izin | Sakit | Alpa</th>
        </tr>
    </thead>
    <tbody>';

$html_kelas_desa_materi = '<table class="table-data">
    <thead>
        <tr>
            <th width="5%">No</th>
            <th width="25%">Nama Kelas</th>
            <th width="20%">Capaian Global</th>
            <th width="50%">Rata-rata Per Kategori</th>
        </tr>
    </thead>
    <tbody>';

if (empty($rekap_kelompok_desa['detail_kelas'])) {
    $html_kelas_desa_kehadiran .= '<tr><td colspan="6" class="text-center">Data kelas tidak tersedia.</td></tr>';
    $html_kelas_desa_materi .= '<tr><td colspan="4" class="text-center">Data kelas tidak tersedia.</td></tr>';
} else {
    $no_d = 1;
    foreach ($rekap_kelompok_desa['detail_kelas'] as $kd) {
        $jml_siswa_d = (int)($kd['jml_siswa'] ?? 0);
        $jml_guru_d = (int)($kd['jml_guru'] ?? 0);
        $tatap_muka_d = (int)($kd['tatap_muka'] ?? 0);

        $adaMuridDesa = $jml_siswa_d > 0;
        $naCellDesa = '<span style="color: #9ca3af; font-style: italic;">N/A</span>';
        $nama_kelas_desa_html = $adaMuridDesa ? '<b>' . htmlspecialchars($kd['nama_kelas']) . '</b>' : htmlspecialchars($kd['nama_kelas']) . ' <span style="font-size:7pt; color:#999; background:#eee; padding:2px 4px; border: 1px solid #ccc;">N/A</span>';

        $adaHadirDesa = isset($kd['kehadiran']['hadir']) && $kd['kehadiran']['hadir'] !== null;
        $kehadiran_desa_html = $adaHadirDesa ?
            '<span style="color:green;">' . $kd['kehadiran']['hadir'] . '%</span> | 
             <span style="color:blue;">' . $kd['kehadiran']['izin'] . '%</span> | 
             <span style="color:#d97706;">' . $kd['kehadiran']['sakit'] . '%</span> | 
             <span style="color:red;">' . $kd['kehadiran']['alpa'] . '%</span>'
            : $naCellDesa;

        $adaCapaianDesa = isset($kd['ketercapaian_global']) && $kd['ketercapaian_global'] !== null;
        $capaian_desa_html = $adaCapaianDesa ? '<b>' . $kd['ketercapaian_global'] . '%</b>' : $naCellDesa;

        $kategori_desa_html = '';
        if (!empty($kd['ketercapaian_kategori'])) {
            $kat_arr_d = [];
            foreach ($kd['ketercapaian_kategori'] as $k_name => $k_val) {
                $v_str = ($k_val !== null) ? $k_val . '%' : '<span style="color:#9ca3af; font-style:italic;">N/A</span>';
                $kat_arr_d[] = "<b>$k_name:</b> $v_str";
            }
            $kategori_desa_html = implode('<br>', $kat_arr_d);
        } else {
            $kategori_desa_html = '<i style="color:#999;">Belum ada data jurnal</i>';
        }

        $html_kelas_desa_kehadiran .= '<tr>
            <td class="text-center">' . $no_d . '</td>
            <td>' . $nama_kelas_desa_html . '</td>
            <td class="text-center">' . $jml_siswa_d . '</td>
            <td class="text-center">' . $jml_guru_d . '</td>
            <td class="text-center">' . $tatap_muka_d . 'x</td>
            <td class="text-center">' . $kehadiran_desa_html . '</td>
        </tr>';

        $html_kelas_desa_materi .= '<tr>
            <td class="text-center">' . $no_d . '</td>
            <td>' . $nama_kelas_desa_html . '</td>
            <td class="text-center">' . $capaian_desa_html . '</td>
            <td style="font-size: 8pt; line-height: 1.4;">' . $kategori_desa_html . '</td>
        </tr>';

        $no_d++;
    }
}
$html_kelas_desa_kehadiran .= '</tbody></table>';
$html_kelas_desa_materi .= '</tbody></table>';

$catatan_html = trim($catatan_desa) === ''
    ? '<i style="color:#777;">Tidak ada catatan/evaluasi.</i>'
    : nl2br(htmlspecialchars($catatan_desa));

$clean_periode = preg_replace('/[^A-Za-z0-9\-]/', '_', $nama_periode);
$filename_base = "Laporan_PJP_Desa_" . $clean_periode;

// 7. SETUP MPDF DAN RENDER DOKUMEN
try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-P',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 5,
        'margin_footer' => 5
    ]);

    $watermarkPath = '../../../assets/images/logo_kbm.png';
    if (file_exists($watermarkPath)) {
        $mpdf->SetWatermarkImage($watermarkPath, 0.05, [100, 100]);
        $mpdf->showWatermarkImage = true;
    }

    $logo_kiri_path = __DIR__ . '/../../../assets/images/logo_kbm.png';
    $logo_kanan_path = __DIR__ . '/../../../assets/images/logo_simak.png';

    function imageToBase64($path)
    {
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        return false;
    }

    $img_kiri = imageToBase64($logo_kiri_path);
    $img_kanan = imageToBase64($logo_kanan_path);

    $mpdf->SetFooter('Dicetak pada: {DATE d-m-Y H:i} Oleh: ' . htmlspecialchars($nama_admin) . '||Halaman {PAGENO}/{nbpg}');

    $stylesheet = '
        body { font-family: sans-serif; font-size: 10pt; color: #111111; }
        .header-table { width: 100%; border-bottom: 2px solid #111111; padding-bottom: 10px; margin-bottom: 20px; }
        .header-text { text-align: center; }
        .title-main { font-size: 14pt; font-weight: bold; margin-bottom: 2px; color: #111111; }
        .title-sub { font-size: 11pt; font-weight: bold; margin-bottom: 2px; }
        .title-desc { font-size: 9pt; font-style: italic; color: #555; }
        h4 { background-color: #e0e7ff; padding: 6px 10px; border-left: 4px solid #2563eb; font-size: 11pt; margin-top: 15px; margin-bottom: 10px; color: #111111; }
        .meta-table { width: 100%; border: none; margin-bottom: 15px; font-size: 10pt; }
        .meta-table td { padding: 4px; vertical-align: top; }
        .table-data { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 10px; }
        .table-data th { background-color: #2563eb; color: #fff; border: 1px solid #2563eb; padding: 8px; font-size: 9pt; }
        .table-data td { border: 1px solid #ccc; padding: 6px; font-size: 9pt; vertical-align: middle; }
        .text-center { text-align: center; }
        .catatan-box { padding: 10px; font-size: 10pt; line-height: 1.5; border: 1px solid #e5e7eb; border-radius: 4px; }
        .kelompok-box { border: 1px solid #cbd5e1; padding: 10px; margin-bottom: 15px; page-break-inside: avoid; }
        .kelompok-title { background-color: #f1f5f9; padding: 5px; font-weight: bold; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px; font-size: 11pt; }
    ';

    $html = '
    <table class="header-table">
        <tr>
            <td width="15%" align="left">' . ($img_kiri ? '<img src="' . $img_kiri . '" width="60px">' : '') . '</td>
            <td width="70%" class="header-text">
                <div class="title-sub">PJP BANGUNTAPAN 1</div>
                <div class="title-main">LAPORAN PJP DESA</div>
                <div class="title-desc">Sistem Informasi Monitoring Akademik</div>
            </td>
            <td width="15%" align="right">' . ($img_kanan ? '<img src="' . $img_kanan . '" width="60px">' : '') . '</td>
        </tr>
    </table>
    
    <table class="meta-table">
        <tr>
            <td width="15%"><strong>Desa</strong></td>
            <td width="2%">:</td>
            <td width="33%">Banguntapan 1</td>
            <td width="15%"><strong>Status</strong></td>
            <td width="2%">:</td>
            <td width="33%">' . ($status_laporan == 'TTD_KETUA' ? 'Disahkan' : $status_laporan) . '</td>
        </tr>
        <tr>
            <td><strong>Periode</strong></td>
            <td>:</td>
            <td>' . htmlspecialchars($nama_periode) . '</td>
            <td><strong>Waktu TTD</strong></td>
            <td>:</td>
            <td>' . $ttd_at . '</td>
        </tr>
    </table>

    <h4>A. DATA KEPENGURUSAN PJP DESA</h4>
    <table width="100%" cellpadding="3" style="font-size:10pt;">
        <tr>
            <td width="50%" valign="top">
                <table width="100%">
                    <tr><td width="35%"><b>Pengawas</b></td><td>: ' . ($kepengurusan_desa['pembina'] ?? '-') . '</td></tr>
                    <tr><td><b>Ketua PJP</b></td><td>: ' . ($kepengurusan_desa['ketua'] ?? '-') . '</td></tr>
                    <tr><td><b>Wakil Ketua</b></td><td>: ' . ($kepengurusan_desa['wakil'] ?? '-') . '</td></tr>
                </table>
            </td>
            <td width="50%" valign="top">
                <table width="100%">
                    <tr><td width="35%"><b>Sekretaris</b></td><td>: ' . ($kepengurusan_desa['sekretaris'] ?? '-') . '</td></tr>
                    <tr><td><b>Bendahara</b></td><td>: ' . ($kepengurusan_desa['bendahara'] ?? '-') . '</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h4>B. REKAPITULASI TINGKAT DESA</h4>
    ' . $html_grand_average . '
    ' . $html_perbandingan . '

    <div style="font-weight: bold; font-size: 10pt; margin-top: 15px; margin-bottom: 5px; color: #333;">1. Tabel Kehadiran Peserta Didik (Desa)</div>
    ' . $html_kelas_desa_kehadiran . '
    
    <div style="font-weight: bold; font-size: 10pt; margin-top: 15px; margin-bottom: 5px; color: #333;">2. Tabel Ketercapaian Materi (Desa)</div>
    ' . $html_kelas_desa_materi . '

    <h4>C. CATATAN / EVALUASI TINGKAT DESA</h4>
    <div class="catatan-box">
        ' . $catatan_html . '
    </div>

    <pagebreak />

    <h4>D. RINCIAN LAPORAN TIAP KELOMPOK</h4>
    ' . $html_rincian_kelompok . '

    <br><br>
    <table width="100%" style="margin-top: 5px; page-break-inside: avoid;">
        <tr>
            <td width="40%" align="center">
                Menyetujui,<br>
                Pembina Desa Banguntapan 1<br>
                ' . ($ttd_pembina_desa_img ? '<img src="' . $ttd_pembina_desa_img . '" style="height: 70px; margin-top: 5px; margin-bottom: 5px;">' : '<br><br><br><br>') . '<br>
                <b><u>' . htmlspecialchars($nama_pembina_desa) . '</u></b>
            </td>
            <td width="20%"></td>
            <td width="40%" align="center">
                Mengetahui,<br>
                Ketua PJP Desa Banguntapan 1<br>
                ' . ($ttd_ketua_desa_img ? '<img src="' . $ttd_ketua_desa_img . '" style="height: 70px; margin-top: 5px; margin-bottom: 5px;">' : '<br><br><br><br>') . '<br>
                <b><u>' . htmlspecialchars($nama_ketua_desa) . '</u></b>
            </td>
        </tr>
    </table>
    ';

    $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $final_filename = $filename_base . ".pdf";

    // BERSIHKAN OUTPUT BUFFER SEBELUM KIRIM PDF
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $final_filename . '"');

    echo $mpdf->Output('', 'S');
} catch (\Mpdf\MpdfException $e) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    echo "Kesalahan saat membuat PDF: " . $e->getMessage();
}
