<?php
// === FILE BACKEND: ajax_daftar_guru.php ===
require_once '../../../../config/config.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// ==========================================================
// 1. GET DATA (Untuk Render Tabel)
// ==========================================================
if ($action === 'get_data') {
    $filter_kelompok = $_GET['kelompok'] ?? 'semua';
    $filter_kelas = $_GET['kelas'] ?? 'semua';

    if ($admin_tingkat === 'kelompok') {
        $filter_kelompok = $admin_kelompok;
    }

    $sql = "
        SELECT g.*, 
               GROUP_CONCAT(p.nama_kelas SEPARATOR ', ') as list_kelas,
               GROUP_CONCAT(p.nama_kelas) as raw_kelas 
        FROM guru g
        LEFT JOIN pengampu p ON g.id = p.id_guru
    ";
    $where_conditions = ["g.deleted_at IS NULL"];
    $params = [];
    $types = "";

    if ($filter_kelompok !== 'semua') {
        $where_conditions[] = "g.kelompok = ?";
        $params[] = $filter_kelompok;
        $types .= "s";
    }

    if ($filter_kelas !== 'semua') {
        $where_conditions[] = "p.nama_kelas = ?";
        $params[] = $filter_kelas;
        $types .= "s";
    }

    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    $sql .= " GROUP BY g.id ORDER BY g.kelompok ASC, g.nama ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    $waktu_sekarang = time();

    while ($row = $res->fetch_assoc()) {
        $status_online = 'offline';
        $waktu_terakhir_aktif = '-';

        if (!empty($row['last_login'])) {
            $waktu_login = strtotime($row['last_login']);
            if (($waktu_sekarang - $waktu_login) < 900) {
                $status_online = 'online';
            } else {
                $waktu_terakhir_aktif = date('d/m/Y H:i', $waktu_login);
            }
        }

        // Nomor disensor agar bisa dilempar ke form Edit, tapi tidak ditampilkan di tabel frontend
        if (!empty($row['nomor_wa'])) {
            $row['nomor_wa'] = substr($row['nomor_wa'], 0, 4) . ' **** ****';
        }

        $row['status_login'] = $status_online;
        $row['terakhir_login'] = $waktu_terakhir_aktif;

        $data[] = $row;
    }
    $stmt->close();

    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}
