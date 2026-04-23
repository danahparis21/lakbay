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
  <title>LAKBAY — Alerts</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --paper:#ffffff;--ink:#111318;--ink-2:#23262f;--ink-3:#3c4050;
      --ink-4:#6e7483;--ink-5:#a0a6b5;--sky:#eef2f8;--teal:#2b6e6f;
      --moss:#3f6a44;--warn:#c96f3e;--border-light:#e9edf2;--line:#e9edf2;
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

    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;}
    .panel{background:var(--paper);border-radius:24px;border:1px solid var(--border-light);padding:20px;}
    .panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .panel-title{font-weight:600;}
    .panel-badge{font-size:0.7rem;padding:4px 10px;border-radius:30px;background:var(--sky);color:var(--ink-2);}
    .panel-badge.warn{background:#fff0e0;color:var(--warn);}

    .alert-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line);}
    .alert-item:last-of-type{border-bottom:none;}
    .alert-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
    .alert-icon.warn{background:#fff0e0;color:var(--warn);}
    .alert-icon.neutral{background:var(--sky);color:var(--ink-4);}
    .alert-body{flex:1;}
    .alert-title{font-size:0.88rem;font-weight:500;margin-bottom:2px;}
    .alert-meta{font-size:0.75rem;color:var(--ink-4);}

    .badge{font-size:0.7rem;padding:4px 10px;border-radius:30px;background:var(--sky);color:var(--ink-2);}
    .badge.green{background:#e0f0e8;color:var(--moss);}

    .btn{background:none;border:1px solid var(--border-light);padding:6px 12px;border-radius:40px;font-size:0.75rem;cursor:pointer;font-family:'Inter',sans-serif;}
    .btn-ghost{border:none;color:var(--teal);background:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:0.75rem;}
    .btn-primary{background:var(--ink);color:white;border:none;padding:8px 16px;border-radius:40px;font-size:0.8rem;cursor:pointer;font-family:'Inter',sans-serif;}
    .btn-primary:hover{background:var(--ink-2);}

    textarea{width:100%;border:1px solid var(--border-light);border-radius:12px;padding:12px;font-family:'Inter',sans-serif;font-size:0.88rem;color:var(--ink);resize:vertical;margin-bottom:8px;}
    textarea:focus{outline:none;border-color:var(--ink);}

    @media(max-width:900px){
      .two-col{grid-template-columns:1fr;}
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
        <li class="nav-item active" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
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
      <div class="page-heading"><i class="fas fa-bell"></i> Alerts</div>
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
      <div class="two-col">

        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Active Alerts</span>
            <span class="panel-badge warn">3 open</span>
          </div>

          <div class="alert-item">
            <div class="alert-icon warn"><i class="fas fa-cloud-bolt"></i></div>
            <div class="alert-body">
              <div class="alert-title">Heavy Rainfall — North Ridge</div>
              <div class="alert-meta">Trail 3 · Apr 22, 09:14 · Weather</div>
            </div>
            <button class="btn-ghost" id="ackAlert1"><i class="fas fa-check"></i> Ack</button>
          </div>

          <div class="alert-item">
            <div class="alert-icon warn"><i class="fas fa-person-falling"></i></div>
            <div class="alert-body">
              <div class="alert-title">Sprained Ankle — Hiker in Trail 2</div>
              <div class="alert-meta">Mt. Batulao · Apr 21, 14:52 · Medical</div>
            </div>
            <button class="btn-ghost" id="ackAlert2"><i class="fas fa-check"></i> Ack</button>
          </div>

          <div class="alert-item">
            <div class="alert-icon neutral"><i class="fas fa-person-walking-arrow-left"></i></div>
            <div class="alert-body">
              <div class="alert-title">Lost Hiker — Resolved</div>
              <div class="alert-meta">Mt. Talamitam · Apr 20, 11:30 · Resolved</div>
            </div>
            <span class="badge green"><i class="fas fa-check"></i> Done</span>
          </div>

          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line);">
            <button class="btn" id="resolveAllBtn"><i class="fas fa-reply-all"></i> Mark All Resolved</button>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Broadcast Notification</span>
          </div>
          <textarea id="broadcastTxt" rows="5" placeholder="Write system-wide notification...">🌊 Heavy rainfall warning: Mt. Batulao trails closed until further notice.</textarea>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <button class="btn-primary" id="sendBroadcastBtn" style="flex:1">
              <i class="fas fa-broadcast-tower"></i> Send to All
            </button>
            <button class="btn" id="previewBroadcastBtn"><i class="fas fa-eye"></i> Preview</button>
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

document.getElementById('ackAlert1').addEventListener('click', () => alert('✅ Rainfall alert acknowledged.'));
document.getElementById('ackAlert2').addEventListener('click', () => alert('✅ Medical alert acknowledged.'));
document.getElementById('resolveAllBtn').addEventListener('click', () => alert('✅ All alerts marked as resolved.'));
document.getElementById('sendBroadcastBtn').addEventListener('click', () => {
  const msg = document.getElementById('broadcastTxt').value;
  alert(`🔔 Broadcast sent:\n"${msg}"`);
});
document.getElementById('previewBroadcastBtn').addEventListener('click', () => {
  const msg = document.getElementById('broadcastTxt').value;
  alert(`👁 Preview:\n"${msg}"`);
});
</script>
</body>
</html>