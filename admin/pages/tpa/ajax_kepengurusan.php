<?php
// pages/tpa/ajax_kepengurusan.php
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

// Struktur kerangka jabatan agar urutannya selalu tetap
$struktur_jabatan = [
    'Pengawas' => [],
    'Wakil' => [],
    'Sekretaris' => [],
    'Bendahara' => []
];

try {
    switch ($action) {
        
        // --- 1. MENGAMBIL DATA KEPENGURUSAN ---
        case 'get_data':
            $data = [
                'desa' => ['ketua' => null, 'pengurus' => $struktur_jabatan],
                'kelompok' => []
            ];

            if ($admin_tingkat === 'desa') {
                // Fetch Desa (Abaikan Wali Kelas)
                $res = $conn->query("SELECT id, nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'desa' AND jabatan != 'Wali Kelas' ORDER BY FIELD(jabatan, 'Pengawas', 'Wakil', 'Sekretaris', 'Bendahara'), id ASC");
                while ($r = $res->fetch_assoc()) {
                    if ($r['jabatan'] === 'Ketua') {
                        $data['desa']['ketua'] = $r;
                    } else {
                        // Jika ada jabatan di luar standar, buatkan arraynya otomatis
                        if (!isset($data['desa']['pengurus'][$r['jabatan']])) {
                            $data['desa']['pengurus'][$r['jabatan']] = [];
                        }
                        $data['desa']['pengurus'][$r['jabatan']][] = ['id' => $r['id'], 'nama_pengurus' => $r['nama_pengurus']];
                    }
                }

                // Fetch Semua Kelompok (Untuk Dilihat Saja)
                $res_kel = $conn->query("SELECT id, nama_pengurus, jabatan, kelompok FROM kepengurusan WHERE tingkat = 'kelompok' AND jabatan != 'Wali Kelas' ORDER BY kelompok ASC, FIELD(jabatan, 'Pengawas', 'Wakil', 'Sekretaris', 'Bendahara'), id ASC");
                while ($r = $res_kel->fetch_assoc()) {
                    $kel = $r['kelompok'];
                    if (!isset($data['kelompok'][$kel])) {
                        $data['kelompok'][$kel] = ['ketua' => null, 'pengurus' => $struktur_jabatan];
                    }
                    if ($r['jabatan'] === 'Ketua') {
                        $data['kelompok'][$kel]['ketua'] = $r;
                    } else {
                        if (!isset($data['kelompok'][$kel]['pengurus'][$r['jabatan']])) {
                            $data['kelompok'][$kel]['pengurus'][$r['jabatan']] = [];
                        }
                        $data['kelompok'][$kel]['pengurus'][$r['jabatan']][] = ['id' => $r['id'], 'nama_pengurus' => $r['nama_pengurus']];
                    }
                }
            } else {
                // Fetch Khusus Kelompok Admin Tersebut
                $kel_safe = $conn->real_escape_string($admin_kelompok);
                $data['kelompok'][$kel_safe] = ['ketua' => null, 'pengurus' => $struktur_jabatan];

                $res = $conn->query("SELECT id, nama_pengurus, jabatan FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = '$kel_safe' AND jabatan != 'Wali Kelas' ORDER BY FIELD(jabatan, 'Pengawas', 'Wakil', 'Sekretaris', 'Bendahara'), id ASC");
                while ($r = $res->fetch_assoc()) {
                    if ($r['jabatan'] === 'Ketua') {
                        $data['kelompok'][$kel_safe]['ketua'] = $r;
                    } else {
                        if (!isset($data['kelompok'][$kel_safe]['pengurus'][$r['jabatan']])) {
                            $data['kelompok'][$kel_safe]['pengurus'][$r['jabatan']] = [];
                        }
                        $data['kelompok'][$kel_safe]['pengurus'][$r['jabatan']][] = ['id' => $r['id'], 'nama_pengurus' => $r['nama_pengurus']];
                    }
                }
            }

            echo json_encode(['status' => 'success', 'data' => $data, 'admin_tingkat' => $admin_tingkat]);
            break;

        // --- 2. SINKRONISASI KETUA DARI TABEL USERS ---
        case 'sync_ketua':
            $tingkat = $admin_tingkat;
            $kelompok = ($tingkat === 'kelompok') ? $admin_kelompok : '';

            // Cari user dengan role 'ketua pjp' sesuai tingkatannya
            if ($tingkat === 'desa') {
                $sql_user = "SELECT nama FROM users WHERE LOWER(role) = 'ketua pjp' AND LOWER(tingkat) = 'desa' LIMIT 1";
            } else {
                $kel_safe = $conn->real_escape_string($kelompok);
                $sql_user = "SELECT nama FROM users WHERE LOWER(role) = 'ketua pjp' AND LOWER(tingkat) = 'kelompok' AND kelompok = '$kel_safe' LIMIT 1";
            }

            $res = $conn->query($sql_user);
            if ($res && $res->num_rows > 0) {
                $nama_ketua = $res->fetch_assoc()['nama'];

                // Hapus data ketua yang lama di kepengurusan agar tidak duplikat
                if ($tingkat === 'desa') {
                    $conn->query("DELETE FROM kepengurusan WHERE tingkat = 'desa' AND jabatan = 'Ketua'");
                    $stmt = $conn->prepare("INSERT INTO kepengurusan (nama_pengurus, jabatan, tingkat, kelompok) VALUES (?, 'Ketua', 'desa', NULL)");
                    $stmt->bind_param("s", $nama_ketua);
                } else {
                    $kel_safe = $conn->real_escape_string($kelompok);
                    $conn->query("DELETE FROM kepengurusan WHERE tingkat = 'kelompok' AND kelompok = '$kel_safe' AND jabatan = 'Ketua'");
                    $stmt = $conn->prepare("INSERT INTO kepengurusan (nama_pengurus, jabatan, tingkat, kelompok) VALUES (?, 'Ketua', 'kelompok', ?)");
                    $stmt->bind_param("ss", $nama_ketua, $kel_safe);
                }
                
                $stmt->execute();
                $stmt->close();
                echo json_encode(['status' => 'success', 'message' => "Jabatan Ketua berhasil diperbarui: $nama_ketua"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Akun Ketua PJP tidak ditemukan di tabel Pengguna untuk tingkat ini. Pastikan akun sudah dibuat!']);
            }
            break;

        // --- 3. TAMBAH PENGURUS LAINNYA ---
        case 'add_pengurus':
            $jabatan = $_POST['jabatan'] ?? '';
            $nama = trim($_POST['nama_pengurus'] ?? '');

            if (empty($jabatan) || empty($nama)) {
                echo json_encode(['status' => 'error', 'message' => 'Jabatan dan Nama wajib diisi!']);
                exit;
            }

            if ($jabatan === 'Ketua') {
                echo json_encode(['status' => 'error', 'message' => 'Ketua tidak bisa ditambah manual. Silakan gunakan tombol Sinkronisasi.']);
                exit;
            }

            if ($admin_tingkat === 'desa') {
                $stmt = $conn->prepare("INSERT INTO kepengurusan (nama_pengurus, jabatan, tingkat, kelompok) VALUES (?, ?, 'desa', NULL)");
                $stmt->bind_param("ss", $nama, $jabatan);
            } else {
                $stmt = $conn->prepare("INSERT INTO kepengurusan (nama_pengurus, jabatan, tingkat, kelompok) VALUES (?, ?, 'kelompok', ?)");
                $stmt->bind_param("sss", $nama, $jabatan, $admin_kelompok);
            }

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => "Pengurus berhasil ditambahkan."]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data ke database.']);
            }
            $stmt->close();
            break;

        // --- 4. HAPUS PENGURUS ---
        case 'delete_pengurus':
            $id = intval($_POST['id'] ?? 0);
            
            // Keamanan lapis dua: Admin hanya bisa hapus di tingkatannya sendiri
            if ($admin_tingkat === 'desa') {
                $stmt = $conn->prepare("DELETE FROM kepengurusan WHERE id = ? AND tingkat = 'desa'");
                $stmt->bind_param("i", $id);
            } else {
                $kel_safe = $conn->real_escape_string($admin_kelompok);
                $stmt = $conn->prepare("DELETE FROM kepengurusan WHERE id = ? AND tingkat = 'kelompok' AND kelompok = ?");
                $stmt->bind_param("is", $id, $kel_safe);
            }

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Data pengurus berhasil dihapus.']);
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