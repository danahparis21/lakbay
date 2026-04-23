<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// ========== FETCH HIKERS ==========
$stmt = $pdo->prepare("
    SELECT id, name, email, role, created_at 
    FROM users 
    WHERE role = 'hiker' 
    ORDER BY name
");
$stmt->execute();
$hikers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each hiker, get additional stats
foreach ($hikers as &$hiker) {
    // Get total trips (day hikes + camping)
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM bookings WHERE user_id = ?) +
            (SELECT COUNT(*) FROM camping_bookings WHERE user_id = ?) as total_trips
    ");
    $stmt->execute([$hiker['id'], $hiker['id']]);
    $hiker['total_trips'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_trips'] ?? 0;
    
    // Get total spent
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE((SELECT SUM(total_amount) FROM bookings WHERE user_id = ? AND payment_status = 'paid'), 0) +
            COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE user_id = ? AND payment_status = 'paid'), 0) as total_spent
    ");
    $stmt->execute([$hiker['id'], $hiker['id']]);
    $hiker['total_spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_spent'] ?? 0;
    
    // Get last mountain visited
    $stmt = $pdo->prepare("
        SELECT m.name, b.hike_date as last_date
        FROM bookings b
        LEFT JOIN mountains m ON b.mountain_id = m.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$hiker['id']]);
    $lastBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastBooking) {
        $hiker['last_mountain'] = $lastBooking['name'] ?? 'Unknown';
        $hiker['last_date'] = date('M d', strtotime($lastBooking['last_date']));
    } else {
        $hiker['last_mountain'] = 'No trips yet';
        $hiker['last_date'] = '—';
    }
    
    // Determine skill level based on total trips
    if ($hiker['total_trips'] >= 10) {
        $hiker['skill'] = 'Advanced';
    } elseif ($hiker['total_trips'] >= 3) {
        $hiker['skill'] = 'Intermediate';
    } else {
        $hiker['skill'] = 'Beginner';
    }
    
    // Get phone (from users table if available, or placeholder)
    $hiker['phone'] = '+63 XXX XXX XXXX';
    $hiker['emergency'] = 'No emergency contact set';
}

// ========== FETCH BOOKINGS ==========
$stmt = $pdo->prepare("
    SELECT 
        b.id,
        b.booking_number,
        b.user_id,
        u.name as hiker_name,
        m.name as mountain_name,
        b.hike_date,
        b.hike_type,
        b.number_of_hikers,
        b.total_amount,
        b.downpayment_amount,
        b.payment_status,
        b.status,
        g.name as guide_name,
        b.created_at
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mountains m ON b.mountain_id = m.id
    LEFT JOIN users g ON b.guide_id = g.id
    WHERE u.role = 'hiker'
    ORDER BY b.created_at DESC
    LIMIT 50
");
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FETCH CAMPING BOOKINGS ==========
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.booking_number,
        c.user_id,
        u.name as hiker_name,
        m.name as mountain_name,
        c.start_date,
        c.end_date,
        c.number_of_nights,
        c.number_of_hikers,
        c.total_amount,
        c.downpayment_amount,
        c.payment_status,
        c.status,
        c.campsite_name,
        c.equipment_rental,
        g.name as guide_name,
        c.created_at
    FROM camping_bookings c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN mountains m ON c.mountain_id = m.id
    LEFT JOIN users g ON c.guide_id = g.id
    WHERE u.role = 'hiker'
    ORDER BY c.created_at DESC
    LIMIT 50
");
$stmt->execute();
$campingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

function getSkillColor($skill) {
    $colors = ['Beginner' => 'green', 'Intermediate' => 'amber', 'Advanced' => 'red'];
    return $colors[$skill] ?? 'gray';
}

function getDpStatusLabel($status) {
    $labels = ['paid' => 'DP Paid', 'pending' => 'DP Pending', 'expired' => 'DP Expired'];
    return $labels[$status] ?? ucfirst($status);
}

function getBookingStatusLabel($status) {
    $labels = ['active' => 'Active', 'finished' => 'Finished', 'expired' => 'Expired', 'pending' => 'Pending', 'confirmed' => 'Confirmed'];
    return $labels[$status] ?? ucfirst($status);
}

function getDpDotClass($status) {
    return $status ?? 'pending';
}

function getBookingChipClass($status) {
    switch($status) {
        case 'active': return 'chip-active';
        case 'finished': return 'chip-finished';
        case 'expired': return 'chip-expired';
        default: return 'chip-pending';
    }
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($dateStr) {
    if (!$dateStr) return 'N/A';
    return date('M d, Y', strtotime($dateStr));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Hikers</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="shared.css">
  <style>
    /* Keep all existing styles - they remain unchanged */
    .tab-bar {
      display: flex;
      gap: 4px;
      background: var(--paper-2);
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 24px;
      width: fit-content;
    }
    .tab-btn {
      font-family: 'Inter', sans-serif;
      font-size: 0.78rem;
      font-weight: 500;
      padding: 7px 18px;
      border-radius: 7px;
      border: none;
      background: transparent;
      color: var(--ink-3);
      cursor: pointer;
      transition: all 0.18s;
      display: flex;
      align-items: center;
      gap: 7px;
    }
    .tab-btn i { font-size: 0.7rem; }
    .tab-btn.active {
      background: white;
      color: var(--ink);
      font-weight: 600;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .filter-bar {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .filter-bar input,
    .filter-bar select {
      font-family: 'Inter', sans-serif;
      font-size: 0.78rem;
      border: 1px solid var(--line);
      background: white;
      color: var(--ink);
      padding: 8px 14px;
      border-radius: 8px;
      outline: none;
    }
    .filter-bar input { width: 200px; }
    .hikers-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 16px;
    }
    .hiker-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 18px;
      transition: box-shadow 0.2s, transform 0.15s;
      cursor: default;
    }
    .hiker-card:hover {
      box-shadow: 0 8px 20px rgba(0,0,0,0.07);
      transform: translateY(-1px);
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
    .hiker-avatar-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }
    .hiker-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: var(--ink);
      color: var(--paper);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.1rem;
      font-weight: 600;
      flex-shrink: 0;
    }
    .hiker-name {
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--ink);
      line-height: 1.3;
    }
    .hiker-sub {
      font-size: 0.7rem;
      color: var(--ink-4);
      margin-top: 2px;
    }
    .hiker-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-bottom: 14px;
    }
    .hiker-stat {
      background: var(--paper);
      border-radius: 8px;
      padding: 8px 10px;
    }
    .hiker-stat-label {
      font-size: 0.62rem;
      color: var(--ink-4);
      font-weight: 500;
      margin-bottom: 3px;
    }
    .hiker-stat-val {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--ink);
    }
    .hiker-card-actions {
      display: flex;
      gap: 6px;
      padding-top: 12px;
      border-top: 1px solid var(--line);
    }
    .btn { background: none; border: 1px solid var(--border-light); padding: 6px 12px; border-radius: 40px; font-size: 0.75rem; cursor: pointer; font-family: 'Inter', sans-serif; }
    .btn-primary { background: var(--ink); color: white; border: none; }
    .btn-ghost { border: none; color: var(--teal); background: none; }
    .hiker-card-actions .btn { font-size: 0.7rem; padding: 6px 12px; flex: 1; }
    .bookings-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
      margin-bottom: 16px;
    }
    .booking-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 0;
      overflow: hidden;
      transition: box-shadow 0.2s, transform 0.15s;
    }
    .booking-card-header {
      padding: 16px 18px 14px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
    }
    .booking-id {
      font-family: 'DM Mono', monospace;
      font-size: 0.62rem;
      color: var(--ink-4);
      letter-spacing: 0.1em;
      margin-bottom: 4px;
    }
    .booking-hiker-name {
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--ink);
    }
    .booking-mountain {
      font-size: 0.75rem;
      color: var(--ink-3);
      margin-top: 2px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .booking-status-chip {
      font-size: 0.65rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .chip-active { background: rgba(30,123,72,0.1); color: #1E7B48; }
    .chip-finished { background: var(--paper-2); color: var(--ink-4); }
    .chip-expired { background: rgba(192,57,43,0.1); color: #C0392B; }
    .chip-pending { background: rgba(230,126,34,0.1); color: #c96a10; }
    .booking-card-body {
      padding: 14px 18px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .bc-field-label {
      font-size: 0.62rem;
      font-weight: 600;
      color: var(--ink-4);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 3px;
    }
    .bc-field-val {
      font-size: 0.78rem;
      color: var(--ink-2);
      font-weight: 500;
    }
    .booking-card-footer {
      padding: 12px 18px;
      border-top: 1px solid var(--line);
      background: var(--paper);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
    .dp-status {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .dp-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .dp-dot.paid { background: #1E7B48; }
    .dp-dot.pending { background: #E67E22; }
    .dp-dot.expired { background: #C0392B; }
    .camping-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
      margin-bottom: 16px;
    }
    .camping-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: 16px;
      overflow: hidden;
      transition: box-shadow 0.2s, transform 0.15s;
    }
    .camping-card-top {
      background: var(--ink);
      padding: 16px 18px 14px;
    }
    .camping-badge {
      font-size: 0.62rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      background: rgba(255,255,255,0.15);
      color: rgba(255,255,255,0.85);
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 8px;
    }
    .camping-name {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.2rem;
      font-weight: 600;
      color: white;
    }
    .camping-mountain {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.6);
      margin-top: 3px;
    }
    .camping-card-body {
      padding: 14px 18px;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 10px;
    }
    .cc-field-label {
      font-size: 0.6rem;
      font-weight: 600;
      color: var(--ink-4);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 3px;
    }
    .camping-card-footer {
      padding: 10px 18px;
      border-top: 1px solid var(--line);
      display: flex;
      justify-content: flex-end;
      gap: 6px;
    }
    .view-all-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
    }
    .count-label { font-size: 0.74rem; color: var(--ink-4); }
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(10,12,18,0.55);
      backdrop-filter: blur(4px);
      z-index: 1000;
      display: flex; align-items: flex-start; justify-content: center;
      padding: 32px 20px;
      overflow-y: auto;
      opacity: 0; pointer-events: none;
      transition: opacity 0.22s ease;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal {
      background: white; border-radius: 20px;
      width: 100%; max-width: 640px;
      box-shadow: 0 32px 64px -16px rgba(0,0,0,0.22);
      transform: translateY(14px);
      transition: transform 0.25s ease;
      overflow: hidden;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-header {
      padding: 20px 26px 16px;
      border-bottom: 1px solid var(--line);
      display: flex; justify-content: space-between;
    }
    .detail-section { margin-bottom: 20px; }
    .detail-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink-4);
      padding-bottom: 8px;
      border-bottom: 1px solid var(--line);
      margin-bottom: 12px;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .detail-label {
      font-size: 0.65rem;
      font-weight: 600;
      color: var(--ink-4);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 4px;
    }
    .receipt-area {
      border: 1.5px dashed var(--ink-5);
      border-radius: 10px;
      padding: 16px;
      text-align: center;
      background: var(--paper);
      position: relative;
      cursor: pointer;
    }
    @media (max-width: 960px) {
      .hikers-grid, .bookings-grid, .camping-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body data-page="hikers">
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
        <div class="logo-sub">wilderness intelligence</div>
      </div>
      <div style="margin-bottom:8px; padding-left:28px;">
        <div class="nav-section-label">Navigation</div>
      </div>
      <ul class="nav-list">
        <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item active" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
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

  <!-- MAIN -->
  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-person-hiking"></i> Hikers</div>
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

      <!-- TAB BAR -->
      <div class="tab-bar">
        <button class="tab-btn active" data-tab="hikers-tab"><i class="fas fa-user"></i> Hikers</button>
        <button class="tab-btn" data-tab="bookings-tab"><i class="fas fa-calendar-check"></i> Bookings</button>
        <button class="tab-btn" data-tab="camping-tab"><i class="fas fa-campground"></i> Camping</button>
      </div>

      <!-- TAB 1: HIKERS -->
      <div class="tab-panel active" id="hikers-tab">
        <div class="filter-bar">
          <input type="text" id="hikerSearch" placeholder="Search by name…">
          <select id="skillFilter">
            <option value="">All skill levels</option>
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
          </select>
          <button class="btn" id="applyFilterBtn"><i class="fas fa-filter"></i> Filter</button>
          <button class="btn btn-ghost" id="resetFilterBtn"><i class="fas fa-rotate-left"></i> Reset</button>
        </div>

        <div class="view-all-row">
          <span class="count-label" id="hikerCountLabel">Showing <?= count($hikers) ?> hikers</span>
          <button class="btn btn-ghost" id="viewAllHikersBtn"><i class="fas fa-table-list"></i> View All</button>
        </div>

        <div class="hikers-grid" id="hikersGrid"></div>
      </div>

      <!-- TAB 2: BOOKINGS -->
      <div class="tab-panel" id="bookings-tab">
        <div class="filter-bar">
          <select id="bookingStatusFilter">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="finished">Finished</option>
            <option value="expired">Expired</option>
          </select>
          <select id="dpStatusFilter">
            <option value="">All DP statuses</option>
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
            <option value="expired">Expired</option>
          </select>
          <button class="btn" id="applyBookingFilter"><i class="fas fa-filter"></i> Filter</button>
        </div>

        <div class="view-all-row">
          <span class="count-label" id="bookingCountLabel">Showing <?= count($bookings) ?> bookings</span>
          <button class="btn btn-ghost" id="viewAllBookingsBtn"><i class="fas fa-table-list"></i> View All</button>
        </div>

        <div class="bookings-grid" id="bookingsGrid"></div>
      </div>

      <!-- TAB 3: CAMPING -->
      <div class="tab-panel" id="camping-tab">
        <div class="filter-bar">
          <select id="campingStatusFilter">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="finished">Finished</option>
            <option value="expired">Expired</option>
          </select>
          <button class="btn" id="applyCampingFilter"><i class="fas fa-filter"></i> Filter</button>
        </div>

        <div class="view-all-row">
          <span class="count-label">Showing <?= count($campingBookings) ?> camping bookings</span>
          <button class="btn btn-ghost" id="viewAllCampingBtn"><i class="fas fa-table-list"></i> View All</button>
        </div>

        <div class="camping-grid" id="campingGrid"></div>
      </div>

    </div><!-- /content -->
  </div>
</div>

<!-- MODALS (simplified for PHP) -->
<div class="modal-overlay" id="hikerModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-user"></i> <span id="hm_title">Hiker Profile</span></div>
      <button class="modal-close" data-close="hikerModal"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="hikerModalBody"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" data-close="hikerModal">Close</button>
    </div>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
const hikersData = <?php echo json_encode($hikers); ?>;
const bookingsData = <?php echo json_encode($bookings); ?>;
const campingData = <?php echo json_encode($campingBookings); ?>;

const skillColor = { Beginner: 'green', Intermediate: 'amber', Advanced: 'red' };
const dpColors = { paid: 'paid', pending: 'pending', expired: 'expired' };
const dpLabel = { paid: 'DP Paid', pending: 'DP Pending', expired: 'DP Expired' };
const bsLabel = { active: 'Active', finished: 'Finished', expired: 'Expired', pending: 'Pending', confirmed: 'Confirmed' };

function getInitials(name) {
  if (!name) return '?';
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

function getSkillColor(skill) {
  return skillColor[skill] || 'gray';
}

function formatCurrency(amount) {
  return '₱' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Render Hikers
function renderHikers(list) {
  const grid = document.getElementById('hikersGrid');
  if (!grid) return;
  grid.innerHTML = '';
  list.forEach(h => {
    const card = document.createElement('div');
    card.className = 'hiker-card';
    card.innerHTML = `
      <div class="hiker-avatar-row">
        <div class="hiker-avatar">${getInitials(h.name)}</div>
        <div>
          <div class="hiker-name">${escapeHtml(h.name)}</div>
          <div class="hiker-sub">${escapeHtml(h.last_mountain)} · ${h.last_date}</div>
        </div>
      </div>
      <div class="hiker-stats">
        <div class="hiker-stat">
          <div class="hiker-stat-label">Skill Level</div>
          <div class="hiker-stat-val"><span class="badge ${getSkillColor(h.skill)}">${h.skill}</span></div>
        </div>
        <div class="hiker-stat">
          <div class="hiker-stat-label">Total Trips</div>
          <div class="hiker-stat-val">${h.total_trips}</div>
        </div>
        <div class="hiker-stat">
          <div class="hiker-stat-label">Total Spent</div>
          <div class="hiker-stat-val">${formatCurrency(h.total_spent)}</div>
        </div>
        <div class="hiker-stat">
          <div class="hiker-stat-label">Member Since</div>
          <div class="hiker-stat-val">${formatDate(h.created_at)}</div>
        </div>
      </div>
      <div class="hiker-card-actions">
        <button class="btn viewHikerBtn" data-id="${h.id}" style="flex:2"><i class="fas fa-eye"></i> View Profile</button>
      </div>`;
    grid.appendChild(card);
  });

  document.querySelectorAll('.viewHikerBtn').forEach(btn =>
    btn.addEventListener('click', () => openHikerModal(parseInt(btn.dataset.id)))
  );
}

// Render Bookings
function renderBookings(list) {
  const grid = document.getElementById('bookingsGrid');
  if (!grid) return;
  grid.innerHTML = '';
  list.forEach(b => {
    const card = document.createElement('div');
    card.className = 'booking-card';
    card.innerHTML = `
      <div class="booking-card-header">
        <div>
          <div class="booking-id">${escapeHtml(b.booking_number || '#' + b.id)}</div>
          <div class="booking-hiker-name">${escapeHtml(b.hiker_name || 'Unknown')}</div>
          <div class="booking-mountain"><i class="fas fa-mountain"></i> ${escapeHtml(b.mountain_name || 'Unknown')}</div>
        </div>
        <span class="booking-status-chip ${getBookingChipClass(b.status)}">${getBookingStatusLabel(b.status)}</span>
      </div>
      <div class="booking-card-body">
        <div class="bc-field"><div class="bc-field-label">Tour Guide</div><div class="bc-field-val">${escapeHtml(b.guide_name || 'Not assigned')}</div></div>
        <div class="bc-field"><div class="bc-field-label">Hike Date</div><div class="bc-field-val">${formatDate(b.hike_date)}</div></div>
        <div class="bc-field"><div class="bc-field-label">Hike Type</div><div class="bc-field-val">${escapeHtml(b.hike_type || 'Day Hike')}</div></div>
        <div class="bc-field"><div class="bc-field-label">No. of Hikers</div><div class="bc-field-val">${b.number_of_hikers} pax</div></div>
        <div class="bc-field"><div class="bc-field-label">Total Amount</div><div class="bc-field-val">${formatCurrency(b.total_amount)}</div></div>
        <div class="bc-field"><div class="bc-field-label">DP Amount</div><div class="bc-field-val">${formatCurrency(b.downpayment_amount)}</div></div>
      </div>
      <div class="booking-card-footer">
        <div class="dp-status">
          <div class="dp-dot ${getDpDotClass(b.payment_status)}"></div>
          <span class="dp-label">${getDpStatusLabel(b.payment_status)}</span>
        </div>
        <button class="btn viewBookingBtn" data-id="${b.id}"><i class="fas fa-eye"></i> View</button>
      </div>`;
    grid.appendChild(card);
  });
}

// Render Camping
function renderCamping(list) {
  const grid = document.getElementById('campingGrid');
  if (!grid) return;
  grid.innerHTML = '';
  list.forEach(c => {
    const card = document.createElement('div');
    card.className = 'camping-card';
    card.innerHTML = `
      <div class="camping-card-top">
        <div class="camping-badge"><i class="fas fa-campground"></i> ${c.number_of_nights} Night${c.number_of_nights > 1 ? 's' : ''}</div>
        <div class="camping-name">${escapeHtml(c.hiker_name || 'Unknown')}</div>
        <div class="camping-mountain"><i class="fas fa-mountain"></i> ${escapeHtml(c.mountain_name || 'Unknown')}</div>
      </div>
      <div class="camping-card-body">
        <div><div class="cc-field-label">Check-in</div><div class="cc-field-val">${formatDate(c.start_date)}</div></div>
        <div><div class="cc-field-label">Check-out</div><div class="cc-field-val">${formatDate(c.end_date)}</div></div>
        <div><div class="cc-field-label">Guide</div><div class="cc-field-val">${escapeHtml(c.guide_name || 'Not assigned')}</div></div>
        <div><div class="cc-field-label">Hikers</div><div class="cc-field-val">${c.number_of_hikers} pax</div></div>
        <div><div class="cc-field-label">Campsite</div><div class="cc-field-val">${escapeHtml(c.campsite_name || 'Not specified')}</div></div>
        <div><div class="cc-field-label">Status</div><div class="cc-field-val">${getBookingStatusLabel(c.status)}</div></div>
      </div>
      <div class="camping-card-footer">
        <div class="dp-status">
          <div class="dp-dot ${getDpDotClass(c.payment_status)}"></div>
          <span class="dp-label">${getDpStatusLabel(c.payment_status)}</span>
        </div>
        <button class="btn viewCampingBtn" data-id="${c.id}"><i class="fas fa-eye"></i> View</button>
      </div>`;
    grid.appendChild(card);
  });
}

// Modal Functions
function openHikerModal(id) {
  const hiker = hikersData.find(h => h.id === id);
  if (!hiker) return;
  
  document.getElementById('hm_title').textContent = hiker.name;
  document.getElementById('hikerModalBody').innerHTML = `
    <div class="detail-section">
      <div class="detail-section-title">Personal Information</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-val">${escapeHtml(hiker.name)}</div></div>
        <div class="detail-item"><div class="detail-label">Email</div><div class="detail-val">${escapeHtml(hiker.email)}</div></div>
        <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-val">${escapeHtml(hiker.phone)}</div></div>
        <div class="detail-item"><div class="detail-label">Member Since</div><div class="detail-val">${formatDate(hiker.created_at)}</div></div>
        <div class="detail-item span2"><div class="detail-label">Emergency Contact</div><div class="detail-val">${escapeHtml(hiker.emergency)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Activity Summary</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Skill Level</div><div class="detail-val"><span class="badge ${getSkillColor(hiker.skill)}">${hiker.skill}</span></div></div>
        <div class="detail-item"><div class="detail-label">Total Trips</div><div class="detail-val">${hiker.total_trips}</div></div>
        <div class="detail-item"><div class="detail-label">Total Spent</div><div class="detail-val">${formatCurrency(hiker.total_spent)}</div></div>
        <div class="detail-item"><div class="detail-label">Last Mountain</div><div class="detail-val">${escapeHtml(hiker.last_mountain)}</div></div>
      </div>
    </div>`;
  openModal('hikerModal');
}

function openBookingModal(id) {
  const booking = bookingsData.find(b => b.id == id);
  if (!booking) {
    alert('Booking details not available');
    return;
  }
  alert(`📋 Booking Details\n\nID: ${booking.booking_number || booking.id}\nHiker: ${booking.hiker_name}\nMountain: ${booking.mountain_name}\nDate: ${formatDate(booking.hike_date)}\nStatus: ${getBookingStatusLabel(booking.status)}\nDP Status: ${getDpStatusLabel(booking.payment_status)}`);
}

function openCampingModal(id) {
  const camping = campingData.find(c => c.id == id);
  if (!camping) {
    alert('Camping booking details not available');
    return;
  }
  alert(`🏕️ Camping Details\n\nID: ${camping.booking_number || camping.id}\nHiker: ${camping.hiker_name}\nMountain: ${camping.mountain_name}\nCheck-in: ${formatDate(camping.start_date)}\nCheck-out: ${formatDate(camping.end_date)}\nCampsite: ${camping.campsite_name || 'Not specified'}\nStatus: ${getBookingStatusLabel(camping.status)}`);
}

function openModal(id) { 
  document.getElementById(id).classList.add('open'); 
}
function closeModal(id) { 
  document.getElementById(id).classList.remove('open'); 
}

// Helper functions
function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    return m;
  });
}

function getBookingChipClass(status) {
  const chips = { active: 'chip-active', finished: 'chip-finished', expired: 'chip-expired' };
  return chips[status] || 'chip-pending';
}

function getBookingStatusLabel(status) {
  const labels = { active: 'Active', finished: 'Finished', expired: 'Expired', pending: 'Pending', confirmed: 'Confirmed' };
  return labels[status] || status || 'Pending';
}

function getDpStatusLabel(status) {
  const labels = { paid: 'DP Paid', pending: 'DP Pending', expired: 'DP Expired' };
  return labels[status] || status || 'Pending';
}

function getDpDotClass(status) {
  return status || 'pending';
}

// Filter functions
document.getElementById('applyFilterBtn')?.addEventListener('click', () => {
  const search = document.getElementById('hikerSearch').value.toLowerCase();
  const skill = document.getElementById('skillFilter').value;
  const filtered = hikersData.filter(h => 
    (!search || h.name.toLowerCase().includes(search)) &&
    (!skill || h.skill === skill)
  );
  renderHikers(filtered);
  document.getElementById('hikerCountLabel').textContent = `Showing ${filtered.length} of ${hikersData.length} hikers`;
});

document.getElementById('resetFilterBtn')?.addEventListener('click', () => {
  document.getElementById('hikerSearch').value = '';
  document.getElementById('skillFilter').value = '';
  renderHikers(hikersData);
  document.getElementById('hikerCountLabel').textContent = `Showing ${hikersData.length} hikers`;
});

document.getElementById('applyBookingFilter')?.addEventListener('click', () => {
  const status = document.getElementById('bookingStatusFilter').value;
  const dpStatus = document.getElementById('dpStatusFilter').value;
  const filtered = bookingsData.filter(b => 
    (!status || b.status === status) && 
    (!dpStatus || b.payment_status === dpStatus)
  );
  renderBookings(filtered);
  document.getElementById('bookingCountLabel').textContent = `Showing ${filtered.length} of ${bookingsData.length} bookings`;
});

document.getElementById('applyCampingFilter')?.addEventListener('click', () => {
  const status = document.getElementById('campingStatusFilter').value;
  const filtered = status ? campingData.filter(c => c.status === status) : campingData;
  renderCamping(filtered);
});

// View All buttons
document.getElementById('viewAllHikersBtn')?.addEventListener('click', () => {
  alert(`📋 Total Hikers: ${hikersData.length}\n\nList available in full database view.`);
});

document.getElementById('viewAllBookingsBtn')?.addEventListener('click', () => {
  alert(`📋 Total Bookings: ${bookingsData.length}\n\nList available in full database view.`);
});

document.getElementById('viewAllCampingBtn')?.addEventListener('click', () => {
  alert(`🏕️ Total Camping Bookings: ${campingData.length}\n\nList available in full database view.`);
});

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

// Modal close handlers
document.querySelectorAll('.modal-close, [data-close]').forEach(el => {
  el.addEventListener('click', () => closeModal(el.dataset.close || el.closest('.modal-overlay')?.id));
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(overlay.id); });
});

// Navigation
document.querySelectorAll('.nav-item[data-href]').forEach(item => {
  item.addEventListener('click', () => { window.location.href = item.dataset.href; });
});

// Date display
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

// Initialize
renderHikers(hikersData);
renderBookings(bookingsData);
renderCamping(campingData);
</script>
</body>
</html>