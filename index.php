<?php
session_start();
// Jika sudah login, langsung redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    // Cek role dan redirect ke halaman yang sesuai
    $role = $_SESSION['user_role'] ?? '';
    switch ($role) {
        case 'admin':
        case 'superadmin':
            header('Location: admin/');
            break;
        case 'ketua pjp':
            header('Location: users/ketuapjp/');
            break;
        case 'guru':
            header('Location: users/guru/');
            break;
        default:
            // Jika tidak ada role, logout untuk keamanan
            header('Location: auth/logout.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIMAK Banguntapan 1</title>
    <link rel="icon" type="image/png" href="assets/images/logo_web_bg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Web App Manifest -->
    <link rel="manifest" href="/manifest.json">
    <!-- iOS fallback -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SIMAK">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="assets/images/logo_web_bg.png">

    <style>
        /* Animasi Selamat Datang */
        #welcome-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(16, 185, 129, 0.95);
            /* emerald-500 with opacity */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        #welcome-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .welcome-message {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            animation: slide-in 0.8s 0.2s ease forwards;
        }

        @keyframes slide-in {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Custom CSS untuk animasi spinner */
        .spinner {
            border-top-color: #3498db;
            /* Warna utama spinner */
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 hidden">
        <div class="spinner w-16 h-16 border-4 border-gray-200 rounded-full"></div>
        <span class="text-white ml-4 text-lg">Loading...</span>
    </div>

    <div id="login-box" class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md text-center transition-opacity duration-500">
        <img src="assets/images/logo_kbm.png"
            alt="Logo Aplikasi"
            class="h-20 w-20 mx-auto mb-2">
        <h2 class="text-2xl font-bold text-gray-800">
            SIMAK
        </h2>
        <h3 class="text-lg font-bold text-gray-800">
            Banguntapan 1
        </h3>
        <span class="text-sm font-bold text-gray-800">
            Sistem Informasi Monitoring Akademik
        </span>
        <hr class="mt-4">

        <h3 class="text-xl font-bold mt-2 mb-6 text-gray-800">
            Login Form
        </h3>
        <!-- Pesan Error -->
        <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 hidden"></div>

        <!-- ===== FORM LOGIN STANDAR ===== -->
        <form id="loginForm">
            <div class="mb-4 text-left">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" id="username" name="username" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-6 text-left">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200">
                Login
            </button>
        </form>

        <div class="my-6 text-gray-500 font-semibold">ATAU</div>

        <!-- ===== FITUR SCAN BARCODE ===== -->
        <div class="grid grid-cols-1 sm:grid-cols-1 gap-4">
            <!-- Tombol untuk scan via kamera -->
            <button id="start-scan-btn" class="w-full bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 flex items-center justify-center gap-2">
                <i class="fa-solid fa-qrcode"></i>
                Scan Barcode
            </button>

            <!-- <div class="my-1 text-gray-500 font-semibold">ATAU</div> -->

            <!-- Tombol untuk scan via galeri -->
            <label for="qr-input-file" class="cursor-pointer w-full bg-teal-500 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 flex items-center justify-center gap-2">
                <i class="fa-solid fa-images"></i>
                Dari Galeri
            </label>
            <input type="file" id="qr-input-file" accept="image/*" class="hidden">
        </div>

        <!-- Area untuk menampilkan video scanner -->
        <div id="scanner-container" class="mt-4 border rounded-lg overflow-hidden hidden">
            <div id="qr-reader" class="w-full"></div>
            <button id="stop-scan-btn" class="w-full bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 focus:outline-none focus:shadow-outline transition duration-200">
                Tutup Kamera
            </button>
        </div>
    </div>

    <!-- === HTML UNTUK ANIMASI SELAMAT DATANG === -->
    <div id="welcome-overlay">
        <div class="welcome-message">
            Selamat Datang,<br>
            <span id="welcome-user-name"></span>
        </div>
    </div>


    <!-- Memuat library html5-qrcode -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(() => console.log('SW terpasang'))
                .catch(err => console.error('SW gagal', err));
        }
    </script>
    <script>
        // --- Elemen DOM ---
        const loginForm = document.getElementById('loginForm');
        const startScanBtn = document.getElementById('start-scan-btn');
        const stopScanBtn = document.getElementById('stop-scan-btn');
        const qrInputFile = document.getElementById('qr-input-file');
        const scannerContainer = document.getElementById('scanner-container');
        const errorMessage = document.getElementById('error-message');
        const loadingOverlay = document.getElementById('loadingOverlay');

        const html5QrCode = new Html5Qrcode("qr-reader");

        // --- Fungsi Bantuan ---
        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.classList.remove('hidden');
        }

        function hideError() {
            errorMessage.classList.add('hidden');
        }

        // --- Fungsi Utama ---
        async function processLogin(data) {
            hideError();
            loadingOverlay.classList.remove('hidden');
            try {
                const response = await fetch('auth/login_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showWelcomeAnimation(result.nama, result.redirect_url);
                } else {
                    showError(result.message || 'Login gagal. Coba lagi.');
                }
            } catch (error) {
                showError('Terjadi kesalahan. Periksa koneksi Anda.');
            }
        }

        function showWelcomeAnimation(userName, redirectUrl) {
            const loginBox = document.getElementById('login-box');
            const welcomeOverlay = document.getElementById('welcome-overlay');
            const welcomeUserName = document.getElementById('welcome-user-name');

            if (loginBox && welcomeOverlay && welcomeUserName) {
                loginBox.style.opacity = '0';
                welcomeUserName.textContent = userName;
                welcomeOverlay.classList.add('show');
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 2500);
            }
        }

        const onScanSuccess = (decodedText, decodedResult) => {
            try {
                html5QrCode.stop();
            } catch (err) {}
            scannerContainer.classList.add('hidden');
            processLogin({
                barcode: decodedText
            });
        };

        // --- Event Listeners ---
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            processLogin(data);
        });

        startScanBtn.addEventListener('click', () => {
            hideError();
            scannerContainer.classList.remove('hidden');
            html5QrCode.start({
                    facingMode: "environment"
                }, {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                },
                onScanSuccess,
                (error) => {}
            ).catch(err => {
                showError("Tidak dapat mengakses kamera. Pastikan Anda memberikan izin.");
                scannerContainer.classList.add('hidden');
            });
        });

        stopScanBtn.addEventListener('click', () => {
            try {
                html5QrCode.stop();
            } catch (err) {}
            scannerContainer.classList.add('hidden');
        });

        qrInputFile.addEventListener('change', e => {
            if (e.target.files.length === 0) return;
            const imageFile = e.target.files[0];
            hideError();
            html5QrCode.scanFile(imageFile, true)
                .then(onScanSuccess)
                .catch(err => {
                    showError('Gagal mendeteksi barcode dari gambar yang dipilih.');
                });
        });
    </script>
</body>

</html>