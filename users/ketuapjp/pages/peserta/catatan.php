<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : null;
$siswa = null;

if ($selected_peserta_id) {
    $siswa = $conn->query("SELECT * FROM peserta WHERE id = $selected_peserta_id")->fetch_assoc();
}

// === AMBIL DATA DARI DATABASE ===
// === AMBIL DAFTAR PESERTA (DROPDOWN) ===
$peserta_list = [];
$sql_peserta = "SELECT id, nama_lengkap, kelas, kelompok FROM peserta WHERE status = 'Aktif'";
if ($ketuapjp_tingkat === 'kelompok') {
    $sql_peserta .= " AND kelompok = ?";
}
$sql_peserta .= " ORDER BY nama_lengkap ASC";
$stmt_peserta = $conn->prepare($sql_peserta);
if ($ketuapjp_tingkat === 'kelompok') {
    $stmt_peserta->bind_param("s", $ketuapjp_kelompok);
}
$stmt_peserta->execute();
$result_peserta = $stmt_peserta->get_result();
if ($result_peserta) {
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}

// === AMBIL DATA CATATAN (LOGIKA BARU) ===
$catatan_bk_list = [];

if ($selected_peserta_id) {
    // --- KASUS A: Jika Siswa Dipilih (Tampilkan khusus siswa itu) ---
    $sql_catatan = "SELECT cb.*, u.nama as nama_pencatat 
                    FROM catatan_bk cb 
                    LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id
                    WHERE cb.peserta_id = ? 
                    ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC";
    $stmt_catatan = $conn->prepare($sql_catatan);
    $stmt_catatan->bind_param("i", $selected_peserta_id);
    $stmt_catatan->execute();
    $result_catatan = $stmt_catatan->get_result();
} else {
    // --- KASUS B: Jika Tidak Ada Siswa Dipilih (Tampilkan SEMUA) ---
    $sql_catatan = "SELECT cb.*, u.nama as nama_pencatat, p.nama_lengkap, p.kelas, p.kelompok, p.id as real_peserta_id
                    FROM catatan_bk cb 
                    JOIN peserta p ON cb.peserta_id = p.id
                    LEFT JOIN users u ON cb.dicatat_oleh_user_id = u.id
                    WHERE 1=1";

    $params = [];
    $types = "";

    // Filter berdasarkan hak akses admin
    if ($ketuapjp_tingkat === 'kelompok') {
        $sql_catatan .= " AND p.kelompok = ?";
        $params[] = $ketuapjp_kelompok;
        $types .= "s";
    }

    $sql_catatan .= " ORDER BY cb.tanggal_catatan DESC, cb.created_at DESC LIMIT 50"; // Limit 50 agar tidak berat

    $stmt_catatan = $conn->prepare($sql_catatan);
    if (!empty($params)) {
        $stmt_catatan->bind_param($types, ...$params);
    }
    $stmt_catatan->execute();
    $result_catatan = $stmt_catatan->get_result();
}

if ($result_catatan) {
    while ($row = $result_catatan->fetch_assoc()) {
        $catatan_bk_list[] = $row;
    }
}
?>
<div class="container mx-auto space-y-6">
    <!-- FILTER PESERTA -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Pilih Peserta</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="peserta/catatan">
            <div class="flex items-center gap-4">
                <select name="peserta_id" onchange="this.form.submit()" class="flex-grow mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm">
                    <option value="">-- Tampilkan Semua Catatan (Terbaru) --</option>
                    <?php foreach ($peserta_list as $peserta): ?>
                        <option value="<?php echo $peserta['id']; ?>" <?php echo ($selected_peserta_id === (int)$peserta['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($peserta['nama_lengkap'] . ' (' . ucfirst($peserta['kelas']) . ' - ' . ucfirst($peserta['kelompok']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- KONTEN UTAMA -->
    <?php if ($selected_peserta_id && $siswa): ?>

        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Catatan: <span class="text-indigo-600"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></span></h3>
            <?php renderCatatanList($catatan_bk_list, false); // false = jangan tampilkan nama siswa di card 
            ?>
        </div>

    <?php else: ?>

        <!-- TAMPILAN SEMUA CATATAN (FULL WIDTH) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Timeline Catatan Terbaru (Semua Siswa)</h3>
            <?php renderCatatanList($catatan_bk_list, true); // true = tampilkan nama siswa di card 
            ?>
        </div>

    <?php endif; ?>
</div>

<?php
// FUNGSI HELPER UNTUK RENDER LIST (SUPAYA TIDAK DUPLIKASI KODE)
function renderCatatanList($list, $showName)
{
    if (empty($list)) {
        echo '<div class="text-center py-10 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">Belum ada catatan yang ditemukan.</p>
              </div>';
        return;
    }

    echo '<div class="space-y-4">';
    foreach ($list as $catatan) {
        $namaSiswaHTML = '';
        if ($showName) {
            $namaSiswaHTML = '
                <div class="mb-2 pb-2 border-b border-indigo-100 flex justify-between items-center">
                    <span class="font-bold text-lg text-indigo-700">' . htmlspecialchars($catatan['nama_lengkap']) . '</span>
                    <span class="text-xs font-semibold bg-indigo-50 text-indigo-600 px-2 py-1 rounded">' . ucfirst($catatan['kelas']) . ' - ' . ucfirst($catatan['kelompok']) . '</span>
                </div>
            ';
        }

        // Siapkan data JSON untuk JS
        // Penting: Jika ini tampilan "Semua", kita perlu menyisipkan real_peserta_id ke dalam data JSON agar modal edit tahu ID pesertanya
        if (isset($catatan['real_peserta_id'])) {
            $catatan['peserta_id'] = $catatan['real_peserta_id'];
        }
        $jsonData = htmlspecialchars(json_encode($catatan), ENT_QUOTES, 'UTF-8');

        echo '
        <div class="border-l-4 border-indigo-500 pl-5 py-4 bg-white hover:bg-gray-50 transition rounded-r-lg shadow-sm border border-gray-100">
            ' . $namaSiswaHTML . '
            <div class="flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fa-regular fa-calendar text-gray-400"></i>
                        <p class="font-bold text-gray-800">' . date("d M Y", strtotime($catatan['tanggal_catatan'])) . '</p>
                    </div>
                    <p class="text-xs text-gray-500 mb-2">Dicatat oleh: <span class="font-medium text-gray-700">' . htmlspecialchars($catatan['nama_pencatat'] ?? 'System') . '</span></p>
                </div>
            </div>
            
            <div class="mt-3">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Permasalahan</p>
                <p class="text-sm text-gray-800 whitespace-pre-wrap mt-1 leading-relaxed">' . htmlspecialchars($catatan['permasalahan']) . '</p>
            </div>
            
            <div class="mt-4 pt-3 border-t border-gray-100">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Tindak Lanjut</p>
                ' . (!empty($catatan['tindak_lanjut'])
            ? '<p class="text-sm text-gray-800 whitespace-pre-wrap mt-1 leading-relaxed">' . htmlspecialchars($catatan['tindak_lanjut']) . '</p>'
            : '-'
        ) . '
            </div>
        </div>';
    }
    echo '</div>';
}
?>