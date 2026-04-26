<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Manila');

// ── AUTH: require guide login ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── Get guide profile ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT g.id AS guide_id, g.specialization, g.years_experience, g.rating, g.total_trips,
           u.name, u.email, u.avatar, u.phone
    FROM guides g
    JOIN users u ON u.id = g.user_id
    WHERE g.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    die('Guide profile not found.');
}
$guide_id = $guide['guide_id'];

// ── Stats: Total bookings, money earned, pending payments ─────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_bookings,
           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as confirmed_bookings,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings
    FROM bookings
    WHERE guide_id = ? AND status != 'cancelled'
");
$stmt->execute([$guide_id]);
$booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total_earned,
           COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
           COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN downpayment_amount ELSE 0 END), 0) as pending_payments
    FROM bookings
    WHERE guide_id = ? AND status IN ('active', 'finished')
");
$stmt->execute([$guide_id]);
$earning_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ── All bookings ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id, b.booking_number, b.hike_date, b.hike_type,
           b.number_of_hikers, b.status, b.total_amount, b.downpayment_amount, b.payment_status,
           u.name AS hiker_name, u.id as hiker_user_id,
           m.name AS mountain_name, m.image AS mountain_image
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN mountains m ON m.id = b.mountain_id
    WHERE b.guide_id = ?
    ORDER BY b.hike_date DESC, b.created_at DESC
");
$stmt->execute([$guide_id]);
$all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Reviews ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, r.is_verified_purchase,
           u.name as reviewer_name, u.avatar as reviewer_avatar,
           b.booking_number, m.name as mountain_name
    FROM guide_reviews r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN bookings b ON b.id = r.booking_id
    LEFT JOIN mountains m ON m.id = b.mountain_id
    WHERE r.guide_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([$guide_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as total_reviews
    FROM guide_reviews
    WHERE guide_id = ? AND status = 'approved'
");
$stmt->execute([$guide_id]);
$rating_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$avg_rating = round($rating_stats['avg_rating'], 1);
$total_reviews = $rating_stats['total_reviews'];

// Avatar initials
$name_parts = explode(' ', $guide['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Helper functions
function fmt_date(string $d): string {
    return date('M j, Y', strtotime($d));
}

function get_payment_badge($status) {
    if ($status === 'paid') return '<span class="badge badge-green"><i class="fas fa-check-circle"></i> Paid</span>';
    if ($status === 'pending') return '<span class="badge badge-amber"><i class="fas fa-clock"></i> Pending</span>';
    return '<span class="badge badge-gray"><i class="fas fa-times-circle"></i> Unpaid</span>';
}

function get_status_badge($status) {
    if ($status === 'active') return '<span class="badge badge-green"><i class="fas fa-check-circle"></i> Confirmed</span>';
    if ($status === 'pending') return '<span class="badge badge-amber"><i class="fas fa-clock"></i> Pending</span>';
    if ($status === 'finished') return '<span class="badge badge-blue"><i class="fas fa-flag-checkered"></i> Finished</span>';
    if ($status === 'cancelled') return '<span class="badge badge-gray"><i class="fas fa-ban"></i> Cancelled</span>';
    return '<span class="badge">' . ucfirst($status) . '</span>';
}

function render_stars($rating) {
    $full = floor($rating);
    $half = $rating - $full >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
    $stars = '';
    for ($i = 0; $i < $full; $i++) $stars .= '<i class="fas fa-star" style="color:#f5b042;"></i>';
    if ($half) $stars .= '<i class="fas fa-star-half-alt" style="color:#f5b042;"></i>';
    for ($i = 0; $i < $empty; $i++) $stars .= '<i class="far fa-star" style="color:#f5b042;"></i>';
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY Guide — Bookings</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    .guide-main {
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .guide-content { 
      padding: 20px 24px; 
      flex: 1; 
      display: flex; 
      flex-direction: column; 
      overflow-y: auto;
      background:
        radial-gradient(ellipse 60% 40% at 80% 10%, rgba(16,6,0,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 10% 80%, rgba(16,6,0,0.03) 0%, transparent 60%);
    }

    /* Stats Row - Responsive */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 16px;
      box-shadow: var(--glass-shadow);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--r-sm);
      background: var(--primary-soft);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      margin-bottom: 10px;
    }
    .stat-card-label { 
      font-size: 0.65rem; 
      font-weight: 600; 
      text-transform: uppercase; 
      letter-spacing: 0.5px; 
      color: var(--ink-4); 
      margin-bottom: 6px; 
    }
    .stat-card-val { 
      font-family: 'Playfair Display', serif; 
      font-size: 1.5rem; 
      font-weight: 700; 
      color: var(--ink); 
      line-height: 1.2; 
    }
    .stat-card-sub { 
      font-size: 0.6rem; 
      color: var(--ink-4); 
      margin-top: 4px; 
    }
    
    .bookings-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 24px;
      margin-top: 24px;
      flex: 1;
      min-height: 0;
    }

    /* Bookings Container */
    .bookings-container {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: 0;
    }
    .section-header {
      padding: 16px 18px 12px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      font-weight: 700;
      color: var(--ink);
    }
    .filter-group {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .filter-btn {
      padding: 5px 12px;
      border-radius: 40px;
      background: var(--stone);
      border: 1px solid var(--line);
      font-size: 0.65rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s;
      color: var(--ink-3);
    }

    /* Mobile Booking Cards (hidden on desktop, shown on mobile) */
    .bookings-mobile-list {
      display: none;
      flex-direction: column;
      gap: 12px;
      padding: 16px;
    }
    .booking-mobile-card {
      background: white;
      border-radius: var(--r-lg);
      padding: 14px;
      border: 1px solid var(--line);
      box-shadow: var(--shadow-sm);
    }
    .booking-mobile-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }
    .booking-mobile-thumb {
      width: 50px;
      height: 50px;
      border-radius: var(--r-md);
      background-size: cover;
      background-position: center;
      flex-shrink: 0;
    }
    .booking-mobile-info {
      flex: 1;
    }
    .booking-mobile-mountain {
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--ink);
    }
    .booking-mobile-number {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      color: var(--ink-4);
      margin-top: 2px;
    }
    .booking-mobile-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid var(--stone-2);
      font-size: 0.75rem;
    }
    .booking-mobile-row:last-child {
      border-bottom: none;
    }
    .booking-mobile-label {
      color: var(--ink-4);
      font-weight: 500;
    }
    .booking-mobile-value {
      color: var(--ink);
      font-weight: 600;
    }
    .booking-mobile-actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
      flex-wrap: wrap;
    }
    .mobile-action-btn {
      flex: 1;
      padding: 8px;
      border-radius: 8px;
      background: var(--stone);
      border: 1px solid var(--line);
      font-size: 0.7rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all 0.2s;
    }

    /* Desktop Table */
    .bookings-table-wrapper {
      overflow-x: auto;
      flex: 1;
      display: block;
    }
    .bookings-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 700px;
    }
    .bookings-table th {
      text-align: left;
      padding: 12px 14px;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--ink-4);
      border-bottom: 1px solid var(--line);
      background: rgba(255,255,255,0.5);
    }
    .bookings-table td {
      padding: 14px;
      font-size: 0.78rem;
      color: var(--ink-2);
      border-bottom: 1px solid var(--line);
      vertical-align: middle;
    }
    .booking-actions-cell {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }
    .action-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: rgba(0,0,0,0.04);
      border: 1px solid var(--line);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      color: var(--ink-3);
    }
    .booking-info-cell {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .mountain-thumb {
      width: 36px;
      height: 36px;
      border-radius: var(--r-sm);
      background-size: cover;
      background-position: center;
      flex-shrink: 0;
    }

    /* Reviews Sidebar - Responsive */
    .reviews-sidebar {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .rating-summary {
      padding: 16px;
      text-align: center;
      border-bottom: 1px solid var(--line);
    }
    .rating-number {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 800;
      color: #f5b042;
      line-height: 1;
    }
    .rating-stars {
      font-size: 0.85rem;
      margin: 6px 0;
    }
    .reviews-list {
      flex: 1;
      overflow-y: auto;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .review-item {
      border-bottom: 1px solid var(--line);
      padding-bottom: 12px;
    }
    .review-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
    }
    .review-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-soft);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.8rem;
    }
    .reviewer-name {
      font-weight: 600;
      font-size: 0.75rem;
      color: var(--ink);
    }
    .review-meta {
      font-size: 0.6rem;
      color: var(--ink-4);
    }
    .verified-badge {
      display: inline-block;
      background: rgba(30, 123, 72, 0.1);
      color: #1E7B48;
      font-size: 0.55rem;
      padding: 2px 6px;
      border-radius: 20px;
      margin-left: 6px;
    }
    .review-stars {
      font-size: 0.65rem;
      margin-bottom: 5px;
    }
    .review-comment {
      font-size: 0.7rem;
      color: var(--ink-2);
      line-height: 1.45;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 8px;
      border-radius: 40px;
      font-size: 0.6rem;
      font-weight: 600;
    }
    .badge-green { background: rgba(30, 123, 72, 0.1); color: #1E7B48; }
    .badge-amber { background: rgba(230, 126, 34, 0.1); color: #c96a10; }
    .badge-blue { background: rgba(52, 89, 149, 0.1); color: #345995; }
    .badge-gray { background: #eef2f8; color: #6e7483; }

    /* Mobile Responsive */
    @media (max-width: 1100px) {
      .bookings-layout { 
        grid-template-columns: 1fr;
        gap: 24px;
      }
      .reviews-sidebar { width: 100%; }
    }

    @media (max-width: 900px) {
      .guide-content { padding: 16px; }
      .stats-row { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 12px;
        margin-bottom: 20px;
      }
      /* Hide desktop table on mobile */
      .bookings-table-wrapper { display: none; }
      /* Show mobile cards */
      .bookings-mobile-list { display: flex; }
    }
    
    @media (max-width: 480px) {
      .guide-content { padding: 12px; }
      .stats-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .stat-card { padding: 10px; }
      .stat-card-val { font-size: 1.1rem; }
      .stat-card-label { font-size: 0.6rem; }
      .section-header { flex-direction: column; align-items: stretch; gap: 12px; }
      .filter-group { justify-content: flex-start; overflow-x: auto; padding-bottom: 4px; }
      .filter-btn { white-space: nowrap; }
      .booking-mobile-actions { flex-direction: column; }
      .mobile-action-btn { width: 100%; }
    }

    /* Modal styles */
    .detail-section { margin-bottom: 20px; }
    .detail-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--ink-4);
      padding-bottom: 6px;
      border-bottom: 1px solid var(--line);
      margin-bottom: 12px;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .detail-item { display: flex; flex-direction: column; gap: 3px; }
    .detail-label { font-size: 0.6rem; font-weight: 600; color: var(--ink-4); text-transform: uppercase; }
    .detail-val { font-size: 0.8rem; color: var(--ink); font-weight: 500; }

    .btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-primary { background: #100600; color: white; border: none; }
    .btn-ghost { background: transparent; border: 1px solid var(--line); }
    .btn-danger { background: var(--red); color: white; border: none; }

    .toast {
      position: fixed;
      bottom: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #100600;
      color: white;
      padding: 10px 20px;
      border-radius: 40px;
      font-size: 0.8rem;
      z-index: 9999;
      opacity: 0;
      transition: 0.25s;
      pointer-events: none;
      white-space: nowrap;
    }
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
  </style>
</head>
<body>
<div class="guide-app">
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="white" opacity="0.9"/><path d="M14 16L18 8L24 22H14V16Z" fill="white" opacity="0.3"/></svg>
      <div><div class="sidebar-logo-text">LAKBAY</div><div class="sidebar-logo-sub">Guide Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.php"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.php"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.php"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-bookings.php" class="active"><i class="fas fa-shield-halved"></i> Bookings</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar"><?= $initials ?></div>
      <div class="sidebar-profile-info"><div class="sidebar-profile-name"><?= htmlspecialchars($guide['name']) ?></div><div class="sidebar-profile-role"><?= htmlspecialchars($guide['specialization'] ?? 'Trail Guide') ?></div></div>
    </div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Bookings</div>
      <div class="topbar-right">
        <div class="topbar-time" id="liveTime"></div>
        <button class="topbar-icon-btn" onclick="refreshPage()"><i class="fas fa-rotate-right"></i></button>
      </div>
    </div>

    <div class="guide-content">

      <!-- STATS CARDS -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
          <div class="stat-card-content">
            <div class="stat-card-label">Total Bookings</div>
            <div class="stat-card-val"><?= (int)$booking_stats['total_bookings'] ?></div>
            <div class="stat-card-sub"><?= (int)$booking_stats['confirmed_bookings'] ?> confirmed · <?= (int)$booking_stats['pending_bookings'] ?> pending</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-coins"></i></div>
          <div class="stat-card-content">
            <div class="stat-card-label">Total Earned</div>
            <div class="stat-card-val">₱<?= number_format($earning_stats['total_earned'], 0) ?></div>
            <div class="stat-card-sub">from completed hikes</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-wallet"></i></div>
          <div class="stat-card-content">
            <div class="stat-card-label">Paid Amount</div>
            <div class="stat-card-val">₱<?= number_format($earning_stats['paid_amount'], 0) ?></div>
            <div class="stat-card-sub">successful payments</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div>
          <div class="stat-card-content">
            <div class="stat-card-label">Pending Payments</div>
            <div class="stat-card-val">₱<?= number_format($earning_stats['pending_payments'], 0) ?></div>
            <div class="stat-card-sub">awaiting settlement</div>
          </div>
        </div>
      </div>

      <!-- MAIN LAYOUT -->
      <div class="bookings-layout">
        
        <!-- LEFT: Bookings -->
        <div class="bookings-container">
          <div class="section-header">
            <div class="section-title">All Bookings</div>
            <div class="filter-group">
              <button class="filter-btn active" data-filter="all" onclick="filterBookings('all')">All</button>
              <button class="filter-btn" data-filter="pending" onclick="filterBookings('pending')">Pending</button>
              <button class="filter-btn" data-filter="active" onclick="filterBookings('active')">Confirmed</button>
              <button class="filter-btn" data-filter="finished" onclick="filterBookings('finished')">Finished</button>
            </div>
          </div>
          
          <!-- DESKTOP TABLE VIEW -->
          <div class="bookings-table-wrapper">
            <table class="bookings-table" id="bookingsTable">
              <thead>
                <tr><th>Booking</th><th>Hiker</th><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Payment</th><th>Actions</th></tr>
              </thead>
              <tbody id="bookingsTableBody">
                <?php foreach ($all_bookings as $bk): ?>
                <tr data-status="<?= $bk['status'] ?>">
                  <td>
                    <div class="booking-info-cell">
                      <div class="mountain-thumb" style="background-image:url('<?= htmlspecialchars($bk['mountain_image'] ?? 'https://images.unsplash.com/photo-1613144492511-59984f1cdeb3?w=60') ?>');"></div>
                      <div class="booking-details">
                        <div class="booking-mountain"><?= htmlspecialchars($bk['mountain_name']) ?></div>
                        <div class="booking-number">#<?= htmlspecialchars($bk['booking_number']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><strong><?= htmlspecialchars($bk['hiker_name']) ?></strong></td>
                  <td><?= fmt_date($bk['hike_date']) ?></td>
                  <td><?= ucfirst(str_replace('_', ' ', $bk['hike_type'])) ?></td>
                  <td>₱<?= number_format($bk['total_amount'], 0) ?></td>
                  <td><?= get_status_badge($bk['status']) ?></td>
                  <td><?= get_payment_badge($bk['payment_status']) ?></td>
                  <td>
                    <div class="booking-actions-cell">
                      <button class="action-icon action-view" onclick="viewBookingDetails(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-eye"></i></button>
                      <button class="action-icon action-chat" onclick="chatWithHiker(<?= $bk['hiker_user_id'] ?>, '<?= htmlspecialchars($bk['hiker_name']) ?>')"><i class="fas fa-comment-dots"></i></button>
                      <?php if ($bk['status'] === 'pending'): ?>
                      <button class="action-icon action-approve" onclick="confirmBooking(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-check-circle"></i></button>
                      <button class="action-icon action-cancel" onclick="showCancelModal(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-times-circle"></i></button>
                      <?php endif; ?>
                      <?php if ($bk['payment_status'] !== 'paid' && $bk['status'] === 'active'): ?>
                      <button class="action-icon action-payment" onclick="requestPayment(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>', <?= $bk['total_amount'] ?>, <?= $bk['downpayment_amount'] ?? 0 ?>)"><i class="fas fa-credit-card"></i></button>
                      <?php endif; ?>
                    </div>
                   </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($all_bookings)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;">No bookings yet</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- MOBILE CARDS VIEW -->
          <div class="bookings-mobile-list" id="bookingsMobileList">
            <?php foreach ($all_bookings as $bk): ?>
            <div class="booking-mobile-card" data-status="<?= $bk['status'] ?>">
              <div class="booking-mobile-header">
                <div class="booking-mobile-thumb" style="background-image:url('<?= htmlspecialchars($bk['mountain_image'] ?? 'https://images.unsplash.com/photo-1613144492511-59984f1cdeb3?w=60') ?>');"></div>
                <div class="booking-mobile-info">
                  <div class="booking-mobile-mountain"><?= htmlspecialchars($bk['mountain_name']) ?></div>
                  <div class="booking-mobile-number">#<?= htmlspecialchars($bk['booking_number']) ?></div>
                </div>
              </div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Hiker:</span><span class="booking-mobile-value"><?= htmlspecialchars($bk['hiker_name']) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Date:</span><span class="booking-mobile-value"><?= fmt_date($bk['hike_date']) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Type:</span><span class="booking-mobile-value"><?= ucfirst(str_replace('_', ' ', $bk['hike_type'])) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Amount:</span><span class="booking-mobile-value">₱<?= number_format($bk['total_amount'], 0) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Status:</span><span class="booking-mobile-value"><?= get_status_badge($bk['status']) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Payment:</span><span class="booking-mobile-value"><?= get_payment_badge($bk['payment_status']) ?></span></div>
              <div class="booking-mobile-actions">
                <button class="mobile-action-btn" onclick="viewBookingDetails(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-eye"></i> View</button>
                <button class="mobile-action-btn" onclick="chatWithHiker(<?= $bk['hiker_user_id'] ?>, '<?= htmlspecialchars($bk['hiker_name']) ?>')"><i class="fas fa-comment-dots"></i> Chat</button>
                <?php if ($bk['status'] === 'pending'): ?>
                <button class="mobile-action-btn" style="background:var(--green-lt);color:var(--green);" onclick="confirmBooking(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-check-circle"></i> Approve</button>
                <button class="mobile-action-btn" style="background:var(--red-lt);color:var(--red);" onclick="showCancelModal(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-times-circle"></i> Cancel</button>
                <?php endif; ?>
                <?php if ($bk['payment_status'] !== 'paid' && $bk['status'] === 'active'): ?>
                <button class="mobile-action-btn" style="background:#f5b04220;color:#e67e22;" onclick="requestPayment(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>', <?= $bk['total_amount'] ?>, <?= $bk['downpayment_amount'] ?? 0 ?>)"><i class="fas fa-credit-card"></i> Payment</button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($all_bookings)): ?>
            <div style="text-align:center;padding:40px;color:var(--ink-4);">No bookings yet</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT: Reviews Sidebar -->
        <div class="reviews-sidebar">
          <div class="rating-summary">
            <div class="rating-number"><?= $avg_rating ?></div>
            <div class="rating-stars"><?= render_stars($avg_rating) ?></div>
            <div class="rating-count">Based on <?= $total_reviews ?> <?= $total_reviews === 1 ? 'review' : 'reviews' ?></div>
          </div>
          <div class="reviews-list">
            <?php if (empty($reviews)): ?>
              <div class="no-reviews" style="text-align:center;padding:30px;color:var(--ink-4);">
                <i class="fas fa-star" style="font-size:2rem;opacity:0.3;margin-bottom:10px;display:block;"></i>
                <p>No reviews yet</p>
              </div>
            <?php else: ?>
              <?php foreach ($reviews as $review): ?>
              <div class="review-item">
                <div class="review-header">
                  <div class="review-avatar"><?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?></div>
                  <div class="review-info">
                    <div class="reviewer-name">
                      <?= htmlspecialchars($review['reviewer_name']) ?>
                      <?php if ($review['is_verified_purchase'] == 1): ?>
                      <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                      <?php endif; ?>
                    </div>
                    <div class="review-meta"><?= fmt_date($review['created_at']) ?> · <?= htmlspecialchars($review['mountain_name'] ?? 'Hike') ?></div>
                  </div>
                </div>
                <div class="review-stars"><?= render_stars($review['rating']) ?></div>
                <div class="review-comment"><?= htmlspecialchars($review['comment'] ?? 'No comment provided.') ?></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>

  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.php" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.php" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.php" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-bookings.php" class="bnav-item active"><i class="fas fa-shield-halved"></i><span>Bookings</span></a>
      <a href="guide-profile.php" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<!-- Modals (same as before) -->
<div class="modal-overlay" id="bookingDetailsModal">
  <div class="modal" style="max-width: 650px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-calendar-check"></i> Booking Details</div>
      <button class="modal-close" onclick="closeModal('bookingDetailsModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="bookingDetailsBody"></div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="closeModal('bookingDetailsModal')">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="cancelModal">
  <div class="modal" style="max-width: 450px;">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);"><i class="fas fa-times-circle"></i> Cancel Booking</div>
      <button class="modal-close" onclick="closeModal('cancelModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <form id="cancelForm" onsubmit="cancelBooking(event)">
      <input type="hidden" id="cancelBookingId" value="">
      <div class="modal-body">
        <p>Cancel booking <strong id="cancelBookingNumber"></strong>?</p>
        <textarea id="cancelReason" class="form-control" rows="3" placeholder="Reason..." required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('cancelModal')">Keep</button>
        <button type="submit" class="btn btn-danger">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CURRENT_USER_ID = <?= json_encode($user_id) ?>;

function filterBookings(status) {
  // Desktop filter
  const rows = document.querySelectorAll('#bookingsTableBody tr');
  // Mobile filter
  const mobileCards = document.querySelectorAll('.booking-mobile-card');
  const buttons = document.querySelectorAll('.filter-btn');
  
  buttons.forEach(btn => {
    if (btn.getAttribute('data-filter') === status) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
  
  // Desktop
  rows.forEach(row => {
    if (status === 'all') {
      row.style.display = '';
    } else {
      row.style.display = row.getAttribute('data-status') === status ? '' : 'none';
    }
  });
  
  // Mobile
  mobileCards.forEach(card => {
    if (status === 'all') {
      card.style.display = '';
    } else {
      card.style.display = card.getAttribute('data-status') === status ? '' : 'none';
    }
  });
}

async function viewBookingDetails(bookingId, bookingNumber) {
  showToast('Loading...');
  try {
    const res = await fetch('../api/guide_messages.php?action=get_booking_details&booking_id=' + bookingId);
    const data = await res.json();
    if (data.success) {
      const bk = data.booking;
      document.getElementById('bookingDetailsBody').innerHTML = `
        <div class="detail-section"><div class="detail-section-title">Booking Info</div>
        <div class="detail-grid">
          <div class="detail-item"><div class="detail-label">Booking #</div><div class="detail-val">${escapeHtml(bk.booking_number)}</div></div>
          <div class="detail-item"><div class="detail-label">Status</div><div class="detail-val">${bk.status}</div></div>
          <div class="detail-item"><div class="detail-label">Mountain</div><div class="detail-val">${escapeHtml(bk.mountain_name)}</div></div>
          <div class="detail-item"><div class="detail-label">Date</div><div class="detail-val">${new Date(bk.hike_date).toLocaleDateString()}</div></div>
          <div class="detail-item"><div class="detail-label">Hikers</div><div class="detail-val">${bk.number_of_hikers} pax</div></div>
          <div class="detail-item"><div class="detail-label">Total</div><div class="detail-val">₱${parseFloat(bk.total_amount).toLocaleString()}</div></div>
        </div></div>
        <div class="detail-section"><div class="detail-section-title">Hiker</div>
        <div class="detail-grid">
          <div class="detail-item"><div class="detail-label">Name</div><div class="detail-val">${escapeHtml(bk.hiker_name)}</div></div>
          <div class="detail-item"><div class="detail-label">Email</div><div class="detail-val">${escapeHtml(bk.hiker_email || 'N/A')}</div></div>
        </div></div>`;
      openModal('bookingDetailsModal');
    } else showToast('Error loading details');
  } catch(e) { showToast('Network error'); }
}

function confirmBooking(bookingId, bookingNumber) {
  if (confirm(`Confirm booking ${bookingNumber}?`)) {
    showToast('Confirming...');
    fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'confirm_booking', booking_id: bookingId })
    }).then(r => r.json()).then(data => {
      showToast(data.message);
      if (data.success) setTimeout(() => location.reload(), 1200);
    }).catch(() => showToast('Error'));
  }
}

function showCancelModal(bookingId, bookingNumber) {
  document.getElementById('cancelBookingId').value = bookingId;
  document.getElementById('cancelBookingNumber').textContent = bookingNumber;
  openModal('cancelModal');
}

function cancelBooking(event) {
  event.preventDefault();
  const bookingId = document.getElementById('cancelBookingId').value;
  const reason = document.getElementById('cancelReason').value;
  showToast('Cancelling...');
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'cancel_booking', booking_id: bookingId, cancel_reason: reason })
  }).then(r => r.json()).then(data => {
    showToast(data.message);
    if (data.success) setTimeout(() => location.reload(), 1200);
  }).catch(() => showToast('Error'));
  closeModal('cancelModal');
}

function requestPayment(bookingId, bookingNumber, totalAmount, downpayment) {
  const remaining = totalAmount - downpayment;
  if (confirm(`Request ₱${remaining.toLocaleString()} payment for ${bookingNumber}?`)) {
    showToast('Request sent!');
  }
}

function chatWithHiker(hikerId, hikerName) {
  window.location.href = `guide-communication.php?user=${hikerId}&name=${encodeURIComponent(hikerName)}`;
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function refreshPage() { location.reload(); }
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}
function updateTime() {
  document.getElementById('liveTime').textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}
updateTime();
setInterval(updateTime, 1000);
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target === o) closeModal(o.id); }));

const viewBookingId = new URLSearchParams(location.search).get('view_booking');
if (viewBookingId) setTimeout(() => viewBookingDetails(viewBookingId, ''), 500);
</script>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_booking') {
        $booking_id = $_POST['booking_id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'active' WHERE id = ? AND guide_id = ?");
        $success = $stmt->execute([$booking_id, $guide_id]) && $stmt->rowCount() > 0;
        echo json_encode(['success' => $success, 'message' => $success ? 'Booking confirmed' : 'Failed']);
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
        $booking_id = $_POST['booking_id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND guide_id = ?");
        $success = $stmt->execute([$booking_id, $guide_id]) && $stmt->rowCount() > 0;
        echo json_encode(['success' => $success, 'message' => $success ? 'Booking cancelled' : 'Failed']);
        exit;
    }
}
?>
</body>
</html>