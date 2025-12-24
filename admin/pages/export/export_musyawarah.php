<?php
session_start();

// 🔐 SECURITY CHECK
$allowed_roles = ['superadmin', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    http_response_code(403);
    die("Akses ditolak.");
}

// Include Config & Library
include '../../../config/config.php';
require_once '../../../vendor/autoload.php';
require_once '../../../helpers/log_helper.php';

use Mpdf\Mpdf;

// Header agar nama file terbaca oleh fetch JS
header("Access-Control-Expose-Headers: Content-Disposition");

$id_musyawarah = $_GET['id'] ?? null;

if (!$id_musyawarah || !filter_var($id_musyawarah, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    die("ID Musyawarah tidak valid.");
}

// 1. Ambil Detail Musyawarah
$stmt_musyawarah = $conn->prepare("SELECT * FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

if (!$musyawarah) {
    http_response_code(404);
    die("Data musyawarah tidak ditemukan.");
}

// === PENCATATAN LOG AKTIVITAS ===
$nama_musyawarah_log = $musyawarah['nama_musyawarah'];
$tgl_log = formatTanggalIndonesia($musyawarah['tanggal']);
$deskripsi_log = "Cetak Notulensi *$nama_musyawarah_log* ($tgl_log). Format: PDF";

writeLog('EXPORT', $deskripsi_log);
// =================================

// 2. Cari Musyawarah SEBELUMNYA
$stmt_prev = $conn->prepare("SELECT id, nama_musyawarah, tanggal FROM musyawarah WHERE tanggal < ? ORDER BY tanggal DESC LIMIT 1");
$stmt_prev->bind_param("s", $musyawarah['tanggal']);
$stmt_prev->execute();
$musyawarah_sebelumnya = $stmt_prev->get_result()->fetch_assoc();
$stmt_prev->close();

// 3. Ambil Poin Sebelumnya
$poin_sebelumnya = null;
if ($musyawarah_sebelumnya) {
    $stmt_poin_prev = $conn->prepare("SELECT * FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
    $stmt_poin_prev->bind_param("i", $musyawarah_sebelumnya['id']);
    $stmt_poin_prev->execute();
    $poin_sebelumnya = $stmt_poin_prev->get_result();
    $stmt_poin_prev->close();
}

// 4. Ambil Daftar Hadir
$stmt_hadir = $conn->prepare("SELECT nama_peserta, jabatan, status FROM kehadiran_musyawarah WHERE id_musyawarah = ? ORDER BY urutan ASC");
$stmt_hadir->bind_param("i", $id_musyawarah);
$stmt_hadir->execute();
$result_hadir = $stmt_hadir->get_result();
$stmt_hadir->close();

// 5. Ambil Poin Notulensi
$stmt_poin = $conn->prepare("SELECT poin_pembahasan, status_evaluasi, keterangan FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
$stmt_poin->bind_param("i", $id_musyawarah);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$stmt_poin->close();

// 6. Ambil Laporan Kelompok
$laporan_kelompok_tersimpan = [];
$stmt_laporan_kelompok = $conn->prepare("SELECT nama_kelompok, isi_laporan FROM musyawarah_laporan_kelompok WHERE id_musyawarah = ?");
$stmt_laporan_kelompok->bind_param("i", $id_musyawarah);
$stmt_laporan_kelompok->execute();
$result_laporan_kelompok = $stmt_laporan_kelompok->get_result();
while ($row = $result_laporan_kelompok->fetch_assoc()) {
    $laporan_kelompok_tersimpan[$row['nama_kelompok']] = $row['isi_laporan'];
}
$stmt_laporan_kelompok->close();
$daftar_kelompok = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];

// 7. Ambil Laporan KMM
$laporan_kmm_tersimpan = [];
$stmt_laporan_kmm = $conn->prepare("SELECT nama_kmm, isi_laporan FROM musyawarah_laporan_kmm WHERE id_musyawarah = ?");
$stmt_laporan_kmm->bind_param("i", $id_musyawarah);
$stmt_laporan_kmm->execute();
$result_laporan_kmm = $stmt_laporan_kmm->get_result();
while ($row_kmm = $result_laporan_kmm->fetch_assoc()) {
    $laporan_kmm_tersimpan[$row_kmm['nama_kmm']] = $row_kmm['isi_laporan'];
}
$stmt_laporan_kmm->close();
$daftar_unit_kmm = ['KMM Banguntapan 1', 'KMM Bintaran', 'KMM Gedongkuning', 'KMM Jombor', 'KMM Sunten'];

// --- MULAI GENERATE PDF ---

try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 20,
        'margin_right' => 20,
        'margin_top' => 20,
        'margin_bottom' => 20
    ]);

    // Metadata
    $mpdf->SetTitle('Notulensi - ' . $musyawarah['nama_musyawarah']);
    $mpdf->SetAuthor($_SESSION['user_nama'] ?? 'Admin');

    // Watermark Logo (Jika file ada)
    $logo_path = __DIR__ . '/../../../assets/images/logo_kbm.png';
    if (file_exists($logo_path)) {
        $mpdf->SetWatermarkImage($logo_path, 0.1, 'F', 'F');
        $mpdf->showWatermarkImage = true;
    }

    // Stylesheet (CSS Manual karena mPDF tidak support Tailwind penuh)
    $stylesheet = '
        body { font-family: "Times New Roman", serif; font-size: 11pt; color: #000; }
        h1 { font-family: sans-serif; font-size: 16pt; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 5px; }
        h2 { font-family: sans-serif; font-size: 12pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11pt; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; vertical-align: top; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .header-table td { border: none; padding: 4px; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .divider { border-bottom: 3px double #000; width: 50%; margin: 0 auto 20px auto; }
        .status-hadir { color: green; }
        .status-izin { color: blue; }
        .status-alpa { color: red; }
    ';

    // HTML Content
    $html = '
    <h1>Notulensi Musyawarah</h1>
    <div class="divider"></div>

    <h2>DETAIL MUSYAWARAH</h2>
    <table class="header-table">
        <tr>
            <td width="25%"><strong>Nama Musyawarah</strong></td>
            <td width="2%">:</td>
            <td>' . htmlspecialchars($musyawarah['nama_musyawarah']) . '</td>
        </tr>
        <tr>
            <td><strong>Tanggal & Waktu</strong></td>
            <td>:</td>
            <td>' . date('d F Y', strtotime($musyawarah['tanggal'])) . ', Pukul ' . date('H:i', strtotime($musyawarah['waktu_mulai'])) . ' WIB</td>
        </tr>
        <tr>
            <td><strong>Tempat</strong></td>
            <td>:</td>
            <td>' . htmlspecialchars($musyawarah['tempat']) . '</td>
        </tr>
    </table>

    <h2>DAFTAR HADIR PESERTA</h2>
    <table>
        <thead>
            <tr>
                <th width="10%">No.</th>
                <th>Nama Peserta</th>
                <th width="30%">Dapukan</th>
                <th width="20%">Status</th>
            </tr>
        </thead>
        <tbody>';

    if ($result_hadir->num_rows > 0) {
        $no = 1;
        while ($peserta = $result_hadir->fetch_assoc()) {
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($peserta['nama_peserta']) . '</td>
                <td class="text-center">' . htmlspecialchars($peserta['jabatan']) . '</td>
                <td class="text-center">' . htmlspecialchars($peserta['status']) . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="4" class="text-center"><i>Tidak ada data kehadiran.</i></td></tr>';
    }
    $html .= '</tbody></table>';

    // Evaluasi Sebelumnya
    if ($poin_sebelumnya && $poin_sebelumnya->num_rows > 0) {
        $html .= '<h2>EVALUASI MUSYAWARAH SEBELUMNYA</h2>
        <div style="font-size: 10pt; font-style: italic; margin-bottom: 5px;">Ref: ' . htmlspecialchars($musyawarah_sebelumnya['nama_musyawarah']) . '</div>
        <table>
            <thead>
                <tr>
                    <th width="10%">No.</th>
                    <th>Poin Pembahasan</th>
                    <th width="20%">Status</th>
                    <th width="30%">Keterangan</th>
                </tr>
            </thead>
            <tbody>';
        $no_prev = 1;
        while ($poin = $poin_sebelumnya->fetch_assoc()) {
            $status_icon = ($poin['status_evaluasi'] == 'Terlaksana') ? '[v] ' : (($poin['status_evaluasi'] == 'Belum Terlaksana') ? '[x] ' : '[-] ');
            $html .= '<tr>
                <td class="text-center">' . $no_prev++ . '</td>
                <td>' . nl2br(htmlspecialchars($poin['poin_pembahasan'])) . '</td>
                <td>' . $status_icon . $poin['status_evaluasi'] . '</td>
                <td>' . nl2br(htmlspecialchars($poin['keterangan'] ?? '')) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
    }

    // Laporan Kelompok
    $html .= '<h2>LAPORAN PJP KELOMPOK</h2>
    <table>
        <thead>
            <tr>
                <th width="25%">Kelompok</th>
                <th>Isi Laporan</th>
            </tr>
        </thead>
        <tbody>';
    $laporan_ditemukan = false;
    foreach ($daftar_kelompok as $kelompok) {
        if (!empty($laporan_kelompok_tersimpan[$kelompok])) {
            $laporan_ditemukan = true;
            $html .= '<tr>
                <td class="text-bold">' . htmlspecialchars($kelompok) . '</td>
                <td>' . nl2br(htmlspecialchars($laporan_kelompok_tersimpan[$kelompok])) . '</td>
            </tr>';
        }
    }
    if (!$laporan_ditemukan) {
        $html .= '<tr><td colspan="2" class="text-center"><i>Tidak ada laporan kelompok.</i></td></tr>';
    }
    $html .= '</tbody></table>';

    // Laporan KMM
    $html .= '<h2>LAPORAN KMM</h2>
    <table>
        <thead>
            <tr>
                <th width="25%">KMM</th>
                <th>Isi Laporan</th>
            </tr>
        </thead>
        <tbody>';
    $laporan_kmm_ditemukan = false;
    foreach ($daftar_unit_kmm as $unit_kmm) {
        if (!empty($laporan_kmm_tersimpan[$unit_kmm])) {
            $laporan_kmm_ditemukan = true;
            $html .= '<tr>
                <td class="text-bold">' . htmlspecialchars($unit_kmm) . '</td>
                <td>' . nl2br(htmlspecialchars($laporan_kmm_tersimpan[$unit_kmm])) . '</td>
            </tr>';
        }
    }
    if (!$laporan_kmm_ditemukan) {
        $html .= '<tr><td colspan="2" class="text-center"><i>Tidak ada laporan KMM.</i></td></tr>';
    }
    $html .= '</tbody></table>';

    // Hasil Musyawarah
    $html .= '<h2>HASIL MUSYAWARAH</h2>
    <table>
        <thead>
            <tr>
                <th width="10%">No.</th>
                <th>Poin Pembahasan</th>
            </tr>
        </thead>
        <tbody>';
    if ($result_poin->num_rows > 0) {
        $no = 1;
        while ($poin = $result_poin->fetch_assoc()) {
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . nl2br(htmlspecialchars($poin['poin_pembahasan'])) . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="2" class="text-center"><i>Tidak ada poin notulensi yang dicatat.</i></td></tr>';
    }
    $html .= '</tbody></table>';

    // Footer
    $mpdf->SetFooter('Dicetak pada: {DATE d-m-Y H:i} oleh ' . ($_SESSION['user_nama'] ?? 'Admin') . '||Halaman {PAGENO}/{nbpg}');

    $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $filename = 'Notulensi_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $musyawarah['nama_musyawarah']) . '.pdf';

    // Output 'D' agar didownload oleh fetch JS
    $mpdf->Output($filename, 'D');
} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo "Gagal membuat PDF: " . $e->getMessage();
}

$conn->close();
