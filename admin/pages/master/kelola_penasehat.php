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

    if ($action === 'tambah_penasehat') {
        $nama = trim($_POST['nama'] ?? '');
        $nomor_wa = trim($_POST['nomor_wa'] ?? '');

        if (empty($nama)) {
            $error_message = 'Nama wajib diisi.';
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO penasehat (nama, nomor_wa) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $nama, $nomor_wa);
            if ($stmt->execute()) {
                // === CCTV ===
                $desc_log = "Menambahkan *Penasehat*: *" . ucwords($nama) . "*.";
                writeLog('INSERT', $desc_log);

                $redirect_url = '?page=master/kelola_penasehat&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan penasehat.';
            }
            $stmt->close();
        }
    }

    if ($action === 'edit_penasehat') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = trim($_POST['edit_nama'] ?? '');
        $nomor_wa = trim($_POST['edit_nomor_wa'] ?? '');

        if (empty($id) || empty($nama)) {
            $error_message = 'Data tidak lengkap untuk proses edit.';
        }

        if (empty($error_message)) {
            $penasehat = $conn->query("SELECT * FROM penasehat WHERE id = $id")->fetch_assoc();

            $sql = "UPDATE penasehat SET nama=?, nomor_wa=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nama, $nomor_wa, $id);
            if ($stmt->execute()) {
                // === CCTV ===
                if ($nama == $penasehat['nama']) {
                    $desc_log = "Memperbarui data *Nomor WA Penasehat*: *" . ucwords($penasehat['nama']) . "*.";
                } else if ($nomor_wa == $penasehat['nomor_wa']) {
                    $desc_log = "Memperbarui data *Nama Penasehat*: *" . ucwords($penasehat['nama']) . "* menjadi *$nama*.";
                } else {
                    $desc_log = "Memperbarui data *Nama & Nomor WA Penasehat*: *" . ucwords($penasehat['nama']) . "* menjadi *$nama*.";
                }
                writeLog('UPDATE', $desc_log);

                $redirect_url = '?page=master/kelola_penasehat&status=edit_success';
            } else {
                $error_message = 'Gagal memperbarui penasehat.';
            }
            $stmt->close();
        }
    }

    if ($action === 'hapus_penasehat') {
        $id = $_POST['hapus_id'] ?? 0;
        if (!empty($id)) {
            $penasehat = $conn->query("SELECT * FROM penasehat WHERE id = $id")->fetch_assoc();

            $stmt = $conn->prepare("DELETE FROM penasehat WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // === CCTV ===
                $desc_log = "Menghapus data *Penasehat*: *" . ucwords($penasehat['nama']) . "*.";
                writeLog('DELETE', $desc_log);

                $redirect_url = '?page=master/kelola_penasehat&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus penasehat.';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Penasehat baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Data penasehat berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Data penasehat berhasil dihapus!';
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
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Penasehat</h3>
        <button id="tambahBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Tambah Penasehat</button>
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor WA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
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
                            <td class="px-6 py-4 text-sm whitespace-nowrap font-medium">
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900" data-penasehat='<?php echo json_encode($p); ?>'>Edit</button>
                                <button class="hapus-btn text-red-600 hover:text-red-900 ml-4" data-id="<?php echo $p['id']; ?>" data-nama="<?php echo htmlspecialchars($p['nama']); ?>">Hapus</button>
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
            <form method="POST" action="?page=master/kelola_penasehat">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Form Penasehat</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Lengkap*</label><input type="text" name="nama" id="nama" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Nomor WA</label><input type="text" name="nomor_wa" id="nomor_wa" placeholder="Contoh: 628123456789" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
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
            <form method="POST" action="?page=master/kelola_penasehat">
                <input type="hidden" name="action" value="hapus_penasehat">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus penasehat <strong id="hapus_nama"></strong>?</p>
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
            document.getElementById('form_action').value = 'tambah_penasehat';
            document.getElementById('edit_id').value = '';
            document.getElementById('modalTitle').textContent = 'Form Tambah Penasehat';
            document.getElementById('nama').name = 'nama';
            document.getElementById('nomor_wa').name = 'nomor_wa';
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
                const data = JSON.parse(target.dataset.penasehat);
                document.getElementById('form_action').value = 'edit_penasehat';
                document.getElementById('modalTitle').textContent = 'Form Edit Penasehat';
                document.getElementById('edit_id').value = data.id;
                document.getElementById('nama').value = data.nama;
                document.getElementById('nomor_wa').value = data.nomor_wa;
                document.getElementById('nama').name = 'edit_nama';
                document.getElementById('nomor_wa').name = 'edit_nomor_wa';
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