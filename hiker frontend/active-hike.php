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
        header('Location: bookings.php?error=invalid_hike');
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
    die("Database error: " . $e->getMessage());
}

$stmt = $pdo->prepare("
    SELECT id, session_token, start_time, last_location_update
    FROM active_hike_sessions
    WHERE booking_id = ? AND user_id = ? AND status = 'active'
");
$stmt->execute([$hike['id'], $currentUserId]);
$activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activeSession) {
    $sessionToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO active_hike_sessions (booking_id, user_id, session_token, start_time, status) VALUES (?, ?, ?, NOW(), 'active')");
    $stmt->execute([$hike['id'], $currentUserId, $sessionToken]);
    $activeSession = ['session_token' => $sessionToken];
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
    <button class="fab" id="metricsBtn" onclick="toggleMetrics()" title="Hide Metrics">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 9l-5 5-2-2-4 4"/></svg>
    </button>
    <button class="fab" id="hikersBtn" onclick="toggleHikers()" title="Toggle Other Hikers">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </button>
    <button class="fab" id="heatmapBtn" onclick="toggleHeatmapFab()" title="Heatmap">
        <svg viewBox="0 0 24 24"><path d="M12 2c-4 0-8 3-8 8 0 5 8 12 8 12s8-7 8-12c0-5-4-8-8-8z"/><circle cx="12" cy="10" r="3"/></svg>
    </button>
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
        <div class="completion-stats">
            <div class="comp-stat">
                <div class="comp-stat-val" id="compDist">0.0</div>
                <div class="comp-stat-label">km hiked</div>
            </div>
            <div class="comp-stat">
                <div class="comp-stat-val" id="compTime">0:00</div>
                <div class="comp-stat-label">duration</div>
            </div>
        </div>
        <div class="completion-actions">
            <button class="btn-secondary" onclick="window.location.href='hikerProfile.php'">Profile</button>
            <a href="bookings.php" class="btn-primary" style="text-decoration:none;text-align:center;">Back to Bookings</a>
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

// ── STATE ───────────────────────────────────────────────
let map, userMarker, trailLayer, traveledLayer;
let waypointMarkers = [], arrowMarkers = [], distanceMarkers = [], hikerMarkers = [];
let heatmapLayer;
let watchId = null, updateInterval = null;
let currentPosition = null, lastPosition = null;
let totalDistance = 0, trailCoords = [], reachedCheckpoints = new Set();
let locationEnabled = false, arrowsVisible = true, hikersVisible = true;
let panelCollapsed = false, metricsHidden = false;
let startTime = Date.now();
let weatherOpen = false, weatherLoaded = false;
let badgeTimeout = null;
let hikeFinished = false;
let nudgeDismissed = false;
let nudgeShown = false;

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
    placeWaypoints();
    placeStartMarker();
    buildHeatmap();
    placeMockHikers();
    checkLocationPermission();
    fetchWeather();

    if (trailCoords.length > 0) {
        map.fitBounds(L.latLngBounds(trailCoords).pad(0.1));
    }

    setInterval(tickTimer, 1000);
    loadStoredBadges();
    updateBadgeCounter();
}

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
        lls = waypoints.map(w => [w.latitude, w.longitude]);
    }
    if (!lls.length) return;

    // Dim base trail
    trailLayer = L.polyline(lls, {
        color: 'rgba(255,255,255,0.25)',
        weight: 5, opacity: 1, lineCap: 'round', lineJoin: 'round'
    }).addTo(map);

    // Glowing traveled overlay
    traveledLayer = L.polyline([], {
        color: '#00e5b4', weight: 6, opacity: 1,
        lineCap: 'round', lineJoin: 'round'
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
            distanceMarkers.push(L.marker(coords[i], { icon, interactive: false }).addTo(map));
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
            if (calcDist(p[0], p[1], wp.latitude, wp.longitude) < 0.02) {
                reachCheckpoint(wp, i);
                break;
            }
        }
    });
}

// ── WAYPOINTS ───────────────────────────────────────────
function placeWaypoints() {
    waypoints.forEach((wp, i) => {
        const key = `${wp.type}-${i}`;
        const done = reachedCheckpoints.has(key);
        const pinClass = done ? 'achieved' : (wp.type === 'summit' ? 'summit' : wp.type === 'start' ? 'start' : '');
        const icon = L.divIcon({
            html: `<div class="wp-marker">
                <div class="wp-pin ${pinClass}"></div>
                <div class="wp-label ${done ? 'achieved' : ''}">${done ? '✓' : ''} ${wp.name}</div>
            </div>`,
            className: '', iconSize: [60, 36], iconAnchor: [30, 9], popupAnchor: [0, -10]
        });
        const m = L.marker([wp.latitude, wp.longitude], { icon })
            .bindPopup(() => {
                const isDone = reachedCheckpoints.has(key);
                return `<div class="popup-title">${wp.name}</div>
                <div class="popup-body">${wp.description || 'Waypoint on the trail'}</div>
                ${isDone
                    ? `<div class="popup-achieved">✓ Checkpoint reached!</div>`
                    : `<button class="popup-btn" onclick="window.forceAchieve(${i})">🎯 Mark as Reached</button>`
                }`;
            });
        m.addTo(map);
        waypointMarkers.push(m);
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

    // Update marker
    const icon = L.divIcon({
        html: `<div class="wp-marker">
            <div class="wp-pin achieved"></div>
            <div class="wp-label achieved">✓ ${wp.name}</div>
        </div>`,
        className: '', iconSize: [60, 36], iconAnchor: [30, 9]
    });
    if (waypointMarkers[i]) waypointMarkers[i].setIcon(icon);

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
    const positions = [0.08, 0.2, 0.37, 0.52, 0.68, 0.82]; // fractions along trail

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
}

// ── LOCATION ─────────────────────────────────────────────
function checkLocationPermission() {
    if (!navigator.geolocation) {
        showToast('Geolocation not supported'); return;
    }
    navigator.permissions.query({ name: 'geolocation' }).then(res => {
        if (res.state === 'granted') {
            // Slight delay so overlay is visible briefly
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
    // document.getElementById('heatmapToggle').style.display = 'flex';

    watchId = navigator.geolocation.watchPosition(onLocationUpdate, onLocationError, {
        enableHighAccuracy: true, timeout: 10000, maximumAge: 0
    });
    startReporting();
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

    // User marker
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
function buildHeatmap() {
    if (!trailCoords.length) return;
    const pts = [0.1, 0.25, 0.45, 0.6, 0.8, 0.95].map(f => {
        const idx = Math.floor(f * trailCoords.length);
        return [...trailCoords[idx], f > 0.7 ? 1.0 : 0.6];
    });
    heatmapLayer = L.heatLayer(pts, {
        radius: 35, blur: 20, maxZoom: 18,
        gradient: { 0.2: 'blue', 0.5: 'cyan', 0.75: 'lime', 0.9: 'yellow', 1.0: 'red' }
    });
}

let heatmapEnabled = false;

function toggleHeatmapFab() {
    heatmapEnabled = !heatmapEnabled;
    const btn = document.getElementById('heatmapBtn');
    
    if (heatmapEnabled) {
        if (heatmapLayer) heatmapLayer.addTo(map);
        btn.classList.remove('fab-off');
        btn.style.background = 'rgba(0,229,180,0.3)';
        showToast('🔥 Heatmap enabled');
    } else {
        if (heatmapLayer) map.removeLayer(heatmapLayer);
        btn.classList.add('fab-off');
        btn.style.background = '';
        showToast('Heatmap off');
    }
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

        // Update header chip
        document.getElementById('weatherIconSmall').textContent = icon;
        document.getElementById('weatherTempSmall').textContent = Math.round(c.temperature_2m) + '°';

        // Store for panel
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
    hikerMarkers.forEach(m => hikersVisible ? m.addTo(map) : map.removeLayer(m));
    const btn = document.getElementById('hikersBtn');
    btn.classList.toggle('fab-off', !hikersVisible);
    showToast(hikersVisible ? `Showing ${hikerMarkers.length} other hikers` : 'Hikers hidden');
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
    // Called when user reaches the 'end' waypoint automatically
    // We show a toast but DO NOT auto-finish — user must click Finish Hike
    showToast('🎉 Trail completed! Tap Finish Hike to save your stats.');
    document.getElementById('statusText').textContent = 'DONE ⛰';
}

// ── FINISH HIKE (MANUAL) ──────────────────────────────────
async function finishHike() {
    if (hikeFinished) return;

    const btn = document.getElementById('finishHikeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span>Finishing…</span>';

    // 1. Stop GPS tracking
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }
    locationEnabled = false;

    // 2. Calculate duration
    const durationSec = Math.floor((Date.now() - startTime) / 1000);

    // 3. Call API
    try {
        const res = await fetch('../api/finish_hike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: bookingId,
                session_token: sessionToken,
                distance: totalDistance,
                duration: durationSec,
                badges: Array.from(reachedCheckpoints)  // This is an array
            })
        });
        const data = await res.json();

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
    // Update status
    document.getElementById('statusText').textContent = 'COMPLETED';
    const pill = document.getElementById('statusPill');
    pill.style.background = 'rgba(6,214,160,0.18)';
    pill.style.borderColor = 'rgba(6,214,160,0.3)';
    pill.style.color = 'var(--success)';
    pill.querySelector('.status-dot').style.animation = 'none';

    // Fill summary card (only distance and time)
    document.getElementById('compTitle').textContent = `${summary.mountain} — Complete!`;
    document.getElementById('compDist').textContent = summary.distance_km.toFixed(1);
    document.getElementById('compTime').textContent = summary.duration;
    // Remove the badges line if it exists
    // document.getElementById('compBadges').textContent = summary.badges_earned;

    // Show overlay
    document.getElementById('completionOverlay').classList.add('open');

    // Hide the finish button
    const btn = document.getElementById('finishHikeBtn');
    btn.style.display = 'none';
}

// ── END-TIME NUDGE ────────────────────────────────────────
function checkEndTimeNudge() {
    if (hikeFinished || nudgeDismissed || nudgeShown) return;

    // Determine scheduled end time based on hike type
    const now = new Date();
    const today = new Date(hikeDate);
    let endHour = 15; // 3 PM default for day hikes

    if (hikeType === 'overnight') {
        endHour = 12; // Noon next day, but we'll just check if >24h
        return; // Don't nudge overnight hikes on time
    } else if (hikeType === 'late' || hikeType === 'late_hike') {
        endHour = 23; // 11 PM
    }
    // day_hike or day → 3 PM

    const scheduledEnd = new Date(today);
    scheduledEnd.setHours(endHour, 1, 0, 0); // +1 min past scheduled end

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

// Check every 60 seconds
setInterval(checkEndTimeNudge, 60000);
// Also check once after GPS is enabled (5 second delay)
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