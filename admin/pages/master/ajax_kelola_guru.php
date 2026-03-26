<?php
// === FILE BACKEND: ajax_kelola_guru.php ===
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$admin_tingkat = $_SESSION['user_tingkat'] ?? 'desa';
$admin_kelompok = $_SESSION['user_kelompok'] ?? '';

// --- FUNGSI HELPER ---
function generateRandomPassword($length = 6)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $res = '';
    for ($i = 0; $i < $length; $i++) {
        $res .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $res;
}

// Fungsi Format & Validasi WA (STRICT / TANPA BYPASS)
// Mengembalikan Array: ['valid' => true/false, 'nomor' => '628...', 'pesan' => '...']
function validasiFormatDanFonnte($nomor_hp)
{
    $fonnte_token = $_ENV['FONNTE_TOKEN'];

    // 1. Bersihkan semua karakter selain angka
    $nomor_bersih = preg_replace('/[^0-9]/', '', $nomor_hp);

    if (empty($nomor_bersih)) {
        return ['valid' => false, 'nomor' => '', 'pesan' => 'Nomor WA tidak boleh kosong.'];
    }

    // 2. Normalisasi Awalan menjadi 62
    if (strpos($nomor_bersih, '0') === 0) {
        $nomor_bersih = '62' . substr($nomor_bersih, 1);
    } elseif (strpos($nomor_bersih, '62') !== 0) {
        if (strpos($nomor_bersih, '8') === 0) {
            $nomor_bersih = '62' . $nomor_bersih;
        } else {
            return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Nomor WA harus diawali dengan 08 atau 628.'];
        }
    }

    // Cek panjang nomor (minimal 10, maksimal 15 digit)
    if (strlen($nomor_bersih) < 10 || strlen($nomor_bersih) > 15) {
        return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Panjang nomor WA tidak valid.'];
    }

    // 3. Cek API Fonnte Langsung secara Ketat
    if (!empty($fonnte_token)) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/validate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('target' => $nomor_bersih, 'countryCode' => '62'),
            CURLOPT_HTTPHEADER => array('Authorization: ' . $fonnte_token),
        ));
        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_status == 200) {
            $data = json_decode($response, true);
            if (isset($data['status']) && $data['status'] == true) {
                if (isset($data['registered']) && in_array($nomor_bersih, $data['registered'])) {
                    return ['valid' => true, 'nomor' => $nomor_bersih, 'pesan' => 'Nomor valid.'];
                } else {
                    return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Nomor WhatsApp tidak terdaftar di sistem Meta.'];
                }
            }
            return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Gagal validasi ke sistem Fonnte.'];
        }
        return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'API Fonnte Error. HTTP Status: ' . $http_status];
    }

    // Jika tidak ada token Fonnte, kita TOLAK (tidak ada lagi bypass)
    return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Token API tidak tersedia. Tidak dapat memvalidasi nomor.'];
}

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

// ==========================================================
// 2. PROSES POST (CRUD)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- TAMBAH GURU ---
    if ($action === 'tambah_guru') {
        $nama = $_POST['nama'] ?? '';
        $nama_panggilan = $_POST['nama_panggilan'] ?? '';
        $nomor_wa_input = $_POST['nomor_wa'] ?? '';
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['kelompok'] ?? '');
        $kelas_array = isset($_POST['kelas']) ? explode(',', $_POST['kelas']) : [];
        $tingkat = 'kelompok';

        if (empty($nama) || empty($nama_panggilan) || empty($kelompok) || empty($kelas_array)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama, Kelompok, dan minimal 1 Kelas wajib diisi.']);
            exit;
        }

        // Validasi Ketat Backend
        $hasil_validasi = validasiFormatDanFonnte($nomor_wa_input);
        if (!$hasil_validasi['valid']) {
            echo json_encode(['status' => 'error', 'message' => $hasil_validasi['pesan']]);
            exit;
        }
        $nomor_wa_final = $hasil_validasi['nomor'];

        $conn->begin_transaction();
        try {
            $is_exist = true;
            $username = '';
            while ($is_exist) {
                $username = 'guru' . rand(1000, 9999) . date('s');
                $stmt_check = $conn->prepare("SELECT id FROM guru WHERE username = ? UNION SELECT id FROM users WHERE username = ?");
                $stmt_check->bind_param("ss", $username, $username);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows == 0) $is_exist = false;
                $stmt_check->close();
            }

            $plain_password = generateRandomPassword(8);
            $password_hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $barcode = 'GRU-' . uniqid();

            $sql = "INSERT INTO guru (nama, nama_panggilan, kelompok, kelas, tingkat, barcode, username, password, nomor_wa) VALUES (?, ?, ?, '', ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $nama, $nama_panggilan, $kelompok, $tingkat, $barcode, $username, $password_hashed, $nomor_wa_final);
            $stmt->execute();
            $id_guru_baru = $conn->insert_id;

            $sql_pengampu = "INSERT INTO pengampu (id_guru, nama_kelas) VALUES (?, ?)";
            $stmt_p = $conn->prepare($sql_pengampu);
            foreach ($kelas_array as $k) {
                $k_clean = trim($k);
                if (!empty($k_clean)) {
                    $stmt_p->bind_param("is", $id_guru_baru, $k_clean);
                    $stmt_p->execute();
                }
            }

            $conn->commit();
            writeLog('INSERT', "Menambah Guru ($kelompok): $nama.");
            echo json_encode(['status' => 'success', 'message' => 'Data Guru berhasil ditambahkan.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- EDIT GURU ---
    if ($action === 'edit_guru') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        $nomor_wa_input = $_POST['edit_nomor_wa'] ?? '';
        $kelompok = ($admin_tingkat === 'kelompok') ? $admin_kelompok : ($_POST['edit_kelompok'] ?? '');
        $kelas_array = isset($_POST['edit_kelas']) ? explode(',', $_POST['edit_kelas']) : [];

        if (empty($nama) || empty($kelompok) || empty($id) || empty($kelas_array)) {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Minimal pilih 1 kelas.']);
            exit;
        }

        $stmt_old = $conn->prepare("SELECT nomor_wa FROM guru WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

        $nomor_wa_final = $nomor_wa_input;

        if (strpos($nomor_wa_input, '*') !== false) {
            // Nomor tidak diubah, pakai yang lama dari DB (tidak perlu cek Fonnte ulang)
            $nomor_wa_final = $old_data['nomor_wa'];
        } else {
            // Nomor diubah, lakukan validasi ketat
            $hasil_validasi = validasiFormatDanFonnte($nomor_wa_input);
            if (!$hasil_validasi['valid']) {
                echo json_encode(['status' => 'error', 'message' => $hasil_validasi['pesan']]);
                exit;
            }
            $nomor_wa_final = $hasil_validasi['nomor'];
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE guru SET nama=?, kelompok=?, nomor_wa=? WHERE id=?");
            $stmt->bind_param("sssi", $nama, $kelompok, $nomor_wa_final, $id);
            $stmt->execute();

            $conn->query("DELETE FROM pengampu WHERE id_guru = $id");
            $stmt_ins = $conn->prepare("INSERT INTO pengampu (id_guru, nama_kelas) VALUES (?, ?)");
            foreach ($kelas_array as $k) {
                $k_clean = trim($k);
                if (!empty($k_clean)) {
                    $stmt_ins->bind_param("is", $id, $k_clean);
                    $stmt_ins->execute();
                }
            }

            $conn->commit();
            writeLog('UPDATE', "Update Guru ID $id ($nama).");
            echo json_encode(['status' => 'success', 'message' => 'Data Guru berhasil diperbarui.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- HAPUS GURU ---
    if ($action === 'hapus_guru') {
        $id = $_POST['hapus_id'] ?? 0;
        $stmt = $conn->prepare("UPDATE guru SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            writeLog('DELETE', "Menghapus (Soft) Guru ID: $id");
            echo json_encode(['status' => 'success', 'message' => 'Data Guru berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus guru.']);
        }
        exit;
    }
}
