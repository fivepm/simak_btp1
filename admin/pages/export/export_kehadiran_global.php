<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ===================================================================
// BAGIAN 1: LOGIKA HANDLER (Jika ini adalah request POST)
// ===================================================================

// DEFINISI OPSI GLOBAL (Dipindahkan ke atas agar bisa dipakai di Bagian 1 & 2)
$kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
$kelompok_opts = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];

// Logika ini hanya berjalan jika form di-submit (POST)
if (isset($_POST['action']) && $_POST['action'] === 'export_terfilter') {

    // --- Mulai Logika dari export_global_handler.php ---

    // Header CORS (fetch) DIBUANG karena kita pakai submit form standar

    // Diasumsikan file koneksi.php sudah di-include
    include '../../../config/config.php';

    // Ambil data sesi admin
    $admin_role = $_SESSION['user_role'] ?? 'admin';
    $admin_level = $_SESSION['user_tingkat'] ?? 'desa';
    $admin_kelompok_sesi = $_SESSION['user_kelompok'] ?? null;
    $nama_admin = htmlspecialchars($_SESSION['user_nama']);

    // Sertakan mPDF autoload
    require_once __DIR__ . '/../../../vendor/autoload.php';

    // Fungsi helper untuk format tanggal
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
    $tipe_laporan = $_POST['tipe_laporan'] ?? []; // Ini adalah ARRAY

    $periode_id = (int)($_POST['periode_id'] ?? null);
    $kelompok = $_POST['kelompok'] ?? null;
    $kelas = $_POST['kelas'] ?? []; // DIUBAH MENJADI ARRAY

    // Jika admin bukan desa, paksa filter kelompok sesuai sesi mereka
    if ($admin_level !== 'desa') {
        $kelompok = $admin_kelompok_sesi;
    }

    // Jika datanya tidak lengkap, hentikan
    // PERUBAHAN VALIDASI: 'kelas' sekarang dicek jika 'empty'
    if ($action !== 'export_terfilter' || !$periode_id || empty($tipe_laporan) || empty($kelas)) {
        die("Akses tidak sah atau data tidak lengkap (Periode, Tipe Laporan, dan minimal 1 Kelas wajib diisi).");
    }

    // ===================================================================
    // MEMBUAT FILTER SQL DINAMIS
    // ===================================================================
    $where_clauses = [];
    $params = [];
    $types = "";

    // 1. Filter Periode (Wajib)
    $where_clauses[] = "jp.periode_id = ?";
    $params[] = $periode_id;
    $types .= "i";

    // 2. Filter Kelompok (Dinamis)
    if (!empty($kelompok) && $kelompok !== 'semua') {
        $where_clauses[] = "jp.kelompok = ?";
        $params[] = $kelompok;
        $types .= "s";
    }

    // 3. Filter Kelas (Dinamis - DIUBAH UNTUK ARRAY CHECKBOX)
    if (!empty($kelas)) {
        // Cek jika jumlah kelas yang dipilih < total kelas yang ada
        // Jika sama, berarti "Semua Kelas" dan tidak perlu filter
        if (count($kelas) < count($kelas_opts)) {
            // Buat placeholder IN (...)
            $kelas_placeholders = implode(',', array_fill(0, count($kelas), '?'));
            $where_clauses[] = "jp.kelas IN ($kelas_placeholders)";
            // Tambahkan setiap kelas ke parameter bind
            foreach ($kelas as $k) {
                $params[] = $k;
                $types .= "s";
            }
        }
        // Jika count($kelas) == count($kelas_opts), maka "Semua Kelas" dipilih
        // dan kita tidak menambahkan klausa WHERE, jadi semua kelas akan terambil.
    }
    // ===================================================================

    // Gabungkan semua filter
    $where_sql = implode(" AND ", $where_clauses);


    $nama_file_prefix = "Rekap_Global_Kehadiran_";
    $nama_file_suffix = "_" . date('Y-m-d');


    if ($format === 'pdf') {
        // --- LOGIKA UNTUK PDF (BISA MENGGABUNGKAN BEBERAPA LAPORAN) ---
        $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']); // Default Landscape

        // --- 1. FOOTER ---
        $print_date = date('d/m/Y H:i:s');
        $footerText = 'Rekap Kehadiran SIMAK Banguntapan 1 | Dicetak pada: {DATE j-m-Y H:i} | Halaman {PAGENO} dari {nb}';
        $mpdf->SetFooter($footerText);
        $mpdf->SetDisplayMode('fullpage');

        // --- 2. WATERMARK FOTO (dengan Fallback Teks) ---
        $watermark_path = __DIR__ . '/../../../assets/images/logo_watermark.png';
        if (file_exists($watermark_path)) {
            $mpdf->SetWatermarkImage($watermark_path, 0.1, 'auto', 'P');
            $mpdf->showWatermarkImage = true;
        } else {
            $mpdf->SetWatermarkText('SIMAK BT1');
            $mpdf->showWatermarkText = true;
            $mpdf->watermarkTextAlpha = 0.05;
        }

        $html_output = '<html><head><style>
                        body { font-family: sans-serif; font-size: 10pt; }
                        h1 { color: #333; font-size: 18pt; } h2 { font-size: 14pt; }
                        table { width:100%; border-collapse: collapse; margin-bottom: 20px; }
                        th, td { border: 1px solid #AAA; padding: 5px; text-align: left; }
                        thead th { background-color:#f2f2f2; text-align: center; }
                        .text-center { text-align: center; }
                        .text-success { color: green; } .text-danger { color: red; } .text-warning { color: orange; }
                    </style></head><body>';

        $html_output .= '<h1>Rekapitulasi Kehadiran Global</h1>';
        // PERUBAHAN TAMPILAN FILTER KELAS (jika array)
        $kelas_display = (count($kelas) < count($kelas_opts)) ? implode(', ', $kelas) : 'Semua';
        $html_output .= "<p>Periode ID: <strong>$periode_id</strong> | Kelompok: <strong>" . htmlspecialchars(ucfirst($kelompok)) . "</strong> | Kelas: <strong>" . htmlspecialchars(ucfirst($kelas_display)) . "</strong></p>";

        $is_first_page = true;
        foreach ($tipe_laporan as $tipe) {
            if (!$is_first_page) {
                $html_output .= '<pagebreak />';
            }
            $is_first_page = false;

            if ($tipe === 'rekap_total') {
                $html_output .= '<h2>Rekapitulasi Total</h2>';
                $sql = "SELECT 
                            p.nama_lengkap, 
                            p.kelompok, p.kelas, -- Tambahkan info kelompok/kelas
                            COUNT(rp.id) AS total_jadwal,
                            SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                            SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                            SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                            SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                            SUM(CASE WHEN rp.status_kehadiran IS NULL OR rp.status_kehadiran = '' THEN 1 ELSE 0 END) as kosong,
                            IF(COUNT(rp.id) > 0, (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.id)) * 100, 0) as persentase
                        FROM 
                            rekap_presensi rp
                        JOIN 
                            jadwal_presensi jp ON rp.jadwal_id = jp.id
                        JOIN 
                            peserta p ON rp.peserta_id = p.id
                        WHERE 
                            $where_sql -- Gunakan filter dinamis
                        GROUP BY 
                            p.id, p.nama_lengkap, p.kelompok, p.kelas
                        ORDER BY 
                            p.kelompok, p.kelas, p.nama_lengkap ASC";

                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $html_output .= '<table><thead><tr><th>No.</th><th>Nama Lengkap</th><th>Kelompok</th><th>Kelas</th><th>Total</th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Kosong</th><th>Persentase</th></tr></thead><tbody>';
                    $no = 1;
                    while ($row = $result->fetch_assoc()) {
                        $persentase = round($row['persentase'], 1);
                        $color = $persentase < 60 ? 'text-danger' : ($persentase < 80 ? 'text-warning' : 'text-success');
                        $html_output .= "<tr>
                                            <td class='text-center'>" . $no++ . "</td>
                                            <td>" . htmlspecialchars($row['nama_lengkap']) . "</td>
                                            <td class='text-center'>" . htmlspecialchars(ucfirst($row['kelompok'])) . "</td>
                                            <td class='text-center'>" . htmlspecialchars(ucfirst($row['kelas'])) . "</td>
                                            <td class='text-center'>" . ($row['total_jadwal'] ?? 0) . "</td>
                                            <td class='text-center'>" . ($row['hadir'] ?? 0) . "</td>
                                            <td class='text-center'>" . ($row['izin'] ?? 0) . "</td>
                                            <td class='text-center'>" . ($row['sakit'] ?? 0) . "</td>
                                            <td class='text-center'>" . ($row['alpa'] ?? 0) . "</td>
                                            <td class='text-center'>" . ($row['kosong'] ?? 0) . "</td>
                                            <td class='text-center {$color}' style='font-weight:bold;'>{$persentase}%</td>
                                        </tr>";
                    }
                    $html_output .= '</tbody></table>';
                } else {
                    $html_output .= "<p class='text-danger'>Error preparing statement: " . $conn->error . "</p>";
                }
            } elseif ($tipe === 'rinci_tanggal') {
                $html_output .= '<h2>Rincian per Tanggal</h2>';
                if ($kelompok === 'semua' || $kelas === 'semua') {
                    $html_output .= "<p class='text-warning'>Laporan 'Rincian per Tanggal' tidak dapat dibuat untuk filter 'Semua Kelompok' atau 'Semua Kelas' karena data terlalu besar. Harap pilih satu kelompok dan satu kelas spesifik.</p>";
                } else {
                    $tanggal_jadwal = [];
                    $sql_tanggal = "SELECT DISTINCT tanggal FROM jadwal_presensi jp WHERE $where_sql ORDER BY tanggal ASC";
                    $stmt_tanggal = $conn->prepare($sql_tanggal);
                    $stmt_tanggal->bind_param($types, ...$params);
                    $stmt_tanggal->execute();
                    $result_tanggal = $stmt_tanggal->get_result();
                    while ($row = $result_tanggal->fetch_assoc()) $tanggal_jadwal[] = $row['tanggal'];

                    $detail_kehadiran = [];
                    $sql_detail = "SELECT p.nama_lengkap, jp.tanggal, rp.status_kehadiran 
                                FROM rekap_presensi rp 
                                JOIN peserta p ON rp.peserta_id = p.id 
                                JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id 
                                WHERE $where_sql 
                                ORDER BY p.nama_lengkap, jp.tanggal ASC";
                    $stmt_detail = $conn->prepare($sql_detail);
                    $stmt_detail->bind_param($types, ...$params);
                    $stmt_detail->execute();
                    $result_detail = $stmt_detail->get_result();
                    while ($row = $result_detail->fetch_assoc()) $detail_kehadiran[$row['nama_lengkap']][$row['tanggal']] = $row['status_kehadiran'];

                    $html_output .= '<table style="font-size: 8pt;"><thead><tr><th>No.</th><th style="min-width: 150px;">Nama Peserta</th>';
                    foreach ($tanggal_jadwal as $tanggal) $html_output .= "<th>" . date('d/m', strtotime($tanggal)) . "</th>";
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
                }
            } elseif ($tipe === 'rinci_siswa') {
                $html_output .= '<h2>Rincian per Siswa</h2>';
                $sql_rinci_siswa = "SELECT p.nama_lengkap, p.kelompok, p.kelas, jp.tanggal, jp.jam_mulai, rp.status_kehadiran 
                                    FROM rekap_presensi rp
                                    JOIN peserta p ON rp.peserta_id = p.id
                                    JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                                    WHERE $where_sql
                                    ORDER BY p.kelompok, p.kelas, p.nama_lengkap, jp.tanggal, jp.jam_mulai";
                $stmt = $conn->prepare($sql_rinci_siswa);
                $stmt->bind_param($types, ...$params);
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
                        $info_kelompok = htmlspecialchars(ucfirst($records[0]['kelompok']));
                        $info_kelas = htmlspecialchars(ucfirst($records[0]['kelas']));

                        $html_output .= "<h3 style='margin-top: 20px; border-bottom: 1px solid #CCC; padding-bottom: 5px; font-size: 12pt;'>" . htmlspecialchars($nama_siswa) . " <span style='font-size: 10pt; color: #555;'>($info_kelompok - $info_kelas)</span></h3>";
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
        $mpdf->Output($nama_file_prefix . $nama_file_suffix . '.pdf', 'D');
    } else {
        // --- LOGIKA UNTUK CSV (HANYA MENGAMBIL TIPE LAPORAN PERTAMA) ---
        $tipe_laporan_csv = $tipe_laporan[0];

        switch ($tipe_laporan_csv) {
            case 'rekap_total':
                $sql = "SELECT 
                            p.nama_lengkap, p.kelompok, p.kelas,
                            COUNT(rp.id) AS total_jadwal,
                            SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                            SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                            SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                            SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                            SUM(CASE WHEN rp.status_kehadiran IS NULL OR rp.status_kehadiran = '' THEN 1 ELSE 0 END) as kosong,
                            IF(COUNT(rp.id) > 0, (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.id)) * 100, 0) as persentase
                        FROM 
                            rekap_presensi rp
                        JOIN 
                            jadwal_presensi jp ON rp.jadwal_id = jp.id
                        JOIN 
                            peserta p ON rp.peserta_id = p.id
                        WHERE 
                            $where_sql -- Gunakan filter dinamis
                        GROUP BY 
                            p.id, p.nama_lengkap, p.kelompok, p.kelas
                        ORDER BY 
                            p.kelompok, p.kelas, p.nama_lengkap ASC";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

                $headers = ['No.', 'Nama Lengkap', 'Kelompok', 'Kelas', 'Total Jadwal', 'Hadir', 'Izin', 'Sakit', 'Alpa', 'Kosong', 'Persentase (%)'];
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nama_file_prefix . 'Total' . $nama_file_suffix . '.csv"');
                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);
                $no = 1;
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [
                        $no++,
                        $row['nama_lengkap'],
                        ucfirst($row['kelompok']),
                        ucfirst($row['kelas']),
                        $row['total_jadwal'],
                        $row['hadir'],
                        $row['izin'],
                        $row['sakit'],
                        $row['alpa'],
                        $row['kosong'],
                        round($row['persentase'], 1)
                    ]);
                }
                fclose($output);
                break;

            case 'rinci_tanggal':
                if ($kelompok === 'semua' || $kelas === 'semua') {
                    die("Laporan 'Rincian per Tanggal' tidak dapat dibuat untuk filter 'Semua Kelompok' atau 'Semua Kelas' dalam format CSV karena data terlalu besar. Harap pilih satu kelompok dan satu kelas spesifik.");
                }
                $tanggal_jadwal = [];
                $sql_tanggal = "SELECT DISTINCT tanggal FROM jadwal_presensi jp WHERE $where_sql ORDER BY tanggal ASC";
                $stmt_tanggal = $conn->prepare($sql_tanggal);
                $stmt_tanggal->bind_param($types, ...$params);
                $stmt_tanggal->execute();
                $result_tanggal = $stmt_tanggal->get_result();
                while ($row = $result_tanggal->fetch_assoc()) $tanggal_jadwal[] = $row['tanggal'];

                $detail_kehadiran = [];
                $sql_detail = "SELECT p.nama_lengkap, jp.tanggal, rp.status_kehadiran 
                            FROM rekap_presensi rp 
                            JOIN peserta p ON rp.peserta_id = p.id 
                            JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id 
                            WHERE $where_sql 
                            ORDER BY p.nama_lengkap, jp.tanggal ASC";
                $stmt_detail = $conn->prepare($sql_detail);
                $stmt_detail->bind_param($types, ...$params);
                $stmt_detail->execute();
                $result_detail = $stmt_detail->get_result();
                while ($row = $result_detail->fetch_assoc()) $detail_kehadiran[$row['nama_lengkap']][$row['tanggal']] = $row['status_kehadiran'];

                $headers = ['No.', 'Nama Peserta'];
                foreach ($tanggal_jadwal as $tanggal) $headers[] = date('d/m', strtotime($tanggal));

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nama_file_prefix . 'Rinci_Tanggal' . $nama_file_suffix . '.csv"');
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
                $sql_rinci_siswa = "SELECT p.nama_lengkap, p.kelompok, p.kelas, jp.tanggal, jp.jam_mulai, rp.status_kehadiran 
                                    FROM rekap_presensi rp
                                    JOIN peserta p ON rp.peserta_id = p.id
                                    JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                                    WHERE $where_sql
                                    ORDER BY p.kelompok, p.kelas, p.nama_lengkap, jp.tanggal, jp.jam_mulai";
                $stmt = $conn->prepare($sql_rinci_siswa);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

                $headers = ['Nama Siswa', 'Kelompok', 'Kelas', 'Tanggal', 'Jam Mulai', 'Status Kehadiran'];
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nama_file_prefix . 'Rinci_Siswa' . $nama_file_suffix . '.csv"');
                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [
                        $row['nama_lengkap'],
                        ucfirst($row['kelompok']),
                        ucfirst($row['kelas']),
                        date('d/m/Y', strtotime($row['tanggal'])),
                        date('H:i', strtotime($row['jam_mulai'])),
                        $row['status_kehadiran'] ?? 'Kosong'
                    ]);
                }
                fclose($output);
                break;
        }
    }

    $conn->close();
    exit(); // SANGAT PENTING: Hentikan eksekusi agar HTML tidak ter-render
}

// ===================================================================
// BAGIAN 2: LOGIKA UI (Jika ini adalah request GET)
// ===================================================================

// Pastikan $conn ada (jika belum di-include oleh handler di atas)
if (!isset($conn)) {
    // Asumsi path config-nya
    include __DIR__ . '/../../../config/config.php';
}

// Cek jika admin desa
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';

// Ambil daftar periode aktif
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status != 'Arsip' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}
// Opsi 'kelas_opts' and 'kelompok_opts' sudah dipindah ke atas (Bagian 1)

?>

<!-- =================================================================== -->
<!-- BAGIAN 3: TAMPILAN HTML (SESUAI export_siswa.php)                 -->
<!-- =================================================================== -->
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ekspor Global Kehadiran</title>
    <!-- Memuat Tailwind dan Font Awesome, seperti export_siswa.php -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="bg-gray-100">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="bg-white p-6 rounded-2xl shadow-lg max-w-2xl mx-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Ekspor Global Rekapitulasi Kehadiran</h2>

            <!-- PENTING: 'action' menunjuk ke halaman ini sendiri, disesuaikan dengan router index.php Anda -->
            <!-- PERUBAHAN NAMA FILE DI 'action' -->
            <form id="form-ekspor-filter" method="POST" action="?page=export/export_kehadiran_global">

                <!-- Input tersembunyi untuk memberi tahu handler apa yang harus dilakukan -->
                <input type="hidden" name="action" value="export_terfilter">

                <div class="space-y-6">
                    <!-- 1. Filter Periode -->
                    <div>
                        <label for="periode_id" class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                        <select id="periode_id" name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="">-- Pilih Periode --</option>
                            <?php foreach ($periode_list as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama_periode']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Filter Kelompok (Hanya untuk Admin Desa) -->
                    <?php if ($admin_tingkat === 'desa'): ?>
                        <div>
                            <label for="kelompok" class="block text-sm font-medium text-gray-700 mb-1">Kelompok</label>
                            <select id="kelompok" name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="semua">Semua Kelompok</option>
                                <?php foreach ($kelompok_opts as $k): ?>
                                    <option value="<?php echo $k; ?>"><?php echo ucfirst($k); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <!-- Admin tingkat kelompok tidak perlu memilih, kirim kelompok mereka secara otomatis -->
                        <input type="hidden" name="kelompok" value="<?php echo htmlspecialchars($_SESSION['user_kelompok']); ?>">
                    <?php endif; ?>

                    <!-- 3. Filter Kelas (DIUBAH JADI CHECKBOX) -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Pilih Kelas</h3>
                        <div class="border p-4 rounded-md space-y-2">
                            <label class="flex items-center space-x-2 font-semibold">
                                <input type="checkbox" id="pilih_semua_kelas" class="rounded">
                                <span>Pilih Semua Kelas</span>
                            </label>
                            <hr>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <?php foreach ($kelas_opts as $k): ?>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" name="kelas[]" value="<?php echo $k; ?>" class="kelas-checkbox rounded">
                                        <span><?php echo htmlspecialchars(ucfirst($k)); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Tipe Laporan -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Tipe Laporan</h3>
                        <div class="space-y-3">
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50">
                                <input type="checkbox" name="tipe_laporan[]" value="rekap_total" class="h-4 w-4 text-cyan-600" checked>
                                <span class="ml-3 text-sm font-medium">Rekapitulasi Kehadiran (Total)</span>
                            </label>
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50">
                                <input type="checkbox" name="tipe_laporan[]" value="rinci_tanggal" class="h-4 w-4 text-cyan-600">
                                <span class="ml-3 text-sm font-medium">Rincian per Tanggal (Tampilan Grid)</span>
                            </label>
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50">
                                <input type="checkbox" name="tipe_laporan[]" value="rinci_siswa" class="h-4 w-4 text-cyan-600">
                                <span class="ml-3 text-sm font-medium">Rincian per Siswa (Tampilan Daftar)</span>
                            </label>
                        </div>
                    </div>

                    <!-- 5. Format File -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mt-6 mb-4">Format File</h3>
                        <div class="flex gap-4">
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="format" value="pdf" class="h-4 w-4 text-cyan-600" checked>
                                <span>PDF</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="format" value="csv" class="h-4 w-4 text-cyan-600">
                                <span>CSV (Excel)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi dan Loader (DISEDERHANAKAN) -->
                <div class="mt-8 pt-6 border-t flex justify-between items-center">
                    <!-- Tombol Submit Standar (Tanpa ID, Tanpa Loader) -->
                    <a href="../../?page=presensi/kehadiran" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-8 rounded-lg shadow-md transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali
                    </a>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition duration-300">
                        <i class="fa-solid fa-file-pdf mr-2"></i> Buat dan Unduh Laporan
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- BAGIAN 4: JAVASCRIPT (DIHAPUS SEMUA)                             -->
    <!-- =================================================================== -->
    <!-- Tidak ada JavaScript yang diperlukan untuk submit form standar -->
    <!-- PERUBAHAN: Menambahkan JS untuk 'Pilih Semua' -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logika untuk "Pilih Semua"
            const setupPilihSemua = (masterId, checkboxClass) => {
                const master = document.getElementById(masterId);
                if (master) {
                    master.addEventListener('change', function() {
                        document.querySelectorAll('.' + checkboxClass).forEach(cb => cb.checked = this.checked);
                    });
                }
            };

            // Terapkan ke checkbox kelas
            setupPilihSemua('pilih_semua_kelas', 'kelas-checkbox');
        });
    </script>

</body>

</html>
<?php $conn->close(); ?>