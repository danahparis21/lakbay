<?php
// frontend/active-hike.php - Live Hike Tracking & Navigation
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();
// Debug session - remove after testing
error_log("Current User ID: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Current User Name: " . ($_SESSION['name'] ?? 'not set'));

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];

// Get hike data from URL parameter
$bookingId = $_GET['booking_id'] ?? '';
$bookingNumber = $_GET['booking_number'] ?? '';

if (empty($bookingId) && empty($bookingNumber)) {
    // No hike specified, redirect to bookings
    header('Location: bookings.php');
    exit;
}

// Fetch the active hike details
$hike = null;
$mountain = null;
$trailData = null;
$currentUserName = $_SESSION['name'] ?? $_SESSION['user_name'] ?? '';

try {
    // First get the booking
    if (!empty($bookingNumber)) {
        $stmt = $pdo->prepare("
            SELECT b.*, m.name as mountain_name, m.location, m.difficulty, 
                   m.trail_data, m.start_point_lat, m.start_point_lng,
                   m.trail_length_km, m.estimated_duration,
                   u.name as guide_name, u.avatar as guide_avatar
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN guides g ON b.guide_id = g.user_id
            JOIN users u ON g.user_id = u.id
            LEFT JOIN booking_hikers bh ON b.id = bh.booking_id
            WHERE b.booking_number = ? AND (b.user_id = ? OR bh.hiker_name = ?)
        ");
        $stmt->execute([$bookingNumber, $currentUserId, $currentUserName]);
    } else {
        $stmt = $pdo->prepare("
            SELECT b.*, m.name as mountain_name, m.location, m.difficulty,
                   m.trail_data, m.start_point_lat, m.start_point_lng,
                   m.trail_length_km, m.estimated_duration,
                   u.name as guide_name, u.avatar as guide_avatar
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN guides g ON b.guide_id = g.user_id
            JOIN users u ON g.user_id = u.id
            LEFT JOIN booking_hikers bh ON b.id = bh.booking_id
            WHERE b.id = ? AND (b.user_id = ? OR bh.hiker_name = ?)
        ");
        $stmt->execute([$bookingId, $currentUserId, $currentUserName]);
    }
    
        $hike = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hike) {
        // Invalid or not your hike
        header('Location: bookings.php?error=invalid_hike');
        exit;
    }
    
    // Parse trail data (GeoJSON stored in DB)
    $trailData = null;
    $trackPoints = [];
    
try {
        // Try multiple approaches to get tracks based on mountain ID
        $trackPoints = [];
        
       // Get tracks based on mountain ID
if ($hike['mountain_id'] == 4) {
    // Mt. Talamitam
    $stmt = $pdo->prepare("
        SELECT lat, lon, ele, idx 
        FROM tracks 
        WHERE fileId = 'TALAMITAM' 
        ORDER BY idx ASC
    ");
    $stmt->execute();
    $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Talamitam: Found " . count($trackPoints) . " track points");
} 
else if ($hike['mountain_id'] == 2) {
    // Mt. Apayang - now using 'APAYANG' fileId
    $stmt = $pdo->prepare("
        SELECT lat, lon, ele, idx 
        FROM tracks 
        WHERE fileId = 'APAYANG' 
        ORDER BY idx ASC
    ");
    $stmt->execute();
    $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Apayang: Found " . count($trackPoints) . " track points");
}
else if ($hike['mountain_id'] == 3) {
    // Mt. Lantik
    $stmt = $pdo->prepare("
        SELECT lat, lon, ele, idx 
        FROM tracks 
        WHERE fileId = 'LANTIK' 
        ORDER BY idx ASC
    ");
    $stmt->execute();
    $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Lantik: Found " . count($trackPoints) . " track points");
}
else if ($hike['mountain_id'] == 1) {
    // Mt. Batulao
    $stmt = $pdo->prepare("
        SELECT lat, lon, ele, idx 
        FROM tracks 
        WHERE fileId = 'BATULAO' 
        ORDER BY idx ASC
    ");
    $stmt->execute();
    $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Batulao: Found " . count($trackPoints) . " track points");
}
else {
    // Other mountains (Lantik, etc.)
    try {
        $stmt = $pdo->prepare("
            SELECT lat, lon, ele, idx 
            FROM tracks 
            WHERE mountain_id = ? 
            ORDER BY idx ASC
        ");
        $stmt->execute([$hike['mountain_id']]);
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Mountain ID " . $hike['mountain_id'] . ": Found " . count($trackPoints) . " track points");
    } catch (Exception $e) {
        error_log("Error fetching tracks by mountain_id: " . $e->getMessage());
    }
}
        
        if (count($trackPoints) > 0) {
            // Build GeoJSON
            $coordinates = [];
            foreach ($trackPoints as $point) {
                $coordinates[] = [(float)$point['lon'], (float)$point['lat']];
            }
            $trailData = [
                'type' => 'LineString',
                'coordinates' => $coordinates
            ];
            
            // Calculate length
            $totalLength = 0;
            for ($i = 0; $i < count($trackPoints) - 1; $i++) {
                $lat1 = $trackPoints[$i]['lat']; $lon1 = $trackPoints[$i]['lon'];
                $lat2 = $trackPoints[$i+1]['lat']; $lon2 = $trackPoints[$i+1]['lon'];
                $R = 6371;
                $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1);
                $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $totalLength += $R * $c;
            }
            $hike['trail_length_km'] = round($totalLength, 2);
            
            // Waypoints
            $waypointInterval = max(1, floor(count($trackPoints) / 8));
            $generatedWaypoints = [];
            $pointTypes = ['start', 'viewpoint', 'rest', 'viewpoint', 'rest', 'viewpoint', 'summit', 'end'];
            for ($i = 0; $i < count($trackPoints); $i += $waypointInterval) {
                $point = $trackPoints[$i];
                $type = $pointTypes[min(floor($i / $waypointInterval), count($pointTypes)-1)];
                $generatedWaypoints[] = [
                    'name' => $type == 'start' ? 'Trailhead' : ($type == 'summit' ? 'Summit' : ($type == 'end' ? 'Exit Point' : ucfirst($type) . ' Point')),
                    'type' => $type, 'latitude' => $point['lat'], 'longitude' => $point['lon'],
                    'description' => $type == 'start' ? 'Starting point.' : ($type == 'summit' ? 'Summit!' : 'Point of interest.'),
                    'order_index' => $i
                ];
            }
            $hike['waypoints'] = $generatedWaypoints;
        } else {
            // Fallback to trail_waypoints table
            $stmt = $pdo->prepare("SELECT name, type, latitude, longitude, description FROM trail_waypoints WHERE mountain_id = ? AND is_active = 1 ORDER BY order_index ASC");
            $stmt->execute([$hike['mountain_id']]);
            $hike['waypoints'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($hike['trail_data'])) {
                $trailData = json_decode($hike['trail_data'], true);
            }
        }
    } catch (Exception $e) {
        error_log("Error processing track data: " . $e->getMessage());
    }
    
} catch (PDOException $e) {
    error_log("Error fetching active hike: " . $e->getMessage());
    // For debugging - remove in production
    die("Database error: " . $e->getMessage());
}

// Check if we're already in an active session
$stmt = $pdo->prepare("
    SELECT id, session_token, start_time, last_location_update
    FROM active_hike_sessions
    WHERE booking_id = ? AND user_id = ? AND status = 'active'
");
$stmt->execute([$hike['id'], $currentUserId]);
$activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate session token if none exists
if (!$activeSession) {
    $sessionToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        INSERT INTO active_hike_sessions (booking_id, user_id, session_token, start_time, status)
        VALUES (?, ?, ?, NOW(), 'active')
    ");
    $stmt->execute([$hike['id'], $currentUserId, $sessionToken]);
    $activeSession = ['session_token' => $sessionToken];
} else {
    $sessionToken = $activeSession['session_token'];
}

// Get user settings for location tracking
$stmt = $pdo->prepare("
    SELECT location_tracking_enabled, share_real_time, battery_saver_mode
    FROM user_settings
    WHERE user_id = ?
");
$stmt->execute([$currentUserId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = ['location_tracking_enabled' => 1, 'share_real_time' => 1, 'battery_saver_mode' => 0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Active Hike — <?= htmlspecialchars($hike['mountain_name']) ?> | LAKBAY</title>
    
    <!-- Leaflet CSS/JS for mapping -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.css" />
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --forest: #1a2e1a;
            --sage: #5c7a5c;
            --gold: #00ffd0; /* Brighter neon cyan for the path */
            --cream: #faf8f3;
            --danger: #ff4757;
            --warning: #ffa502;
            --success: #2ed573;
            --glass: rgba(255, 255, 255, 0.75);
            --dark-glass: rgba(0, 0, 0, 0.6);
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
            height: 100vh;
            background: #000;
        }
        
        /* Map Container - Full Screen */
        #map {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            background: #000;
        }
        
        /* Top Header */
        .hike-header {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            z-index: 10;
            background: var(--dark-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px 24px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            color: white;
        }
        
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px 18px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .back-btn:hover {
            background: white;
            color: var(--forest);
            transform: translateX(-4px);
        }
        
        .hike-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .hike-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .hike-stats {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .hike-stats span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Control Panel - Bottom */
        .control-panel {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .fab-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--forest);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.2s;
        }
        
        .fab-btn:hover {
            transform: scale(1.05);
            background: #243824;
        }
        
        .fab-btn svg {
            width: 24px;
            height: 24px;
            stroke: white;
            fill: none;
            stroke-width: 2;
        }
        
        /* Info Card */
        .info-card {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            max-width: 360px;
            z-index: 10;
            background: white;
            border-radius: 20px;
            padding: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(16, 6, 0, 0.08);
            transition: transform 0.3s;
        }
        
        .info-card.minimized {
            transform: translateY(calc(100% - 60px));
        }
        
        .info-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            margin-bottom: 12px;
        }
        
        .info-card-title {
            font-weight: 700;
            color: var(--forest);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card-title svg {
            width: 18px;
            height: 18px;
            stroke: var(--gold);
        }
        
        .info-card-content {
            transition: opacity 0.3s;
        }
        
        .info-card.minimized .info-card-content {
            display: none;
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(16, 6, 0, 0.06);
            font-size: 13px;
        }
        
        .metric-label {
            color: var(--stone);
        }
        
        .metric-value {
            font-weight: 700;
            color: var(--forest);
        }
        
        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold), var(--sage));
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        /* Heatmap Toggle */
        .heatmap-toggle {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            padding: 8px 16px;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        
        .heatmap-toggle input {
            cursor: pointer;
            width: 36px;
            height: 20px;
        }
        
        /* Permission Overlay */
        .permission-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .permission-card {
            background: white;
            border-radius: 28px;
            max-width: 400px;
            width: 100%;
            padding: 32px 24px;
            text-align: center;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .permission-icon {
            width: 72px;
            height: 72px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .permission-icon svg {
            width: 36px;
            height: 36px;
            stroke: #2e7d32;
            stroke-width: 1.8;
        }
        
        .permission-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--forest);
            margin-bottom: 12px;
        }
        
        .permission-text {
            font-size: 14px;
            color: var(--stone);
            line-height: 1.6;
            margin-bottom: 24px;
        }
        
        .permission-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--forest);
            color: white;
        }
        
        .btn-primary:hover {
            background: #243824;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: transparent;
            border: 1.5px solid rgba(16, 6, 0, 0.2);
            color: var(--forest);
        }
        
        /* Info Note */
        .info-note {
            background: #e3f2fd;
            border-radius: 12px;
            padding: 12px;
            font-size: 12px;
            color: #1565c0;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tracking-active {
            background: rgba(39, 174, 96, 0.1);
            border-left: 3px solid var(--success);
        }
        
        /* Toast */
        .toast-msg {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--forest);
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            white-space: nowrap;
        }
        
        .toast-msg.show {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .hike-title { font-size: 14px; }
            .hike-stats { font-size: 10px; }
            .info-card { left: 12px; right: 12px; }
            .control-panel { bottom: 12px; right: 12px; }
            .fab-btn { width: 44px; height: 44px; }
            .back-btn span { display: none; }
        }
        /* Waypoint styles */
.waypoint-icon {
    position: relative;
    cursor: pointer;
}

.waypoint-dot {
    width: 16px;
    height: 16px;
    background: #ffa502;
    border: 2px solid white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: all 0.3s;
}

.waypoint-dot.achieved-dot {
    background: #2ed573;
    box-shadow: 0 0 12px rgba(46, 213, 115, 0.6);
}

.waypoint-label {
    position: absolute;
    bottom: -22px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.75);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 9px;
    font-weight: bold;
    white-space: nowrap;
    backdrop-filter: blur(4px);
}

.waypoint-label.achieved-label {
    background: #2ed573;
    color: #1a2e1a;
}

.achieve-btn {
    background: linear-gradient(135deg, #00ffd0, #00b894);
    border: none;
    padding: 6px 16px;
    border-radius: 20px;
    color: #1a2e1a;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
}

.achieve-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,255,208,0.3);
}

/* Start point pulse animation */
.start-point-pulse {
    position: relative;
    cursor: pointer;
}

.start-icon {
    width: 40px;
    height: 40px;
    background: #00ffd0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 0 0 0 rgba(0,255,208,0.7);
    animation: pulse-green 2s infinite;
}

.start-label {
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%);
    background: #00ffd0;
    color: #1a2e1a;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
    white-space: nowrap;
}

@keyframes pulse-green {
    0% {
        box-shadow: 0 0 0 0 rgba(0,255,208,0.7);
    }
    70% {
        box-shadow: 0 0 0 15px rgba(0,255,208,0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0,255,208,0);
    }
}

/* Badge Notification */
.badge-notification {
    position: fixed;
    top: 100px;
    right: 20px;
    background: linear-gradient(135deg, #1a2e1a, #2d4a2d);
    border-left: 4px solid #ffd700;
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    z-index: 1000;
    transform: translateX(120%);
    transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    max-width: 320px;
}

.badge-notification.show {
    transform: translateX(0);
}

.badge-icon {
    font-size: 32px;
    background: rgba(255,215,0,0.2);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.badge-content {
    flex: 1;
}

.badge-title {
    font-weight: bold;
    color: #ffd700;
    font-size: 13px;
    margin-bottom: 4px;
}

.badge-message {
    font-size: 11px;
    color: rgba(255,255,255,0.9);
}

.badge-location {
    font-size: 9px;
    color: rgba(255,255,255,0.6);
    margin-top: 4px;
}

.badge-close {
    cursor: pointer;
    font-size: 18px;
    color: rgba(255,255,255,0.5);
    padding: 0 4px;
}

.badge-close:hover {
    color: white;
}

/* Traveled path animation */
.traveled-path {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
    animation: drawPath 0.5s ease forwards;
}

@keyframes drawPath {
    to {
        stroke-dashoffset: 0;
    }
}

/* Metric grid */
.metric-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.metric-box {
    background: rgba(26, 46, 26, 0.05);
    border-radius: 12px;
    padding: 8px;
    text-align: center;
}

.metric-box .metric-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--sage);
    display: block;
    margin-bottom: 4px;
}

.metric-box .metric-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--forest);
}

.progress-container {
    margin-top: 8px;
}

.progress-labels {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--stone);
    margin-bottom: 6px;
}

.progress-bar {
    height: 8px;
    background: rgba(26, 46, 26, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #00ffd0, #00b894);
    border-radius: 4px;
    transition: width 0.3s ease;
}
    </style>
</head>
<body>

<div id="map"></div>

<!-- Top Header -->
<div class="hike-header">
    <a href="bookings.php" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>Back</span>
    </a>
    <div class="hike-info">
        <div class="hike-title">🏔️ <?= htmlspecialchars($hike['mountain_name']) ?></div>
        <div class="hike-stats">
            <span>📏 <?= $hike['trail_length_km'] ?? '?' ?> km</span>
            <span>⏱️ <?= $hike['estimated_duration'] ?? '?' ?> hrs</span>
            <span>👤 <?= htmlspecialchars($hike['guide_name']) ?></span>
        </div>
        <span class="status-badge" id="hikeStatus">Active</span>
    </div>
</div>

<!-- Control Panel (FAB buttons) -->
<div class="control-panel">
    <button class="fab-btn" id="centerBtn" onclick="centerOnUser()" title="Center on Me">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>
    </button>
    <button class="fab-btn" id="layersBtn" onclick="toggleLayers()" title="Change Map Style">
        <svg viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
    </button>
    <button class="fab-btn" id="arrowsToggleBtn" onclick="toggleArrows()" title="Toggle Trail Arrows">
        <svg viewBox="0 0 24 24"><path d="M7 13l5 5 5-5M7 6l5 5 5-5"/></svg>
    </button>
    <button class="fab-btn" id="metricsToggleBtn" onclick="toggleMetrics()" title="Toggle Hike Metrics">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 9l-5 5-2-2-4 4"/></svg>
    </button>
</div>

<!-- Info Card -->
<div class="info-card" id="infoCard">
    <div class="info-card-header" onclick="toggleInfoCard()">
        <div class="info-card-title">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Hike Progress
        </div>
        <svg id="infoCardArrow" viewBox="0 0 24 24" width="16" stroke="currentColor"><path d="M6 9l6 6 6-6"/></svg>
    </div>
    <div class="info-card-content">
        <div class="metric-grid">
            <div class="metric-box">
                <span class="metric-label">Distance</span>
                <span class="metric-value" id="distanceCovered">0.0 km</span>
            </div>
            <div class="metric-box">
                <span class="metric-label">Speed</span>
                <span class="metric-value" id="speed">0.0 km/h</span>
            </div>
            <div class="metric-box">
                <span class="metric-label">Elevation</span>
                <span class="metric-value" id="elevation">-- m</span>
            </div>
            <div class="metric-box">
                <span class="metric-label">Time</span>
                <span class="metric-value" id="hikeTime">0:00:00</span>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-labels">
                <span>Progress to Summit</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
            <div class="progress-labels" style="margin-top: 8px;">
                <span id="distanceRemaining"><?= $hike['trail_length_km'] ?? '?' ?> km remaining</span>
            </div>
        </div>

        <div class="info-note tracking-active" id="trackingNote" style="background: rgba(46, 213, 115, 0.1); border: 1px solid rgba(46, 213, 115, 0.2); color: #2ed573;">
            <div style="width: 8px; height: 8px; background: #2ed573; border-radius: 50%; box-shadow: 0 0 8px #2ed573; animation: pulse 2s infinite;"></div>
            <span>GPS Satellite Tracking: <strong id="trackingStatus">LIVE</strong></span>
        </div>
    </div>
</div>
<style>
@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 213, 115, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(46, 213, 115, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 213, 115, 0); }
}
</style>

<!-- Heatmap Toggle -->
<div class="heatmap-toggle" id="heatmapToggle" style="display: none;">
    <span>🔥 Heatmap</span>
    <input type="checkbox" id="heatmapCheckbox" onchange="toggleHeatmap(this.checked)">
</div>

<!-- Permission Overlay -->
<div class="permission-overlay" id="permissionOverlay">
    <div class="permission-card">
        <div class="permission-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
        </div>
        <div class="permission-title">Enable Location Sharing</div>
        <div class="permission-text">
            Lakbay needs your location to show your position on the trail, 
            provide navigation guidance, and enable safety features.
            
            <div class="info-note" style="margin-top: 12px;">
                <svg viewBox="0 0 24 24" width="14"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                You can disable this anytime in Settings > Location & Tracking
            </div>
        </div>
        <div class="permission-buttons">
            <button class="btn btn-outline" onclick="declineLocation()">Not Now</button>
            <button class="btn btn-primary" onclick="enableLocation()">Enable Location</button>
        </div>
    </div>
</div>

<div class="toast-msg" id="toastMsg"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
    // PHP Data to JavaScript
    const mountainName = <?= json_encode($hike['mountain_name']) ?>;
    const startPoint = {
        lat: <?= $hike['start_point_lat'] ?? 0 ?>,
        lng: <?= $hike['start_point_lng'] ?? 0 ?>
    };
    const trailLength = <?= $hike['trail_length_km'] ?? 5 ?>;
    const trailData = <?= json_encode($trailData) ?>;
    const waypoints = <?= json_encode($hike['waypoints'] ?? []) ?>;
    const sessionToken = <?= json_encode($sessionToken) ?>;
    const bookingId = <?= json_encode($hike['id']) ?>;
    const userId = <?= json_encode($currentUserId) ?>;
    
    // Global variables
    let map;
    let userMarker;
    let trailLayer;
    let traveledLayer;
    let waypointMarkers = [];
    let arrowMarkers = [];
    let heatmapLayer;
    let otherUsersLayer = [];
    let watchId = null;
    let currentPosition = null;
    let arrowsVisible = true;
    let distanceMarkers = [];
    let distanceMarkersVisible = true;
    let locationEnabled = false;
    let heatmapEnabled = false;
    let infoCardMinimized = false;
    let updateInterval;
    let totalDistance = 0;
    let lastPosition = null;
    let startTime = Date.now();
    let trailCoordinates = [];
    let reachedCheckpoints = new Set();
    let earnedBadges = [];
    
    // Badges for the mountain
    const mountainBadges = {
        'Mt. Apayang': {
            'start': { name: 'Trailblazer', icon: '🌄', message: 'You started your journey!' },
            'viewpoint': { name: 'Scout', icon: '👁️', message: 'You found a scenic viewpoint!' },
            'rest': { name: 'Rest Seeker', icon: '💧', message: 'Took a well-deserved rest!' },
            'summit': { name: 'Apayang Warrior', icon: '🏔️', message: 'CONGRATULATIONS! You reached the summit!' },
            'end': { name: 'Trail Master', icon: '🏆', message: 'You completed the entire trail! Amazing!' }
        },
        'default': {
            'start': { name: 'Hiker', icon: '🥾', message: 'Let the adventure begin!' },
            'viewpoint': { name: 'Explorer', icon: '🔭', message: 'Great view discovered!' },
            'rest': { name: 'Pace Setter', icon: '💪', message: 'Taking breaks = smart hiking!' },
            'summit': { name: 'Peak Conqueror', icon: '⛰️', message: 'You reached the summit! UNFORGETTABLE!' },
            'end': { name: 'Trail Legend', icon: '👑', message: 'You completed the hike! Hero!' }
        }
    };
    
    const activeBadges = mountainBadges[mountainName] || mountainBadges['default'];
    
    // Trail-specific heatmap points (only along the trail)
    const trailHeatmapPoints = [
        // Cluster 1: Near start point (busy area)
        { lat: startPoint.lat + 0.0005, lng: startPoint.lng + 0.0003, intensity: 0.8 },
        { lat: startPoint.lat + 0.0008, lng: startPoint.lng + 0.0005, intensity: 0.7 },
        { lat: startPoint.lat + 0.0012, lng: startPoint.lng + 0.0008, intensity: 0.6 },
        // Cluster 2: Midpoint (crowded viewpoint)
        { lat: 14.0915, lng: 120.7705, intensity: 0.9 },
        { lat: 14.0918, lng: 120.7708, intensity: 0.8 },
        { lat: 14.0920, lng: 120.7710, intensity: 0.7 },
        // Cluster 3: Near summit (very crowded)
        { lat: 14.0965, lng: 120.7668, intensity: 1.0 },
        { lat: 14.0968, lng: 120.7665, intensity: 0.9 },
        { lat: 14.0970, lng: 120.7662, intensity: 0.8 },
        { lat: 14.0973, lng: 120.7659, intensity: 0.7 },
        // Cluster 4: Rest area
        { lat: 14.0942, lng: 120.7675, intensity: 0.6 },
        { lat: 14.0945, lng: 120.7672, intensity: 0.5 },
    ];
    
    // Initialize map
    function initMap() {
        map = L.map('map').setView([startPoint.lat || 14.1147, startPoint.lng || 120.8892], 15);
        
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
            subdomains: 'abcd',
            maxZoom: 19,
            minZoom: 12
        }).addTo(map);
        
        drawTrail();
        addWaypointsWithAchievements();
        addStartPointWithClick();
        addTrailHeatmap();
        addMockHikersOnTrail();
        checkLocationPermission();
        
        if (trailCoordinates.length > 0) {
            map.fitBounds(L.latLngBounds(trailCoordinates).pad(0.1));
        }
        
        setInterval(updateHikeTime, 1000);
        
        // Load previously earned badges from localStorage
        loadEarnedBadges();
    }
    
    function updateHikeTime() {
        const diff = Date.now() - startTime;
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        document.getElementById('hikeTime').textContent = `${h}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    }
    
    function drawTrail() {
        let latlngs = [];
        if (trailData && trailData.type === 'LineString') {
            latlngs = trailData.coordinates.map(coord => [coord[1], coord[0]]);
        } else if (waypoints.length >= 2) {
            latlngs = waypoints.map(wp => [wp.latitude, wp.longitude]);
        }
        
        if (latlngs.length > 0) {
            // Full trail (completed portion will be overlaid)
            trailLayer = L.polyline(latlngs, {
                color: '#888',
                weight: 6,
                opacity: 0.4,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(map);
            
            // Traveled path (starts empty, grows as user moves)
            traveledLayer = L.polyline([], {
                color: '#00ffd0',
                weight: 7,
                opacity: 0.95,
                lineCap: 'round',
                lineJoin: 'round',
                className: 'traveled-path'
            }).addTo(map);
            
            trailCoordinates = latlngs;
            addDirectionArrows(latlngs);
            addDistanceMarkers(latlngs);
        }
    }
    
    function updateTraveledPath(currentLat, currentLng) {
        if (!trailCoordinates.length || !currentLat) return;
        
        // Find closest point on trail to user
        let closestIndex = 0;
        let closestDist = Infinity;
        
        for (let i = 0; i < trailCoordinates.length; i++) {
            const dist = calculateDistance(
                currentLat, currentLng,
                trailCoordinates[i][0], trailCoordinates[i][1]
            );
            if (dist < closestDist) {
                closestDist = dist;
                closestIndex = i;
            }
        }
        
        // If user is within 50m of trail, mark path up to that point as traveled
        if (closestDist < 0.05) { // 50 meters
            const traveledPath = trailCoordinates.slice(0, closestIndex + 1);
            traveledLayer.setLatLngs(traveledPath);
            
            // Check for checkpoints along the traveled path
            checkCheckpoints(traveledPath);
        }
    }
    
    function checkCheckpoints(traveledPath) {
        waypoints.forEach((wp, index) => {
            const checkpointKey = `${wp.type}-${index}`;
            if (!reachedCheckpoints.has(checkpointKey)) {
                // Check if this waypoint is on the traveled path
                for (let i = 0; i < traveledPath.length; i++) {
                    const dist = calculateDistance(
                        traveledPath[i][0], traveledPath[i][1],
                        wp.latitude, wp.longitude
                    );
                    if (dist < 0.02) { // Within 20 meters
                        reachCheckpoint(wp, index);
                        break;
                    }
                }
            }
        });
    }
    
    function reachCheckpoint(waypoint, index) {
        const checkpointKey = `${waypoint.type}-${index}`;
        if (reachedCheckpoints.has(checkpointKey)) return;
        
        reachedCheckpoints.add(checkpointKey);
        const badge = activeBadges[waypoint.type] || activeBadges['viewpoint'];
        
        // Update marker visual
        const marker = waypointMarkers[index];
        if (marker) {
            const newIcon = L.divIcon({
                html: `
                    <div class="waypoint-icon achieved">
                        <div class="waypoint-dot achieved-dot"></div>
                        <div class="waypoint-label achieved-label">✓ ${waypoint.name}</div>
                    </div>
                `,
                className: '',
                iconSize: [32, 32]
            });
            marker.setIcon(newIcon);
        }
        
        // Show badge notification
        showBadgeNotification(badge.name, badge.icon, badge.message, waypoint.name);
        
        // Save to localStorage
        saveEarnedBadge(checkpointKey, badge);
        
        // If summit, update status badge
        if (waypoint.type === 'summit') {
            document.getElementById('hikeStatus').textContent = 'Summit Reached! 🏔️';
            document.getElementById('hikeStatus').style.background = '#ffd700';
            document.getElementById('hikeStatus').style.color = '#1a2e1a';
        }
        
        // If end, complete the hike
        if (waypoint.type === 'end') {
            completeHike();
        }
    }
    
    function showBadgeNotification(badgeName, icon, message, location) {
        // Create floating notification
        const notification = document.createElement('div');
        notification.className = 'badge-notification';
        notification.innerHTML = `
            <div class="badge-icon">${icon}</div>
            <div class="badge-content">
                <div class="badge-title">🏅 Badge Earned: ${badgeName}</div>
                <div class="badge-message">${message}</div>
                <div class="badge-location">📍 ${location}</div>
            </div>
            <div class="badge-close" onclick="this.parentElement.remove()">×</div>
        `;
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 500);
        }, 5000);
        
        showToast(`🏅 New Badge: ${badgeName}! ${message}`);
    }
    
    function saveEarnedBadge(key, badge) {
        const earned = JSON.parse(localStorage.getItem(`badges_${mountainName}`) || '[]');
        if (!earned.find(b => b.key === key)) {
            earned.push({ key, badge, earnedAt: new Date().toISOString() });
            localStorage.setItem(`badges_${mountainName}`, JSON.stringify(earned));
        }
        updateBadgeCounter();
    }
    
    function loadEarnedBadges() {
        const earned = JSON.parse(localStorage.getItem(`badges_${mountainName}`) || '[]');
        earned.forEach(item => {
            reachedCheckpoints.add(item.key);
        });
        updateBadgeCounter();
    }
    
    function updateBadgeCounter() {
        const badgeCount = reachedCheckpoints.size;
        const totalBadges = waypoints.length;
        
        // Add badge counter to UI if not exists
        let badgeCounter = document.getElementById('badgeCounter');
        if (!badgeCounter) {
            const statsDiv = document.querySelector('.hike-stats');
            if (statsDiv) {
                badgeCounter = document.createElement('span');
                badgeCounter.id = 'badgeCounter';
                badgeCounter.innerHTML = `🏅 0/${totalBadges}`;
                statsDiv.appendChild(badgeCounter);
            }
        }
        if (badgeCounter) {
            badgeCounter.innerHTML = `🏅 ${badgeCount}/${totalBadges}`;
        }
    }
    
    function completeHike() {
        showToast("🎉 AMAZING! You've completed the entire trail! 🎉");
        
        // Save completion to database
        fetch('../api/complete_hike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: bookingId,
                session_token: sessionToken,
                badges: Array.from(reachedCheckpoints)
            })
        });
        
        // Update booking status
        document.getElementById('hikeStatus').textContent = 'Completed! 🎉';
        document.getElementById('hikeStatus').style.background = '#2ed573';
    }
    
    function addDirectionArrows(coordinates) {
        if (coordinates.length < 2) return;
        const arrowCount = 15;
        const step = Math.floor(coordinates.length / arrowCount);
        
        for (let i = step; i < coordinates.length - 1; i += step) {
            const p1 = coordinates[i];
            const p2 = coordinates[i + 1];
            const angle = Math.atan2(p2[0] - p1[0], p2[1] - p1[1]) * 180 / Math.PI;
            
            const arrowIcon = L.divIcon({
                html: `<div style="transform: rotate(${angle}deg); color: rgba(0,255,208,0.7); font-size: 18px; text-shadow: 0 0 4px rgba(0,0,0,0.5);">➤</div>`,
                className: 'direction-arrow',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            
            const marker = L.marker([p1[0], p1[1]], { icon: arrowIcon, interactive: false }).addTo(map);
            arrowMarkers.push(marker);
        }
    }
    
    // Toggle Trail Arrows and Distance Markers
    function toggleArrows() {
        arrowsVisible = !arrowsVisible;
        distanceMarkersVisible = arrowsVisible;
        
        // Toggle arrows
        arrowMarkers.forEach(marker => {
            if (arrowsVisible) marker.addTo(map);
            else map.removeLayer(marker);
        });
        
        // Toggle distance markers
        distanceMarkers.forEach(marker => {
            if (distanceMarkersVisible) marker.addTo(map);
            else map.removeLayer(marker);
        });
        
        const btn = document.getElementById('arrowsToggleBtn');
        btn.style.background = arrowsVisible ? 'var(--dark-glass)' : 'rgba(255,255,255,0.1)';
        btn.style.border = arrowsVisible ? '1px solid rgba(255,255,255,0.2)' : '1px solid rgba(255,255,255,0.05)';
        btn.style.color = arrowsVisible ? 'white' : 'rgba(255,255,255,0.5)';
        
        showToast(arrowsVisible ? "Trail markers shown" : "Trail markers hidden");
    }
    
    let metricsVisible = true;
    function toggleMetrics() {
        metricsVisible = !metricsVisible;
        const card = document.getElementById('infoCard');
        card.style.display = metricsVisible ? 'block' : 'none';
        
        const btn = document.getElementById('metricsToggleBtn');
        btn.style.background = metricsVisible ? 'var(--dark-glass)' : 'rgba(255,255,255,0.1)';
        btn.style.color = metricsVisible ? 'white' : 'rgba(255,255,255,0.5)';
        
        showToast(metricsVisible ? "Hike metrics shown" : "Hike metrics hidden");
    }
    
    function addDistanceMarkers(coordinates) {
        let accumulatedDistance = 0;
        let lastPoint = coordinates[0];
        let nextDistance = 0.5;
        
        for (let i = 1; i < coordinates.length; i++) {
            const dist = calculateDistance(
                lastPoint[0], lastPoint[1],
                coordinates[i][0], coordinates[i][1]
            );
            accumulatedDistance += dist;
            
            if (accumulatedDistance >= nextDistance) {
                const distanceIcon = L.divIcon({
                    html: `<div style="background: rgba(0,255,208,0.9); color: #1a2e1a; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: bold; white-space: nowrap;">${nextDistance.toFixed(1)} km</div>`,
                    className: 'distance-marker',
                    iconSize: [50, 20]
                });
                
                const marker = L.marker([coordinates[i][0], coordinates[i][1]], { icon: distanceIcon, interactive: false })
                    .bindTooltip(`${nextDistance.toFixed(1)}km from start`, { permanent: false });
                marker.addTo(map);
                distanceMarkers.push(marker);
                
                nextDistance += 0.5;
            }
            lastPoint = coordinates[i];
        }
    }
    
    function addWaypointsWithAchievements() {
        if (waypoints.length > 0) {
            waypoints.forEach((wp, index) => {
                const checkpointKey = `${wp.type}-${index}`;
                const isAchieved = reachedCheckpoints.has(checkpointKey);
                
                const customIcon = L.divIcon({
                    html: `
                        <div class="waypoint-icon ${isAchieved ? 'achieved' : ''}">
                            <div class="waypoint-dot ${isAchieved ? 'achieved-dot' : ''}"></div>
                            <div class="waypoint-label ${isAchieved ? 'achieved-label' : ''}">${isAchieved ? '✓ ' : ''}${wp.name}</div>
                        </div>
                    `,
                    className: '',
                    iconSize: [32, 32],
                    popupAnchor: [0, -15]
                });
                
                const marker = L.marker([wp.latitude, wp.longitude], { icon: customIcon })
                    .bindPopup(`
                        <div style="text-align: center; padding: 8px;">
                            <strong style="font-size: 14px; color: #00ffd0;">🏁 ${wp.name}</strong><br>
                            <p style="font-size: 11px; margin: 8px 0;">${wp.description || 'Waypoint on the trail'}</p>
                            ${!isAchieved ? `<button class="achieve-btn" onclick="window.forceAchieve(${index})">🎯 Mark as Reached</button>` : '<span style="color: #2ed573;">✓ Achieved!</span>'}
                        </div>
                    `);
                marker.addTo(map);
                waypointMarkers.push(marker);
            });
        }
    }
    
    // Manual force achievement (for testing)
    window.forceAchieve = function(index) {
        if (waypoints[index]) {
            reachCheckpoint(waypoints[index], index);
            const marker = waypointMarkers[index];
            if (marker) {
                const newIcon = L.divIcon({
                    html: `
                        <div class="waypoint-icon achieved">
                            <div class="waypoint-dot achieved-dot"></div>
                            <div class="waypoint-label achieved-label">✓ ${waypoints[index].name}</div>
                        </div>
                    `,
                    className: '',
                    iconSize: [32, 32]
                });
                marker.setIcon(newIcon);
            }
        }
    };
    
    function addStartPointWithClick() {
        const startIcon = L.divIcon({
            html: `
                <div class="start-point-pulse">
                    <div class="start-icon">🏁</div>
                    <div class="start-label">START HIKE</div>
                </div>
            `,
            className: 'start-pulse-marker',
            iconSize: [60, 60],
            popupAnchor: [0, -30]
        });
        
        const startMarker = L.marker([startPoint.lat, startPoint.lng], { icon: startIcon })
            .bindPopup(() => {
                const isActive = locationEnabled;
                return `
                    <div style="text-align: center; padding: 12px; min-width: 200px;">
                        <div style="font-size: 32px;">${isActive ? '🚀' : '🏁'}</div>
                        <strong style="font-size: 16px; color: #00ffd0;">${isActive ? 'Hike in Progress' : 'Trailhead'}</strong>
                        <p style="font-size: 12px; margin: 8px 0;">${isActive ? 'You are currently tracking this hike!' : 'Your adventure begins here!'}</p>
                        <button class="btn-primary" 
                                style="padding: 8px 20px; margin-top: 8px; ${isActive ? 'opacity: 0.5; cursor: not-allowed; background: #555;' : ''}" 
                                onclick="${isActive ? 'return false' : "document.getElementById('permissionOverlay').style.display='flex'"}"
                                ${isActive ? 'disabled' : ''}>
                            ${isActive ? '✓ Tracking Active' : '🎒 Start Hike'}
                        </button>
                    </div>
                `;
            });
        startMarker.addTo(map);
        
        // Add click handler
        startMarker.on('click', () => {
            if (!locationEnabled) {
                showToast("Click 'Start Hike' to begin your adventure!");
            } else {
                showToast("Hike is already being tracked!");
            }
        });
    }
    
    function addTrailHeatmap() {
        // Create heatmap points only along the trail
        const heatmapPoints = trailHeatmapPoints.map(p => [p.lat, p.lng, p.intensity]);
        
        heatmapLayer = L.heatLayer(heatmapPoints, {
            radius: 35,
            blur: 20,
            maxZoom: 18,
            minOpacity: 0.4,
            gradient: {
                0.2: 'blue',
                0.4: 'cyan',
                0.6: 'lime',
                0.8: 'yellow',
                1.0: 'red'
            }
        });
        // Don't add to map yet - will be added when toggled
    }
    
    function addMockHikersOnTrail() {
        // Place mock hikers along the trail
        const mockHikersOnTrail = [
            { name: "Maria Santos", lat: startPoint.lat + 0.0005, lng: startPoint.lng + 0.0003, user_id: 9991 },
            { name: "John Reyes", lat: startPoint.lat + 0.0012, lng: startPoint.lng + 0.0008, user_id: 9992 },
            { name: "Lisa Cruz", lat: 14.0915, lng: 120.7705, user_id: 9993 },
            { name: "Mike Tan", lat: 14.0940, lng: 120.7678, user_id: 9994 },
            { name: "Anna Garcia", lat: 14.0962, lng: 120.7669, user_id: 9995 },
            { name: "Carlos Lopez", lat: 14.0975, lng: 120.7658, user_id: 9996 },
        ];
        
        mockHikersOnTrail.forEach(hiker => {
            const mockIcon = L.divIcon({
                html: `<div style="background: #ff6b6b; width: 28px; height: 28px; border-radius: 50%; border: 2px solid white; display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                    🧑
                </div>`,
                className: 'mock-hiker',
                iconSize: [28, 28]
            });
            
            const marker = L.marker([hiker.lat, hiker.lng], { icon: mockIcon })
                .bindPopup(`<strong>${hiker.name}</strong><br>Also hiking ${mountainName}<br><small>📍 ~${calculateDistanceFromStart(hiker.lat, hiker.lng).toFixed(1)}km from start</small>`)
                .addTo(map);
            otherUsersLayer.push(marker);
        });
    }
    
    function calculateDistanceFromStart(lat, lng) {
        if (!startPoint.lat) return 0;
        return calculateDistance(startPoint.lat, startPoint.lng, lat, lng);
    }
    
    function checkLocationPermission() {
        if ("geolocation" in navigator) {
            navigator.permissions.query({ name: "geolocation" }).then(result => {
                if (result.state === "granted") {
                    enableLocation();
                } else {
                    document.getElementById('permissionOverlay').style.display = 'flex';
                }
            });
        } else {
            showToast("Geolocation is not supported by your browser");
        }
    }
    
    function enableLocation() {
        locationEnabled = true;
        document.getElementById('permissionOverlay').style.display = 'none';
        document.getElementById('trackingStatus').textContent = 'ACTIVE';
        
        if ("geolocation" in navigator) {
            watchId = navigator.geolocation.watchPosition(
                onLocationUpdate,
                onLocationError,
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
            startLocationReporting();
        }
        
        document.getElementById('heatmapToggle').style.display = 'flex';
        showToast("📍 GPS tracking active! Your path will be recorded.");
    }
    
    function declineLocation() {
        locationEnabled = false;
        document.getElementById('permissionOverlay').style.display = 'none';
        document.getElementById('trackingStatus').textContent = 'OFF';
        document.getElementById('trackingNote').innerHTML = `
            <div style="width: 8px; height: 8px; background: #ff4757; border-radius: 50%;"></div>
            <span>Location tracking <strong>OFF</strong> - Enable for trail recording</span>
        `;
    }
    
    function onLocationUpdate(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy;
        const altitude = position.coords.altitude;
        const speed = position.coords.speed;
        
        currentPosition = { lat, lng };
        
        if (altitude) {
            document.getElementById('elevation').textContent = Math.round(altitude) + ' m';
        }
        
        if (speed) {
            const speedKmh = speed * 3.6;
            document.getElementById('speed').textContent = speedKmh.toFixed(1) + ' km/h';
        }
        
        // Update user marker
        if (!userMarker) {
            const userIcon = L.divIcon({
                html: `<div style="background: #00ffd0; width: 36px; height: 36px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 20px rgba(0,255,208,0.5); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    🧗
                </div>
                <div style="position: absolute; bottom: -28px; left: 50%; transform: translateX(-50%); background: #00ffd0; color: #1a2e1a; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: bold;">
                    YOU
                </div>`,
                className: 'user-marker',
                iconSize: [36, 36],
                popupAnchor: [0, -20]
            });
            userMarker = L.marker([lat, lng], { icon: userIcon })
                .bindPopup(`<strong>Your Location</strong><br>${mountainName}<br><small>🏁 ${calculateDistanceFromStart(lat, lng).toFixed(1)}km from start</small>`)
                .addTo(map);
        } else {
            userMarker.setLatLng([lat, lng]);
        }
        
        // Update traveled path
        updateTraveledPath(lat, lng);
        
        // Calculate distance traveled
        if (lastPosition) {
            const dist = calculateDistance(
                lastPosition.lat, lastPosition.lng,
                lat, lng
            );
            totalDistance += dist;
            document.getElementById('distanceCovered').textContent = totalDistance.toFixed(2) + ' km';
            
            const remaining = Math.max(0, trailLength - totalDistance);
            document.getElementById('distanceRemaining').textContent = remaining.toFixed(2) + ' km remaining';
            
            const progress = (totalDistance / trailLength) * 100;
            document.getElementById('progressFill').style.width = Math.min(100, progress) + '%';
            document.getElementById('progressPercent').textContent = Math.min(100, progress).toFixed(0) + '%';
        }
        
        lastPosition = { lat, lng };
        
        // Accuracy circle
        if (accuracy > 20) {
            if (window.accuracyCircle) map.removeLayer(window.accuracyCircle);
            window.accuracyCircle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#00ffd0',
                weight: 1,
                opacity: 0.3,
                fillOpacity: 0.05
            }).addTo(map);
            setTimeout(() => {
                if (window.accuracyCircle) map.removeLayer(window.accuracyCircle);
            }, 2000);
        }
    }
    
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    function onLocationError(error) {
        console.error("Location error:", error);
        let message = "Unable to get your location. ";
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message += "Please enable location permissions.";
                break;
            case error.POSITION_UNAVAILABLE:
                message += "Location information is unavailable.";
                break;
            case error.TIMEOUT:
                message += "Location request timed out.";
                break;
        }
        showToast(message);
    }
    
    function startLocationReporting() {
        updateInterval = setInterval(() => {
            if (currentPosition && locationEnabled) {
                sendLocationToServer(currentPosition.lat, currentPosition.lng);
            }
        }, 10000);
    }
    
    function sendLocationToServer(lat, lng) {
        fetch('../api/update_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_token: sessionToken,
                booking_id: bookingId,
                latitude: lat,
                longitude: lng,
                timestamp: Date.now()
            })
        }).catch(err => console.error("Failed to send location:", err));
    }
    
    function toggleHeatmap(enabled) {
        heatmapEnabled = enabled;
        if (enabled) {
            heatmapLayer.addTo(map);
            showToast("🔥 Trail heatmap enabled - showing crowded areas");
        } else if (heatmapLayer) {
            map.removeLayer(heatmapLayer);
        }
    }
    
    function centerOnUser() {
        if (currentPosition) {
            map.setView([currentPosition.lat, currentPosition.lng], 17);
            showToast("📍 Centered on your location");
        } else {
            showToast("Location not available yet");
        }
    }
    
    let currentLayer = 0;
    const layerOptions = [
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
    ];
    
    function toggleLayers() {
        currentLayer = (currentLayer + 1) % layerOptions.length;
        map.eachLayer(layer => {
            if (layer instanceof L.TileLayer) {
                map.removeLayer(layer);
            }
        });
        
        L.tileLayer(layerOptions[currentLayer], {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        if (trailLayer) trailLayer.addTo(map);
        if (traveledLayer) traveledLayer.addTo(map);
        showToast(currentLayer === 2 ? "🛰️ Satellite view" : "🗺️ Map view");
    }
    
    function toggleInfoCard() {
        infoCardMinimized = !infoCardMinimized;
        const card = document.getElementById('infoCard');
        const arrow = document.getElementById('infoCardArrow');
        
        if (infoCardMinimized) {
            card.classList.add('minimized');
            arrow.style.transform = 'rotate(180deg)';
        } else {
            card.classList.remove('minimized');
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    
    function showToast(message) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    
    window.addEventListener('beforeunload', () => {
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
        }
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
    
    document.addEventListener('DOMContentLoaded', () => {
        initMap();
    });
</script>
<!-- Debug output -->
<?php if (isset($trailData) && $trailData): ?>
<script>
console.log("Trail Data loaded:", <?= json_encode($trailData) ?>);
console.log("Track points count:", <?= isset($trackPoints) ? count($trackPoints) : 0 ?>);
console.log("Waypoints count:", <?= isset($hike['waypoints']) ? count($hike['waypoints']) : 0 ?>);
</script>
<?php else: ?>
<script>
console.error("No trail data available!");
console.log("trailData:", <?= json_encode($trailData ?? null) ?>);
console.log("hike data:", <?= json_encode(['mountain_id' => $hike['mountain_id'] ?? null, 'mountain_name' => $hike['mountain_name'] ?? null]) ?>);
</script>
<?php endif; ?>
</body>
</html>