<?php
require_once '../../../../config/config.php';
require_once '../../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

// Validasi session (Khusus Admin Desa)
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Definisi Standar Kelompok & Kelas SIMAK
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];

$DATA_KELAS = ['PAUD', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];

// ==========================================================
// 1. GET LIST (Untuk me-render 2 halaman tabel daftar periode)
// ==========================================================
if ($action === 'get_list') {
    $total_kelompok_hardcode = count($DATA_KELOMPOK);

    // Query ini akan mengambil seluruh daftar periode dan merekap status kelompok + desa
    $query = "
        SELECT 
            p.id, 
            p.nama_periode, 
            p.tanggal_selesai as tanggal_akhir,
            DATE_FORMAT(p.tanggal_selesai, '%d %b %Y') as tanggal_akhir_format,
            (SELECT COUNT(id) FROM laporan_pjp_kelompok WHERE periode_id = p.id) as cek_generated,
            $total_kelompok_hardcode as total_kelompok,
            (SELECT COUNT(id) FROM laporan_pjp_kelompok WHERE periode_id = p.id AND status = 'TTD_KETUA') as kelompok_selesai,
            (SELECT status FROM laporan_pjp_desa WHERE periode_id = p.id LIMIT 1) as status_desa
        FROM periode p
        ORDER BY p.tanggal_selesai DESC
    ";

    try {
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query Database Error: " . $conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            // is_generated bernilai true jika Admin Desa sudah pernah klik tombol "Generate Laporan"
            $row['is_generated'] = (int)$row['cek_generated'] > 0;
            $data[] = $row;
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. GENERATE DRAFT MASSAL KELOMPOK
// ==========================================================
elseif ($action === 'generate_draft') {
    $periode_id = (int)($_POST['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Mencegah Generate Ganda
        $cek = $conn->prepare("SELECT id FROM laporan_pjp_kelompok WHERE periode_id = ? LIMIT 1");
        $cek->bind_param("i", $periode_id);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows > 0) {
            throw new Exception("Akses laporan untuk periode ini sudah pernah dibuat sebelumnya.");
        }
        $cek->close();

        // Membuat template JSON Detail Kelas kosong untuk memancing Form Kelompok
        $template_kelas = [];
        foreach ($DATA_KELAS as $kelas) {
            $template_kelas[] = [
                'nama_kelas' => $kelas,
                'jml_siswa' => 0,
                'jml_guru' => 0,
                'kehadiran' => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0],
                'ketercapaian_global' => 0,
                'ketercapaian_kategori' => [],
                'penyelenggara' => 'kelompok',
                'tatap_muka' => 0
            ];
        }
        $json_detail_kelas = json_encode($template_kelas);

        // Insert Status DRAFT untuk keempat kelompok agar muncul di akun Admin Kelompok
        $stmt = $conn->prepare("INSERT INTO laporan_pjp_kelompok (periode_id, kelompok_id, status, detail_kelas) VALUES (?, ?, 'DRAFT', ?)");
        foreach ($DATA_KELOMPOK as $k_id => $nama_kelompok) {
            $stmt->bind_param("iis", $periode_id, $k_id, $json_detail_kelas);
            $stmt->execute();
        }

        $conn->commit();
        writeLog('INSERT', "Admin Desa me-generate akses draft laporan PJP untuk periode ID: $periode_id");

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Akses laporan PJP berhasil dibuka untuk seluruh kelompok.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
