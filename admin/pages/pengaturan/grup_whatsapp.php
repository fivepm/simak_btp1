<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan_grup') {
        $id = $_POST['id'] ?? 0;
        $nama_grup = trim($_POST['nama_grup'] ?? '');
        $group_id = trim($_POST['group_id'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        // HAK AKSES: Tentukan kelompok
        if ($admin_tingkat === 'kelompok') {
            $kelompok = $admin_kelompok;
        } else {
            $kelompok = ($_POST['kelompok'] === 'semua') ? null : ($_POST['kelompok'] ?? null);
        }
        $kelas = ($_POST['kelas'] === 'semua') ? null : ($_POST['kelas'] ?? null);

        if (empty($nama_grup) || empty($group_id)) {
            $error_message = 'Nama Grup dan ID Grup wajib diisi.';
        }

        if (empty($error_message)) {
            if (empty($id)) { // Proses Tambah
                $sql = "INSERT INTO grup_whatsapp (nama_grup, kelompok, kelas, group_id, keterangan) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $nama_grup, $kelompok, $kelas, $group_id, $keterangan);
            } else { // Proses Edit
                $sql = "UPDATE grup_whatsapp SET nama_grup=?, kelompok=?, kelas=?, group_id=?, keterangan=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $nama_grup, $kelompok, $kelas, $group_id, $keterangan, $id);
            }

            if ($stmt->execute()) {
                $redirect_url = '?page=pengaturan/grup_whatsapp&status=save_success';
            } else {
                $error_message = 'Gagal menyimpan. ID Grup mungkin sudah terdaftar.';
            }
            $stmt->close();
        }
    }

    if ($action === 'hapus_grup') {
        $id = $_POST['hapus_id'] ?? 0;
        if (!empty($id)) {
            $stmt = $conn->prepare("DELETE FROM grup_whatsapp WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=pengaturan/grup_whatsapp&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus grup.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'save_success') $success_message = 'Data grup berhasil disimpan!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Grup berhasil dihapus!';
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
$grup_list = [];
$sql = "SELECT * FROM grup_whatsapp";
if ($admin_tingkat === 'kelompok') {
    $sql .= " WHERE kelompok = ? OR kelompok IS NULL";
}
$sql .= " ORDER BY nama_grup";
$stmt = $conn->prepare($sql);
if ($admin_tingkat === 'kelompok') {
    $stmt->bind_param("s", $admin_kelompok);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $grup_list[] = $row;
    }
}
?>
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola ID Grup WhatsApp</h3>
        <button id="tambahBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Grup</button>
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Grup</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelompok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Grup</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($grup_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada data grup.</td>
                    </tr>
                    <?php else: foreach ($grup_list as $grup): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold"><?php echo htmlspecialchars($grup['nama_grup']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($grup['kelompok'] ?? 'Semua'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($grup['kelas'] ?? 'Semua'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($grup['group_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900" data-grup='<?php echo json_encode($grup); ?>'>Edit</button>
                                <button class="hapus-btn text-red-600 hover:text-red-900 ml-4" data-id="<?php echo $grup['id']; ?>" data-nama="<?php echo htmlspecialchars($grup['nama_grup']); ?>">Hapus</button>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div id="formModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/grup_whatsapp">
                <input type="hidden" name="action" value="simpan_grup">
                <input type="hidden" name="id" id="grup_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Form Grup</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Grup*</label><input type="text" name="nama_grup" id="nama_grup" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">ID Grup*</label><input type="text" name="group_id" id="group_id" placeholder="628xxxx@g.us" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Untuk Kelompok</label>
                            <?php if ($admin_tingkat === 'kelompok'): ?>
                                <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                            <?php else: ?>
                                <select name="kelompok" id="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                                    <option value="semua">Semua Kelompok (Umum)</option>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div><label class="block text-sm font-medium">Untuk Kelas</label><select name="kelas" id="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                                <option value="semua">Semua Kelas (Umum)</option>
                                <option value="paud">PAUD</option>
                                <option value="caberawit a">Caberawit A</option>
                                <option value="caberawit b">Caberawit B</option>
                                <option value="pra remaja">Pra Remaja</option>
                                <option value="remaja">Remaja</option>
                                <option value="pra nikah">Pra Nikah</option>
                            </select></div>
                        <div><label class="block text-sm font-medium">Keterangan</label><textarea name="keterangan" id="keterangan" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
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

<!-- Modal Hapus -->
<div id="hapusModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=pengaturan/grup_whatsapp">
                <input type="hidden" name="action" value="hapus_grup">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus grup <strong id="hapus_nama"></strong>?</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
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

        const formModal = document.getElementById('formModal');
        const hapusModal = document.getElementById('hapusModal');
        const btnTambah = document.getElementById('tambahBtn');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        btnTambah.onclick = () => {
            formModal.querySelector('form').reset();
            document.getElementById('grup_id').value = '';
            document.getElementById('modalTitle').textContent = 'Form Tambah Grup';
            openModal(formModal);
        };

        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.onclick = () => {
                closeModal(formModal);
                closeModal(hapusModal);
            };
        });

        document.querySelector('tbody').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('edit-btn')) {
                const data = JSON.parse(target.dataset.grup);
                document.getElementById('modalTitle').textContent = 'Form Edit Grup';
                document.getElementById('grup_id').value = data.id;
                document.getElementById('nama_grup').value = data.nama_grup;
                document.getElementById('group_id').value = data.group_id;
                document.getElementById('kelompok').value = data.kelompok || 'semua';
                document.getElementById('kelas').value = data.kelas || 'semua';
                document.getElementById('keterangan').value = data.keterangan;
                openModal(formModal);
            }

            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_nama').textContent = target.dataset.nama;
                openModal(hapusModal);
            }
        });
    });
</script>