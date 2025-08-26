<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;

// === AMBIL DATA DARI DATABASE ===
$peserta_list = [];
$sql_peserta = "SELECT id, nama_lengkap, kelas, kelompok FROM peserta WHERE status = 'Aktif'";
if ($ketuapjp_tingkat === 'kelompok') {
    $sql_peserta .= " AND kelompok = ?";
}
$sql_peserta .= " ORDER BY nama_lengkap ASC";
$stmt_peserta = $conn->prepare($sql_peserta);
if ($ketuapjp_tingkat === 'kelompok') {
    $stmt_peserta->bind_param("s", $ketuapjp_kelompok);
}
$stmt_peserta->execute();
$result_peserta = $stmt_peserta->get_result();
if ($result_peserta) {
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}

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

    <!-- BAGIAN 2: FORM & RIWAYAT -->
    <?php if ($selected_peserta_id && $selected_peserta): ?>
        <div class="grid grid-cols-1 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Catatan untuk <span class="text-indigo-600"><?php echo htmlspecialchars($selected_peserta['nama_lengkap']); ?></span></h3>
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                    <?php if (empty($catatan_bk_list)): ?>
                        <p class="text-center text-gray-500 py-8">Belum ada catatan BK untuk peserta ini.</p>
                        <?php else: foreach ($catatan_bk_list as $catatan): ?>
                            <div class="border-l-4 border-indigo-500 pl-4 py-2 bg-gray-50 rounded">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-800"><?php echo date("d M Y", strtotime($catatan['tanggal_catatan'])); ?></p>
                                        <p class="text-xs text-gray-500">Dicatat oleh: <?php echo htmlspecialchars($catatan['nama_pencatat'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="font-semibold text-gray-600">Permasalahan:</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($catatan['permasalahan']); ?></p>
                                </div>
                                <div class="mt-2">
                                    <p class="font-semibold text-gray-600">Tindak Lanjut:</p>
                                    <?php if (!empty($catatan['tindak_lanjut'])): ?>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($catatan['tindak_lanjut']); ?></p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap">Belum ada tindak lanjut</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>