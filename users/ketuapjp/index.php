<?php
session_start();

// 🔐 SECURITY CHECK
$allowed_roles = ['ketua pjp'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../../index.php");
    exit;
}

// Ambil data admin yang sedang login dari session
$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';

// === KONEKSI DATABASE TERPUSAT ===
require_once __DIR__ . '/../../config/config.php';
if (!isset($conn) || $conn->connect_error) {
    die("Koneksi database gagal.");
}

// require_once 'helpers/fonnte_helper.php';
// require_once 'helpers/template_helper.php';
// require_once 'helpers/whatsapp_helper.php';

// --- ROUTING ---
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard',
    // Halaman Master
    'master/daftar_ketua_pjp',
    'master/kepengurusan',
    'master/daftar_penasehat',
    'master/daftar_guru',
    'master/daftar_peserta',
    // Halaman Presensi Baru
    'presensi/periode',
    'presensi/jadwal',
    'presensi/kehadiran',
    'presensi/jurnal',
    // Halaman Peserta
    'peserta/catatan',
    //Halaman Kurikulum
    'kurikulum/materi_hafalan',
    'kurikulum/kurikulum_hafalan',
    //Pustaka Materi
    'pustaka_materi/index',
    'pustaka_materi/detail_materi',
    //Halaman Pengaturan
    'pengaturan/template_pesan',
];

if (in_array($page, $allowedPages) && strpos($page, '..') === false) {
    $currentPage = $page;
    $pagePath = "pages/{$currentPage}.php";
} else {
    $currentPage = 'dashboard';
    $pagePath = "pages/dashboard.php";
}

// Tentukan judul halaman
switch ($currentPage) {
    //Master Data
    case 'master/daftar_ketua_pjp':
        $pageTitle = 'Daftar Ketua PJP';
        break;
    case 'master/kepengurusan':
        $pageTitle = 'Kepengurusan';
        break;
    case 'master/daftar_penasehat':
        $pageTitle = 'Daftar Penasehat';
        break;
    case 'master/daftar_guru':
        $pageTitle = 'Daftar Guru';
        break;
    case 'master/daftar_peserta':
        $pageTitle = 'Daftar Peserta';
        break;
    // Presensi
    case 'presensi/periode':
        $pageTitle = 'Daftar Periode';
        break;
    case 'presensi/jadwal':
        $pageTitle = 'Daftar Jadwal';
        break;
    case 'presensi/kehadiran':
        $pageTitle = 'Rekap Kehadiran';
        break;
    case 'presensi/jurnal':
        $pageTitle = 'Rekap Jurnal';
        break;
    // Peserta
    case 'peserta/catatan':
        $pageTitle = 'Catatan BK';
        break;
    // Kurikulum
    case 'kurikulum/materi_hafalan':
        $pageTitle = 'Daftar Materi Hafalan';
        break;
    case 'kurikulum/kurikulum_hafalan':
        $pageTitle = 'Kurikulum Hafalan';
        break;
    //Pengaturan
    case 'pengaturan/template_pesan':
        $pageTitle = 'Template Pesan';
        break;
    //Pustaka Materi
    case 'pustaka_materi/index':
        $pageTitle = 'Pustaka Materi';
        break;
    case 'pustaka_materi/detail_materi':
        $pageTitle = 'Detail Materi';
        break;
    default:
        $pageTitle = 'Dashboard';
        break;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Ketua PJP Panel</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo_web_bg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Web App Manifest -->
    <link rel="manifest" href="/manifest.json">
    <!-- iOS fallback -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SIMAK">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="../../assets/images/logo_web_bg.png">

    <style>
        /* CSS untuk animasi loading halaman */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        #loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 56px;
            height: 56px;
            border: 7px solid #4ade80;
            border-bottom-color: #166534;
            border-radius: 50%;
            animation: rotation 1s linear infinite;
        }

        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <!-- Overlay untuk background saat sidebar terbuka di HP -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden"></div>
    <div class="flex">
        <?php include 'components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
            <?php include 'components/header.php'; ?>
            <main class="flex-1 overflow-y-auto overflow-x-auto bg-gray-100 p-6">
                <?php include $pagePath; ?>
            </main>
        </div>
    </div>
    <div id="loading-overlay">
        <div class="spinner"></div>
    </div>
    <script>
        // JavaScript untuk loading animasi
        document.addEventListener('DOMContentLoaded', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const sidebarLinks = document.querySelectorAll('.w-64 nav a');
            const allForms = document.querySelectorAll('main form');

            const showLoader = () => {
                if (loadingOverlay) loadingOverlay.classList.add('show');
            };

            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') && link.getAttribute('href') !== '#') {
                    link.addEventListener('click', function(event) {
                        event.preventDefault();
                        const destination = this.href;
                        showLoader();
                        setTimeout(() => {
                            window.location.href = destination;
                        }, 1000);
                    });
                }
            });

            allForms.forEach(form => {
                form.addEventListener('submit', showLoader);
            });

            window.addEventListener('pageshow', function(event) {
                if (event.persisted && loadingOverlay) {
                    loadingOverlay.classList.remove('show');
                }
            });
        });

        // === JAVASCRIPT BARU UNTUK SIDEBAR RESPONSIVE ===
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar-menu');
            const toggleButton = document.getElementById('sidebar-toggle-button');
            const overlay = document.getElementById('sidebar-overlay');

            const openSidebar = () => {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            };

            const closeSidebar = () => {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            };

            if (toggleButton) {
                toggleButton.addEventListener('click', openSidebar);
            }

            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>

</html>