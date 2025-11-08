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

// === AMBIL DATA PERIODE (DIPINDAHKAN KE ATAS) ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// === TENTUKAN PERIODE DEFAULT BERDASARKAN TANGGAL HARI INI ===
$default_periode_id = null;
$today = date('Y-m-d');

foreach ($periode_list as $p) {
    if ($today >= $p['tanggal_mulai'] && $today <= $p['tanggal_selesai']) {
        $default_periode_id = $p['id'];
        break; // Ditemukan periode yang aktif hari ini
    }
}

// Jika tidak ada periode yang aktif hari ini (misal di antara periode),
// ambil periode terbaru (paling atas di list) sebagai default.
if ($default_periode_id === null && !empty($periode_list)) {
    $default_periode_id = $periode_list[0]['id'];
}

// === Ambil filter dari URL (MODIFIKASI) ===
// Gunakan $default_periode_id jika $_GET['periode_id'] tidak ada
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

$redirect_url_base = '?page=presensi/jadwal&periode_id=' . $selected_periode_id . '&kelompok=' . urlencode($selected_kelompok) . '&kelas=' . urlencode($selected_kelas);

$selected_periode_nama = '';
$selected_periode_tanggal_mulai = '';
$selected_periode_tanggal_selesai = '';

if ($selected_periode_id) {
    $stmt_periode_nama = $conn->prepare("SELECT nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE id = ?");
    $stmt_periode_nama->bind_param("i", $selected_periode_id);
    $stmt_periode_nama->execute();
    $result_periode_nama = $stmt_periode_nama->get_result();

    if ($result_periode_nama->num_rows > 0) {
        // Panggil fetch_assoc() SATU KALI saja
        $periode_data = $result_periode_nama->fetch_assoc();

        // Ambil semua data yang Anda butuhkan dari variabel $periode_data
        $selected_periode_nama = $periode_data['nama_periode'];
        $selected_periode_tanggal_mulai = $periode_data['tanggal_mulai'];
        $selected_periode_tanggal_selesai = $periode_data['tanggal_selesai'];
    }
}

// === PROSES POST REQUEST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AKSI: TAMBAH JADWAL ---
    if ($action === 'tambah_jadwal') {
        $tanggal = $_POST['tanggal'] ?? '';
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';

        if (empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
            $error_message = 'Semua field wajib diisi.';
        } else {
            $conn->begin_transaction();
            try {
                // 1. Masukkan jadwal baru
                $sql_jadwal = "INSERT INTO jadwal_presensi (periode_id, kelompok, kelas, tanggal, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_jadwal = $conn->prepare($sql_jadwal);
                $stmt_jadwal->bind_param("isssss", $selected_periode_id, $selected_kelompok, $selected_kelas, $tanggal, $jam_mulai, $jam_selesai);
                $stmt_jadwal->execute();
                $jadwal_id = $stmt_jadwal->insert_id;

                // 2. Ambil semua peserta aktif yang sesuai
                $sql_peserta = "SELECT id FROM peserta WHERE kelompok = ? AND kelas = ? AND status = 'Aktif'";
                $stmt_peserta = $conn->prepare($sql_peserta);
                $stmt_peserta->bind_param("ss", $selected_kelompok, $selected_kelas);
                $stmt_peserta->execute();
                $result_peserta = $stmt_peserta->get_result();

                // 3. Masukkan setiap peserta ke tabel rekap_presensi
                if ($result_peserta->num_rows > 0) {
                    $sql_rekap = "INSERT INTO rekap_presensi (jadwal_id, peserta_id) VALUES (?, ?)";
                    $stmt_rekap = $conn->prepare($sql_rekap);
                    while ($peserta = $result_peserta->fetch_assoc()) {
                        $stmt_rekap->bind_param("ii", $jadwal_id, $peserta['id']);
                        $stmt_rekap->execute();
                    }
                }

                $conn->commit();
                $redirect_url = $redirect_url_base . '&status=add_success';
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Gagal menambahkan jadwal: ' . $e->getMessage();
            }
        }
    }

    // --- AKSI: EDIT JADWAL ---
    if ($action === 'edit_jadwal') {
        $id = $_POST['edit_id'] ?? 0;
        $tanggal = $_POST['edit_tanggal'] ?? '';
        $jam_mulai = $_POST['edit_jam_mulai'] ?? '';
        $jam_selesai = $_POST['edit_jam_selesai'] ?? '';

        if (empty($id) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
            $error_message = 'Data untuk edit tidak lengkap.';
        } else {
            $sql = "UPDATE jadwal_presensi SET tanggal=?, jam_mulai=?, jam_selesai=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $tanggal, $jam_mulai, $jam_selesai, $id);
            if ($stmt->execute()) {
                $redirect_url = $redirect_url_base . '&status=edit_success';
            } else {
                $error_message = 'Gagal memperbarui jadwal.';
            }
        }
    }

    // --- AKSI: HAPUS JADWAL ---
    if ($action === 'hapus_jadwal') {
        $id = $_POST['hapus_id'] ?? 0;
        if (empty($id)) {
            $error_message = 'ID jadwal tidak valid.';
        } else {
            // ON DELETE CASCADE di DB akan menghapus semua data terkait
            $stmt = $conn->prepare("DELETE FROM jadwal_presensi WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $redirect_url = $redirect_url_base . '&status=delete_success';
            } else {
                $error_message = 'Gagal menghapus jadwal.';
            }
        }
    }
}


if (isset($_GET['status'])) {
    if ($_GET['status'] === 'add_success') $success_message = 'Jadwal baru berhasil dibuat!';
    if ($_GET['status'] === 'edit_success') $success_message = 'Jadwal berhasil diperbarui!';
    if ($_GET['status'] === 'delete_success') $success_message = 'Jadwal berhasil dihapus!';
}

$jadwal_list = [];
$rekap_petugas_data = [];

if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua') {
    // GANTI QUERY LAMA ANDA DENGAN YANG INI
    $sql = "SELECT 
                jp.id, jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.pengajar, jp.periode_id,
                GROUP_CONCAT(DISTINCT g.nama SEPARATOR ', ') as daftar_guru,
                GROUP_CONCAT(DISTINCT p.nama SEPARATOR ', ') as daftar_penasehat
            FROM jadwal_presensi jp
            LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id
            LEFT JOIN guru g ON jg.guru_id = g.id
            LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id
            LEFT JOIN penasehat p ON jn.penasehat_id = p.id
            WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
            GROUP BY jp.id
            ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";

    $stmt_jadwal = $conn->prepare($sql);
    $stmt_jadwal->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    if ($result_jadwal) {
        while ($row = $result_jadwal->fetch_assoc()) {
            $jadwal_list[] = $row;
        }
    }

    // 2. Ambil data untuk tabel kedua (Rekapitulasi Petugas)
    $sql_rekap = "SELECT jp.tanggal, jp.jam_mulai, jp.jam_selesai, GROUP_CONCAT(DISTINCT g.nama ORDER BY g.nama SEPARATOR '\n') as daftar_guru, GROUP_CONCAT(DISTINCT p.nama ORDER BY p.nama SEPARATOR '\n') as daftar_penasehat FROM jadwal_presensi jp LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id LEFT JOIN guru g ON jg.guru_id = g.id LEFT JOIN jadwal_penasehat jn ON jp.id = jn.jadwal_id LEFT JOIN penasehat p ON jn.penasehat_id = p.id WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? GROUP BY jp.tanggal, jp.jam_mulai, jp.jam_selesai ORDER BY jp.tanggal ASC";
    $stmt_rekap = $conn->prepare($sql_rekap);
    $stmt_rekap->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_rekap->execute();
    $result_rekap = $stmt_rekap->get_result();
    if ($result_rekap) {
        while ($row = $result_rekap->fetch_assoc()) {
            $rekap_petugas_data[] = $row;
        }
    }
}

?>
<div class="container mx-auto space-y-6">
    <!-- FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter & Buat Jadwal</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/jadwal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label>
                    <select id="filter_periode_id" name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <?php foreach ($periode_list as $p): ?>
                            <option
                                value="<?php echo $p['id']; ?>"
                                <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>
                                data-mulai="<?php echo $p['tanggal_mulai']; ?>"
                                data-selesai="<?php echo $p['tanggal_selesai']; ?>">

                                <?php echo htmlspecialchars($p['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                        <input id="filter_kelompok" type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select id="filter_kelompok" name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="semua" <?php echo ($selected_kelompok == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelompok --</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label>
                    <select id="filter_kelas" name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <option value="semua" <?php echo ($selected_kelas == 'semua') ? 'selected' : ''; ?>>-- Pilih Kelas --</option>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <!-- TABEL JADWAL -->
    <?php if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua'): ?>
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-xl font-medium text-gray-800">Daftar Jadwal</h3>
                    <p class="text-md text-gray-800">
                        Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span>
                    </p>
                    <p class="text-md text-gray-800 mb-4">
                        <span class="font-semibold capitalize"><?php echo htmlspecialchars(formatTanggalIndonesiaTanpaNol($selected_periode_tanggal_mulai)); ?></span> -
                        <span class="font-semibold capitalize"><?php echo htmlspecialchars(formatTanggalIndonesiaTanpaNol($selected_periode_tanggal_selesai)); ?></span>
                    </p>
                </div>
                <button id="tambahJadwalBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">+ Tambah Jadwal</button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tanggal & Jam</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Pemateri</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Jurnal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jadwal_list)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4">Tidak ada jadwal yang cocok dengan filter.</td>
                        </tr>
                        <?php else: foreach ($jadwal_list as $jadwal): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium"><?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div>
                                        <span class="font-semibold">Guru:</span>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($jadwal['daftar_guru'] ?? 'Belum Diatur'); ?></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Penasehat:</span>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($jadwal['daftar_penasehat'] ?? 'Belum Diatur'); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo !empty($jadwal['pengajar']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo !empty($jadwal['pengajar']) ? 'Terisi' : 'Kosong'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="?page=presensi/input_presensi&jadwal_id=<?php echo $jadwal['id']; ?>" class="text-indigo-600 hover:text-indigo-900 font-bold">Presensi</a>

                                    <button class="edit-btn text-indigo-600 hover:text-indigo-900 ml-4"
                                        data-id="<?php echo $jadwal['id']; ?>"
                                        data-tanggal="<?php echo $jadwal['tanggal']; ?>"
                                        data-jam-mulai="<?php echo $jadwal['jam_mulai']; ?>"
                                        data-jam-selesai="<?php echo $jadwal['jam_selesai']; ?>"
                                        data-periode-id="<?php echo $jadwal['periode_id']; ?>">
                                        Edit</button>

                                    <button class="hapus-btn text-red-600 hover:text-red-900 ml-4"
                                        data-id="<?php echo $jadwal['id']; ?>"
                                        data-info="Jadwal tanggal <?php echo date("d M Y", strtotime($jadwal['tanggal'])); ?>">Hapus</button>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TABEL REKAP PETUGAS BARU -->
        <div class="border border-black bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h3 class="text-xl font-medium text-center text-gray-800">Jadwal Guru dan Penasehat</h3>
            <p class="text-md text-center text-gray-800">
                Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span>
            </p>
            <p class="text-md text-center text-gray-800 mb-4">
                <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelompok); ?></span> -
                <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelas); ?></span>
            </p>
            <table class="border min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-1/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">No</th>
                        <th class="w-3/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">Tanggal</th>
                        <th class="w-4/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">Guru</th>
                        <th class="w-4/12 border px-4 py-2 text-left text-xs font-medium text-center text-gray-500 uppercase">Penasehat</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($rekap_petugas_data)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">Tidak ada data petugas yang ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $no = 1;
                        foreach ($rekap_petugas_data as $item): ?>
                            <tr>
                                <td class="border px-4 py-3 align-top font-semibold text-center"><?php echo $no++; ?></td>
                                <td class="border px-4 py-3 align-top font-semibold text-center">
                                    <?php echo format_hari_tanggal(date("l, d F Y", strtotime($item['tanggal']))); ?>
                                    <p class="text-sm text-gray-500"><?php echo date("H:i", strtotime($item['jam_mulai'])) . ' - ' . date("H:i", strtotime($item['jam_selesai'])); ?></p>
                                </td>
                                <td class="border px-4 py-3 align-top text-sm whitespace-pre-line text-center"><?php echo !empty($item['daftar_guru']) ? nl2br(htmlspecialchars($item['daftar_guru'])) : '<i class="text-gray-400">--</i>'; ?></td>
                                <td class="border px-4 py-3 align-top text-sm whitespace-pre-line text-center"><?php echo !empty($item['daftar_penasehat']) ? nl2br(htmlspecialchars($item['daftar_penasehat'])) : '<i class="text-gray-400">--</i>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Jadwal -->
<div id="tambahJadwalModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="tambah_jadwal">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tambah Jadwal Baru</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Tanggal*</label>
                            <input id="tanggal-jadwal" type="date" name="tanggal" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">Jam Mulai*</label><input type="time" name="jam_mulai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                            <div><label class="block text-sm font-medium">Jam Selesai*</label><input type="time" name="jam_selesai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan Jadwal</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Jadwal -->
<div id="editJadwalModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="edit_jadwal">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Jadwal</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Tanggal*</label>
                            <input type="date" name="edit_tanggal" id="edit_tanggal" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">Jam Mulai*</label><input type="time" name="edit_jam_mulai" id="edit_jam_mulai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                            <div><label class="block text-sm font-medium">Jam Selesai*</label><input type="time" name="edit_jam_selesai" id="edit_jam_selesai" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Update Jadwal</button>
                    <button type="button" class="modal-close-btn mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Jadwal -->
<div id="hapusJadwalModal" class="fixed z-20 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="<?php echo $redirect_url_base; ?>">
                <input type="hidden" name="action" value="hapus_jadwal">
                <input type="hidden" name="hapus_id" id="hapus_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <p class="mt-2 text-sm text-gray-500">Anda yakin ingin menghapus <strong id="hapus_info"></strong>? Semua data presensi, penugasan guru, dan pengingat terkait akan ikut terhapus.</p>
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

        // --- KUMPULKAN SEMUA ELEMEN PENTING ---
        const modals = {
            tambah: document.getElementById('tambahJadwalModal'),
            edit: document.getElementById('editJadwalModal'),
            hapus: document.getElementById('hapusJadwalModal')
        };

        // Filter Periode Utama (Satu-satunya sumber data periode)
        const masterPeriodeSelect = document.getElementById('filter_periode_id');

        // Elemen input tanggal di dalam masing-masing modal
        const tambahTanggalInput = document.getElementById('tanggal-jadwal');
        const editTanggalInput = document.getElementById('edit_tanggal');


        // --- FUNGSI-FUNGSI UTAMA ---

        // Fungsi generik untuk membuka/menutup modal
        const openModal = (modal) => modal.classList.remove('hidden');
        const closeModal = (modal) => modal.classList.add('hidden');

        // Fungsi utama untuk menerapkan batasan rentang tanggal
        function applyDateRange(periodeSelect, tanggalInput) {
            if (!periodeSelect || !tanggalInput) return;

            const selectedOption = periodeSelect.options[periodeSelect.selectedIndex];
            const tanggalMulai = selectedOption.dataset.mulai;
            const tanggalSelesai = selectedOption.dataset.selesai;

            if (tanggalMulai && tanggalSelesai) {
                tanggalInput.min = tanggalMulai;
                tanggalInput.max = tanggalSelesai;
                tanggalInput.disabled = false;
            } else {
                tanggalInput.disabled = true;
                tanggalInput.value = '';
            }
        }


        // --- PENGATURAN EVENT LISTENER ---

        // Listener untuk menutup modal saat klik di luar atau tombol close
        Object.values(modals).forEach(modal => {
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal || e.target.closest('.modal-close-btn')) closeModal(modal);
            });
        });

        // Listener utama untuk semua tombol (event delegation)
        document.querySelector('body').addEventListener('click', async function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            // === LOGIKA BARU SAAT TOMBOL TAMBAH DIKLIK ===
            if (target.id === 'tambahJadwalBtn') {
                // Terapkan batasan tanggal berdasarkan filter utama
                applyDateRange(masterPeriodeSelect, tambahTanggalInput);

                // Kosongkan input tanggal setiap kali membuka modal tambah
                tambahTanggalInput.value = '';

                openModal(modals.tambah);
            }

            // === LOGIKA BARU SAAT TOMBOL EDIT DIKLIK ===
            if (target.classList.contains('edit-btn')) {
                const periodeIdAsli = target.dataset.periodeId; // Pastikan data-periode-id ada di tombol edit

                // Cari data periode asli dari filter utama
                const sourceOption = masterPeriodeSelect.querySelector(`option[value="${periodeIdAsli}"]`);

                if (sourceOption) {
                    // Terapkan batasan tanggal berdasarkan periode asli dari data
                    editTanggalInput.min = sourceOption.dataset.mulai;
                    editTanggalInput.max = sourceOption.dataset.selesai;
                    editTanggalInput.disabled = false;
                }

                // Isi sisa form dengan data dari tombol
                document.getElementById('edit_id').value = target.dataset.id;
                document.getElementById('edit_tanggal').value = target.dataset.tanggal;
                document.getElementById('edit_jam_mulai').value = target.dataset.jamMulai;
                document.getElementById('edit_jam_selesai').value = target.dataset.jamSelesai;

                openModal(modals.edit);
            }

            // === LOGIKA TOMBOL HAPUS (tetap sama) ===
            if (target.classList.contains('hapus-btn')) {
                document.getElementById('hapus_id').value = target.dataset.id;
                document.getElementById('hapus_info').textContent = target.dataset.info;
                openModal(modals.hapus);
            }
        });

        // Notifikasi otomatis hilang
        const autoHideAlert = (alertId) => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                setTimeout(() => {
                    alertElement.style.transition = 'opacity 0.5s ease';
                    alertElement.style.opacity = '0';
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        };
        autoHideAlert('success-alert');
        autoHideAlert('error-alert');
    });
</script>