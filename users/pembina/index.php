<?php
session_start();

// 🔐 SECURITY CHECK UNTUK ROLE PEMBINA
$allowed_roles = ['pembina'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../../index"); // Sesuaikan dengan path login Anda
    exit;
}

$pembina_level = $_SESSION['user_tingkat'] ?? 'desa';
$pembina_kelompok = $_SESSION['user_kelompok'] ?? null;

// === KONEKSI DATABASE (Opsional di sini jika hanya mengambil setting) ===
require_once __DIR__ . '/../../config/config.php';

$pageTitle = 'Dashboard Pembina';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SIMAK</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo_web_bg.png">

    <!-- Tailwind & Alpine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- FontAwesome & SweetAlert -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/sweetalert2.min.css">

    <!-- Web App Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SIMAK">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="../../assets/images/logo_web_bg.png">

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c7c7cc;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a1a1aa;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans flex flex-col min-h-screen">

    <!-- ========================================== -->
    <!-- HEADER STAND-ALONE (TANPA SIDEBAR/PROFILE) -->
    <!-- ========================================== -->
    <header class="flex justify-between items-center p-6 bg-white border-b shadow-sm sticky top-0 z-40">
        <div class="flex items-center">
            <!-- Logo / Title -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-md">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
            </div>
        </div>

        <!-- User Dropdown -->
        <div class="relative">
            <!-- Tombol Expander (yang bisa diklik) -->
            <button id="userMenuButton" class="flex items-center space-x-3 focus:outline-none bg-gray-50 hover:bg-gray-100 py-2 px-3 rounded-full transition border border-gray-200">
                <img
                    class="w-8 h-8 rounded-full object-cover border-2 border-indigo-200 bg-white"
                    src="../../uploads/profiles/<?php echo htmlspecialchars($_SESSION['foto_profil'] ?? 'default.png'); ?>"
                    alt="Foto Profil"
                    id="header-profile-pic"
                    onerror="this.onerror=null; this.src='../../uploads/profiles/default.png';">

                <span class="hidden md:inline font-semibold text-sm text-gray-700" id="header-user-name">
                    <?php echo htmlspecialchars($_SESSION['user_nama_panggilan'] ?? 'Pembina'); ?>
                </span>

                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Menu Dropdown -->
            <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50 hidden transition-all">
                <a href="#" onclick="event.preventDefault(); handleLogout();" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-medium transition">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout Sistem
                </a>
            </div>
        </div>
    </header>

    <!-- ========================================== -->
    <!-- MAIN CONTENT DASHBOARD                     -->
    <!-- ========================================== -->
    <main class="flex-1 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 relative">

        <!-- Loader Animasi HTML -->
        <div id="dashLoader" class="absolute inset-0 z-30 flex items-center justify-center bg-gray-100 bg-opacity-90 backdrop-blur-sm rounded-xl">
            <div class="flex flex-col items-center">
                <div class="w-12 h-12 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-indigo-600 font-bold tracking-widest uppercase text-sm">Memuat Data Pembina...</p>
            </div>
        </div>

        <div class="hidden" id="dashContent">
            <!-- Header Informasi -->
            <div class="mb-6 flex flex-col items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800">Ringkasan Sistem Terpadu</h2>
                <p class="text-gray-500 text-center mt-1">Periode Aktif: <span id="lbl_periode" class="font-bold text-indigo-600">Memuat...</span></p>
                <?php if ($pembina_level === 'kelompok'): ?>
                    <p class="text-xs text-center text-gray-400 mt-1">Kelompok <?php echo ucwords($pembina_kelompok); ?></p>
                <?php endif; ?>
                <div class="mt-3 inline-block px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-bold uppercase tracking-wide border border-indigo-100">
                    <i class="fa-solid fa-shield-halved mr-1"></i> Akses Pembina <?php echo $pembina_level === 'desa' ? 'Global' : 'Kelompok ' . ucwords($pembina_kelompok); ?>
                </div>
            </div>

            <!-- ROW 1: ENTITAS DATA PENGGUNA -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 w-full">
                <!-- CARD: TOTAL PESERTA -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition hover:shadow-md h-max w-full flex flex-col">
                    <div class="p-6 flex flex-col items-center relative">
                        <div class="absolute top-4 right-4 bg-blue-50 text-blue-600 p-2 rounded-xl"><i class="fa-solid fa-users text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-2">Total Seluruh Siswa</h3>
                        <span class="text-4xl font-black text-gray-800" id="val_peserta_top">0</span>
                        <p class="text-xs text-gray-400 mt-2"><span id="val_peserta_l_top">0</span> Laki-laki, <span id="val_peserta_p_top">0</span> Perempuan</p>

                        <button class="mt-5 text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-2 bg-blue-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_peserta', 'icon_peserta')">
                            Rincian Per Kelompok <i class="fas fa-chevron-down transition-transform duration-300" id="icon_peserta"></i>
                        </button>
                    </div>
                    <div id="det_peserta" class="hidden border-t border-gray-100 bg-gray-50/50 p-4">
                        <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_peserta_detail"></div>
                    </div>
                </div>

                <!-- CARD: GURU PENGAJAR -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition hover:shadow-md h-max w-full flex flex-col">
                    <div class="p-6 flex flex-col items-center relative">
                        <div class="absolute top-4 right-4 bg-orange-50 text-orange-600 p-2 rounded-xl"><i class="fa-solid fa-chalkboard-user text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-2">Total Tenaga Pengajar</h3>
                        <span class="text-4xl font-black text-gray-800" id="val_guru_top">0</span>
                        <p class="text-xs text-gray-400 mt-2">Guru Aktif di Semua Kelompok</p>

                        <button class="mt-5 text-sm font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-2 bg-orange-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_guru', 'icon_guru')">
                            Rincian Per Kelompok <i class="fas fa-chevron-down transition-transform duration-300" id="icon_guru"></i>
                        </button>
                    </div>
                    <div id="det_guru" class="hidden border-t border-gray-100 bg-gray-50/50 p-4">
                        <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_guru_detail"></div>
                    </div>
                </div>
            </div>

            <!-- ROW 2: MAIN DASHBOARD CARDS (KEHADIRAN & MATERI) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 w-full">
                <!-- CARD: RATA-RATA KEHADIRAN -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition hover:shadow-md h-max flex flex-col">
                    <div class="p-8 flex flex-col w-full relative">
                        <div class="absolute top-4 right-4 bg-indigo-50 text-indigo-600 p-2 rounded-xl"><i class="fa-solid fa-chart-pie text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-8 text-center">Rata-rata Kehadiran Global</h3>

                        <div class="grid grid-cols-4 gap-2 mb-4 w-full px-2">
                            <div class="text-center">
                                <span class="block text-2xl md:text-3xl font-black text-emerald-500" id="val_h_glob">0%</span>
                                <span class="text-[10px] md:text-xs font-bold text-gray-500 uppercase">Hadir</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-2xl md:text-3xl font-black text-yellow-500" id="val_i_glob">0%</span>
                                <span class="text-[10px] md:text-xs font-bold text-gray-500 uppercase">Izin</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-2xl md:text-3xl font-black text-blue-500" id="val_s_glob">0%</span>
                                <span class="text-[10px] md:text-xs font-bold text-gray-500 uppercase">Sakit</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-2xl md:text-3xl font-black text-red-500" id="val_a_glob">0%</span>
                                <span class="text-[10px] md:text-xs font-bold text-gray-500 uppercase">Alpa</span>
                            </div>
                        </div>

                        <div class="w-full h-5 md:h-6 bg-gray-100 rounded-full flex overflow-hidden shadow-inner px-1 py-1">
                            <div id="bar_h_glob" class="bg-emerald-500 h-full rounded-l-full transition-all duration-1000 ease-out" style="width: 0%"></div>
                            <div id="bar_i_glob" class="bg-yellow-400 h-full transition-all duration-1000 ease-out" style="width: 0%"></div>
                            <div id="bar_s_glob" class="bg-blue-500 h-full transition-all duration-1000 ease-out" style="width: 0%"></div>
                            <div id="bar_a_glob" class="bg-red-500 h-full rounded-r-full transition-all duration-1000 ease-out" style="width: 0%"></div>
                        </div>

                        <div class="flex justify-center mt-8">
                            <button class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-2 bg-indigo-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_hadir', 'icon_hadir')">
                                Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_hadir"></i>
                            </button>
                        </div>
                    </div>
                    <div id="det_hadir" class="hidden border-t border-gray-100 bg-gray-50/50 p-6 flex-grow">
                        <?php if ($pembina_level !== 'kelompok'): ?>
                            <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-4"><i class="fa-solid fa-layer-group mr-1"></i> Detail Rata-rata per Kelompok</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" id="grid_hadir_kel"></div>
                        <?php else: ?>
                            <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-4"><i class="fa-solid fa-chalkboard-user mr-1"></i> Rata-rata Kehadiran per Kelas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6" id="grid_hadir_kel"></div>
                        <?php endif; ?>

                        <div class="mt-6 text-center">
                            <a href="grafik_kehadiran" class="inline-block bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-bold py-2 px-6 rounded-full shadow-sm transition"><i class="fa-solid fa-chart-line mr-2"></i> Buka Halaman Grafik Kehadiran</a>
                        </div>
                    </div>
                </div>

                <!-- CARD: KETERCAPAIAN MATERI -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition hover:shadow-md h-max">
                    <div class="p-8 flex flex-col items-center relative">
                        <div class="absolute top-4 right-4 bg-emerald-50 text-emerald-600 p-2 rounded-xl"><i class="fa-solid fa-book-bookmark text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-6 text-center">Ketercapaian Materi Kurikulum</h3>

                        <div class="relative w-44 h-44 flex justify-center items-center">
                            <svg class="transform -rotate-90 w-44 h-44">
                                <circle cx="88" cy="88" r="76" stroke="currentColor" stroke-width="14" fill="transparent" class="text-gray-100" />
                                <circle id="circ_materi" cx="88" cy="88" r="76" stroke="currentColor" stroke-width="14" fill="transparent" class="text-emerald-500 transition-all duration-1000 ease-out" stroke-dasharray="477.5" stroke-dashoffset="477.5" stroke-linecap="round" />
                            </svg>
                            <div class="absolute flex flex-col items-center">
                                <span class="text-4xl font-black text-gray-800" id="val_materi">0%</span>
                                <span class="text-xs text-gray-400 uppercase tracking-widest mt-1">Keseluruhan</span>
                            </div>
                        </div>

                        <button class="mt-8 text-sm font-semibold text-emerald-600 hover:text-emerald-800 flex items-center gap-2 bg-emerald-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_materi', 'icon_materi')">
                            Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_materi"></i>
                        </button>
                    </div>
                    <div id="det_materi" class="hidden border-t border-gray-100 bg-gray-50/50 p-6">
                        <?php if ($pembina_level !== 'kelompok'): ?>
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-layer-group mr-1"></i> Rata-rata Tiap Kelompok</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6" id="grid_materi_kel"></div>
                        <?php else: ?>
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-chalkboard-user mr-1"></i> Rata-rata Tiap Kelas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6" id="grid_materi_kls"></div>
                        <?php endif; ?>

                        <div class="mt-6 text-center">
                            <a href="grafik_ketercapaian" class="inline-block bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-bold py-2 px-6 rounded-full shadow-sm transition"><i class="fa-solid fa-chart-column mr-2"></i> Buka Halaman Grafik Materi</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 3: TINDAKAN MENDESAK & JADWAL -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-12">
                <!-- Kolom Kiri: Urgent Actions -->
                <div class="lg:col-span-2 flex flex-col gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-red-100 relative overflow-hidden">
                        <div class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">URGENT</div>
                        <h2 class="text-lg font-bold text-red-600 mb-4 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Pantauan Jadwal Terlewat Belum Terisi</h2>
                        <div id="list_kosong" class="space-y-2 text-sm max-h-48 overflow-y-auto pr-2 custom-scrollbar"></div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-orange-100 relative overflow-hidden">
                        <div class="absolute top-0 right-0 bg-orange-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">WARNING</div>
                        <h2 class="text-lg font-bold text-orange-600 mb-4 flex items-center gap-2"><i class="fas fa-user-times"></i> Pantauan Jadwal Tanpa Pengajar</h2>
                        <div id="list_tanpa_guru" class="space-y-2 text-sm max-h-48 overflow-y-auto pr-2 custom-scrollbar"></div>
                    </div>
                </div>

                <!-- Kolom Kanan: Jadwal Hari Ini -->
                <div class="flex flex-col gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-calendar-check text-blue-500"></i> Pantauan Jadwal Hari Ini (<span id="val_jadwal_hari_ini_bot" class="text-blue-500">0</span>)</h2>
                        <div id="list_mendatang" class="space-y-3 text-sm max-h-[22rem] overflow-y-auto pr-2 custom-scrollbar"></div>
                    </div>
                </div>
            </div>

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
        const pembinaLevelSession = '<?= $pembina_level ?>';

        // Header Dropdown Script
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('userMenuButton');
            const menu = document.getElementById('userMenu');

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });

            window.addEventListener('click', () => {
                if (!menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            });
        });

        // Logout Script
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

        // Toggle Expandable Details
        function toggleDetails(divId, iconId) {
            const div = document.getElementById(divId);
            const icon = document.getElementById(iconId);
            if (div.classList.contains('hidden')) {
                div.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                div.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }

        // Dashboard Data Scripts
        document.addEventListener('DOMContentLoaded', function() {
            const formatTgl = (tgl) => {
                if (!tgl) return '';
                const d = new Date(tgl);
                const bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                return `${d.getDate()} ${bln[d.getMonth()]} ${d.getFullYear()}`;
            };

            const setCircleProgress = (circleId, textId, percent) => {
                const circle = document.getElementById(circleId);
                const textEl = document.getElementById(textId);
                const circumference = 477.5;

                circle.classList.remove('text-gray-300', 'text-indigo-500', 'text-emerald-500', 'text-red-500', 'text-yellow-500', 'text-green-500');
                textEl.classList.remove('text-gray-800', 'text-red-600', 'text-yellow-600', 'text-green-600', 'text-gray-400');

                if (percent === null || percent === undefined) {
                    circle.classList.add('text-gray-300');
                    textEl.classList.add('text-gray-400');
                    textEl.innerText = 'N/A';
                    setTimeout(() => {
                        circle.style.strokeDashoffset = circumference;
                    }, 100);
                    return;
                }

                const offset = circumference - (percent / 100) * circumference;

                if (percent <= 50) {
                    circle.classList.add('text-red-500');
                    textEl.classList.add('text-red-600');
                } else if (percent <= 75) {
                    circle.classList.add('text-yellow-500');
                    textEl.classList.add('text-yellow-600');
                } else {
                    circle.classList.add('text-green-500');
                    textEl.classList.add('text-green-600');
                }

                setTimeout(() => {
                    circle.style.strokeDashoffset = offset;
                }, 100);
            };

            const renderGrid = (containerId, dataObj, isClass = false) => {
                const container = document.getElementById(containerId);
                if (!container) return;
                container.innerHTML = '';
                for (const [key, value] of Object.entries(dataObj)) {
                    let color, display;
                    if (value === null || value === undefined) {
                        color = 'text-gray-400 bg-gray-50 border-gray-200';
                        display = 'N/A';
                    } else {
                        if (value > 75) color = 'text-green-600 bg-green-50 border-green-200';
                        else if (value > 50) color = 'text-yellow-600 bg-yellow-50 border-yellow-200';
                        else color = 'text-red-600 bg-red-50 border-red-200';
                        display = value + '%';
                    }
                    let displayKey = isClass ? key.replace('caberawit', 'CBR').toUpperCase() : key.toUpperCase();
                    container.innerHTML += `
                    <div class="border rounded-xl p-3 ${color} flex flex-col items-center justify-center text-center shadow-sm transition-colors">
                        <span class="text-[10px] font-bold tracking-wider opacity-70 mb-1">${displayKey}</span>
                        <span class="text-xl font-black">${display}</span>
                    </div>`;
                }
            };

            const renderKehadiranKel = (containerId, dataObj) => {
                const container = document.getElementById(containerId);
                if (!container) return;
                container.innerHTML = '';
                const fmtPct = v => (v === null || v === undefined) ? 'N/A' : v + '%';

                for (const [kelompok, val] of Object.entries(dataObj)) {
                    if (val === null || val === undefined) {
                        container.innerHTML += `
                        <div class="bg-white border border-gray-200 p-4 rounded-xl shadow-sm">
                            <h5 class="text-xs font-bold text-gray-700 uppercase mb-3 border-b pb-2">KLP. ${kelompok}</h5>
                            <p class="text-center text-gray-400 text-xs italic py-2">Belum ada data kehadiran</p>
                        </div>`;
                    } else {
                        container.innerHTML += `
                        <div class="bg-white border border-gray-200 p-4 rounded-xl shadow-sm">
                            <h5 class="text-xs font-bold text-gray-700 uppercase mb-3 border-b pb-2">KLP. ${kelompok}</h5>
                            <div class="grid grid-cols-4 gap-1 text-center">
                                <div><span class="block text-lg font-black text-emerald-500">${fmtPct(val.hadir)}</span><span class="text-[9px] text-gray-500 font-bold uppercase">H</span></div>
                                <div><span class="block text-lg font-black text-yellow-500">${fmtPct(val.izin)}</span><span class="text-[9px] text-gray-500 font-bold uppercase">I</span></div>
                                <div><span class="block text-lg font-black text-blue-500">${fmtPct(val.sakit)}</span><span class="text-[9px] text-gray-500 font-bold uppercase">S</span></div>
                                <div><span class="block text-lg font-black text-red-500">${fmtPct(val.alpa)}</span><span class="text-[9px] text-gray-500 font-bold uppercase">A</span></div>
                            </div>
                        </div>`;
                    }
                }
            };

            const renderKehadiranKelasGrid = (containerId, dataObj) => {
                const container = document.getElementById(containerId);
                if (!container) return;
                container.innerHTML = '';
                for (const [key, value] of Object.entries(dataObj)) {
                    let color, display;
                    if (value === null || value === undefined) {
                        color = 'text-gray-400 bg-gray-50 border-gray-200';
                        display = 'N/A';
                    } else {
                        if (value > 75) color = 'text-green-600 bg-green-50 border-green-200';
                        else if (value > 50) color = 'text-yellow-600 bg-yellow-50 border-yellow-200';
                        else color = 'text-red-600 bg-red-50 border-red-200';
                        display = value + '%';
                    }
                    let displayKey = key.replace('caberawit', 'CBR').toUpperCase();
                    container.innerHTML += `
                    <div class="border rounded-xl p-3 ${color} flex flex-col items-center justify-center text-center shadow-sm transition-colors">
                        <span class="text-[10px] font-bold tracking-wider opacity-70 mb-1">${displayKey}</span>
                        <span class="text-xl font-black">${display}</span>
                    </div>`;
                }
            };

            // AJAX Fetch ke Backend Stand-Alone Pembina
            fetch('ajax_dashboard.php')
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.text();
                })
                .then(text => {
                    try {
                        const res = JSON.parse(text);

                        if (res.status === 'success') {
                            const d = res.data;

                            document.getElementById('lbl_periode').innerText = d.periode_nama;

                            // Update Info Top (Peserta & Guru)
                            document.getElementById('val_peserta_top').innerText = d.total_peserta;
                            document.getElementById('val_peserta_l_top').innerText = d.peserta_l;
                            document.getElementById('val_peserta_p_top').innerText = d.peserta_p;

                            const cPeserta = document.getElementById('list_peserta_detail');
                            if (d.peserta_summary && Object.keys(d.peserta_summary).length > 0) {
                                let html = '<div class="flex flex-col gap-4">';
                                for (const [kel, kelasData] of Object.entries(d.peserta_summary)) {
                                    let namaKelompok = kel.charAt(0).toUpperCase() + kel.slice(1);
                                    let totalKel = 0;
                                    for (const counts of Object.values(kelasData)) totalKel += counts.total;
                                    html += `<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden w-full"><div class="bg-blue-50 text-blue-800 font-bold px-4 py-2 text-xs uppercase tracking-wider flex justify-between items-center border-b border-blue-100"><span>KLP. ${namaKelompok}</span><span>TOTAL: ${totalKel} PESERTA</span></div><div class="p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">`;
                                    for (const [kls, counts] of Object.entries(kelasData)) {
                                        let namaKelas = kls.replace('caberawit', 'CBR').toUpperCase();
                                        html += `<div class="bg-gray-50 border border-gray-100 rounded-lg p-2 flex flex-col items-center justify-center transition-colors hover:bg-blue-50/50 hover:border-blue-100"><span class="text-[10px] font-bold text-gray-500 mb-1">${namaKelas}</span><span class="text-lg font-black text-gray-800">${counts.total}</span><span class="text-[9px] text-gray-400 font-medium mt-0.5">${counts.l} L &middot; ${counts.p} P</span></div>`;
                                    }
                                    html += `</div></div>`;
                                }
                                html += '</div>';
                                cPeserta.innerHTML = html;
                            }

                            document.getElementById('val_guru_top').innerText = d.total_guru;
                            const cGuru = document.getElementById('list_guru_detail');
                            if (d.guru_summary && Object.keys(d.guru_summary).length > 0) {
                                let html = '<div class="flex flex-col gap-4">';
                                for (const [kel, kelasData] of Object.entries(d.guru_summary)) {
                                    let namaKelompok = kel.charAt(0).toUpperCase() + kel.slice(1);
                                    let totalKel = 0;
                                    for (const count of Object.values(kelasData)) totalKel += count;
                                    html += `<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden w-full"><div class="bg-orange-50 text-orange-800 font-bold px-4 py-2 text-xs uppercase tracking-wider flex justify-between items-center border-b border-orange-100"><span>KLP. ${namaKelompok}</span><span>TOTAL: ${totalKel} GURU</span></div><div class="p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">`;
                                    for (const [kls, count] of Object.entries(kelasData)) {
                                        let namaKelas = kls.replace('caberawit', 'CBR').toUpperCase();
                                        html += `<div class="bg-gray-50 border border-gray-100 rounded-lg p-2 flex flex-col items-center justify-center transition-colors hover:bg-orange-50/50 hover:border-orange-100"><span class="text-[10px] font-bold text-gray-500 mb-1">${namaKelas}</span><span class="text-lg font-black text-gray-800">${count}</span><span class="text-[9px] text-gray-400 font-medium mt-0.5">Guru</span></div>`;
                                    }
                                    html += `</div></div>`;
                                }
                                html += '</div>';
                                cGuru.innerHTML = html;
                            }

                            // Kehadiran Global (Pembina Mode)
                            const k = d.kehadiran.global;
                            const fmtPct = v => (v === null || v === undefined) ? 'N/A' : v + '%';
                            document.getElementById('val_h_glob').innerText = fmtPct(k.hadir);
                            document.getElementById('val_i_glob').innerText = fmtPct(k.izin);
                            document.getElementById('val_s_glob').innerText = fmtPct(k.sakit);
                            document.getElementById('val_a_glob').innerText = fmtPct(k.alpa);

                            setTimeout(() => {
                                document.getElementById('bar_h_glob').style.width = (k.hadir !== null && k.hadir !== undefined) ? k.hadir + '%' : '0%';
                                document.getElementById('bar_i_glob').style.width = (k.izin !== null && k.izin !== undefined) ? k.izin + '%' : '0%';
                                document.getElementById('bar_s_glob').style.width = (k.sakit !== null && k.sakit !== undefined) ? k.sakit + '%' : '0%';
                                document.getElementById('bar_a_glob').style.width = (k.alpa !== null && k.alpa !== undefined) ? k.alpa + '%' : '0%';
                            }, 100);

                            if (pembinaLevelSession === 'kelompok') {
                                renderKehadiranKelasGrid('grid_hadir_kel', d.kehadiran.kelas);
                            } else {
                                renderKehadiranKel('grid_hadir_kel', d.kehadiran.kelompok);
                            }

                            // Materi Global (Pembina Mode)
                            const materiGlobal = d.materi.global;
                            document.getElementById('val_materi').innerText = (materiGlobal === null || materiGlobal === undefined) ? 'N/A' : materiGlobal + '%';
                            setCircleProgress('circ_materi', 'val_materi', materiGlobal);

                            if (pembinaLevelSession === 'kelompok') {
                                renderGrid('grid_materi_kls', d.materi.kelas, true);
                            } else {
                                renderGrid('grid_materi_kel', d.materi.kelompok);
                            }

                            // Jadwal & Alerts
                            document.getElementById('val_jadwal_hari_ini_bot').innerText = d.jadwal_hari_ini;

                            const lKosong = document.getElementById('list_kosong');
                            if (d.jadwal_terlewat_kosong.length > 0) {
                                d.jadwal_terlewat_kosong.forEach(j => {
                                    lKosong.innerHTML += `<div class="flex justify-between items-center p-3 bg-red-50 border border-red-100 rounded-lg mb-2"><div><p class="font-semibold text-gray-800">${formatTgl(j.tanggal)} <span class="text-gray-400 text-xs ml-1 capitalize">(${j.kelompok} - ${j.kelas.replace('caberawit', 'CBR')})</span></p><p class="text-xs font-bold text-red-600 mt-0.5">Kosong: ${j.keterangan_kosong}</p></div></div>`;
                                });
                            } else lKosong.innerHTML = `<div class="p-4 text-center text-gray-400 text-sm border border-dashed rounded-lg">Semua jadwal terlewat sudah terisi. <i class="fa-solid fa-check text-green-500 ml-1"></i></div>`;

                            const lTanpa = document.getElementById('list_tanpa_guru');
                            if (d.jadwal_tanpa_pengajar.length > 0) {
                                d.jadwal_tanpa_pengajar.forEach(j => {
                                    lTanpa.innerHTML += `<div class="p-3 bg-orange-50 border border-orange-100 rounded-lg mb-2"><p class="font-semibold text-gray-800">${formatTgl(j.tanggal)} <span class="text-gray-400 text-xs ml-1 capitalize">(${j.kelompok} - ${j.kelas.replace('caberawit', 'CBR')})</span></p></div>`;
                                });
                            } else lTanpa.innerHTML = `<div class="p-4 text-center text-gray-400 text-sm border border-dashed rounded-lg">Semua jadwal sudah ada pengajarnya. <i class="fa-solid fa-check text-green-500 ml-1"></i></div>`;

                            const lAkan = document.getElementById('list_mendatang');
                            if (d.jadwal_akan_datang.length > 0) {
                                d.jadwal_akan_datang.forEach(j => {
                                    const hari = (j.tanggal === new Date().toISOString().split('T')[0]) ? 'Hari Ini' : 'Besok';
                                    lAkan.innerHTML += `<div class="p-3 border border-gray-100 bg-gray-50 rounded-lg mb-2"><p class="font-semibold text-indigo-700">${hari}, ${j.jam_mulai.substring(0,5)} <span class="text-gray-500 font-normal ml-1 capitalize">(${j.kelompok} - ${j.kelas.replace('caberawit', 'CBR')})</span></p><p class="text-xs text-gray-500 mt-1"><i class="fa-solid fa-chalkboard-user mr-1"></i> ${j.daftar_guru || 'Belum diatur'}</p></div>`;
                                });
                            } else lAkan.innerHTML = `<div class="p-4 text-center text-gray-400 text-sm border border-dashed rounded-lg">Tidak ada jadwal KBM hari ini/besok.</div>`;

                            // Hide Loader & Show Content
                            document.getElementById('dashLoader').classList.add('hidden');
                            document.getElementById('dashContent').classList.remove('hidden');

                        } else throw new Error(res.message);

                    } catch (e) {
                        console.error("Terjadi Error PHP/JSON:", e);
                        document.getElementById('dashLoader').classList.add('hidden');
                        Swal.fire({
                            title: 'Kesalahan Sistem',
                            html: `Gagal memproses data Dashboard. Detail error:<br><br><div class="text-left text-xs bg-gray-100 p-2 rounded max-h-32 overflow-y-auto font-mono text-red-600 border border-red-200">${text || e.message}</div>`,
                            icon: 'error',
                            confirmButtonText: 'Tutup'
                        });
                    }
                })
                .catch(err => {
                    console.error('Fetch Error:', err);
                    document.getElementById('dashLoader').classList.add('hidden');
                    Swal.fire({
                        title: 'Error Jaringan',
                        text: 'Gagal terhubung ke server.',
                        icon: 'error'
                    });
                });
        });
    </script>
</body>

</html>