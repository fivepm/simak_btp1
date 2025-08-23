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
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : ($admin_tingkat === 'kelompok' ? $admin_kelompok : '');
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;

// === PROSES POST REQUEST (Update Hafalan) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peserta_id_form = $_POST['peserta_id'] ?? 0;
    $materi_yang_dicentang = $_POST['materi'] ?? [];
    $semua_materi_kurikulum = $_POST['semua_materi_kurikulum'] ?? [];

    if (!empty($peserta_id_form) && !empty($semua_materi_kurikulum)) {
        $conn->begin_transaction();
        try {
            // PERBAIKAN: Gunakan "INSERT ... ON DUPLICATE KEY UPDATE"
            $sql = "INSERT INTO progres_hafalan (peserta_id, materi_id, status_hafalan, tanggal_hafal) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE status_hafalan = VALUES(status_hafalan), tanggal_hafal = VALUES(tanggal_hafal)";

            $stmt = $conn->prepare($sql);

            // Loop melalui SEMUA materi yang ada di kurikulum
            foreach ($semua_materi_kurikulum as $materi_id) {
                // Cek apakah materi ini ada di dalam array yang dicentang
                $status_hafalan = in_array($materi_id, $materi_yang_dicentang) ? 1 : 0;
                $tanggal_hafal = ($status_hafalan == 1) ? date('Y-m-d') : null;

                $stmt->bind_param("iiss", $peserta_id_form, $materi_id, $status_hafalan, $tanggal_hafal);
                $stmt->execute();
            }

            $conn->commit();
            $success_message = "Progres hafalan berhasil diperbarui!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Gagal memperbarui progres: " . $e->getMessage();
        }
    }
}


// === AMBIL DATA DARI DATABASE ===
$peserta_list = [];
if (!empty($selected_kelompok) && !empty($selected_kelas)) {
    $sql_peserta = "SELECT id, nama_lengkap FROM peserta WHERE kelompok = ? AND kelas = ? AND status = 'Aktif' ORDER BY nama_lengkap ASC";
    $stmt_peserta = $conn->prepare($sql_peserta);
    $stmt_peserta->bind_param("ss", $selected_kelompok, $selected_kelas);
    $stmt_peserta->execute();
    $result_peserta = $stmt_peserta->get_result();
    if ($result_peserta) {
        while ($row = $result_peserta->fetch_assoc()) {
            $peserta_list[] = $row;
        }
    }
}

$kurikulum_list = [];
$progres_hafalan = [];
if ($selected_peserta_id) {
    // Ambil kurikulum untuk kelas peserta
    $kelas_peserta = $conn->query("SELECT kelas FROM peserta WHERE id = $selected_peserta_id")->fetch_assoc()['kelas'];
    if ($kelas_peserta) {
        $sql_kurikulum = "SELECT mh.id, mh.kategori, mh.nama_materi 
                          FROM kurikulum_hafalan kh
                          JOIN materi_hafalan mh ON kh.materi_id = mh.id
                          WHERE kh.kelas = ? ORDER BY mh.kategori, mh.nama_materi";
        $stmt_kurikulum = $conn->prepare($sql_kurikulum);
        $stmt_kurikulum->bind_param("s", $kelas_peserta);
        $stmt_kurikulum->execute();
        $result_kurikulum = $stmt_kurikulum->get_result();
        if ($result_kurikulum) {
            while ($row = $result_kurikulum->fetch_assoc()) {
                $kurikulum_list[$row['kategori']][] = $row;
            }
        }
    }

    // Ambil progres hafalan peserta
    $sql_progres = "SELECT materi_id FROM progres_hafalan WHERE peserta_id = ? AND status_hafalan = 1";
    $stmt_progres = $conn->prepare($sql_progres);
    $stmt_progres->bind_param("i", $selected_peserta_id);
    $stmt_progres->execute();
    $result_progres = $stmt_progres->get_result();
    if ($result_progres) {
        while ($row = $result_progres->fetch_assoc()) {
            $progres_hafalan[] = $row['materi_id'];
        }
    }
}
?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pilih Peserta</h3>
        <form id="filterForm" method="GET" action="">
            <input type="hidden" name="page" value="peserta/kartu_hafalan">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled>
                        <input type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" id="kelompok_filter" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="">-- Pilih --</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label><select name="kelas" id="kelas_filter" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <option value="">-- Pilih --</option><?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                                                                foreach ($kelas_opts as $k): ?><option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="block text-sm font-medium">Nama Peserta</label><select name="peserta_id" id="peserta_filter" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required <?php echo empty($peserta_list) ? 'disabled' : ''; ?>>
                        <option value="">-- Pilih Peserta --</option><?php foreach ($peserta_list as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo ($selected_peserta_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_lengkap']); ?></option><?php endforeach; ?>
                    </select></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: KARTU HAFALAN -->
    <?php if ($selected_peserta_id): ?>
        <div id="hafalan-section">
            <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error_message; ?></div><?php endif; ?>

            <form method="POST" action="?page=peserta/kartu_hafalan&kelompok=<?php echo $selected_kelompok; ?>&kelas=<?php echo $selected_kelas; ?>&peserta_id=<?php echo $selected_peserta_id; ?>">
                <input type="hidden" name="peserta_id" value="<?php echo $selected_peserta_id; ?>">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php $kategori_list = ['Surat', 'Doa', 'Dalil']; ?>
                        <?php foreach ($kategori_list as $kategori): ?>
                            <div>
                                <h4 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2"><?php echo $kategori; ?></h4>
                                <div class="space-y-3">
                                    <?php if (!empty($kurikulum_list[$kategori])): ?>
                                        <?php foreach ($kurikulum_list[$kategori] as $materi): ?>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="materi[]" value="<?php echo $materi['id']; ?>" class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                    <?php echo in_array($materi['id'], $progres_hafalan) ? 'checked' : ''; ?>>
                                                <span class="ml-3 text-gray-700"><?php echo htmlspecialchars($materi['nama_materi']); ?></span>
                                                <!-- Input hidden untuk mengirim semua materi yang ada di kartu -->
                                                <input type="hidden" name="semua_materi_kurikulum[]" value="<?php echo $materi['id']; ?>">
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 italic">Tidak ada materi <?php echo strtolower($kategori); ?> di kurikulum kelas ini.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 border-t pt-4 text-right">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg">Simpan Progres</button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>
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

        const kelompokFilter = document.getElementById('kelompok_filter');
        const kelasFilter = document.getElementById('kelas_filter');
        const pesertaFilter = document.getElementById('peserta_filter');
        const filterForm = document.getElementById('filterForm');

        // Submit form jika filter diubah
        if (kelompokFilter) kelompokFilter.addEventListener('change', () => filterForm.submit());
        if (kelasFilter) kelasFilter.addEventListener('change', () => filterForm.submit());
        if (pesertaFilter) pesertaFilter.addEventListener('change', () => filterForm.submit());
    });
</script>