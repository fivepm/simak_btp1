<?php
// FILE HANDLER STANDALONE UNTUK EKSPOR PDF LAPORAN HARIAN

// Pastikan error reporting dimatikan untuk output PDF bersih
error_reporting(0);
ini_set('display_errors', 0);

// Include file konfigurasi database
include '../../../config/config.php'; // Sesuaikan path jika perlu
require_once '../../../helpers/log_helper.php';

// Mulai sesi untuk mengambil nama admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil ID laporan dari URL
$id_laporan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_laporan <= 0) {
    die("Error: ID Laporan tidak valid.");
}

// Ambil data laporan dari database
// Koneksi $conn dari config.php
if (!$conn) {
    die("Error: Gagal terhubung ke database.");
}
$stmt = $conn->prepare("SELECT * FROM laporan_harian WHERE id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("i", $id_laporan);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    die("Error: Laporan tidak ditemukan.");
}

// === PENCATATAN LOG AKTIVITAS ===
$tgl_log = formatTanggalIndonesia($laporan['tanggal_laporan']);
$deskripsi_log = "Cetak *Laporan Harian* pada tanggal $tgl_log. Format: PDF";

writeLog('EXPORT', $deskripsi_log);
// =================================

// Decode data statistik JSON
$data_statistik = json_decode($laporan['data_statistik'], true);
// Fallback jika JSON decode gagal
if ($data_statistik === null) {
    die("Error: Data statistik dalam laporan rusak.");
}

// Definisikan urutan standar (harus sama dengan di form)
$URUTAN_KELOMPOK = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

// Helper function untuk format tanggal (didefinisikan ulang di sini karena standalone)
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($tanggal_db)
    {
        if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
        try {
            $date = new DateTime($tanggal_db);
            $bulan_indonesia = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return $date->format('j') . ' ' . $bulan_indonesia[(int)$date->format('n')] . ' ' . $date->format('Y');
        } catch (Exception $e) {
            return date('d/m/Y', strtotime($tanggal_db));
        }
    }
}
if (!function_exists('formatTimestampIndo')) {
    function formatTimestampIndo($timestamp_db)
    {
        if (empty($timestamp_db) || $timestamp_db === '0000-00-00 00:00:00') return '-';
        try {
            // Asumsikan $timestamp_db sudah dalam WIB
            $date = new DateTime($timestamp_db);
            return formatTanggalIndo($date->format('Y-m-d')) . ' ' . $date->format('H:i') . ' WIB';
        } catch (Exception $e) {
            return '-';
        }
    }
}

// ===================================================================
// ▼▼▼ TAMBAHAN: Fungsi untuk Generate URL QuickChart ▼▼▼
// ===================================================================
/**
 * Membuat URL QuickChart.io untuk grafik batang kehadiran per kelompok.
 * @param string $kelompok Nama kelompok (e.g., 'bintaran')
 * @param array $data_kelompok Data rincian untuk kelompok tersebut
 * @param array $urutan_kelas Array urutan kelas standar
 * @return string URL gambar chart atau string kosong jika error
 */
function generateQuickChartUrl($kelompok, $data_kelompok, $urutan_kelas)
{
    $chart_labels = [];
    $chart_data = [];
    $background_colors = [];
    $border_colors = [];

    // Fungsi helper warna (sama seperti JS)
    $getBarColor = function ($value) {
        if ($value === null) return 'rgba(156, 163, 175, 0.6)'; // Abu-abu
        if ($value < 50) return 'rgba(239, 68, 68, 0.6)'; // Merah
        if ($value >= 50 && $value <= 75) return 'rgba(245, 158, 11, 0.6)'; // Kuning
        return 'rgba(34, 197, 94, 0.6)'; // Hijau
    };
    $getBorderColor = function ($value) {
        if ($value === null) return 'rgba(156, 163, 175, 1)'; // Abu-abu solid
        if ($value < 50) return 'rgba(239, 68, 68, 1)'; // Merah solid
        if ($value >= 50 && $value <= 75) return 'rgba(245, 158, 11, 1)'; // Kuning solid
        return 'rgba(34, 197, 94, 1)'; // Hijau solid
    };

    // Loop sesuai urutan kelas
    foreach ($urutan_kelas as $kelas) {
        $d = $data_kelompok[$kelas] ?? null;
        // PERBAIKAN LABEL: Singkat saja
        // $chart_labels[] = ucwords($kelas); // Label lama
        // Gunakan singkatan jika memungkinkan
        switch (strtolower($kelas)) {
            case 'caberawit a':
                $label = 'CBR A';
                break;
            case 'caberawit b':
                $label = 'CBR B';
                break;
            case 'pra remaja':
                $label = 'PraRemaja';
                break;
            default:
                $label = ucwords($kelas);
        }
        $chart_labels[] = $label;


        $percentage = null;
        if ($d) {
            $totalTerisi = $d['hadir'] + $d['izin'] + $d['sakit'] + $d['alpa'];
            if ($d['total_jadwal'] > 0) {
                $percentage = ($totalTerisi > 0) ? round(($d['hadir'] / $totalTerisi) * 100) : 0;
            }
        }
        $chart_data[] = $percentage; // Data (angka atau null)
        $background_colors[] = $getBarColor($percentage);
        $border_colors[] = $getBorderColor($percentage);
    }

    // Konfigurasi Chart.js dalam format JSON
    $chart_config = [
        'type' => 'bar',
        'data' => [
            'labels' => $chart_labels,
            'datasets' => [[
                // 'label' => 'Kehadiran (%)', // Label dataset tidak perlu di PDF
                'data' => $chart_data,
                'backgroundColor' => $background_colors,
                'borderColor' => $border_colors,
                'borderWidth' => 1
            ]]
        ],
        'options' => [
            'title' => [ // Tambahkan Judul Grafik
                'display' => true,
                'text' => ucfirst($kelompok),
                'fontSize' => 12,
                'fontStyle' => 'bold',
                'fontColor' => '#444'
            ],
            'responsive' => true,
            'scales' => ['yAxes' => [['ticks' => ['beginAtZero' => true, 'max' => 100]]]],
            'legend' => ['display' => false],
            'plugins' => [
                'datalabels' => [
                    // PERBAIKAN: Sederhanakan opsi untuk QuickChart
                    'anchor' => 'end',
                    'align' => 'top', // Selalu di atas
                    'offset' => -6,    // Sedikit di bawah ujung atas
                    // PERBAIKAN: Formatter sederhana
                    'formatter' => "(ctx) => { const v = ctx.dataset.data[ctx.dataIndex]; return v === null ? 'N/A' : v + '%'; }", // String JS
                    'color' => '#6b7280', // Warna default
                    'font' => ['weight' => 'bold']
                ],
                'tooltip' => ['enabled' => false]
            ]
        ]
    ];

    // Encode JSON, lalu URL encode
    $encoded_config = urlencode(json_encode($chart_config));

    // PERBAIKAN: Perbesar lebar gambar (misal 800px), tinggi bisa disesuaikan (misal 400px)
    return "https://quickchart.io/chart?w=400&h=250&c=" . $encoded_config;
}
// ===================================================================
// ▲▲▲ AKHIR FUNGSI QUICKCHART ▲▲▲
// ===================================================================


// ===================================================================
// LOGIKA PEMBUATAN PDF
// ===================================================================

// Ambil data admin dari sesi (untuk footer PDF)
$nama_admin = htmlspecialchars($_SESSION['user_nama'] ?? 'Admin');

// Sertakan mPDF autoload
require_once __DIR__ . '/../../../vendor/autoload.php';

// Aktifkan allow_remote_images agar bisa load dari QuickChart
// Juga set tempDir karena mPDF membutuhkannya untuk remote images
$tempDir = __DIR__ . '/../../../temp'; // Pastikan folder 'temp' ada dan writable
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}
$mpdf = new \Mpdf\Mpdf(['orientation' => 'P', 'tempDir' => $tempDir, 'allow_remote_images' => true]);

// --- Footer & Watermark ---
date_default_timezone_set('Asia/Jakarta'); // Set timezone untuk tanggal cetak
$print_date = date('d/m/Y H:i:s');
$footerText = 'Report Harian SIMAK Banguntapan 1 | Dicetak pada: {DATE j-m-Y H:i} | Halaman {PAGENO} dari {nb}';
$mpdf->SetFooter($footerText);
$mpdf->SetDisplayMode('fullpage');
$watermark_path = __DIR__ . '/../../../assets/images/logo_kbm.png';
if (file_exists($watermark_path)) {
    $mpdf->SetWatermarkImage($watermark_path, 0.1, 'auto', 'P');
    $mpdf->showWatermarkImage = true;
} else {
    $mpdf->SetWatermarkText('SIMAK BT1');
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'DejaVuSansCondensed';
    $mpdf->watermarkTextAlpha = 0.05;
}

$logo_kiri_path = __DIR__ . '/../../../assets/images/logo_kbm.png'; // Contoh path
$logo_kanan_path = __DIR__ . '/../../../assets/images/logo_simak.png'; // Contoh path

// Cek apakah file logo ada
$logo_kiri_html = file_exists($logo_kiri_path) ? '<img src="' . $logo_kiri_path . '" style="height: 50px; width: auto;">' : '';
$logo_kanan_html = file_exists($logo_kanan_path) ? '<img src="' . $logo_kanan_path . '" style="height: 50px; width: auto;">' : '';

// --- Buat Konten HTML untuk PDF ---
$html_pdf = '<html><head><style>
            body { font-family: sans-serif; font-size: 10pt; }
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .header-table td { border: none; vertical-align: middle; padding: 0 10px; }
            .header-table .logo-left { width: 15%; text-align: center; }
            .header-table .title { width: 70%; text-align: center; }
            .header-table .logo-right { width: 15%; text-align: center; }
            .header-table h1 { color: #333; font-size: 16pt; margin: 0; padding: 0; border: none; } 

            h2 { font-size: 12pt; color: #555; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 20px; margin-bottom: 10px;}
            table { width:100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9pt;}
            th, td { border: 1px solid #AAA; padding: 4px; text-align: left; vertical-align: top;}
            thead th { background-color:#f2f2f2; text-align: center; font-weight: bold;}
            .meta-info { margin-bottom: 20px; font-size: 9pt; color: #666; }
            .meta-info strong { color: #333; }
            .section { margin-bottom: 20px; page-break-inside: avoid; } 
            .chart-container { text-align: center; margin-bottom: 10px; } 
            .chart-container img { max-width: 100%; height: auto; border: 1px solid #ccc; } 
            .text-center { text-align: center; }
            .text-success { color: green; } .text-danger { color: red; } .text-warning { color: orange; } .text-info { color: blue; }
            .capitalize { text-transform: capitalize; }
            
            /* PERBAIKAN: Style untuk Global Summary Tabel */
            .global-summary-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9pt; }
            .global-summary-table td { border: none; padding: 3px 5px; vertical-align: top; }
            .global-summary-table td.label { color: #555; width: 18%;}
            .global-summary-table td.value { font-weight: bold; width: 7%;}
            .global-summary-table td.spacer { width: 5%;} /* Spasi antar kolom */
            
            /* PERBAIKAN: Style untuk Grid Grafik 2x2 */
            .chart-grid { width: 100%; }
            .chart-grid .chart-cell { width: 48%; display: inline-block; vertical-align: top; padding: 0 0.5%; /* Jarak antar grafik */ box-sizing: border-box; } 
            .whitespace-pre-wrap { white-space: pre-wrap; } 
            </style></head><body>';

// ==========================================================
// ▼▼▼ PERBAIKAN: Gunakan Tabel untuk Header ▼▼▼
// ==========================================================
$html_pdf .= '<table class="header-table"><tr>';
$html_pdf .= '<td class="logo-left">' . $logo_kiri_html . '</td>';
$html_pdf .= '<td class="title"><h1>Laporan Harian SIMAK</h1></td>';
$html_pdf .= '<td class="logo-right">' . $logo_kanan_html . '</td>';
$html_pdf .= '</tr></table><hr>';
// ==========================================================

// Info Meta Laporan
$html_pdf .= '<div class="meta-info">';
$html_pdf .= 'Tanggal Laporan: <strong>' . formatTanggalIndo($laporan['tanggal_laporan']) . '</strong><br>';
$html_pdf .= 'Dibuat Oleh: <strong>' . htmlspecialchars($laporan['nama_admin_pembuat']) . '</strong><br>';
$html_pdf .= 'Dikeluarkan Oleh: <strong>' . htmlspecialchars($nama_admin) . '</strong><br>';
$html_pdf .= 'Waktu Dibuat: <strong>' . formatTimestampIndo($laporan['timestamp_dibuat']) . '</strong><br>';
$html_pdf .= 'Terakhir Diperbarui: <strong>' . formatTimestampIndo($laporan['timestamp_diperbarui'] ?? null) . '</strong><br>';
$html_pdf .= 'Status: <strong>' . htmlspecialchars($laporan['status_laporan']) . '</strong>';
$html_pdf .= '</div>';

// 1. Ringkasan Global
$html_pdf .= '<div class="section"><h2>Ringkasan Umum</h2>';
if (!empty($data_statistik['global'])) {
    $g = $data_statistik['global'];
    $html_pdf .= '<table class="global-summary-table">';
    // Baris 1
    $html_pdf .= '<tr>';
    $html_pdf .= '<td class="label">Total Jadwal:</td><td class="value">' . $g['total_jadwal_hari_ini'] . '</td>';
    $html_pdf .= '<td class="spacer"></td>'; // Spasi
    $html_pdf .= '<td class="label">Presensi Terisi:</td><td class="value">' . $g['total_presensi_terisi'] . '</td>';
    $html_pdf .= '<td class="spacer"></td>'; // Spasi
    $html_pdf .= '<td class="label">Jurnal Terisi:</td><td class="value">' . $g['total_jurnal_terisi'] . '</td>';
    $html_pdf .= '<td class="spacer"></td>'; // Spasi
    $html_pdf .= '<td class="label text-danger">Presensi Terlewat:</td><td class="value text-danger">' . $g['jadwal_terlewat'] . '</td>';
    $html_pdf .= '</tr>';
    // Baris 2
    $html_pdf .= '<tr>';
    $html_pdf .= '<td class="label text-success">Total Hadir:</td><td class="value text-success">' . $g['total_siswa_hadir'] . '</td>';
    $html_pdf .= '<td class="spacer"></td>'; // Spasi
    $html_pdf .= '<td class="label text-warning">Total Sakit:</td><td class="value text-warning">' . $g['total_siswa_sakit'] . '</td>';
    $html_pdf .= '<td class="spacer"></td>'; // Spasi
    $html_pdf .= '<td class="label text-info">Total Izin:</td><td class="value text-info">' . $g['total_siswa_izin'] . '</td>';
    $html_pdf .= '<td class="spacer"></td>'; // Spasi
    $html_pdf .= '<td class="label text-danger">Total Alpa:</td><td class="value text-danger">' . $g['total_siswa_alpa'] . '</td>';
    $html_pdf .= '</tr>';
    $html_pdf .= '</table>';
} else {
    $html_pdf .= '<p>Data global tidak tersedia.</p>';
}
$html_pdf .= '</div>';

// ==========================================================
// ▼▼▼ PERBAIKAN: Sisipkan Gambar Grafik (SATU PER SATU) ▼▼▼
// ==========================================================
$html_pdf .= '<div class="section"><h2>Grafik Persentase Kehadiran</h2>';
if (!empty($data_statistik['rincian_per_kelompok'])) {
    $html_pdf .= '<table class="chart-grid-table">'; // Buka Tabel Grid
    $chart_urls = [];
    // Generate semua URL dulu
    foreach ($URUTAN_KELOMPOK as $kelompok) {
        if (isset($data_statistik['rincian_per_kelompok'][$kelompok])) {
            $chart_urls[$kelompok] = generateQuickChartUrl($kelompok, $data_statistik['rincian_per_kelompok'][$kelompok], $URUTAN_KELAS);
        } else {
            $chart_urls[$kelompok] = null; // Tandai jika tidak ada data
        }
    }

    // Buat Baris 1
    $html_pdf .= '<tr>';
    // Kolom 1 (Bintaran)
    $html_pdf .= '<td>';
    if ($chart_urls['bintaran']) {
        $html_pdf .= '<div class="chart-container"><img src="' . $chart_urls['bintaran'] . '" alt="Grafik Bintaran"></div>';
    } else {
        $html_pdf .= 'Bintaran: N/A';
    }
    $html_pdf .= '</td>';
    // Kolom 2 (Gedongkuning)
    $html_pdf .= '<td>';
    if ($chart_urls['gedongkuning']) {
        $html_pdf .= '<div class="chart-container"><img src="' . $chart_urls['gedongkuning'] . '" alt="Grafik Gedongkuning"></div>';
    } else {
        $html_pdf .= 'Gedongkuning: N/A';
    }
    $html_pdf .= '</td>';
    $html_pdf .= '</tr>';

    // Buat Baris 2
    $html_pdf .= '<tr>';
    // Kolom 1 (Jombor)
    $html_pdf .= '<td>';
    if ($chart_urls['jombor']) {
        $html_pdf .= '<div class="chart-container"><img src="' . $chart_urls['jombor'] . '" alt="Grafik Jombor"></div>';
    } else {
        $html_pdf .= 'Jombor: N/A';
    }
    $html_pdf .= '</td>';
    // Kolom 2 (Sunten)
    $html_pdf .= '<td>';
    if ($chart_urls['sunten']) {
        $html_pdf .= '<div class="chart-container"><img src="' . $chart_urls['sunten'] . '" alt="Grafik Sunten"></div>';
    } else {
        $html_pdf .= 'Sunten: N/A';
    }
    $html_pdf .= '</td>';
    $html_pdf .= '</tr>';

    $html_pdf .= '</table>'; // Tutup Tabel Grid
} else {
    $html_pdf .= '<p>Data rincian tidak tersedia untuk grafik.</p>';
}
$html_pdf .= '</div>';
// ==========================================================
// ▲▲▲ AKHIR PERBAIKAN GRAFIK ▲▲▲
// ==========================================================


// 2. Rincian per Kelas
$html_pdf .= '<div class="section"><h2>Rincian per Kelas</h2>';
$html_pdf .= '<table><thead><tr>';
$html_pdf .= '<th>Kelompok</th><th>Kelas</th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Presensi</th><th>Jurnal</th>';
$html_pdf .= '</tr></thead><tbody>';
$has_rincian_pdf = false;
// Pastikan data rincian ada
if (!empty($data_statistik['rincian_per_kelompok'])) {
    foreach ($URUTAN_KELOMPOK as $kelompok) {
        foreach ($URUTAN_KELAS as $kelas) {
            if (isset($data_statistik['rincian_per_kelompok'][$kelompok][$kelas])) {
                $has_rincian_pdf = true;
                $d = $data_statistik['rincian_per_kelompok'][$kelompok][$kelas];
                if ($d['total_jadwal'] === 0) {
                    $html_pdf .= '<tr style="background-color:#f9f9f9;"><td class="capitalize">' . $kelompok . '</td><td class="capitalize">' . $kelas . '</td><td colspan="6" class="text-center" style="color:#999;"><em>Tidak ada jadwal</em></td></tr>';
                } else {
                    $html_pdf .= '<tr>';
                    $html_pdf .= '<td class="capitalize">' . $kelompok . '</td>';
                    $html_pdf .= '<td class="capitalize">' . $kelas . '</td>';
                    $html_pdf .= '<td class="text-center text-success">' . $d['hadir'] . '</td>';
                    $html_pdf .= '<td class="text-center text-info">' . $d['izin'] . '</td>';
                    $html_pdf .= '<td class="text-center text-warning">' . $d['sakit'] . '</td>';
                    $html_pdf .= '<td class="text-center text-danger">' . $d['alpa'] . '</td>';
                    $presensiIconPdf = ($d['jadwal_terisi'] === $d['total_jadwal']) ? '<span style="color:green;font-weight:bold;">✓</span>' : '<span style="color:red;font-weight:bold;">X</span>';
                    $jurnalIconPdf = ($d['jurnal_terisi'] === $d['total_jadwal']) ? '<span style="color:green;font-weight:bold;">✓</span>' : '<span style="color:red;font-weight:bold;">X</span>';
                    $html_pdf .= '<td class="text-center">' . $presensiIconPdf . '</td>';
                    $html_pdf .= '<td class="text-center">' . $jurnalIconPdf . '</td>';
                    $html_pdf .= '</tr>';
                }
            }
        }
    }
} // Akhir cek empty rincian
if (!$has_rincian_pdf) {
    $html_pdf .= '<tr><td colspan="8" class="text-center">Tidak ada data rincian.</td></tr>';
}
$html_pdf .= '</tbody></table></div>';

// 3. Daftar Siswa Alpa
$html_pdf .= '<div class="section"><h2>Daftar Siswa Alpa</h2>';
if (!empty($data_statistik['daftar_alpa'])) {
    $html_pdf .= '<table><thead><tr><th>Nama Siswa</th><th>Kelompok</th><th>Kelas</th><th>Nama Ortu</th><th>No. HP Ortu</th></tr></thead><tbody>';
    foreach ($data_statistik['daftar_alpa'] as $siswa) {
        $html_pdf .= '<tr>';
        $html_pdf .= '<td>' . htmlspecialchars($siswa['nama_lengkap']) . '</td>';
        $html_pdf .= '<td class="capitalize">' . htmlspecialchars($siswa['kelompok']) . '</td>';
        $html_pdf .= '<td class="capitalize">' . htmlspecialchars($siswa['kelas']) . '</td>';
        $html_pdf .= '<td>' . htmlspecialchars($siswa['nama_orang_tua'] ?? '-') . '</td>';
        $html_pdf .= '<td>' . htmlspecialchars($siswa['nomor_hp_orang_tua'] ?? '-') . '</td>';
        $html_pdf .= '</tr>';
    }
    $html_pdf .= '</tbody></table>';
} else {
    $html_pdf .= '<p>Tidak ada siswa alpa pada tanggal ini.</p>';
}
$html_pdf .= '</div>';

// 4. Catatan Kondisi
$html_pdf .= '<div class="section"><h2>Catatan Kondisi</h2>';
$html_pdf .= '<p style="border: 1px solid #eee; padding: 10px; background-color:#fdfdfd; white-space: pre-wrap;">' . htmlspecialchars($laporan['catatan_kondisi']) . '</p>';
$html_pdf .= '</div>';

// 5. Rekomendasi Tindakan
$html_pdf .= '<div class="section"><h2>Rekomendasi Tindakan</h2>';
$html_pdf .= '<p style="border: 1px solid #eee; padding: 10px; background-color:#fdfdfd; white-space: pre-wrap;">' . htmlspecialchars($laporan['rekomendasi_tindakan']) . '</p>';
$html_pdf .= '</div>';

// 6. Tindak Lanjut (jika ada)
if (!empty($laporan['tindak_lanjut_ketua'])) {
    $html_pdf .= '<div class="section"><h2>Tindak Lanjut</h2>';
    $html_pdf .= '<p style="border: 1px solid #eee; padding: 10px; background-color:#f0fff0; white-space: pre-wrap;">' . htmlspecialchars($laporan['tindak_lanjut_ketua']) . '</p>';
    $html_pdf .= '</div>';
}

$html_pdf .= '</body></html>';

// Tulis HTML ke PDF dan output
try {
    // PERBAIKAN: Hapus output buffering sebelum WriteHTML
    if (ob_get_level()) {
        ob_end_clean();
    }

    $mpdf->WriteHTML($html_pdf);
    $nama_file_pdf = "Laporan_Harian_" . $laporan['tanggal_laporan'] . ".pdf";
    $mpdf->Output($nama_file_pdf, 'D'); // 'D' = Force Download
} catch (\Mpdf\MpdfException $e) {
    // Log error jika ada masalah mPDF
    error_log("MPDF Error in export_laporan_harian_handler: " . $e->getMessage());
    die("MPDF Error: Gagal membuat PDF. Silakan cek log server.");
}


// Tutup koneksi database
$conn->close();
exit; // Hentikan script setelah PDF dikirim
