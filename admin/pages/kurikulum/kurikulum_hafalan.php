<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil filter kelas dari URL
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $kelas_from_form = $_POST['kelas'] ?? $selected_kelas;
    $materi_id = $_POST['materi_id'] ?? 0;

    if ($action === 'tambah_kurikulum') {
        if (!empty($kelas_from_form) && !empty($materi_id)) {
            $sql = "INSERT INTO kurikulum_hafalan (kelas, materi_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $kelas_from_form, $materi_id);
            if (!$stmt->execute()) {
                $error_message = 'Gagal menambahkan materi ke kurikulum.';
            }
            $stmt->close();
        }
    }

    if ($action === 'hapus_kurikulum') {
        if (!empty($kelas_from_form) && !empty($materi_id)) {
            $sql = "DELETE FROM kurikulum_hafalan WHERE kelas = ? AND materi_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $kelas_from_form, $materi_id);
            if (!$stmt->execute()) {
                $error_message = 'Gagal menghapus materi dari kurikulum.';
            }
            $stmt->close();
        }
    }
    // Set redirect URL agar halaman refresh dengan filter yang sama
    if (empty($error_message)) {
        $redirect_url = '?page=kurikulum/kurikulum_hafalan&kelas=' . $kelas_from_form;
    }
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
$all_materi = [];
$sql_all = "SELECT id, kategori, nama_materi FROM materi_hafalan ORDER BY kategori, nama_materi";
$result_all = $conn->query($sql_all);
if ($result_all) {
    while ($row = $result_all->fetch_assoc()) {
        $all_materi[$row['kategori']][] = $row;
    }
}

$assigned_materi_ids = [];
$assigned_materi = [];
if (!empty($selected_kelas)) {
    $sql_assigned = "SELECT mh.id, mh.kategori, mh.nama_materi 
                     FROM kurikulum_hafalan kh
                     JOIN materi_hafalan mh ON kh.materi_id = mh.id
                     WHERE kh.kelas = ?
                     ORDER BY mh.kategori, mh.nama_materi";
    $stmt_assigned = $conn->prepare($sql_assigned);
    $stmt_assigned->bind_param("s", $selected_kelas);
    $stmt_assigned->execute();
    $result_assigned = $stmt_assigned->get_result();
    if ($result_assigned) {
        while ($row = $result_assigned->fetch_assoc()) {
            $assigned_materi_ids[] = $row['id'];
            $assigned_materi[$row['kategori']][] = $row;
        }
    }
}
?>
<div class="container mx-auto">
    <h3 class="text-gray-700 text-2xl font-medium mb-6">Atur Kurikulum Hafalan</h3>

    <!-- Filter Kelas -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="kurikulum/kurikulum_hafalan">
            <label for="kelas_filter" class="block text-sm font-medium text-gray-700">Pilih Kelas untuk Diatur</label>
            <div class="flex items-center gap-2 mt-1">
                <select id="kelas_filter" name="kelas" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas --</option>
                    <?php $kelas_list = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                    <?php foreach ($kelas_list as $k): ?>
                        <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (!empty($error_message)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <!-- Tampilan Kurikulum (hanya jika kelas dipilih) -->
    <?php if (!empty($selected_kelas)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Kolom Materi Tersedia -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-xl font-semibold text-gray-800 mb-4">Materi Tersedia</h4>
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php foreach ($all_materi as $kategori => $materi_items): ?>
                        <div>
                            <h5 class="font-bold text-gray-600"><?php echo $kategori; ?></h5>
                            <ul class="mt-2 space-y-1 text-sm">
                                <?php foreach ($materi_items as $materi): ?>
                                    <?php if (!in_array($materi['id'], $assigned_materi_ids)): ?>
                                        <li class="flex justify-between items-center p-2 rounded hover:bg-gray-50">
                                            <span><?php echo htmlspecialchars($materi['nama_materi']); ?></span>
                                            <form method="POST" action="?page=kurikulum/kurikulum_hafalan&kelas=<?php echo $selected_kelas; ?>">
                                                <input type="hidden" name="action" value="tambah_kurikulum">
                                                <input type="hidden" name="materi_id" value="<?php echo $materi['id']; ?>">
                                                <button type="submit" class="text-green-500 hover:text-green-700 font-semibold">Tambah &rarr;</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kolom Materi Ditugaskan -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-xl font-semibold text-gray-800 mb-4">Materi Ditugaskan untuk Kelas <span class="capitalize text-indigo-600"><?php echo $selected_kelas; ?></span></h4>
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php foreach ($assigned_materi as $kategori => $materi_items): ?>
                        <div>
                            <h5 class="font-bold text-gray-600"><?php echo $kategori; ?></h5>
                            <ul class="mt-2 space-y-1 text-sm">
                                <?php foreach ($materi_items as $materi): ?>
                                    <li class="flex justify-between items-center p-2 rounded hover:bg-gray-50">
                                        <span><?php echo htmlspecialchars($materi['nama_materi']); ?></span>
                                        <form method="POST" action="?page=kurikulum/kurikulum_hafalan&kelas=<?php echo $selected_kelas; ?>">
                                            <input type="hidden" name="action" value="hapus_kurikulum">
                                            <input type="hidden" name="materi_id" value="<?php echo $materi['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">&larr; Hapus</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>
            // Hapus parameter status dari URL saat redirect agar notifikasi tidak muncul lagi saat refresh
            window.history.replaceState(null, null, '<?php echo $redirect_url; ?>');
            // Optional: reload untuk memastikan data terbaru
            // window.location.reload(); 
        <?php endif; ?>
    });
</script>