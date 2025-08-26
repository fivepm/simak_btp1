<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// === AMBIL DATA PERIODE UNTUK DITAMPILKAN ===
$periode_list = [];
$sql = "SELECT * FROM periode ORDER BY tanggal_mulai DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// === TAMPILAN HTML ===
?>
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Periode</h3>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Periode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($periode_list)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">Belum ada data periode.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($periode_list as $periode): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?php echo htmlspecialchars($periode['nama_periode']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo date("d M Y", strtotime($periode['tanggal_mulai'])) . ' - ' . date("d M Y", strtotime($periode['tanggal_selesai'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo ($periode['status'] === 'Aktif') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo htmlspecialchars($periode['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {});
</script>