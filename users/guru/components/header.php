<?php
$nama_guru = htmlspecialchars($_SESSION['user_nama']);
// Variabel $pageTitle akan di-set di file index.php utama
?>
<header class="flex justify-between items-center p-6 bg-white border-b">
    <div class="flex items-center">
        <!-- Tombol Hamburger (Hanya tampil di HP) -->
        <button id="sidebar-toggle-button-guru" class="text-gray-500 focus:outline-none md:hidden mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        <h1 class="text-xl font-semibold"><?php echo $pageTitle; ?></h1>
    </div>

    <!-- User Dropdown -->
    <div class="relative">
        <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
            <!-- <span>Selamat datang, <strong><?php echo $nama_guru; ?></strong>!</span> -->
            <i class="fa-solid fa-right-to-bracket"></i>
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
            <!-- Header Mobile di Dropdown -->
            <div class="px-4 py-2 border-b border-gray-100 sm:hidden">
                <div class="font-bold text-gray-800"><?php echo $nama_guru; ?></div>
                <?php if ($_SESSION['user_role'] === 'guru' && !empty($_SESSION['user_kelas'])): ?>
                    <div class="text-xs text-indigo-600">Kelas <?php echo ucfirst($_SESSION['user_kelas']); ?></div>
                <?php endif; ?>
            </div>

            <!-- TOMBOL GANTI KELAS (Hanya muncul jika multi-kelas) -->
            <?php if (isset($_SESSION['is_multi_kelas']) && $_SESSION['is_multi_kelas'] === true): ?>
                <a href="pilih_kelas.php" class="flex items-center gap-2 px-4 py-3 text-sm text-indigo-700 hover:bg-indigo-50 border-b border-gray-100 transition-colors">
                    <i class="fa-solid fa-repeat"></i>
                    Ganti Kelas
                </a>
            <?php endif; ?>

            <a href="#" onclick="event.preventDefault(); handleLogout();" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>
        </div>
    </div>
</header>
<script>
    // Pastikan skrip ini hanya dimuat sekali
    if (!window.headerScriptLoaded) {
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');

        if (userMenuButton) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation(); // Mencegah window.click langsung menutupnya
                userMenu.classList.toggle('hidden');
            });
        }

        // Menutup dropdown jika diklik di luar
        window.addEventListener('click', (e) => {
            if (userMenu && !userMenu.classList.contains('hidden')) {
                userMenu.classList.add('hidden');
            }
        });

        window.headerScriptLoaded = true;
    }
</script>