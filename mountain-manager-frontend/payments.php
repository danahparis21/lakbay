<?php
// payments.php - Mountain Manager Payment Tracking (Updated for new booking_payments structure)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php';


// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$manager_id = $_SESSION['user_id'];

// Get manager's assigned mountains
$stmt = $pdo->prepare("
    SELECT m.* 
    FROM mountains m
    INNER JOIN manager_mountains mm ON m.id = mm.mountain_id
    WHERE mm.manager_id = ?
    ORDER BY m.name
");
$stmt->execute([$manager_id]);
$assigned_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mountain_ids = array_column($assigned_mountains, 'id');
$mountain_ids_placeholder = !empty($mountain_ids) ? implode(',', array_fill(0, count($mountain_ids), '?')) : 'NULL';

// Get bookings with payment info using the new booking_payments structure
$bookings = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.booking_number,
            b.user_id,
            b.mountain_id,
            b.guide_id,
            b.hike_date,
            b.start_time,
            b.hike_type,
            b.number_of_hikers,
            b.total_amount,
            b.downpayment_amount,
            b.downpayment_status,
            b.payment_status,
            b.guide_payment_status,
            b.status,
            m.name as mountain_name,
            m.registration_fee,
            m.environmental_fee,
            u.name as guide_name
        FROM bookings b
        INNER JOIN mountains m ON b.mountain_id = m.id
        LEFT JOIN guides g ON b.guide_id = g.id
        LEFT JOIN users u ON g.user_id = u.id
        WHERE b.mountain_id IN ($mountain_ids_placeholder)
        AND b.status NOT IN ('cancelled')
        ORDER BY b.hike_date DESC, b.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load payment statuses from booking_payments table for each booking
    foreach ($bookings as &$booking) {
        $stmt = $pdo->prepare("
            SELECT 
                bp.id,
                bp.source_type,
                bp.source_id,
                bp.registration_paid,
                bp.registration_amount,
                bp.registration_paid_at,
                bp.environmental_paid,
                bp.environmental_amount,
                bp.environmental_paid_at,
                CASE 
                    WHEN bp.source_type = 'user' THEN u.name
                    WHEN bp.source_type = 'booking_hiker' THEN bh.hiker_name
                END as hiker_name
            FROM booking_payments bp
            LEFT JOIN users u ON bp.source_type = 'user' AND bp.source_id = u.id
            LEFT JOIN booking_hikers bh ON bp.source_type = 'booking_hiker' AND bp.source_id = bh.id
            WHERE bp.booking_id = ?
            ORDER BY bp.id
        ");
        $stmt->execute([$booking['id']]);
        $booking['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper functions
function fmtDate($date) { 
    return $date ? date('M d, Y', strtotime($date)) : 'N/A'; 
}

function fmtTime($time) { 
    return $time ? date('g:i A', strtotime($time)) : 'N/A'; 
}

function fmtMoney($amt) { 
    return '₱' . number_format((float)$amt, 2); 
}

function getHikeTypeLabel($type) {
    $labels = [
        'day_hike' => 'Day Hike',
        'overnight' => 'Overnight',
        'multi_day' => 'Multi-Day'
    ];
    return $labels[$type] ?? $type;
}

$manager_name = $manager['name'];
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));

// Calculate stats
$total_bookings = count($bookings);
$total_paid_reg = 0;
$total_reg = 0;
$total_paid_env = 0;
$total_env = 0;
$total_hikers = 0;

foreach ($bookings as $booking) {
    $total_hikers += $booking['number_of_hikers'];
    foreach ($booking['payments'] as $payment) {
        $total_reg++;
        if ($payment['registration_paid'] === 'paid') $total_paid_reg++;
        
        if ($booking['environmental_fee'] > 0) {
            $total_env++;
            if ($payment['environmental_paid'] === 'paid') $total_paid_env++;
        }
    }
}
$collection_rate = ($total_reg + $total_env) > 0 ? round(($total_paid_reg + $total_paid_env) / ($total_reg + $total_env) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY Manager — Payments</title>
<link rel="stylesheet" href="manager.css">
<style>
  /* Sidebar - Desktop */
.sidebar {
  width: var(--sidebar-w);
  background: #F8F6F0;
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 200;
  transition: width .25s ease;
  overflow: hidden;
}
.sidebar.collapsed { width: var(--sidebar-w-sm); }
.sidebar.collapsed .nav-label,
.sidebar.collapsed .nav-text,
.sidebar.collapsed .sidebar-brand-text,
.sidebar.collapsed .sidebar-footer-text,
.sidebar.collapsed .mountain-badge-text { display: none; }
.sidebar.collapsed .sidebar-brand { justify-content: center; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 14px 0; }
.sidebar.collapsed .nav-item svg { margin: 0; }
.sidebar.collapsed .mountain-badge { justify-content: center; padding: 12px; }

.sidebar-brand {
  display: flex; align-items: center; gap: 12px;
  padding: 22px 24px 18px;
  border-bottom: 1px solid rgba(16,6,0,0.08);
  text-decoration: none;
}
.sidebar-logo {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--gold); display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sidebar-logo svg { width: 20px; height: 20px; }
.sidebar-brand-text { line-height: 1.2; }
.sidebar-app-name { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700; color: var(--ink); letter-spacing: .3px; }
.sidebar-app-sub { font-size: 9px; color: rgba(16,6,0,0.35); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 1px; }

.mountain-badge {
  display: flex; align-items: center; gap: 10px;
  margin: 14px 16px;
  background: linear-gradient(135deg, rgba(201,168,76,0.1), rgba(201,168,76,0.05));
  border: 1px solid rgba(201,168,76,0.3);
  border-radius: 12px;
  padding: 12px 14px;
}
.mountain-badge-icon {
  width: 34px; height: 34px; border-radius: 9px;
  background: var(--gold); display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.mountain-badge-icon svg { width: 16px; height: 16px; stroke: var(--ink); }
.mountain-badge-text { overflow: hidden; }
.mountain-badge-name { font-size: 12px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mountain-badge-role { font-size: 10px; color: rgba(16,6,0,0.5); margin-top: 1px; }

.nav-section { padding: 0 0 8px; flex: 1; overflow-y: auto; }
.nav-label { font-size: 9px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; color: rgba(16,6,0,0.35); padding: 16px 24px 6px; }
.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 24px; font-size: 13px; font-weight: 500;
  color: rgba(16,6,0,0.6); cursor: pointer; text-decoration: none;
  transition: all .15s; border-left: 3px solid transparent;
  white-space: nowrap;
}
.nav-item:hover { color: var(--ink); background: rgba(16,6,0,0.04); transform: translateX(2px); }
.nav-item.active { color: var(--ink); background: rgba(201,168,76,0.1); border-left-color: var(--gold); }
.nav-item svg { width: 17px; height: 17px; stroke: currentColor; stroke-width: 1.8; flex-shrink: 0; }
.nav-divider { height: 1px; background: rgba(16,6,0,0.08); margin: 8px 20px; }

/* Log out button - red style */
.nav-item.logout-red {
  margin-top: 12px;
  border-top: 1px solid var(--border);
  border-radius: 0;
  color: #b91c1c;
}
.nav-item.logout-red:hover {
  background: rgba(185, 28, 28, 0.08);
  color: #b91c1c;
}
.nav-item.logout-red svg {
  stroke: #b91c1c;
}
.nav-item.logout-red:hover svg {
  stroke: #b91c1c;
}

.sidebar-footer {
  padding: 16px 20px; border-top: 1px solid rgba(16,6,0,0.08);
  display: flex; align-items: center; gap: 10px;
}
.sidebar-footer-avatar {
  width: 34px; height: 34px; border-radius: 50%; background: var(--gold);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: var(--ink); flex-shrink: 0;
}
.sidebar-footer-text { overflow: hidden; }
.sidebar-footer-name { font-size: 12px; font-weight: 700; color: var(--ink); }
.sidebar-footer-role { font-size: 10px; color: rgba(16,6,0,0.5); }

/* Page specific styles */
.payment-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: var(--white);
    border-radius: var(--r);
    border: 1px solid var(--border);
    padding: 20px;
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}
.stat-value {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 700;
    color: var(--ink);
}
.stat-label {
    font-size: 12px;
    color: var(--ink3);
    margin-top: 8px;
}
.booking-group {
    background: var(--white);
    border-radius: var(--r);
    border: 1px solid var(--border);
    margin-bottom: 20px;
    overflow: hidden;
}
.booking-header {
    background: linear-gradient(135deg, var(--ink) 0%, var(--ink2) 100%);
    padding: 16px 20px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.booking-header-left {
    flex: 1;
}
.booking-id {
    font-family: 'DM Mono', monospace;
    font-size: 13px;
    background: rgba(255,255,255,0.15);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}
.booking-mountain {
    font-weight: 700;
    margin-top: 6px;
}
.booking-meta {
    font-size: 11px;
    opacity: 0.7;
    margin-top: 4px;
}
.progress-bar {
    width: 120px;
    height: 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: var(--gold);
    transition: width 0.3s;
}
.booking-body {
    padding: 0;
    max-height: 2000px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}
.booking-body.collapsed {
    max-height: 0;
}
.payment-row {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    gap: 16px;
    flex-wrap: wrap;
}
.payment-row:last-child {
    border-bottom: none;
}
.hiker-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
}
.hiker-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--ink), var(--ink2));
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.fee-section {
    flex: 1;
    min-width: 200px;
}
.fee-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--ink3);
    margin-bottom: 6px;
}
.fee-amount {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
}
.fee-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}
.fee-badge:hover {
    transform: scale(0.95);
}
.fee-badge.paid {
    background: var(--green-bg);
    color: var(--green);
}
.fee-badge.unpaid {
    background: var(--red-bg);
    color: var(--red);
}
.fee-badge.waived {
    background: var(--blue-bg);
    color: var(--blue);
}
.bulk-bar {
    background: var(--ink);
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    display: none;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.bulk-bar.show {
    display: flex;
}
.bulk-actions {
    display: flex;
    gap: 8px;
}
.bulk-btn {
    padding: 6px 16px;
    border-radius: 20px;
    border: none;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.bulk-btn.paid {
    background: var(--green);
    color: white;
}
.bulk-btn.unpaid {
    background: var(--red);
    color: white;
}
.bulk-btn.waived {
    background: var(--blue);
    color: white;
}
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-tab {
    padding: 8px 20px;
    border-radius: 30px;
    background: var(--white);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
}
.filter-tab.active {
    background: var(--ink);
    color: white;
    border-color: var(--ink);
}
.mtn-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.mtn-tab {
    padding: 8px 20px;
    border-radius: 30px;
    background: var(--white);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
}
.mtn-tab.active {
    background: var(--ink);
    color: white;
    border-color: var(--ink);
}
.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: var(--ink);
    color: white;
    padding: 12px 24px;
    border-radius: 30px;
    font-size: 13px;
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.3s;
}
.toast.show {
    opacity: 1;
}
/* Logout button style */
.nav-item.logout {
  margin-top: 12px;
  border-radius: 0;
  color: #b91c1c;
}
.nav-item.logout:hover {
  background: rgba(185, 28, 28, 0.08);
  color: #b91c1c;
}
.nav-item.logout svg {
  stroke: #b91c1c;
}
.nav-item.logout:hover svg {
  stroke: #b91c1c;
}
</style>
</head>
<body>
<div class="app-shell">
<!-- SIDEBAR -->
<?php $activePage = 'payments'; ?>
<?php include 'shared_sidebar.php'; ?>

<!-- MAIN -->
<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Payments</div>
        <div class="topbar-page-sub" id="paySubtitle">Fee collection overview</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar" id="topbarAvatar"><?= $manager_initials ?></div>
    </div>
  </div>
  <div class="content">
    <!-- Stats Cards -->
    <div class="payment-stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $total_bookings ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $total_hikers ?></div>
        <div class="stat-label">Total Hikers</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $collection_rate ?>%</div>
        <div class="stat-label">Collection Rate</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">₱<?= number_format($total_paid_reg + $total_paid_env, 2) ?></div>
        <div class="stat-label">Fees Collected</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-tabs">
      <div class="mtn-tabs">
        <div class="filter-tab active" data-mtn="all" onclick="filterByMountain('all')">All Mountains</div>
        <?php foreach ($assigned_mountains as $mtn): ?>
        <div class="filter-tab" data-mtn="<?= $mtn['id'] ?>" onclick="filterByMountain(<?= $mtn['id'] ?>)"><?= htmlspecialchars($mtn['name']) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="filter-tabs" style="margin-left: auto;">
        <div class="filter-tab active" data-status="all" onclick="filterByStatus('all')">All</div>
        <div class="filter-tab" data-status="unpaid" onclick="filterByStatus('unpaid')">Unpaid Only</div>
        <div class="filter-tab" data-status="paid" onclick="filterByStatus('paid')">Paid Only</div>
      </div>
    </div>

    <!-- Bulk Action Bar -->
    <div id="bulkBar" class="bulk-bar">
      <span id="bulkLabel">0 hikers selected</span>
      <div class="bulk-actions">
        <button class="bulk-btn paid" onclick="bulkUpdate('registration', 'paid')">Mark Registration Paid</button>
        <button class="bulk-btn paid" onclick="bulkUpdate('environmental', 'paid')">Mark Environmental Paid</button>
        <button class="bulk-btn waived" onclick="bulkUpdate('registration', 'waived')">Waive Registration</button>
        <button class="bulk-btn waived" onclick="bulkUpdate('environmental', 'waived')">Waive Environmental</button>
        <button class="bulk-btn" style="background:rgba(255,255,255,0.2)" onclick="clearSelection()">Clear</button>
      </div>
    </div>

    <!-- Bookings List -->
    <div id="bookingsList">
      <?php foreach ($bookings as $booking): 
        $booking_progress = 0;
        $booking_total = 0;
        $booking_paid = 0;
        foreach ($booking['payments'] as $p) {
            $booking_total += 2;
            if ($p['registration_paid'] === 'paid') $booking_paid++;
            if ($booking['environmental_fee'] > 0) {
                if ($p['environmental_paid'] === 'paid') $booking_paid++;
            } else {
                $booking_total--;
            }
        }
        $booking_progress = $booking_total > 0 ? round($booking_paid / $booking_total * 100) : 0;
      ?>
      <div class="booking-group" data-booking-id="<?= $booking['id'] ?>" data-mountain-id="<?= $booking['mountain_id'] ?>">
        <div class="booking-header" onclick="toggleBooking(this)">
          <div class="booking-header-left">
            <div class="booking-id"><?= htmlspecialchars($booking['booking_number']) ?></div>
            <div class="booking-mountain"><?= htmlspecialchars($booking['mountain_name']) ?></div>
            <div class="booking-meta">
              <?= fmtDate($booking['hike_date']) ?> · 
              <?= fmtTime($booking['start_time']) ?> · 
              <?= getHikeTypeLabel($booking['hike_type']) ?> · 
              <?= $booking['number_of_hikers'] ?> hiker(s) · 
              Guide: <?= htmlspecialchars($booking['guide_name'] ?? 'Unassigned') ?>
            </div>
          </div>
          <div>
            <span class="badge status-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $booking_progress ?>%"></div>
          </div>
          <div class="bgh-pct"><?= $booking_progress ?>%</div>
          <div class="bgh-chevron">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
        </div>
        <div class="booking-body">
          <?php foreach ($booking['payments'] as $payment): 
            $payment_id = $payment['id'];
            $hiker_name = $payment['hiker_name'] ?? 'Unknown Hiker';
            $reg_status = $payment['registration_paid'];
            $env_status = $payment['environmental_paid'];
            $has_env = $booking['environmental_fee'] > 0;
          ?>
          <div class="payment-row" data-payment-id="<?= $payment_id ?>">
            <input type="checkbox" class="hiker-checkbox" data-payment-id="<?= $payment_id ?>" data-reg-status="<?= $reg_status ?>" data-env-status="<?= $env_status ?>" onchange="updateBulkBar()">
            <div class="hiker-info">
              <div class="hiker-avatar"><?= substr($hiker_name, 0, 2) ?></div>
              <div>
                <div style="font-weight: 600;"><?= htmlspecialchars($hiker_name) ?></div>
                <div style="font-size: 11px; color: var(--ink3);"><?= $payment['source_type'] === 'user' ? 'Booker' : 'Additional Hiker' ?></div>
              </div>
            </div>
            <div class="fee-section">
              <div class="fee-label">Registration Fee</div>
              <div class="fee-amount"><?= fmtMoney($payment['registration_amount']) ?></div>
              <button class="fee-badge <?= $reg_status ?>" onclick="updatePayment(<?= $payment_id ?>, 'registration', '<?= $reg_status ?>')">
                <?= $reg_status === 'paid' ? '✓' : ($reg_status === 'waived' ? '⊘' : '○') ?> <?= ucfirst($reg_status) ?>
              </button>
            </div>
            <?php if ($has_env): ?>
            <div class="fee-section">
              <div class="fee-label">Environmental Fee</div>
              <div class="fee-amount"><?= fmtMoney($payment['environmental_amount']) ?></div>
              <button class="fee-badge <?= $env_status ?>" onclick="updatePayment(<?= $payment_id ?>, 'environmental', '<?= $env_status ?>')">
                <?= $env_status === 'paid' ? '✓' : ($env_status === 'waived' ? '⊘' : '○') ?> <?= ucfirst($env_status) ?>
              </button>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <div class="payment-row" style="background: var(--off); border-top: 1px solid var(--border);">
            <div style="flex: 1;"></div>
            <div class="fee-section">
              <button class="btn btn-primary btn-sm" onclick="markAllPaidForBooking(<?= $booking['id'] ?>)">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
                Mark All Paid
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($bookings)): ?>
      <div class="empty-state" style="text-align: center; padding: 60px;">
        <div class="empty-state-icon" style="font-size: 48px; margin-bottom: 16px;">💰</div>
        <h3>No payments to display</h3>
        <p>No active bookings found for your mountains.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('mainArea').classList.toggle('expanded');
}

function updateClock() {
  const d = new Date();
  const dateEl = document.getElementById('topbarDate');
  if (dateEl) {
    dateEl.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() + ' ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
  }
}

function toggleBooking(header) {
  const body = header.nextElementSibling;
  const chevron = header.querySelector('.bgh-chevron');
  body.classList.toggle('collapsed');
  if (chevron) chevron.classList.toggle('open');
}

function filterByMountain(mountainId) {
  document.querySelectorAll('.mtn-tab').forEach(tab => tab.classList.remove('active'));
  document.querySelector(`.mtn-tab[data-mtn="${mountainId}"]`)?.classList.add('active');
  
  const bookings = document.querySelectorAll('.booking-group');
  bookings.forEach(booking => {
    const bookingMtn = parseInt(booking.dataset.mountainId);
    if (mountainId === 'all' || bookingMtn === mountainId) {
      booking.style.display = '';
    } else {
      booking.style.display = 'none';
    }
  });
}

let currentStatusFilter = 'all';

function filterByStatus(status) {
  currentStatusFilter = status;
  document.querySelectorAll('.filter-tabs .filter-tab').forEach(tab => {
    if (tab.getAttribute('data-status') === status) {
      tab.classList.add('active');
    } else if (tab.getAttribute('data-status')) {
      tab.classList.remove('active');
    }
  });
  
  const paymentRows = document.querySelectorAll('.payment-row:not(:last-child)');
  paymentRows.forEach(row => {
    const regBadge = row.querySelector('.fee-section:first-child .fee-badge');
    const envBadge = row.querySelector('.fee-section:last-child .fee-badge');
    
    if (status === 'all') {
      row.style.display = '';
    } else if (status === 'unpaid') {
      const regUnpaid = regBadge && regBadge.classList.contains('unpaid');
      const envUnpaid = envBadge && envBadge.classList.contains('unpaid');
      row.style.display = (regUnpaid || envUnpaid) ? '' : 'none';
    } else if (status === 'paid') {
      const regPaid = regBadge && regBadge.classList.contains('paid');
      const envPaid = envBadge && envBadge.classList.contains('paid');
      row.style.display = (regPaid && envPaid) ? '' : 'none';
    }
  });
}

// Selection and bulk actions
let selectedPayments = new Set();

function updateBulkBar() {
  const checkboxes = document.querySelectorAll('.hiker-checkbox:checked');
  selectedPayments.clear();
  checkboxes.forEach(cb => {
    selectedPayments.add(parseInt(cb.dataset.paymentId));
  });
  
  const bulkBar = document.getElementById('bulkBar');
  const bulkLabel = document.getElementById('bulkLabel');
  
  if (selectedPayments.size > 0) {
    bulkBar.classList.add('show');
    bulkLabel.textContent = `${selectedPayments.size} hiker(s) selected`;
  } else {
    bulkBar.classList.remove('show');
  }
}

function clearSelection() {
  document.querySelectorAll('.hiker-checkbox').forEach(cb => cb.checked = false);
  selectedPayments.clear();
  updateBulkBar();
}

function bulkUpdate(feeType, status) {
  if (selectedPayments.size === 0) {
    showToast('No hikers selected');
    return;
  }
  
  if (!confirm(`Mark ${feeType} fee as ${status} for ${selectedPayments.size} hiker(s)?`)) return;
  
  const paymentIds = Array.from(selectedPayments);
  
  fetch('update_payment_bulk.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_ids: paymentIds, fee_type: feeType, status: status })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(`${selectedPayments.size} hiker(s) updated successfully!`);
      location.reload();
    } else {
      showToast('Error: ' + (data.message || 'Update failed'));
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Network error');
  });
}

function updatePayment(paymentId, feeType, currentStatus) {
  let newStatus;
  if (currentStatus === 'unpaid') newStatus = 'paid';
  else if (currentStatus === 'paid') newStatus = 'waived';
  else newStatus = 'unpaid';
  
  console.log('Sending request to: update_payment.php');
  console.log('Data:', { payment_id: paymentId, fee_type: feeType, status: newStatus });
  
  fetch('update_payment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_id: paymentId, fee_type: feeType, status: newStatus })
  })
  .then(res => {
    console.log('Response status:', res.status);
    console.log('Response headers:', res.headers);
    return res.text(); // Get raw text first to debug
  })
  .then(text => {
    console.log('Raw response:', text);
    try {
      const data = JSON.parse(text);
      if (data.success) {
        location.reload();
      } else {
        showToast('Error: ' + (data.error || 'Update failed'));
      }
    } catch (e) {
      console.error('JSON parse error:', e);
      showToast('Server error - check console');
    }
  })
  .catch(err => {
    console.error('Fetch error:', err);
    showToast('Network error');
  });
}

function markAllPaidForBooking(bookingId) {
  if (!confirm('Mark ALL fees (registration + environmental) as PAID for this entire booking?')) return;
  
  fetch('mark_booking_paid.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: bookingId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('All fees for this booking marked as paid!');
      location.reload();
    } else {
      showToast('Error: ' + (data.message || 'Update failed'));
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Network error');
  });
}

function showToast(message) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}

// Initialize
setInterval(updateClock, 1000);
updateClock();

// Apply mountain filter from URL if present
const urlParams = new URLSearchParams(window.location.search);
const mtnParam = urlParams.get('mtn');
if (mtnParam) {
  filterByMountain(parseInt(mtnParam));
}
</script>
</body>
</html>