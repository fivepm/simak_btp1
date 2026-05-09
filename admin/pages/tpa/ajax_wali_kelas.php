<?php
// pages/tpa/ajax_wali_kelas.php
session_start();

require_once '../../../config/config.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa'; 
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

try {
    switch ($action) {
        
        // --- 1. MENGAMBIL DATA SEMUA KELAS PER KELOMPOK ---
        case 'get_data':
            // A. Ambil daftar kelompok (Filter jika admin kelompok)
            $kelompok_list = [];
            if ($admin_tingkat === 'kelompok') {
                $kelompok_list[] = $admin_kelompok;
            } else {
                $res_kel = $conn->query("SELECT nama_kelompok FROM kelompok ORDER BY nama_kelompok ASC");
                while ($r = $res_kel->fetch_assoc()) $kelompok_list[] = $r['nama_kelompok'];
            }

            // B. Ambil daftar semua kelas master
            $kelas_list = [];
            $res_kls = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY id ASC");
            while ($r = $res_kls->fetch_assoc()) $kelas_list[] = $r;

            // C. Ambil data wali kelas yang sudah ada
            $wali_data = [];
            $res_wali = $conn->query("SELECT w.id, w.id_kelas, w.kelompok, g.nama FROM wali_kelas w JOIN guru g ON w.id_guru = g.id");
            while ($r = $res_wali->fetch_assoc()) {
                // Ubah nama kelompok dari tabel guru/wali_kelas menjadi lowercase
                $k_kel_wali = strtolower(trim($r['kelompok']));
                // Simpan dalam format array multidimensi [kelompok][id_kelas]
                $wali_data[$k_kel_wali][$r['id_kelas']] = $r;
            }

            // D. Hitung jumlah murid (Sesuai struktur tabel PESERTA milikmu)
            $murid_data = [];
            // Mengambil dari tabel peserta, digroup berdasarkan kelompok dan kelas.
            // Hanya menghitung peserta yang statusnya 'Aktif' agar lebih akurat.
            $res_murid = $conn->query("SELECT kelompok, kelas, COUNT(id) as jml FROM peserta WHERE status = 'Aktif' GROUP BY kelompok, kelas");
            if ($res_murid) {
                while ($r = $res_murid->fetch_assoc()) {
                    // Ubah key menjadi lowercase dan hapus spasi berlebih untuk pencocokan yang aman
                    $k_kel = strtolower(trim($r['kelompok']));
                    $k_kls = strtolower(trim($r['kelas']));
                    $murid_data[$k_kel][$k_kls] = $r['jml'];
                }
            }

            // E. Rangkai Data Final
            $final_data = [];
            foreach ($kelompok_list as $kel) {
                $final_data[$kel] = [];
                foreach ($kelas_list as $kls) {
                    $id_kelas = $kls['id'];
                    $nama_kelas = $kls['nama_kelas'];
                    
                    // Cocokkan data wali dan murid dengan merubah string yang dicari menjadi lowercase juga
                    $match_kel = strtolower(trim($kel));
                    $match_kls = strtolower(trim($nama_kelas));
                    
                    $wali = $wali_data[$match_kel][$id_kelas] ?? null;
                    $jml_murid = $murid_data[$match_kel][$match_kls] ?? 0;

                    $final_data[$kel][] = [
                        'id_kelas' => $id_kelas,
                        'nama_kelas' => $nama_kelas,
                        'id_wali' => $wali ? $wali['id'] : null,
                        'nama_guru' => $wali ? $wali['nama'] : null,
                        'jml_murid' => (int)$jml_murid
                    ];
                }
            }

            echo json_encode(['status' => 'success', 'data' => $final_data]);
            break;

        // --- 2. OPTION AWAL (SAAT BUKA MODAL) ---
        case 'get_options':
            $kelompok = [];
            $guru_kelompok_ini = [];

            if ($admin_tingkat === 'desa') {
                // Admin desa butuh list kelompok
                $res_kelp = $conn->query("SELECT nama_kelompok FROM kelompok ORDER BY nama_kelompok ASC");
                while ($row = $res_kelp->fetch_assoc()) $kelompok[] = $row;
            } else {
                // Admin kelompok langsung dapat list guru di kelompoknya
                $kel_safe = $conn->real_escape_string($admin_kelompok);
                $res_guru = $conn->query("SELECT id, nama FROM guru WHERE kelompok = '$kel_safe' AND deleted_at IS NULL ORDER BY nama ASC");
                while ($row = $res_guru->fetch_assoc()) $guru_kelompok_ini[] = $row;
            }

            echo json_encode([
                'status' => 'success', 
                'kelompok' => $kelompok, 
                'guru' => $guru_kelompok_ini,
                'admin_tingkat' => $admin_tingkat
            ]);
            break;

        // --- 3. AMBIL GURU BERDASARKAN KELOMPOK (KHUSUS ADMIN DESA) ---
        case 'get_guru_by_kelompok':
            $kel = $conn->real_escape_string($_POST['kelompok'] ?? '');
            $guru = [];
            if(!empty($kel)){
                $res = $conn->query("SELECT id, nama FROM guru WHERE kelompok = '$kel' AND deleted_at IS NULL ORDER BY nama ASC");
                while ($row = $res->fetch_assoc()) $guru[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $guru]);
            break;

        // --- 4. AMBIL KELAS BERDASARKAN JADWAL MENGAJAR GURU ---
        case 'get_kelas_by_guru':
            $id_guru = intval($_POST['id_guru'] ?? 0);
            $sql = "SELECT k.id, k.nama_kelas 
                    FROM kelas k 
                    JOIN pengampu p ON k.nama_kelas = p.nama_kelas 
                    WHERE p.id_guru = ? 
                    ORDER BY k.id ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_guru);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $kelas = [];
            while ($row = $res->fetch_assoc()) {
                $kelas[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $kelas]);
            break;

        // --- 5. SIMPAN PENUGASAN ---
        case 'add_data':
            $id_guru = $_POST['id_guru'] ?? '';
            $id_kelas = $_POST['id_kelas'] ?? '';
            $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '-');

            if (empty($id_guru) || empty($id_kelas)) {
                echo json_encode(['status' => 'error', 'message' => 'Guru dan Kelas wajib diisi!']);
                exit;
            }

            // Cari kelompok guru
            $stmt_guru = $conn->prepare("SELECT kelompok FROM guru WHERE id = ?");
            $stmt_guru->bind_param("i", $id_guru);
            $stmt_guru->execute();
            $res_guru = $stmt_guru->get_result();
            if ($res_guru->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Data Guru tidak ditemukan!']);
                exit;
            }
            $kelompok = $res_guru->fetch_assoc()['kelompok'];
            $stmt_guru->close();

            // Cek duplikasi
            $stmt_cek = $conn->prepare("SELECT id FROM wali_kelas WHERE id_kelas = ? AND kelompok = ? AND tahun_ajaran = ?");
            $stmt_cek->bind_param("iss", $id_kelas, $kelompok, $tahun_ajaran);
            $stmt_cek->execute();
            if ($stmt_cek->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Kelas tersebut sudah memiliki Wali Kelas!']);
                exit;
            }
            $stmt_cek->close();

            // Insert
            $stmt = $conn->prepare("INSERT INTO wali_kelas (id_guru, id_kelas, kelompok, tahun_ajaran) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $id_guru, $id_kelas, $kelompok, $tahun_ajaran);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Wali Kelas berhasil ditugaskan!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data.']);
            }
            $stmt->close();
            break;

        // --- 6. HAPUS PENUGASAN ---
        case 'delete_data':
            $id = $_POST['id'] ?? 0;
            if ($admin_tingkat === 'kelompok') {
                $kel_safe = $conn->real_escape_string($admin_kelompok);
                $stmt = $conn->prepare("DELETE FROM wali_kelas WHERE id = ? AND kelompok = ?");
                $stmt->bind_param("is", $id, $kel_safe);
            } else {
                $stmt = $conn->prepare("DELETE FROM wali_kelas WHERE id = ?");
                $stmt->bind_param("i", $id);
            }
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Data Wali Kelas berhasil dihapus!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']);
            }
            $stmt->close();
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid!']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
?>