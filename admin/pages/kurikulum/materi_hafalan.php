<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_materi') {
        $kategori = $_POST['kategori'] ?? '';
        $nama_materi = trim($_POST['nama_materi'] ?? '');

        if (empty($kategori) || empty($nama_materi)) {
            $error_message = 'Kategori dan Nama Materi wajib diisi.';
        } else {
            $sql = "INSERT INTO materi_hafalan (kategori, nama_materi) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $kategori, $nama_materi);
            if ($stmt->execute()) {
                $redirect_url = '?page=kurikulum/materi_hafalan&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan materi. Mungkin sudah ada.';
            }
            $stmt->close();
        }
    }

    if ($action === 'hapus_materi') {
        $id = $_POST['hapus_id'] ?? 0;
        if (!empty($id)) {
            $stmt = $conn->prepare("DELETE FROM materi_hafalan WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=kurikulum/materi_hafalan&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus materi.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Materi baru berhasil ditambahkan!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Materi berhasil dihapus!';
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
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Materi Hafalan</h3>
        <button id="tambahBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Materi</button>
    </div>

    <?php if (!empty($success_message)): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

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
                                <form method="POST" action="?page=kurikulum/materi_hafalan" class="inline opacity-0 group-hover:opacity-100 transition-opacity" onsubmit="return confirm('Anda yakin ingin menghapus materi ini?');">
                                    <input type="hidden" name="action" value="hapus_materi">
                                    <input type="hidden" name="hapus_id" value="<?php echo $materi['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700">[Hapus]</button>
                                </form>
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

<div id="tambahModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=kurikulum/materi_hafalan">
                <input type="hidden" name="action" value="tambah_materi">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Materi Hafalan</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Kategori*</label><select name="kategori" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="Surat">Surat</option>
                                <option value="Doa">Doa</option>
                                <option value="Dalil">Dalil</option>
                            </select></div>
                        <div><label class="block text-sm font-medium">Nama Materi*</label><input type="text" name="nama_materi" placeholder="Contoh: An-Nas atau Doa Masuk Masjid" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>window.location.href = '<?php echo $redirect_url; ?>';
    <?php endif; ?>
    const modal = document.getElementById('tambahModal');
    const btn = document.getElementById('tambahBtn');
    if (btn) btn.onclick = () => modal.classList.remove('hidden');
    if (modal) modal.addEventListener('click', e => {
        if (e.target === modal || e.target.closest('.modal-close-btn')) modal.classList.add('hidden');
    });
    <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        document.getElementById('tambahModal').classList.remove('hidden');
    <?php endif; ?>
    });
</script>