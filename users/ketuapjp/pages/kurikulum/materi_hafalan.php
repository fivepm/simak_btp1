<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
$materi_list = [];
$sql = "SELECT id, kategori, nama_materi FROM materi_hafalan ORDER BY kategori, nama_materi";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $materi_list[$row['kategori']][] = $row;
    }
}
?>
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Daftar Materi Hafalan</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php $kategori_list = ['Surat', 'Doa', 'Dalil']; ?>
        <?php foreach ($kategori_list as $kategori): ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-xl font-semibold text-gray-800 mb-4"><?php echo $kategori; ?></h4>
                <ul class="space-y-2">
                    <?php if (!empty($materi_list[$kategori])): ?>
                        <?php foreach ($materi_list[$kategori] as $materi): ?>
                            <li class="flex justify-between items-center text-sm group">
                                <span><?php echo htmlspecialchars($materi['nama_materi']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-gray-400 italic">Belum ada data.</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>