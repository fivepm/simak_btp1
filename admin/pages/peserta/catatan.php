<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_id = $_SESSION['user_id'] ?? 0;
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil filter dari URL
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'tambah_catatan') {
        $peserta_id = $_POST['peserta_id'] ?? 0;
        $tanggal_catatan = $_POST['tanggal_catatan'] ?? '';
        $permasalahan = $_POST['permasalahan'] ?? '';
        $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';

        if (empty($peserta_id) || empty($tanggal_catatan) || empty($permasalahan)) {
            $error_message = 'Peserta, Tanggal, dan Permasalahan wajib diisi.';
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO catatan_bk (peserta_id, tanggal_catatan, permasalahan, tindak_lanjut, dicatat_oleh_user_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssi", $peserta_id, $tanggal_catatan, $permasalahan, $tindak_lanjut, $admin_id);
            if ($stmt->execute()) {
                $redirect_url = '?page=peserta/catatan&peserta_id=' . $peserta_id . '&status=add_success';
            } else {
                $error_message = 'Gagal menyimpan catatan BK.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'add_success') {
    $success_message = 'Catatan BK baru berhasil disimpan!';
}

// === AMBIL DATA DARI DATABASE ===
// Ambil daftar semua peserta untuk dropdown filter
$peserta_list = [];
$sql_peserta = "SELECT id, nama_lengkap, kelas, kelompok FROM peserta WHERE status = 'Aktif'";
if ($admin_tingkat === 'kelompok') {
    $sql_peserta .= " AND kelompok = ?";
}
$sql_peserta .= " ORDER BY nama_lengkap ASC";
$stmt_peserta = $conn->prepare($sql_peserta);
if ($admin_tingkat === 'kelompok') {
    $stmt_peserta->bind_param("s", $admin_kelompok);
}
$stmt_peserta->execute();
$result_peserta = $stmt_peserta->get_result();
if ($result_peserta) {
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}

// Ambil catatan BK jika peserta sudah dipilih
$catatan_bk_list = [];
$selected_peserta = null;
if ($selected_peserta_id) {
    $sql_catatan = "SELECT cb.*, u.nama as nama_pencatat 
                    FROM catatan_bk cb 
                    LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id
                    WHERE cb.peserta_id = ? 
                    ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC";
    $stmt_catatan = $conn->prepare($sql_catatan);
    $stmt_catatan->bind_param("i", $selected_peserta_id);
    $stmt_catatan->execute();
    $result_catatan = $stmt_catatan->get_result();
    if ($result_catatan) {
        while ($row = $result_catatan->fetch_assoc()) {
            $catatan_bk_list[] = $row;
        }
    }

    // Ambil nama peserta yang dipilih
    $stmt_nama = $conn->prepare("SELECT nama_lengkap FROM peserta WHERE id = ?");
    $stmt_nama->bind_param("i", $selected_peserta_id);
    $stmt_nama->execute();
    $selected_peserta = $stmt_nama->get_result()->fetch_assoc();
}
?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER PESERTA -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pilih Peserta</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="peserta/catatan">
            <div class="flex items-center gap-4">
                <select name="peserta_id" onchange="this.form.submit()" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                    <option value="">-- Cari dan Pilih Nama Peserta --</option>
                    <?php foreach ($peserta_list as $peserta): ?>
                        <option value="<?php echo $peserta['id']; ?>" <?php echo ($selected_peserta_id === (int)$peserta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($peserta['nama_lengkap'] . ' (' . ucfirst($peserta['kelas']) . ' - ' . ucfirst($peserta['kelompok']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: FORM & RIWAYAT (Hanya tampil jika peserta dipilih) -->
    <?php if ($selected_peserta_id && $selected_peserta): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Kartu Form Tambah Catatan -->
            <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tambah Catatan Baru</h3>
                <form method="POST" action="?page=peserta/catatan&peserta_id=<?php echo $selected_peserta_id; ?>">
                    <input type="hidden" name="action" value="tambah_catatan">
                    <input type="hidden" name="peserta_id" value="<?php echo $selected_peserta_id; ?>">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Tanggal Catatan*</label><input type="date" name="tanggal_catatan" value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Permasalahan*</label><textarea name="permasalahan" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></textarea></div>
                        <div><label class="block text-sm font-medium">Tindak Lanjut</label><textarea name="tindak_lanjut" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
                        <div class="text-right"><button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Catatan</button></div>
                    </div>
                </form>
            </div>

            <!-- Kartu Riwayat Catatan -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Catatan untuk <span class="text-indigo-600"><?php echo htmlspecialchars($selected_peserta['nama_lengkap']); ?></span></h3>
                <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
                <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                    <?php if (empty($catatan_bk_list)): ?>
                        <p class="text-center text-gray-500 py-8">Belum ada catatan BK untuk peserta ini.</p>
                    <?php else: ?>
                        <?php foreach ($catatan_bk_list as $catatan): ?>
                            <div class="border-l-4 border-indigo-500 pl-4 py-2">
                                <div class="flex justify-between items-center">
                                    <p class="font-bold text-gray-800"><?php echo date("d M Y", strtotime($catatan['tanggal_catatan'])); ?></p>
                                    <p class="text-xs text-gray-500">Dicatat oleh: <?php echo htmlspecialchars($catatan['nama_pencatat'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="mt-2">
                                    <p class="font-semibold text-gray-600">Permasalahan:</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($catatan['permasalahan']); ?></p>
                                </div>
                                <?php if (!empty($catatan['tindak_lanjut'])): ?>
                                    <div class="mt-2">
                                        <p class="font-semibold text-gray-600">Tindak Lanjut:</p>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($catatan['tindak_lanjut']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>

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