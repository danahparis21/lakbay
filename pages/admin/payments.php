<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// ========== REVENUE STATISTICS FROM ACTUAL DATA ==========

// 1. MTD Revenue (current month from bookings table)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total
    FROM bookings 
    WHERE payment_status = 'paid' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$mtdRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 2. Total Environmental Fees Collected (from booking_payments)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(environmental_amount), 0) as total
    FROM booking_payments 
    WHERE environmental_paid = 'paid'
");
$stmt->execute();
$envFees = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 3. Total Registration Fees Collected (from booking_payments)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(registration_amount), 0) as total
    FROM booking_payments 
    WHERE registration_paid = 'paid'
");
$stmt->execute();
$regFees = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 4. Previous month revenue for trend
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total
    FROM bookings 
    WHERE payment_status = 'paid' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
");
$stmt->execute();
$prevMonthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;

$trendPercent = $prevMonthRevenue > 0 ? round((($mtdRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100) : 0;
$trendIcon = $trendPercent >= 0 ? '↑' : '↓';
$trendClass = $trendPercent >= 0 ? 'stat-trend' : 'stat-trend warn';

// 5. Total Environmental and Registration from bookings (as backup/alternative view)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(environmental_amount), 0) as total_env,
        COALESCE(SUM(registration_amount), 0) as total_reg
    FROM booking_payments 
    WHERE environmental_paid = 'paid' OR registration_paid = 'paid'
");
$stmt->execute();
$feeBreakdown = $stmt->fetch(PDO::FETCH_ASSOC);

// 6. Fetch recent transactions with payment details including fee breakdown
$stmt = $pdo->prepare("
    SELECT 
        b.id,
        b.booking_number,
        b.user_id,
        b.mountain_id,
        b.total_amount,
        b.downpayment_amount,
        b.payment_status,
        b.status as booking_status,
        b.created_at,
        bp.registration_paid,
        bp.registration_amount,
        bp.environmental_paid,
        bp.environmental_amount,
        bp.paid_by,
        bp.notes
    FROM bookings b
    LEFT JOIN booking_payments bp ON b.id = bp.booking_id AND bp.source_type = 'user'
    WHERE b.payment_status IN ('paid', 'pending', 'expired')
    ORDER BY b.created_at DESC
    LIMIT 15
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

// 7. Payment breakdown percentages
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN payment_status IN ('failed', 'expired') THEN 1 ELSE 0 END) as failed_count,
        COUNT(*) as total_count
    FROM bookings
");
$stmt->execute();
$breakdown = $stmt->fetch(PDO::FETCH_ASSOC);

$paidPercent = $breakdown['total_count'] > 0 ? round(($breakdown['paid_count'] / $breakdown['total_count']) * 100) : 0;
$pendingPercent = $breakdown['total_count'] > 0 ? round(($breakdown['pending_count'] / $breakdown['total_count']) * 100) : 0;
$failedPercent = $breakdown['total_count'] > 0 ? round(($breakdown['failed_count'] / $breakdown['total_count']) * 100) : 0;

// 8. Get total from booking_payments for additional stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN registration_paid = 'paid' THEN registration_amount ELSE 0 END) as total_reg_collected,
        SUM(CASE WHEN environmental_paid = 'paid' THEN environmental_amount ELSE 0 END) as total_env_collected
    FROM booking_payments
");
$stmt->execute();
$paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper functions
function getPaymentBadgeClass($status) {
    switch($status) {
        case 'paid': return 'green';
        case 'pending': return 'amber';
        case 'failed':
        case 'expired': return 'red';
        default: return 'gray';
    }
}

function getPaymentStatusLabel($status) {
    switch($status) {
        case 'paid': return 'Paid';
        case 'pending': return 'Pending';
        case 'failed': return 'Failed';
        case 'expired': return 'Expired';
        default: return ucfirst($status);
    }
}

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
  <style>
    .fee-detail { font-size: 0.7rem; color: var(--ink-4); margin-top: 4px; }
    .stat-sub { font-size: 0.7rem; color: var(--ink-4); margin-top: 4px; }
  </style>
</head>
<body data-page="payments">
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
            <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
            <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
            <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
            <li class="nav-item" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
            <div class="nav-divider"></div>
            <li class="nav-item active" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
            <li class="nav-item" data-href="/pages/admin/reviews.php"><i class="fas fa-star"></i> Reviews</li>
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
        <div class="topbar-user">
          <div class="avatar"><?= htmlspecialchars($adminInitial) ?></div>
          <?= htmlspecialchars($adminName) ?>
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
          <div class="stat-sub">From paid bookings this month</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Environmental Fees Collected</div>
          <div class="stat-num"><?= formatCurrency($envFees) ?></div>
          <div class="stat-sub">From booking_payments table</div>
          <div class="fee-detail">Total collected: <?= formatCurrency($paymentStats['total_env_collected'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Registration Fees Collected</div>
          <div class="stat-num"><?= formatCurrency($regFees) ?></div>
          <div class="stat-sub">From booking_payments table</div>
          <div class="fee-detail">Total collected: <?= formatCurrency($paymentStats['total_reg_collected'] ?? 0) ?></div>
        </div>
      </div>

      <!-- Additional Stats Row -->
      <div class="stat-grid" style="grid-template-columns:repeat(2,1fr); margin-top: -10px;">
        <div class="stat-card">
          <div class="stat-label">Booking Payments Overview</div>
          <div class="stat-num"><?= $paymentStats['total_payments'] ?? 0 ?></div>
          <div class="stat-sub">Total payment records processed</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Combined Fee Total</div>
          <div class="stat-num"><?= formatCurrency(($paymentStats['total_env_collected'] ?? 0) + ($paymentStats['total_reg_collected'] ?? 0)) ?></div>
          <div class="stat-sub">Registration + Environmental fees</div>
        </div>
      </div>

      <div class="two-col">
        <!-- TRANSACTIONS -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Transactions</span>
            <button class="btn" id="genReportBtn"><i class="fas fa-file-invoice"></i> Export Report</button>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Hiker</th>
                  <th>Mountain</th>
                  <th>Amount</th>
                  <th>DP</th>
                  <th>Reg Fee</th>
                  <th>Env Fee</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($transactions)): ?>
                  <tr><td colspan="8" style="text-align:center; padding:40px;">No transactions found</td></tr>
                <?php else: ?>
                  <?php foreach ($transactions as $transaction): ?>
                    <tr>
                      <td style="font-size:0.75rem;color:var(--ink-4);"><?= htmlspecialchars($transaction['booking_number'] ?? 'N/A') ?></td>
                      <td><?= htmlspecialchars($transaction['hiker_name'] ?? 'Unknown') ?></td>
                      <td><?= htmlspecialchars($transaction['mountain_name'] ?? 'Unknown') ?></td>
                      <td><?= formatCurrency($transaction['total_amount'] ?? 0) ?></td>
                      <td><?= formatCurrency($transaction['downpayment_amount'] ?? 0) ?></td>
                      <td>
                        <?php if ($transaction['registration_paid'] == 'paid'): ?>
                          <span class="badge green">Paid: <?= formatCurrency($transaction['registration_amount'] ?? 0) ?></span>
                        <?php else: ?>
                          <span class="badge gray">Not paid</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($transaction['environmental_paid'] == 'paid'): ?>
                          <span class="badge green">Paid: <?= formatCurrency($transaction['environmental_amount'] ?? 0) ?></span>
                        <?php else: ?>
                          <span class="badge gray">Not paid</span>
                        <?php endif; ?>
                      </td>
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
                <div style="height:100%;width:<?= $paidPercent ?>%;background:#4a6741;border-radius:2px;"></div>
              </div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--ink-2);margin-bottom:6px;">
                <span>Pending</span><span class="badge amber"><?= $pendingPercent ?>%</span>
              </div>
              <div style="height:4px;background:var(--paper-3);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?= $pendingPercent ?>%;background:#b88a15;border-radius:2px;"></div>
              </div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--ink-2);margin-bottom:6px;">
                <span>Failed/Expired</span><span class="badge red"><?= $failedPercent ?>%</span>
              </div>
              <div style="height:4px;background:var(--paper-3);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?= $failedPercent ?>%;background:#b94040;border-radius:2px;"></div>
              </div>
            </div>
          </div>
          
          <!-- Fee Collection Summary -->
          <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--line);">
            <div style="font-size:0.7rem; font-weight:600; margin-bottom:12px;">FEE COLLECTION SUMMARY</div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
              <span>Registration Fees</span>
              <span><strong><?= formatCurrency($paymentStats['total_reg_collected'] ?? 0) ?></strong></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span>Environmental Fees</span>
              <span><strong><?= formatCurrency($paymentStats['total_env_collected'] ?? 0) ?></strong></span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Export Modal -->
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
    if(item.dataset.href) window.location.href = item.dataset.href; 
  });
});

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

document.getElementById('exportModal').addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

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