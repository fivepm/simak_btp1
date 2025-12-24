<?php
session_start();
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/helpers/log_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // --- LOG SCREENSHOT ---
    case 'log_screenshot':
        $trigger = $_POST['trigger'] ?? 'Unknown';
        $path = $_POST['path'] ?? 'Unknown Page';
        writeLog('EXPORT', "Terdeteksi upaya screenshot/cetak via tombol `$trigger` di halaman `$path`.");
        echo json_encode(['success' => true]);
        break;

    // --- GET STATS (GRAFIK DINAMIS + FILTER) ---
    case 'get_stats':
        if ($_SESSION['user_role'] !== 'superadmin') {
            echo json_encode(['success' => false]);
            exit;
        }

        $mode = $_POST['mode'] ?? 'daily';
        $filterValue = $_POST['filter_value'] ?? date('Y-m-d');

        // Filter Tambahan (Log)
        $filterType = $_POST['type'] ?? 'ALL';
        $search = $_POST['search'] ?? '';

        $extraSql = "";
        if ($filterType !== 'ALL') {
            $safeType = mysqli_real_escape_string($conn, $filterType);
            $extraSql .= " AND action_type = '$safeType'";
        }
        if (!empty($search)) {
            $safeSearch = mysqli_real_escape_string($conn, $search);
            $extraSql .= " AND (user_name LIKE '%$safeSearch%' OR description LIKE '%$safeSearch%')";
        }

        $labels = [];
        $dataPoints = [];

        // Variabel untuk range Query Maintenance
        $startDateQuery = '';
        $endDateQuery = '';

        // --- 1. SIAPKAN DATA GRAFIK ---

        // KASUS 1: HARIAN
        if ($mode == 'daily') {
            $startDateQuery = $filterValue . ' 00:00:00';
            $endDateQuery   = $filterValue . ' 23:59:59';

            for ($i = 0; $i < 24; $i++) {
                $h = str_pad($i, 2, '0', STR_PAD_LEFT);
                $labels[] = "$h:00";
                $dataPoints[$i] = 0;
            }
            $date = mysqli_real_escape_string($conn, $filterValue);
            $sql = "SELECT HOUR(created_at) as unit, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE DATE(created_at) = '$date' $extraSql
                    GROUP BY HOUR(created_at)";
            $res = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($res)) {
                $dataPoints[(int)$row['unit']] = (int)$row['total'];
            }
        }

        // KASUS 2: MINGGUAN
        elseif ($mode == 'weekly') {
            $dto = new DateTime();
            $dto->setISODate((int)substr($filterValue, 0, 4), (int)substr($filterValue, 6));
            $start = $dto->format('Y-m-d');
            $dto->modify('+6 days');
            $end = $dto->format('Y-m-d');

            $startDateQuery = $start . ' 00:00:00';
            $endDateQuery   = $end . ' 23:59:59';

            $daysIndo = ['Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu', 'Sun' => 'Minggu'];
            $tempData = [];
            $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));

            foreach ($period as $dt) {
                $tgl = $dt->format('Y-m-d');
                $labels[] = $daysIndo[$dt->format('D')] . " (" . $dt->format('d/m') . ")";
                // Key array pakai tanggal YYYY-MM-DD biar mudah dicocokkan
                $tempData[$tgl] = 0;
            }

            $sql = "SELECT DATE(created_at) as unit, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE DATE(created_at) BETWEEN '$start' AND '$end' $extraSql
                    GROUP BY DATE(created_at)";
            $res = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($res)) {
                if (isset($tempData[$row['unit']])) $tempData[$row['unit']] = (int)$row['total'];
            }
            $dataPoints = array_values($tempData);
        }

        // KASUS 3: BULANAN
        elseif ($mode == 'monthly') {
            $year = substr($filterValue, 0, 4);
            $month = substr($filterValue, 5, 2);
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $startDateQuery = "$year-$month-01 00:00:00";
            $endDateQuery   = "$year-$month-$daysInMonth 23:59:59";

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $labels[] = (string)$i;
                $dataPoints[$i] = 0;
            }

            $sql = "SELECT DAY(created_at) as unit, COUNT(*) as total 
                    FROM activity_logs 
                    WHERE YEAR(created_at) = '$year' AND MONTH(created_at) = '$month' $extraSql
                    GROUP BY DAY(created_at)";
            $res = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_assoc($res)) {
                $dataPoints[(int)$row['unit']] = (int)$row['total'];
            }
            $dataPoints = array_values($dataPoints);
        }

        // --- 2. AMBIL DATA MAINTENANCE ---
        // Cari maintenance yang overlap dengan range grafik
        $maintenance = [];
        $sqlMaint = "SELECT title, start_time, end_time FROM maintenance_sessions 
                     WHERE status IN ('completed', 'active') 
                     AND (start_time <= '$endDateQuery' AND (end_time >= '$startDateQuery' OR end_time IS NULL))";

        $resMaint = mysqli_query($conn, $sqlMaint);
        while ($row = mysqli_fetch_assoc($resMaint)) {
            $maintenance[] = [
                'title' => $row['title'],
                'start' => $row['start_time'],
                'end'   => $row['end_time'] ?? date('Y-m-d H:i:s') // Jika active, anggap sampai sekarang
            ];
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'data' => $dataPoints,
            'maintenance' => $maintenance // Kirim data maintenance ke frontend
        ]);
        break;

    // --- GET LOGS (PAGINATION FIX) ---
    case 'get_logs':
        if ($_SESSION['user_role'] !== 'superadmin') {
            echo json_encode(['success' => false]);
            exit;
        }

        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        if ($page < 1) $page = 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $filterType = $_POST['type'] ?? 'ALL';
        $search = $_POST['search'] ?? '';

        $whereClauses = ["1=1"];
        $types = "";
        $params = [];

        if ($filterType !== 'ALL') {
            $whereClauses[] = "action_type = ?";
            $types .= "s";
            $params[] = $filterType;
        }

        if (!empty($search)) {
            $whereClauses[] = "(user_name LIKE ? OR description LIKE ? OR created_at LIKE ?)";
            $types .= "sss";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereSQL = "WHERE " . implode(" AND ", $whereClauses);

        // Count Total
        $countSql = "SELECT COUNT(*) as total FROM activity_logs $whereSQL";
        $stmtCount = mysqli_prepare($conn, $countSql);
        if (!empty($types)) mysqli_stmt_bind_param($stmtCount, $types, ...$params);
        mysqli_stmt_execute($stmtCount);
        $resCount = mysqli_stmt_get_result($stmtCount);
        $totalRecords = mysqli_fetch_assoc($resCount)['total'];
        $totalPages = ceil($totalRecords / $limit);
        mysqli_stmt_close($stmtCount);

        // Get Data
        $dataSql = "SELECT * FROM activity_logs $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $finalTypes = $types . "ii";
        $finalParams = $params;
        $finalParams[] = $limit;
        $finalParams[] = $offset;

        $stmt = mysqli_prepare($conn, $dataSql);
        mysqli_stmt_bind_param($stmt, $finalTypes, ...$finalParams);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['time_ago'] = time_elapsed_string($row['created_at']);
            $row['date_fmt'] = date('d M Y H:i:s', strtotime($row['created_at']));
            $row['role'] = (isset($row['role']) && strtolower($row['role']) === 'superadmin') ? 'Developer' : ucwords($row['role'] ?? 'Guest');
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data, 'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_records' => $totalRecords]]);
        break;

    case 'clear_logs':
        if ($_SESSION['user_role'] !== 'superadmin') {
            echo json_encode(['success' => false]);
            exit;
        }
        mysqli_query($conn, "DELETE FROM activity_logs");
        echo json_encode(['success' => true]);
        break;
}

function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $w = floor($diff->d / 7);
    $d = $diff->d - ($w * 7);
    $string = array('y' => 'tahun', 'm' => 'bulan', 'w' => 'minggu', 'd' => 'hari', 'h' => 'jam', 'i' => 'menit', 's' => 'detik');
    $vals = ['y' => $diff->y, 'm' => $diff->m, 'w' => $w, 'd' => $d, 'h' => $diff->h, 'i' => $diff->i, 's' => $diff->s];
    foreach ($string as $k => &$v) {
        if ($vals[$k]) $v = $vals[$k] . ' ' . $v;
        else unset($string[$k]);
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
