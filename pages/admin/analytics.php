<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}
$adminName    = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));
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
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --paper:#ffffff;--ink:#111318;--ink-2:#23262f;--ink-3:#3c4050;
      --ink-4:#6e7483;--ink-5:#a0a6b5;--sky:#eef2f8;--teal:#2b6e6f;
      --moss:#3f6a44;--warn:#c96f3e;--border-light:#e9edf2;
    }
    body{background:#f5f7fb;font-family:'Inter',sans-serif;color:var(--ink);}
    .app{display:flex;min-height:100vh;}

    .sidebar{width:280px;background:var(--paper);border-right:1px solid var(--border-light);display:flex;flex-direction:column;justify-content:space-between;padding:32px 20px;position:sticky;top:0;height:100vh;}
    .logo-wordmark{font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:600;letter-spacing:-0.02em;display:flex;align-items:center;gap:10px;}
    .logo-icon svg{width:32px;height:32px;}
    .logo-sub{font-size:0.7rem;color:var(--ink-4);letter-spacing:0.5px;margin-top:4px;}
    .nav-section-label{font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--ink-5);margin:28px 0 12px 0;}
    .nav-list{list-style:none;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;font-size:0.9rem;font-weight:500;color:var(--ink-3);cursor:pointer;transition:all 0.2s;margin-bottom:4px;}
    .nav-item i{width:22px;font-size:1rem;color:var(--ink-4);}
    .nav-item:hover{background:var(--sky);}
    .nav-item.active{background:var(--ink);color:white;}
    .nav-item.active i{color:white;}
    .nav-divider{height:1px;background:var(--border-light);margin:16px 0;}
    .sidebar-footer{font-size:0.7rem;color:var(--ink-5);display:flex;align-items:center;gap:8px;border-top:1px solid var(--border-light);padding-top:20px;}
    .status-dot{width:8px;height:8px;background:#2b6e6f;border-radius:50%;}
    .logout-btn{display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:12px;font-size:0.9rem;font-weight:500;color:#c0392b;cursor:pointer;border:none;background:none;width:100%;margin-top:8px;}
    .logout-btn:hover{background:#fff0ee;}
    .logout-btn i{width:22px;}

    .main{flex:1;overflow-x:auto;}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:20px 32px;background:var(--paper);border-bottom:1px solid var(--border-light);}
    .page-heading{font-size:1.4rem;font-weight:600;display:flex;align-items:center;gap:12px;}
    .topbar-right{display:flex;gap:24px;align-items:center;}
    .topbar-date{font-size:0.8rem;color:var(--ink-4);font-family:'DM Mono',monospace;}
    .avatar{width:32px;height:32px;background:var(--ink);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.85rem;}
    .topbar-user{display:flex;align-items:center;gap:8px;font-size:0.85rem;font-weight:500;}
    .content{padding:28px 32px;}

    .analytics-row{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:32px;}
    .stat-card{background:var(--paper);border-radius:24px;padding:20px;border:1px solid var(--border-light);}
    .stat-label{font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--ink-5);margin-bottom:12px;}
    .stat-num{font-size:2.2rem;font-weight:600;color:var(--ink);}
    .stat-trend{font-size:0.7rem;margin-top:8px;color:var(--teal);}
    .stat-sub{font-size:0.7rem;margin-top:8px;color:var(--ink-4);}

    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;}
    .panel{background:var(--paper);border-radius:24px;border:1px solid var(--border-light);padding:20px;margin-bottom:24px;}
    .panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .panel-title{font-weight:600;}
    .panel-badge{font-size:0.7rem;padding:4px 10px;border-radius:30px;background:var(--sky);color:var(--ink-4);}
    .chart-wrap{height:150px;}

    .insight-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;font-size:0.8rem;color:var(--ink-2);}
    .insight-card{padding:14px;background:#f5f7fb;border-radius:12px;border:1px solid var(--border-light);}
    .insight-label{font-size:0.72rem;font-weight:600;color:var(--ink-3);margin-bottom:8px;}

    .btn{background:none;border:1px solid var(--border-light);padding:6px 12px;border-radius:40px;font-size:0.75rem;cursor:pointer;font-family:'Inter',sans-serif;}
    .btn-ghost{border:none;color:var(--teal);background:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:0.75rem;}

    @media(max-width:900px){
      .analytics-row,.two-col,.insight-grid{grid-template-columns:1fr;}
      .sidebar{display:none;}
    }
  </style>
</head>
<body>
<div class="app">

  <aside class="sidebar">
    <div>
      <div class="logo">
        <div class="logo-wordmark">
          <div class="logo-icon">
            <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#111318" opacity="0.9"/><path d="M14 16L18 8L24 22H14V16Z" fill="#111318" opacity="0.35"/></svg>
          </div>
          LAKBAY
        </div>
        <div class="logo-sub">wilderness intelligence</div>
      </div>
      <div class="nav-section-label">Navigation</div>
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
      <form method="POST" action="/pages/modals/logout.php">
        <button class="logout-btn" type="submit"><i class="fas fa-right-from-bracket"></i> Log Out</button>
      </form>
      <div class="sidebar-footer"><div class="status-dot"></div> SYSTEM LIVE · V3</div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-chart-simple"></i> Analytics</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user">
          <div class="avatar"><?= $adminInitial ?></div>
          <?= htmlspecialchars($adminName) ?>
          <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
        </div>
      </div>
    </div>

    <div class="content">

      <div class="analytics-row">
        <div class="stat-card">
          <div class="stat-label">Visitor Growth <i class="fas fa-chart-line"></i></div>
          <div class="stat-num">+23%</div>
          <div class="stat-trend">↑ YTD vs prior year</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Top Mountain</div>
          <div class="stat-num" style="font-size:1.3rem;font-weight:700;">Mt. Batulao</div>
          <div class="stat-sub">340 avg visitors/day</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Guide Rating <i class="fas fa-star"></i></div>
          <div class="stat-num">4.9</div>
          <div class="stat-sub">Top: Juan dela Cruz</div>
        </div>
      </div>

      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Visitor Trend — 6 Months</span>
            <button class="btn-ghost" id="refreshAnalyticsBtn"><i class="fas fa-rotate-right"></i> Refresh</button>
          </div>
          <div class="chart-wrap"><canvas id="chartVisitors"></canvas></div>
        </div>
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Monthly Revenue</span>
          </div>
          <div class="chart-wrap"><canvas id="chartRevMonth"></canvas></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Key Insights</span>
          <span class="panel-badge">Auto-generated</span>
        </div>
        <div class="insight-grid">
          <div class="insight-card">
            <div class="insight-label">Congestion</div>
            High traffic at Mt. Batulao every Friday between 8–11AM. Consider imposing slot limits.
          </div>
          <div class="insight-card">
            <div class="insight-label">Seasonality</div>
            Mt. Batulao peaks March–May. Prepare surge permits and extra guide deployment.
          </div>
          <div class="insight-card">
            <div class="insight-label">Revenue</div>
            Weekend bookings account for 62% of weekly revenue. Optimize weekend pricing.
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
updateDate(); setInterval(updateDate, 1000);

document.querySelectorAll('.nav-item[data-href]').forEach(item => {
  item.addEventListener('click', () => window.location.href = item.dataset.href);
});

function buildChart(id, type, data) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  new Chart(ctx, {
    type, data,
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false } },
      scales:{
        x:{ grid:{ display:false }, ticks:{ font:{ family:"'Inter'", size:10 }, color:'#9098a6' } },
        y:{ display:false }
      }
    }
  });
}

buildChart('chartVisitors','line',{
  labels:['Jan','Feb','Mar','Apr','May','Jun'],
  datasets:[{ data:[380,420,390,510,480,560], borderColor:'#4a6741', backgroundColor:'rgba(74,103,65,0.06)', tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#4a6741', borderWidth:1.5 }]
});
buildChart('chartRevMonth','bar',{
  labels:['Jan','Feb','Mar','Apr'],
  datasets:[{ data:[85000,96000,88000,124800], backgroundColor:'rgba(17,19,24,0.1)', borderRadius:4 }]
});

document.getElementById('refreshAnalyticsBtn').addEventListener('click', () => alert('📈 Analytics data refreshed.'));
</script>
</body>
</html>