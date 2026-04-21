<?php
// Pastikan path vendor/autoload.php sesuai dengan struktur folder kamu
// Jika file ini di pages/laporan_kelompok/, maka mundurnya 2 level (../../)
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

// 1. AMBIL DATA DARI POST
$laporan_id = isset($_POST['laporan_id']) ? (int)$_POST['laporan_id'] : 0;

if ($laporan_id == 0) {
    http_response_code(400);
    die("Error: ID Laporan tidak valid.");
}

// Definisi Standar Kelompok (Hardcode sementara karena tabel kelompok belum direlasikan penuh)
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];

$id_admin = $_SESSION['user_id'];
$nama_admin = "Admin";
$q_admin = $conn->query("SELECT nama FROM users WHERE id = $id_admin");
if ($q_admin && $row_a = $q_admin->fetch_assoc()) {
    $nama_admin = $row_a['nama'];
}

// 2. AMBIL DATA LAPORAN KELOMPOK DARI DATABASE
$stmt = $conn->prepare("
    SELECT lk.*, p.nama_periode 
    FROM laporan_pjp_kelompok lk 
    JOIN periode p ON lk.periode_id = p.id 
    WHERE lk.id = ? LIMIT 1
");
$stmt->bind_param("i", $laporan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die("Laporan tidak ditemukan.");
}

$row = $result->fetch_assoc();
$nama_periode = $row['nama_periode'];
$nama_kelompok = $DATA_KELOMPOK[$row['kelompok_id']] ?? 'Unknown';
$status_laporan = $row['status'];
$ttd_at = $row['ttd_at'] ? date('d M Y H:i', strtotime($row['ttd_at'])) : '-';

// Parse JSON Data
$kepengurusan = json_decode($row['data_kepengurusan'], true) ?: [];
$checklist = json_decode($row['checklist_musyawarah'], true) ?: ['pjp' => false, 'unsur' => false];
$detail_kelas = json_decode($row['detail_kelas'], true) ?: [];
$permasalahan = json_decode($row['permasalahan'], true) ?: [];

// 3. AMBIL DATA TTD KETUA PJP KELOMPOK DARI TABEL USERS
// Ambil nama dari JSON sebagai default jika di database tidak ditemukan
$nama_ketua_kelompok = $kepengurusan['ketua'] ?? "_______________________";
$ttd_ketua_kelompok_img = "";

$q_ketua = $conn->prepare("SELECT nama, ttd FROM users WHERE role = 'ketua pjp' AND tingkat = 'kelompok' AND kelompok = ? LIMIT 1");
$q_ketua->bind_param("s", $nama_kelompok);
$q_ketua->execute();
$res_ketua = $q_ketua->get_result();

if ($res_ketua && $row_ketua = $res_ketua->fetch_assoc()) {
    // Timpa nama ketua menggunakan nama dari tabel users
    if (!empty($row_ketua['nama'])) {
        $nama_ketua_kelompok = $row_ketua['nama'];
    }
    // Konversi gambar ttd menjadi base64 untuk PDF
    if (!empty($row_ketua['ttd'])) {
        $ttd_path = __DIR__ . '/../../../../uploads/ttd/' . $row_ketua['ttd'];
        if (file_exists($ttd_path)) {
            $type = pathinfo($ttd_path, PATHINFO_EXTENSION);
            $data = file_get_contents($ttd_path);
            $ttd_ketua_kelompok_img = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
}
$q_ketua->close();

// === PENCATATAN LOG ===
writeLog('EXPORT', "Ekspor *Laporan PJP Kelompok* - Periode: $nama_periode, Kelompok: $nama_kelompok");

// 4. KALKULASI RATA-RATA TINGKAT KELOMPOK
$totalHadir = 0;
$totalIzin = 0;
$totalSakit = 0;
$totalAlpa = 0;
$totalCapaian = 0;
$count_kelas = count($detail_kelas);

if ($count_kelas > 0) {
    foreach ($detail_kelas as $k) {
        $totalHadir += (float)($k['kehadiran']['hadir'] ?? 0);
        $totalIzin += (float)($k['kehadiran']['izin'] ?? 0);
        $totalSakit += (float)($k['kehadiran']['sakit'] ?? 0);
        $totalAlpa += (float)($k['kehadiran']['alpa'] ?? 0);
        $totalCapaian += (float)($k['ketercapaian_global'] ?? 0);
    }
}
$avgHadir = $count_kelas > 0 ? round($totalHadir / $count_kelas) : 0;
$avgIzin = $count_kelas > 0 ? round($totalIzin / $count_kelas) : 0;
$avgSakit = $count_kelas > 0 ? round($totalSakit / $count_kelas) : 0;
$avgAlpa = $count_kelas > 0 ? round($totalAlpa / $count_kelas) : 0;
$avgCapaian = $count_kelas > 0 ? round($totalCapaian / $count_kelas) : 0;

$clean_periode = preg_replace('/[^A-Za-z0-9\-]/', '_', $nama_periode);
$filename_base = "Laporan_PJP_" . $nama_kelompok . "_" . $clean_periode;

// 5. FORMAT PDF MENGGUNAKAN MPDF
try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-P', // P = Portrait
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 5,
        'margin_footer' => 5
    ]);

    // === SETTING WATERMARK (GAMBAR JPG) ===
    $watermarkPath = '../../../assets/images/logo_kbm.png'; // Sesuaikan path jika perlu
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

        h4 { background-color: #f3f4f6; padding: 5px 10px; border-left: 4px solid #0f766e; font-size: 11pt; margin-top: 15px; margin-bottom: 10px; color: #111111; }
        
        .meta-table { width: 100%; border: none; margin-bottom: 15px; font-size: 10pt; }
        .meta-table td { padding: 4px; vertical-align: top; }
        
        .table-data { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 15px; }
        .table-data th { background-color: #0f766e; color: #fff; border: 1px solid #0f766e; padding: 8px; font-size: 9pt; }
        .table-data td { border: 1px solid #ccc; padding: 6px; font-size: 9pt; vertical-align: middle; }
        .text-center { text-align: center; }
        
        ul { margin-top: 0; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .badge { font-size: 8pt; padding: 2px 5px; border-radius: 3px; background-color: #eee; border: 1px solid #ccc; }
    ';

    // HTML Checklist Musyawarah
    $check_pjp = $checklist['pjp'] ? '[ V ]' : '[ X ]';
    $check_unsur = $checklist['unsur'] ? '[ V ]' : '[ X ]';

    // HTML Wali Kelas
    $wk_html = '<ul style="margin:0; padding-left:15px;">';
    if (!empty($kepengurusan['wali_kelas'])) {
        foreach ($kepengurusan['wali_kelas'] as $kls => $wk_nama) {
            $kls = ucwords($kls);
            $wk_html .= "<li>$kls : $wk_nama</li>";
        }
    } else {
        $wk_html .= "<li>-</li>";
    }
    $wk_html .= '</ul>';

    // HTML Detail Kelas
    $detail_kelas_html = '';
    if (empty($detail_kelas)) {
        $detail_kelas_html = '<tr><td colspan="7" class="text-center">Tidak ada data kelas.</td></tr>';
    } else {
        $no = 1;
        foreach ($detail_kelas as $kls) {
            $penyelenggara = ucfirst($kls['penyelenggara'] ?? '-');
            $detail_kelas_html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td><b>' . $kls['nama_kelas'] . '</b><br><span style="font-size:7pt; color:#666;">KBM: ' . $penyelenggara . '</span></td>
                <td class="text-center">' . $kls['jml_siswa'] . '</td>
                <td class="text-center">' . $kls['jml_guru'] . '</td>
                <td class="text-center">' . $kls['tatap_muka'] . 'x</td>
                <td class="text-center">
                    <span style="color:green;">' . ($kls['kehadiran']['hadir'] ?? 0) . '%</span> / 
                    <span style="color:blue;">' . ($kls['kehadiran']['izin'] ?? 0) . '%</span> / 
                    <span style="color:#d97706;">' . ($kls['kehadiran']['sakit'] ?? 0) . '%</span> / 
                    <span style="color:red;">' . ($kls['kehadiran']['alpa'] ?? 0) . '%</span>
                </td>
                <td class="text-center"><b>' . ($kls['ketercapaian_global'] ?? 0) . '%</b></td>
            </tr>';
        }
    }

    // HTML Permasalahan
    $masalah_html = '<ul style="margin-top:5px;">';
    if (!empty($permasalahan)) {
        foreach ($permasalahan as $m) {
            $masalah_html .= '<li>' . nl2br(htmlspecialchars($m)) . '</li>';
        }
    } else {
        $masalah_html .= '<li><i style="color:#777;">Tidak ada catatan permasalahan.</i></li>';
    }
    $masalah_html .= '</ul>';

    $html = '
    <table class="header-table">
        <tr>
            <td width="15%" align="left">' . ($img_kiri ? '<img src="' . $img_kiri . '" width="60px">' : '') . '</td>
            <td width="70%" class="header-text">
                <div class="title-sub">PJP BANGUNTAPAN 1</div>
                <div class="title-main">LAPORAN PJP KELOMPOK</div>
                <div class="title-desc">Sistem Informasi Monitoring Akademik</div>
            </td>
            <td width="15%" align="right">' . ($img_kanan ? '<img src="' . $img_kanan . '" width="60px">' : '') . '</td>
        </tr>
    </table>
    
    <table class="meta-table">
        <tr>
            <td width="15%"><strong>Kelompok</strong></td>
            <td width="2%">:</td>
            <td width="33%">' . htmlspecialchars(ucwords($nama_kelompok)) . '</td>
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

    <h4>A. DATA KEPENGURUSAN PJP KELOMPOK</h4>
    <table width="100%" cellpadding="3" style="font-size:10pt;">
        <tr>
            <td width="50%" valign="top">
                <table width="100%">
                    <tr><td width="35%"><b>Pengawas</b></td><td>: ' . ($kepengurusan['pengawas'] ?? '-') . '</td></tr>
                    <tr><td><b>Ketua</b></td><td>: ' . ($kepengurusan['ketua'] ?? '-') . '</td></tr>
                    <tr><td><b>Wakil Ketua</b></td><td>: ' . ($kepengurusan['wakil'] ?? '-') . '</td></tr>
                    <tr><td><b>Sekretaris</b></td><td>: ' . ($kepengurusan['sekretaris'] ?? '-') . '</td></tr>
                    <tr><td><b>Bendahara</b></td><td>: ' . ($kepengurusan['bendahara'] ?? '-') . '</td></tr>
                </table>
            </td>
            <td width="50%" valign="top">
                <b>Wali Kelas:</b><br>
                ' . $wk_html . '
            </td>
        </tr>
    </table>

    <h4>B. REKAPITULASI RATA-RATA KELOMPOK</h4>
    <table width="100%" border="0" cellpadding="5" cellspacing="0" style="margin-bottom: 15px;">
        <tr>
            <td width="20%" align="center">
                <div style="font-size: 10pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Hadir</div>
                <div style="font-size: 20pt; font-weight: 900; color: green;">' . $avgHadir . '%</div>
            </td>
            <td width="20%" align="center">
                <div style="font-size: 10pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Izin</div>
                <div style="font-size: 20pt; font-weight: 900; color: blue;">' . $avgIzin . '%</div>
            </td>
            <td width="20%" align="center">
                <div style="font-size: 10pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Sakit</div>
                <div style="font-size: 20pt; font-weight: 900; color: #d97706;">' . $avgSakit . '%</div>
            </td>
            <td width="20%" align="center">
                <div style="font-size: 10pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Alpa</div>
                <div style="font-size: 20pt; font-weight: 900; color: red;">' . $avgAlpa . '%</div>
            </td>
            <td width="20%" align="center">
                <div style="font-size: 10pt; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Materi</div>
                <div style="font-size: 20pt; font-weight: 900; color: #0f766e;">' . $avgCapaian . '%</div>
            </td>
        </tr>
    </table>

    <h4>C. RINCIAN PER KELAS</h4>
    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="20%">Nama Kelas</th>
                <th width="10%">Siswa</th>
                <th width="10%">Guru</th>
                <th width="15%">Pertemuan</th>
                <th width="25%">Hadir/Izin/Sakit/Alpa</th>
                <th width="15%">Materi</th>
            </tr>
        </thead>
        <tbody>
            ' . $detail_kelas_html . '
        </tbody>
    </table>

    <table width="100%" style="margin-top:10px; page-break-inside: avoid;">
        <tr>
            <td width="50%" valign="top" style="padding-right: 10px;">
                <h4>D. EVALUASI MUSYAWARAH</h4>
                <div style="font-size:10pt; line-height: 1.5;">
                    ' . $check_pjp . ' Musyawarah PJP Kelompok<br>
                    ' . $check_unsur . ' Musyawarah 5 Unsur
                </div>
            </td>
            <td width="50%" valign="top" style="padding-left: 10px;">
                <h4>E. CATATAN PERMASALAHAN</h4>
                ' . $masalah_html . '
            </td>
        </tr>
    </table>

    <br><br>
    <table width="100%" style="margin-top: 30px; page-break-inside: avoid;">
        <tr>
            <td width="70%"></td>
            <td width="30%" align="center">
                Mengetahui,<br>
                Ketua PJP Kelompok ' . htmlspecialchars(ucwords($nama_kelompok)) . '<br>
                ' . ($ttd_ketua_kelompok_img ? '<img src="' . $ttd_ketua_kelompok_img . '" style="height: 70px; margin-top: 5px; margin-bottom: 5px;">' : '<br><br><br><br>') . '
                <b><u>' . htmlspecialchars($nama_ketua_kelompok) . '</u></b>
            </td>
        </tr>
    </table>
    ';

    $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $final_filename = $filename_base . ".pdf";

    // Set Header untuk Download (Javascript Blob)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $final_filename . '"');

    // Output langsung stream PDF nya ke browser
    echo $mpdf->Output('', 'S');
} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo "Terjadi kesalahan saat membuat PDF: " . $e->getMessage();
}
