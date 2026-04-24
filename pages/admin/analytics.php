<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// ========== STAT CARDS DATA ==========

// Visitor Growth (compare this month vs same month last year)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT user_id) as current_month
    FROM (
        SELECT user_id FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
        UNION
        SELECT user_id FROM camping_bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ) as current
");
$stmt->execute();
$currentMonthVisitors = $stmt->fetch(PDO::FETCH_ASSOC)['current_month'] ?? 0;

$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT user_id) as last_year_month
    FROM (
        SELECT user_id FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)
        UNION
        SELECT user_id FROM camping_bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)
    ) as last_year
");
$stmt->execute();
$lastYearMonthVisitors = $stmt->fetch(PDO::FETCH_ASSOC)['last_year_month'] ?? 1;

$visitorGrowth = $lastYearMonthVisitors > 0 ? round((($currentMonthVisitors - $lastYearMonthVisitors) / $lastYearMonthVisitors) * 100) : 0;
$growthIcon = $visitorGrowth >= 0 ? '↑' : '↓';
$growthClass = $visitorGrowth >= 0 ? 'stat-trend' : 'stat-trend warn';

// Top Mountain (most booked mountain)
$stmt = $pdo->prepare("
    SELECT m.name, COUNT(*) as booking_count
    FROM (
        SELECT mountain_id FROM bookings
        UNION ALL
        SELECT mountain_id FROM camping_bookings
    ) as all_bookings
    LEFT JOIN mountains m ON all_bookings.mountain_id = m.id
    GROUP BY all_bookings.mountain_id, m.name
    ORDER BY booking_count DESC
    LIMIT 1
");
$stmt->execute();
$topMountain = $stmt->fetch(PDO::FETCH_ASSOC);

$topMountainName = $topMountain['name'] ?? 'No data';
$topMountainCount = $topMountain['booking_count'] ?? 0;

// Calculate average daily visitors for top mountain
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) / 30 as avg_daily
    FROM (
        SELECT mountain_id FROM bookings WHERE mountain_id = (SELECT mountain_id FROM bookings GROUP BY mountain_id ORDER BY COUNT(*) DESC LIMIT 1)
        UNION ALL
        SELECT mountain_id FROM camping_bookings WHERE mountain_id = (SELECT mountain_id FROM bookings GROUP BY mountain_id ORDER BY COUNT(*) DESC LIMIT 1)
    ) as top_mountain_bookings
");
$stmt->execute();
$avgDaily = $stmt->fetch(PDO::FETCH_ASSOC)['avg_daily'] ?? 0;

// Guide Rating - Fixed GROUP BY issue
$stmt = $pdo->prepare("
    SELECT 
        AVG(g.rating) as avg_rating
    FROM guides g
    WHERE g.rating IS NOT NULL AND g.rating > 0
");
$stmt->execute();
$guideData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get top guide by rating separately (to avoid GROUP BY issue)
$stmt = $pdo->prepare("
    SELECT u.name, g.rating
    FROM guides g
    LEFT JOIN users u ON g.user_id = u.id
    WHERE g.rating IS NOT NULL AND g.rating > 0
    ORDER BY g.rating DESC
    LIMIT 1
");
$stmt->execute();
$topGuideData = $stmt->fetch(PDO::FETCH_ASSOC);

// If no ratings exist, provide default values
if ($guideData['avg_rating']) {
    $avgGuideRating = number_format($guideData['avg_rating'], 1);
    $topGuide = $topGuideData ? $topGuideData['name'] . ' (' . $topGuideData['rating'] . '★)' : 'No guides rated yet';
} else {
    $avgGuideRating = 'N/A';
    $topGuide = 'No ratings yet';
}

// ========== CHART DATA - Visitor Trend (6 months) ==========
$months = [];
$visitorCounts = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as count
        FROM (
            SELECT user_id FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL ? MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL ? MONTH)
            UNION
            SELECT user_id FROM camping_bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL ? MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL ? MONTH)
        ) as monthly_visitors
    ");
    $stmt->execute([$i, $i, $i, $i]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $visitorCounts[] = $result['count'] ?? 0;
}

// ========== CHART DATA - Monthly Revenue (6 months) ==========
$revenueMonths = [];
$revenueAmounts = [];
for ($i = 5; $i >= 0; $i--) {
    $revenueMonths[] = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL ? MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL ? MONTH)), 0) +
            COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL ? MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL ? MONTH)), 0)
            as total
    ");
    $stmt->execute([$i, $i, $i, $i]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $revenueAmounts[] = $result['total'] ?? 0;
}

// ========== DYNAMIC INSIGHTS ==========
$insights = [];

// 1. Congestion insight - Find peak times/days
$stmt = $pdo->prepare("
    SELECT 
        m.name as mountain_name,
        DAYNAME(b.hike_date) as day_of_week,
        COUNT(*) as booking_count
    FROM bookings b
    LEFT JOIN mountains m ON b.mountain_id = m.id
    WHERE b.hike_date IS NOT NULL
    GROUP BY b.mountain_id, m.name, DAYNAME(b.hike_date)
    ORDER BY booking_count DESC
    LIMIT 1
");
$stmt->execute();
$peakTime = $stmt->fetch(PDO::FETCH_ASSOC);

if ($peakTime && $peakTime['mountain_name']) {
    $insights['congestion'] = "High traffic at {$peakTime['mountain_name']} every {$peakTime['day_of_week']}. Consider implementing slot limits and additional guide deployment during peak hours.";
} else {
    $insights['congestion'] = "Monitor booking patterns to identify peak congestion periods. Consider implementing a booking cap for popular mountains.";
}

// 2. Seasonality insight - Find peak months
$stmt = $pdo->prepare("
    SELECT 
        MONTHNAME(hike_date) as month_name,
        COUNT(*) as booking_count
    FROM bookings
    WHERE hike_date IS NOT NULL
    GROUP BY MONTH(hike_date), MONTHNAME(hike_date)
    ORDER BY booking_count DESC
    LIMIT 2
");
$stmt->execute();
$peakMonths = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($peakMonths) >= 2) {
    $insights['seasonality'] = "Peak season occurs during {$peakMonths[0]['month_name']} and {$peakMonths[1]['month_name']}. Prepare surge permits, additional guides, and coordinate with local authorities for crowd management.";
} else {
    $insights['seasonality'] = "March to May shows increased booking activity. Prepare for seasonal surge with additional resources and special permits.";
}

// 3. Revenue insight - Weekend vs Weekday revenue
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN DAYOFWEEK(hike_date) IN (1, 7) THEN total_amount ELSE 0 END) as weekend_revenue,
        SUM(CASE WHEN DAYOFWEEK(hike_date) NOT IN (1, 7) THEN total_amount ELSE 0 END) as weekday_revenue
    FROM bookings
    WHERE payment_status = 'paid'
");
$stmt->execute();
$revenueSplit = $stmt->fetch(PDO::FETCH_ASSOC);

$totalRevenue = ($revenueSplit['weekend_revenue'] ?? 0) + ($revenueSplit['weekday_revenue'] ?? 0);
$weekendPercent = $totalRevenue > 0 ? round((($revenueSplit['weekend_revenue'] ?? 0) / $totalRevenue) * 100) : 62;

$insights['revenue'] = "Weekend bookings account for {$weekendPercent}% of total revenue. Consider implementing dynamic weekend pricing or creating special weekend packages to maximize revenue.";

// 4. Additional insights based on data
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_hikers FROM users WHERE role = 'hiker'
");
$stmt->execute();
$totalHikers = $stmt->fetch(PDO::FETCH_ASSOC)['total_hikers'] ?? 0;

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id) as returning_hikers
    FROM (
        SELECT user_id FROM bookings GROUP BY user_id HAVING COUNT(*) > 1
        UNION
        SELECT user_id FROM camping_bookings GROUP BY user_id HAVING COUNT(*) > 1
    ) as returning
");
$stmt->execute();
$returningHikers = $stmt->fetch(PDO::FETCH_ASSOC)['returning_hikers'] ?? 0;

$returnRate = $totalHikers > 0 ? round(($returningHikers / $totalHikers) * 100) : 0;

// Get counts for day hike vs camping
$stmt = $pdo->prepare("SELECT COUNT(*) as day_hike FROM bookings");
$stmt->execute();
$dayHike = $stmt->fetch(PDO::FETCH_ASSOC)['day_hike'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as camping FROM camping_bookings");
$stmt->execute();
$camping = $stmt->fetch(PDO::FETCH_ASSOC)['camping'] ?? 0;

$totalBookings = $dayHike + $camping;
$dayHikePercent = $totalBookings > 0 ? round(($dayHike / $totalBookings) * 100) : 70;

// Format currency function
function formatCurrency($amount) {
    return '₱' . number_format($amount, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Analytics</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="shared.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
  <style>
    .insight-card {
      padding: 14px;
      background: var(--paper);
      border-radius: 8px;
      transition: all 0.2s;
    }
    .insight-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .insight-title {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--ink-3);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .insight-title i {
      font-size: 0.7rem;
    }
    .insight-text {
      font-size: 0.8rem;
      color: var(--ink-2);
      line-height: 1.4;
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
      <div class="page-heading"><i class="fas fa-chart-simple"></i> Analytics</div>
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
      <div class="analytics-row">
        <div class="stat-card">
          <div class="stat-label">Visitor Growth <?= $visitorGrowth >= 0 ? '<i class="fas fa-chart-line"></i>' : '<i class="fas fa-chart-line" style="color:var(--warn);"></i>' ?></div>
          <div class="stat-num"><?= $visitorGrowth >= 0 ? '+' : '' ?><?= $visitorGrowth ?>%</div>
          <div class="<?= $growthClass ?>">
            <?= $growthIcon ?> YTD vs prior year
          </div>
          <div class="stat-sub" style="margin-top: 6px;"><?= $currentMonthVisitors ?> unique visitors this month</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Top Mountain</div>
          <div class="stat-num" style="font-size:1.3rem;font-weight:700;"><?= htmlspecialchars($topMountainName) ?></div>
          <div class="stat-sub">~<?= round($avgDaily) ?> avg visitors/day</div>
          <div class="stat-sub" style="margin-top: 4px;"><?= $topMountainCount ?> total bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Guide Rating <i class="fas fa-star" style="color: #f5b042;"></i></div>
          <div class="stat-num"><?= $avgGuideRating ?></div>
          <div class="stat-sub">Top: <?= htmlspecialchars($topGuide) ?></div>
          <div class="stat-sub" style="margin-top: 4px;"><?= $returnRate ?>% hiker return rate</div>
        </div>
      </div>

      <!-- CHARTS -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Visitor Trend — 6 Months</span>
            <button class="btn btn-ghost" id="refreshAnalyticsBtn"><i class="fas fa-rotate-right"></i> Refresh</button>
          </div>
          <div class="chart-wrap" style="height:150px"><canvas id="chartVisitors"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Monthly Revenue</span>
          </div>
          <div class="chart-wrap" style="height:150px"><canvas id="chartRevMonth"></canvas></div>
        </div>
      </div>

      <!-- KEY INSIGHTS -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Key Insights</span>
          <span class="panel-badge">Data-driven</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;font-size:0.8rem;color:var(--ink-2);">
          <div class="insight-card">
            <div class="insight-title">
              <i class="fas fa-clock"></i> Congestion Management
            </div>
            <div class="insight-text"><?= htmlspecialchars($insights['congestion']) ?></div>
          </div>
          <div class="insight-card">
            <div class="insight-title">
              <i class="fas fa-calendar-alt"></i> Seasonality Patterns
            </div>
            <div class="insight-text"><?= htmlspecialchars($insights['seasonality']) ?></div>
          </div>
          <div class="insight-card">
            <div class="insight-title">
              <i class="fas fa-chart-line"></i> Revenue Optimization
            </div>
            <div class="insight-text"><?= htmlspecialchars($insights['revenue']) ?></div>
          </div>
        </div>
        
        <!-- Additional Insights Row -->
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-top:16px;">
          <div class="insight-card">
            <div class="insight-title">
              <i class="fas fa-users"></i> Hiker Retention
            </div>
            <div class="insight-text">
              <?= $returningHikers ?> out of <?= $totalHikers ?> hikers have booked multiple trips (<?= $returnRate ?>% return rate). 
              Consider implementing a loyalty program to increase retention.
            </div>
          </div>
          <div class="insight-card">
            <div class="insight-title">
              <i class="fas fa-tent"></i> Camping vs Day Hike
            </div>
            <div class="insight-text">
              Day hikes account for <?= $dayHikePercent ?>% of total bookings (<?= $dayHike ?> day hikes, <?= $camping ?> camping trips). 
              Consider promoting camping experiences for extended stays and additional revenue.
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
const visitorData = <?php echo json_encode($visitorCounts); ?>;
const revenueData = <?php echo json_encode($revenueAmounts); ?>;
const monthLabels = <?php echo json_encode($months); ?>;
const revenueMonthLabels = <?php echo json_encode($revenueMonths); ?>;

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

// Build charts with real data
function buildChart(id, type, data, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  
  // Destroy existing chart if it exists
  if (window[id + 'Chart']) {
    window[id + 'Chart'].destroy();
  }
  
  window[id + 'Chart'] = new Chart(ctx, {
    type, data,
    options: {
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { 
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              let value = context.raw;
              if (id === 'chartRevMonth') {
                return '₱' + value.toLocaleString();
              }
              return value + ' visitors';
            }
          }
        }
      },
      scales: {
        x: { 
          grid:{ display:false }, 
          ticks:{ font:{ family:"'Inter'", size:10 }, color:'#9098a6' } 
        },
        y: { display: false }
      },
      ...opts
    }
  });
}

// Visitor Trend Chart
buildChart('chartVisitors', 'line', {
  labels: monthLabels,
  datasets: [{ 
    label: 'Unique Visitors',
    data: visitorData, 
    borderColor: '#4a6741', 
    backgroundColor: 'rgba(74,103,65,0.06)', 
    tension: 0.4, 
    fill: true, 
    pointRadius: 3, 
    pointBackgroundColor: '#4a6741', 
    borderWidth: 1.5 
  }]
});

// Revenue Chart
buildChart('chartRevMonth', 'bar', {
  labels: revenueMonthLabels,
  datasets: [{ 
    label: 'Revenue (₱)',
    data: revenueData, 
    backgroundColor: 'rgba(17,19,24,0.1)', 
    borderRadius: 4,
    borderColor: '#4a6741',
    borderWidth: 1
  }]
});

// Refresh button
document.getElementById('refreshAnalyticsBtn').addEventListener('click', () => {
  location.reload();
});
// Make topbar user clickable
document.addEventListener('DOMContentLoaded', function() {
    const topbarUser = document.querySelector('.topbar-user');
    console.log('Setting up topbar click listener');
    if (topbarUser) {
        topbarUser.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Topbar clicked - opening modal');
            if (typeof openProfileModal === 'function') {
                openProfileModal();
            } else {
                console.error('openProfileModal function not found!');
                alert('Modal function not loaded yet. Please refresh the page.');
            }
        });
    }
});

</script>
<!-- Include Profile Modal -->
<?php 

$modalPath = __DIR__ . '/profile-modal.php';
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
$logoutModalPath = __DIR__ . '/../../includes/logout-modal.php';
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
</body>
</html>