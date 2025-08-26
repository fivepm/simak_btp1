<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil filter kelas dari URL
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

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
    <h3 class="text-gray-700 text-2xl font-medium mb-6">Kurikulum Hafalan</h3>

    <!-- Filter Kelas -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="kurikulum/kurikulum_hafalan">
            <label for="kelas_filter" class="block text-sm font-medium text-gray-700">Pilih Kelas</label>
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

    <!-- Tampilan Kurikulum (hanya jika kelas dipilih) -->
    <?php if (!empty($selected_kelas)): ?>
        <div class="grid grid-cols-1 gap-6">
            <!-- Kolom Materi Ditugaskan -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-xl font-semibold text-gray-800 mb-4">Materi Hafalan Ditugaskan untuk Kelas <span class="capitalize text-indigo-600"><?php echo $selected_kelas; ?></span></h4>
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php foreach ($assigned_materi as $kategori => $materi_items): ?>
                        <div>
                            <h5 class="font-bold text-gray-600"><?php echo $kategori; ?></h5>
                            <ul class="mt-2 space-y-1 text-sm">
                                <?php foreach ($materi_items as $materi): ?>
                                    <li class="flex justify-between items-center p-2 rounded hover:bg-gray-50">
                                        <span><?php echo htmlspecialchars($materi['nama_materi']); ?></span>
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