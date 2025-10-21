<?php
// Diasumsikan file koneksi.php sudah di-include
include '../../../config/config.php';


// --- (SIMULASI SESI ADMIN) ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// $_SESSION['user_tingkat'] = 'desa'; // Ubah menjadi 'kelompok' untuk tes
// $_SESSION['user_kelompok'] = 'Bintaran'; // Diisi jika tingkat='kelompok'
// --- (AKHIR SIMULASI) ---

// Ambil data sesi admin
$admin_level = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;
$nama_admin = htmlspecialchars($_SESSION['user_nama']);

// ===================================================================
// BAGIAN 1: PEMROSESAN FORM EKSPOR (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kelompok_pilihan = $_POST['kelompok'] ?? [];
    $kelas_pilihan = $_POST['kelas'] ?? [];
    $kolom_pilihan = $_POST['kolom'] ?? [];
    $format = $_POST['format'] ?? 'csv';

    // --- LOGIKA BARU UNTUK NAMA FILE ---

    // 1. Hitung total kelompok yang ada di database
    $total_kelompok_db = $conn->query("SELECT COUNT(DISTINCT kelompok) as total FROM peserta WHERE kelompok IS NOT NULL AND kelompok != ''")->fetch_assoc()['total'];

    $nama_file_kelompok = '';
    // 2. Cek apakah jumlah yang dipilih sama dengan total (artinya "semua")
    if (count($kelompok_pilihan) >= $total_kelompok_db) {
        $nama_file_kelompok = 'banguntapan_1';
    } else {
        // Jika tidak semua, gabungkan nama kelompok yang dipilih
        // Ganti spasi dengan underscore dan bersihkan karakter lain
        $nama_kelompok_bersih = array_map(function ($k) {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $k));
        }, $kelompok_pilihan);
        $nama_file_kelompok = implode('_', $nama_kelompok_bersih);
    }
    // Batasi panjang nama file agar tidak terlalu panjang (opsional)
    if (strlen($nama_file_kelompok) > 50) {
        $nama_file_kelompok = substr($nama_file_kelompok, 0, 50) . '_etc';
    }

    // --- AKHIR LOGIKA NAMA FILE ---

    // Validasi: pastikan minimal satu pilihan di setiap kategori
    if (empty($kelompok_pilihan) || empty($kelas_pilihan) || empty($kolom_pilihan)) {
        die("Error: Anda harus memilih minimal satu kelompok, satu kelas, dan satu kolom untuk diekspor.");
    }

    // --- Keamanan: Buat daftar putih (whitelist) kolom yang diizinkan ---
    $kolom_yang_diizinkan = ['nama_lengkap', 'kelas', 'kelompok', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nomor_hp', 'nama_orang_tua', 'nomor_hp_orang_tua'];
    $kolom_aman = array_intersect($kolom_pilihan, $kolom_yang_diizinkan);

    if (empty($kolom_aman)) {
        die("Error: Kolom yang dipilih tidak valid.");
    }

    // --- Bangun Query SQL secara Dinamis ---
    $kolom_select = implode(', ', $kolom_aman);
    $kelompok_placeholders = implode(',', array_fill(0, count($kelompok_pilihan), '?'));
    $kelas_placeholders = implode(',', array_fill(0, count($kelas_pilihan), '?'));

    $sql = "SELECT $kolom_select FROM peserta WHERE kelompok IN ($kelompok_placeholders) AND kelas IN ($kelas_placeholders) AND status = 'Aktif' ORDER BY kelompok, kelas, nama_lengkap";

    $stmt = $conn->prepare($sql);

    $bind_values = array_merge($kelompok_pilihan, $kelas_pilihan);
    $bind_types = str_repeat('s', count($bind_values));

    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $result = $stmt->get_result();

    // --- Proses Output berdasarkan Format ---
    $tanggal_sekarang = date('Y-m-d');
    $nama_file_akhir = "laporan_siswa_{$nama_file_kelompok}_{$tanggal_sekarang}";

    // --- Proses Output berdasarkan Format ---
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nama_file_akhir . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array_merge(['No.'], $kolom_aman));
        $nomor = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, array_merge([$nomor++], array_values($row)));
        }
        fclose($output);
        exit();
    } elseif ($format === 'pdf') {
        require_once __DIR__ . '/../../../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']);

        // Konfigurasi Watermark Gambar
        $imagePath = '../../../assets/images/logo_kbm.png'; // Ganti dengan path logo Anda
        $alpha = 0.1; // Transparansi (0.0 - 1.0)
        $size = 'D'; // Ukuran (D=Default/Original, P=Stretch)
        $position = 'P'; // Posisi (P=Behind Content, F=Foreground)

        // Terapkan watermark
        $mpdf->SetWatermarkImage($imagePath, $alpha, $size, $position);
        $mpdf->showWatermarkImage = true; // Aktifkan tampilan watermark

        // Pengaturan lebar kolom untuk PDF
        $lebar_kolom = [
            'nama_lengkap' => '17%',
            'kelas' => '8%',
            'kelompok' => '10%',
            'jenis_kelamin' => '9%',
            'tempat_lahir' => '9%',
            'tanggal_lahir' => '11%',
            'nomor_hp' => '12%',
            'nama_orang_tua' => '13%',
            'nomor_hp_orang_tua' => '12%',
        ];

        $html = '<h1 style="text-align:center;">Data Siswa PJP Banguntapan 1</h1><p>Tanggal Ekspor: ' . formatTanggalIndonesiaTanpaNol(date('d M Y')) . '<br>Dikeluarkan Oleh: ' . $nama_admin . '</p>';
        $html .= '<table border="1" style="width:100%; border-collapse: collapse; font-size: 8pt;">'; // Font size diperkecil
        $html .= '<thead><tr style="background-color:#FFFB00;">';
        $html .= '<th style="width: 3%;">No.</th>';

        foreach ($kolom_aman as $kolom) {
            $style_lebar = isset($lebar_kolom[$kolom]) ? 'style="width:' . $lebar_kolom[$kolom] . '"' : '';
            $html .= '<th ' . $style_lebar . '>' . htmlspecialchars(ucwords(str_replace('_', ' ', $kolom))) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        $nomor = 1;
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>';
            $html .= '<td style="text-align:center;">' . $nomor++ . '</td>';
            // Gunakan foreach ($array as $key => $value) untuk mendapatkan nama kolom
            foreach ($row as $kolom => $data) {

                // Cek apakah kolom saat ini adalah 'tanggal_lahir'
                if ($kolom === 'tanggal_lahir') {
                    // Jika ya, format tanggalnya menggunakan fungsi yang sudah kita buat
                    $data_tampil = formatTanggalLahirIndonesia($data);
                } else {
                    // Jika bukan, lakukan htmlspecialchars seperti biasa
                    $data_tampil = htmlspecialchars(ucwords($data ?? '') ?? '');
                }

                $html .= '<td>' . $data_tampil . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
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
$result_kelas = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
if ($result_kelas) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row['kelas'];
    }
}

// Daftar kolom yang bisa dipilih (sudah diperbarui)
$kolom_list = [
    'nama_lengkap' => 'Nama Lengkap',
    'kelas' => 'Kelas',
    'kelompok' => 'Kelompok',
    'jenis_kelamin' => 'Jenis Kelamin',
    'tempat_lahir' => 'Tempat Lahir',
    'tanggal_lahir' => 'Tanggal Lahir',
    'nomor_hp' => 'No. HP Siswa',
    'nama_orang_tua' => 'Nama Orang Tua',
    'nomor_hp_orang_tua' => 'No. HP Orang Tua',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ekspor Data Siswa</title>
    <link rel="icon" type="image/png" href="../../../assets/images/logo_web_bg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
        }
    </style>
</head>

<body>
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="bg-white p-6 rounded-2xl shadow-lg max-w-4xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-4 border-b pb-3">Ekspor Data Siswa</h1>

            <form method="POST" action="">
                <!-- 1. PILIH KELOMPOK -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Langkah 1: Pilih Kelompok</h2>
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
                            <p class="text-gray-700">Anda akan mengekspor data untuk kelompok Anda:</p>
                            <p class="font-bold text-lg text-cyan-700"><?php echo htmlspecialchars(ucwords($admin_kelompok)); ?></p>
                            <input type="hidden" name="kelompok[]" value="<?php echo htmlspecialchars($admin_kelompok); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. PILIH KELAS -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Langkah 2: Pilih Kelas</h2>
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

                <!-- 3. PILIH KOLOM -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Langkah 3: Pilih Kolom Data</h2>
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
                                    <span><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 4. PILIH FORMAT -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Langkah 4: Pilih Format Ekspor</h2>
                    <div class="flex gap-4 border p-4 rounded-md">
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="format" value="pdf" class="h-4 w-4 text-cyan-600" checked>
                            <span>PDF (untuk Dicetak)</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="format" value="csv" class="h-4 w-4 text-cyan-600">
                            <span>CSV (untuk Excel/Spreadsheet)</span>
                        </label>
                    </div>
                </div>

                <!-- 5. TOMBOL EKSPOR -->
                <div class="mt-6 border-t pt-4 flex justify-end gap-3">
                    <!-- <button
                        type="button"
                        onclick="window.close();"
                        class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg shadow-md transition duration-300">
                        <i class="fas fa-times mr-2"></i> Tutup Halaman
                    </button> -->
                    <a href="../../?page=master/kelola_peserta" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-8 rounded-lg shadow-md transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali
                    </a>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition duration-300">
                        <i class="fas fa-download mr-2"></i> Ekspor Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pilihSemuaKelompok = document.getElementById('pilih_semua_kelompok');
            if (pilihSemuaKelompok) {
                pilihSemuaKelompok.addEventListener('change', function() {
                    document.querySelectorAll('.kelompok-checkbox').forEach(cb => cb.checked = this.checked);
                });
            }
            const pilihSemuaKelas = document.getElementById('pilih_semua_kelas');
            if (pilihSemuaKelas) {
                pilihSemuaKelas.addEventListener('change', function() {
                    document.querySelectorAll('.kelas-checkbox').forEach(cb => cb.checked = this.checked);
                });
            }
            const pilihSemuaKolom = document.getElementById('pilih_semua_kolom');
            if (pilihSemuaKolom) {
                pilihSemuaKolom.addEventListener('change', function() {
                    document.querySelectorAll('.kolom-checkbox').forEach(cb => cb.checked = this.checked);
                });
            }
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>