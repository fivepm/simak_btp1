<?php
// Diasumsikan file koneksi.php sudah di-include
include '../../../helpers/log_helper.php';
include '../../../config/config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Ambil data sesi admin
$admin_role = $_SESSION['user_role'] ?? 'admin';
$admin_level = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;
$nama_admin = htmlspecialchars($_SESSION['user_nama']);

// ===================================================================
// BAGIAN 1: PEMROSESAN FORM EKSPOR (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe_laporan_pilihan = $_POST['tipe_laporan'] ?? []; // Ini sekarang array
    $kelompok_pilihan = $_POST['kelompok'] ?? [];
    $kelas_pilihan = $_POST['kelas'] ?? [];
    $kolom_pilihan = $_POST['kolom'] ?? [];
    $format = $_POST['format'] ?? 'pdf';
    $tanggal_sekarang = date('Y-m-d');

    // Validasi
    if (empty($kelompok_pilihan) || empty($kelas_pilihan)) {
        die("Error: Anda harus memilih minimal satu kelompok dan satu kelas.");
    }
    if (empty($tipe_laporan_pilihan)) {
        die("Error: Anda harus memilih minimal satu tipe laporan.");
    }

    // Tentukan nama file berdasarkan pilihan kelompok
    $total_kelompok_db = $conn->query("SELECT COUNT(DISTINCT kelompok) as total FROM peserta WHERE kelompok IS NOT NULL AND kelompok != ''")->fetch_assoc()['total'];
    $nama_file_kelompok = '';
    if (count($kelompok_pilihan) >= $total_kelompok_db) {
        $nama_file_kelompok = 'banguntapan_1';
    } else {
        $nama_kelompok_bersih = array_map(fn($k) => preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $k)), $kelompok_pilihan);
        $nama_file_kelompok = implode('_', $nama_kelompok_bersih);
    }
    if (strlen($nama_file_kelompok) > 50) {
        $nama_file_kelompok = substr($nama_file_kelompok, 0, 50) . '_etc';
    }

    $nama_file_akhir = "{$tanggal_sekarang}_data_siswa_{$nama_file_kelompok}";

    // Siapkan query placeholders
    $kelompok_placeholders = implode(',', array_fill(0, count($kelompok_pilihan), '?'));
    $kelas_placeholders = implode(',', array_fill(0, count($kelas_pilihan), '?'));
    $bind_types_filter = str_repeat('s', count($kelompok_pilihan)) . str_repeat('s', count($kelas_pilihan));
    $bind_values_filter = array_merge($kelompok_pilihan, $kelas_pilihan);

    // === CCTV ===
    $str_tipe = ucwords(implode(', ', $tipe_laporan_pilihan));
    $str_kelas = ucwords(implode(', ', $kelas_pilihan));
    if (count($kelompok_pilihan) >= $total_kelompok_db) {
        $str_kelompok = 'Semua';
    } else {
        $str_kelompok = ucwords(implode(', ', $kelompok_pilihan));
    }
    $deskripsi_log = "Ekspor data siswa (*$str_tipe*) untuk Kelas [*$str_kelas*] dari Kelompok *$str_kelompok*. Format: " . strtoupper($format);
    writeLog('EXPORT', $deskripsi_log);

    // ==========================================================
    // LOGIKA UNTUK FORMAT CSV (Hanya bisa data mentah)
    // ==========================================================
    if ($format === 'csv') {
        if (!in_array('data_mentah', $tipe_laporan_pilihan) && in_array('ringkasan', $tipe_laporan_pilihan)) {
            // --- AWAL LOGIKA BARU UNTUK CSV RINGKASAN ---
            $nama_file_akhir = "laporan_ringkasan_siswa_{$nama_file_kelompok}_{$tanggal_sekarang}";

            // 1. Ambil data ringkasan dari database
            $peserta_per_kelas_rinci = [];
            $kelas_list_display = ['Paud', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];

            $sql_rincian = "SELECT kelas, kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' AND kelompok IN ($kelompok_placeholders) AND kelas IN ($kelas_placeholders) GROUP BY kelas, kelompok, jenis_kelamin";
            $stmt_rincian = $conn->prepare($sql_rincian);
            $stmt_rincian->bind_param($bind_types_filter, ...$bind_values_filter);
            $stmt_rincian->execute();
            $result_rincian = $stmt_rincian->get_result();
            if ($result_rincian) {
                while ($row = $result_rincian->fetch_assoc()) {
                    $peserta_per_kelas_rinci[strtolower($row['kelas'])][strtolower($row['kelompok'])][$row['jenis_kelamin']] = $row['jumlah'];
                }
            }

            // 2. Set header untuk file CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nama_file_akhir . '.csv"');
            $output = fopen('php://output', 'w');

            // 3. Buat Header CSV (Baris 1)
            $header1 = ['Kelas'];
            foreach ($kelompok_pilihan as $kelompok) {
                $header1[] = htmlspecialchars(ucfirst($kelompok));
                $header1[] = ''; // Kolom kosong untuk colspan
            }
            $header1[] = 'Total Kelas';
            fputcsv($output, $header1);

            // 4. Buat Header CSV (Baris 2)
            $header2 = ['']; // Kolom kosong di bawah 'Kelas'
            foreach ($kelompok_pilihan as $kelompok) {
                $header2[] = 'L';
                $header2[] = 'P';
            }
            $header2[] = ''; // Kolom kosong di bawah 'Total Kelas'
            fputcsv($output, $header2);

            // 5. Tulis data baris per kelas
            $grand_total_semua = 0;
            $grand_totals_kelompok = [];
            foreach ($kelas_list_display as $kelas) {
                $row_data = [htmlspecialchars($kelas)]; // Kolom pertama adalah nama kelas
                $total_per_kelas = 0;
                $kelas_key = strtolower($kelas);

                foreach ($kelompok_pilihan as $kelompok) {
                    $kelompok_key = strtolower($kelompok);
                    $jumlah_l = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Laki-laki'] ?? 0;
                    $jumlah_p = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Perempuan'] ?? 0;

                    $row_data[] = $jumlah_l; // Kolom L
                    $row_data[] = $jumlah_p; // Kolom P

                    $total_per_kelas += ($jumlah_l + $jumlah_p);

                    // Akumulasi total
                    $grand_totals_kelompok[$kelompok_key]['Laki-laki'] = ($grand_totals_kelompok[$kelompok_key]['Laki-laki'] ?? 0) + $jumlah_l;
                    $grand_totals_kelompok[$kelompok_key]['Perempuan'] = ($grand_totals_kelompok[$kelompok_key]['Perempuan'] ?? 0) + $jumlah_p;
                }

                $row_data[] = $total_per_kelas; // Kolom Total Kelas
                $grand_total_semua += $total_per_kelas;
                fputcsv($output, $row_data);
            }

            // 6. Tulis baris Total
            $row_total = ['TOTAL'];
            foreach ($kelompok_pilihan as $kelompok) {
                $kelompok_key = strtolower($kelompok);
                $row_total[] = $grand_totals_kelompok[$kelompok_key]['Laki-laki'] ?? 0;
                $row_total[] = $grand_totals_kelompok[$kelompok_key]['Perempuan'] ?? 0;
            }
            $row_total[] = $grand_total_semua;
            fputcsv($output, $row_total);

            fclose($output);
            exit();
            // --- AKHIR LOGIKA BARU UNTUK CSV RINGKASAN ---
        }

        // Default: Ekspor data mentah jika 'data_mentah' dipilih (atau default)
        $nama_file_akhir = "laporan_siswa_mentah_{$nama_file_kelompok}_{$tanggal_sekarang}";

        $kolom_yang_diizinkan = ['nama_lengkap', 'kelas', 'kelompok', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nomor_hp', 'nama_orang_tua', 'nomor_hp_orang_tua'];
        $kolom_aman = array_intersect($kolom_pilihan, $kolom_yang_diizinkan);
        if (empty($kolom_aman)) {
            die("Error: Kolom yang dipilih tidak valid untuk data mentah.");
        }

        $sql = "SELECT " . implode(', ', $kolom_aman) . " FROM peserta WHERE kelompok IN ($kelompok_placeholders) AND kelas IN ($kelas_placeholders) ORDER BY kelompok, FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama_lengkap";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($bind_types_filter, ...$bind_values_filter);
        $stmt->execute();
        $result = $stmt->get_result();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nama_file_akhir . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array_merge(['No.'], $kolom_aman));
        $nomor = 1;
        while ($row = $result->fetch_assoc()) {
            $data_baris = [];
            foreach ($kolom_aman as $kolom) {
                $data = $row[$kolom] ?? '';
                if ($kolom === 'tanggal_lahir') {
                    $data_baris[] = formatTanggalLahirIndonesia($data);
                } else {
                    $data_baris[] = $data;
                }
            }
            fputcsv($output, array_merge([$nomor++], $data_baris));
        }
        fclose($output);
        exit();
    }
    // ==========================================================
    // LOGIKA UNTUK FORMAT PDF (Bisa gabung)
    // ==========================================================
    elseif ($format === 'pdf') {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']);
        $mpdf->SetWatermarkImage('../../../assets/images/logo_kbm.png', 0.1, 'D', 'P'); // Path watermark
        $mpdf->showWatermarkImage = true;

        // 1. Definisikan isi footer
        // Format string dibagi oleh pipe '|': TeksKiri|TeksTengah|TeksKanan
        // {DATE j-m-Y H:i} = Tanggal & Waktu saat PDF dibuat
        // {PAGENO} = Nomor halaman saat ini
        // {nb} = Total jumlah halaman
        $footerText = 'Ekspor Data Siswa SIMAK Banguntapan 1 | Dicetak pada: {DATE j-m-Y H:i} | Halaman {PAGENO} dari {nb}';

        // 2. Terapkan footer ke PDF
        $mpdf->SetFooter($footerText);

        $logo_kiri_path = __DIR__ . '/../../../assets/images/logo_kbm.png'; // Contoh path
        $logo_kanan_path = __DIR__ . '/../../../assets/images/logo_simak.png'; // Contoh path

        // Cek apakah file logo ada
        $logo_kiri_html = file_exists($logo_kiri_path) ? '<img src="' . $logo_kiri_path . '" style="height: 50px; width: auto;">' : '';
        $logo_kanan_html = file_exists($logo_kanan_path) ? '<img src="' . $logo_kanan_path . '" style="height: 50px; width: auto;">' : '';

        $html_output = '<html><head><style>
                        body { font-family: sans-serif; }
                        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .header-table td { border: none; vertical-align: middle; padding: 0 10px; }
                        .header-table .logo-left { width: 15%; text-align: center; }
                        .header-table .title { width: 70%; text-align: center; }
                        .header-table .logo-right { width: 15%; text-align: center; }
                        .header-table h1 { color: #333; font-size: 16pt; margin: 0; padding: 0; border: none; } 
                        h1 { color: #333; }
                        table { width:100%; border-collapse: collapse; font-size: 8pt; }
                        th, td { border: 1px solid #AAA; padding: 5px; }
                        thead th { background-color:#FFFB00; }
                        tbody tr:nth-child(even) { background-color: #f9f9f9; }
                        .page-break { page-break-before: always; }
                    </style></head><body>';

        // $html_output .= '<h1>Laporan Data Siswa</h1><p>Kelompok: ' . htmlspecialchars($nama_file_kelompok) . '<br>Tanggal Ekspor: ' . date('d F Y') . '</p>';
        if (count($kelompok_pilihan) >= $total_kelompok_db) {
            $nama_header = 'Desa Banguntapan 1';
        } else {
            $nama_kelompok_bersih = array_map(fn($k) => preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $k)), $kelompok_pilihan);
            $nama_header = 'Kelompok: ' . ucwords(implode(', ', $nama_kelompok_bersih));
        }

        $html_output .= '<table class="header-table"><tr>';
        $html_output .= '<td class="logo-left">' . $logo_kiri_html . '</td>';
        $html_output .= '<td class="title"><h1>Data Siswa PJP Banguntapan 1</h1></td>';
        $html_output .= '<td class="logo-right">' . $logo_kanan_html . '</td>';
        $html_output .= '</tr></table><hr>';

        if ($admin_role == 'superadmin'):
            $html_output .=
                // '<h1 style="text-align:center;">Data Siswa PJP Banguntapan 1</h1>' .
                '<p>' . $nama_header .
                '<br>Tanggal Ekspor: ' . formatTanggalIndonesiaTanpaNol(date('d M Y')) .
                '<br>
                Dikeluarkan Oleh: ' . $nama_admin . ' - Super Admin</p>';
        elseif ($admin_role == 'admin' && $admin_level == 'desa'):
            $html_output .=
                // '<h1 style="text-align:center;">Data Siswa PJP Banguntapan 1</h1>' .
                '<p>' . $nama_header .
                '<br>Tanggal Ekspor: ' . formatTanggalIndonesiaTanpaNol(date('d M Y')) .
                '<br>
                Dikeluarkan Oleh: ' . $nama_admin . ' - Admin Desa</p>';
        elseif ($admin_role == 'admin' && $admin_level == 'kelompok'):
            $html_output .=
                // '<h1 style="text-align:center;">Data Siswa PJP Banguntapan 1</h1>' .
                '<p>' . $nama_header .
                '<br>Tanggal Ekspor: ' . formatTanggalIndonesiaTanpaNol(date('d M Y')) .
                '<br>
                Dikeluarkan Oleh: ' . $nama_admin . ' - Admin Kelompok ' . ucfirst($admin_kelompok) . '</p>';
        endif;

        $butuh_page_break = false;

        // --- 1. PROSES RINGKASAN (JIKA DIPILIH) ---
        if (in_array('ringkasan', $tipe_laporan_pilihan)) {
            $peserta_per_kelas_rinci = [];
            $kelas_list_display = ['Paud', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];

            $sql_rincian = "SELECT kelas, kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' AND kelompok IN ($kelompok_placeholders) AND kelas IN ($kelas_placeholders) AND status = 'Aktif' GROUP BY kelas, kelompok, jenis_kelamin";
            $stmt_rincian = $conn->prepare($sql_rincian);
            $stmt_rincian->bind_param($bind_types_filter, ...$bind_values_filter);
            $stmt_rincian->execute();
            $result_rincian = $stmt_rincian->get_result();
            if ($result_rincian) {
                while ($row = $result_rincian->fetch_assoc()) {
                    $peserta_per_kelas_rinci[strtolower($row['kelas'])][strtolower($row['kelompok'])][$row['jenis_kelamin']] = $row['jumlah'];
                }
            }

            $html_output .= '<h2 style="text-align:center;">Ringkasan Jumlah Siswa</h2>';
            $html_output .= '<table><thead><tr>';
            $html_output .= '<th rowspan="2" style="padding:5px;">Kelas</th>';
            foreach ($kelompok_pilihan as $kelompok) {
                $html_output .= '<th colspan="2" style="padding:5px; text-align:center;">' . htmlspecialchars(ucfirst($kelompok)) . '</th>';
            }
            $html_output .= '<th rowspan="2" style="padding:5px; text-align:center;">Total</th></tr>';
            $html_output .= '<tr>';
            foreach ($kelompok_pilihan as $kelompok) {
                $html_output .= '<th style="padding:5px; text-align:center;">L</th><th style="padding:5px; text-align:center;">P</th>';
            }
            $html_output .= '</tr></thead><tbody>';

            $grand_total_semua = 0;
            $grand_totals_kelompok = [];
            foreach ($kelas_list_display as $kelas) {
                $html_output .= '<tr><td style="padding:5px; font-weight:bold;">' . htmlspecialchars($kelas) . '</td>';
                $total_per_kelas = 0;
                $kelas_key = strtolower($kelas);
                foreach ($kelompok_pilihan as $kelompok) {
                    $kelompok_key = strtolower($kelompok);
                    $jumlah_l = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Laki-laki'] ?? 0;
                    $jumlah_p = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Perempuan'] ?? 0;
                    $total_per_kelas += ($jumlah_l + $jumlah_p);
                    $html_output .= '<td style="padding:5px; text-align:center;">' . $jumlah_l . '</td>';
                    $html_output .= '<td style="padding:5px; text-align:center;">' . $jumlah_p . '</td>';
                    $grand_totals_kelompok[$kelompok_key]['Laki-laki'] = ($grand_totals_kelompok[$kelompok_key]['Laki-laki'] ?? 0) + $jumlah_l;
                    $grand_totals_kelompok[$kelompok_key]['Perempuan'] = ($grand_totals_kelompok[$kelompok_key]['Perempuan'] ?? 0) + $jumlah_p;
                }
                $html_output .= '<td style="padding:5px; text-align:center; font-weight:bold;">' . $total_per_kelas . '</td></tr>';
                $grand_total_semua += $total_per_kelas;
            }
            $html_output .= '</tbody><tfoot style="background-color:#f2f2f2; font-weight:bold;"><tr>';
            $html_output .= '<td style="padding:5px;">TOTAL</td>';
            foreach ($kelompok_pilihan as $kelompok) {
                $kelompok_key = strtolower($kelompok);
                $html_output .= '<td style="padding:5px; text-align:center;">' . ($grand_totals_kelompok[$kelompok_key]['Laki-laki'] ?? 0) . '</td>';
                $html_output .= '<td style="padding:5px; text-align:center;">' . ($grand_totals_kelompok[$kelompok_key]['Perempuan'] ?? 0) . '</td>';
            }
            $html_output .= '<td style="padding:5px; text-align:center;">' . $grand_total_semua . '</td></tr></tfoot>';
            $html_output .= '</table>';

            $butuh_page_break = true; // Tandai bahwa kita perlu halaman baru jika ada data mentah
        }

        // --- 2. PROSES DATA MENTAH (JIKA DIPILIH) ---
        if (in_array('data_mentah', $tipe_laporan_pilihan)) {
            if (empty($kolom_pilihan)) {
                die("Error: Anda harus memilih minimal satu kolom untuk diekspor.");
            }
            $kolom_yang_diizinkan = ['nama_lengkap', 'kelas', 'kelompok', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nomor_hp', 'nama_orang_tua', 'nomor_hp_orang_tua'];
            $kolom_aman = array_intersect($kolom_pilihan, $kolom_yang_diizinkan);
            if (empty($kolom_aman)) {
                die("Error: Kolom yang dipilih tidak valid.");
            }

            if ($butuh_page_break) {
                $html_output .= '<pagebreak />'; // Pindah ke halaman baru
            }

            $sql = "SELECT " . implode(', ', $kolom_aman) . " FROM peserta WHERE kelompok IN ($kelompok_placeholders) AND kelas IN ($kelas_placeholders) AND status = 'Aktif' ORDER BY kelompok, FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama_lengkap";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($bind_types_filter, ...$bind_values_filter);
            $stmt->execute();
            $result = $stmt->get_result();

            $lebar_kolom = [
                'nama_lengkap' => '20%',
                'kelas' => '8%',
                'kelompok' => '10%',
                'jenis_kelamin' => '8%',
                'tempat_lahir' => '10%',
                'tanggal_lahir' => '10%',
                'nomor_hp' => '12%',
                'nama_orang_tua' => '15%',
                'nomor_hp_orang_tua' => '12%',
            ];
            $html_output .= '<h2 style="text-align:center;">Data Lengkap Siswa</h2>';
            $html_output .= '<table><thead><tr>';
            $html_output .= '<th style="width: 3%;">No.</th>';
            foreach ($kolom_aman as $kolom) {
                $style_lebar = isset($lebar_kolom[$kolom]) ? 'style="width:' . $lebar_kolom[$kolom] . '"' : '';
                $html_output .= '<th ' . $style_lebar . '>' . htmlspecialchars(ucwords(str_replace('_', ' ', $kolom))) . '</th>';
            }
            $html_output .= '</tr></thead><tbody>';
            $nomor = 1;
            while ($row = $result->fetch_assoc()) {
                $html_output .= '<tr>';
                $html_output .= '<td style="text-align:center;">' . $nomor++ . '</td>';
                foreach ($row as $kolom => $data) {
                    $data_tampil = ($kolom === 'tanggal_lahir') ? formatTanggalLahirIndonesia($data) : htmlspecialchars(ucwords($data ?? '') ?? '');
                    $html_output .= '<td>' . $data_tampil . '</td>';
                }
                $html_output .= '</tr>';
            }
            $html_output .= '</tbody></table>';
        }

        $html_output .= '</body></html>';
        $mpdf->WriteHTML($html_output);
        $mpdf->Output($nama_file_akhir . '.pdf', 'D');
        exit();
    }
}

// ===================================================================
// BAGIAN 2: PENGAMBILAN DATA UNTUK TAMPILAN FORM (GET)
// ===================================================================
$kelompok_list = [];
$result_kelompok = $conn->query("SELECT DISTINCT kelompok FROM peserta WHERE kelompok IS NOT NULL AND kelompok != '' ORDER BY kelompok ASC");
if ($result_kelompok) {
    while ($row = $result_kelompok->fetch_assoc()) {
        $kelompok_list[] = $row['kelompok'];
    }
}
$kelas_list = [];
$result_kelas = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE kelas IS NOT NULL AND kelas != '' ORDER BY FIELD(kelas, 'Paud', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah')");
if ($result_kelas) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row['kelas'];
    }
}

$kolom_list = [
    'nama_lengkap' => 'Nama Lengkap',
    'kelas' => 'Kelas',
    'kelompok' => 'Kelompok',
    'jenis_kelamin' => 'Jenis Kelamin',
    'tempat_lahir' => 'Tempat Lahir',
    'tanggal_lahir' => 'Tanggal Lahir',
    'nomor_hp' => 'No. HP Siswa',
    'nama_orang_tua' => 'Nama Orang Tua',
    'nomor_hp_orang_tua' => 'No. HP Orang Tua'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ekspor Data Siswa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
        }

        #kolom-container.is-hidden {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="bg-white p-6 rounded-2xl shadow-lg max-w-4xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-4 border-b pb-3">Ekspor Data Siswa</h1>

            <form method="POST" action="?page=export/export_siswa">
                <!-- 1. PILIH TIPE LAPORAN (DIUBAH JADI CHECKBOX) -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Pilih Tipe Laporan</h2>
                    <div class="flex flex-col gap-2 border p-4 rounded-md">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="tipe_ringkasan" name="tipe_laporan[]" value="ringkasan" class="h-4 w-4 text-cyan-600" checked>
                            <span>Ringkasan Jumlah Peserta (L/P)</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="tipe_data_mentah" name="tipe_laporan[]" value="data_mentah" class="h-4 w-4 text-cyan-600" checked>
                            <span>Data Lengkap Siswa</span>
                        </label>
                    </div>
                </div>

                <!-- 2. PILIH KELOMPOK -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Pilih Kelompok</h2>
                    <?php if ($admin_level === 'desa'): ?>
                        <div class="border p-4 rounded-md space-y-2">
                            <label class="flex items-center space-x-2 font-semibold">
                                <input type="checkbox" id="pilih_semua_kelompok" class="rounded">
                                <span>Pilih Semua Kelompok</span>
                            </label>
                            <hr>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <?php foreach ($kelompok_list as $kelompok): ?>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" name="kelompok[]" value="<?php echo $kelompok; ?>" class="kelompok-checkbox rounded">
                                        <span><?php echo htmlspecialchars(ucwords($kelompok)); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-100 p-4 rounded-md">
                            <p class="text-gray-700">Anda akan mengekspor data untuk kelompok Anda:
                                <span class="font-bold text-lg text-cyan-700"><?php echo htmlspecialchars(ucwords($admin_kelompok)); ?></span>
                            </p>
                            <input type="hidden" name="kelompok[]" value="<?php echo htmlspecialchars($admin_kelompok); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. PILIH KELAS -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Pilih Kelas</h2>
                    <div class="border p-4 rounded-md space-y-2">
                        <label class="flex items-center space-x-2 font-semibold">
                            <input type="checkbox" id="pilih_semua_kelas" class="rounded">
                            <span>Pilih Semua Kelas</span>
                        </label>
                        <hr>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <?php foreach ($kelas_list as $kelas): ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="kelas[]" value="<?php echo $kelas; ?>" class="kelas-checkbox rounded">
                                    <span><?php echo htmlspecialchars(ucwords($kelas)); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 4. PILIH KOLOM (Sekarang dinamis) -->
                <div id="kolom-container" class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Pilih Kolom Data Mentah</h2>
                    <div class="border p-4 rounded-md space-y-2">
                        <label class="flex items-center space-x-2 font-semibold">
                            <input type="checkbox" id="pilih_semua_kolom" class="rounded">
                            <span>Pilih Semua Kolom</span>
                        </label>
                        <hr>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <?php foreach ($kolom_list as $key => $label): ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="kolom[]" value="<?php echo $key; ?>" class="kolom-checkbox rounded" checked>
                                    <span><?php echo ucwords($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 5. PILIH FORMAT -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Pilih Format Ekspor</h2>
                    <div class="flex gap-4 border p-4 rounded-md">
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="format" value="pdf" class="h-4 w-4 text-cyan-600" checked>
                            <span>PDF (untuk Dicetak)</span>
                        </label>
                        <!-- <label class="flex items-center space-x-2">
                            <input type="radio" name="format" value="csv" class="h-4 w-4 text-cyan-600">
                            <span>CSV (untuk Excel/Spreadsheet)</span>
                        </label> -->
                    </div>
                </div>

                <!-- 6. TOMBOL EKSPOR -->
                <div class="mt-6 border-t pt-4 flex justify-between items-center">
                    <!-- <a href="../../?page=master/kelola_peserta" class="text-gray-600 hover:text-gray-800 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali
                    </a> -->
                    <a href="../../?page=master/kelola_peserta" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-8 rounded-lg shadow-md transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali
                    </a>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition duration-300">
                        <!-- <i class="fas fa-download mr-2"></i> Ekspor Data -->
                        <i class="fa-solid fa-file-pdf mr-2"></i> Ekspor Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- JAVASCRIPT BARU UNTUK MENGONTROL TAMPILAN ---
            const tipeDataMentahCheckbox = document.getElementById('tipe_data_mentah');
            const tipeRingkasanCheckbox = document.getElementById('tipe_ringkasan');
            const kolomContainer = document.getElementById('kolom-container');
            const formatRadios = document.querySelectorAll('input[name="format"]');

            function toggleKolomContainer() {
                if (tipeDataMentahCheckbox.checked) {
                    kolomContainer.classList.remove('is-hidden');
                } else {
                    kolomContainer.classList.add('is-hidden');
                }
            }

            function handleFormatChange() {
                // Jika CSV dipilih, nonaktifkan pilihan "Ringkasan" karena tidak didukung
                // (Atau biarkan backend yg mengabaikan)
                // Untuk saat ini, kita hanya akan menyembunyikan/menampilkan kolom
            }

            tipeDataMentahCheckbox.addEventListener('change', toggleKolomContainer);
            // Panggil saat memuat untuk set status awal
            toggleKolomContainer();

            // --- AKHIR JAVASCRIPT BARU ---


            // Logika untuk "Pilih Semua"
            const setupPilihSemua = (masterId, checkboxClass) => {
                const master = document.getElementById(masterId);
                if (master) {
                    master.addEventListener('change', function() {
                        document.querySelectorAll('.' + checkboxClass).forEach(cb => cb.checked = this.checked);
                    });
                }
            };
            setupPilihSemua('pilih_semua_kelompok', 'kelompok-checkbox');
            setupPilihSemua('pilih_semua_kelas', 'kelas-checkbox');
            setupPilihSemua('pilih_semua_kolom', 'kolom-checkbox');
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>