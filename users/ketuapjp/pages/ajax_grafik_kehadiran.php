<?php
// === FILE BACKEND: ajax_grafik_kehadiran.php ===
// Letakkan di folder: pages/report/ajax_grafik_kehadiran.php
require_once '../../../config/config.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir.']);
    exit;
}

$ketuapjp_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$ketuapjp_kelompok = $_SESSION['user_kelompok'] ?? '';

$periode_id = $_GET['periode_id'] ?? null;
if (!$periode_id) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$where_admin = ($ketuapjp_tingkat === 'kelompok') ? " AND p.kelompok = '$ketuapjp_kelompok' " : "";

$sql_grafik = "
    SELECT 
        LOWER(p.kelompok) as kelompok, LOWER(p.kelas) as kelas,
        COUNT(DISTINCT jp.id) AS total_jadwal_periode, 
        SUM(CASE WHEN rp.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN rp.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN rp.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN rp.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) AS alpa
    FROM (SELECT DISTINCT kelompok, kelas FROM peserta WHERE kelompok IS NOT NULL AND kelas IS NOT NULL) p
    LEFT JOIN jadwal_presensi jp ON p.kelompok = jp.kelompok AND p.kelas = jp.kelas AND jp.periode_id = ?
    LEFT JOIN rekap_presensi rp ON jp.id = rp.jadwal_id
    WHERE p.kelompok IS NOT NULL AND p.kelompok != '' AND p.kelas IS NOT NULL AND p.kelas != '' $where_admin
    GROUP BY p.kelompok, p.kelas
";

$stmt = $conn->prepare($sql_grafik);
$stmt->bind_param("i", $periode_id);
$stmt->execute();
$res = $stmt->get_result();

$grafik_data = [];
while ($row = $res->fetch_assoc()) {
    $grafik_data[$row['kelompok']][$row['kelas']] = $row;
}
$stmt->close();

echo json_encode(['status' => 'success', 'data' => $grafik_data]);
