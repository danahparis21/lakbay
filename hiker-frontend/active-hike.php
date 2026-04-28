<?php
// frontend/active-hike.php - Live Hike Tracking & Navigation (Redesigned)
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

error_log("Current User ID: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Current User Name: " . ($_SESSION['name'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$bookingId = $_GET['booking_id'] ?? '';
$bookingNumber = $_GET['booking_number'] ?? '';

if (empty($bookingId) && empty($bookingNumber)) {
    header('Location: bookings.php');
    exit;
}

$hike = null;
$mountain = null;
$trailData = null;
$currentUserName = $_SESSION['name'] ?? $_SESSION['user_name'] ?? '';

try {
    $currentUserName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? '';
    
    if (!empty($bookingNumber)) {
        $stmt = $pdo->prepare("
            SELECT b.*, m.name as mountain_name, m.location, m.difficulty,
                   m.trail_data, m.start_point_lat, m.start_point_lng,
                   m.trail_length_km, m.estimated_duration,
                   u.name as guide_name, u.avatar as guide_avatar
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN guides g ON b.guide_id = g.id
            JOIN users u ON g.user_id = u.id
            WHERE b.booking_number = ? AND (b.user_id = ? OR EXISTS (
                SELECT 1 FROM booking_hikers bh WHERE bh.booking_id = b.id AND bh.hiker_name = ?
            ))
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
            JOIN guides g ON b.guide_id = g.id
            JOIN users u ON g.user_id = u.id
            WHERE b.id = ? AND (b.user_id = ? OR EXISTS (
                SELECT 1 FROM booking_hikers bh WHERE bh.booking_id = b.id AND bh.hiker_name = ?
            ))
        ");
        $stmt->execute([$bookingId, $currentUserId, $currentUserName]);
    }

    $hike = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hike) {
        header('Location: bookings.php?error=invalid_hike');
        exit;
    }

    // Only allow active or confirmed bookings to be started
    if ($hike['status'] !== 'active' && $hike['status'] !== 'confirmed') {
        header('Location: bookings.php?error=booking_not_active');
        exit;
    }

    $trailData = null;
    $trackPoints = [];

    try {
        $trackPoints = [];

        if ($hike['mountain_id'] == 4) {
            $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'TALAMITAM' ORDER BY idx ASC");
            $stmt->execute();
            $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else if ($hike['mountain_id'] == 2) {
            $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'APAYANG' ORDER BY idx ASC");
            $stmt->execute();
            $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else if ($hike['mountain_id'] == 3) {
            $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'LANTIK' ORDER BY idx ASC");
            $stmt->execute();
            $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else if ($hike['mountain_id'] == 1) {
            $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'BATULAO' ORDER BY idx ASC");
            $stmt->execute();
            $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            try {
                $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE mountain_id = ? ORDER BY idx ASC");
                $stmt->execute([$hike['mountain_id']]);
                $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching tracks by mountain_id: " . $e->getMessage());
            }
        }

        if (count($trackPoints) > 0) {
    $coordinates = [];
    foreach ($trackPoints as $point) {
        $coordinates[] = [(float)$point['lon'], (float)$point['lat']];
    }
    $trailData = ['type' => 'LineString', 'coordinates' => $coordinates];

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
}

// ALWAYS fetch waypoints from database (don't generate fake ones!)
$waypointsFromDb = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, type, latitude, longitude, elevation, description, order_index FROM trail_waypoints WHERE mountain_id = ? AND is_active = 1 ORDER BY order_index ASC");
    $stmt->execute([$hike['mountain_id']]);
    $waypointsFromDb = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching waypoints: " . $e->getMessage());
}

// Use database waypoints if available, otherwise fallback to empty array
$hike['waypoints'] = $waypointsFromDb;

if (!empty($hike['trail_data']) && empty($trailData)) {
    $trailData = json_decode($hike['trail_data'], true);
}
    } catch (Exception $e) {
        error_log("Error processing track data: " . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log("Error fetching active hike: " . $e->getMessage());
    die("Database error: " . $e->getMessage());
}

// Check/create active session - make sure table exists first
$activeSession = null;
try {
    $stmt = $pdo->prepare("
        SELECT id, session_token, start_time, last_location_update
        FROM active_hike_sessions
        WHERE booking_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->execute([$hike['id'], $currentUserId]);
    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist, create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS active_hike_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL,
            start_time DATETIME NOT NULL,
            last_location_update DATETIME,
            status ENUM('active', 'completed', 'abandoned') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    $activeSession = null;
}

if (!$activeSession) {
    $sessionToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO active_hike_sessions (booking_id, user_id, session_token, start_time, status) VALUES (?, ?, ?, NOW(), 'active')");
    $stmt->execute([$hike['id'], $currentUserId, $sessionToken]);
} else {
    $sessionToken = $activeSession['session_token'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Active Hike — <?= htmlspecialchars($hike['mountain_name']) ?> | LAKBAY</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
    
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --forest: #0f1f0f;
            --forest-mid: #1a2e1a;
            --sage: #4a6741;
            --mint: #00e5b4;
            --mint-dim: rgba(0,229,180,0.15);
            --mint-glow: rgba(0,229,180,0.4);
            --cream: #f5f2eb;
            --danger: #ff4d6d;
            --warning: #ffb703;
            --success: #06d6a0;
            --glass-dark: rgba(10, 20, 10, 0.72);
            --glass-light: rgba(255,255,255,0.92);
            --text-on-dark: rgba(255,255,255,0.92);
            --text-muted-dark: rgba(255,255,255,0.5);
            --radius-sm: 12px;
            --radius-md: 18px;
            --radius-lg: 26px;
            --radius-pill: 100px;
            --shadow-float: 0 8px 32px rgba(0,0,0,0.28), 0 2px 8px rgba(0,0,0,0.18);
            --shadow-card: 0 4px 20px rgba(0,0,0,0.14);
        }

        html, body {
            font-family: 'DM Sans', sans-serif;
            overflow: hidden;
            height: 100%;
            height: 100dvh;
            background: #0a150a;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── MAP ─────────────────────────────────────── */
        #map {
            position: fixed;
            inset: 0;
            z-index: 1;
        }

        /* ─── LEAFLET OVERRIDE: push zoom controls below header ─── */
        .leaflet-top.leaflet-left {
            top: 120px !important;
        }
        .leaflet-control-zoom {
            border: none !important;
            box-shadow: var(--shadow-float) !important;
            border-radius: var(--radius-sm) !important;
            overflow: hidden;
        }
        .leaflet-control-zoom a {
            background: var(--glass-dark) !important;
            color: white !important;
            border: none !important;
            font-size: 18px !important;
            width: 38px !important;
            height: 38px !important;
            line-height: 38px !important;
            transition: background 0.2s !important;
        }
        .leaflet-control-zoom a:hover {
            background: var(--sage) !important;
        }
        .leaflet-control-zoom-in { border-bottom: 1px solid rgba(255,255,255,0.1) !important; }

        /* ─── TOP HEADER ─────────────────────────────── */
        .hike-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: env(safe-area-inset-top, 0) 14px 0;
        }

        .header-inner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--glass-dark);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: var(--radius-lg);
            padding: 10px 14px 10px 10px;
            box-shadow: var(--shadow-float);
            margin-top: 10px;
        }

        .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius-pill);
            padding: 8px 14px 8px 10px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            flex-shrink: 0;
            transition: background 0.2s, transform 0.2s;
            white-space: nowrap;
        }
        .back-btn svg {
            width: 16px; height: 16px;
            stroke: white; fill: none; stroke-width: 2.2;
            flex-shrink: 0;
        }
        .back-btn:hover, .back-btn:active {
            background: rgba(255,255,255,0.2);
            transform: translateX(-2px);
        }
        .back-btn .btn-label {
            display: inline; /* Always visible */
        }

        .header-center {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .mountain-title {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-chip {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--text-muted-dark);
            font-weight: 500;
        }
        .meta-chip svg {
            width: 11px; height: 11px;
            stroke: var(--mint); fill: none; stroke-width: 2;
            opacity: 0.85;
        }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(6,214,160,0.18);
            border: 1px solid rgba(6,214,160,0.3);
            border-radius: var(--radius-pill);
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            color: var(--success);
            font-family: 'DM Mono', monospace;
            flex-shrink: 0;
        }
        .status-dot {
            width: 6px; height: 6px;
            background: var(--success);
            border-radius: 50%;
            animation: blink 1.8s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Weather chip in header */
        .weather-chip {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,183,3,0.12);
            border: 1px solid rgba(255,183,3,0.2);
            border-radius: var(--radius-pill);
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            color: var(--warning);
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .weather-chip:hover { background: rgba(255,183,3,0.2); }
        .weather-icon { font-size: 13px; }

        /* ─── FAB CONTROLS ───────────────────────────── */
        .control-panel {
            position: fixed;
            right: 14px;
            bottom: 200px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .fab {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-sm);
            background: var(--glass-dark);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.12);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-float);
            transition: background 0.2s, transform 0.15s, opacity 0.2s;
            position: relative;
        }
        .fab svg {
            width: 20px; height: 20px;
            stroke: rgba(255,255,255,0.85); fill: none; stroke-width: 1.8;
        }
        .fab:hover { background: rgba(0,229,180,0.25); transform: scale(1.06); }
        .fab:active { transform: scale(0.96); }
        .fab.fab-off {
            opacity: 0.45; /* Reduced opacity instead of invisible */
            background: rgba(10,20,10,0.5);
        }
        .fab.fab-off svg { stroke: rgba(255,255,255,0.5); }
        .fab-center-btn { background: var(--mint); }
        .fab-center-btn svg { stroke: var(--forest); }
        .fab-center-btn:hover { background: #00d4a8; }

        /* ─── HIKE METRICS PANEL ─────────────────────── */
        .metrics-panel {
            position: fixed;
            left: 14px;
            right: 70px; /* don't overlap FABs */
            bottom: 14px;
            bottom: calc(14px + env(safe-area-inset-bottom, 0px));
            z-index: 100;
            background: var(--glass-dark);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-float);
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .metrics-panel.collapsed {
            transform: translateY(calc(100% - 52px));
        }

        .panel-handle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            cursor: pointer;
            user-select: none;
        }
        .panel-handle-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-label {
            font-family: 'Syne', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.2px;
        }
        .panel-handle-chevron {
            width: 18px; height: 18px;
            stroke: var(--text-muted-dark); fill: none; stroke-width: 2;
            transition: transform 0.3s;
        }
        .metrics-panel.collapsed .panel-handle-chevron {
            transform: rotate(180deg);
        }
        .live-dot {
            width: 7px; height: 7px;
            background: var(--mint);
            border-radius: 50%;
            box-shadow: 0 0 6px var(--mint-glow);
            animation: blink 1.8s ease-in-out infinite;
        }

        .panel-content {
            padding: 0 16px 16px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        .metric-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
        }
        .metric-box-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted-dark);
            margin-bottom: 4px;
        }
        .metric-box-value {
            font-family: 'DM Mono', monospace;
            font-size: 20px;
            font-weight: 500;
            color: white;
            line-height: 1;
        }
        .metric-box-unit {
            font-size: 11px;
            color: var(--text-muted-dark);
            margin-left: 2px;
        }

        .progress-section { margin-top: 4px; }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .progress-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted-dark);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .progress-pct {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            font-weight: 500;
            color: var(--mint);
        }
        .progress-track {
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00e5b4, #06d6a0);
            border-radius: 3px;
            width: 0%;
            transition: width 0.5s ease;
            position: relative;
        }
        .progress-fill::after {
            content: '';
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 8px;
            background: white;
            border-radius: 50%;
            transform: translateX(50%);
        }
        .progress-sub {
            font-size: 10px;
            color: var(--text-muted-dark);
        }

        .tracking-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            background: rgba(6,214,160,0.08);
            border: 1px solid rgba(6,214,160,0.15);
            border-radius: var(--radius-sm);
            margin-top: 10px;
            font-size: 11px;
            color: var(--success);
            font-weight: 500;
        }

        /* ─── PERMISSION OVERLAY ─────────────────────── */
        .permission-overlay {
            position: fixed;
            inset: 0;
            background: rgba(5,12,5,0.88);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 1000;
            display: flex;
            align-items: flex-end;
            padding: 20px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }
        .permission-sheet {
            background: #111d11;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px 28px 20px 20px;
            width: 100%;
            padding: 28px 24px 24px;
            animation: sheetUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes sheetUp {
            from { opacity: 0; transform: translateY(60px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .sheet-icon {
            width: 60px; height: 60px;
            background: var(--mint-dim);
            border: 1px solid var(--mint-glow);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
        }
        .sheet-icon svg {
            width: 28px; height: 28px;
            stroke: var(--mint); fill: none; stroke-width: 1.8;
        }
        .sheet-title {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 10px;
        }
        .sheet-body {
            font-size: 13px;
            color: var(--text-muted-dark);
            text-align: center;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .sheet-note {
            background: rgba(255,255,255,0.05);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .sheet-note svg {
            width: 14px; height: 14px;
            stroke: var(--mint); fill: none; stroke-width: 2;
            flex-shrink: 0;
        }
        .sheet-btns { display: flex; gap: 10px; }
        .btn-secondary {
            flex: 0 0 auto;
            padding: 13px 18px;
            border-radius: var(--radius-pill);
            border: 1px solid rgba(255,255,255,0.15);
            background: transparent;
            color: rgba(255,255,255,0.65);
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
        }
        .btn-primary {
            flex: 1;
            padding: 14px 20px;
            border-radius: var(--radius-pill);
            border: none;
            background: linear-gradient(135deg, #00e5b4, #06d6a0);
            color: var(--forest);
            font-size: 14px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 20px var(--mint-glow);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-primary:active { transform: scale(0.97); }

        /* ─── TOAST ──────────────────────────────────── */
        .toast {
            position: fixed;
            bottom: 220px;
            left: 50%;
            transform: translateX(-50%) translateY(8px);
            background: rgba(10,20,10,0.9);
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(16px);
            color: white;
            padding: 10px 18px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 500;
            z-index: 500;
            opacity: 0;
            transition: opacity 0.25s, transform 0.25s;
            pointer-events: none;
            white-space: nowrap;
            max-width: 90vw;
            text-overflow: ellipsis;
            overflow: hidden;
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* ─── BADGE NOTIFICATION ─────────────────────── */
        .badge-notif {
            position: fixed;
            top: calc(90px + env(safe-area-inset-top, 0px));
            left: 14px;
            right: 14px;
            z-index: 200;
            background: linear-gradient(135deg, #0d1e0d, #162516);
            border: 1px solid rgba(255,215,0,0.25);
            border-left: 3px solid #ffd700;
            border-radius: var(--radius-md);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.35);
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(0.16,1,0.3,1), opacity 0.3s;
            pointer-events: none;
        }
        .badge-notif.show {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
        .badge-emoji {
            font-size: 28px;
            width: 48px; height: 48px;
            background: rgba(255,215,0,0.12);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .badge-text { flex: 1; min-width: 0; }
        .badge-earned-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #ffd700;
            margin-bottom: 2px;
        }
        .badge-name {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: white;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-msg {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-close-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,0.4);
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
            flex-shrink: 0;
        }

        /* ─── WEATHER PANEL ──────────────────────────── */
        .weather-panel {
            position: fixed;
            top: calc(80px + env(safe-area-inset-top, 0px));
            left: 14px;
            right: 14px;
            z-index: 150;
            background: var(--glass-dark);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            padding: 18px;
            box-shadow: var(--shadow-float);
            display: none;
            animation: fadeDown 0.25s ease;
        }
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .weather-panel.open { display: block; }
        .weather-panel-title {
            font-family: 'Syne', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted-dark);
            margin-bottom: 12px;
        }
        .weather-main {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        .weather-big-icon { font-size: 40px; }
        .weather-temp {
            font-family: 'Syne', sans-serif;
            font-size: 36px;
            font-weight: 800;
            color: white;
            line-height: 1;
        }
        .weather-desc {
            font-size: 13px;
            color: var(--text-muted-dark);
            margin-top: 2px;
        }
        .weather-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .weather-stat {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 8px 10px;
            text-align: center;
        }
        .weather-stat-label {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted-dark);
            margin-bottom: 3px;
        }
        .weather-stat-val {
            font-family: 'DM Mono', monospace;
            font-size: 14px;
            color: white;
            font-weight: 500;
        }
        .weather-close {
            position: absolute;
            top: 14px; right: 14px;
            background: none; border: none;
            color: var(--text-muted-dark);
            font-size: 20px;
            cursor: pointer;
        }
        .weather-loading {
            text-align: center;
            color: var(--text-muted-dark);
            font-size: 13px;
            padding: 16px 0;
        }

        /* ─── WAYPOINT MARKERS ───────────────────────── */
        .wp-marker {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        .wp-pin {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 2.5px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            background: var(--warning);
            transition: transform 0.2s;
        }
        .wp-pin.achieved { background: var(--success); box-shadow: 0 0 14px rgba(6,214,160,0.5); }
        .wp-pin.summit { background: #ffd700; box-shadow: 0 0 14px rgba(255,215,0,0.5); width: 22px; height: 22px; }
        .wp-pin.start { background: var(--mint); box-shadow: 0 0 14px var(--mint-glow); width: 22px; height: 22px; }
        .wp-label {
            margin-top: 4px;
            background: rgba(10,20,10,0.85);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
            backdrop-filter: blur(8px);
            font-family: 'DM Mono', monospace;
            letter-spacing: 0.3px;
        }
        .wp-label.achieved { background: rgba(6,214,160,0.85); color: #0a150a; }

        /* ─── START MARKER ───────────────────────────── */
        .start-marker {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .start-ring {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: var(--mint);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 0 0 0 var(--mint-glow);
            animation: pulseRing 2.4s cubic-bezier(0.4,0,0.6,1) infinite;
        }
        @keyframes pulseRing {
            0%   { box-shadow: 0 0 0 0 rgba(0,229,180,0.6); }
            70%  { box-shadow: 0 0 0 18px rgba(0,229,180,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,229,180,0); }
        }
        .start-chip {
            margin-top: 6px;
            background: var(--mint);
            color: var(--forest);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
            font-family: 'Syne', sans-serif;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        /* ─── USER MARKER ────────────────────────────── */
        .user-pin {
            display: flex; flex-direction: column; align-items: center;
        }
        .user-dot {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: var(--mint);
            border: 3px solid white;
            box-shadow: 0 0 20px var(--mint-glow), 0 4px 12px rgba(0,0,0,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .user-chip {
            margin-top: 4px;
            background: var(--mint);
            color: var(--forest);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
            font-family: 'Syne', sans-serif;
        }

        /* ─── DIRECTION ARROW ────────────────────────── */
        .dir-arrow {
            font-size: 15px;
            color: rgba(0,229,180,0.75);
            text-shadow: 0 0 6px rgba(0,0,0,0.5);
            line-height: 1;
        }

        /* ─── DISTANCE BADGE ─────────────────────────── */
        .dist-badge {
            background: rgba(0,229,180,0.9);
            color: #0f1f0f;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            font-family: 'DM Mono', monospace;
            white-space: nowrap;
        }

        /* ─── HIKER MARKER ───────────────────────────── */
        .hiker-dot {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #ff6b9d;
            border: 2px solid white;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(255,107,157,0.3);
        }

        /* ─── POPUP STYLES ───────────────────────────── */
        .leaflet-popup-content-wrapper {
            background: #111d11 !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 16px !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4) !important;
        }
        .leaflet-popup-content {
            margin: 14px 16px !important;
            color: white !important;
            font-family: 'DM Sans', sans-serif !important;
        }
        .leaflet-popup-tip { background: #111d11 !important; }

        .popup-title {
            font-family: 'Syne', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--mint);
            margin-bottom: 6px;
        }
        .popup-body {
            font-size: 12px;
            color: rgba(255,255,255,0.65);
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .popup-btn {
            background: linear-gradient(135deg, var(--mint), #06d6a0);
            border: none;
            padding: 7px 16px;
            border-radius: 20px;
            color: var(--forest);
            font-size: 12px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            width: 100%;
        }
        .popup-btn:hover { opacity: 0.9; }
        .popup-achieved {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--success); font-weight: 600;
        }

        

        /* ─── FINISH HIKE BUTTON ──────────────────────── */
        .finish-hike-area {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .btn-finish-hike {
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: var(--radius-pill);
            background: linear-gradient(135deg, #ff4d6d, #e63946);
            color: white;
            font-size: 14px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(255,77,109,0.35);
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
        }
        .btn-finish-hike:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 28px rgba(255,77,109,0.45);
        }
        .btn-finish-hike:active { transform: scale(0.97); }
        .btn-finish-hike:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-finish-hike svg {
            width: 16px; height: 16px;
            fill: white; stroke: none;
        }
        .finish-note {
            text-align: center;
            font-size: 10px;
            color: var(--text-muted-dark);
            margin-top: 6px;
            line-height: 1.4;
        }

        /* ─── END-TIME NUDGE BAR ──────────────────────── */
        .nudge-bar {
            position: fixed;
            top: calc(85px + env(safe-area-inset-top, 0px));
            left: 14px;
            right: 14px;
            z-index: 180;
            background: rgba(255,183,3,0.12);
            border: 1px solid rgba(255,183,3,0.25);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: var(--warning);
            font-weight: 500;
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow-float);
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(0.16,1,0.3,1), opacity 0.3s;
            pointer-events: none;
        }
        .nudge-bar.show {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
        .nudge-bar svg {
            width: 16px; height: 16px;
            stroke: var(--warning); fill: none; stroke-width: 2;
            flex-shrink: 0;
        }
        .nudge-dismiss {
            margin-left: auto;
            background: rgba(255,183,3,0.2);
            border: 1px solid rgba(255,183,3,0.3);
            border-radius: 20px;
            padding: 4px 12px;
            color: var(--warning);
            font-size: 11px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            flex-shrink: 0;
        }

        /* ─── COMPLETION SUMMARY OVERLAY ──────────────── */
        .completion-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(5,12,5,0.92);
            backdrop-filter: blur(16px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .completion-overlay.open {
            display: flex;
        }
        .completion-card {
            background: #111d11;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px;
            width: 100%;
            max-width: 400px;
            padding: 32px 24px 24px;
            text-align: center;
            animation: sheetUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .completion-emoji { font-size: 56px; margin-bottom: 16px; }
        .completion-title {
            font-family: 'Syne', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: white;
            margin-bottom: 6px;
        }
        .completion-sub {
            font-size: 13px;
            color: var(--text-muted-dark);
            margin-bottom: 20px;
        }
        .completion-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .comp-stat {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-sm);
            padding: 14px 10px;
        }
        .comp-stat-val {
            font-family: 'DM Mono', monospace;
            font-size: 22px;
            font-weight: 500;
            color: var(--mint);
            line-height: 1;
            margin-bottom: 4px;
        }
        .comp-stat-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted-dark);
        }
        .completion-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        .completion-actions .btn-secondary {
            flex: 0 0 auto;
        }
        .completion-actions .btn-primary {
            flex: 1;
        }

        @media (max-width: 380px) {
            .mountain-title { font-size: 13px; }
            .metrics-panel { right: 64px; }
        }

      /* ─── DESKTOP OPTIMIZATION ──────────────────── */
@media (min-width: 768px) {
    /* Constrain Header to center */
    .hike-header {
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: 600px;
        padding: 16px 20px 0;
    }
    .header-inner { margin-top: 10px; padding: 12px 20px; }
    .mountain-title { font-size: 17px; }

    /* Constrain Metrics Panel - center */
    .metrics-panel {
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: 500px;
        right: auto;
        bottom: 20px;
    }
    .metrics-panel.collapsed {
        transform: translateX(-50%) translateY(calc(100% - 52px));
    }

    /* FAB buttons - keep on the RIGHT side, not centered */
    .control-panel {
        position: fixed;
        right: 24px;
        left: auto;
        bottom: 24px;
        transform: none;
    }

    /* Weather panel - slide down from top (not from right) */
    .weather-panel {
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        width: 100%;
        max-width: 500px;
        right: auto;
        animation: fadeDownDesktop 0.3s ease forwards;
    }
    .weather-panel.open {
        transform: translateX(-50%) translateY(0);
    }
    @keyframes fadeDownDesktop {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }

    /* Nudge bar - center like header */
    .nudge-bar {
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        width: 100%;
        max-width: 550px;
        right: auto;
    }
    .nudge-bar.show {
        transform: translateX(-50%) translateY(0);
    }

    /* Badge notification - center */
    .badge-notif {
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        width: 100%;
        max-width: 480px;
        right: auto;
        top: 110px;
    }
    .badge-notif.show {
        transform: translateX(-50%) translateY(0);
    }

    /* Toast - center */
    .toast {
        left: 50%;
        transform: translateX(-50%) translateY(8px);
        right: auto;
        bottom: 100px;
        white-space: nowrap;
    }
    .toast.show {
        transform: translateX(-50%) translateY(0);
    }

    /* Metric sizes - slightly smaller for desktop */
    .metric-box-value { font-size: 18px; }
    .metric-box-label { font-size: 9px; }
    
    .comp-stat-val { font-size: 24px; }
    
    /* Center permission overlay properly */
    .permission-overlay { 
        align-items: center; 
        justify-content: center; 
        padding-bottom: 20px; 
    }
    .permission-sheet { 
        max-width: 450px; 
        border-radius: var(--radius-lg); 
    }

    /* Fix zoom controls position */
    .leaflet-top.leaflet-left {
        top: 100px !important;
        left: 20px !important;
    }
}
/* ── HEATMAP LEGEND ── */
.heatmap-legend {
    position: absolute;
    bottom: 20px;
    right: 12px;
    z-index: 10;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px);
    border-radius: 12px;
    padding: 10px 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.08);
    font-family: 'DM Sans', sans-serif;
    min-width: 130px;
    opacity: 0;
    transform: translateX(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    pointer-events: none;
}

.heatmap-legend.visible {
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
}

.heatmap-legend-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: #100600;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.heatmap-legend-title i {
    font-size: 0.7rem;
    color: #F59E0B;
}

.heatmap-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
    font-size: 0.65rem;
    color: #555;
}

.heatmap-legend-color {
    width: 20px;
    height: 10px;
    border-radius: 3px;
}

.heatmap-legend-note {
    font-size: 0.55rem;
    color: #999;
    margin-top: 6px;
    padding-top: 5px;
    border-top: 1px solid rgba(0,0,0,0.05);
    text-align: center;
}
.fab-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #EF4444;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}
/* Mock hiker special styling */
.real-hiker-marker.mock-hiker {
    animation: mock-pulse 2s ease-in-out infinite;
    border: 2px solid #ffd700;
}

@keyframes mock-pulse {
    0%, 100% { 
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        transform: scale(1);
    }
    50% { 
        box-shadow: 0 0 0 6px rgba(155,89,182,0.4);
        transform: scale(1.05);
    }
}

.real-hiker-marker.mock-hiker .hiker-initials {
    font-size: 10px;
}

.real-hiker-marker.mock-hiker .hiker-status-dot {
    background: #ffd700;
    animation: blink-gold 1s ease-in-out infinite;
}
/* Completion mini map */
#completionMap {
    cursor: pointer;
    transition: transform 0.2s;
}
#completionMap:hover {
    transform: scale(1.01);
}
.leaflet-control-attribution {
    display: none !important;
}

@keyframes blink-gold {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* For very large screens, keep FABs at a reasonable position */
@media (min-width: 1200px) {
    .control-panel {
        right: calc((100% - 1200px) / 2 + 24px);
    }
}


    </style>
</head>
<body>

<div id="map"></div>

<!-- ── TOP HEADER ─────────────────────────────────────── -->
<header class="hike-header">
    <div class="header-inner">
        <a href="bookings.php" class="back-btn">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span class="btn-label">Back</span>
        </a>

        <div class="header-center">
            <div class="mountain-title">⛰ <?= htmlspecialchars($hike['mountain_name']) ?></div>
            <div class="header-meta">
                <span class="meta-chip">
                    <svg viewBox="0 0 24 24"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/></svg>
                    <?= $hike['trail_length_km'] ?? '?' ?> km
                </span>
                <span class="meta-chip">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= $hike['estimated_duration'] ?? '?' ?> hrs
                </span>
                <span class="meta-chip">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?= htmlspecialchars($hike['guide_name']) ?>
                </span>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <div class="status-pill" id="statusPill">
                <div class="status-dot"></div>
                <span id="statusText">LIVE</span>
            </div>
            <button class="weather-chip" onclick="toggleWeather()" id="weatherChip">
                <span class="weather-icon" id="weatherIconSmall">⟳</span>
                <span id="weatherTempSmall">--°</span>
            </button>
        </div>
    </div>
</header>

<!-- ── WEATHER PANEL ──────────────────────────────────── -->
<div class="weather-panel" id="weatherPanel">
    <button class="weather-close" onclick="closeWeather()">×</button>
    <div class="weather-panel-title">Trail Conditions — <?= htmlspecialchars($hike['mountain_name']) ?></div>
    <div id="weatherContent"><div class="weather-loading">Fetching weather data…</div></div>
</div>
<!-- ── FAB CONTROLS ───────────────────────────────────── -->
<div class="control-panel">
    <button class="fab fab-center-btn" onclick="centerOnUser()" title="Center on Me">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>
    </button>
    <button class="fab" id="layersBtn" onclick="toggleLayers()" title="Map Style">
        <svg viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
    </button>
    <button class="fab" id="arrowsBtn" onclick="toggleArrows()" title="Trail Markers">
        <svg viewBox="0 0 24 24"><path d="M7 13l5 5 5-5M7 6l5 5 5-5"/></svg>
    </button>
   <button class="fab" id="waypointsBtn" onclick="toggleWaypoints()" title="Toggle Waypoints">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
        <circle cx="12" cy="10" r="3"/>
    </svg>
</button>
    <button class="fab" id="metricsBtn" onclick="toggleMetrics()" title="Hide Metrics">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 9l-5 5-2-2-4 4"/></svg>
    </button>
    <button class="fab" id="hikersBtn" onclick="toggleHikers()" title="Toggle Other Hikers">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <span id="nearbyHikerCount" class="fab-badge" style="display: none;">0</span>
</button>
    
   <button class="fab" id="heatmapBtn" onclick="toggleHeatmapFab()" title="Heatmap">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
    </svg>
</button>
    
 
</div>
   <!-- Heatmap Legend -->
<div class="heatmap-legend" id="heatmapLegend">
    <div class="heatmap-legend-title">
        <i class="fas fa-fire"></i> Crowd Density
    </div>
    <div class="heatmap-legend-item">
        <div class="heatmap-legend-color" style="background: #10B981;"></div>
        <span>Low</span>
    </div>
    <div class="heatmap-legend-item">
        <div class="heatmap-legend-color" style="background: #84CC16;"></div>
        <span>Light</span>
    </div>
    <div class="heatmap-legend-item">
        <div class="heatmap-legend-color" style="background: #F59E0B;"></div>
        <span>Moderate</span>
    </div>
    <div class="heatmap-legend-item">
        <div class="heatmap-legend-color" style="background: #EF4444;"></div>
        <span>High</span>
    </div>
    <div class="heatmap-legend-item">
        <div class="heatmap-legend-color" style="background: #7F1D1D;"></div>
        <span>Very High</span>
    </div>
    <div class="heatmap-legend-note">
        <i class="fas fa-chart-line"></i> Based on live hiker locations
    </div>
</div>

<!-- ── HIKE METRICS PANEL ─────────────────────────────── -->
<div class="metrics-panel" id="metricsPanel">
    <div class="panel-handle" onclick="collapsePanel()">
        <div class="panel-handle-left">
            <div class="live-dot"></div>
            <span class="panel-label">Hike Progress</span>
        </div>
        <svg class="panel-handle-chevron" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>
    </div>
    <div class="panel-content">
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-box-label">Distance</div>
                <div class="metric-box-value"><span id="distanceCovered">0.0</span><span class="metric-box-unit">km</span></div>
            </div>
            <div class="metric-box">
                <div class="metric-box-label">Speed</div>
                <div class="metric-box-value"><span id="speed">0.0</span><span class="metric-box-unit">km/h</span></div>
            </div>
            <div class="metric-box">
                <div class="metric-box-label">Elevation</div>
                <div class="metric-box-value"><span id="elevation">--</span><span class="metric-box-unit">m</span></div>
            </div>
            <div class="metric-box">
                <div class="metric-box-label">Time</div>
                <div class="metric-box-value" id="hikeTime" style="font-size:17px">0:00:00</div>
            </div>
        </div>

        <div class="progress-section">
            <div class="progress-header">
                <span class="progress-title">Trail Progress</span>
                <span class="progress-pct" id="progressPct">0%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-sub" id="distRemaining"><?= $hike['trail_length_km'] ?? '?' ?> km remaining</div>
        </div>

        <div class="tracking-status" id="trackingNote">
            <div class="live-dot"></div>
            <span>GPS Satellite: <strong id="gpsStatus">CONNECTING…</strong></span>
            <span id="badgeCounter" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,0.5)">🏅 0/0</span>
        </div>

        <!-- Finish Hike Button -->
        <div class="finish-hike-area">
            <button class="btn-finish-hike" id="finishHikeBtn" onclick="finishHike()">
                <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                Finish Hike
            </button>
            <div class="finish-note">Stops GPS tracking &amp; saves your route</div>
        </div>
    </div>
</div>



<!-- ── PERMISSION SHEET ───────────────────────────────── -->
<div class="permission-overlay" id="permOverlay">
    <div class="permission-sheet">
        <div class="sheet-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="sheet-title">Enable Location</div>
        <div class="sheet-body">
            Lakbay needs your GPS to track your trail progress, record your path, and unlock checkpoint badges along the way.
        </div>
        <div class="sheet-note">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            You can disable location tracking anytime in Settings
        </div>
        <div class="sheet-btns">
            <button class="btn-secondary" onclick="declineLocation()">Not now</button>
            <button class="btn-primary" onclick="enableLocation()">Enable GPS</button>
        </div>
    </div>
</div>

<!-- ── END-TIME NUDGE BAR ─────────────────────────────── -->
<div class="nudge-bar" id="nudgeBar">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span id="nudgeMsg">Your scheduled hike time has ended. Tap <strong>Finish Hike</strong> when you're done.</span>
    <button class="nudge-dismiss" onclick="dismissNudge()">OK</button>
</div>

<!-- ── COMPLETION SUMMARY OVERLAY ─────────────────────── -->
<div class="completion-overlay" id="completionOverlay">
    <div class="completion-card">
        <div class="completion-emoji">🎉</div>
        <div class="completion-title" id="compTitle">Hike Complete!</div>
        <div class="completion-sub" id="compSub">Amazing work on the trail today</div>
        
        <!-- Mini map preview -->
        <div id="completionMap" style="height: 160px; width: 100%; border-radius: 16px; margin-bottom: 16px; overflow: hidden; background: #1a2e1a;"></div>
        
        <div class="completion-stats">
            <div class="comp-stat">
                <div class="comp-stat-val" id="compDist">0.0</div>
                <div class="comp-stat-label">km hiked</div>
            </div>
            <div class="comp-stat">
                <div class="comp-stat-val" id="compTime">0:00</div>
                <div class="comp-stat-label">duration</div>
            </div>
            <div class="comp-stat">
                <div class="comp-stat-val" id="compPace">0:00</div>
                <div class="comp-stat-label">pace /km</div>
            </div>
            <div class="comp-stat">
                <div class="comp-stat-val" id="compSpeed">0.0</div>
                <div class="comp-stat-label">avg speed</div>
            </div>
            <div class="comp-stat" style="grid-column: span 2;">
                <div class="comp-stat-val" id="compBadgesCount">0</div>
                <div class="comp-stat-label">badges earned</div>
            </div>
        </div>
        
        <!-- LAKBAY Branding -->
        <div style="margin: 12px 0; padding: 8px; border-top: 1px solid rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.08);">
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span style="font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800; background: linear-gradient(135deg, #00e5b4, #06d6a0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">LAKBAY</span>
                <span style="color: rgba(255,255,255,0.3);">|</span>
                <span style="font-size: 11px; color: var(--text-muted-dark);">Trail recorded with Lakbay</span>
            </div>
        </div>
        
        <div class="completion-actions">
            <button class="btn-secondary" onclick="window.location.href='hikerProfile.php'">Profile</button>
            <button class="btn-secondary" onclick="shareActivity()" id="shareBtn">Share</button>
            <a href="bookings.php" class="btn-primary" style="text-decoration:none;text-align:center;">Done</a>
        </div>
    </div>

</div>

<!-- ── TOAST ──────────────────────────────────────────── -->
<div class="toast" id="toast"></div>

<!-- ── BADGE NOTIFICATION ─────────────────────────────── -->
<div class="badge-notif" id="badgeNotif">
    <div class="badge-emoji" id="badgeEmoji"></div>
    <div class="badge-text">
        <div class="badge-earned-label">Badge Earned</div>
        <div class="badge-name" id="badgeName"></div>
        <div class="badge-msg" id="badgeMsg"></div>
    </div>
    <button class="badge-close-btn" onclick="closeBadge()">×</button>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>

// ── PHP → JS DATA ──────────────────────────────────────
const mountainName   = <?= json_encode($hike['mountain_name']) ?>;
const startPoint     = { lat: <?= $hike['start_point_lat'] ?? 14.1147 ?>, lng: <?= $hike['start_point_lng'] ?? 120.8892 ?> };
const trailLength    = <?= $hike['trail_length_km'] ?? 5 ?>;
const trailData      = <?= json_encode($trailData) ?>;
const waypoints      = <?= json_encode($hike['waypoints'] ?? []) ?>;
const sessionToken   = <?= json_encode($sessionToken) ?>;
const bookingId      = <?= json_encode($hike['id']) ?>;
const userId         = <?= json_encode($currentUserId) ?>;
const mountainLat    = <?= $hike['start_point_lat'] ?? 14.1147 ?>;
const mountainLng    = <?= $hike['start_point_lng'] ?? 120.8892 ?>;
const hikeType       = <?= json_encode($hike['hike_type'] ?? 'day_hike') ?>;
const hikeDate       = <?= json_encode($hike['hike_date'] ?? date('Y-m-d')) ?>;
const MOUNTAIN_ID = <?= json_encode($hike['mountain_id'] ?? null) ?>;

// ── STATE ───────────────────────────────────────────────
let map, userMarker, trailLayer, traveledLayer;
let waypointMarkers = [], arrowMarkers = [], distanceMarkers = [], hikerMarkers = [];
let heatmapLayer;
let watchId = null, updateInterval = null;
let currentPosition = null, lastPosition = null;
let totalDistance = 0, trailCoords = [], reachedCheckpoints = new Set();
let locationEnabled = false, arrowsVisible = false;  // OFF by default
let hikersVisible = false;  // OFF by default
let waypointsVisible = true;  // Keep waypoints visible, they're essential
let panelCollapsed = false, metricsHidden = false;
let startTime = Date.now();
let weatherOpen = false, weatherLoaded = false;
let badgeTimeout = null;
let hikeFinished = false;
let nudgeDismissed = false;
let nudgeShown = false;
let heatmapEnabled = false;  // <-- ADD THIS LINE


const BADGES = {
    'Mt. Apayang': {
        start:     { name: 'Trailblazer',      icon: '🌄', msg: 'Your journey begins!' },
        viewpoint: { name: 'Scout',             icon: '👁️', msg: 'Scenic viewpoint reached!' },
        rest:      { name: 'Rest Seeker',       icon: '💧', msg: 'Smart hiking — rest up!' },
        summit:    { name: 'Apayang Warrior',   icon: '🏔️', msg: 'SUMMIT REACHED!' },
        end:       { name: 'Trail Master',      icon: '🏆', msg: 'Trail completed!' }
    },
    default: {
        start:     { name: 'Hiker',             icon: '🥾', msg: 'Let the adventure begin!' },
        viewpoint: { name: 'Explorer',          icon: '🔭', msg: 'Great view discovered!' },
        rest:      { name: 'Pace Setter',       icon: '💪', msg: 'Breaks = smart hiking!' },
        summit:    { name: 'Peak Conqueror',    icon: '⛰️', msg: 'Summit reached!' },
        end:       { name: 'Trail Legend',      icon: '👑', msg: 'You completed the hike!' }
    }
};
const activeBadges = BADGES[mountainName] || BADGES.default;

// Weather data per mountain (real coords → OpenMeteo)
const WEATHER_COORDS = {
    lat: mountainLat,
    lng: mountainLng
};

// ── MAP INIT ────────────────────────────────────────────
function initMap() {
    map = L.map('map', { zoomControl: true }).setView([startPoint.lat, startPoint.lng], 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OSM © CartoDB',
        subdomains: 'abcd', maxZoom: 19
    }).addTo(map);

    drawTrail();
    placeWaypointMarkersFromDB();
    placeStartMarker();
    
    // Build heatmap from real data (not mock)
    buildHeatmap();
    
    fetchNearbyHikers();
    checkLocationPermission();
    fetchWeather();

    if (trailCoords.length > 0) {
        map.fitBounds(L.latLngBounds(trailCoords).pad(0.1));
    }

    setInterval(tickTimer, 1000);
    loadStoredBadges();
    updateBadgeCounter();

    // Set FAB button states to reflect OFF by default
    document.getElementById('arrowsBtn')?.classList.add('fab-off');
    document.getElementById('hikersBtn')?.classList.add('fab-off');
    document.getElementById('heatmapBtn')?.classList.add('fab-off');
}

// ── DATABASE WAYPOINTS (same as guide map!) ──────────────
function placeWaypointMarkersFromDB() {
    if (!waypoints || !waypoints.length) return;
    
    // Define icon styles based on type - NO LABELS, just icons
    const getIconHtml = (type) => {
        const icons = {
            'summit': '<i class="fas fa-mountain"></i>',
            'campsite': '<i class="fas fa-campground"></i>',
            'viewpoint': '<i class="fas fa-eye"></i>',
            'information': '<i class="fas fa-info-circle"></i>',
            'peak': '<i class="fas fa-flag-checkered"></i>',
            'mountain_pass': '<i class="fas fa-road"></i>',
            'tree': '<i class="fas fa-tree"></i>',
            'water_source': '<i class="fas fa-water"></i>',
            'rest': '<i class="fas fa-chair"></i>',
            'danger': '<i class="fas fa-triangle-exclamation"></i>',
            'start': '<i class="fas fa-flag"></i>'
        };
        const iconHtml = icons[type] || '<i class="fas fa-map-pin"></i>';
        return `<div class="waypoint-marker ${type}-marker">${iconHtml}</div>`;
    };
    
    const getIconColorClass = (type) => {
        const colors = {
            'summit': 'summit-marker',
            'campsite': 'campsite-marker',
            'viewpoint': 'viewpoint-marker',
            'information': 'information-marker',
            'peak': 'peak-marker',
            'mountain_pass': 'mountain_pass-marker',
            'tree': 'tree-marker',
            'water_source': 'water-marker',
            'rest': 'rest-marker',
            'danger': 'danger-marker',
            'start': 'start-marker'
        };
        return colors[type] || 'default-marker';
    };
    
    const getDisplayType = (type) => {
        const typeMap = {
            'summit': 'Summit',
            'campsite': 'Campsite',
            'viewpoint': 'Viewpoint',
            'information': 'Information Point',
            'peak': 'Peak',
            'mountain_pass': 'Mountain Pass',
            'tree': 'Tree',
            'start': 'Starting Point',
            'rest': 'Rest Area',
            'danger': 'Danger Zone',
            'water_source': 'Water Source'
        };
        return typeMap[type] || type.charAt(0).toUpperCase() + type.slice(1);
    };
    
    waypoints.forEach((wp, idx) => {
        const lat = parseFloat(wp.latitude);
        const lng = parseFloat(wp.longitude);
        const type = wp.type || 'viewpoint';
        const colorClass = getIconColorClass(type);
        
        const icon = L.divIcon({
            html: `<div class="waypoint-marker ${colorClass}">
                        <i class="fas ${getIconForType(type)}"></i>
                    </div>`,
            className: 'custom-waypoint-icon',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });
        
        // Build elevation text
        let elevationText = '';
        if (wp.elevation) {
            elevationText = ` · ${Math.round(wp.elevation)}m`;
        } else if (wp.description && wp.description.match(/elevation:?\s*(\d+(?:\.\d+)?)\s*m/i)) {
            const match = wp.description.match(/elevation:?\s*(\d+(?:\.\d+)?)\s*m/i);
            if (match) elevationText = ` · ${Math.round(parseFloat(match[1]))}m`;
        }
        
        const isReached = reachedCheckpoints.has(`${wp.type}-${idx}`);
        
        const popupContent = `
            <div class="waypoint-popup">
                <strong><i class="fas ${getIconForType(type)}"></i> ${escapeHtml(wp.name)}</strong>
                <div class="popup-detail">
                    ${getDisplayType(type)}${elevationText}
                    ${wp.description ? `<br><span class="popup-desc">📝 ${escapeHtml(wp.description)}</span>` : ''}
                </div>
                <div class="popup-coords">
                    📍 ${lat.toFixed(5)}, ${lng.toFixed(5)}
                    ${wp.elevation ? `<br>📊 Elevation: ${Math.round(wp.elevation)}m` : ''}
                </div>
                ${!isReached ? `<button class="popup-btn" onclick="window.forceAchieve(${idx})">🎯 Mark as Reached</button>` : '<div class="popup-achieved">✓ Checkpoint reached!</div>'}
            </div>
        `;
        
        const marker = L.marker([lat, lng], { icon })
            .bindPopup(popupContent)
            .addTo(map);
        
        waypointMarkers.push(marker);
    });
    
    console.log(`✅ Loaded ${waypoints.length} waypoints for ${mountainName}`);
}

function getIconForType(type) {
    const icons = {
        'summit': 'fa-mountain',
        'campsite': 'fa-campground',
        'viewpoint': 'fa-eye',
        'information': 'fa-info-circle',
        'peak': 'fa-flag-checkered',
        'mountain_pass': 'fa-road',
        'tree': 'fa-tree',
        'start': 'fa-flag',
        'rest': 'fa-chair',
        'danger': 'fa-triangle-exclamation',
        'water_source': 'fa-water'
    };
    return icons[type] || 'fa-map-pin';
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Add CSS for waypoint markers (append to existing style)
const waypointStyle = document.createElement('style');
waypointStyle.textContent = `
    .custom-waypoint-icon {
        background: transparent;
        border: none;
    }
    .waypoint-marker {
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.1s ease;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    }
    .waypoint-marker:hover {
        transform: scale(1.15);
    }
    .waypoint-marker i {
        font-size: 24px;
    }
    .summit-marker i { color: #E74C3C; text-shadow: 0 0 4px rgba(231,76,60,0.3); }
    .campsite-marker i { color: #F39C12; }
    .viewpoint-marker i { color: #3498DB; }
    .information-marker i { color: #1ABC9C; }
    .peak-marker i { color: #2ECC71; }
    .mountain_pass-marker i { color: #9B59B6; }
    .tree-marker i { color: #27AE60; }
    .water-marker i { color: #3498DB; }
    .rest-marker i { color: #E67E22; }
    .danger-marker i { color: #E74C3C; }
    .start-marker i { color: #1ABC9C; }
    .default-marker i { color: #95A5A6; }
    .waypoint-popup {
        min-width: 180px;
        max-width: 260px;
    }
    .waypoint-popup strong {
        font-size: 0.9rem;
        color: var(--mint);
        display: block;
        margin-bottom: 6px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 4px;
    }
    .popup-detail {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.65);
        padding-top: 4px;
        line-height: 1.5;
    }
    .popup-desc {
        font-size: 0.68rem;
        color: rgba(255,255,255,0.5);
        font-style: italic;
        display: inline-block;
        margin-top: 4px;
    }
    .popup-coords {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.4);
        margin-top: 8px;
        padding-top: 5px;
        border-top: 1px solid rgba(255,255,255,0.08);
        font-family: monospace;
    }
    .popup-achieved {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--success);
        font-weight: 600;
        margin-top: 8px;
    }
`;
document.head.appendChild(waypointStyle);

// ── TIMER ───────────────────────────────────────────────
function tickTimer() {
    const d = Date.now() - startTime;
    const h = Math.floor(d / 3600000);
    const m = Math.floor((d % 3600000) / 60000);
    const s = Math.floor((d % 60000) / 1000);
    document.getElementById('hikeTime').textContent =
        `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

// ── TRAIL ───────────────────────────────────────────────
function drawTrail() {
    let lls = [];
    if (trailData && trailData.type === 'LineString') {
        lls = trailData.coordinates.map(c => [c[1], c[0]]);
    } else if (waypoints.length >= 2) {
        lls = waypoints.map(w => [parseFloat(w.latitude), parseFloat(w.longitude)]);
    }
    if (!lls.length) return;

// Outline layer (behind) - gives contrast on any background
L.polyline(lls, {
    color: '#375837ff',      // Dark forest green
    weight: 9, 
    opacity: 0.8,
    lineCap: 'round', 
    lineJoin: 'round'
}).addTo(map);

// Main trail layer (on top)
trailLayer = L.polyline(lls, {
    color: '#dbffdcff',      // Soft mint green
    weight: 5, 
    opacity: 0.85,
    lineCap: 'round', 
    lineJoin: 'round'
}).addTo(map);

traveledLayer = L.polyline([], {
    color: '#00e5b4',        // Bright mint (your brand color)
    weight: 6, 
    opacity: 1,
    lineCap: 'round', 
    lineJoin: 'round'
}).addTo(map);

    trailCoords = lls;
    addArrows(lls);
    addDistBadges(lls);
}

function addArrows(coords) {
    const count = 14;
    const step = Math.max(1, Math.floor(coords.length / count));
    for (let i = step; i < coords.length - 1; i += step) {
        const p1 = coords[i], p2 = coords[i+1];
        const angle = Math.atan2(p2[0]-p1[0], p2[1]-p1[1]) * 180 / Math.PI;
        const icon = L.divIcon({
            html: `<div class="dir-arrow" style="transform:rotate(${angle}deg)">➤</div>`,
            className: '', iconSize: [18, 18], iconAnchor: [9, 9]
        });
        arrowMarkers.push(L.marker(p1, { icon, interactive: false }).addTo(map));
    }
    // Hide arrows by default
    if (!arrowsVisible) arrowMarkers.forEach(m => map.removeLayer(m));
}

function addDistBadges(coords) {
    let acc = 0, last = coords[0], next = 0.5;
    for (let i = 1; i < coords.length; i++) {
        acc += calcDist(last[0], last[1], coords[i][0], coords[i][1]);
        if (acc >= next) {
            const icon = L.divIcon({
                html: `<div class="dist-badge">${next.toFixed(1)} km</div>`,
                className: '', iconSize: [50, 18]
            });
            const marker = L.marker(coords[i], { icon, interactive: false });
            distanceMarkers.push(marker);
            // Only add to map if arrowsVisible is true
            if (arrowsVisible) marker.addTo(map);
            next += 0.5;
        }
        last = coords[i];
    }
}

function updateTraveledPath(lat, lng) {
    if (!trailCoords.length) return;
    let closest = 0, minD = Infinity;
    for (let i = 0; i < trailCoords.length; i++) {
        const d = calcDist(lat, lng, trailCoords[i][0], trailCoords[i][1]);
        if (d < minD) { minD = d; closest = i; }
    }
    if (minD < 0.05) {
        traveledLayer.setLatLngs(trailCoords.slice(0, closest + 1));
        checkCheckpoints(trailCoords.slice(0, closest + 1));
    }
}

function checkCheckpoints(path) {
    waypoints.forEach((wp, i) => {
        const key = `${wp.type}-${i}`;
        if (reachedCheckpoints.has(key)) return;
        for (const p of path) {
            if (calcDist(p[0], p[1], parseFloat(wp.latitude), parseFloat(wp.longitude)) < 0.02) {
                reachCheckpoint(wp, i);
                break;
            }
        }
    });
}

window.forceAchieve = function(i) {
    if (waypoints[i]) reachCheckpoint(waypoints[i], i);
};

function reachCheckpoint(wp, i) {
    const key = `${wp.type}-${i}`;
    if (reachedCheckpoints.has(key)) return;
    reachedCheckpoints.add(key);

    const badge = activeBadges[wp.type] || activeBadges.viewpoint;

    showBadge(badge.name, badge.icon, badge.msg, wp.name);
    saveBadgeDB(badge.name, badge.icon, wp.name, wp.type);
    storeBadgeLocal(key);
    updateBadgeCounter();

    if (wp.type === 'summit') {
        document.getElementById('statusText').textContent = 'SUMMIT ⛰';
        document.getElementById('statusPill').style.background = 'rgba(255,215,0,0.2)';
        document.getElementById('statusPill').style.borderColor = 'rgba(255,215,0,0.4)';
        document.getElementById('statusPill').style.color = '#ffd700';
    }
    if (wp.type === 'end') completeHike();
}

// ── START MARKER ────────────────────────────────────────
function placeStartMarker() {
    const icon = L.divIcon({
        html: `<div class="start-marker">
            <div class="start-ring">🏁</div>
            <div class="start-chip">TRAILHEAD</div>
        </div>`,
        className: 'start-pulse-marker', iconSize: [60, 66], iconAnchor: [30, 22], popupAnchor: [0, -30]
    });
    L.marker([startPoint.lat, startPoint.lng], { icon })
        .bindPopup(`<div class="popup-title">Trailhead — ${mountainName}</div>
            <div class="popup-body">Your adventure begins here. Stay on the marked trail and hike safe!</div>`)
        .addTo(map);
}

// ── MOCK HIKERS (placed along trail coords) ──────────────
function placeMockHikers() {
    if (!trailCoords.length) return;

    const names = ['Maria S.', 'John R.', 'Lisa C.', 'Mike T.', 'Anna G.', 'Carlos L.'];
    const positions = [0.08, 0.2, 0.37, 0.52, 0.68, 0.82];

    names.forEach((name, idx) => {
        const frac = positions[idx];
        const pointIdx = Math.min(Math.floor(frac * trailCoords.length), trailCoords.length - 1);
        const [lat, lng] = trailCoords[pointIdx];

        const icon = L.divIcon({
            html: `<div class="hiker-dot">🧑</div>`,
            className: '', iconSize: [28, 28], iconAnchor: [14, 14]
        });
        const m = L.marker([lat, lng], { icon })
            .bindPopup(`<div class="popup-title">${name}</div>
                <div class="popup-body">Also hiking ${mountainName}<br>~${(frac * trailLength).toFixed(1)} km along the trail</div>`);
        m.addTo(map);
        hikerMarkers.push(m);
    });
    
    // Hide hikers by default
    if (!hikersVisible) hikerMarkers.forEach(m => map.removeLayer(m));
}

// ── LOCATION ─────────────────────────────────────────────
function checkLocationPermission() {
    if (!navigator.geolocation) {
        showToast('Geolocation not supported'); return;
    }
    navigator.permissions.query({ name: 'geolocation' }).then(res => {
        if (res.state === 'granted') {
            setTimeout(() => enableLocation(), 800);
        } else {
            document.getElementById('permOverlay').style.display = 'flex';
        }
    }).catch(() => {
        document.getElementById('permOverlay').style.display = 'flex';
    });
}
function enableLocation() {
    locationEnabled = true;
    document.getElementById('permOverlay').style.display = 'none';
    document.getElementById('gpsStatus').textContent = 'ACTIVE';

    watchId = navigator.geolocation.watchPosition(onLocationUpdate, onLocationError, {
        enableHighAccuracy: true, timeout: 10000, maximumAge: 0
    });
    startReporting();
    
    // Start polling for nearby hikers
    if (!nearbyHikerPollId) {
        startNearbyHikerPolling();
    }
    
    showToast('📍 GPS live — your path is being recorded');
}
function declineLocation() {
    document.getElementById('permOverlay').style.display = 'none';
    document.getElementById('gpsStatus').textContent = 'OFF';
    document.getElementById('trackingNote').style.borderColor = 'rgba(255,77,109,0.2)';
    document.getElementById('trackingNote').style.background = 'rgba(255,77,109,0.05)';
}

function onLocationUpdate(pos) {
    const { latitude: lat, longitude: lng, altitude, speed, accuracy } = pos.coords;
    currentPosition = { lat, lng };

    if (altitude) document.getElementById('elevation').textContent = Math.round(altitude);
    if (speed != null) document.getElementById('speed').textContent = (speed * 3.6).toFixed(1);

    if (!userMarker) {
        const icon = L.divIcon({
            html: `<div class="user-pin"><div class="user-dot">🧗</div><div class="user-chip">YOU</div></div>`,
            className: '', iconSize: [38, 52], iconAnchor: [19, 19]
        });
        userMarker = L.marker([lat, lng], { icon })
            .bindPopup(`<div class="popup-title">Your Location</div>
                <div class="popup-body">GPS accuracy: ±${Math.round(accuracy)}m</div>`)
            .addTo(map);
    } else {
        userMarker.setLatLng([lat, lng]);
    }

    updateTraveledPath(lat, lng);

    if (lastPosition) {
        const d = calcDist(lastPosition.lat, lastPosition.lng, lat, lng);
        totalDistance += d;
        const rem = Math.max(0, trailLength - totalDistance);
        const pct = Math.min(100, (totalDistance / trailLength) * 100);
        document.getElementById('distanceCovered').textContent = totalDistance.toFixed(2);
        document.getElementById('distRemaining').textContent = rem.toFixed(2) + ' km remaining';
        document.getElementById('progressFill').style.width = pct + '%';
        document.getElementById('progressPct').textContent = pct.toFixed(0) + '%';
    }
    lastPosition = { lat, lng };
}

function onLocationError(err) {
    const msgs = ['', 'Please enable location permissions.', 'Location unavailable.', 'Location timed out.'];
    showToast('GPS error: ' + (msgs[err.code] || err.message));
}

function startReporting() {
    updateInterval = setInterval(() => {
        if (currentPosition && locationEnabled) {
            fetch('../api/update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_token: sessionToken,
                    booking_id: bookingId,
                    latitude: currentPosition.lat,
                    longitude: currentPosition.lng,
                    timestamp: Date.now()
                })
            }).catch(() => {});
        }
    }, 10000);
}

// ── HEATMAP ──────────────────────────────────────────────
let heatmapPoints = [];
let heatmapAutoRefresh = null;

async function buildHeatmap() {
    if (!trailCoords.length || !mountainLat) return;
    
    try {
       const res = await fetch(`../api/detect_crowd_hotspots.php?mountain_id=${MOUNTAIN_ID}`);
       const data = await res.json();
        
        console.log('Heatmap data received:', data);
        
        if (data.success && data.points && data.points.length) {
            // Convert points for heatmap
            heatmapPoints = data.points.map(p => [p.latitude, p.longitude, p.intensity]);
            
            if (heatmapLayer) {
                map.removeLayer(heatmapLayer);
            }
            
            heatmapLayer = L.heatLayer(heatmapPoints, { 
                radius: 45, 
                blur: 20, 
                maxZoom: 18,
                minOpacity: 0.4,
                gradient: { 
                    0.2: '#10B981',  // Low - green
                    0.4: '#84CC16',  // Low-medium - lime
                    0.6: '#F59E0B',  // Medium - orange  
                    0.8: '#EF4444',  // High - red
                    1.0: '#7F1D1D'   // Very high - dark red
                }
            });
            
            if (heatmapEnabled) {
                heatmapLayer.addTo(map);
                showToast(`🔥 Heatmap showing ${data.points.length} crowded area(s)`);
            }
            
            // Update crowd badge in status bar (optional - add if you have one)
            const highPoints = data.points.filter(p => p.intensity >= 0.7);
            const medPoints = data.points.filter(p => p.intensity >= 0.4 && p.intensity < 0.7);
            
            let overallLevel = 'Low';
            if (highPoints.length > 0) overallLevel = 'High';
            else if (medPoints.length > 0) overallLevel = 'Medium';
            
            // You can add a crowd badge display in your header if desired
        } else {
            if (heatmapLayer && heatmapEnabled) {
                map.removeLayer(heatmapLayer);
            }
            heatmapPoints = [];
            if (heatmapEnabled) {
                showToast('No crowd data available');
            }
        }
    } catch (e) {
        console.error('Heatmap fetch error:', e);
        showToast('Error loading crowd data');
    }
}

// Auto-refresh heatmap every 2 minutes
function startHeatmapAutoRefresh() {
    if (heatmapAutoRefresh) clearInterval(heatmapAutoRefresh);
    heatmapAutoRefresh = setInterval(() => {
        if (heatmapEnabled) {
            buildHeatmap();
        }
    }, 120000); // 2 minutes
}
function toggleHeatmapFab() {
    console.log('Heatmap button clicked, current state:', heatmapEnabled);
    heatmapEnabled = !heatmapEnabled;
    const btn = document.getElementById('heatmapBtn');
    const legend = document.getElementById('heatmapLegend');
    
    if (heatmapEnabled) {
        if (heatmapPoints.length) {
            if (heatmapLayer) heatmapLayer.addTo(map);
        } else {
            buildHeatmap().then(() => {
                if (heatmapLayer) heatmapLayer.addTo(map);
            });
        }
        btn.classList.remove('fab-off');
        btn.style.background = 'rgba(0,229,180,0.3)';
        startHeatmapAutoRefresh();
        
        if (legend) legend.classList.add('visible');
        
        showToast('🔥 Heatmap enabled - Showing real-time crowd density');
    } else {
        if (heatmapLayer) map.removeLayer(heatmapLayer);
        btn.classList.add('fab-off');
        btn.style.background = '';
        if (heatmapAutoRefresh) clearInterval(heatmapAutoRefresh);
        
        if (legend) legend.classList.remove('visible');
        
        showToast('Heatmap off');
    }
}
function toggleWaypoints() {
    waypointsVisible = !waypointsVisible;
    waypointMarkers.forEach(m => waypointsVisible ? m.addTo(map) : map.removeLayer(m));
    const btn = document.getElementById('waypointsBtn');
    btn.classList.toggle('fab-off', !waypointsVisible);
    showToast(waypointsVisible ? 'Waypoints shown' : 'Waypoints hidden');
}

// ── WEATHER ──────────────────────────────────────────────
async function fetchWeather() {
    try {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${WEATHER_COORDS.lat}&longitude=${WEATHER_COORDS.lng}&current=temperature_2m,relative_humidity_2m,wind_speed_10m,precipitation,weather_code&wind_speed_unit=kmh&timezone=Asia/Manila`;
        const res = await fetch(url);
        const data = await res.json();
        const c = data.current;
        const code = c.weather_code;
        const { icon, desc } = weatherCodeInfo(code);

        document.getElementById('weatherIconSmall').textContent = icon;
        document.getElementById('weatherTempSmall').textContent = Math.round(c.temperature_2m) + '°';

        window._weatherData = { icon, desc, temp: Math.round(c.temperature_2m),
            humidity: c.relative_humidity_2m, wind: Math.round(c.wind_speed_10m),
            rain: c.precipitation };
        weatherLoaded = true;
    } catch(e) {
        document.getElementById('weatherIconSmall').textContent = '🌤';
        document.getElementById('weatherTempSmall').textContent = '--°';
    }
}

function weatherCodeInfo(code) {
    if (code === 0) return { icon: '☀️', desc: 'Clear skies' };
    if (code <= 2) return { icon: '⛅', desc: 'Partly cloudy' };
    if (code <= 3) return { icon: '☁️', desc: 'Overcast' };
    if (code <= 49) return { icon: '🌫', desc: 'Foggy' };
    if (code <= 67) return { icon: '🌧', desc: 'Rainy' };
    if (code <= 77) return { icon: '❄️', desc: 'Snowy' };
    if (code <= 82) return { icon: '🌦', desc: 'Rain showers' };
    if (code <= 99) return { icon: '⛈', desc: 'Thunderstorm' };
    return { icon: '🌤', desc: 'Partly cloudy' };
}

function toggleWeather() {
    const panel = document.getElementById('weatherPanel');
    weatherOpen = !weatherOpen;
    if (weatherOpen) {
        panel.classList.add('open');
        renderWeatherPanel();
    } else {
        panel.classList.remove('open');
    }
}
function closeWeather() {
    weatherOpen = false;
    document.getElementById('weatherPanel').classList.remove('open');
}
function renderWeatherPanel() {
    const el = document.getElementById('weatherContent');
    if (!weatherLoaded || !window._weatherData) {
        el.innerHTML = '<div class="weather-loading">Loading weather…</div>';
        fetchWeather().then(() => setTimeout(renderWeatherPanel, 500));
        return;
    }
    const w = window._weatherData;
    const trailAlert = w.rain > 1 ? `<div style="background:rgba(255,77,109,0.1);border:1px solid rgba(255,77,109,0.2);border-radius:10px;padding:10px 12px;font-size:12px;color:#ff4d6d;margin-top:10px;">⚠️ Precipitation detected — trail may be slippery. Exercise caution.</div>` : '';
    el.innerHTML = `
        <div class="weather-main">
            <div class="weather-big-icon">${w.icon}</div>
            <div>
                <div class="weather-temp">${w.temp}°C</div>
                <div class="weather-desc">${w.desc}</div>
            </div>
        </div>
        <div class="weather-grid">
            <div class="weather-stat">
                <div class="weather-stat-label">Humidity</div>
                <div class="weather-stat-val">${w.humidity}%</div>
            </div>
            <div class="weather-stat">
                <div class="weather-stat-label">Wind</div>
                <div class="weather-stat-val">${w.wind} km/h</div>
            </div>
            <div class="weather-stat">
                <div class="weather-stat-label">Rain</div>
                <div class="weather-stat-val">${w.rain} mm</div>
            </div>
        </div>
        ${trailAlert}
    `;
}

// ── FAB CONTROLS ─────────────────────────────────────────
function centerOnUser() {
    if (currentPosition) map.setView([currentPosition.lat, currentPosition.lng], 17);
    else showToast('Location not available yet');
}

let currentLayerIdx = 0;
const LAYERS = [
    'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
    'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
];
function toggleLayers() {
    currentLayerIdx = (currentLayerIdx + 1) % LAYERS.length;
    map.eachLayer(l => { if (l instanceof L.TileLayer) map.removeLayer(l); });
    L.tileLayer(LAYERS[currentLayerIdx], { attribution: '© OSM', maxZoom: 19 }).addTo(map);
    [trailLayer, traveledLayer].forEach(l => l && l.addTo(map));
    const labels = ['🗺 Voyager', '🌑 Dark', '🛰 Satellite'];
    showToast(labels[currentLayerIdx]);
}

function toggleArrows() {
    arrowsVisible = !arrowsVisible;
    const allMarkers = [...arrowMarkers, ...distanceMarkers];
    allMarkers.forEach(m => arrowsVisible ? m.addTo(map) : map.removeLayer(m));
    const btn = document.getElementById('arrowsBtn');
    btn.classList.toggle('fab-off', !arrowsVisible);
    showToast(arrowsVisible ? 'Trail markers shown' : 'Trail markers dimmed');
}

function toggleHikers() {
    hikersVisible = !hikersVisible;
    realHikerMarkers.forEach(m => hikersVisible ? m.addTo(map) : map.removeLayer(m));
    const btn = document.getElementById('hikersBtn');
    btn.classList.toggle('fab-off', !hikersVisible);
    
    // Update badge visibility
    const badge = document.getElementById('nearbyHikerCount');
    if (badge) {
        if (hikersVisible && realHikerMarkers.length) {
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
    
    showToast(hikersVisible ? `Showing ${realHikerMarkers.length} other hikers nearby` : 'Other hikers hidden');
    
    // Start polling when enabled
    if (hikersVisible && !nearbyHikerPollId) {
        fetchNearbyHikers();
        startNearbyHikerPolling();
    } else if (!hikersVisible && nearbyHikerPollId) {
        clearInterval(nearbyHikerPollId);
        nearbyHikerPollId = null;
    }
}

function toggleMetrics() {
    metricsHidden = !metricsHidden;
    document.getElementById('metricsPanel').style.display = metricsHidden ? 'none' : '';
    const btn = document.getElementById('metricsBtn');
    btn.classList.toggle('fab-off', metricsHidden);
    showToast(metricsHidden ? 'Metrics hidden' : 'Metrics shown');
}

function collapsePanel() {
    panelCollapsed = !panelCollapsed;
    document.getElementById('metricsPanel').classList.toggle('collapsed', panelCollapsed);
}

// ── BADGES ───────────────────────────────────────────────
function showBadge(name, icon, msg) {
    const n = document.getElementById('badgeNotif');
    document.getElementById('badgeEmoji').textContent = icon;
    document.getElementById('badgeName').textContent = name;
    document.getElementById('badgeMsg').textContent = msg;
    n.classList.add('show');
    clearTimeout(badgeTimeout);
    badgeTimeout = setTimeout(closeBadge, 5500);
    showToast(`🏅 ${name}`);
}
function closeBadge() {
    document.getElementById('badgeNotif').classList.remove('show');
}

function saveBadgeDB(name, icon, location, type) {
    fetch('../api/save_badge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ badge_name: name, icon, location, type, mountain_name: mountainName, booking_id: bookingId })
    }).catch(() => {});
}

function storeBadgeLocal(key) {
    const arr = JSON.parse(localStorage.getItem(`badges_${mountainName}`) || '[]');
    if (!arr.includes(key)) { arr.push(key); localStorage.setItem(`badges_${mountainName}`, JSON.stringify(arr)); }
}

function loadStoredBadges() {
    const arr = JSON.parse(localStorage.getItem(`badges_${mountainName}`) || '[]');
    arr.forEach(k => reachedCheckpoints.add(k));
}

function updateBadgeCounter() {
    const el = document.getElementById('badgeCounter');
    if (el) el.textContent = `🏅 ${reachedCheckpoints.size}/${waypoints.length}`;
}

function completeHike() {
    showToast('🎉 Trail completed! Tap Finish Hike to save your stats.');
    document.getElementById('statusText').textContent = 'DONE ⛰';
}

// ── FINISH HIKE (MANUAL) ──────────────────────────────────
async function finishHike() {
    if (hikeFinished) return;

    const btn = document.getElementById('finishHikeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span>Finishing…</span>';

    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }
    locationEnabled = false;

    let durationSec = Math.floor((Date.now() - startTime) / 1000);
    let finalDistance = totalDistance;
    
    // 🎯 DEMO MODE: If no distance was tracked, use full trail length
    if (finalDistance === 0 && trailLength > 0) {
        finalDistance = trailLength;
        // Calculate estimated duration based on trail length (approx 2.5 km/h average hiking speed)
        const estimatedDurationSec = finalDistance * 1440; // 2.5 km/h = 1440 sec per km
        durationSec = Math.max(60, estimatedDurationSec);
        showToast('🎭 Demo mode: Using full trail stats for presentation');
        
        // Also mark all waypoints as reached for demo
        waypoints.forEach((wp, idx) => {
            const key = `${wp.type}-${idx}`;
            if (!reachedCheckpoints.has(key)) {
                reachedCheckpoints.add(key);
                const badge = activeBadges[wp.type] || activeBadges.viewpoint;
                storeBadgeLocal(key);
            }
        });
        updateBadgeCounter();
        
        // Visually fill the entire traveled path
        if (trailCoords.length > 0 && traveledLayer) {
            traveledLayer.setLatLngs(trailCoords);
        }
        
        // Update progress UI to 100%
        document.getElementById('progressFill').style.width = '100%';
        document.getElementById('progressPct').textContent = '100%';
        document.getElementById('distanceCovered').textContent = finalDistance.toFixed(2);
        document.getElementById('distRemaining').innerHTML = '0.00 km remaining';
    }

    try {
    const res = await fetch('../api/finish_hike.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            booking_id: bookingId,
            session_token: sessionToken,
            distance: finalDistance,
            duration: durationSec,
            badges: Array.from(reachedCheckpoints)
        })
    });
    
    // Get the response text first to see what's actually returned
    const responseText = await res.text();
    console.log('Raw response:', responseText);
    
    // Try to parse as JSON
    let data;
    try {
        data = JSON.parse(responseText);
    } catch (e) {
        console.error('Failed to parse JSON. Response was:', responseText);
        showToast('Server error: Invalid response from server');
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg> Finish Hike';
        return;
    }

    if (data.success) {
        hikeFinished = true;
        showCompletionSummary(data.summary);
    } else {
        showToast('Error: ' + (data.message || 'Could not finish hike'));
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg> Finish Hike';
    }
} catch (err) {
    console.error('Finish hike error:', err);
    showToast('Network error — please try again');
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg> Finish Hike';
}
}
function showCompletionSummary(summary) {
    document.getElementById('statusText').textContent = 'COMPLETED';
    const pill = document.getElementById('statusPill');
    pill.style.background = 'rgba(6,214,160,0.18)';
    pill.style.borderColor = 'rgba(6,214,160,0.3)';
    pill.style.color = 'var(--success)';
    pill.querySelector('.status-dot').style.animation = 'none';

    // Update all stats
    document.getElementById('compTitle').textContent = `${summary.mountain} — Complete!`;
    document.getElementById('compDist').innerHTML = `${summary.distance_km} <span style="font-size:12px;">km</span>`;
    document.getElementById('compTime').innerHTML = `${summary.duration} <span style="font-size:12px;"></span>`;
    document.getElementById('compPace').innerHTML = `${summary.pace} <span style="font-size:12px;">/km</span>`;
    document.getElementById('compSpeed').innerHTML = `${summary.avg_speed_kmh} <span style="font-size:12px;">km/h</span>`;
    document.getElementById('compBadgesCount').textContent = `${summary.badges_count} badges earned`;

    document.getElementById('completionOverlay').classList.add('open');
    
    const btn = document.getElementById('finishHikeBtn');
    btn.style.display = 'none';
    
    // Wait for the modal to fully render and get proper dimensions
    setTimeout(() => {
        drawCompletionMap();
    }, 200);
}

function drawCompletionMap() {
    console.log('drawCompletionMap called');
    
    let coords = null;
    
    if (trailCoords && trailCoords.length > 0) {
        coords = trailCoords;
        console.log('Using trailCoords:', coords.length, 'points');
    } else if (trailData && trailData.type === 'LineString' && trailData.coordinates) {
        coords = trailData.coordinates.map(c => [c[1], c[0]]);
        console.log('Using trailData:', coords.length, 'points');
    }
    
    if (!coords || coords.length === 0) {
        console.log('No trail coordinates available');
        const mapContainer = document.getElementById('completionMap');
        if (mapContainer) {
            mapContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(255,255,255,0.5); font-size: 12px;">🗺️ Loading trail map...</div>';
        }
        return;
    }
    
    const mapContainer = document.getElementById('completionMap');
    if (!mapContainer) {
        console.log('completionMap element not found');
        return;
    }
    
    // Force a height on the container if needed
    if (mapContainer.clientHeight === 0) {
        console.log('Container has 0 height, forcing style');
        mapContainer.style.height = '160px';
        mapContainer.style.minHeight = '160px';
    }
    
    console.log('Container dimensions after fix:', mapContainer.clientWidth, 'x', mapContainer.clientHeight);
    
    // Clear and create canvas (reliable fallback)
    mapContainer.innerHTML = '';
    
    const canvas = document.createElement('canvas');
    canvas.width = mapContainer.clientWidth || 400;
    canvas.height = mapContainer.clientHeight || 160;
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.background = '#1a2e1a';
    canvas.style.borderRadius = '16px';
    canvas.style.display = 'block';
    mapContainer.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    
    // Calculate bounds
    let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
    coords.forEach(c => {
        minLat = Math.min(minLat, c[0]);
        maxLat = Math.max(maxLat, c[0]);
        minLng = Math.min(minLng, c[1]);
        maxLng = Math.max(maxLng, c[1]);
    });
    
    const latRange = maxLat - minLat;
    const lngRange = maxLng - minLng;
    const padding = 20;
    const width = canvas.width;
    const height = canvas.height;
    
    if (width > 0 && height > 0) {
        // Draw background
        ctx.fillStyle = '#1a2e1a';
        ctx.fillRect(0, 0, width, height);
        
        // Draw trail
        ctx.beginPath();
        ctx.strokeStyle = '#00e5b4';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        
        for (let i = 0; i < coords.length; i++) {
            const x = padding + ((coords[i][1] - minLng) / lngRange) * (width - padding * 2);
            const y = height - (padding + ((coords[i][0] - minLat) / latRange) * (height - padding * 2));
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();
        
        // Draw start marker
        const startX = padding + ((coords[0][1] - minLng) / lngRange) * (width - padding * 2);
        const startY = height - (padding + ((coords[0][0] - minLat) / latRange) * (height - padding * 2));
        ctx.fillStyle = '#00e5b4';
        ctx.beginPath();
        ctx.arc(startX, startY, 6, 0, 2 * Math.PI);
        ctx.fill();
        ctx.fillStyle = 'white';
        ctx.beginPath();
        ctx.arc(startX, startY, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw end marker
        const endX = padding + ((coords[coords.length-1][1] - minLng) / lngRange) * (width - padding * 2);
        const endY = height - (padding + ((coords[coords.length-1][0] - minLat) / latRange) * (height - padding * 2));
        ctx.fillStyle = '#ffd700';
        ctx.beginPath();
        ctx.arc(endX, endY, 7, 0, 2 * Math.PI);
        ctx.fill();
        ctx.fillStyle = 'white';
        ctx.beginPath();
        ctx.arc(endX, endY, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        console.log('Canvas trail drawn!', width, 'x', height);
    } else {
        console.log('Canvas dimensions invalid:', width, 'x', height);
        mapContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(255,255,255,0.5); font-size: 12px;">🗺️ Trail map</div>';
    }
}
// Share activity function (placeholder - can be extended later)
function shareActivity() {
    // Create a shareable message
    const distance = document.getElementById('compDist').innerText;
    const duration = document.getElementById('compTime').innerText;
    const mountain = document.getElementById('compTitle').innerText;
    
    const shareText = `${mountain}\n📏 ${distance} hiked\n⏱️ ${duration}\n\nTracked with Lakbay 🏔️`;
    
    if (navigator.share) {
        navigator.share({
            title: 'My Lakbay Hike',
            text: shareText,
            url: window.location.href
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(shareText);
        showToast('Stats copied to clipboard!');
    }
}
// ── END-TIME NUDGE ────────────────────────────────────────
function checkEndTimeNudge() {
    if (hikeFinished || nudgeDismissed || nudgeShown) return;

    const now = new Date();
    const today = new Date(hikeDate);
    let endHour = 15;

    if (hikeType === 'overnight') {
        return;
    } else if (hikeType === 'late' || hikeType === 'late_hike') {
        endHour = 23;
    }

    const scheduledEnd = new Date(today);
    scheduledEnd.setHours(endHour, 1, 0, 0);

    if (now >= scheduledEnd) {
        nudgeShown = true;
        const bar = document.getElementById('nudgeBar');
        bar.classList.add('show');
    }
}

function dismissNudge() {
    nudgeDismissed = true;
    document.getElementById('nudgeBar').classList.remove('show');
}

setInterval(checkEndTimeNudge, 60000);
setTimeout(checkEndTimeNudge, 5000);

// ── TOAST ─────────────────────────────────────────────────
let toastTimer;
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}

// ── UTILS ─────────────────────────────────────────────────
function calcDist(lat1, lon1, lat2, lon2) {
    const R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}


// ── REAL HIKERS (from database) + 1 MOCK HIKER ──────────
let realHikerMarkers = [];
let nearbyHikerPollId = null;
let mockHikerAdded = false;

// Mock hiker data (shows at a scenic viewpoint on the trail)
const MOCK_HIKER = {
    name: 'Demo Hiker',
    latitude: null, // Will be set to a midpoint on the trail
    longitude: null,
    minutes_ago: 2,
    is_mock: true
};

function getMockHikerPosition() {
    // Place mock hiker at about 30-40% of the trail (a nice viewpoint area)
    if (!trailCoords.length) return null;
    
    const midPoint = Math.floor(trailCoords.length * 0.35);
    const [lat, lng] = trailCoords[midPoint];
    return { lat, lng };
}

function startNearbyHikerPolling() {
    if (nearbyHikerPollId) clearInterval(nearbyHikerPollId);
    nearbyHikerPollId = setInterval(fetchNearbyHikers, 15000); // 15 seconds polling
}

async function fetchNearbyHikers() {
    if (!MOUNTAIN_ID) return;
    
    try {
        const res = await fetch(`../api/get_nearby_hikers.php?mountain_id=${MOUNTAIN_ID}&booking_id=${bookingId}`);
        const data = await res.json();
        
        if (data.success) {
            const realHikers = data.hikers || [];
            
            // If no real hikers, add one mock hiker for demo
            if (realHikers.length === 0) {
                const mockPos = getMockHikerPosition();
                if (mockPos) {
                    MOCK_HIKER.latitude = mockPos.lat;
                    MOCK_HIKER.longitude = mockPos.lng;
                    renderRealHikers([MOCK_HIKER], true);
                    mockHikerAdded = true;
                } else {
                    renderRealHikers([], false);
                }
            } else {
                // Reset mock flag when real hikers appear
                mockHikerAdded = false;
                renderRealHikers(realHikers, false);
            }
        }
    } catch (e) {
        console.error('Error fetching nearby hikers:', e);
        // On error, show mock hiker if none exist
        if (!mockHikerAdded && realHikerMarkers.length === 0) {
            const mockPos = getMockHikerPosition();
            if (mockPos) {
                MOCK_HIKER.latitude = mockPos.lat;
                MOCK_HIKER.longitude = mockPos.lng;
                renderRealHikers([MOCK_HIKER], true);
                mockHikerAdded = true;
            }
        }
    }
}

function renderRealHikers(hikers, isMock = false) {
    // Clear existing markers
    realHikerMarkers.forEach(m => map.removeLayer(m));
    realHikerMarkers = [];
    
    if (!hikers.length) {
        // Update badge to show 0
        const hikerCount = document.getElementById('nearbyHikerCount');
        if (hikerCount) {
            hikerCount.textContent = '0';
            if (!isMock) hikerCount.style.display = 'none';
        }
        return;
    }
    
    hikers.forEach(hiker => {
        if (!hiker.latitude || !hiker.longitude) return;
        
        const lat = parseFloat(hiker.latitude);
        const lng = parseFloat(hiker.longitude);
        const initials = (hiker.name || 'Hiker').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
        const isActive = hiker.minutes_ago <= 10;
        const isMockHiker = hiker.is_mock === true;
        
        // Different colors: pink for real hikers, purple for mock/demo
        const markerColor = isMockHiker ? '#9B59B6' : '#ff6b9d';
        
        const icon = L.divIcon({
            html: `<div class="real-hiker-marker ${isMockHiker ? 'mock-hiker' : ''}" style="background: ${markerColor};">
                        <span class="hiker-initials">${initials}</span>
                        <div class="hiker-status-dot ${isActive ? 'online' : 'offline'}"></div>
                    </div>`,
            className: 'real-hiker-icon',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -18]
        });
        
        const timeAgo = hiker.minutes_ago === 0 ? 'Just now' : `${hiker.minutes_ago} min ago`;
        
        let popupContent;
        if (isMockHiker) {
            popupContent = `
                <div class="popup-title">🎭 ${escapeHtml(hiker.name)} (Demo)</div>
                <div class="popup-body">
                    <strong>Status:</strong> 🟢 Demo hiker - showing how other hikers appear<br>
                    <strong>Note:</strong> This is a demonstration. Real hikers will appear here when they are nearby!
                </div>
                <div class="popup-note">
                    <i class="fas fa-info-circle"></i> Mock hiker - for demonstration only
                </div>
            `;
        } else {
            popupContent = `
                <div class="popup-title">🧑‍🦯 ${escapeHtml(hiker.name)}</div>
                <div class="popup-body">
                    <strong>Status:</strong> ${isActive ? '🟢 Active on trail' : '🟡 Last seen ' + timeAgo}<br>
                    <strong>Last update:</strong> ${timeAgo}
                </div>
                <div class="popup-note">
                    <i class="fas fa-map-marker-alt"></i> Also hiking ${mountainName}
                </div>
            `;
        }
        
        const marker = L.marker([lat, lng], { icon })
            .bindPopup(popupContent);
            
        if (hikersVisible) {
            marker.addTo(map);
        }
        
        realHikerMarkers.push(marker);
    });
    
    // Update badge counter
    const realCount = hikers.filter(h => !h.is_mock).length;
    const hikerCount = document.getElementById('nearbyHikerCount');
    if (hikerCount) {
        if (realCount > 0) {
            hikerCount.textContent = realCount;
            hikerCount.style.display = 'inline-block';
        } else if (hikers.length > 0 && hikers[0].is_mock) {
            // Mock hiker - show 0 or hide badge
            hikerCount.textContent = '0';
            hikerCount.style.display = 'none';
        } else {
            hikerCount.textContent = '0';
            hikerCount.style.display = 'none';
        }
    }
}



// ── CLEANUP ───────────────────────────────────────────────
window.addEventListener('beforeunload', () => {
    if (watchId) navigator.geolocation.clearWatch(watchId);
    if (updateInterval) clearInterval(updateInterval);
});

document.addEventListener('DOMContentLoaded', initMap);
</script>

<?php if (isset($trailData) && $trailData): ?>
<script>
console.log('Trail points:', <?= isset($trackPoints) ? count($trackPoints) : 0 ?>);
console.log('Waypoints:', <?= isset($hike['waypoints']) ? count($hike['waypoints']) : 0 ?>);
</script>
<?php endif; ?>
</body>
</html>