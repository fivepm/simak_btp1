<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/config.php';

// Keamanan
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // --- FITUR BARU: AMBIL DATA STATISTIK UNTUK GRAFIK ---
    case 'get_stats':
        $range = $_POST['range'] ?? 'daily'; // daily, weekly, monthly
        $data = [];
        $labels = [];
        $counts = [];

        // PERBAIKAN: Menggunakan MIN(created_at) agar kompatibel dengan only_full_group_by
        if ($range == 'daily') {
            // 7 Hari Terakhir
            $sql = "SELECT DATE_FORMAT(MIN(created_at), '%d %b') as label, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at) 
                    ORDER BY MIN(created_at) ASC";
        } elseif ($range == 'weekly') {
            // 8 Minggu Terakhir
            $sql = "SELECT CONCAT('Minggu ke-', WEEK(MIN(created_at))) as label, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                    GROUP BY YEARWEEK(created_at) 
                    ORDER BY MIN(created_at) ASC";
        } elseif ($range == 'monthly') {
            // 6 Bulan Terakhir
            $sql = "SELECT DATE_FORMAT(MIN(created_at), '%M %Y') as label, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY YEAR(created_at), MONTH(created_at) 
                    ORDER BY MIN(created_at) ASC";
        }

        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['label'];
            $counts[] = $row['total'];
        }

        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $counts]);
        break;

    // --- (KODE LAMA TETAP SAMA) ---
    case 'get_logs':
        // Filter
        $filterType = $_POST['type'] ?? 'ALL';
        $search = $_POST['search'] ?? '';
        $limit = 20; // Item per halaman

        // Bangun Query
        $sql = "SELECT * FROM activity_logs WHERE 1=1";
        $types = "";
        $params = [];

        if ($filterType !== 'ALL') {
            $sql .= " AND action_type = ?";
            $types .= "s";
            $params[] = $filterType;
        }

        if (!empty($search)) {
            $sql .= " AND (user_name LIKE ? OR description LIKE ?)";
            $types .= "ss";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $types .= "i";
        $params[] = $limit;

        // Eksekusi
        $stmt = mysqli_prepare($conn, $sql);
        if ($types) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($row['role']) && strtolower($row['role']) === 'superadmin') {
                $row['role'] = 'Developer';
            }
            // Format waktu agar cantik (Contoh: "2 jam yang lalu" atau Tanggal)
            $row['time_ago'] = time_elapsed_string($row['created_at']);
            $row['date_fmt'] = date('d M Y H:i:s', strtotime($row['created_at']));
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'clear_logs':
        // Fitur hapus log lama (misal > 30 hari)
        mysqli_query($conn, "DELETE FROM activity_logs");
        echo json_encode(['success' => true]);
        break;
}

// Helper Time Ago
// PERBAIKAN: Menghindari dynamic property deprecation pada PHP 8.2+ ($diff->w)
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Hitung minggu manual ke variabel lokal
    $w = floor($diff->d / 7);
    $d = $diff->d - ($w * 7);

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );

    // Mapping nilai
    $vals = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $w,
        'd' => $d,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s
    ];

    foreach ($string as $k => &$v) {
        if ($vals[$k]) {
            $v = $vals[$k] . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
