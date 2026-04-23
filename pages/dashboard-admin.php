<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

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

// ========== RECENT BOOKINGS ==========
$stmt = $pdo->prepare("
    SELECT 
        'booking' as type,
        b.id,
        u.name as hiker_name,
        m.name as mountain_name,
        b.hike_date as date,
        b.payment_status as status,
        b.created_at
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mountains m ON b.mountain_id = m.id
    WHERE u.role = 'hiker'
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If not enough bookings, get from camping_bookings
if (count($recentBookings) < 5) {
    $stmt = $pdo->prepare("
        SELECT 
            'camping' as type,
            c.id,
            u.name as hiker_name,
            m.name as mountain_name,
            c.start_date as date,
            c.payment_status as status,
            c.created_at
        FROM camping_bookings c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN mountains m ON c.mountain_id = m.id
        WHERE u.role = 'hiker'
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $campingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <div class="logo-sub">wilderness intelligence</div>
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
      <form method="POST" action="/pages/modals/logout.php" style="margin:0;padding:0;display:block;">
        <button class="logout-btn" type="submit" style="width:100%;display:flex;align-items:center;gap:12px;padding:10px 16px;background:transparent;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.82rem;font-weight:400;color:#dc2626;cursor:pointer;">
          <i class="fas fa-right-from-bracket" style="width:16px;font-size:0.75rem;"></i> 
          Log Out
        </button>
      </form>
      <div class="sidebar-footer">
        <div class="status-dot"></div> 
        SYSTEM LIVE · V3
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-chart-line"></i> Dashboard</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user">
          <div class="avatar"><?= htmlspecialchars($adminInitial) ?></div>
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
            <span class="panel-title">Revenue — This Week</span>
            <span class="panel-badge"><?= formatCurrency($totalWeekRevenue) ?> total</span>
          </div>
          <div class="chart-wrap"><canvas id="chartRevenue"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Bookings / Week</span>
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

// Charts
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

// Revenue Chart
buildChart('chartRevenue', 'line', {
  labels: weekdays,
  datasets: [{ 
    data: revenueData, 
    borderColor: '#111318', 
    backgroundColor: 'rgba(17,19,24,0.04)', 
    tension: 0.4, 
    fill: true, 
    pointRadius: 3, 
    pointBackgroundColor: '#111318', 
    borderWidth: 1.5 
  }]
});

// Weekly Bookings Chart
buildChart('chartActivity', 'bar', {
  labels: weekLabels,
  datasets: [{ 
    data: weeklyBookingsData, 
    backgroundColor: 'rgba(17,19,24,0.12)', 
    borderRadius: 4 
  }]
});

// Button events
document.getElementById('refreshStatsBtn').addEventListener('click', () => {
  location.reload();
});

document.getElementById('viewAllBookings').addEventListener('click', () => {
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

document.querySelectorAll('.viewBookBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    const bookingId = btn.dataset.id;
    const hikerName = btn.dataset.name;
    alert(`📋 Booking details for ${hikerName}\nBooking ID: ${bookingId}`);
  });
});
</script>
</body>
</html>