<?php
// === FILE FRONTEND: dashboard.php ===
$ketuapjp_level = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? null;
?>

<!-- Loader Animasi HTML -->
<div id="dashLoader" class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-80 backdrop-blur-sm">
    <div class="flex flex-col items-center">
        <div class="w-12 h-12 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-4 text-indigo-600 font-semibold tracking-widest uppercase text-sm">Menyiapkan Dashboard...</p>
    </div>
</div>

<div class="container mx-auto p-4 sm:p-6 lg:p-8 hidden" id="dashContent">

    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row justify-center items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <?php echo 'Dashboard Ketua PJP ' . ucwords($ketuapjp_level); ?>
            </h1>
            <p class="text-gray-500 text-center mt-1">Periode Aktif: <span id="lbl_periode" class="font-semibold text-indigo-600">Memuat...</span></p>
            <?php if ($ketuapjp_level === 'kelompok'): ?>
                <p class="text-xs text-center text-gray-400 mt-1">Kelompok <?php echo ucwords($ketuapjp_kelompok); ?></p>
            <?php endif; ?>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- ROW 1: ENTITAS DATA PENGGUNA (PESERTA, USERS, GURU)                       -->
    <!-- ========================================================================= -->

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 w-full">


        <!-- CARD: TOTAL PESERTA -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max w-full flex flex-col">
            <div class="p-6 flex flex-col items-center relative">
                <div class="absolute top-4 right-4 bg-blue-50 text-blue-600 p-2 rounded-xl"><i class="fa-solid fa-users text-xl"></i></div>
                <h3 class="font-bold text-gray-700 text-lg mb-2">Total Siswa</h3>
                <span class="text-4xl font-black text-gray-800" id="val_peserta_top">0</span>
                <p class="text-xs text-gray-400 mt-2"><span id="val_peserta_l_top">0</span> Laki-laki, <span id="val_peserta_p_top">0</span> Perempuan</p>

                <button class="mt-5 text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-2 bg-blue-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_peserta', 'icon_peserta')">
                    Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_peserta"></i>
                </button>
            </div>
            <!-- Expandable Details -->
            <div id="det_peserta" class="hidden border-t border-gray-100 bg-gray-50/50 p-4">
                <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_peserta_detail"></div>
            </div>
        </div>

        <!-- CARD: GURU PENGAJAR -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max w-full flex flex-col">
            <div class="p-6 flex flex-col items-center relative">
                <div class="absolute top-4 right-4 bg-orange-50 text-orange-600 p-2 rounded-xl"><i class="fa-solid fa-chalkboard-user text-xl"></i></div>
                <h3 class="font-bold text-gray-700 text-lg mb-2">Total Guru</h3>
                <span class="text-4xl font-black text-gray-800" id="val_guru_top">0</span>
                <p class="text-xs text-gray-400 mt-2">Tenaga Pengajar Aktif</p>

                <button class="mt-5 text-sm font-semibold text-orange-600 hover:text-orange-800 flex items-center gap-2 bg-orange-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_guru', 'icon_guru')">
                    Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_guru"></i>
                </button>
            </div>
            <!-- Expandable Details -->
            <div id="det_guru" class="hidden border-t border-gray-100 bg-gray-50/50 p-4">
                <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_guru_detail"></div>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- ROW 1.5: STATUS KESIAPAN KELAS (JADWAL & TARGET PEMBELAJARAN)             -->
    <!-- ========================================================================= -->
    <div class="w-full mb-8">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md">

            <!-- Card Header -->
            <div class="p-5 md:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="bg-violet-50 text-violet-600 p-2.5 rounded-xl shrink-0">
                        <i class="fa-solid fa-clipboard-check text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-base">Status Kesiapan Kelas</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Kelengkapan jadwal & target pembelajaran per kelas</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span> Sudah diatur
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Belum diatur
                    </span>
                </div>
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="tbl_kesiapan">
                    <thead>
                        <tr class="bg-gray-50/70 border-b border-gray-100">
                            <th class="text-left px-5 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                <?php echo ($ketuapjp_level === 'kelompok') ? 'Kelas' : 'Kelompok / Kelas'; ?>
                            </th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">PAUD</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">CBR A</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">CBR B</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Pra Remaja</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Remaja</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Pra Nikah</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_kesiapan" class="divide-y divide-gray-50">
                        <!-- Dirender via JS -->
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">
                                <div class="inline-flex items-center gap-2"><div class="w-4 h-4 border-2 border-violet-400 border-t-transparent rounded-full animate-spin"></div> Memuat data...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ROW 2: MAIN DASHBOARD CARDS (KEHADIRAN & MATERI)                          -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 w-full">

        <!-- CARD: RATA-RATA KEHADIRAN (BARU - STACKED BAR) -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max flex flex-col">
            <div class="p-8 flex flex-col w-full relative">
                <div class="absolute top-4 right-4 bg-indigo-50 text-indigo-600 p-2 rounded-xl"><i class="fa-solid fa-chart-pie text-xl"></i></div>
                <h3 class="font-bold text-gray-700 text-lg mb-8 text-center">Rata-rata Kehadiran Global</h3>

                <!-- Statistik 4 Variabel -->
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

                <!-- Stacked Bar Grafik -->
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

            <!-- Expandable Details -->
            <div id="det_hadir" class="hidden border-t border-gray-100 bg-gray-50/50 p-6 flex-grow">
                <?php if ($ketuapjp_level !== 'kelompok'): ?>
                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-4"><i class="fa-solid fa-layer-group mr-1"></i> Detail Rata-rata per Kelompok</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" id="grid_hadir_kel">
                        <!-- Dirender via JS -->
                    </div>
                <?php else: ?>
                    <!-- Ketua PJP Kelompok: tampilkan rata-rata kehadiran per kelas -->
                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-4"><i class="fa-solid fa-chalkboard-user mr-1"></i> Rata-rata Kehadiran per Kelas</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6" id="grid_hadir_kel"></div>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <a href="?page=grafik_kehadiran" class="inline-block bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-bold py-2 px-6 rounded-full shadow-sm transition"><i class="fa-solid fa-chart-line mr-2"></i> Buka Halaman Grafik Kehadiran</a>
                </div>
            </div>
        </div>

        <!-- CARD: KETERCAPAIAN MATERI -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max">
            <div class="p-8 flex flex-col items-center relative">
                <div class="absolute top-4 right-4 bg-emerald-50 text-emerald-600 p-2 rounded-xl"><i class="fa-solid fa-book-bookmark text-xl"></i></div>
                <h3 class="font-bold text-gray-700 text-lg mb-6 text-center">Ketercapaian Materi Kurikulum</h3>

                <!-- Circular Chart -->
                <div class="relative w-44 h-44 flex justify-center items-center">
                    <svg class="transform -rotate-90 w-44 h-44">
                        <circle cx="88" cy="88" r="76" stroke="currentColor" stroke-width="14" fill="transparent" class="text-gray-100" />
                        <circle id="circ_materi" cx="88" cy="88" r="76" stroke="currentColor" stroke-width="14" fill="transparent" class="text-emerald-500 transition-all duration-1000 ease-out" stroke-dasharray="477.5" stroke-dashoffset="477.5" stroke-linecap="round" />
                    </svg>
                    <div class="absolute flex flex-col items-center">
                        <span class="text-4xl font-black text-gray-800" id="val_materi">0%</span>
                        <span class="text-xs text-gray-400 uppercase tracking-widest mt-1">Global</span>
                    </div>
                </div>

                <button class="mt-8 text-sm font-semibold text-emerald-600 hover:text-emerald-800 flex items-center gap-2 bg-emerald-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_materi', 'icon_materi')">
                    Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_materi"></i>
                </button>
            </div>

            <!-- Expandable Details -->
            <div id="det_materi" class="hidden border-t border-gray-100 bg-gray-50/50 p-6">
                <?php if ($ketuapjp_level !== 'kelompok'): ?>
                    <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-layer-group mr-1"></i> Rata-rata Tiap Kelompok</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6" id="grid_materi_kel"></div>
                <?php else: ?>
                    <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-chalkboard-user mr-1"></i> Rata-rata Tiap Kelas</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6" id="grid_materi_kls"></div>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <a href="?page=grafik_ketercapaian" class="inline-block bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-bold py-2 px-6 rounded-full shadow-sm transition"><i class="fa-solid fa-chart-column mr-2"></i> Buka Halaman Grafik Materi</a>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- ROW 3: TINDAKAN MENDESAK & JADWAL                                         -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Urgent Actions -->
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-red-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">URGENT</div>
                <h2 class="text-lg font-bold text-red-600 mb-4 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Jadwal Terlewat Belum Terisi</h2>
                <div id="list_kosong" class="space-y-2 text-sm max-h-48 overflow-y-auto pr-2 custom-scrollbar"></div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-orange-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-orange-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">WARNING</div>
                <h2 class="text-lg font-bold text-orange-600 mb-4 flex items-center gap-2"><i class="fas fa-user-times"></i> Jadwal Tanpa Pengajar</h2>
                <div id="list_tanpa_guru" class="space-y-2 text-sm max-h-48 overflow-y-auto pr-2 custom-scrollbar"></div>
            </div>
        </div>

        <!-- Kolom Kanan: Jadwal & Shortcut -->
        <div class="flex flex-col gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-calendar-check text-blue-500"></i> Jadwal Hari Ini (<span id="val_jadwal_hari_ini_bot" class="text-blue-500">0</span>)</h2>
                <div id="list_mendatang" class="space-y-3 text-sm max-h-60 overflow-y-auto pr-2 custom-scrollbar"></div>
            </div>
            <?php if ($ketuapjp_level === 'desa'): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-bolt text-yellow-500"></i> Pintasan Menu</h2>
                    <div class="flex flex-col gap-2">
                        <a href="?page=report/daftar_laporan_harian" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex justify-between items-center transition">Laporan Harian <i class="fa-solid fa-arrow-right text-xs"></i></a>
                        <a href="?page=report/daftar_laporan_mingguan" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex justify-between items-center transition">Laporan Mingguan <i class="fa-solid fa-arrow-right text-xs"></i></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

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

<?php
if (isset($_SESSION['user_id'])) {
    $cp_user_id = $_SESSION['user_id'];
    $cp_role = $_SESSION['user_role'] ?? 'guru';
    $cp_table = ($cp_role === 'guru') ? 'guru' : 'users';

    $stmt_cp = $conn->prepare("SELECT pin FROM $cp_table WHERE id = ?");
    $stmt_cp->bind_param("i", $cp_user_id);
    $stmt_cp->execute();
    $res_cp = $stmt_cp->get_result();
    $data_cp = $res_cp->fetch_assoc();
    $stmt_cp->close();

    if ($data_cp && password_verify('354313', $data_cp['pin'])) {
        $link_profil = '?page=profile/index';
        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (!sessionStorage.getItem('ignore_pin_warning')) {
                    Swal.fire({
                        title: '⚠️ Keamanan Akun',
                        html: `
                            <div class='text-left text-sm text-gray-600'>
                                <p class='mb-2'>Anda terdeteksi masih menggunakan <b>PIN Default</b>.</p>
                                <p>Demi keamanan data, mohon segera ganti PIN Anda melalui menu Profil.</p>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ganti PIN Sekarang',
                        cancelButtonText: 'Ingatkan Nanti',
                        confirmButtonColor: '#f59e0b', 
                        cancelButtonColor: '#9ca3af',  
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '$link_profil';
                        } else {
                            sessionStorage.setItem('ignore_pin_warning', 'true');
                        }
                    });
                }
            });
        </script>
        ";
    }
    
    // --- CEK PENGINGAT FAST LOGIN (JIKA PIN SUDAH BUKAN DEFAULT) ---
    // Logikanya: Jangan tumpuk pengingat. Jika PIN sudah aman, baru tawarkan Fast Login.
    if (!$data_cp || !password_verify('354313', $data_cp['pin'])) {
        require_once __DIR__ . '/../../../components/webauthn_reminder.php';
    }
}
?>

<script>
    const ketuapjpLevelSession = '<?= $ketuapjp_level ?>';

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

    document.addEventListener('DOMContentLoaded', function() {
        const formatTgl = (tgl) => {
            if (!tgl) return '';
            const d = new Date(tgl);
            const bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${d.getDate()} ${bln[d.getMonth()]} ${d.getFullYear()}`;
        };

        // Fungsi Update Lingkaran HANYA UNTUK MATERI
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

        // Render Grid Standar (Untuk Materi) - null ditampilkan sebagai N/A
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

        // Render Kehadiran per Kelompok (level desa) - null = N/A
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

        // Render Rata-rata Kehadiran per Kelas (level kelompok) - null = N/A
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

        // Fetch Data
        fetch('pages/ajax_dashboard.php?action=get_dashboard') // Sesuaikan URL
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

                        // --- Card 1: Total Peserta ---
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
                        } else cPeserta.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-4">Tidak ada data peserta aktif.</p>';

                        // --- Card 3: Guru Pengajar ---
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
                        } else cGuru.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-4">Tidak ada data guru.</p>';

                        // =======================================================
                        // 1. UPDATE KEHADIRAN (STACKED BAR H, I, S, A)
                        // =======================================================
                        const k = d.kehadiran.global;
                        const fmtPct = v => (v === null || v === undefined) ? 'N/A' : v + '%';

                        // Update Angka Teks (N/A jika tidak ada data)
                        document.getElementById('val_h_glob').innerText = fmtPct(k.hadir);
                        document.getElementById('val_i_glob').innerText = fmtPct(k.izin);
                        document.getElementById('val_s_glob').innerText = fmtPct(k.sakit);
                        document.getElementById('val_a_glob').innerText = fmtPct(k.alpa);

                        // Animasi Stacked Bar (0% jika tidak ada data)
                        setTimeout(() => {
                            document.getElementById('bar_h_glob').style.width = (k.hadir !== null && k.hadir !== undefined) ? k.hadir + '%' : '0%';
                            document.getElementById('bar_i_glob').style.width = (k.izin !== null && k.izin !== undefined) ? k.izin + '%' : '0%';
                            document.getElementById('bar_s_glob').style.width = (k.sakit !== null && k.sakit !== undefined) ? k.sakit + '%' : '0%';
                            document.getElementById('bar_a_glob').style.width = (k.alpa !== null && k.alpa !== undefined) ? k.alpa + '%' : '0%';
                        }, 100);

                        // Render Detail: level kelompok = per kelas, level desa = per kelompok
                        if (ketuapjpLevelSession === 'kelompok') {
                            renderKehadiranKelasGrid('grid_hadir_kel', d.kehadiran.kelas);
                        } else {
                            renderKehadiranKel('grid_hadir_kel', d.kehadiran.kelompok);
                        }

                        // =======================================================
                        // 2. UPDATE MATERI (TETAP MENGGUNAKAN CIRCULAR)
                        // =======================================================
                        const materiGlobal = d.materi.global;
                        if (materiGlobal === null || materiGlobal === undefined) {
                            document.getElementById('val_materi').innerText = 'N/A';
                        } else {
                            document.getElementById('val_materi').innerText = materiGlobal + '%';
                        }
                        setCircleProgress('circ_materi', 'val_materi', materiGlobal);

                        // Level kelompok: tampilkan per kelas; level desa: per kelompok
                        if (ketuapjpLevelSession === 'kelompok') {
                            renderGrid('grid_materi_kls', d.materi.kelas, true);
                        } else {
                            renderGrid('grid_materi_kel', d.materi.kelompok);
                        }

                        // 3. Update Lainnya (Jadwal dll)
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

        // =======================================================
        // FETCH: STATUS KESIAPAN KELAS (Jadwal & Target)
        // =======================================================
        const kelasList = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

        const badgeStatus = (ada) => ada
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                   <i class="fa-solid fa-check text-[8px]"></i> Sudah
               </span>`
            : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-600 border border-red-200 whitespace-nowrap">
                   <i class="fa-solid fa-xmark text-[8px]"></i> Belum
               </span>`;

        const renderKelompokRows = (kelompok, statusData) => {
            const namaKel = kelompok.charAt(0).toUpperCase() + kelompok.slice(1);
            const kelData = statusData[kelompok] || {};
            let jadwalCells = '', targetCells = '';
            kelasList.forEach(kls => {
                const kd = kelData[kls] || { jadwal: false, target: false };
                jadwalCells += `<td class="text-center px-4 py-2.5">${badgeStatus(kd.jadwal)}</td>`;
                targetCells += `<td class="text-center px-4 py-2.5">${badgeStatus(kd.target)}</td>`;
            });
            return `
            <tr class="hover:bg-violet-50/30 transition-colors">
                <td class="px-5 py-2.5" rowspan="2">
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 text-sm">${namaKel}</span>
                        <span class="text-[10px] text-gray-400 uppercase tracking-wider mt-0.5">Kelompok</span>
                    </div>
                </td>
                ${jadwalCells}
            </tr>
            <tr class="hover:bg-violet-50/30 transition-colors border-b border-gray-100">
                ${targetCells}
            </tr>`;
        };

        const renderKelompokSingleRows = (statusData) => {
            const klpData = Object.values(statusData)[0] || {};
            let jadwalCells = '', targetCells = '';
            kelasList.forEach(kls => {
                const kd = klpData[kls] || { jadwal: false, target: false };
                jadwalCells += `<td class="text-center px-4 py-2.5">${badgeStatus(kd.jadwal)}</td>`;
                targetCells += `<td class="text-center px-4 py-2.5">${badgeStatus(kd.target)}</td>`;
            });
            return `
            <tr class="hover:bg-violet-50/30 transition-colors">
                <td class="px-5 py-2.5">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-700 bg-violet-50 px-2.5 py-1 rounded-lg border border-violet-100">
                        <i class="fa-solid fa-calendar-days text-[10px]"></i> Jadwal KBM
                    </span>
                </td>
                ${jadwalCells}
            </tr>
            <tr class="hover:bg-violet-50/30 transition-colors border-b border-gray-100">
                <td class="px-5 py-2.5">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-100">
                        <i class="fa-solid fa-bullseye text-[10px]"></i> Target Probul
                    </span>
                </td>
                ${targetCells}
            </tr>`;
        };

        fetch('pages/ajax_dashboard.php?action=get_kesiapan_kelas')
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('tbody_kesiapan');
                if (!tbody) return;
                if (res.status !== 'success') {
                    tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-6 text-center text-red-500 text-sm">Gagal memuat data kesiapan.</td></tr>`;
                    return;
                }
                const statusData = res.data;
                const kelompokUrutan = ['bintaran', 'gedongkuning', 'jombor', 'sunten'];
                let html = '';

                if (ketuapjpLevelSession === 'kelompok') {
                    html = renderKelompokSingleRows(statusData);
                } else {
                    kelompokUrutan.forEach(kel => {
                        if (!statusData[kel]) return;
                        html += `
                        <tr class="bg-gray-50/70 border-y border-gray-100">
                            <td colspan="7" class="px-5 py-1.5">
                                <div class="flex items-center gap-2 text-[11px] text-gray-500">
                                    <span class="font-bold text-gray-700 uppercase tracking-wider">${kel.charAt(0).toUpperCase()+kel.slice(1)}</span>
                                    <span class="text-gray-300">·</span>
                                    <span class="flex items-center gap-1 text-violet-600 font-semibold"><i class="fa-solid fa-calendar-days text-[9px]"></i> Baris 1: Jadwal KBM</span>
                                    <span class="text-gray-300">·</span>
                                    <span class="flex items-center gap-1 text-amber-600 font-semibold"><i class="fa-solid fa-bullseye text-[9px]"></i> Baris 2: Target Probul</span>
                                </div>
                            </td>
                        </tr>
                        ${renderKelompokRows(kel, statusData)}`;
                    });
                }

                tbody.innerHTML = html || `<tr><td colspan="7" class="px-5 py-6 text-center text-gray-400 text-sm">Tidak ada data kelas.</td></tr>`;
            })
            .catch(() => {
                const tbody = document.getElementById('tbody_kesiapan');
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-6 text-center text-red-400 text-sm">Gagal terhubung ke server.</td></tr>`;
            });

    });
</script>