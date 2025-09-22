<?php
// ===================================================================
// BLOK PENGAMBILAN DATA (READ) UNTUK DITAMPILKAN
// ===================================================================
$sql = "SELECT * FROM musyawarah ORDER BY tanggal DESC";
$result = $conn->query($sql);

?>

<!-- Di sini Anda bisa menyertakan header atau layout utama admin -->
<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">Daftar Notulensi Musyawarah</h1>

    <div class="bg-white rounded-lg shadow-md p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-700">Pilih Musyawarah</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Nama Musyawarah</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Tanggal</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Pimpinan Rapat</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Tempat</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase">Status</th>
                        <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4"><?= htmlspecialchars($row['nama_musyawarah']) ?></td>
                                <td class="py-3 px-4"><?= date('d F Y', strtotime($row['tanggal'])) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['pimpinan_rapat']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['tempat']) ?></td>
                                <td class="py-3 px-4">
                                    <?php
                                    $status_class = '';
                                    switch ($row['status']) {
                                        case 'Selesai':
                                            $status_class = 'bg-green-200 text-green-800';
                                            break;
                                        case 'Dibatalkan':
                                            $status_class = 'bg-red-200 text-red-800';
                                            break;
                                        default:
                                            $status_class = 'bg-yellow-200 text-yellow-800';
                                    }
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $status_class ?>"><?= $row['status'] ?></span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="grid grid-cols-1 gap-2">
                                        <a href="?page=musyawarah/catat_notulensi&id=<?= $row['id'] ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Catat Notulensi">
                                            <i class="fas fa-pencil-alt"></i> Isi Notulensi
                                        </a>
                                        <a href="?page=musyawarah/evaluasi_notulensi&id=<?= $row['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Catat Notulensi">
                                            <i class="fa-solid fa-circle-check"></i> Evaluasi Notulensi
                                        </a>
                                        <a href="?page=musyawarah/lihat_notulensi&id=<?= $row['id'] ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold p-2 rounded-lg text-xs transition duration-300" title="Catat Notulensi">
                                            <i class="fa-solid fa-eye"></i> Lihat Notulensi
                                        </a>
                                    </div>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-gray-500">Belum ada data musyawarah.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->