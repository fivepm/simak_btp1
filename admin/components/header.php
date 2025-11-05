<?php
$nama_admin = htmlspecialchars($_SESSION['user_nama']);
// Variabel $pageTitle akan di-set di file index.php utama
?>
<header class="flex justify-between items-center p-6 bg-white border-b">
    <div class="flex items-center">
        <!-- TOMBOL HAMBURGER BARU (Hanya tampil di HP) -->
        <button id="sidebar-toggle-button" class="text-gray-500 focus:outline-none md:hidden mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        <h1 class="text-xl font-semibold"><?php echo $pageTitle; ?></h1>
    </div>

    <!-- User Dropdown -->
    <div class="relative">
        <!-- Tombol Expander (yang bisa diklik) -->
        <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
            <span>Selamat datang, <strong><?php echo $nama_admin; ?></strong>!</span>
            <!-- Ikon panah bawah -->
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <!-- Menu Dropdown (Modal yang muncul) -->
        <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
            <a href="#" onclick="event.preventDefault(); handleLogout();" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>
            <!-- Anda bisa menambahkan item menu lain di sini jika perlu -->
        </div>
    </div>
</header>

<script>
    // Pastikan skrip ini tidak dijalankan ulang jika header dimuat berkali-kali via AJAX
    if (!window.headerScriptLoaded) {
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');

        // Tampilkan/sembunyikan menu saat tombol diklik
        if (userMenuButton) {
            userMenuButton.addEventListener('click', (event) => {
                event.stopPropagation(); // Mencegah event 'click' window di bawah tertrigger
                userMenu.classList.toggle('hidden');
            });
        }

        // Sembunyikan menu jika diklik di luar area menu
        window.addEventListener('click', (event) => {
            if (userMenu && !userMenu.classList.contains('hidden')) {
                userMenu.classList.add('hidden');
            }
        });

        window.headerScriptLoaded = true;
    }
</script>