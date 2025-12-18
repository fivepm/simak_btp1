<?php
// --- Security Check: Hanya Admin ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "<p>Akses ditolak. Anda harus menjadi Super Admin.</p>";
    return;
}

// --- Ambil Data Pengaturan Saat Ini ---
// Kita butuh koneksi $conn dari index.php
if (!isset($conn)) {
    echo "<p>Koneksi database tidak ditemukan.</p>";
    return;
}

$settings = [];
$sql = "SELECT setting_key, setting_value FROM settings";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Konversi nilai 'true'/'false' string menjadi boolean
$isMaintenanceOn = filter_var($settings['maintenance_mode'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

// --- (BARU) AMBIL RIWAYAT LOG MAINTENANCE ---
// Ambil 10 log terakhir
$logs = [];
$sql_logs = "SELECT * FROM maintenance_logs ORDER BY created_at DESC LIMIT 10";
$result_logs = mysqli_query($conn, $sql_logs);
if ($result_logs) {
    while ($row = mysqli_fetch_assoc($result_logs)) {
        $logs[] = $row;
    }
}
?>

<div class="container mx-auto p-4 md:p-6">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">Maintenance Sistem</h1>

    <!-- Notifikasi/Alert Placeholder -->
    <div id="settings-alert" class="mb-4"></div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Mode Pemeliharaan (Maintenance)</h2>

        <!-- LOTTIE ANIMATION -->
        <div class="w-full flex justify-center items-center h-48 mb-4">
            <!-- Mode OFF (Tidur) -->
            <lottie-player
                id="lottie-maintenance-off"
                src="../assets/animations/cat_sleep.json"
                background="transparent" speed="1" style="width: 200px; height: 200px;" loop autoplay
                <?php echo $isMaintenanceOn ? 'class="hidden"' : ''; ?>>
            </lottie-player>

            <!-- Mode ON (Kerja) -->
            <lottie-player
                id="lottie-maintenance-on"
                src="../assets/animations/maintenance.json"
                background="transparent" speed="1" style="width: 200px; height: 200px;" loop autoplay
                <?php echo !$isMaintenanceOn ? 'class="hidden"' : ''; ?>>
            </lottie-player>
        </div>

        <p class="text-gray-600 mb-4">
            Jika diaktifkan, semua pengguna (kecuali Admin) akan dialihkan ke halaman 'maintenance' dan tidak dapat mengakses sistem.
        </p>

        <div class="flex items-center space-x-4">
            <!-- Toggle Switch (Saklar) -->
            <label for="maintenance-toggle" class="flex items-center cursor-pointer">
                <!-- Latar belakang saklar -->
                <div class="relative">
                    <input
                        type="checkbox"
                        id="maintenance-toggle"
                        class="sr-only"
                        <?php echo $isMaintenanceOn ? 'checked' : ''; ?>>
                    <!-- Garis saklar -->
                    <div class="block bg-gray-300 w-14 h-8 rounded-full transition"></div>
                    <!-- Tombol saklar (bulat) -->
                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                </div>
                <!-- Label Status -->
                <div id="maintenance-status-text" class="ml-3 text-lg font-medium">
                    <?php if ($isMaintenanceOn): ?>
                        <span class="text-red-600">AKTIF</span>
                    <?php else: ?>
                        <span class="text-green-600">NON-AKTIF</span>
                    <?php endif; ?>
                </div>
            </label>
        </div>

        <p id="maintenance-loading" class="text-sm text-gray-500 mt-2 hidden">
            Menyimpan...
        </p>

    </div>

    <!-- CARD 2: RIWAYAT MAINTENANCE (BARU) -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Riwayat Aktivitas (10 Terakhir)
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada riwayat tercatat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($log['admin_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($log['action'] == 'activated'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Mengaktifkan
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Menonaktifkan
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================ -->
<!--   MODAL PIN (POP-UP)         -->
<!-- ============================ -->
<div id="pin-display-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 hidden">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center transform scale-100 transition-transform">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h3 class="text-lg leading-6 font-medium text-gray-900">Mode Maintenance Aktif!</h3>

        <div class="mt-2">
            <p class="text-sm text-gray-500">
                Silakan catat <strong>PIN Keamanan</strong> ini. Anda WAJIB memasukkannya saat login di Portal Admin Darurat.
            </p>

            <!-- Area PIN & Tombol Copy -->
            <div class="mt-4 p-4 bg-gray-100 rounded-lg border-2 border-dashed border-gray-400 flex flex-col items-center justify-center relative group">

                <!-- Teks PIN -->
                <span id="generated-pin-text" class="text-4xl font-mono font-bold text-gray-800 tracking-widest mb-2 select-all">000000</span>

                <!-- Tombol Copy -->
                <button type="button" id="copy-pin-btn" class="flex items-center space-x-1 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors focus:outline-none bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full border border-blue-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    <span>Salin Kode</span>
                </button>
            </div>
        </div>

        <div class="mt-6">
            <button type="button" id="close-pin-modal" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:text-sm">
                Saya sudah mencatatnya
            </button>
        </div>
    </div>
</div>

<!-- CSS untuk Saklar (Toggle) -->
<style>
    input:checked~.dot {
        transform: translateX(100%);
        background-color: #ffffff;
    }

    input:checked~.block {
        background-color: #EF4444;
        /* Merah (saat aktif) */
    }
</style>

<!-- <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script> -->
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

<!-- JavaScript untuk Saklar -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('maintenance-toggle');
        const statusText = document.getElementById('maintenance-status-text');
        const loadingText = document.getElementById('maintenance-loading');
        const alertBox = document.getElementById('settings-alert');
        // Ambil Lottie players
        const lottieOn = document.getElementById('lottie-maintenance-on');
        const lottieOff = document.getElementById('lottie-maintenance-off');

        // Elemen Modal PIN
        const pinModal = document.getElementById('pin-display-modal');
        const pinText = document.getElementById('generated-pin-text');
        const closeModalBtn = document.getElementById('close-pin-modal');

        // Tutup modal
        closeModalBtn.addEventListener('click', function() {
            pinModal.classList.add('hidden');
            showAlert('Maintenance diaktifkan. Sistem dalam mode maintenance.', 'success');
            setTimeout(() => location.reload(), 2000);
        });

        toggle.addEventListener('change', function() {
            const isChecked = this.checked;
            const newValue = isChecked ? 'true' : 'false';

            // Generate PIN 6 digit jika mengaktifkan (ON)
            let generatedPin = '';
            if (isChecked) {
                generatedPin = Math.floor(100000 + Math.random() * 900000).toString();
            }

            loadingText.classList.remove('hidden');
            alertBox.innerHTML = '';
            toggle.disabled = true;

            // Siapkan payload data
            const payload = {
                key: 'maintenance_mode',
                value: newValue
            };
            // Kirim PIN hanya jika sedang mengaktifkan
            if (isChecked) {
                payload.pin = generatedPin;
            }

            fetch('pages/development/ajax_update_maintenance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (isChecked) {
                            // --- MODE ON ---
                            statusText.innerHTML = '<span class="text-red-600">AKTIF</span>';
                            lottieOff.classList.add('hidden');
                            lottieOn.classList.remove('hidden');

                            // Tampilkan Modal PIN
                            pinText.innerText = generatedPin;
                            pinModal.classList.remove('hidden');
                        } else {
                            // --- MODE OFF ---
                            statusText.innerHTML = '<span class="text-green-600">NON-AKTIF</span>';
                            lottieOn.classList.add('hidden');
                            lottieOff.classList.remove('hidden');
                            showAlert('Maintenance dinonaktifkan. Sistem kembali normal.', 'success');
                            setTimeout(() => location.reload(), 2000);
                        }
                    } else {
                        showAlert(data.message || 'Gagal mengubah status.', 'error');
                        toggle.checked = !isChecked; // Kembalikan posisi saklar
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Terjadi kesalahan koneksi.', 'error');
                    toggle.checked = !isChecked;
                })
                .finally(() => {
                    loadingText.classList.add('hidden');
                    toggle.disabled = false;
                });
        });

        // --- LOGIKA TOMBOL COPY ---
        const copyBtn = document.getElementById('copy-pin-btn');
        // pinText sudah didefinisikan di atas (generated-pin-text)

        if (copyBtn && pinText) {
            copyBtn.addEventListener('click', function() {
                // Ambil hanya PIN-nya
                const pinCode = pinText.innerText;

                // Tambahkan kata-kata pengantar di sini
                const codeToCopy = `Kode PIN Maintenance SIMAK: ${pinCode}`;

                // Gunakan API clipboard browser modern
                navigator.clipboard.writeText(codeToCopy).then(() => {
                    // Simpan konten asli tombol
                    const originalContent = copyBtn.innerHTML;

                    // Ubah tampilan tombol jadi hijau (Sukses)
                    copyBtn.classList.remove('text-blue-600', 'bg-blue-50', 'border-blue-200');
                    copyBtn.classList.add('text-green-600', 'bg-green-50', 'border-green-200');
                    copyBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Tersalin!</span>
                `;

                    // Kembalikan seperti semula setelah 2 detik
                    setTimeout(() => {
                        copyBtn.innerHTML = originalContent;
                        copyBtn.classList.remove('text-green-600', 'bg-green-50', 'border-green-200');
                        copyBtn.classList.add('text-blue-600', 'bg-blue-50', 'border-blue-200');
                    }, 2000);

                }).catch(err => {
                    console.error('Gagal menyalin:', err);
                    // Fallback manual jika API gagal
                    alert('Gagal menyalin otomatis. Kode PIN: ' + codeToCopy);
                });
            });
        }

        // Fungsi notifikasi
        function showAlert(message, type = 'success') {
            let bgColor, borderColor, textColor;
            if (type === 'success') {
                bgColor = 'bg-green-100';
                borderColor = 'border-green-500';
                textColor = 'text-green-700';
            } else {
                bgColor = 'bg-red-100';
                borderColor = 'border-red-500';
                textColor = 'text-red-700';
            }

            alertBox.innerHTML = `
            <div class="${bgColor} border-l-4 ${borderColor} ${textColor} p-4" role="alert">
                <p>${message}</p>
            </div>`;

            // Hilangkan notifikasi setelah 3 detik
            setTimeout(() => {
                alertBox.innerHTML = '';
            }, 3000);
        }
    });
</script>