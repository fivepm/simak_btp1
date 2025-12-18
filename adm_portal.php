<?php
session_start();
require_once 'config/config.php';

// --- FITUR RESET DARURAT (Manual) ---
// Cara pakai: Buka adm_portal.php?reset=1 di browser
// if (isset($_GET['reset'])) {
//     unset($_SESSION['pin_blocked_time']);
//     unset($_SESSION['pin_attempts']);
//     // Redirect balik ke halaman bersih
//     header("Location: adm_portal");
//     exit;
// }

$error_message = '';
$show_pin_input = false; // State awal: Sembunyikan input PIN
$temp_barcode_data = ''; // Untuk menyimpan hasil scan sementara
$login_success = false;
$max_attempts = 5;          // Maksimal percobaan
$lockout_duration = 15 * 60; // Durasi blokir dalam detik (15 menit)

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
if (!$isMaintenance) {
    // Tampilkan halaman maintenance dan hentikan skrip.
    // Ini akan memblokir halaman login, dashboard, dll.
    header("Location: /");
    exit;
}
// --- LOGIKA MAINTENANCE MODE SELESAI ---

// Jika sudah login, tendang ke dashboard
// if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'superadmin') {
//     header('Location: admin/?page=dashboard');
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($conn)) {

    // Ambil input
    $barcode_data = $_POST['barcode_data'] ?? '';
    $input_pin = $_POST['pin'] ?? '';

    // Cek Maintenance & PIN DB
    $maintenance_on = false;
    $real_pin = '';

    $sql_set = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('maintenance_mode', 'maintenance_pin')";
    $res_set = mysqli_query($conn, $sql_set);
    while ($row = mysqli_fetch_assoc($res_set)) {
        if ($row['setting_key'] == 'maintenance_mode') $maintenance_on = filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN);
        if ($row['setting_key'] == 'maintenance_pin') $real_pin = $row['setting_value'];
    }

    // --- TAHAP 0: CEK APAKAH SEDANG DIBLOKIR? ---
    if (isset($_SESSION['pin_blocked_time']) && time() < $_SESSION['pin_blocked_time']) {
        $time_left = ceil(($_SESSION['pin_blocked_time'] - time()) / 60);
        $error_message = "Terlalu banyak percobaan salah! Silakan tunggu $time_left menit lagi.";

        // Paksa tampilan tetap di input PIN agar user tau dia diblokir
        $show_pin_input = true;
        $temp_barcode_data = $barcode_data;
    } else {
        // Jika waktu blokir sudah habis, hapus session blokir
        if (isset($_SESSION['pin_blocked_time']) && time() >= $_SESSION['pin_blocked_time']) {
            unset($_SESSION['pin_blocked_time']);
            unset($_SESSION['pin_attempts']);
        }

        // --- TAHAP 1: Validasi Barcode ---
        if (empty($barcode_data)) {
            $error_message = 'Data Barcode tidak terbaca.';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE barcode = ?");
            mysqli_stmt_bind_param($stmt, "s", $barcode_data);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user) {
                if ($user['role'] !== 'superadmin') {
                    $error_message = 'Barcode valid, tapi akun ini bukan Super Admin.';
                } else {
                    // User Ditemukan & Role ADMIN

                    // --- TAHAP 2: Cek Maintenance & PIN ---
                    if ($maintenance_on) {

                        if (empty($input_pin)) {
                            // KASUS A: Baru scan barcode, belum masukkan PIN
                            $show_pin_input = true;
                            $temp_barcode_data = $barcode_data;
                        } else {
                            // KASUS B: Sudah masukkan PIN -> VALIDASI DISINI
                            if ($input_pin === $real_pin) {
                                // --- PIN BENAR ---
                                // Reset penghitung percobaan
                                unset($_SESSION['pin_attempts']);
                                unset($_SESSION['pin_blocked_time']);

                                // Set Session Login (Tapi JANGAN redirect dulu)
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['user_nama'] = $user['nama'] ?? 'Pengguna';
                                $_SESSION['user_role'] = $user['role'] ?? 'guru';
                                $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
                                $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
                                $_SESSION['user_kelas'] = $user['kelas'] ?? '';
                                $_SESSION['foto_profil'] = $user['foto_profil'] ?? 'default.png';
                                $_SESSION['username'] = $user['username'] ?? '';

                                // Aktifkan mode sukses untuk UI
                                $login_success = true;
                                $show_pin_input = true; // Tetap tampilkan form untuk animasi
                                $temp_barcode_data = $barcode_data;
                            } else {
                                // --- PIN SALAH ---
                                $show_pin_input = true;
                                $temp_barcode_data = $barcode_data;

                                // Tambah counter salah
                                if (!isset($_SESSION['pin_attempts'])) {
                                    $_SESSION['pin_attempts'] = 0;
                                }
                                $_SESSION['pin_attempts']++;

                                // Cek apakah sudah limit?
                                if ($_SESSION['pin_attempts'] >= $max_attempts) {
                                    // BLOKIR USER
                                    $_SESSION['pin_blocked_time'] = time() + $lockout_duration;
                                    $error_message = "Anda telah salah 5 kali. Akses diblokir selama 15 menit.";
                                } else {
                                    // Beri peringatan sisa percobaan
                                    $sisa = $max_attempts - $_SESSION['pin_attempts'];
                                    $error_message = "PIN Salah! Sisa percobaan: $sisa kali.";
                                }
                            }
                        }
                    } else {
                        // Maintenance OFF: Login langsung
                        doLogin($user);
                    }
                }
            } else {
                $error_message = 'Barcode tidak dikenali (User tidak ditemukan).';
            }
        }
    }
}

function doLogin($user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nama'] = $user['nama'] ?? 'Pengguna';
    $_SESSION['user_role'] = $user['role'] ?? 'guru';
    $_SESSION['user_tingkat'] = $user['tingkat'] ?? 'kelompok';
    $_SESSION['user_kelompok'] = $user['kelompok'] ?? '';
    $_SESSION['user_kelas'] = $user['kelas'] ?? '';
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? 'default.png';
    $_SESSION['username'] = $user['username'] ?? '';
    header('Location: admin/?page=dashboard');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Login Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Library Scan QR Code -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-900 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md p-6 bg-gray-800 rounded-2xl shadow-xl border border-gray-700">

        <!-- Header -->
        <img src="assets/images/logo_kbm.png"
            alt="Logo Aplikasi"
            class="h-20 w-20 mx-auto mb-2">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-white">SIMAK</h1>
            <h2 class="text-lg font-bold text-white">Banguntapan 1</h2>
            <hr>
            <h1 class="text-2xl font-bold text-white">Portal Developer</h1>
            <p class="text-gray-400 text-sm">Scan Identitas SuperAdmin</p>
        </div>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-900/50 border border-red-600 text-red-200 px-4 py-3 rounded-lg relative mb-6 text-sm text-center">
                <strong>PERHATIAN:</strong><br>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- FORM UTAMA -->
        <form action="adm_portal" method="POST" id="loginForm">

            <?php if ($show_pin_input): ?>
                <!-- === TAMPILAN 2: INPUT PIN (Jika Maint ON & Scan Sukses) === -->
                <input type="hidden" name="barcode_data" value="<?php echo htmlspecialchars($temp_barcode_data); ?>">

                <!-- Cek apakah sedang diblokir untuk mematikan input -->
                <?php $is_blocked = (isset($_SESSION['pin_blocked_time']) && time() < $_SESSION['pin_blocked_time']); ?>

                <div class="mb-6 text-center">
                    <div class="mb-4">
                        <span class="inline-block bg-green-900 text-green-300 text-xs px-2 py-1 rounded-full">Identitas Terverifikasi</span>
                        <p class="text-gray-300 text-sm mt-2">Halo, <strong><?php echo $user['nama']; ?></strong>. Masukkan PIN Maintenance.</p>
                    </div>

                    <?php if ($is_blocked): ?>
                        <!-- Tampilan Saat Diblokir -->
                        <div class="p-6 bg-red-900/20 rounded-lg border border-red-800">
                            <svg class="w-12 h-12 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="text-red-400 font-bold text-lg">AKSES DIBLOKIR</h3>
                            <p class="text-gray-400 text-xs mt-1">Tunggu hingga waktu habis.</p>
                        </div>
                    <?php else: ?>
                        <!-- LOGIKA WARNA TEMA -->
                        <?php
                        // Jika Login Sukses: Hijau, Jika Normal: Kuning
                        $theme_color = $login_success ? 'green' : 'yellow';
                        $input_value = $login_success ? $_POST['pin'] : ''; // Tampilkan PIN jika sukses
                        $btn_text    = $login_success ? 'AKSES DITERIMA...' : 'VERIFIKASI & MASUK';
                        $readonly    = $login_success ? 'readonly' : '';
                        ?>

                        <label for="pin" class="block text-sm font-bold text-<?php echo $theme_color; ?>-400 mb-2">
                            <?php echo $login_success ? 'VERIFIKASI BERHASIL' : 'PIN KEAMANAN MAINTENANCE (6 DIGIT)'; ?>
                        </label>

                        <input type="text" name="pin" id="pin" maxlength="6"
                            value="<?php echo htmlspecialchars($input_value); ?>"
                            class="w-full px-4 py-3 bg-gray-700 text-white text-center text-2xl tracking-[0.5em] font-mono border-2 border-<?php echo $theme_color; ?>-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-<?php echo $theme_color; ?>-400 transition-colors duration-500"
                            placeholder="000000" required <?php echo $readonly; ?>
                            <?php echo $login_success ? '' : 'autofocus'; ?> autocomplete="off">

                        <button type="submit" class="mt-6 w-full bg-<?php echo $theme_color; ?>-600 text-white py-3 px-4 rounded-lg font-bold hover:bg-<?php echo $theme_color; ?>-700 transition-colors duration-500 shadow-[0_0_15px_rgba(0,0,0,0.5)] flex items-center justify-center gap-2">
                            <?php if ($login_success): ?>
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            <?php endif; ?>
                            <?php echo $btn_text; ?>
                        </button>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- === TAMPILAN 1: SCANNER BARCODE === -->
                <input type="hidden" name="barcode_data" id="hidden_barcode_data">

                <!-- Area Kamera -->
                <div id="reader" class="w-full bg-gray-900 rounded-lg overflow-hidden border-2 border-dashed border-gray-600 mb-4" style="min-height: 250px;"></div>

                <!-- Tombol Kontrol -->
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <button type="button" id="startScanBtn" class="bg-blue-600 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Buka Kamera
                    </button>

                    <!-- Input File Tersembunyi -->
                    <input type="file" id="qr-input-file" accept="image/*" class="hidden">

                    <button type="button" id="uploadBtn" class="bg-gray-700 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-gray-600 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Pilih Galeri
                    </button>
                </div>

                <div id="scan-status" class="text-center text-xs text-gray-500">
                    Arahkan kamera ke QR Code Username Anda
                </div>
            <?php endif; ?>

        </form>
    </div>

    <!-- SCRIPT REDIRECT OTOMATIS (Jika Login Sukses) -->
    <?php if ($login_success): ?>
        <script>
            setTimeout(function() {
                window.location.href = 'admin/?page=dashboard';
            }, 1500); // Tunggu 1.5 detik sebelum redirect
        </script>
    <?php endif; ?>

    <!-- SCRIPT LOGIKA SCANNER -->
    <?php if (!$show_pin_input): ?>
        <script>
            const html5QrCode = new Html5Qrcode("reader");
            const scanStatus = document.getElementById('scan-status');
            const form = document.getElementById('loginForm');
            const hiddenInput = document.getElementById('hidden_barcode_data');

            // Fungsi Sukses Scan
            const onScanSuccess = (decodedText, decodedResult) => {
                // Hentikan kamera
                html5QrCode.stop().then((ignore) => {
                    // Isi input hidden dan submit
                    scanStatus.innerText = "Barcode terdeteksi: " + decodedText;
                    scanStatus.className = "text-center text-xs text-green-400 font-bold";
                    hiddenInput.value = decodedText;
                    form.submit();
                }).catch((err) => {
                    console.log("Stop failed: ", err);
                });
            };

            const onScanFailure = (error) => {
                // Jangan spam console, biarkan kosong atau tampilkan status ringan
                // console.warn(`Code scan error = ${error}`);
            };

            // Event Tombol Kamera
            document.getElementById('startScanBtn').addEventListener('click', () => {
                scanStatus.innerText = "Memulai kamera...";
                html5QrCode.start({
                        facingMode: "environment"
                    }, // Kamera belakang
                    {
                        fps: 10, // Frame per second
                        qrbox: {
                            width: 250,
                            height: 250
                        } // Area scan
                    },
                    onScanSuccess,
                    onScanFailure
                ).catch(err => {
                    scanStatus.innerText = "Gagal akses kamera: " + err;
                    scanStatus.className = "text-center text-xs text-red-400";
                });
            });

            // Event Tombol Upload Galeri
            document.getElementById('uploadBtn').addEventListener('click', () => {
                document.getElementById('qr-input-file').click();
            });

            // Event Saat File Dipilih
            document.getElementById('qr-input-file').addEventListener('change', e => {
                if (e.target.files.length == 0) return;

                const imageFile = e.target.files[0];
                scanStatus.innerText = "Memproses gambar...";

                // Scan File
                html5QrCode.scanFile(imageFile, true)
                    .then(decodedText => {
                        // Sukses baca file
                        scanStatus.innerText = "Gambar terbaca: " + decodedText;
                        scanStatus.className = "text-center text-xs text-green-400 font-bold";
                        hiddenInput.value = decodedText;
                        form.submit();
                    })
                    .catch(err => {
                        scanStatus.innerText = "Gagal membaca QR dari gambar. Coba gambar lain.";
                        scanStatus.className = "text-center text-xs text-red-400";
                    });
            });
        </script>
    <?php endif; ?>

</body>

</html>