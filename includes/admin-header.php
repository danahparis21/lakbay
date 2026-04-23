<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

// Get admin details for header
$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Get current page title (can be overridden by including file)
$currentPageTitle = $currentPageTitle ?? 'Dashboard';
$currentPageIcon = $currentPageIcon ?? 'fa-chart-line';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — <?= htmlspecialchars($currentPageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/pages/admin/shared.css">
</head>
<body data-page="<?= strtolower(str_replace(' ', '-', $currentPageTitle)) ?>">
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
        <li class="nav-item <?= $currentPageTitle == 'Dashboard' ? 'active' : '' ?>" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item <?= $currentPageTitle == 'Mountains' ? 'active' : '' ?>" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item <?= $currentPageTitle == 'Hikers' ? 'active' : '' ?>" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item <?= $currentPageTitle == 'Guides' ? 'active' : '' ?>" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item <?= $currentPageTitle == 'Revenue' || $currentPageTitle == 'Payments' ? 'active' : '' ?>" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item <?= $currentPageTitle == 'Alerts' ? 'active' : '' ?>" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item <?= $currentPageTitle == 'Analytics' ? 'active' : '' ?>" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
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
      <div class="page-heading"><i class="fas <?= $currentPageIcon ?>"></i> <?= htmlspecialchars($currentPageTitle) ?></div>
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