<?php
// Variabel $conn dan data session sudah tersedia dari index.php
$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// Ambil filter dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;

// === AMBIL DATA DARI DATABASE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

$jurnal_list = [];
if ($selected_periode_id) {
    $sql_jurnal = "SELECT * FROM jadwal_presensi WHERE periode_id = ? AND kelompok = ? AND kelas = ? AND pengajar IS NOT NULL AND pengajar != '' ORDER BY tanggal DESC";
    $stmt_jurnal = $conn->prepare($sql_jurnal);
    $stmt_jurnal->bind_param("iss", $selected_periode_id, $guru_kelompok, $guru_kelas);
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
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Rekap Jurnal</h3>
        <form id="filterForm" method="GET" action="">
            <input type="hidden" name="page" value="rekap_jurnal">
            <div class="flex items-center gap-4">
                <select name="periode_id" onchange="this.form.submit()" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                    <option value="">-- Pilih Periode --</option>
                    <?php foreach ($periode_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

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