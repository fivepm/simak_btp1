<?php
// Sesuaikan path ke vendor/autoload.php dan config
require_once '../../../vendor/autoload.php';
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

use Mpdf\Mpdf;

session_start();

if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak.");
}

// 1. AMBIL DATA POST
$periode_id = $_POST['periode_id'] ?? 0;
$kelompok = $_POST['kelompok'] ?? '';
$kelas = $_POST['kelas'] ?? '';

if (empty($periode_id) || empty($kelompok) || empty($kelas)) {
    die("Data filter tidak lengkap.");
}

// 2. AMBIL INFO PERIODE
$q_periode = $conn->query("SELECT nama_periode FROM periode WHERE id = $periode_id");
$nama_periode = ($q_periode->num_rows > 0) ? $q_periode->fetch_assoc()['nama_periode'] : '-';

// === LOG ===
writeLog('EXPORT', "Admin mengekspor Jadwal & Probul ($kelompok & $kelas) - Periode: $nama_periode");

// 3. QUERY TARGET BULANAN
$target_html = '';
$sql_target = "SELECT * FROM target_pembelajaran 
               WHERE periode_id = ?";

// Logic Filter untuk Target
$params_t = [$periode_id];
$types_t = "i";

if ($kelompok !== 'semua') {
    $sql_target .= " AND (kelompok = ? OR kelompok = 'Semua')";
    $params_t[] = $kelompok;
    $types_t .= "s";
}
if ($kelas !== 'semua') {
    $sql_target .= " AND (kelas = ? OR kelas = 'Semua')";
    $params_t[] = $kelas;
    $types_t .= "s";
}

$sql_target .= " ORDER BY kategori ASC, judul_materi ASC";

$stmt_t = $conn->prepare($sql_target);
if (!empty($params_t)) {
    $stmt_t->bind_param($types_t, ...$params_t);
}
$stmt_t->execute();
$res_t = $stmt_t->get_result();

if ($res_t->num_rows > 0) {
    $no = 1;
    while ($t = $res_t->fetch_assoc()) {
        $target_val = "";
        if ($t['tipe_input'] == 'RANGE') {
            $target_val = $t['satuan'] . " " . (float)$t['target_start'] . " - " . (float)$t['target_end'];
        } elseif ($t['tipe_input'] == 'CHECKLIST') {
            $target_val = "Poin Checklist";
        } else {
            $target_val = (float)$t['total_volume'] . " " . $t['satuan'];
        }

        $target_html .= '
        <tr>
            <td align="center">' . $no++ . '</td>
            <td>' . htmlspecialchars($t['kategori']) . '</td>
            <td>' . htmlspecialchars($t['judul_materi']) . '</td>
            <td align="center">' . $target_val . '</td>
        </tr>';
    }
} else {
    $target_html = '<tr><td colspan="4" align="center">Belum ada probul yang diatur.</td></tr>';
}

// 4. QUERY JADWAL (GURU & PENASEHAT)
$jadwal_html = '';
$sql_jadwal = "SELECT 
                jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.kelompok, jp.kelas,
                GROUP_CONCAT(DISTINCT g.nama SEPARATOR ', ') as daftar_guru,
                GROUP_CONCAT(DISTINCT p.nama SEPARATOR ', ') as daftar_penasehat
            FROM jadwal_presensi jp
            LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id
            LEFT JOIN guru g ON jg.guru_id = g.id
            LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id
            LEFT JOIN penasehat p ON jn.penasehat_id = p.id
            WHERE jp.periode_id = ?";

$params_j = [$periode_id];
$types_j = "i";

if ($kelompok !== 'semua') {
    $sql_jadwal .= " AND jp.kelompok = ?";
    $params_j[] = $kelompok;
    $types_j .= "s";
}
if ($kelas !== 'semua') {
    $sql_jadwal .= " AND jp.kelas = ?";
    $params_j[] = $kelas;
    $types_j .= "s";
}

$sql_jadwal .= " GROUP BY jp.id ORDER BY jp.tanggal ASC, jp.jam_mulai ASC";

$stmt_j = $conn->prepare($sql_jadwal);
if (!empty($params_j)) {
    $stmt_j->bind_param($types_j, ...$params_j);
}
$stmt_j->execute();
$res_j = $stmt_j->get_result();

if ($res_j->num_rows > 0) {
    $no = 1;
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    while ($row = $res_j->fetch_assoc()) {
        $ts = strtotime($row['tanggal']);
        $hari = $days[date('w', $ts)];
        $tgl = date('d-m-Y', $ts);
        $jam = date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai']));

        // Logika Penasehat "-"
        $penasehat = !empty($row['daftar_penasehat']) ? $row['daftar_penasehat'] : '-';
        $guru = !empty($row['daftar_guru']) ? $row['daftar_guru'] : '-';

        $jadwal_html .= '
        <tr>
            <td align="center">' . $no++ . '</td>
            <td>' . $hari . ', ' . $tgl . '</td>
            <td align="center">' . $jam . '</td>
            <td>' . htmlspecialchars($guru) . '</td>
            <td>' . htmlspecialchars($penasehat) . '</td>
        </tr>';
    }
} else {
    $jadwal_html = '<tr><td colspan="6" align="center">Tidak ada jadwal ditemukan.</td></tr>';
}

// 5. GENERATE PDF
try {
    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-P']); // Portrait
    // $mpdf->SetHeader('Jadwal & Target KBM||Periode: ' . $nama_periode);
    $mpdf->SetFooter('Dicetak: {DATE d-m-Y H:i}||Hal {PAGENO}');

    // $css = '
    //     body { font-family: sans-serif; font-size: 10pt; }
    //     .meta-table { width: 100%; margin-bottom: 20px; }
    //     .meta-table td { padding: 3px; font-weight: bold; }
    //     .section-title { font-size: 12pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; text-decoration: underline; }
    //     table.data { width: 100%; border-collapse: collapse; }
    //     table.data th { border: 1px solid #000; background-color: #f0f0f0; padding: 8px; }
    //     table.data td { border: 1px solid #000; padding: 6px; vertical-align: top; }
    // ';

    $css = '
    body { font-family: Arial, sans-serif;  font-size: 10pt; }
    .header-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
    .header-text { text-align: center; }
    .title-main { font-size: 14pt; font-weight: bold; margin-bottom: 2px; }
    .title-sub { font-size: 11pt; font-weight: bold; margin-bottom: 2px; }
    .title-desc { font-size: 9pt; font-style: italic; }
    
    .meta-table { width: 100%; margin-bottom: 20px; }
    .meta-table td { padding: 3px; font-weight: bold; }
    .section-title { font-size: 12pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; text-decoration: underline; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { border: 1px solid #000; background-color: #f0f0f0; padding: 8px; }
    table.data td { border: 1px solid #000; padding: 6px; vertical-align: top; }
';

    $display_kelompok = ($kelompok === 'semua') ? 'Semua Kelompok' : ucfirst($kelompok);
    $display_kelas = ($kelas === 'semua') ? 'Semua Kelas' : ucfirst($kelas);

    $logo_kiri_path = __DIR__ . '/../../../assets/images/logo_kbm.png'; // Contoh path
    $logo_kanan_path = __DIR__ . '/../../../assets/images/logo_simak.png'; // Contoh path

    function imageToBase64($path)
    {
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        return false; // Kembalikan false jika file tidak ditemukan
    }

    $img_kiri = imageToBase64($logo_kiri_path);
    $img_kanan = imageToBase64($logo_kanan_path);

    $watermark_path = __DIR__ . '/../../../assets/images/logo_kbm.png';

    if (file_exists($watermark_path)) {
        $mpdf->SetWatermarkImage(
            $watermark_path,
            0.1, // Opacity
            'auto',
            'P'
        );
        $mpdf->showWatermarkImage = true;
    }

    $html = '
    <!-- Header dengan 3 Kolom -->
    <table class="header-table">
        <tr>
            <td width="15%" align="left">' .
        ($img_kiri ? '<img src="' . $img_kiri . '" width="60px">' : '') . '
            </td>
            <td width="70%" class="header-text">
                <div class="title-sub">PJP BANGUNTAPAN 1</div>
                <div class="title-main">JADWAL & TARGET PEMBELAJARAN</div>
                <div class="title-desc">Sistem Informasi Monitoring Akademik</div>
            </td>
            <td width="15%" align="right">' .
        ($img_kanan ? '<img src="' . $img_kanan . '" width="60px">' : '') . '
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr><td width="15%">Kelompok</td><td>: ' . $display_kelompok . '</td></tr>
        <tr><td>Kelas</td><td>: ' . $display_kelas . '</td></tr>
        <tr><td>Periode</td><td>: ' . $nama_periode . '</td></tr>
    </table>

    <div class="section-title">A. Target Pembelajaran (Probul)</div>
    <table class="data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="20%">Kategori</th>
                <th width="55%">Materi</th>
                <th width="20%">Target</th>
            </tr>
        </thead>
        <tbody>' . $target_html . '</tbody>
    </table>

    <div class="section-title">B. Jadwal Kegiatan Belajar Mengajar</div>
    <table class="data">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th width="25%">Hari, Tanggal</th>
                <th width="15%">Waktu</th>
                <th width="25%">Guru</th>
                <th width="25%">Penasehat</th>
            </tr>
        </thead>
        <tbody>' . $jadwal_html . '</tbody>
    </table>
    ';

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $filename = "[JADWAL]-" . ucwords($nama_periode) . " (" . ucwords($kelompok) . "-" . ucwords($kelas) . ").pdf";
    $mpdf->Output($filename, 'D');
} catch (\Mpdf\MpdfException $e) {
    echo "Gagal membuat PDF: " . $e->getMessage();
}
