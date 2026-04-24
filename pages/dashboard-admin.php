<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../config/db.php';



$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));
// Handle AJAX requests for revenue data
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (isset($_POST['action']) && $_POST['action'] === 'get_revenue_data') {
        header('Content-Type: application/json');
        
        $view = $_POST['view'] ?? 'daily';
        $response = ['labels' => [], 'data' => [], 'total' => 0, 'metadata' => []];
        
        if ($view === 'daily') {
            // Weekly daily data
            $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $response['labels'] = $weekdays;
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime('monday this week +' . $i . ' days'));
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?), 0) +
                        COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?), 0)
                        as total,
                        (SELECT COUNT(*) FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?) +
                        (SELECT COUNT(*) FROM camping_bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?) as count
                ");
                $stmt->execute([$date, $date, $date, $date]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $response['data'][] = $row['total'];
                $response['total'] += $row['total'];
                $response['metadata'][] = ['revenue' => $row['total'], 'booking_count' => $row['count']];
            }
        } elseif ($view === 'monthly') {
            $year = $_POST['year'] ?? date('Y');
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $response['labels'] = $months;
            for ($month = 1; $month <= 12; $month++) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ? AND MONTH(created_at) = ?), 0) +
                        COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ? AND MONTH(created_at) = ?), 0)
                        as total
                ");
                $stmt->execute([$year, $month, $year, $month]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $response['data'][] = $row['total'];
                $response['total'] += $row['total'];
                $response['metadata'][] = ['revenue' => $row['total']];
            }
        } elseif ($view === 'yearly') {
            $year = $_POST['year'] ?? date('Y');
            $response['labels'] = [];
            $response['data'] = [];
            for ($i = 4; $i >= 0; $i--) {
                $y = $year - $i;
                $response['labels'][] = $y;
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ?), 0) +
                        COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ?), 0)
                        as total
                ");
                $stmt->execute([$y, $y]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $response['data'][] = $row['total'];
                $response['total'] += $row['total'];
                $response['metadata'][] = ['revenue' => $row['total']];
            }
        } elseif ($view === 'hourly') {
            $hours = [];
            for ($i = 0; $i < 24; $i++) {
                $hours[] = $i . ':00';
            }
            $response['labels'] = $hours;
            for ($i = 0; $i < 24; $i++) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as booking_count,
                        COALESCE(SUM(total_amount), 0) as total
                    FROM (
                        SELECT total_amount, created_at FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE() AND HOUR(created_at) = ?
                        UNION ALL
                        SELECT total_amount, created_at FROM camping_bookings WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE() AND HOUR(created_at) = ?
                    ) as hourly
                ");
                $stmt->execute([$i, $i]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $response['data'][] = $row['total'];
                $response['total'] += $row['total'];
                $response['metadata'][] = ['revenue' => $row['total'], 'booking_count' => $row['booking_count']];
            }
        }
        
        echo json_encode($response);
        exit;
    }
}
// ========== STAT CARDS DATA ==========

// Daily Bookings (today)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM (
        SELECT id FROM bookings WHERE DATE(created_at) = CURDATE()
        UNION ALL
        SELECT id FROM camping_bookings WHERE DATE(created_at) = CURDATE()
    ) as today_bookings
");
$stmt->execute();
$dailyBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Yesterday's bookings for comparison
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM (
        SELECT id FROM bookings WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        UNION ALL
        SELECT id FROM camping_bookings WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ) as yesterday_bookings
");
$stmt->execute();
$yesterdayBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 1;

$bookingTrend = $yesterdayBookings > 0 ? round((($dailyBookings - $yesterdayBookings) / $yesterdayBookings) * 100) : 0;
$bookingIcon = $bookingTrend >= 0 ? '↑' : '↓';
$bookingClass = $bookingTrend >= 0 ? 'stat-trend' : 'stat-trend warn';

// Active Hikers (hikers with bookings in last 7 days or currently active)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id) as count
    FROM (
        SELECT user_id FROM bookings WHERE status = 'active' OR created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION
        SELECT user_id FROM camping_bookings WHERE status = 'active' OR created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ) as active
");
$stmt->execute();
$activeHikers = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// MTD Revenue (current month to date)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())), 0) +
        COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())), 0)
        as total
");
$stmt->execute();
$mtdRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Previous month revenue for projection
$stmt = $pdo->prepare("
    SELECT 
        COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)), 0) +
        COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)), 0)
        as total
");
$stmt->execute();
$prevMonthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;

$revenueTrend = $prevMonthRevenue > 0 ? round((($mtdRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100) : 0;
$revenueIcon = $revenueTrend >= 0 ? '↑' : '↓';

// Open Alerts
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, 
           SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as critical_count
    FROM alerts 
    WHERE status IN ('active', 'pending')
");
$stmt->execute();
$alertsData = $stmt->fetch(PDO::FETCH_ASSOC);
$openAlerts = $alertsData['count'] ?? 0;
$criticalAlerts = $alertsData['critical_count'] ?? 0;

// ========== CHART DATA ==========

// Revenue by day this week
$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dailyRevenue = [];
$totalWeekRevenue = 0;

for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime('monday this week +' . $i . ' days'));
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?), 0) +
            COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?), 0)
            as total
    ");
    $stmt->execute([$date, $date]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $dailyRevenue[] = $revenue;
    $totalWeekRevenue += $revenue;
}
// ========== ADVANCED REVENUE DATA FUNCTIONS ==========

// Function to get hourly revenue for a specific date
function getHourlyRevenue($pdo, $date = null) {
    if (!$date) $date = date('Y-m-d');
    
    $hourlyData = array_fill(0, 24, 0);
    
    // Get day hike bookings by hour
    $stmt = $pdo->prepare("
        SELECT HOUR(created_at) as hour, SUM(total_amount) as total
        FROM bookings 
        WHERE payment_status = 'paid' AND DATE(created_at) = ?
        GROUP BY HOUR(created_at)
    ");
    $stmt->execute([$date]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $hourlyData[$row['hour']] += $row['total'];
    }
    
    // Get camping bookings by hour
    $stmt = $pdo->prepare("
        SELECT HOUR(created_at) as hour, SUM(total_amount) as total
        FROM camping_bookings 
        WHERE payment_status = 'paid' AND DATE(created_at) = ?
        GROUP BY HOUR(created_at)
    ");
    $stmt->execute([$date]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $hourlyData[$row['hour']] += $row['total'];
    }
    
    return $hourlyData;
}

// Function to get monthly revenue for a specific year
function getMonthlyRevenue($pdo, $year = null) {
    if (!$year) $year = date('Y');
    
    $monthlyData = array_fill(1, 12, 0);
    
    $stmt = $pdo->prepare("
        SELECT MONTH(created_at) as month, SUM(total_amount) as total
        FROM bookings 
        WHERE payment_status = 'paid' AND YEAR(created_at) = ?
        GROUP BY MONTH(created_at)
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $monthlyData[$row['month']] += $row['total'];
    }
    
    $stmt = $pdo->prepare("
        SELECT MONTH(created_at) as month, SUM(total_amount) as total
        FROM camping_bookings 
        WHERE payment_status = 'paid' AND YEAR(created_at) = ?
        GROUP BY MONTH(created_at)
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $monthlyData[$row['month']] += $row['total'];
    }
    
    return $monthlyData;
}

// Function to get yearly revenue (last 5 years)
function getYearlyRevenue($pdo) {
    $yearlyData = [];
    $currentYear = date('Y');
    
    for ($i = 4; $i >= 0; $i--) {
        $year = $currentYear - $i;
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ?), 0) +
                COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ?), 0)
                as total
        ");
        $stmt->execute([$year, $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $yearlyData[$year] = $result['total'] ?? 0;
    }
    
    return $yearlyData;
}

// Get current data for different views
$currentHourlyData = getHourlyRevenue($pdo);
$currentMonthlyData = getMonthlyRevenue($pdo);
$currentYearlyData = getYearlyRevenue($pdo);

// Get hourly revenue for today with booking counts
$hourlyDetails = [];
for ($i = 0; $i < 24; $i++) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as booking_count,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM (
            SELECT total_amount, created_at FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE() AND HOUR(created_at) = ?
            UNION ALL
            SELECT total_amount, created_at FROM camping_bookings WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE() AND HOUR(created_at) = ?
        ) as hourly
    ");
    $stmt->execute([$i, $i]);
    $hourlyDetails[$i] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get peak hour information
$peakHour = 0;
$peakRevenue = 0;
foreach ($hourlyDetails as $hour => $data) {
    if ($data['revenue'] > $peakRevenue) {
        $peakRevenue = $data['revenue'];
        $peakHour = $hour;
    }
}
// Weekly bookings trend (last 4 weeks)
$weeklyBookings = [];
$weekLabels = [];
for ($i = 3; $i >= 0; $i--) {
    $weekLabels[] = 'Wk ' . (4 - $i);
    $startDate = date('Y-m-d', strtotime('-' . ($i + 1) . ' weeks monday'));
    $endDate = date('Y-m-d', strtotime('-' . $i . ' weeks sunday'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM (
            SELECT id FROM bookings WHERE DATE(created_at) BETWEEN ? AND ?
            UNION ALL
            SELECT id FROM camping_bookings WHERE DATE(created_at) BETWEEN ? AND ?
        ) as weekly
    ");
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    $weeklyBookings[] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}
// ========== RECENT BOOKINGS WITH FULL DETAILS ==========
$stmt = $pdo->prepare("
    SELECT 
        'booking' as type,
        b.id,
        b.booking_number,
        b.user_id,
        u.name as hiker_name,
        u.email as hiker_email,
        u.phone as hiker_phone,
        m.name as mountain_name,
        b.hike_date as date,
        b.hike_type,
        b.number_of_hikers,
        b.total_amount,
        b.downpayment_amount,
        b.payment_status as status,
        b.status as booking_status,
        g.name as guide_name,
        b.special_requests,
        b.created_at
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mountains m ON b.mountain_id = m.id
    LEFT JOIN users g ON b.guide_id = g.id
    WHERE u.role = 'hiker'
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each booking, fetch additional hikers from booking_hikers table
foreach ($recentBookings as &$booking) {
    $stmt = $pdo->prepare("
        SELECT 
            hiker_name,
            age,
            emergency_contact_name,
            emergency_contact_number
        FROM booking_hikers
        WHERE booking_id = ?
    ");
    $stmt->execute([$booking['id']]);
    $booking['additional_hikers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If not enough bookings, get from camping_bookings
if (count($recentBookings) < 5) {
    $stmt = $pdo->prepare("
        SELECT 
            'camping' as type,
            c.id,
            c.booking_number,
            c.user_id,
            u.name as hiker_name,
            u.email as hiker_email,
            u.phone as hiker_phone,
            m.name as mountain_name,
            c.start_date as date,
            c.end_date,
            c.number_of_nights,
            c.number_of_hikers,
            c.total_amount,
            c.downpayment_amount,
            c.payment_status as status,
            c.status as booking_status,
            c.campsite_name,
            c.equipment_rental,
            g.name as guide_name,
            c.special_requests,
            c.created_at
        FROM camping_bookings c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN mountains m ON c.mountain_id = m.id
        LEFT JOIN users g ON c.guide_id = g.id
        WHERE u.role = 'hiker'
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $campingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For camping bookings, add empty additional_hikers array
    foreach ($campingBookings as &$camping) {
        $camping['additional_hikers'] = [];
    }
    
    // Merge and sort
    $recentBookings = array_merge($recentBookings, $campingBookings);
    usort($recentBookings, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recentBookings = array_slice($recentBookings, 0, 5);
}

// ========== ACTIVE ALERT ==========
$stmt = $pdo->prepare("
    SELECT * FROM alerts 
    WHERE status IN ('active', 'pending') AND severity = 'high'
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute();
$activeAlert = $stmt->fetch(PDO::FETCH_ASSOC);

$alertTitle = $activeAlert ? $activeAlert['title'] : 'No active alerts';
$alertLocation = $activeAlert ? ($activeAlert['location'] . ' · ' . $activeAlert['type']) : 'All systems operational';

// Helper function for status badge class
function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'paid': return 'green';
        case 'pending': return 'amber';
        case 'completed':
        case 'finished': return 'gray';
        case 'failed':
        case 'expired': return 'red';
        default: return 'gray';
    }
}

function getStatusIcon($status) {
    switch(strtolower($status)) {
        case 'paid': return '<i class="fas fa-circle"></i>';
        case 'pending': return '<i class="fas fa-clock"></i>';
        case 'completed':
        case 'finished': return '<i class="fas fa-check"></i>';
        case 'failed':
        case 'expired': return '<i class="fas fa-exclamation-circle"></i>';
        default: return '<i class="fas fa-circle"></i>';
    }
}

// Format currency
function formatCurrency($amount) {
    if ($amount >= 1000) {
        return '₱' . number_format($amount / 1000, 1) . 'k';
    }
    return '₱' . number_format($amount, 0);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="admin/shared.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
</head>
<body data-page="dashboard">
<div class="app">

  <aside class="sidebar">
    <div>
      <div class="logo">
        <div class="logo-wordmark">
          <div class="logo-icon">
            <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#111318" opacity="0.9"/>
              <path d="M14 16L18 8L24 22H14V16Z" fill="#111318" opacity="0.35"/>
            </svg>
          </div>
          LAKBAY
        </div>
        <div class="logo-sub">wilderness: Silence beneath steps</div>
      </div>
      <div style="margin-bottom:8px; padding-left:28px;">
        <div class="nav-section-label">Navigation</div>
      </div>
      <ul class="nav-list">
        <li class="nav-item active" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
      </ul>
    </div>
   <div>
      <button class="logout-btn" onclick="showLogoutModal()" style="width:100%;display:flex;align-items:center;gap:12px;padding:10px 16px;background:transparent;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.82rem;font-weight:400;color:#dc2626;cursor:pointer;">
        <i class="fas fa-right-from-bracket" style="width:16px;font-size:0.75rem;"></i> 
        Log Out
      </button>
      <div class="sidebar-footer">
        <div class="status-dot"></div> 
        TEAM AURIX
      </div>
    </div>
  </aside>

   <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-chart-line"></i> Dashboard</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user" style="cursor: pointer;">
    <div class="avatar" id="topbarAvatar">
        <?php if (!empty($_SESSION['user_avatar'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <?= htmlspecialchars($adminInitial) ?>
        <?php endif; ?>
    </div>
    <?= htmlspecialchars($adminName) ?>
    <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
</div>
      </div>
    </div>


    <div class="content">

      <!-- STAT CARDS -->
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">Daily Bookings <i class="fas fa-ticket-alt"></i></div>
          <div class="stat-num" id="dailyBookVal"><?= $dailyBookings ?></div>
          <div class="<?= $bookingClass ?>"><?= $bookingIcon ?> <?= abs($bookingTrend) ?>% vs yesterday</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Active Hikers <i class="fas fa-person-hiking"></i></div>
          <div class="stat-num" id="activeHikVal"><?= $activeHikers ?></div>
          <div class="stat-trend">Active in last 7 days</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">MTD Revenue <i class="fas fa-coins"></i></div>
          <div class="stat-num"><?= formatCurrency($mtdRevenue) ?></div>
          <div class="stat-trend"><?= $revenueIcon ?> <?= abs($revenueTrend) ?>% vs last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Open Alerts <i class="fas fa-triangle-exclamation"></i></div>
          <div class="stat-num" style="color:var(--warn);"><?= $openAlerts ?></div>
          <div class="stat-trend warn"><?= $criticalAlerts ?> critical</div>
        </div>
      </div>

      <!-- CHARTS -->
<div class="two-col">
    <div class="panel">
        <div class="panel-header">
            <div>
                <span class="panel-title">Revenue Analytics</span>
                <div style="display: flex; gap: 8px; margin-top: 12px;">
                    <select id="revenueViewFilter" class="revenue-filter-select">
                        <option value="daily">This Week (Daily)</option>
                        <option value="monthly">Monthly View</option>
                        <option value="yearly">Yearly View</option>
                        <option value="hourly">Hourly Today</option>
                    </select>
                    <select id="revenueYearFilter" style="display: none;" class="revenue-filter-select">
                        <?php
                        $currentYear = date('Y');
                        for ($i = $currentYear - 4; $i <= $currentYear; $i++) {
                            echo "<option value='$i'>Year $i</option>";
                        }
                        ?>
                    </select>
                    <select id="revenueMonthFilter" style="display: none;" class="revenue-filter-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>
            </div>
            <div>
                <span class="panel-badge" id="revenueTotalDisplay"><?= formatCurrency($totalWeekRevenue) ?> total</span>
                <button class="btn btn-ghost" id="refreshRevenueBtn" style="margin-left: 12px;">
                    <i class="fas fa-rotate-right"></i> Refresh
                </button>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="chartRevenue"></canvas>
        </div>
        <div id="revenueInsights" style="margin-top: 16px; padding: 12px; background: var(--paper); border-radius: 12px; font-size: 0.75rem;">
            <i class="fas fa-info-circle"></i> Hover over any data point for detailed information
        </div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Bookings Trend</span>
            <button class="btn btn-ghost" id="refreshStatsBtn"><i class="fas fa-rotate-right"></i> Refresh</button>
        </div>
        <div class="chart-wrap"><canvas id="chartActivity"></canvas></div>
    </div>
</div>
      <!-- RECENT BOOKINGS -->
      <div class="full-span">
        <div class="section-header">
          <div class="section-title">Recent Bookings</div>
          <button class="section-action" id="viewAllBookings">View all →</button>
        </div>
        <div class="panel" style="padding:0 4px;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Hiker</th><th>Mountain</th><th>Date</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentBookings)): ?>
                <tr>
                  <td colspan="5" style="text-align:center; padding:40px;">No recent bookings found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentBookings as $booking): ?>
                  <tr>
                    <td><?= htmlspecialchars($booking['hiker_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($booking['mountain_name'] ?? 'Unknown') ?></td>
                    <td style="font-size:0.75rem;color:var(--ink-4);">
                      <?= date('M d', strtotime($booking['date'] ?? $booking['created_at'])) ?>
                    </td>
                    <td>
                      <span class="badge <?= getStatusBadgeClass($booking['status']) ?>">
                        <?= getStatusIcon($booking['status']) ?> 
                        <?= ucfirst($booking['status'] ?? 'Pending') ?>
                      </span>
                    </td>
                    <td>
                      <button class="btn btn-ghost viewBookBtn" data-name="<?= htmlspecialchars($booking['hiker_name'] ?? '') ?>" data-id="<?= $booking['id'] ?>">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ALERT BANNER -->
      <div class="panel" style="background:var(--ink);border-color:var(--ink);color:var(--paper);padding:18px 22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
          <div>
            <div style="font-size:0.65rem;font-weight:600;color:var(--ink-5);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em;">
              <?= $activeAlert ? 'Active Alert' : 'System Status' ?>
            </div>
            <div style="font-size:0.88rem;font-weight:500;">
              <?php if ($activeAlert): ?>
                ⚠️ <?= htmlspecialchars($alertTitle) ?> · <?= htmlspecialchars($alertLocation) ?>
              <?php else: ?>
                ✅ All systems operational · No active alerts
              <?php endif; ?>
            </div>
          </div>
          <?php if ($activeAlert): ?>
          <div style="display:flex;gap:8px;">
            <button class="btn" style="border-color:rgba(255,255,255,0.2);color:var(--paper);background:rgba(255,255,255,0.1);" id="ackAlertBtn"><i class="fas fa-check"></i> Acknowledge</button>
            <button class="btn" style="border-color:rgba(255,255,255,0.2);color:var(--paper);background:rgba(255,255,255,0.1);" id="viewAlertBtn"><i class="fas fa-arrow-right"></i> Details</button>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /app -->
<script>
// Pass PHP data to JavaScript
const revenueData = <?php echo json_encode($dailyRevenue); ?>;
const weeklyBookingsData = <?php echo json_encode($weeklyBookings); ?>;
const weekdays = <?php echo json_encode($weekdays); ?>;
const weekLabels = <?php echo json_encode($weekLabels); ?>;

// ========== CHART UTILITY FUNCTIONS ==========
// Define buildChart function FIRST
const charts = {};
function buildChart(id, type, data, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  
  // Destroy existing chart if it exists
  if (charts[id]) {
    charts[id].destroy();
  }
  
  charts[id] = new Chart(ctx, {
    type, data,
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid:{ display:false }, ticks:{ font:{ family:"'Inter'", size:10 }, color:'#9098a6' } },
        y: { display: false }
      },
      ...opts
    }
  });
}

// Live clock
function updateDate() {
  const d = new Date();
  document.getElementById('liveDate').textContent =
    d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() +
    '  ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
updateDate(); 
setInterval(updateDate, 1000);

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    if(item.dataset.href) {
      window.location.href = item.dataset.href;
    }
  });
});

// ========== ENHANCED REVENUE CHART WITH FILTERS ==========

let currentRevenueChart = null;
let currentRevenueData = {
    daily: { labels: weekdays, data: revenueData, total: <?= $totalWeekRevenue ?> },
    monthly: null,
    yearly: null,
    hourly: null
};

// Set default year to 2026 for year filter
document.addEventListener('DOMContentLoaded', function() {
    const yearFilter = document.getElementById('revenueYearFilter');
    if (yearFilter) {
        // Try to set to 2026, fallback to latest year if not available
        let year2026Exists = false;
        for (let i = 0; i < yearFilter.options.length; i++) {
            if (yearFilter.options[i].value === '2026') {
                year2026Exists = true;
                break;
            }
        }
        if (year2026Exists) {
            yearFilter.value = '2026';
        } else {
            yearFilter.value = yearFilter.options[yearFilter.options.length - 1]?.value || '<?= date('Y') ?>';
        }
        
        // Trigger change to load data for default year
        yearFilter.dispatchEvent(new Event('change'));
    }
});

// Fetch data via AJAX
async function fetchRevenueData(view, year = null, month = null) {
    const params = new URLSearchParams();
    params.append('action', 'get_revenue_data');
    params.append('view', view);
    if (year) params.append('year', year);
    if (month) params.append('month', month);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching revenue data:', error);
        return null;
    }
}

// Update chart with custom tooltips
function updateRevenueChart(labels, data, total, viewType, metadata = null) {
    const ctx = document.getElementById('chartRevenue');
    if (!ctx) return;
    
    if (currentRevenueChart) {
        currentRevenueChart.destroy();
    }
    
    // Format currency for tooltip
    const formatCurrencyFull = (value) => {
        return '₱' + parseFloat(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    };
    
    currentRevenueChart = new Chart(ctx, {
        type: viewType === 'hourly' ? 'bar' : 'line',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderColor: '#111318',
                backgroundColor: viewType === 'hourly' ? 'rgba(17,19,24,0.8)' : 'rgba(17,19,24,0.04)',
                tension: viewType === 'hourly' ? 0 : 0.4,
                fill: viewType === 'hourly' ? false : true,
                pointRadius: viewType === 'hourly' ? 4 : 5,
                pointHoverRadius: viewType === 'hourly' ? 6 : 8,
                pointBackgroundColor: '#111318',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                borderWidth: 2,
                barPercentage: 0.7,
                categoryPercentage: 0.9
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            let revenue = formatCurrencyFull(value);
                            
                            if (metadata && metadata[context.dataIndex]) {
                                let meta = metadata[context.dataIndex];
                                if (meta.booking_count !== undefined && meta.booking_count > 0) {
                                    return [`Revenue: ${revenue}`, `Bookings: ${meta.booking_count}`];
                                }
                            }
                            
                            if (viewType === 'hourly') {
                                let hour = parseInt(context.label);
                                let period = hour >= 12 ? 'PM' : 'AM';
                                let hour12 = hour % 12 || 12;
                                return [` ${hour12}:00 ${period}`, `Revenue: ${revenue}`];
                            } else if (viewType === 'daily') {
                                return [`${context.label}`, `Revenue: ${revenue}`];
                            } else if (viewType === 'monthly') {
                                return [`${context.label}`, `Revenue: ${revenue}`];
                            } else if (viewType === 'yearly') {
                                return [`${context.label}`, `Revenue: ${revenue}`];
                            }
                            return `Revenue: ${revenue}`;
                        },
                        footer: function(tooltipItems) {
                            if (viewType === 'hourly' && metadata && metadata[tooltipItems[0].dataIndex]) {
                                let meta = metadata[tooltipItems[0].dataIndex];
                                if (meta.booking_count && meta.booking_count > 0 && meta.revenue > 0) {
                                    let avgPerBooking = meta.revenue / meta.booking_count;
                                    return [`📈 Average per booking: ${formatCurrencyFull(avgPerBooking)}`];
                                }
                            }
                            return [];
                        }
                    },
                    backgroundColor: 'rgba(0,0,0,0.85)',
                    titleColor: '#fff',
                    bodyColor: '#e0e0e0',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: viewType === 'hourly' ? {
                x: {
                    grid: { display: false },
                    ticks: { 
                        font: { family: "'Inter'", size: 10 }, 
                        color: '#9098a6',
                        callback: function(val, index) {
                            let hour = this.getLabelForValue(val);
                            if (hour % 3 === 0 || hour === 12) {
                                let period = hour >= 12 ? 'PM' : 'AM';
                                let hour12 = hour % 12 || 12;
                                return `${hour12}${period}`;
                            }
                            return '';
                        }
                    }
                },
                y: { 
                    display: true,
                    grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                    ticks: { 
                        font: { family: "'Inter'", size: 10 }, 
                        color: '#9098a6',
                        callback: function(value) {
                            if (value >= 1000) {
                                return '₱' + (value / 1000).toFixed(0) + 'k';
                            }
                            return '₱' + value;
                        }
                    },
                    title: {
                        display: true,
                        text: 'Revenue (PHP)',
                        font: { size: 10 }
                    }
                }
            } : {
                x: { 
                    grid: { display: false }, 
                    ticks: { font: { family: "'Inter'", size: 10 }, color: '#9098a6' } 
                },
                y: { 
                    display: true,
                    grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                    ticks: { 
                        font: { family: "'Inter'", size: 10 }, 
                        color: '#9098a6',
                        callback: function(value) {
                            if (value >= 1000) {
                                return '₱' + (value / 1000).toFixed(0) + 'k';
                            }
                            return '₱' + value;
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            hover: {
                mode: 'nearest',
                intersect: false
            },
            onClick: function(event, activeElements) {
                if (activeElements.length > 0) {
                    const index = activeElements[0].dataIndex;
                    const label = this.data.labels[index];
                    const value = this.data.datasets[0].data[index];
                    showRevenueDetail(label, value, viewType, metadata ? metadata[index] : null);
                }
            }
        }
    });
    
    // Update total display
    const totalDisplay = document.getElementById('revenueTotalDisplay');
    if (totalDisplay) {
        totalDisplay.innerHTML = formatCurrency(total) + ' total';
    }
    
    // Update insights
    updateRevenueInsights(labels, data, viewType, metadata);
}
function updateRevenueInsights(labels, data, viewType, metadata) {
    const insightsDiv = document.getElementById('revenueInsights');
    if (!insightsDiv) return;
    
    // Debug: Log what we received
    console.log('updateRevenueInsights called with:', { viewType, labels, data });
    
    // Handle empty or all-zero data
    if (!data || data.length === 0) {
        insightsDiv.innerHTML = `<i class="fas fa-info-circle"></i> <span>No revenue data available for this period.</span>`;
        return;
    }
    
    // Parse strings to numbers to avoid string concatenation and indexOf issues
    const numericData = data.map(v => parseFloat(v || 0));
    const totalRevenue = numericData.reduce((a, b) => a + b, 0);
    
    if (totalRevenue === 0) {
        insightsDiv.innerHTML = `<i class="fas fa-info-circle"></i> <span>No revenue generated during this period.</span>`;
        return;
    }
    
    const maxRevenue = Math.max(...numericData);
    const maxIndex = numericData.indexOf(maxRevenue);
    
    // Get the proper label based on view type
    let peakLabel = '';
    
    if (viewType === 'daily') {
        // Use the actual day name from the labels array
        if (labels && labels[maxIndex]) {
            const dayNames = {
                'Mon': 'Monday',
                'Tue': 'Tuesday', 
                'Wed': 'Wednesday',
                'Thu': 'Thursday',
                'Fri': 'Friday',
                'Sat': 'Saturday',
                'Sun': 'Sunday'
            };
            peakLabel = dayNames[labels[maxIndex]] || labels[maxIndex];
        } else {
            // Fallback: use the index to determine day
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            peakLabel = days[maxIndex] || `Day ${maxIndex + 1}`;
        }
    } else if (viewType === 'monthly') {
        if (labels && labels[maxIndex]) {
            peakLabel = labels[maxIndex];
        } else {
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            peakLabel = months[maxIndex] || `Month ${maxIndex + 1}`;
        }
    } else if (viewType === 'yearly') {
        if (labels && labels[maxIndex]) {
            peakLabel = labels[maxIndex];
        } else {
            const currentYear = new Date().getFullYear();
            peakLabel = String(currentYear - (numericData.length - 1 - maxIndex));
        }
    } else if (viewType === 'hourly') {
        peakLabel = `${maxIndex}:00 ${maxIndex >= 12 ? 'PM' : 'AM'}`;
    } else {
        peakLabel = (labels && labels[maxIndex]) ? labels[maxIndex] : `Period ${maxIndex + 1}`;
    }
    
    // Calculate average only on active periods (non-zero values)
    const activeValues = numericData.filter(v => v > 0);
    const avgRevenue = activeValues.length > 0 ? totalRevenue / activeValues.length : 0;
    const activePeriods = activeValues.length;
    
    let insightsHtml = `<i class="fas fa-chart-line"></i> <strong>Revenue Insights:</strong> `;
    
    if (viewType === 'daily') {
        insightsHtml += `Peak day: <strong>${peakLabel}</strong> with ${formatCurrency(maxRevenue)} | `;
        insightsHtml += `Daily average: ${formatCurrency(avgRevenue)} | `;
        insightsHtml += `${activePeriods} out of ${numericData.length} days with activity`;
    } else if (viewType === 'monthly') {
        insightsHtml += `Peak month: <strong>${peakLabel}</strong> with ${formatCurrency(maxRevenue)} | `;
        insightsHtml += `Monthly average: ${formatCurrency(avgRevenue)} | `;
        insightsHtml += `Total YTD: ${formatCurrency(totalRevenue)}`;
    } else if (viewType === 'yearly') {
        insightsHtml += `Peak year: <strong>${peakLabel}</strong> with ${formatCurrency(maxRevenue)} | `;
        insightsHtml += `Yearly average: ${formatCurrency(avgRevenue)} | `;
        insightsHtml += `Total (5 years): ${formatCurrency(totalRevenue)}`;
    } else if (viewType === 'hourly' && metadata) {
        let peakHour = 0;
        let maxRev = 0;
        metadata.forEach((m, idx) => {
            const rev = parseFloat(m.revenue || 0);
            if (rev > maxRev) { maxRev = rev; peakHour = idx; }
        });
        const peakHourLabel = `${peakHour}:00 ${peakHour >= 12 ? 'PM' : 'AM'}`;
        insightsHtml = `<i class="fas fa-chart-line"></i> <strong>Hourly Insights:</strong> `;
        insightsHtml += `Peak hour: <strong>${peakHourLabel}</strong> with ${formatCurrency(maxRev)} | `;
        insightsHtml += `Total today: ${formatCurrency(totalRevenue)} | `;
        insightsHtml += `Bookings today: ${metadata.reduce((sum, h) => sum + parseInt(h.booking_count || 0), 0)}`;
    }
    
    insightsDiv.innerHTML = insightsHtml;
}
function showRevenueDetail(label, value, viewType, metadata) {
    // Optional: Show a more detailed modal or toast notification
    console.log(`Detail clicked: ${label} - ${formatCurrency(value)}`);
}

function formatCurrency(value) {
    if (value === null || value === undefined || isNaN(value)) {
        return '₱0.00';
    }
    return '₱' + parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 2 });
}

// Handle filter changes
document.getElementById('revenueViewFilter')?.addEventListener('change', async (e) => {
    const view = e.target.value;
    const yearFilter = document.getElementById('revenueYearFilter');
    const monthFilter = document.getElementById('revenueMonthFilter');
    
    if (view === 'yearly') {
        yearFilter.style.display = 'inline-block';
        monthFilter.style.display = 'none';
        const year = yearFilter.value;
        const data = await fetchRevenueData('yearly', year);
        if (data && data.labels && data.data) {
            updateRevenueChart(data.labels, data.data, data.total, 'yearly', data.metadata);
        }
    } else if (view === 'monthly') {
        yearFilter.style.display = 'inline-block';
        monthFilter.style.display = 'none';
        const year = yearFilter.value;
        const data = await fetchRevenueData('monthly', year);
        if (data && data.labels && data.data) {
            updateRevenueChart(data.labels, data.data, data.total, 'monthly', data.metadata);
        }
    } else if (view === 'hourly') {
        yearFilter.style.display = 'none';
        monthFilter.style.display = 'none';
        const data = await fetchRevenueData('hourly');
        if (data && data.labels && data.data) {
            updateRevenueChart(data.labels, data.data, data.total, 'hourly', data.metadata);
        }
    } else {
        yearFilter.style.display = 'none';
        monthFilter.style.display = 'none';
        const data = await fetchRevenueData('daily');
        if (data && data.labels && data.data) {
            updateRevenueChart(data.labels, data.data, data.total, 'daily', data.metadata);
        }
    }
});

document.getElementById('revenueYearFilter')?.addEventListener('change', async (e) => {
    const view = document.getElementById('revenueViewFilter').value;
    const year = e.target.value;
    
    if (view === 'yearly') {
        const data = await fetchRevenueData('yearly', year);
        if (data && data.labels && data.data) {
            updateRevenueChart(data.labels, data.data, data.total, 'yearly', data.metadata);
        }
    } else if (view === 'monthly') {
        const data = await fetchRevenueData('monthly', year);
        if (data && data.labels && data.data) {
            updateRevenueChart(data.labels, data.data, data.total, 'monthly', data.metadata);
        }
    }
});

document.getElementById('refreshRevenueBtn')?.addEventListener('click', () => {
    document.getElementById('revenueViewFilter').dispatchEvent(new Event('change'));
});

// Initialize revenue chart
updateRevenueChart(weekdays, revenueData, <?= $totalWeekRevenue ?>, 'daily');

// Initialize Weekly Bookings Chart (ONLY ONCE)
buildChart('chartActivity', 'bar', {
    labels: weekLabels,
    datasets: [{ 
        data: weeklyBookingsData, 
        backgroundColor: 'rgba(17,19,24,0.12)', 
        borderRadius: 4 
    }]
});

// Button events
document.getElementById('refreshStatsBtn')?.addEventListener('click', () => {
  location.reload();
});

document.getElementById('viewAllBookings')?.addEventListener('click', () => {
  window.location.href = '/pages/admin/hikers.php?tab=bookings-tab';
});

document.getElementById('ackAlertBtn')?.addEventListener('click', () => {
  <?php if ($activeAlert): ?>
  fetch(window.location.href, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({
      action: 'acknowledge',
      alert_id: <?= $activeAlert['id'] ?>
    })
  })
  .then(response => response.json())
  .then(result => {
    if(result.success) {
      alert('✅ Alert acknowledged.');
      location.reload();
    }
  })
  .catch(() => alert('✅ Alert acknowledged.'));
  <?php else: ?>
  alert('No active alerts to acknowledge.');
  <?php endif; ?>
});

document.getElementById('viewAlertBtn')?.addEventListener('click', () => {
  window.location.href = '/pages/admin/alerts.php';
});

// Pass PHP booking data to JavaScript
const recentBookingsData = <?php echo json_encode($recentBookings); ?>;

// View booking button handler
document.querySelectorAll('.viewBookBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        const bookingId = parseInt(btn.dataset.id);
        const bookingData = recentBookingsData.find(b => b.id === bookingId);
        if (bookingData) {
            openBookingDetailModal(bookingData);
        } else {
            alert('Booking details not available');
        }
    });
});

// Function to open the detailed modal
function openBookingDetailModal(booking) {
    const isCamping = booking.type === 'camping';
    
    let html = `
        <div class="detail-section">
            <div class="detail-section-title">Booking Information</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Booking Number</div>
                    <div class="detail-val"><strong>${escapeHtml(booking.booking_number)}</strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-val"><span class="badge ${getBookingStatusBadgeClass(booking.booking_status)}">${getBookingStatusLabel(booking.booking_status)}</span></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Payment Status</div>
                    <div class="detail-val"><span class="badge ${getPaymentBadgeClass(booking.status)}">${getPaymentStatusLabel(booking.status)}</span></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Mountain</div>
                    <div class="detail-val">${escapeHtml(booking.mountain_name)}</div>
                </div>
                ${!isCamping ? `
                <div class="detail-item">
                    <div class="detail-label">Hike Date</div>
                    <div class="detail-val">${formatDate(booking.date)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Hike Type</div>
                    <div class="detail-val">${escapeHtml(booking.hike_type || 'Day Hike')}</div>
                </div>
                ` : `
                <div class="detail-item">
                    <div class="detail-label">Start Date</div>
                    <div class="detail-val">${formatDate(booking.date)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">End Date</div>
                    <div class="detail-val">${formatDate(booking.end_date)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Nights</div>
                    <div class="detail-val">${booking.number_of_nights}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Campsite</div>
                    <div class="detail-val">${escapeHtml(booking.campsite_name || 'Not specified')}</div>
                </div>
                `}
                <div class="detail-item">
                    <div class="detail-label">Total Hikers</div>
                    <div class="detail-val">${booking.number_of_hikers}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Amount</div>
                    <div class="detail-val">${formatCurrency(booking.total_amount)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Downpayment</div>
                    <div class="detail-val">${formatCurrency(booking.downpayment_amount)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Guide</div>
                    <div class="detail-val">${escapeHtml(booking.guide_name || 'Not assigned')}</div>
                </div>
                ${booking.special_requests ? `
                <div class="detail-item span2">
                    <div class="detail-label">Special Requests</div>
                    <div class="detail-val">${escapeHtml(booking.special_requests)}</div>
                </div>
                ` : ''}
                ${isCamping && booking.equipment_rental ? `
                <div class="detail-item span2">
                    <div class="detail-label">Equipment Rental</div>
                    <div class="detail-val">${escapeHtml(booking.equipment_rental)}</div>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="detail-section">
            <div class="detail-section-title">Main Hiker (Booker)</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Name</div>
                    <div class="detail-val"><strong>${escapeHtml(booking.hiker_name)}</strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-val">${escapeHtml(booking.hiker_email || 'Not provided')}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-val">${escapeHtml(booking.hiker_phone || 'Not provided')}</div>
                </div>
            </div>
        </div>
    `;
    
    // Add additional hikers if any
    if (booking.additional_hikers && booking.additional_hikers.length > 0) {
        html += `
            <div class="detail-section">
                <div class="detail-section-title">Group Members (${booking.additional_hikers.length} additional)</div>
                <table class="hikers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Emergency Contact</th>
                            <th>Emergency Phone</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        booking.additional_hikers.forEach(hiker => {
            html += `
                <tr>
                    <td>${escapeHtml(hiker.hiker_name)}</td>
                    <td>${hiker.age || 'N/A'}</td>
                    <td>${escapeHtml(hiker.emergency_contact_name || 'N/A')}</td>
                    <td>${escapeHtml(hiker.emergency_contact_number || 'N/A')}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    document.getElementById('bookingDetailTitle').textContent = `${isCamping ? 'Camping' : 'Booking'}: ${booking.booking_number}`;
    document.getElementById('bookingDetailBody').innerHTML = html;
    openBookingModal();
}

function openBookingModal() {
    const modal = document.getElementById('bookingDetailModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
}

function closeBookingModal() {
    const modal = document.getElementById('bookingDetailModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

// Helper functions
function getBookingStatusBadgeClass(status) {
    const classes = {
        'active': 'green',
        'finished': 'gray',
        'expired': 'red',
        'pending': 'amber',
        'confirmed': 'green'
    };
    return classes[status] || 'gray';
}

function getBookingStatusLabel(status) {
    const labels = {
        'active': 'Active',
        'finished': 'Finished',
        'expired': 'Expired',
        'pending': 'Pending',
        'confirmed': 'Confirmed'
    };
    return labels[status] || status || 'Pending';
}

function getPaymentBadgeClass(status) {
    const classes = {
        'paid': 'green',
        'pending': 'amber',
        'expired': 'red',
        'failed': 'red'
    };
    return classes[status] || 'gray';
}

function getPaymentStatusLabel(status) {
    const labels = {
        'paid': 'Paid',
        'pending': 'Pending',
        'expired': 'Expired',
        'failed': 'Failed'
    };
    return labels[status] || status || 'Pending';
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Close modal when clicking overlay
document.getElementById('bookingDetailModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBookingModal();
    }
});

// Make topbar user clickable
document.addEventListener('DOMContentLoaded', function() {
    const topbarUser = document.querySelector('.topbar-user');
    if (topbarUser) {
        topbarUser.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof openProfileModal === 'function') {
                openProfileModal();
            } else {
                alert('Profile modal not loaded yet. Please refresh the page.');
            }
        });
    }
});

</script>
<!-- Include Profile Modal -->
<?php 

$modalPath = __DIR__ . '/admin/profile-modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
    echo '<!-- Profile modal loaded from: ' . $modalPath . ' -->';
} else {
    echo '<!-- Profile modal NOT found at: ' . $modalPath . ' -->';
    // Fallback: try alternative path
    $altPath = 'admin/profile-modal.php';
    if (file_exists($altPath)) {
        include_once $altPath;
        echo '<!-- Profile modal loaded from: ' . $altPath . ' -->';
    }
}
?>
<!-- Include Logout Modal -->
<?php 
$logoutModalPath = __DIR__ . '/includes/logout-modal.php';
if (file_exists($logoutModalPath)) {
    include_once $logoutModalPath;
    echo '<!-- Logout modal loaded from: ' . $logoutModalPath . ' -->';
} else {
    // Try alternative path from pages directory
    $altLogoutPath = '../includes/logout-modal.php';
    if (file_exists($altLogoutPath)) {
        include_once $altLogoutPath;
        echo '<!-- Logout modal loaded from: ' . $altLogoutPath . ' -->';
    } else {
        echo '<!-- Logout modal NOT found -->';
    }
}
?>
<!-- Booking Details Modal -->
<div class="modal-overlay" id="bookingDetailModal" style="display: none; position: fixed; inset: 0; background: rgba(10,12,18,0.55); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;">
    <div class="modal" style="width: 100%; max-width: 1000px; max-height: 90vh; background: white; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
            <div class="modal-title" style="font-size: 1.1rem; font-weight: 600;">
                <i class="fas fa-calendar-check"></i> 
                <span id="bookingDetailTitle">Booking Details</span>
            </div>
            <button class="modal-close" onclick="closeBookingModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer;">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="bookingDetailBody" style="flex: 1; overflow-y: auto; padding: 24px;">
            <!-- Content will be loaded here -->
        </div>
        <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end;">
            <button class="btn btn-ghost" onclick="closeBookingModal()">Close</button>
        </div>
    </div>
</div>

<style>
    .detail-section { margin-bottom: 28px; }
    .detail-section-title {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-4);
        padding-bottom: 8px;
        border-bottom: 1px solid var(--line);
        margin-bottom: 16px;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .detail-item.span2 {
        grid-column: span 2;
    }
    .detail-item.span3 {
        grid-column: span 3;
    }
    .detail-label {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--ink-4);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .detail-val {
        font-size: 0.85rem;
        color: var(--ink);
        font-weight: 500;
    }
    .hikers-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
    }
    .hikers-table th {
        text-align: left;
        padding: 10px 8px;
        background: var(--paper);
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--ink-4);
        border-bottom: 1px solid var(--line);
    }
    .hikers-table td {
        padding: 10px 8px;
        font-size: 0.75rem;
        border-bottom: 1px solid var(--line);
        color: var(--ink-2);
    }
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
    }
    .badge.green { background: rgba(30,123,72,0.1); color: #1E7B48; }
    .badge.amber { background: rgba(230,126,34,0.1); color: #c96a10; }
    .badge.red { background: rgba(192,57,43,0.1); color: #C0392B; }
    .badge.gray { background: #eef2f8; color: #6e7483; }

    /* Responsive adjustments for Booking Modal */
    @media (max-width: 900px) {
        .detail-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .detail-item.span3 {
            grid-column: span 2;
        }
    }
    
    @media (max-width: 600px) {
        .detail-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        .detail-item.span2, .detail-item.span3 {
            grid-column: span 1;
        }
        .modal-header, .modal-body, .modal-footer {
            padding: 16px !important;
        }
        .detail-section {
            margin-bottom: 20px;
        }
        .hikers-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        .hikers-table th, .hikers-table td {
            padding: 8px;
        }
    }
     .revenue-filter-select {
        font-family: 'Inter', sans-serif;
        font-size: 0.75rem;
        padding: 6px 12px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: white;
        color: var(--ink);
        cursor: pointer;
        outline: none;
    }
    .revenue-filter-select:hover {
        border-color: var(--ink-3);
    }
    .chart-wrap {
        position: relative;
        min-height: 300px;
    }
    .revenue-tooltip {
        position: fixed;
        background: rgba(0,0,0,0.9);
        color: white;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 0.8rem;
        z-index: 1000;
        pointer-events: none;
        font-family: 'Inter', sans-serif;
        backdrop-filter: blur(8px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        max-width: 250px;
        line-height: 1.5;
    }
    .revenue-tooltip strong {
        color: #ffd700;
    }
</style>
</body>
</html>