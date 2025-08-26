<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
$penasehat_list = [];
$result = $conn->query("SELECT * FROM penasehat ORDER BY nama ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $penasehat_list[] = $row;
    }
}
?>
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Penasehat</h3>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor WA</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($penasehat_list)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">Belum ada data penasehat.</td>
                    </tr>
                    <?php else: $i = 1;
                    foreach ($penasehat_list as $p): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($p['nomor_wa'] ?? '-'); ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {});
</script>