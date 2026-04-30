<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// ========== HANDLE AJAX REQUESTS FOR DRILL-DOWN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'get_hourly_heatmap') {
        $mountain_id = $_POST['mountain_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM bookings
            WHERE mountain_id = ? AND payment_status = 'paid'
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ");
        $stmt->execute([$mountain_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    
    echo json_encode(['success' => false]);
    exit;
}

// ========== ADVANCED ANALYTICS QUERIES ==========

// 1. HOURLY DISTRIBUTION - When do hikers book?
$stmt = $pdo->prepare("
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as booking_count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM bookings), 1) as percentage
    FROM bookings
    WHERE payment_status = 'paid'
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC
");
$stmt->execute();
$hourlyDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Find peak booking hour
$peakHour = array_reduce($hourlyDistribution, function($carry, $item) {
    return (!$carry || $item['booking_count'] > $carry['booking_count']) ? $item : $carry;
}, null);

// 2. WEEKDAY DISTRIBUTION - Which days are busiest?
$stmt = $pdo->prepare("
    SELECT 
        DAYNAME(hike_date) as day_name,
        DAYOFWEEK(hike_date) as day_num,
        COUNT(*) as booking_count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM bookings WHERE hike_date IS NOT NULL), 1) as percentage
    FROM bookings
    WHERE hike_date IS NOT NULL AND payment_status = 'paid'
    GROUP BY DAYNAME(hike_date), DAYOFWEEK(hike_date)
    ORDER BY day_num ASC
");
$stmt->execute();
$weekdayDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Find busiest day
$busiestDay = array_reduce($weekdayDistribution, function($carry, $item) {
    return (!$carry || $item['booking_count'] > $carry['booking_count']) ? $item : $carry;
}, null);

// 3. MONTHLY SEASONALITY - Most popular months
$stmt = $pdo->prepare("
    SELECT 
        MONTHNAME(hike_date) as month_name,
        MONTH(hike_date) as month_num,
        COUNT(*) as booking_count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM bookings WHERE hike_date IS NOT NULL), 1) as percentage
    FROM bookings
    WHERE hike_date IS NOT NULL AND payment_status = 'paid'
    GROUP BY MONTHNAME(hike_date), MONTH(hike_date)
    ORDER BY month_num ASC
");
$stmt->execute();
$monthlyDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Find peak months (top 3)
$peakMonths = $monthlyDistribution;
usort($peakMonths, function($a, $b) {
    return $b['booking_count'] - $a['booking_count'];
});
$topMonths = array_slice($peakMonths, 0, 3);

// 4. MOUNTAIN CONGESTION RANKING
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.name,
        m.crowdLevel,
        COUNT(b.id) as total_bookings,
        ROUND(AVG(b.number_of_hikers), 1) as avg_group_size,
        COUNT(DISTINCT DATE(b.hike_date)) as active_days,
        ROUND(COUNT(b.id) / NULLIF(COUNT(DISTINCT DATE(b.hike_date)), 0), 1) as avg_daily_visitors
    FROM mountains m
    LEFT JOIN bookings b ON m.id = b.mountain_id AND b.payment_status = 'paid' AND b.hike_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY m.id, m.name, m.crowdLevel
    ORDER BY total_bookings DESC
");
$stmt->execute();
$mountainCongestion = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. CANCELLATION/NO-SHOW RATES
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status IN ('cancelled', 'expired') THEN 1 END) as cancelled,
        COUNT(*) as total
    FROM bookings
");
$stmt->execute();
$cancellationStats = $stmt->fetch(PDO::FETCH_ASSOC);
$cancellationRate = $cancellationStats['total'] > 0 ? round(($cancellationStats['cancelled'] / $cancellationStats['total']) * 100, 1) : 0;

// 6. RETURNING HIKER RATE
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT user_id) as total_hikers,
        COUNT(DISTINCT CASE WHEN user_id IN (
            SELECT user_id FROM bookings GROUP BY user_id HAVING COUNT(*) > 1
        ) THEN user_id END) as returning_hikers
    FROM bookings
    WHERE payment_status = 'paid'
");
$stmt->execute();
$returnStats = $stmt->fetch(PDO::FETCH_ASSOC);
$returnRate = $returnStats['total_hikers'] > 0 ? round(($returnStats['returning_hikers'] / $returnStats['total_hikers']) * 100, 1) : 0;

// 7. LEAD TIME
$stmt = $pdo->prepare("
    SELECT 
        AVG(DATEDIFF(hike_date, created_at)) as avg_lead_days
    FROM bookings
    WHERE hike_date IS NOT NULL AND created_at IS NOT NULL AND payment_status = 'paid'
");
$stmt->execute();
$leadTimeStats = $stmt->fetch(PDO::FETCH_ASSOC);

// 8. REVENUE BY MOUNTAIN
$stmt = $pdo->prepare("
    SELECT 
        m.name,
        COALESCE(SUM(b.total_amount), 0) as revenue
    FROM mountains m
    LEFT JOIN bookings b ON m.id = b.mountain_id AND b.payment_status = 'paid'
    GROUP BY m.id, m.name
    ORDER BY revenue DESC
");
$stmt->execute();
$revenueByMountain = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. TREND - Last 12 months
$monthlyTrend = [];
for ($i = 11; $i >= 0; $i--) {
    $monthDate = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as bookings
        FROM bookings
        WHERE payment_status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$monthDate]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $monthlyTrend[] = [
        'month' => $monthName,
        'bookings' => $data['bookings'] ?? 0
    ];
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Analytics Intelligence</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

  <link rel="stylesheet" href="shared.css">
  <style>
    /* Analytics-specific styles */
    .insight-panel {
      background: white;
      border-radius: 20px;
      padding: 20px;
      border: 1px solid var(--line);
      transition: all 0.2s ease;
    }
    .insight-panel:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    }
    .insight-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--line);
    }
    .insight-icon {
      width: 32px;
      height: 32px;
      background: var(--paper);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--ink);
    }
    .insight-title {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      color: var(--ink-4);
    }
    .insight-value {
      font-size: 1.8rem;
      font-weight: 700;
      font-family: 'DM Mono', monospace;
      color: var(--ink);
      line-height: 1.2;
      margin-bottom: 4px;
    }
    .insight-label {
      font-size: 0.7rem;
      color: var(--ink-4);
      margin-bottom: 12px;
    }
    .insight-divider {
      height: 1px;
      background: var(--line);
      margin: 12px 0;
    }
    .action-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 0;
      font-size: 0.75rem;
      color: var(--ink-2);
      border-bottom: 1px solid var(--line);
    }
    .action-item:last-child {
      border-bottom: none;
    }
    .action-marker {
      width: 20px;
      height: 20px;
      background: rgba(201,168,76,0.12);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.6rem;
      color: var(--gold);
      flex-shrink: 0;
    }
    .stat-highlight {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--ink);
    }
    .stat-subtle {
      font-size: 0.7rem;
      color: var(--ink-4);
    }
    .recommendation-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .rec-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--line);
      font-size: 0.8rem;
    }
    .rec-item:last-child {
      border-bottom: none;
    }
    .rec-bullet {
      width: 20px;
      height: 20px;
      background: var(--paper);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 0.65rem;
      color: var(--gold);
    }
    .stats-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 8px;
    }
    .badge-stat {
      background: var(--paper);
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.6rem;
      font-weight: 600;
      color: var(--ink-3);
    }
    .metric-card {
      background: white;
      border-radius: 16px;
      padding: 16px;
      border: 1px solid var(--line);
      text-align: center;
    }
    .metric-value {
      font-size: 1.6rem;
      font-weight: 700;
      font-family: 'DM Mono', monospace;
    }
    .metric-label {
      font-size: 0.65rem;
      color: var(--ink-4);
      margin-top: 4px;
    }
    .insight-grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 32px;
    }
    .insight-grid-4 {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 32px;
    }
    @media (max-width: 1000px) {
      .insight-grid-3, .insight-grid-4 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .insight-grid-3, .insight-grid-4 { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body data-page="analytics">
<div class="app">

  <aside class="sidebar">
    <div>
      <div class="logo">
        <div class="logo-wordmark">
          <div class="logo-icon">
            <svg viewBox="0 0 28 28" fill="none">
              <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#111318" opacity="0.9"/>
              <path d="M14 16L18 8L24 22H14V16Z" fill="#111318" opacity="0.35"/>
            </svg>
          </div>
          LAKBAY
        </div>
        <div class="logo-sub">wilderness: Silence beneath steps</div>
      </div>
      <ul class="nav-list">
        <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item active" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
      </ul>
    </div>
    <div>
      <button class="logout-btn" onclick="showLogoutModal()" style="width:100%;display:flex;align-items:center;gap:12px;padding:10px 16px;background:transparent;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.82rem;font-weight:400;color:#dc2626;cursor:pointer;">
        <i class="fas fa-right-from-bracket"></i> Log Out
      </button>
      <div class="sidebar-footer">TEAM AURIX</div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-chart-simple"></i> Analytics Intelligence</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user">
          <div class="avatar"><?= htmlspecialchars($adminInitial) ?></div>
          <?= htmlspecialchars($adminName) ?>
        </div>
      </div>
    </div>

    <div class="content">

      <!-- KEY METRICS ROW -->
      <div class="insight-grid-4">
        <div class="metric-card">
          <div class="metric-value"><?= $peakHour ? date('g:i A', strtotime($peakHour['hour'] . ':00')) : 'N/A' ?></div>
          <div class="metric-label">Peak Booking Hour</div>
          <div class="stat-subtle" style="margin-top: 6px;"><?= $peakHour ? $peakHour['percentage'] . '% of daily bookings' : '' ?></div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?= $busiestDay ? $busiestDay['day_name'] : 'N/A' ?></div>
          <div class="metric-label">Busiest Day</div>
          <div class="stat-subtle" style="margin-top: 6px;"><?= $busiestDay ? $busiestDay['percentage'] . '% of weekly bookings' : '' ?></div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?= !empty($topMonths) ? $topMonths[0]['month_name'] : 'N/A' ?></div>
          <div class="metric-label">Peak Season</div>
          <div class="stat-subtle" style="margin-top: 6px;"><?= !empty($topMonths) ? $topMonths[0]['percentage'] . '% of annual bookings' : '' ?></div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?= round($leadTimeStats['avg_lead_days'] ?? 0) ?> days</div>
          <div class="metric-label">Average Lead Time</div>
          <div class="stat-subtle" style="margin-top: 6px;">How far ahead hikers book</div>
        </div>
      </div>

      <!-- HOURLY & WEEKDAY CHARTS -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Booking Hours Distribution</span>
            <span class="panel-badge">Peak: <?= $peakHour ? date('g:i A', strtotime($peakHour['hour'] . ':00')) : 'N/A' ?></span>
          </div>
          <div class="chart-wrap" style="height: 220px;"><canvas id="hourlyChart"></canvas></div>
          <div class="stat-subtle" style="margin-top: 12px; text-align: center;">
            Most bookings occur around <?= $peakHour ? date('g:i A', strtotime($peakHour['hour'] . ':00')) : 'peak hours' ?>
          </div>
        </div>
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Busiest Days of Week</span>
            <span class="panel-badge">Peak: <?= $busiestDay ? $busiestDay['day_name'] : 'N/A' ?></span>
          </div>
          <div class="chart-wrap" style="height: 220px;"><canvas id="weekdayChart"></canvas></div>
          <div class="stat-subtle" style="margin-top: 12px; text-align: center;">
            <?= $busiestDay ? $busiestDay['day_name'] . 's see the highest volume' : '' ?>
          </div>
        </div>
      </div>

      <!-- SEASONALITY CHART -->
      <div class="panel" style="margin-bottom: 32px;">
        <div class="panel-header">
          <span class="panel-title">Seasonality — Monthly Booking Trends</span>
          <span class="panel-badge">Peak: <?= !empty($topMonths) ? $topMonths[0]['month_name'] : 'N/A' ?></span>
        </div>
        <div class="chart-wrap" style="height: 240px;"><canvas id="monthlyChart"></canvas></div>
        <div class="stat-subtle" style="margin-top: 12px; text-align: center;">
          Peak season runs through <?= !empty($topMonths) ? implode(', ', array_column(array_slice($topMonths, 0, 2), 'month_name')) : 'peak months' ?>
        </div>
      </div>

      <!-- MOUNTAIN CONGESTION TABLE -->
      <div class="panel" style="margin-bottom: 32px;">
        <div class="panel-header">
          <span class="panel-title">Mountain Congestion Analysis</span>
          <span class="panel-badge">Last 90 days</span>
          <button class="btn btn-ghost" id="refreshBtn" style="margin-left: auto;"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
        <div style="overflow-x: auto;">
          <table class="data-table">
            <thead>
              <tr><th>Mountain</th><th>Bookings</th><th>Daily Avg</th><th>Group Size</th><th>Crowd Level</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($mountainCongestion as $m): 
                  $avgDaily = $m['avg_daily_visitors'] ?? 0;
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                <td><?= $m['total_bookings'] ?></td>
                <td><?= $avgDaily ?> visitors/day</td>
                <td><?= $m['avg_group_size'] ?> hikers</td>
                <td><span class="badge <?= $m['crowdLevel'] === 'High' ? 'red' : ($m['crowdLevel'] === 'Moderate' ? 'amber' : 'green') ?>"><?= htmlspecialchars($m['crowdLevel'] ?? 'Unknown') ?></span></td>
                <td><?= $avgDaily > 40 ? '<span class="warning-badge">High Traffic</span>' : '<span class="success-badge">Normal Flow</span>' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- THREE INSIGHT CARDS -->
      <div class="insight-grid-3">
        <!-- Congestion Management -->
        <div class="insight-panel">
          <div class="insight-header">
            <div class="insight-icon"><i class="fas fa-clock"></i></div>
            <div>
              <div class="insight-title">CONGESTION MANAGEMENT</div>
            </div>
          </div>
          <div class="stats-row">
            <span class="stat-highlight">Peak hour</span>
            <span class="badge-stat"><?= $peakHour ? date('g:i A', strtotime($peakHour['hour'] . ':00')) . ' (' . $peakHour['percentage'] . '%)' : 'N/A' ?></span>
          </div>
          <div class="stats-row">
            <span class="stat-highlight">Busiest day</span>
            <span class="badge-stat"><?= $busiestDay ? $busiestDay['day_name'] . 's (' . $busiestDay['percentage'] . '%)' : 'N/A' ?></span>
          </div>
          <div class="insight-divider"></div>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-users"></i></div>
            <span>Add <?= $busiestDay ? round($busiestDay['booking_count'] * 0.15) : 2 ?> extra guides on <?= $busiestDay ? $busiestDay['day_name'] : 'peak' ?> days</span>
          </div>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-clock"></i></div>
            <span>Implement staggered start times (6AM, 8AM, 10AM)</span>
          </div>
        </div>

        <!-- Revenue Optimization -->
        <div class="insight-panel">
          <div class="insight-header">
            <div class="insight-icon"><i class="fas fa-chart-line"></i></div>
            <div>
              <div class="insight-title">REVENUE OPTIMIZATION</div>
            </div>
          </div>
          <div class="stats-row">
            <span class="stat-highlight">Weekend premium</span>
            <span class="badge-stat"><?= round(($busiestDay && $busiestDay['percentage'] > 20) ? $busiestDay['percentage'] * 2 : 40) ?>% more traffic</span>
          </div>
          <div class="stats-row">
            <span class="stat-highlight">Lead time</span>
            <span class="badge-stat"><?= round($leadTimeStats['avg_lead_days'] ?? 0) ?> days average</span>
          </div>
          <div class="insight-divider"></div>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-tag"></i></div>
            <span>Offer early-bird discounts for bookings 14+ days in advance</span>
          </div>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-chart-simple"></i></div>
            <span>Dynamic pricing for weekend and holiday slots</span>
          </div>
        </div>

        <!-- Hiker Behavior -->
        <div class="insight-panel">
          <div class="insight-header">
            <div class="insight-icon"><i class="fas fa-users"></i></div>
            <div>
              <div class="insight-title">HIKER BEHAVIOR</div>
            </div>
          </div>
          <div class="stats-row">
            <span class="stat-highlight">Return rate</span>
            <span class="badge-stat"><?= $returnRate ?>% of hikers return</span>
          </div>
          <div class="stats-row">
            <span class="stat-highlight">Cancellation rate</span>
            <span class="badge-stat"><?= $cancellationRate ?>%</span>
          </div>
          <div class="insight-divider"></div>
          <?php if ($returnRate < 30): ?>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-gem"></i></div>
            <span>Implement loyalty program (free guide on 5th trek)</span>
          </div>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-envelope"></i></div>
            <span>Send post-hike surveys with discount codes</span>
          </div>
          <?php else: ?>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-star"></i></div>
            <span>Great retention rate — consider referral rewards program</span>
          </div>
          <div class="action-item">
            <div class="action-marker"><i class="fas fa-share-alt"></i></div>
            <span>Launch ambassador program for frequent hikers</span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- REVENUE & TREND CHARTS -->
      <div class="two-col" style="margin-bottom: 32px;">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Revenue by Mountain</span>
          </div>
          <div class="chart-wrap" style="height: 220px;"><canvas id="revenueMtnChart"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">12-Month Booking Trend</span>
          </div>
          <div class="chart-wrap" style="height: 220px;"><canvas id="trendChart"></canvas></div>
        </div>
      </div>

      <!-- ACTIONABLE RECOMMENDATIONS -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Actionable Recommendations</span>
          <span class="panel-badge">Priority Actions</span>
        </div>
        <div class="recommendation-list">
          <?php 
          $recommendations = [];
          if ($peakHour && $peakHour['booking_count'] > 0) {
              $recommendations[] = "Schedule additional staff during " . date('g:i A', strtotime($peakHour['hour'] . ':00')) . " peak booking hour";
          }
          if ($busiestDay && $busiestDay['booking_count'] > 30) {
              $recommendations[] = "Deploy " . round($busiestDay['booking_count'] / 25) . " extra guides on {$busiestDay['day_name']}s";
          }
          if (!empty($topMonths)) {
              $recommendations[] = "Prepare surge capacity for {$topMonths[0]['month_name']} ({$topMonths[0]['percentage']}% of annual traffic)";
          }
          if ($cancellationRate > 10) {
              $recommendations[] = "Reduce {$cancellationRate}% cancellation rate with reminder emails 48 hours before hike";
          }
          if (round($leadTimeStats['avg_lead_days'] ?? 0) < 5) {
              $recommendations[] = "Encourage early booking with 10% discount for reservations 14+ days ahead";
          }
          if (empty($recommendations)) {
              $recommendations[] = "All systems optimal. Continue monitoring trends for proactive adjustments.";
          }
          foreach ($recommendations as $idx => $rec) {
              echo '<div class="rec-item"><div class="rec-bullet">' . ($idx + 1) . '</div><span>' . $rec . '</span></div>';
          }
          ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Chart Data
const hourlyLabels = <?= json_encode(array_column($hourlyDistribution, 'hour')) ?>;
const hourlyData = <?= json_encode(array_column($hourlyDistribution, 'booking_count')) ?>;
const weekdayLabels = <?= json_encode(array_column($weekdayDistribution, 'day_name')) ?>;
const weekdayData = <?= json_encode(array_column($weekdayDistribution, 'booking_count')) ?>;
const monthlyLabels = <?= json_encode(array_column($monthlyDistribution, 'month_name')) ?>;
const monthlyData = <?= json_encode(array_column($monthlyDistribution, 'booking_count')) ?>;
const revenueMtnLabels = <?= json_encode(array_column($revenueByMountain, 'name')) ?>;
const revenueMtnData = <?= json_encode(array_column($revenueByMountain, 'revenue')) ?>;
const trendLabels = <?= json_encode(array_column($monthlyTrend, 'month')) ?>;
const trendBookings = <?= json_encode(array_column($monthlyTrend, 'bookings')) ?>;

const charts = {};

function buildChart(id, type, labels, data, customOptions = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  if (charts[id]) charts[id].destroy();
  
  const isLine = type === 'line';
  charts[id] = new Chart(ctx, {
    type: type,
    data: {
      labels: labels,
      datasets: [{
        data: data,
        backgroundColor: isLine ? 'rgba(17,19,24,0.04)' : 'rgba(17,19,24,0.85)',
        borderColor: '#111318',
        borderWidth: isLine ? 2 : 0,
        tension: isLine ? 0.4 : 0,
        fill: isLine ? true : false,
        pointRadius: isLine ? 3 : 0,
        pointHoverRadius: isLine ? 5 : 0,
        pointBackgroundColor: isLine ? '#111318' : 'transparent',
        borderRadius: isLine ? 0 : 6,
        barPercentage: isLine ? undefined : 0.7
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              let value = context.raw;
              if (id === 'revenueMtnChart') return '₱' + value.toLocaleString();
              return value + ' bookings';
            }
          }
        }
      },
      scales: {
        y: { 
          beginAtZero: true, 
          grid: { display: true, color: 'rgba(0,0,0,0.05)' },
          ticks: { font: { size: 10 }, stepSize: 1, callback: (val) => id === 'revenueMtnChart' ? '₱' + (val/1000).toFixed(0) + 'k' : val }
        },
        x: { 
          grid: { display: false }, 
          ticks: { font: { size: 9 }, rotation: id === 'monthlyChart' ? 45 : 0 }
        }
      },
      ...customOptions
    }
  });
}

// Initialize charts with curved line for monthly
buildChart('hourlyChart', 'bar', hourlyLabels.map(h => h + ':00'), hourlyData);
buildChart('weekdayChart', 'bar', weekdayLabels, weekdayData);
buildChart('monthlyChart', 'line', monthlyLabels, monthlyData);
buildChart('revenueMtnChart', 'bar', revenueMtnLabels, revenueMtnData);
buildChart('trendChart', 'line', trendLabels, trendBookings);

// Live clock
function updateDate() {
  const d = new Date();
  const dateElem = document.getElementById('liveDate');
  if (dateElem) {
    dateElem.textContent = d.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' }).toUpperCase() +
      '  ' + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
  }
}
updateDate();
setInterval(updateDate, 1000);

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if(item.dataset.href) window.location.href = item.dataset.href; });
});

document.getElementById('refreshBtn')?.addEventListener('click', () => location.reload());

// Profile modal
document.addEventListener('DOMContentLoaded', function() {
  const topbarUser = document.querySelector('.topbar-user');
  if (topbarUser) {
    topbarUser.addEventListener('click', function(e) {
      e.preventDefault();
      if (typeof openProfileModal === 'function') openProfileModal();
      else alert('Profile modal not loaded');
    });
  }
});
</script>

<?php 
$modalPath = __DIR__ . '/profile-modal.php';
if (file_exists($modalPath)) include_once $modalPath;
$logoutPath = __DIR__ . '/../../includes/logout-modal.php';
if (file_exists($logoutPath)) include_once $logoutPath;
?>
</body>
</html>