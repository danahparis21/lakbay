<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));
$adminId = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'acknowledge':
                if (isset($_POST['alert_id'])) {
                    $stmt = $pdo->prepare("UPDATE alerts SET status = 'acknowledged', acknowledged_at = NOW() WHERE id = ?");
                    $stmt->execute([$_POST['alert_id']]);
                    
                    $stmt = $pdo->prepare("INSERT INTO alert_acknowledgements (alert_id, user_id, user_name) VALUES (?, ?, ?)");
                    $stmt->execute([$_POST['alert_id'], $adminId, $adminName]);
                    
                    echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
                }
                break;
                
            case 'resolve':
                if (isset($_POST['alert_id'])) {
                    $stmt = $pdo->prepare("UPDATE alerts SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                    $stmt->execute([$adminId, $_POST['alert_id']]);
                    echo json_encode(['success' => true, 'message' => 'Alert resolved']);
                }
                break;
                
            case 'false_alarm':
                if (isset($_POST['alert_id'])) {
                    $stmt = $pdo->prepare("UPDATE alerts SET status = 'false_alarm', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                    $stmt->execute([$adminId, $_POST['alert_id']]);
                    echo json_encode(['success' => true, 'message' => 'Alert marked as false alarm']);
                }
                break;
                
            case 'resolve_all':
                $stmt = $pdo->prepare("UPDATE alerts SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE status IN ('pending', 'active', 'acknowledged')");
                $stmt->execute([$adminId]);
                echo json_encode(['success' => true, 'message' => 'All alerts resolved']);
                break;
                
            case 'broadcast_to_guides':
                if (isset($_POST['message']) && trim($_POST['message'])) {
                    $stmt = $pdo->prepare("INSERT INTO broadcasts (message, sender_id, recipient_role, created_at) VALUES (?, ?, 'all_guides', NOW())");
                    $stmt->execute([trim($_POST['message']), $adminId]);
                    echo json_encode(['success' => true, 'message' => 'Broadcast sent to all guides']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
                }
                break;
                
            case 'get_alerts':
                $stmt = $pdo->prepare("
                    SELECT a.*, 
                           COUNT(DISTINCT aa.id) as acknowledgement_count,
                           GROUP_CONCAT(DISTINCT aa.user_name) as acknowledged_by
                    FROM alerts a
                    LEFT JOIN alert_acknowledgements aa ON a.id = aa.alert_id
                    WHERE a.status IN ('pending', 'active', 'acknowledged')
                    GROUP BY a.id
                    ORDER BY 
                        CASE a.severity 
                            WHEN 'critical' THEN 1
                            WHEN 'high' THEN 2
                            WHEN 'medium' THEN 3
                            ELSE 4
                        END,
                        a.created_at DESC
                ");
                $stmt->execute();
                $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'alerts' => $alerts]);
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Initial page load - fetch active alerts
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               COUNT(DISTINCT aa.id) as acknowledgement_count,
               GROUP_CONCAT(DISTINCT aa.user_name) as acknowledged_by
        FROM alerts a
        LEFT JOIN alert_acknowledgements aa ON a.id = aa.alert_id
        WHERE a.status IN ('pending', 'active', 'acknowledged')
        GROUP BY a.id
        ORDER BY 
            CASE a.severity 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END,
            a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeAlertCount = count($alerts);
} catch (PDOException $e) {
    $alerts = [];
    $activeAlertCount = 0;
}

// Fetch recent broadcasts to guides
try {
    $stmt = $pdo->prepare("
        SELECT b.*, u.name as sender_name 
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.recipient_role = 'all_guides' OR b.recipient_role IS NULL
        ORDER BY b.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentBroadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentBroadcasts = [];
}

// Mountain coordinates for Nasugbu, Batangas
$mountains = [
    'Mt. Talamitam' => [
        'lat' => 14.0738,
        'lon' => 120.7038,
        'elevation' => 630,
        'difficulty' => 'Moderate',
        'trails' => 2
    ],
    'Mt. Batulao' => [
        'lat' => 14.0458,
        'lon' => 120.7917,
        'elevation' => 693,
        'difficulty' => 'Easy to Moderate',
        'trails' => 3
    ],
    'Mt. Lantik' => [
        'lat' => 14.0600,
        'lon' => 120.7200,
        'elevation' => 500,
        'difficulty' => 'Easy',
        'trails' => 1
    ],
    'Mt. Apayang' => [
        'lat' => 14.0500,
        'lon' => 120.7400,
        'elevation' => 550,
        'difficulty' => 'Moderate',
        'trails' => 1
    ]
];

function getWeatherDesc($code) {
    $weatherCodes = [
        0 => ['desc' => 'Clear sky', 'icon' => '☀️', 'class' => 'sunny'],
        1 => ['desc' => 'Mainly clear', 'icon' => '🌤️', 'class' => 'sunny'],
        2 => ['desc' => 'Partly cloudy', 'icon' => '⛅', 'class' => 'cloudy'],
        3 => ['desc' => 'Overcast', 'icon' => '☁️', 'class' => 'cloudy'],
        45 => ['desc' => 'Foggy', 'icon' => '🌫️', 'class' => 'foggy'],
        48 => ['desc' => 'Depositing rime fog', 'icon' => '🌫️', 'class' => 'foggy'],
        51 => ['desc' => 'Light drizzle', 'icon' => '🌦️', 'class' => 'rainy'],
        53 => ['desc' => 'Moderate drizzle', 'icon' => '🌦️', 'class' => 'rainy'],
        55 => ['desc' => 'Dense drizzle', 'icon' => '🌧️', 'class' => 'rainy'],
        61 => ['desc' => 'Slight rain', 'icon' => '🌧️', 'class' => 'rainy'],
        63 => ['desc' => 'Moderate rain', 'icon' => '🌧️', 'class' => 'rainy'],
        65 => ['desc' => 'Heavy rain', 'icon' => '🌧️', 'class' => 'rainy'],
        71 => ['desc' => 'Slight snow', 'icon' => '❄️', 'class' => 'snowy'],
        73 => ['desc' => 'Moderate snow', 'icon' => '❄️', 'class' => 'snowy'],
        75 => ['desc' => 'Heavy snow', 'icon' => '❄️', 'class' => 'snowy'],
        80 => ['desc' => 'Slight rain showers', 'icon' => '🌦️', 'class' => 'rainy'],
        81 => ['desc' => 'Moderate rain showers', 'icon' => '🌧️', 'class' => 'rainy'],
        82 => ['desc' => 'Violent rain showers', 'icon' => '⛈️', 'class' => 'stormy'],
        95 => ['desc' => 'Thunderstorm', 'icon' => '⛈️', 'class' => 'stormy'],
        96 => ['desc' => 'Thunderstorm with hail', 'icon' => '⛈️', 'class' => 'stormy'],
        99 => ['desc' => 'Thunderstorm with heavy hail', 'icon' => '⛈️', 'class' => 'stormy']
    ];
    return $weatherCodes[$code] ?? ['desc' => 'Unknown', 'icon' => '❓', 'class' => 'unknown'];
}

function getTempColor($temp) {
    if ($temp >= 30) return '#ef4444';
    if ($temp >= 25) return '#f97316';
    if ($temp >= 20) return '#eab308';
    if ($temp >= 15) return '#22c55e';
    return '#06b6d4';
}

// Helper functions
function getAlertIcon($type) {
    $icons = [
        'weather' => '<i class="fas fa-cloud-bolt"></i>',
        'medical' => '<i class="fas fa-person-falling"></i>',
        'trail' => '<i class="fas fa-road"></i>',
        'safety' => '<i class="fas fa-shield-alt"></i>',
        'animal' => '<i class="fas fa-paw"></i>',
        'other' => '<i class="fas fa-bell"></i>'
    ];
    return $icons[$type] ?? $icons['other'];
}

function getSeverityBadge($severity) {
    $badges = [
        'critical' => '<span class="severity-badge critical">🔴 CRITICAL</span>',
        'high' => '<span class="severity-badge high">🟠 HIGH</span>',
        'medium' => '<span class="severity-badge medium">🟡 MEDIUM</span>',
        'low' => '<span class="severity-badge low">🟢 LOW</span>'
    ];
    return $badges[$severity] ?? $badges['medium'];
}

function formatTimeAgo($timestamp) {
    if (!$timestamp) return 'Unknown';
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return floor($diff / 86400) . ' days ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKBAY — Alert Command Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="shared.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

      <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
    <style>
        .alert-item {
            transition: all 0.2s ease;
            margin-bottom: 12px;
            padding: 12px;
            border-radius: 8px;
            background: var(--bg-elevated);
            border: 1px solid var(--line);
        }
        
        .alert-item:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .severity-badge {
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-left: 8px;
        }
        
        .severity-badge.critical { background: #dc2626; color: white; }
        .severity-badge.high { background: #f97316; color: white; }
        .severity-badge.medium { background: #eab308; color: #422006; }
        .severity-badge.low { background: #22c55e; color: white; }
        
        .alert-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.75rem;
        }
        
        .auto-refresh-indicator {
            font-size: 0.7rem;
            color: var(--ink-4);
            padding: 4px 8px;
            background: var(--bg-elevated);
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-btn {
            padding: 8px 14px;
            border-radius: 8px;
            background: var(--bg-elevated);
            border: 1px solid var(--line);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            color: #5B6A7E;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            background: #F8F9FB;
        }

        .filter-btn.active {
            background: #111318;
            color: white;
            border-color: #111318;
        }
        
        .alert-details {
            margin-top: 8px;
            padding: 8px;
            background: rgba(0,0,0,0.05);
            border-radius: 6px;
            font-size: 0.75rem;
        }
        
        .notification-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .broadcast-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
        }
        
        .broadcast-item:last-child {
            border-bottom: none;
        }
        
        .guide-alert-demo {
            margin-top: 20px;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            color: white;
        }
        
        .guide-alert-demo button {
            background: white;
            color: #764ba2;
            border: none;
        }
        
        /* Weather Tabs Styling */
        .weather-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .weather-tab {
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 0.85rem;
            color: #5B6A7E;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .weather-tab:hover {
            background: #F8F9FB;
        }
        
        .weather-tab.active {
            background: #111318;
            color: white;
        }
        
        .weather-card {
            padding: 16px;
            background: #F8F9FB;
            border-radius: 12px;
            margin-bottom: 12px;
        }
        
        .weather-current {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .weather-temp {
            font-size: 3rem;
            font-weight: 700;
        }
        
        .weather-icon {
            font-size: 3rem;
        }
        
        .weather-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }
        
        .weather-detail-item {
            text-align: center;
            padding: 8px;
            background: white;
            border-radius: 8px;
        }
        
        .forecast-days {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            overflow-x: auto;
        }
        
        .forecast-day {
            flex: 1;
            min-width: 80px;
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
        }
        
        .temp-high {
            color: #ef4444;
            font-weight: 600;
        }
        
        .temp-low {
            color: #06b6d4;
        }
        
        .weather-loading {
            text-align: center;
            padding: 40px;
            color: var(--ink-4);
        }
        
        .mountain-info {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            font-size: 0.75rem;
            color: #5B6A7E;
        }
        
        .sunny { color: #fbbf24; }
        .cloudy { color: #9ca3af; }
        .rainy { color: #60a5fa; }
        .stormy { color: #6366f1; }
        .snowy { color: #e0f2fe; }
        .foggy { color: #d1d5db; }
    </style>
</head>
<body data-page="alerts">
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
            <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
            <li class="nav-item" data-href="/pages/admin/reviews.php"><i class="fas fa-star"></i> Reviews</li>
            <li class="nav-item active" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
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

  <!-- MAIN -->
  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-bell"></i> Alert Command Center</div>
      <div class="topbar-right">
        <div class="auto-refresh-indicator">
                    <i class="fas fa-sync-alt fa-fw"></i>
                    <span>Auto-refresh: <span id="refreshCountdown">30</span>s</span>
                </div>
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
            <!-- Filter Bar -->
            <div class="filter-bar">
                <button class="filter-btn active" data-filter="all"><i class="fas fa-list"></i> All Active</button>
                <button class="filter-btn" data-filter="critical"><i class="fas fa-circle-exclamation"></i> Critical</button>
                <button class="filter-btn" data-filter="high"><i class="fas fa-triangle-exclamation"></i> High</button>
                <button class="filter-btn" data-filter="medical"><i class="fas fa-kit-medical"></i> Medical</button>
                <button class="filter-btn" data-filter="weather"><i class="fas fa-cloud-bolt"></i> Weather</button>
                <button class="filter-btn" data-filter="trail"><i class="fas fa-route"></i> Trail</button>
                <button class="btn" id="refreshBtn" style="margin-left: auto;">
                    <i class="fas fa-sync-alt"></i> Refresh Now
                </button>
            </div>

            <div class="two-col">
                <!-- ACTIVE ALERTS (from guides) -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">
                            <i class="fas fa-exclamation-triangle"></i> Active Incidents from Guides
                        </span>
                        <span class="panel-badge warn" id="alertCount"><?= $activeAlertCount ?> open</span>
                    </div>

                    <div id="alertsContainer">
                        <?php if (empty($alerts)): ?>
                            <div style="text-align:center; padding:40px; color:var(--ink-4);">
                                <i class="fas fa-check-circle" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                                No active alerts! All trails are safe.
                                <div style="margin-top: 16px; font-size: 0.8rem;">
                                    <i class="fas fa-info-circle"></i> Guides can submit alerts from their mobile app
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert-item" data-alert-id="<?= $alert['id'] ?>" data-severity="<?= $alert['severity'] ?>" data-type="<?= $alert['type'] ?>">
                                    <div class="alert-icon" style="float: left; margin-right: 12px; font-size: 1.2rem;">
                                        <?= getAlertIcon($alert['type']) ?>
                                    </div>
                                    <div class="alert-body" style="margin-left: 32px;">
                                        <div class="alert-title" style="font-weight: 600; margin-bottom: 4px;">
                                            <?= htmlspecialchars($alert['title']) ?>
                                            <?= getSeverityBadge($alert['severity']) ?>
                                        </div>
                                        <div class="alert-meta" style="font-size: 0.75rem; color: var(--ink-4); margin-bottom: 6px;">
                                            <i class="fas fa-location-dot"></i> <?= htmlspecialchars($alert['location']) ?>
                                            <?php if ($alert['trail_name']): ?> · Trail: <?= htmlspecialchars($alert['trail_name']) ?><?php endif; ?>
                                            <br>
                                            <i class="fas fa-user"></i> Reported by: <?= htmlspecialchars($alert['reporter_name'] ?? 'Guide') ?> 
                                            · <i class="fas fa-clock"></i> <?= formatTimeAgo($alert['created_at']) ?>
                                            <?php if ($alert['acknowledgement_count'] > 0): ?>
                                                · <i class="fas fa-check-circle"></i> <?= $alert['acknowledgement_count'] ?> staff acknowledged
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($alert['description'])): ?>
                                            <div class="alert-details">
                                                <i class="fas fa-file-alt"></i> <?= htmlspecialchars($alert['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="alert-actions">
                                            <?php if ($alert['status'] !== 'resolved' && $alert['status'] !== 'false_alarm'): ?>
                                                <button class="btn btn-sm ack-alert-btn" data-id="<?= $alert['id'] ?>">
                                                    <i class="fas fa-check"></i> Acknowledge
                                                </button>
                                                <button class="btn btn-sm resolve-alert-btn" data-id="<?= $alert['id'] ?>">
                                                    <i class="fas fa-check-double"></i> Resolve
                                                </button>
                                                <button class="btn btn-sm false-alarm-btn" data-id="<?= $alert['id'] ?>">
                                                    <i class="fas fa-ban"></i> False Alarm
                                                </button>
                                            <?php else: ?>
                                                <span class="badge green"><i class="fas fa-check"></i> <?= ucfirst($alert['status']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($activeAlertCount > 0): ?>
                        <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line);">
                            <button class="btn" id="resolveAllBtn">
                                <i class="fas fa-check-double"></i> Resolve All Open Alerts
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- BROADCAST TO GUIDES PANEL -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">
                            <i class="fas fa-broadcast-tower"></i> Broadcast to Guides
                        </span>
                        <span class="panel-badge">All Guides Will Receive</span>
                    </div>
                    
                    <textarea id="broadcastTxt" rows="4" placeholder="Send urgent instructions or safety advisories to all guides..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--line); font-family: inherit;"></textarea>
                    
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button class="btn btn-primary" id="sendBroadcastBtn" style="flex: 1">
                            <i class="fas fa-paper-plane"></i> Send to All Guides
                        </button>
                        <button class="btn" id="previewBroadcastBtn">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <div class="panel-title" style="margin-bottom: 12px;">
                            <i class="fas fa-history"></i> Recent Guide Broadcasts
                        </div>
                        <div id="recentBroadcasts" style="max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($recentBroadcasts)): ?>
                                <?php foreach ($recentBroadcasts as $broadcast): ?>
                                    <div class="broadcast-item">
                                        <div style="font-size: 0.7rem; color: var(--ink-4); margin-bottom: 4px;">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($broadcast['sender_name'] ?? 'Admin') ?> · 
                                            <?= formatTimeAgo($broadcast['created_at']) ?>
                                        </div>
                                        <div style="font-size: 0.85rem;"><?= nl2br(htmlspecialchars($broadcast['message'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="color: var(--ink-4); text-align: center; padding: 20px;">
                                    No broadcasts sent to guides yet
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- WEATHER FORECAST PANEL - NASUGBU MOUNTAINS -->
            <div class="panel" style="margin-top: 20px;">
                <div class="panel-header">
                    <span class="panel-title">
                        <i class="fas fa-cloud-sun"></i> Mountain Weather Forecast
                    </span>
                    <span class="panel-badge">Nasugbu, Batangas</span>
                </div>
                
                <div class="weather-tabs" id="weatherTabs">
                    <?php foreach ($mountains as $name => $data): ?>
                        <button class="weather-tab" data-mountain="<?= htmlspecialchars($name) ?>">
                            <i class="fas fa-mountain"></i> <?= htmlspecialchars($name) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <div id="weatherContent">
                    <div class="weather-loading">
                        <i class="fas fa-spinner fa-spin"></i> Loading weather data...
                    </div>
                </div>
            </div>
            
           
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal-overlay" id="customAlertModal" style="z-index: 10000; display:none; position: fixed; inset: 0; background: rgba(10,12,18,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div class="modal-box" style="width: 100%; max-width: 400px; padding: 32px 24px; text-align: center; background: white; border-radius: 24px; box-shadow: 0 32px 64px rgba(0,0,0,0.18);">
        <div style="font-size: 36px; color: #111318; margin-bottom: 16px;"><i class="fas fa-circle-exclamation"></i></div>
        <div class="modal-title" id="customAlertTitle" style="margin-bottom: 8px; font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: #111318;">Notice</div>
        <div id="customAlertMessage" style="font-size: 14px; color: #5B6A7E; line-height: 1.5; margin-bottom: 28px;"></div>
        <button class="btn btn-primary" onclick="closeCustomAlert()" style="width: 100%; padding: 12px; border-radius: 12px; border: none; background: #111318; color: white; cursor: pointer; font-family: 'Inter', sans-serif;">OK</button>
    </div>
</div>

<div class="modal-overlay" id="customConfirmModal" style="z-index: 10000; display:none; position: fixed; inset: 0; background: rgba(10,12,18,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div class="modal-box" style="width: 100%; max-width: 400px; padding: 32px 24px; text-align: center; background: white; border-radius: 24px; box-shadow: 0 32px 64px rgba(0,0,0,0.18);">
        <div style="font-size: 36px; color: #E67E22; margin-bottom: 16px;"><i class="fas fa-circle-question"></i></div>
        <div class="modal-title" id="customConfirmTitle" style="margin-bottom: 8px; font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: #111318;">Confirm</div>
        <div id="customConfirmMessage" style="font-size: 14px; color: #5B6A7E; line-height: 1.5; margin-bottom: 28px; white-space: pre-wrap;"></div>
        <div style="display: flex; gap: 12px;">
            <button class="btn" onclick="closeCustomConfirm()" style="flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #E5E9EF; background: transparent; cursor: pointer; font-family: 'Inter', sans-serif; font-weight: 500;">Cancel</button>
            <button class="btn btn-primary" id="customConfirmBtn" style="flex: 1; padding: 12px; border-radius: 12px; border: none; background: #111318; color: white; cursor: pointer; font-family: 'Inter', sans-serif;">Confirm</button>
        </div>
    </div>
</div>

<!-- Guide Alert Submission Modal -->
<div id="guideAlertModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; padding: 24px;">
        <h3 style="margin-bottom: 16px;"><i class="fas fa-exclamation-triangle"></i> Submit Field Alert (Guide Mode)</h3>
        <input type="text" id="alertTitle" placeholder="Alert Title (e.g., 'Injured Hiker on Trail 3')" style="width:100%; margin-bottom:12px; padding:10px; border:1px solid #ddd; border-radius:6px;">
        <textarea id="alertDesc" placeholder="Detailed description of the incident..." rows="3" style="width:100%; margin-bottom:12px; padding:10px; border:1px solid #ddd; border-radius:6px;"></textarea>
        <input type="text" id="alertLocation" placeholder="Location (e.g., 'North Ridge, Mt. Batulao')" style="width:100%; margin-bottom:12px; padding:10px; border:1px solid #ddd; border-radius:6px;">
        <input type="text" id="alertTrail" placeholder="Trail Name (optional)" style="width:100%; margin-bottom:12px; padding:10px; border:1px solid #ddd; border-radius:6px;">
        <select id="alertType" style="width:100%; margin-bottom:12px; padding:10px; border:1px solid #ddd; border-radius:6px;">
            <option value="weather">⚠️ Weather Emergency</option>
            <option value="medical">🚑 Medical Emergency</option>
            <option value="trail">🥾 Trail Hazard</option>
            <option value="safety">🛡️ Safety Concern</option>
            <option value="animal">🐾 Wildlife Encounter</option>
            <option value="other">📢 Other</option>
        </select>
        <select id="alertSeverity" style="width:100%; margin-bottom:12px; padding:10px; border:1px solid #ddd; border-radius:6px;">
            <option value="low">🟢 Low - Minor issue, monitor only</option>
            <option value="medium">🟡 Medium - Caution advised</option>
            <option value="high">🟠 High - Urgent attention needed</option>
            <option value="critical">🔴 Critical - Immediate emergency!</option>
        </select>
        <div style="display:flex; gap:8px;">
            <button onclick="submitGuideAlert()" class="btn btn-primary" style="flex:1">🚨 Submit Alert</button>
            <button onclick="closeGuideModal()" class="btn">Cancel</button>
        </div>
    </div>
</div>

<script>
// Mountain weather data storage
const mountainWeatherData = {};
let activeMountain = 'Mt. Talamitam';
let refreshInterval;
let countdown = 30;
let currentFilter = 'all';
let previousAlertIds = new Set();

// Mountain coordinates
const mountains = {
    'Mt. Talamitam': { lat: 14.0738, lon: 120.7038, elevation: 630 },
    'Mt. Batulao': { lat: 14.0458, lon: 120.7917, elevation: 693 },
    'Mt. Lantik': { lat: 14.0600, lon: 120.7200, elevation: 500 },
    'Mt. Apayang': { lat: 14.0500, lon: 120.7400, elevation: 550 }
};

// Custom Alert Functions
function showCustomAlert(message, title = 'Notice') {
    document.getElementById('customAlertTitle').textContent = title;
    document.getElementById('customAlertMessage').textContent = message;
    document.getElementById('customAlertModal').style.display = 'flex';
}

function closeCustomAlert() {
    document.getElementById('customAlertModal').style.display = 'none';
}

function showCustomConfirm(message, onConfirm, title = 'Confirm') {
    document.getElementById('customConfirmTitle').textContent = title;
    document.getElementById('customConfirmMessage').textContent = message;
    const confirmBtn = document.getElementById('customConfirmBtn');
    
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
    
    newBtn.addEventListener('click', () => {
        closeCustomConfirm();
        if (typeof onConfirm === 'function') onConfirm();
    });
    
    document.getElementById('customConfirmModal').style.display = 'flex';
}

function closeCustomConfirm() {
    document.getElementById('customConfirmModal').style.display = 'none';
}

// Weather Functions
function getWeatherIcon(code) {
    if (code === 0) return '☀️';
    if (code >= 1 && code <= 2) return '🌤️';
    if (code === 3) return '☁️';
    if (code >= 45 && code <= 48) return '🌫️';
    if (code >= 51 && code <= 55) return '🌦️';
    if (code >= 61 && code <= 65) return '🌧️';
    if (code >= 80 && code <= 82) return '🌧️';
    if (code >= 95) return '⛈️';
    return '🌡️';
}

function getWeatherClass(code) {
    if (code === 0) return 'sunny';
    if (code >= 1 && code <= 3) return 'cloudy';
    if (code >= 45 && code <= 48) return 'foggy';
    if (code >= 51 && code <= 65) return 'rainy';
    if (code >= 80 && code <= 82) return 'rainy';
    if (code >= 95) return 'stormy';
    return 'cloudy';
}

function getWeatherDescription(code) {
    const descriptions = {
        0: 'Clear sky',
        1: 'Mainly clear',
        2: 'Partly cloudy',
        3: 'Overcast',
        45: 'Foggy',
        48: 'Foggy',
        51: 'Light drizzle',
        53: 'Moderate drizzle',
        55: 'Dense drizzle',
        61: 'Light rain',
        63: 'Moderate rain',
        65: 'Heavy rain',
        80: 'Rain showers',
        81: 'Rain showers',
        82: 'Violent rain',
        95: 'Thunderstorm',
        96: 'Thunderstorm',
        99: 'Thunderstorm'
    };
    return descriptions[code] || 'Variable';
}

async function fetchMountainWeather(mountainName) {
    const coords = mountains[mountainName];
    if (!coords) return null;
    
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${coords.lat}&longitude=${coords.lon}&current_weather=true&daily=temperature_2m_max,temperature_2m_min,weathercode&timezone=Asia%2FManila&windspeed_unit=ms`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        return {
            current: {
                temp: Math.round(data.current_weather.temperature),
                wind: data.current_weather.windspeed,
                code: data.current_weather.weathercode
            },
            daily: {
                times: data.daily.time.slice(1, 5),
                maxTemps: data.daily.temperature_2m_max.slice(1, 5).map(t => Math.round(t)),
                minTemps: data.daily.temperature_2m_min.slice(1, 5).map(t => Math.round(t)),
                codes: data.daily.weathercode.slice(1, 5)
            }
        };
    } catch (error) {
        console.error(`Error fetching weather for ${mountainName}:`, error);
        return null;
    }
}

async function loadAllWeatherData() {
    const weatherContent = document.getElementById('weatherContent');
    weatherContent.innerHTML = '<div class="weather-loading"><i class="fas fa-spinner fa-spin"></i> Loading weather data...</div>';
    
    for (const mountain of Object.keys(mountains)) {
        const data = await fetchMountainWeather(mountain);
        if (data) {
            mountainWeatherData[mountain] = data;
        }
    }
    
    displayWeatherForMountain(activeMountain);
}

function displayWeatherForMountain(mountainName) {
    const weatherContent = document.getElementById('weatherContent');
    const data = mountainWeatherData[mountainName];
    const mountain = mountains[mountainName];
    
    if (!data) {
        weatherContent.innerHTML = '<div class="weather-loading"><i class="fas fa-exclamation-triangle"></i> Unable to load weather data</div>';
        return;
    }
    
    const current = data.current;
    const daily = data.daily;
    const weatherClass = getWeatherClass(current.code);
    const weatherIcon = getWeatherIcon(current.code);
    const weatherDesc = getWeatherDescription(current.code);
    const tempColor = current.temp >= 30 ? '#ef4444' : (current.temp >= 25 ? '#f97316' : (current.temp >= 20 ? '#eab308' : '#06b6d4'));
    
    weatherContent.innerHTML = `
        <div class="weather-card">
            <div class="weather-current">
                <div>
                    <div style="font-size: 0.8rem; color: #5B6A7E;">Current Conditions</div>
                    <div class="weather-temp" style="color: ${tempColor}">${current.temp}°C</div>
                    <div style="font-size: 0.9rem; margin-top: 4px;">Feels like ${current.temp}°C</div>
                </div>
                <div class="weather-icon ${weatherClass}" style="text-align: center;">
                    <div style="font-size: 3rem;">${weatherIcon}</div>
                    <div style="font-size: 0.85rem; margin-top: 4px;">${weatherDesc}</div>
                </div>
            </div>
            
            <div class="weather-details">
                <div class="weather-detail-item">
                    <i class="fas fa-wind"></i>
                    <div>${current.wind} km/h</div>
                    <div style="font-size: 0.7rem;">Wind Speed</div>
                </div>
                <div class="weather-detail-item">
                    <i class="fas fa-mountain"></i>
                    <div>${mountain.elevation}m</div>
                    <div style="font-size: 0.7rem;">Elevation</div>
                </div>
                <div class="weather-detail-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <div>${mountain.difficulty || 'Moderate'}</div>
                    <div style="font-size: 0.7rem;">Difficulty</div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <div style="font-size: 0.75rem; font-weight: 600; margin-bottom: 12px; color: #5B6A7E;">5-DAY FORECAST</div>
                <div class="forecast-days">
                    ${daily.times.map((time, i) => {
                        const dayName = new Date(time).toLocaleDateString('en-PH', { weekday: 'short' });
                        const maxTemp = daily.maxTemps[i];
                        const minTemp = daily.minTemps[i];
                        const forecastIcon = getWeatherIcon(daily.codes[i]);
                        const forecastClass = getWeatherClass(daily.codes[i]);
                        return `
                            <div class="forecast-day">
                                <div style="font-weight: 600; margin-bottom: 8px;">${dayName}</div>
                                <div class="${forecastClass}" style="font-size: 1.5rem; margin-bottom: 8px;">${forecastIcon}</div>
                                <div><span class="temp-high">${maxTemp}°</span> / <span class="temp-low">${minTemp}°</span></div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
            
            <div class="mountain-info">
                <i class="fas fa-info-circle"></i>
                <span>Trails: ${mountain.trails || 'Multiple'} | Best season: Dec-May | Always check local guidelines before hiking</span>
            </div>
        </div>
    `;
}

// Alert Functions
function refreshAlerts() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=get_alerts'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateAlertsDisplay(data.alerts);
            updateAlertCount(data.alerts.length);
            checkForNewAlerts(data.alerts);
        }
    })
    .catch(error => console.error('Error refreshing alerts:', error));
}

function checkForNewAlerts(alerts) {
    const currentAlertIds = new Set(alerts.map(a => String(a.id)));
    
    currentAlertIds.forEach(id => {
        if (!previousAlertIds.has(id)) {
            showNotification('🚨 New alert from a guide!');
        }
    });
    
    previousAlertIds = currentAlertIds;
}

function showNotification(message) {
    const toast = document.createElement('div');
    toast.className = 'notification-toast';
    toast.innerHTML = `
        <i class="fas fa-bell" style="color: #60a5fa;"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:white; cursor:pointer;">✕</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function updateAlertsDisplay(alerts) {
    const container = document.getElementById('alertsContainer');
    if (!container) return;
    
    if (alerts.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px; color:var(--ink-4);">
                <i class="fas fa-check-circle" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                No active alerts! All trails are safe.
            </div>
        `;
        return;
    }
    
    let filteredAlerts = alerts;
    if (currentFilter !== 'all') {
        if (currentFilter === 'critical' || currentFilter === 'high') {
            filteredAlerts = alerts.filter(a => a.severity === currentFilter);
        } else {
            filteredAlerts = alerts.filter(a => a.type === currentFilter);
        }
    }
    
    if (filteredAlerts.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding:40px;">No alerts match the filter</div>`;
        return;
    }
    
    container.innerHTML = filteredAlerts.map(alert => `
        <div class="alert-item" data-alert-id="${alert.id}">
            <div class="alert-icon" style="float: left; margin-right: 12px; font-size: 1.2rem;">
                ${getAlertIcon(alert.type)}
            </div>
            <div class="alert-body" style="margin-left: 32px;">
                <div class="alert-title" style="font-weight: 600; margin-bottom: 4px;">
                    ${escapeHtml(alert.title)}
                    ${getSeverityBadge(alert.severity)}
                </div>
                <div class="alert-meta" style="font-size: 0.75rem; color: #666; margin-bottom: 6px;">
                    <i class="fas fa-location-dot"></i> ${escapeHtml(alert.location)}
                    ${alert.trail_name ? ` · Trail: ${escapeHtml(alert.trail_name)}` : ''}
                    <br>
                    <i class="fas fa-user"></i> Reported by: ${escapeHtml(alert.reporter_name || 'Guide')}
                    · <i class="fas fa-clock"></i> ${formatTimeAgo(alert.created_at)}
                    ${alert.acknowledgement_count > 0 ? ` · <i class="fas fa-check-circle"></i> ${alert.acknowledgement_count} staff acknowledged` : ''}
                </div>
                ${alert.description ? `
                    <div class="alert-details">
                        <i class="fas fa-file-alt"></i> ${escapeHtml(alert.description)}
                    </div>
                ` : ''}
                <div class="alert-actions">
                    <button class="btn btn-sm ack-alert-btn" data-id="${alert.id}">
                        <i class="fas fa-check"></i> Acknowledge
                    </button>
                    <button class="btn btn-sm resolve-alert-btn" data-id="${alert.id}">
                        <i class="fas fa-check-double"></i> Resolve
                    </button>
                    <button class="btn btn-sm false-alarm-btn" data-id="${alert.id}">
                        <i class="fas fa-ban"></i> False Alarm
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    attachEventListeners();
}

function getAlertIcon(type) {
    const icons = {
        'weather': '<i class="fas fa-cloud-bolt"></i>',
        'medical': '<i class="fas fa-person-falling"></i>',
        'trail': '<i class="fas fa-road"></i>',
        'safety': '<i class="fas fa-shield-alt"></i>',
        'animal': '<i class="fas fa-paw"></i>',
        'other': '<i class="fas fa-bell"></i>'
    };
    return icons[type] || icons.other;
}

function getSeverityBadge(severity) {
    const badges = {
        'critical': '<span class="severity-badge critical">🔴 CRITICAL</span>',
        'high': '<span class="severity-badge high">🟠 HIGH</span>',
        'medium': '<span class="severity-badge medium">🟡 MEDIUM</span>',
        'low': '<span class="severity-badge low">🟢 LOW</span>'
    };
    return badges[severity] || badges.medium;
}

function formatTimeAgo(timestamp) {
    if (!timestamp) return 'Unknown';
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    return Math.floor(diff / 86400) + ' days ago';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateAlertCount(count) {
    const countElement = document.getElementById('alertCount');
    if (countElement) {
        countElement.textContent = count + ' open';
    }
}

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
        if (result.success) {
            showNotification(result.message);
            refreshAlerts();
        } else {
            showCustomAlert('Error: ' + result.message, 'Error');
        }
    })
    .catch(error => console.error('Error:', error));
}

function attachEventListeners() {
    document.querySelectorAll('.ack-alert-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const alertId = btn.dataset.id;
            showCustomConfirm('Acknowledge this alert? This will notify the guide that help is on the way.', () => {
                sendAlertAction('acknowledge', {alert_id: alertId});
            });
        });
    });
    
    document.querySelectorAll('.resolve-alert-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const alertId = btn.dataset.id;
            showCustomConfirm('Mark this alert as resolved? The incident has been handled.', () => {
                sendAlertAction('resolve', {alert_id: alertId});
            });
        });
    });
    
    document.querySelectorAll('.false-alarm-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const alertId = btn.dataset.id;
            showCustomConfirm('Mark as false alarm? This will close the alert.', () => {
                sendAlertAction('false_alarm', {alert_id: alertId});
            });
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateDate();
    startAutoRefresh();
    attachFilterListeners();
    attachEventListeners();
    loadAllWeatherData();
    setupWeatherTabs();
    
    document.querySelectorAll('.alert-item').forEach(el => {
        previousAlertIds.add(el.dataset.alertId);
    });
});

function updateDate() {
    const d = new Date();
    const dateElement = document.getElementById('liveDate');
    if (dateElement) {
        dateElement.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() +
            '  ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    }
}
setInterval(updateDate, 1000);

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    
    refreshInterval = setInterval(() => {
        countdown--;
        const countdownElement = document.getElementById('refreshCountdown');
        if (countdownElement) {
            countdownElement.textContent = countdown;
        }
        
        if (countdown <= 0) {
            refreshAlerts();
            countdown = 30;
        }
    }, 1000);
}

function attachFilterListeners() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            refreshAlerts();
        });
    });
    
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        const newRefreshBtn = refreshBtn.cloneNode(true);
        refreshBtn.parentNode.replaceChild(newRefreshBtn, refreshBtn);
        
        newRefreshBtn.addEventListener('click', (e) => {
            e.preventDefault();
            refreshAlerts();
            countdown = 30;
            showNotification('Refreshing alerts...');
        });
    }
    
    const resolveAllBtn = document.getElementById('resolveAllBtn');
    if (resolveAllBtn) {
        resolveAllBtn.addEventListener('click', () => {
            showCustomConfirm('⚠️ WARNING: This will resolve ALL open alerts. Continue?', () => {
                sendAlertAction('resolve_all', {});
            });
        });
    }
    
    const sendBroadcastBtn = document.getElementById('sendBroadcastBtn');
    if (sendBroadcastBtn) {
        sendBroadcastBtn.addEventListener('click', () => {
            const message = document.getElementById('broadcastTxt').value;
            if (!message.trim()) {
                showCustomAlert("Please enter a message to broadcast.", "Validation Error");
                return;
            }
            showCustomConfirm(`Send to ALL guides?\n\nMessage: "${message}"\n\nThis will appear in their app immediately.`, () => {
                sendAlertAction('broadcast_to_guides', {message: message});
                document.getElementById('broadcastTxt').value = '';
            });
        });
    }
    
    const previewBroadcastBtn = document.getElementById('previewBroadcastBtn');
    if (previewBroadcastBtn) {
        previewBroadcastBtn.addEventListener('click', () => {
            const message = document.getElementById('broadcastTxt').value;
            showCustomAlert(`"${message || 'Empty message'}"`, "PREVIEW - Message to Guides");
        });
    }
}

function setupWeatherTabs() {
    const tabs = document.querySelectorAll('.weather-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeMountain = tab.dataset.mountain;
            if (mountainWeatherData[activeMountain]) {
                displayWeatherForMountain(activeMountain);
            } else {
                fetchMountainWeather(activeMountain).then(data => {
                    if (data) {
                        mountainWeatherData[activeMountain] = data;
                        displayWeatherForMountain(activeMountain);
                    }
                });
            }
        });
    });
    if (tabs.length > 0) {
        tabs[0].classList.add('active');
    }
}

function openGuideAlertModal() {
    document.getElementById('guideAlertModal').style.display = 'flex';
}

function closeGuideModal() {
    document.getElementById('guideAlertModal').style.display = 'none';
}

function submitGuideAlert() {
    const title = document.getElementById('alertTitle').value;
    const description = document.getElementById('alertDesc').value;
    const location = document.getElementById('alertLocation').value;
    const trail = document.getElementById('alertTrail').value;
    const type = document.getElementById('alertType').value;
    const severity = document.getElementById('alertSeverity').value;
    
    if (!title || !location) {
        showCustomAlert('Please fill in Title and Location', 'Validation Error');
        return;
    }
    
    fetch('/api/submit_alert.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            title: title,
            description: description,
            location: location,
            trail_name: trail,
            type: type,
            severity: severity,
            guide_id: 1,
            guide_name: 'Test Guide'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showCustomAlert('Alert submitted successfully! It will appear in Active Alerts.', 'Success');
            closeGuideModal();
            refreshAlerts();
            
            document.getElementById('alertTitle').value = '';
            document.getElementById('alertDesc').value = '';
            document.getElementById('alertLocation').value = '';
            document.getElementById('alertTrail').value = '';
        } else {
            showCustomAlert('Error: ' + data.message, 'Error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCustomAlert('Demo mode: Alert would be submitted here.', 'Info');
        closeGuideModal();
    });
}

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => { 
        if(item.dataset.href) {
            window.location.href = item.dataset.href; 
        }
    });
});
</script>

<!-- Include Modals -->
<?php 
$modalPath = __DIR__ . '/profile-modal.php';
if (file_exists($modalPath)) include_once $modalPath;

$logoutModalPath = __DIR__ . '/../../includes/logout-modal.php';
if (file_exists($logoutModalPath)) include_once $logoutModalPath;
?>
</body>
</html>