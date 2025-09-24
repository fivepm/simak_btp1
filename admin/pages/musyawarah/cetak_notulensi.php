<?php
session_start();
// 🔐 SECURITY CHECK
$allowed_roles = ['superadmin', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../../");
    exit;
}

// Diasumsikan file koneksi.php sudah di-include
include '../../../config/config.php';

$id_musyawarah = $_GET['id'] ?? null;

// Keamanan: Pastikan ID Musyawarah valid
if (!$id_musyawarah || !filter_var($id_musyawarah, FILTER_VALIDATE_INT)) {
    die("ID Musyawarah tidak valid.");
}

// 1. Ambil Detail Musyawarah
$stmt_musyawarah = $conn->prepare("SELECT * FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

if (!$musyawarah) {
    die("Data musyawarah tidak ditemukan.");
}

// 2. Cari Musyawarah SEBELUMNYA berdasarkan tanggal
$stmt_prev = $conn->prepare("SELECT id, nama_musyawarah, tanggal FROM musyawarah WHERE tanggal < ? ORDER BY tanggal DESC LIMIT 1");
$stmt_prev->bind_param("s", $musyawarah['tanggal']);
$stmt_prev->execute();
$musyawarah_sebelumnya = $stmt_prev->get_result()->fetch_assoc();
$stmt_prev->close();

// 3. Jika musyawarah sebelumnya ditemukan, ambil poin-poinnya
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


// 6. Ambil Laporan Kelompok musyawarah SAAT INI
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

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[HASIL] - <?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></title>
    <link rel="icon" type="image/png" href="../../../assets/images/logo_web_bg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Roboto:wght@400;700&display=swap');

        /* --- ATURAN BARU UNTUK HALAMAN CETAK --- */
        @page {
            size: A4;
            margin: 2cm;
            /* Mengatur margin kertas untuk semua sisi */
        }

        body {
            font-family: 'Times New Roman', serif;
            background-color: #f0f2f5;
        }

        .page-container {
            /* --- INI BAGIAN YANG DIPERBAIKI --- */
            position: relative;
            z-index: 0;
            /* Membuat stacking context baru */
            /* --- AKHIR PERBAIKAN --- */
            max-width: 21cm;
            min-height: 29.7cm;
            margin: 2rem auto;
            background: white;
            padding: 2.5cm 2cm;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            /* Mencegah watermark keluar dari kontainer */
        }

        h1,
        h2,
        h3 {
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #e9ecef;
            font-family: 'Roboto', sans-serif;
            font-weight: bold;
        }

        .header-table td {
            border: none;
            padding: 4px 0;
        }

        /* --- CSS BARU UNTUK WATERMARK GAMBAR --- */
        .page-container::before {
            content: '';
            /* Kosongkan content karena kita pakai background-image */
            position: absolute;
            top: 50%;
            left: 50%;
            width: 400px;
            /* Lebar area watermark */
            height: 400px;
            /* Tinggi area watermark */
            transform: translate(-50%, -50%);
            /* Posisikan di tengah */

            /* Ganti URL di bawah ini dengan URL logo Anda */
            background-image: url('../../../assets/images/logo_kbm.png');

            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            /* Agar logo pas di dalam area */
            opacity: 0.1;
            /* Atur transparansi agar terlihat pudar */
            z-index: -1;
            /* Posisikan di belakang konten */
            pointer-events: none;
            /* Agar tidak bisa diseleksi */
        }

        /* --- AKHIR CSS BARU --- */

        @media print {
            body {
                background-color: white;
                margin: 0;
            }

            .page-container {
                /* --- INI BAGIAN YANG DIPERBAIKI --- */
                margin: 0;
                padding: 0;
                /* Hapus padding karena margin diatur @page */
                border: none;
                box-shadow: none;
                width: 100%;
                min-height: 0;
                /* --- AKHIR PERBAIKAN --- */

                /* --- INI BAGIAN YANG DIPERBAIKI --- */
                -webkit-print-color-adjust: exact !important;
                /* Untuk Chrome, Safari, Edge */
                print-color-adjust: exact !important;
                /* Properti standar */
                /* --- AKHIR PERBAIKAN --- */
            }

            /* --- INI BAGIAN YANG DIPERBAIKI --- */
            .page-container::before {
                position: fixed;
                /* Gunakan 'fixed' agar berpusat di setiap halaman cetak */
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            /* --- AKHIR PERBAIKAN --- */

            .no-print {
                display: none;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="page-container">
        <header class="text-center mb-8">
            <h1 class="text-2xl uppercase font-bold tracking-wider">Notulensi Musyawarah</h1>
            <div class="w-24 border-b-2 border-black mx-auto mt-2"></div>
        </header>

        <section class="mb-8">
            <h2 class="text-lg font-bold mb-3">DETAIL MUSYAWARAH</h2>
            <table class="header-table">
                <tr>
                    <td style="width: 180px;"><strong>Nama Musyawarah</strong></td>
                    <td style="width: 20px;">:</td>
                    <td><?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></td>
                </tr>
                <tr>
                    <td><strong>Tanggal & Waktu</strong></td>
                    <td>:</td>
                    <td><?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?>, Pukul <?php echo date('H:i', strtotime($musyawarah['waktu_mulai'])); ?> WIB</td>
                </tr>
                <tr>
                    <td><strong>Tempat</strong></td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($musyawarah['tempat']); ?></td>
                </tr>
            </table>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-bold mb-3">DAFTAR HADIR PESERTA MUSYAWARAH</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">No.</th>
                        <th class="text-center">Nama Peserta</th>
                        <th style="width: 30%;" class="text-center">Dapukan</th>
                        <th style="width: 25%;" class="text-center">Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_hadir->num_rows > 0): $no = 1; ?>
                        <?php while ($peserta = $result_hadir->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($peserta['nama_peserta']); ?></td>
                                <td><?php echo htmlspecialchars($peserta['jabatan']); ?></td>
                                <td><?php echo htmlspecialchars($peserta['status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center italic">Tidak ada data kehadiran.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- BAGIAN BARU: Tinjauan Musyawarah Sebelumnya -->
        <?php if ($poin_sebelumnya && $poin_sebelumnya->num_rows > 0): ?>
            <section class="mb-10 section-break">
                <h2 class="text-lg font-bold mb-3">EVALUASI MUSYAWARAH SEBELUMNYA</h2>
                <p class="text-sm italic mb-2" style="font-family: 'Roboto', sans-serif;">Dari Musyawarah: "<?php echo htmlspecialchars($musyawarah_sebelumnya['nama_musyawarah']); ?>" (<?php echo date('d F Y', strtotime($musyawarah_sebelumnya['tanggal'])); ?>)</p>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 5%;">No.</th>
                            <th>Poin Pembahasan</th>
                            <th style="width: 20%;">Status</th>
                            <th style="width: 30%;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no_prev = 1; ?>
                        <?php while ($poin = $poin_sebelumnya->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?php echo $no_prev++; ?></td>
                                <td><?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?></td>
                                <td>
                                    <?php
                                    if ($poin['status_evaluasi'] == 'Terlaksana') {
                                        echo '<i class="fas fa-check-circle text-green-600 mr-2"></i> Terlaksana';
                                    } elseif ($poin['status_evaluasi'] == 'Belum Terlaksana') {
                                        echo '<i class="fas fa-times-circle text-red-600 mr-2"></i> Belum Terlaksana';
                                    } else {
                                        echo '<i class="far fa-circle text-gray-500 mr-2"></i> Belum Dievaluasi';
                                    }
                                    ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($poin['keterangan'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
        <!-- AKHIR BAGIAN BARU -->

        <!-- --- BAGIAN BARU: LAPORAN KELOMPOK --- -->
        <section class="mb-10 section-break">
            <h2 class="text-lg font-bold mb-3">LAPORAN KELOMPOK</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Kelompok</th>
                        <th>Isi Laporan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $laporan_ditemukan = false; ?>
                    <?php foreach ($daftar_kelompok as $kelompok): ?>
                        <?php if (!empty($laporan_kelompok_tersimpan[$kelompok])): ?>
                            <?php $laporan_ditemukan = true; ?>
                            <tr>
                                <td class="font-bold"><?php echo htmlspecialchars($kelompok); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($laporan_kelompok_tersimpan[$kelompok])); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (!$laporan_ditemukan): ?>
                        <tr>
                            <td colspan="2" class="text-center italic">Tidak ada laporan kelompok yang diisi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <!-- --- AKHIR BAGIAN BARU --- -->

        <section>
            <h2 class="text-lg font-bold mb-3">HASIL MUSYAWARAH</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">No.</th>
                        <th class="text-center">Poin Pembahasan</th>
                        <!-- <th style="width: 20%;" class="text-center">Status</th>
                        <th style="width: 30%;" class="text-center">Keterangan</th> -->
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_poin->num_rows > 0): $no = 1; ?>
                        <?php while ($poin = $result_poin->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td><?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?></td>
                                <!-- <td>
                                    <?php
                                    if ($poin['status_evaluasi'] == 'Terlaksana') {
                                        echo '<i class="fas fa-check-circle text-green-600 mr-2"></i> Terlaksana';
                                    } elseif ($poin['status_evaluasi'] == 'Belum Terlaksana') {
                                        echo '<i class="fas fa-times-circle text-red-600 mr-2"></i> Belum Terlaksana';
                                    } else {
                                        echo '<i class="far fa-circle text-gray-500 mr-2"></i> Belum Dievaluasi';
                                    }
                                    ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($poin['keterangan'])); ?></td> -->
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center italic">Tidak ada poin notulensi yang dicatat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

    </div>

    <script>
        // Otomatis memicu dialog print saat halaman selesai dimuat
        window.onload = function() {
            // 1. Panggil dialog print
            window.print();

            // 2. Siapkan event listener untuk setelah print
            window.addEventListener('afterprint', function(event) {
                // 3. Setelah print selesai (atau dibatalkan), tutup tab
                window.close();
            });
        };
    </script>
</body>

</html>