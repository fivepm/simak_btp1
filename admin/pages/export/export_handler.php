<?php
// Pastikan file ini di-include oleh index.php Anda, 
// sehingga $conn dan $_SESSION sudah tersedia.

// Nonaktifkan error reporting untuk output file bersih
// error_reporting(0);
// ini_set('display_errors', 0);
// Diasumsikan file koneksi.php sudah di-include
include '../../../config/config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil data sesi admin
$admin_role = $_SESSION['user_role'] ?? 'admin';
$admin_level = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;
$nama_admin = htmlspecialchars($_SESSION['user_nama']);

// Sertakan mPDF autoload (pastikan path ini benar)
require_once __DIR__ . '/../../../vendor/autoload.php';

// Fungsi helper untuk format tanggal (jika Anda belum memilikinya)
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($tanggal_db)
    {
        if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
        try {
            $date = new DateTime($tanggal_db);
            $bulan_indonesia = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return $date->format('j') . ' ' . $bulan_indonesia[(int)$date->format('n')] . ' ' . $date->format('Y');
        } catch (Exception $e) {
            return '';
        }
    }
}

// Ambil data POST dari modal
$action = $_POST['action'] ?? null;
$format = $_POST['format'] ?? 'csv';
$tipe_laporan = $_POST['tipe_laporan'] ?? []; // Ini sekarang adalah ARRAY

$periode_id = (int)($_POST['periode_id'] ?? null);
$kelompok = $_POST['kelompok'] ?? null;
$kelas = $_POST['kelas'] ?? null;

// Jika datanya tidak lengkap, hentikan
if ($action !== 'export_terfilter' || !$periode_id || !$kelompok || !$kelas || empty($tipe_laporan)) {
    die("Akses tidak sah atau data tidak lengkap (pastikan minimal 1 tipe laporan dipilih).");
}

$query_current = "SELECT id, nama_periode FROM periode WHERE id = $periode_id LIMIT 1";
$result_current = $conn->query($query_current);
if ($result_current && $result_current->num_rows > 0) {
    $periode_aktif = $result_current->fetch_assoc();
}
$periode_aktif_id = $periode_aktif['id'] ?? null;
$periode_aktif_nama = $periode_aktif['nama_periode'] ?? 'Tidak Ada Periode Aktif';

// Header CORS (Cross-Origin Resource Sharing)
// Ini mengizinkan JavaScript (fetch) untuk membaca respon
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Headers: *");
// Ini PENTING: mengizinkan JS membaca header 'Content-Disposition'
// header("Access-Control-Expose-Headers: Content-Disposition");


$nama_file_prefix = "Rekap_Kehadiran_";
$nama_file_suffix = "_{$kelompok}_{$kelas}_" . date('Y-m-d');

// ===================================================================
// LOGIKA UTAMA
// ===================================================================

if ($format === 'pdf') {
    // --- LOGIKA UNTUK PDF (BISA MENGGABUNGKAN BEBERAPA LAPORAN) ---
    $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']); // Default Landscape

    // ========================================================
    // PENAMBAHAN FOOTER DAN WATERMARK
    // ========================================================

    // --- 1. FOOTER ---
    $print_date = date('d/m/Y H:i:s');
    $footerText = 'Rekap Kehadiran SIMAK Banguntapan 1 | Dicetak pada: {DATE j-m-Y H:i} | Halaman {PAGENO} dari {nb}';
    $mpdf->SetFooter($footerText);
    $mpdf->SetDisplayMode('fullpage');

    // --- 2. WATERMARK FOTO (dengan Fallback Teks) ---
    $watermark_path = __DIR__ . '/../../../assets/images/logo_kbm.png';

    if (file_exists($watermark_path)) {
        $mpdf->SetWatermarkImage(
            $watermark_path,
            0.1, // Opacity
            'auto',
            'P'
        );
        $mpdf->showWatermarkImage = true;
    } else {
        $mpdf->SetWatermarkText('SIMAK BT1');
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'DejaVuSansCondensed';
        $mpdf->watermarkTextAlpha = 0.05;
    }
    // ========================================================

    $logo_kiri_path = __DIR__ . '/../../../assets/images/logo_kbm.png'; // Contoh path
    $logo_kanan_path = __DIR__ . '/../../../assets/images/logo_simak.png'; // Contoh path

    // Cek apakah file logo ada
    $logo_kiri_html = file_exists($logo_kiri_path) ? '<img src="' . $logo_kiri_path . '" style="height: 50px; width: auto;">' : '';
    $logo_kanan_html = file_exists($logo_kanan_path) ? '<img src="' . $logo_kanan_path . '" style="height: 50px; width: auto;">' : '';

    $html_output = '<html><head><style>
                    body { font-family: sans-serif; font-size: 10pt; }
                    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    .header-table td { border: none; vertical-align: middle; padding: 0 10px; }
                    .header-table .logo-left { width: 15%; text-align: center; }
                    .header-table .title { width: 70%; text-align: center; }
                    .header-table .logo-right { width: 15%; text-align: center; }
                    .header-table h1 { color: #333; font-size: 16pt; margin: 0; padding: 0; border: none; } 
                    h1 { color: #333; font-size: 18pt; } h2 { font-size: 14pt; }
                    table { width:100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #AAA; padding: 5px; text-align: left; }
                    thead th { background-color:#FFFB00; text-align: center; }
                    tfoot tr { background-color:#f2f2f2; font-weight: bold; } /* Style untuk footer tabel */
                    .text-center { text-align: center; }
                    .text-success { color: green; } .text-danger { color: red; } .text-warning { color: orange; }
                    </style></head><body>';

    $html_output .= '<table class="header-table"><tr>';
    $html_output .= '<td class="logo-left">' . $logo_kiri_html . '</td>';
    $html_output .= '<td class="title"><h1>Rekapitulasi Kehadiran</h1></td>';
    $html_output .= '<td class="logo-right">' . $logo_kanan_html . '</td>';
    $html_output .= '</tr></table><hr>';
    $html_output .= "<p>Periode: <strong>$periode_aktif_nama</strong><br>Kelompok: <strong>" . htmlspecialchars(ucwords($kelompok)) . "</strong><br>Kelas: <strong>" . htmlspecialchars(ucwords($kelas)) . "</strong><br>Dikeluarkan Oleh: <strong>" . htmlspecialchars(ucwords($nama_admin)) . "</strong></p>";

    $is_first_page = true;

    // Loop berdasarkan checkbox yang dipilih
    foreach ($tipe_laporan as $tipe) {
        if (!$is_first_page) {
            $html_output .= '<pagebreak />';
        }
        $is_first_page = false;

        if ($tipe === 'rekap_total') {
            $html_output .= '<h2 style="text-align:center;">Rekapitulasi Total</h2>';

            // ========================================================
            // PERUBAHAN LOGIKA QUERY 'rekap_total' (PDF)
            // ========================================================
            $sql = "SELECT 
                        p.nama_lengkap, 
                        COUNT(rp.id) AS total_jadwal,
                        SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                        SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                        SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                        SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                        SUM(CASE WHEN rp.status_kehadiran IS NULL OR rp.status_kehadiran = '' THEN 1 ELSE 0 END) as kosong,
                        
                        -- PERBAIKAN PERSENTASE: Denominator diubah ke COUNT(rp.status_kehadiran)
                        IF(
                            COUNT(rp.status_kehadiran) > 0, 
                            (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.status_kehadiran)) * 100, 
                            0
                        ) as persentase
                    FROM 
                        rekap_presensi rp
                    JOIN 
                        jadwal_presensi jp ON rp.jadwal_id = jp.id
                    JOIN 
                        peserta p ON rp.peserta_id = p.id
                    WHERE 
                        jp.periode_id = ? 
                        AND jp.kelompok = ? 
                        AND jp.kelas = ?
                    GROUP BY 
                        p.id, p.nama_lengkap
                    ORDER BY 
                        p.nama_lengkap ASC";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $html_output .= "<p class='text-danger'>Error preparing statement: " . $conn->error . "</p>";
                continue;
            }
            $stmt->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt->execute();
            $result = $stmt->get_result();

            // ========================================================
            // PERUBAHAN LOGIKA RENDER (PDF) UNTUK MENGHITUNG RATA-RATA
            // ========================================================
            $rekap_data_pdf = [];
            while ($row = $result->fetch_assoc()) {
                $rekap_data_pdf[] = $row;
            }

            $html_output .= '<table><thead><tr><th>No.</th><th>Nama Lengkap</th><th>Total Jadwal</th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Kosong</th><th>Persentase</th></tr></thead><tbody>';

            $total_persentase_kelas_pdf = 0;
            $no = 1;

            if (empty($rekap_data_pdf)) {
                $html_output .= "<tr><td colspan='9' class='text-center'>Tidak ada data rekap.</td></tr>";
            } else {
                foreach ($rekap_data_pdf as $row) {
                    $persentase = round($row['persentase'], 1);
                    $total_persentase_kelas_pdf += $persentase; // Akumulasi total
                    $color = $persentase < 60 ? 'text-danger' : ($persentase < 80 ? 'text-warning' : 'text-success');

                    $html_output .= "<tr>
                                        <td class='text-center'>" . $no++ . "</td>
                                        <td>" . htmlspecialchars($row['nama_lengkap']) . "</td>
                                        <td class='text-center'>" . ($row['total_jadwal'] ?? 0) . "</td>
                                        <td class='text-center'>" . ($row['hadir'] ?? 0) . "</td>
                                        <td class='text-center'>" . ($row['izin'] ?? 0) . "</td>
                                        <td class='text-center'>" . ($row['sakit'] ?? 0) . "</td>
                                        <td class='text-center'>" . ($row['alpa'] ?? 0) . "</td>
                                        <td class='text-center'>" . ($row['kosong'] ?? 0) . "</td>
                                        <td class='text-center {$color}' style='font-weight:bold;'>{$persentase}%</td>
                                     </tr>";
                }
            }

            // Hitung Rata-rata
            $jumlah_peserta_pdf = count($rekap_data_pdf);
            $rata_rata_pdf = ($jumlah_peserta_pdf > 0) ? ($total_persentase_kelas_pdf / $jumlah_peserta_pdf) : 0;

            // TAMBAHAN: Tampilkan Footer Rata-rata
            $html_output .= '</tbody><tfoot><tr>';
            $html_output .= '<td colspan="8" class="text-center">Rata-rata Kehadiran Kelas</td>';
            $html_output .= '<td class="text-center" style="font-weight:bold;">' . number_format($rata_rata_pdf, 2) . '%</td>';
            $html_output .= '</tr></tfoot></table>';
            // ========================================================
            // AKHIR PERUBAHAN RENDER (PDF)
            // ========================================================

        } elseif ($tipe === 'rinci_tanggal') {
            // --- Query 'rinci_tanggal' (Tidak berubah) ---
            $html_output .= '<h2 style="text-align:center;">Rincian per Tanggal</h2>';
            $tanggal_jadwal = [];
            $sql_tanggal = "SELECT DISTINCT tanggal FROM jadwal_presensi WHERE periode_id = ? AND kelompok = ? AND kelas = ? ORDER BY tanggal ASC";
            $stmt_tanggal = $conn->prepare($sql_tanggal);
            $stmt_tanggal->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt_tanggal->execute();
            $result_tanggal = $stmt_tanggal->get_result();
            while ($row = $result_tanggal->fetch_assoc()) {
                $tanggal_jadwal[] = $row['tanggal'];
            }

            $detail_kehadiran = [];
            $sql_detail = "SELECT p.nama_lengkap, jp.tanggal, rp.status_kehadiran FROM rekap_presensi rp JOIN peserta p ON rp.peserta_id = p.id JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? ORDER BY p.nama_lengkap, jp.tanggal ASC";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            while ($row = $result_detail->fetch_assoc()) {
                $detail_kehadiran[$row['nama_lengkap']][$row['tanggal']] = $row['status_kehadiran'];
            }

            $html_output .= '<table style="font-size: 8pt;"><thead><tr><th>No.</th><th style="min-width: 150px;">Nama Peserta</th>';
            foreach ($tanggal_jadwal as $tanggal) {
                $html_output .= "<th>" . date('d/m', strtotime($tanggal)) . "</th>";
            }
            $html_output .= '</tr></thead><tbody>';
            $no = 1;
            foreach ($detail_kehadiran as $nama => $kehadiran) {
                $html_output .= "<tr><td class='text-center'>" . $no++ . "</td><td>" . htmlspecialchars($nama) . "</td>";
                foreach ($tanggal_jadwal as $tanggal) {
                    $status = $kehadiran[$tanggal] ?? '-';
                    $html_output .= "<td class='text-center'>" . substr(htmlspecialchars($status), 0, 1) . "</td>";
                }
                $html_output .= "</tr>";
            }
            $html_output .= '</tbody></table>';
        } elseif ($tipe === 'rinci_siswa') {
            // --- Query 'rinci_siswa' (Tidak berubah, format per siswa) ---
            $html_output .= '<h2 style="text-align:center;">Rincian per Siswa</h2>';

            $sql_rinci_siswa = "SELECT p.nama_lengkap, jp.tanggal, jp.jam_mulai, rp.status_kehadiran 
                                FROM rekap_presensi rp
                                JOIN peserta p ON rp.peserta_id = p.id
                                JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                                WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
                                ORDER BY p.nama_lengkap, jp.tanggal, jp.jam_mulai";
            $stmt = $conn->prepare($sql_rinci_siswa);
            $stmt->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt->execute();
            $result = $stmt->get_result();

            $data_per_siswa = [];
            while ($row = $result->fetch_assoc()) {
                $data_per_siswa[$row['nama_lengkap']][] = $row;
            }

            if (empty($data_per_siswa)) {
                $html_output .= "<p>Tidak ada data rincian siswa untuk filter ini.</p>";
            } else {
                foreach ($data_per_siswa as $nama_siswa => $records) {
                    $html_output .= "<h3 style='margin-top: 20px; border-bottom: 1px solid #CCC; padding-bottom: 5px; font-size: 12pt;'>" . htmlspecialchars($nama_siswa) . "</h3>";
                    $html_output .= '<table style="font-size: 9pt;"><thead><tr>
                                        <th style="width: 25%;">Tanggal</th>
                                        <th style="width: 25%;" class="text-center">Jam Mulai</th>
                                        <th style="width: 50%;" class="text-center">Status Kehadiran</th>
                                    </tr></thead><tbody>';
                    foreach ($records as $row) {
                        $html_output .= "<tr>
                                            <td>" . formatTanggalIndo($row['tanggal']) . "</td>
                                            <td class='text-center'>" . date('H:i', strtotime($row['jam_mulai'])) . "</td>
                                            <td class='text-center'>" . htmlspecialchars($row['status_kehadiran'] ?? 'Kosong') . "</td>
                                        </tr>";
                    }
                    $html_output .= '</tbody></table>';
                }
            }
        }
    }

    $html_output .= '</body></html>';
    $mpdf->WriteHTML($html_output);

    // Set nama file dinamis berdasarkan tipe laporan
    $nama_file_tipe = count($tipe_laporan) > 1 ? 'Rekap_Kehadiran' : ucwords($tipe_laporan[0]);
    $nama_file_suffix = '_' . ucwords($kelompok) . '_' . ucwords($kelas);
    $mpdf->Output(date('Ymd') . '_' . $nama_file_tipe . $nama_file_suffix . '.pdf', 'D');
} else {
    // --- LOGIKA UNTUK CSV (HANYA MENGAMBIL TIPE LAPORAN PERTAMA) ---
    $tipe_laporan_csv = $tipe_laporan[0];

    // Set nama file dinamis
    $nama_file_prefix = "Rekap_Kehadiran_";
    $nama_file_suffix = "_{$kelompok}_{$kelas}_" . date('Y-m-d');
    $nama_file_tipe_csv = $tipe_laporan_csv;

    switch ($tipe_laporan_csv) {
        case 'rekap_total':
            // ========================================================
            // PERUBAHAN LOGIKA QUERY 'rekap_total' (CSV)
            // ========================================================
            $sql = "SELECT 
                        p.nama_lengkap, 
                        COUNT(rp.id) AS total_jadwal,
                        SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                        SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                        SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                        SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                        SUM(CASE WHEN rp.status_kehadiran IS NULL OR rp.status_kehadiran = '' THEN 1 ELSE 0 END) as kosong,
                        
                        -- PERBAIKAN PERSENTASE: Denominator diubah ke COUNT(rp.status_kehadiran)
                        IF(
                            COUNT(rp.status_kehadiran) > 0, 
                            (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.status_kehadiran)) * 100, 
                            0
                        ) as persentase
                    FROM 
                        rekap_presensi rp
                    JOIN 
                        jadwal_presensi jp ON rp.jadwal_id = jp.id
                    JOIN 
                        peserta p ON rp.peserta_id = p.id
                    WHERE 
                        jp.periode_id = ? 
                        AND jp.kelompok = ? 
                        AND jp.kelas = ?
                    GROUP BY 
                        p.id, p.nama_lengkap
                    ORDER BY 
                        p.nama_lengkap ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt->execute();
            $result = $stmt->get_result();

            // ========================================================
            // PERUBAHAN LOGIKA RENDER (CSV) UNTUK MENGHITUNG RATA-RATA
            // ========================================================
            $rekap_data_csv = [];
            while ($row = $result->fetch_assoc()) {
                $rekap_data_csv[] = $row;
            }

            $headers = ['No.', 'Nama Lengkap', 'Total Jadwal', 'Hadir', 'Izin', 'Sakit', 'Alpa', 'Kosong', 'Persentase (%)'];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nama_file_tipe_csv . $nama_file_suffix . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $total_persentase_kelas_csv = 0;
            $no = 1;

            if (!empty($rekap_data_csv)) {
                foreach ($rekap_data_csv as $row) {
                    $persentase_csv = round($row['persentase'], 1);
                    $total_persentase_kelas_csv += $persentase_csv; // Akumulasi total

                    fputcsv($output, [
                        $no++,
                        $row['nama_lengkap'],
                        $row['total_jadwal'],
                        $row['hadir'],
                        $row['izin'],
                        $row['sakit'],
                        $row['alpa'],
                        $row['kosong'],
                        $persentase_csv
                    ]);
                }
            }

            // Hitung Rata-rata
            $jumlah_peserta_csv = count($rekap_data_csv);
            $rata_rata_csv = ($jumlah_peserta_csv > 0) ? ($total_persentase_kelas_csv / $jumlah_peserta_csv) : 0;

            // TAMBAHAN: Tulis baris footer rata-rata
            // (Membuat 7 kolom kosong agar rata-rata sejajar dengan kolom 'Persentase')
            fputcsv($output, []); // Baris kosong
            fputcsv($output, ['', '', '', '', '', '', '', 'Rata-rata Kehadiran Kelas', number_format($rata_rata_csv, 2) . '%']);

            fclose($output);
            break;

        case 'rinci_tanggal':
            // --- Query 'rinci_tanggal' (Tidak berubah) ---
            $nama_file_tipe_csv = 'Rinci_Tanggal';
            $tanggal_jadwal = [];
            $sql_tanggal = "SELECT DISTINCT tanggal FROM jadwal_presensi WHERE periode_id = ? AND kelompok = ? AND kelas = ? ORDER BY tanggal ASC";
            $stmt_tanggal = $conn->prepare($sql_tanggal);
            $stmt_tanggal->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt_tanggal->execute();
            $result_tanggal = $stmt_tanggal->get_result();
            while ($row = $result_tanggal->fetch_assoc()) {
                $tanggal_jadwal[] = $row['tanggal'];
            }
            $detail_kehadiran = [];
            $sql_detail = "SELECT p.nama_lengkap, jp.tanggal, rp.status_kehadiran FROM rekap_presensi rp JOIN peserta p ON rp.peserta_id = p.id JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? ORDER BY p.nama_lengkap, jp.tanggal ASC";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            while ($row = $result_detail->fetch_assoc()) {
                $detail_kehadiran[$row['nama_lengkap']][$row['tanggal']] = $row['status_kehadiran'];
            }
            $headers = ['No.', 'Nama Peserta'];
            foreach ($tanggal_jadwal as $tanggal) {
                $headers[] = date('d/m', strtotime($tanggal));
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nama_file_tipe_csv . $nama_file_suffix . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            $no = 1;
            foreach ($detail_kehadiran as $nama => $kehadiran) {
                $baris = [$no++, $nama];
                foreach ($tanggal_jadwal as $tanggal) {
                    $status = $kehadiran[$tanggal] ?? '-';
                    $baris[] = substr($status, 0, 1);
                }
                fputcsv($output, $baris);
            }
            fclose($output);
            break;

        case 'rinci_siswa':
            // --- Query 'rinci_siswa' (Tidak berubah) ---
            $nama_file_tipe_csv = 'Rinci_Siswa';
            $sql_rinci_siswa = "SELECT p.nama_lengkap, jp.tanggal, jp.jam_mulai, rp.status_kehadiran FROM rekap_presensi rp JOIN peserta p ON rp.peserta_id = p.id JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? ORDER BY p.nama_lengkap, jp.tanggal, jp.jam_mulai";
            $stmt = $conn->prepare($sql_rinci_siswa);
            $stmt->bind_param("iss", $periode_id, $kelompok, $kelas);
            $stmt->execute();
            $result = $stmt->get_result();
            $headers = ['Nama Siswa', 'Tanggal', 'Jam Mulai', 'Status Kehadiran'];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nama_file_tipe_csv . $nama_file_suffix . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [$row['nama_lengkap'], date('d/m/Y', strtotime($row['tanggal'])), date('H:i', strtotime($row['jam_mulai'])), $row['status_kehadiran'] ?? 'Kosong']);
            }
            fclose($output);
            break;
    }
}

$conn->close();
exit();
