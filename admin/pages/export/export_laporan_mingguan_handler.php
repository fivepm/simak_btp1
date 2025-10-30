<?php
// FILE HANDLER STANDALONE UNTUK EKSPOR PDF LAPORAN MINGGUAN

// Pastikan error reporting dimatikan untuk output PDF bersih
error_reporting(0);
ini_set('display_errors', 0);

// Include file konfigurasi database
include '../../../config/config.php'; // Sesuaikan path jika perlu

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
$stmt = $conn->prepare("SELECT * FROM laporan_mingguan WHERE id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("i", $id_laporan);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    die("Error: Laporan mingguan tidak ditemukan.");
}

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
// Fungsi Generate URL QuickChart (Ukuran Disesuaikan)
// ===================================================================
function generateQuickChartUrl($kelompok, $data_kelompok, $urutan_kelas)
{
    $chart_labels = [];
    $chart_data_sets = [ // Array untuk menyimpan dataset per status
        'Hadir' => [],
        'Izin' => [],
        'Sakit' => [],
        'Alpa' => [],
        'Kosong' => []
    ];
    $background_colors = [ // Warna tetap per status
        'Hadir' => 'rgba(34, 197, 94, 0.7)',
        'Izin' => 'rgba(245, 158, 11, 0.7)',
        'Sakit' => 'rgba(59, 130, 246, 0.7)',
        'Alpa' => 'rgba(239, 68, 68, 0.7)',
        'Kosong' => 'rgba(107, 114, 128, 0.5)'
    ];

    // Loop sesuai urutan kelas
    foreach ($urutan_kelas as $kelas) {
        $d = $data_kelompok[$kelas] ?? null;
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

        $totalJadwalMinggu = 0;
        $totalEntry = 0;
        $hadir = 0;
        $izin = 0;
        $sakit = 0;
        $alpa = 0;
        $kosong = 0;

        if ($d) {
            $totalJadwalMinggu = $d['total_jadwal_minggu'] ?? 0;
            $totalEntry = $d['hadir'] + $d['izin'] + $d['sakit'] + $d['alpa'] + $d['kosong'];
            if ($totalJadwalMinggu > 0) {
                $hadir = $d['hadir'] ?? 0;
                $izin = $d['izin'] ?? 0;
                $sakit = $d['sakit'] ?? 0;
                $alpa = $d['alpa'] ?? 0;
                $kosong = $d['kosong'] ?? max(0, $totalJadwalMinggu - ($hadir + $izin + $sakit + $alpa));
            }
        }
        // Hitung persentase
        $persen = fn($val) => $totalJadwalMinggu > 0 ? round(($val / $totalEntry) * 100) : null;

        $chart_data_sets['Hadir'][] = $persen($hadir);
        $chart_data_sets['Izin'][] = $persen($izin);
        $chart_data_sets['Sakit'][] = $persen($sakit);
        $chart_data_sets['Alpa'][] = $persen($alpa);
        $chart_data_sets['Kosong'][] = $persen($kosong);
    }

    // Buat array datasets untuk Chart.js
    $datasets = [];
    // Urutan stack: Hadir, Izin, Sakit, Alpa, Kosong
    $order = ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Kosong'];
    foreach ($order as $label) {
        if (isset($chart_data_sets[$label])) {
            $datasets[] = [
                'label' => $label,
                'data' => $chart_data_sets[$label],
                'backgroundColor' => $background_colors[$label],
            ];
        }
    }


    // Konfigurasi Chart.js dalam format JSON (Grouped Bar)
    $chart_config = [
        'type' => 'bar',
        'data' => [
            'labels' => $chart_labels,
            'datasets' => $datasets
        ],
        'options' => [
            'title' => [
                'display' => true,
                'text' => 'Kehadiran ' . ucfirst($kelompok) . ' (%)',
                'fontSize' => 14, // Ukuran font judul
                'fontStyle' => 'bold',
                'fontColor' => '#444'
            ],
            'responsive' => true,
            'scales' => [
                // PERBAIKAN: Gunakan format scales baru
                'x' => ['grid' => ['display' => false]],
                'y' => ['ticks' => ['beginAtZero' => true, 'max' => 100, 'callback' => 'function(value) { return value + "%"; }']]
            ],
            'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 10, 'fontSize' => 9]], // Perkecil legend
            'plugins' => [
                'datalabels' => [
                    'display' => true,
                    'anchor' => 'end',
                    'align' => 'top',
                    'offset' => -5,
                    'color' => '#000',
                    'font' => ['weight' => 'bold', 'size' => 9], // Ukuran font label
                    'formatter' => "(v, ctx) => { return v > 0 ? Math.round(v) + '%' : null; }", // String JS
                    'display' => '(ctx) => ctx.dataset.data[ctx.dataIndex] > 0', // Tampilkan jika > 5% (String JS)
                ],
                'tooltip' => ['enabled' => false] // Tooltip tidak relevan di PDF
            ]
        ]
    ];

    // Encode JSON, lalu URL encode
    $encoded_config = urlencode(json_encode($chart_config));

    // Ukuran gambar lebih lebar (800), tinggi bisa disesuaikan (400)
    // Format 'png' atau 'webp'
    return "https://quickchart.io/chart?w=800&h=400&f=png&c=" . $encoded_config;
}
// ===================================================================
// ▲▲▲ AKHIR FUNGSI QUICKCHART ▲▲▲
// ===================================================================

// ===================================================================
// ▼▼▼ TAMBAHAN: Fungsi Fetch Gambar via cURL ▼▼▼
// ===================================================================
/**
 * Mengambil gambar dari URL QuickChart dan mengembalikannya sebagai Base64 Data URI.
 * @param string $chart_url URL QuickChart.io
 * @return string Data URI (e.g., data:image/png;base64,...) atau string kosong jika gagal.
 */
function fetchChartImageAsBase64($chart_url)
{
    if (!function_exists('curl_init')) {
        error_log("cURL extension is not enabled.");
        return ''; // cURL dibutuhkan
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $chart_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout koneksi
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);      // Timeout total
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Hanya jika perlu di localhost

    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code !== 200 || empty($image_data)) {
        error_log("Failed to fetch chart image from QuickChart. URL: $chart_url, HTTP: $http_code, Error: $curl_error");
        return ''; // Gagal mengambil gambar
    }

    // Ambil tipe konten (seharusnya image/png)
    // QuickChart biasanya mengembalikan PNG
    $content_type = 'image/png';

    // Encode ke Base64 dan buat Data URI
    $base64_image = base64_encode($image_data);
    return 'data:' . $content_type . ';base64,' . $base64_image;
}
// ===================================================================
// ▲▲▲ AKHIR FUNGSI FETCH GAMBAR ▲▲▲
// ===================================================================


// ===================================================================
// LOGIKA PEMBUATAN PDF
// ===================================================================

// Ambil data admin dari sesi (untuk footer PDF)
$nama_admin = htmlspecialchars($_SESSION['user_nama'] ?? 'Admin');

// Sertakan mPDF autoload
require_once __DIR__ . '/../../../vendor/autoload.php';

// Aktifkan allow_remote_images (Meskipun pakai base64, ini bisa membantu jika ada fallback)
$tempDir = __DIR__ . '/../../../temp';
if (!is_dir($tempDir)) {
    if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
        die('Error: Failed to create temporary directory: ' . $tempDir);
    }
}
if (!is_writable($tempDir)) {
    die('Error: Temporary directory is not writable: ' . $tempDir);
}
$mpdf = new \Mpdf\Mpdf(['orientation' => 'P', 'tempDir' => $tempDir, 'allow_remote_images' => true, 'debug' => false]);

// --- Footer & Watermark ---
date_default_timezone_set('Asia/Jakarta');
$print_date = date('d/m/Y H:i:s');
$footerText = 'Report Mingguan SIMAK Banguntapan 1 | Dicetak pada: {DATE j-m-Y H:i} | Halaman {PAGENO} dari {nb}';
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

// Definisikan Path Logo
$logo_kiri_path = __DIR__ . '/../../../assets/images/logo_kbm.png';
$logo_kanan_path = __DIR__ . '/../../../assets/images/logo_simak.png';
$logo_kiri_html = file_exists($logo_kiri_path) ? '<img src="' . $logo_kiri_path . '" style="height: 50px; width: auto;">' : '';
$logo_kanan_html = file_exists($logo_kanan_path) ? '<img src="' . $logo_kanan_path . '" style="height: 50px; width: auto;">' : '';

// --- Buat Konten HTML untuk PDF ---
$html_pdf = '<html><head><style>
             /* ... (CSS tidak berubah) ... */
             body { font-family: sans-serif; font-size: 9pt; }
             .header-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
             .header-table td { border: none; vertical-align: middle; padding: 0 5px; }
             .header-table .logo-left { width: 10%; text-align: center; }
             .header-table .title { width: 80%; text-align: center; }
             .header-table .logo-right { width: 10%; text-align: center; }
             .header-table h1 { color: #333; font-size: 14pt; margin: 0; padding: 0; border: none; }

             h2 { font-size: 11pt; color: #555; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-top: 15px; margin-bottom: 8px;}
             table { width:100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8pt;}
             th, td { border: 1px solid #AAA; padding: 3px; text-align: left; vertical-align: top;}
             thead th { background-color:#f2f2f2; text-align: center; font-weight: bold;}
             .meta-info { margin-bottom: 15px; font-size: 8pt; color: #666; }
             .meta-info strong { color: #333; }
             .section { margin-bottom: 15px; page-break-inside: avoid; }
             .chart-container { text-align: center; margin-bottom: 10px; }
             .chart-container img { max-width: 100%; height: auto; border: 1px solid #ccc; }
             .text-center { text-align: center; }
             .text-success { color: green; } .text-danger { color: red; } .text-warning { color: orange; } .text-info { color: blue; }
             .capitalize { text-transform: capitalize; }

             .global-summary-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8pt; }
             .global-summary-table td { border: none; padding: 2px 5px; vertical-align: top; }
             .global-summary-table td.label { color: #555; width: 18%;}
             .global-summary-table td.value { font-weight: bold; width: 7%;}
             .global-summary-table td.spacer { width: 5%;}

             .whitespace-pre-wrap { white-space: pre-wrap; }
           </style></head><body>';

// Gunakan Tabel untuk Header
$html_pdf .= '<table class="header-table"><tr>';
$html_pdf .= '<td class="logo-left">' . $logo_kiri_html . '</td>';
$html_pdf .= '<td class="title"><h1>Laporan Mingguan SIMAK</h1></td>';
$html_pdf .= '<td class="logo-right">' . $logo_kanan_html . '</td>';
$html_pdf .= '</tr></table><hr>';


// Info Meta Laporan
$html_pdf .= '<div class="meta-info">';
$html_pdf .= 'Periode Minggu: <strong>' . formatTanggalIndo($laporan['tanggal_mulai']) . ' - ' . formatTanggalIndo($laporan['tanggal_akhir']) . '</strong><br>';
$html_pdf .= 'Dibuat Oleh: <strong>' . htmlspecialchars($laporan['nama_admin_pembuat']) . '</strong><br>';
$html_pdf .= 'Dikeluarkan Oleh: <strong>' . htmlspecialchars($nama_admin) . '</strong><br>';
$html_pdf .= 'Waktu Dibuat: <strong>' . formatTimestampIndo($laporan['timestamp_dibuat']) . '</strong><br>';
$html_pdf .= 'Terakhir Diperbarui: <strong>' . formatTimestampIndo($laporan['timestamp_diperbarui'] ?? null) . '</strong><br>';
$html_pdf .= 'Status: <strong>' . htmlspecialchars($laporan['status_laporan']) . '</strong>';
$html_pdf .= '</div>';

// Ringkasan Global jadi Tabel Tanpa Border
$html_pdf .= '<div class="section"><h2>Ringkasan Mingguan</h2>';
if (!empty($data_statistik['global'])) {
    $g = $data_statistik['global'];
    $html_pdf .= '<table class="global-summary-table">';
    // Baris 1
    $html_pdf .= '<tr>';
    $html_pdf .= '<td class="label">Total Jadwal:</td><td class="value">' . ($g['total_jadwal_minggu_ini'] ?? 0) . '</td>';
    $html_pdf .= '<td class="spacer"></td>';
    $html_pdf .= '<td class="label text-success">Total Hadir:</td><td class="value text-success">' . ($g['total_siswa_hadir'] ?? 0) . '</td>';
    $html_pdf .= '<td class="spacer"></td>';
    $html_pdf .= '<td class="label">Presensi Terisi:</td><td class="value">' . ($g['total_presensi_terisi'] ?? 0) . '</td>';
    $html_pdf .= '<td class="spacer"></td>';
    $html_pdf .= '<td class="label text-info">Total Izin:</td><td class="value text-info">' . ($g['total_siswa_izin'] ?? 0) . '</td>';
    $html_pdf .= '</tr>';
    // Baris 2
    $html_pdf .= '<tr>';
    $html_pdf .= '<td class="label">Jurnal Terisi:</td><td class="value">' . ($g['total_jurnal_terisi'] ?? 0) . '</td>';
    $html_pdf .= '<td class="spacer"></td>';
    $html_pdf .= '<td class="label text-warning">Total Sakit:</td><td class="value text-warning">' . ($g['total_siswa_sakit'] ?? 0) . '</td>';
    $html_pdf .= '<td class="spacer"></td>';
    $html_pdf .= '<td class="label text-danger">Jadwal Terlewat:</td><td class="value text-danger">' . ($g['jadwal_terlewat'] ?? 0) . '</td>';
    $html_pdf .= '<td class="spacer"></td>';
    $html_pdf .= '<td class="label text-danger">Total Alpa:</td><td class="value text-danger">' . ($g['total_siswa_alpa'] ?? 0) . '</td>';
    $html_pdf .= '</tr>';
    $html_pdf .= '</table>';
} else {
    $html_pdf .= '<p>Data global tidak tersedia.</p>';
}
$html_pdf .= '</div>';

// ==========================================================
// ▼▼▼ PERBAIKAN: Gunakan Base64 Image dari cURL ▼▼▼
// ==========================================================
$html_pdf .= '<div class="section"><h2>Grafik Persentase Status Kehadiran Mingguan</h2>';
if (!empty($data_statistik['rincian_per_kelompok'])) {
    // Loop satu per satu, tidak pakai tabel grid
    foreach ($URUTAN_KELOMPOK as $kelompok) {
        if (isset($data_statistik['rincian_per_kelompok'][$kelompok])) {
            // 1. Generate URL QuickChart
            $chart_url = generateQuickChartUrl($kelompok, $data_statistik['rincian_per_kelompok'][$kelompok], $URUTAN_KELAS);
            if (!empty($chart_url)) {
                // 2. Fetch gambar sebagai Base64
                $base64_image_data = fetchChartImageAsBase64($chart_url);

                $html_pdf .= '<div class="chart-container">';
                if (!empty($base64_image_data)) {
                    // 3. Tampilkan gambar menggunakan Data URI
                    $html_pdf .= '<img src="' . $base64_image_data . '" alt="Grafik Kehadiran ' . ucfirst($kelompok) . '">';
                } else {
                    // Fallback jika gagal fetch
                    $html_pdf .= '<p style="color:red; font-style:italic;">[Gagal memuat grafik untuk ' . ucfirst($kelompok) . ']</p>';
                }
                $html_pdf .= '</div>';
            }
        }
    }
} else {
    $html_pdf .= '<p>Data rincian tidak tersedia untuk grafik.</p>';
}
$html_pdf .= '</div>';
// ==========================================================
// ▲▲▲ AKHIR PERBAIKAN GRAFIK ▲▲▲
// ==========================================================


// 2. Rincian per Kelas (Tidak Berubah)
$html_pdf .= '<div class="section"><h2>Rincian Mingguan per Kelas</h2>';
// ... (Kode tabel rincian per kelas sama seperti sebelumnya) ...
$html_pdf .= '<table><thead><tr>';
$html_pdf .= '<th>Kelompok</th><th>Kelas</th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Kosong</th><th>Presensi Terisi</th><th>Jurnal Terisi</th>'; // Tambah header Kosong
$html_pdf .= '</tr></thead><tbody>';
$has_rincian_pdf = false;
if (!empty($data_statistik['rincian_per_kelompok'])) {
    foreach ($URUTAN_KELOMPOK as $kelompok) {
        foreach ($URUTAN_KELAS as $kelas) {
            if (isset($data_statistik['rincian_per_kelompok'][$kelompok][$kelas])) {
                $has_rincian_pdf = true;
                $d = $data_statistik['rincian_per_kelompok'][$kelompok][$kelas];
                $totalJadwalMinggu = $d['total_jadwal_minggu'] ?? 0;
                $hadir = $d['hadir'] ?? 0;
                $izin = $d['izin'] ?? 0;
                $sakit = $d['sakit'] ?? 0;
                $alpa = $d['alpa'] ?? 0;
                $kosong = $d['kosong'] ?? max(0, $totalJadwalMinggu - ($hadir + $izin + $sakit + $alpa));
                $presensiTerisi = $d['jadwal_presensi_terisi'] ?? 0;
                $jurnalTerisi = $d['jadwal_jurnal_terisi'] ?? 0;

                if ($totalJadwalMinggu === 0) {
                    $html_pdf .= '<tr style="background-color:#f9f9f9;"><td class="capitalize">' . $kelompok . '</td><td class="capitalize">' . $kelas . '</td><td colspan="7" class="text-center" style="color:#999;"><em>Tidak ada jadwal</em></td></tr>'; // colspan 7
                } else {
                    $html_pdf .= '<tr>';
                    $html_pdf .= '<td class="capitalize">' . $kelompok . '</td>';
                    $html_pdf .= '<td class="capitalize">' . $kelas . '</td>';
                    $html_pdf .= '<td class="text-center text-success">' . $hadir . '</td>';
                    $html_pdf .= '<td class="text-center text-warning">' . $izin . '</td>'; // Izin Kuning
                    $html_pdf .= '<td class="text-center text-info">' . $sakit . '</td>';    // Sakit Biru
                    $html_pdf .= '<td class="text-center text-danger">' . $alpa . '</td>';
                    $html_pdf .= '<td class="text-center" style="color:#666;">' . $kosong . '</td>'; // Kolom Kosong
                    $html_pdf .= '<td class="text-center">' . $presensiTerisi . '/' . $totalJadwalMinggu . '</td>';
                    $html_pdf .= '<td class="text-center">' . $jurnalTerisi . '/' . $totalJadwalMinggu . '</td>';
                    $html_pdf .= '</tr>';
                }
            }
        }
    }
}
if (!$has_rincian_pdf) {
    $html_pdf .= '<tr><td colspan="9" class="text-center">Tidak ada data rincian.</td></tr>'; // colspan 9
}
$html_pdf .= '</tbody></table></div>';

// 3. Daftar Siswa Alpa (Tidak Berubah)
$html_pdf .= '<div class="section"><h2>Daftar Siswa Alpa Minggu Ini</h2>';
// ... (Kode tabel daftar alpa sama seperti sebelumnya, pastikan kolom Jml Alpa ada) ...
if (!empty($data_statistik['daftar_alpa'])) {
    $html_pdf .= '<table><thead><tr><th>Nama Siswa</th><th>Kelompok</th><th>Kelas</th><th>Jml Alpa</th><th>Nama Ortu</th><th>No. HP Ortu</th></tr></thead><tbody>'; // Tambah Jml Alpa
    foreach ($data_statistik['daftar_alpa'] as $siswa) {
        $html_pdf .= '<tr>';
        $html_pdf .= '<td>' . htmlspecialchars($siswa['nama_lengkap'] ?? '') . '</td>';
        $html_pdf .= '<td class="capitalize">' . htmlspecialchars($siswa['kelompok'] ?? '') . '</td>';
        $html_pdf .= '<td class="capitalize">' . htmlspecialchars($siswa['kelas'] ?? '') . '</td>';
        $html_pdf .= '<td class="text-center text-danger" style="font-weight:bold;">' . htmlspecialchars($siswa['jumlah_alpa'] ?? 0) . '</td>'; // Tampilkan Jml Alpa
        $html_pdf .= '<td>' . htmlspecialchars($siswa['nama_orang_tua'] ?? '-') . '</td>';
        $html_pdf .= '<td>' . htmlspecialchars($siswa['nomor_hp_orang_tua'] ?? '-') . '</td>';
        $html_pdf .= '</tr>';
    }
    $html_pdf .= '</tbody></table>';
} else {
    $html_pdf .= '<p>Tidak ada siswa alpa minggu ini.</p>';
}
$html_pdf .= '</div>';


// 4. Catatan Kondisi (Tidak Berubah)
$html_pdf .= '<div class="section"><h2>Catatan Kondisi Mingguan</h2>';
$html_pdf .= '<p style="border: 1px solid #eee; padding: 10px; background-color:#fdfdfd; white-space: pre-wrap;">' . htmlspecialchars($laporan['catatan_kondisi']) . '</p>';
$html_pdf .= '</div>';

// 5. Rekomendasi Tindakan (Tidak Berubah)
$html_pdf .= '<div class="section"><h2>Rekomendasi Tindakan Mingguan</h2>';
$html_pdf .= '<p style="border: 1px solid #eee; padding: 10px; background-color:#fdfdfd; white-space: pre-wrap;">' . htmlspecialchars($laporan['rekomendasi_tindakan']) . '</p>';
$html_pdf .= '</div>';

// 6. Tindak Lanjut (jika ada) (Tidak Berubah)
if (!empty($laporan['tindak_lanjut_ketua'])) {
    $html_pdf .= '<div class="section"><h2>Tindak Lanjut</h2>';
    $html_pdf .= '<p style="border: 1px solid #eee; padding: 10px; background-color:#f0fff0; white-space: pre-wrap;">' . htmlspecialchars($laporan['tindak_lanjut_ketua']) . '</p>';
    $html_pdf .= '</div>';
}

$html_pdf .= '</body></html>';

// Tulis HTML ke PDF dan output
try {
    // Hapus output buffering sebelum WriteHTML
    if (ob_get_level()) {
        ob_end_clean();
    }

    $mpdf->WriteHTML($html_pdf);
    $nama_file_pdf = "Laporan_Mingguan_" . $laporan['tanggal_mulai'] . ".pdf";
    $mpdf->Output($nama_file_pdf, 'D'); // 'D' = Force Download
} catch (\Mpdf\MpdfException $e) {
    // Log error jika ada masalah mPDF
    error_log("MPDF Error in export_laporan_mingguan_handler: " . $e->getMessage());
    die("MPDF Error: Gagal membuat PDF. Silakan cek log server.");
}


// Tutup koneksi database
$conn->close();
exit; // Hentikan script setelah PDF dikirim
