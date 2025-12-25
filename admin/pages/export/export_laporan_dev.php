<?php
session_start();
require_once '../../../config/config.php';
require_once '../../../vendor/autoload.php';
require_once __DIR__ . '/../../../helpers/log_helper.php';

use Mpdf\Mpdf;

// Header PENTING agar fetch API bisa membaca nama file
header("Access-Control-Expose-Headers: Content-Disposition");

$id = $_GET['id'] ?? 0;

// 1. Ambil Data Laporan Utama
$q = $conn->query("SELECT * FROM laporan_developer WHERE id = $id");
$data = $q->fetch_assoc();

if (!$data) {
    http_response_code(404);
    die("Data laporan tidak ditemukan");
}

// --- LOG AKTIVITAS (EXPORT) ---
// Mencatat bahwa user telah mendownload PDF ini
if (function_exists('writeLog')) {
    $tgl_log = date('d-m-Y', strtotime($data['tanggal_laporan']));
    $deskripsi_log = "Download PDF Laporan Developer (Tgl Laporan: $tgl_log)";

    writeLog('EXPORT', $deskripsi_log);
}
// ------------------------------

// 2. Ambil Data Kontributor
$kontributor_names = [];
$q_contrib = $conn->query("SELECT u.nama FROM laporan_contributors lc JOIN users u ON lc.user_id = u.id WHERE lc.laporan_id = $id ORDER BY u.nama ASC");

if ($q_contrib) {
    while ($row = $q_contrib->fetch_assoc()) {
        $kontributor_names[] = $row['nama'];
    }
}

if (empty($kontributor_names)) {
    $q_creator = $conn->query("SELECT nama FROM users WHERE id = " . (int)$data['dibuat_oleh']);
    if ($r_creator = $q_creator->fetch_assoc()) {
        $kontributor_names[] = $r_creator['nama'];
    }
}
$tim_display = implode(', ', $kontributor_names);

// Fungsi pembantu render content
function renderContent($content)
{
    if (empty(trim($content))) {
        return '<p class="text-muted">- Tidak ada data -</p>';
    }
    return $content;
}

try {
    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
    $mpdf->SetTitle("Laporan Progres Dev - " . date('d M Y', strtotime($data['tanggal_laporan'])));

    $css = '
        body { font-family: sans-serif; color: #333; }
        .header { border-bottom: 2px solid #4F46E5; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 18pt; font-weight: bold; color: #1F2937; }
        .meta { font-size: 10pt; color: #6B7280; margin-top: 5px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 12pt; font-weight: bold; background-color: #F3F4F6; padding: 8px; border-left: 5px solid #4F46E5; margin-bottom: 10px; }
        
        ul, ol { margin-top: 5px; margin-bottom: 5px; padding-left: 20px; }
        li { margin-bottom: 3px; line-height: 1.4; }
        p { margin-bottom: 5px; margin-top: 0; }
        strong { font-weight: bold; }
        em { font-style: italic; }
        .text-muted { color: #9CA3AF; font-style: italic; }
    ';

    $html = '
    <div class="header">
        <div class="title">Laporan Progres Pengembangan</div>
        <div class="meta">
            Tanggal Laporan: ' . date('d F Y', strtotime($data['tanggal_laporan'])) . '<br>
            Periode Pengerjaan: ' . date('d/m/Y', strtotime($data['periode_awal'])) . ' s.d. ' . date('d/m/Y', strtotime($data['periode_akhir'])) . '
        </div>
    </div>

    <div class="section">
        <div class="section-title">Ringkasan Eksekutif</div>
        <p>' . htmlspecialchars($data['summary']) . '</p>
    </div>

    <div class="section">
        <div class="section-title" style="border-color: #10B981;">✅ Pekerjaan Diselesaikan (Completed)</div>
        ' . renderContent($data['fitur_selesai']) . '
    </div>

    <div class="section">
        <div class="section-title" style="border-color: #3B82F6;">🚧 Sedang Berjalan (In Progress)</div>
        ' . renderContent($data['pekerjaan_berjalan']) . '
    </div>

    <div class="section">
        <div class="section-title" style="border-color: #EF4444;">⚠️ Kendala & Isu (Issues)</div>
        ' . renderContent($data['kendala_teknis']) . '
    </div>

    <div class="section">
        <div class="section-title" style="border-color: #6B7280;">🔧 Catatan Teknis</div>
        ' . renderContent($data['catatan_teknis']) . '
    </div>
    
    <div class="section">
        <div class="section-title" style="border-color: #8B5CF6;">💡 Usulan Fitur Kedepan (Future Requests)</div>
        ' . renderContent($data['usulan_fitur']) . '
    </div>

    <br><br>
    <table width="100%">
        <tr>
            <td width="60%"></td>
            <td width="40%" align="center">
                <p>Dibuat Oleh,</p>
                <br><br><br>
                <strong>Tim Developer</strong><br>
                <small>(' . htmlspecialchars($tim_display) . ')</small>
            </td>
        </tr>
    </table>
    ';

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $filename = "Laporan_Dev_" . date('Ymd', strtotime($data['tanggal_laporan'])) . ".pdf";

    // UBAH JADI 'D' UNTUK FORCE DOWNLOAD
    $mpdf->Output($filename, 'D');
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
