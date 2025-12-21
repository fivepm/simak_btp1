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
        $mode = $_POST['mode'] ?? 'daily'; // daily, weekly, monthly
        $filterValue = $_POST['filter_value'] ?? date('Y-m-d');

        $labels = [];
        $dataPoints = [];

        // ---------------------------------------------------------
        // KASUS 1: HARIAN (Per Jam 00:00 - 23:00)
        // ---------------------------------------------------------
        if ($mode == 'daily') {
            // 1. Siapkan kerangka 24 Jam (00 - 23)
            for ($i = 0; $i < 24; $i++) {
                $h = str_pad($i, 2, '0', STR_PAD_LEFT);
                $labels[] = "$h:00";
                $dataPoints[$i] = 0; // Default 0
            }

            // 2. Query
            $date = mysqli_real_escape_string($conn, $filterValue); // Format YYYY-MM-DD
            $sql = "SELECT HOUR(created_at) as jam, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE DATE(created_at) = '$date' 
                    GROUP BY HOUR(created_at)";

            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $jam = (int)$row['jam'];
                $dataPoints[$jam] = (int)$row['total'];
            }
        }

        // ---------------------------------------------------------
        // KASUS 2: MINGGUAN (Senin - Minggu)
        // ---------------------------------------------------------
        elseif ($mode == 'weekly') {
            // Input dari browser: "2023-W45" (Tahun-W[MingguKe])
            // Kita butuh konversi ke Range Tanggal
            $dto = new DateTime();
            // Trik parsing format ISO Week
            $dto->setISODate((int)substr($filterValue, 0, 4), (int)substr($filterValue, 6));

            $startOfWeek = $dto->format('Y-m-d'); // Senin
            $dto->modify('+6 days');
            $endOfWeek = $dto->format('Y-m-d');   // Minggu

            // 1. Siapkan kerangka Hari (Senin - Minggu)
            $daysIndo = ['Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu', 'Sun' => 'Minggu'];
            $tempData = [];

            // Generate label tanggal untuk 7 hari
            $period = new DatePeriod(
                new DateTime($startOfWeek),
                new DateInterval('P1D'),
                (new DateTime($endOfWeek))->modify('+1 day')
            );

            foreach ($period as $dt) {
                $tgl = $dt->format('Y-m-d');
                $dayName = $dt->format('D'); // Mon, Tue...
                $labels[] = $daysIndo[$dayName] . " (" . $dt->format('d/m') . ")";
                $tempData[$tgl] = 0; // Key based on date
            }

            // 2. Query
            $sql = "SELECT DATE(created_at) as tgl, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE DATE(created_at) BETWEEN '$startOfWeek' AND '$endOfWeek'
                    GROUP BY DATE(created_at)";

            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $tempData[$row['tgl']] = (int)$row['total'];
            }

            // Ratakan array ke index numerik
            $dataPoints = array_values($tempData);
        }

        // ---------------------------------------------------------
        // KASUS 3: BULANAN (Tanggal 1 - Akhir Bulan)
        // ---------------------------------------------------------
        elseif ($mode == 'monthly') {
            // Input: "2023-10" (YYYY-MM)
            $year = substr($filterValue, 0, 4);
            $month = substr($filterValue, 5, 2);
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            // 1. Siapkan kerangka tanggal (1 - 30/31)
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $labels[] = (string)$i; // Label tanggal
                $dataPoints[$i] = 0;    // Default 0, Key = tanggal
            }

            // 2. Query
            $sql = "SELECT DAY(created_at) as tgl, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE YEAR(created_at) = '$year' AND MONTH(created_at) = '$month'
                    GROUP BY DAY(created_at)";

            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $tgl = (int)$row['tgl'];
                $dataPoints[$tgl] = (int)$row['total'];
            }

            // Re-index array (karena chart.js butuh array 0-indexed)
            $dataPoints = array_values($dataPoints);
        }

        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $dataPoints]);
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
            $sql .= " AND (user_name LIKE ? OR description LIKE ? OR created_at LIKE ?)";
            $types .= "sss";
            $params[] = "%$search%";
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
