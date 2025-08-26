<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil kategori yang dipilih dari URL
$selected_kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;
$selected_kategori = null;

// === AMBIL DATA DARI DATABASE ===
$kategori_list = [];
$sql_kat = "SELECT id, nama_kategori FROM materi_kategori ORDER BY nama_kategori ASC";
$result_kat = $conn->query($sql_kat);
if ($result_kat) {
    while ($row = $result_kat->fetch_assoc()) {
        $kategori_list[] = $row;
    }
}

$materi_induk_list = [];
if ($selected_kategori_id) {
    $stmt_kat_sel = $conn->prepare("SELECT nama_kategori FROM materi_kategori WHERE id = ?");
    $stmt_kat_sel->bind_param("i", $selected_kategori_id);
    $stmt_kat_sel->execute();
    $selected_kategori = $stmt_kat_sel->get_result()->fetch_assoc();
    $stmt_kat_sel->close();

    $stmt_materi = $conn->prepare("SELECT id, judul_materi, deskripsi FROM materi_induk WHERE kategori_id = ? ORDER BY judul_materi ASC");
    $stmt_materi->bind_param("i", $selected_kategori_id);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();
    if ($result_materi) {
        while ($row = $result_materi->fetch_assoc()) {
            $materi_induk_list[] = $row;
        }
    }
    $stmt_materi->close();
}
?>
<div class="container mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-semibold text-gray-800">Pustaka Materi</h1>
        <p class="mt-1 text-gray-600">Daftar semua materi pembelajaran di sini, mulai dari kategori hingga file dan video.</p>
    </div>

    <!-- BAGIAN 1: MANAJEMEN KATEGORI -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Kategori Materi</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Kategori</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($kategori_list)): ?>
                        <tr>
                            <td colspan="2" class="text-center py-4 text-gray-500">Belum ada kategori.</td>
                        </tr>
                        <?php else: foreach ($kategori_list as $kategori): ?>
                            <tr class="<?php echo ($selected_kategori_id === (int)$kategori['id']) ? 'bg-green-200' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap font-medium">
                                    <a href="?page=pustaka_materi/index&kategori_id=<?php echo $kategori['id']; ?>" class="text-indigo-600 hover:text-indigo-800">
                                        <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                    </a>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BAGIAN 2: MANAJEMEN MATERI (Dinamis) -->
    <?php if ($selected_kategori_id && $selected_kategori): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Daftar Materi di <span class="text-green-600"><?php echo htmlspecialchars($selected_kategori['nama_kategori']); ?></span></h2>
            </div>

            <div class="space-y-4">
                <?php if (empty($materi_induk_list)): ?>
                    <p class="text-center text-gray-500 py-4">Belum ada materi di kategori ini.</p>
                    <?php else: foreach ($materi_induk_list as $materi): ?>
                        <div class="border rounded-lg p-4 flex justify-between items-center group hover:bg-green-100">
                            <div>
                                <!-- <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($materi['judul_materi']); ?></h3> -->
                                <a href="?page=pustaka_materi/detail_materi&materi_id=<?php echo $materi['id']; ?>" class="text-indigo-600 font-semibold hover:text-indigo-800">
                                    <?php echo htmlspecialchars($materi['judul_materi']); ?>
                                </a>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($materi['deskripsi']); ?></p>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>