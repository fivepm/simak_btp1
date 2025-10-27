<?php
// Pastikan $conn sudah ada dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil semua laporan
$laporan_list = [];
$sql = "SELECT id, tanggal_laporan, nama_admin_pembuat, status_laporan, timestamp_dibuat 
        FROM laporan_harian 
        WHERE status_laporan = 'Final'
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
        <a href="?page=report/form_laporan_harian" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition duration-300">
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
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
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
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <a href="?page=report/lihat_laporan_harian&id=<?php echo $laporan['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Lihat/Cetak">
                                    <i class="fas fa-eye mr-2"></i> Lihat Laporan
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>