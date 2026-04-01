<?php
if (!isset($conn)) {
    die("Koneksi database tidak ditemukan.");
}
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// === AMBIL DATA PERIODE ===
$periode_list = [];
$sql_periode = "SELECT id, nama_periode, tanggal_mulai, tanggal_selesai FROM periode WHERE status = 'Aktif' ORDER BY tanggal_mulai DESC";
$result_periode = $conn->query($sql_periode);
if ($result_periode) {
    while ($row = $result_periode->fetch_assoc()) {
        $periode_list[] = $row;
    }
}

// === TENTUKAN PERIODE DEFAULT ===
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

// === AMBIL FILTER DARI URL ===
$selected_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : $default_periode_id;
$selected_kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : '-';
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '-';

if ($admin_tingkat === 'kelompok') {
    $selected_kelompok = $admin_kelompok;
}

// === CEK KELENGKAPAN FILTER ===
// Jika filter kelompok dan kelas bukan '-', berarti filter lengkap
$is_filter_complete = ($selected_periode_id && $selected_kelompok !== '-' && $selected_kelas !== '-');

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

// Inisialisasi variabel wadah data
$rekap_data = [];
$detail_kehadiran = [];
$tanggal_jadwal = [];
$rincian_per_siswa = [];
$rata_rata_kehadiran = 0;

// === AMBIL DATA JIKA FILTER LENGKAP ===
if ($is_filter_complete) {
    // 1. Ambil data ringkasan (summary)
    $sql_summary = "SELECT 
                    p.nama_lengkap,
                    COUNT(rp.id) as total_pertemuan,
                    SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                    SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                    SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa,
                    COUNT(rp.id) as total_diisi,
                    IF(COUNT(rp.id) > 0, 
                        (SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) / COUNT(rp.status_kehadiran)) * 100, 
                        0
                        ) as persentase
                    FROM peserta p
                    LEFT JOIN rekap_presensi rp ON p.id = rp.peserta_id
                    LEFT JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id 
                    WHERE jp.periode_id = ? AND p.kelompok = ? AND p.kelas = ?
                    GROUP BY p.id, p.nama_lengkap
                    ORDER BY p.nama_lengkap ASC";

    $stmt_summary = $conn->prepare($sql_summary);
    $stmt_summary->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_summary->execute();
    $result_summary = $stmt_summary->get_result();

    $total_persentase_kelas = 0;
    if ($result_summary) {
        while ($row = $result_summary->fetch_assoc()) {
            $rekap_data[] = $row;
            $total_persentase_kelas += $row['persentase'];
        }
    }

    $jumlah_peserta = count($rekap_data);
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

    // 4. Ambil Jurnal dari jadwal_presensi
    $sql_rinci_siswa = "SELECT p.nama_lengkap, jp.tanggal, jp.jam_mulai, rp.status_kehadiran, rp.keterangan 
                        FROM rekap_presensi rp
                        JOIN peserta p ON rp.peserta_id = p.id
                        JOIN jadwal_presensi jp ON rp.jadwal_id = jp.id
                        WHERE jp.periode_id = ? AND jp.kelompok = ? AND jp.kelas = ?
                        ORDER BY p.nama_lengkap, jp.tanggal, jp.jam_mulai";
    $stmt_rinci_siswa = $conn->prepare($sql_rinci_siswa);
    $stmt_rinci_siswa->bind_param("iss", $selected_periode_id, $selected_kelompok, $selected_kelas);
    $stmt_rinci_siswa->execute();
    $result_rinci_siswa = $stmt_rinci_siswa->get_result();
    if ($result_rinci_siswa) {
        while ($row = $result_rinci_siswa->fetch_assoc()) {
            $rincian_per_siswa[$row['nama_lengkap']][] = $row;
        }
    }
}

// Fungsi bantu format tanggal (Jika dibutuhkan oleh view)
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
