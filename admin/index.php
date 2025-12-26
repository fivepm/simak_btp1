<?php
session_start();
// === HELPER CCTV ===
require_once __DIR__ . '/../helpers/log_helper.php';
// === KONEKSI DATABASE TERPUSAT ===
require_once __DIR__ . '/../config/config.php';
if (!isset($conn) || $conn->connect_error) {
    die("Koneksi database gagal.");
}

// --- LOGIKA MAINTENANCE MODE DARI DATABASE (VERSI BARU - LEBIH KETAT) ---

// 1. Ambil status dari database
$maintenance_status = 'false'; // Default
if (isset($conn)) {
    $sql_maint = "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'";
    $result_maint = mysqli_query($conn, $sql_maint);
    if ($result_maint) {
        $row_maint = mysqli_fetch_assoc($result_maint);
        $maintenance_status = $row_maint['setting_value'] ?? 'false';
    }
}

// 2. Konversi ke boolean
$isMaintenance = filter_var($maintenance_status, FILTER_VALIDATE_BOOLEAN);

// 3. Cek bypass untuk Admin (HANYA JIKA SUDAH LOGIN)
$isSuperAdmin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'superadmin');

// 4. LOGIKA BARU YANG LEBIH KETAT:
// Jika maintenance AKTIF dan Anda BUKAN admin (yang sudah login),
// BLOKIR SEMUANYA.
if ($isMaintenance && !$isSuperAdmin) {
    // Tampilkan halaman maintenance dan hentikan skrip.
    // Ini akan memblokir halaman login, dashboard, dll.
    header("Location: ../maintenance");
    exit;
}
// --- LOGIKA MAINTENANCE MODE SELESAI ---

// 🔐 SECURITY CHECK
$allowed_roles = ['superadmin', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../");
    exit;
}

// Ambil data admin yang sedang login dari session
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';

require_once 'helpers/fonnte_helper.php';
require_once 'helpers/template_helper.php';
require_once 'helpers/whatsapp_helper.php';

// --- ROUTING ---
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard',
    'grafik_kehadiran',
    //Profile
    'profile/index',
    // Halaman Master
    'master/kelola_pengguna',
    'master/kelola_ketua_pjp',
    'master/kepengurusan',
    'master/kelola_penasehat',
    'master/kelola_guru',
    'master/kelola_peserta',
    // Halaman Presensi Baru
    'presensi/periode',
    'presensi/jadwal',
    'presensi/atur_guru',
    'presensi/atur_penasehat',
    'presensi/input_presensi',
    'presensi/kehadiran',
    'presensi/jurnal',
    // Halaman Peserta
    'peserta/catatan',
    'peserta/kartu_hafalan',
    //Halaman Kurikulum
    'kurikulum/materi_hafalan',
    'kurikulum/kurikulum_hafalan',
    //Pustaka Materi
    'pustaka_materi/index',
    'pustaka_materi/detail_materi',
    //Halaman Pengaturan
    'pengaturan/template_pesan',
    'pengaturan/grup_whatsapp',
    'pengaturan/pesan_terjadwal',
    'pengaturan/tes_fonnte',
    'pengaturan/pengumuman',
    'pengaturan/pengaturan_pengingat',
    'pengaturan/daftar_chat',
    'pengaturan/riwayat_chat',
    //Halaman Musyawarah
    'musyawarah/daftar_musyawarah',
    'musyawarah/ringkasan_musyawarah',
    'musyawarah/daftar_notulensi',
    'musyawarah/catat_notulensi',
    'musyawarah/lihat_notulensi',
    'musyawarah/evaluasi_notulensi',
    'musyawarah/daftar_kehadiran',
    'musyawarah/daftar_hadir',
    'musyawarah/lihat_kehadiran',
    //Laporan
    'laporan/laporan_kelompok',
    'laporan/laporan_detail',
    //report
    'report/daftar_laporan_harian',
    'report/form_laporan_harian',
    'report/lihat_laporan_harian',
    'report/daftar_laporan_mingguan',
    'report/form_laporan_mingguan',
    'report/lihat_laporan_mingguan',
    //Development
    'development/maintenance',
    'development/log_error',
    'development/backup_db',
    'development/server_info',
    'development/activity_log',
    'development/laporan_dev',
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
    //General
    case 'dashboard':
        $pageTitle = 'Dashboard';
        break;
    case 'grafik_kehadiran':
        $pageTitle = 'Grafik Kehadiran';
        break;
    //Profile
    case 'profile/index':
        $pageTitle = 'Profile';
        break;
    //Master Data
    case 'master/kelola_pengguna':
        $pageTitle = 'Kelola Admin';
        break;
    case 'master/kelola_ketua_pjp':
        $pageTitle = 'Kelola Ketua PJP';
        break;
    case 'master/kepengurusan':
        $pageTitle = 'Kepengurusan';
        break;
    case 'master/kelola_penasehat':
        $pageTitle = 'Kelola Penasehat';
        break;
    case 'master/kelola_guru':
        $pageTitle = 'Kelola Guru';
        break;
    case 'master/kelola_peserta':
        $pageTitle = 'Kelola Peserta';
        break;
    //Presensi
    case 'presensi/periode':
        if ($admin_tingkat === 'desa'):
            $pageTitle = 'Atur Periode Presensi';
        elseif ($admin_tingkat === 'kelompok'):
            $pageTitle = 'Daftar Periode Presensi';
        endif;
        break;
    case 'presensi/jadwal':
        $pageTitle = 'Atur Jadwal Presensi';
        break;
    case 'presensi/atur_guru':
        $pageTitle = 'Atur Jadwal Guru';
        break;
    case 'presensi/atur_penasehat':
        $pageTitle = 'Atur Jadwal Penasehat';
        break;
    case 'presensi/input_presensi':
        $pageTitle = 'Input Presensi';
        break;
    case 'presensi/kehadiran':
        $pageTitle = 'Rekap Kehadiran';
        break;
    case 'presensi/jurnal':
        $pageTitle = 'Jurnal Harian';
        break;
    //Peserta
    case 'peserta/catatan':
        $pageTitle = 'Catatan BK';
        break;
    case 'peserta/kartu_hafalan':
        $pageTitle = 'Kartu Hafalan Siswa';
        break;
    //Kurikulum
    case 'kurikulum/materi_hafalan':
        $pageTitle = 'Daftar Materi Hafalan';
        break;
    case 'kurikulum/kurikulum_hafalan':
        $pageTitle = 'Atur Kurikulum Hafalan';
        break;
    //Pengaturan
    case 'pengaturan/template_pesan':
        $pageTitle = 'Template Pesan';
        break;
    case 'pengaturan/grup_whatsapp':
        $pageTitle = 'Grup Whatsapp';
        break;
    case 'pengaturan/pesan_terjadwal':
        $pageTitle = 'Pesan Terjadwal';
        break;
    case 'pengaturan/tes_fonnte':
        $pageTitle = 'Tes Device Fonnte';
        break;
    case 'pengaturan/pengumuman':
        $pageTitle = 'Pengumuman';
        break;
    case 'pengaturan/pengaturan_pengingat':
        $pageTitle = 'Pengaturan Pengingat';
        break;
    case 'pengaturan/daftar_chat':
        $pageTitle = 'Daftar Riwayat Chat';
        break;
    case 'pengaturan/riwayat_chat':
        $pageTitle = 'Riwayat Chat';
        break;
    //Pustaka Materi
    case 'pustaka_materi/index':
        $pageTitle = 'Pustaka Materi';
        break;
    case 'pustaka_materi/detail_materi':
        $pageTitle = 'Detail Materi';
        break;
    //Musyawarah
    case 'musyawarah/daftar_musyawarah':
        $pageTitle = 'Daftar Musyawarah';
        break;
    case 'musyawarah/ringkasan_musyawarah':
        $pageTitle = 'Hasil Musyawarah';
        break;
    case 'musyawarah/daftar_notulensi':
        $pageTitle = 'Daftar Notulensi';
        break;
    case 'musyawarah/catat_notulensi':
        $pageTitle = 'Catat Notulensi';
        break;
    case 'musyawarah/lihat_notulensi':
        $pageTitle = 'Hasil Musyawarah';
        break;
    case 'musyawarah/evaluasi_notulensi':
        $pageTitle = 'Evaluasi Notulensi';
        break;
    case 'musyawarah/daftar_kehadiran':
        $pageTitle = 'Daftar Kehadiran Musyawarah';
        break;
    case 'musyawarah/daftar_hadir':
        $pageTitle = 'Isi Kehadiran Musyawarah';
        break;
    case 'musyawarah/lihat_kehadiran':
        $pageTitle = 'Lihat Kehadiran Musyawarah';
        break;
    //Laporan
    case 'laporan/laporan_kelompok':
        $pageTitle = 'Daftar Laporan Kelompok';
        break;
    case 'laporan/laporan_detail':
        $pageTitle = 'Detail Laporan Kelompok';
        break;
    //Report
    case 'report/daftar_laporan_harian':
        $pageTitle = 'Daftar Report Harian';
        break;
    case 'report/form_laporan_harian':
        $pageTitle = 'Form Report Harian';
        break;
    case 'report/lihat_laporan_harian':
        $pageTitle = 'Lihat Report Harian';
        break;
    case 'report/daftar_laporan_mingguan':
        $pageTitle = 'Daftar Report Mingguan';
        break;
    case 'report/form_laporan_mingguan':
        $pageTitle = 'Form Report Mingguan';
        break;
    case 'report/lihat_laporan_mingguan':
        $pageTitle = 'Lihat Report Mingguan';
        break;
    //Development
    case 'development/maintenance':
        $pageTitle = 'Maintenance';
        break;
    case 'development/log_error':
        $pageTitle = 'Log Error';
        break;
    case 'development/backup_db':
        $pageTitle = 'Backup Database';
        break;
    case 'development/server_info':
        $pageTitle = 'Informasi Server';
        break;
    case 'development/activity_log':
        $pageTitle = 'Log Aktivitas';
        break;
    case 'development/laporan_dev':
        $pageTitle = 'Laporan Developer';
        break;

    //Error
    default:
        $pageTitle = '404 Not Found';
        break;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Panel</title>
    <link rel="icon" type="image/png" href="../assets/images/logo_web_bg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <!-- Web App Manifest -->
    <link rel="manifest" href="/manifest.json">
    <!-- iOS fallback -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SIMAK">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="../assets/images/logo_web_bg.png">

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
    <div id="logout-overlay" class="fixed inset-0 z-[999] flex flex-col items-center justify-center bg-gray-900 bg-opacity-75 transition-opacity duration-300 ease-in-out opacity-0 hidden">
        <div class="w-16 h-16 border-4 border-t-4 border-t-cyan-500 border-gray-600 rounded-full animate-spin"></div>
        <p class="mt-4 text-white text-lg font-semibold">Logging out...</p>
    </div>
    <?php
    // Screenshot Guard
    include '../helpers/screenshot_guard.php';
    ?>
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
        // ==============================================
        // ▼▼▼ JavaScript untuk Fungsi Logout ▼▼▼
        // ==============================================
        function handleLogout() {
            const overlay = document.getElementById('logout-overlay');
            if (!overlay) {
                console.error("Elemen logout-overlay tidak ditemukan!");
                // Fallback jika overlay tidak ada
                window.location.href = '../auth/logout'; // Langsung logout paksa
                return;
            }

            // 1. Tampilkan Overlay dengan fade-in
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
            }, 10); // delay kecil untuk trigger transisi CSS

            // 2. Panggil file logout.php di server setelah animasi terlihat
            setTimeout(() => {
                fetch('../auth/logout.php', { // Pastikan path ke logout.php benar
                        method: 'POST', // Gunakan POST agar tidak di-cache
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest' // Tanda ini request AJAX
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // 3. Sukses, tunggu sebentar lalu redirect ke login
                            setTimeout(() => {
                                // Ganti 'login.php' dengan halaman login Anda
                                window.location.href = '../';
                            }, 500); // Beri waktu 0.5 detik agar user melihat animasi
                        } else {
                            // Gagal logout (jarang terjadi)
                            alert('Logout gagal. Mencoba redirect paksa...');
                            window.location.href = '../';
                        }
                    })
                    .catch(error => {
                        console.error('Error saat logout:', error);
                        // Jika fetch gagal (misal server down), redirect paksa
                        alert('Error koneksi saat logout. Redirecting...');
                        window.location.href = '../';
                    });
            }, 500); // Mulai proses logout setelah 0.5 detik animasi
        }
        // ==============================================
        // ▲▲▲ AKHIR JavaScript Logout ▲▲▲
        // ==============================================
    </script>

    <!-- EKSEKUSI NOTIFIKASI DARI PHP -->
    <?php if (!empty($swal_notification)): ?>
        <script>
            <?= $swal_notification ?>
        </script>
    <?php endif; ?>
</body>

</html>