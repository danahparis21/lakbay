<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));


$alerts = [];

// Check if alerts table exists, if not, create it dynamically
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'alerts'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Fetch active alerts (status = 'active' or 'pending')
        $stmt = $pdo->prepare("
            SELECT * FROM alerts 
            WHERE status IN ('active', 'pending') 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also get count of active alerts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM alerts 
            WHERE status IN ('active', 'pending')
        ");
        $stmt->execute();
        $activeAlertCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } else {
        // If no alerts table, use sample data (for demonstration)
        $activeAlertCount = 2;
        $alerts = [
            [
                'id' => 1,
                'title' => 'Heavy Rainfall — North Ridge',
                'description' => 'Trail 3',
                'location' => 'North Ridge',
                'type' => 'weather',
                'severity' => 'high',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'resolved_at' => null
            ],
            [
                'id' => 2,
                'title' => 'Sprained Ankle — Hiker in Trail 2',
                'description' => 'Mt. Pulag',
                'location' => 'Trail 2',
                'type' => 'medical',
                'severity' => 'high',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'resolved_at' => null
            ]
        ];
    }
} catch (PDOException $e) {
    // If error, use sample data
    $activeAlertCount = 2;
    $alerts = [];
}

// Handle AJAX requests for acknowledging alerts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'acknowledge':
                if (isset($_POST['alert_id'])) {
                    $stmt = $pdo->prepare("UPDATE alerts SET status = 'acknowledged', acknowledged_at = NOW() WHERE id = ?");
                    $stmt->execute([$_POST['alert_id']]);
                    echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
                }
                break;
            case 'resolve_all':
                $stmt = $pdo->prepare("UPDATE alerts SET status = 'resolved', resolved_at = NOW() WHERE status IN ('active', 'pending', 'acknowledged')");
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'All alerts resolved']);
                break;
            case 'broadcast':
                if (isset($_POST['message'])) {
                    // Store broadcast in database
                    $stmt = $pdo->prepare("INSERT INTO broadcasts (message, sent_by, sent_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$_POST['message'], $_SESSION['user_id']]);
                    echo json_encode(['success' => true, 'message' => 'Broadcast sent']);
                }
                break;
        }
    }
    exit;
}

// Format date helper
function formatAlertDate($dateStr) {
    if (!$dateStr) return 'Unknown';
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        return $date->format('H:i') . ' · Today';
    } elseif ($diff->days == 1) {
        return $date->format('M d, H:i') . ' · Yesterday';
    } else {
        return $date->format('M d, H:i');
    }
}

// Get alert icon based on type
function getAlertIcon($type) {
    switch($type) {
        case 'weather': return '<i class="fas fa-cloud-bolt"></i>';
        case 'medical': return '<i class="fas fa-person-falling"></i>';
        case 'trail': return '<i class="fas fa-road"></i>';
        case 'safety': return '<i class="fas fa-shield-alt"></i>';
        default: return '<i class="fas fa-bell"></i>';
    }
}

// Get alert severity class
function getAlertSeverityClass($severity) {
    switch($severity) {
        case 'high': return 'warn';
        case 'medium': return 'info';
        case 'low': return 'neutral';
        default: return 'neutral';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Alerts</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="shared.css">
</head>
<body data-page="alerts">
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
        <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item active" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
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

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-bell"></i> Alerts</div>
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

      <div class="two-col">
        <!-- ACTIVE ALERTS -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Active Alerts</span>
            <span class="panel-badge warn"><?= $activeAlertCount ?> open</span>
          </div>

          <?php if (empty($alerts)): ?>
            <div style="text-align:center; padding:40px; color:var(--ink-4);">
              <i class="fas fa-check-circle" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
              No active alerts! All systems are operational.
            </div>
          <?php else: ?>
            <?php foreach ($alerts as $alert): ?>
              <div class="alert-item" data-alert-id="<?= $alert['id'] ?>">
                <div class="alert-icon <?= getAlertSeverityClass($alert['severity'] ?? 'medium') ?>">
                  <?= getAlertIcon($alert['type'] ?? 'general') ?>
                </div>
                <div class="alert-body">
                  <div class="alert-title"><?= htmlspecialchars($alert['title'] ?? 'Alert') ?></div>
                  <div class="alert-meta">
                    <?= htmlspecialchars($alert['location'] ?? $alert['description'] ?? 'Unknown location') ?> · 
                    <?= formatAlertDate($alert['created_at'] ?? date('Y-m-d H:i:s')) ?> · 
                    <?= ucfirst($alert['type'] ?? 'General') ?>
                  </div>
                </div>
                <?php if (($alert['status'] ?? 'active') !== 'resolved'): ?>
                  <button class="btn btn-ghost ack-alert-btn" data-id="<?= $alert['id'] ?>">
                    <i class="fas fa-check"></i> Ack
                  </button>
                <?php else: ?>
                  <span class="badge green"><i class="fas fa-check"></i> Done</span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line);">
            <button class="btn" id="resolveAllBtn"><i class="fas fa-reply-all"></i> Mark All Resolved</button>
          </div>
        </div>

        <!-- BROADCAST -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Broadcast Notification</span>
          </div>
          <textarea id="broadcastTxt" rows="5" placeholder="Write system-wide notification...">🌊 Heavy rainfall warning: Mt. Banahaw trails closed until further notice.</textarea>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <button class="btn btn-primary" id="sendBroadcastBtn" style="flex:1">
              <i class="fas fa-broadcast-tower"></i> Send to All
            </button>
            <button class="btn" id="previewBroadcastBtn"><i class="fas fa-eye"></i> Preview</button>
          </div>
          <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--line);">
            <div class="panel-title" style="margin-bottom: 12px;">Recent Broadcasts</div>
            <div id="recentBroadcasts" style="font-size: 0.75rem; color: var(--ink-4);">
              Loading...
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

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { 
    if(item.dataset.href) {
      window.location.href = item.dataset.href; 
    }
  });
});

// Function to send AJAX request
function sendAlertAction(action, data) {
  fetch(window.location.href, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({action: action, ...data})
  })
  .then(response => response.json())
  .then(result => {
    if(result.success) {
      alert(result.message);
      location.reload();
    }
  })
  .catch(error => console.error('Error:', error));
}

// Acknowledge individual alerts
document.querySelectorAll('.ack-alert-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    const alertId = btn.dataset.id;
    if(confirm('Acknowledge this alert?')) {
      sendAlertAction('acknowledge', {alert_id: alertId});
    }
  });
});

// Resolve all alerts
document.getElementById('resolveAllBtn').addEventListener('click', () => {
  if(confirm('Mark all alerts as resolved?')) {
    sendAlertAction('resolve_all', {});
  }
});

// Send broadcast
document.getElementById('sendBroadcastBtn').addEventListener('click', () => {
  const message = document.getElementById('broadcastTxt').value;
  if(message.trim() && confirm(`Send broadcast:\n"${message}"\n\nThis will notify all users.`)) {
    sendAlertAction('broadcast', {message: message});
  }
});

// Preview broadcast
document.getElementById('previewBroadcastBtn').addEventListener('click', () => {
  const message = document.getElementById('broadcastTxt').value;
  alert(`📢 Preview:\n"${message}"`);
});

// Load recent broadcasts
function loadRecentBroadcasts() {
  fetch('/api/get_broadcasts.php')
    .then(response => response.json())
    .then(data => {
      const container = document.getElementById('recentBroadcasts');
      if(data.length > 0) {
        container.innerHTML = data.map(b => 
          `<div style="padding: 8px 0; border-bottom: 1px solid var(--line);">
             <div style="font-weight: 500;">${escapeHtml(b.message.substring(0, 100))}${b.message.length > 100 ? '...' : ''}</div>
             <div style="font-size: 0.7rem; margin-top: 4px;">${formatDate(b.sent_at)}</div>
           </div>`
        ).join('');
      } else {
        container.innerHTML = 'No recent broadcasts';
      }
    })
    .catch(() => {
      document.getElementById('recentBroadcasts').innerHTML = 'No recent broadcasts';
    });
}

function escapeHtml(text) {
  if(!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-PH', {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
}

// Load recent broadcasts if the endpoint exists
// loadRecentBroadcasts();
</script>


</body>
</html>