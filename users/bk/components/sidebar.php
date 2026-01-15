<?php
// Variabel $currentPage akan di-set di file index.php utama
$activeClass = 'bg-green-600 text-white';
$inactiveClass = 'text-gray-300 hover:bg-green-700 hover:text-white';

// Grup baru untuk Pustaka Materi
$pustakaMateriPages = ['pustaka_materi/index', 'pustaka_materi/detail_materi'];
$ispustakaMateriActive = in_array($currentPage, $pustakaMateriPages);
?>
<!-- PERBAIKAN: Tambahkan id="sidebar-menu-guru" di sini -->
<div id="sidebar-menu-guru" class="w-64 bg-green-800 text-white flex flex-col fixed inset-y-0 left-0 z-30
    transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">

    <!-- Logo/Header Sidebar -->
    <div class="w-full px-6 py-6 border-b border-green-700 justify-center text-center">
        <img src="../../assets/images/logo_kbm.png"
            alt="Logo Aplikasi"
            class="h-10 w-10 border border-red-300 mx-auto mb-2 bg-white">
        <h2 class="text-2xl font-semibold text-white text-center">SIMAK</h2>
        <span class="text-xs text-white">Sistem Informasi Monitoring Akademik</span>
        <br>
        <span class="text-sm text-green-300">BK Panel</span>
    </div>

    <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
        <a href="?page=dashboard" class="flex items-center px-4 py-2.5 rounded-lg <?php echo ($currentPage === 'dashboard') ? $activeClass : $inactiveClass; ?>">
            <i class="fas fa-home fa-fw mr-3"></i>
            Dashboard
        </a>
        <a href="?page=catatan" class="flex items-center px-4 py-2.5 rounded-lg <?php echo ($currentPage === 'catatan') ? $activeClass : $inactiveClass; ?>">
            <i class="fa-solid fa-book fa-fw mr-3"></i>
            Buku Konseling
        </a>
        <a href="?page=pustaka_materi/index" class="flex items-center px-4 py-2.5 rounded-lg <?php echo $ispustakaMateriActive ? $activeClass : $inactiveClass; ?>">
            <i class="fas fa-book-open fa-fw mr-3"></i>
            Pustaka Materi
        </a>
    </nav>
</div>