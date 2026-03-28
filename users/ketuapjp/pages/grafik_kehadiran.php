<?php
// === FILE FRONTEND: grafik_kehadiran.php ===
$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

$periode_list = [];
$res_per = $conn->query("SELECT id, nama_periode FROM periode WHERE status != 'Arsip' ORDER BY tanggal_mulai DESC");
while ($row = $res_per->fetch_assoc()) $periode_list[] = $row;
$selected_periode_id = $_GET['periode_id'] ?? ($periode_list[0]['id'] ?? null);

$URUTAN_KELOMPOK = ($ketuapjp_tingkat === 'kelompok') ? [strtolower($ketuapjp_kelompok)] : ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-wrap gap-4">
        <a href="?page=dashboard" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition"><i class="fas fa-arrow-left mr-2"></i> Dashboard</a>
        <a href="?page=grafik_ketercapaian" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-4 py-2 rounded-lg font-medium transition"><i class="fa-solid fa-book-open mr-2"></i> Grafik Materi</a>
    </div>

    <!-- Filter -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-chart-pie text-indigo-500 mr-2"></i> Grafik Rincian Kehadiran</h1>
            <form method="GET" action="" class="flex items-center gap-2 w-full md:w-auto">
                <input type="hidden" name="page" value="grafik_kehadiran">
                <select name="periode_id" class="w-full md:w-64 py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php foreach ($periode_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($selected_periode_id == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_periode']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <!-- Container Grafik & Skeleton Loader -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative min-h-[400px]">
        <div id="chartLoader" class="absolute inset-0 flex items-center justify-center bg-white z-10 rounded-2xl">
            <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <?php if (count($URUTAN_KELOMPOK) > 1): ?>
            <!-- Tabs Kelompok -->
            <div class="flex space-x-2 border-b border-gray-200 mb-6 overflow-x-auto pb-2" id="tabs_kelompok">
                <?php foreach ($URUTAN_KELOMPOK as $i => $kel): ?>
                    <button class="tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg transition <?= $i === 0 ? 'bg-indigo-50 text-indigo-700 border-b-2 border-indigo-600' : 'text-gray-500 hover:bg-gray-50' ?>" data-target="<?= $kel ?>"><?= ucwords($kel) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Container Canvas -->
        <div id="canvas_container" class="relative h-80 md:h-96 w-full"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Chart.register(ChartDataLabels);

        const periodeId = '<?= $selected_periode_id ?>';
        const urutanKelompok = <?= json_encode($URUTAN_KELOMPOK) ?>;
        const urutanKelas = <?= json_encode($URUTAN_KELAS) ?>;

        let chartInstance = null;
        let chartDataRaw = {};
        let activeKelompok = urutanKelompok[0];

        // Setup Tabs
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                tabBtns.forEach(b => {
                    b.classList.remove('bg-indigo-50', 'text-indigo-700', 'border-b-2', 'border-indigo-600');
                    b.classList.add('text-gray-500');
                });
                this.classList.remove('text-gray-500');
                this.classList.add('bg-indigo-50', 'text-indigo-700', 'border-b-2', 'border-indigo-600');
                activeKelompok = this.dataset.target;
                renderChart(activeKelompok);
            });
        });

        // Fetch Data AJAX
        fetch(`pages/ajax_grafik_kehadiran.php?action=get_data&periode_id=${periodeId}`) // Sesuaikan path
            .then(res => res.json())
            .then(res => {
                document.getElementById('chartLoader').classList.add('hidden');
                if (res.status === 'success') {
                    chartDataRaw = res.data;
                    renderChart(activeKelompok);
                }
            });

        function renderChart(kelompok) {
            const container = document.getElementById('canvas_container');
            container.innerHTML = '<canvas id="mainChart"></canvas>'; // Reset canvas
            const ctx = document.getElementById('mainChart').getContext('2d');

            if (chartInstance) chartInstance.destroy();

            if (!chartDataRaw[kelompok]) {
                ctx.font = "14px Arial";
                ctx.fillStyle = "#9ca3af";
                ctx.textAlign = "center";
                ctx.fillText("Data absensi belum tersedia.", container.clientWidth / 2, container.clientHeight / 2);
                return;
            }

            let dHadir = [],
                dIzin = [],
                dSakit = [],
                dAlpa = [];
            let labels = [];

            urutanKelas.forEach(kls => {
                labels.push(kls.toUpperCase().replace('CABERAWIT', 'CBR').replace('PRA REMAJA', 'PRA-R'));
                const dataDB = chartDataRaw[kelompok][kls];

                if (!dataDB || parseInt(dataDB.total_jadwal_periode) === 0) {
                    dHadir.push(null);
                    dIzin.push(null);
                    dSakit.push(null);
                    dAlpa.push(null);
                } else {
                    const tot = parseInt(dataDB.hadir) + parseInt(dataDB.izin) + parseInt(dataDB.sakit) + parseInt(dataDB.alpa);
                    if (tot === 0) {
                        dHadir.push(0);
                        dIzin.push(0);
                        dSakit.push(0);
                        dAlpa.push(0);
                    } else {
                        dHadir.push(parseFloat(((parseInt(dataDB.hadir) / tot) * 100).toFixed(1)));
                        dIzin.push(parseFloat(((parseInt(dataDB.izin) / tot) * 100).toFixed(1)));
                        dSakit.push(parseFloat(((parseInt(dataDB.sakit) / tot) * 100).toFixed(1)));
                        dAlpa.push(parseFloat(((parseInt(dataDB.alpa) / tot) * 100).toFixed(1)));
                    }
                }
            });

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Hadir',
                            data: dHadir,
                            backgroundColor: 'rgba(34, 197, 94, 0.8)'
                        },
                        {
                            label: 'Izin',
                            data: dIzin,
                            backgroundColor: 'rgba(245, 158, 11, 0.8)'
                        },
                        {
                            label: 'Sakit',
                            data: dSakit,
                            backgroundColor: 'rgba(59, 130, 246, 0.8)'
                        },
                        {
                            label: 'Alpa',
                            data: dAlpa,
                            backgroundColor: 'rgba(239, 68, 68, 0.8)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            max: 100,
                            ticks: {
                                callback: v => v + '%'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.raw === null) return ctx.dataset.label + ': N/A';
                                    return ctx.dataset.label + ': ' + ctx.raw + '%';
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: '#6b7280',
                            font: {
                                size: 9,
                                weight: 'bold'
                            },
                            formatter: (val) => val === null ? '' : Math.round(val) + '%'
                        }
                    }
                }
            });
        }

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                renderChart(activeKelompok);
            }, 250);
        });
    });
</script>