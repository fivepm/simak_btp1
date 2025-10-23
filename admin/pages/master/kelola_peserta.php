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

// Ambil filter dari URL
$filter_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

// Jika admin tingkat kelompok, paksa filter kelompok
if ($admin_tingkat === 'kelompok') {
    $filter_kelompok = $admin_kelompok;
}

// === BAGIAN BACKEND: PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: IMPORT CSV ---
    if ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");

            $conn->begin_transaction();
            try {
                $sql = "INSERT INTO peserta (kelompok, nama_lengkap, kelas, jenis_kelamin, tempat_lahir, tanggal_lahir, nomor_hp, status, nama_orang_tua, nomor_hp_orang_tua) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                // Lewati baris header
                fgetcsv($handle, 1000, ",");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Pastikan data tanggal valid atau NULL
                    $tanggal_lahir = (!empty($data[5]) && strtotime($data[5])) ? date('Y-m-d', strtotime($data[5])) : null;

                    $stmt->bind_param("ssssssssss", $data[0], $data[1], $data[2], $data[3], $data[4], $tanggal_lahir, $data[6], $data[7], $data[8], $data[9]);
                    $stmt->execute();
                }

                $conn->commit();
                $redirect_url = '?page=master/kelola_peserta&status=import_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal mengimpor data: " . $e->getMessage();
            }
            fclose($handle);
        } else {
            $error_message = "Gagal mengunggah file atau tidak ada file yang dipilih.";
        }
    }

    // --- AKSI: TAMBAH PESERTA ---
    if ($action === 'tambah_peserta') {
        $nama_lengkap = $_POST['nama_lengkap'] ?? '';
        $kelas = $_POST['kelas'] ?? '';
        $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
        $tempat_lahir = $_POST['tempat_lahir'] ?? '';
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
        $nomor_hp = $_POST['nomor_hp'] ?? '';
        $status = $_POST['status'] ?? 'Aktif';
        $nama_orang_tua = $_POST['nama_orang_tua'] ?? '';
        $nomor_hp_orang_tua = $_POST['nomor_hp_orang_tua'] ?? '';

        // HAK AKSES: Jika admin tingkat kelompok, kelompok diatur secara otomatis dan tidak bisa diubah.
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['kelompok'] ?? '');

        if (empty($nama_lengkap) || empty($jenis_kelamin) || empty($kelompok)) {
            $error_message = 'Nama, Jenis Kelamin, dan Kelompok wajib diisi.';
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO peserta (kelompok, nama_lengkap, kelas, jenis_kelamin, tempat_lahir, tanggal_lahir, nomor_hp, status, nama_orang_tua, nomor_hp_orang_tua) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssss", $kelompok, $nama_lengkap, $kelas, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $nomor_hp, $status, $nama_orang_tua, $nomor_hp_orang_tua);
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kelola_peserta&status=add_success';
            } else {
                $error_message = 'Gagal menambahkan peserta.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: EDIT PESERTA ---
    if ($action === 'edit_peserta') {
        $id = $_POST['edit_id'] ?? 0;
        $nama_lengkap = $_POST['edit_nama_lengkap'] ?? '';
        $kelas = $_POST['edit_kelas'] ?? '';
        $jenis_kelamin = $_POST['edit_jenis_kelamin'] ?? '';
        $tempat_lahir = $_POST['edit_tempat_lahir'] ?? '';
        $tanggal_lahir = $_POST['edit_tanggal_lahir'] ?? '';
        $nomor_hp = $_POST['edit_nomor_hp'] ?? '';
        $status = $_POST['edit_status'] ?? 'Aktif';
        $nama_orang_tua = $_POST['edit_nama_orang_tua'] ?? '';
        $nomor_hp_orang_tua = $_POST['edit_nomor_hp_orang_tua'] ?? '';

        // HAK AKSES: Jika admin tingkat kelompok, kelompok diatur secara otomatis dan tidak bisa diubah.
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['edit_kelompok'] ?? '');

        if (empty($nama_lengkap) || empty($jenis_kelamin) || empty($kelompok) || empty($id)) {
            $error_message = 'Data tidak lengkap untuk proses edit.';
        }

        if (empty($error_message)) {
            $sql = "UPDATE peserta SET kelompok=?, nama_lengkap=?, kelas=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, nomor_hp=?, status=?, nama_orang_tua=?, nomor_hp_orang_tua=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssi", $kelompok, $nama_lengkap, $kelas, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $nomor_hp, $status, $nama_orang_tua, $nomor_hp_orang_tua, $id);
            if ($stmt->execute()) {
                $redirect_url = '?page=master/kelola_peserta&status=edit_success';
            } else {
                $error_message = 'Gagal mengedit peserta.';
            }
            $stmt->close();
        }
    }

    // --- AKSI: HAPUS PESERTA ---
    if ($action === 'hapus_peserta') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID peserta tidak valid.';
        } else {
            // $sql = "DELETE FROM peserta WHERE id = ?";
            $sql = "UPDATE peserta SET status='Tidak Aktif' WHERE id = ?";
            // HAK AKSES: Admin kelompok hanya bisa menghapus peserta dari kelompoknya sendiri.
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
                // PERBAIKAN: Cek apakah ada baris yang benar-benar terhapus.
                if ($stmt->affected_rows > 0) {
                    $redirect_url = '?page=master/kelola_peserta&status=delete_success';
                } else {
                    $error_message = 'Gagal menghapus: Peserta tidak ditemukan atau Anda tidak memiliki izin.';
                }
            } else {
                $error_message = 'Gagal menghapus peserta.';
            }
            $stmt->close();
        }
    }
}

// Cek notifikasi dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'import_success') $success_message = 'Data peserta berhasil diimpor!';
    if ($_GET['status'] === 'add_success') $success_message = 'Peserta baru berhasil ditambahkan!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Data peserta berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Data peserta berhasil dihapus!';
}

// === AMBIL DATA PESERTA BERDASARKAN FILTER ===
$peserta_list = [];
$sql = "SELECT * FROM peserta";
$where_conditions = [];
$params = [];
$types = "";

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
    $sql .= " WHERE status = 'Aktif' AND " . implode(" AND ", $where_conditions);
} else {
    $sql .= " WHERE status = 'Aktif'";
}
$sql .= " ORDER BY kelompok ASC, kelas ASC, nama_lengkap ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}
$stmt->close();

$peserta_per_kelas_rinci = [];
$peserta_per_kelas_total = [];
$grand_totals = [];
/// --- LOGIKA PHP ANDA UNTUK RINCIAN PESERTA (DIPINDAHKAN KE SINI) ---
if ($admin_tingkat === 'desa') {
    // Query rinci untuk admin desa
    $sql_kelas_rinci = "SELECT kelas, kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' GROUP BY kelas, kelompok, jenis_kelamin";
    $result_kelas_rinci = $conn->query($sql_kelas_rinci);
    if ($result_kelas_rinci) {
        while ($row = $result_kelas_rinci->fetch_assoc()) {
            // Pastikan konsistensi case (misal: semua lowercase)
            $kelas_key = strtolower($row['kelas']);
            $kelompok_key = strtolower($row['kelompok']);
            $peserta_per_kelas_rinci[$kelas_key][$kelompok_key][$row['jenis_kelamin']] = $row['jumlah'];
        }
    }
    // Hitung Grand Total untuk footer tabel (hanya untuk admin desa)
    $sql_grand_total = "SELECT kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' GROUP BY kelompok, jenis_kelamin";
    $result_grand_total = $conn->query($sql_grand_total);
    if ($result_grand_total) {
        while ($row = $result_grand_total->fetch_assoc()) {
            $kelompok_key = strtolower($row['kelompok']);
            $grand_totals[$kelompok_key][$row['jenis_kelamin']] = $row['jumlah'];
        }
    }
} else { // admin_level === 'kelompok'
    // Query total untuk admin kelompok
    $sql_kelas_total = "SELECT kelas, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' AND kelompok = ? GROUP BY kelas";
    $stmt_kelas_total = $conn->prepare($sql_kelas_total);
    if ($stmt_kelas_total) {
        $stmt_kelas_total->bind_param("s", $admin_kelompok);
        $stmt_kelas_total->execute();
        $result_kelas_total = $stmt_kelas_total->get_result();
        if ($result_kelas_total) {
            while ($row = $result_kelas_total->fetch_assoc()) {
                $kelas_key = strtolower($row['kelas']);
                $peserta_per_kelas_total[$kelas_key] = $row['jumlah'];
            }
        }
        $stmt_kelas_total->close();
    } else {
        error_log("Gagal prepare statement sql_kelas_total: " . $conn->error);
    }
}
// --- AKHIR LOGIKA RINCIAN PESERTA ---

// Definisikan list kelas dan kelompok untuk digunakan di HTML
$kelas_list_display = ['Paud', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];
$kelompok_list_display = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];

// === TAMPILAN HTML ===
?>
<div class="container mx-auto">
    <!-- Header Halaman -->
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-gray-700 text-2xl font-medium">Kelola Peserta</h3>
        <div class="flex space-x-2">
            <!-- Tombol untuk membuka modal rincian peserta -->
            <button
                type="button"
                id="bukaRincianPesertaBtn"
                class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-lg">
                <i class="fas fa-users mr-2"></i> Rincian Peserta
            </button>
            <!-- <button
                id="bukaRincianPesertaBtn"
                class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center justify-center w-full sm:w-auto">
                <i class="fas fa-users mr-2"></i> Lihat Rincian Peserta
            </button> -->
            <a href="pages/export/export_siswa" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                Export Data
            </a>
            <button id="importBtn" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">
                <i class="fa fa-download" aria-hidden="true"></i>
                Import CSV
            </button>
            <button id="tambahPesertaBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">
                <i class="fa fa-plus" aria-hidden="true"> </i>
                Tambah Peserta
            </button>
        </div>
    </div>

    <!-- BAGIAN FILTER BARU -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="">
            <input type="hidden" name="page" value="master/kelola_peserta">
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelompok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="pesertaTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($peserta_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">Belum ada data peserta.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($peserta_list as $peserta): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $i++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($peserta['jenis_kelamin']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($peserta['kelas']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize"><?php echo htmlspecialchars($peserta['kelompok']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo ($peserta['status'] === 'Aktif') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars($peserta['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="detail-btn text-gray-600 hover:text-gray-900" data-peserta='<?php echo json_encode($peserta); ?>'>Detail</button>
                                <button class="edit-btn text-indigo-600 hover:text-indigo-900 ml-4" data-peserta='<?php echo json_encode($peserta); ?>'>Edit</button>
                                <button class="hapus-btn text-red-600 hover:text-red-900 ml-4" data-id="<?php echo $peserta['id']; ?>" data-nama="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Import CSV -->
<div id="importModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kelola_peserta" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Import Data Peserta dari CSV</h3>
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Unggah file CSV dengan format yang sesuai untuk menambahkan data peserta secara massal.</p>
                        <div>
                            <label class="block text-sm font-medium">Pilih File CSV*</label>
                            <input type="file" name="csv_file" accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required>
                        </div>
                        <div>
                            <!-- PERBAIKAN: Link mengarah ke file action baru -->
                            <a href="actions/download_template.php?type=peserta" class="text-sm text-indigo-600 hover:underline">Unduh Template CSV</a>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Import Data</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit Peserta -->
<div id="pesertaModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kelola_peserta">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Form Peserta</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto pr-2">
                        <div><label class="block text-sm font-medium">Nama Lengkap*</label>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                        <div><label class="block text-sm font-medium">Kelas</label>
                            <select name="kelas" id="kelas" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">-- Pilih Kelas --</option>
                                <option value="paud">PAUD</option>
                                <option value="caberawit a">Caberawit A</option>
                                <option value="caberawit b">Caberawit B</option>
                                <option value="pra remaja">Pra Remaja</option>
                                <option value="remaja">Remaja</option>
                                <option value="pra nikah">Pra Nikah</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium">Jenis Kelamin*</label><select name="jenis_kelamin" id="jenis_kelamin" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select></div>
                        <div><label class="block text-sm font-medium">Kelompok*</label>
                            <!-- HAK AKSES: Tampilkan input disabled jika admin kelompok -->
                            <?php if ($admin_tingkat === 'kelompok'): ?>
                                <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 shadow-sm sm:text-sm border-gray-300 rounded-md" disabled>
                                <input type="hidden" name="kelompok" id="kelompok_hidden" value="<?php echo $admin_kelompok; ?>">
                            <?php else: ?>
                                <select name="kelompok" id="kelompok" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                    <option value="bintaran">Bintaran</option>
                                    <option value="gedongkuning">Gedongkuning</option>
                                    <option value="jombor">Jombor</option>
                                    <option value="sunten">Sunten</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div><label class="block text-sm font-medium">Tempat Lahir</label><input type="text" name="tempat_lahir" id="tempat_lahir" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium">Tanggal Lahir</label><input type="date" name="tanggal_lahir" id="tanggal_lahir" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium">Nomor HP</label><input type="tel" name="nomor_hp" id="nomor_hp" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium">Status*</label><select name="status" id="status" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                <option value="Aktif">Aktif</option>
                                <option value="Tidak Aktif">Tidak Aktif</option>
                                <option value="Lulus">Lulus</option>
                            </select></div>
                        <div class="sm:col-span-2"><label class="block text-sm font-medium">Nama Orang Tua</label><input type="text" name="nama_orang_tua" id="nama_orang_tua" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div class="sm:col-span-2"><label class="block text-sm font-medium">Nomor HP Orang Tua</label><input type="tel" name="nomor_hp_orang_tua" id="nomor_hp_orang_tua" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
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
<div id="hapusPesertaModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="?page=master/kelola_peserta">
                <input type="hidden" name="action" value="hapus_peserta">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus peserta <strong id="hapus_nama"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Ya, Hapus</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Peserta -->
<div id="detailPesertaModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-11/12 max-w-sm sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Detail Peserta</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="font-semibold text-gray-500">Nama Lengkap</dt>
                    <dd id="detail_nama_lengkap" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Jenis Kelamin</dt>
                    <dd id="detail_jenis_kelamin" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">TTL</dt>
                    <dd id="detail_ttl" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Kelas</dt>
                    <dd id="detail_kelas" class="text-gray-900 sm:col-span-1 capitalize"></dd>
                    <dt class="font-semibold text-gray-500">Kelompok</dt>
                    <dd id="detail_kelompok" class="text-gray-900 sm:col-span-1 capitalize"></dd>
                    <dt class="font-semibold text-gray-500">Status</dt>
                    <dd id="detail_status" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Nomor HP</dt>
                    <dd id="detail_nomor_hp" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Nama Orang Tua</dt>
                    <dd id="detail_nama_orang_tua" class="text-gray-900 sm:col-span-1"></dd>
                    <dt class="font-semibold text-gray-500">Nomor HP Ortu</dt>
                    <dd id="detail_nomor_hp_orang_tua" class="text-gray-900 sm:col-span-1"></dd>
                </dl>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Rincian Peserta (sekarang berada di file ini) -->
<div id="modalRincianPeserta" class="fixed z-30 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">

        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-4xl">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 text-center" id="modal-title">
                            Rincian Jumlah Peserta
                        </h3>
                        <!-- ▼▼▼ KONTEN MODAL DIGANTI DENGAN KODE HTML ANDA ▼▼▼ -->
                        <div class="mt-4">
                            <?php if ($admin_tingkat === 'desa'): // Tampilan Tabel Rinci untuk Admin Desa 
                            ?>
                                <div class="overflow-x-auto border rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th rowspan="2" class="px-4 py-2 text-left text-xs font-bold text-gray-600 uppercase align-middle border-r">Kelas</th>
                                                <?php foreach ($kelompok_list_display as $kelompok): ?>
                                                    <th colspan="2" class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase border-l"><?php echo htmlspecialchars(ucfirst($kelompok)); ?></th>
                                                <?php endforeach; ?>
                                                <th rowspan="2" class="px-4 py-2 text-center text-xs font-bold text-gray-600 uppercase align-middle border-l">Total Kelas</th>
                                            </tr>
                                            <tr>
                                                <?php foreach ($kelompok_list_display as $kelompok): ?>
                                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase border-l">L</th>
                                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase">P</th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $grand_totals_modal = []; // Reset untuk perhitungan di modal
                                            ?>
                                            <?php foreach ($kelas_list_display as $kelas):
                                                $total_per_kelas_modal = 0;
                                                $kelas_key = strtolower($kelas); // Gunakan lowercase key
                                            ?>
                                                <tr>
                                                    <td class="px-4 py-3 whitespace-nowrap font-semibold capitalize text-gray-800 border-r"><?php echo htmlspecialchars($kelas); ?></td>
                                                    <?php foreach ($kelompok_list_display as $kelompok):
                                                        $kelompok_key = strtolower($kelompok); // Gunakan lowercase key
                                                        // Mengambil data dari array PHP
                                                        $jumlah_l_modal = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Laki-laki'] ?? 0;
                                                        $jumlah_p_modal = $peserta_per_kelas_rinci[$kelas_key][$kelompok_key]['Perempuan'] ?? 0;
                                                        $total_per_kelas_modal += ($jumlah_l_modal + $jumlah_p_modal);
                                                    ?>
                                                        <td class="px-2 py-3 whitespace-nowrap text-center text-sm font-medium text-gray-700 border-l"><?php echo $jumlah_l_modal; ?></td>
                                                        <td class="px-2 py-3 whitespace-nowrap text-center text-sm font-medium text-gray-700"><?php echo $jumlah_p_modal; ?></td>
                                                    <?php endforeach; ?>
                                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-bold text-indigo-600 bg-indigo-50 border-l"><?php echo $total_per_kelas_modal; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-gray-200 font-bold">
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-gray-800 border-r">TOTAL</td>
                                                <?php $grand_total_semua_modal = 0; ?>
                                                <?php foreach ($kelompok_list_display as $kelompok):
                                                    $kelompok_key = strtolower($kelompok); // Gunakan lowercase key
                                                    // Ambil dari $grand_totals yang dihitung di PHP atas
                                                    $total_l_modal = $grand_totals[$kelompok_key]['Laki-laki'] ?? 0;
                                                    $total_p_modal = $grand_totals[$kelompok_key]['Perempuan'] ?? 0;
                                                    $grand_total_semua_modal += ($total_l_modal + $total_p_modal);
                                                ?>
                                                    <td class="px-2 py-3 whitespace-nowrap text-center text-sm text-gray-800 border-l"><?php echo $total_l_modal; ?></td>
                                                    <td class="px-2 py-3 whitespace-nowrap text-center text-sm text-gray-800"><?php echo $total_p_modal; ?></td>
                                                <?php endforeach; ?>
                                                <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-indigo-700 bg-indigo-100 border-l"><?php echo $grand_total_semua_modal; ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: // Tampilan Kartu Total untuk Admin Kelompok 
                            ?>
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Peserta per Kelas di Kelompok <?php echo htmlspecialchars($admin_kelompok); ?></h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    <?php foreach ($kelas_list_display as $kelas):
                                        $kelas_key = strtolower($kelas); // Gunakan lowercase key
                                    ?>
                                        <div class="text-center bg-gray-50 p-4 rounded-lg border">
                                            <p class="capitalize text-sm font-semibold text-gray-500"><?php echo htmlspecialchars($kelas); ?></p>
                                            <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_kelas_total[$kelas_key] ?? 0; ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- ▲▲▲ AKHIR KONTEN YANG DIGANTI ▲▲▲ -->
                    </div>
                </div>
            </div>
            <div class="bg-gray-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="modal-close-btn-rincian mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Tutup
                </button>
            </div>
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
        const pesertaModal = document.getElementById('pesertaModal');
        const hapusModal = document.getElementById('hapusPesertaModal');
        const detailModal = document.getElementById('detailPesertaModal');
        const btnTambah = document.getElementById('tambahPesertaBtn');
        const tableBody = document.getElementById('pesertaTableBody');

        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        // --- Buka Modal Tambah ---
        btnTambah.onclick = () => {
            pesertaModal.querySelector('form').reset();
            document.getElementById('modalTitle').textContent = 'Form Tambah Peserta';
            document.getElementById('form_action').value = 'tambah_peserta';
            const editIdInput = document.getElementById('edit_id');
            if (editIdInput) editIdInput.value = '';

            document.getElementById('nama_lengkap').name = 'nama_lengkap';
            document.getElementById('kelas').name = 'kelas';
            document.getElementById('jenis_kelamin').name = 'jenis_kelamin';
            document.getElementById('tempat_lahir').name = 'tempat_lahir';
            document.getElementById('tanggal_lahir').name = 'tanggal_lahir';
            document.getElementById('nomor_hp').name = 'nomor_hp';
            document.getElementById('status').name = 'status';
            document.getElementById('nama_orang_tua').name = 'nama_orang_tua';
            document.getElementById('nomor_hp_orang_tua').name = 'nomor_hp_orang_tua';
            const kelompokSelect = document.getElementById('kelompok');
            if (kelompokSelect) kelompokSelect.name = 'kelompok';
            const kelompokHidden = document.getElementById('kelompok_hidden');
            if (kelompokHidden) kelompokHidden.name = 'kelompok';

            openModal(pesertaModal);
        };

        // --- Event Listener untuk Tombol di Tabel ---
        tableBody.addEventListener('click', function(event) {
            const target = event.target;
            const data = target.dataset.peserta ? JSON.parse(target.dataset.peserta) : null;

            // Buka Modal Detail
            if (target.classList.contains('detail-btn')) {
                document.getElementById('detail_nama_lengkap').textContent = data.nama_lengkap || '-';
                document.getElementById('detail_jenis_kelamin').textContent = data.jenis_kelamin || '-';
                const ttl = (data.tempat_lahir && data.tanggal_lahir) ? `${data.tempat_lahir}, ${data.tanggal_lahir}` : '-';
                document.getElementById('detail_ttl').textContent = ttl;
                document.getElementById('detail_kelas').textContent = data.kelas || '-';
                document.getElementById('detail_kelompok').textContent = data.kelompok || '-';
                document.getElementById('detail_status').textContent = data.status || '-';
                document.getElementById('detail_nomor_hp').textContent = data.nomor_hp || '-';
                document.getElementById('detail_nama_orang_tua').textContent = data.nama_orang_tua || '-';
                document.getElementById('detail_nomor_hp_orang_tua').textContent = data.nomor_hp_orang_tua || '-';
                openModal(detailModal);
            }

            // Buka Modal Edit
            if (target.classList.contains('edit-btn')) {
                document.getElementById('modalTitle').textContent = 'Form Edit Peserta';
                document.getElementById('form_action').value = 'edit_peserta';
                document.getElementById('edit_id').value = data.id;

                document.getElementById('nama_lengkap').name = 'edit_nama_lengkap';
                document.getElementById('kelas').name = 'edit_kelas';
                document.getElementById('jenis_kelamin').name = 'edit_jenis_kelamin';
                document.getElementById('tempat_lahir').name = 'edit_tempat_lahir';
                document.getElementById('tanggal_lahir').name = 'edit_tanggal_lahir';
                document.getElementById('nomor_hp').name = 'edit_nomor_hp';
                document.getElementById('status').name = 'edit_status';
                document.getElementById('nama_orang_tua').name = 'edit_nama_orang_tua';
                document.getElementById('nomor_hp_orang_tua').name = 'edit_nomor_hp_orang_tua';
                const kelompokSelect = document.getElementById('kelompok');
                if (kelompokSelect) kelompokSelect.name = 'edit_kelompok';
                const kelompokHidden = document.getElementById('kelompok_hidden');
                if (kelompokHidden) kelompokHidden.name = 'edit_kelompok';

                document.getElementById('nama_lengkap').value = data.nama_lengkap || '';
                document.getElementById('kelas').value = data.kelas || '';
                document.getElementById('jenis_kelamin').value = data.jenis_kelamin || 'Laki-laki';
                if (kelompokSelect) kelompokSelect.value = data.kelompok || '';
                document.getElementById('tempat_lahir').value = data.tempat_lahir || '';
                document.getElementById('tanggal_lahir').value = data.tanggal_lahir || '';
                document.getElementById('nomor_hp').value = data.nomor_hp || '';
                document.getElementById('status').value = data.status || 'Aktif';
                document.getElementById('nama_orang_tua').value = data.nama_orang_tua || '';
                document.getElementById('nomor_hp_orang_tua').value = data.nomor_hp_orang_tua || '';

                openModal(pesertaModal);
            }

            // Buka Modal Hapus
            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_nama').textContent = target.dataset.nama;
                openModal(hapusModal);
            }
        });

        // --- Tutup Semua Modal ---
        document.querySelectorAll('.fixed.z-20').forEach(modal => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('.modal-close-btn')) {
                    closeModal(modal);
                }
            });
        });

        // --- Buka kembali modal jika ada error ---
        <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($_POST['action'] === 'tambah_peserta' || $_POST['action'] === 'edit_peserta'): ?>
                openModal(pesertaModal);
            <?php endif; ?>
        <?php endif; ?>

        // --- KONTROL MODAL IMPORT BARU ---
        const importModal = document.getElementById('importModal');
        const btnImport = document.getElementById('importBtn');

        if (btnImport) {
            btnImport.onclick = () => importModal.classList.remove('hidden');
        }
        if (importModal) {
            importModal.addEventListener('click', (event) => {
                if (event.target === importModal || event.target.closest('.modal-close-btn')) {
                    importModal.classList.add('hidden');
                }
            });
        }

        // --- Logika untuk Tombol Rincian Peserta ---
        const tombolBukaRincian = document.getElementById('bukaRincianPesertaBtn');
        const modalRincian = document.getElementById('modalRincianPeserta');

        const bukaModalRincian = () => {
            if (modalRincian) modalRincian.classList.remove('hidden');
        }
        const tutupModalRincian = () => {
            if (modalRincian) modalRincian.classList.add('hidden');
        }

        if (tombolBukaRincian) {
            tombolBukaRincian.addEventListener('click', bukaModalRincian);
        }

        if (modalRincian) {
            modalRincian.addEventListener('click', function(event) {
                if (event.target === modalRincian || event.target.closest('.modal-close-btn-rincian')) {
                    tutupModalRincian();
                }
            });
            const tombolTutupModal = modalRincian.querySelector('.modal-close-btn-rincian');
            if (tombolTutupModal) {
                tombolTutupModal.addEventListener('click', tutupModalRincian);
            }
        }
    });
</script>