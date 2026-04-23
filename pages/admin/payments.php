<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Fetch revenue statistics
// MTD Revenue (current month) - Combine bookings and camping bookings
$stmt = $pdo->prepare("
    SELECT 
        (COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())), 0) +
         COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())), 0)
        ) as total
");
$stmt->execute();
$mtdRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Environmental Fees (assuming 38.6% of total revenue - adjust based on your actual fee structure)
$stmt = $pdo->prepare("
    SELECT 
        (COALESCE((SELECT SUM(total_amount * 0.386) FROM bookings WHERE payment_status = 'paid'), 0) +
         COALESCE((SELECT SUM(total_amount * 0.386) FROM camping_bookings WHERE payment_status = 'paid'), 0)
        ) as total
");
$stmt->execute();
$envFees = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Registration Fees (61.4% of total revenue)
$stmt = $pdo->prepare("
    SELECT 
        (COALESCE((SELECT SUM(total_amount * 0.614) FROM bookings WHERE payment_status = 'paid'), 0) +
         COALESCE((SELECT SUM(total_amount * 0.614) FROM camping_bookings WHERE payment_status = 'paid'), 0)
        ) as total
");
$stmt->execute();
$regFees = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Fetch recent transactions from both tables
$stmt = $pdo->prepare("
    SELECT 
        'booking' as type,
        id,
        booking_number as transaction_code,
        user_id,
        mountain_id,
        total_amount as amount,
        downpayment_amount,
        payment_status,
        status,
        created_at,
        NULL as start_date,
        NULL as end_date
    FROM bookings 
    WHERE payment_status IN ('paid', 'pending', 'expired')
    
    UNION ALL
    
    SELECT 
        'camping' as type,
        id,
        booking_number as transaction_code,
        user_id,
        mountain_id,
        total_amount as amount,
        downpayment_amount,
        payment_status,
        status,
        created_at,
        start_date,
        end_date
    FROM camping_bookings 
    WHERE payment_status IN ('paid', 'pending', 'expired')
    
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user and mountain names for transactions
foreach ($transactions as &$transaction) {
    // Get user name
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$transaction['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $transaction['hiker_name'] = $user['name'] ?? 'Unknown';
    
    // Get mountain name
    $stmt = $pdo->prepare("SELECT name FROM mountains WHERE id = ?");
    $stmt->execute([$transaction['mountain_id']]);
    $mountain = $stmt->fetch(PDO::FETCH_ASSOC);
    $transaction['mountain_name'] = $mountain['name'] ?? 'Unknown';
}

// Calculate payment breakdown percentages
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN payment_status IN ('failed', 'expired') THEN 1 ELSE 0 END) as failed_count,
        COUNT(*) as total_count
    FROM (
        SELECT payment_status FROM bookings 
        UNION ALL 
        SELECT payment_status FROM camping_bookings
    ) as all_payments
");
$stmt->execute();
$breakdown = $stmt->fetch(PDO::FETCH_ASSOC);

$paidPercent = $breakdown['total_count'] > 0 ? round(($breakdown['paid_count'] / $breakdown['total_count']) * 100) : 0;
$pendingPercent = $breakdown['total_count'] > 0 ? round(($breakdown['pending_count'] / $breakdown['total_count']) * 100) : 0;
$failedPercent = $breakdown['total_count'] > 0 ? round(($breakdown['failed_count'] / $breakdown['total_count']) * 100) : 0;

// Calculate trend (compare with previous month)
$stmt = $pdo->prepare("
    SELECT 
        (COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)), 0) +
         COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)), 0)
        ) as prev_month_total
");
$stmt->execute();
$prevMonthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['prev_month_total'] ?? 1;

$trendPercent = $prevMonthRevenue > 0 ? round((($mtdRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100) : 0;
$trendIcon = $trendPercent >= 0 ? '↑' : '↓';
$trendClass = $trendPercent >= 0 ? 'stat-trend' : 'stat-trend warn';

// Helper function for status badge class
function getPaymentBadgeClass($status) {
    switch($status) {
        case 'paid': return 'green';
        case 'pending': return 'amber';
        case 'failed':
        case 'expired': return 'red';
        default: return 'gray';
    }
}

// Helper function for status label
function getPaymentStatusLabel($status) {
    switch($status) {
        case 'paid': return 'Paid';
        case 'pending': return 'Pending';
        case 'failed': return 'Failed';
        case 'expired': return 'Expired';
        default: return ucfirst($status);
    }
}

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Revenue</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="shared.css">
</head>
<body data-page="payments">
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
        <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item active" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
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
      <div class="page-heading"><i class="fas fa-coins"></i> Revenue</div>
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
      <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="stat-card">
          <div class="stat-label">MTD Revenue <i class="fas fa-chart-bar"></i></div>
          <div class="stat-num"><?= formatCurrency($mtdRevenue) ?></div>
          <div class="<?= $trendClass ?>">
            <?= $trendIcon ?> <?= abs($trendPercent) ?>% vs last month
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Environmental Fees</div>
          <div class="stat-num"><?= formatCurrency($envFees) ?></div>
          <div class="stat-sub"><?= $mtdRevenue > 0 ? round(($envFees / $mtdRevenue) * 100) : 0 ?>% of total</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Registration Fees</div>
          <div class="stat-num"><?= formatCurrency($regFees) ?></div>
          <div class="stat-sub"><?= $mtdRevenue > 0 ? round(($regFees / $mtdRevenue) * 100) : 0 ?>% of total</div>
        </div>
      </div>

      <div class="two-col">
        <!-- TRANSACTIONS -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Transactions</span>
            <button class="btn" id="genReportBtn"><i class="fas fa-file-invoice"></i> Export Report</button>

            <!-- Add this modal/dialog for export options -->
<div id="exportModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:24px; max-width:500px; width:90%;">
        <h3 style="margin:0 0 16px 0;">Export Revenue Report</h3>
        <form id="exportForm">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500;">Report Type</label>
                <select name="type" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    <option value="full">Complete Report (Summary + Transactions)</option>
                    <option value="summary">Summary Only</option>
                    <option value="transactions">Transactions Only</option>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500;">Start Date</label>
                <input type="date" name="start_date" value="<?= date('Y-m-01') ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500;">End Date</label>
                <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" onclick="closeExportModal()" style="padding:8px 16px; background:#f0f0f0; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:8px 16px; background:#2c5f2d; color:white; border:none; border-radius:4px; cursor:pointer;">Export Excel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Export functionality
document.getElementById('genReportBtn').addEventListener('click', function() {
    document.getElementById('exportModal').style.display = 'flex';
});

function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}

document.getElementById('exportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const params = new URLSearchParams(formData);
    window.location.href = 'generate_revenue_report.php?' + params.toString();
    closeExportModal();
});

// Close modal when clicking outside
document.getElementById('exportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeExportModal();
    }
});
</script>

          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Type</th>
                <th>Hiker</th>
                <th>Mountain</th>
                <th>Amount</th>
                <th>DP Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($transactions)): ?>
                <tr>
                  <td colspan="7" style="text-align:center; padding:40px;">No transactions found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($transactions as $transaction): ?>
                  <tr>
                    <td style="font-size:0.75rem;color:var(--ink-4);">
                      <?= htmlspecialchars($transaction['transaction_code'] ?? 'N/A') ?>
                    </td>
                    <td>
                      <span class="badge <?= $transaction['type'] == 'booking' ? 'green' : 'amber' ?>">
                        <?= $transaction['type'] == 'booking' ? 'Day Hike' : 'Camping' ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($transaction['hiker_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($transaction['mountain_name'] ?? 'Unknown') ?></td>
                    <td><?= formatCurrency($transaction['amount'] ?? 0) ?></td>
                    <td><?= formatCurrency($transaction['downpayment_amount'] ?? 0) ?></td>
                    <td>
                      <span class="badge <?= getPaymentBadgeClass($transaction['payment_status']) ?>">
                        <?= getPaymentStatusLabel($transaction['payment_status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- PAYMENT BREAKDOWN -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Payment Breakdown</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:16px;margin-top:4px;">
            <div>
              <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--ink-2);margin-bottom:6px;">
                <span>Paid</span><span class="badge green"><?= $paidPercent ?>%</span>
              </div>
              <div style="height:4px;background:var(--paper-3);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?= $paidPercent ?>%;background:#4a6741;border-radius:2px;transition:width 0.6s ease;"></div>
              </div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--ink-2);margin-bottom:6px;">
                <span>Pending</span><span class="badge amber"><?= $pendingPercent ?>%</span>
              </div>
              <div style="height:4px;background:var(--paper-3);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?= $pendingPercent ?>%;background:#b88a15;border-radius:2px;transition:width 0.6s ease;"></div>
              </div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--ink-2);margin-bottom:6px;">
                <span>Failed/Expired</span><span class="badge red"><?= $failedPercent ?>%</span>
              </div>
              <div style="height:4px;background:var(--paper-3);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?= $failedPercent ?>%;background:#b94040;border-radius:2px;transition:width 0.6s ease;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function updateDate() {
  const d = new Date();
  document.getElementById('liveDate').textContent =
    d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() +
    '  ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
updateDate(); 
setInterval(updateDate, 1000);

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { 
    if(item.dataset.href) {
      window.location.href = item.dataset.href; 
    }
  });
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