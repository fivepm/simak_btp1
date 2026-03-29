<?php
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

// Cek Sesi Login (Pastikan yang login adalah Admin Kelompok)
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

// Ambil nama kelompok admin dari session
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';
$nama_kelompok_login = $admin_kelompok;

// Mapping nama kelompok berdasarkan ID (Definisi manual)
$DATA_KELOMPOK = [
    1 => 'bintaran',
    2 => 'gedongkuning',
    3 => 'jombor',
    4 => 'sunten'
];

// Mencari kelompok_id berupa angka berdasarkan nama kelompok dari session
$kelompok_id_login = array_search($nama_kelompok_login, $DATA_KELOMPOK);

// Validasi jika kelompok tidak ada di array
if (!$kelompok_id_login) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Data kelompok Anda tidak valid atau tidak ditemukan.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ==========================================================
// 1. GET DATA (Memuat Data Laporan ke Form)
// ==========================================================
if ($action === 'get_laporan') {
    $periode_id = (int)($_GET['periode_id'] ?? 0);

    if (!$periode_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID Periode tidak valid.']);
        exit;
    }

    try {
        // Ambil data laporan khusus untuk kelompok yang sedang login
        $stmt = $conn->prepare("SELECT id, status, checklist_musyawarah, data_kepengurusan, detail_kelas, permasalahan FROM laporan_pjp_kelompok WHERE periode_id = ? AND kelompok_id = ? LIMIT 1");
        $stmt->bind_param("ii", $periode_id, $kelompok_id_login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Laporan belum di-generate oleh Admin Desa untuk periode ini.");
        }

        $row = $result->fetch_assoc();

        // --- AMBIL DATA KEPENGURUSAN ASLI DARI DATABASE JIKA MASIH KOSONG ---
        $kepengurusan_asli = [];
        if (!empty($row['data_kepengurusan'])) {
            // Jika sudah pernah disave sebelumnya (Snapshot sudah ada)
            $kepengurusan_asli = json_decode($row['data_kepengurusan'], true);
        } else {
            // Jika masih NULL (Baru pertama kali dibuka), ambil live dari tabel kepengurusan
            $nama_pengawas = '-';
            $nama_ketua = '-';
            $nama_wakil = '-';
            $arr_sekretaris = [];
            $arr_bendahara = [];
            $wali_kelas_asli = [];

            // Query 1 kali untuk mengambil semua yang relevan
            $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan, kelas FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Wali Kelas')");
            $q_pengurus->bind_param("s", $nama_kelompok_login);
            $q_pengurus->execute();
            $res_pengurus = $q_pengurus->get_result();

            if ($res_pengurus) {
                while ($p = $res_pengurus->fetch_assoc()) {
                    $jabatan = strtolower(trim($p['jabatan']));

                    if ($jabatan === 'pengawas') {
                        $nama_pengawas = $p['nama_pengurus'];
                    } elseif ($jabatan === 'ketua') {
                        $nama_ketua = $p['nama_pengurus'];
                    } elseif ($jabatan === 'wakil') {
                        $nama_wakil = $p['nama_pengurus'];
                    } elseif ($jabatan === 'sekretaris') {
                        $arr_sekretaris[] = $p['nama_pengurus']; // Push ke array jika > 1
                    } elseif ($jabatan === 'bendahara') {
                        $arr_bendahara[] = $p['nama_pengurus'];
                    } elseif ($jabatan === 'wali kelas') {
                        if (!empty($p['kelas'])) {
                            $wali_kelas_asli[$p['kelas']] = $p['nama_pengurus'];
                        }
                    }
                }
            }
            $q_pengurus->close();

            // Gabungkan nama sekretaris dengan koma jika lebih dari 1
            $nama_sekretaris = !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-';
            $nama_bendahara = !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-';

            // Susun menjadi array untuk dikirim ke frontend
            $kepengurusan_asli = [
                'pengawas' => $nama_pengawas,
                'ketua' => $nama_ketua,
                'wakil' => $nama_wakil,
                'sekretaris' => $nama_sekretaris,
                'bendahara' => $nama_bendahara,
                'wali_kelas' => $wali_kelas_asli
            ];
        }

        // Parse data JSON dari database menjadi Array PHP
        $data_response = [
            'laporan_id' => $row['id'],
            'status' => $row['status'],
            'checklist' => $row['checklist_musyawarah'] ? json_decode($row['checklist_musyawarah'], true) : ['pjp' => false, 'unsur' => false],
            'detail_kelas' => $row['detail_kelas'] ? json_decode($row['detail_kelas'], true) : [],
            'permasalahan' => $row['permasalahan'] ? json_decode($row['permasalahan'], true) : [],
            'kepengurusan' => $kepengurusan_asli
        ];

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $data_response]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. POST DATA (Simpan Draft / Final)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'simpan_laporan') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);
    $target_status = $_POST['status'] ?? 'DRAFT'; // DRAFT atau FINAL

    // Menerima data JSON dari Frontend
    $json_checklist = $_POST['checklist_musyawarah'] ?? '{}';
    $json_detail_kelas = $_POST['detail_kelas'] ?? '[]';
    $json_permasalahan = $_POST['permasalahan'] ?? '[]';

    // Opsional: Jika kamu ingin menyimpan ulang data_kepengurusan saat Simpan Draft/Final
    // (Bisa dikirim dari frontend jika dibutuhkan untuk snapshot)

    $conn->begin_transaction();
    try {
        // Cek apakah laporan tersebut milik kelompok ini dan belum di TTD
        $cek = $conn->prepare("SELECT status, data_kepengurusan FROM laporan_pjp_kelompok WHERE id = ? AND kelompok_id = ?");
        $cek->bind_param("ii", $laporan_id, $kelompok_id_login);
        $cek->execute();
        $res_cek = $cek->get_result();

        if ($res_cek->num_rows === 0) {
            throw new Exception("Laporan tidak valid atau Anda tidak memiliki akses.");
        }

        $row_cek = $res_cek->fetch_assoc();
        if ($row_cek['status'] === 'TTD_KETUA') {
            throw new Exception("Laporan sudah ditandatangani Ketua, tidak dapat diubah lagi.");
        }

        // --- SIMPAN SNAPSHOT KEPENGURUSAN JIKA MASIH KOSONG ---
        // Ini memastikan saat diubah ke DRAFT/FINAL, data kepengurusan ikut terkunci
        if (empty($row_cek['data_kepengurusan'])) {
            $nama_pengawas = '-';
            $nama_ketua = '-';
            $nama_wakil = '-';
            $arr_sekretaris = [];
            $arr_bendahara = [];
            $wali_kelas_asli = [];
            $q_pengurus = $conn->prepare("SELECT nama_pengurus, jabatan, kelas FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = ? AND jabatan IN ('Pengawas', 'Ketua', 'Wakil', 'Sekretaris', 'Bendahara', 'Wali Kelas')");
            $q_pengurus->bind_param("s", $nama_kelompok_login);
            $q_pengurus->execute();
            $res_pengurus = $q_pengurus->get_result();
            if ($res_pengurus) {
                while ($p = $res_pengurus->fetch_assoc()) {
                    $jabatan = strtolower(trim($p['jabatan']));
                    if ($jabatan === 'pengawas') $nama_pengawas = $p['nama_pengurus'];
                    elseif ($jabatan === 'ketua') $nama_ketua = $p['nama_pengurus'];
                    elseif ($jabatan === 'wakil') $nama_wakil = $p['nama_pengurus'];
                    elseif ($jabatan === 'sekretaris') $arr_sekretaris[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'bendahara') $arr_bendahara[] = $p['nama_pengurus'];
                    elseif ($jabatan === 'wali kelas' && !empty($p['kelas'])) $wali_kelas_asli[$p['kelas']] = $p['nama_pengurus'];
                }
            }
            $json_kepengurusan = json_encode([
                'pengawas' => $nama_pengawas,
                'ketua' => $nama_ketua,
                'wakil' => $nama_wakil,
                'sekretaris' => !empty($arr_sekretaris) ? implode(', ', $arr_sekretaris) : '-',
                'bendahara' => !empty($arr_bendahara) ? implode(', ', $arr_bendahara) : '-',
                'wali_kelas' => $wali_kelas_asli
            ]);

            // Update beserta Snapshot Kepengurusan
            $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = ?, checklist_musyawarah = ?, detail_kelas = ?, permasalahan = ?, data_kepengurusan = ? WHERE id = ?");
            $stmt_update->bind_param("sssssi", $target_status, $json_checklist, $json_detail_kelas, $json_permasalahan, $json_kepengurusan, $laporan_id);
        } else {
            // Update tanpa menyentuh data_kepengurusan yang sudah ada
            $stmt_update = $conn->prepare("UPDATE laporan_pjp_kelompok SET status = ?, checklist_musyawarah = ?, detail_kelas = ?, permasalahan = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $target_status, $json_checklist, $json_detail_kelas, $json_permasalahan, $laporan_id);
        }

        $stmt_update->execute();

        $conn->commit();
        writeLog('UPDATE', "Admin Kelompok $kelompok_id_login menyimpan Laporan PJP (ID: $laporan_id) dengan status: $target_status");

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => $target_status === 'FINAL' ? 'Laporan berhasil difinalkan.' : 'Draft berhasil disimpan.'
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
