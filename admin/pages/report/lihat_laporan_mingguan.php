<?php
// Pastikan $conn dan $_SESSION sudah ada
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil ID laporan dari URL
$id_laporan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_laporan <= 0) {
    die("ID Laporan mingguan tidak valid.");
}

// Ambil data laporan mingguan dari database
$stmt = $conn->prepare("SELECT * FROM laporan_mingguan WHERE id = ?"); // Ambil dari tabel mingguan
if (!$stmt) {
    die("Gagal prepare statement: " . $conn->error);
} // Tambah cek prepare
$stmt->bind_param("i", $id_laporan);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    die("Laporan mingguan tidak ditemukan.");
}

// Decode data statistik JSON
$data_statistik = json_decode($laporan['data_statistik'], true);
// Fallback jika JSON decode gagal
if ($data_statistik === null) {
    // Tampilkan pesan error tapi jangan hentikan script
    echo "<div class='container mx-auto p-4'><p class='text-red-500 bg-red-100 p-3 rounded'>Error: Data statistik dalam laporan ini rusak atau tidak valid.</p></div>";
    $data_statistik = [ // Sediakan struktur default
        'global' => [],
        'rincian_per_kelompok' => [],
        'daftar_alpa' => [],
        'catatan_kondisi_ai' => 'Data statistik tidak dapat dibaca.', // Beri info error
        'rekomendasi_tindakan_ai' => ''
    ];
}

// Definisikan urutan standar (harus sama dengan di form/handler)
$URUTAN_KELOMPOK = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

// Helper function untuk format tanggal (didefinisikan ulang)
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($tanggal_db)
    {
        if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
        try {
            $date = new DateTime($tanggal_db);
            $bulan_indonesia = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
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
// BAGIAN TAMPILAN HTML 
// ===================================================================
?>

<!-- OVERLAY LOADING KHUSUS EXPORT PDF -->
<div id="loadingOverlayExport" class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center max-w-sm w-full mx-4">
        <!-- Ikon PDF Berdenyut -->
        <div class="relative mx-auto mb-4 w-16 h-16 flex items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <i class="fas fa-file-pdf text-4xl text-red-600 relative z-10"></i>
        </div>

        <h3 class="text-lg font-semibold text-gray-800">Sedang Membuat PDF...</h3>
        <p class="text-sm text-gray-500 mt-2">Mohon tunggu, sistem sedang menyusun laporan Anda.</p>

        <!-- Progress Bar Indeterminate -->
        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-4 overflow-hidden">
            <div class="bg-red-600 h-2.5 rounded-full animate-progress-indeterminate"></div>
        </div>
    </div>
</div>

<!-- Tambahkan style untuk animasi progress bar jika belum ada di tailwind config -->
<style>
    @keyframes progress-indeterminate {
        0% {
            width: 0%;
            margin-left: 0%;
        }

        50% {
            width: 70%;
            margin-left: 30%;
        }

        100% {
            width: 0%;
            margin-left: 100%;
        }
    }

    .animate-progress-indeterminate {
        animation: progress-indeterminate 1.5s infinite ease-in-out;
    }
</style>

<div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
    <div class="bg-white p-6 rounded-lg shadow-md">

        <!-- Header Halaman -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Laporan Mingguan</h1>
                <p class="text-gray-600">Periode: <?php echo formatTanggalIndo($laporan['tanggal_mulai']) . ' s/d ' . formatTanggalIndo($laporan['tanggal_akhir']); ?></p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                <a href="?page=report/daftar_laporan_mingguan" class="text-sm text-cyan-600 hover:text-cyan-800 whitespace-nowrap order-2 sm:order-1">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                </a>

                <!-- TOMBOL EKSPOR DENGAN ID DAN TANPA TARGET BLANK -->
                <a href="pages/export/export_laporan_mingguan_handler.php?id=<?php echo $id_laporan; ?>"
                    id="btn-export-pdf"
                    class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center transition duration-300 order-1 sm:order-2">
                    <i class="fas fa-file-pdf mr-2"></i> Ekspor ke PDF
                </a>
            </div>
        </div>

        <!-- Meta Info -->
        <div class="mb-6 text-sm text-gray-700 space-y-1">
            <p><strong>Dibuat Oleh:</strong> <?php echo htmlspecialchars($laporan['nama_admin_pembuat']); ?></p>
            <p><strong>Waktu Dibuat:</strong> <?php echo formatTimestampIndo($laporan['timestamp_dibuat']); ?></p>
            <p><strong>Terakhir Diperbarui:</strong> <?php echo formatTimestampIndo($laporan['timestamp_diperbarui'] ?? null); ?></p>
            <p><strong>Status:</strong> <span class="font-semibold <?php echo $laporan['status_laporan'] === 'Final' ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo htmlspecialchars($laporan['status_laporan']); ?></span></p>
        </div>

        <!-- 1. Ringkasan Global -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">1. Ringkasan Mingguan</h2>
            <?php if (!empty($data_statistik['global'])): $g = $data_statistik['global']; ?>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div><strong>Total Jadwal:</strong> <?php echo $g['total_jadwal_minggu_ini'] ?? 0; ?></div>
                    <div><strong>Presensi Terisi:</strong> <?php echo $g['total_presensi_terisi'] ?? 0; ?></div>
                    <div><strong>Jurnal Terisi:</strong> <?php echo $g['total_jurnal_terisi'] ?? 0; ?></div>
                    <div class="text-red-600"><strong>Jadwal Terlewat:</strong> <?php echo $g['jadwal_terlewat'] ?? 0; ?></div>
                    <div class="text-green-600"><strong>Total Hadir:</strong> <?php echo $g['total_siswa_hadir'] ?? 0; ?></div>
                    <div class="text-blue-600"><strong>Total Izin:</strong> <?php echo $g['total_siswa_izin'] ?? 0; ?></div>
                    <div class="text-yellow-600"><strong>Total Sakit:</strong> <?php echo $g['total_siswa_sakit'] ?? 0; ?></div>
                    <div class="text-red-600"><strong>Total Alpa:</strong> <?php echo $g['total_siswa_alpa'] ?? 0; ?></div>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">Data global tidak tersedia.</p>
            <?php endif; ?>
        </div>

        <!-- ========================================================== -->
        <!-- ▼▼▼ TAMBAHAN: Kontainer Grafik ▼▼▼ -->
        <!-- ========================================================== -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">Grafik Persentase Status Kehadiran Mingguan</h2>
            <?php if (!empty($data_statistik['rincian_per_kelompok'])): ?>
                <div class="space-y-6 mt-4">
                    <?php foreach ($URUTAN_KELOMPOK as $kelompok): ?>
                        <div>
                            <h4 class="text-md font-semibold text-center text-gray-700 capitalize"><?php echo $kelompok; ?></h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg bg-white">
                                <!-- ID Canvas disesuaikan untuk mingguan -->
                                <canvas id="kehadiranChartMingguan_<?php echo $kelompok; ?>"></canvas>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">Data rincian tidak tersedia untuk grafik.</p>
            <?php endif; ?>
        </div>
        <!-- ========================================================== -->
        <!-- ▲▲▲ AKHIR KONTENER GRAFIK ▲▲▲ -->
        <!-- ========================================================== -->


        <!-- 2. Rincian per Kelas -->
        <div class="mb-8 overflow-x-auto">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">2. Rincian Mingguan per Kelas</h2>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Hadir</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Izin</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Sakit</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Alpa</th>
                        <!-- PERBAIKAN: Tambah header Kosong -->
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Kosong</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Presensi Terisi</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Jurnal Terisi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $has_rincian_html = false;
                    // Pastikan data rincian ada sebelum loop
                    if (!empty($data_statistik['rincian_per_kelompok'])) {
                        foreach ($URUTAN_KELOMPOK as $kelompok):
                            foreach ($URUTAN_KELAS as $kelas):
                                // Tampilkan baris HANYA jika datanya ada atau 0 (bukan null total)
                                if (isset($data_statistik['rincian_per_kelompok'][$kelompok][$kelas])):
                                    $has_rincian_html = true;
                                    $d = $data_statistik['rincian_per_kelompok'][$kelompok][$kelas];
                                    $totalJadwalMinggu = $d['total_jadwal_minggu'] ?? 0;
                                    $hadir = $d['hadir'] ?? 0;
                                    $izin = $d['izin'] ?? 0;
                                    $sakit = $d['sakit'] ?? 0;
                                    $alpa = $d['alpa'] ?? 0;
                                    // Hitung kosong dari data jika ada, fallback ke perhitungan
                                    $kosong = $d['kosong'] ?? max(0, $totalJadwalMinggu - ($hadir + $izin + $sakit + $alpa));
                                    $presensiTerisi = $d['jadwal_presensi_terisi'] ?? 0;
                                    $jurnalTerisi = $d['jadwal_jurnal_terisi'] ?? 0;

                                    if ($totalJadwalMinggu === 0): ?>
                                        <tr class="bg-gray-50">
                                            <td class="px-4 py-2 font-medium capitalize text-gray-500"><?php echo $kelompok; ?></td>
                                            <td class="px-4 py-2 capitalize text-gray-500"><?php echo $kelas; ?></td>
                                            <!-- PERBAIKAN: colspan 7 -->
                                            <td colspan="7" class="px-4 py-2 text-center text-gray-400 italic">Tidak ada jadwal</td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td class="px-4 py-2 font-medium capitalize"><?php echo $kelompok; ?></td>
                                            <td class="px-4 py-2 capitalize"><?php echo $kelas; ?></td>
                                            <td class="px-4 py-2 text-center text-green-600 font-semibold"><?php echo $hadir; ?></td>
                                            <td class="px-4 py-2 text-center text-yellow-600 font-semibold"><?php echo $izin; ?></td>
                                            <td class="px-4 py-2 text-center text-blue-600 font-semibold"><?php echo $sakit; ?></td>
                                            <td class="px-4 py-2 text-center text-red-600 font-semibold"><?php echo $alpa; ?></td>
                                            <!-- PERBAIKAN: Tampilkan Kolom Kosong -->
                                            <td class="px-4 py-2 text-center text-gray-500 font-semibold"><?php echo $kosong; ?></td>
                                            <td class="px-4 py-2 text-center <?php echo $presensiTerisi < $totalJadwalMinggu ? 'text-red-500' : ''; ?>">
                                                <?php echo $presensiTerisi . '/' . $totalJadwalMinggu; ?>
                                            </td>
                                            <td class="px-4 py-2 text-center <?php echo $jurnalTerisi < $totalJadwalMinggu ? 'text-red-500' : ''; ?>">
                                                <?php echo $jurnalTerisi . '/' . $totalJadwalMinggu; ?>
                                            </td>
                                        </tr>
                        <?php endif; // Akhir cek totalJadwalMinggu
                                endif; // Akhir isset
                            endforeach; // Akhir loop kelas
                        endforeach; // Akhir loop kelompok
                    } // Akhir cek empty rincian
                    if (!$has_rincian_html): ?>
                        <!-- PERBAIKAN: colspan 9 -->
                        <tr>
                            <td colspan="9" class="text-center py-4 text-gray-500">Tidak ada data rincian ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 3. Daftar Siswa Alpa -->
        <div class="mb-8 overflow-x-auto">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2 text-red-600">3. Daftar Siswa Alpa Minggu Ini</h2>
            <?php if (!empty($data_statistik['daftar_alpa'])): ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Nama Siswa</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>
                            <!-- PERBAIKAN: Tambah header Jml Alpa -->
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Jml Alpa</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Nama Orang Tua</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">No. HP Orang Tua</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($data_statistik['daftar_alpa'] as $siswa): ?>
                            <tr>
                                <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($siswa['nama_lengkap'] ?? ''); ?></td>
                                <td class="px-4 py-2 capitalize"><?php echo htmlspecialchars($siswa['kelompok'] ?? ''); ?></td>
                                <td class="px-4 py-2 capitalize"><?php echo htmlspecialchars($siswa['kelas'] ?? ''); ?></td>
                                <!-- PERBAIKAN: Tampilkan Jml Alpa -->
                                <td class="px-4 py-2 text-center text-red-600 font-bold"><?php echo htmlspecialchars($siswa['jumlah_alpa'] ?? 0); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($siswa['nama_orang_tua'] ?? '-'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($siswa['nomor_hp_orang_tua'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-sm text-gray-600 mt-2 p-4 border rounded-md bg-green-50 text-green-700">Tidak ada siswa yang tercatat alpa minggu ini.</p>
            <?php endif; ?>
        </div>

        <!-- 4. Catatan Kondisi -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">4. Catatan Kondisi Mingguan</h2>
            <div class="p-4 border rounded-md bg-gray-50 text-gray-800 text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($laporan['catatan_kondisi'])); ?>
            </div>
        </div>

        <!-- 5. Rekomendasi Tindakan -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">5. Rekomendasi Tindakan Mingguan</h2>
            <div class="p-4 border rounded-md bg-gray-50 text-gray-800 text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($laporan['rekomendasi_tindakan'])); ?>
            </div>
        </div>

        <!-- 6. Tindak Lanjut (jika ada) -->
        <?php if (!empty($laporan['tindak_lanjut_ketua'])): ?>
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">6. Tindak Lanjut</h2>
                <div class="p-4 border rounded-md bg-blue-50 text-blue-800 text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($laporan['tindak_lanjut_ketua'])); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ambil data statistik dari variabel PHP
        const dataStatistik = <?php echo isset($data_statistik['rincian_per_kelompok']) ? json_encode($data_statistik['rincian_per_kelompok']) : 'null'; ?>;
        const urutanKelompok = <?php echo json_encode($URUTAN_KELOMPOK); ?>;
        const urutanKelas = <?php echo json_encode($URUTAN_KELAS); ?>;

        // Daftarkan plugin
        Chart.register(ChartDataLabels);

        // Definisikan Warna Status 
        const WARNA_STATUS = {
            hadir: 'rgba(34, 197, 94, 0.7)',
            sakit: 'rgba(59, 130, 246, 0.7)',
            izin: 'rgba(245, 158, 11, 0.7)',
            alpa: 'rgba(239, 68, 68, 0.7)',
            kosong: 'rgba(107, 114, 128, 0.5)',
        };

        // Cek jika dataStatistik ada sebelum loop
        if (dataStatistik) {
            // Loop untuk membuat grafik per kelompok
            urutanKelompok.forEach(kelompok => {
                const canvasId = 'kehadiranChartMingguan_' + kelompok; // ID Canvas Mingguan
                const ctx = document.getElementById(canvasId);

                // Cek jika ada data untuk kelompok ini
                if (ctx && dataStatistik[kelompok]) {
                    const chartLabels = [];
                    // Siapkan array data untuk setiap status
                    const dataHadir = [],
                        dataSakit = [],
                        dataIzin = [],
                        dataAlpa = [],
                        dataKosong = [];
                    const kelompokData = dataStatistik[kelompok];

                    // Loop sesuai urutan kelas standar
                    urutanKelas.forEach(kelas => {
                        chartLabels.push(kelas.charAt(0).toUpperCase() + kelas.slice(1));
                        const d = kelompokData[kelas] ?? null;
                        let totalJadwalMinggu = 0;
                        let totalEntry = 0;
                        let hadir = 0,
                            izin = 0,
                            sakit = 0,
                            alpa = 0,
                            kosong = 0;

                        if (d) {
                            totalJadwalMinggu = d.total_jadwal_minggu || 0;
                            totalEntry = (d.hadir + d.izin + d.sakit + d.alpa + d.kosong);
                            if (totalJadwalMinggu > 0) {
                                hadir = d.hadir || 0;
                                izin = d.izin || 0;
                                sakit = d.sakit || 0;
                                alpa = d.alpa || 0;
                                kosong = d.kosong !== undefined ? (d.kosong || 0) : Math.max(0, totalJadwalMinggu - (hadir + izin + sakit + alpa));
                            }
                        }
                        // Hitung persentase
                        const persen = (val) => totalJadwalMinggu > 0 ? parseFloat(((val / totalEntry) * 100).toFixed(1)) : null;
                        dataHadir.push(persen(hadir));
                        dataSakit.push(persen(sakit));
                        dataIzin.push(persen(izin));
                        dataAlpa.push(persen(alpa));
                        dataKosong.push(persen(kosong));
                    });

                    // Buat chart Grouped Bar (sama seperti di form)
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                    label: 'Hadir',
                                    data: dataHadir,
                                    backgroundColor: WARNA_STATUS.hadir,
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
                                {
                                    label: 'Kosong',
                                    data: dataKosong,
                                    backgroundColor: WARNA_STATUS.kosong
                                }
                            ]
                        },
                        options: { // Opsi sama persis seperti di form
                            responsive: true,
                            maintainAspectRatio: false,
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
                                        callback: function(value) {
                                            return value + "%"
                                        }
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
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
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
                                    display: true, // Selalu coba tampilkan
                                    anchor: 'end',
                                    // align: 'top',
                                    align: (value, context) => {
                                        if (value === null) return 'center';
                                        return value >= 90 ? 'bottom' : 'top';
                                    },
                                    offset: -5, // Jarak sedikit di atas bar
                                    font: {
                                        weight: 'bold',
                                        size: 10 // PERBESAR UKURAN FONT
                                    },
                                    // Formatter untuk menampilkan nilai + '%' atau null jika 0/null
                                    formatter: (value, context) => {
                                        // Jika nilainya null (tidak ada jadwal), tampilkan N/A
                                        if (value === null) {
                                            return 'N/A';
                                        }
                                        // Jika nilainya > 0, tampilkan persentase bulat
                                        if (value >= 0) {
                                            return Math.round(value) + '%';
                                        }
                                        // Jika nilainya 0, jangan tampilkan apa-apa (return null atau string kosong)
                                        // return null;
                                    },
                                    // Warna teks: Putih jika di bar 'Kosong', Abu2 jika 'N/A', Hitam jika lainnya
                                    color: (context) => {
                                        // Safety check
                                        if (!context || context.datasetIndex === undefined || !context.dataset || !context.dataset.data || context.dataIndex === undefined) {
                                            return '#000'; // Default hitam
                                        }
                                        const value = context.dataset.data[context.dataIndex];
                                        if (value === null) return '#9ca3af'; // N/A abu-abu
                                        // Putih jika di bar kosong (index 4)
                                        return context.datasetIndex === 4 ? '#000' : '#000';
                                    },
                                    display: function(context) {
                                        // Ambil nilai mentah dari data
                                        const value = context.dataset.data[context.dataIndex];
                                    }
                                }
                            }
                        }
                    });
                } else if (ctx) {
                    // Tampilkan pesan jika data kelompok tidak ada
                    const context = ctx.getContext('2d');
                    context.font = "14px Arial";
                    context.fillStyle = "#9ca3af";
                    context.textAlign = "center";
                    context.fillText("Data tidak tersedia", ctx.width / 2, ctx.height / 2);
                }
            }); // Akhir loop kelompok
        } else {
            console.warn("[JS] Data rincian per kelompok tidak tersedia untuk membuat grafik.");
            // Optional: Sembunyikan seluruh kontainer grafik jika tidak ada data sama sekali
            const chartGrid = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2');
            if (chartGrid) chartGrid.parentElement.style.display = 'none';
        }

        // ==========================================================
        // ▼▼▼ LOGIKA DOWNLOAD PDF VIA AJAX + LOADER ▼▼▼
        // ==========================================================
        const btnExport = document.getElementById('btn-export-pdf');
        const loaderExport = document.getElementById('loadingOverlayExport');

        if (btnExport) {
            btnExport.addEventListener('click', function(e) {
                e.preventDefault(); // Mencegah pindah halaman langsung

                // 1. Tampilkan Loader
                loaderExport.classList.remove('hidden');

                const url = this.href;
                // Nama file default (bisa disesuaikan)
                const filename = 'Laporan_Mingguan_<?php echo $laporan['tanggal_mulai'] . '_sd_' . $laporan['tanggal_akhir']; ?>.pdf';

                // 2. Fetch Blob
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Terjadi kesalahan saat membuat PDF (Status ' + response.status + ')');
                        }
                        return response.blob();
                    })
                    .then(blob => {
                        // 3. Buat Link Download Sementara
                        const downloadUrl = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = downloadUrl;
                        a.download = filename;

                        document.body.appendChild(a);
                        a.click();

                        // Bersihkan
                        window.URL.revokeObjectURL(downloadUrl);
                        document.body.removeChild(a);
                    })
                    .catch(error => {
                        console.error('Download Error:', error);
                        Swal.fire({
                            title: 'Gagal Ekspor',
                            text: error.message,
                            icon: 'error'
                        });
                    })
                    .finally(() => {
                        // 4. Sembunyikan Loader
                        loaderExport.classList.add('hidden');
                    });
            });
        }
    });
</script>
<!-- ========================================================== -->
<!-- ▲▲▲ AKHIR SCRIPT CHART.JS ▲▲▲ -->
<!-- ========================================================== -->

<?php $conn->close(); ?>