<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : null;
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : null;

// Jika admin tingkat kelompok, paksa filter kelompok
if ($ketuapjp_tingkat === 'kelompok') {
    $selected_kelompok = $ketuapjp_kelompok;
}

// === AMBIL DATA DARI DATABASE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

$jadwal_list = [];
if ($selected_periode_id && $selected_kelompok && $selected_kelas) {
    $sql_jadwal = "SELECT jp.*, g.nama as nama_pengajar , p.nama as nama_penasehat
                   FROM jadwal_presensi jp 
                   LEFT JOIN guru g ON jp.guru_id = g.id
                   LEFT JOIN penasehat p ON jp.penasehat_id = p.id
                   WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ? 
                   ORDER BY jp.tanggal DESC, jp.jam_mulai DESC";
    $stmt_jadwal = $conn->prepare($sql_jadwal);
    $stmt_jadwal->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    if ($result_jadwal) {
        while ($row = $result_jadwal->fetch_assoc()) {
            $jadwal_list[] = $row;
        }
    }
}

?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Jadwal</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/jadwal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label><select name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required><?php foreach ($periode_list as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($ketuapjp_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($ketuapjp_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled><input type="hidden" name="kelompok" value="<?php echo $ketuapjp_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><label class="block text-sm font-medium">Kelas</label><select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required><?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                                                                                                                                                                                foreach ($kelas_opts as $k): ?><?php if ($ketuapjp_tingkat === 'kelompok' && $k === 'remaja') continue; ?><option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option><?php endforeach; ?></select></div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: MANAJEMEN JADWAL -->
    <?php if ($selected_periode_id && $selected_kelompok && $selected_kelas): ?>
        <div id="jadwal-section">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-700 text-2xl font-medium">Daftar Jadwal</h3>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tanggal & Jam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Pengajar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Penasehat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jadwal_list)): ?><tr>
                                <td colspan="3" class="text-center py-4">Belum ada jadwal. Silakan tambahkan.</td>
                            </tr>
                            <?php else: foreach ($jadwal_list as $jadwal): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">
                                            <?php echo format_hari_tanggal($jadwal['tanggal']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo date("H:i", strtotime($jadwal['jam_mulai'])) . ' - ' . date("H:i", strtotime($jadwal['jam_selesai'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($jadwal['nama_pengajar'])): ?>
                                            <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($jadwal['nama_pengajar']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Belum Diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($jadwal['nama_penasehat'])): ?>
                                            <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($jadwal['nama_penasehat']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Belum Diatur</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {});
</script>