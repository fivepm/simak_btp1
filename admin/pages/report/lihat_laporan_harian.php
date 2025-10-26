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
    die("ID Laporan tidak valid.");
}

// Ambil data laporan dari database
$stmt = $conn->prepare("SELECT * FROM laporan_harian WHERE id = ?");
$stmt->bind_param("i", $id_laporan);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    die("Laporan tidak ditemukan.");
}

// Decode data statistik JSON
$data_statistik = json_decode($laporan['data_statistik'], true);
// Fallback jika JSON decode gagal
if ($data_statistik === null) {
    $data_statistik = [
        'global' => [],
        'rincian_per_kelompok' => [],
        'daftar_alpa' => [],
        'catatan_kondisi_ai' => 'Error: Data statistik rusak.',
        'rekomendasi_tindakan_ai' => ''
    ];
}

// Definisikan urutan standar (harus sama dengan di form)
$URUTAN_KELOMPOK = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

// Helper function untuk format tanggal (jika belum ada)
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
function formatTimestampIndo($timestamp_db)
{
    if (empty($timestamp_db) || $timestamp_db === '0000-00-00 00:00:00') return '-';
    try {
        // Set timezone ke UTC dulu karena data dari DB adalah UTC
        $date = new DateTime($timestamp_db, new DateTimeZone('UTC'));
        // Ubah timezone ke Asia/Jakarta
        $date->setTimezone(new DateTimeZone('Asia/Jakarta'));
        // Format dengan tanggal Indonesia
        return formatTanggalIndo($date->format('Y-m-d')) . ' ' . $date->format('H:i') . ' WIB';
    } catch (Exception $e) {
        return '-';
    }
}

// ===================================================================
// BAGIAN TAMPILAN HTML 
// ===================================================================
?>
<div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
    <div class="bg-white p-6 rounded-lg shadow-md">

        <!-- Header Halaman -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Laporan Harian</h1>
                <p class="text-gray-600">Tanggal: <?php echo formatTanggalIndo($laporan['tanggal_laporan']); ?></p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                <a href="?page=report/daftar_laporan_harian" class="text-sm text-cyan-600 hover:text-cyan-800 whitespace-nowrap order-2 sm:order-1">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                </a>
                <!-- PERBAIKAN: Link ke handler export PDF -->
                <a href="pages/export/export_laporan_harian_handler.php?id=<?php echo $id_laporan; ?>"
                    class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center transition duration-300 order-1 sm:order-2"
                    target="_blank"> <!-- target blank agar tidak mengganggu halaman -->
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
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">1. Ringkasan Global</h2>
            <?php if (!empty($data_statistik['global'])): $g = $data_statistik['global']; ?>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div><strong>Total Jadwal:</strong> <?php echo $g['total_jadwal_hari_ini']; ?></div>
                    <div><strong>Presensi Terisi:</strong> <?php echo $g['total_presensi_terisi']; ?></div>
                    <div><strong>Jurnal Terisi:</strong> <?php echo $g['total_jurnal_terisi']; ?></div>
                    <div class="text-red-600"><strong>Jadwal Terlewat:</strong> <?php echo $g['jadwal_terlewat']; ?></div>
                    <div class="text-green-600"><strong>Total Hadir:</strong> <?php echo $g['total_siswa_hadir']; ?></div>
                    <div class="text-blue-600"><strong>Total Izin:</strong> <?php echo $g['total_siswa_izin']; ?></div>
                    <div class="text-yellow-600"><strong>Total Sakit:</strong> <?php echo $g['total_siswa_sakit']; ?></div>
                    <div class="text-red-600"><strong>Total Alpa:</strong> <?php echo $g['total_siswa_alpa']; ?></div>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">Data global tidak tersedia.</p>
            <?php endif; ?>
        </div>

        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">Grafik Persentase Kehadiran</h2>
            <?php if (!empty($data_statistik['rincian_per_kelompok'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <?php foreach ($URUTAN_KELOMPOK as $kelompok): ?>
                        <div>
                            <h4 class="text-md font-semibold text-center text-gray-700 capitalize"><?php echo $kelompok; ?></h4>
                            <div class="relative h-72 w-full mt-2 p-4 border rounded-lg bg-white">
                                <canvas id="kehadiranChart_<?php echo $kelompok; ?>"></canvas>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">Data rincian tidak tersedia untuk grafik.</p>
            <?php endif; ?>
        </div>

        <!-- 2. Rincian per Kelas -->
        <div class="mb-8 overflow-x-auto">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">2. Rincian per Kelas</h2>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Hadir</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Izin</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Sakit</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Alpa</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Presensi</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-500">Jurnal</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $has_rincian_html = false;
                    // Pastikan data rincian ada sebelum loop
                    if (!empty($data_statistik['rincian_per_kelompok'])) {
                        foreach ($URUTAN_KELOMPOK as $kelompok):
                            foreach ($URUTAN_KELAS as $kelas):
                                if (isset($data_statistik['rincian_per_kelompok'][$kelompok][$kelas])):
                                    $has_rincian_html = true;
                                    $d = $data_statistik['rincian_per_kelompok'][$kelompok][$kelas];
                                    if ($d['total_jadwal'] === 0): ?>
                                        <tr class="bg-gray-50">
                                            <td class="px-4 py-2 font-medium capitalize text-gray-500"><?php echo $kelompok; ?></td>
                                            <td class="px-4 py-2 capitalize text-gray-500"><?php echo $kelas; ?></td>
                                            <td colspan="6" class="px-4 py-2 text-center text-gray-400 italic">Tidak ada jadwal</td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td class="px-4 py-2 font-medium capitalize"><?php echo $kelompok; ?></td>
                                            <td class="px-4 py-2 capitalize"><?php echo $kelas; ?></td>
                                            <td class="px-4 py-2 text-center text-green-600 font-semibold"><?php echo $d['hadir']; ?></td>
                                            <td class="px-4 py-2 text-center text-blue-600 font-semibold"><?php echo $d['izin']; ?></td>
                                            <td class="px-4 py-2 text-center text-yellow-600 font-semibold"><?php echo $d['sakit']; ?></td>
                                            <td class="px-4 py-2 text-center text-red-600 font-semibold"><?php echo $d['alpa']; ?></td>
                                            <?php
                                            $presensiIconHtml = ($d['jadwal_terisi'] === $d['total_jadwal']) ? '<span class="text-green-500 font-bold">✓</span>' : '<span class="text-red-500 font-bold">X</span>';
                                            $jurnalIconHtml = ($d['jurnal_terisi'] === $d['total_jadwal']) ? '<span class="text-green-500 font-bold">✓</span>' : '<span class="text-red-500 font-bold">X</span>';
                                            ?>
                                            <td class="px-4 py-2 text-center"><?php echo $presensiIconHtml; ?></td>
                                            <td class="px-4 py-2 text-center"><?php echo $jurnalIconHtml; ?></td>
                                        </tr>
                        <?php endif;
                                endif;
                            endforeach;
                        endforeach;
                    } // Akhir cek empty rincian
                    if (!$has_rincian_html): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-gray-500">Tidak ada data rincian ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 3. Daftar Siswa Alpa -->
        <div class="mb-8 overflow-x-auto">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2 text-red-600">3. Daftar Siswa Alpa</h2>
            <?php if (!empty($data_statistik['daftar_alpa'])): ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Nama Siswa</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Kelompok</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Kelas</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Nama Orang Tua</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">No. HP Orang Tua</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($data_statistik['daftar_alpa'] as $siswa): ?>
                            <tr>
                                <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                <td class="px-4 py-2 capitalize"><?php echo htmlspecialchars($siswa['kelompok']); ?></td>
                                <td class="px-4 py-2 capitalize"><?php echo htmlspecialchars($siswa['kelas']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($siswa['nama_orang_tua'] ?? '-'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($siswa['nomor_hp_orang_tua'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-sm text-gray-600 mt-2 p-4 border rounded-md bg-green-50 text-green-700">Tidak ada siswa yang tercatat alpa pada tanggal ini.</p>
            <?php endif; ?>
        </div>

        <!-- 4. Catatan Kondisi -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">4. Catatan Kondisi</h2>
            <div class="p-4 border rounded-md bg-gray-50 text-gray-800 text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($laporan['catatan_kondisi'])); ?>
            </div>
        </div>

        <!-- 5. Rekomendasi Tindakan -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">5. Rekomendasi Tindakan</h2>
            <div class="p-4 border rounded-md bg-gray-50 text-gray-800 text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($laporan['rekomendasi_tindakan'])); ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ambil data statistik dari variabel PHP
        const dataStatistik = <?php echo json_encode($data_statistik['rincian_per_kelompok'] ?? []); ?>;
        const urutanKelompok = <?php echo json_encode($URUTAN_KELOMPOK); ?>;
        const urutanKelas = <?php echo json_encode($URUTAN_KELAS); ?>;

        // Daftarkan plugin
        Chart.register(ChartDataLabels);

        // Fungsi helper warna (sama seperti di form)
        const getBarColor = (value) => {
            if (value === null) return 'rgba(156, 163, 175, 0.6)'; // Abu-abu
            if (value < 50) return 'rgba(239, 68, 68, 0.6)'; // Merah
            if (value >= 50 && value <= 75) return 'rgba(245, 158, 11, 0.6)'; // Kuning
            return 'rgba(34, 197, 94, 0.6)'; // Hijau
        };
        const getBorderColor = (value) => {
            if (value === null) return 'rgba(156, 163, 175, 1)'; // Abu-abu solid
            if (value < 50) return 'rgba(239, 68, 68, 1)'; // Merah solid
            if (value >= 50 && value <= 75) return 'rgba(245, 158, 11, 1)'; // Kuning solid
            return 'rgba(34, 197, 94, 1)'; // Hijau solid
        };

        // Loop untuk membuat grafik per kelompok
        urutanKelompok.forEach(kelompok => {
            const canvasId = 'kehadiranChart_' + kelompok;
            const ctx = document.getElementById(canvasId);

            if (ctx && dataStatistik[kelompok]) {
                const chartLabels = [];
                const chartData = [];
                const kelompokData = dataStatistik[kelompok];

                // Loop sesuai urutan kelas standar
                urutanKelas.forEach(kelas => {
                    const d = kelompokData[kelas] ?? null; // Ambil data atau null jika tidak ada

                    chartLabels.push(kelas.charAt(0).toUpperCase() + kelas.slice(1));

                    let percentage = null;
                    if (d) {
                        const totalTerisi = d.hadir + d.izin + d.sakit + d.alpa;
                        if (d.total_jadwal > 0) {
                            percentage = (totalTerisi > 0) ? (d.hadir / totalTerisi) * 100 : 0;
                        }
                    }
                    chartData.push(percentage !== null ? Math.round(percentage) : null);
                });

                // Buat chart
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Kehadiran (%)',
                            data: chartData,
                            backgroundColor: chartData.map(value => getBarColor(value)),
                            borderColor: chartData.map(value => getBorderColor(value)),
                            borderWidth: 1
                        }]
                    },
                    options: { // Opsi sama persis seperti di form
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        const value = context.raw;
                                        if (value === null) {
                                            return label + 'N/A';
                                        }
                                        return label + value + '%';
                                    }
                                }
                            },
                            datalabels: {
                                anchor: (context) => 'end',
                                align: (context) => {
                                    const value = context.dataset.data[context.dataIndex];
                                    if (value === null) return 'center';
                                    return value >= 90 ? 'bottom' : 'top';
                                },
                                offset: (context) => {
                                    const value = context.dataset.data[context.dataIndex];
                                    if (value === null) return 0;
                                    return value >= 90 ? 8 : -6;
                                },
                                formatter: (value, context) => {
                                    if (value === null) {
                                        return 'N/A';
                                    }
                                    return value + '%';
                                },
                                color: (context) => {
                                    const value = context.dataset.data[context.dataIndex];
                                    if (value === null) return '#9ca3af';
                                    return value >= 90 ? '#ffffff' : '#6b7280';
                                },
                                font: {
                                    weight: 'bold'
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
        });
    });
</script>
<?php $conn->close(); ?>