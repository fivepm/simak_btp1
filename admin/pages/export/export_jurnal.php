<?php
// Pastikan path vendor/autoload.php sesuai dengan struktur project Anda
require_once '../../../vendor/autoload.php';
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

use Mpdf\Mpdf;

session_start();

// Header agar JavaScript bisa membaca Content-Disposition (Nama File)
header("Access-Control-Expose-Headers: Content-Disposition");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

// 1. AMBIL DATA FILTER DARI POST
$periode_id = isset($_POST['periode_id']) ? (int)$_POST['periode_id'] : 0;
$kelompok_filter = isset($_POST['kelompok']) ? $_POST['kelompok'] : 'semua';
$kelas_filter = isset($_POST['kelas']) ? $_POST['kelas'] : 'semua';
$format = isset($_POST['format']) ? $_POST['format'] : 'pdf';

if ($periode_id == 0) {
    http_response_code(400);
    die("Error: Periode belum dipilih.");
}

// 2. AMBIL NAMA PERIODE
$nama_periode = "Tidak Diketahui";
$q_periode = $conn->query("SELECT nama_periode FROM periode WHERE id = $periode_id");
if ($row_p = $q_periode->fetch_assoc()) {
    $nama_periode = $row_p['nama_periode'];
}

// === PENCATATAN LOG AKTIVITAS (CCTV) ===
$log_kelompok = ($kelompok_filter !== 'semua') ? ucwords($kelompok_filter) : 'Semua Kelompok';
$log_kelas = ($kelas_filter !== 'semua') ? ucwords($kelas_filter) : 'Semua Kelas';
$log_format = strtoupper($format);

// Format deskripsi log: "Ekspor Jurnal KBM - Periode: [Nama Periode]. Kelas: [Kelas], Kelompok: [Kelompok]. Format: [PDF/CSV]"
$deskripsi_log = "Ekspor *Jurnal KBM* - Periode: $nama_periode. Kelas: $log_kelas, Kelompok: $log_kelompok. Format: $log_format";

// Panggil fungsi writeLog (pastikan fungsi ini ada di helpers/log_helper.php)
writeLog('EXPORT', $deskripsi_log);
// =======================================

// 3. QUERY DATA JURNAL
$sql = "SELECT j.* FROM jadwal_presensi j 
        WHERE j.periode_id = ? AND j.pengajar IS NOT NULL AND j.pengajar != ''";

$params = [$periode_id];
$types = "i";

if ($kelompok_filter !== 'semua') {
    $sql .= " AND j.kelompok = ?";
    $params[] = $kelompok_filter;
    $types .= "s";
}

if ($kelas_filter !== 'semua') {
    $sql .= " AND j.kelas = ?";
    $params[] = $kelas_filter;
    $types .= "s";
}

$sql .= " ORDER BY j.tanggal ASC, j.kelompok ASC, j.kelas ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Nama File Dasar
$clean_periode = preg_replace('/[^A-Za-z0-9\-]/', '_', $nama_periode);
$filename_base = "Jurnal_KBM_" . $clean_periode . "_" . date('Ymd_Hi');

// 4. JIKA FORMAT CSV (EXCEL)
if ($format === 'csv') {
    $filename = $filename_base . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Tanggal', 'Hari', 'Kelompok', 'Kelas', 'Pengajar', 'Materi 1', 'Materi 2', 'Materi 3']);

    $no = 1;
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    foreach ($data as $row) {
        $ts = strtotime($row['tanggal']);
        $hari = $days[date('w', $ts)];
        fputcsv($output, [
            $no++,
            $row['tanggal'],
            $hari,
            ucfirst($row['kelompok']),
            ucfirst($row['kelas']),
            $row['pengajar'],
            $row['materi1'],
            $row['materi2'],
            $row['materi3']
        ]);
    }
    fclose($output);
    exit;
}

// 5. FORMAT PDF MENGGUNAKAN MPDF
try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_header' => 5,
        'margin_footer' => 5
    ]);

    $mpdf->SetHeader('Laporan Jurnal KBM||Periode: ' . $nama_periode);
    $mpdf->SetFooter('Dicetak pada: {DATE d-m-Y H:i}||Halaman {PAGENO}/{nbpg}');

    $stylesheet = '
        body { font-family: sans-serif; font-size: 11pt; }
        .table-header { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table-header td { border: none; padding: 5px; vertical-align: top; }
        .table-data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-data th { background-color: #f0f0f0; font-weight: bold; border: 1px solid #000; padding: 8px; text-align: center; font-size: 10pt; }
        .table-data td { border: 1px solid #000; padding: 6px; vertical-align: top; font-size: 10pt; }
        .text-center { text-align: center; }
        ul { margin: 0; padding-left: 15px; }
        li { margin-bottom: 2px; }
    ';

    $html = '
    <h2 style="text-align: center; margin-bottom: 5px;">LAPORAN JURNAL KEGIATAN BELAJAR MENGAJAR</h2>
    <div style="text-align: center; margin-bottom: 20px; font-size: 14px;">Periode: ' . htmlspecialchars($nama_periode) . '</div>

    <table class="table-header">
        <tr>
            <td width="15%"><strong>Filter Kelompok</strong></td>
            <td>: ' . ($kelompok_filter == 'semua' ? 'Semua Kelompok' : ucfirst($kelompok_filter)) . '</td>
            <td width="15%" align="right"><strong>Total Data</strong></td>
            <td width="15%" align="right">: ' . count($data) . ' Jurnal</td>
        </tr>
        <tr>
            <td><strong>Filter Kelas</strong></td>
            <td>: ' . ($kelas_filter == 'semua' ? 'Semua Kelas' : ucfirst($kelas_filter)) . '</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="12%">Hari, Tanggal</th>
                <th width="10%">Kelompok</th>
                <th width="10%">Kelas</th>
                <th width="18%">Pengajar</th>
                <th width="45%">Materi</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($data)) {
        $html .= '<tr><td colspan="6" class="text-center">Tidak ada data jurnal untuk filter ini.</td></tr>';
    } else {
        $no = 1;
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        foreach ($data as $row) {
            $ts = strtotime($row['tanggal']);
            $hari = $days[date('w', $ts)];
            $tgl_indo = date('d-m-Y', $ts);

            $materi_html = '<ul>';
            if ($row['materi1']) $materi_html .= '<li>' . htmlspecialchars($row['materi1']) . '</li>';
            if ($row['materi2']) $materi_html .= '<li>' . htmlspecialchars($row['materi2']) . '</li>';
            if ($row['materi3']) $materi_html .= '<li>' . htmlspecialchars($row['materi3']) . '</li>';
            $materi_html .= '</ul>';
            if (empty($row['materi1']) && empty($row['materi2']) && empty($row['materi3'])) {
                $materi_html = '-';
            }

            $html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . $hari . ', <br>' . $tgl_indo . '</td>
                <td class="text-center">' . ucfirst($row['kelompok']) . '</td>
                <td class="text-center">' . ucfirst($row['kelas']) . '</td>
                <td>' . htmlspecialchars($row['pengajar']) . '</td>
                <td>' . $materi_html . '</td>
            </tr>';
        }
    }

    $html .= '</tbody></table>';

    $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    // PENTING: Gunakan Mode 'D' (Download) agar Content-Disposition terset dengan benar untuk fetch
    $final_filename = $filename_base . ".pdf";
    $mpdf->Output($final_filename, 'D');
} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo "Terjadi kesalahan saat membuat PDF: " . $e->getMessage();
}
