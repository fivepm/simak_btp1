<?php
// Pastikan koneksi database tersedia
if (!isset($conn)) die("Koneksi database gagal.");

$guru_kelompok = $_SESSION['user_kelompok'] ?? '';
$guru_kelas = $_SESSION['user_kelas'] ?? '';

// === 1. LOGIK FILTER PERIODE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// Tentukan Default Periode
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

$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;

// =========================================================
// 2. HITUNG PROGRESS KETERCAPAIAN (HEADER)
// =========================================================
$progress_data = [];
$total_progress_percent = 0;

if ($selected_periode_id) {
    // A. Ambil Semua Target untuk Kelas/Kelompok/Periode ini
    $sql_target = "SELECT id, kategori, judul_materi, total_volume, satuan, tipe_input 
                   FROM target_pembelajaran 
                   WHERE periode_id = ? 
                   AND (kelas = ? OR kelas = 'Semua') 
                   AND (kelompok = ? OR kelompok = 'Semua')";

    $stmt_target = $conn->prepare($sql_target);
    $stmt_target->bind_param("iss", $selected_periode_id, $guru_kelas, $guru_kelompok);
    $stmt_target->execute();
    $res_target = $stmt_target->get_result();

    $categories = [];

    while ($t = $res_target->fetch_assoc()) {
        $cat = $t['kategori'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = ['total_target' => 0, 'total_capaian' => 0, 'satuan' => $t['satuan']];
        }

        // Hitung Target
        $vol_target = (float)$t['total_volume'];
        // Jika checklist, targetnya 1 (selesai/belum), jika Range/Manual sesuai volume
        if ($t['tipe_input'] == 'CHECKLIST') $vol_target = 1;

        $categories[$cat]['total_target'] += $vol_target;

        // Hitung Capaian (Query Sum dari Jurnal Materi)
        // Kita cari berapa banyak volume yg sudah dicapai untuk target_id ini
        // Note: Filter jurnal berdasarkan kelompok/kelas guru sudah implisit karena jurnal_materi terhubung ke jadwal_presensi yg difilter user ini
        // Tapi untuk akurasi, kita hanya hitung capaian dari jadwal yg valid
        $q_capaian = $conn->query("SELECT SUM(jm.volume_capaian) as total 
                                   FROM jurnal_materi jm 
                                   JOIN jadwal_presensi jp ON jm.jadwal_id = jp.id
                                   WHERE jm.target_id = {$t['id']} 
                                   AND jp.kelompok = '$guru_kelompok' 
                                   AND jp.kelas = '$guru_kelas'");

        $row_capaian = $q_capaian->fetch_assoc();
        $vol_capaian = (float)$row_capaian['total'];

        // Capping: Jangan sampai capaian melebihi target (misal input berlebih) untuk perhitungan persentase
        if ($vol_capaian > $vol_target) $vol_capaian = $vol_target;

        $categories[$cat]['total_capaian'] += $vol_capaian;
    }

    // Hitung Persentase Per Kategori
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

    // Rata-rata Total
    if ($count_cat > 0) {
        $total_progress_percent = round($sum_percent / $count_cat, 1);
    }
}

// =========================================================
// 3. AMBIL DATA JURNAL HARIAN (TIMELINE)
// =========================================================
$jurnal_list = [];
if ($selected_periode_id) {
    // Ambil Header Jurnal (Jadwal) yang sudah ada pengajarnya
    $sql_jurnal = "SELECT id, tanggal, pengajar, materi3 as catatan_umum 
                   FROM jadwal_presensi 
                   WHERE periode_id = ? AND kelompok = ? AND kelas = ? 
                   AND pengajar IS NOT NULL AND pengajar != '' 
                   ORDER BY tanggal DESC";

    $stmt_jurnal = $conn->prepare($sql_jurnal);
    $stmt_jurnal->bind_param("iss", $selected_periode_id, $guru_kelompok, $guru_kelas);
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

    <!-- HEADER & FILTER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-lg shadow-md">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Rekap Jurnal Pembelajaran</h1>
            <p class="text-gray-500 text-sm">Monitoring progres kurikulum dan riwayat mengajar.</p>
        </div>
        <form method="GET" action="" class="w-full md:w-auto">
            <input type="hidden" name="page" value="rekap_jurnal">
            <select name="periode_id" onchange="this.form.submit()" class="w-full md:w-64 border border-gray-300 rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50">
                <?php foreach ($periode_list as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo ($selected_periode_id == $p['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nama_periode']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_periode_id): ?>

        <!-- SECTION 1: PROGRESS BAR KETERCAPAIAN -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Total Progress Card -->
            <div class="bg-indigo-600 rounded-lg shadow-md p-6 text-white flex flex-col justify-center items-center text-center">
                <h3 class="text-lg font-semibold opacity-90 mb-2">Total Ketercapaian Periode Ini</h3>
                <div class="relative w-32 h-32 flex items-center justify-center">
                    <svg class="transform -rotate-90 w-32 h-32">
                        <circle cx="64" cy="64" r="56" stroke="currentColor" stroke-width="12" fill="transparent" class="text-indigo-500" />
                        <circle cx="64" cy="64" r="56" stroke="white" stroke-width="12" fill="transparent" stroke-dasharray="351.86" stroke-dashoffset="<?php echo 351.86 - (351.86 * $total_progress_percent / 100); ?>" class="transition-all duration-1000 ease-out" />
                    </svg>
                    <span class="absolute text-3xl font-bold"><?php echo $total_progress_percent; ?>%</span>
                </div>
                <p class="text-xs mt-2 opacity-75">Rata-rata dari semua kategori</p>
            </div>

            <!-- Detail Per Kategori -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Progres Per Materi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <?php if (empty($progress_data)): ?>
                        <p class="text-gray-400 text-sm col-span-2 italic">Belum ada target yang diatur untuk periode ini.</p>
                        <?php else: foreach ($progress_data as $kategori => $persen):
                            // Warna Bar
                            $barColor = 'bg-blue-500';
                            if ($persen >= 75) $barColor = 'bg-green-500';
                            elseif ($persen < 40) $barColor = 'bg-yellow-500';
                        ?>
                            <div class="mb-2">
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700"><?php echo $kategori; ?></span>
                                    <span class="text-sm font-medium text-gray-700"><?php echo $persen; ?>%</span>
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

        <!-- SECTION 2: TIMELINE JURNAL -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-indigo-600"></i> Riwayat Jurnal
            </h3>

            <div class="relative border-l-4 border-gray-200 ml-3 space-y-8">
                <?php if (empty($jurnal_list)): ?>
                    <div class="ml-6">
                        <p class="text-gray-500 italic">Belum ada jurnal yang diisi pada periode ini.</p>
                    </div>
                    <?php else: foreach ($jurnal_list as $jurnal): ?>
                        <div class="ml-6 relative">
                            <!-- Dot Timeline -->
                            <span class="absolute -left-[35px] flex h-6 w-6 items-center justify-center rounded-full bg-white ring-4 ring-indigo-50">
                                <i class="fa-solid fa-circle-check text-indigo-600 text-sm"></i>
                            </span>

                            <!-- Header Tanggal & Pengajar -->
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center bg-gray-50 p-3 rounded-t-lg border border-gray-100">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900"><?php echo date("l, d F Y", strtotime($jurnal['tanggal'])); ?></h4>
                                    <p class="text-sm text-gray-500">Pengajar: <span class="font-medium text-indigo-700"><?php echo htmlspecialchars($jurnal['pengajar']); ?></span></p>
                                </div>
                                <!-- Jika ada catatan umum/nasehat (old style) -->
                                <?php if (!empty($jurnal['catatan_umum'])): ?>
                                    <div class="mt-2 sm:mt-0 text-sm text-gray-600 italic bg-white px-2 py-1 rounded border border-gray-200">
                                        "<?php echo htmlspecialchars($jurnal['catatan_umum']); ?>"
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Isi Jurnal -->
                            <div class="border border-t-0 border-gray-100 rounded-b-lg p-4 space-y-3">

                                <!-- 1. Materi Kurikulum -->
                                <?php if (!empty($jurnal['detail_materi'])): ?>
                                    <div class="grid gap-2">
                                        <?php foreach ($jurnal['detail_materi'] as $dm):
                                            $teks_capaian = "";
                                            $v_start = (float)$dm['capaian_start'];
                                            $v_end = (float)$dm['capaian_end'];
                                            $v_vol = (float)$dm['volume_capaian'];

                                            if ($dm['tipe_input'] == 'RANGE') {
                                                $teks_capaian = "{$dm['satuan']} $v_start - $v_end ($v_vol {$dm['satuan']})";
                                            } elseif ($dm['tipe_input'] == 'CHECKLIST') {
                                                $teks_capaian = "Tercapai";
                                            } else {
                                                $teks_capaian = "$v_vol {$dm['satuan']}";
                                            }
                                        ?>
                                            <div class="flex items-start gap-3 p-2 hover:bg-gray-50 rounded transition grid grid-cols-1 lg:grid-cols-2">
                                                <div class="mt-1">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-100 rounded border border-indigo-200">
                                                        <?php echo htmlspecialchars($dm['kategori']); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($dm['judul_materi']); ?></p>
                                                    <p class="text-sm text-gray-600">
                                                        <i class="fa-solid fa-check text-green-500 text-xs"></i> <?php echo $teks_capaian; ?>
                                                    </p>
                                                    <?php if (!empty($dm['catatan_tambahan'])): ?>
                                                        <p class="text-xs text-gray-500 italic mt-0.5">Note: <?php echo htmlspecialchars($dm['catatan_tambahan']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- 2. Materi Tambahan -->
                                <?php if (!empty($jurnal['detail_tambahan'])): ?>
                                    <div class="border-t border-dashed border-gray-200 pt-3 mt-2">
                                        <h5 class="text-xs font-bold text-gray-500 uppercase mb-2">Materi Tambahan</h5>
                                        <div class="grid gap-2">
                                            <?php foreach ($jurnal['detail_tambahan'] as $dt): ?>
                                                <div class="flex items-start gap-3 p-2 bg-yellow-50 rounded border border-yellow-100">
                                                    <div class="mt-1"><i class="fa-solid fa-star text-yellow-500"></i></div>
                                                    <div>
                                                        <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($dt['judul_materi']); ?></p>
                                                        <p class="text-xs text-gray-600">Oleh: <?php echo htmlspecialchars($dt['pemateri']); ?></p>
                                                        <?php if (!empty($dt['keterangan'])): ?>
                                                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($dt['keterangan']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Empty State jika kosong sama sekali -->
                                <?php if (empty($jurnal['detail_materi']) && empty($jurnal['detail_tambahan'])): ?>
                                    <p class="text-sm text-gray-400 italic">Tidak ada detail materi yang tercatat.</p>
                                <?php endif; ?>

                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>