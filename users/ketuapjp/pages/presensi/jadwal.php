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

$selected_periode_nama = '';
if ($selected_periode_id) {
    $stmt_periode_nama = $conn->prepare("SELECT nama_periode FROM periode WHERE id = ?");
    $stmt_periode_nama->bind_param("i", $selected_periode_id);
    $stmt_periode_nama->execute();
    $result_periode_nama = $stmt_periode_nama->get_result();
    if ($result_periode_nama->num_rows > 0) {
        $selected_periode_nama = $result_periode_nama->fetch_assoc()['nama_periode'];
    }
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
$rekap_petugas_data = [];

if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua') {
    // GANTI QUERY LAMA ANDA DENGAN YANG INI
    $sql = "SELECT 
                jp.id, jp.tanggal, jp.jam_mulai, jp.jam_selesai, jp.pengajar,
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
                                                                                                                                                                                foreach ($kelas_opts as $k): ?><option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option><?php endforeach; ?></select></div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan</button></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: MANAJEMEN JADWAL -->
    <!-- TABEL JADWAL -->
    <?php if ($selected_periode_id && $selected_kelompok !== 'semua' && $selected_kelas !== 'semua'): ?>
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-medium text-gray-800">Daftar Jadwal</h3>
                <button id="tambahJadwalBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">+ Tambah Jadwal</button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tanggal & Jam</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Pemateri</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Jurnal</th>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {});
</script>