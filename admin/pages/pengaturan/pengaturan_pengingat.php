<?php
// --- (SIMULASI SESI ADMIN) ---
// if (session_status() == PHP_SESSION_NONE) { session_start(); }
// $_SESSION['user_tingkat'] = 'kelompok'; // 'desa' atau 'kelompok'
// $_SESSION['user_kelompok'] = 'Bintaran'; // Diisi jika tingkat='kelompok'
// --- (AKHIR SIMULASI) ---

$redirect_url = '';
$pesan_notifikasi = '';
$status_notifikasi = '';

// Ambil data sesi admin
$admin_level = $admin_tingkat ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;

// ===================================================================
// BAGIAN 1: PEMROSESAN FORM SAAT DISIMPAN (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waktu_pengingat'])) {
    $pengaturan_baru = $_POST['waktu_pengingat'];
    $berhasil = true;

    // Query cerdas: Insert baru jika belum ada, update jika sudah ada.
    $stmt = $conn->prepare("
        INSERT INTO pengaturan_pengingat (kelompok, kelas, waktu_pengingat_jam) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE waktu_pengingat_jam = VALUES(waktu_pengingat_jam)
    ");

    foreach ($pengaturan_baru as $kelompok => $kelas_data) {
        // Keamanan: Pastikan admin kelompok hanya bisa mengubah kelompoknya sendiri
        if ($admin_level === 'kelompok' && $kelompok !== $admin_kelompok) {
            continue; // Lewati jika mencoba mengubah kelompok lain
        }

        foreach ($kelas_data as $kelas => $jam) {
            $jam_integer = (int)$jam;
            // Hanya simpan jika nilainya diisi dan lebih dari 0
            if ($jam_integer > 0) {
                $stmt->bind_param("ssi", $kelompok, $kelas, $jam_integer);
                if (!$stmt->execute()) {
                    $berhasil = false;
                    $pesan_notifikasi = "Gagal menyimpan pengaturan untuk " . htmlspecialchars($kelompok) . " - " . htmlspecialchars($kelas);
                    $status_notifikasi = "gagal";
                    break 2; // Keluar dari kedua loop
                }
            }
        }
    }
    $stmt->close();

    if ($berhasil) {
        $pesan_notifikasi = "Pengaturan pengingat berhasil disimpan.";
        $status_notifikasi = "sukses";
    }
}

// ===================================================================
// BAGIAN 2: PENGAMBILAN DATA UNTUK TAMPILAN (GET)
// ===================================================================

// 1. Ambil daftar kelas unik, terfilter berdasarkan level admin
$sql_kelas = "SELECT DISTINCT kelompok, kelas FROM peserta ORDER BY kelompok, kelas";
if ($admin_level === 'kelompok' && $admin_kelompok) {
    $sql_kelas = "SELECT DISTINCT kelompok, kelas FROM peserta WHERE kelompok = ? ORDER BY FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah')";
    $stmt_kelas = $conn->prepare($sql_kelas);
    $stmt_kelas->bind_param("s", $admin_kelompok);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
} else {
    $sql_kelas_desa = "SELECT DISTINCT kelompok, kelas FROM peserta ORDER BY kelompok, FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah')";
    $result_kelas = $conn->query($sql_kelas_desa);
}

// 2. Ambil semua pengaturan yang sudah ada untuk ditampilkan di form
$pengaturan_tersimpan = [];
$result_pengaturan = $conn->query("SELECT kelompok, kelas, waktu_pengingat_jam FROM pengaturan_pengingat");
if ($result_pengaturan) {
    while ($row = $result_pengaturan->fetch_assoc()) {
        $pengaturan_tersimpan[$row['kelompok']][$row['kelas']] = $row['waktu_pengingat_jam'];
    }
}
?>

<!-- Di sini Anda bisa menyertakan header/layout utama -->
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="bg-white p-6 rounded-2xl shadow-lg">

        <h1 class="text-3xl font-bold text-gray-800 mb-2">Pusat Kontrol Pengingat Jadwal</h1>
        <p class="text-gray-500 mb-4 border-b pb-4">Atur berapa jam sebelum jadwal dimulai pengingat otomatis akan dikirim. Jika dikosongkan, sistem akan menggunakan default (4 jam).</p>

        <!-- Notifikasi -->
        <?php if (!empty($pesan_notifikasi)): ?>
            <div id="<?php echo ($status_notifikasi === 'sukses') ? 'success-alert' : 'error-alert'; ?>"
                class="bg-<?php echo ($status_notifikasi === 'gagal') ? 'red' : 'green'; ?>-100 border-l-4 border-<?php echo ($status_notifikasi === 'gagal') ? 'red' : 'green'; ?>-500 text-<?php echo ($status_notifikasi === 'gagal') ? 'red' : 'green'; ?>-700 p-4 mb-4 rounded-lg" role="alert">
                <p><?php echo htmlspecialchars($pesan_notifikasi); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Kelompok</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Kelas</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Waktu Pengingat (Jam Sebelum Mulai)</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if ($result_kelas->num_rows > 0): ?>
                            <?php while ($row_kelas = $result_kelas->fetch_assoc()):
                                $kelompok = $row_kelas['kelompok'];
                                $kelas = $row_kelas['kelas'];
                                $nilai_tersimpan = $pengaturan_tersimpan[$kelompok][$kelas] ?? '';
                            ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 font-medium capitalize"><?php echo htmlspecialchars($kelompok); ?></td>
                                    <td class="py-3 px-4 capitalize"><?php echo htmlspecialchars($kelas); ?></td>
                                    <td class="py-3 px-4">
                                        <input
                                            type="number"
                                            name="waktu_pengingat[<?php echo $kelompok; ?>][<?php echo $kelas; ?>]"
                                            value="<?php echo htmlspecialchars($nilai_tersimpan); ?>"
                                            class="w-24 text-center border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500"
                                            placeholder="4"
                                            min="1">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-gray-500">Tidak ada data kelas yang ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tombol Simpan -->
            <div class="mt-6 border-t pt-4 flex justify-end">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300">
                    <i class="fas fa-save mr-2"></i> Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Script untuk menghilangkan notifikasi setelah 3 detik
    window.addEventListener('load', function() {
        const successAlert = document.getElementById('success-alert');
        const errorAlert = document.getElementById('error-alert');
        const hide = (el) => {
            if (el) {
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity = '0';
                    setTimeout(() => el.style.display = 'none', 500);
                }, 3000);
            }
        };
        hide(successAlert);
        hide(errorAlert);
    });

    // Logika untuk redirect jika ada
    <?php if (!empty($redirect_url)): ?>
        window.location.href = '<?php echo $redirect_url; ?>';
    <?php endif; ?>
</script>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->