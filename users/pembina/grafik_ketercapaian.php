<?php
// === FILE FRONTEND STANDALONE: grafik_ketercapaian.php ===
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

$pageTitle = 'Grafik Ketercapaian Materi';
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

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f9fafb;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans flex flex-col min-h-screen">

    <!-- ========================================== -->
    <!-- HEADER STAND-ALONE (TANPA SIDEBAR/PROFILE) -->
    <!-- ========================================== -->
    <header class="flex justify-between items-center p-6 bg-white border-b shadow-sm sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-md">
                <i class="fa-solid fa-book-open"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
        </div>

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
            <a href="grafik_kehadiran" class="bg-indigo-50 border border-indigo-100 shadow-sm hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg font-medium transition"><i class="fa-solid fa-chart-pie mr-2"></i> Buka Grafik Kehadiran</a>
        </div>

        <!-- Filter -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-book-open text-emerald-500 mr-2"></i> Rincian Capaian Materi</h2>
                <form method="GET" action="" class="flex items-center gap-2 w-full md:w-auto">
                    <select name="periode_id" class="w-full md:w-64 py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selected_periode_id == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_periode']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg transition"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <!-- Container Grid & Tabs -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative min-h-[400px]">
            <div id="chartLoader" class="absolute inset-0 flex items-center justify-center bg-white z-10 rounded-2xl">
                <div class="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <?php if (count($URUTAN_KELOMPOK) > 1): ?>
                <div class="flex space-x-2 border-b border-gray-200 mb-6 overflow-x-auto pb-2" id="tabs_kelompok">
                    <?php foreach ($URUTAN_KELOMPOK as $i => $kel): ?>
                        <button class="tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg transition <?= $i === 0 ? 'bg-emerald-50 text-emerald-700 border-b-2 border-emerald-600' : 'text-gray-500 hover:bg-gray-50' ?>" data-target="<?= $kel ?>"><?= ucwords($kel) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="canvas_container" class="relative w-full"></div>
        </div>
    </main>

    <!-- Overlay Logout -->
    <div id="logout-overlay" class="fixed inset-0 z-[999] flex flex-col items-center justify-center bg-gray-900 bg-opacity-75 transition-opacity duration-300 ease-in-out opacity-0 hidden">
        <div class="w-16 h-16 border-4 border-t-4 border-t-cyan-500 border-gray-600 rounded-full animate-spin"></div>
        <p class="mt-4 text-white text-lg font-semibold">Memproses Logout...</p>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/sweetalert2.min.js"></script>
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

        // Ketercapaian Logic
        document.addEventListener('DOMContentLoaded', function() {
            const periodeId = '<?= $selected_periode_id ?>';
            const urutanKelompok = <?= json_encode($URUTAN_KELOMPOK) ?>;
            const urutanKelas = <?= json_encode($URUTAN_KELAS) ?>;

            let chartDataRaw = {};
            let activeKelompok = urutanKelompok[0];

            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    tabBtns.forEach(b => {
                        b.classList.remove('bg-emerald-50', 'text-emerald-700', 'border-b-2', 'border-emerald-600');
                        b.classList.add('text-gray-500');
                    });
                    this.classList.remove('text-gray-500');
                    this.classList.add('bg-emerald-50', 'text-emerald-700', 'border-b-2', 'border-emerald-600');
                    activeKelompok = this.dataset.target;
                    renderView(activeKelompok);
                });
            });

            // Fetch AJAX Stand-alone
            fetch(`ajax_grafik_ketercapaian.php?periode_id=${periodeId}`)
                .then(res => res.json())
                .then(res => {
                    document.getElementById('chartLoader').classList.add('hidden');
                    if (res.status === 'success') {
                        chartDataRaw = res.data;
                        renderView(activeKelompok);
                    } else {
                        Swal.fire('Error', res.message || 'Gagal memuat data.', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('chartLoader').classList.add('hidden');
                });

            function renderView(kelompok) {
                const container = document.getElementById('canvas_container');
                container.innerHTML = '';

                if (!chartDataRaw[kelompok]) {
                    container.innerHTML = '<div class="text-center py-10 text-gray-400 italic">Data materi belum tersedia.</div>';
                    return;
                }

                let html = '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">';

                urutanKelas.forEach(kls => {
                    let namaKelas = kls.replace('caberawit', 'CBR').toUpperCase();
                    let dataKls = chartDataRaw[kelompok][kls];

                    let avg = dataKls ? dataKls.rata_rata : null;
                    let kats = dataKls ? dataKls.kategori : {};

                    let isKosong = (avg === null);

                    let colorTheme = 'text-gray-400';
                    let strokeColor = 'text-gray-200';
                    let displayAvg = 'N/A';
                    let offset = 201.06;

                    if (!isKosong) {
                        displayAvg = avg + '%';
                        if (avg >= 80) {
                            colorTheme = 'text-emerald-600';
                            strokeColor = 'text-emerald-500';
                        } else if (avg >= 50) {
                            colorTheme = 'text-amber-500';
                            strokeColor = 'text-amber-400';
                        } else {
                            colorTheme = 'text-red-500';
                            strokeColor = 'text-red-400';
                        }
                        offset = 201.06 - (avg / 100) * 201.06;
                    }

                    let rightHtml = '';
                    if (isKosong || Object.keys(kats).length === 0) {
                        rightHtml = '<div class="text-xs text-gray-400 italic flex h-full items-center justify-center">Belum ada target pembelajaran / realisasi jurnal.</div>';
                    } else {
                        rightHtml = '<div class="flex flex-col gap-3 justify-center h-full max-h-[140px] overflow-y-auto pr-2 custom-scrollbar">';
                        for (let [cName, cVal] of Object.entries(kats)) {
                            let barColor = cVal >= 80 ? 'bg-emerald-500' : (cVal >= 50 ? 'bg-amber-400' : 'bg-red-400');
                            rightHtml += `
                            <div>
                                <div class="flex justify-between text-[10px] font-bold text-gray-500 mb-1 gap-2">
                                    <span class="truncate" title="${cName}">${cName}</span>
                                    <span>${cVal}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5 md:h-2">
                                    <div class="${barColor} h-1.5 md:h-2 rounded-full" style="width: 0%; transition: width 1s ease-out;" data-width="${cVal}%"></div>
                                </div>
                            </div>
                        `;
                        }
                        rightHtml += '</div>';
                    }

                    html += `
                <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-stretch gap-4 transition hover:shadow-md h-44">
                    <div class="flex flex-col items-center justify-center shrink-0 w-24 md:w-1/3 border-r border-gray-100 pr-2 md:pr-4">
                        <h3 class="font-bold text-gray-700 text-[11px] md:text-sm mb-3 text-center">${namaKelas}</h3>
                        <div class="relative w-16 h-16 md:w-20 md:h-20 flex justify-center items-center">
                            <svg class="transform -rotate-90 w-16 h-16 md:w-20 md:h-20" viewBox="0 0 80 80">
                                <circle cx="40" cy="40" r="32" stroke="currentColor" stroke-width="6" fill="transparent" class="text-gray-100" />
                                <circle cx="40" cy="40" r="32" stroke="currentColor" stroke-width="6" fill="transparent" class="${strokeColor}" style="stroke-dasharray: 201.06; stroke-dashoffset: 201.06; transition: stroke-dashoffset 1.5s ease-out;" stroke-linecap="round" data-offset="${offset}" />
                            </svg>
                            <div class="absolute flex flex-col items-center">
                                <span class="text-xs md:text-sm font-black ${colorTheme}">${displayAvg}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 py-1">
                        ${rightHtml}
                    </div>
                </div>
                `;
                });

                html += '</div>';
                container.innerHTML = html;

                setTimeout(() => {
                    const circles = container.querySelectorAll('circle[data-offset]');
                    circles.forEach(c => {
                        c.style.strokeDashoffset = c.getAttribute('data-offset');
                    });
                    const bars = container.querySelectorAll('div[data-width]');
                    bars.forEach(b => {
                        b.style.width = b.getAttribute('data-width');
                    });
                }, 100);
            }
        });
    </script>
</body>

</html>