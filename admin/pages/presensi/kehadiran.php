<?php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// Ambil filter dari URL
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : null;
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : null;

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

// Ambil daftar periode aktif
$periode_list = [];
$sql_periode = "SELECT id, nama_periode FROM periode WHERE status != 'Arsip' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// Ambil data rekap jika semua filter terisi
$rekap_data = [];
if ($selected_periode_id && $selected_kelompok && $selected_kelas) {
    // PERUBAHAN 1: Tambahkan perhitungan persentase di query SQL
    $sql = "SELECT 
                p.nama_lengkap,
                COUNT(rp.id) as total_pertemuan,
                SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                -- Rumus persentase: (hadir / total) * 100, hindari pembagian dengan nol
                IF(COUNT(rp.id) > 0, (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.id)) * 100, 0) as persentase
            FROM peserta p
            JOIN rekap_presensi rp ON p.id = rp.peserta_id
            JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
            WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
            GROUP BY p.id, p.nama_lengkap
            ORDER BY p.nama_lengkap ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rekap_data[] = $row;
        }
    }
}
?>
<div class="container mx-auto space-y-6">
    <!-- BAGIAN 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-medium text-gray-800 mb-4">Filter Rekapitulasi Kehadiran</h3>
        <form method="GET" action="">
            <input type="hidden" name="page" value="presensi/kehadiran">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium">Periode</label><select name="periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required><?php foreach ($periode_list as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_periode']); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-medium">Kelompok</label>
                    <?php if ($admin_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($admin_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md" disabled><input type="hidden" name="kelompok" value="<?php echo $admin_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium">Kelas</label>
                    <select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <?php $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <?php if ($admin_tingkat === 'kelompok' && $k === 'remaja') continue; ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($k); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end"><button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Tampilkan Rekap</button></div>
            </div>
        </form>
    </div>

    <!-- BAGIAN 2: TABEL REKAP -->
    <?php if ($selected_periode_id && $selected_kelompok && $selected_kelas): ?>
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Nama Peserta</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Total Pertemuan</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Hadir</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Izin</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Sakit</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Alpa</th>
                        <!-- PERUBAHAN 2: Tambah kolom header baru -->
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Persentase Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekap_data)): ?><tr>
                            <td colspan="8" class="text-center py-4">Tidak ada data rekap untuk filter yang dipilih.</td>
                        </tr>
                        <?php else: $i = 1;
                        foreach ($rekap_data as $rekap): ?>
                            <tr>
                                <td class="px-6 py-4"><?php echo $i++; ?></td>
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($rekap['nama_lengkap']); ?></td>
                                <td class="px-6 py-4 text-center"><?php echo $rekap['total_pertemuan']; ?></td>
                                <td class="px-6 py-4 text-center text-green-600 font-semibold"><?php echo $rekap['hadir']; ?></td>
                                <td class="px-6 py-4 text-center text-blue-600 font-semibold"><?php echo $rekap['izin']; ?></td>
                                <td class="px-6 py-4 text-center text-yellow-600 font-semibold"><?php echo $rekap['sakit']; ?></td>
                                <td class="px-6 py-4 text-center text-red-600 font-semibold"><?php echo $rekap['alpa']; ?></td>
                                <!-- PERUBAHAN 3: Tambah kolom data baru -->
                                <td class="px-6 py-4 text-center font-bold text-lg 
                            <?php
                            if ($rekap['persentase'] >= 80) echo 'text-green-600';
                            elseif ($rekap['persentase'] >= 60) echo 'text-yellow-600';
                            else echo 'text-red-600';
                            ?>">
                                    <?php echo round($rekap['persentase']); ?>%
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>