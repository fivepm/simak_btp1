<?php
// Pastikan koneksi database tersedia
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}

$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

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
if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
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
