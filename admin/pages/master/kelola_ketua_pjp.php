<?php
// Variabel $conn sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$success_message = '';
$error_message = '';
$redirect_url = '?page=master/kelola_ketua_pjp'; // Variabel untuk menyimpan URL redirect

function generateRandomPassword($length = 6)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $res = '';
    for ($i = 0; $i < $length; $i++) {
        $res .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $res;
}

// === BAGIAN BACKEND: PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH KETUA PJP ---
    if ($action === 'tambah_pjp') {
        $nama = $_POST['nama'] ?? '';
        $nama_panggilan = $_POST['nama_panggilan'] ?? '';
        // $username = $_POST['username'] ?? '';
        // $password = $_POST['password'] ?? '';
        $kelompok = $_POST['kelompok'] ?? '';
        $tingkat = $_POST['tingkat'] ?? '';

        if (empty($nama) || empty($nama_panggilan) || empty($kelompok) || empty($tingkat)) {
            $err_msg = 'Semua field wajib diisi.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
        }

        if (empty($error_message)) {
            $is_exist = true;
            $username = '';
            while ($is_exist) {
                $username = 'pjp' . rand(1000, 9999) . date('s');
                $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? UNION SELECT id FROM guru WHERE username = ?");
                $stmt_check->bind_param("ss", $username, $username);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows == 0) $is_exist = false;
                $stmt_check->close();
            }

            $plain_password = generateRandomPassword(8);
            $password_hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $role = 'ketua pjp'; // Role spesifik untuk Ketua PJP
            $barcode = 'PJP-' . uniqid(); // Prefix barcode baru
            $sql = "INSERT INTO users (nama, nama_panggilan, kelompok, role, tingkat, barcode, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $nama, $nama_panggilan, $kelompok, $role, $tingkat, $barcode, $username, $password_hashed);
            if ($stmt->execute()) {
                // === CCTV ===
                if ($tingkat == 'desa') {
                    $desc_log = "Menambahkan *Ketua PJP " . ucwords($tingkat) .  "* Baru : *" . ucwords($nama) . "*.";
                } else {
                    $desc_log = "Menambahkan *Ketua PJP " . ucwords($tingkat) .  "* Baru : *" . ucwords($nama) . "* (Kelompok " . ucwords($kelompok) . ").";
                }
                writeLog('INSERT', $desc_log);

                // $redirect_url = '?page=master/kelola_ketua_pjp&status=add_success';
                $swal_notification = "
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil ditambah.',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location = '$redirect_url';
                    });
                ";
            } else {
                // $error_message = 'Gagal menambahkan Ketua PJP.';
                $err_msg = addslashes($stmt->error);
                $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT KETUA PJP ---
    if ($action === 'edit_pjp') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        // $username = $_POST['edit_username'] ?? '';
        // $password = $_POST['edit_password'] ?? '';
        $kelompok = $_POST['edit_kelompok'] ?? '';
        $tingkat = $_POST['edit_tingkat'] ?? '';

        if (empty($nama) || empty($kelompok) || empty($tingkat) || empty($id)) {
            $err_msg = 'Data tidak lengkap untuk proses edit.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
        }

        if (empty($error_message)) {
            // if (!empty($password)) {
            //     $password_hashed = password_hash($password, PASSWORD_DEFAULT);
            //     $sql = "UPDATE users SET nama=?, username=?, kelompok=?, tingkat=?, password=? WHERE id=?";
            //     $stmt = $conn->prepare($sql);
            //     $stmt->bind_param("sssssi", $nama, $username, $kelompok, $tingkat, $password_hashed, $id);
            // } else {
            // }
            $sql = "UPDATE users SET nama=?, kelompok=?, tingkat=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $nama, $kelompok, $tingkat, $id);

            if ($stmt->execute()) {
                // === CCTV ===
                if ($tingkat == 'desa') {
                    $desc_log = "Memperbarui data *Ketua PJP " . ucwords($tingkat) .  "*: *" . ucwords($nama) . "*.";
                } else {
                    $desc_log = "Memperbarui data *Ketua PJP " . ucwords($tingkat) .  "*: *" . ucwords($nama) . "* (Kelompok " . ucwords($kelompok) . ").";
                }
                writeLog('UPDATE', $desc_log);

                // $redirect_url = '?page=master/kelola_ketua_pjp&status=edit_success';
                $swal_notification = "
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil diperbarui.',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location = '$redirect_url';
                    });
                ";
            } else {
                // $error_message = 'Gagal mengedit Ketua PJP.';
                $err_msg = addslashes($stmt->error);
                $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS KETUA PJP ---
    if ($action === 'hapus_pjp') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $err_msg = 'ID Ketua PJP tidak valid.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
        } else {
            $ketuapjp = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'ketua pjp'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // === CCTV ===
                if ($ketuapjp['tingkat'] == 'desa') {
                    $desc_log = "Menghapus data *Ketua PJP " . ucwords($ketuapjp['tingkat']) .  "*: *" . ucwords($ketuapjp['nama']) . "*.";
                } else {
                    $desc_log = "Menghapus data *Ketua PJP " . ucwords($ketuapjp['tingkat']) .  "*: *" . ucwords($ketuapjp['nama']) . "* (Kelompok " . ucwords($ketuapjp['kelompok']) . ").";
                }
                writeLog('DELETE', $desc_log);

                // $redirect_url = '?page=master/kelola_ketua_pjp&status=delete_success';
                $swal_notification = "
                    Swal.fire({
                        title: 'Terhapus!',
                        text: 'Data berhasil dihapus.',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location = '$redirect_url';
                    });
                ";
            } else {
                // $error_message = 'Gagal menghapus Ketua PJP.';
                $err_msg = addslashes($stmt->error);
                $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
// if (isset($_GET['status'])) {
//     if ($_GET['status'] === 'add_success') $success_message = 'Ketua PJP baru berhasil ditambahkan!';
//     if ($_GET['status'] === 'edit_success') $success_message = 'Data Ketua PJP berhasil diperbarui!';
//     if ($_GET['status'] === 'delete_success') $success_message = 'Data Ketua PJP berhasil dihapus!';
// }

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
    <!-- <?php if (!empty($success_message)): ?>
        <div id="success-alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $success_message; ?></span></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div id="error-alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $error_message; ?></span></div>
    <?php endif; ?> -->

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">QR Code</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
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
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <button class="qr-code-btn text-blue-500 hover:text-blue-700"
                                    data-barcode="<?php echo htmlspecialchars($user['barcode']); ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>"
                                    data-tingkat="<?php echo htmlspecialchars($user['tingkat']); ?>"
                                    data-kelompok="<?php echo htmlspecialchars(ucwords($user['kelompok'])); ?>">Lihat</button>
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap text-sm font-medium">
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-kelompok="<?php echo htmlspecialchars($user['kelompok']); ?>"
                                    data-tingkat="<?php echo htmlspecialchars($user['tingkat']); ?>">Edit</button>
                                <button class="hapus-btn text-red-600 hover:text-red-900 ml-4"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>">Hapus</button>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
                                    <button class="reset-pin-btn text-yellow-600 hover:text-yellow-800 ml-4"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-nama="<?php echo htmlspecialchars($user['nama']); ?>"
                                        data-barcode="<?php echo htmlspecialchars($user['barcode']); ?>"
                                        data-role="ketua pjp"> <!-- Target Role diset Guru -->
                                        <i class="fa-solid fa-key"></i> Reset PIN
                                    </button>
                                <?php endif; ?>
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
                        <div>
                            <label for="nama" class="block text-sm font-medium">Nama Lengkap</label>
                            <input type="text" name="nama" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                        </div>
                        <div>
                            <label for="nama_panggilan" class="block text-sm font-medium">Nama Panggilan</label>
                            <input type="text" name="nama_panggilan" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                        </div>
                        <!-- <div>
                            <label for="username" class="block text-sm font-medium">Username</label>
                            <input type="text" name="username" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                        <div><label for="password" class="block text-sm font-medium">Password</label><input type="password" name="password" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div> -->

                        <!-- Info Login Hidden -->
                        <div class="text-xs text-gray-500 italic bg-gray-50 p-2 rounded">
                            <span class="font-semibold text-gray-700">Catatan:</span> Akun login dan barcode akan digenerate otomatis oleh sistem.
                        </div>
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
                        <div><label for="edit_nama" class="block text-sm font-medium">Nama Lengkap</label><input type="text" name="edit_nama" id="edit_nama" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" required></div>
                        <!-- <div><label for="edit_username" class="block text-sm font-medium">Username</label><input type="text" name="edit_username" id="edit_username" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label for="edit_password" class="block text-sm font-medium">Password Baru (Opsional)</label><input type="password" name="edit_password" id="edit_password" placeholder="Kosongkan jika tidak diubah" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div> -->

                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Username (System Generated)</label>
                            <input type="text" id="view_username" class="w-full border border-gray-200 bg-gray-100 text-gray-500 p-2 rounded cursor-not-allowed font-mono text-sm" disabled>
                        </div>
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

<!-- MODAL RESET PIN -->
<div id="resetPinModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity modal-backdrop"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-sm z-50">
            <div class="bg-yellow-50 px-4 pt-5 pb-4 sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fa-solid fa-shield-halved text-yellow-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Reset PIN Pengguna</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 mb-4">
                                Anda akan mereset PIN untuk <strong id="reset_target_nama">User</strong> menjadi default.
                            </p>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Verifikasi PIN Superadmin:</label>
                            <input type="password" id="superadmin_pin" class="shadow-sm focus:ring-yellow-500 focus:border-yellow-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border" placeholder="Masukkan PIN Anda" inputmode="numeric">
                            <p id="reset_error_msg" class="text-red-600 text-xs mt-1 hidden"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="btnConfirmReset" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Reset PIN
                </button>
                <button type="button" onclick="closeResetPinModal()" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Library untuk generate QR Code -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- JavaScript Redirect ---
        // <?php if (!empty($redirect_url)): ?>
        //     window.location.href = '<?php echo $redirect_url; ?>';
        // <?php endif; ?>

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
                    document.getElementById('view_username').value = target.dataset.username;
                    document.getElementById('edit_kelompok').value = target.dataset.kelompok;
                    document.getElementById('edit_tingkat').value = target.dataset.tingkat;
                    // document.getElementById('edit_password').value = '';
                }

                if (target.classList.contains('hapus-btn')) {
                    openModal('hapusPjpModal');
                    document.getElementById('hapus_id').value = target.dataset.id;
                    document.getElementById('hapus_nama').textContent = target.dataset.nama;
                }

                if (target.classList.contains('qr-code-btn')) {
                    openModal('qrCodeModal');
                    const container = document.getElementById('qrcode-container');
                    const downloadLink = document.getElementById('download-qr-link');
                    container.innerHTML = '';
                    document.getElementById('qr_nama').textContent = target.dataset.nama;

                    const namaPemilik = target.dataset.nama;
                    const tingkatPemilik = target.dataset.tingkat;
                    const kelompokPemilik = target.dataset.kelompok;

                    if (downloadLink) {
                        downloadLink.addEventListener('click', function() {

                            const formData = new FormData();
                            formData.append('log_type', 'EXPORT');

                            let pesanDinamis;
                            if (tingkatPemilik == 'desa') {
                                pesanDinamis = `Mendownload file QR Code *${namaPemilik}* - Ketua PJP Desa.`;
                            } else {
                                pesanDinamis = `Mendownload file QR Code *${namaPemilik}* - Ketua PJP Kelompok ${kelompokPemilik}.`;
                            }

                            formData.append('message', pesanDinamis);
                            fetch('helpers/ajax_writeLog.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        console.log("Log download berhasil dicatat.");
                                    } else {
                                        console.warn("Gagal mencatat log:", data.message);
                                    }
                                })
                                .catch(err => console.error("Error fetch:", err));

                            // Browser akan melanjutkan proses download secara otomatis
                        });
                    }

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

        // --- TAMBAHAN: LOGIKA MODAL & AJAX RESET PIN ---
        const resetModal = document.getElementById('resetPinModal');
        const btnConfirmReset = document.getElementById('btnConfirmReset');
        const superadminPinInput = document.getElementById('superadmin_pin');
        const errorMsg = document.getElementById('reset_error_msg');

        let targetUserId = null;
        let targetUserBarcode = null;
        let targetUserRole = 'guru'; // Default

        // Fungsi Buka Modal Reset
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.reset-pin-btn');
            if (btn) {
                targetUserId = btn.dataset.id;
                targetUserBarcode = btn.dataset.barcode;
                targetUserRole = btn.dataset.role; // Bisa 'guru' atau 'users'
                document.getElementById('reset_target_nama').textContent = btn.dataset.nama;

                superadminPinInput.value = ''; // Reset input
                errorMsg.classList.add('hidden'); // Sembunyikan error lama
                resetModal.classList.remove('hidden');

                // Fokus ke input pin
                setTimeout(() => superadminPinInput.focus(), 100);
            }
        });

        // Fungsi Kirim AJAX saat tombol diklik
        btnConfirmReset.addEventListener('click', function() {
            const adminPin = superadminPinInput.value;

            if (!adminPin) {
                errorMsg.textContent = "PIN Superadmin wajib diisi.";
                errorMsg.classList.remove('hidden');
                return;
            }

            // Tampilkan Loading state pada tombol
            const originalBtnText = btnConfirmReset.innerText;
            btnConfirmReset.disabled = true;
            btnConfirmReset.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

            // Kirim Fetch Request
            fetch('pages/master/ajax_reset_pin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'target_id': targetUserId,
                        'target_barcode': targetUserBarcode,
                        'target_role': targetUserRole, // Dinamis: guru/users
                        'admin_pin': adminPin
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // alert('Berhasil! ' + data.message);
                        Swal.fire({
                            title: 'Berhasil!',
                            text: data.message,
                            icon: 'success',
                            timer: 2000, // Otomatis tutup dalam 2 detik (opsional)
                            showConfirmButton: false // Hilangkan tombol OK jika pakai timer
                        });
                        resetModal.classList.add('hidden');
                    } else {
                        // errorMsg.textContent = data.message;
                        Swal.fire({
                            title: 'Gagal!',
                            text: data.message,
                            icon: 'error',
                            timer: 2000, // Otomatis tutup dalam 2 detik (opsional)
                            showConfirmButton: false // Hilangkan tombol OK jika pakai timer
                        });
                        errorMsg.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    errorMsg.textContent = "Terjadi kesalahan server.";
                    errorMsg.classList.remove('hidden');
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Kembalikan tombol ke semula
                    btnConfirmReset.disabled = false;
                    btnConfirmReset.innerText = originalBtnText;
                });
        });
    });

    function closeResetPinModal() {
        // 1. Ambil elemen modal
        const modal = document.getElementById('resetPinModal');

        // 2. Sembunyikan modal (tambah class hidden)
        modal.classList.add('hidden');
    }

    // Opsional: Tutup modal dengan tombol ESC keyboard
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeResetPinModal();
        }
    });
</script>