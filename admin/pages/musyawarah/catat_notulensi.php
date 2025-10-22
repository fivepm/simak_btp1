<?php
$success_message = '';
$error_message = '';
$redirect_url = ''; // Inisialisasi variabel redirect
$id_musyawarah = $_GET['id'] ?? null;


// Definisikan daftar unit KMM
$daftar_unit_kmm = ['KMM Banguntapan 1', 'KMM Bintaran', 'KMM Gedongkuning', 'KMM Jombor', 'KMM Sunten'];

// ===================================================================
// BAGIAN 1: LOGIKA PEMROSESAN FORM (TAMBAH & HAPUS POIN)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Aksi untuk menambah poin baru
    if ($action === 'tambah_poin') {
        $poin_pembahasan = trim($_POST['poin_pembahasan'] ?? '');

        if (!empty($poin_pembahasan)) {
            $stmt = $conn->prepare("INSERT INTO notulensi_poin (id_musyawarah, poin_pembahasan) VALUES (?, ?)");
            $stmt->bind_param("is", $id_musyawarah, $poin_pembahasan);
            if ($stmt->execute()) {
                $success_message = "Poin notulensi berhasil ditambahkan.";
            } else {
                $error_message = "Gagal menambahkan poin: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Poin pembahasan tidak boleh kosong.";
        }
    }

    // Aksi untuk menghapus poin
    elseif ($action === 'hapus_poin') {
        $id_poin = $_POST['id_poin'] ?? null;
        if ($id_poin && filter_var($id_poin, FILTER_VALIDATE_INT)) {
            // Klausa WHERE id_musyawarah ditambahkan sebagai lapisan keamanan tambahan
            $stmt = $conn->prepare("DELETE FROM notulensi_poin WHERE id = ? AND id_musyawarah = ?");
            $stmt->bind_param("ii", $id_poin, $id_musyawarah);
            if ($stmt->execute()) {
                $success_message = "Poin notulensi berhasil dihapus.";
            } else {
                $error_message = "Gagal menghapus poin: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "ID poin tidak valid.";
        }
    }

    // --- AKSI BARU: Simpan Laporan Kelompok ---
    elseif ($action === 'simpan_laporan_kelompok') {
        $laporan_data = $_POST['laporan'] ?? [];
        $berhasil = true;

        // Query cerdas: Insert baru jika belum ada, update jika sudah ada
        $stmt = $conn->prepare("
            INSERT INTO musyawarah_laporan_kelompok (id_musyawarah, nama_kelompok, isi_laporan) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE isi_laporan = VALUES(isi_laporan)
        ");

        foreach ($laporan_data as $kelompok => $isi_laporan) {
            $stmt->bind_param("iss", $id_musyawarah, $kelompok, $isi_laporan);
            if (!$stmt->execute()) {
                $berhasil = false;
                $error_message = "Gagal menyimpan laporan untuk kelompok " . htmlspecialchars($kelompok) . ".";
                break; // Hentikan jika ada satu yang gagal
            }
        }
        $stmt->close();

        if ($berhasil) {
            $success_message = "Semua laporan kelompok berhasil disimpan.";
        }
    }

    // --- AKSI BARU: Simpan Laporan KMM ---
    elseif ($action === 'simpan_laporan_kmm') {
        $laporan_kmm_data = $_POST['laporan_kmm'] ?? []; // Gunakan nama unik dari form KMM
        $berhasil_kmm = true;

        // Query cerdas untuk tabel KMM
        $stmt_kmm = $conn->prepare("
            INSERT INTO musyawarah_laporan_kmm (id_musyawarah, nama_kmm, isi_laporan) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE isi_laporan = VALUES(isi_laporan)
        ");

        foreach ($laporan_kmm_data as $nama_kmm => $isi_laporan) {
            // Pastikan hanya unit KMM yang valid yang disimpan

            if (in_array($nama_kmm, $daftar_unit_kmm)) {
                $stmt_kmm->bind_param("iss", $id_musyawarah, $nama_kmm, $isi_laporan);
                if (!$stmt_kmm->execute()) {
                    $berhasil_kmm = false;
                    $error_message = "Gagal menyimpan laporan untuk " . htmlspecialchars($nama_kmm) . ". Error: " . $stmt_kmm->error;
                    break;
                }
            }
        }
        $stmt_kmm->close();

        if ($berhasil_kmm) {
            $success_message = "Semua laporan KMM berhasil disimpan.";
        }
    }
}


// ===================================================================
// BAGIAN 2: PENGAMBILAN DATA UNTUK TAMPILAN
// ===================================================================

// Ambil detail musyawarah untuk judul halaman
$stmt_musyawarah = $conn->prepare("SELECT * FROM musyawarah WHERE id = ?");
$stmt_musyawarah->bind_param("i", $id_musyawarah);
$stmt_musyawarah->execute();
$musyawarah = $stmt_musyawarah->get_result()->fetch_assoc();
$stmt_musyawarah->close();

// if (!$musyawarah) {
//     header('Location: daftar_musyawarah.php?status=error&msg=' . urlencode('Musyawarah tidak ditemukan.'));
//     exit();
// }

// Ambil semua poin notulensi yang sudah ada untuk musyawarah ini
$stmt_poin = $conn->prepare("SELECT * FROM notulensi_poin WHERE id_musyawarah = ? ORDER BY id ASC");
$stmt_poin->bind_param("i", $id_musyawarah);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$stmt_poin->close();

// --- PENGAMBILAN DATA BARU: Laporan Kelompok ---
$laporan_kelompok_tersimpan = [];
$stmt_laporan = $conn->prepare("SELECT nama_kelompok, isi_laporan FROM musyawarah_laporan_kelompok WHERE id_musyawarah = ?");
$stmt_laporan->bind_param("i", $id_musyawarah);
$stmt_laporan->execute();
$result_laporan = $stmt_laporan->get_result();
while ($row = $result_laporan->fetch_assoc()) {
    $laporan_kelompok_tersimpan[$row['nama_kelompok']] = $row['isi_laporan'];
}
$stmt_laporan->close();

// Daftar kelompok yang ada
$daftar_kelompok = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];

// Ambil Laporan KMM dari tabel BARU
$laporan_kmm_tersimpan = [];
$stmt_laporan_kmm = $conn->prepare("SELECT nama_kmm, isi_laporan FROM musyawarah_laporan_kmm WHERE id_musyawarah = ?");
$stmt_laporan_kmm->bind_param("i", $id_musyawarah);
$stmt_laporan_kmm->execute();
$result_laporan_kmm = $stmt_laporan_kmm->get_result();
while ($row_kmm = $result_laporan_kmm->fetch_assoc()) {
    $laporan_kmm_tersimpan[$row_kmm['nama_kmm']] = $row_kmm['isi_laporan'];
}
$stmt_laporan_kmm->close();

?>

<!-- Di sini Anda bisa menyertakan header atau layout utama admin -->
<div class="p-6">
    <!-- Header Halaman -->
    <div class="mb-6">
        <a href="?page=musyawarah/daftar_notulensi" class="text-cyan-600 hover:text-cyan-800 transition-colors">
            &larr; Kembali ke Daftar Notulensi
        </a>
        <h1 class="text-3xl font-bold text-gray-800 mt-2">Notulensi Musyawarah</h1>
        <p class="text-gray-600">
            <?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?> -
            <span class="font-medium"><?php echo date('d F Y', strtotime($musyawarah['tanggal'])); ?></span>
        </p>
    </div>

    <?php if (!empty($success_message)): ?><div id="success-alert" class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $success_message; ?></span></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div id="error-alert" class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert"><span class="block sm:inline"><?php echo $error_message; ?></span></div><?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Detail Musyawarah -->
        <div class="mb-6 pb-4 border-b">
            <h2 class="text-2xl font-semibold text-gray-700 mb-2"><?= htmlspecialchars($musyawarah['nama_musyawarah']) ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 text-gray-600 gap-2">
                <p><i class="fas fa-calendar-alt mr-2 text-cyan-500"></i><?= date('l, d F Y', strtotime($musyawarah['tanggal'])) ?></p>
                <p><i class="fas fa-clock mr-2 text-cyan-500"></i>Pukul <?= date('H:i', strtotime($musyawarah['waktu_mulai'])) ?> WIB</p>
                <p><i class="fas fa-user-tie mr-2 text-cyan-500"></i>Pimpinan: <?= htmlspecialchars($musyawarah['pimpinan_rapat']) ?></p>
                <p><i class="fas fa-map-marker-alt mr-2 text-cyan-500"></i>Tempat: <?= htmlspecialchars($musyawarah['tempat']) ?></p>
            </div>
        </div>

        <!-- --- KARTU BARU: LAPORAN KELOMPOK --- -->
        <div class="bg-white border border-gray-500 p-6 rounded-2xl shadow-lg mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-3">Laporan PJP Kelompok</h2>
            <form method="POST" action="?page=musyawarah/catat_notulensi&id=<?php echo $id_musyawarah; ?>">
                <input type="hidden" name="action" value="simpan_laporan_kelompok">

                <div class="space-y-6">
                    <?php foreach ($daftar_kelompok as $kelompok): ?>
                        <div>
                            <label for="laporan_<?php echo strtolower($kelompok); ?>" class="block text-lg font-semibold text-gray-700 mb-2">
                                Kelompok <?php echo htmlspecialchars($kelompok); ?>
                            </label>
                            <textarea
                                id="laporan_<?php echo strtolower($kelompok); ?>"
                                name="laporan[<?php echo htmlspecialchars($kelompok); ?>]"
                                class="w-full p-2 border border-black-500 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500"
                                rows="3"
                                placeholder="Ketik laporan dari kelompok <?php echo htmlspecialchars($kelompok); ?> di sini..."><?php echo htmlspecialchars($laporan_kelompok_tersimpan[$kelompok] ?? ''); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end mt-6 border-t pt-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300 flex items-center">
                        <i class="fas fa-save mr-2"></i> Simpan Semua Laporan
                    </button>
                </div>
            </form>
        </div>

        <!-- --- KARTU BARU: LAPORAN KMM --- -->
        <div class="bg-white border border-gray-500 p-6 rounded-2xl shadow-lg mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-3">Laporan KMM</h2>

            <form method="POST" action="?page=musyawarah/catat_notulensi&id=<?php echo $id_musyawarah; ?>">
                <input type="hidden" name="action" value="simpan_laporan_kmm">

                <div class="space-y-6">
                    <?php foreach ($daftar_unit_kmm as $unit_kmm): ?>
                        <div>
                            <label for="laporan_kmm_<?php echo strtolower(str_replace(' ', '_', $unit_kmm)); ?>" class="block text-lg font-semibold text-gray-700 mb-2">
                                <?php echo htmlspecialchars($unit_kmm); ?>
                            </label>
                            <textarea
                                id="laporan_kmm_<?php echo strtolower(str_replace(' ', '_', $unit_kmm)); ?>"
                                name="laporan_kmm[<?php echo htmlspecialchars($unit_kmm); ?>]"
                                class="w-full p-2 border border-black-500 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500"
                                rows="6"
                                placeholder="Laporan <?php echo htmlspecialchars($unit_kmm); ?>..."><?php echo htmlspecialchars($laporan_kmm_tersimpan[$unit_kmm] ?? ''); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end mt-6 border-t pt-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300 flex items-center">
                        <i class="fas fa-save mr-2"></i> Simpan Laporan KMM
                    </button>
                </div>
            </form>
        </div>

        <!-- Form Pencatatan -->
        <div class="bg-white border border-gray-500 p-6 rounded-2xl shadow-lg">
            <!-- Form untuk Menambah Poin Baru -->
            <div class="mb-8 border-b pb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah Poin Hasil Musyawarah</h2>
                <form method="POST" action="" class="flex items-start gap-4">
                    <input type="hidden" name="action" value="tambah_poin">
                    <textarea name="poin_pembahasan" class="flex-grow mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-cyan-500 focus:border-cyan-500" rows="3" placeholder="Ketik hasil pembahasan atau keputusan musyawarah di sini..." required></textarea>
                    <button type="submit" class="bg-cyan-500 hover:bg-cyan-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 self-center">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>
            </div>

            <!-- Daftar Poin yang Sudah Ada -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 mb-4">Hasil Musyawarah</h2>
                <div class="space-y-3">
                    <?php if ($result_poin->num_rows > 0): ?>
                        <?php $nomor = 1; ?>
                        <?php while ($poin = $result_poin->fetch_assoc()): ?>
                            <div class="flex items-start justify-between p-4 bg-gray-50 rounded-lg border">
                                <div class="flex items-start">
                                    <span class="font-bold text-gray-500 mr-3"><?php echo $nomor++; ?>.</span>
                                    <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($poin['poin_pembahasan'])); ?></p>
                                </div>
                                <form method="POST" action="" onsubmit="return confirm('Anda yakin ingin menghapus poin ini?');" class="ml-4">
                                    <input type="hidden" name="action" value="hapus_poin">
                                    <input type="hidden" name="id_poin" value="<?php echo $poin['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 transition-colors" title="Hapus Poin">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center py-4 text-gray-500">Belum ada poin notulensi yang ditambahkan untuk musyawarah ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php $conn->close(); ?>

<!-- Script untuk redirect setelah form submit -->
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
    });
</script>

<!-- Di sini Anda bisa menyertakan footer -->