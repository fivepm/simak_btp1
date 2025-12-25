<?php
// Pastikan $conn sudah ada dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Logika Hapus (jika ada param ?action=hapus&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_laporan = (int)$_GET['id'];

    $q_cek = $conn->query("SELECT tanggal_laporan FROM laporan_harian WHERE id = $id_laporan");
    if ($row_cek = $q_cek->fetch_assoc()) $tanggal_laporan = $row_cek['tanggal_laporan'];

    $stmt_hapus = $conn->prepare("DELETE FROM laporan_harian WHERE id = ?");
    $stmt_hapus->bind_param("i", $id_laporan);
    if ($stmt_hapus->execute()) {
        // --- CCTV ---
        $deskripsi_log = "Menghapus *Laporan Harian* pada tanggal " . formatTanggalIndonesia($tanggal_laporan) . ".";
        writeLog('DELETE', $deskripsi_log);
        // -------------------------------------------
        echo "<script>alert('Laporan berhasil dihapus.'); window.location.href='?page=report/daftar_laporan_harian';</script>";
    } else {
        echo "<script>alert('Gagal menghapus laporan.');</script>";
    }
    $stmt_hapus->close();
}

// Ambil semua laporan
$laporan_list = [];
$sql = "SELECT id, tanggal_laporan, nama_admin_pembuat, status_laporan, timestamp_dibuat 
        FROM laporan_harian 
        ORDER BY tanggal_laporan DESC, timestamp_dibuat DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $laporan_list[] = $row;
    }
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Laporan Harian</h1>
        <a href="?page=report/form_laporan_harian" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition duration-300">
            <i class="fas fa-plus mr-2"></i> Buat Laporan Baru
        </a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Laporan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat Oleh</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Dibuat</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($laporan_list)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Belum ada laporan yang dibuat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($laporan_list as $laporan): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?php echo date('d M Y', strtotime($laporan['tanggal_laporan'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($laporan['nama_admin_pembuat']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($laporan['status_laporan'] === 'Final'): ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Final</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($laporan['timestamp_dibuat'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="?page=report/lihat_laporan_harian&id=<?php echo $laporan['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Lihat/Cetak">
                                    <i class="fas fa-eye mr-2"></i>
                                </a>
                                <?php if ($laporan['status_laporan'] === 'Draft'): // Hanya draft yang bisa diedit/dihapus 
                                ?>
                                    <a href="?page=report/form_laporan_harian&id=<?php echo $laporan['id']; ?>" class="text-yellow-600 hover:text-yellow-900 ml-2" title="Edit">
                                        <i class="fas fa-edit mr-2"></i>
                                    </a>
                                    <a href="?page=report/daftar_laporan_harian&action=hapus&id=<?php echo $laporan['id']; ?>" class="text-red-600 hover:text-red-900 ml-2" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus laporan draft ini?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>