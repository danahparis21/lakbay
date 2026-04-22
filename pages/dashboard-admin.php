<?php
$pageTitle  = 'Tourism Admin Dashboard — LAKBAY';
$activePage = '';
$isLoggedIn = true;
$userRole   = 'tourism';
$base       = '../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
$adminName = 'Admin Reyes';
$totalBookings = count($bookings);
$pendingCount  = count(array_filter($bookings,fn($b)=>$b['status']==='pending'));
$completedCount= count(array_filter($bookings,fn($b)=>$b['status']==='completed'));
?>

<<<<<<< HEAD
<!-- ── Mountain Card Styles (Integrated from original HTML) ────────────────── -->
<style>
/* ── Variables ─────────────────────────────────────── */
:root {
  --ink:       #0d0b09;
  --ink-deep:  #1a1612;
  --ink-mid:   #2e2824;
  --ink-soft:  #5a524a;
  --ink-ghost: #a09690;
  --white:     #fdfcfb;
  --off:       #f5f3f0;
  --cream:     #ede9e3;
  --cream-d:   #e0d9cf;
  --gold:      #c9a96e;

  --font-d: 'Cormorant Garamond', Georgia, serif;
  --font-b: 'Outfit', system-ui, sans-serif;

  --ease: cubic-bezier(0.22, 1, 0.36, 1);
  --r-card: 22px;
  --r-img:  18px;
  --r-btn:  999px;   /* pill buttons like reference */
  --shadow-card: 0 2px 6px rgba(13,11,9,.04), 0 12px 40px rgba(13,11,9,.10);
  --shadow-lift: 0 4px 12px rgba(13,11,9,.06), 0 24px 64px rgba(13,11,9,.16);
}

/* ── Mountain Card ──────────────────────────────────── */
.mtn-card {
  background: var(--white);
  border-radius: var(--r-card);
  box-shadow: var(--shadow-card);
  overflow: hidden;
  transition: box-shadow 0.35s var(--ease), transform 0.35s var(--ease);
  cursor: default;
  display: flex;
  flex-direction: column;
}
.mtn-card:hover {
  box-shadow: var(--shadow-lift);
  transform: translateY(-4px);
}

/* ── Image section ──────────────────────────────────── */
.mtn-img-wrap {
  position: relative;
  border-radius: var(--r-img);
  overflow: hidden;
  margin: 10px 10px 0;
  aspect-ratio: 4/3;
  flex-shrink: 0;
}
.mtn-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.6s var(--ease), filter 0.4s ease;
  filter: saturate(0.82);
}
.mtn-card:hover .mtn-img-wrap img {
  transform: scale(1.04);
  filter: saturate(1);
}

/* Gradient overlay */
.mtn-img-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    transparent 30%,
    rgba(13,11,9,0.78) 100%
  );
  border-radius: var(--r-img);
}

/* Name + CTA pinned to bottom of image */
.mtn-img-footer {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  padding: 0.9rem 1rem;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.6rem;
}
.mtn-img-name {
  flex: 1;
  min-width: 0;
}
.mtn-img-name strong {
  display: block;
  font-size: 0.92rem;
  font-weight: 500;
  color: var(--white);
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mtn-img-name span {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.55);
  font-weight: 300;
  letter-spacing: 0.02em;
}

/* Directions pill button — matches reference */
.btn-directions {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.5rem 1rem;
  border-radius: var(--r-btn);
  background: rgba(30,22,18,0.75);
  backdrop-filter: blur(10px) saturate(1.4);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.14);
  color: rgba(255,255,255,0.92);
  font-family: var(--font-b);
  font-size: 0.72rem;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  cursor: pointer;
  transition: background 0.25s var(--ease), border-color 0.25s var(--ease), transform 0.2s var(--ease);
  white-space: nowrap;
}
.btn-directions:hover {
  background: rgba(13,11,9,0.92);
  border-color: rgba(255,255,255,0.28);
  transform: scale(1.03);
}
.btn-directions:active { transform: scale(0.97); }
.btn-directions svg { width: 12px; height: 12px; }

/* ── Info section ───────────────────────────────────── */
.mtn-info {
  padding: 0.9rem 1rem 0.75rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

/* Difficulty + credit row */
.mtn-meta-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}
.mtn-diff-block {}
.mtn-diff {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ink-deep);
  line-height: 1;
  margin-bottom: 0.18rem;
}
.mtn-credit {
  font-size: 0.65rem;
  color: var(--ink-ghost);
  font-weight: 300;
  line-height: 1.3;
}

/* Trail map circle — like reference */
.mtn-trail-map {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: var(--off);
  border: 1px solid var(--cream-d);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.mtn-trail-map svg {
  width: 38px;
  height: 38px;
}

/* ── Stat trio ──────────────────────────────────────── */
.mtn-stats {
  display: flex;
  gap: 0;
  border-top: 1px solid var(--cream);
  padding-top: 0.65rem;
  margin-top: auto;
}
.mtn-stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding-right: 0.5rem;
}
.mtn-stat + .mtn-stat {
  padding-left: 0.6rem;
  border-left: 1px solid var(--cream);
}
.mtn-stat-val {
  font-family: var(--font-d);
  font-size: 1.05rem;
  font-weight: 400;
  color: var(--ink-deep);
  line-height: 1;
}
.mtn-stat-label {
  font-size: 0.62rem;
  font-weight: 400;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-ghost);
}

/* ── Action row ─────────────────────────────────────── */
.mtn-actions {
  display: flex;
  gap: 0.4rem;
  padding: 0 1rem 1rem;
}

/* Shared action button base */
.mtn-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.55rem 0.5rem;
  border-radius: 999px;
  font-family: var(--font-b);
  font-size: 0.68rem;
  font-weight: 500;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  cursor: pointer;
  border: none;
  outline: none;
  transition: background 0.22s var(--ease), color 0.22s var(--ease), transform 0.18s var(--ease), box-shadow 0.22s var(--ease);
  white-space: nowrap;
  position: relative;
  overflow: hidden;
}
.mtn-btn:active { transform: scale(0.96); }
/* Shimmer */
.mtn-btn::after {
  content: '';
  position: absolute;
  top: 0; left: -80%;
  width: 50%; height: 100%;
  background: linear-gradient(105deg, transparent, rgba(255,255,255,0.18), transparent);
  transition: left 0.45s var(--ease);
  pointer-events: none;
}
.mtn-btn:hover::after { left: 130%; }

/* Edit — outline */
.mtn-btn-edit {
  background: transparent;
  color: var(--ink-soft);
  border: 1px solid var(--cream-d);
}
.mtn-btn-edit:hover {
  background: var(--off);
  color: var(--ink-deep);
  border-color: var(--ink-soft);
}

/* Alert — dark pill */
.mtn-btn-alert {
  background: var(--ink-deep);
  color: rgba(255,255,255,0.9);
  border: 1px solid var(--ink-deep);
}
.mtn-btn-alert:hover {
  background: var(--ink-mid);
  box-shadow: 0 4px 14px rgba(13,11,9,0.22);
}
.mtn-btn svg { width: 11px; height: 11px; flex-shrink: 0; }

/* Difficulty badges */
.diff-easy   { color: #2a4a1e; }
.diff-moderate { color: #4a3a10; }
.diff-hard   { color: #6a1a0a; }
.diff-extreme { color: #1a0a2a; }

/* Mountain Grid Override */
.mountain-grid-new {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
}
@media (max-width: 1024px) {
  .mountain-grid-new { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 580px) {
  .mountain-grid-new { grid-template-columns: 1fr; max-width: 360px; margin: 0 auto; }
}
</style>

=======
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
<div class="dashboard-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user">
<<<<<<< HEAD
      <div class="sidebar-avatar">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
      </div>
=======
      <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      <div class="sidebar-username"><?= htmlspecialchars($adminName) ?></div>
      <div class="sidebar-role">Tourism Admin</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <div class="sidebar-section-title">Administration</div>
<<<<<<< HEAD
        <a href="dashboard-admin.php" class="sidebar-link active">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          Overview
        </a>
        <a href="#users" class="sidebar-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          User Management
        </a>
        <a href="#mountains" class="sidebar-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>
          Mountain Oversight
        </a>
        <a href="#bookings" class="sidebar-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          All Bookings
        </a>
        <a href="#reports" class="sidebar-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
          Reports
        </a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Account</div>
        <a href="profile.php" class="sidebar-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          My Profile
        </a>
        <a href="modals/logout.php" class="sidebar-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Log Out
        </a>
=======
        <a href="dashboard-admin.php" class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Overview</a>
        <a href="#users"              class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>User Management</a>
        <a href="#mountains"          class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Mountain Oversight</a>
        <a href="#bookings"           class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>All Bookings</a>
        <a href="#reports"            class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>Reports</a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Account</div>
        <a href="profile.php"       class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>My Profile</a>
        <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      </div>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="dashboard-main">
<<<<<<< HEAD

    <!-- Page Header -->
    <div class="dashboard-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1>Tourism Dashboard</h1>
        <p>Nasugbu Hiking Trail Management — <?= date('F Y') ?></p>
      </div>
      <!-- Primary action: icon + label, standard size -->
=======
    <div class="dashboard-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1>Tourism Admin Dashboard</h1>
        <p>Nasugbu Hiking Trail Management System — <?= date('F Y') ?></p>
      </div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      <button class="btn btn-primary" onclick="showToast('Report downloaded!');">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download Report
      </button>
    </div>

<<<<<<< HEAD
    <!-- Stats Row -->
    <div class="stats-row">
      <div class="stat-card stat-teal">
        <div class="stat-card-label">Registered Hikers</div>
        <div class="stat-card-value">1,245</div>
        <div class="stat-card-sub">+12% this month</div>
        <div class="stat-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
=======
    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card stat-teal">
        <div class="stat-card-label">Total Hikers (Registered)</div>
        <div class="stat-card-value">1,245</div>
        <div class="stat-card-sub">+12% this month</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      </div>
      <div class="stat-card stat-moss">
        <div class="stat-card-label">Active Guides</div>
        <div class="stat-card-value"><?= count($guides) ?></div>
        <div class="stat-card-sub">Verified &amp; active</div>
<<<<<<< HEAD
        <div class="stat-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
=======
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      </div>
      <div class="stat-card stat-sage">
        <div class="stat-card-label">Total Bookings</div>
        <div class="stat-card-value"><?= $totalBookings ?></div>
        <div class="stat-card-sub"><?= $pendingCount ?> pending review</div>
<<<<<<< HEAD
        <div class="stat-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-card-label">Est. Revenue</div>
        <div class="stat-card-value">&#8369;45K</div>
        <div class="stat-card-sub">This month</div>
        <div class="stat-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
=======
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-card-label">Revenue (Est.)</div>
        <div class="stat-card-value">&#8369;45K</div>
        <div class="stat-card-sub">This month</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" class="admin-charts-grid">
<<<<<<< HEAD

      <!-- Booking Trends -->
      <div class="card">
        <div class="card-header"><h3>Booking Trends</h3></div>
=======
      <!-- Booking Trends -->
      <div class="card">
        <div class="card-header"><h3>Booking Trends (6 Months)</h3></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
        <div class="card-body">
          <div style="display:flex;align-items:flex-end;gap:0.6rem;height:160px;padding-top:1rem;">
            <?php
            $months=['Jan'=>45,'Feb'=>52,'Mar'=>48,'Apr'=>65,'May'=>78,'Jun'=>61];
            $mx=max($months);
            foreach($months as $mo=>$v):
              $h=round(($v/$mx)*130);
            ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;flex:1;">
<<<<<<< HEAD
              <span style="font-size:0.66rem;color:var(--ink-ghost);font-weight:500;font-family:var(--font-body);"><?= $v ?></span>
              <div style="width:100%;height:<?= $h ?>px;background:var(--ink-deep);border-radius:2px 2px 0 0;opacity:0.75;transition:opacity 0.2s;" onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0.75'"></div>
              <span style="font-size:0.66rem;color:var(--ink-ghost);font-family:var(--font-body);letter-spacing:0.05em;"><?= $mo ?></span>
=======
              <span style="font-size:0.68rem;color:var(--teal);font-weight:600;"><?= $v ?></span>
              <div style="width:100%;height:<?= $h ?>px;background:var(--teal);border-radius:4px 4px 0 0;opacity:0.8;"></div>
              <span style="font-size:0.68rem;color:var(--teal);"><?= $mo ?></span>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Mountain Distribution -->
      <div class="card">
<<<<<<< HEAD
        <div class="card-header"><h3>Hiker Distribution</h3></div>
        <div class="card-body">
          <?php
          $dist=[['Mt. Batulao',120],['Mt. Talamitam',95],['Mt. Apayang',85],['Mt. Lantik',45]];
          $dtotal = array_sum(array_column($dist,1));
          $alphas = ['1','0.7','0.45','0.25'];
          foreach($dist as $i=>[$dn,$dv]): $pct=round($dv/$dtotal*100); ?>
          <div style="margin-bottom:0.85rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem;">
              <span style="font-size:0.82rem;color:var(--ink);font-weight:400;"><?= $dn ?></span>
              <span style="font-size:0.72rem;color:var(--ink-ghost);font-family:var(--font-body);letter-spacing:0.04em;"><?= $dv ?> · <?= $pct ?>%</span>
            </div>
            <div style="height:3px;background:var(--cream-deep);border-radius:999px;overflow:hidden;">
              <div style="height:100%;width:<?= $pct ?>%;background:var(--ink-deep);opacity:<?= $alphas[$i] ?>;border-radius:999px;transition:width 0.6s var(--ease);"></div>
=======
        <div class="card-header"><h3>Hiker Distribution by Mountain</h3></div>
        <div class="card-body">
          <?php
          $dist=[['Mt. Batulao',120,'var(--teal)'],['Mt. Talamitam',95,'var(--moss)'],['Mt. Apayang',85,'var(--sage)'],['Mt. Lantik',45,'var(--blue)']];
          $dtotal = array_sum(array_column($dist,1));
          foreach($dist as [$dn,$dv,$dc]): $pct=round($dv/$dtotal*100); ?>
          <div style="margin-bottom:0.75rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.25rem;">
              <span style="color:var(--navy);font-weight:500;"><?= $dn ?></span>
              <span style="color:var(--teal);"><?= $dv ?> hikers (<?= $pct ?>%)</span>
            </div>
            <div style="height:8px;background:var(--sky);border-radius:999px;overflow:hidden;">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $dc ?>;border-radius:999px;"></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- User Management -->
    <div class="card" id="users" style="margin-bottom:1.5rem;">
<<<<<<< HEAD
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <h3>User Management</h3>
        <div style="display:flex;gap:0.6rem;align-items:center;">
          <!-- Ghost search input -->
          <div class="input-icon-wrap">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="form-control" type="text" placeholder="Search users…"
              style="padding-top:0.45rem;padding-bottom:0.45rem;font-size:0.82rem;width:200px;">
          </div>
          <!-- Small primary: add user -->
          <button class="btn btn-primary btn-sm" onclick="showToast('Add user feature coming soon.');">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add User
          </button>
=======
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>User Management</h3>
        <div style="display:flex;gap:0.5rem;">
          <div class="input-icon-wrap"><svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input class="form-control form-control-sm" type="text" placeholder="Search users…" style="padding-left:2.5rem;font-size:0.85rem;padding-top:0.45rem;padding-bottom:0.45rem;width:200px;"></div>
          <button class="btn btn-primary btn-sm" onclick="showToast('Add user feature coming soon.');">Add User</button>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
        </div>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
<<<<<<< HEAD
            <thead>
              <tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Actions</th></tr>
            </thead>
=======
            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
            <tbody>
              <?php
              $sampleUsers=[
                ['Juan Dela Cruz','Hiker','juan@email.com','Active'],
                ['Ana Reyes','Hiker','ana@email.com','Active'],
                ['Mang Jose Dela Cruz','Guide','jose@email.com','Active'],
                ['Kuya Bong Reyes','Guide','bong@email.com','Active'],
                ['Maria Santos','Manager','maria@email.com','Active'],
                ['Carlos Tan','Hiker','carlos@email.com','Inactive'],
              ];
              foreach($sampleUsers as [$uname,$urole,$uemail,$ustatus]):
                $roleBadge=['Hiker'=>'badge-blue','Guide'=>'badge-moss','Manager'=>'badge-teal','Tourism'=>'badge-yellow'][$urole]??'badge-sky';
              ?>
              <tr>
<<<<<<< HEAD
                <td style="font-weight:500;font-size:0.88rem;"><?= $uname ?></td>
                <td><span class="badge <?= $roleBadge ?>"><?= $urole ?></span></td>
                <td style="font-size:0.83rem;color:var(--ink-ghost);font-weight:300;"><?= $uemail ?></td>
                <td><span class="badge <?= $ustatus==='Active'?'badge-moss':'badge-outline' ?>"><?= $ustatus ?></span></td>
                <td>
                  <div class="table-actions">
                    <!-- Edit: outline sm -->
                    <button class="btn btn-outline btn-sm" onclick="showToast('Editing <?= $uname ?>...');">Edit</button>
                    <!-- Suspend: danger sm -->
=======
                <td><strong><?= $uname ?></strong></td>
                <td><span class="badge <?= $roleBadge ?>"><?= $urole ?></span></td>
                <td style="font-size:0.88rem;color:var(--teal);"><?= $uemail ?></td>
                <td><span class="badge <?= $ustatus==='Active'?'badge-moss':'badge-sky' ?>"><?= $ustatus ?></span></td>
                <td>
                  <div class="table-actions">
                    <button class="btn btn-outline btn-sm" onclick="showToast('Editing <?= $uname ?>...');">Edit</button>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
                    <button class="btn btn-danger btn-sm" onclick="showToast('User suspended.','error');">Suspend</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

<<<<<<< HEAD
    <!-- Mountain Oversight Section with New Mountain Cards -->
    <div class="card" id="mountains" style="margin-bottom:1.5rem;">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>Mountain Oversight</h3>
        <!-- Ghost button: secondary action -->
        <button class="btn btn-ghost btn-sm" onclick="showToast('Mountain settings panel coming soon.');">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Manage
        </button>
      </div>
      <div class="card-body">
        <!-- New Mountain Grid with card design from HTML file -->
        <div class="mountain-grid-new">
          <?php 
          // Enhanced mountain data with additional fields for the new card design
          $enhancedMountains = [
            [
              'name' => 'Mt. Batulao',
              'location' => 'Nasugbu, Batangas',
              'image' => 'https://images.unsplash.com/photo-1606117331085-5760e3097277?w=600&q=80',
              'difficulty' => 'Moderate',
              'difficultyClass' => 'diff-moderate',
              'guide' => 'Mang Jose',
              'elevation' => '811m',
              'distance' => '8.4km',
              'duration' => '4h 20m',
              'masl' => '811'
            ],
            [
              'name' => 'Mt. Talamitam',
              'location' => 'Nasugbu, Batangas',
              'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=80',
              'difficulty' => 'Easy',
              'difficultyClass' => 'diff-easy',
              'guide' => 'Kuya Bong',
              'elevation' => '630m',
              'distance' => '6.2km',
              'duration' => '3h 15m',
              'masl' => '630'
            ],
            [
              'name' => 'Mt. Apayang',
              'location' => 'Nasugbu, Batangas',
              'image' => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&q=80',
              'difficulty' => 'Hard',
              'difficultyClass' => 'diff-hard',
              'guide' => 'Ate Maria',
              'elevation' => '1050m',
              'distance' => '12.4km',
              'duration' => '6h 30m',
              'masl' => '1050'
            ],
            [
              'name' => 'Mt. Lantik',
              'location' => 'Nasugbu, Batangas',
              'image' => 'https://images.unsplash.com/photo-1434394354979-a235cd36269d?w=600&q=80',
              'difficulty' => 'Moderate',
              'difficultyClass' => 'diff-moderate',
              'guide' => 'Mang Pedro',
              'elevation' => '740m',
              'distance' => '9.8km',
              'duration' => '5h 10m',
              'masl' => '740'
            ]
          ];
          
          foreach($enhancedMountains as $m): 
          ?>
          <div class="mtn-card">
            <div class="mtn-img-wrap">
              <img src="<?= $m['image'] ?>" alt="<?= htmlspecialchars($m['name']) ?>">
              <div class="mtn-img-overlay"></div>
              <div class="mtn-img-footer">
                <div class="mtn-img-name">
                  <strong><?= htmlspecialchars($m['name']) ?></strong>
                  <span><?= htmlspecialchars($m['location']) ?></span>
                </div>
                <button class="btn-directions" onclick="showToast('Directions to <?= htmlspecialchars($m['name']) ?>');">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                  Directions
                </button>
              </div>
            </div>
            <div class="mtn-info">
              <div class="mtn-meta-top">
                <div class="mtn-diff-block">
                  <div class="mtn-diff <?= $m['difficultyClass'] ?>"><?= $m['difficulty'] ?></div>
                  <div class="mtn-credit"><?= $m['masl'] ?> masl · Guide: <?= $m['guide'] ?></div>
                </div>
                <div class="mtn-trail-map" title="Trail map">
                  <!-- SVG trail outline, abstract shape -->
                  <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 44 Q10 30 18 22 Q24 16 30 20 Q38 26 34 34 Q30 40 36 44 Q42 48 46 40 Q50 32 44 24 Q40 18 46 14" stroke="#1a1612" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <circle cx="14" cy="44" r="2.5" fill="#1a1612"/>
                  </svg>
                </div>
              </div>
              <div class="mtn-stats">
                <div class="mtn-stat">
                  <span class="mtn-stat-val"><?= $m['distance'] ?></span>
                  <span class="mtn-stat-label">Distance</span>
                </div>
                <div class="mtn-stat">
                  <span class="mtn-stat-val"><?= $m['elevation'] ?></span>
                  <span class="mtn-stat-label">Elevation</span>
                </div>
                <div class="mtn-stat">
                  <span class="mtn-stat-val"><?= $m['duration'] ?></span>
                  <span class="mtn-stat-label">Duration</span>
                </div>
              </div>
            </div>
            <div class="mtn-actions">
              <button class="mtn-btn mtn-btn-edit" onclick="showToast('Editing <?= htmlspecialchars($m['name']) ?>...');">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button class="mtn-btn mtn-btn-alert" onclick="showToast('Post advisory for <?= htmlspecialchars($m['name']) ?>');">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Alert
              </button>
            </div>
=======
    <!-- Mountain Oversight -->
    <div class="card" id="mountains" style="margin-bottom:1.5rem;">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>Mountain Oversight</h3>
        <button class="btn btn-primary btn-sm" onclick="showToast('Mountain settings panel coming soon.');">Manage Mountains</button>
      </div>
      <div class="card-body">
        <div class="grid-4" style="gap:1rem;">
          <?php foreach($mountains as $m): ?>
          <div class="card card--flat" style="border:1.5px solid var(--sky);">
            <img src="<?= $m['image'] ?>" alt="" style="width:100%;height:90px;object-fit:cover;">
            <div class="card-body" style="padding:0.85rem;">
              <div style="font-weight:700;font-size:0.92rem;color:var(--navy);margin-bottom:0.25rem;"><?= htmlspecialchars($m['name']) ?></div>
              <?= getDifficultyBadge($m['difficulty']) ?>
              <div style="font-size:0.8rem;color:var(--teal);margin-top:0.4rem;"><?= getCrowdBadge($m['crowdLevel']) ?></div>
              <div style="display:flex;gap:0.4rem;margin-top:0.6rem;">
                <button class="btn btn-outline btn-sm" style="flex:1;font-size:0.78rem;" onclick="showToast('Editing <?= htmlspecialchars($m['name']) ?>...');">Edit</button>
                <button class="btn btn-primary btn-sm" style="flex:1;font-size:0.78rem;" onclick="showToast('Advisory posted.');">Alert</button>
              </div>
            </div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- All Bookings -->
    <div class="card" id="bookings" style="margin-bottom:1.5rem;">
      <div class="card-header"><h3>All Booking Records</h3></div>
      <div class="card-body">
        <div class="tabs" style="margin-bottom:1rem;">
          <button class="tab-btn active" onclick="switchTab2('all',this)">All</button>
          <button class="tab-btn" onclick="switchTab2('pending',this)">Pending</button>
          <button class="tab-btn" onclick="switchTab2('approved',this)">Approved</button>
          <button class="tab-btn" onclick="switchTab2('completed',this)">Completed</button>
        </div>
        <div class="table-wrap">
          <table>
<<<<<<< HEAD
            <thead>
              <tr><th>ID</th><th>Hiker</th><th>Mountain</th><th>Date</th><th>Hikers</th><th>Guide</th><th>Status</th></tr>
            </thead>
=======
            <thead><tr><th>ID</th><th>Hiker</th><th>Mountain</th><th>Date</th><th>Hikers</th><th>Guide</th><th>Status</th></tr></thead>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
            <tbody id="allBookingBody">
              <?php foreach($bookings as $b):
                $mt=null; foreach($mountains as $m){ if($m['id']===$b['mountainId']){ $mt=$m; break; } }
                $gd=null; foreach($guides as $g){ if($g['id']===$b['guideId']){ $gd=$g; break; } }
                $sb=['pending'=>'badge-yellow','approved'=>'badge-moss','rejected'=>'badge-red','completed'=>'badge-teal'][$b['status']]??'badge-sky';
              ?>
              <tr data-status="<?= $b['status'] ?>">
<<<<<<< HEAD
                <td><span style="font-family:var(--font-mono);font-size:0.78rem;color:var(--ink-ghost);"><?= $b['id'] ?></span></td>
                <td style="font-weight:400;font-size:0.88rem;"><?= htmlspecialchars($b['user']) ?></td>
                <td style="font-size:0.86rem;"><?= htmlspecialchars($mt['name']??'—') ?></td>
                <td style="font-size:0.83rem;color:var(--ink-soft);"><?= $b['date'] ?></td>
                <td style="font-size:0.86rem;"><?= $b['hikers'] ?></td>
                <td style="font-size:0.83rem;color:var(--ink-soft);"><?= htmlspecialchars($gd['name']??'—') ?></td>
=======
                <td><strong><?= $b['id'] ?></strong></td>
                <td><?= htmlspecialchars($b['user']) ?></td>
                <td><?= htmlspecialchars($mt['name']??'—') ?></td>
                <td><?= $b['date'] ?></td>
                <td><?= $b['hikers'] ?></td>
                <td style="font-size:0.85rem;"><?= htmlspecialchars($gd['name']??'—') ?></td>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
                <td><span class="badge <?= $sb ?>"><?= ucfirst($b['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Reports -->
    <div class="card" id="reports">
<<<<<<< HEAD
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>Generate Reports</h3>
        <!-- Gold accent for a premium "export all" action -->
        <button class="btn btn-gold btn-sm" onclick="showToast('Exporting all reports...');">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export All
        </button>
      </div>
=======
      <div class="card-header"><h3>Generate Reports</h3></div>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;" class="report-grid">
          <?php
          $reports=[
<<<<<<< HEAD
            ['Monthly Hiker Summary','Total hiker counts per mountain per month.','data'],
            ['Guide Performance','Ratings, hike counts, and feedback for each guide.','data'],
            ['Revenue Report','Trail fee and guide fee collection breakdown.','data'],
            ['Environmental Compliance','Leave No Trace adherence and incident reports.','advisory'],
            ['Trail Condition Log','Hazard reports and condition updates by managers.','advisory'],
            ['Booking Analytics','Conversion rates, cancellations, and trends.','data'],
          ];
          foreach($reports as [$rt,$rd,$rtype]): ?>
          <div class="card card--flat">
            <div class="card-body">
              <span class="badge <?= $rtype==='advisory'?'badge-outline':'badge-teal' ?>" style="margin-bottom:0.85rem;">
                <?= ucfirst($rtype) ?>
              </span>
              <div style="font-size:0.92rem;font-weight:500;color:var(--ink-deep);margin-bottom:0.35rem;line-height:1.3;"><?= $rt ?></div>
              <p style="font-size:0.8rem;margin-bottom:1.25rem;line-height:1.6;"><?= $rd ?></p>
              <!-- Download: outline sm, block -->
=======
            ['Monthly Hiker Summary','Total hiker counts per mountain per month.','badge-teal'],
            ['Guide Performance','Ratings, hike counts, and feedback for each guide.','badge-moss'],
            ['Revenue Report','Trail fee and guide fee collection breakdown.','badge-sage'],
            ['Environmental Compliance','Leave No Trace adherence and incident reports.','badge-blue'],
            ['Trail Condition Log','Hazard reports and condition updates by managers.','badge-yellow'],
            ['Booking Analytics','Conversion rates, cancellations, and trends.','badge-teal'],
          ];
          foreach($reports as [$rt,$rd,$rb]): ?>
          <div class="card card--flat" style="border:1.5px solid var(--sky);">
            <div class="card-body">
              <span class="badge <?= $rb ?>" style="margin-bottom:0.75rem;"><?= $rb === 'badge-yellow'?'Advisory':'Data' ?></span>
              <h4 style="font-size:0.95rem;margin-bottom:0.3rem;"><?= $rt ?></h4>
              <p style="font-size:0.83rem;margin-bottom:1rem;"><?= $rd ?></p>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
              <button class="btn btn-outline btn-sm btn-block" onclick="showToast('Downloading <?= $rt ?>...');">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
<<<<<<< HEAD

  </main><!-- /dashboard-main -->
</div><!-- /dashboard-layout -->

<!-- Mobile sidebar toggle -->
=======
  </main>
</div>

>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<<<<<<< HEAD
<style>
  @media(max-width:900px){
    .admin-charts-grid { grid-template-columns:1fr !important; }
    .report-grid       { grid-template-columns:1fr 1fr !important; }
  }
  @media(max-width:560px){
    .report-grid { grid-template-columns:1fr !important; }
  }
</style>

<script>
function switchTab2(status, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#allBookingBody tr').forEach(r => {
    r.style.display = (status === 'all' || r.dataset.status === status) ? '' : 'none';
  });
}
document.getElementById('sidebarToggle').addEventListener('click', function () {
  document.getElementById('dashSidebar').classList.toggle('open');
});
</script>

<?php ?>
=======
<style>@media(max-width:900px){.admin-charts-grid{grid-template-columns:1fr!important;}.report-grid{grid-template-columns:1fr 1fr!important;}}</style>
<script>
function switchTab2(status,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#allBookingBody tr').forEach(r=>{
    r.style.display=(status==='all'||r.dataset.status===status)?'':'none';
  });
}
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
>>>>>>> 52fc9258d2c39cf250247481bbd0e9bcc75f38b1
