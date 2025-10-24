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

// Ambil filter dari URL, sediakan nilai default 'semua'
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

// Jika admin tingkat kelompok, paksa filter kelompok
if ($admin_tingkat === 'kelompok') {
    $filter_kelompok = $admin_kelompok;
}

$redirect_url_base = '?page=master/kelola_guru&kelompok=' . urlencode($filter_kelompok) . '&kelas=' . urlencode($filter_kelas);

// === PROSES POST REQUEST (CRUD) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_guru') {
        $nama = $_POST['nama'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $kelas = $_POST['kelas'] ?? '';
        $nomor_wa = $_POST['nomor_wa'] ?? '';
        $tingkat = 'kelompok';
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['kelompok'] ?? '');

        if (empty($nama) || empty($username) || empty($password) || empty($kelompok) || empty($kelas)) {
            $error_message = 'Semua field wajib diisi.';
        } else {
            // Cek duplikasi username di tabel guru dan users
            $stmt_check = $conn->prepare("SELECT id FROM guru WHERE username = ? UNION SELECT id FROM users WHERE username = ?");
            $stmt_check->bind_param("ss", $username, $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $error_message = 'Username sudah digunakan.';
            }
            $stmt_check->close();
        }

        if (empty($error_message)) {
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
            $barcode = 'GRU-' . uniqid();
            $sql = "INSERT INTO guru (nama, kelompok, kelas, tingkat, barcode, username, password, nomor_wa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $nama, $kelompok, $kelas, $tingkat, $barcode, $username, $password_hashed, $nomor_wa);
            if ($stmt->execute()) {
                $redirect_url = $redirect_url_base . '&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan Guru.';
            }
            $stmt->close();
        }
    }

    if ($action === 'edit_guru') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        $username = $_POST['edit_username'] ?? '';
        $password = $_POST['edit_password'] ?? '';
        $kelas = $_POST['edit_kelas'] ?? '';
        $nomor_wa = $_POST['edit_nomor_wa'] ?? '';
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['edit_kelompok'] ?? '');

        if (empty($nama) || empty($username) || empty($kelompok) || empty($id) || empty($kelas)) {
            $error_message = 'Data tidak lengkap untuk proses edit.';
        } else {
            $stmt_check = $conn->prepare("(SELECT id FROM guru WHERE username = ? AND id != ?) UNION (SELECT id FROM users WHERE username = ?)");
            $stmt_check->bind_param("sis", $username, $id, $username);
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
                $sql = "UPDATE guru SET nama=?, username=?, kelompok=?, kelas=?, nomor_wa=?, password=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $nama, $username, $kelompok, $kelas, $nomor_wa, $password_hashed, $id);
            } else {
                $sql = "UPDATE guru SET nama=?, username=?, kelompok=?, kelas=?, nomor_wa=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $nama, $username, $kelompok, $kelas, $nomor_wa, $id);
            }

            if ($stmt->execute()) {
                $redirect_url = $redirect_url_base . '&status=edit_success';
            } else {
                $error_message = 'Gagal mengedit Guru.';
            }
            $stmt->close();
        }
    }

    if ($action === 'hapus_guru') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID Guru tidak valid.';
        } else {
            $sql = "DELETE FROM guru WHERE id = ?";
            if ($admin_tingkat === 'kelompok') {
                $sql .= " AND kelompok = ?";
            }
            $stmt = $conn->prepare($sql);
            if ($admin_tingkat === 'kelompok') {
                $stmt->bind_param("is", $id, $admin_kelompok);
            } else {
                $stmt->bind_param("i", $id);
            }
            if ($stmt->execute()) {
                $redirect_url = $redirect_url_base . '&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus Guru.';
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Guru baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Data Guru berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Data Guru berhasil dihapus!';
}

// === AMBIL DATA GURU BERDASARKAN FILTER ===
$guru_list = [];
$sql = "SELECT * FROM guru";
$params = [];
$types = "";
$where_conditions = [];

if ($filter_kelompok !== 'semua') {
    $where_conditions[] = "kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= "s";
}
if ($filter_kelas !== 'semua') {
    $where_conditions[] = "kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " ORDER BY kelompok ASC, FIELD(kelas, 'paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'), nama ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $guru_list[] = $row;
    }
}
$stmt->close();
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Guru</h3>
        <button id="tambahGuruBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Tambah Guru
        </button>
    </div>

    <!-- BAGIAN FILTER BARU -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="master/kelola_guru">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Filter Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md border-gray-300" disabled>
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                            <option value="semua">Semua Kelompok</option>
                            <option value="bintaran" <?php echo ($filter_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($filter_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($filter_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($filter_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium">Filter Kelas</label>
                    <select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md">
                        <option value="semua">Semua Kelas</option>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                        <?php foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($filter_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Notifikasi -->
    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $success_message; ?></span></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $error_message; ?></span></div><?php endif; ?>

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                    <?php if ($admin_tingkat === 'desa'): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor WA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kartu Akses</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="guruTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($guru_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">Tidak ada data guru yang cocok dengan filter.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($guru_list as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['nama']); ?></div>
                                <div class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($user['kelompok']); ?></div>
                            </td>
                            <?php if ($admin_tingkat === 'desa'): ?>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['username']); ?></td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap capitalize font-semibold"><?php echo htmlspecialchars($user['kelas'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['nomor_wa'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <!-- <button class="qr-code-btn text-blue-500 hover:text-blue-700"
                                    data-barcode="<?php echo htmlspecialchars($user['barcode']); ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>">Lihat & Download
                                </button> -->
                                <!-- TOMBOL BARU UNTUK MENCETAK KARTU -->
                                <a href="actions/cetak_kartu.php?guru_id=<?php echo $user['id']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700">
                                    Cetak Kartu
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($user['nama']); ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-kelompok="<?php echo htmlspecialchars($user['kelompok']); ?>"
                                    data-kelas="<?php echo htmlspecialchars($user['kelas'] ?? ''); ?>"
                                    data-nomor_wa="<?php echo htmlspecialchars($user['nomor_wa'] ?? ''); ?>">Edit</button>
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
<div id="tambahGuruModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="tambah_guru">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Tambah Guru</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Lengkap (Maks. 24 Char)</label><input type="text" name="nama" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Username</label><input type="text" name="username" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Password</label><input type="password" name="password" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Nomor WA</label><input type="text" name="nomor_wa" placeholder="Contoh: 628123456789" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium">Kelompok</label>
                            <?php if ($admin_tingkat === 'kelompok'): ?>
                                <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                                <input type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                            <?php else: ?>
                                <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div><label class="block text-sm font-medium">Kelas</label><select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Kelas --</option>
                                <option value="paud">PAUD</option>
                                <option value="caberawit a">Caberawit A</option>
                                <option value="caberawit b">Caberawit B</option>
                                <option value="pra remaja">Pra Remaja</option>
                                <option value="remaja">Remaja</option>
                                <option value="pra nikah">Pra Nikah</option>
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
<div id="editGuruModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="edit_guru">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Form Edit Guru</h3>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nama Lengkap (Maks. 24 Char)</label><input type="text" name="edit_nama" id="edit_nama" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Username</label><input type="text" name="edit_username" id="edit_username" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        <div><label class="block text-sm font-medium">Password Baru (Opsional)</label><input type="password" name="edit_password" id="edit_password" placeholder="Kosongkan jika tidak diubah" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium">Nomor WA</label><input type="text" name="edit_nomor_wa" id="edit_nomor_wa" placeholder="Contoh: 628123456789" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium">Kelompok</label>
                            <?php if ($admin_tingkat === 'kelompok'): ?>
                                <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                                <input type="hidden" name="edit_kelompok" id="edit_kelompok" value="<?php echo $admin_kelompok; ?>">
                            <?php else: ?>
                                <select name="edit_kelompok" id="edit_kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div><label class="block text-sm font-medium">Kelas</label><select name="edit_kelas" id="edit_kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                                <option value="">-- Pilih Kelas --</option>
                                <option value="paud">PAUD</option>
                                <option value="caberawit a">Caberawit A</option>
                                <option value="caberawit b">Caberawit B</option>
                                <option value="pra remaja">Pra Remaja</option>
                                <option value="remaja">Remaja</option>
                                <option value="pra nikah">Pra Nikah</option>
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
<div id="hapusGuruModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="hapus_guru">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus pengguna <strong id="hapus_nama"></strong>?</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal QR Code BARU -->
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
            tambah: document.getElementById('tambahGuruModal'),
            edit: document.getElementById('editGuruModal'),
            hapus: document.getElementById('hapusGuruModal'),
            qr: document.getElementById('qrCodeModal') // Tambahkan modal QR
        };
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');
        document.getElementById('tambahGuruBtn').onclick = () => openModal(modals.tambah);
        Object.values(modals).forEach(modal => {
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
            });
        });

        document.getElementById('guruTableBody').addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.classList.contains('edit-btn')) {
                document.getElementById('edit_id').value = target.dataset.id;
                document.getElementById('edit_nama').value = target.dataset.nama;
                document.getElementById('edit_username').value = target.dataset.username;
                document.getElementById('edit_kelompok').value = target.dataset.kelompok;
                document.getElementById('edit_kelas').value = target.dataset.kelas;
                document.getElementById('edit_nomor_wa').value = target.dataset.nomor_wa;
                document.getElementById('edit_password').value = '';
                openModal(modals.edit);
            }
            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_nama').textContent = target.dataset.nama;
                openModal(modals.hapus);
            }

            // LOGIKA BARU UNTUK TOMBOL QR
            if (target.classList.contains('qr-code-btn')) {
                const container = document.getElementById('qrcode-container');
                const downloadLink = document.getElementById('download-qr-link');

                container.innerHTML = ''; // Kosongkan QR lama
                document.getElementById('qr_nama').textContent = target.dataset.nama;

                new QRCode(container, {
                    text: target.dataset.barcode,
                    width: 200,
                    height: 200,
                });

                // Beri jeda agar canvas sempat tergambar sebelum membuat link download
                setTimeout(() => {
                    const canvas = container.querySelector('canvas');
                    if (canvas) {
                        downloadLink.href = canvas.toDataURL("image/png");
                        downloadLink.download = `qrcode-${target.dataset.nama.replace(/\s+/g, '-')}.png`;
                    }
                }, 100);

                openModal(modals.qr);
            }
        });

        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($_POST['action'] === 'tambah_guru'): ?> openModal(modals.tambah);
            <?php elseif ($_POST['action'] === 'edit_guru'): ?> openModal(modals.edit);
            <?php endif; ?>
        <?php endif; ?>
    });
</script>