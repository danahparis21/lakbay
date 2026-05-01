<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login-and-signup/login.php');
    exit;
}

require_once '../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// ========== HANDLE AJAX REQUESTS ==========
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'get_revenue_data') {
            $view = $_POST['view'] ?? 'daily';
            $response = ['labels' => [], 'data' => [], 'total' => 0, 'metadata' => []];
            
            if ($view === 'daily') {
                $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                $response['labels'] = $weekdays;
                for ($i = 0; $i < 7; $i++) {
                    $date = date('Y-m-d', strtotime('monday this week +' . $i . ' days'));
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as booking_count
                        FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?
                    ");
                    $stmt->execute([$date]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response['data'][] = $row['total'];
                    $response['total'] += $row['total'];
                    $response['metadata'][] = ['revenue' => $row['total'], 'booking_count' => $row['booking_count']];
                }
            } elseif ($view === 'monthly') {
                $year = $_POST['year'] ?? date('Y');
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $response['labels'] = $months;
                for ($month = 1; $month <= 12; $month++) {
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(total_amount), 0) as total
                        FROM bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ? AND MONTH(created_at) = ?
                    ");
                    $stmt->execute([$year, $month]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response['data'][] = $row['total'];
                    $response['total'] += $row['total'];
                }
            } elseif ($view === 'yearly') {
                $currentYear = date('Y');
                for ($i = 4; $i >= 0; $i--) {
                    $year = $currentYear - $i;
                    $response['labels'][] = $year;
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(total_amount), 0) as total
                        FROM bookings WHERE payment_status = 'paid' AND YEAR(created_at) = ?
                    ");
                    $stmt->execute([$year]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response['data'][] = $row['total'];
                    $response['total'] += $row['total'];
                }
            }
            
            echo json_encode($response);
            exit;
        }
    }
}

// ========== COMPREHENSIVE STAT CARDS ==========

// 1. TODAY'S OPERATIONS OVERVIEW
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN status = 'active' AND hike_date = CURDATE() AND payment_status = 'paid' THEN id END) as active_treks_today,
        COUNT(DISTINCT CASE WHEN status = 'active' AND hike_date = CURDATE() AND payment_status = 'paid' THEN guide_id END) as guides_on_trail,
        SUM(CASE WHEN status = 'active' AND hike_date = CURDATE() AND payment_status = 'paid' THEN number_of_hikers ELSE 0 END) as hikers_on_trail,
        COUNT(DISTINCT CASE WHEN status = 'pending' AND payment_status = 'pending' AND hike_date >= CURDATE() THEN id END) as pending_treks,
        COUNT(DISTINCT CASE WHEN status = 'finished' AND DATE(completed_at) = CURDATE() THEN id END) as completed_today
    FROM bookings
");
$stmt->execute();
$opsData = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. SAFETY & ALERTS OVERVIEW
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN severity = 'critical' AND status = 'active' THEN 1 END) as critical_alerts,
        COUNT(CASE WHEN severity = 'high' AND status = 'active' THEN 1 END) as high_alerts,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as total_active_alerts
    FROM alerts
");
$stmt->execute();
$safetyMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

// Unsafe hikers
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id) as unsafe_hikers
    FROM hiker_safety_status
    WHERE status = 'unsafe' AND updated_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
");
$stmt->execute();
$unsafeHikers = $stmt->fetch(PDO::FETCH_ASSOC)['unsafe_hikers'] ?? 0;

// 3. REVENUE METRICS
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' AND downpayment_deadline > NOW() THEN downpayment_amount ELSE 0 END), 0) as pending_downpayments,
        COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'partial') AND downpayment_deadline < NOW() THEN total_amount ELSE 0 END), 0) as expired_payments
    FROM bookings
");
$stmt->execute();
$revenueMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. GUIDE PERFORMANCE
$stmt = $pdo->prepare("
    SELECT 
        g.name,
        COUNT(b.id) as total_treks,
        ROUND(AVG(gr.rating), 1) as avg_rating,
        COUNT(CASE WHEN b.status = 'finished' THEN 1 END) as completed_treks
    FROM users g
    LEFT JOIN bookings b ON g.id = b.guide_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN guide_reviews gr ON g.id = gr.guide_id AND gr.status = 'approved'
    WHERE g.role = 'guide'
    GROUP BY g.id, g.name
    ORDER BY completed_treks DESC
    LIMIT 5
");
$stmt->execute();
$topGuides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. MOUNTAIN POPULARITY
$stmt = $pdo->prepare("
    SELECT 
        m.name,
        COUNT(b.id) as total_bookings_30d,
        COALESCE(cr.crowd_level, 'Unknown') as current_crowd
    FROM mountains m
    LEFT JOIN bookings b ON m.id = b.mountain_id AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN crowd_reports cr ON m.id = cr.mountain_id AND cr.expires_at > NOW()
    GROUP BY m.id, m.name, cr.crowd_level
    ORDER BY total_bookings_30d DESC
    LIMIT 5
");
$stmt->execute();
$popularMountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. UPCOMING BOOKINGS
$stmt = $pdo->prepare("
    SELECT 
        b.booking_number, u.name as hiker_name, m.name as mountain_name,
        b.hike_date, b.number_of_hikers, b.payment_status,
        CASE 
            WHEN b.downpayment_deadline < NOW() AND b.payment_status != 'paid' THEN 'overdue'
            WHEN b.payment_status = 'paid' THEN 'paid'
            WHEN b.downpayment_deadline <= DATE_ADD(NOW(), INTERVAL 2 DAY) THEN 'due_soon'
            ELSE 'pending'
        END as urgency
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN mountains m ON b.mountain_id = m.id
    WHERE b.hike_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY b.hike_date ASC
    LIMIT 10
");
$stmt->execute();
$upcomingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. HOURLY CHECK-IN PATTERN
$hourlyCheckins = [];
for ($i = 0; $i < 24; $i++) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as checkins
        FROM active_hike_sessions
        WHERE DATE(start_time) = CURDATE() AND HOUR(start_time) = ?
    ");
    $stmt->execute([$i]);
    $hourlyCheckins[$i] = $stmt->fetch(PDO::FETCH_ASSOC)['checkins'] ?? 0;
}

// 8. REVIEWS SUMMARY
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reviews,
        ROUND(AVG(rating), 1) as avg_rating
    FROM system_reviews
    WHERE status != 'rejected'
");
$stmt->execute();
$reviewSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// 9. ACTIVE ALERTS
$stmt = $pdo->prepare("
    SELECT id, title, location, severity, created_at
    FROM alerts
    WHERE status = 'active' AND severity IN ('critical', 'high')
    ORDER BY created_at DESC
    LIMIT 3
");
$stmt->execute();
$activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. RECENT BOOKINGS
$stmt = $pdo->prepare("
    SELECT 
        b.id, b.booking_number, u.name as hiker_name, m.name as mountain_name,
        b.hike_date, b.payment_status, b.status as booking_status,
        b.total_amount, b.created_at
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mountains m ON b.mountain_id = m.id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function getUrgencyBadge($urgency) {
    $badges = [
        'overdue' => '<span class="badge red"><i class="fas fa-skull-crosswalk"></i> OVERDUE</span>',
        'due_soon' => '<span class="badge amber"><i class="fas fa-hourglass-half"></i> Due Soon</span>',
        'paid' => '<span class="badge green"><i class="fas fa-check-circle"></i> Paid</span>',
        'pending' => '<span class="badge gray"><i class="fas fa-clock"></i> Pending</span>'
    ];
    return $badges[$urgency] ?? '<span class="badge gray">Unknown</span>';
}

function formatCurrencyShort($amount) {
    if ($amount >= 1000000) return '₱' . number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return '₱' . number_format($amount / 1000, 1) . 'k';
    return '₱' . number_format($amount, 0);
}

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'paid': return 'green';
        case 'pending': return 'amber';
        case 'active': return 'green';
        case 'finished': return 'gray';
        default: return 'gray';
    }
}

// Revenue chart data
$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dailyRevenue = [];
$totalWeekRevenue = 0;
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime('monday this week +' . $i . ' days'));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $dailyRevenue[] = $revenue;
    $totalWeekRevenue += $revenue;
}

// Weekly bookings trend
$weeklyBookings = [];
$weekLabels = [];
for ($i = 3; $i >= 0; $i--) {
    $weekLabels[] = 'Wk ' . (4 - $i);
    $startDate = date('Y-m-d', strtotime('-' . ($i + 1) . ' weeks monday'));
    $endDate = date('Y-m-d', strtotime('-' . $i . ' weeks sunday'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $weeklyBookings[] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
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
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

  <link rel="stylesheet" href="admin/shared.css">
</head>
<body data-page="dashboard">
<div class="app">

    <!-- SIDEBAR -->
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
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard-admin.php' ? 'active' : '' ?>" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'mountains.php' ? 'active' : '' ?>" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'hikers.php' ? 'active' : '' ?>" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'guides.php' ? 'active' : '' ?>" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : '' ?>" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'active' : '' ?>" data-href="/pages/admin/reviews.php"><i class="fas fa-star"></i> Reviews</li>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'alerts.php' ? 'active' : '' ?>" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : '' ?>" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
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

  <!-- MAIN -->
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

    <!-- ALERT BANNER - COMPACT VERSION -->
<?php if (!empty($activeAlerts)): ?>
<div style="background:var(--ink);color:var(--paper);padding:12px 20px;margin-bottom:24px;border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span style="background:#dc2626;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;">⚠️ CRITICAL</span>
    <span style="font-size:13px;">
      <?php foreach ($activeAlerts as $idx => $alert): ?>
        <?= $idx > 0 ? ' · ' : '' ?><?= htmlspecialchars($alert['title']) ?> (<?= htmlspecialchars($alert['location']) ?>)
      <?php endforeach; ?>
    </span>
  </div>
  <button class="btn" style="border-color:rgba(255,255,255,0.2);color:var(--paper);background:rgba(255,255,255,0.1);padding:6px 12px;font-size:12px;" onclick="window.location.href='/pages/admin/alerts.php'">
    Manage →
  </button>
</div>
<?php endif; ?>

      <!-- STAT CARDS (Expanded to 6 for comprehensive view) -->
      <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
          <div class="stat-label">Active Treks Now <i class="fas fa-person-hiking"></i></div>
          <div class="stat-num"><?= $opsData['active_treks_today'] ?? 0 ?></div>
          <div class="stat-trend"><?= $opsData['hikers_on_trail'] ?? 0 ?> hikers on trail</div>
          <div style="margin-top:8px; font-size:0.7rem; color:var(--ink-4);">👥 <?= $opsData['guides_on_trail'] ?? 0 ?> guides active</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-label">Safety Status <i class="fas fa-shield-heart"></i></div>
          <div class="stat-num" style="color:<?= ($safetyMetrics['critical_alerts'] ?? 0) > 0 ? 'var(--warn)' : 'var(--success)' ?>">
            <?= $safetyMetrics['critical_alerts'] ?? 0 ?> critical
          </div>
          <div class="stat-trend warn"><?= $unsafeHikers ?> hikers marked unsafe</div>
          <div style="margin-top:8px; font-size:0.7rem;"><?= $safetyMetrics['high_alerts'] ?? 0 ?> high priority alerts</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-label">Revenue <i class="fas fa-coins"></i></div>
          <div class="stat-num"><?= formatCurrencyShort($revenueMetrics['total_revenue'] ?? 0) ?></div>
          <div class="stat-trend">Total all time</div>
          <div style="margin-top:8px; font-size:0.7rem;">💸 Pending DP: <?= formatCurrencyShort($revenueMetrics['pending_downpayments'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card">
          <div class="stat-label">Pending Tasks <i class="fas fa-clock"></i></div>
          <div class="stat-num"><?= $opsData['pending_treks'] ?? 0 ?></div>
          <div class="stat-trend">Upcoming treks</div>
          <div style="margin-top:8px; font-size:0.7rem;">💰 Overdue: <?= formatCurrencyShort($revenueMetrics['expired_payments'] ?? 0) ?></div>
        </div>
        
        <div class="stat-card">
          <div class="stat-label">Community <i class="fas fa-star"></i></div>
          <div class="stat-num"><?= $reviewSummary['avg_rating'] ?? 0 ?> ★</div>
          <div class="stat-trend">from <?= $reviewSummary['total_reviews'] ?? 0 ?> reviews</div>
          <div style="margin-top:8px; font-size:0.7rem;">📝 <?= $reviewSummary['pending_reviews'] ?? 0 ?> pending moderation</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-label">Completed Today <i class="fas fa-check-circle"></i></div>
          <div class="stat-num"><?= $opsData['completed_today'] ?? 0 ?></div>
          <div class="stat-trend">treks finished</div>
          <div style="margin-top:8px; font-size:0.7rem;">✅ All good</div>
        </div>
      </div>

<!-- CHARTS ROW -->
<div class="two-col">
  <div class="panel">
    <div class="panel-header">
      <span class="panel-title">Revenue Analytics</span>
      <div class="revenue-filter-group">
        <select id="revenueViewFilter" class="revenue-filter-select">
          <option value="daily">Weekly</option>
          <option value="monthly">Monthly</option>
          <option value="yearly">Yearly</option>
        </select>
        <select id="revenueYearFilter" class="revenue-filter-select">
          <?php for ($y = date('Y')-4; $y <= date('Y'); $y++): ?>
            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    <div class="chart-wrap">
  <canvas id="chartRevenue" width="100%" height="280" style="height: 280px; width: 100%;"></canvas>
</div>
    <div id="revenueInsights" style="margin-top:12px; padding:8px 12px; background:var(--paper); border-radius:8px; font-size:0.7rem;">
      <i class="fas fa-info-circle"></i> Total: ₱<?= number_format($totalWeekRevenue, 0) ?> · Peak: <?= $weekdays[array_search(max($dailyRevenue), $dailyRevenue)] ?>
    </div>
  </div>
  
  <div class="panel">
    <div class="panel-header">
      <span class="panel-title">Bookings Trend (Last 4 Weeks)</span>
      <button class="btn btn-ghost" id="refreshStatsBtn"><i class="fas fa-rotate-right"></i></button>
    </div>
    <div class="chart-wrap">
  <canvas id="chartActivity" width="100%" height="280" style="height: 280px; width: 100%;"></canvas>
</div>
  </div>
</div>

      <!-- MOUNTAIN POPULARITY & HOURLY CHECKINS ROW -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title"><i class="fas fa-mountain"></i> Mountain Popularity (Last 30 Days)</span>
          </div>
          <div class="chart-wrap" style="height:250px;"><canvas id="popularityChart"></canvas></div>
        </div>
        
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title"><i class="fas fa-clock"></i> Hourly Check-in Pattern (Today)</span>
          </div>
          <div class="chart-wrap" style="height:250px;"><canvas id="hourlyChart"></canvas></div>
          <div class="mini-stats" style="margin-top:12px;">
            <div class="mini-stat">
              <div class="mini-stat-value"><?= max($hourlyCheckins) ?></div>
              <div class="mini-stat-label">Peak Hour</div>
            </div>
            <div class="mini-stat">
              <div class="mini-stat-value"><?= array_sum($hourlyCheckins) ?></div>
              <div class="mini-stat-label">Total Check-ins</div>
            </div>
          </div>
        </div>
      </div>

      <!-- UPCOMING BOOKINGS -->
      <div class="full-span">
        <div class="section-header">
          <div class="section-title">Urgent & Upcoming Bookings (Next 7 Days)</div>
          <button class="section-action" id="viewAllBookings">View all →</button>
        </div>
        <div class="panel" style="padding:0 4px;">
          <table class="data-table">
            <thead>
              <tr><th>Booking#</th><th>Hiker</th><th>Mountain</th><th>Hike Date</th><th>Pax</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <?php if (empty($upcomingBookings)): ?>
                <tr><td colspan="7" style="text-align:center; padding:40px;">No upcoming bookings</td></tr>
              <?php else: ?>
                <?php foreach ($upcomingBookings as $booking): ?>
                  <tr style="<?= $booking['urgency'] === 'overdue' ? 'background:rgba(192,57,43,0.05);' : '' ?>">
                    <td><code><?= htmlspecialchars($booking['booking_number']) ?></code></td>
                    <td><?= htmlspecialchars($booking['hiker_name']) ?></td>
                    <td><?= htmlspecialchars($booking['mountain_name']) ?></td>
                    <td><?= date('M d', strtotime($booking['hike_date'])) ?></td>
                    <td><?= $booking['number_of_hikers'] ?></td>
                    <td><?= getUrgencyBadge($booking['urgency']) ?></td>
                    <td><?php if ($booking['urgency'] === 'overdue'): ?><span class="badge red">ACTION NEEDED</span><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TOP GUIDES & RECENT BOOKINGS -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title"><i class="fas fa-chalkboard-user"></i> Top Guides (Last 30 Days)</span>
          </div>
          <table class="data-table">
            <thead><tr><th>Guide</th><th>Treks</th><th>⭐ Rating</th><th>Completed</th></tr></thead>
            <tbody>
              <?php foreach ($topGuides as $guide): ?>
                <tr>
                  <td><?= htmlspecialchars($guide['name']) ?></td>
                  <td><?= $guide['total_treks'] ?? 0 ?></td>
                  <td><?= number_format($guide['avg_rating'] ?? 0, 1) ?> ★</td>
                  <td><?= $guide['completed_treks'] ?? 0 ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title"><i class="fas fa-receipt"></i> Recent Bookings</span>
          </div>
          <table class="data-table">
            <thead><tr><th>Hiker</th><th>Mountain</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentBookings as $booking): ?>
                <tr>
                  <td><?= htmlspecialchars($booking['hiker_name'] ?? 'Unknown') ?></td>
                  <td><?= htmlspecialchars($booking['mountain_name'] ?? 'Unknown') ?></td>
                  <td><?= date('M d', strtotime($booking['hike_date'] ?? $booking['created_at'])) ?></td>
                  <td><span class="badge <?= getStatusBadgeClass($booking['payment_status']) ?>"><?= ucfirst($booking['payment_status'] ?? 'Pending') ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ACTIVE ALERTS SECTION -->
      <?php if (!empty($activeAlerts)): ?>
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title"><i class="fas fa-bell"></i> Active Alerts</span>
          <button class="btn btn-ghost" onclick="window.location.href='/pages/admin/alerts.php'">Manage All</button>
        </div>
        <?php foreach ($activeAlerts as $alert): ?>
          <div style="padding:12px; background:var(--paper); border-radius:12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
            <div>
              <span class="badge red"><?= strtoupper($alert['severity']) ?></span>
              <strong><?= htmlspecialchars($alert['title']) ?></strong>
              <div style="font-size:0.7rem; color:var(--ink-4);">📍 <?= htmlspecialchars($alert['location']) ?> · <?= date('H:i', strtotime($alert['created_at'])) ?></div>
            </div>
            <button class="btn btn-ghost" onclick="acknowledgeAlert(<?= $alert['id'] ?>)">Acknowledge</button>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
const revenueData = <?= json_encode($dailyRevenue) ?>;
const weeklyBookingsData = <?= json_encode($weeklyBookings) ?>;
const weekdays = <?= json_encode($weekdays) ?>;
const weekLabels = <?= json_encode($weekLabels) ?>;
const mountainNames = <?= json_encode(array_column($popularMountains, 'name')) ?>;
const mountainBookings = <?= json_encode(array_column($popularMountains, 'total_bookings_30d')) ?>;
const hourlyData = <?= json_encode(array_values($hourlyCheckins)) ?>;

// Live clock
function updateDate() {
  const d = new Date();
  document.getElementById('liveDate').textContent = d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() + '  ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
updateDate(); 
setInterval(updateDate, 1000);

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if(item.dataset.href) window.location.href = item.dataset.href; });
});

// Charts
const charts = {};
function buildChart(id, type, data, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  if (charts[id]) charts[id].destroy();
  charts[id] = new Chart(ctx, { type, data, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, ...opts } });
}

// Revenue Chart
let currentRevenueChart = null;
function updateRevenueChart(labels, data, total, viewType) {
  const ctx = document.getElementById('chartRevenue');
  if (!ctx) return;
  if (currentRevenueChart) currentRevenueChart.destroy();
  
  currentRevenueChart = new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ data: data, backgroundColor: 'rgba(17,19,24,0.8)', borderRadius: 6 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { tooltip: { callbacks: { label: (ctx) => `₱${ctx.raw.toLocaleString()}` } } },
      scales: { y: { ticks: { callback: (val) => val >= 1000 ? `₱${(val/1000).toFixed(0)}k` : `₱${val}` } } }
    }
  });
  
  document.getElementById('revenueInsights').innerHTML = `<i class="fas fa-chart-line"></i> Total: ₱${total.toLocaleString()} · Peak: ${labels[data.indexOf(Math.max(...data))]}`;
}

// Initialize all charts
updateRevenueChart(weekdays, revenueData, <?= $totalWeekRevenue ?>, 'daily');
buildChart('chartActivity', 'line', { labels: weekLabels, datasets: [{ data: weeklyBookingsData, borderColor: '#111318', tension: 0.3, fill: true }] });
buildChart('popularityChart', 'bar', { labels: mountainNames, datasets: [{ data: mountainBookings, backgroundColor: '#d4af37', borderRadius: 6 }] });
buildChart('hourlyChart', 'bar', { labels: Array.from({length:24}, (_,i)=>`${i}:00`), datasets: [{ data: hourlyData, backgroundColor: (ctx) => ctx.raw === Math.max(...hourlyData) ? '#c0392b' : '#adb5bd', borderRadius: 4 }] });

// Revenue filter handlers - FIXED to prevent layout shift
document.getElementById('revenueViewFilter')?.addEventListener('change', async (e) => {
  const view = e.target.value;
  const yearFilter = document.getElementById('revenueYearFilter');
  
  // Enable/disable year filter (not hide/show)
  if (view === 'yearly' || view === 'monthly') {
    yearFilter.disabled = false;
    yearFilter.style.opacity = '1';
  } else {
    yearFilter.disabled = true;
    yearFilter.style.opacity = '0.5';
  }
  
  const year = yearFilter.value;
  const formData = new URLSearchParams();
  formData.append('action', 'get_revenue_data');
  formData.append('view', view);
  if (view === 'monthly' || view === 'yearly') {
    formData.append('year', year);
  }
  
  const response = await fetch(window.location.href, { 
    method: 'POST', 
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, 
    body: formData 
  });
  const data = await response.json();
  if (data) {
    updateRevenueChart(data.labels, data.data, data.total, view);
  }
});

// Initial state - disable year filter for daily view
document.getElementById('revenueYearFilter').disabled = true;
document.getElementById('revenueYearFilter').style.opacity = '0.5';

document.getElementById('revenueYearFilter')?.addEventListener('change', async (e) => {
  const view = document.getElementById('revenueViewFilter').value;
  const year = e.target.value;
  const formData = new URLSearchParams();
  formData.append('action', 'get_revenue_data');
  formData.append('view', view);
  formData.append('year', year);
  const response = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
  const data = await response.json();
  if (data) updateRevenueChart(data.labels, data.data, data.total, view);
});

document.getElementById('refreshStatsBtn')?.addEventListener('click', () => location.reload());
document.getElementById('viewAllBookings')?.addEventListener('click', () => window.location.href = '/pages/admin/hikers.php?tab=bookings-tab');

function acknowledgeAlert(alertId) {
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams({ action: 'acknowledge_alert', alert_id: alertId })
  }).then(() => location.reload()).catch(() => alert('Alert acknowledged'));
}


</script>

<!-- Simple working logout modal fallback -->
<div id="simpleLogoutModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 999999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 20px; width: 90%; max-width: 400px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; text-align: center;">
            <div style="width: 52px; height: 52px; background: #fee2e2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <i class="fas fa-right-from-bracket" style="font-size: 22px; color: #dc2626;"></i>
            </div>
            <h3 style="font-family: 'Inter', sans-serif; font-size: 1.15rem; font-weight: 600; margin: 0 0 8px;">Confirm Logout</h3>
            <p style="color: #5B6A7E; font-size: 0.83rem; margin: 0 0 20px;">Are you sure you want to log out?</p>
            <div style="display: flex; gap: 12px;">
                <button onclick="document.getElementById('simpleLogoutModal').style.display='none'" style="flex: 1; padding: 10px; border: 1.5px solid #E5E9EF; background: white; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 500; cursor: pointer;">Cancel</button>
                <a href="../login-and-signup/login.php" style="flex: 1; padding: 10px; background: #dc2626; color: white; border: none; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; display: block;">
                    <i class="fas fa-right-from-bracket"></i> Log Out
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Override the showLogoutModal function to use our simple modal
function showLogoutModal() {
    const modal = document.getElementById('simpleLogoutModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

// Also handle the existing modal if it has issues
document.addEventListener('DOMContentLoaded', function() {
    // Check if the original modal exists but isn't working
    const originalModal = document.getElementById('logoutModal');
    if (originalModal && originalModal.style.display === 'none') {
        console.log('Original logout modal found');
    }
});
</script>

<!-- Include Modals -->
<?php include_once __DIR__ . '/admin/profile-modal.php'; ?>
<?php include_once __DIR__ . '/includes/logout-modal.php'; ?>
<style>
  .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px; }
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px; }
  .full-span { margin-bottom: 32px; }
  .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
  .section-title { font-weight: 600; font-size: 0.9rem; }
  .section-action { font-size: 0.7rem; color: var(--ink-4); cursor: pointer; background: none; border: none; }
  .revenue-filter-group { display: flex; gap: 8px; align-items: center; }
  .revenue-filter-select { border: 1px solid var(--line); border-radius: 6px; padding: 4px 8px; font-size: 0.7rem; }
  .mini-stats { display: flex; gap: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
  .mini-stat { flex: 1; text-align: center; }
  .mini-stat-value { font-size: 1rem; font-weight: 700; }
  .mini-stat-label { font-size: 0.6rem; color: var(--ink-4); }
  
  /* FIX: Force fixed canvas sizes */
  .chart-wrap {
    position: relative;
    height: 280px !important;
    width: 100% !important;
  }
  
  .chart-wrap canvas {
    max-height: 280px !important;
    height: 280px !important;
    width: 100% !important;
  }
  
  .two-col .panel {
    min-height: auto;
  }
  
  @media (max-width: 1000px) { 
    .stat-grid { grid-template-columns: repeat(2, 1fr); } 
    .two-col { grid-template-columns: 1fr; } 
  }
  @media (max-width: 600px) { 
    .stat-grid { grid-template-columns: 1fr; } 
  }
</style>
</body>
</html>