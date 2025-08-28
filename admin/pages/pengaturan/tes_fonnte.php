<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Panggil helper Fonnte
require_once __DIR__ . '/../../helpers/fonnte_helper.php';

$status_message = '';
$status_color = 'gray';
$pesan_tes_result = '';

// --- Cek Status Device Saat Halaman Dimuat ---
$token = $_ENV['FONNTE_TOKEN'] ?? '';
if (empty($token)) {
    $status_message = "Token Fonnte tidak ditemukan di file .env";
    $status_color = 'red';
} else {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.fonnte.com/device",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => ["Authorization: " . $token],
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $response_data = json_decode($response, true);

    if (isset($response_data['device_status']) && $response_data['device_status'] === 'connect') {
        $status_message = "Device Terhubung (Connected)";
        $status_color = 'green';
    } else {
        $status_message = "Device Terputus (Disconnected)";
        $status_color = 'red';
    }
}

// --- PROSES KIRIM PESAN TES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_tujuan_select = $_POST['nomor_tujuan_select'] ?? '';
    $nomor_tujuan_lainnya = $_POST['nomor_tujuan_lainnya'] ?? '';

    $nomor_tujuan = ($nomor_tujuan_select === 'lainnya') ? $nomor_tujuan_lainnya : $nomor_tujuan_select;
    $pesan = $_POST['pesan'] ?? 'Ini adalah pesan tes dari sistem SIMAK.';

    if (empty($nomor_tujuan)) {
        $pesan_tes_result = '<div id="error-alert" class="bg-red-100 text-red-700 p-3 rounded-md">Nomor tujuan tidak boleh kosong.</div>';
    } else {
        $berhasil = kirimPesanFonnte($nomor_tujuan, $pesan, 3);
        if ($berhasil) {
            $pesan_tes_result = '<div id="success-alert" class="bg-green-100 text-green-700 p-3 rounded-md">Pesan tes berhasil dikirim! Silakan periksa WhatsApp Anda.</div>';
        } else {
            $pesan_tes_result = '<div id="error-alert" class="bg-red-100 text-red-700 p-3 rounded-md">Gagal mengirim pesan. Pastikan device terhubung dan token benar.</div>';
        }
    }
}
?>
<div class="container mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-semibold text-gray-800">Tes Koneksi Fonnte</h1>
        <p class="mt-1 text-gray-600">Gunakan halaman ini untuk memeriksa status koneksi device dan mengirim pesan tes.</p>
    </div>

    <!-- KARTU STATUS DEVICE -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Status Device Saat Ini</h2>
        <div class="flex items-center space-x-3">
            <span class="h-4 w-4 rounded-full bg-<?php echo $status_color; ?>-500"></span>
            <p class="text-lg font-semibold text-gray-700"><?php echo $status_message; ?></p>
        </div>
        <?php if ($status_color === 'red'): ?>
            <p class="text-sm text-gray-500 mt-2">Notifikasi otomatis tidak akan berjalan sampai device terhubung kembali. Silakan scan ulang QR di dashboard Fonnte.</p>
        <?php endif; ?>
    </div>

    <!-- KARTU KIRIM PESAN TES -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Kirim Pesan Tes</h2>
        <?php if (!empty($pesan_tes_result)) {
            echo $pesan_tes_result;
        } ?>
        <form method="POST" action="?page=pengaturan/tes_fonnte" class="mt-4 space-y-4">
            <div>
                <label for="nomor_tujuan_select" class="block text-sm font-medium text-gray-700">Nomor WhatsApp Tujuan*</label>
                <select name="nomor_tujuan_select" id="nomor_tujuan_select" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                    <!-- PERUBAHAN: Nomor di-hardcode di sini -->
                    <option value="120363194369588883@g.us">Grup Administrasi KBM</option>
                    <option value="6287848248295">Admin 1 - Panca</option>
                    <option value="6289628167288">Admin 2 - Qolbi</option>
                    <option value="6282139871090">Admin 3 - Devia</option>
                    <!-- Anda bisa menambah atau menghapus <option> di sini -->
                    <option value="lainnya">Lainnya...</option>
                </select>
            </div>
            <div id="nomor_lainnya_field" class="hidden">
                <label for="nomor_tujuan_lainnya" class="block text-sm font-medium text-gray-700">Masukkan Nomor Lain</label>
                <input type="text" name="nomor_tujuan_lainnya" id="nomor_tujuan_lainnya" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Gunakan format 62, contoh: 6281234567890">
            </div>
            <div>
                <label for="pesan" class="block text-sm font-medium text-gray-700">Isi Pesan</label>
                <textarea name="pesan" id="pesan" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">Ini adalah pesan tes dari sistem SIMAK Banguntapan 1. Device Connected!!!
                </textarea>
            </div>
            <div class="text-right">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">
                    Kirim Tes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectNomor = document.getElementById('nomor_tujuan_select');
        const fieldLainnya = document.getElementById('nomor_lainnya_field');
        const inputLainnya = document.getElementById('nomor_tujuan_lainnya');

        selectNomor.addEventListener('change', function() {
            if (this.value === 'lainnya') {
                fieldLainnya.classList.remove('hidden');
                inputLainnya.required = true;
            } else {
                fieldLainnya.classList.add('hidden');
                inputLainnya.required = false;
            }
        });

        const autoHideAlert = (alertId) => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                setTimeout(() => {
                    alertElement.style.transition = 'opacity 0.5s ease';
                    alertElement.style.opacity = '0';
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500); // Waktu untuk animasi fade-out
                }, 3000); // 3000 milidetik = 3 detik
            }
        };
        autoHideAlert('success-alert');
        autoHideAlert('error-alert');
    });
</script>