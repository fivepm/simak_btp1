<?php
// === FILE BACKEND: ajax_kelola_ketua_pjp.php ===
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';
// require_once '../../../helpers/fonnte_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

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

    // Cek panjang nomor
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

    return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Token API tidak tersedia. Tidak dapat memvalidasi nomor.'];
}

// Fungsi Helper Upload TTD
function uploadTTD($fileInputName)
{
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => true, 'filename' => null]; // Tidak ada file yg diupload (bisa diizinkan karena opsional)
    }

    $targetDir = '../../../uploads/ttd/';
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = $_FILES[$fileInputName]['name'];
    $fileTmpName = $_FILES[$fileInputName]['tmp_name'];
    $fileSize = $_FILES[$fileInputName]['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'png') {
        return ['status' => false, 'message' => 'Format file Tanda Tangan harus PNG.'];
    }
    if ($fileSize > 2097152) { // Max 2MB
        return ['status' => false, 'message' => 'Ukuran file Tanda Tangan maksimal 2MB.'];
    }

    $newFileName = 'ttd_pjp_' . uniqid() . '.png';
    $targetFilePath = $targetDir . $newFileName;

    if (move_uploaded_file($fileTmpName, $targetFilePath)) {
        return ['status' => true, 'filename' => $newFileName];
    } else {
        return ['status' => false, 'message' => 'Gagal mengunggah file Tanda Tangan.'];
    }
}

// ==========================================================
// 1. GET DATA
// ==========================================================
if ($action === 'get_data') {
    $sql = "SELECT id, nama, nama_panggilan, username, kelompok, tingkat, role, barcode, nomor_wa, last_login, ttd 
            FROM users 
            WHERE role = 'ketua pjp' AND deleted_at IS NULL 
            ORDER BY nama ASC";
    $result = $conn->query($sql);

    $data = [];
    $waktu_sekarang = time();

    while ($row = $result->fetch_assoc()) {
        $status_online = 'offline';
        $waktu_terakhir_aktif = '-';

        if (!empty($row['last_login'])) {
            $waktu_login = strtotime($row['last_login']);
            if (($waktu_sekarang - $waktu_login) < 900) { // 15 Menit aktif
                $status_online = 'online';
            } else {
                $waktu_terakhir_aktif = date('d/m/Y H:i', $waktu_login);
            }
        }

        // Masking Nomor WA untuk keamanan data sensitif
        if (!empty($row['nomor_wa'])) {
            $row['nomor_wa'] = substr($row['nomor_wa'], 0, 4) . ' **** ****';
        }

        $row['status_login'] = $status_online;
        $row['terakhir_login'] = $waktu_terakhir_aktif;

        $data[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// ==========================================================
// 2. PROSES POST (CRUD)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- TAMBAH KETUA PJP ---
    if ($action === 'tambah_pjp') {
        $nama = $_POST['nama'] ?? '';
        $nama_panggilan = $_POST['nama_panggilan'] ?? '';
        $nomor_wa_input = $_POST['nomor_wa'] ?? '';
        $kelompok = $_POST['kelompok'] ?? '';
        $tingkat = $_POST['tingkat'] ?? '';

        if (empty($nama) || empty($nama_panggilan) || empty($kelompok) || empty($tingkat)) {
            echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi.']);
            exit;
        }

        // Validasi Ketat WA
        $hasil_validasi = validasiFormatDanFonnte($nomor_wa_input);
        if (!$hasil_validasi['valid']) {
            echo json_encode(['status' => 'error', 'message' => $hasil_validasi['pesan']]);
            exit;
        }
        $nomor_wa_final = $hasil_validasi['nomor'];

        // Proses Upload TTD
        $uploadRes = uploadTTD('ttd');
        if (!$uploadRes['status']) {
            echo json_encode(['status' => 'error', 'message' => $uploadRes['message']]);
            exit;
        }
        $ttd_filename = $uploadRes['filename'];

        $conn->begin_transaction();
        try {
            $is_exist = true;
            $username = '';
            while ($is_exist) {
                $username = 'pjp' . rand(1000, 9999) . date('s');
                $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? UNION SELECT id FROM guru WHERE username = ?");
                $stmt_check->bind_param("ss", $username, $username);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows == 0) $is_exist = false;
                $stmt_check->close();
            }

            $plain_password = generateRandomPassword(8);
            $password_hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $role = 'ketua pjp';
            $barcode = 'PJP-' . uniqid();

            $sql = "INSERT INTO users (nama, nama_panggilan, kelompok, role, tingkat, barcode, username, password, nomor_wa, ttd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssss", $nama, $nama_panggilan, $kelompok, $role, $tingkat, $barcode, $username, $password_hashed, $nomor_wa_final, $ttd_filename);
            $stmt->execute();

            $conn->commit();
            writeLog('INSERT', "Menambah Ketua PJP Baru: $nama ($tingkat $kelompok).");
            echo json_encode(['status' => 'success', 'message' => 'Data Ketua PJP berhasil ditambahkan.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem.']);
        }
        exit;
    }

    // --- EDIT KETUA PJP ---
    if ($action === 'edit_pjp') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        $nomor_wa_input = $_POST['edit_nomor_wa'] ?? '';
        $kelompok = $_POST['edit_kelompok'] ?? '';
        $tingkat = $_POST['edit_tingkat'] ?? '';

        if (empty($nama) || empty($kelompok) || empty($tingkat) || empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap untuk proses edit.']);
            exit;
        }

        $stmt_old = $conn->prepare("SELECT nomor_wa, ttd FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

        // Cek Nomor WA
        $nomor_wa_final = $nomor_wa_input;
        if (strpos($nomor_wa_input, '*') !== false) {
            $nomor_wa_final = $old_data['nomor_wa'];
        } else {
            $hasil_validasi = validasiFormatDanFonnte($nomor_wa_input);
            if (!$hasil_validasi['valid']) {
                echo json_encode(['status' => 'error', 'message' => $hasil_validasi['pesan']]);
                exit;
            }
            $nomor_wa_final = $hasil_validasi['nomor'];
        }

        // Proses Upload TTD Baru
        $uploadRes = uploadTTD('edit_ttd');
        if (!$uploadRes['status']) {
            echo json_encode(['status' => 'error', 'message' => $uploadRes['message']]);
            exit;
        }

        $new_ttd_filename = $uploadRes['filename'];

        // Hapus TTD Lama jika Upload TTD Baru berhasil
        if ($new_ttd_filename && !empty($old_data['ttd'])) {
            $old_ttd_path = '../../../uploads/ttd/' . $old_data['ttd'];
            if (file_exists($old_ttd_path)) {
                unlink($old_ttd_path);
            }
        }

        if ($new_ttd_filename) {
            $sql = "UPDATE users SET nama=?, kelompok=?, tingkat=?, nomor_wa=?, ttd=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $nama, $kelompok, $tingkat, $nomor_wa_final, $new_ttd_filename, $id);
        } else {
            $sql = "UPDATE users SET nama=?, kelompok=?, tingkat=?, nomor_wa=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $nama, $kelompok, $tingkat, $nomor_wa_final, $id);
        }

        if ($stmt->execute()) {
            writeLog('UPDATE', "Update Ketua PJP ID $id ($nama).");
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil diperbarui.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui data.']);
        }
        exit;
    }

    // --- HAPUS KETUA PJP (SOFT DELETE) ---
    if ($action === 'hapus_pjp') {
        $id = $_POST['hapus_id'] ?? 0;
        $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            writeLog('DELETE', "Menghapus (Soft) Ketua PJP ID: $id");
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']);
        }
        exit;
    }
}
