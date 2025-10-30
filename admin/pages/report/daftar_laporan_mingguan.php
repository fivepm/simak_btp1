<?php
// Pastikan $conn sudah ada
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil daftar laporan mingguan, urutkan dari yang terbaru
$sql = "SELECT id, tanggal_mulai, tanggal_akhir, nama_admin_pembuat, status_laporan, timestamp_dibuat 
        FROM laporan_mingguan 
        ORDER BY tanggal_mulai DESC";
$result = $conn->query($sql);
$laporan_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $laporan_list[] = $row;
    }
}

// Helper format tanggal
if (!function_exists('formatTanggalIndoShort')) {
    function formatTanggalIndoShort($tanggal_db)
    {
        if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
        try {
            $date = new DateTime($tanggal_db);
            $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return $date->format('j') . ' ' . $bulan[(int)$date->format('n')]; // Contoh: 1 Jan
        } catch (Exception $e) {
            return date('d/m', strtotime($tanggal_db));
        }
    }
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Daftar Laporan Mingguan</h1>
        <a href="?page=report/form_laporan_mingguan" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition duration-300">
            <i class="fas fa-plus mr-2"></i> Buat Laporan Baru
        </a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode Minggu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembuat</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Dibuat</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($laporan_list)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">Belum ada laporan mingguan yang dibuat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($laporan_list as $laporan): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                <?php echo formatTanggalIndoShort($laporan['tanggal_mulai']) . ' - ' . formatTanggalIndoShort($laporan['tanggal_akhir']) . ' ' . date('Y', strtotime($laporan['tanggal_mulai'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($laporan['nama_admin_pembuat']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $laporan['status_laporan'] === 'Final' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $laporan['status_laporan']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-gray-500 text-sm">
                                <?php echo date('d/m/y H:i', strtotime($laporan['timestamp_dibuat'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                <a href="?page=report/lihat_laporan_mingguan&id=<?php echo $laporan['id']; ?>" class="text-cyan-600 hover:text-cyan-900" title="Lihat"><i class="fas fa-eye"></i></a>
                                <?php if ($laporan['status_laporan'] === 'Draft'): ?>
                                    <a href="?page=report/form_laporan_mingguan&id=<?php echo $laporan['id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></a>
                                    <!-- Tambahkan tombol hapus jika perlu -->
                                    <a href="?page=report/hapus_laporan&tipe=mingguan&id=<?php echo $laporan['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        title="Hapus"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus draf laporan mingguan ini?');">
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