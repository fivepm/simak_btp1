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

// 2. AMBIL DATA PENDUKUNG
$nama_periode = "Tidak Diketahui";
$q_periode = $conn->query("SELECT nama_periode FROM periode WHERE id = $periode_id");
if ($row_p = $q_periode->fetch_assoc()) {
    $nama_periode = $row_p['nama_periode'];
}

$id_admin = $_SESSION['user_id'];
$nama_admin = "Admin";
$q_admin = $conn->query("SELECT nama FROM users WHERE id = $id_admin");
if ($q_admin && $row_a = $q_admin->fetch_assoc()) {
    $nama_admin = $row_a['nama'];
}

// === PENCATATAN LOG ===
$log_kelompok = ($kelompok_filter !== 'semua') ? ucwords($kelompok_filter) : 'Semua Kelompok';
$log_kelas = ($kelas_filter !== 'semua') ? ucwords($kelas_filter) : 'Semua Kelas';
$log_format = strtoupper($format);
writeLog('EXPORT', "Ekspor *Jurnal KBM* - Periode: $nama_periode. Kelas: $log_kelas, Kelompok: $log_kelompok. Format: $log_format");


// =========================================================
// 3. HITUNG PROGRESS KETERCAPAIAN (LOGIKA DASHBOARD)
// =========================================================
$progress_data = [];
$total_progress_percent = 0;

// A. Query Target Pembelajaran (Sesuai Filter)
$sql_target = "SELECT id, kategori, total_volume, tipe_input 
               FROM target_pembelajaran 
               WHERE periode_id = ?";
$params_t = [$periode_id];
$types_t = "i";

if ($kelas_filter !== 'semua') {
    $sql_target .= " AND (kelas = ? OR kelas = 'Semua')";
    $params_t[] = $kelas_filter;
    $types_t .= "s";
}
if ($kelompok_filter !== 'semua') {
    $sql_target .= " AND (kelompok = ? OR kelompok = 'Semua')";
    $params_t[] = $kelompok_filter;
    $types_t .= "s";
}

$stmt_t = $conn->prepare($sql_target);
if (!empty($params_t)) $stmt_t->bind_param($types_t, ...$params_t);
$stmt_t->execute();
$res_t = $stmt_t->get_result();

$categories = [];

while ($t = $res_t->fetch_assoc()) {
    $cat = $t['kategori'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = ['total_target' => 0, 'total_capaian' => 0];
    }

    // Hitung Target
    $vol_target = (float)$t['total_volume'];
    if ($t['tipe_input'] == 'CHECKLIST') $vol_target = 1;

    $categories[$cat]['total_target'] += $vol_target;

    // Hitung Capaian
    $sql_cap = "SELECT SUM(jm.volume_capaian) as total 
                FROM jurnal_materi jm 
                JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id
                WHERE jm.target_id = ?";

    // Filter Scope Realisasi sesuai filter export
    if ($kelompok_filter !== 'semua') $sql_cap .= " AND jp.kelompok = '$kelompok_filter'";
    if ($kelas_filter !== 'semua') $sql_cap .= " AND jp.kelas = '$kelas_filter'";

    $stmt_c = $conn->prepare($sql_cap);
    $stmt_c->bind_param("i", $t['id']);
    $stmt_c->execute();
    $row_c = $stmt_c->get_result()->fetch_assoc();
    $vol_cap = (float)$row_c['total'];

    // Capping
    if ($vol_cap > $vol_target) $vol_cap = $vol_target;

    $categories[$cat]['total_capaian'] += $vol_cap;
}

// Hitung Persentase Akhir
$count_cat = 0;
$sum_percent = 0;
foreach ($categories as $cat_name => $data) {
    $percent = 0;
    if ($data['total_target'] > 0) {
        $percent = ($data['total_capaian'] / $data['total_target']) * 100;
    }
    $progress_data[$cat_name] = round($percent, 1);
    $sum_percent += $percent;
    $count_cat++;
}

if ($count_cat > 0) {
    $total_progress_percent = round($sum_percent / $count_cat, 1);
}


// =========================================================
// 4. QUERY DATA JURNAL (DETAIL)
// =========================================================
$sql = "SELECT j.id, j.tanggal, j.kelompok, j.kelas, j.pengajar 
        FROM jadwal_presensi j 
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

$data_final = [];
while ($row = $result->fetch_assoc()) {
    $jadwal_id = $row['id'];
    $materi_text_array = [];

    // A. Materi Kurikulum
    $q_mat = $conn->query("SELECT jm.*, tp.judul_materi, tp.kategori, tp.tipe_input, tp.satuan 
                           FROM jurnal_materi jm 
                           JOIN target_pembelajaran tp ON jm.target_id = tp.id 
                           WHERE jm.jadwal_id = $jadwal_id
                           ORDER BY tp.kategori ASC");

    while ($m = $q_mat->fetch_assoc()) {
        $detail = "";
        $v_start = (float)$m['capaian_start'];
        $v_end = (float)$m['capaian_end'];
        $v_vol = (float)$m['volume_capaian'];

        if ($m['tipe_input'] == 'RANGE') {
            $detail = "{$m['satuan']} $v_start - $v_end";
        } elseif ($m['tipe_input'] == 'CHECKLIST') {
            $detail = "Tercapai";
        } else {
            $detail = "$v_vol {$m['satuan']}";
        }

        $materi_text_array[] = "<b>[" . strtoupper($m['kategori']) . "]</b> " . $m['judul_materi'] . ": " . $detail;
    }

    // B. Materi Tambahan
    $q_add = $conn->query("SELECT * FROM jurnal_tambahan WHERE jadwal_id = $jadwal_id");
    while ($add = $q_add->fetch_assoc()) {
        $ket = $add['keterangan'] ? " ({$add['keterangan']})" : "";
        $materi_text_array[] = "<b>[TAMBAHAN]</b> " . $add['judul_materi'] . " (Oleh: " . $add['pemateri'] . ")$ket";
    }

    if (empty($materi_text_array)) {
        $row['isi_materi'] = "-";
        $row['isi_materi_raw'] = "-";
    } else {
        $row['isi_materi'] = '<ul style="margin:0; padding-left:15px;"><li>' . implode('</li><li>', $materi_text_array) . '</li></ul>';
        $row['isi_materi_raw'] = implode("\n", array_map('strip_tags', $materi_text_array));
    }

    $data_final[] = $row;
}

$clean_periode = preg_replace('/[^A-Za-z0-9\-]/', '_', $nama_periode);
$filename_base = "Jurnal_KBM_" . $clean_periode . "_" . date('Ymd_Hi');

// 5. JIKA FORMAT CSV (EXCEL)
if ($format === 'csv') {
    $filename = $filename_base . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Header Info
    fputcsv($output, ['LAPORAN JURNAL KEGIATAN BELAJAR MENGAJAR']);
    fputcsv($output, ['Diekspor Oleh', $nama_admin]);
    fputcsv($output, ['Periode', $nama_periode]);
    fputcsv($output, ['Kelompok', ($kelompok_filter == 'semua' ? 'Semua Kelompok' : ucfirst($kelompok_filter))]);
    fputcsv($output, ['Kelas', ($kelas_filter == 'semua' ? 'Semua Kelas' : ucfirst($kelas_filter))]);
    fputcsv($output, []);

    // Progress Info
    fputcsv($output, ['RINGKASAN PROGRES KURIKULUM']);
    fputcsv($output, ['Total Ketercapaian', $total_progress_percent . '%']);
    foreach ($progress_data as $cat => $pct) {
        fputcsv($output, ["Capaian $cat", $pct . '%']);
    }
    fputcsv($output, []);

    // Data Table
    fputcsv($output, ['No', 'Tanggal', 'Hari', 'Kelompok', 'Kelas', 'Pengajar', 'Detail Materi']);

    $no = 1;
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    foreach ($data_final as $row) {
        $ts = strtotime($row['tanggal']);
        $hari = $days[date('w', $ts)];
        fputcsv($output, [
            $no++,
            $row['tanggal'],
            $hari,
            ucfirst($row['kelompok']),
            ucfirst($row['kelas']),
            $row['pengajar'],
            $row['isi_materi_raw']
        ]);
    }
    fclose($output);
    exit;
}

// 6. FORMAT PDF MENGGUNAKAN MPDF
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

    // === SETTING WATERMARK (GAMBAR JPG) ===
    $watermarkPath = '../../../assets/images/logo_kbm.png';
    if (file_exists($watermarkPath)) {
        $mpdf->SetWatermarkImage($watermarkPath, 0.1, [100, 100]);
        $mpdf->showWatermarkImage = true;
    }

    $mpdf->SetHeader('Laporan Jurnal KBM||Periode: ' . $nama_periode);
    $mpdf->SetFooter('Dicetak pada: {DATE d-m-Y H:i}||Halaman {PAGENO}/{nbpg}');

    $stylesheet = '
        body { font-family: sans-serif; font-size: 10pt; }
        .table-data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-data th { background-color: #f0f0f0; border: 1px solid #333; padding: 8px; font-weight: bold; font-size: 10pt; }
        .table-data td { border: 1px solid #333; padding: 6px; vertical-align: top; font-size: 10pt; }
        .text-center { text-align: center; }
        .meta-table { width: 100%; border: none; margin-bottom: 5px; font-size: 11pt; }
        .meta-table td { padding: 2px; vertical-align: top; }
        ul { margin-top: 0; margin-bottom: 0; }
        li { margin-bottom: 3px; }
        b { color: #000; }
        
        /* Progress Styles */
        .progress-container { width: 100%; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; background-color: #f9fafb; border-radius: 5px; }
        .progress-title { font-weight: bold; font-size: 14pt; margin-bottom: 10px; color: #374151; }
        .progress-bar-bg { width: 100%; background-color: #e5e7eb; height: 10px; border-radius: 5px; overflow: hidden; margin-top: 5px; }
        .progress-bar-fill { height: 100%; background-color: #3b82f6; }
        .cat-row td { padding-bottom: 8px; font-size: 10pt; }
        .total-box { text-align: center; border-right: 1px solid #ccc; padding-right: 20px; }
        .total-percent { font-size: 32pt; font-weight: bold; color: #4338ca; display: block; margin-top: 10px; }
    ';

    $display_kelompok = ($kelompok_filter === 'semua') ? 'Semua Kelompok' : ucfirst($kelompok_filter);
    $display_kelas = ($kelas_filter === 'semua') ? 'Semua Kelas' : ucwords($kelas_filter);

    // --- HTML UNTUK BAGIAN PROGRESS ---
    $progress_html = '';
    if (!empty($progress_data)) {
        $progress_rows = '';
        foreach ($progress_data as $cat => $pct) {
            // Warna bar sederhana
            $color = '#3b82f6'; // Blue
            if ($pct >= 80) $color = '#22c55e'; // Green
            elseif ($pct < 40) $color = '#eab308'; // Yellow

            $progress_rows .= '
            <tr class="cat-row">
                <td width="35%">' . htmlspecialchars($cat) . '</td>
                <td width="50%">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: ' . $pct . '%; background-color: ' . $color . ';"></div>
                    </div>
                </td>
                <td width="15%" align="right" style="font-weight: bold;">' . $pct . '%</td>
            </tr>';
        }

        $progress_html = '
        <table class="progress-container">
            <tr>
                <td width="25%" class="total-box">
                    <div style="font-size: 12pt; font-weight: bold; color: #555;">Total Capaian</div>
                    <div class="total-percent">' . $total_progress_percent . '%</div>
                    <div style="font-size: 9pt; color: #777;">Rata-rata Periode Ini</div>
                </td>
                <td width="75%" style="padding-left: 25px; vertical-align: top;">
                    <div class="progress-title">Progres Per Materi</div>
                    <table width="100%">' . $progress_rows . '</table>
                </td>
            </tr>
        </table>';
    } else {
        $progress_html = '<div style="padding: 15px; border: 1px dashed #ccc; color: #777; text-align: center; margin-bottom: 20px;">Belum ada target kurikulum yang diatur.</div>';
    }

    $html = '
    <br><br>
    <h2 style="text-align: center; margin-bottom: 20px;">LAPORAN JURNAL KEGIATAN BELAJAR MENGAJAR</h2>
    
    <table class="meta-table">
        <tr>
            <td width="15%"><strong>Diekspor Oleh</strong></td>
            <td width="2%">:</td>
            <td>' . htmlspecialchars($nama_admin) . '</td>
        </tr>
        <tr>
            <td><strong>Kelompok</strong></td>
            <td>:</td>
            <td>' . htmlspecialchars($display_kelompok) . '</td>
        </tr>
        <tr>
            <td><strong>Kelas</strong></td>
            <td>:</td>
            <td>' . htmlspecialchars($display_kelas) . '</td>
        </tr>
        <tr>
            <td><strong>Periode</strong></td>
            <td>:</td>
            <td>' . htmlspecialchars($nama_periode) . '</td>
        </tr>
    </table>

    <!-- Insert Progress Summary Here -->
    ' . $progress_html . '

    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="12%">Hari, Tanggal</th>
                <th width="10%">Kelompok</th>
                <th width="10%">Kelas</th>
                <th width="15%">Pengajar</th>
                <th width="48%">Materi Disampaikan</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($data_final)) {
        $html .= '<tr><td colspan="6" class="text-center">Tidak ada data jurnal untuk filter ini.</td></tr>';
    } else {
        $no = 1;
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        foreach ($data_final as $row) {
            $ts = strtotime($row['tanggal']);
            $hari = $days[date('w', $ts)];
            $tgl_indo = date('d-m-Y', $ts);

            $html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . $hari . ', <br>' . $tgl_indo . '</td>
                <td class="text-center">' . ucfirst($row['kelompok']) . '</td>
                <td class="text-center">' . ucfirst($row['kelas']) . '</td>
                <td>' . htmlspecialchars($row['pengajar']) . '</td>
                <td>' . $row['isi_materi'] . '</td>
            </tr>';
        }
    }

    $html .= '</tbody></table>';

    $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

    $final_filename = $filename_base . ".pdf";
    $mpdf->Output($final_filename, 'D');
} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo "Terjadi kesalahan saat membuat PDF: " . $e->getMessage();
}
