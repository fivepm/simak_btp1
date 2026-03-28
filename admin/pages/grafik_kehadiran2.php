<?php
// Pastikan $conn dan $_SESSION sudah ada
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// =============================================
// ▼▼▼ PERBAIKAN: Logika Filter Admin Kelompok ▼▼▼
// =============================================

// Ambil info admin
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;

// Definisikan urutan standar
$URUTAN_KELOMPOK_GLOBAL = ['bintaran', 'gedongkuning', 'jombor', 'sunten']; // Daftar semua kelompok
$URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

// Tentukan kelompok mana yang akan ditampilkan berdasarkan level admin
$kelompok_to_display = [];
// PERBAIKAN: Jika admin adalah 'kelompok', tampilkan hanya grupnya.
// Jika 'desa', 'superadmin', atau lainnya, tampilkan semua.
if ($admin_tingkat === 'kelompok' && !empty($admin_kelompok) && in_array($admin_kelompok, $URUTAN_KELOMPOK_GLOBAL)) {
    $kelompok_to_display = [$admin_kelompok]; // Array berisi 1 kelompok
} else {
    // Admin Desa, Superadmin, dll. bisa melihat semua
    $kelompok_to_display = $URUTAN_KELOMPOK_GLOBAL;
}
// =============================================
// ▲▲▲ AKHIR PERBAIKAN ▲▲▲
// =============================================


// ===================================================================
// BAGIAN 1: PENGAMBILAN DATA (PHP)
// ===================================================================

// 1. Ambil daftar periode untuk filter
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status != 'Arsip' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// 2. Tentukan periode yang dipilih (Selected Period)
$selected_periode_id = null;
if (isset($_GET['periode_id'])) {
    $selected_periode_id = (int)$_GET['periode_id'];
} else {
    // Jika tidak ada yg dipilih, cari periode aktif (default)
    $sql_active_periode = "SELECT id FROM periode 
                           WHERE CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai
                           AND status = 'Aktif' 
                           LIMIT 1";
    $result_active = $conn->query($sql_active_periode);
    if ($result_active && $result_active->num_rows > 0) {
        $selected_periode_id = $result_active->fetch_assoc()['id'];
    } else {
        // Jika tidak ada yg aktif, ambil periode terbaru dari list
        if (!empty($periode_list)) {
            $selected_periode_id = $periode_list[0]['id'];
        }
    }
}

// 3. Ambil data statistik untuk periode yang dipilih
$grafik_data_php = [];
if ($selected_periode_id && !empty($kelompok_to_display)) { // Hanya jalankan jika ada kelompok yg boleh dilihat

    // =============================================
    // ▼▼▼ PERBAIKAN: Tambahkan Filter Admin ke SQL ▼▼▼
    // =============================================

    // Siapkan filter admin
    $admin_where_clause = "";
    $bind_types = "i"; // Tipe untuk periode_id
    $bind_params = [$selected_periode_id]; // Nilai untuk periode_id

    // Logika ini sudah benar: Jika 'kelompok', filter SQL.
    if ($admin_tingkat === 'kelompok' && !empty($admin_kelompok)) {
        $admin_where_clause = " AND p.kelompok = ? "; // Filter kelompok
        $bind_types .= "s"; // Tambah tipe string
        $bind_params[] = $admin_kelompok; // Tambah nilai kelompok
    }
    // =============================================

    // Kueri SQL (Sudah diperbaiki)
    $sql_grafik = "
        SELECT 
            p.kelompok, p.kelas,
            COUNT(DISTINCT jp.id) AS total_jadwal_periode, 
            SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) AS alpa,
            SUM(CASE 
                WHEN (rp.status_kehadiran IS NULL OR rp.status_kehadiran = '') AND jp.id IS NOT NULL 
                THEN 1 
                ELSE 0 
            END) AS kosong
        FROM 
            (SELECT DISTINCT kelompok, kelas FROM peserta WHERE kelompok IS NOT NULL AND kelas IS NOT NULL) p
        LEFT JOIN 
            jadwal_presensi jp ON p.kelompok = jp.kelompok COLLATE utf8mb4_unicode_ci 
                               AND p.kelas = jp.kelas COLLATE utf8mb4_unicode_ci 
                               AND jp.periode_id = ?  -- Filter Periode
        LEFT JOIN 
            rekap_presensi rp ON jp.id = rp.jadwal_id
        WHERE
            p.kelompok IS NOT NULL AND p.kelompok != '' AND p.kelas IS NOT NULL AND p.kelas != ''
            $admin_where_clause -- Tambahkan filter admin di sini
        GROUP BY p.kelompok, p.kelas
        ORDER BY p.kelompok, FIELD(p.kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah')
    ";

    $stmt_grafik = $conn->prepare($sql_grafik);
    if ($stmt_grafik) {
        // =============================================
        // ▼▼▼ PERBAIKAN: Gunakan bind_param dinamis ▼▼▼
        // =============================================
        $stmt_grafik->bind_param($bind_types, ...$bind_params);
        // =============================================

        $stmt_grafik->execute();
        $result_grafik = $stmt_grafik->get_result();

        while ($row = $result_grafik->fetch_assoc()) {
            $grafik_data_php[$row['kelompok']][$row['kelas']] = $row;
        }
        $stmt_grafik->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
}

// 4. Encode data untuk JavaScript
$grafik_data_json = json_encode($grafik_data_php);

?>

<!-- =================================================================== -->
<!-- BAGIAN 2: TAMPILAN HTML (Dengan Struktur TAB Responsif) -->
<!-- =================================================================== -->
<div class="container mx-auto p-4 sm:p-6 lg:p-8">

    <div class="mb-6">
        <a href="?page=dashboard" class="text-cyan-600 hover:text-cyan-800 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dahsboard
        </a>
    </div>

    <!-- Header dan Filter (Tidak Berubah) -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Grafik Rekap Kehadiran</h1>

            <form method="GET" action="" class="flex items-center gap-2">
                <input type="hidden" name="page" value="grafik_kehadiran">
                <label for="periode_id" class="text-sm font-medium text-gray-700 whitespace-nowrap">Pilih Periode:</label>
                <select name="periode_id" id="periode_id" class="block w-full md:w-64 py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500">
                    <?php if (empty($periode_list)): ?>
                        <option value="">Tidak ada periode</option>
                    <?php else: ?>
                        <?php foreach ($periode_list as $periode): ?>
                            <option value="<?php echo $periode['id']; ?>" <?php echo ($selected_periode_id == $periode['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($periode['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Kontainer Grafik -->
    <div id="grafik-container" class="bg-white rounded-lg shadow-md">
        <?php if (empty($grafik_data_php)): ?>
            <p class="text-center text-gray-500 p-6">Tidak ada data untuk ditampilkan pada periode ini.</p>
        <?php else: ?>

            <!-- Tampilkan tabs hanya jika ada lebih dari 1 kelompok (Admin Desa/Superadmin) -->
            <?php if (count($kelompok_to_display) > 1): ?>
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-4 overflow-x-auto" aria-label="Tabs Kelompok">
                        <?php foreach ($kelompok_to_display as $i => $kelompok): ?>
                            <button type="button"
                                data-tab-target="chart-panel-<?php echo $kelompok; ?>"
                                class="tab-button-kelompok capitalize whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm <?php echo $i === 0 ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                <?php echo $kelompok; ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php endif; ?>

            <!-- Kontainer Panel -->
            <div class="p-4 sm:p-6">
                <?php foreach ($kelompok_to_display as $i => $kelompok): ?>
                    <!-- Panel untuk setiap kelompok -->
                    <div id="chart-panel-<?php echo $kelompok; ?>"
                        class="tab-panel-kelompok <?php echo ($i > 0) ? 'hidden' : ''; ?>"
                        role="tabpanel">

                        <!-- Judul H2 ini opsional, bisa dihapus jika tab sudah cukup jelas -->
                        <h2 class="text-xl font-semibold text-center capitalize text-gray-700 md:hidden <?php echo count($kelompok_to_display) > 1 ? 'hidden' : ''; ?>">
                            <?php echo $kelompok; ?>
                        </h2>

                        <!-- ▼▼▼ TAB KELAS (HANYA TAMPIL DI HP) ▼▼▼ -->
                        <div class="mt-4 border-b border-gray-200 overflow-x-auto md:hidden">
                            <nav class="flex -mb-px space-x-4" aria-label="Tabs <?php echo $kelompok; ?>">
                                <?php foreach ($URUTAN_KELAS as $j => $kelas): ?>
                                    <button type="button"
                                        data-kelompok="<?php echo $kelompok; ?>"
                                        data-kelas="<?php echo $kelas; ?>"
                                        class="grafik-kelas-tab-<?php echo $kelompok; ?> capitalize whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm
                                            <?php echo $j === 0 ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                        <?php
                                        // Buat singkatan untuk HP
                                        switch (strtolower($kelas)) {
                                            case 'caberawit a':
                                                echo 'CBR A';
                                                break;
                                            case 'caberawit b':
                                                echo 'CBR B';
                                                break;
                                            case 'pra remaja':
                                                echo 'PraRemaja';
                                                break;
                                            default:
                                                echo ucwords($kelas);
                                        }
                                        ?>
                                    </button>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                        <!-- ▲▲▲ AKHIR TAB KELAS ▲▲▲ -->

                        <!-- Kontainer Canvas -->
                        <div class="relative h-80 md:h-96 w-full mt-4">
                            <canvas id="chart_<?php echo $kelompok; ?>"></canvas>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <!-- ============================================= -->
    <!-- ▼▼▼ TAMBAHAN: KARTU TABEL DATA RINCI ▼▼▼ -->
    <!-- ============================================= -->
    <?php if (!empty($grafik_data_php)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mt-6"> <!-- Tambah mt-6 untuk jarak -->
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                Data Rinci (Angka Absolut per Periode)
            </h2>
            <div class="overflow-x-auto max-h-64 overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Total Entri</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Hadir</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Izin</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Sakit</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Alpa</th>
                            <!-- <th class="px-4 py-2 text-center font-medium text-gray-500">Kosong</th> -->
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $has_data_rincian = false;
                        // PERBAIKAN: Loop berdasarkan $kelompok_to_display
                        foreach ($kelompok_to_display as $kelompok) {
                            // Hanya tampilkan jika kelompok ini ada datanya (mengikuti filter admin)
                            if (isset($grafik_data_php[$kelompok])) {
                                foreach ($URUTAN_KELAS as $kelas) {
                                    // Cek apakah data ada di array PHP
                                    if (isset($grafik_data_php[$kelompok][$kelas])) {
                                        $has_data_rincian = true;
                                        $d = $grafik_data_php[$kelompok][$kelas];

                                        // Ambil data (paksa jadi integer)
                                        $total_jadwal = (int)($d['total_jadwal_periode'] ?? 0);
                                        $hadir = (int)($d['hadir'] ?? 0);
                                        $izin = (int)($d['izin'] ?? 0);
                                        $sakit = (int)($d['sakit'] ?? 0);
                                        $alpa = (int)($d['alpa'] ?? 0);
                                        $kosong = (int)($d['kosong'] ?? 0);
                                        $total_entri = $hadir + $izin + $sakit + $alpa;

                                        // Tampilkan baris "Tidak ada jadwal"
                                        if ($total_jadwal == 0) {
                        ?>
                                            <tr class="bg-gray-50">
                                                <td class="px-4 py-2 font-medium capitalize text-gray-500"><?php echo $kelompok; ?></td>
                                                <td class="px-4 py-2 capitalize text-gray-500"><?php echo $kelas; ?></td>
                                                <td colspan="6" class="px-4 py-2 text-center text-gray-400 italic">Tidak ada jadwal</td>
                                            </tr>
                                        <?php
                                        } else {
                                            // Tampilkan baris data normal
                                        ?>
                                            <tr>
                                                <td class="px-4 py-2 font-medium capitalize"><?php echo $kelompok; ?></td>
                                                <td class="px-4 py-2 capitalize"><?php echo $kelas; ?></td>
                                                <td class="px-4 py-2 text-center font-bold"><?php echo $total_entri; ?></td>
                                                <td class="px-4 py-2 text-center text-green-600"><?php echo $hadir; ?></td>
                                                <td class="px-4 py-2 text-center text-yellow-600"><?php echo $izin; ?></td>
                                                <td class="px-4 py-2 text-center text-blue-600"><?php echo $sakit; ?></td>
                                                <td class="px-4 py-2 text-center text-red-600"><?php echo $alpa; ?></td>
                                                <!-- <td class="px-4 py-2 text-center text-gray-500"><?php echo $kosong; ?></td> -->
                                            </tr>
                            <?php
                                        }
                                    } // end if isset kelas
                                } // end foreach kelas
                            } // end if isset kelompok
                        } // end foreach kelompok

                        if (!$has_data_rincian) {
                            ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">Data rincian tidak ditemukan.</td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <!-- ▲▲▲ AKHIR KARTU TABEL DATA ▲▲▲ -->

</div>


<!-- =================================================================== -->
<!-- BAGIAN 3: JAVASCRIPT UNTUK GRAFIK (LOGIKA RESPONSIVE) -->
<!-- =================================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Ambil data dari PHP
        const dataFromPHP = <?php echo $grafik_data_json; ?>;

        // =============================================
        // ▼▼▼ PERBAIKAN: Gunakan $kelompok_to_display ▼▼▼
        // =============================================
        const URUTAN_KELOMPOK_JS = <?php echo json_encode($kelompok_to_display); ?>;
        const URUTAN_KELAS = <?php echo json_encode($URUTAN_KELAS); ?>;
        // Cek apakah admin kelompok (hanya 1 kelompok)
        const isSingleGroup = URUTAN_KELOMPOK_JS.length === 1;
        // =============================================
        // ▲▲▲ AKHIR PERBAIKAN ▲▲▲
        // =============================================

        Chart.register(ChartDataLabels);

        const WARNA_STATUS = {
            hadir: 'rgba(34, 197, 94, 0.7)', // Hijau
            sakit: 'rgba(59, 130, 246, 0.7)', // Biru
            izin: 'rgba(245, 158, 11, 0.7)', // Kuning
            alpa: 'rgba(239, 68, 68, 0.7)', // Merah
            kosong: 'rgba(107, 114, 128, 0.5)', // Abu sedang
        };

        // =============================================
        // ▼▼▼ LOGIKA GRAFIK BARU ▼▼▼
        // =============================================

        const chartInstances = {}; // Simpan semua instance chart
        let currentIsMobile = window.innerWidth < 768; // Cek ukuran layar awal

        /**
         * Fungsi utama untuk merender atau me-render ulang grafik
         * @param {string} kelompok - Nama kelompok (e.g., 'bintaran')
         * @param {string|null} filterKelas - Nama kelas (e.g., 'paud') atau null (untuk semua)
         */
        function renderChart(kelompok, filterKelas = null) {

            const canvasId = 'chart_' + kelompok;
            const ctx = document.getElementById(canvasId);

            if (!ctx || !dataFromPHP[kelompok]) {
                if (ctx) {
                    const context = ctx.getContext('2d');
                    context.font = "14px Arial";
                    context.fillStyle = "#9ca3af";
                    context.textAlign = "center";
                    context.fillText("Data tidak tersedia untuk kelompok " + kelompok, ctx.width / 2, ctx.height / 2);
                }
                return;
            }

            // Hancurkan chart lama jika ada
            if (chartInstances[kelompok]) {
                chartInstances[kelompok].destroy();
            }

            const kelompokData = dataFromPHP[kelompok];
            let chartLabels = [];
            const dataHadir = [],
                dataSakit = [],
                dataIzin = [],
                dataAlpa = [],
                dataKosong = [];

            // Tentukan kelas mana yang akan di-loop
            const kelasUntukDiLoop = filterKelas ? [filterKelas] : URUTAN_KELAS;

            if (filterKelas) {
                // Jika di HP, label X-axis adalah 5 status
                chartLabels = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
            } else {
                // Jika di Desktop, label X-axis adalah 6 kelas
                chartLabels = URUTAN_KELAS.map(k => {
                    const kelasLower = k.toLowerCase();
                    switch (kelasLower) {
                        case 'caberawit a':
                            return 'CBR A';
                        case 'caberawit b':
                            return 'CBR B';
                        case 'pra remaja':
                            return 'PraRemaja';
                        default:
                            return k.charAt(0).toUpperCase() + k.slice(1);
                    }
                });
            }

            // Loop data berdasarkan filter
            kelasUntukDiLoop.forEach(kelas => {
                const d = kelompokData[kelas] ?? null;

                // =============================================
                // ▼▼▼ PERBAIKAN BUG "41014" & "0.0%" ▼▼▼
                // =============================================

                let totalJadwalKBM = 0;
                let totalEntriPeriode = 0;
                let hadir = 0,
                    izin = 0,
                    sakit = 0,
                    alpa = 0,
                    kosong = 0;

                if (d) {
                    // total_jadwal_periode adalah hitungan COUNT(DISTINCT jp.id)
                    totalJadwalKBM = parseInt(d.total_jadwal_periode, 10) || 0;

                    // HANYA proses jika ada jadwal KBM
                    if (totalJadwalKBM > 0) {
                        // Paksa semua nilai menjadi Angka (Integer)
                        hadir = parseInt(d.hadir, 10) || 0;
                        izin = parseInt(d.izin, 10) || 0;
                        sakit = parseInt(d.sakit, 10) || 0;
                        alpa = parseInt(d.alpa, 10) || 0;
                        kosong = parseInt(d.kosong, 10) || 0; // 'kosong' dari SQL sudah benar

                        // Denominator adalah total dari semua status
                        totalEntriPeriode = hadir + izin + sakit + alpa;
                    }
                    // Jika totalJadwalKBM = 0, semua nilai tetap 0
                }

                // Logika Perhitungan Persen yang sudah benar
                const calculatePercent = (value, statusType) => {
                    // KASUS 1: Tidak ada jadwal KBM sama sekali.
                    if (totalJadwalKBM === 0) {
                        return null; // N/A untuk semua bar
                    }

                    // KASUS 2: Ada jadwal KBM, tapi 0 entri rekap (0 siswa/data).
                    if (totalEntriPeriode === 0) {
                        // Jika KBM ada tapi total entri 0, berarti 100% kosong
                        return (statusType === 'kosong') ? 100.0 : 0.0;
                    }

                    // KASUS 3: Ada jadwal, ada entri. Hitung normal.
                    return parseFloat(((value / totalEntriPeriode) * 100).toFixed(1));
                };

                dataHadir.push(calculatePercent(hadir, 'hadir'));
                dataSakit.push(calculatePercent(sakit, 'sakit'));
                dataIzin.push(calculatePercent(izin, 'izin'));
                dataAlpa.push(calculatePercent(alpa, 'alpa'));
                // dataKosong.push(calculatePercent(kosong, 'kosong'));

                // =============================================
                // ▲▲▲ AKHIR PERBAIKAN PERHITUNGAN ▲▲▲
                // =============================================
            });

            // =============================================
            // ▼▼▼ Konfigurasi Data & Opsi ▼▼▼
            // =============================================

            let chartDataConfig, chartOptionsConfig;

            if (filterKelas) {
                // --- Konfigurasi MOBILE (1 Kelas, 5 Bar) ---
                chartDataConfig = {
                    labels: chartLabels, // ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Kosong']
                    datasets: [{
                        label: 'Persentase (%)',
                        data: [
                            dataHadir[0], dataIzin[0], dataSakit[0], dataAlpa[0], dataKosong[0]
                        ],
                        backgroundColor: [
                            WARNA_STATUS.hadir, WARNA_STATUS.izin, WARNA_STATUS.sakit,
                            WARNA_STATUS.alpa, WARNA_STATUS.kosong
                        ]
                    }]
                };
                chartOptionsConfig = {
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: value => value + "%"
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                    }
                };

            } else {
                // --- Konfigurasi DESKTOP (6 Kelas, 5 Bar Grouped) ---
                chartDataConfig = {
                    labels: chartLabels, // ['Paud', 'CR A', ...]
                    datasets: [{
                            label: 'Hadir',
                            data: dataHadir,
                            backgroundColor: WARNA_STATUS.hadir
                        },
                        {
                            label: 'Izin',
                            data: dataIzin,
                            backgroundColor: WARNA_STATUS.izin
                        },
                        {
                            label: 'Sakit',
                            data: dataSakit,
                            backgroundColor: WARNA_STATUS.sakit
                        },
                        {
                            label: 'Alpa',
                            data: dataAlpa,
                            backgroundColor: WARNA_STATUS.alpa
                        },
                        // {
                        //     label: 'Kosong',
                        //     data: dataKosong,
                        //     backgroundColor: WARNA_STATUS.kosong
                        // }
                    ]
                };
                chartOptionsConfig = {
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: value => value + "%"
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }, // Mode index untuk grouped
                    }
                };
            }

            // Opsi universal
            const universalOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const value = context.parsed.y;
                                if (value === null || isNaN(value)) {
                                    return label + 'N/A';
                                }
                                return label + value.toFixed(1) + '%';
                            }
                        }
                    },
                    datalabels: {
                        display: true,
                        anchor: 'end',
                        align: 'top',
                        offset: -5,
                        color: '#6b7280',
                        font: {
                            weight: 'bold',
                            size: 10
                        },
                        formatter: (value, context) => {
                            if (value === null) {
                                return 'N/A';
                            }
                            const rounded = Math.round(value);
                            // Tampilkan 0% jika nilainya 0
                            if (rounded === 0) {
                                return '0%';
                            }
                            return rounded + '%';
                        },
                        display: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            // Tampilkan jika N/A (null) atau angka (termasuk 0)
                            return value === null || value >= 0;
                        }
                    }
                }
            };

            // Gabungkan opsi
            const finalOptions = {
                ...universalOptions,
                ...chartOptionsConfig,
                plugins: { // Gabungkan plugins secara manual
                    legend: chartOptionsConfig.plugins.legend || universalOptions.plugins.legend,
                    tooltip: {
                        ...universalOptions.plugins.tooltip,
                        ...chartOptionsConfig.plugins.tooltip
                    },
                    datalabels: {
                        ...universalOptions.plugins.datalabels,
                        ...chartOptionsConfig.plugins.datalabels
                    }
                },
                scales: chartOptionsConfig.scales || universalOptions.scales
            };


            // Buat Chart
            chartInstances[kelompok] = new Chart(ctx, {
                type: 'bar',
                data: chartDataConfig,
                options: finalOptions
            });
        }

        // =============================================
        // ▼▼▼ LOGIKA TAB & RESIZE ▼▼▼
        // =============================================

        // --- Inisialisasi Saat Halaman Dimuat ---
        function initializeCharts() {
            currentIsMobile = window.innerWidth < 768; // Cek ulang ukuran

            URUTAN_KELOMPOK_JS.forEach(kelompok => {
                if (currentIsMobile) {
                    // HP: Render HANYA kelas pertama (Paud)
                    renderChart(kelompok, URUTAN_KELAS[0]);
                } else {
                    // Desktop: Render semua kelas (grouped)
                    renderChart(kelompok, null);
                }
            });
        }

        // --- Panggil Inisialisasi ---
        if (Object.keys(dataFromPHP).length > 0) {
            initializeCharts();
        }

        // --- Listener untuk Klik Tab HP (Tabs Kelas) ---
        URUTAN_KELOMPOK_JS.forEach(kelompok => {
            const tabs = document.querySelectorAll(`.grafik-kelas-tab-${kelompok}`);
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Update style tab
                    tabs.forEach(t => t.classList.remove('border-cyan-500', 'text-cyan-600'));
                    tabs.forEach(t => t.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300'));
                    tab.classList.add('border-cyan-500', 'text-cyan-600');
                    tab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');

                    // Render ulang chart HANYA untuk kelompok ini dan kelas ini
                    const kelasDipilih = tab.getAttribute('data-kelas');
                    renderChart(kelompok, kelasDipilih);
                });
            });
        });

        // --- Listener untuk Klik Tab Kelompok (Hanya jika bukan admin kelompok) ---
        if (!isSingleGroup) {
            const tabButtonsKelompok = document.querySelectorAll('.tab-button-kelompok');
            const tabPanelsKelompok = document.querySelectorAll('.tab-panel-kelompok');

            tabButtonsKelompok.forEach(button => {
                button.addEventListener('click', () => {
                    // 1. Nonaktifkan semua tombol
                    tabButtonsKelompok.forEach(btn => {
                        btn.classList.remove('border-cyan-500', 'text-cyan-600');
                        btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    });
                    // 2. Aktifkan tombol yang diklik
                    button.classList.add('border-cyan-500', 'text-cyan-600');
                    button.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');

                    // 3. Sembunyikan semua panel
                    tabPanelsKelompok.forEach(panel => {
                        panel.classList.add('hidden');
                    });

                    // 4. Tampilkan panel yang dituju
                    const targetPanelId = button.getAttribute('data-tab-target');
                    const targetPanel = document.getElementById(targetPanelId);
                    if (targetPanel) {
                        targetPanel.classList.remove('hidden');

                        // 5. BUAT GRAFIK SAAT TAB DIKLIK (jika belum ada)
                        const kelompok = targetPanelId.replace('chart-panel-', '');

                        // Cek mode mobile atau tidak
                        const isMobileNow = window.innerWidth < 768;
                        let filterKelas = null; // Default desktop (semua)

                        if (isMobileNow) {
                            // Jika mobile, cari tab kelas yg aktif di kelompok yg BARU diklik
                            const activeKelasTab = document.querySelector(`.grafik-kelas-tab-${kelompok}.border-cyan-500`);
                            if (activeKelasTab) {
                                filterKelas = activeKelasTab.getAttribute('data-kelas');
                            } else {
                                filterKelas = URUTAN_KELAS[0]; // default ke yg pertama
                            }
                        }

                        renderChart(kelompok, filterKelas);
                    }
                });
            });
        }


        // --- Listener untuk Resize Layar (Debounced) ---
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const newIsMobile = window.innerWidth < 768;
                if (newIsMobile !== currentIsMobile) { // Hanya render ulang jika breakpoint berubah
                    currentIsMobile = newIsMobile;
                    console.log("Breakpoint crossed, re-rendering charts. Mobile:", currentIsMobile);

                    // Hancurkan SEMUA chart instance agar bisa digambar ulang
                    Object.keys(chartInstances).forEach(kelompok => {
                        if (chartInstances[kelompok]) {
                            chartInstances[kelompok].destroy();
                            delete chartInstances[kelompok];
                        }
                    });

                    // Tentukan kelompok mana yang aktif saat ini
                    let kelompokAktif = URUTAN_KELOMPOK_JS[0];

                    if (!isSingleGroup) {
                        const activeKelompokTab = document.querySelector('.tab-button-kelompok.border-cyan-500');
                        if (activeKelompokTab) {
                            kelompokAktif = activeKelompokTab.getAttribute('data-tab-target').replace('chart-panel-', '');
                        }
                    }

                    // Tentukan filter kelas
                    let filterKelas = null; // Default desktop (semua kelas)
                    if (currentIsMobile) {
                        // Jika mobile, cari tab kelas yg aktif di kelompok yg aktif
                        const activeKelasTab = document.querySelector(`.grafik-kelas-tab-${kelompokAktif}.border-cyan-500`);
                        if (activeKelasTab) {
                            filterKelas = activeKelasTab.getAttribute('data-kelas');
                        } else {
                            filterKelas = URUTAN_KELAS[0]; // default ke yg pertama
                        }
                    }

                    // Render ulang HANYA chart yg aktif (atau semua jika desktop)
                    if (currentIsMobile) {
                        renderChart(kelompokAktif, filterKelas);
                    } else {
                        // Desktop, render semua
                        URUTAN_KELOMPOK_JS.forEach(kelompok => {
                            renderChart(kelompok, null);
                        });
                    }

                    // Atur ulang visibilitas panel
                    const tabPanels = document.querySelectorAll('.tab-panel-kelompok');
                    if (currentIsMobile) {
                        // Di HP, sembunyikan semua panel kelompok kecuali yg aktif
                        tabPanels.forEach((panel) => {
                            panel.classList.toggle('hidden', panel.id !== `chart-panel-${kelompokAktif}`);
                        });
                    } else {
                        // Di Desktop, tampilkan SEMUA panel (jika admin bukan kelompok)
                        if (!isSingleGroup) {
                            tabPanels.forEach(panel => panel.classList.remove('hidden'));
                        }
                    }
                }
            }, 250); // Delay 250ms
        });
        // =============================================
        // ▲▲▲ AKHIR LOGIKA TAB & RESIZE ▲▲▲
        // =============================================

    });
</script>