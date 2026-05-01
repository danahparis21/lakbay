<?php
// advisories.php - Complete Lakbay Manager Advisories System
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$manager_id = $_SESSION['user_id'];

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

$manager_name = $manager['name'];
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));
$manager_role = $manager['role'];

// Get manager's assigned mountains
$stmt = $pdo->prepare("
    SELECT m.*, 
           mm.is_primary,
           (SELECT AVG(rating) FROM reviews WHERE mountain_id = m.id AND status = 'approved') as avg_rating
    FROM mountains m
    INNER JOIN manager_mountains mm ON m.id = mm.mountain_id
    WHERE mm.manager_id = ?
    ORDER BY mm.is_primary DESC, m.name
");
$stmt->execute([$manager_id]);
$assigned_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mountain_ids = array_column($assigned_mountains, 'id');
$mountain_ids_placeholder = !empty($mountain_ids) ? implode(',', array_fill(0, count($mountain_ids), '?')) : '';

// Get active alerts for manager's mountains
$active_alerts = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as reporter_name, u.role as reporter_role,
               m.name as mountain_name
        FROM alerts a
        INNER JOIN mountains m ON a.mountain_id = m.id
        LEFT JOIN users u ON a.reported_by = u.id
        WHERE a.mountain_id IN ($mountain_ids_placeholder) 
          AND a.status = 'active'
        ORDER BY a.severity = 'critical' DESC, a.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $active_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent crowd reports
$crowd_reports = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT cr.*, m.name as mountain_name,
               u.name as reporter_name,
               TIMESTAMPDIFF(MINUTE, cr.created_at, NOW()) as minutes_ago
        FROM crowd_reports cr
        INNER JOIN mountains m ON cr.mountain_id = m.id
        LEFT JOIN users u ON cr.reported_by = u.id
        WHERE cr.mountain_id IN ($mountain_ids_placeholder) 
          AND cr.expires_at > NOW()
        ORDER BY cr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($mountain_ids);
    $crowd_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get active safety alerts (from guides during hikes)
$safety_alerts = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT sa.*, b.booking_number, m.name as mountain_name,
               u.name as reporter_name
        FROM safety_alerts sa
        INNER JOIN bookings b ON sa.booking_id = b.id
        INNER JOIN mountains m ON b.mountain_id = m.id
        LEFT JOIN users u ON sa.reported_by = u.id
        WHERE b.mountain_id IN ($mountain_ids_placeholder) 
          AND sa.status = 'active'
        ORDER BY sa.severity = 'critical' DESC, sa.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $safety_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get broadcasts (advisories from admin/managers)
$broadcasts = [];
$stmt = $pdo->prepare("
    SELECT b.*, u.name as sender_name,
           (SELECT COUNT(*) FROM broadcast_read_status 
            WHERE broadcast_id = b.id AND user_id = ? AND user_role = 'manager') as is_read
    FROM broadcasts b
    INNER JOIN users u ON b.sender_id = u.id
    WHERE b.recipient_role IN ('all_guides', 'all_hikers', 'specific_guide', 'all')
       OR b.recipient_role IS NULL
    ORDER BY b.created_at DESC
    LIMIT 50
");
$stmt->execute([$manager_id]);
$broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Open-Meteo API - Free, no API key required!
function getWeatherForecast($lat, $lng, $mountain_name) {
    // Cache for 1 hour
    $cache_file = sys_get_temp_dir() . "/weather_{$mountain_name}_" . date('Y-m-d-H') . '.json';
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
        return json_decode(file_get_contents($cache_file), true);
    }
    
    $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lng}&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weather_code&current=temperature_2m,relative_humidity_2m,wind_speed_10m,precipitation,weather_code&wind_speed_unit=kmh&timezone=Asia/Manila&forecast_days=5";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        
        // Process data for display
        $forecast = [];
        if (isset($data['daily'])) {
            for ($i = 0; $i < count($data['daily']['time']); $i++) {
                $code = $data['daily']['weather_code'][$i] ?? 0;
                $forecast[$data['daily']['time'][$i]] = [
                    'temp_max' => $data['daily']['temperature_2m_max'][$i] ?? 0,
                    'temp_min' => $data['daily']['temperature_2m_min'][$i] ?? 0,
                    'precip_prob' => $data['daily']['precipitation_probability_max'][$i] ?? 0,
                    'weather_code' => $code,
                    'icon' => getWeatherIcon($code),
                    'condition' => getWeatherDescription($code)
                ];
            }
        }
        
        // Add current conditions
        if (isset($data['current'])) {
            $forecast['current'] = [
                'temp' => $data['current']['temperature_2m'] ?? 0,
                'humidity' => $data['current']['relative_humidity_2m'] ?? 0,
                'wind' => $data['current']['wind_speed_10m'] ?? 0,
                'precip' => $data['current']['precipitation'] ?? 0,
                'weather_code' => $data['current']['weather_code'] ?? 0,
                'icon' => getWeatherIcon($data['current']['weather_code'] ?? 0),
                'condition' => getWeatherDescription($data['current']['weather_code'] ?? 0)
            ];
        }
        
        file_put_contents($cache_file, json_encode($forecast));
        return $forecast;
    }
    return null;
}

function getWeatherIcon($code) {
    if ($code === 0) return '☀️';
    if ($code <= 2) return '⛅';
    if ($code <= 3) return '☁️';
    if ($code <= 49) return '🌫';
    if ($code <= 67) return '🌧';
    if ($code <= 77) return '❄️';
    if ($code <= 82) return '🌦';
    if ($code <= 99) return '⛈';
    return '🌤';
}

function getWeatherDescription($code) {
    if ($code === 0) return 'Clear skies';
    if ($code <= 2) return 'Partly cloudy';
    if ($code <= 3) return 'Overcast';
    if ($code <= 49) return 'Foggy';
    if ($code <= 67) return 'Rainy';
    if ($code <= 77) return 'Snowy';
    if ($code <= 82) return 'Rain showers';
    if ($code <= 99) return 'Thunderstorm';
    return 'Partly cloudy';
}

// Get weather for each mountain (in the main code after fetching mountains)
$weather_data = [];
foreach ($assigned_mountains as $mountain) {
    if ($mountain['start_point_lat'] && $mountain['start_point_lng']) {
        $weather_data[$mountain['id']] = getWeatherForecast(
            $mountain['start_point_lat'], 
            $mountain['start_point_lng'], 
            $mountain['name']
        );
    }
}
// Calculate stats
$critical_count = count(array_filter($active_alerts, fn($a) => $a['severity'] === 'critical'));
$warning_count = count(array_filter($active_alerts, fn($a) => $a['severity'] === 'high' || $a['severity'] === 'medium'));
$crowd_report_count = count($crowd_reports);
$safety_alert_count = count($safety_alerts);
$unread_broadcast_count = count(array_filter($broadcasts, fn($b) => $b['is_read'] == 0));

// Helper function to format money
function fmtMoney($amount) {
    return '₱' . number_format($amount, 0);
}

// Helper for crowd level badge
function getCrowdBadge($level) {
    switch($level) {
        case 'Low': return ['color' => '#2e7d32', 'bg' => '#e8f5e9', 'icon' => '😌'];
        case 'Medium': return ['color' => '#c2410c', 'bg' => '#fff7ed', 'icon' => '👥'];
        case 'High': return ['color' => '#b91c1c', 'bg' => '#fef2f2', 'icon' => '⚠️'];
        default: return ['color' => '#666', 'bg' => '#f5f5f5', 'icon' => '📍'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY Manager — Advisories & Alerts</title>
<link rel="stylesheet" href="manager.css">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

<script>
</script>
<!-- Leaflet for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  /* Import fonts and base styles */
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

  * { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ink: #100600;
    --ink2: #3a2a1a;
    --ink3: #7a6a5a;
    --ink4: #b0a090;
    --white: #ffffff;
    --off: #faf9f7;
    --surface: #f4f1ec;
    --border: rgba(16,6,0,0.09);
    --border2: rgba(16,6,0,0.15);
    --gold: #c9a84c;
    --gold2: #e8c96a;
    --critical: #b91c1c;
    --critical-bg: #fef2f2;
    --warning: #c2410c;
    --warning-bg: #fff7ed;
    --info: #1e40af;
    --info-bg: #eff6ff;
    --resolved: #166534;
    --resolved-bg: #f0fdf4;
    --green: #2e7d32;
    --green-bg: #e8f5e9;
    --blue: #1565c0;
    --blue-bg: #e3f2fd;
    --sidebar-w: 260px;
    --sidebar-w-sm: 72px;
    --topbar-h: 64px;
    --mobile-nav-h: 60px;
    --r: 18px;
    --r-sm: 10px;
    --shadow: 0 4px 24px rgba(16,6,0,0.08);
    --shadow-lg: 0 16px 48px rgba(16,6,0,0.14);
  }

  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--surface); color: var(--ink); min-height: 100vh; }
  
  /* Scrollbar */
  ::-webkit-scrollbar { width: 4px; height: 4px; }
  ::-webkit-scrollbar-thumb { background: rgba(16,6,0,0.15); border-radius: 2px; }

  /* Layout */
  .app-shell { display: flex; min-height: 100vh; }
  
  /* Sidebar */
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

  .sidebar-brand {
    display: flex; align-items: center; gap: 12px;
    padding: 22px 24px 18px;
    border-bottom: 1px solid rgba(16,6,0,0.08);
    text-decoration: none;
  }
  .sidebar-logo {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--gold);
    display: flex; align-items: center; justify-content: center;
  }
  .sidebar-app-name { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700; color: var(--ink); }
  .sidebar-app-sub { font-size: 9px; color: rgba(16,6,0,0.35); text-transform: uppercase; margin-top: 1px; }

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
    background: var(--gold);
    display: flex; align-items: center; justify-content: center;
  }
  .mountain-badge-name { font-size: 12px; font-weight: 700; color: var(--ink); }
  .mountain-badge-role { font-size: 10px; color: rgba(16,6,0,0.5); }

  .nav-section { padding: 0 0 8px; flex: 1; overflow-y: auto; }
  .nav-label { font-size: 9px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; color: rgba(16,6,0,0.35); padding: 16px 24px 6px; }
  .nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 24px; font-size: 13px; font-weight: 500;
    color: rgba(16,6,0,0.6); text-decoration: none;
    transition: all .15s; border-left: 3px solid transparent;
  }
  .nav-item:hover { color: var(--ink); background: rgba(16,6,0,0.04); }
  .nav-item.active { color: var(--ink); background: rgba(201,168,76,0.1); border-left-color: var(--gold); }
  .nav-item svg { width: 17px; height: 17px; stroke: currentColor; stroke-width: 1.8; flex-shrink: 0; }
  .nav-item.logout-red { margin-top: 12px; color: #b91c1c; }
  .nav-item.logout-red:hover { background: rgba(185,28,28,0.08); }

  .sidebar-footer {
    padding: 16px 20px; border-top: 1px solid rgba(16,6,0,0.08);
    display: flex; align-items: center; gap: 10px;
  }
  .sidebar-footer-avatar {
    width: 34px; height: 34px; border-radius: 50%; background: var(--gold);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
  }

  /* Main Area */
  .main-area {
    flex: 1;
    margin-left: var(--sidebar-w);
    display: flex; flex-direction: column;
    min-height: 100vh;
    transition: margin-left .25s ease;
  }
  .main-area.expanded { margin-left: var(--sidebar-w-sm); }

  /* Topbar */
  .topbar {
    height: var(--topbar-h);
    background: rgba(255,255,255,0.72);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .sidebar-toggle {
    width: 36px; height: 36px; border-radius: 9px; border: 1px solid var(--border2);
    background: var(--white); cursor: pointer;
  }
  .topbar-page-title { font-size: 15px; font-weight: 700; }
  .topbar-page-sub { font-size: 11px; color: var(--ink3); }
  .topbar-date { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--ink4); padding: 6px 12px; background: var(--off); border-radius: 20px; }
  .topbar-avatar {
    width: 34px; height: 34px; border-radius: 50%; background: var(--ink); color: var(--gold);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
  }

  /* Mobile Bottom Nav */
  .mobile-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: var(--mobile-nav-h);
    background: var(--white);
    border-top: 1px solid var(--border);
    z-index: 150;
    justify-content: space-around;
    align-items: center;
    padding: 8px 16px;
  }
  .mobile-nav-item {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 6px 12px; border-radius: 12px;
    color: var(--ink3); text-decoration: none;
  }
  .mobile-nav-item svg { width: 22px; height: 22px; stroke: currentColor; }
  .mobile-nav-item span { font-size: 10px; font-weight: 500; }
  .mobile-nav-item.active { color: var(--gold); background: rgba(201,168,76,0.1); }

  /* Content */
  .content { flex: 1; padding: 28px; display: flex; flex-direction: column; gap: 24px; }

  /* Stats Grid */
  .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
  .stat-card {
    background: var(--white); border-radius: var(--r); border: 1px solid var(--border);
    padding: 20px; text-align: center; cursor: pointer;
    transition: all 0.3s;
  }
  .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
  .stat-card-icon { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
  .stat-value { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; line-height: 1; }
  .stat-label { font-size: 11px; font-weight: 600; color: var(--ink3); margin-top: 8px; text-transform: uppercase; }
  .stat-card.critical .stat-card-icon { background: var(--critical-bg); }
  .stat-card.critical .stat-value { color: var(--critical); }
  .stat-card.warning .stat-card-icon { background: var(--warning-bg); }
  .stat-card.warning .stat-value { color: var(--warning); }
  .stat-card.info .stat-card-icon { background: var(--info-bg); }
  .stat-card.info .stat-value { color: var(--info); }
  .stat-card.blue .stat-card-icon { background: var(--blue-bg); }
  .stat-card.blue .stat-value { color: var(--blue); }

  /* Panels */
  .panel { background: var(--white); border-radius: var(--r); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
  .panel-hdr { padding: 16px 22px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .panel-title { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px; }
  .panel-title svg { width: 18px; height: 18px; stroke: var(--gold); }
  .panel-body { padding: 20px 22px; }

  /* Alert Bar */
  .alert-bar {
    display: flex; align-items: center; gap: 14px;
    background: linear-gradient(135deg, var(--critical) 0%, #991b1b 100%);
    border-radius: var(--r); padding: 16px 20px;
    animation: pulseBar 3s ease-in-out infinite;
  }
  @keyframes pulseBar { 0%,100% { box-shadow: 0 8px 32px rgba(185,28,28,0.25); } 50% { box-shadow: 0 12px 40px rgba(185,28,28,0.4); } }
  .alert-bar-icon { width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; }
  .alert-bar-content { flex: 1; }
  .alert-bar-title { font-size: 13px; font-weight: 700; color: white; }
  .alert-bar-sub { font-size: 11px; color: rgba(255,255,255,0.75); }
  .alert-bar-action { background: white; color: var(--critical); border: none; border-radius: 8px; padding: 8px 16px; font-size: 12px; font-weight: 700; cursor: pointer; }
/* Enhanced Weather Cards */

  /* Weather Cards */
  .weather-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
  .weather-card { background: linear-gradient(135deg, var(--ink) 0%, #2a1a0a 100%); border-radius: 16px; padding: 16px; color: white; }
  .weather-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .weather-mountain { font-family: 'Playfair Display', serif; font-size: 16px; font-weight: 700; }
  .weather-temp { font-size: 28px; font-weight: 700; font-family: 'DM Mono', monospace; }
  .weather-condition { font-size: 12px; opacity: 0.8; margin-top: 4px; }
  .weather-detail { display: flex; gap: 16px; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 11px; }
  .forecast-list { display: flex; gap: 12px; margin-top: 12px; overflow-x: auto; }
  .forecast-day { text-align: center; min-width: 60px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 10px; }
  .forecast-temp { font-size: 14px; font-weight: 700; }

  
.weather-card {
    border-radius: 20px;
    padding: 20px;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

/* Weather type backgrounds */
.weather-card.sunny {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    box-shadow: 0 8px 32px rgba(245, 158, 11, 0.3);
}
.weather-card.sunny::before {
    content: '☀️';
    position: absolute;    font-size: 80px;
    opacity: 0.1;
    right: -20px;
    bottom: -20px;
    animation: sunShine 4s ease-in-out infinite;
}

.weather-card.rainy {
    background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 100%);
    box-shadow: 0 8px 32px rgba(30, 64, 175, 0.3);
}
.weather-card.rainy::before {
    content: '🌧️';
    position: absolute;
    font-size: 80px;
    opacity: 0.1;
    right: -20px;
    bottom: -20px;
    animation: rainDrop 1s linear infinite;
}

.weather-card.storm {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    animation: stormPulse 2s ease-in-out infinite;
}
.weather-card.storm::before {
    content: '⛈️';
    position: absolute;
    font-size: 80px;
    opacity: 0.15;
    right: -20px;
    bottom: -20px;
}

.weather-card.cloudy {
    background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    box-shadow: 0 8px 32px rgba(74, 85, 104, 0.3);
}
.weather-card.cloudy::before {
    content: '☁️';
    position: absolute;
    font-size: 80px;
    opacity: 0.1;
    right: -20px;
    bottom: -20px;
    animation: cloudFloat 6s ease-in-out infinite;
}

.weather-card.foggy {
    background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
}
.weather-card.foggy::before {
    content: '🌫️';
    position: absolute;
    font-size: 80px;
    opacity: 0.15;
    right: -20px;
    bottom: -20px;
    filter: blur(2px);
}

/* Animations */
@keyframes sunShine {
    0%, 100% { transform: rotate(0deg) scale(1); opacity: 0.1; }
    50% { transform: rotate(10deg) scale(1.1); opacity: 0.15; }
}

@keyframes rainDrop {
    0% { transform: translateY(-10px); opacity: 0.1; }
    100% { transform: translateY(10px); opacity: 0.15; }
}

@keyframes stormPulse {
    0%, 100% { box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4); }
    50% { box-shadow: 0 8px 48px rgba(255, 255, 255, 0.2); }
}

@keyframes cloudFloat {
    0%, 100% { transform: translateX(0px); }
    50% { transform: translateX(10px); }
}

@keyframes gentleRain {
    0% { background-position: 0% 0%; }
    100% { background-position: 100% 100%; }
}

.rain-animation {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 10px,
        rgba(255, 255, 255, 0.1) 10px,
        rgba(255, 255, 255, 0.1) 15px
    );
    animation: gentleRain 0.5s linear infinite;
}

/* Weather advisory banner */
.weather-advisory {
    margin-top: 16px;
    padding: 12px;
    border-radius: 12px;
    font-size: 12px;
    backdrop-filter: blur(10px);
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.weather-advisory.critical {
    background: rgba(185, 28, 28, 0.9);
    border-left: 4px solid #ff6b6b;
}

.weather-advisory.warning {
    background: rgba(245, 158, 11, 0.9);
    border-left: 4px solid #fbbf24;
}

.weather-advisory.info {
    background: rgba(30, 64, 175, 0.9);
    border-left: 4px solid #60a5fa;
}

.weather-advisory.success {
    background: rgba(46, 125, 50, 0.9);
    border-left: 4px solid #86efac;
}

/* Rain meter */
.rain-meter {
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}

.rain-fill {
    height: 100%;
    background: #60a5fa;
    border-radius: 2px;
    transition: width 0.5s ease;
    animation: rainFillPulse 2s ease-in-out infinite;
}

@keyframes rainFillPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Weather icon animations */
.weather-icon-large {
    font-size: 48px;
    display: inline-block;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
}

.weather-icon-large.sunny {
    animation: spin 20s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}


  /* Alert Cards */
  .alerts-grid, .safety-grid, .crowd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
  .alert-card {
    background: var(--white); border-radius: 16px; border: 1px solid var(--border);
    overflow: hidden; transition: all 0.3s;
  }
  .alert-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
  .alert-card.critical { border-left: 4px solid var(--critical); }
  .alert-card.warning { border-left: 4px solid var(--warning); }
  .alert-card-header { padding: 16px; display: flex; gap: 12px; align-items: flex-start; }
  .alert-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .alert-icon.critical { background: var(--critical-bg); }
  .alert-icon.warning { background: var(--warning-bg); }
  .alert-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
  .alert-meta { font-size: 11px; color: var(--ink3); display: flex; gap: 12px; margin-top: 6px; }
  .alert-body { padding: 0 16px 16px; font-size: 12px; color: var(--ink3); line-height: 1.5; }
  .alert-footer { padding: 12px 16px; background: var(--off); border-top: 1px solid var(--border); display: flex; gap: 8px; }
  .btn-sm { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; }
  .btn-primary-sm { background: var(--ink); color: white; }
  .btn-outline-sm { background: transparent; border: 1px solid var(--border2); }

  /* Crowd Level Badge */
  .crowd-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }

  /* Broadcast Item */
  .broadcast-item { padding: 16px; border-bottom: 1px solid var(--border); display: flex; gap: 12px; }
  .broadcast-item.unread { background: var(--info-bg); }
  .broadcast-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .broadcast-content { flex: 1; }
  .broadcast-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; }
  .broadcast-message { font-size: 12px; color: var(--ink3); line-height: 1.5; }
  .broadcast-meta { font-size: 10px; color: var(--ink4); margin-top: 6px; display: flex; gap: 12px; }

  /* Form Elements */
  .form-group { margin-bottom: 16px; }
  .form-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--ink3); display: block; margin-bottom: 6px; }
  .form-input, .form-select, .form-textarea {
    width: 100%; padding: 10px 14px; border: 1.5px solid var(--border2); border-radius: 10px;
    font-family: inherit; font-size: 13px; background: var(--off);
  }
  .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--gold); }
  .modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(6px);
    z-index: 1000; align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal-container {
    background: var(--white); border-radius: 24px; width: 100%; max-width: 500px;
    max-height: 90vh; overflow: auto;
  }
  .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .modal-body { padding: 24px; }
  .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; }
  .btn { padding: 10px 20px; border-radius: 10px; font-weight: 600; border: none; cursor: pointer; }
  .btn-primary { background: var(--ink); color: white; }
  .btn-outline { background: transparent; border: 1px solid var(--border2); }

  /* Toast */
  .toast {
    position: fixed; bottom: 30px; right: 30px;
    background: var(--ink); color: white; padding: 12px 20px;
    border-radius: 40px; font-size: 13px; opacity: 0;
    transition: all 0.3s; z-index: 1100;
  }
  .toast.show { opacity: 1; }

  /* Responsive */
  @media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.mobile-open { transform: translateX(0); }
    .main-area { margin-left: 0 !important; }
    .mobile-bottom-nav { display: flex; }
    .content { padding: 14px; padding-bottom: calc(var(--mobile-nav-h) + 14px); }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .weather-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="app-shell">
<!-- SIDEBAR -->
<?php $activePage = 'advisories'; ?>
<?php include 'shared_sidebar.php'; ?>

<!-- Main Area -->
<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Advisories & Alerts</div>
        <div class="topbar-page-sub">Real-time monitoring for your mountains</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar"><?= htmlspecialchars($manager_initials) ?></div>
    </div>
  </div>

  <div class="content">
    <!-- Critical Alert Banner -->
    <?php if($critical_count > 0): $critical_alert = current(array_filter($active_alerts, fn($a) => $a['severity'] === 'critical')); ?>
      <?php if($critical_alert): ?>
      <div class="alert-bar">
        <div class="alert-bar-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="white" width="20"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
        </div>
        <div class="alert-bar-content">
          <div class="alert-bar-title">⚠ CRITICAL ALERT — <?= htmlspecialchars($critical_alert['title']) ?></div>
          <div class="alert-bar-sub"><?= htmlspecialchars($critical_alert['mountain_name']) ?> · <?= date('M d, g:i A', strtotime($critical_alert['created_at'])) ?></div>
        </div>
        <button class="alert-bar-action" onclick="showAlertDetails(<?= htmlspecialchars(json_encode($critical_alert)) ?>)">View Details</button>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="stats-grid">
      <div class="stat-card critical" onclick="scrollToSection('alerts')">
        <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="22"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div>
        <div class="stat-value"><?= $critical_count + $warning_count ?></div>
        <div class="stat-label">Active Alerts</div>
      </div>
      <div class="stat-card warning" onclick="scrollToSection('safety')">
        <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="22"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <div class="stat-value"><?= $safety_alert_count ?></div>
        <div class="stat-label">Safety Alerts</div>
      </div>
      <div class="stat-card info" onclick="scrollToSection('crowd')">
        <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="22"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="stat-value"><?= $crowd_report_count ?></div>
        <div class="stat-label">Crowd Reports</div>
      </div>
      <div class="stat-card blue" onclick="scrollToSection('weather')">
        <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="22"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg></div>
        <div class="stat-value"><?= count($assigned_mountains) ?></div>
        <div class="stat-label">Weather Tracked</div>
      </div>
      <div class="stat-card" onclick="scrollToSection('broadcasts')">
        <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="22"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg></div>
        <div class="stat-value"><?= $unread_broadcast_count ?></div>
        <div class="stat-label">Unread Updates</div>
      </div>
    </div>

   <!-- Weather Section - Enhanced -->
<div class="panel" id="weather">
    <div class="panel-hdr">
        <div class="panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
            Weather Forecast & Advisories
        </div>
        <button class="btn-sm btn-outline-sm" onclick="refreshWeather()">⟳ Refresh</button>
    </div>
    <div class="panel-body">
        <div class="weather-grid">
            <?php foreach($assigned_mountains as $mountain): ?>
                <?php 
                $weather = $weather_data[$mountain['id']] ?? null;
                $current = $weather['current'] ?? null;
                
                // Determine weather type for styling
                $weather_type = 'sunny';
                $advisory_level = 'success';
                $advisory_message = '';
                $action_needed = '';
                $rain_percent = 0;
                
                if ($current) {
                    $code = $current['weather_code'];
                    $precip = $current['precip'] ?? 0;
                    
                    // Forecast rain probability for today
                    $today_forecast = $weather[date('Y-m-d')] ?? null;
                    $rain_prob = $today_forecast['precip_prob'] ?? 0;
                    
                    if ($code >= 95) { // Thunderstorm
                        $weather_type = 'storm';
                        $advisory_level = 'critical';
                        $advisory_message = '⛈️ SEVERE THUNDERSTORM WARNING';
                        $action_needed = '🚨 ALL HIKING ACTIVITIES SUSPENDED. Evacuate immediately if on trail.';
                        $rain_percent = 100;
                    } elseif ($code >= 61 && $code <= 67) { // Rain
                        $weather_type = 'rainy';
                        $advisory_level = 'warning';
                        $advisory_message = '🌧️ HEAVY RAIN ADVISORY';
                        $action_needed = '⚠️ Trails may be slippery. Guides must carry rain gear. Consider postponing if rain persists.';
                        $rain_percent = max($precip * 10, $rain_prob);
                    } elseif ($code >= 80 && $code <= 82) { // Rain showers
                        $weather_type = 'rainy';
                        $advisory_level = 'warning';
                        $advisory_message = '🌦️ RAIN SHOWERS EXPECTED';
                        $action_needed = '⚠️ Intermittent rain expected. Bring waterproof gear and extra clothing.';
                        $rain_percent = max($precip * 8, $rain_prob);
                    } elseif ($code >= 45 && $code <= 49) { // Fog
                        $weather_type = 'foggy';
                        $advisory_level = 'warning';
                        $advisory_message = '🌫️ LOW VISIBILITY ADVISORY';
                        $action_needed = '⚠️ Dense fog reduces visibility. Stay on marked trails and use headlamps.';
                        $rain_percent = $rain_prob;
                    } elseif ($code >= 3) { // Overcast/Cloudy
                        $weather_type = 'cloudy';
                        $advisory_level = 'info';
                        $advisory_message = '☁️ OVERCAST CONDITIONS';
                        $action_needed = '📋 Cool weather expected. Dress in layers for changing conditions.';
                        $rain_percent = $rain_prob;
                    } elseif ($code >= 0 && $code <= 2) { // Clear/Sunny
                        $weather_type = 'sunny';
                        $advisory_level = 'success';
                        $advisory_message = '☀️ PERFECT HIKING WEATHER';
                        $action_needed = '✅ Warm bright day! Great for hiking. Don\'t forget sun protection and hydration.';
                        $rain_percent = $rain_prob;
                    }
                    
                    // Check for high wind
                    if (($current['wind'] ?? 0) > 40) {
                        $advisory_message .= ' 💨 Strong Winds';
                        $action_needed .= ' Strong winds expected at summit. Extra caution needed.';
                        if ($advisory_level !== 'critical') $advisory_level = 'warning';
                    }
                    
                    // Check for extreme heat
                    if (($current['temp'] ?? 0) > 30) {
                        $advisory_message .= ' 🔥 Heat Warning';
                        $action_needed .= ' High temperatures - ensure adequate water supply (min 3L per person).';
                    }
                }
                ?>
                <div class="weather-card <?= $weather_type ?>">
                    <?php if($weather_type === 'rainy'): ?>
                        <div class="rain-animation"></div>
                    <?php endif; ?>
                    
                    <div class="weather-header">
                        <div>
                            <span class="weather-mountain"><?= htmlspecialchars($mountain['name']) ?></span>
                            <div class="weather-condition">
                                <?php if($current): ?>
                                    <span class="weather-icon-large <?= $weather_type ?>"><?= $current['icon'] ?? '🌤' ?></span>
                                    <span style="margin-left: 8px;"><?= $current['condition'] ?? 'Unknown' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="weather-temp">
                                <?php if($current): ?>
                                    <?= round($current['temp']) ?>°C
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 11px; opacity: 0.8;">
                                Feels like <?= round(($current['temp'] ?? 0) - (($current['wind'] ?? 0) * 0.2)) ?>°
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 16px; margin: 12px 0; font-size: 12px; flex-wrap: wrap;">
                        <?php if($current): ?>
                            <span>💨 Wind: <?= round($current['wind']) ?> km/h</span>
                            <span>💧 Humidity: <?= round($current['humidity']) ?>%</span>
                            <span>📍 Elev: <?= $mountain['elevation'] ?? 'N/A' ?>m</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($rain_percent > 0): ?>
                        <div class="rain-meter">
                            <div class="rain-fill" style="width: <?= min($rain_percent, 100) ?>%"></div>
                        </div>
                        <div style="font-size: 10px; margin-top: 4px; opacity: 0.8;">
                            💧 Rain probability: <?= round($rain_percent) ?>%
                        </div>
                    <?php endif; ?>
                    
                    <?php if($weather && count($weather) > 1): ?>
                        <div class="forecast-list" style="margin-top: 16px;">
                            <?php 
                            $days = array_slice(array_filter(array_keys($weather), fn($k) => $k !== 'current'), 0, 4);
                            foreach($days as $day):
                                $forecast = $weather[$day];
                                $is_rainy = ($forecast['precip_prob'] ?? 0) > 30;
                            ?>
                                <div class="forecast-day" style="<?= $is_rainy ? 'background: rgba(96, 165, 250, 0.3);' : '' ?>">
                                    <div><?= date('D', strtotime($day)) ?></div>
                                    <div class="forecast-temp"><?= round($forecast['temp_max'] ?? 0) ?>°</div>
                                    <div style="font-size: 16px; margin: 4px 0;"><?= $forecast['icon'] ?? '🌤' ?></div>
                                    <?php if($is_rainy): ?>
                                        <div style="font-size: 9px; color: #93c5fd;">🌧️ <?= round($forecast['precip_prob']) ?>%</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Weather Advisory Banner -->
                    <?php if($advisory_message): ?>
                        <div class="weather-advisory <?= $advisory_level ?>">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 20px;">
                                    <?= $advisory_level === 'critical' ? '🚨' : ($advisory_level === 'warning' ? '⚠️' : ($advisory_level === 'success' ? '✅' : 'ℹ️')) ?>
                                </span>
                                <strong><?= $advisory_message ?></strong>
                            </div>
                            <div style="font-size: 11px; line-height: 1.4;">
                                <?= $action_needed ?>
                            </div>
                            <?php if($advisory_level === 'critical'): ?>
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 11px; font-weight: bold;">
                                    🔴 ALL BOOKINGS AFFECTED: Contact all scheduled hikers immediately to reschedule or cancel.
                                </div>
                            <?php elseif($advisory_level === 'warning' && $rain_percent > 50): ?>
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 11px;">
                                    📢 Recommended: Postpone long hikes. Short trails only with proper rain gear.
                                </div>
                            <?php elseif($advisory_level === 'success'): ?>
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 11px;">
                                    ✨ Perfect day for hiking! Sunrise views are expected to be spectacular.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

    <!-- Active Alerts Section -->
    <div class="panel" id="alerts">
      <div class="panel-hdr">
        <div class="panel-title"><svg viewBox="0 0 24 24" fill="none"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>Active Alerts from Guides</div>
        <button class="btn-sm btn-primary-sm" onclick="openPostAlert()">+ Post Alert</button>
      </div>
      <div class="panel-body">
        <?php if(empty($active_alerts)): ?>
          <div style="text-align:center;padding:40px;color:var(--ink3);">✅ No active alerts. All trails are currently safe.</div>
        <?php else: ?>
          <div class="alerts-grid">
            <?php foreach($active_alerts as $alert): ?>
              <div class="alert-card <?= $alert['severity'] === 'critical' ? 'critical' : 'warning' ?>">
                <div class="alert-card-header">
                  <div class="alert-icon <?= $alert['severity'] === 'critical' ? 'critical' : 'warning' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                  </div>
                  <div>
                    <div class="alert-title"><?= htmlspecialchars($alert['title']) ?></div>
                    <div class="alert-meta">
                      <span>📍 <?= htmlspecialchars($alert['mountain_name']) ?></span>
                      <span>🕐 <?= date('M d, g:i A', strtotime($alert['created_at'])) ?></span>
                    </div>
                  </div>
                </div>
                <div class="alert-body">
                  <?= nl2br(htmlspecialchars(substr($alert['description'], 0, 150))) ?>
                </div>
                <div class="alert-footer">
                  <button class="btn-sm btn-outline-sm" onclick="acknowledgeAlert(<?= $alert['id'] ?>)">✓ Acknowledge</button>
                  <button class="btn-sm btn-outline-sm" onclick="viewOnMap(<?= $alert['latitude'] ?: 'null' ?>, <?= $alert['longitude'] ?: 'null' ?>)">🗺 View on Map</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Safety Alerts (from active hikes) -->
    <div class="panel" id="safety">
      <div class="panel-hdr">
        <div class="panel-title"><svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Safety Alerts from Active Hikes</div>
      </div>
      <div class="panel-body">
        <?php if(empty($safety_alerts)): ?>
          <div style="text-align:center;padding:40px;color:var(--ink3);">✅ No active safety alerts. All hikes are proceeding normally.</div>
        <?php else: ?>
          <div class="safety-grid">
            <?php foreach($safety_alerts as $alert): ?>
              <div class="alert-card <?= $alert['severity'] === 'critical' ? 'critical' : ($alert['severity'] === 'high' ? 'warning' : 'info') ?>">
                <div class="alert-card-header">
                  <div class="alert-icon <?= $alert['severity'] === 'critical' ? 'critical' : 'warning' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20"><path d="M12 8v4l3 3M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  </div>
                  <div>
                    <div class="alert-title"><?= htmlspecialchars($alert['title']) ?></div>
                    <div class="alert-meta">
                      <span>🏔 <?= htmlspecialchars($alert['mountain_name']) ?></span>
                      <span>🎫 <?= htmlspecialchars($alert['booking_number']) ?></span>
                      <span>🕐 <?= date('M d, g:i A', strtotime($alert['created_at'])) ?></span>
                    </div>
                  </div>
                </div>
                <div class="alert-body">
                  <?= nl2br(htmlspecialchars(substr($alert['description'], 0, 150))) ?>
                  <?php if($alert['hiker_involved']): ?>
                    <div style="margin-top:8px;padding:6px;background:var(--off);border-radius:8px;">
                      👤 Affected Hiker: <?= htmlspecialchars($alert['hiker_involved']) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="alert-footer">
                  <button class="btn-sm btn-primary-sm" onclick="resolveSafetyAlert(<?= $alert['id'] ?>)">✓ Mark Resolved</button>
                  <button class="btn-sm btn-outline-sm" onclick="contactGuide(<?= $alert['booking_id'] ?>)">📞 Contact Guide</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Crowd Reports -->
    <div class="panel" id="crowd">
      <div class="panel-hdr">
        <div class="panel-title"><svg viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Live Crowd Reports</div>
      </div>
      <div class="panel-body">
        <?php if(empty($crowd_reports)): ?>
          <div style="text-align:center;padding:40px;color:var(--ink3);">📊 No recent crowd reports. Trails are likely quiet.</div>
        <?php else: ?>
          <div class="crowd-grid">
            <?php foreach($crowd_reports as $report): 
              $badge = getCrowdBadge($report['crowd_level']);
            ?>
              <div class="alert-card">
                <div class="alert-card-header">
                  <div class="alert-icon" style="background:<?= $badge['bg'] ?>">
                    <span style="font-size:20px"><?= $badge['icon'] ?></span>
                  </div>
                  <div>
                    <div class="alert-title"><?= htmlspecialchars($report['mountain_name']) ?></div>
                    <div class="alert-meta">
                      <span class="crowd-badge" style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>">
                        <?= $badge['icon'] ?> <?= $report['crowd_level'] ?> Crowd
                      </span>
                      <span>🕐 <?= $report['minutes_ago'] ?> min ago</span>
                    </div>
                  </div>
                </div>
                <div class="alert-body">
                  <?= htmlspecialchars($report['notes'] ?? 'No additional notes') ?>
                  <?php if($report['latitude'] && $report['longitude']): ?>
                    <div style="margin-top:8px;font-size:11px;color:var(--ink4);">
                      📍 <?= number_format($report['latitude'], 6) ?>, <?= number_format($report['longitude'], 6) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="alert-footer">
                  <button class="btn-sm btn-outline-sm" onclick="reportCrowd(<?= $report['mountain_id'] ?>)">➕ Add Report</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Broadcasts / Advisories -->
    <div class="panel" id="broadcasts">
      <div class="panel-hdr">
        <div class="panel-title"><svg viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>Official Advisories & Broadcasts</div>
        <button class="btn-sm btn-primary-sm" onclick="openBroadcastModal()">📢 Send Broadcast</button>
      </div>
      <div class="panel-body">
        <?php if(empty($broadcasts)): ?>
          <div style="text-align:center;padding:40px;color:var(--ink3);">📭 No broadcast messages yet.</div>
        <?php else: ?>
          <?php foreach($broadcasts as $broadcast): ?>
            <div class="broadcast-item <?= $broadcast['is_read'] == 0 ? 'unread' : '' ?>" onclick="markBroadcastRead(<?= $broadcast['id'] ?>, this)">
              <div class="broadcast-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="16"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div class="broadcast-content">
                <div class="broadcast-title">From: <?= htmlspecialchars($broadcast['sender_name']) ?></div>
                <div class="broadcast-message"><?= nl2br(htmlspecialchars(substr($broadcast['message'], 0, 200))) ?></div>
                <div class="broadcast-meta">
                  <span>📅 <?= date('M d, Y g:i A', strtotime($broadcast['created_at'])) ?></span>
                  <span>👥 Target: <?= ucfirst(str_replace('_', ' ', $broadcast['recipient_role'] ?? 'All')) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Mobile Bottom Nav -->

<!-- Post Alert Modal -->
<div class="modal-overlay" id="alertModal" onclick="closeModal(event, 'alertModal')">
  <div class="modal-container" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3>Post Alert to Guides & Hikers</h3>
      <button class="modal-close" onclick="closeModal(null, 'alertModal')">×</button>
    </div>
    <form id="alertForm" onsubmit="submitAlert(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Mountain</label>
          <select class="form-select" id="alertMountainId" required>
            <option value="">Select Mountain</option>
            <?php foreach($assigned_mountains as $mountain): ?>
              <option value="<?= $mountain['id'] ?>"><?= htmlspecialchars($mountain['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Alert Title</label>
          <input type="text" class="form-input" id="alertTitle" required placeholder="e.g., Trail Closure due to Rockslide">
        </div>
        <div class="form-group">
          <label class="form-label">Severity</label>
          <select class="form-select" id="alertSeverity" required>
            <option value="info">ℹ Info - Informational only</option>
            <option value="medium">⚠ Medium - Caution advised</option>
            <option value="high">🚨 High - Be prepared</option>
            <option value="critical">🔴 Critical - Urgent action needed</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" id="alertDescription" rows="4" required placeholder="Provide detailed information about the alert..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Location (Optional)</label>
          <input type="text" class="form-input" id="alertLocation" placeholder="e.g., Near Summit, Trail Section 3">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal(null, 'alertModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Post Alert</button>
      </div>
    </form>
  </div>
</div>

<!-- Broadcast Modal -->
<div class="modal-overlay" id="broadcastModal" onclick="closeModal(event, 'broadcastModal')">
  <div class="modal-container" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3>Send Broadcast Message</h3>
      <button class="modal-close" onclick="closeModal(null, 'broadcastModal')">×</button>
    </div>
    <form id="broadcastForm" onsubmit="submitBroadcast(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Recipient</label>
          <select class="form-select" id="broadcastRecipient" required>
            <option value="all">All Users (Hikers & Guides)</option>
            <option value="all_guides">All Guides Only</option>
            <option value="all_hikers">All Hikers Only</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea class="form-textarea" id="broadcastMessage" rows="5" required placeholder="Type your advisory message here... This will be sent to all affected users."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal(null, 'broadcastModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Send Broadcast</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Global variables
const managerId = <?= json_encode($manager_id) ?>;
const mountains = <?= json_encode($assigned_mountains) ?>;


function toggleMobileSidebar() {
  document.getElementById('sidebar').classList.toggle('mobile-open');
}

function scrollToSection(id) {
  const element = document.getElementById(id);
  if (element) element.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showToast(msg, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className = `toast ${type} show`;
  setTimeout(() => toast.classList.remove('show'), 3000);
}

function showAlertDetails(alert) {
  showToast(`Alert: ${alert.title} - ${alert.description}`, 'info');
}

function acknowledgeAlert(alertId) {
  fetch('../api/acknowledge_alert.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ alert_id: alertId, user_id: managerId, role: 'manager' })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('Alert acknowledged successfully', 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast('Failed to acknowledge alert', 'danger');
    }
  })
  .catch(err => showToast('Error acknowledging alert', 'danger'));
}

function resolveSafetyAlert(alertId) {
  if (confirm('Mark this safety alert as resolved?')) {
    fetch('../api/resolve_safety_alert.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ alert_id: alertId, resolved_by: managerId })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Safety alert resolved', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('Failed to resolve alert', 'danger');
      }
    });
  }
}

function contactGuide(bookingId) {
  showToast('Contacting guide... This feature will open messaging.', 'info');
}

function reportCrowd(mountainId) {
  const level = prompt('Enter crowd level (Low, Medium, High):', 'Medium');
  if (level && ['Low', 'Medium', 'High'].includes(level)) {
    const notes = prompt('Additional notes (optional):', '');
    fetch('../api/report_crowd.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mountain_id: mountainId,
        crowd_level: level,
        notes: notes,
        reported_by: managerId
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Crowd report submitted!', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('Failed to submit report', 'danger');
      }
    });
  }
}

function viewOnMap(lat, lng) {
  if (lat && lng) {
    window.open(`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=15`, '_blank');
  } else {
    showToast('No location coordinates available', 'info');
  }
}

function openPostAlert() {
  document.getElementById('alertModal').classList.add('open');
}

function openBroadcastModal() {
  document.getElementById('broadcastModal').classList.add('open');
}

function closeModal(event, modalId) {
  if (event && event.target !== document.getElementById(modalId)) return;
  document.getElementById(modalId).classList.remove('open');
}

function submitAlert(event) {
  event.preventDefault();
  const data = {
    mountain_id: document.getElementById('alertMountainId').value,
    title: document.getElementById('alertTitle').value,
    severity: document.getElementById('alertSeverity').value,
    description: document.getElementById('alertDescription').value,
    location: document.getElementById('alertLocation').value,
    reported_by: managerId,
    reporter_role: 'manager'
  };
  
  fetch('../api/post_alert.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(response => {
    if (response.success) {
      showToast('Alert posted successfully!', 'success');
      document.getElementById('alertModal').classList.remove('open');
      document.getElementById('alertForm').reset();
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(response.error || 'Failed to post alert', 'danger');
    }
  })
  .catch(err => showToast('Error posting alert', 'danger'));
}

function submitBroadcast(event) {
  event.preventDefault();
  const data = {
    message: document.getElementById('broadcastMessage').value,
    recipient_role: document.getElementById('broadcastRecipient').value,
    sender_id: managerId
  };
  
  fetch('../api/send_broadcast.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(response => {
    if (response.success) {
      showToast('Broadcast sent successfully!', 'success');
      document.getElementById('broadcastModal').classList.remove('open');
      document.getElementById('broadcastForm').reset();
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(response.error || 'Failed to send broadcast', 'danger');
    }
  })
  .catch(err => showToast('Error sending broadcast', 'danger'));
}

function markBroadcastRead(broadcastId, element) {
  fetch('../api/mark_broadcast_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ broadcast_id: broadcastId, user_id: managerId, user_role: 'manager' })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      element.classList.remove('unread');
    }
  });
}

function refreshWeather() {
  showToast('Refreshing weather data...', 'info');
  setTimeout(() => location.reload(), 500);
}

// Update time
setInterval(() => {
  const el = document.getElementById('topbarDate');
  if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}, 1000);
document.getElementById('topbarDate').textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
</script>
</body>
</html>