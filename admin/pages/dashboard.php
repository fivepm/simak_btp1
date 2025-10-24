<?php

// Ambil data sesi admin
$admin_level = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? null;
$admin_role = $_SESSION['user_role'] ?? '';

// --- (FUNGSI HELPER TANGGAL) ---
function formatTanggalIndonesiaSingkat($tanggal_db)
{
    if (empty($tanggal_db) || $tanggal_db === '0000-00-00') return '';
    try {
        $date = new DateTime($tanggal_db);
        $bulan_indonesia = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return $date->format('j') . ' ' . $bulan_indonesia[(int)$date->format('n')] . ' ' . $date->format('Y');
    } catch (Exception $e) {
        return '';
    }
}
// --- (AKHIR FUNGSI HELPER) ---

// Siapkan klausa WHERE untuk filter berdasarkan kelompok jika perlu
$where_clause = "";
$params = [];
$types = "";
if ($admin_level === 'kelompok') {
    $where_clause = " WHERE kelompok = ? AND status = 'Aktif'";
    $params[] = $admin_kelompok;
    $types .= "s";
} else {
    $where_clause = " WHERE status = 'Aktif'";
}

// Total Peserta
$total_peserta = 0;
$sql_total = "SELECT COUNT(id) as total FROM peserta" . $where_clause;
$stmt_total = $conn->prepare($sql_total);
if ($admin_level === 'kelompok') {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
if ($result_total) {
    $total_peserta = $result_total->fetch_assoc()['total'] ?? 0;
}
$stmt_total->close();

// Peserta per Jenis Kelamin
$peserta_per_jk = ['Laki-laki' => 0, 'Perempuan' => 0];
$sql_jk = "SELECT jenis_kelamin, COUNT(id) as jumlah FROM peserta" . $where_clause . " GROUP BY jenis_kelamin";
$stmt_jk = $conn->prepare($sql_jk);
if ($admin_level === 'kelompok') {
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

// ===================================================================
// PENGAMBILAN DATA UNTUK DASHBOARD
// ===================================================================

// 1. Tentukan Periode Aktif
$periode_aktif = null;

// 1. Prioritas 1: Cari periode yang AKTIF HARI INI
$query_current = "SELECT id, nama_periode FROM periode WHERE CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai LIMIT 1";
$result_current = $conn->query($query_current);
if ($result_current && $result_current->num_rows > 0) {
    $periode_aktif = $result_current->fetch_assoc();
}

// 2. Prioritas 2: Jika tidak ada, cari periode TERDEKAT YANG AKAN DATANG
if (!$periode_aktif) {
    $query_next = "SELECT id, nama_periode FROM periode WHERE tanggal_mulai > CURDATE() ORDER BY tanggal_mulai ASC LIMIT 1";
    $result_next = $conn->query($query_next);
    if ($result_next && $result_next->num_rows > 0) {
        $periode_aktif = $result_next->fetch_assoc();
    }
}

// 3. Prioritas 3: Jika tidak ada juga, ambil periode TERAKHIR YANG BARU SELESAI
if (!$periode_aktif) {
    $query_last = "SELECT id, nama_periode FROM periode WHERE tanggal_selesai < CURDATE() ORDER BY tanggal_selesai DESC LIMIT 1";
    $result_last = $conn->query($query_last);
    if ($result_last && $result_last->num_rows > 0) {
        $periode_aktif = $result_last->fetch_assoc();
    }
}
$periode_aktif_id = $periode_aktif['id'] ?? null;
$periode_aktif_nama = $periode_aktif['nama_periode'] ?? 'Tidak Ada Periode Aktif';

$data = [
    'rata_rata_kehadiran_global' => 0,
    'jadwal_hari_ini' => 0,
    'laporan_draft' => 0,
    'jadwal_terlewat_kosong' => [],
    'jadwal_tanpa_pengajar' => [],
    'jadwal_akan_datang' => [],
    'musyawarah_terdekat' => [],
    'kehadiran_per_kelas_grouped' => []
];

if ($periode_aktif_id) {
    // 2. Statistik Utama
    // Rata-rata kehadiran GLOBAL
    $sql_kehadiran_global = "
        SELECT 
            IF(COUNT(rp.id) > 0, 
               (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.id)) * 100, 
               0
            ) as rata_rata
        FROM rekap_presensi rp
        JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
        JOIN peserta p ON rp.peserta_id = p.id
        WHERE jp.periode_id = ? 
    ";

    $bind_types_global = "i";
    $bind_values_global = [$periode_aktif_id];

    if ($admin_level === 'kelompok' && $admin_kelompok) {
        $sql_kehadiran_global .= " AND p.kelompok = ?";
        $bind_types_global .= "s";
        $bind_values_global[] = $admin_kelompok;
    }

    // Grouping tidak diperlukan untuk rata-rata global
    // $sql_kehadiran_global .= " GROUP BY p.id ) as student_summary"; 

    $stmt_kehadiran_global = $conn->prepare($sql_kehadiran_global);
    if ($stmt_kehadiran_global) {
        $stmt_kehadiran_global->bind_param($bind_types_global, ...$bind_values_global);
        $stmt_kehadiran_global->execute();
        $result_kehadiran_global = $stmt_kehadiran_global->get_result()->fetch_assoc();
        // Fallback ke 0 jika hasilnya NULL (tidak ada data sama sekali)
        $data['rata_rata_kehadiran_global'] = $result_kehadiran_global['rata_rata'] ?? 0;
        $stmt_kehadiran_global->close();
    } else {
        // Handle error prepare statement
        error_log("Gagal prepare statement sql_kehadiran_global: " . $conn->error);
        $data['rata_rata_kehadiran_global'] = 0; // Set default jika query gagal
    }

    // --- Query Kehadiran PER KELAS (untuk data chart) ---
    $sql_kehadiran_kelas = "
        SELECT 
            p.kelompok, 
            p.kelas, 
            AVG(sub.persentase_siswa) as rata_rata_kelas
        FROM peserta p
        LEFT JOIN (
            SELECT 
                rp.peserta_id,
                IF(COUNT(rp.id) > 0, (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.id)) * 100, 0) as persentase_siswa
            FROM rekap_presensi rp
            JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
            WHERE jp.periode_id = ?
            GROUP BY rp.peserta_id
        ) AS sub ON p.id = sub.peserta_id
        WHERE 1=1 ";
    $bind_types_kelas = "i";
    $bind_values_kelas = [$periode_aktif_id];
    if ($admin_level === 'kelompok' && $admin_kelompok) {
        $sql_kehadiran_kelas .= " AND p.kelompok = ?";
        $bind_types_kelas .= "s";
        $bind_values_kelas[] = $admin_kelompok;
    }
    $sql_kehadiran_kelas .= " GROUP BY p.kelompok, p.kelas ORDER BY p.kelompok, FIELD(p.kelas, 'Paud', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah')";
    $stmt_kehadiran_kelas = $conn->prepare($sql_kehadiran_kelas);
    if ($stmt_kehadiran_kelas) {
        $stmt_kehadiran_kelas->bind_param($bind_types_kelas, ...$bind_values_kelas);
        $stmt_kehadiran_kelas->execute();
        $result_kehadiran_kelas = $stmt_kehadiran_kelas->get_result();
        if ($result_kehadiran_kelas) {
            while ($row = $result_kehadiran_kelas->fetch_assoc()) {
                $nama_kelompok = $row['kelompok'];
                $nama_kelas = $row['kelas'];
                $rata_rata = $row['rata_rata_kelas'] ?? 0;

                // Kelompokkan data untuk JavaScript
                if (!isset($data['kehadiran_per_kelas_grouped'][$nama_kelompok])) {
                    $data['kehadiran_per_kelas_grouped'][$nama_kelompok] = ['labels' => [], 'data' => []];
                }
                $data['kehadiran_per_kelas_grouped'][$nama_kelompok]['labels'][] = ucwords($nama_kelas);
                $data['kehadiran_per_kelas_grouped'][$nama_kelompok]['data'][] = round($rata_rata, 1);
            }
        }
        $stmt_kehadiran_kelas->close();
    }
    // --- AKHIR QUERY PER KELAS ---

    // Jumlah jadwal hari ini
    $sql_jadwal_hari_ini = "SELECT COUNT(id) as total FROM jadwal_presensi WHERE tanggal = CURDATE() AND periode_id = ?";
    $bind_types_hari_ini = "i";
    $bind_values_hari_ini = [$periode_aktif_id];
    if ($admin_level === 'kelompok' && $admin_kelompok) {
        $sql_jadwal_hari_ini .= " AND kelompok = ?";
        $bind_types_hari_ini .= "s";
        $bind_values_hari_ini[] = $admin_kelompok;
    }
    $stmt_jadwal_hari_ini = $conn->prepare($sql_jadwal_hari_ini);
    if ($stmt_jadwal_hari_ini) {
        $stmt_jadwal_hari_ini->bind_param($bind_types_hari_ini, ...$bind_values_hari_ini);
        $stmt_jadwal_hari_ini->execute();
        $data['jadwal_hari_ini'] = $stmt_jadwal_hari_ini->get_result()->fetch_assoc()['total'];
        $stmt_jadwal_hari_ini->close();
    } else {
        error_log("Gagal prepare sql_jadwal_hari_ini: " . $conn->error);
    }

    // Jumlah laporan draft
    // $sql_laporan_draft = "SELECT COUNT(id) as total FROM laporan_kelompok WHERE status = 'Draft'";
    // $bind_types_draft = "";
    // $bind_values_draft = [];
    // if ($admin_level === 'kelompok' && $admin_kelompok) {
    //     $sql_laporan_draft .= " AND kelompok = ?";
    //     $bind_types_draft .= "s";
    //     $bind_values_draft[] = $admin_kelompok;
    // }
    // $stmt_laporan_draft = $conn->prepare($sql_laporan_draft);
    // if ($stmt_laporan_draft) {
    //     if (!empty($bind_types_draft)) {
    //         $stmt_laporan_draft->bind_param($bind_types_draft, ...$bind_values_draft);
    //     }
    //     $stmt_laporan_draft->execute();
    //     $data['laporan_draft'] = $stmt_laporan_draft->get_result()->fetch_assoc()['total'];
    //     $stmt_laporan_draft->close();
    // } else {
    //     error_log("Gagal prepare sql_laporan_draft: " . $conn->error);
    // }


    // 3. Tindakan Mendesak
    // Jadwal terlewat kosong (presensi/jurnal)
    // *** PERUBAHAN: LIMIT 5 DIHAPUS DARI SQL ***
    $sql_terlewat = "
        SELECT 
            jp.id, 
            jp.tanggal, 
            jp.kelas, 
            jp.kelompok,
            (jp.pengajar IS NULL OR jp.pengajar = '') AS jurnal_kosong,
            EXISTS (SELECT 1 FROM rekap_presensi rp WHERE rp.jadwal_id = jp.id AND rp.status_kehadiran IS NULL) AS presensi_kosong
        FROM jadwal_presensi jp 
        WHERE 
            TIMESTAMP(jp.tanggal, jp.jam_selesai) <= NOW()
            AND jp.periode_id = ? ";

    $bind_types_terlewat = "i";
    $bind_values_terlewat = [$periode_aktif_id];
    if ($admin_level === 'kelompok' && $admin_kelompok) {
        $sql_terlewat .= " AND jp.kelompok = ?";
        $bind_types_terlewat .= "s";
        $bind_values_terlewat[] = $admin_kelompok;
    }
    $sql_terlewat .= " AND (
              EXISTS (SELECT 1 FROM rekap_presensi rp WHERE rp.jadwal_id = jp.id AND rp.status_kehadiran IS NULL)
              OR (jp.pengajar IS NULL OR jp.pengajar = '')
          )
        ORDER BY jp.tanggal DESC, jp.jam_mulai DESC"; // LIMIT 5 dihapus

    $stmt_terlewat = $conn->prepare($sql_terlewat);
    if ($stmt_terlewat) {
        $stmt_terlewat->bind_param($bind_types_terlewat, ...$bind_values_terlewat);
        $stmt_terlewat->execute();
        $result_terlewat = $stmt_terlewat->get_result();
        if ($result_terlewat) {
            while ($row = $result_terlewat->fetch_assoc()) {
                $keterangan_kosong = '';
                if ($row['jurnal_kosong'] && $row['presensi_kosong']) {
                    $keterangan_kosong = 'Presensi & Jurnal';
                } elseif ($row['presensi_kosong']) {
                    $keterangan_kosong = 'Presensi';
                } elseif ($row['jurnal_kosong']) {
                    $keterangan_kosong = 'Jurnal';
                }
                $row['keterangan_kosong'] = $keterangan_kosong;
                $data['jadwal_terlewat_kosong'][] = $row;
            }
        }
        $stmt_terlewat->close();
    } else {
        error_log("Gagal prepare sql_terlewat: " . $conn->error);
    }


    // Jadwal terlewat tanpa pengajar
    // *** PERUBAHAN: LIMIT 5 DIHAPUS DARI SQL ***
    $sql_tanpa_guru = "
        SELECT jp.id, jp.tanggal, jp.kelas, jp.kelompok
        FROM jadwal_presensi jp
        LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id
        WHERE jg.jadwal_id IS NULL 
          AND TIMESTAMP(jp.tanggal, jp.jam_mulai) < NOW()
          AND jp.periode_id = ? ";
    $bind_types_tanpa_guru = "i";
    $bind_values_tanpa_guru = [$periode_aktif_id];
    if ($admin_level === 'kelompok' && $admin_kelompok) {
        $sql_tanpa_guru .= " AND jp.kelompok = ?";
        $bind_types_tanpa_guru .= "s";
        $bind_values_tanpa_guru[] = $admin_kelompok;
    }
    $sql_tanpa_guru .= " ORDER BY jp.tanggal DESC, jp.jam_mulai DESC"; // LIMIT 5 dihapus
    $stmt_tanpa_guru = $conn->prepare($sql_tanpa_guru);
    if ($stmt_tanpa_guru) {
        $stmt_tanpa_guru->bind_param($bind_types_tanpa_guru, ...$bind_values_tanpa_guru);
        $stmt_tanpa_guru->execute();
        $result_tanpa_guru = $stmt_tanpa_guru->get_result();
        if ($result_tanpa_guru) {
            while ($row = $result_tanpa_guru->fetch_assoc()) {
                $data['jadwal_tanpa_pengajar'][] = $row;
            }
        }
        $stmt_tanpa_guru->close();
    } else {
        error_log("Gagal prepare sql_tanpa_guru: " . $conn->error);
    }

    // 4. Jadwal Akan Datang (Hari Ini & Besok)
    // *** QUERY DIPERBAIKI DENGAN FILTER ROLE ***
    $sql_akan_datang = "
        SELECT jp.id, jp.tanggal, jp.jam_mulai, jp.kelas, jp.kelompok, GROUP_CONCAT(DISTINCT g.nama SEPARATOR ', ') as daftar_guru
        FROM jadwal_presensi jp
        LEFT JOIN jadwal_guru jg ON jp.id = jg.jadwal_id
        LEFT JOIN guru g ON jg.guru_id = g.id
        WHERE jp.tanggal BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 DAY
          AND jp.periode_id = ? ";
    $bind_types_akan_datang = "i";
    $bind_values_akan_datang = [$periode_aktif_id];
    if ($admin_level === 'kelompok' && $admin_kelompok) {
        $sql_akan_datang .= " AND jp.kelompok = ?";
        $bind_types_akan_datang .= "s";
        $bind_values_akan_datang[] = $admin_kelompok;
    }
    $sql_akan_datang .= " GROUP BY jp.id ORDER BY jp.tanggal ASC, jp.jam_mulai ASC";
    $stmt_akan_datang = $conn->prepare($sql_akan_datang);
    if ($stmt_akan_datang) {
        $stmt_akan_datang->bind_param($bind_types_akan_datang, ...$bind_values_akan_datang);
        $stmt_akan_datang->execute();
        $result_akan_datang = $stmt_akan_datang->get_result();
        if ($result_akan_datang) {
            while ($row = $result_akan_datang->fetch_assoc()) {
                $data['jadwal_akan_datang'][] = $row;
            }
        }
        $stmt_akan_datang->close();
    } else {
        error_log("Gagal prepare sql_akan_datang: " . $conn->error);
    }

    // 5. Musyawarah Terdekat
    $sql_musyawarah = "SELECT id, nama_musyawarah, tanggal, waktu_mulai FROM musyawarah WHERE tanggal >= CURDATE() ORDER BY tanggal ASC, waktu_mulai ASC LIMIT 3";
    $result_musyawarah = $conn->query($sql_musyawarah);
    if ($result_musyawarah) {
        while ($row = $result_musyawarah->fetch_assoc()) {
            $data['musyawarah_terdekat'][] = $row;
        }
    }
}
?>

<!-- Di sini Anda bisa menyertakan header/layout utama -->
<div class="container mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header Dashboard -->
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-gray-800">Dashboard Admin <?php echo ucwords($admin_level) ?></h1>
        <?php if ($admin_level === 'kelompok'): ?>
            <p class="text-gray-500 mt-1">Menampilkan data untuk Kelompok <span class="font-semibold capitalize"><?php echo htmlspecialchars($admin_kelompok); ?></span></p>
        <?php endif; ?>
        <p class="text-gray-500">Ringkasan Sistem - Periode: <?php echo htmlspecialchars($periode_aktif_nama); ?></p>
    </div>

    <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="fa-solid fa-server mr-2"></i> Ringkasan Data Umum</h2>
    <!-- Kartu Statistik Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Card Total Siswa -->
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="bg-blue-100 p-4 rounded-full mr-4">
                <i class="fa-solid fa-users text-blue-600 text-2xl"></i>
            </div>
            <div>
                <span class="text-sm font-semibold text-gray-500">Total Siswa</span>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $total_peserta; ?></p>
            </div>
        </div>
        <!-- Card Siswa Laki-Laki -->
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="bg-green-100 p-4 rounded-full mr-4">
                <i class="fa-solid fa-mars text-green-600 text-2xl"></i>
            </div>
            <div>
                <span class="text-sm font-semibold text-gray-500">Laki-laki</span>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_jk['Laki-laki']; ?></p>
            </div>
        </div>
        <!-- Card Siswa Perempuan -->
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="bg-pink-100 p-4 rounded-full mr-4">
                <i class="fa-solid fa-venus text-pink-600 text-2xl"></i>
            </div>
            <div>
                <span class="text-sm font-semibold text-gray-500">Perempuan</span>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $peserta_per_jk['Perempuan']; ?></p>
            </div>
        </div>
    </div>

    <!-- Grid Statistik Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-6 mb-6">
        <!-- Card Rata-rata Kehadiran (Global) -->
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="bg-green-100 p-4 rounded-full mr-4">
                <i class="fas fa-chart-line text-green-600 text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Rata-rata Kehadiran <?php echo ($admin_level === 'kelompok' ? 'Kelompok ' . htmlspecialchars($admin_kelompok) : 'Global'); ?></p>
                <p class="font-bold text-3xl text-gray-800"><?php echo number_format($data['rata_rata_kehadiran_global'], 1); ?>%</p>
            </div>
        </div>
        <!-- Card Jadwal Hari Ini -->
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="bg-blue-100 p-4 rounded-full mr-4">
                <i class="fas fa-calendar-day text-blue-600 text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Jadwal KBM Hari Ini</p>
                <p class="font-bold text-3xl text-gray-800"><?php echo $data['jadwal_hari_ini']; ?></p>
            </div>
        </div>
        <!-- Card Laporan Draft -->
        <!-- <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="bg-yellow-100 p-4 rounded-full mr-4">
                <i class="fas fa-file-alt text-yellow-600 text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Laporan Kelompok (Draft)</p>
                <?php if ($data['laporan_draft'] > 0 && $admin_level === 'kelompok'): ?>
                    <p class="font-bold text-3xl text-gray-800"><?php echo $data['laporan_draft']; ?><sup class="fa-solid fa-circle-exclamation text-sm text-yellow-600 ml-1"></sup></p>
                <?php else: ?>
                    <p class="font-bold text-3xl text-gray-800"><?php echo $data['laporan_draft']; ?></p>
                <?php endif; ?>
            </div>
        </div> -->
    </div>

    <!-- --- KARTU GRAFIK KEHADIRAN PER KELAS (LAYOUT BARU) --- -->
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="fas fa-chart-bar mr-2"></i> Rata-rata Kehadiran per Kelas (%)</h2>
        <?php if ($admin_level === 'desa'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <?php if (!empty($data['kehadiran_per_kelas_grouped'])): ?>
                    <?php foreach ($data['kehadiran_per_kelas_grouped'] as $nama_kelompok => $chart_info): ?>
                        <div class="bg-white p-4 rounded-2xl shadow-lg">
                            <h3 class="text-lg font-semibold text-center text-gray-700 mb-3"><?php echo htmlspecialchars(strtoupper($nama_kelompok)); ?></h3>
                            <div class="relative h-64">
                                <?php // Buat ID unik untuk setiap canvas 
                                ?>
                                <canvas id="kehadiranChart_<?php echo str_replace(' ', '_', $nama_kelompok); ?>"></canvas>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white p-6 rounded-2xl shadow-lg lg:col-span-4">
                        <p class="text-center text-gray-500 italic">Data kehadiran per kelas belum tersedia untuk ditampilkan dalam grafik.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php if (!empty($data['kehadiran_per_kelas_grouped'])): ?>
                    <?php foreach ($data['kehadiran_per_kelas_grouped'] as $nama_kelompok => $chart_info): ?>
                        <div class="bg-white p-4 rounded-2xl shadow-lg">
                            <h3 class="text-lg font-semibold text-center text-gray-700 mb-3"><?php echo htmlspecialchars(strtoupper($nama_kelompok)); ?></h3>
                            <div class="relative h-64">
                                <?php // Buat ID unik untuk setiap canvas 
                                ?>
                                <canvas id="kehadiranChart_<?php echo str_replace(' ', '_', $nama_kelompok); ?>"></canvas>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white p-6 rounded-2xl shadow-lg">
                        <p class="text-center text-gray-500 italic">Data kehadiran per kelas belum tersedia untuk ditampilkan dalam grafik.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- --- AKHIR KARTU GRAFIK --- -->

    <!-- Grid Tindakan Mendesak -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Card Jadwal Kosong (Presensi/Jurnal) -->
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <?php $total_terlewat_kosong = count($data['jadwal_terlewat_kosong']); ?>
            <h2 class="text-xl font-bold text-red-600 mb-3"><i class="fas fa-exclamation-triangle mr-2"></i> Jadwal Terlewat Belum Terisi (<?php echo $total_terlewat_kosong; ?>)</h2>
            <div id="list-terlewat-kosong" class="space-y-2 text-sm max-h-48 overflow-y-auto">
                <?php if ($total_terlewat_kosong > 0): ?>
                    <?php foreach ($data['jadwal_terlewat_kosong'] as $index => $jadwal): ?>
                        <div class="flex justify-between items-center p-2 bg-red-50 rounded <?php if ($index >= 5) echo 'hidden-item-terlewat hidden'; ?>">
                            <div>
                                <span><?php echo formatTanggalIndonesiaSingkat($jadwal['tanggal']); ?> - <?php echo htmlspecialchars(ucwords($jadwal['kelompok']) . ' / ' . ucwords($jadwal['kelas'])); ?></span>
                                <span class="block text-xs font-semibold text-red-700">
                                    Belum diisi: <?php echo htmlspecialchars($jadwal['keterangan_kosong']); ?>
                                </span>
                            </div>
                            <!-- <a href="?page=presensi/isi_presensi&jadwal_id=<?php echo $jadwal['id']; ?>" class="text-red-600 hover:underline text-xs flex-shrink-0 ml-2">Isi Sekarang</a> -->
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 italic">Tidak ada jadwal terlewat yang perlu diisi.</p>
                <?php endif; ?>
            </div>
            <?php if ($total_terlewat_kosong > 5): ?>
                <button type="button" id="btn-lihat-lainnya-terlewat" class="text-sm text-blue-600 hover:underline mt-3">
                    Lihat <?php echo ($total_terlewat_kosong - 5); ?> Lainnya...
                </button>
                <button type="button" id="btn-sembunyikan-terlewat" class="text-sm text-gray-600 hover:underline mt-3 hidden">
                    Sembunyikan
                </button>
            <?php endif; ?>
        </div>

        <!-- Card Jadwal Tanpa Pengajar -->
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <?php $total_tanpa_pengajar = count($data['jadwal_tanpa_pengajar']); ?>
            <h2 class="text-xl font-bold text-orange-600 mb-3"><i class="fas fa-user-times mr-2"></i> Jadwal Terlewat Tanpa Pengajar (<?php echo $total_tanpa_pengajar; ?>)</h2>
            <div id="list-tanpa-pengajar" class="space-y-2 text-sm max-h-48 overflow-y-auto">
                <?php if ($total_tanpa_pengajar > 0): ?>
                    <?php foreach ($data['jadwal_tanpa_pengajar'] as $index => $jadwal): ?>
                        <div class="flex justify-between items-center p-2 bg-orange-50 rounded <?php if ($index >= 5) echo 'hidden-item-tanpa-pengajar hidden'; ?>">
                            <span><?php echo formatTanggalIndonesiaSingkat($jadwal['tanggal']); ?> - <?php echo htmlspecialchars(ucwords($jadwal['kelompok']) . ' / ' . ucwords($jadwal['kelas'])); ?></span>
                            <!-- <a href="?page=presensi/atur_jadwal&periode_id=<?php echo $periode_aktif_id; ?>&kelompok=<?php echo $jadwal['kelompok']; ?>&kelas=<?php echo $jadwal['kelas']; ?>" class="text-orange-600 hover:underline text-xs">Atur Pengajar</a> -->
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 italic">Semua jadwal terlewat sudah memiliki pengajar.</p>
                <?php endif; ?>
            </div>
            <?php if ($total_tanpa_pengajar > 5): ?>
                <button type="button" id="btn-lihat-lainnya-tanpa-pengajar" class="text-sm text-blue-600 hover:underline mt-3">
                    Lihat <?php echo ($total_tanpa_pengajar - 5); ?> Lainnya...
                </button>
                <button type="button" id="btn-sembunyikan-tanpa-pengajar" class="text-sm text-gray-600 hover:underline mt-3 hidden">
                    Sembunyikan
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid Jadwal & Pintasan -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Card Jadwal Akan Datang -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-3"><i class="fas fa-calendar-check mr-2"></i> Jadwal KBM Hari Ini & Besok</h2>
            <div class="space-y-3 text-sm max-h-60 overflow-y-auto">
                <?php if (!empty($data['jadwal_akan_datang'])): ?>
                    <?php foreach ($data['jadwal_akan_datang'] as $jadwal): ?>
                        <div class="p-3 border rounded-lg">
                            <p class="font-semibold"><?php echo ($jadwal['tanggal'] == date('Y-m-d')) ? 'Hari Ini' : 'Besok'; ?>, <?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo htmlspecialchars(ucwords($jadwal['kelompok']) . ' / ' . ucwords($jadwal['kelas'])); ?></p>
                            <p class="text-xs text-gray-500">Pengajar: <?php echo htmlspecialchars($jadwal['daftar_guru'] ?: '-'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 italic">Tidak ada jadwal KBM untuk hari ini atau besok.</p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Card Pintasan & Musyawarah -->
        <div class="flex flex-col gap-6">
            <!-- Card Musyawarah Terdekat -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-3"><i class="fas fa-users mr-2"></i> Musyawarah Terdekat</h2>
                <div class="space-y-2 text-sm">
                    <?php if (!empty($data['musyawarah_terdekat'])): ?>
                        <?php foreach ($data['musyawarah_terdekat'] as $musyawarah): ?>
                            <div class="border-b pb-2">
                                <p class="font-semibold"><?php echo htmlspecialchars($musyawarah['nama_musyawarah']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo formatTanggalIndonesiaSingkat($musyawarah['tanggal']); ?>, <?php echo date('H:i', strtotime($musyawarah['waktu_mulai'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 italic">Tidak ada musyawarah terjadwal.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Card Pintasan Cepat -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-3"><i class="fas fa-bolt mr-2"></i> Pintasan Cepat</h2>
                <div class="flex flex-col space-y-2">
                    <!-- <a href="?page=presensi/atur_jadwal" class="bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-medium py-2 px-4 rounded-lg text-center transition-colors">Tambah Jadwal Baru</a> -->
                    <!-- <a href="?page=laporan/laporan_kelompok" class="bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-2 px-4 rounded-lg text-center transition-colors">Kelola Laporan Kelompok</a> -->
                    <!-- <a href="?page=pengaturan/pengingat" class="bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium py-2 px-4 rounded-lg text-center transition-colors">Pengaturan Pengingat</a> -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sertakan library Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- ▼▼▼ SERTAKAN PLUGIN DATALABELS ▼▼▼ -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // --- KODE BARU UNTUK MULTIPLE GRAFIK ---
        const dataKehadiranPerKelompok = <?php echo json_encode($data['kehadiran_per_kelas_grouped']); ?>;

        // Daftarkan plugin datalabels secara global
        Chart.register(ChartDataLabels);

        // Loop melalui setiap kelompok dalam data
        for (const kelompok in dataKehadiranPerKelompok) {
            if (dataKehadiranPerKelompok.hasOwnProperty(kelompok)) {
                const chartInfo = dataKehadiranPerKelompok[kelompok];
                const canvasId = 'kehadiranChart_' + kelompok.replace(/ /g, '_');
                const ctx = document.getElementById(canvasId);

                if (ctx) {
                    // ▼▼▼ FUNGSI BARU UNTUK MENENTUKAN WARNA ▼▼▼
                    const getBarColor = (value) => {
                        if (value < 50) return 'rgba(239, 68, 68, 0.6)'; // Merah (Tailwind red-500 opacity 60%)
                        if (value >= 50 && value <= 75) return 'rgba(245, 158, 11, 0.6)'; // Kuning (Tailwind yellow-500 opacity 60%)
                        return 'rgba(34, 197, 94, 0.6)'; // Hijau (Tailwind green-500 opacity 60%)
                    };
                    const getBorderColor = (value) => {
                        if (value < 50) return 'rgba(239, 68, 68, 1)'; // Merah solid
                        if (value >= 50 && value <= 75) return 'rgba(245, 158, 11, 1)'; // Kuning solid
                        return 'rgba(34, 197, 94, 1)'; // Hijau solid
                    };
                    // ▲▲▲ AKHIR FUNGSI WARNA ▲▲▲

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartInfo.labels,
                            datasets: [{
                                label: 'Kehadiran (%)',
                                data: chartInfo.data,
                                // ▼▼▼ WARNA DINAMIS ▼▼▼
                                backgroundColor: chartInfo.data.map(value => getBarColor(value)),
                                borderColor: chartInfo.data.map(value => getBorderColor(value)),
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + '%';
                                        }
                                    }
                                },
                                // ▼▼▼ KONFIGURASI DATALABELS ▼▼▼
                                datalabels: {
                                    anchor: 'end', // Posisi label di ujung atas bar
                                    align: 'top', // Rata atas
                                    formatter: (value, context) => {
                                        return value + '%'; // Format angka dengan '%'
                                    },
                                    color: '#6b7280', // Warna teks label (abu-abu Tailwind)
                                    font: {
                                        weight: 'bold'
                                    }
                                }
                                // ▲▲▲ AKHIR KONFIGURASI DATALABELS ▲▲▲
                            }
                        }
                    });
                } else {
                    console.warn('Canvas element not found for ID:', canvasId);
                }
            }
        }
        // --- AKHIR KODE MULTIPLE GRAFIK ---

        // Fungsi pembantu untuk "Lihat Lainnya" / "Sembunyikan"
        function setupLihatLainnya(btnLihatId, btnSembunyiId, listContainerId, itemClass) {
            const btnLihat = document.getElementById(btnLihatId);
            const btnSembunyi = document.getElementById(btnSembunyiId);
            const listContainer = document.getElementById(listContainerId);

            if (btnLihat && btnSembunyi && listContainer) {
                // Event saat klik "Lihat Lainnya"
                btnLihat.addEventListener('click', function() {
                    // Tampilkan semua item
                    listContainer.querySelectorAll('.' + itemClass).forEach(item => {
                        item.classList.remove('hidden');
                    });

                    // Sembunyikan tombol "Lihat Lainnya"
                    this.classList.add('hidden');

                    // Tampilkan tombol "Sembunyikan"
                    btnSembunyi.classList.remove('hidden');

                    // PENTING: JANGAN ubah maxHeight, biarkan tetap scrollable
                });

                // Event saat klik "Sembunyikan"
                btnSembunyi.addEventListener('click', function() {
                    // Sembunyikan kembali item-item
                    listContainer.querySelectorAll('.' + itemClass).forEach(item => {
                        item.classList.add('hidden');
                    });

                    // Sembunyikan tombol "Sembunyikan"
                    this.classList.add('hidden');

                    // Tampilkan kembali tombol "Lihat Lainnya"
                    btnLihat.classList.remove('hidden');

                    // Kembalikan list ke posisi scroll paling atas
                    listContainer.scrollTop = 0;
                });
            }
        }

        // Terapkan fungsi ke kedua kartu
        setupLihatLainnya('btn-lihat-lainnya-terlewat', 'btn-sembunyikan-terlewat', 'list-terlewat-kosong', 'hidden-item-terlewat');
        setupLihatLainnya('btn-lihat-lainnya-tanpa-pengajar', 'btn-sembunyikan-tanpa-pengajar', 'list-tanpa-pengajar', 'hidden-item-tanpa-pengajar');
    });
</script>

<?php $conn->close(); ?>
<!-- Di sini Anda bisa menyertakan footer -->