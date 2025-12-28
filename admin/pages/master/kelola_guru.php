<?php
// Pastikan variabel $conn tersedia
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

$success_message = '';
$error_message = '';
$redirect_url = '';

// Ambil filter dari URL
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($admin_tingkat === 'kelompok') {
    $filter_kelompok = $admin_kelompok;
}

// Base URL
$redirect_url_base = '?page=master/kelola_guru&kelompok=' . urlencode($filter_kelompok) . '&kelas=' . urlencode($filter_kelas);

// Daftar Opsi Kelas
$list_opsi_kelas = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];

// Fungsi Helper: Generate Password Acak
function generateRandomPassword($length = 6)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $res = '';
    for ($i = 0; $i < $length; $i++) {
        $res .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $res;
}

// === PROSES POST REQUEST (CRUD) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- 1. TAMBAH GURU ---
    if ($action === 'tambah_guru') {
        $nama = $_POST['nama'] ?? '';
        $nomor_wa = $_POST['nomor_wa'] ?? '';
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['kelompok'] ?? '');
        $kelas_array = $_POST['kelas'] ?? [];
        $tingkat = 'kelompok';

        if (empty($nama) || empty($kelompok) || empty($kelas_array)) {
            $err_msg = 'Nama, Kelompok, dan minimal 1 Kelas wajib diisi.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
        } else {
            // A. Auto-Generate Username Unik
            $is_exist = true;
            $username = '';
            while ($is_exist) {
                $username = 'guru' . rand(1000, 9999) . date('s');
                $stmt_check = $conn->prepare("SELECT id FROM guru WHERE username = ? UNION SELECT id FROM users WHERE username = ?");
                $stmt_check->bind_param("ss", $username, $username);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows == 0) $is_exist = false;
                $stmt_check->close();
            }

            // B. Auto-Generate Password
            $plain_password = generateRandomPassword(8);
            $password_hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $barcode = 'GRU-' . uniqid();

            // C. Insert Guru
            $sql = "INSERT INTO guru (nama, kelompok, kelas, tingkat, barcode, username, password, nomor_wa) VALUES (?, ?, '', ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $nama, $kelompok, $tingkat, $barcode, $username, $password_hashed, $nomor_wa);

            if ($stmt->execute()) {
                $id_guru_baru = $conn->insert_id;

                // D. Insert Pengampu & Siapkan String Log
                $sql_pengampu = "INSERT INTO pengampu (id_guru, nama_kelas) VALUES (?, ?)";
                $stmt_p = $conn->prepare($sql_pengampu);
                foreach ($kelas_array as $k) {
                    $stmt_p->bind_param("is", $id_guru_baru, $k);
                    $stmt_p->execute();
                }
                $stmt_p->close();

                // Format Log: "Kelas: Paud, Remaja"
                $kelas_string = implode(', ', array_map('ucfirst', $kelas_array));
                writeLog('INSERT', "Menambah Guru ($kelompok): $nama. Kelas: $kelas_string.");

                // Set Notifikasi Sukses Edit
                $swal_notification = "
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data Guru berhasil ditambahkan.',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location = '$redirect_url_base';
                    });
                ";

                // $redirect_url = $redirect_url_base . '&status=add_success';
            } else {
                // $error_message = 'Gagal database: ' . $conn->error;
                // Set Notifikasi Gagal
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

    // --- 2. EDIT GURU ---
    if ($action === 'edit_guru') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        $nomor_wa = $_POST['edit_nomor_wa'] ?? '';
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['edit_kelompok'] ?? '');
        $kelas_array = $_POST['edit_kelas'] ?? [];

        if (empty($nama) || empty($kelompok) || empty($id) || empty($kelas_array)) {
            $err_msg = 'Data tidak lengkap. Minimal pilih 1 kelas.';
            $swal_notification = "
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: $err_msg',
                        icon: 'error'
                    });
                ";
        } else {
            // Update Data Dasar
            $stmt = $conn->prepare("UPDATE guru SET nama=?, kelompok=?, nomor_wa=? WHERE id=?");
            $stmt->bind_param("sssi", $nama, $kelompok, $nomor_wa, $id);

            if ($stmt->execute()) {
                // Reset & Insert Ulang Pengampu
                $conn->query("DELETE FROM pengampu WHERE id_guru = $id");

                $stmt_ins = $conn->prepare("INSERT INTO pengampu (id_guru, nama_kelas) VALUES (?, ?)");
                foreach ($kelas_array as $k) {
                    $stmt_ins->bind_param("is", $id, $k);
                    $stmt_ins->execute();
                }
                $stmt_ins->close();

                // Log Perubahan
                $kelas_string = implode(', ', array_map('ucfirst', $kelas_array));
                writeLog('UPDATE', "Update Guru ID $id ($nama). Mengajar: $kelas_string.");

                // Set Notifikasi Sukses Edit
                $swal_notification = "
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data Guru berhasil diperbarui.',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location = '$redirect_url_base';
                    });
                ";

                // $redirect_url = $redirect_url_base . '&status=edit_success';
            } else {
                // $error_message = 'Gagal update database.';
                // Set Notifikasi Gagal
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

    // --- 3. HAPUS GURU (SOFT DELETE) ---
    if ($action === 'hapus_guru') {
        $id = $_POST['hapus_id'] ?? 0;

        // Soft Delete: Isi kolom deleted_at
        $stmt = $conn->prepare("UPDATE guru SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Ambil nama untuk log
            $nama_guru = $_POST['hapus_nama_log'] ?? 'ID ' . $id;
            writeLog('DELETE', "Menghapus (Soft) Guru: $nama_guru");

            // Set Notifikasi Sukses Hapus
            $swal_notification = "
                Swal.fire({
                    title: 'Terhapus!',
                    text: 'Data Guru berhasil dihapus.',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    window.location = '$redirect_url_base';
                });
            ";
            // $redirect_url = $redirect_url_base . '&status=delete_success';
        } else {
            // $error_message = 'Gagal menghapus data.';
            // Set Notifikasi Gagal
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

// === QUERY DATA ===
$sql = "
    SELECT g.*, 
           GROUP_CONCAT(p.nama_kelas SEPARATOR ', ') as list_kelas,
           GROUP_CONCAT(p.nama_kelas) as raw_kelas 
    FROM guru g
    LEFT JOIN pengampu p ON g.id = p.id_guru
";
$where_conditions = [];
$params = [];
$types = "";

// 1. Filter Soft Delete (Hanya tampilkan yang belum dihapus)
$where_conditions[] = "g.deleted_at IS NULL";

// 2. Filter Kelompok
if ($filter_kelompok !== 'semua') {
    $where_conditions[] = "g.kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= "s";
}

// 3. Filter Kelas
if ($filter_kelas !== 'semua') {
    $where_conditions[] = "p.nama_kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " GROUP BY g.id ORDER BY g.kelompok ASC, g.nama ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$guru_list = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $guru_list[] = $row;
$stmt->close();
?>

<div class="container mx-auto relative">

    <!-- Header & Filter -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Guru & Akses Kelas</h3>
        <button id="tambahGuruBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 shadow transition transform hover:scale-105">
            + Tambah Guru
        </button>
    </div>

    <!-- Filter Section -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="master/kelola_guru">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Filter Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?= ucfirst($admin_kelompok) ?>" class="mt-1 w-full bg-gray-100 p-2 rounded border" disabled>
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 w-full p-2 border rounded focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="semua">Semua</option>
                            <option value="bintaran" <?= $filter_kelompok == 'bintaran' ? 'selected' : '' ?>>Bintaran</option>
                            <option value="gedongkuning" <?= $filter_kelompok == 'gedongkuning' ? 'selected' : '' ?>>Gedongkuning</option>
                            <option value="jombor" <?= $filter_kelompok == 'jombor' ? 'selected' : '' ?>>Jombor</option>
                            <option value="sunten" <?= $filter_kelompok == 'sunten' ? 'selected' : '' ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Filter Kelas</label>
                    <select name="kelas" class="mt-1 w-full p-2 border rounded focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="semua">Semua</option>
                        <?php foreach ($list_opsi_kelas as $k): ?>
                            <option value="<?= $k ?>" <?= $filter_kelas == $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded hover:bg-indigo-700 shadow transition">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama / Kelompok</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Kelas Diampu</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Kartu Akses</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody id="guruTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($guru_list)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">Tidak ada data.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($guru_list as $g): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm"><?= $i++ ?></td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900"><?= htmlspecialchars($g['nama']) ?></div>
                                <div class="text-xs text-gray-500 uppercase"><?= htmlspecialchars($g['kelompok']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php
                                $arrK = explode(',', $g['raw_kelas'] ?? '');
                                foreach ($arrK as $kls) {
                                    if (trim($kls)) echo '<span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded-full mr-1 mb-1 border border-blue-200 capitalize">' . htmlspecialchars(trim($kls)) . '</span>';
                                }
                                if (empty($g['raw_kelas'])) echo '<span class="text-red-400 italic text-xs">Belum ada kelas</span>';
                                ?>
                            </td>
                            <!-- <td class="px-6 py-4">
                                <a href="actions/cetak_kartu.php?guru_id=<?php echo $g['id']; ?>" onclick="downloadLaporan()" target="_blank" class="text-blue-500 hover:text-blue-700">
                                    Cetak Kartu
                                </a>
                            </td> -->
                            <td class="px-6 py-4 text-center text-sm">
                                <a href="actions/cetak_kartu.php?guru_id=<?= $g['id'] ?>"
                                    class="cetak-kartu-btn inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium"
                                    data-nama="<?= htmlspecialchars($g['nama']) ?>">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                    Cetak Kartu
                                </a>
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap text-sm font-medium">
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900 mr-3"
                                    data-id="<?= $g['id'] ?>"
                                    data-nama="<?= htmlspecialchars($g['nama']) ?>"
                                    data-username="<?= htmlspecialchars($g['username']) ?>"
                                    data-kelompok="<?= htmlspecialchars($g['kelompok']) ?>"
                                    data-nomor_wa="<?= htmlspecialchars($g['nomor_wa'] ?? '') ?>"
                                    data-kelas-list="<?= htmlspecialchars($g['raw_kelas'] ?? '') ?>">Edit</button>
                                <button class="hapus-btn text-red-600 hover:text-red-900 mr-3"
                                    data-id="<?= $g['id'] ?>"
                                    data-nama="<?= htmlspecialchars($g['nama']) ?>">Hapus</button>
                                <!-- TAMBAHAN: Tombol Reset PIN (Khusus Superadmin) -->
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
                                    <button class="reset-pin-btn text-yellow-600 hover:text-yellow-800"
                                        data-id="<?php echo $g['id']; ?>"
                                        data-nama="<?php echo htmlspecialchars($g['nama']); ?>"
                                        data-barcode="<?php echo htmlspecialchars($g['barcode']); ?>"
                                        data-role="guru"> <!-- Target Role diset Guru -->
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

<!-- ================= MODALS ================= -->

<!-- MODAL TAMBAH (User/Pass disembunyikan) -->
<div id="tambahGuruModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form method="POST" action="<?= $redirect_url_base ?>">
                    <input type="hidden" name="action" value="tambah_guru">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Tambah Guru Baru</h3>
                        <div class="space-y-4">
                            <!-- Nama -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                                <input type="text" name="nama" placeholder="Contoh: Budi Santoso" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                            </div>

                            <!-- WA -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nomor WA</label>
                                <input type="text" name="nomor_wa" placeholder="628..." class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none">
                            </div>

                            <!-- Info Login Hidden -->
                            <div class="text-xs text-gray-500 italic bg-gray-50 p-2 rounded">
                                <span class="font-semibold text-gray-700">Catatan:</span> Akun login dan barcode akan digenerate otomatis oleh sistem.
                            </div>

                            <!-- Kelompok -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Kelompok</label>
                                <?php if ($admin_tingkat === 'kelompok'): ?>
                                    <input type="hidden" name="kelompok" value="<?= $admin_kelompok ?>">
                                    <input type="text" value="<?= ucfirst($admin_kelompok) ?>" class="mt-1 w-full bg-gray-100 border p-2 rounded text-gray-500" disabled>
                                <?php else: ?>
                                    <select name="kelompok" class="mt-1 w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-green-500 outline-none" required>
                                        <option value="bintaran">Bintaran</option>
                                        <option value="gedongkuning">Gedongkuning</option>
                                        <option value="jombor">Jombor</option>
                                        <option value="sunten">Sunten</option>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Checkbox Kelas -->
                            <div class="border p-3 rounded bg-gray-50">
                                <label class="block text-sm font-medium mb-2 text-gray-700">Mengajar Kelas:</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($list_opsi_kelas as $k): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="kelas[]" value="<?= $k ?>" class="rounded text-green-600 focus:ring-green-500 h-4 w-4 border-gray-300">
                                            <span class="capitalize text-sm"><?= $k ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 sm:ml-3 sm:w-auto">Simpan</button>
                        <button type="button" class="modal-close-btn mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div id="editGuruModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form method="POST" action="<?= $redirect_url_base ?>">
                    <input type="hidden" name="action" value="edit_guru">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Edit Data Guru</h3>
                        <div class="space-y-4">
                            <!-- Nama -->
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Nama</label>
                                <input type="text" name="edit_nama" id="edit_nama" class="w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none" required>
                            </div>

                            <!-- Username Readonly -->
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Username (System Generated)</label>
                                <input type="text" id="view_username" class="w-full border border-gray-200 bg-gray-100 text-gray-500 p-2 rounded cursor-not-allowed font-mono text-sm" disabled>
                            </div>

                            <!-- WA -->
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Nomor WA</label>
                                <input type="text" name="edit_nomor_wa" id="edit_nomor_wa" class="w-full border border-gray-300 p-2 rounded focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>

                            <!-- Kelompok -->
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase">Kelompok</label>
                                <?php if ($admin_tingkat === 'kelompok'): ?>
                                    <input type="hidden" name="edit_kelompok" id="edit_kelompok" value="<?= $admin_kelompok ?>">
                                    <input type="text" value="<?= ucfirst($admin_kelompok) ?>" class="w-full bg-gray-100 border p-2 rounded" disabled>
                                <?php else: ?>
                                    <select name="edit_kelompok" id="edit_kelompok" class="w-full border border-gray-300 p-2 rounded" required>
                                        <option value="bintaran">Bintaran</option>
                                        <option value="gedongkuning">Gedongkuning</option>
                                        <option value="jombor">Jombor</option>
                                        <option value="sunten">Sunten</option>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Kelas -->
                            <div class="border p-3 rounded bg-gray-50">
                                <label class="block text-sm font-medium mb-2 text-gray-700">Mengajar Kelas:</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($list_opsi_kelas as $k): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="edit_kelas[]" value="<?= $k ?>" class="edit-kelas-checkbox rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                                            <span class="capitalize text-sm"><?= $k ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto">Update</button>
                        <button type="button" class="modal-close-btn mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HAPUS (SOFT DELETE) -->
<div id="hapusGuruModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
    <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form method="POST" action="<?= $redirect_url_base ?>">
                    <input type="hidden" name="action" value="hapus_guru">
                    <input type="hidden" name="hapus_id" id="hapus_id">
                    <input type="hidden" name="hapus_nama_log" id="hapus_nama_log">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Hapus Guru</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Anda yakin ingin menghapus <strong id="hapus_nama" class="text-gray-900"></strong>?<br>Data ini akan diarsipkan (Soft Delete).</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">Ya, Hapus</button>
                        <button type="button" class="modal-close-btn mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
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
                <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- LOADER CETAK KARTU (Sesuai Request) -->
<div id="downloadLoader" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center">
        <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-gray-800">Sedang Mencetak Kartu Akses...</h3>
        <p class="text-sm text-gray-500">Mohon tunggu, Kartu Akses sedang disiapkan.</p>
    </div>
</div>

<!-- QR Code Library -->
<!-- <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script> -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Redirect Logic (Clean GET params)
        <?php if (!empty($redirect_url)): ?>
            window.location.href = '<?= $redirect_url ?>';
        <?php endif; ?>

        // Auto Hide Alert
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 3000);

        // Modal Handlers
        const modals = {
            tambah: document.getElementById('tambahGuruModal'),
            edit: document.getElementById('editGuruModal'),
            hapus: document.getElementById('hapusGuruModal'),
            reset: document.getElementById('resetPinModal')
        };
        const openModal = (m) => m && m.classList.remove('hidden');
        const closeModal = (m) => m && m.classList.add('hidden');

        // Button Tambah
        const btnTambah = document.getElementById('tambahGuruBtn');
        if (btnTambah) btnTambah.onclick = () => openModal(modals.tambah);

        // Backdrop Actions
        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.modal-close-btn') || e.target.classList.contains('modal-backdrop')) {
                Object.values(modals).forEach(closeModal);
            }
        });

        // -------------------------------------------------------------
        // LOGIKA BARU: CETAK KARTU DENGAN LOADER & AJAX (FETCH)
        // -------------------------------------------------------------
        document.body.addEventListener('click', function(e) {
            // Cek apakah yang diklik adalah tombol Cetak Kartu
            const btnCetak = e.target.closest('.cetak-kartu-btn');

            if (btnCetak) {
                e.preventDefault(); // Mencegah pindah halaman

                const url = btnCetak.getAttribute('href');
                const namaGuru = btnCetak.dataset.nama || 'Guru';
                const loader = document.getElementById('downloadLoader');

                // 1. Tampilkan Loader
                loader.classList.remove('hidden');

                // 2. Fetch File di Background
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Gagal mengambil data kartu akses.');
                        }
                        return response.blob(); // Ubah response jadi Blob (File)
                    })
                    .then(blob => {
                        // 3. Buat Link Download Sementara
                        const downloadUrl = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = downloadUrl;

                        // Nama file saat didownload
                        a.download = `Kartu_Akses_${namaGuru.replace(/\s+/g, '_')}.png`; // Asumsi output PDF, sesuaikan jika image

                        document.body.appendChild(a);
                        a.click(); // Trigger download
                        a.remove(); // Hapus link sementara
                        window.URL.revokeObjectURL(downloadUrl); // Bersihkan memori
                    })
                    .catch(error => {
                        alert('Terjadi kesalahan: ' + error.message);
                    })
                    .finally(() => {
                        // 4. Sembunyikan Loader
                        loader.classList.add('hidden');
                    });

                return; // Stop eksekusi event listener lain
            }

            // --- Logic Table Actions Lainnya (Edit/Hapus) ---
            const btn = e.target.closest('button');
            if (!btn) return;
            // EDIT Logic
            if (btn.classList.contains('edit-btn')) {
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_nama').value = btn.dataset.nama;
                document.getElementById('edit_nomor_wa').value = btn.dataset.nomor_wa;
                if (document.getElementById('view_username')) {
                    document.getElementById('view_username').value = btn.dataset.username;
                }
                if (document.getElementById('edit_kelompok')) {
                    document.getElementById('edit_kelompok').value = btn.dataset.kelompok;
                }
                const rawKelas = btn.dataset.kelasList || "";
                const kelasArr = rawKelas.split(',');
                document.querySelectorAll('.edit-kelas-checkbox').forEach(cb => {
                    cb.checked = false;
                    if (kelasArr.some(k => k.trim() === cb.value)) cb.checked = true;
                });
                openModal(modals.edit);
            }
            // HAPUS Logic
            if (btn.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = btn.dataset.id;
                document.getElementById('hapus_nama').textContent = btn.dataset.nama;
                document.getElementById('hapus_nama_log').value = btn.dataset.nama;
                openModal(modals.hapus);
            }
        });

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
</script>