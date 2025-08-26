<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = ''; // Variabel untuk menyimpan URL redirect

// === BAGIAN BACKEND: PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH KETUA PJP ---
    if ($action === 'tambah_pjp') {
        $nama = $_POST['nama'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $kelompok = $_POST['kelompok'] ?? '';
        $tingkat = $_POST['tingkat'] ?? '';

        if (empty($nama) || empty($username) || empty($password) || empty($kelompok) || empty($tingkat)) {
            $error_message = 'Semua field wajib diisi.';
        } else {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $error_message = 'Username sudah digunakan.';
            }
            $stmt_check->close();
        }

        if (empty($error_message)) {
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'ketua pjp'; // Role spesifik untuk Ketua PJP
            $barcode = 'PJP-' . uniqid(); // Prefix barcode baru
            $sql = "INSERT INTO users (nama, kelompok, role, tingkat, barcode, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $nama, $kelompok, $role, $tingkat, $barcode, $username, $password_hashed);
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kelola_ketua_pjp&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan Ketua PJP.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT KETUA PJP ---
    if ($action === 'edit_pjp') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        $username = $_POST['edit_username'] ?? '';
        $password = $_POST['edit_password'] ?? '';
        $kelompok = $_POST['edit_kelompok'] ?? '';
        $tingkat = $_POST['edit_tingkat'] ?? '';

        if (empty($nama) || empty($username) || empty($kelompok) || empty($tingkat) || empty($id)) {
            $error_message = 'Data tidak lengkap untuk proses edit.';
        } else {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_check->bind_param("si", $username, $id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $error_message = 'Username sudah digunakan oleh pengguna lain.';
            }
            $stmt_check->close();
        }

        if (empty($error_message)) {
            if (!empty($password)) {
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nama=?, username=?, kelompok=?, tingkat=?, password=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $nama, $username, $kelompok, $tingkat, $password_hashed, $id);
            } else {
                $sql = "UPDATE users SET nama=?, username=?, kelompok=?, tingkat=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $nama, $username, $kelompok, $tingkat, $id);
            }

            if ($stmt->execute()) {
                $redirect_url = '?page=master/kelola_ketua_pjp&status=edit_success';
            } else {
                $error_message = 'Gagal mengedit Ketua PJP.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS KETUA PJP ---
    if ($action === 'hapus_pjp') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID Ketua PJP tidak valid.';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'ketua pjp'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kelola_ketua_pjp&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus Ketua PJP.';
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Ketua PJP baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Data Ketua PJP berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Data Ketua PJP berhasil dihapus!';
}

// === AMBIL DATA UNTUK DITAMPILKAN ===
$pjp_users = [];
$sql = "SELECT id, nama, username, kelompok, tingkat, role, barcode FROM users WHERE role = 'ketua pjp' ORDER BY nama ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pjp_users[] = $row;
    }
}

// === TAMPILAN HTML ===
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Ketua PJP</h3>
        <button id="tambahPjpBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Tambah Ketua PJP
        </button>
    </div>

    <!-- Notifikasi -->
    <?php if (!empty($success_message)): ?>
        <div id="success-alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $success_message; ?></span></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div id="error-alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $error_message; ?></span></div>
    <?php endif; ?>

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QR Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="pjpTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($pjp_users)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada data Ketua PJP.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($pjp_users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['nama']); ?></div>
                                <div class="text-sm text-gray-500 capitalize">
                                    <?php echo htmlspecialchars($user['tingkat']); ?> - <?php echo htmlspecialchars($user['kelompok']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="qr-code-btn text-blue-500 hover:text-blue-700"
                                    data-barcode="<?php echo htmlspecialchars($user['barcode']); ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>">Lihat</button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-kelompok="<?php echo htmlspecialchars($user['kelompok']); ?>"
                                    data-tingkat="<?php echo htmlspecialchars($user['tingkat']); ?>">Edit</button>
                                <button class="hapus-btn text-red-600 hover:text-red-900 ml-4"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="tambahPjpModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kelola_ketua_pjp">
                <input type="hidden" name="action" value="tambah_pjp">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Ketua PJP</h3>
                    <div class="space-y-4">
                        <div><label for="nama" class="block text-sm font-medium">Nama Lengkap</label><input type="text" name="nama" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label for="username" class="block text-sm font-medium">Username</label><input type="text" name="username" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label for="password" class="block text-sm font-medium">Password</label><input type="password" name="password" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label for="kelompok" class="block text-sm font-medium">Kelompok</label><select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                <option value="bintaran">Bintaran</option>
                                <option value="gedongkuning">Gedongkuning</option>
                                <option value="jombor">Jombor</option>
                                <option value="sunten">Sunten</option>
                            </select></div>
                        <div><label for="tingkat" class="block text-sm font-medium">Tingkat</label><select name="tingkat" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                <option value="desa">Desa</option>
                                <option value="kelompok">Kelompok</option>
                            </select></div>
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

<!-- Modal Edit -->
<div id="editPjpModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kelola_ketua_pjp">
                <input type="hidden" name="action" value="edit_pjp">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Ketua PJP</h3>
                    <div class="space-y-4">
                        <div><label for="edit_nama" class="block text-sm font-medium">Nama Lengkap</label><input type="text" name="edit_nama" id="edit_nama" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label for="edit_username" class="block text-sm font-medium">Username</label><input type="text" name="edit_username" id="edit_username" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label for="edit_password" class="block text-sm font-medium">Password Baru (Opsional)</label><input type="password" name="edit_password" id="edit_password" placeholder="Kosongkan jika tidak diubah" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="edit_kelompok" class="block text-sm font-medium">Kelompok</label><select name="edit_kelompok" id="edit_kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                <option value="bintaran">Bintaran</option>
                                <option value="gedongkuning">Gedongkuning</option>
                                <option value="jombor">Jombor</option>
                                <option value="sunten">Sunten</option>
                            </select></div>
                        <div><label for="edit_tingkat" class="block text-sm font-medium">Tingkat</label><select name="edit_tingkat" id="edit_tingkat" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                                <option value="desa">Desa</option>
                                <option value="kelompok">Kelompok</option>
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

<!-- Modal Hapus -->
<div id="hapusPjpModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kelola_ketua_pjp">
                <input type="hidden" name="action" value="hapus_pjp">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus pengguna <strong id="hapus_nama"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal QR Code -->
<div id="qrCodeModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-center p-6 overflow-hidden shadow-xl transform transition-all sm:max-w-sm sm:w-full">
            <h3 class="text-lg font-medium text-gray-900">QR Code untuk <span id="qr_nama" class="font-bold"></span></h3>
            <div id="qrcode-container" class="my-4 flex justify-center"></div>
            <a id="download-qr-link" href="#" download="qrcode.png" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Download</a>
            <button type="button" class="modal-close-btn ml-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Tutup</button>
        </div>
    </div>
</div>

<!-- Library untuk generate QR Code -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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
        const modals = document.querySelectorAll('.fixed.z-20');
        const openModalButtons = {
            'tambahPjpBtn': 'tambahPjpModal',
        };
        const tableBody = document.getElementById('pjpTableBody');

        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('hidden');
        }
        const closeModal = (modal) => {
            if (modal) modal.classList.add('hidden');
        }

        for (const [btnId, modalId] of Object.entries(openModalButtons)) {
            const btn = document.getElementById(btnId);
            if (btn) btn.onclick = () => openModal(modalId);
        }

        modals.forEach(modal => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('.modal-close-btn')) {
                    closeModal(modal);
                }
            });
        });

        // --- Dynamic Button Listeners ---
        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                const target = event.target;

                if (target.classList.contains('edit-btn')) {
                    openModal('editPjpModal');
                    document.getElementById('edit_id').value = target.dataset.id;
                    document.getElementById('edit_nama').value = target.dataset.nama;
                    document.getElementById('edit_username').value = target.dataset.username;
                    document.getElementById('edit_kelompok').value = target.dataset.kelompok;
                    document.getElementById('edit_tingkat').value = target.dataset.tingkat;
                    document.getElementById('edit_password').value = '';
                }

                if (target.classList.contains('hapus-btn')) {
                    openModal('hapusPjpModal');
                    document.getElementById('hapus_id').value = target.dataset.id;
                    document.getElementById('hapus_nama').textContent = target.dataset.nama;
                }

                if (target.classList.contains('qr-code-btn')) {
                    openModal('qrCodeModal');
                    const container = document.getElementById('qrcode-container');
                    container.innerHTML = '';
                    document.getElementById('qr_nama').textContent = target.dataset.nama;
                    new QRCode(container, {
                        text: target.dataset.barcode,
                        width: 200,
                        height: 200
                    });
                    setTimeout(() => {
                        const canvas = container.querySelector('canvas');
                        if (canvas) {
                            const downloadLink = document.getElementById('download-qr-link');
                            downloadLink.href = canvas.toDataURL("image/png");
                            downloadLink.download = `qrcode-${target.dataset.nama.replace(/\s+/g, '-')}.png`;
                        }
                    }, 100);
                }
            });
        }

        // --- Re-open modal on POST error ---
        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($_POST['action'] === 'tambah_pjp'): ?>
                openModal('tambahPjpModal');
            <?php elseif ($_POST['action'] === 'edit_pjp'): ?>
                openModal('editPjpModal');
                document.getElementById('edit_id').value = "<?php echo htmlspecialchars($_POST['edit_id'] ?? ''); ?>";
                document.getElementById('edit_nama').value = "<?php echo htmlspecialchars($_POST['edit_nama'] ?? ''); ?>";
                document.getElementById('edit_username').value = "<?php echo htmlspecialchars($_POST['edit_username'] ?? ''); ?>";
                document.getElementById('edit_kelompok').value = "<?php echo htmlspecialchars($_POST['edit_kelompok'] ?? ''); ?>";
                document.getElementById('edit_tingkat').value = "<?php echo htmlspecialchars($_POST['edit_tingkat'] ?? ''); ?>";
            <?php endif; ?>
        <?php endif; ?>
    });
</script>