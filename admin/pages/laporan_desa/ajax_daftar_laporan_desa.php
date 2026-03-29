<?php
// Path config disesuaikan dengan struktur folder (asumsi mundur 2 folder ke root)
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();

// Set Header JSON
header('Content-Type: application/json');

// Cek Sesi Login
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// Definisi Data Manual (Hardcode karena tabel belum ada)
$DATA_KELOMPOK = [
    1 => 'Bintaran',
    2 => 'Gedongkuning',
    3 => 'Jombor',
    4 => 'Sunten'
];
$DATA_KELAS = ['PAUD', 'Caberawit A', 'Caberawit B', 'Pra Remaja', 'Remaja', 'Pra Nikah'];


// ==========================================================
// 1. GET DATA (Untuk Render Tabel Daftar Periode)
// ==========================================================
if ($action === 'get_list') {
    $total_kelompok_hardcode = count($DATA_KELOMPOK);

    // Query mengambil data periode beserta status laporannya
    // UPDATE: Disesuaikan dengan nama kolom tabel periode aslimu
    $query = "
        SELECT 
            p.id, 
            p.nama_periode, 
            p.tanggal_selesai,
            p.tanggal_selesai as tanggal_akhir,
            DATE_FORMAT(p.tanggal_selesai, '%d %b %Y') as tanggal_akhir_format,
            (SELECT COUNT(*) FROM laporan_pjp_kelompok WHERE periode_id = p.id) as cek_generated,
            $total_kelompok_hardcode as total_kelompok,
            (SELECT COUNT(*) FROM laporan_pjp_kelompok WHERE periode_id = p.id AND status = 'TTD_KETUA') as kelompok_selesai,
            (SELECT status FROM laporan_pjp_desa WHERE periode_id = p.id LIMIT 1) as status_desa
        FROM periode p
        ORDER BY p.tanggal_selesai DESC
    ";

    try {
        $result = $conn->query($query);

        // Mencegah Fatal Error HTML jika Query gagal dieksekusi
        if (!$result) {
            throw new Exception("Query Database Error: " . $conn->error);
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {
            // Tentukan flag boolean is_generated untuk mempermudah frontend
            $row['is_generated'] = ($row['cek_generated'] > 0);
            $data[] = $row;
        }

        ob_clean(); // Pastikan tidak ada output lain sebelum json_encode
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        ob_clean(); // Sapu bersih output sebelum mengirim error json
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. PROSES POST (Generate Draft Laporan Kelompok Massal)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate_draft') {
    $periode_id = (int)($_POST['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Cek apakah sudah digenerate sebelumnya untuk mencegah duplikasi
        $stmt_cek = $conn->prepare("SELECT id FROM laporan_pjp_kelompok WHERE periode_id = ? LIMIT 1");
        $stmt_cek->bind_param("i", $periode_id);
        $stmt_cek->execute();
        if ($stmt_cek->get_result()->num_rows > 0) {
            throw new Exception("Laporan untuk periode ini sudah pernah di-generate.");
        }
        $stmt_cek->close();

        // 2. Siapkan struktur JSON awal untuk detail_kelas
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

        // 3. Looping untuk insert DRAFT ke tabel laporan_pjp_kelompok berdasarkan array hardcode
        $stmt_insert = $conn->prepare("INSERT INTO laporan_pjp_kelompok (periode_id, kelompok_id, status, detail_kelas) VALUES (?, ?, 'DRAFT', ?)");

        foreach ($DATA_KELOMPOK as $k_id => $nama_kelompok) {
            $stmt_insert->bind_param("iis", $periode_id, $k_id, $json_detail_kelas);
            $stmt_insert->execute();
        }
        $stmt_insert->close();

        // 4. Commit dan catat ke Log
        $conn->commit();
        writeLog('INSERT', "Generate massal Draft Laporan PJP Kelompok untuk Periode ID: $periode_id");

        ob_clean(); // Pastikan murni JSON
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil membuat draft laporan untuk seluruh kelompok.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean(); // Sapu bersih output
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
