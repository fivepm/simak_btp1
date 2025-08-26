<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil data admin yang sedang login untuk hak akses
$jetuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// === AMBIL DATA UNTUK DITAMPILKAN ===
$template_list = [];
$sql = "SELECT * FROM template_pesan";
// HAK AKSES: Filter data yang ditampilkan untuk admin kelompok
if ($jetuapjp_tingkat === 'kelompok') {
    $sql .= " WHERE kelompok = ? OR kelompok IS NULL";
}
$sql .= " ORDER BY tipe_pesan, kelompok, kelas";
$stmt = $conn->prepare($sql);
if ($jetuapjp_tingkat === 'kelompok') {
    $stmt->bind_param("s", $ketuapjp_kelompok);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $template_list[] = $row;
    }
}
?>
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Template Pesan</h3>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe Pesan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelompok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Isi Template</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($template_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada template.</td>
                    </tr>
                    <?php else: foreach ($template_list as $template): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold"><?php echo htmlspecialchars($template['tipe_pesan']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize font-semibold">
                                <?php echo htmlspecialchars($template['kelompok'] ?? 'Semua Kelompok'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize font-semibold"><?php echo htmlspecialchars($template['kelas']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <pre class="font-sans whitespace-pre-wrap"><?php echo htmlspecialchars($template['template']); ?></pre>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>