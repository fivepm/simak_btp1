<?php
// === FILE FRONTEND: dashboard.php ===
$admin_level = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;
$admin_role = $_SESSION['user_role'] ?? '';
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
                <?php echo ($admin_role === 'superadmin') ? 'Dashboard Developer' : 'Dashboard Admin ' . ucwords($admin_level); ?>
            </h1>
            <p class="text-gray-500 text-center mt-1">Periode Aktif: <span id="lbl_periode" class="font-semibold text-indigo-600">Memuat...</span></p>
            <?php if ($admin_level === 'kelompok'): ?>
                <p class="text-xs text-center text-gray-400 mt-1">Filter aktif: Kelompok <?php echo ucwords($admin_kelompok); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ROW 1: ENTITAS DATA PENGGUNA (PESERTA, USERS, GURU)                       -->
    <!-- ========================================================================= -->
    <?php if ($admin_role === 'superadmin'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8 w-full">
        <?php else: ?>
            <!-- Jika bukan Superadmin, card akan melebar 100% penuh -->
            <div class="grid grid-cols-1 gap-6 mb-8 w-full">
            <?php endif; ?>

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
                    <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_peserta_detail">
                        <!-- Disuntikkan JS -->
                    </div>
                </div>
            </div>

            <?php if ($admin_role === 'superadmin'): ?>
                <!-- CARD: PENGGUNA SISTEM (Hanya Developer) -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max w-full flex flex-col">
                    <div class="p-6 flex flex-col items-center relative">
                        <div class="absolute top-4 right-4 bg-purple-50 text-purple-600 p-2 rounded-xl"><i class="fa-solid fa-user-shield text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-2">Pengguna Sistem</h3>
                        <span class="text-4xl font-black text-gray-800" id="val_users_top">0</span>
                        <p class="text-xs text-gray-400 mt-2">Agregat berdasarkan Role</p>

                        <button class="mt-5 text-sm font-semibold text-purple-600 hover:text-purple-800 flex items-center gap-2 bg-purple-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_users', 'icon_users')">
                            Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_users"></i>
                        </button>
                    </div>
                    <!-- Expandable Details -->
                    <div id="det_users" class="hidden border-t border-gray-100 bg-gray-50/50 p-4">
                        <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_users_detail">
                            <!-- Disuntikkan JS -->
                        </div>
                    </div>
                </div>

                <!-- CARD: GURU PENGAJAR (Hanya Developer) -->
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
                        <div class="max-h-[24rem] overflow-y-auto custom-scrollbar pr-2" id="list_guru_detail">
                            <!-- Disuntikkan JS -->
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            </div>

            <!-- ========================================================================= -->
            <!-- ROW 2: MAIN DASHBOARD CARDS (KEHADIRAN & MATERI)                          -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 w-full">

                <!-- CARD: RATA-RATA KEHADIRAN -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max">
                    <div class="p-8 flex flex-col items-center relative">
                        <div class="absolute top-4 right-4 bg-indigo-50 text-indigo-600 p-2 rounded-xl"><i class="fa-solid fa-chart-pie text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-6">Rata-rata Kehadiran (Hadir)</h3>

                        <!-- Circular Chart -->
                        <div class="relative w-44 h-44 flex justify-center items-center">
                            <svg class="transform -rotate-90 w-44 h-44">
                                <circle cx="88" cy="88" r="76" stroke="currentColor" stroke-width="14" fill="transparent" class="text-gray-100" />
                                <circle id="circ_hadir" cx="88" cy="88" r="76" stroke="currentColor" stroke-width="14" fill="transparent" class="text-indigo-500 transition-all duration-1000 ease-out" stroke-dasharray="477.5" stroke-dashoffset="477.5" stroke-linecap="round" />
                            </svg>
                            <div class="absolute flex flex-col items-center">
                                <span class="text-4xl font-black text-gray-800" id="val_hadir">0%</span>
                                <span class="text-xs text-gray-400 uppercase tracking-widest mt-1">Global</span>
                            </div>
                        </div>

                        <button class="mt-8 text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-2 bg-indigo-50 px-4 py-2 rounded-full transition" onclick="toggleDetails('det_hadir', 'icon_hadir')">
                            Lihat Selengkapnya <i class="fas fa-chevron-down transition-transform duration-300" id="icon_hadir"></i>
                        </button>
                    </div>

                    <!-- Expandable Details -->
                    <div id="det_hadir" class="hidden border-t border-gray-100 bg-gray-50/50 p-6">
                        <?php if ($admin_level !== 'kelompok'): ?>
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-layer-group mr-1"></i> Rata-rata Tiap Kelompok</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6" id="grid_hadir_kel"></div>
                        <?php endif; ?>

                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-chalkboard-user mr-1"></i> Rata-rata Tiap Kelas</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" id="grid_hadir_kls"></div>

                        <div class="mt-6 text-center">
                            <a href="?page=grafik_kehadiran" class="inline-block bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-bold py-2 px-6 rounded-full shadow-sm transition"><i class="fa-solid fa-chart-line mr-2"></i> Buka Halaman Grafik Kehadiran</a>
                        </div>
                    </div>
                </div>

                <!-- CARD: KETERCAPAIAN MATERI -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md h-max">
                    <div class="p-8 flex flex-col items-center relative">
                        <div class="absolute top-4 right-4 bg-emerald-50 text-emerald-600 p-2 rounded-xl"><i class="fa-solid fa-book-bookmark text-xl"></i></div>
                        <h3 class="font-bold text-gray-700 text-lg mb-6">Ketercapaian Materi Kurikulum</h3>

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
                        <?php if ($admin_level !== 'kelompok'): ?>
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-layer-group mr-1"></i> Rata-rata Tiap Kelompok</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6" id="grid_materi_kel"></div>
                        <?php endif; ?>

                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3"><i class="fa-solid fa-chalkboard-user mr-1"></i> Rata-rata Tiap Kelas</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" id="grid_materi_kls"></div>

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

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-bolt text-yellow-500"></i> Pintasan Menu</h2>
                        <div class="flex flex-col gap-2">
                            <a href="?page=report/daftar_laporan_harian" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex justify-between items-center transition">Laporan Harian <i class="fa-solid fa-arrow-right text-xs"></i></a>
                            <a href="?page=report/daftar_laporan_mingguan" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex justify-between items-center transition">Laporan Mingguan <i class="fa-solid fa-arrow-right text-xs"></i></a>
                        </div>
                    </div>
                </div>
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

<script>
    // Konstanta Info Role
    const userRoleSession = '<?= $admin_role ?>';

    // Fungsi Accordion
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

        // Fungsi Update Lingkaran (r=76 -> circumference = 477.5)
        const setCircleProgress = (id, percent) => {
            const circle = document.getElementById(id);
            const circumference = 477.5;
            const offset = circumference - (percent / 100) * circumference;
            setTimeout(() => {
                circle.style.strokeDashoffset = offset;
            }, 100);
        };

        // Fungsi Render Grid Persentase
        const renderGrid = (containerId, dataObj, isClass = false) => {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';
            for (const [key, value] of Object.entries(dataObj)) {
                let color = 'text-gray-600 bg-gray-50 border-gray-200';
                if (value >= 80) color = 'text-green-600 bg-green-50 border-green-200';
                else if (value >= 50) color = 'text-yellow-600 bg-yellow-50 border-yellow-200';
                else color = 'text-red-600 bg-red-50 border-red-200';

                let displayKey = isClass ? key.replace('caberawit', 'CBR').toUpperCase() : key.toUpperCase();

                container.innerHTML += `
                <div class="border rounded-xl p-3 ${color} flex flex-col items-center justify-center text-center shadow-sm">
                    <span class="text-[10px] font-bold tracking-wider opacity-70 mb-1">${displayKey}</span>
                    <span class="text-xl font-black">${value}%</span>
                </div>
            `;
            }
        };

        // Fetch Data
        fetch('pages/ajax_dashboard.php?action=get_dashboard') // Pastikan URL file backend benar
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

                        // =======================================================
                        // RENDER TOP CARDS (DATA ENTITAS - GROUP PER BARIS)
                        // =======================================================

                        // --- Card 1: Total Peserta ---
                        document.getElementById('val_peserta_top').innerText = d.total_peserta;
                        document.getElementById('val_peserta_l_top').innerText = d.peserta_l;
                        document.getElementById('val_peserta_p_top').innerText = d.peserta_p;

                        const cPeserta = document.getElementById('list_peserta_detail');
                        if (d.peserta_summary && Object.keys(d.peserta_summary).length > 0) {
                            let html = '<div class="space-y-4">'; // Stack vertikal

                            for (const [kel, kelasData] of Object.entries(d.peserta_summary)) {
                                let namaKelompok = kel.charAt(0).toUpperCase() + kel.slice(1);

                                // Hitung total peserta di kelompok ini
                                let totalKel = 0;
                                for (const counts of Object.values(kelasData)) totalKel += counts.total;

                                html += `
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden w-full">
                                <div class="bg-blue-50 text-blue-800 font-bold px-4 py-2 text-xs uppercase tracking-wider flex justify-between items-center border-b border-blue-100">
                                    <span>Kelompok ${namaKelompok}</span>
                                    <span>TOTAL: ${totalKel} Siswa</span>
                                </div>
                                <div class="p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">`;

                                // Render kotak masing-masing kelas (walau isinya 0)
                                for (const [kls, counts] of Object.entries(kelasData)) {
                                    let namaKelas = kls.replace('caberawit', 'CBR').toUpperCase();
                                    html += `
                                    <div class="bg-gray-50 border border-gray-100 rounded-lg p-2 flex flex-col items-center justify-center transition-colors hover:bg-blue-50/50 hover:border-blue-100">
                                        <span class="text-[10px] font-bold text-gray-500 mb-1">${namaKelas}</span>
                                        <span class="text-lg font-black text-gray-800">${counts.total}</span>
                                        <span class="text-[9px] text-gray-400 font-medium mt-0.5">${counts.l} L &middot; ${counts.p} P</span>
                                    </div>`;
                                }

                                html += `   </div>
                                     </div>`;
                            }
                            html += '</div>';
                            cPeserta.innerHTML = html;
                        } else {
                            cPeserta.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-4">Tidak ada data peserta aktif.</p>';
                        }

                        // --- Card 2 & 3: Users & Guru (Khusus Superadmin/Developer) ---
                        if (userRoleSession === 'superadmin') {
                            // Users Card (Sudah vertikal sejak awal, biarkan)
                            document.getElementById('val_users_top').innerText = d.total_users;
                            const cUsers = document.getElementById('list_users_detail');
                            if (d.users_summary && Object.keys(d.users_summary).length > 0) {
                                let html = '<div class="grid grid-cols-1 gap-2">';
                                for (const [roleName, count] of Object.entries(d.users_summary)) {
                                    if (count > 0) {
                                        html += `
                                        <div class="bg-white border border-gray-200 p-3 rounded-xl flex justify-between items-center shadow-sm">
                                            <span class="font-bold text-gray-700 text-xs uppercase">${roleName}</span>
                                            <span class="font-black text-purple-600 text-sm bg-purple-50 px-2 py-1 rounded-lg">${count} Org</span>
                                        </div>`;
                                    }
                                }
                                html += '</div>';
                                cUsers.innerHTML = html;
                            } else {
                                cUsers.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-4">Tidak ada data pengguna.</p>';
                            }

                            // Guru Card
                            document.getElementById('val_guru_top').innerText = d.total_guru;
                            const cGuru = document.getElementById('list_guru_detail');
                            if (d.guru_summary && Object.keys(d.guru_summary).length > 0) {
                                let html = '<div class="space-y-4">'; // Stack vertikal

                                for (const [kel, kelasData] of Object.entries(d.guru_summary)) {
                                    let namaKelompok = kel.charAt(0).toUpperCase() + kel.slice(1);

                                    // Hitung total guru di kelompok ini
                                    let totalKel = 0;
                                    for (const count of Object.values(kelasData)) totalKel += count;

                                    html += `
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden w-full">
                                    <div class="bg-orange-50 text-orange-800 font-bold px-4 py-2 text-xs uppercase tracking-wider flex justify-between items-center border-b border-orange-100">
                                        <span>Kelompok ${namaKelompok}</span>
                                        <span>TOTAL: ${totalKel} GURU</span>
                                    </div>
                                    <div class="p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">`;

                                    // Render kotak masing-masing kelas
                                    for (const [kls, count] of Object.entries(kelasData)) {
                                        let namaKelas = kls.replace('caberawit', 'CBR').toUpperCase();
                                        html += `
                                        <div class="bg-gray-50 border border-gray-100 rounded-lg p-2 flex flex-col items-center justify-center transition-colors hover:bg-orange-50/50 hover:border-orange-100">
                                            <span class="text-[10px] font-bold text-gray-500 mb-1">${namaKelas}</span>
                                            <span class="text-lg font-black text-gray-800">${count}</span>
                                            <span class="text-[9px] text-gray-400 font-medium mt-0.5">Guru</span>
                                        </div>`;
                                    }

                                    html += `   </div>
                                         </div>`;
                                }
                                html += '</div>';
                                cGuru.innerHTML = html;
                            } else {
                                cGuru.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-4">Tidak ada data guru.</p>';
                            }
                        }

                        // =======================================================
                        // RENDER GRAFIK & LIST TINDAKAN LAINNYA
                        // =======================================================
                        // 1. Update Kehadiran
                        document.getElementById('val_hadir').innerText = d.kehadiran.global + '%';
                        setCircleProgress('circ_hadir', d.kehadiran.global);
                        renderGrid('grid_hadir_kel', d.kehadiran.kelompok);
                        renderGrid('grid_hadir_kls', d.kehadiran.kelas, true);

                        // 2. Update Materi
                        document.getElementById('val_materi').innerText = d.materi.global + '%';
                        setCircleProgress('circ_materi', d.materi.global);
                        renderGrid('grid_materi_kel', d.materi.kelompok);
                        renderGrid('grid_materi_kls', d.materi.kelas, true);

                        // 3. Update Lainnya
                        document.getElementById('val_jadwal_hari_ini_bot').innerText = d.jadwal_hari_ini;

                        const lKosong = document.getElementById('list_kosong');
                        if (d.jadwal_terlewat_kosong.length > 0) {
                            d.jadwal_terlewat_kosong.forEach(j => {
                                lKosong.innerHTML += `<div class="flex justify-between items-center p-3 bg-red-50 border border-red-100 rounded-lg mb-2"><div><p class="font-semibold text-gray-800">${formatTgl(j.tanggal)} <span class="text-gray-400 text-xs ml-1 capitalize">(${j.kelompok} - ${j.kelas.replace('caberawit', 'CBR')})</span></p><p class="text-xs font-bold text-red-600 mt-0.5">Kosong: ${j.keterangan_kosong}</p></div></div>`;
                            });
                        } else {
                            lKosong.innerHTML = `<div class="p-4 text-center text-gray-400 text-sm border border-dashed rounded-lg">Semua jadwal terlewat sudah terisi. <i class="fa-solid fa-check text-green-500 ml-1"></i></div>`;
                        }

                        const lTanpa = document.getElementById('list_tanpa_guru');
                        if (d.jadwal_tanpa_pengajar.length > 0) {
                            d.jadwal_tanpa_pengajar.forEach(j => {
                                lTanpa.innerHTML += `<div class="p-3 bg-orange-50 border border-orange-100 rounded-lg mb-2"><p class="font-semibold text-gray-800">${formatTgl(j.tanggal)} <span class="text-gray-400 text-xs ml-1 capitalize">(${j.kelompok} - ${j.kelas.replace('caberawit', 'CBR')})</span></p></div>`;
                            });
                        } else {
                            lTanpa.innerHTML = `<div class="p-4 text-center text-gray-400 text-sm border border-dashed rounded-lg">Semua jadwal sudah ada pengajarnya. <i class="fa-solid fa-check text-green-500 ml-1"></i></div>`;
                        }

                        const lAkan = document.getElementById('list_mendatang');
                        if (d.jadwal_akan_datang.length > 0) {
                            d.jadwal_akan_datang.forEach(j => {
                                const hari = (j.tanggal === new Date().toISOString().split('T')[0]) ? 'Hari Ini' : 'Besok';
                                lAkan.innerHTML += `<div class="p-3 border border-gray-100 bg-gray-50 rounded-lg mb-2"><p class="font-semibold text-indigo-700">${hari}, ${j.jam_mulai.substring(0,5)} <span class="text-gray-500 font-normal ml-1 capitalize">(${j.kelompok} - ${j.kelas.replace('caberawit', 'CBR')})</span></p><p class="text-xs text-gray-500 mt-1"><i class="fa-solid fa-chalkboard-user mr-1"></i> ${j.daftar_guru || 'Belum diatur'}</p></div>`;
                            });
                        } else {
                            lAkan.innerHTML = `<div class="p-4 text-center text-gray-400 text-sm border border-dashed rounded-lg">Tidak ada jadwal KBM hari ini/besok.</div>`;
                        }

                        // Selesai Merender -> Hilangkan Loader
                        document.getElementById('dashLoader').classList.add('hidden');
                        document.getElementById('dashContent').classList.remove('hidden');
                    } else {
                        throw new Error(res.message);
                    }

                } catch (e) {
                    console.error("Terjadi Error PHP/JSON:", e);
                    document.getElementById('dashLoader').classList.add('hidden');
                    Swal.fire({
                        title: 'Kesalahan Sistem',
                        html: `Gagal memproses data Dashboard. Detail error:<br><br>
                           <div class="text-left text-xs bg-gray-100 p-2 rounded max-h-32 overflow-y-auto font-mono text-red-600 border border-red-200">
                               ${text || e.message}
                           </div>`,
                        icon: 'error',
                        confirmButtonText: 'Tutup',
                        customClass: {
                            container: 'z-[99999]'
                        }
                    });
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                document.getElementById('dashLoader').classList.add('hidden');
                Swal.fire({
                    title: 'Error Jaringan',
                    text: 'Gagal terhubung ke server.',
                    icon: 'error',
                    customClass: {
                        container: 'z-[99999]'
                    }
                });
            });
    });
</script>