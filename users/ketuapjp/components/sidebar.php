<?php
// Variabel $currentPage akan di-set di file index.php utama
$activeClass = 'bg-green-600 text-white';
$inactiveClass = 'text-gray-300 hover:bg-green-700 hover:text-white';
$groupActiveClass = 'text-white bg-green-700';

// Ambil data admin yang sedang login dari session
$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';

// Definisikan halaman-halaman untuk setiap grup
$masterDataPages = ['master/daftar_ketua_pjp', 'master/kepengurusan', 'master/daftar_penasehat', 'master/daftar_guru', 'master/daftar_peserta'];
$isMasterDataActive = in_array($currentPage, $masterDataPages);

$presensiPages = ['presensi/periode', 'presensi/jadwal', 'presensi/kehadiran', 'presensi/jurnal'];
$isPresensiActive = in_array($currentPage, $presensiPages);

$pesertaPages = ['peserta/catatan'];
$isPesertaActive = in_array($currentPage, $pesertaPages);

// Grup baru untuk Kurikulum
$kurikulumPages = ['kurikulum/materi_hafalan', 'kurikulum/kurikulum_hafalan'];
$isKurikulumActive = in_array($currentPage, $kurikulumPages);

$pengaturanPages = ['pengaturan/template_pesan'];
$isPengaturanActive = in_array($currentPage, $pengaturanPages);
?>
<!-- Sidebar -->
<div id="sidebar-menu" class="w-64 bg-green-800 text-white flex flex-col fixed inset-y-0 left-0 z-30
    transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">

    <!-- Logo/Header Sidebar -->
    <div class="w-full px-6 py-6 border-b border-green-700 justify-center text-center">
        <img src="../../assets/images/logo_kbm.png"
            alt="Logo Aplikasi"
            class="h-10 w-10 border border-red-300 mx-auto mb-2 bg-white">
        <h2 class="text-2xl font-semibold text-white text-center">SIMAK</h2>
        <span class="text-xs text-white">Sistem Informasi Monitoring Akademik</span>
        <br>
        <span class="text-sm text-green-300">Ketua PJP Panel</span>
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
                <?php if ($ketuapjp_tingkat === 'desa'): ?>
                    <a href="?page=master/daftar_ketua_pjp" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/daftar_ketua_pjp') ? $activeClass : $inactiveClass; ?>">Daftar Ketua PJP</a>
                <?php endif; ?>
                <a href="?page=master/kepengurusan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/kepengurusan') ? $activeClass : $inactiveClass; ?>">Kepengurusan</a>
                <a href="?page=master/daftar_penasehat" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/daftar_penasehat') ? $activeClass : $inactiveClass; ?>">Penasehat</a>
                <a href="?page=master/daftar_guru" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/daftar_guru') ? $activeClass : $inactiveClass; ?>">Guru</a>
                <a href="?page=master/daftar_peserta" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'master/daftar_peserta') ? $activeClass : $inactiveClass; ?>">Peserta</a>
            </div>
        </div>

        <!-- GRUP MENU BARU: Presensi -->
        <div class="pt-2">
            <button id="presensiButton" class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg transition-colors duration-200 <?php echo $isPresensiActive ? $groupActiveClass : 'text-gray-300'; ?> hover:bg-green-700 hover:text-white focus:outline-none">
                <span class="flex items-center">
                    <i class="fa-solid fa-check-to-slot fa-fw mr-3"></i>
                    Presensi
                </span>
                <svg id="presensiArrow" class="w-5 h-5 transition-transform duration-300 <?php echo $isPresensiActive ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="presensiSubmenu" class="mt-2 space-y-1 pl-8 <?php echo $isPresensiActive ? '' : 'hidden'; ?>">
                <a href="?page=presensi/periode" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/periode') ? $activeClass : $inactiveClass; ?>">Periode</a>
                <a href="?page=presensi/jadwal" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/jadwal') ? $activeClass : $inactiveClass; ?>">Jadwal KBM</a>
                <a href="?page=presensi/kehadiran" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/kehadiran') ? $activeClass : $inactiveClass; ?>">Rekap Kehadiran</a>
                <a href="?page=presensi/jurnal" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'presensi/jurnal') ? $activeClass : $inactiveClass; ?>">Rekap Jurnal</a>
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
            </div>
        </div>

        <?php if ($ketuapjp_tingkat === 'desa'): ?>
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
                    <a href="?page=kurikulum/kurikulum_hafalan" class="block px-4 py-2 rounded-md text-sm <?php echo ($currentPage === 'kurikulum/kurikulum_hafalan') ? $activeClass : $inactiveClass; ?>">Kurikulum Hafalan</a>
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
            </div>
        </div>

        <a href="?page=pustaka_materi/index" class="flex items-center px-4 py-2.5 rounded-lg <?php echo ($currentPage === 'pustaka_materi/index') ? $activeClass : $inactiveClass; ?>">
            <i class="fa-solid fa-book fa-fw mr-3"></i>
            Pustaka Materi
        </a>

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
        setupDropdown('pengaturanButton', 'pengaturanSubmenu', 'pengaturanArrow');

        window.sidebarScriptLoaded = true;
    }
</script>