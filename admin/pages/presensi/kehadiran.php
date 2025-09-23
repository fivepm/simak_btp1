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

// Ambil data rekap jika semua filter terisi
$rekap_data = [];
$detail_kehadiran = [];
$tanggal_jadwal = [];

if ($selected_periode_id && $selected_kelompok && $selected_kelas) {
    // 1. Ambil data ringkasan (summary)
    // $sql_summary = "SELECT 
    //             p.nama_lengkap,
    //             COUNT(rp.id) as total_pertemuan,
    //             -- SUM(CASE WHEN rp.status_kehadiran IS NOT NULL THEN 1 ELSE 0 END), 0) as terisi,
    //             SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
    //             SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
    //             SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
    //             SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
    //             IF(COUNT(rp.id) > 0, (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.status_kehadiran)) * 100, 0) as persentase
    //         FROM peserta p
    //         JOIN rekap_presensi rp ON p.id = rp.peserta_id
    //         JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
    //         WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
    //         GROUP BY p.id, p.nama_lengkap
    //         ORDER BY p.nama_lengkap ASC";
    $sql_summary = "SELECT 
    p.nama_lengkap,
    COUNT(rp.id) as total_pertemuan,
    
    -- Hitung komponen kehadiran seperti biasa
    -- Fungsi SUM akan menghasilkan 0 jika tidak ada data, yang sudah benar
    SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
    SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
    
    -- COUNT(rp.id) akan menghasilkan 0 untuk siswa tanpa rekap, yang sudah benar
    COUNT(rp.id) as total_diisi,

    -- Rumus persentase ini tetap akurat
    IF(COUNT(rp.id) > 0, 
       (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.status_kehadiran)) * 100, 
       0
    ) as persentase

-- --- INI BAGIAN UTAMA YANG DIPERBAIKI ---
FROM 
    peserta p
LEFT JOIN 
    rekap_presensi rp ON p.id = rp.peserta_id
LEFT JOIN 
    -- Filter periode dan jadwal harus ada di dalam ON clause pada LEFT JOIN
    jadwal_presensi jp ON rp.jadwal_id = jp.id AND jp.periode_id = ?
WHERE 
    -- Filter utama untuk memilih siswa dari kelas mana
    p.kelompok = ? AND p.kelas = ?
-- --- AKHIR PERBAIKAN ---
GROUP BY 
    p.id, p.nama_lengkap
ORDER BY 
    p.nama_lengkap ASC";
    $stmt_summary = $conn->prepare($sql_summary);
    $stmt_summary->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_summary->execute();
    $result_summary = $stmt_summary->get_result();

    // BARU: Langkah 1 - Siapkan variabel untuk total
    $total_persentase_kelas = 0;
    if ($result_summary) {
        while ($row = $result_summary->fetch_assoc()) {
            $rekap_data[] = $row;

            // BARU: Langkah 2 - Jumlahkan persentase setiap peserta
            $total_persentase_kelas += $row['persentase'];
        }
    }
    // BARU: Langkah 3 - Hitung rata-ratanya setelah loop selesai
    $jumlah_peserta = count($rekap_data);
    $rata_rata_kehadiran = 0; // Default value

    if ($jumlah_peserta > 0) {
        $rata_rata_kehadiran = $total_persentase_kelas / $jumlah_peserta;
    }

    // 2. Ambil tanggal-tanggal pertemuan untuk header tabel detail
    $sql_tanggal = "SELECT DISTINCT tanggal FROM jadwal_presensi WHERE periode_id = ? AND kelompok = ? AND kelas = ? ORDER BY tanggal ASC";
    $stmt_tanggal = $conn->prepare($sql_tanggal);
    $stmt_tanggal->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_tanggal->execute();
    $result_tanggal = $stmt_tanggal->get_result();
    if ($result_tanggal) {
        while ($row = $result_tanggal->fetch_assoc()) {
            $tanggal_jadwal[] = $row['tanggal'];
        }
    }

    // 3. Ambil data detail kehadiran per tanggal
    $sql_detail = "SELECT p.nama_lengkap, jp.tanggal, rp.status_kehadiran 
                   FROM rekap_presensi rp
                   JOIN peserta p ON rp.peserta_id = p.id
                   JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                   WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
                   ORDER BY p.nama_lengkap, jp.tanggal ASC";
    $stmt_detail = $conn->prepare($sql_detail);
    $stmt_detail->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();
    if ($result_detail) {
        while ($row = $result_detail->fetch_assoc()) {
            $detail_kehadiran[$row['nama_lengkap']][$row['tanggal']] = $row['status_kehadiran'];
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
                <div><label class="block text-sm font-medium">Kelas</label>
                    <select name="kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md" required>
                        <?php
                        $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k):
                        ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>><?php echo ucfirst($k); ?></option>
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
            <div class="mb-4 border-b pb-4 text-center">
                <h2 class="text-xl font-bold text-gray-800">Rekapitulasi Kehadiran</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Kelompok: <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelompok); ?></span> |
                    Kelas: <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelas); ?></span> |
                    Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span>
                </p>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-black-500">Nama Peserta</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">Total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">Hadir</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">Izin</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">Sakit</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">Alpa</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekap_data)): ?><tr>
                            <td colspan="8" class="text-center py-4">Tidak ada data rekap untuk filter yang dipilih.</td>
                        </tr>
                        <?php else: $i = 1;
                        foreach ($rekap_data as $rekap): ?>
                            <tr>
                                <td class="px-6 py-4 text-center"><?php echo $i++; ?></td>
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($rekap['nama_lengkap']); ?></td>
                                <td class="px-6 py-4 text-center"><?php echo $rekap['total_pertemuan']; ?></td>
                                <td class="px-6 py-4 text-center text-green-600 font-semibold"><?php echo $rekap['hadir']; ?></td>
                                <td class="px-6 py-4 text-center text-blue-600 font-semibold"><?php echo $rekap['izin']; ?></td>
                                <td class="px-6 py-4 text-center text-yellow-600 font-semibold"><?php echo $rekap['sakit']; ?></td>
                                <td class="px-6 py-4 text-center text-red-600 font-semibold"><?php echo $rekap['alpa']; ?></td>
                                <td class="px-6 py-4 text-center font-bold text-lg 
                                    <?php
                                    if ($rekap['persentase'] >= 80) echo 'text-green-600';
                                    elseif ($rekap['persentase'] >= 60) echo 'text-yellow-600';
                                    else echo 'text-red-600';
                                    ?>">
                                    <?php echo $rekap['persentase'] ? round($rekap['persentase']) : '0'; ?>%
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold text-gray-800">
                        <td colspan="7" class="text-center px-4 py-3">Rata-rata Kehadiran Kelas</td>
                        <td class="text-center px-4 py-3">
                            <?php echo number_format($rata_rata_kehadiran, 2); ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- TABEL RINCIAN BARU -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-4 text-center">Rincian Kehadiran per Tanggal</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">No.</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 sticky left-0 bg-gray-50 z-10">Nama Peserta</th>
                        <?php foreach ($tanggal_jadwal as $tanggal): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500"><?php echo date('d/m', strtotime($tanggal)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($detail_kehadiran)): ?>
                        <tr>
                            <td colspan="<?php echo count($tanggal_jadwal) + 1; ?>" class="text-center py-4">Tidak ada rincian kehadiran.</td>
                        </tr>
                        <?php
                    else:
                        $i = 1;
                        foreach ($detail_kehadiran as $nama => $kehadiran): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-center"><?php echo $i++; ?></td>
                                <td class="px-4 py-3 font-medium text-gray-900 sticky left-0 bg-white hover:bg-gray-50 z-10"><?php echo htmlspecialchars($nama); ?></td>
                                <?php foreach ($tanggal_jadwal as $tanggal):
                                    $status = $kehadiran[$tanggal] ?? '-';
                                    $color = 'text-gray-400';
                                    if ($status === 'Hadir') $color = 'text-green-600';
                                    if ($status === 'Izin') $color = 'text-blue-600';
                                    if ($status === 'Sakit') $color = 'text-yellow-600';
                                    if ($status === 'Alpa') $color = 'text-red-600';
                                ?>
                                    <td class="px-4 py-3 text-center font-semibold <?php echo $color; ?>"><?php echo substr($status, 0, 1); ?></td>
                                <?php endforeach; ?>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>