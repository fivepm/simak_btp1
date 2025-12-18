<?php
// Variabel $currentPage akan di-set di file index.php utama
$activeClass = 'bg-green-600 text-white';
$inactiveClass = 'text-gray-300 hover:bg-green-700 hover:text-white';
$groupActiveClass = 'text-white bg-green-700';

// Ambil data admin yang sedang login dari session
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_role = $_SESSION['user_role'] ?? '';

// Definisikan halaman-halaman untuk setiap grup
$masterDataPages = ['master/kelola_pengguna', 'master/kelola_ketua_pjp', 'master/kepengurusan', 'master/kelola_penasehat', 'master/kelola_guru', 'master/kelola_peserta'];
$isMasterDataActive = in_array($currentPage, $masterDataPages);

$presensiPages = ['presensi/periode', 'presensi/jadwal', 'presensi/atur_guru', 'presensi/atur_penasehat', 'presensi/kehadiran', 'presensi/jurnal'];
$isPresensiActive = in_array($currentPage, $presensiPages);

$pesertaPages = ['peserta/catatan', 'peserta/kartu_hafalan'];
$isPesertaActive = in_array($currentPage, $pesertaPages);

// Grup baru untuk Kurikulum
$kurikulumPages = ['kurikulum/materi_hafalan', 'kurikulum/kurikulum_hafalan'];
$isKurikulumActive = in_array($currentPage, $kurikulumPages);

$whatsappPages = ['pengaturan/tes_fonnte', 'pengaturan/pengumuman', 'pengaturan/daftar_chat', 'pengaturan/riwayat_chat'];
$isWhatsappActive = in_array($currentPage, $whatsappPages);

$pengaturanPages = ['pengaturan/template_pesan', 'pengaturan/grup_whatsapp', 'pengaturan/pesan_terjadwal', 'pengaturan/pengaturan_pengingat'];
$isPengaturanActive = in_array($currentPage, $pengaturanPages);

// Grup baru untuk Musyawarah
$musyawarahPages = ['musyawarah/daftar_musyawarah', 'musyawarah/ringkasan_musyawarah', 'musyawarah/daftar_notulensi', 'musyawarah/catat_notulensi', 'musyawarah/lihat_notulensi', 'musyawarah/evaluasi_notulensi', 'musyawarah/daftar_kehadiran', 'musyawarah/daftar_hadir', 'musyawarah/lihat_kehadiran'];
$isMusyawarahActive = in_array($currentPage, $musyawarahPages);

// Grup baru untuk Laporan
$laporanPages = ['laporan/laporan_kelompok', 'laporan/laporan_detail'];
$isLaporanActive = in_array($currentPage, $laporanPages);

// Grup baru untuk Report
$reportPages = ['report/daftar_laporan_harian', 'report/form_laporan_harian', 'report/lihat_laporan_harian', 'report/daftar_laporan_mingguan', 'report/form_laporan_mingguan', 'report/lihat_laporan_mingguan'];
$isReportActive = in_array($currentPage, $reportPages);

// Grup baru untuk Pustaka Materi
$pustakaMateriPages = ['pustaka_materi/index', 'pustaka_materi/detail_materi'];
$ispustakaMateriActive = in_array($currentPage, $pustakaMateriPages);

// Grup baru untuk Development
$developmentPages = ['development/maintenance'];
$isDevelopmentActive = in_array($currentPage, $developmentPages);
?>
<!-- Sidebar -->
<div id="sidebar-menu" class="w-64 bg-green-800 text-white flex flex-col fixed inset-y-0 left-0 z-30
    transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">

    <!-- Logo/Header Sidebar -->
    <div class="w-full px-6 py-6 border-b border-green-700 justify-center text-center">
        <img src="../assets/images/logo_kbm.png"
            alt="Logo Aplikasi"
            class="h-10 w-10 border border-red-300 mx-auto mb-2 bg-white">
        <h2 class="text-2xl font-semibold text-white text-center">SIMAK</h2>
        <span class="text-xs text-white">Sistem Informasi Monitoring Akademik</span>
        <br>
        <?php if ($admin_role == 'admin'): ?>
            <span class="text-sm text-green-300">Admin Panel - <?php echo ucfirst($admin_tingkat) ?></span>
        <?php else: ?>
            <span class="text-sm text-green-300">Super Admin Panel</span>
        <?php endif ?>
    </div>

    <!-- Menu Navigasi -->
    <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
        <!-- Menu Utama -->
        <a href="?page=dashboard" class="flex items-center px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo ($currentPage === 'dashboard') ? $activeClass : $inactiveClass; ?>">
            <i class="fa fa-tachometer fa-fw mr-3" aria-hidden="true"> </i>
            Dashboard
        </a>

        <!-- Grup Menu: Master Data -->
        <div class="pt-2">
            <button id="masterDataButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isMasterDataActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                <span class="flex items-center">
                    <i class="fa fa-database fa-fw mr-3" aria-hidden="true"></i>
                    Master Data
                </span>
                <svg id="masterDataArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isMasterDataActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="masterDataSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isMasterDataActive ? '' : 'hidden'; ?>">
                <?php if ($admin_tingkat === 'desa'): ?>
                    <a href="?page=master/kelola_pengguna" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kelola_pengguna') ? $activeClass : $inactiveClass; ?>">Admin</a>
                    <a href="?page=master/kelola_ketua_pjp" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kelola_ketua_pjp') ? $activeClass : $inactiveClass; ?>">Ketua PJP</a>
                <?php endif; ?>
                <a href="?page=master/kepengurusan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kepengurusan') ? $activeClass : $inactiveClass; ?>">Kepengurusan</a>
                <a href="?page=master/kelola_penasehat" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kelola_penasehat') ? $activeClass : $inactiveClass; ?>">Penasehat</a>
                <a href="?page=master/kelola_guru" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kelola_guru') ? $activeClass : $inactiveClass; ?>">Guru</a>
                <a href="?page=master/kelola_peserta" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kelola_peserta') ? $activeClass : $inactiveClass; ?>">Peserta</a>
            </div>
        </div>

        <!-- GRUP MENU BARU: Presensi -->
        <div class="pt-2">
            <button id="presensiButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isPresensiActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                <span class="flex items-center">
                    <i class="fa-solid fa-check-to-slot fa-fw mr-3"></i>
                    Jadwal
                </span>
                <svg id="presensiArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isPresensiActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="presensiSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isPresensiActive ? '' : 'hidden'; ?>">
                <a href="?page=presensi/periode" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/periode') ? $activeClass : $inactiveClass; ?>">
                    <?php if ($admin_tingkat === 'desa'): ?>
                        Atur Periode
                    <?php elseif ($admin_tingkat === 'kelompok'): ?>
                        Periode
                    <?php endif; ?>
                </a>
                <a href="?page=presensi/jadwal" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/jadwal') ? $activeClass : $inactiveClass; ?>">Atur Jadwal</a>
                <a href="?page=presensi/atur_guru" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/atur_guru') ? $activeClass : $inactiveClass; ?>">Atur Jadwal Guru</a>
                <a href="?page=presensi/atur_penasehat" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/atur_penasehat') ? $activeClass : $inactiveClass; ?>">Atur Jadwal Penasehat</a>
                <a href="?page=presensi/kehadiran" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/kehadiran') ? $activeClass : $inactiveClass; ?>">Rekap Kehadiran</a>
                <a href="?page=presensi/jurnal" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/jurnal') ? $activeClass : $inactiveClass; ?>">Jurnal Harian</a>
            </div>
        </div>

        <!-- GRUP MENU BARU: Peserta -->
        <div class="pt-2">
            <button id="pesertaButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isPesertaActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                <span class="flex items-center">
                    <span class="flex items-center">
                        <i class="fas fa-users fa-fw mr-3"></i>
                        Peserta
                    </span>
                </span>
                <svg id="pesertaArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isPesertaActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="pesertaSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isPesertaActive ? '' : 'hidden'; ?>">
                <a href="?page=peserta/catatan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'peserta/catatan') ? $activeClass : $inactiveClass; ?>">Catatan BK</a>
                <a href="?page=peserta/kartu_hafalan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'peserta/kartu_hafalan') ? $activeClass : $inactiveClass; ?>">Kartu Hafalan</a>
            </div>
        </div>

        <?php if ($admin_tingkat === 'desa'): ?>
            <!-- GRUP MENU BARU: Kurikulum -->
            <div class="pt-2">
                <button id="kurikulumButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isKurikulumActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                    <span class="flex items-center">
                        <i class="fa-solid fa-book-open fa-fw mr-3"></i>
                        Kurikulum
                    </span>
                    <svg id="kurikulumArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isKurikulumActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="kurikulumSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isKurikulumActive ? '' : 'hidden'; ?>">
                    <a href="?page=kurikulum/materi_hafalan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'kurikulum/materi_hafalan') ? $activeClass : $inactiveClass; ?>">Materi Hafalan</a>
                    <a href="?page=kurikulum/kurikulum_hafalan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'kurikulum/kurikulum_hafalan') ? $activeClass : $inactiveClass; ?>">Atur Kurikulum</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- GRUP MENU BARU: Musyawarah (KHUSUS ADMIN DESA) -->
        <?php if ($admin_tingkat === 'desa'): ?>
            <div class="pt-2">
                <button id="musyawarahButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isMusyawarahActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                    <span class="flex items-center">
                        <i class="fa-solid fa-clipboard fa-fw mr-3"></i>
                        Musyawarah
                    </span>
                    <svg id="musyawarahArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isMusyawarahActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="musyawarahSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isMusyawarahActive ? '' : 'hidden'; ?>">
                    <a href="?page=musyawarah/daftar_musyawarah" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'musyawarah/daftar_musyawarah') ? $activeClass : $inactiveClass; ?>">Daftar Musyawarah</a>
                    <a href="?page=musyawarah/daftar_kehadiran" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'musyawarah/daftar_kehadiran') ? $activeClass : $inactiveClass; ?>">Kehadiran</a>
                    <a href="?page=musyawarah/daftar_notulensi" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'musyawarah/daftar_notulensi') ? $activeClass : $inactiveClass; ?>">Notulensi</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- GRUP MENU BARU: Laporan (KHUSUS ADMIN DESA) -->
        <!-- <?php if ($admin_tingkat === 'desa'): ?>
            <div class="pt-2">
                <button id="laporanButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isLaporanActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                    <span class="flex items-center">
                        <i class="fa-solid fa-flag fa-fw mr-3"></i>
                        Laporan
                    </span>
                    <svg id="laporanArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isLaporanActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="laporanSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isLaporanActive ? '' : 'hidden'; ?>">
                    <a href="?page=laporan/laporan_kelompok" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'laporan/laporan_kelompok') ? $activeClass : $inactiveClass; ?>">Kelompok</a>
                </div>
            </div>
        <?php endif; ?> -->

        <!-- GRUP MENU BARU: Whatsapp -->
        <?php if ($admin_tingkat === 'desa'): ?>
            <div class="pt-2">
                <button id="whatsappButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isWhatsappActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                    <span class="flex items-center">
                        <i class="fa-brands fa-whatsapp fa-fw mr-3"></i>
                        Whatsapp
                    </span>
                    <svg id="whatsappArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isWhatsappActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="whatsappSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isWhatsappActive ? '' : 'hidden'; ?>">
                    <a href="?page=pengaturan/pengumuman" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/pengumuman') ? $activeClass : $inactiveClass; ?>">Pengumuman</a>
                    <a href="?page=pengaturan/tes_fonnte" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/tes_fonnte') ? $activeClass : $inactiveClass; ?>">Cek Koneksi</a>
                    <a href="?page=pengaturan/daftar_chat" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/daftar_chat') ? $activeClass : $inactiveClass; ?>">Riwayat Chat</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- GRUP MENU BARU: Report -->
        <?php if ($admin_tingkat === 'desa'): ?>
            <div class="pt-2">
                <button id="reportButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isReportActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                    <span class="flex items-center">
                        <i class="fa-solid fa-magnifying-glass-chart fa-fw mr-3"></i>
                        Report
                    </span>
                    <svg id="reportArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isReportActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="reportSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isReportActive ? '' : 'hidden'; ?>">
                    <a href="?page=report/daftar_laporan_harian" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'report/daftar_laporan_harian') ? $activeClass : $inactiveClass; ?>">Harian</a>
                    <a href="?page=report/daftar_laporan_mingguan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'report/daftar_laporan_mingguan') ? $activeClass : $inactiveClass; ?>">Mingguan</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- GRUP MENU BARU: Pengaturan -->
        <div class="pt-2">
            <button id="pengaturanButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isPengaturanActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                <span class="flex items-center">
                    <i class="fa-solid fa-gear fa-fw mr-3"></i>
                    Pengaturan
                </span>
                <svg id="pengaturanArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isPengaturanActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="pengaturanSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isPengaturanActive ? '' : 'hidden'; ?>">
                <a href="?page=pengaturan/template_pesan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/template_pesan') ? $activeClass : $inactiveClass; ?>">Template Pesan</a>
                <a href="?page=pengaturan/pengaturan_pengingat" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/pengaturan_pengingat') ? $activeClass : $inactiveClass; ?>">Waktu WA Pengingat</a>
                <?php if ($admin_tingkat === 'desa'): ?>
                    <a href="?page=pengaturan/grup_whatsapp" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/grup_whatsapp') ? $activeClass : $inactiveClass; ?>">Grup WA</a>
                    <a href="?page=pengaturan/pesan_terjadwal" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'pengaturan/pesan_terjadwal') ? $activeClass : $inactiveClass; ?>">Pesan Terjadwal</a>
                <?php endif; ?>
            </div>
        </div>

        <a href="?page=pustaka_materi/index" class="flex items-center px-4 py-2.5 rounded-lg <?php echo $ispustakaMateriActive ? $activeClass : $inactiveClass; ?>">
            <i class="fa-solid fa-book fa-fw mr-3"></i>
            Pustaka Materi
        </a>

        <!-- GRUP MENU BARU: Development -->
        <?php if ($admin_role === 'superadmin'): ?>
            <div class="pt-2">
                <button id="developmentButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isDevelopmentActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                    <span class="flex items-center">
                        <i class="fa-solid fa-file-code fa-fw mr-3"></i>
                        Development
                    </span>
                    <svg id="developmentArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isDevelopmentActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="developmentSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isDevelopmentActive ? '' : 'hidden'; ?>">
                    <a href="?page=development/maintenance" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'development/maintenance') ? $activeClass : $inactiveClass; ?>">Maintenance</a>
                </div>
            <?php endif; ?>
            </div>
    </nav>
</div>

<!-- JavaScript untuk Dropdown -->
<script>
    if (!window.sidebarScriptLoaded) {
        const setupDropdown = (buttonId, submenuId, arrowId) => {
            const button = document.getElementById(buttonId);
            const submenu = document.getElementById(submenuId);
            const arrow = document.getElementById(arrowId);
            if (button) {
                button.addEventListener('click', () => {
                    submenu.classList.toggle('hidden');
                    arrow.classList.toggle('rotate-180');
                });
            }
        };

        setupDropdown('masterDataButton', 'masterDataSubmenu', 'masterDataArrow');
        setupDropdown('presensiButton', 'presensiSubmenu', 'presensiArrow');
        setupDropdown('pesertaButton', 'pesertaSubmenu', 'pesertaArrow');
        setupDropdown('kurikulumButton', 'kurikulumSubmenu', 'kurikulumArrow');
        setupDropdown('whatsappButton', 'whatsappSubmenu', 'whatsappArrow');
        setupDropdown('pengaturanButton', 'pengaturanSubmenu', 'pengaturanArrow');
        setupDropdown('musyawarahButton', 'musyawarahSubmenu', 'musyawarahArrow');
        setupDropdown('reportButton', 'reportSubmenu', 'reportArrow');
        setupDropdown('laporanButton', 'laporanSubmenu', 'laporanArrow');
        setupDropdown('developmentButton', 'developmentSubmenu', 'developmentArrow');

        window.sidebarScriptLoaded = true;
    }
</script>