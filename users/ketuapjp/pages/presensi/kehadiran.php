<?php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

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

// Ambil data rekap jika semua filter terisi
$rekap_data = [];
$detail_kehadiran = [];
$tanggal_jadwal = [];

if ($selected_periode_id && $selected_kelompok && $selected_kelas) {
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
                        jadwal_presensi jp ON rp.jadwal_id = jp.id 
                    WHERE 
                        -- Filter utama untuk memilih siswa dari kelas mana
                        jp.periode_id = ? AND p.kelompok = ? AND p.kelas = ?
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

    // Ambil juga Jurnal dari jadwal_presensi
    $sql_rinci_siswa = "SELECT p.nama_lengkap, jp.tanggal, jp.jam_mulai, rp.status_kehadiran, rp.keterangan 
                        FROM rekap_presensi rp
                        JOIN peserta p ON rp.peserta_id = p.id
                        JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                        WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
                        ORDER BY p.nama_lengkap, jp.tanggal, jp.jam_mulai";
    $stmt_rinci_siswa = $conn->prepare($sql_rinci_siswa);
    // Bind parameter yang sama dengan kueri sebelumnya
    $stmt_rinci_siswa->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_rinci_siswa->execute();
    $result_rinci_siswa = $stmt_rinci_siswa->get_result();
    if ($result_rinci_siswa) {
        while ($row = $result_rinci_siswa->fetch_assoc()) {
            // Kelompokkan berdasarkan nama lengkap
            $rincian_per_siswa[$row['nama_lengkap']][] = $row;
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
                <h2 class="text-xl font-bold text-gray-800">Ringkasan Kehadiran</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Kelompok: <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelompok); ?></span> |
                    Kelas: <span class="font-semibold capitalize"><?php echo htmlspecialchars($selected_kelas); ?></span> |
                    Periode: <span class="font-semibold"><?php echo htmlspecialchars($selected_periode_nama); ?></span>
                </p>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Nama Peserta</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Hadir</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Izin</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Sakit</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Alpa</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500">Kehadiran</th>
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
        <!-- TABEL RINCIAN BARU -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-4 text-center">Rincian Kehadiran per Tanggal</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-medium text-black-500">No.</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-black-500 sticky left-0 z-10">Nama Peserta</th>
                        <?php foreach ($tanggal_jadwal as $tanggal): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-black-500"><?php echo date('d/m', strtotime($tanggal)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($detail_kehadiran)): ?>
                        <tr>
                            <td colspan="<?php echo count($tanggal_jadwal) + 2; ?>" class="text-center py-4">Tidak ada rincian kehadiran.</td>
                        </tr>
                        <?php
                    else:
                        $i = 1;
                        foreach ($detail_kehadiran as $nama => $kehadiran):
                        ?>
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-6 py-4 text-center"><?php echo $i++; ?></td>
                                <!-- Nama Sticky -->
                                <td class="px-4 py-3 font-medium text-gray-900 sticky left-0 bg-white hover:bg-gray-50 z-10 border-r border-gray-200">
                                    <?php echo htmlspecialchars($nama); ?>
                                </td>

                                <?php foreach ($tanggal_jadwal as $tanggal):
                                    // --- LOGIKA PEMBEDA NULL VS TIDAK ADA DATA ---

                                    // Cek 1: Apakah siswa ini punya jadwal di tanggal ini? (Ada di tabel rekap_presensi?)
                                    if (array_key_exists($tanggal, $kehadiran)) {

                                        // Ambil nilai statusnya
                                        $status_raw = $kehadiran[$tanggal];

                                        // Cek 2: Apakah nilainya NULL? (Artinya belum diinput)
                                        if ($status_raw === null) {
                                            $tampilan = '<i class="fa-solid fa-circle-question" title="Belum Diinput"></i>'; // Icon tanda tanya
                                            $color = 'text-orange-400'; // Warna peringatan
                                            $bg_cell = '';
                                        } else {
                                            // KASUS: Data Ada dan Sudah Diinput
                                            $tampilan = substr($status_raw, 0, 1); // Ambil huruf depan (H, I, S, A)
                                            $bg_cell = '';

                                            // Tentukan Warna
                                            if ($status_raw === 'Hadir') $color = 'text-green-600 font-bold';
                                            elseif ($status_raw === 'Izin') $color = 'text-blue-600 font-bold';
                                            elseif ($status_raw === 'Sakit') $color = 'text-yellow-600 font-bold';
                                            elseif ($status_raw === 'Alpa') $color = 'text-red-600 font-bold';
                                            else $color = 'text-gray-600';
                                        }
                                    } else {
                                        // KASUS: Data Tidak Ada sama sekali di database (Siswa belum masuk/sudah keluar)
                                        $tampilan = '-';
                                        $color = 'text-gray-300'; // Abu-abu pudar
                                        $bg_cell = 'bg-gray-50'; // Opsional: kasih background beda biar kelihatan kosong
                                    }
                                ?>

                                    <td class="px-4 py-3 text-center <?php echo $color . ' ' . $bg_cell; ?>">
                                        <?php echo $tampilan; ?>
                                    </td>

                                <?php endforeach; ?>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ========================================================== -->
        <!-- ▼▼▼ KARTU BARU: Rincian per Siswa ▼▼▼ -->
        <!-- ========================================================== -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-4 text-center">Rincian Kehadiran per Siswa</h2>

            <?php if (empty($rincian_per_siswa)): ?>
                <p class="text-center text-gray-500">Tidak ada data rincian untuk ditampilkan.</p>
            <?php else: ?>
                <div class="space-y-6">
                    <?php
                    // Ambil fungsi formatTanggalIndo jika ada (dari file export_handler.php)
                    if (!function_exists('formatTanggalIndo')) {
                        function formatTanggalIndo($tanggal_db)
                        {
                            if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
                            try {
                                $date = new DateTime($tanggal_db);
                                $bulan_indonesia = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                                return $date->format('j') . ' ' . $bulan_indonesia[(int)$date->format('n')] . ' ' . $date->format('Y');
                            } catch (Exception $e) {
                                return date('d/m/Y', strtotime($tanggal_db));
                            }
                        }
                    }
                    $nomor = 1;
                    foreach ($rincian_per_siswa as $nama => $records):
                    ?>
                        <div>
                            <!-- Judul Nama Siswa -->
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-3">
                                <?php echo $nomor++ . '. ' . htmlspecialchars($nama); ?>
                            </h3>
                            <!-- Tabel Rincian untuk Siswa Ini -->
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-yellow-200">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-black-500">Tanggal</th>
                                        <!-- <th class="px-4 py-2 text-center text-xs font-medium text-black-500">Jam</th> -->
                                        <th class="px-4 py-2 text-center text-xs font-medium text-black-500">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-black-500">Jurnal/Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($records as $rec): ?>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <?php echo formatTanggalIndo($rec['tanggal']); ?>
                                            </td>
                                            <!-- <td class="px-4 py-2 text-center whitespace-nowrap">
                                                <?php echo date('H:i', strtotime($rec['jam_mulai'])); ?>
                                            </td> -->
                                            <td class="px-4 py-2 text-center whitespace-nowrap font-medium
                                                <?php
                                                if ($rec['status_kehadiran'] === 'Hadir') echo 'text-green-600';
                                                elseif ($rec['status_kehadiran'] === 'Izin') echo 'text-blue-600';
                                                elseif ($rec['status_kehadiran'] === 'Sakit') echo 'text-yellow-600';
                                                elseif ($rec['status_kehadiran'] === 'Alpa') echo 'text-red-600';
                                                else echo 'text-gray-400';
                                                ?>">
                                                <?php echo htmlspecialchars(string: $rec['status_kehadiran'] ?? 'Kosong'); ?>
                                            </td>
                                            <td class="px-4 py-2 text-gray-700">
                                                <?php echo htmlspecialchars(ucwords($rec['keterangan'] ?? '-') ?? '-'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>