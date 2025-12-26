<?php
session_start();
// === KONEKSI DATABASE TERPUSAT ===
require_once __DIR__ . '/config/config.php';
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

// 3. LOGIKA BARU YANG LEBIH KETAT:
// Jika maintenance AKTIF dan Anda BUKAN admin (yang sudah login),
// BLOKIR SEMUANYA.
if ($isMaintenance) {
    // Tampilkan halaman maintenance dan hentikan skrip.
    // Ini akan memblokir halaman login, dashboard, dll.
    header("Location: maintenance");
    exit;
}
// --- LOGIKA MAINTENANCE MODE SELESAI ---
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
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            animation: slide-in 0.8s 0.2s ease forwards;
        }

        .welcome-message-role {
            color: white;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
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

        /* PIN Input Styles */
        .pin-input {
            width: 3rem;
            height: 3rem;
            font-size: 1.5rem;
            text-align: center;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .pin-input:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 hidden">
        <div class="spinner w-16 h-16 border-4 border-gray-200 rounded-full"></div>
        <span class="text-white ml-4 text-lg">Loading...</span>
    </div>

    <!-- MODAL PIN -->
    <div id="pinModal" class="fixed inset-0 bg-black bg-opacity-80 flex justify-center items-center z-40 hidden">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-sm text-center transform transition-all scale-100">
            <div class="mb-4">
                <div class="mx-auto bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mb-2">
                    <i class="fa-solid fa-lock text-2xl text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Masukkan PIN</h3>
                <p class="text-sm text-gray-500">Halo, <span id="pin-user-name" class="font-bold text-gray-700">User</span></p>
            </div>

            <div id="pin-error" class="text-red-500 text-sm mb-3 hidden font-bold">PIN Salah!</div>

            <div class="flex justify-center gap-2 mb-6">
                <!-- Single Input Logic untuk Mobile Friendly -->
                <input type="password" id="pin-input-field" maxlength="6" inputmode="numeric" autocomplete="off"
                    class="w-full text-center text-3xl tracking-[1em] border-b-2 border-gray-300 focus:border-green-500 focus:outline-none py-2 font-bold text-gray-700"
                    placeholder="••••••" autofocus>
            </div>

            <button id="submit-pin-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg shadow transition duration-200">
                Masuk
            </button>
            <button id="cancel-pin-btn" class="mt-3 text-gray-500 text-sm hover:text-gray-700 font-medium">
                Batal / Ganti Akun
            </button>
        </div>
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
            LOGIN
        </h3>
        <!-- Pesan Error -->
        <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 hidden"></div>

        <!-- ===== FITUR SCAN BARCODE ===== -->
        <div class="grid grid-cols-1 sm:grid-cols-1 gap-4">
            <!-- Tombol untuk scan via kamera -->
            <button id="start-scan-btn" class="w-full bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 flex items-center justify-center gap-2">
                <i class="fa-solid fa-qrcode"></i>
                Scan Barcode
            </button>

            <div class="my-1 text-gray-500 font-semibold">ATAU</div>

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
            <span id="welcome-user-name"></span><br>
            <span id="welcome-user-role" class="welcome-message-role"></span>
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
        // DOM Elements
        const startScanBtn = document.getElementById('start-scan-btn');
        const stopScanBtn = document.getElementById('stop-scan-btn');
        const qrInputFile = document.getElementById('qr-input-file');
        const scannerContainer = document.getElementById('scanner-container');
        const errorMessage = document.getElementById('error-message');
        const loadingOverlay = document.getElementById('loadingOverlay');

        // PIN Modal Elements
        const pinModal = document.getElementById('pinModal');
        const pinInput = document.getElementById('pin-input-field');
        const submitPinBtn = document.getElementById('submit-pin-btn');
        const cancelPinBtn = document.getElementById('cancel-pin-btn');
        const pinError = document.getElementById('pin-error');
        const pinUserName = document.getElementById('pin-user-name');

        let currentBarcode = null; // Menyimpan barcode sementara
        const html5QrCode = new Html5Qrcode("qr-reader");

        function showError(msg) {
            errorMessage.textContent = msg;
            errorMessage.classList.remove('hidden');
        }

        function hideError() {
            errorMessage.classList.add('hidden');
        }

        // Fungsi Login Utama
        async function processLogin(data) {
            hideError();
            if (!data.pin) loadingOverlay.classList.remove('hidden'); // Show loading only for initial scan

            try {
                const response = await fetch('auth/login_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                loadingOverlay.classList.add('hidden');

                if (result.success) {
                    // STEP 1: Barcode Valid, Minta PIN
                    if (result.require_pin) {
                        currentBarcode = data.barcode; // Simpan barcode
                        pinUserName.textContent = result.nama; // Tampilkan nama user

                        // Buka Modal PIN
                        pinModal.classList.remove('hidden');
                        pinInput.value = '';
                        pinError.classList.add('hidden');
                        pinInput.focus();
                    }
                    // STEP 2: Login Sukses Sepenuhnya
                    else {
                        pinModal.classList.add('hidden'); // Tutup modal jika ada
                        showWelcomeAnimation(result.nama, result.tampilan_role, result.redirect_url);
                    }
                } else {
                    // Error Handling
                    if (data.pin) {
                        // Error saat verifikasi PIN
                        pinError.textContent = result.message;
                        pinError.classList.remove('hidden');
                        pinInput.value = '';
                        pinInput.focus();
                    } else {
                        // Error saat scan barcode
                        showError(result.message || 'Login gagal.');
                    }
                }
            } catch (error) {
                loadingOverlay.classList.add('hidden');
                console.error(error);
                if (data.pin) {
                    pinError.textContent = "Gagal terhubung ke server.";
                    pinError.classList.remove('hidden');
                } else {
                    showError('Terjadi kesalahan koneksi.');
                }
            }
        }

        // Logic Input PIN
        submitPinBtn.addEventListener('click', () => {
            const pin = pinInput.value;
            if (pin.length < 6) {
                pinError.textContent = "PIN harus 6 digit.";
                pinError.classList.remove('hidden');
                return;
            }
            // Kirim Barcode + PIN ke server
            processLogin({
                barcode: currentBarcode,
                pin: pin
            });
        });

        // Submit PIN dengan Enter
        pinInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') submitPinBtn.click();
        });

        // Batalkan PIN
        cancelPinBtn.addEventListener('click', () => {
            pinModal.classList.add('hidden');
            currentBarcode = null;
            pinInput.value = '';
        });

        // Welcome Animation
        function showWelcomeAnimation(name, role, url) {
            const loginBox = document.getElementById('login-box');
            const welcomeOverlay = document.getElementById('welcome-overlay');

            if (loginBox) loginBox.style.opacity = '0';
            document.getElementById('welcome-user-name').textContent = name;
            document.getElementById('welcome-user-role').textContent = role;

            welcomeOverlay.classList.add('show');
            setTimeout(() => {
                window.location.href = url;
            }, 2000);
        }

        // Scanner Logic
        const onScanSuccess = (decodedText) => {
            try {
                html5QrCode.stop();
            } catch (err) {}
            scannerContainer.classList.add('hidden');
            processLogin({
                barcode: decodedText
            });
        };

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
                }, onScanSuccess, (err) => {})
                .catch(() => {
                    showError("Izin kamera ditolak.");
                    scannerContainer.classList.add('hidden');
                });
        });

        stopScanBtn.addEventListener('click', () => {
            try {
                html5QrCode.stop();
            } catch (e) {}
            scannerContainer.classList.add('hidden');
        });

        qrInputFile.addEventListener('change', e => {
            if (e.target.files.length === 0) return;
            hideError();
            html5QrCode.scanFile(e.target.files[0], true).then(onScanSuccess).catch(() => showError('Barcode tidak terbaca.'));
        });
    </script>
</body>

</html>