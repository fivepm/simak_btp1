<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

// Jika admin tingkat kelompok, paksa filter kelompok
if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

// === AMBIL DATA DARI DATABASE ===
// Ambil daftar periode aktif
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// Ambil data jurnal jika periode dipilih
$jurnal_list = [];
if ($selected_periode_id) {
    $sql_jurnal = "SELECT * FROM jadwal_presensi WHERE periode_id = ? AND pengajar IS NOT NULL AND pengajar != ''";
    $params = [$selected_periode_id];
    $types = "i";

    if ($selected_kelompok !== 'semua') {
        $sql_jurnal .= " AND kelompok = ?";
        $params[] = $selected_kelompok;
        $types .= "s";
    }
    if ($selected_kelas !== 'semua') {
        $sql_jurnal .= " AND kelas = ?";
        $params[] = $selected_kelas;
        $types .= "s";
    }
    $sql_jurnal .= " ORDER BY tanggal DESC, kelompok, kelas";

    $stmt_jurnal = $conn->prepare($sql_jurnal);
    if (!empty($params)) {
        $stmt_jurnal->bind_param($types, ...$params);
    }
    $stmt_jurnal->execute();
    $result_jurnal = $stmt_jurnal->get_result();
    if ($result_jurnal) {
        while ($row = $result_jurnal->fetch_assoc()) {
            $jurnal_list[] = $row;
        }
    }
}
?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Jurnal Harian</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/jurnal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label>
                    <select name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required onchange="this.form.submit()">
                        <option value="">-- Pilih Periode --</option>
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                            <option value="semua">Semua Kelompok</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label><select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                        <option value="semua">Semua Kelas</option><?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                                                                    foreach ($kelas_opts as $k): ?><option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: DAFTAR JURNAL -->
    <?php if ($selected_periode_id): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-medium text-gray-800 mb-4">Daftar Jurnal yang Telah Diisi</h3>
            <div class="space-y-4">
                <?php if (empty($jurnal_list)): ?>
                    <p class="text-center text-gray-500 py-8">Tidak ada jurnal yang cocok dengan filter yang dipilih.</p>
                    <?php else: foreach ($jurnal_list as $jurnal): ?>
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-bold text-gray-800 text-lg"><?php echo date("d M Y", strtotime($jurnal['tanggal'])); ?></p>
                                    <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($jurnal['kelompok'] . ' - ' . $jurnal['kelas']); ?></p>
                                </div>
                                <p class="text-sm text-gray-600">Pengajar: <span class="font-semibold"><?php echo htmlspecialchars($jurnal['pengajar']); ?></span></p>
                            </div>
                            <div class="mt-4 pt-4 border-t">
                                <h4 class="font-semibold text-gray-700">Materi yang Disampaikan:</h4>
                                <ul class="list-disc list-inside text-gray-600 text-sm mt-2 space-y-1">
                                    <?php if (!empty($jurnal['materi1'])): ?><li><?php echo htmlspecialchars($jurnal['materi1']); ?></li><?php endif; ?>
                                    <?php if (!empty($jurnal['materi2'])): ?><li><?php echo htmlspecialchars($jurnal['materi2']); ?></li><?php endif; ?>
                                    <?php if (!empty($jurnal['materi3'])): ?><li><?php echo htmlspecialchars($jurnal['materi3']); ?></li><?php endif; ?>
                                    <?php if (empty($jurnal['materi1']) && empty($jurnal['materi2']) && empty($jurnal['materi3'])): ?>
                                        <li class="italic">Tidak ada detail materi yang diisi.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>