<?php
// Mundur 3 folder ke root (pages/export/export_laporan_desa.php -> root)
require_once '../../../vendor/autoload.php';
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

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
// Catatan: Di JS Anda mengirimkan 'laporan_desa_id', tapi isinya adalah ID Periode. Kita tampung di sini.

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

$q_ketua = $conn->query("SELECT nama, ttd FROM users WHERE role = 'ketua pjp' AND tingkat = 'desa' LIMIT 1");
if ($q_ketua && $row_ketua = $q_ketua->fetch_assoc()) {
    if (!empty($row_ketua['nama'])) {
        $nama_ketua_desa = $row_ketua['nama'];
    }
    if (!empty($row_ketua['ttd'])) {
        $ttd_path = __DIR__ . '/../../../uploads/ttd/' . $row_ketua['ttd'];
        if (file_exists($ttd_path)) {
            $type = pathinfo($ttd_path, PATHINFO_EXTENSION);
            $data = file_get_contents($ttd_path);
            $ttd_ketua_desa_img = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
}

// 4. INISIALISASI KERANGKA 4 KELOMPOK (Mencegah Sunten hilang/Jombor ganda)
$raw_kelompok_data = [];
foreach ($DATA_KELOMPOK as $id_kel => $nama_kel) {
    $raw_kelompok_data[$id_kel] = [
        'nama_kelompok' => $nama_kel,
        'checklist' => ['pjp' => false, 'unsur' => false],
        'detail_kelas' => [],
        'permasalahan' => [],
        'avg_hadir' => 0,
        'avg_capaian' => 0
    ];
}

// AMBIL DATA DETAIL TIAP KELOMPOK DARI DATABASE
$q_kel = $conn->prepare("SELECT kelompok_id, checklist_musyawarah, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ?");
$q_kel->bind_param("i", $periode_id);
$q_kel->execute();
$res_kel = $q_kel->get_result();

while ($rk = $res_kel->fetch_assoc()) {
    $k_id = $rk['kelompok_id'];
    // Masukkan data sesuai dengan ID Kelompok aslinya
    if (isset($raw_kelompok_data[$k_id])) {
        $raw_kelompok_data[$k_id]['checklist'] = json_decode($rk['checklist_musyawarah'], true) ?: ['pjp' => false, 'unsur' => false];
        $raw_kelompok_data[$k_id]['detail_kelas'] = json_decode($rk['detail_kelas'], true) ?: [];
        $raw_kelompok_data[$k_id]['permasalahan'] = json_decode($rk['permasalahan'], true) ?: [];
    }
}
$q_kel->close();

// === PENCATATAN LOG ===
writeLog('EXPORT', "Ekspor *Laporan PJP Desa* - Periode: $nama_periode");

// 5. KALKULASI RATA-RATA DESA & RENDER HTML KELOMPOK
$sumGrandHadir = 0;
$sumGrandCapaian = 0;
$validGroupCount = 0;
$html_rincian_kelompok = '';

foreach ($raw_kelompok_data as &$k) {
    $totalHadir = 0;
    $totalIzin = 0;
    $totalSakit = 0;
    $totalAlpa = 0;
    $totalCapaian = 0;
    $totalSiswa = 0;
    $totalGuru = 0;
    $count_kelas = count($k['detail_kelas']);
    $html_baris_kelas = '';

    if ($count_kelas > 0) {
        $no = 1;
        foreach ($k['detail_kelas'] as $kelas) {
            $totalHadir += (float)($kelas['kehadiran']['hadir'] ?? 0);
            $totalIzin += (float)($kelas['kehadiran']['izin'] ?? 0);
            $totalSakit += (float)($kelas['kehadiran']['sakit'] ?? 0);
            $totalAlpa += (float)($kelas['kehadiran']['alpa'] ?? 0);
            $totalCapaian += (float)($kelas['ketercapaian_global'] ?? 0);
            $totalSiswa += (int)($kelas['jml_siswa'] ?? 0);
            $totalGuru += (int)($kelas['jml_guru'] ?? 0);

            $html_baris_kelas .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td><b>' . htmlspecialchars($kelas['nama_kelas']) . '</b></td>
                <td class="text-center">' . $kelas['jml_siswa'] . '</td>
                <td class="text-center">' . $kelas['jml_guru'] . '</td>
                <td class="text-center">' . $kelas['tatap_muka'] . 'x</td>
                <td class="text-center">
                    <span style="color:green;">' . ($kelas['kehadiran']['hadir'] ?? 0) . '%</span> | 
                    <span style="color:blue;">' . ($kelas['kehadiran']['izin'] ?? 0) . '%</span> | 
                    <span style="color:#d97706;">' . ($kelas['kehadiran']['sakit'] ?? 0) . '%</span> | 
                    <span style="color:red;">' . ($kelas['kehadiran']['alpa'] ?? 0) . '%</span>
                </td>
                <td class="text-center"><b>' . ($kelas['ketercapaian_global'] ?? 0) . '%</b></td>
            </tr>';
        }
    } else {
        $html_baris_kelas = '<tr><td colspan="7" class="text-center">Data kelas tidak tersedia.</td></tr>';
    }

    $avgHadir = $count_kelas > 0 ? round($totalHadir / $count_kelas) : 0;
    $avgCapaian = $count_kelas > 0 ? round($totalCapaian / $count_kelas) : 0;

    // Simpan untuk tabel perbandingan Bagian B
    $k['avg_hadir'] = $avgHadir;
    $k['avg_capaian'] = $avgCapaian;

    if ($count_kelas > 0) {
        $sumGrandHadir += $avgHadir;
        $sumGrandCapaian += $avgCapaian;
        $validGroupCount++;
    }

    $check_pjp = $k['checklist']['pjp'] ? '[ V ]' : '[ &nbsp;&nbsp; ]';
    $check_unsur = $k['checklist']['unsur'] ? '[ V ]' : '[ &nbsp;&nbsp; ]';

    $masalah_html = '<ul style="margin: 0; padding-left: 15px; font-size: 9pt;">';
    if (!empty($k['permasalahan'])) {
        foreach ($k['permasalahan'] as $m) {
            $masalah_html .= '<li>' . nl2br(htmlspecialchars($m)) . '</li>';
        }
    } else {
        $masalah_html .= '<li><i style="color:#777;">Tidak ada catatan masalah.</i></li>';
    }
    $masalah_html .= '</ul>';

    $html_rincian_kelompok .= '
    <div class="kelompok-box">
        <div class="kelompok-title">KELOMPOK ' . strtoupper($k['nama_kelompok']) . '</div>
        <table width="100%" cellpadding="3">
            <tr>
                <td width="50%" valign="top">
                    <b>Ringkasan:</b><br>
                    Total Murid: ' . $totalSiswa . ' | Total Guru: ' . $totalGuru . '<br>
                    Rata-rata Hadir: <span style="color:green; font-weight:bold;">' . $avgHadir . '%</span><br>
                    Materi Tercapai: <span style="color:blue; font-weight:bold;">' . $avgCapaian . '%</span>
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

        <table class="table-data" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">Nama Kelas</th>
                    <th width="10%">Murid</th>
                    <th width="10%">Guru</th>
                    <th width="15%">Tatap Muka</th>
                    <th width="25%">Hadir|Izin|Sakit|Alpa</th>
                    <th width="15%"> Materi</th>
                </tr>
            </thead>
            <tbody>' . $html_baris_kelas . '</tbody>
        </table>
    </div>';
}

// PENTING: Hapus referensi variabel agar tidak tumpang tindih di loop selanjutnya!
unset($k);

// =========================================================================
// 6. GENERATE HTML UNTUK BAGIAN B (Tabel Borderless Rata Tengah)
// =========================================================================
$grandAvgHadir = $validGroupCount > 0 ? round($sumGrandHadir / $validGroupCount) : 0;
$grandAvgCapaian = $validGroupCount > 0 ? round($sumGrandCapaian / $validGroupCount) : 0;

// Rata-rata Grand Desa (Borderless & Centered)
$html_grand_average = '
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-bottom: 15px;">
    <tr>
        <td width="50%" align="center">
            <div style="font-size: 11pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Rata-rata Kehadiran</div>
            <div style="font-size: 28pt; font-weight: 900; color: green;">' . $grandAvgHadir . '%</div>
        </td>
        <td width="50%" align="center">
            <div style="font-size: 11pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Rata-rata Ketercapaian Materi</div>
            <div style="font-size: 28pt; font-weight: 900; color: #2563eb;">' . $grandAvgCapaian . '%</div>
        </td>
    </tr>
</table>';

// Perbandingan Antar Kelompok (Borderless & Centered)
$html_perbandingan = '<table width="100%" border="0" cellpadding="5" cellspacing="0" style="margin-bottom: 15px;"><tr>';
$col_width = (100 / count($raw_kelompok_data));
foreach ($raw_kelompok_data as $kelData) { // Menggunakan variabel baru untuk mencegah bug referensi
    $html_perbandingan .= '
    <td width="' . $col_width . '%" align="center">
        <div style="font-weight:bold; color:#1e40af; margin-bottom: 5px; font-size:11pt;">' . strtoupper($kelData['nama_kelompok']) . '</div>
        <div style="font-size: 10pt; margin-bottom: 3px; color:#333;">Hadir: <span style="color:green; font-weight:bold;">' . $kelData['avg_hadir'] . '%</span></div>
        <div style="font-size: 10pt; color:#333;">Tercapai: <span style="color:blue; font-weight:bold;">' . $kelData['avg_capaian'] . '%</span></div>
    </td>';
}
$html_perbandingan .= '</tr></table>';


// Tabel Rincian Kelas Tingkat Desa
$html_kelas_desa = '<table class="table-data" style="margin-top: 10px;">
    <thead>
        <tr>
            <th width="5%">No</th>
            <th width="25%">Nama Kelas</th>
            <th width="10%">Murid</th>
            <th width="10%">Guru</th>
            <th width="30%">Hadir | Izin | Sakit | Alpa</th>
            <th width="20%">Materi Tercapai</th>
        </tr>
    </thead>
    <tbody>';

if (empty($rekap_kelompok_desa['detail_kelas'])) {
    $html_kelas_desa .= '<tr><td colspan="6" class="text-center">Data kelas tidak tersedia.</td></tr>';
} else {
    $no_d = 1;
    foreach ($rekap_kelompok_desa['detail_kelas'] as $kd) {
        $html_kelas_desa .= '<tr>
            <td class="text-center">' . $no_d++ . '</td>
            <td><b>' . htmlspecialchars($kd['nama_kelas']) . '</b></td>
            <td class="text-center">' . ($kd['jml_siswa'] ?? 0) . '</td>
            <td class="text-center">' . ($kd['jml_guru'] ?? 0) . '</td>
            <td class="text-center">
                <span style="color:green;">' . ($kd['kehadiran']['hadir'] ?? 0) . '%</span> | 
                <span style="color:blue;">' . ($kd['kehadiran']['izin'] ?? 0) . '%</span> | 
                <span style="color:#d97706;">' . ($kd['kehadiran']['sakit'] ?? 0) . '%</span> | 
                <span style="color:red;">' . ($kd['kehadiran']['alpa'] ?? 0) . '%</span>
            </td>
            <td class="text-center"><b>' . ($kd['ketercapaian_global'] ?? 0) . '%</b></td>
        </tr>';
    }
}
$html_kelas_desa .= '</tbody></table>';

// Html Catatan (Bagian C)
$catatan_html = trim($catatan_desa) === ''
    ? '<i style="color:#777;">Tidak ada catatan/evaluasi.</i>'
    : nl2br(htmlspecialchars($catatan_desa));

$clean_periode = preg_replace('/[^A-Za-z0-9\-]/', '_', $nama_periode);
$filename_base = "Laporan_PJP_Desa_" . $clean_periode;

// =========================================================================
// 7. SETUP MPDF DAN RENDER DOKUMEN
// =========================================================================
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

    // Watermark
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
    
    <!-- Rata-rata Grand Desa -->
    ' . $html_grand_average . '
    
    <!-- Perbandingan Antar Kelompok -->
    ' . $html_perbandingan . '

    <!-- Rincian Kehadiran & Ketercapaian Kelas -->
    ' . $html_kelas_desa . '

    <h4>C. CATATAN / EVALUASI TINGKAT DESA</h4>
    <div class="catatan-box">
        ' . $catatan_html . '
    </div>

    <pagebreak />

    <h4>D. RINCIAN LAPORAN TIAP KELOMPOK</h4>
    ' . $html_rincian_kelompok . '

    <br><br>
    <table width="100%" style="margin-top: 30px; page-break-inside: avoid;">
        <tr>
            <td width="70%"></td>
            <td width="30%" align="center">
                Mengetahui,<br>
                Ketua PJP Desa Banguntapan 1<br>
                ' . ($ttd_ketua_desa_img ? '<img src="' . $ttd_ketua_desa_img . '" style="height: 70px; margin-top: 5px; margin-bottom: 5px;">' : '<br><br><br><br>') . '
                <b><u>' . htmlspecialchars($nama_ketua_desa) . '</u></b>
            </td>
        </tr>
    </table>
    ';

    $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $final_filename = $filename_base . ".pdf";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $final_filename . '"');

    echo $mpdf->Output('', 'S');
} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo "Kesalahan saat membuat PDF: " . $e->getMessage();
}
