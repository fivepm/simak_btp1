<?php
// Variabel $conn dan data session sudah tersedia dari index.php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

// Ambil data admin yang sedang login untuk hak akses
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// === AMBIL DATA STATISTIK DARI DATABASE ===

// Siapkan klausa WHERE untuk filter berdasarkan kelompok jika perlu
$where_clause = "";
$params = [];
$types = "";
if ($admin_tingkat === 'kelompok') {
    $where_clause = " WHERE kelompok = ? AND status = 'Aktif'";
    $params[] = $admin_kelompok;
    $types .= "s";
} else {
    $where_clause = " WHERE status = 'Aktif'";
}

// 1. Total Peserta
$total_peserta = 0;
$sql_total = "SELECT COUNT(id) as total FROM peserta" . $where_clause;
$stmt_total = $conn->prepare($sql_total);
if ($admin_tingkat === 'kelompok') {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
if ($result_total) {
    $total_peserta = $result_total->fetch_assoc()['total'] ?? 0;
}
$stmt_total->close();

// 2. Data Peserta per Kelas (Logika berbeda berdasarkan tingkat admin)
$peserta_per_kelas_rinci = [];
$peserta_per_kelas_total = [];

if ($admin_tingkat === 'desa') {
    // PERUBAHAN: Query sekarang mengelompokkan juga berdasarkan jenis kelamin
    $sql_kelas_rinci = "SELECT kelas, kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' GROUP BY kelas, kelompok, jenis_kelamin";
    $result_kelas_rinci = $conn->query($sql_kelas_rinci);
    if ($result_kelas_rinci) {
        while ($row = $result_kelas_rinci->fetch_assoc()) {
            // Buat array bersarang: ['kelas']['kelompok']['jenis_kelamin'] = jumlah
            $peserta_per_kelas_rinci[$row['kelas']][$row['kelompok']][$row['jenis_kelamin']] = $row['jumlah'];
        }
    }
} else { // admin_tingkat === 'kelompok'
    // Query total untuk admin kelompok
    $sql_kelas_total = "SELECT kelas, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' AND kelompok = ? GROUP BY kelas";
    $stmt_kelas_total = $conn->prepare($sql_kelas_total);
    $stmt_kelas_total->bind_param("s", $admin_kelompok);
    $stmt_kelas_total->execute();
    $result_kelas_total = $stmt_kelas_total->get_result();
    if ($result_kelas_total) {
        while ($row = $result_kelas_total->fetch_assoc()) {
            $peserta_per_kelas_total[$row['kelas']] = $row['jumlah'];
        }
    }
    $stmt_kelas_total->close();
}

// 3. Peserta per Jenis Kelamin
$peserta_per_jk = ['Laki-laki' => 0, 'Perempuan' => 0];
$sql_jk = "SELECT jenis_kelamin, COUNT(id) as jumlah FROM peserta" . $where_clause . " GROUP BY jenis_kelamin";
$stmt_jk = $conn->prepare($sql_jk);
if ($admin_tingkat === 'kelompok') {
    $stmt_jk->bind_param($types, ...$params);
}
$stmt_jk->execute();
$result_jk = $stmt_jk->get_result();
if ($result_jk) {
    while ($row = $result_jk->fetch_assoc()) {
        $peserta_per_jk[$row['jenis_kelamin']] = $row['jumlah'];
    }
}
$stmt_jk->close();

// 4. Hitung Grand Total untuk footer tabel (hanya untuk admin desa)
$grand_totals = [];
if ($admin_tingkat === 'desa') {
    $sql_grand_total = "SELECT kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta WHERE status = 'Aktif' GROUP BY kelompok, jenis_kelamin";
    $result_grand_total = $conn->query($sql_grand_total);
    if ($result_grand_total) {
        while ($row = $result_grand_total->fetch_assoc()) {
            $grand_totals[$row['kelompok']][$row['jenis_kelamin']] = $row['jumlah'];
        }
    }
}

?>
<div class="container">
    <!-- Judul Halaman -->
    <h3 class="text-gray-700 text-3xl font-medium">Dashboard</h3>
    <?php if ($admin_tingkat === 'kelompok'): ?>
        <p class="text-gray-500 mt-1">Menampilkan data untuk Kelompok <span class="font-semibold capitalize"><?php echo htmlspecialchars($admin_kelompok); ?></span></p>
    <?php endif; ?>

    <!-- Kartu Statistik Utama -->
    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <span class="text-sm font-semibold text-gray-500">Total Peserta Aktif</span>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $total_peserta; ?></p>
            </div>
            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <span class="text-sm font-semibold text-gray-500">Laki-laki</span>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_jk['Laki-laki']; ?></p>
            </div>
            <div class="bg-green-100 text-green-600 p-3 rounded-full">
                <i class="fa-solid fa-mars"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <span class="text-sm font-semibold text-gray-500">Perempuan</span>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_jk['Perempuan']; ?></p>
            </div>
            <div class="bg-pink-100 text-pink-600 p-3 rounded-full">
                <i class="fa-solid fa-venus"></i>
            </div>
        </div>
    </div>

    <div class="mt-8">
        <!-- Tampilan berbeda berdasarkan tingkat admin -->
        <?php if ($admin_tingkat === 'desa'): ?>
            <!-- PERBAIKAN: overflow-x-auto ditambahkan langsung ke kartu -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Rincian Peserta per Kelas dan Kelompok</h4>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th rowspan="2" class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase align-middle border-r">Kelas</th>
                                <?php $kelompok_list = ['bintaran', 'gedongkuning', 'jombor', 'sunten']; ?>
                                <?php foreach ($kelompok_list as $kelompok): ?>
                                    <th colspan="2" class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase border-l"><?php echo htmlspecialchars($kelompok); ?></th>
                                <?php endforeach; ?>
                                <th rowspan="2" class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase align-middle border-l">Total</th>
                            </tr>
                            <tr>
                                <?php foreach ($kelompok_list as $kelompok): ?>
                                    <th class="px-2 py-2 text-center text-xs font-bold text-gray-500 uppercase border-l">L</th>
                                    <th class="px-2 py-2 text-center text-xs font-bold text-gray-500 uppercase">P</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php $kelas_list = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                            <?php foreach ($kelas_list as $kelas):
                                $total_per_kelas = 0;
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap font-semibold capitalize text-gray-800 border-r"><?php echo htmlspecialchars($kelas); ?></td>
                                    <?php foreach ($kelompok_list as $kelompok):
                                        $jumlah_l = $peserta_per_kelas_rinci[$kelas][$kelompok]['Laki-laki'] ?? 0;
                                        $jumlah_p = $peserta_per_kelas_rinci[$kelas][$kelompok]['Perempuan'] ?? 0;
                                        $total_per_kelas += ($jumlah_l + $jumlah_p);
                                    ?>
                                        <td class="px-2 py-4 whitespace-nowrap text-center text-lg font-medium text-gray-700 border-l"><?php echo $jumlah_l; ?></td>
                                        <td class="px-2 py-4 whitespace-nowrap text-center text-lg font-medium text-gray-700"><?php echo $jumlah_p; ?></td>
                                    <?php endforeach; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-lg font-bold text-indigo-600 bg-indigo-50 border-l"><?php echo $total_per_kelas; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <!-- FOOTER TABEL BARU -->
                        <tfoot class="bg-gray-200 font-bold">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-800 border-r">TOTAL</td>
                                <?php $grand_total_semua = 0; ?>
                                <?php foreach ($kelompok_list as $kelompok):
                                    $total_l = $grand_totals[$kelompok]['Laki-laki'] ?? 0;
                                    $total_p = $grand_totals[$kelompok]['Perempuan'] ?? 0;
                                    $grand_total_semua += ($total_l + $total_p);
                                ?>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-lg text-gray-800 border-l"><?php echo $total_l; ?></td>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-lg text-gray-800"><?php echo $total_p; ?></td>
                                <?php endforeach; ?>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-lg text-indigo-700 bg-indigo-100 border-l"><?php echo $grand_total_semua; ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php else: // Tampilan Total untuk Admin Kelompok 
        ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Peserta per Kelas</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <?php $kelas_list = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah']; ?>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <div class="text-center bg-gray-50 p-4 rounded-lg">
                            <p class="capitalize text-sm font-semibold text-gray-500"><?php echo $kelas; ?></span>
                            <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_kelas_total[$kelas] ?? 0; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>