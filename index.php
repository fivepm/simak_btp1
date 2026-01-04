<?php
session_start();
// === KONEKSI DATABASE TERPUSAT ===
require_once __DIR__ . '/config/config.php';
if (!isset($conn) || $conn->connect_error) {
    die("Koneksi database gagal.");
}

// --- LOGIKA MAINTENANCE MODE ---
$maintenance_status = 'false';
if (isset($conn)) {
    $sql_maint = "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'";
    $result_maint = mysqli_query($conn, $sql_maint);
    if ($result_maint) {
        $row_maint = mysqli_fetch_assoc($result_maint);
        $maintenance_status = $row_maint['setting_value'] ?? 'false';
    }
}

$isMaintenance = filter_var($maintenance_status, FILTER_VALIDATE_BOOLEAN);

if ($isMaintenance) {
    header("Location: maintenance");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - SIMAK Banguntapan 1</title>
    <link rel="icon" type="image/png" href="assets/images/logo_web_bg.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#10b981">

    <style>
        /* Base Style */
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        /* Glassmorphism Card */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        /* Overlay Animasi */
        #welcome-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
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

        .welcome-content {
            text-align: center;
            color: white;
        }

        .welcome-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #059669;
            opacity: 0;
            transform: scale(0.5);
            animation: pop-in 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .welcome-text {
            opacity: 0;
            transform: translateY(20px);
            animation: slide-up 0.6s 0.2s ease forwards;
        }

        @keyframes pop-in {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slide-up {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Spinner */
        .spinner {
            border-top-color: #10b981;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* PIN Input */
        .pin-input {
            letter-spacing: 0.5em;
            font-size: 1.5rem;
            text-align: center;
            transition: all 0.3s;
        }

        .pin-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            outline: none;
        }
    </style>
</head>

<body class="flex flex-col items-center justify-center min-h-screen px-4 py-6">

    <!-- Background Decoration -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute -top-20 -left-20 w-72 h-72 bg-green-200 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob"></div>
        <div class="absolute top-40 -right-20 w-72 h-72 bg-emerald-200 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob animation-delay-2000"></div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-white/80 backdrop-blur-sm z-[60] hidden flex flex-col justify-center items-center">
        <div class="spinner w-12 h-12 border-4 border-gray-200 rounded-full mb-3"></div>
        <span class="text-gray-600 font-medium text-sm animate-pulse">Memproses Data...</span>
    </div>

    <!-- Modal PIN -->
    <div id="pinModal" class="fixed inset-0 bg-black/60 z-50 hidden flex justify-center items-center backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-6 transform transition-all scale-100">
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-shield-halved text-2xl text-green-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Verifikasi Keamanan</h3>
                <p class="text-xs text-gray-500 mt-1">Halo, <span id="pin-user-name" class="font-bold text-gray-800">User</span></p>
            </div>

            <div id="pin-error" class="text-red-500 text-xs text-center mb-3 hidden font-semibold bg-red-50 p-2 rounded">PIN Salah!</div>

            <div class="mb-6 relative">
                <input type="password" id="pin-input-field" maxlength="6" inputmode="numeric" autocomplete="off"
                    class="pin-input w-full py-3 border-2 border-gray-200 rounded-xl bg-gray-50 font-bold text-gray-800"
                    placeholder="••••••" autofocus>
            </div>

            <div class="space-y-3">
                <button id="submit-pin-btn" class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800 text-white font-bold py-3 rounded-xl shadow-lg shadow-green-200 transition-all transform active:scale-95">
                    Masuk
                </button>
                <button id="cancel-pin-btn" class="w-full text-gray-400 hover:text-gray-600 text-sm font-medium py-2">
                    Batal / Ganti Akun
                </button>
            </div>
        </div>
    </div>

    <!-- MAIN CARD -->
    <div id="login-box" class="login-card w-full max-w-sm rounded-3xl shadow-xl overflow-hidden transition-all duration-500">
        <!-- Logo Section -->
        <div class="bg-gradient-to-b from-green-50 to-white pt-8 pb-4 px-6 text-center">
            <img src="assets/images/logo_kbm.png" alt="Logo" class="h-20 w-20 mx-auto mb-3 drop-shadow-md hover:scale-105 transition-transform duration-300">
            <h1 class="text-2xl font-extrabold text-gray-800 tracking-tight">SIMAK</h1>
            <p class="text-xs font-semibold text-green-600 tracking-widest uppercase mb-1">Banguntapan 1</p>
            <p class="text-[10px] text-gray-400">Sistem Informasi Monitoring Akademik</p>
        </div>

        <div class="px-6 pb-8 pt-2">
            <!-- Alert Message -->
            <div id="error-message" class="hidden mb-4 p-3 bg-red-50 border border-red-100 text-red-600 text-xs rounded-lg flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span id="error-text">Error message here</span>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button id="start-scan-btn" class="group w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-gray-200 transition-all flex items-center justify-center gap-3 relative overflow-hidden">
                    <div class="absolute inset-0 w-full h-full bg-white/10 scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
                    <i class="fa-solid fa-qrcode text-lg"></i>
                    <span>Scan Kartu Akses</span>
                </button>

                <div class="relative flex py-1 items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="flex-shrink-0 mx-3 text-xs text-gray-400 font-medium">ATAU</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>

                <label for="qr-input-file" class="cursor-pointer group w-full bg-white border-2 border-gray-100 hover:border-green-500 text-gray-600 hover:text-green-600 font-bold py-3.5 px-4 rounded-xl transition-all flex items-center justify-center gap-3">
                    <i class="fa-regular fa-image text-lg"></i>
                    <span>Upload QR dari Galeri</span>
                </label>
                <input type="file" id="qr-input-file" accept="image/*" class="hidden">
            </div>

            <!-- Scanner View -->
            <div id="scanner-container" class="mt-4 hidden rounded-xl overflow-hidden border-2 border-gray-200 relative bg-black">
                <div id="qr-reader" class="w-full h-64 bg-black"></div>
                <button id="stop-scan-btn" class="absolute top-2 right-2 bg-white/20 backdrop-blur-md hover:bg-white/40 text-white p-2 rounded-full transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="absolute bottom-4 left-0 right-0 text-center">
                    <span class="bg-black/50 text-white text-xs px-3 py-1 rounded-full backdrop-blur-sm">Arahkan kamera ke QR Code</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER VERSION -->
    <div class="mt-8 text-center">
        <p class="text-xs text-gray-400 mb-1">&copy; <?php echo date('Y'); ?> PJP Banguntapan 1</p>
        <div class="inline-flex items-center gap-1 bg-white/50 px-2 py-1 rounded-md border border-white/60 backdrop-blur-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
            <span class="text-[10px] font-mono text-gray-500">Versi 2.1.1</span>
        </div>
    </div>

    <!-- Welcome Overlay -->
    <div id="welcome-overlay">
        <div class="welcome-content">
            <div class="welcome-avatar">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="welcome-text">
                <h2 class="text-2xl font-bold mb-1">Selamat Datang!</h2>
                <p id="welcome-user-name" class="text-xl font-medium mb-1"></p>
                <span id="welcome-user-role" class="inline-block bg-white/20 px-3 py-1 rounded-full text-xs uppercase tracking-wider"></span>
            </div>
        </div>
    </div>

    <!-- Logic Script -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        // --- SERVICE WORKER (PWA) ---
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }

        // --- DOM ELEMENTS ---
        const els = {
            startBtn: document.getElementById('start-scan-btn'),
            stopBtn: document.getElementById('stop-scan-btn'),
            fileInput: document.getElementById('qr-input-file'),
            scannerBox: document.getElementById('scanner-container'),
            errorBox: document.getElementById('error-message'),
            errorText: document.getElementById('error-text'),
            loader: document.getElementById('loadingOverlay'),

            // PIN Modal
            pinModal: document.getElementById('pinModal'),
            pinInput: document.getElementById('pin-input-field'),
            pinSubmit: document.getElementById('submit-pin-btn'),
            pinCancel: document.getElementById('cancel-pin-btn'),
            pinError: document.getElementById('pin-error'),
            pinUser: document.getElementById('pin-user-name'),

            // Welcome
            welcomeOverlay: document.getElementById('welcome-overlay'),
            welcomeName: document.getElementById('welcome-user-name'),
            welcomeRole: document.getElementById('welcome-user-role'),
            loginBox: document.getElementById('login-box')
        };

        let currentBarcode = null;
        let html5QrCode = new Html5Qrcode("qr-reader");

        // --- HELPER FUNCTIONS ---
        const showError = (msg) => {
            els.errorText.textContent = msg;
            els.errorBox.classList.remove('hidden');
            setTimeout(() => els.errorBox.classList.add('hidden'), 4000);
        };

        const hideError = () => els.errorBox.classList.add('hidden');

        const showLoader = () => els.loader.classList.remove('hidden');
        const hideLoader = () => els.loader.classList.add('hidden');

        // --- LOGIN PROCESS ---
        async function processLogin(data) {
            hideError();
            if (!data.pin) showLoader();

            try {
                const response = await fetch('auth/login_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                // Cek jika response bukan JSON (misal error server PHP)
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Respon server tidak valid.");
                }

                const result = await response.json();
                hideLoader();

                if (result.success) {
                    // KASUS 1: Butuh PIN
                    if (result.require_pin) {
                        currentBarcode = data.barcode;
                        els.pinUser.textContent = result.nama;

                        els.pinModal.classList.remove('hidden');
                        els.pinInput.value = '';
                        els.pinError.classList.add('hidden');
                        setTimeout(() => els.pinInput.focus(), 100);
                    }
                    // KASUS 2: Login Sukses
                    else {
                        els.pinModal.classList.add('hidden');
                        showWelcomeAnimation(result.nama, result.tampilan_role, result.redirect_url);
                    }
                } else {
                    // Error Handling
                    if (data.pin) {
                        els.pinError.textContent = result.message || 'PIN Salah';
                        els.pinError.classList.remove('hidden');
                        els.pinInput.value = '';
                        els.pinInput.focus();
                        // Efek getar pada modal
                        els.pinModal.firstElementChild.classList.add('animate-shake');
                        setTimeout(() => els.pinModal.firstElementChild.classList.remove('animate-shake'), 500);
                    } else {
                        showError(result.message || 'Login gagal.');
                    }
                }
            } catch (error) {
                hideLoader();
                console.error(error);
                if (data.pin) {
                    els.pinError.textContent = "Gagal terhubung ke server.";
                    els.pinError.classList.remove('hidden');
                } else {
                    showError('Terjadi kesalahan koneksi.');
                }
            }
        }

        // --- PIN LOGIC ---
        els.pinSubmit.addEventListener('click', () => {
            const pin = els.pinInput.value;
            if (pin.length < 6) {
                els.pinError.textContent = "PIN harus 6 digit.";
                els.pinError.classList.remove('hidden');
                return;
            }
            processLogin({
                barcode: currentBarcode,
                pin: pin
            });
        });

        els.pinInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') els.pinSubmit.click();
        });

        els.pinCancel.addEventListener('click', () => {
            els.pinModal.classList.add('hidden');
            currentBarcode = null;
            els.pinInput.value = '';
        });

        // --- ANIMASI WELCOME ---
        function showWelcomeAnimation(name, role, url) {
            els.loginBox.style.transform = 'scale(0.9)';
            els.loginBox.style.opacity = '0';

            els.welcomeName.textContent = name;
            els.welcomeRole.textContent = role;
            els.welcomeOverlay.classList.add('show');

            setTimeout(() => {
                window.location.href = url;
            }, 1800);
        }

        // --- SCANNER LOGIC ---
        const onScanSuccess = (decodedText) => {
            try {
                html5QrCode.stop();
            } catch (err) {}
            els.scannerBox.classList.add('hidden');
            processLogin({
                barcode: decodedText
            });
        };

        els.startBtn.addEventListener('click', () => {
            hideError();
            els.scannerBox.classList.remove('hidden');

            const config = {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250
                },
                aspectRatio: 1.0
            };

            html5QrCode.start({
                    facingMode: "environment"
                }, config, onScanSuccess)
                .catch(err => {
                    showError("Tidak dapat mengakses kamera.");
                    els.scannerBox.classList.add('hidden');
                });
        });

        els.stopBtn.addEventListener('click', () => {
            try {
                html5QrCode.stop();
            } catch (e) {}
            els.scannerBox.classList.add('hidden');
        });

        els.fileInput.addEventListener('change', e => {
            if (e.target.files.length === 0) return;
            hideError();
            showLoader();

            html5QrCode.scanFile(e.target.files[0], true)
                .then(text => {
                    hideLoader();
                    onScanSuccess(text);
                })
                .catch(err => {
                    hideLoader();
                    showError('Barcode tidak terbaca dari gambar ini.');
                });
        });
    </script>

    <!-- Animasi Shake untuk Error -->
    <style>
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .animate-shake {
            animation: shake 0.3s ease-in-out;
        }

        .animate-blob {
            animation: blob 7s infinite;
        }

        @keyframes blob {
            0% {
                transform: translate(0px, 0px) scale(1);
            }

            33% {
                transform: translate(30px, -50px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }

            100% {
                transform: translate(0px, 0px) scale(1);
            }
        }

        .animation-delay-2000 {
            animation-delay: 2s;
        }
    </style>
</body>

</html>