<?php
// === FILE BACKEND: ajax_kelola_pengguna.php ===
require_once '../../../config/config.php';
require_once '../../../helpers/log_helper.php';
require_once '../../helpers/wa_gateway.php';
require_once '../../helpers/template_helper.php'; // Dibutuhkan untuk getFormattedMessage()

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$current_user_role = $_SESSION['user_role'] ?? '';

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
function validasiFormatDanFonnte($nomor_hp)
{
    $fonnte_token = $_ENV['FONNTE_TOKEN'];

    $nomor_bersih = preg_replace('/[^0-9]/', '', $nomor_hp);
    if (empty($nomor_bersih)) return ['valid' => false, 'nomor' => '', 'pesan' => 'Nomor WA tidak boleh kosong.'];

    if (strpos($nomor_bersih, '0') === 0) {
        $nomor_bersih = '62' . substr($nomor_bersih, 1);
    } elseif (strpos($nomor_bersih, '62') !== 0) {
        if (strpos($nomor_bersih, '8') === 0) {
            $nomor_bersih = '62' . $nomor_bersih;
        } else {
            return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Nomor WA harus diawali dengan 08 atau 628.'];
        }
    }

    if (strlen($nomor_bersih) < 10 || strlen($nomor_bersih) > 15) {
        return ['valid' => false, 'nomor' => $nomor_bersih, 'pesan' => 'Panjang nomor WA tidak valid.'];
    }

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

// ==========================================================
// 1. GET DATA
// ==========================================================
if ($action === 'get_data') {
    if ($current_user_role === 'superadmin') {
        $sql = "SELECT id, nama, nama_panggilan, username, kelompok, tingkat, role, barcode, foto_profil, nomor_wa, last_login 
                FROM users 
                WHERE role IN ('admin', 'superadmin') AND deleted_at IS NULL 
                ORDER BY role DESC, nama ASC";
    } else {
        $sql = "SELECT id, nama, nama_panggilan, username, kelompok, tingkat, role, barcode, foto_profil, nomor_wa, last_login 
                FROM users 
                WHERE role = 'admin' AND deleted_at IS NULL 
                ORDER BY nama ASC";
    }

    $result = $conn->query($sql);

    $data = [];
    $waktu_sekarang = time();

    while ($row = $result->fetch_assoc()) {
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

        // Masking Nomor WA untuk keamanan
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

    // --- TAMBAH ADMIN ---
    if ($action === 'tambah_admin') {
        $nama = $_POST['nama'] ?? '';
        $nama_panggilan = $_POST['nama_panggilan'] ?? '';
        $nomor_wa_input = $_POST['nomor_wa'] ?? '';
        $kelompok = $_POST['kelompok'] ?? '';
        $tingkat = $_POST['tingkat'] ?? '';
        $role = $_POST['role'] ?? 'admin';

        if (empty($nama) || empty($nama_panggilan) || empty($kelompok) || empty($tingkat) || empty($role)) {
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

        $conn->begin_transaction();
        try {
            $is_exist = true;
            $username = '';
            while ($is_exist) {
                if ($role == 'admin') {
                    $username = 'adm' . rand(1000, 9999) . date('s');
                } else {
                    $username = 'sa' . rand(1000, 9999) . date('s');
                }
                $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? UNION SELECT id FROM guru WHERE username = ?");
                $stmt_check->bind_param("ss", $username, $username);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows == 0) $is_exist = false;
                $stmt_check->close();
            }

            $plain_password = generateRandomPassword(8);
            $password_hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $barcode = ($role == 'admin') ? 'ADM-' . uniqid() : 'SA-' . uniqid();

            $sql = "INSERT INTO users (nama, nama_panggilan, kelompok, role, tingkat, barcode, username, password, nomor_wa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $nama, $nama_panggilan, $kelompok, $role, $tingkat, $barcode, $username, $password_hashed, $nomor_wa_final);
            $stmt->execute();

            $conn->commit();

            // === LOG & NOTIFIKASI ===
            if ($role == 'superadmin') {
                writeLog('INSERT', "Menambahkan *Developer* Baru : *" . ucwords($nama) . "*.");
            } else {
                $desc_log = ($tingkat == 'desa') ? "Menambahkan *Admin Desa* Baru : *" . ucwords($nama) . "*." : "Menambahkan *Admin Kelompok* Baru : *" . ucwords($nama) . "* (Kelompok " . ucwords($kelompok) . ").";
                writeLog('INSERT', $desc_log);
            }

            // Notif WA ke Administrasi
            $id_administrasi_kbm = "120363194369588883@g.us";
            $data_untuk_pesan = [
                '[tingkat]' => $tingkat,
                '[kelompok]' => $kelompok,
                '[nama]' => $nama,
                '[username]' => $username
            ];
            $template_nama = ($role == 'admin') ? 'tambah_admin' : 'tambah_super_admin';
            $pesan_final = getFormattedMessage($conn, $template_nama, 'default', NULL, $data_untuk_pesan);

            if (function_exists('kirimWhatsApp')) {
                kirimWhatsApp($id_administrasi_kbm, $pesan_final);
            }

            echo json_encode(['status' => 'success', 'message' => 'Data Admin berhasil ditambahkan.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- EDIT ADMIN ---
    if ($action === 'edit_admin') {
        $id = $_POST['edit_id'] ?? 0;
        $nama = $_POST['edit_nama'] ?? '';
        $nomor_wa_input = $_POST['edit_nomor_wa'] ?? '';
        $kelompok = $_POST['edit_kelompok'] ?? '';
        $tingkat = $_POST['edit_tingkat'] ?? '';
        $role = $_POST['edit_role'] ?? 'admin';

        if (empty($nama) || empty($kelompok) || empty($tingkat) || empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap untuk proses edit.']);
            exit;
        }

        $stmt_old = $conn->prepare("SELECT nomor_wa, role FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

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

        $sql = "UPDATE users SET nama=?, kelompok=?, tingkat=?, role=?, nomor_wa=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $nama, $kelompok, $tingkat, $role, $nomor_wa_final, $id);
        if ($stmt->execute()) {
            if ($role == 'superadmin') {
                writeLog('UPDATE', "Memperbarui data *Developer* : *" . ucwords($nama) . "*.");
            } else {
                $desc_log = ($tingkat == 'desa') ? "Memperbarui data *Admin Desa* : *" . ucwords($nama) . "*." : "Memperbarui data *Admin Kelompok* : *" . ucwords($nama) . "* (Kelompok " . ucwords($kelompok) . ").";
                writeLog('UPDATE', $desc_log);
            }
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil diperbarui.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui data.']);
        }
        exit;
    }

    // --- HAPUS ADMIN (SOFT DELETE) ---
    if ($action === 'hapus_admin') {
        $id = $_POST['hapus_id'] ?? 0;

        // Cek dulu datanya untuk keperluan LOG
        $admin = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();

        $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($admin['role'] == 'superadmin') {
                writeLog('DELETE', "Menghapus data *Developer* : *" . ucwords($admin['nama']) . "*.");
            } else {
                $desc_log = ($admin['tingkat'] == 'desa') ? "Menghapus data *Admin Desa* : *" . ucwords($admin['nama']) . "*." : "Menghapus data *Admin Kelompok* : *" . ucwords($admin['nama']) . "* (Kelompok " . ucwords($admin['kelompok']) . ").";
                writeLog('DELETE', $desc_log);
            }
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']);
        }
        exit;
    }
}
