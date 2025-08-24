<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil kategori yang dipilih dari URL
$selected_kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;
$selected_kategori = null;

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI KATEGORI ---
    if ($action === 'tambah_kategori') {
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        if (empty($nama_kategori)) {
            $error_message = 'Nama Kategori wajib diisi.';
        } else {
            $sql = "INSERT INTO materi_kategori (nama_kategori) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $nama_kategori);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/index&status=add_cat_success';
            } else {
                $error_message = 'Gagal menambahkan kategori. Mungkin sudah ada.';
            }
            $stmt->close();
        }
    }
    if ($action === 'edit_kategori') {
        $id = $_POST['edit_id'] ?? 0;
        $nama_kategori = trim($_POST['edit_nama_kategori'] ?? '');
        if (empty($id) || empty($nama_kategori)) {
            $error_message = 'Data tidak lengkap.';
        } else {
            $stmt = $conn->prepare("UPDATE materi_kategori SET nama_kategori = ? WHERE id = ?");
            $stmt->bind_param("si", $nama_kategori, $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/index&status=edit_cat_success';
            } else {
                $error_message = 'Gagal memperbarui kategori.';
            }
            $stmt->close();
        }
    }
    if ($action === 'hapus_kategori') {
        $id = $_POST['hapus_id'] ?? 0;
        if (!empty($id)) {
            $stmt = $conn->prepare("DELETE FROM materi_kategori WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/index&status=delete_cat_success';
            } else {
                $error_message = 'Gagal menghapus kategori.';
            }
            $stmt->close();
        }
    }

    // --- AKSI MATERI INDUK ---
    if ($action === 'tambah_materi_induk') {
        $kategori_id = $_POST['kategori_id'] ?? 0;
        $judul_materi = trim($_POST['judul_materi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if (empty($kategori_id) || empty($judul_materi)) {
            $error_message = 'Judul Materi wajib diisi.';
        } else {
            $sql = "INSERT INTO materi_induk (kategori_id, judul_materi, deskripsi) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $kategori_id, $judul_materi, $deskripsi);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/index&kategori_id=' . $kategori_id . '&status=add_materi_success';
            } else {
                $error_message = 'Gagal menambahkan materi.';
            }
            $stmt->close();
        }
    }
    if ($action === 'edit_materi_induk') {
        $id = $_POST['edit_materi_id'] ?? 0;
        $judul_materi = trim($_POST['edit_judul_materi'] ?? '');
        $deskripsi = trim($_POST['edit_deskripsi'] ?? '');
        if (empty($id) || empty($judul_materi)) {
            $error_message = 'Data tidak lengkap.';
        } else {
            $stmt = $conn->prepare("UPDATE materi_induk SET judul_materi = ?, deskripsi = ? WHERE id = ?");
            $stmt->bind_param("ssi", $judul_materi, $deskripsi, $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/index&kategori_id=' . $selected_kategori_id . '&status=edit_materi_success';
            } else {
                $error_message = 'Gagal memperbarui materi.';
            }
            $stmt->close();
        }
    }
    if ($action === 'hapus_materi_induk') {
        $id = $_POST['hapus_materi_id'] ?? 0;
        if (!empty($id)) {
            $stmt = $conn->prepare("DELETE FROM materi_induk WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pustaka_materi/index&kategori_id=' . $selected_kategori_id . '&status=delete_materi_success';
            } else {
                $error_message = 'Gagal menghapus materi.';
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_cat_success') $success_message = 'Kategori baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_cat_success') $success_message = 'Kategori berhasil diperbarui!';
    if ($_GET['status'] === 'delete_cat_success') $success_message = 'Kategori berhasil dihapus!';
    if ($_GET['status'] === 'add_materi_success') $success_message = 'Materi baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_materi_success') $success_message = 'Materi berhasil diperbarui!';
    if ($_GET['status'] === 'delete_materi_success') $success_message = 'Materi berhasil dihapus!';
}

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
        <p class="mt-1 text-gray-600">Kelola semua materi pembelajaran di sini, mulai dari kategori hingga file dan video.</p>
    </div>

    <!-- BAGIAN 1: MANAJEMEN KATEGORI -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Kategori Materi</h2>
            <?php if ($admin_tingkat === 'desa'): ?>
                <button id="tambahKategoriBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg text-sm">Tambah Kategori</button>
            <?php endif; ?>
        </div>

        <?php if (!empty($success_message) && strpos($_GET['status'] ?? '', '_cat_') !== false): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if (!empty($error_message) && isset($_POST['action']) && strpos($_POST['action'], '_kategori') !== false): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Kategori</th>
                        <?php if ($admin_tingkat === 'desa'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        <?php endif; ?>
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
                                <?php if ($admin_tingkat === 'desa'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="edit-kategori-btn text-indigo-600 hover:text-indigo-800" data-id="<?php echo $kategori['id']; ?>" data-nama="<?php echo htmlspecialchars($kategori['nama_kategori']); ?>">Edit</button>
                                        <form method="POST" action="?page=pustaka_materi/index" class="inline ml-4" onsubmit="return confirm('Menghapus kategori akan menghapus SEMUA materi di dalamnya. Anda yakin?');">
                                            <input type="hidden" name="action" value="hapus_kategori">
                                            <input type="hidden" name="hapus_id" value="<?php echo $kategori['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
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
                <?php if ($admin_tingkat === 'desa'): ?>
                    <button id="tambahMateriBtn" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg text-sm">Tambah Materi</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($success_message) && strpos($_GET['status'] ?? '', '_materi_') !== false): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if (!empty($error_message) && isset($_POST['action']) && strpos($_POST['action'], '_materi_') !== false): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

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
                            <?php if ($admin_tingkat === 'desa'): ?>
                                <div class="flex items-center space-x-2">
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button class="edit-materi-btn text-indigo-600 hover:text-indigo-800 text-sm" data-materi='<?php echo json_encode($materi); ?>'>Edit</button>
                                        <button class="hapus-materi-btn text-red-600 hover:text-red-800 text-sm ml-2" data-id="<?php echo $materi['id']; ?>" data-nama="<?php echo htmlspecialchars($materi['judul_materi']); ?>">Hapus</button>
                                    </div>
                                    <a href="?page=pustaka_materi/detail_materi&materi_id=<?php echo $materi['id']; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg text-sm">Kelola Poin & File</a>
                                </div>
                            <?php endif; ?>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Kategori -->
<div id="tambahKategoriModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/index">
                <input type="hidden" name="action" value="tambah_kategori">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Kategori Baru</h3>
                    <div>
                        <label for="nama_kategori" class="block text-sm font-medium text-gray-700">Nama Kategori*</label>
                        <input type="text" name="nama_kategori" id="nama_kategori" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
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

<!-- Modal Edit Kategori -->
<div id="editKategoriModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/index">
                <input type="hidden" name="action" value="edit_kategori">
                <input type="hidden" name="edit_id" id="edit_kategori_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Kategori</h3>
                    <div><label class="block text-sm font-medium">Nama Kategori*</label><input type="text" name="edit_nama_kategori" id="edit_nama_kategori" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Update</button><button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Materi Induk -->
<div id="tambahMateriModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/index&kategori_id=<?php echo $selected_kategori_id; ?>">
                <input type="hidden" name="action" value="tambah_materi_induk">
                <input type="hidden" name="kategori_id" value="<?php echo $selected_kategori_id; ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Materi Baru</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Judul Materi*</label><input type="text" name="judul_materi" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Deskripsi Singkat</label><textarea name="deskripsi" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan Materi</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Materi Induk -->
<div id="editMateriModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/index&kategori_id=<?php echo $selected_kategori_id; ?>">
                <input type="hidden" name="action" value="edit_materi_induk">
                <input type="hidden" name="edit_materi_id" id="edit_materi_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Materi</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Judul Materi*</label><input type="text" name="edit_judul_materi" id="edit_judul_materi" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Deskripsi Singkat</label><textarea name="edit_deskripsi" id="edit_deskripsi" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Update</button><button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Materi Induk -->
<div id="hapusMateriModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pustaka_materi/index&kategori_id=<?php echo $selected_kategori_id; ?>">
                <input type="hidden" name="action" value="hapus_materi_induk">
                <input type="hidden" name="hapus_materi_id" id="hapus_materi_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus materi <strong id="hapus_materi_nama"></strong>? Semua poin dan file di dalamnya juga akan terhapus.</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button><button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?php echo $redirect_url; ?>';
        <?php endif; ?>

        const autoHideAlert = (alertId) => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                setTimeout(() => {
                    alertElement.style.transition = 'opacity 0.5s ease';
                    alertElement.style.opacity = '0';
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500); // Waktu untuk animasi fade-out
                }, 3000); // 3000 milidetik = 3 detik
            }
        };
        autoHideAlert('success-alert');
        autoHideAlert('error-alert');

        const modals = {
            tambahKategori: document.getElementById('tambahKategoriModal'),
            editKategori: document.getElementById('editKategoriModal'),
            tambahMateri: document.getElementById('tambahMateriModal'),
            editMateri: document.getElementById('editMateriModal'),
            hapusMateri: document.getElementById('hapusMateriModal')
        };
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        document.getElementById('tambahKategoriBtn').onclick = () => openModal(modals.tambahKategori);
        const btnTambahMateri = document.getElementById('tambahMateriBtn');
        if (btnTambahMateri) btnTambahMateri.onclick = () => openModal(modals.tambahMateri);

        Object.values(modals).forEach(modal => {
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
            });
        });

        document.querySelector('body').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('edit-kategori-btn')) {
                document.getElementById('edit_kategori_id').value = target.dataset.id;
                document.getElementById('edit_nama_kategori').value = target.dataset.nama;
                openModal(modals.editKategori);
            }
            if (target.classList.contains('edit-materi-btn')) {
                const data = JSON.parse(target.dataset.materi);
                document.getElementById('edit_materi_id').value = data.id;
                document.getElementById('edit_judul_materi').value = data.judul_materi;
                document.getElementById('edit_deskripsi').value = data.deskripsi;
                openModal(modals.editMateri);
            }
            if (target.classList.contains('hapus-materi-btn')) {
                document.getElementById('hapus_materi_id').value = target.dataset.id;
                document.getElementById('hapus_materi_nama').textContent = target.dataset.nama;
                openModal(modals.hapusMateri);
            }
        });

        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if (strpos($_POST['action'], '_kategori') !== false): ?>
                openModal(modals.tambahKategori);
            <?php elseif (strpos($_POST['action'], '_materi_') !== false): ?>
                openModal(modals.tambahMateri);
            <?php endif; ?>
        <?php endif; ?>
    });
</script>