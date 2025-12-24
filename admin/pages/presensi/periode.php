<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '';

// === BAGIAN BACKEND: PROSES POST REQUEST UNTUK PERIODE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH PERIODE ---
    if ($action === 'tambah_periode') {
        $nama_periode = $_POST['nama_periode'] ?? '';
        $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
        $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';

        if (empty($nama_periode) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
            $error_message = 'Semua field wajib diisi.';
        } elseif ($tanggal_selesai < $tanggal_mulai) {
            $error_message = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO periode (nama_periode, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $nama_periode, $tanggal_mulai, $tanggal_selesai);
            if ($stmt->execute()) {
                // === CCTV ===
                $desc_log = "Menambahkan data *Periode* : *" . ucwords($nama_periode) . "*.";
                writeLog('INSERT', $desc_log);

                $redirect_url = '?page=presensi/periode&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan periode.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT PERIODE ---
    if ($action === 'edit_periode') {
        $id = $_POST['edit_id'] ?? 0;
        $nama_periode = $_POST['edit_nama_periode'] ?? '';
        $tanggal_mulai = $_POST['edit_tanggal_mulai'] ?? '';
        $tanggal_selesai = $_POST['edit_tanggal_selesai'] ?? '';
        $status = $_POST['edit_status'] ?? 'Aktif';

        if (empty($nama_periode) || empty($tanggal_mulai) || empty($tanggal_selesai) || empty($id)) {
            $error_message = 'Data tidak lengkap untuk proses edit.';
        } elseif ($tanggal_selesai < $tanggal_mulai) {
            $error_message = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if (empty($error_message)) {
            $periode = $conn->query("SELECT * FROM periode WHERE id = $id")->fetch_assoc();

            $sql = "UPDATE periode SET nama_periode = ?, tanggal_mulai = ?, tanggal_selesai = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $nama_periode, $tanggal_mulai, $tanggal_selesai, $status, $id);
            if ($stmt->execute()) {
                // === CCTV ===
                if ($nama_periode == $periode['nama_periode']) {
                    $desc_log = "Memperbarui data *Periode* : *" . ucwords($nama_periode) . "*.";
                } else {
                    $desc_log = "Memperbarui data *Periode* : *" . ucwords($periode['nama_periode']) . "* menjadi *$nama_periode*.";
                }
                writeLog('UPDATE', $desc_log);

                $redirect_url = '?page=presensi/periode&status=edit_success';
            } else {
                $error_message = 'Gagal mengedit periode.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS PERIODE ---
    if ($action === 'hapus_periode') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID periode tidak valid.';
        } else {
            $periode = $conn->query("SELECT * FROM periode WHERE id = $id")->fetch_assoc();

            // ON DELETE CASCADE di database akan otomatis menghapus jadwal & rekap terkait
            $stmt = $conn->prepare("DELETE FROM periode WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // === CCTV ===
                $desc_log = "Menghapus data *Periode* : *" . ucwords($periode['nama_periode']) . "*.";
                writeLog('DELETE', $desc_log);

                $redirect_url = '?page=presensi/periode&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus periode. Pastikan tidak ada data terkait.';
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Periode baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Periode berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Periode berhasil dihapus!';
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
        <h3 class="text-gray-700 text-2xl font-medium">Manajemen Periode</h3>
        <?php if ($admin_tingkat === 'desa'): ?>
            <button id="tambahPeriodeBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">
                Tambah Periode
            </button>
        <?php endif; ?>
    </div>

    <!-- Notifikasi -->
    <?php if (!empty($success_message)): ?>
        <div id="success-alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $success_message; ?></span></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div id="error-alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $error_message; ?></span></div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Periode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <?php if ($admin_tingkat === 'desa'): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    <?php endif; ?>
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
                            <?php if ($admin_tingkat === 'desa'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="edit-btn text-indigo-600 hover:text-indigo-900" data-periode='<?php echo json_encode($periode); ?>'>Edit</button>
                                    <button class="hapus-btn text-red-600 hover:text-red-900 ml-4" data-id="<?php echo $periode['id']; ?>" data-nama="<?php echo htmlspecialchars($periode['nama_periode']); ?>">Hapus</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Periode -->
<div id="tambahPeriodeModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=presensi/periode">
                <input type="hidden" name="action" value="tambah_periode">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Periode Baru</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Periode*</label><input type="text" name="nama_periode" placeholder="Contoh: Agustus - September 2025" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Tanggal Mulai*</label><input type="date" name="tanggal_mulai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Tanggal Selesai*</label><input type="date" name="tanggal_selesai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
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

<!-- Modal Edit Periode -->
<div id="editPeriodeModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=presensi/periode">
                <input type="hidden" name="action" value="edit_periode">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Periode</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Periode*</label><input type="text" name="edit_nama_periode" id="edit_nama_periode" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Tanggal Mulai*</label><input type="date" name="edit_tanggal_mulai" id="edit_tanggal_mulai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Tanggal Selesai*</label><input type="date" name="edit_tanggal_selesai" id="edit_tanggal_selesai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Status*</label><select name="edit_status" id="edit_status" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                <option value="Aktif">Aktif</option>
                                <option value="Selesai">Selesai</option>
                                <option value="Arsip">Arsip</option>
                            </select></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Update</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Periode -->
<div id="hapusPeriodeModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=presensi/periode">
                <input type="hidden" name="action" value="hapus_periode">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus periode <strong id="hapus_nama"></strong>? Semua jadwal dan rekap presensi di dalamnya juga akan terhapus.</p>
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
        // --- JavaScript Redirect ---
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

        // --- Modal Controls ---
        const modals = {
            tambah: document.getElementById('tambahPeriodeModal'),
            edit: document.getElementById('editPeriodeModal'),
            hapus: document.getElementById('hapusPeriodeModal')
        };

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        document.getElementById('tambahPeriodeBtn').onclick = () => openModal(modals.tambah);

        document.querySelectorAll('.fixed.z-20').forEach(modal => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('.modal-close-btn')) {
                    closeModal(modal);
                }
            });
        });

        // --- Dynamic Button Listeners ---
        document.querySelector('tbody').addEventListener('click', function(event) {
            const target = event.target;

            // Edit Button
            if (target.classList.contains('edit-btn')) {
                const data = JSON.parse(target.dataset.periode);
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_nama_periode').value = data.nama_periode;
                document.getElementById('edit_tanggal_mulai').value = data.tanggal_mulai;
                document.getElementById('edit_tanggal_selesai').value = data.tanggal_selesai;
                document.getElementById('edit_status').value = data.status;
                openModal(modals.edit);
            }

            // Hapus Button
            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_nama').textContent = target.dataset.nama;
                openModal(modals.hapus);
            }
        });

        // --- Re-open modal on POST error ---
        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($_POST['action'] === 'tambah_periode'): ?>
                openModal(modals.tambah);
            <?php elseif ($_POST['action'] === 'edit_periode'): ?>
                openModal(modals.edit);
            <?php endif; ?>
        <?php endif; ?>
    });
</script>