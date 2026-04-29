<?php
// === FILE FRONTEND STANDALONE: grafik_kehadiran.php ===
session_start();

// 🔐 SECURITY CHECK UNTUK ROLE PEMBINA
$allowed_roles = ['pembina'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../../index"); // Sesuaikan dengan path login Anda
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$pembina_level = $_SESSION['user_tingkat'] ?? 'desa';
$pembina_kelompok = $_SESSION['user_kelompok'] ?? '';

$periode_list = [];
$res_per = $conn->query("SELECT id, nama_periode FROM periode WHERE status != 'Arsip' ORDER BY tanggal_mulai DESC");
while ($row = $res_per->fetch_assoc()) $periode_list[] = $row;
$selected_periode_id = $_GET['periode_id'] ?? ($periode_list[0]['id'] ?? null);

$URUTAN_KELOMPOK = ($pembina_level === 'kelompok') ? [strtolower($pembina_kelompok)] : ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
$URUTAN_KELAS = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

$pageTitle = 'Grafik Rincian Kehadiran';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SIMAK Pembina</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo_web_bg.png">

    <!-- Tailwind, Alpine, FontAwesome, SweetAlert -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/sweetalert2.min.css">
</head>

<body class="bg-gray-100 font-sans flex flex-col min-h-screen">

    <!-- ========================================== -->
    <!-- HEADER STAND-ALONE (TANPA SIDEBAR/PROFILE) -->
    <!-- ========================================== -->
    <header class="flex justify-between items-center p-6 bg-white border-b shadow-sm sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-md">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
        </div>

        <!-- User Dropdown -->
        <div class="relative">
            <button id="userMenuButton" class="flex items-center space-x-3 focus:outline-none bg-gray-50 hover:bg-gray-100 py-2 px-3 rounded-full transition border border-gray-200">
                <img class="w-8 h-8 rounded-full object-cover border-2 border-indigo-200 bg-white" src="../../uploads/profiles/<?php echo htmlspecialchars($_SESSION['foto_profil'] ?? 'default.png'); ?>" alt="Foto Profil" onerror="this.onerror=null; this.src='../../uploads/profiles/default.png';">
                <span class="hidden md:inline font-semibold text-sm text-gray-700">
                    <?php echo htmlspecialchars($_SESSION['user_nama_panggilan'] ?? 'Pembina'); ?>
                </span>
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50 hidden transition-all">
                <a href="#" onclick="event.preventDefault(); handleLogout();" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-medium transition">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout Sistem
                </a>
            </div>
        </div>
    </header>

    <!-- ========================================== -->
    <!-- MAIN CONTENT                               -->
    <!-- ========================================== -->
    <main class="flex-1 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Navigation Buttons -->
        <div class="mb-6 flex flex-wrap gap-4">
            <a href="/users/pembina/" class="bg-white border border-gray-200 shadow-sm hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium transition"><i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard</a>
            <a href="grafik_ketercapaian" class="bg-emerald-50 border border-emerald-100 shadow-sm hover:bg-emerald-100 text-emerald-700 px-4 py-2 rounded-lg font-medium transition"><i class="fa-solid fa-book-open mr-2"></i> Buka Grafik Materi</a>
        </div>

        <!-- Filter -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-chart-pie text-indigo-500 mr-2"></i> Filter Kehadiran</h2>
                <form method="GET" action="" class="flex items-center gap-2 w-full md:w-auto">
                    <select name="periode_id" class="w-full md:w-64 py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selected_periode_id == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_periode']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <!-- Container Grafik -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative min-h-[400px]">
            <div id="chartLoader" class="absolute inset-0 flex items-center justify-center bg-white z-10 rounded-2xl">
                <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <?php if (count($URUTAN_KELOMPOK) > 1): ?>
                <!-- Tabs Kelompok (Untuk Level Desa) -->
                <div class="flex space-x-2 border-b border-gray-200 mb-6 overflow-x-auto pb-2" id="tabs_kelompok">
                    <?php foreach ($URUTAN_KELOMPOK as $i => $kel): ?>
                        <button class="tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg transition <?= $i === 0 ? 'bg-indigo-50 text-indigo-700 border-b-2 border-indigo-600' : 'text-gray-500 hover:bg-gray-50' ?>" data-target="<?= $kel ?>"><?= ucwords($kel) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Wrapper Canvas Dinamis -->
            <div id="canvas_wrapper" class="w-full"></div>
        </div>
    </main>

    <!-- Overlay Logout -->
    <div id="logout-overlay" class="fixed inset-0 z-[999] flex flex-col items-center justify-center bg-gray-900 bg-opacity-75 transition-opacity duration-300 ease-in-out opacity-0 hidden">
        <div class="w-16 h-16 border-4 border-t-4 border-t-cyan-500 border-gray-600 rounded-full animate-spin"></div>
        <p class="mt-4 text-white text-lg font-semibold">Memproses Logout...</p>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/sweetalert2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <script>
        // Header & Logout Script
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('userMenuButton');
            const menu = document.getElementById('userMenu');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            window.addEventListener('click', () => {
                if (!menu.classList.contains('hidden')) menu.classList.add('hidden');
            });
        });

        function handleLogout() {
            const overlay = document.getElementById('logout-overlay');
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
            }, 10);
            setTimeout(() => {
                fetch('../../auth/logout.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        setTimeout(() => {
                            window.location.href = '../../';
                        }, 500);
                    })
                    .catch(error => {
                        window.location.href = '../../';
                    });
            }, 500);
        }

        // Chart Logic
        document.addEventListener('DOMContentLoaded', function() {
            Chart.register(ChartDataLabels);

            const periodeId = '<?= $selected_periode_id ?>';
            const urutanKelompok = <?= json_encode($URUTAN_KELOMPOK) ?>;
            const urutanKelas = <?= json_encode($URUTAN_KELAS) ?>;

            let chartInstances = []; // Gunakan array untuk menyimpan banyak instance grafik (di mode HP)
            let chartDataRaw = {};
            let activeKelompok = urutanKelompok[0];
            let isMobileView = window.innerWidth < 768;

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

            // Fetch AJAX Stand-alone
            fetch(`ajax_grafik_kehadiran.php?periode_id=${periodeId}`)
                .then(res => res.json())
                .then(res => {
                    document.getElementById('chartLoader').classList.add('hidden');
                    if (res.status === 'success') {
                        chartDataRaw = res.data;
                        renderChart(activeKelompok);
                    }
                });

            function renderChart(kelompok) {
                const container = document.getElementById('canvas_wrapper');

                // Hancurkan semua instance grafik yang ada
                chartInstances.forEach(chart => chart.destroy());
                chartInstances = [];
                container.innerHTML = '';

                if (!chartDataRaw[kelompok]) {
                    container.className = 'relative h-80 flex items-center justify-center';
                    container.innerHTML = '<span class="text-gray-400 italic">Data absensi belum tersedia.</span>';
                    return;
                }

                if (isMobileView) {
                    // ==========================================
                    // TAMPILAN HP: 1 Grafik Bar per Kelas
                    // ==========================================
                    container.className = 'grid grid-cols-1 sm:grid-cols-2 gap-6 w-full';

                    urutanKelas.forEach((kls, idx) => {
                        let namaKelas = kls.toUpperCase().replace('CABERAWIT', 'CBR').replace('PRA REMAJA', 'PRA-R');
                        const dataDB = chartDataRaw[kelompok][kls];

                        let dataGrafik = [null, null, null, null]; // Hadir, Izin, Sakit, Alpa
                        let hasData = false;

                        if (dataDB && parseInt(dataDB.total_jadwal_periode) > 0) {
                            const tot = parseInt(dataDB.hadir) + parseInt(dataDB.izin) + parseInt(dataDB.sakit) + parseInt(dataDB.alpa);
                            if (tot > 0) {
                                dataGrafik = [
                                    parseFloat(((parseInt(dataDB.hadir) / tot) * 100).toFixed(1)),
                                    parseFloat(((parseInt(dataDB.izin) / tot) * 100).toFixed(1)),
                                    parseFloat(((parseInt(dataDB.sakit) / tot) * 100).toFixed(1)),
                                    parseFloat(((parseInt(dataDB.alpa) / tot) * 100).toFixed(1))
                                ];
                                hasData = true;
                            } else {
                                dataGrafik = [0, 0, 0, 0];
                            }
                        }

                        // Buat elemen card dan kanvas untuk kelas ini
                        const canvasId = `chart_hp_${idx}`;
                        let cardHtml = `
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 shadow-sm">
                                <h3 class="text-center font-bold text-gray-700 text-sm mb-3 border-b border-gray-200 pb-2">${namaKelas}</h3>
                                <div class="relative h-48 w-full">
                                    ${hasData ? `<canvas id="${canvasId}"></canvas>` : '<div class="absolute inset-0 flex items-center justify-center text-xs text-gray-400 italic">N/A</div>'}
                                </div>
                            </div>
                        `;
                        container.insertAdjacentHTML('beforeend', cardHtml);

                        if (hasData) {
                            const ctx = document.getElementById(canvasId).getContext('2d');
                            const chart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['Hadir', 'Izin', 'Sakit', 'Alpa'],
                                    datasets: [{
                                        data: dataGrafik,
                                        backgroundColor: [
                                            'rgba(34, 197, 94, 0.8)', // Hadir
                                            'rgba(245, 158, 11, 0.8)', // Izin
                                            'rgba(59, 130, 246, 0.8)', // Sakit
                                            'rgba(239, 68, 68, 0.8)' // Alpa
                                        ],
                                        borderRadius: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }, // Sembunyikan legend karena label sudah ada di bawah (sumbu X)
                                        tooltip: {
                                            callbacks: {
                                                label: function(ctx) {
                                                    return ctx.raw + '%';
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
                                            formatter: (val) => Math.round(val) + '%'
                                        }
                                    },
                                    scales: {
                                        y: {
                                            max: 100,
                                            ticks: {
                                                display: false,
                                                callback: v => v + '%'
                                            },
                                            grid: {
                                                display: false,
                                                drawBorder: false
                                            }
                                        },
                                        x: {
                                            grid: {
                                                display: false,
                                                drawBorder: false
                                            },
                                            ticks: {
                                                font: {
                                                    size: 10
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                            chartInstances.push(chart);
                        }
                    });

                } else {
                    // ==========================================
                    // TAMPILAN LAPTOP: 1 Grafik Gabungan
                    // ==========================================
                    container.className = 'relative h-80 md:h-96 w-full';
                    container.innerHTML = '<canvas id="mainChart"></canvas>';
                    const ctx = document.getElementById('mainChart').getContext('2d');

                    let dHadir = [],
                        dIzin = [],
                        dSakit = [],
                        dAlpa = [],
                        labels = [];

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

                    const chart = new Chart(ctx, {
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
                    chartInstances.push(chart);
                }
            }

            // Mencegah re-render jika hanya scroll atas bawah di HP
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    const currentIsMobile = window.innerWidth < 768;
                    // Hanya render ulang jika terjadi pergantian breakpoint (Dari HP ke Laptop / Sebaliknya)
                    if (isMobileView !== currentIsMobile) {
                        isMobileView = currentIsMobile;
                        renderChart(activeKelompok);
                    }
                }, 250);
            });
        });
    </script>
</body>

</html>