<?php
// Pastikan koneksi database tersedia
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

// === 1. LOGIC FILTER PERIODE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// Tentukan Default Periode (Hari Ini)
$default_periode_id = null;
$today = date('Y-m-d');
foreach ($periode_list as $p) {
    if ($today >= $p['tanggal_mulai'] && $today <= $p['tanggal_selesai']) {
        $default_periode_id = $p['id'];
        break;
    }
}
if ($default_periode_id === null && !empty($periode_list)) {
    $default_periode_id = $periode_list[0]['id'];
}

// === 2. AMBIL NILAI FILTER DARI URL ===
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : 'semua';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'semua';

// Kunci filter kelompok jika Admin Tingkat Kelompok
if ($ketuapjp_tingkat === 'kelompok') {
    $selected_kelompok = $ketuapjp_kelompok;
}

// =========================================================
// 3. HITUNG PROGRESS KETERCAPAIAN (HEADER DASHBOARD)
// =========================================================
$progress_data = [];
$total_progress_percent = 0;

if ($selected_periode_id) {
    // A. Query Target Pembelajaran (Sesuai Filter)
    $sql_target = "SELECT id, kategori, total_volume, satuan, tipe_input 
                   FROM target_pembelajaran 
                   WHERE periode_id = ?";

    $params_target = [$selected_periode_id];
    $types_target = "i";

    if ($selected_kelas !== 'semua') {
        $sql_target .= " AND (kelas = ? OR kelas = 'Semua')";
        $params_target[] = $selected_kelas;
        $types_target .= "s";
    }
    if ($selected_kelompok !== 'semua') {
        $sql_target .= " AND (kelompok = ? OR kelompok = 'Semua')";
        $params_target[] = $selected_kelompok;
        $types_target .= "s";
    }

    $stmt_target = $conn->prepare($sql_target);
    if (!empty($params_target)) $stmt_target->bind_param($types_target, ...$params_target);
    $stmt_target->execute();
    $res_target = $stmt_target->get_result();

    $categories = [];

    while ($t = $res_target->fetch_assoc()) {
        $cat = $t['kategori'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = ['total_target' => 0, 'total_capaian' => 0];
        }

        // Hitung Volume Target
        $vol_target = (float)$t['total_volume'];
        if ($t['tipe_input'] == 'CHECKLIST') $vol_target = 1; // Checklist targetnya 1 (Tercapai)

        $categories[$cat]['total_target'] += $vol_target;

        // Hitung Capaian Real (Dari Tabel Jurnal Materi)
        $sql_capaian = "SELECT SUM(jm.volume_capaian) as total 
                        FROM jurnal_materi jm 
                        JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id
                        WHERE jm.target_id = ?";

        // Filter Scope Realisasi
        if ($selected_kelompok !== 'semua') $sql_capaian .= " AND jp.kelompok = '$selected_kelompok'";
        if ($selected_kelas !== 'semua') $sql_capaian .= " AND jp.kelas = '$selected_kelas'";

        $stmt_cap = $conn->prepare($sql_capaian);
        $stmt_cap->bind_param("i", $t['id']);
        $stmt_cap->execute();
        $row_cap = $stmt_cap->get_result()->fetch_assoc();

        $vol_capaian = (float)$row_cap['total'];

        // Capping (Agar tidak > 100% per item)
        if ($vol_capaian > $vol_target) $vol_capaian = $vol_target;

        $categories[$cat]['total_capaian'] += $vol_capaian;
    }

    // Hitung Persentase Akhir
    $count_cat = 0;
    $sum_percent = 0;
    foreach ($categories as $cat_name => $data) {
        $percent = 0;
        if ($data['total_target'] > 0) {
            $percent = ($data['total_capaian'] / $data['total_target']) * 100;
        }
        $progress_data[$cat_name] = round($percent, 1);
        $sum_percent += $percent;
        $count_cat++;
    }

    if ($count_cat > 0) {
        $total_progress_percent = round($sum_percent / $count_cat, 1);
    }
}

// =========================================================
// 4. AMBIL LIST JURNAL HARIAN (TIMELINE)
// =========================================================
$jurnal_list = [];
if ($selected_periode_id) {
    // Query Header Jurnal
    $sql_jurnal = "SELECT id, tanggal, pengajar, kelompok, kelas 
                   FROM jadwal_presensi 
                   WHERE periode_id = ? 
                   AND pengajar IS NOT NULL AND pengajar != ''";

    $params = [$selected_periode_id];
    $types = "i";

    if ($selected_kelompok !== 'semua') {
        $sql_jurnal .= " AND kelompok = ?";
        $params[] = $selected_kelompok;
        $types .= "s";
    }
    if ($selected_kelas !== 'semua') {
        $sql_jurnal .= " AND kelas = ?";
        $params[] = $selected_kelas;
        $types .= "s";
    }

    $sql_jurnal .= " ORDER BY tanggal DESC, kelompok ASC, kelas ASC";

    $stmt_jurnal = $conn->prepare($sql_jurnal);
    if (!empty($params)) $stmt_jurnal->bind_param($types, ...$params);
    $stmt_jurnal->execute();
    $res_jurnal = $stmt_jurnal->get_result();

    if ($res_jurnal) {
        while ($row = $res_jurnal->fetch_assoc()) {
            $jadwal_id = $row['id'];

            // A. Ambil Materi Kurikulum
            $row['detail_materi'] = [];
            $q_mat = $conn->query("SELECT jm.*, tp.judul_materi, tp.kategori, tp.tipe_input, tp.satuan 
                                   FROM jurnal_materi jm 
                                   JOIN target_pembelajaran tp ON jm.target_id = tp.id 
                                   WHERE jm.jadwal_id = $jadwal_id");
            while ($m = $q_mat->fetch_assoc()) {
                $row['detail_materi'][] = $m;
            }

            // B. Ambil Materi Tambahan
            $row['detail_tambahan'] = [];
            $q_add = $conn->query("SELECT * FROM jurnal_tambahan WHERE jadwal_id = $jadwal_id");
            while ($a = $q_add->fetch_assoc()) {
                $row['detail_tambahan'][] = $a;
            }

            $jurnal_list[] = $row;
        }
    }
}
?>

<div class="container mx-auto space-y-6">

    <!-- CARD 1: FILTER -->
    <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-indigo-600">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-medium text-gray-800">Filter Rekap Jurnal</h3>
        </div>

        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="page" value="presensi/jurnal">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <!-- Periode -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Periode</label>
                    <select name="periode_id" id="filter_periode_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required onchange="this.form.submit()">
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Kelompok -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kelompok</label>
                    <?php if ($ketuapjp_tingkat === 'kelompok'): ?>
                        <input type="text" value="<?php echo ucfirst($ketuapjp_kelompok); ?>" class="mt-1 block w-full bg-gray-100 rounded-md border border-gray-300 py-2 px-3 text-gray-500 cursor-not-allowed" disabled>
                        <input type="hidden" name="kelompok" id="filter_kelompok" value="<?php echo $ketuapjp_kelompok; ?>">
                    <?php else: ?>
                        <select name="kelompok" id="filter_kelompok" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="semua" <?php echo ($selected_kelompok == 'semua') ? 'selected' : ''; ?>>Semua Kelompok</option>
                            <option value="bintaran" <?php echo ($selected_kelompok == 'bintaran') ? 'selected' : ''; ?>>Bintaran</option>
                            <option value="gedongkuning" <?php echo ($selected_kelompok == 'gedongkuning') ? 'selected' : ''; ?>>Gedongkuning</option>
                            <option value="jombor" <?php echo ($selected_kelompok == 'jombor') ? 'selected' : ''; ?>>Jombor</option>
                            <option value="sunten" <?php echo ($selected_kelompok == 'sunten') ? 'selected' : ''; ?>>Sunten</option>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Kelas -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kelas</label>
                    <select name="kelas" id="filter_kelas" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="semua" <?php echo ($selected_kelas == 'semua') ? 'selected' : ''; ?>>Semua Kelas</option>
                        <?php
                        $kelas_opts = ['paud', 'caberawit a', 'caberawit b', 'pra remaja', 'remaja', 'pra nikah'];
                        foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($selected_kelas == $k) ? 'selected' : ''; ?>>
                                <?php echo ucwords($k); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tombol -->
                <div class="self-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 shadow-sm">
                        Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($selected_periode_id): ?>

        <!-- CARD 2: PROGRESS SUMMARY (DASHBOARD MINI) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Total Progress -->
            <div class="bg-indigo-600 rounded-lg shadow-md p-6 text-white flex flex-col justify-center items-center text-center relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-chart-pie text-8xl"></i></div>
                <h3 class="text-lg font-semibold opacity-90 mb-2 relative z-10">Capaian Kurikulum</h3>
                <div class="relative w-32 h-32 flex items-center justify-center z-10">
                    <svg class="transform -rotate-90 w-32 h-32">
                        <circle cx="64" cy="64" r="56" stroke="currentColor" stroke-width="12" fill="transparent" class="text-indigo-500" />
                        <circle cx="64" cy="64" r="56" stroke="white" stroke-width="12" fill="transparent" stroke-dasharray="351.86" stroke-dashoffset="<?php echo 351.86 - (351.86 * $total_progress_percent / 100); ?>" class="transition-all duration-1000 ease-out" />
                    </svg>
                    <span class="absolute text-3xl font-bold"><?php echo $total_progress_percent; ?>%</span>
                </div>
                <p class="text-xs mt-2 opacity-80 z-10">Sesuai Filter (Kelas/Kelompok)</p>
            </div>

            <!-- Detail Kategori -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Progres Per Materi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <?php if (empty($progress_data)): ?>
                        <div class="col-span-2 text-center text-gray-400 py-4 italic">
                            Belum ada target kurikulum yang diatur untuk filter ini.
                        </div>
                        <?php else: foreach ($progress_data as $kategori => $persen):
                            $barColor = 'bg-blue-500';
                            if ($persen >= 80) $barColor = 'bg-green-500';
                            elseif ($persen < 40) $barColor = 'bg-yellow-500';
                        ?>
                            <div class="mb-2">
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700"><?php echo $kategori; ?></span>
                                    <span class="text-sm font-bold text-gray-600"><?php echo $persen; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?php echo $barColor; ?> h-2.5 rounded-full transition-all duration-1000" style="width: <?php echo $persen; ?>%"></div>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>

        <!-- CARD 3: LIST JURNAL -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600"></i> Daftar Jurnal Materi
            </h3>

            <div class="space-y-6">
                <?php if (empty($jurnal_list)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                        <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500">Tidak ada data jurnal yang cocok dengan filter.</p>
                    </div>
                    <?php else: foreach ($jurnal_list as $jurnal): ?>
                        <div class="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition bg-white">

                            <!-- Header Card Jurnal -->
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 flex flex-col md:flex-row justify-between md:items-center">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-800 text-lg"><?php echo date("l, d M Y", strtotime($jurnal['tanggal'])); ?></span>
                                        <span class="px-2 py-0.5 text-xs font-bold bg-indigo-100 text-indigo-700 rounded uppercase">
                                            <?php echo htmlspecialchars($jurnal['kelompok']); ?>
                                        </span>
                                        <span class="px-2 py-0.5 text-xs font-bold bg-blue-100 text-blue-700 rounded uppercase">
                                            <?php echo htmlspecialchars($jurnal['kelas']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">Pengajar: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($jurnal['pengajar']); ?></span></p>
                                </div>
                            </div>

                            <!-- Isi Jurnal -->
                            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">

                                <!-- Kolom Kiri: Kurikulum -->
                                <div>
                                    <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Materi Kurikulum</h5>
                                    <?php if (!empty($jurnal['detail_materi'])): ?>
                                        <ul class="space-y-2">
                                            <?php foreach ($jurnal['detail_materi'] as $dm):
                                                $teks = "";
                                                // Hapus trailing zeros (2.50 -> 2.5)
                                                $v_start = (float)$dm['capaian_start'];
                                                $v_end = (float)$dm['capaian_end'];
                                                $v_vol = (float)$dm['volume_capaian'];

                                                if ($dm['tipe_input'] == 'RANGE') $teks = "{$dm['satuan']} $v_start - $v_end";
                                                elseif ($dm['tipe_input'] == 'CHECKLIST') $teks = "Tercapai";
                                                else $teks = "$v_vol {$dm['satuan']}";
                                            ?>
                                                <li class="text-sm text-gray-700 flex items-start gap-2">
                                                    <i class="fa-solid fa-check text-green-500 mt-1 text-xs"></i>
                                                    <div>
                                                        <span class="font-semibold"><?php echo htmlspecialchars($dm['judul_materi']); ?></span>
                                                        <span class="text-gray-500 text-xs block"><?php echo $teks; ?></span>
                                                        <?php if ($dm['catatan_tambahan']): ?>
                                                            <span class="text-xs text-gray-400 italic">"<?php echo htmlspecialchars($dm['catatan_tambahan']); ?>"</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 italic">- Tidak ada materi kurikulum -</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Kolom Kanan: Tambahan -->
                                <div>
                                    <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Materi Tambahan / Nasehat</h5>
                                    <?php if (!empty($jurnal['detail_tambahan'])): ?>
                                        <div class="space-y-2">
                                            <?php foreach ($jurnal['detail_tambahan'] as $dt): ?>
                                                <div class="bg-yellow-50 p-2 rounded border border-yellow-100 flex items-start gap-2">
                                                    <i class="fa-solid fa-star text-yellow-500 mt-1 text-xs"></i>
                                                    <div>
                                                        <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($dt['judul_materi']); ?></p>
                                                        <p class="text-xs text-gray-600">Oleh: <?php echo htmlspecialchars($dt['pemateri']); ?></p>
                                                        <?php if ($dt['keterangan']): ?>
                                                            <p class="text-xs text-gray-500 mt-1 italic"><?php echo htmlspecialchars($dt['keterangan']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 italic">- Tidak ada materi tambahan -</p>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>