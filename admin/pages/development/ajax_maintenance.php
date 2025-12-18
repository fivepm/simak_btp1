<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/config.php';

// --- 1. Validasi Akses ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin' || $_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// --- 2. Switch Case Logic ---
switch ($action) {

    // A. BUAT SESI RENCANA BARU
    case 'create_plan':
        $title = $input['title'];
        $desc  = $input['description'] ?? '';
        $adminId = $_SESSION['user_id'];
        $adminName = $_SESSION['user_nama'];

        // Cek apakah masih ada sesi planned/active yg belum selesai
        $check = mysqli_query($conn, "SELECT id FROM maintenance_sessions WHERE status IN ('planned', 'active') LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            echo json_encode(['success' => false, 'message' => 'Masih ada sesi maintenance yang aktif/direncanakan. Selesaikan dulu.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO maintenance_sessions (title, description, status, created_by, created_by_name) VALUES (?, ?, 'planned', ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssis", $title, $desc, $adminId, $adminName);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal membuat rencana.']);
        }
        break;

    // B. TAMBAH TUGAS KE SESI
    case 'add_task':
        $sessionId = $input['session_id'];
        $taskName = $input['task_name'];
        $pic = $input['pic'];

        $stmt = mysqli_prepare($conn, "INSERT INTO maintenance_tasks (session_id, task_name, pic) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $sessionId, $taskName, $pic);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'task_id' => mysqli_insert_id($conn)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambah tugas.']);
        }
        break;

    // C. TOGGLE STATUS TUGAS (Selesai/Belum)
    case 'toggle_task':
        $taskId = $input['task_id'];
        $status = $input['status']; // 1 or 0

        $stmt = mysqli_prepare($conn, "UPDATE maintenance_tasks SET is_completed = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $status, $taskId);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true]);
        break;

    // D. HAPUS TUGAS
    case 'delete_task':
        $taskId = $input['task_id'];
        mysqli_query($conn, "DELETE FROM maintenance_tasks WHERE id = $taskId");
        echo json_encode(['success' => true]);
        break;

    // E. MULAI MAINTENANCE (ON)
    case 'start_maintenance':
        $sessionId = $input['session_id'];
        $pin = $input['pin'];

        // 1. Update Session -> Active
        $now = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($conn, "UPDATE maintenance_sessions SET status = 'active', start_time = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $now, $sessionId);
        mysqli_stmt_execute($stmt);

        // 2. Update Settings -> Maintenance ON
        mysqli_query($conn, "UPDATE settings SET setting_value = 'true' WHERE setting_key = 'maintenance_mode'");

        // 3. Simpan PIN
        $stmtPin = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_pin', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        mysqli_stmt_bind_param($stmtPin, "ss", $pin, $pin);
        mysqli_stmt_execute($stmtPin);

        echo json_encode(['success' => true]);
        break;

    // F. SELESAI MAINTENANCE (OFF)
    case 'finish_maintenance':
        $sessionId = $input['session_id'];

        // 1. Update Session -> Completed
        $now = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($conn, "UPDATE maintenance_sessions SET status = 'completed', end_time = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $now, $sessionId);
        mysqli_stmt_execute($stmt);

        // 2. Update Settings -> Maintenance OFF
        mysqli_query($conn, "UPDATE settings SET setting_value = 'false' WHERE setting_key = 'maintenance_mode'");

        echo json_encode(['success' => true]);
        break;

    // G. AMBIL DETAIL SESI (Untuk Riwayat/History)
    case 'get_session_detail':
        $sessionId = $input['session_id'];

        // Ambil info sesi
        $qSesi = mysqli_query($conn, "SELECT * FROM maintenance_sessions WHERE id = $sessionId");
        $sesi = mysqli_fetch_assoc($qSesi);

        // Ambil tasks
        $tasks = [];
        $qTasks = mysqli_query($conn, "SELECT * FROM maintenance_tasks WHERE session_id = $sessionId");
        while ($row = mysqli_fetch_assoc($qTasks)) {
            $tasks[] = $row;
        }

        echo json_encode(['success' => true, 'session' => $sesi, 'tasks' => $tasks]);
        break;

    // --- FITUR BARU: BATALKAN RENCANA ---
    case 'cancel_plan':
        $sessionId = $input['session_id'];

        // Pastikan hanya status 'planned' yang bisa dibatalkan
        // Kita tidak menghapus datanya, tapi mengubah status jadi 'cancelled' agar tersimpan di history
        $stmt = mysqli_prepare($conn, "UPDATE maintenance_sessions SET status = 'cancelled' WHERE id = ? AND status = 'planned'");
        mysqli_stmt_bind_param($stmt, "i", $sessionId);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Rencana tidak ditemukan atau sudah aktif.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal membatalkan rencana.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenal.']);
        break;
}
exit;
