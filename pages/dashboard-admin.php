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

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Tourism Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --paper: #ffffff;
      --ink: #111318;
      --ink-2: #23262f;
      --ink-3: #3c4050;
      --ink-4: #6e7483;
      --ink-5: #a0a6b5;
      --sky: #eef2f8;
      --teal: #2b6e6f;
      --moss: #3f6a44;
      --sage: #6f8f6a;
      --warn: #c96f3e;
      --border-light: #e9edf2;
    }

    body {
      background: #f5f7fb;
      font-family: 'Inter', sans-serif;
      color: var(--ink);
    }

    .app {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      width: 280px;
      background: var(--paper);
      border-right: 1px solid var(--border-light);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 32px 20px;
      position: sticky;
      top: 0;
      height: 100vh;
    }

    .logo-wordmark {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.8rem;
      font-weight: 600;
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo-icon svg {
      width: 32px;
      height: 32px;
    }

    .logo-sub {
      font-size: 0.7rem;
      color: var(--ink-4);
      letter-spacing: 0.5px;
      margin-top: 4px;
    }

    .nav-section-label {
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--ink-5);
      margin: 28px 0 12px 0;
    }

    .nav-list {
      list-style: none;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--ink-3);
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 4px;
    }

    .nav-item i {
      width: 22px;
      font-size: 1rem;
      color: var(--ink-4);
    }

    .nav-item.active {
      background: var(--ink);
      color: white;
    }

    .nav-item.active i {
      color: white;
    }

    .nav-divider {
      height: 1px;
      background: var(--border-light);
      margin: 16px 0;
    }

    .sidebar-footer {
      font-size: 0.7rem;
      color: var(--ink-5);
      display: flex;
      align-items: center;
      gap: 8px;
      border-top: 1px solid var(--border-light);
      padding-top: 20px;
    }

    .status-dot {
      width: 8px;
      height: 8px;
      background: #2b6e6f;
      border-radius: 50%;
    }

    /* Main */
    .main {
      flex: 1;
      overflow-x: auto;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 32px;
      background: var(--paper);
      border-bottom: 1px solid var(--border-light);
    }

    .page-heading {
      font-size: 1.4rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .topbar-right {
      display: flex;
      gap: 24px;
      align-items: center;
    }

    .topbar-date {
      font-size: 0.8rem;
      color: var(--ink-4);
      font-family: 'DM Mono', monospace;
    }

    .avatar {
      width: 32px;
      height: 32px;
      background: var(--ink);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }

    .topbar-user {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .content {
      padding: 28px 32px;
    }

    /* Stat cards */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--paper);
      border-radius: 24px;
      padding: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      border: 1px solid var(--border-light);
    }

    .stat-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--ink-5);
      margin-bottom: 12px;
    }

    .stat-num {
      font-size: 2.2rem;
      font-weight: 600;
      color: var(--ink);
    }

    .stat-trend {
      font-size: 0.7rem;
      margin-top: 8px;
      color: var(--teal);
    }

    /* Charts row */
    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 32px;
    }

    .panel {
      background: var(--paper);
      border-radius: 24px;
      border: 1px solid var(--border-light);
      padding: 20px;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .panel-title {
      font-weight: 600;
    }

    .chart-wrap {
      height: 220px;
    }

    /* Table */
    .full-span {
      margin-bottom: 32px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 12px;
    }

    .section-title {
      font-weight: 600;
      font-size: 1rem;
    }

    .section-action {
      background: none;
      border: none;
      color: var(--teal);
      font-size: 0.8rem;
      cursor: pointer;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
    }

    .data-table th {
      text-align: left;
      padding: 12px 8px;
      font-size: 0.7rem;
      font-weight: 600;
      color: var(--ink-5);
      border-bottom: 1px solid var(--border-light);
    }

    .data-table td {
      padding: 14px 8px;
      border-bottom: 1px solid var(--border-light);
      font-size: 0.85rem;
    }

    .badge {
      font-size: 0.7rem;
      padding: 4px 10px;
      border-radius: 30px;
      background: var(--sky);
      color: var(--ink-2);
    }

    .badge.green {
      background: #e0f0e8;
      color: var(--moss);
    }

    .badge.amber {
      background: #fff0e0;
      color: var(--warn);
    }

    .btn {
      background: none;
      border: 1px solid var(--border-light);
      padding: 6px 12px;
      border-radius: 40px;
      font-size: 0.75rem;
      cursor: pointer;
    }

    .btn-ghost {
      border: none;
      color: var(--teal);
    }

    @media (max-width: 900px) {
      .stat-grid, .two-col {
        grid-template-columns: 1fr;
      }
      .sidebar {
        display: none;
      }
    }
  </style>
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
        <li class="nav-item active"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item"><i class="fas fa-chart-simple"></i> Analytics</li>
      </ul>
    </div>
    <div class="sidebar-footer"><div class="status-dot"></div> SYSTEM LIVE · V3</div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-chart-line"></i> Dashboard</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user">
          <div class="avatar"><?= substr($adminName, 0, 1) ?></div>
          <?= htmlspecialchars($adminName) ?> <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
        </div>
      </div>
    </div>

    <div class="content">

      <!-- STAT CARDS (dynamic from PHP) -->
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">Total Bookings <i class="fas fa-ticket-alt"></i></div>
          <div class="stat-num"><?= $totalBookings ?></div>
          <div class="stat-trend">↑ +<?= $pendingCount ?> pending</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Active Hikers <i class="fas fa-person-hiking"></i></div>
          <div class="stat-num">1,245</div>
          <div class="stat-trend">↑ +12% this month</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">MTD Revenue <i class="fas fa-coins"></i></div>
          <div class="stat-num">₱45k</div>
          <div class="stat-trend">↑ +18% projected</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Open Alerts <i class="fas fa-triangle-exclamation"></i></div>
          <div class="stat-num" style="color:var(--warn);">2</div>
          <div class="stat-trend warn">↑ 1 critical</div>
        </div>
      </div>

      <!-- CHARTS (LAKBAY style) -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Revenue — This Week</span>
            <span class="panel-badge">₱133.9k total</span>
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

      <!-- RECENT BOOKINGS (dynamic) -->
      <div class="full-span">
        <div class="section-header">
          <div class="section-title">Recent Bookings</div>
          <button class="section-action" id="viewAllBookings">View all →</button>
        </div>
        <div class="panel" style="padding:0 4px;">
          <table class="data-table">
            <thead><tr><th>Hiker</th><th>Mountain</th><th>Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php 
              $recentBookings = array_slice($bookings, 0, 3);
              foreach($recentBookings as $b):
                $mt = null;
                foreach($mountains as $m) if($m['id'] === $b['mountainId']) { $mt = $m; break; }
                $statusClass = $b['status'] === 'completed' ? 'green' : ($b['status'] === 'pending' ? 'amber' : '');
                $statusIcon = $b['status'] === 'completed' ? '<i class="fas fa-check"></i>' : ($b['status'] === 'pending' ? '<i class="fas fa-clock"></i>' : '<i class="fas fa-circle"></i>');
              ?>
              <tr>
                <td><?= htmlspecialchars($b['user']) ?></td>
                <td><?= htmlspecialchars($mt['name'] ?? '—') ?></td>
                <td style="font-size:0.75rem;color:var(--ink-4);"><?= $b['date'] ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= $statusIcon ?> <?= ucfirst($b['status']) ?></span></td>
                <td><button class="btn btn-ghost viewBookBtn" data-name="<?= htmlspecialchars($b['user']) ?>"><i class="fas fa-arrow-up-right-from-square"></i></button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ALERT BANNER (LAKBAY style) -->
      <div class="panel" style="background:var(--ink);border-color:var(--ink);color:var(--paper);padding:18px 22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
          <div>
            <div style="font-size:0.65rem;font-weight:600;color:var(--ink-5);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em;">Active Alert</div>
            <div style="font-size:0.88rem;font-weight:500;">⚠️ Trail 3 — North Ridge · Heavy rainfall advisory</div>
          </div>
          <div style="display:flex;gap:8px;">
            <button class="btn" style="border-color:rgba(255,255,255,0.2);color:var(--paper);background:rgba(255,255,255,0.1);" id="ackAlertBtn"><i class="fas fa-check"></i> Acknowledge</button>
            <button class="btn" style="border-color:rgba(255,255,255,0.2);color:var(--paper);background:rgba(255,255,255,0.1);" id="viewAlertBtn"><i class="fas fa-arrow-right"></i> Details</button>
          </div>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /app -->

<script>
  // Live clock
  function updateDate() {
    const d = new Date();
    document.getElementById('liveDate').textContent =
      d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() +
      '  ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
  }
  updateDate(); setInterval(updateDate, 1000);

  // Chart.js initialization
  const charts = {};
  function buildChart(id, type, data, opts = {}) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
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

  buildChart('chartRevenue', 'line', {
    labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
    datasets: [{ data:[12800,14500,13200,16800,22400,27800,25400], borderColor:'#111318', backgroundColor:'rgba(17,19,24,0.04)', tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#111318', borderWidth:1.5 }]
  });
  buildChart('chartActivity', 'bar', {
    labels: ['Wk 1','Wk 2','Wk 3','Wk 4'],
    datasets: [{ data:[42,58,51,66], backgroundColor:'rgba(17,19,24,0.12)', borderRadius:4 }]
  });

  // UI Interactions
  document.getElementById('refreshStatsBtn')?.addEventListener('click', () => {
    alert('📊 Stats refreshed (simulated).');
  });
  document.getElementById('viewAllBookings')?.addEventListener('click', () => alert('📋 Opening full bookings list…'));
  document.getElementById('ackAlertBtn')?.addEventListener('click', () => alert('✅ Alert acknowledged.'));
  document.getElementById('viewAlertBtn')?.addEventListener('click', () => alert('🔔 Opening alert details…'));
  document.querySelectorAll('.viewBookBtn').forEach(btn => {
    btn.addEventListener('click', () => alert(`📋 Booking details for ${btn.dataset.name}.`));
  });
</script>
</body>
</html>

<?php include_once $base.'includes/footer.php'; ?>