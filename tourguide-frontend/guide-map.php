<?php
// guide-map.php — Live Trail Map for Guides (Leaflet + Real DB Data + Crowd Reporting)
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Must be a guide
$currentUserId = $_SESSION['user_id'];
$currentUserName = $_SESSION['name'] ?? $_SESSION['user_name'] ?? '';

// Get guide record
$guideRecord = null;
try {
    $stmt = $pdo->prepare("SELECT g.id as guide_id, u.name, u.avatar FROM guides g JOIN users u ON g.user_id = u.id WHERE g.user_id = ?");
    $stmt->execute([$currentUserId]);
    $guideRecord = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Guide lookup error: " . $e->getMessage());
}

// Find today's active/confirmed booking for this guide
$booking = null;
$hike = null;
$trackPoints = [];
$trailData = null;
$bookingHikers = [];
$currentCrowdLevel = null;

try {
    $guideId = $guideRecord['guide_id'] ?? null;
    if ($guideId) {
        $stmt = $pdo->prepare("
            SELECT b.*, m.name as mountain_name, m.location, m.difficulty,
                   m.trail_data, m.start_point_lat, m.start_point_lng,
                   m.trail_length_km, m.estimated_duration, m.crowdLevel, m.id as mountain_id
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            WHERE b.guide_id = ?
              AND b.status IN ('active','confirmed')
            ORDER BY ABS(DATEDIFF(b.hike_date, CURDATE())) ASC
            LIMIT 1
        ");
        $stmt->execute([$guideId]);
        $hike = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($hike) {
            $currentCrowdLevel = $hike['crowdLevel'];
        }
    }

    // Fallback: try GET param
    $bookingId = $_GET['booking_id'] ?? null;
    if (!$hike && $bookingId) {
        $stmt = $pdo->prepare("
            SELECT b.*, m.name as mountain_name, m.location, m.difficulty,
                   m.trail_data, m.start_point_lat, m.start_point_lng,
                   m.trail_length_km, m.estimated_duration, m.crowdLevel, m.id as mountain_id
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $hike = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($hike) {
            $currentCrowdLevel = $hike['crowdLevel'];
        }
    }

    if ($hike) {
        // Fetch track points
        $fileIdMap = [1 => 'BATULAO', 2 => 'APAYANG', 3 => 'LANTIK', 4 => 'TALAMITAM'];
        $fileId = $fileIdMap[$hike['mountain_id']] ?? null;
        if ($fileId) {
            $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = ? ORDER BY idx ASC");
            $stmt->execute([$fileId]);
            $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($trackPoints)) {
            try {
                $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE mountain_id = ? ORDER BY idx ASC");
                $stmt->execute([$hike['mountain_id']]);
                $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        if (!empty($trackPoints)) {
            $coordinates = [];
            foreach ($trackPoints as $p) {
                $coordinates[] = [(float)$p['lon'], (float)$p['lat']];
            }
            $trailData = ['type' => 'LineString', 'coordinates' => $coordinates];

            $totalLength = 0;
            for ($i = 0; $i < count($trackPoints) - 1; $i++) {
                $lat1=$trackPoints[$i]['lat']; $lon1=$trackPoints[$i]['lon'];
                $lat2=$trackPoints[$i+1]['lat']; $lon2=$trackPoints[$i+1]['lon'];
                $R=6371; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
                $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
                $totalLength += $R*2*atan2(sqrt($a),sqrt(1-$a));
            }
            $hike['trail_length_km'] = round($totalLength, 2);
        } elseif (!empty($hike['trail_data'])) {
            $trailData = json_decode($hike['trail_data'], true);
        }

        // After fetching track points, add waypoints loading from your existing table
$waypoints = [];
if ($hike) {
    try {
       $stmt = $pdo->prepare("
    SELECT id, name, type, latitude, longitude, elevation, description, order_index 
    FROM trail_waypoints 
    WHERE mountain_id = ? AND is_active = 1
    ORDER BY order_index ASC, id ASC
");
        $stmt->execute([$hike['mountain_id']]);
        $waypoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Waypoints fetch error: " . $e->getMessage());
    }
}

        // Get booking hikers
        $stmt = $pdo->prepare("
            SELECT bh.hiker_name as name, bh.age, bh.emergency_contact_name, bh.emergency_contact_number,
                   u.id as user_id, u.avatar, u.location_tracking_enabled
            FROM booking_hikers bh
            LEFT JOIN users u ON (u.name = bh.hiker_name AND u.role = 'hiker')
            WHERE bh.booking_id = ?
        ");
        $stmt->execute([$hike['id']]);
        $bookingHikers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Also add the booking owner
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.name, u.avatar, u.location_tracking_enabled
            FROM bookings b JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$hike['id']]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($owner) {
            $ownerInList = false;
            foreach ($bookingHikers as $bh) {
                if ($bh['user_id'] == $owner['user_id']) { $ownerInList = true; break; }
            }
            if (!$ownerInList) {
                array_unshift($bookingHikers, [
                    'name' => $owner['name'],
                    'user_id' => $owner['user_id'],
                    'avatar' => $owner['avatar'],
                    'location_tracking_enabled' => $owner['location_tracking_enabled'],
                    'age' => null,
                    'emergency_contact_name' => null,
                    'emergency_contact_number' => null,
                ]);
            }
        }

        // Get latest location for each hiker
        foreach ($bookingHikers as &$bh) {
            if (!empty($bh['user_id'])) {
                $stmt = $pdo->prepare("
                    SELECT latitude, longitude, recorded_at,
                           TIMESTAMPDIFF(MINUTE, recorded_at, NOW()) as minutes_ago
                    FROM location_history
                    WHERE user_id = ? AND booking_id = ?
                    ORDER BY recorded_at DESC LIMIT 1
                ");
                $stmt->execute([$bh['user_id'], $hike['id']]);
                $loc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($loc) {
                    $bh['latitude'] = $loc['latitude'];
                    $bh['longitude'] = $loc['longitude'];
                    $bh['recorded_at'] = $loc['recorded_at'];
                    $bh['minutes_ago'] = $loc['minutes_ago'];
                    $bh['is_active'] = $loc['minutes_ago'] <= 10;
                }
            }
        }
        unset($bh);
    }

    // Guide's own session token
    $sessionToken = null;
    if ($hike) {
        $stmt = $pdo->prepare("SELECT session_token FROM active_hike_sessions WHERE booking_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$hike['id'], $currentUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $sessionToken = $row['session_token'];
        } else {
            $sessionToken = bin2hex(random_bytes(32));
            try {
                $stmt = $pdo->prepare("INSERT INTO active_hike_sessions (booking_id, user_id, session_token, start_time, status) VALUES (?, ?, ?, NOW(), 'active')");
                $stmt->execute([$hike['id'], $currentUserId, $sessionToken]);
            } catch (Exception $e) {}
        }
    }

} catch (PDOException $e) {
    error_log("Guide map DB error: " . $e->getMessage());
}

$guideName = $guideRecord['name'] ?? $currentUserName;
$guideInitials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $guideName), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Live Trail Map</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    /* Override guide-shared to allow full height */
    .guide-main {
      display: flex;
      flex-direction: column;
      flex: 1;
      overflow: hidden;
    }
    
    .guide-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding: 0;
    }

    /* Top Tabs - positioned inside guide-main, below topbar */
    .view-tabs {
      display: flex;
      background: white;
      border-bottom: 1px solid rgba(0,0,0,0.08);
      padding: 0 20px;
      gap: 0;
      flex-shrink: 0;
    }
    
    .view-tab {
      padding: 12px 24px;
      font-size: 0.85rem;
      font-weight: 600;
      color: #666;
      background: none;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }
    
    .view-tab i {
      margin-right: 8px;
    }
    
    .view-tab:hover {
      color: #100600;
    }
    
    .view-tab.active {
      color: #100600;
    }
    
    .view-tab.active::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 2px;
      background: #100600;
    }

    /* Map View Container */
    .map-view {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    
    .map-view.hidden {
      display: none;
    }
    
    /* Safety View Container */
    .safety-view {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    
    .safety-view.hidden {
      display: none;
    }
    
    .safety-iframe {
      width: 100%;
      height: 100%;
      border: none;
    }

    .map-layout {
      display: flex;
      flex: 1;
      overflow: hidden;
    }

    /* ── MAP CANVAS ── */
    .map-canvas {
      flex: 1;
      position: relative;
      overflow: hidden;
      background: #1a1208;
    }
    #leaflet-map {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      z-index: 1;
    }

    /* Leaflet overrides */
    .leaflet-control-zoom {
      border: none !important;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18) !important;
      border-radius: 10px !important;
      overflow: hidden;
    }
    .leaflet-control-zoom a {
      background: rgba(255,255,255,0.95) !important;
      color: #100600 !important;
      border: none !important;
      width: 36px !important; height: 36px !important;
      line-height: 36px !important;
      font-size: 16px !important;
      transition: background 0.15s !important;
    }
    .leaflet-control-zoom a:hover { background: #100600 !important; color: white !important; }
    .leaflet-control-zoom-in { border-bottom: 1px solid rgba(0,0,0,0.06) !important; }
    .leaflet-top.leaflet-left { top: 80px !important; left: 12px !important; }
    .leaflet-popup-content-wrapper {
      background: rgba(255,255,255,0.97) !important;
      border: 1px solid rgba(16,6,0,0.08) !important;
      border-radius: 14px !important;
      box-shadow: 0 8px 32px rgba(0,0,0,0.14) !important;
    }
    .leaflet-popup-content { color: #100600 !important; font-family: 'DM Sans', sans-serif !important; margin: 14px 16px !important; }
    .leaflet-popup-tip { background: rgba(255,255,255,0.97) !important; }
    .leaflet-control-attribution { display: none !important; }

    /* Crowd Report Button */
    .crowd-report-btn {
    display: none;
}
    .crowd-report-btn:hover {
      background: #2a1a0f;
      transform: translateX(-50%) scale(1.02);
    }
    .crowd-report-btn i {
      font-size: 1rem;
    }

    /* Crowd Level Modal */
    .crowd-modal {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(5px);
      z-index: 2000;
      display: none;
      align-items: center;
      justify-content: center;
    }
    .crowd-modal.open {
      display: flex;
    }
    .crowd-modal-content {
      background: white;
      border-radius: 24px;
      padding: 24px;
      max-width: 320px;
      width: 90%;
      text-align: center;
    }
    .crowd-modal-content h3 {
      font-size: 1.2rem;
      margin-bottom: 20px;
      color: #100600;
    }
    .crowd-level-options {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 20px;
    }
    .crowd-option {
      padding: 14px;
      border: 2px solid #eee;
      border-radius: 12px;
      background: #faf9f7;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      font-weight: 600;
    }
    .crowd-option:hover {
      border-color: #100600;
      background: #f5f3ef;
    }
    .crowd-option.selected {
      border-color: #100600;
      background: #100600;
      color: white;
    }
    .crowd-option[data-level="Low"] { color: #1B7045; }
    .crowd-option[data-level="Medium"] { color: #C97B1A; }
    .crowd-option[data-level="High"] { color: #B8312A; }
    .crowd-option.selected { color: white; }
    .crowd-modal-buttons {
      display: flex;
      gap: 10px;
    }
    .crowd-modal-buttons button {
      flex: 1;
      padding: 12px;
      border: none;
      border-radius: 30px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-cancel {
      background: #eee;
      color: #666;
    }
    .btn-submit {
      background: #100600;
      color: white;
    }

    /* ── MAP STATUS BAR ── */
    .map-status-bar {
      position: absolute;
      top: 12px; left: 12px;
      z-index: 10;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(14px);
      border-radius: 30px;
      padding: 8px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      border: 1px solid rgba(255,255,255,0.8);
    }
    .live-dot {
      width: 8px; height: 8px;
      background: #E74C3C;
      border-radius: 50%;
      flex-shrink: 0;
      animation: livePulse 1.5s infinite;
    }
    @keyframes livePulse {
      0%,100% { box-shadow: 0 0 0 0 rgba(231,76,60,0.5); }
      50% { box-shadow: 0 0 0 6px rgba(231,76,60,0); }
    }
    .map-status-text { font-size: 0.78rem; font-weight: 700; color: #100600; }
    .map-status-sub { font-size: 0.64rem; color: #888; }

    /* Crowd indicator in status bar */
    .crowd-indicator {
      margin-left: 12px;
      padding-left: 12px;
      border-left: 1px solid #ddd;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .crowd-badge {
      font-size: 0.7rem;
      padding: 3px 8px;
      border-radius: 20px;
      font-weight: 600;
    }
    .crowd-badge.Low { background: rgba(27,112,69,0.15); color: #1B7045; }
    .crowd-badge.Medium { background: rgba(201,123,26,0.15); color: #C97B1A; }
    .crowd-badge.High { background: rgba(184,49,42,0.15); color: #B8312A; }

    /* ── MAP FAB CONTROLS ── */
    .map-fabs {
      position: absolute;
      right: 12px; top: 12px;
      z-index: 10;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .map-fab {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(0,0,0,0.06);
      border-radius: 10px;
      color: #100600;
      font-size: 0.85rem;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.09);
      transition: all 0.15s;
    }
    .map-fab:hover { background: #100600; color: white; }
    .map-fab.active { background: #100600; color: white; }

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

    /* ── GUIDE MARKER ── */
    .guide-pin {
      display: flex; flex-direction: column; align-items: center; gap: 3px;
    }
    .guide-pin-dot {
      width: 20px; height: 20px;
      border-radius: 50%;
      background: #100600;
      border: 3px solid white;
      box-shadow: 0 0 0 4px rgba(16,6,0,0.2), 0 2px 12px rgba(0,0,0,0.25);
      animation: guidePulse 2.2s infinite;
    }
    @keyframes guidePulse {
      0%,100% { box-shadow: 0 0 0 4px rgba(16,6,0,0.2), 0 2px 12px rgba(0,0,0,0.2); }
      50% { box-shadow: 0 0 0 12px rgba(16,6,0,0), 0 2px 12px rgba(0,0,0,0.2); }
    }
    .guide-pin-label {
      background: #100600;
      color: white;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: 0.55rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }

    /* ── HIKER MARKER ── */
    .hiker-pin {
      display: flex; flex-direction: column; align-items: center; gap: 3px;
      cursor: pointer;
    }
    .hiker-pin-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      border: 3px solid white;
      background: #f0ede8;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.75rem;
      font-weight: 700;
      color: #100600;
      box-shadow: 0 2px 12px rgba(0,0,0,0.18);
      overflow: hidden;
    }
    .hiker-pin-avatar.status-safe { border-color: #1B7045; }
    .hiker-pin-avatar.status-warn { border-color: #C97B1A; }
    .hiker-pin-avatar.status-off { border-color: #ccc; opacity: 0.7; }
    .hiker-pin-name {
      background: rgba(16,6,0,0.78);
      color: white;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 0.58rem;
      font-weight: 600;
      white-space: nowrap;
      backdrop-filter: blur(4px);
    }

    /* ── SIDE PANEL ── */
    .map-side-panel {
      width: 300px;
      background: rgba(255,255,255,0.97);
      border-left: 1px solid rgba(0,0,0,0.06);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .side-panel-header {
      padding: 16px 18px;
      border-bottom: 1px solid rgba(0,0,0,0.05);
      background: rgba(248,247,245,0.95);
    }
    .side-panel-header h3 {
      font-size: 0.95rem;
      font-weight: 700;
      color: #100600;
    }
    .side-panel-header p {
      font-size: 0.66rem;
      color: #aaa;
      margin-top: 2px;
    }

    .side-tabs {
      display: flex;
      border-bottom: 1px solid rgba(0,0,0,0.06);
      background: #faf9f7;
    }
    .side-tab {
      flex: 1;
      padding: 8px 0;
      font-size: 0.7rem;
      font-weight: 600;
      color: #999;
      text-align: center;
      cursor: pointer;
      border: none;
      background: none;
      border-bottom: 2px solid transparent;
      transition: all 0.15s;
    }
    .side-tab.active { color: #100600; border-bottom-color: #100600; background: rgba(255,255,255,0.9); }

    .hiker-track-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
      display: flex;
      flex-direction: column;
      gap: 7px;
    }
    .track-item {
      background: rgba(248,247,245,0.8);
      border-radius: 12px;
      padding: 11px 13px;
      display: flex;
      align-items: center;
      gap: 11px;
      cursor: pointer;
      transition: all 0.18s;
      border: 1.5px solid transparent;
    }
    .track-item:hover { background: rgba(16,6,0,0.04); border-color: rgba(16,6,0,0.1); }
    .track-item.selected { background: rgba(16,6,0,0.07); border-color: #100600; }
    .track-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: #f0ede8;
      color: #100600;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem;
      font-weight: 700;
      flex-shrink: 0;
      overflow: hidden;
    }
    .track-info { flex: 1; min-width: 0; }
    .track-name { font-size: 0.82rem; font-weight: 600; color: #100600; }
    .track-location { font-size: 0.66rem; color: #aaa; margin-top: 2px; }
    .track-status-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .ts-safe { background: #1B7045; box-shadow: 0 0 0 2px rgba(27,112,69,0.2); }
    .ts-warn { background: #C97B1A; box-shadow: 0 0 0 2px rgba(201,123,26,0.2); }
    .ts-off { background: #ccc; }

    /* Info tab */
    .info-tab-content {
      flex: 1;
      overflow-y: auto;
      padding: 14px;
      display: none;
      flex-direction: column;
      gap: 10px;
    }
    .info-tab-content.active { display: flex; }
    .hikers-tab-content {
      display: flex;
      flex-direction: column;
      overflow: hidden;
      flex: 1;
    }
    .hikers-tab-content.hidden { display: none; }

    .info-stat-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .info-stat-box {
      background: #faf9f7;
      border: 1px solid rgba(0,0,0,0.05);
      border-radius: 10px;
      padding: 10px 12px;
    }
    .info-stat-label { font-size: 0.62rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: #bbb; margin-bottom: 3px; }
    .info-stat-val { font-size: 1.1rem; font-weight: 700; color: #100600; font-family: 'DM Mono', monospace; }
    .info-stat-unit { font-size: 0.65rem; color: #bbb; }
    .info-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #bbb;
      margin-top: 4px;
      margin-bottom: 4px;
    }

    .legend {
      padding: 12px 16px;
      border-top: 1px solid rgba(0,0,0,0.05);
      background: rgba(255,255,255,0.9);
    }
    .legend-title { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #ccc; margin-bottom: 7px; }
    .legend-item { display: flex; align-items: center; gap: 9px; font-size: 0.68rem; color: #888; margin-bottom: 5px; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

    /* ── FINISH HIKE SECTION ── */
    .finish-section {
      padding: 12px 14px;
      border-top: 1px solid rgba(0,0,0,0.05);
      background: rgba(255,255,255,0.95);
    }
    .btn-finish {
      width: 100%;
      padding: 11px 16px;
      border: none;
      border-radius: 30px;
      background: linear-gradient(135deg, #B8312A, #8B1A14);
      color: white;
      font-size: 0.82rem;
      font-weight: 700;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 7px;
      box-shadow: 0 4px 16px rgba(184,49,42,0.3);
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .btn-finish:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(184,49,42,0.4); }
    .btn-finish:active { transform: scale(0.97); }
    .btn-finish:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    /* ── COMPLETION OVERLAY ── */
    .completion-overlay {
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(16,6,0,0.85);
      backdrop-filter: blur(18px);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .completion-overlay.open { display: flex; }
    .completion-card {
      background: white;
      border-radius: 24px;
      width: 100%;
      max-width: 400px;
      padding: 36px 28px 28px;
      text-align: center;
      animation: cardUp 0.4s cubic-bezier(0.16,1,0.3,1);
    }
    @keyframes cardUp {
      from { opacity:0; transform: translateY(40px); }
      to { opacity:1; transform: translateY(0); }
    }
    .comp-emoji { font-size: 52px; margin-bottom: 14px; }
    .comp-title { font-size: 1.4rem; font-weight: 800; color: #100600; margin-bottom: 5px; }
    .comp-sub { font-size: 0.82rem; color: #999; margin-bottom: 20px; }
    .comp-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
    .comp-stat { background: #faf9f7; border-radius: 12px; padding: 14px 10px; }
    .comp-stat-val { font-size: 1.4rem; font-weight: 700; color: #100600; font-family: 'DM Mono', monospace; }
    .comp-stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.7px; color: #bbb; margin-top: 3px; }
    .comp-actions { display: flex; gap: 10px; }
    .comp-btn-back {
      flex: 1; padding: 13px; border: none; border-radius: 30px;
      background: #100600; color: white;
      font-size: 0.85rem; font-weight: 700; cursor: pointer;
      text-decoration: none; display: flex; align-items: center; justify-content: center;
    }
    .comp-btn-sec {
      padding: 13px 16px; border: 1.5px solid rgba(0,0,0,0.1); border-radius: 30px;
      background: transparent; color: #888;
      font-size: 0.82rem; font-weight: 600; cursor: pointer;
    }

    /* ── NO BOOKING STATE ── */
    .no-booking {
      position: absolute;
      inset: 0;
      z-index: 20;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.95);
      gap: 12px;
      text-align: center;
      padding: 40px;
    }
    .no-booking-icon { font-size: 3rem; margin-bottom: 8px; }
    .no-booking h3 { font-size: 1.1rem; font-weight: 700; color: #100600; }
    .no-booking p { font-size: 0.8rem; color: #aaa; line-height: 1.6; }

    .toast {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      background: #100600;
      color: white;
      padding: 10px 20px;
      border-radius: 40px;
      font-size: 0.8rem;
      z-index: 3000;
      opacity: 0;
      transition: opacity 0.3s;
      pointer-events: none;
      white-space: nowrap;
    }
    .toast.show { opacity: 1; }

    /* Topbar adjustments */
    .guide-topbar {
      flex-shrink: 0;
    }

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

/* Type-specific colors */
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

/* Waypoint popup stays the same */
.waypoint-popup {
    min-width: 180px;
    max-width: 260px;
}

.waypoint-popup strong {
    font-size: 0.9rem;
    color: #100600;
    display: block;
    margin-bottom: 6px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
    padding-bottom: 4px;
}

.popup-detail {
    font-size: 0.72rem;
    color: #555;
    padding-top: 4px;
    line-height: 1.5;
}

.popup-desc {
    font-size: 0.68rem;
    color: #777;
    font-style: italic;
    display: inline-block;
    margin-top: 4px;
}

.popup-coords {
    font-size: 0.6rem;
    color: #999;
    margin-top: 8px;
    padding-top: 5px;
    border-top: 1px solid rgba(0,0,0,0.05);
    font-family: monospace;
}
/* Add to your existing waypoint styles */
.information-marker i { color: #3498DB; }
.peak-marker i { color: #2ECC71; }
.mountain_pass-marker i { color: #9B59B6; }
.tree-marker i { color: #27AE60; }
.start-marker i { color: #1ABC9C; }
.rest-marker i { color: #F39C12; }

    @media (max-width: 768px) {
      .map-layout { flex-direction: column; }
      .map-side-panel { width: 100%; height: 220px; border-left: none; border-top: 1px solid rgba(0,0,0,0.05); }
      .hiker-track-list { flex-direction: row; flex-wrap: nowrap; overflow-x: auto; padding: 8px 10px; gap: 8px; }
      .track-item { min-width: 160px; }
      .info-tab-content { flex-direction: row; flex-wrap: nowrap; overflow-x: auto; }
      .info-stat-row { display: flex; flex-wrap: nowrap; }
    }
  </style>
</head>
<body>
<div class="guide-app">

  <!-- ── SIDEBAR ── -->
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="white" opacity="0.9"/><path d="M14 16L18 8L24 22H14V16Z" fill="white" opacity="0.3"/></svg>
      <div><div class="sidebar-logo-text">LAKBAY</div><div class="sidebar-logo-sub">Guide Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.php"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.php" class="active"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.php"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.php"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar"><?= htmlspecialchars($guideInitials) ?></div>
      <div class="sidebar-profile-info">
        <div class="sidebar-profile-name"><?= htmlspecialchars($guideName) ?></div>
        <div class="sidebar-profile-role">Trail Guide</div>
      </div>
    </div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">
        Trail Management
      </div>
      <div class="topbar-right">
        <button class="topbar-icon-btn" title="Refresh"><i class="fas fa-rotate"></i></button>
      </div>
    </div>

    <!-- View Tabs (inside guide-main, below topbar) -->
    <div class="view-tabs">
      <button class="view-tab active" onclick="switchView('map')">
        <i class="fas fa-map"></i> Map View
      </button>
      <button class="view-tab" onclick="switchView('safety')">
        <i class="fas fa-shield-alt"></i> Safety Page
      </button>
    </div>

    <!-- MAP VIEW -->
    <div class="map-view" id="mapView">
      <div class="guide-content">
        <div class="map-layout">

          <!-- MAP CANVAS -->
          <div class="map-canvas" id="mapCanvas">
            <div id="leaflet-map"></div>

            <?php if (!$hike): ?>
            <div class="no-booking">
              <div class="no-booking-icon">🗺️</div>
              <h3>No Active Booking</h3>
              <p>You don't have an active or confirmed booking for today.<br>Go to your dashboard to manage your bookings.</p>
              <a href="guide-dashboard.php" style="padding:10px 22px;background:#100600;color:white;border-radius:30px;text-decoration:none;font-size:0.82rem;font-weight:700;margin-top:8px;">Go to Dashboard</a>
            </div>
            <?php endif; ?>

            <!-- Status Bar -->
            <?php if ($hike): ?>
            <div class="map-status-bar">
              <div class="live-dot"></div>
              <div>
                <div class="map-status-text">⛰ <?= htmlspecialchars($hike['mountain_name']) ?> — Active Trek</div>
                <div class="map-status-sub" id="mapStatusSub"><?= count($bookingHikers) ?> hiker<?= count($bookingHikers) != 1 ? 's' : '' ?> · <span id="mapTimeSpan"></span></div>
              </div>
              <div class="crowd-indicator">
                <i class="fas fa-users"></i>
                <span id="crowdBadge" class="crowd-badge <?= htmlspecialchars($currentCrowdLevel ?? 'Low') ?>"><?= htmlspecialchars($currentCrowdLevel ?? 'Low') ?> Crowd</span>
              </div>
            </div>
            <?php endif; ?>

            <!-- Crowd Report Button -->
            <button class="crowd-report-btn" onclick="openCrowdModal()">
              <i class="fas fa-chart-line"></i> Report Crowd Level
            </button>

            <!-- FAB Controls -->
            <div class="map-fabs">
              <button class="map-fab" onclick="centerMap()" title="My Location"><i class="fas fa-location-crosshairs"></i></button>
              <button class="map-fab" id="layerBtn" onclick="cycleLayer()" title="Map Style"><i class="fas fa-layer-group"></i></button>
              <button class="map-fab" id="heatBtn" onclick="toggleHeatmap()" title="Heatmap"><i class="fas fa-fire"></i></button>
              <button class="map-fab" id="trailBtn" onclick="toggleTrailMarkers()" title="Trail Markers"><i class="fas fa-signs-post"></i></button>
              <button class="map-fab" id="waypointBtn" onclick="toggleWaypoints()" title="Toggle Waypoints">
    <i class="fas fa-location-dot"></i>
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
        <i class="fas fa-hand-pointer"></i> Click trail to report
    </div>
</div>
            
          </div>
         

          <!-- SIDE PANEL -->
          <div class="map-side-panel">
            <div class="side-panel-header">
              <h3><i class="fas fa-person-hiking" style="margin-right:6px;font-size:0.8rem;"></i> Your Group</h3>
              <p id="panelLastUpdate">Updating…</p>
            </div>

            <div class="side-tabs">
              <button class="side-tab active" onclick="showTab('hikers')">
                <i class="fas fa-users" style="margin-right:4px;font-size:0.65rem;"></i>Hikers (<?= count($bookingHikers) ?>)
              </button>
              <button class="side-tab" onclick="showTab('info')">
                <i class="fas fa-chart-line" style="margin-right:4px;font-size:0.65rem;"></i>Hike Info
              </button>
            </div>

            <!-- Hikers Tab -->
            <div class="hikers-tab-content" id="hikersTab">
              <div class="hiker-track-list" id="trackList">
                <?php if (empty($bookingHikers)): ?>
                <div style="padding:20px;text-align:center;color:#bbb;font-size:0.78rem;">No hikers found for this booking.</div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Info Tab -->
            <div class="info-tab-content hidden" id="infoTab">
              <?php if ($hike): ?>
              <div class="info-stat-row">
                <div class="info-stat-box">
                  <div class="info-stat-label">Trail Length</div>
                  <div class="info-stat-val"><?= $hike['trail_length_km'] ?? '?' ?><span class="info-stat-unit"> km</span></div>
                </div>
                <div class="info-stat-box">
                  <div class="info-stat-label">Duration</div>
                  <div class="info-stat-val"><?= $hike['estimated_duration'] ?? '?' ?><span class="info-stat-unit"> hrs</span></div>
                </div>
              </div>
              <div class="info-stat-row">
                <div class="info-stat-box">
                  <div class="info-stat-label">Hikers</div>
                  <div class="info-stat-val"><?= $hike['number_of_hikers'] ?? count($bookingHikers) ?></div>
                </div>
                <div class="info-stat-box">
                  <div class="info-stat-label">Difficulty</div>
                  <div class="info-stat-val" style="font-size:0.8rem;text-transform:capitalize;"><?= htmlspecialchars($hike['difficulty'] ?? 'N/A') ?></div>
                </div>
              </div>
              <div>
                <div class="info-section-title">Booking</div>
                <div class="info-stat-box" style="padding:10px 12px;">
                  <div style="font-size:0.72rem;color:#666;line-height:1.6;">
                    <div><strong>Mountain:</strong> <?= htmlspecialchars($hike['mountain_name']) ?></div>
                    <div><strong>Date:</strong> <?= htmlspecialchars($hike['hike_date']) ?></div>
                    <div><strong>Type:</strong> <?= htmlspecialchars(str_replace('_',' ', $hike['hike_type'] ?? 'Day Hike')) ?></div>
                    <div><strong>Status:</strong> <span style="text-transform:capitalize;color:#1B7045;"><?= htmlspecialchars($hike['status']) ?></span></div>
                  </div>
                </div>
              </div>
              <div>
                <div class="info-section-title">Your Guide Info</div>
                <div class="info-stat-box" style="padding:10px 12px;">
                  <div style="font-size:0.72rem;color:#666;">
                    <div><strong>Guide:</strong> <?= htmlspecialchars($guideName) ?></div>
                    <div><strong>Timer:</strong> <span id="guideTimer" style="font-family:monospace;color:#100600;">0:00:00</span></div>
                  </div>
                </div>
              </div>
              <?php else: ?>
              <div style="padding:20px;text-align:center;color:#bbb;font-size:0.78rem;">No active booking loaded.</div>
              <?php endif; ?>
            </div>

            <!-- Legend -->
            <div class="legend">
              <div class="legend-title">Legend</div>
              <div class="legend-item"><div class="legend-dot" style="background:#1B7045;"></div> Active / On trail</div>
              <div class="legend-item"><div class="legend-dot" style="background:#C97B1A;"></div> Lagging / No recent update</div>
              <div class="legend-item"><div class="legend-dot" style="background:#ccc;"></div> Tracking off</div>
              <div class="legend-item"><div class="legend-dot" style="background:#100600;border:2px solid white;width:12px;height:12px;"></div> Guide (you)</div>
            </div>

            <!-- Finish Hike -->
            <?php if ($hike): ?>
            <div class="finish-section">
              <button class="btn-finish" id="finishBtn" onclick="finishHike()">
                <i class="fas fa-flag-checkered"></i> Finish Hike
              </button>
            </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>

    <!-- SAFETY VIEW (embedded via iframe) -->
    <div class="safety-view hidden" id="safetyView">
      <iframe src="guide-safety.php?embed=1" class="safety-iframe"></iframe>
    </div>
  </div>

  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.php" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.php" class="bnav-item active"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.php" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-safety.php" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
      <a href="guide-profile.php" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<!-- Crowd Level Modal -->
<div class="crowd-modal" id="crowdModal">
  <div class="crowd-modal-content">
    <h3><i class="fas fa-chart-line"></i> Report Crowd Level</h3>
    <div class="crowd-level-options" id="crowdOptions">
      <div class="crowd-option" data-level="Low" onclick="selectCrowdLevel('Low')">
        <i class="fas fa-smile"></i> Low - Not crowded
      </div>
      <div class="crowd-option" data-level="Medium" onclick="selectCrowdLevel('Medium')">
        <i class="fas fa-meh"></i> Medium - Moderate traffic
      </div>
      <div class="crowd-option" data-level="High" onclick="selectCrowdLevel('High')">
        <i class="fas fa-frown"></i> High - Very crowded
      </div>
    </div>
    <div class="crowd-modal-buttons">
      <button class="btn-cancel" onclick="closeCrowdModal()">Cancel</button>
      <button class="btn-submit" onclick="submitCrowdLevel()">Submit Report</button>
    </div>
  </div>
</div>

<!-- COMPLETION OVERLAY -->
<div class="completion-overlay" id="completionOverlay">
  <div class="completion-card">
    <div class="comp-emoji">🏔️</div>
    <div class="comp-title">Hike Finished!</div>
    <div class="comp-sub" id="compSub">Great work leading today's group</div>
    <div class="comp-stats">
      <div class="comp-stat">
        <div class="comp-stat-val" id="compDist">—</div>
        <div class="comp-stat-label">km covered</div>
      </div>
      <div class="comp-stat">
        <div class="comp-stat-val" id="compTime">—</div>
        <div class="comp-stat-label">duration</div>
      </div>
    </div>
    <div class="comp-actions">
      <button class="comp-btn-sec" onclick="document.getElementById('completionOverlay').classList.remove('open')">Close</button>
      <a href="guide-dashboard.php" class="comp-btn-back">Back to Dashboard</a>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- ── SCRIPTS ── -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
// ── PHP DATA → JS ──────────────────────────────────────
const HIKE_DATA = <?= json_encode($hike) ?>;
const TRAIL_DATA = <?= json_encode($trailData) ?>;
const BOOKING_HIKERS = <?= json_encode($bookingHikers) ?>;
const GUIDE_NAME = <?= json_encode($guideName) ?>;
const SESSION_TOKEN = <?= json_encode($sessionToken) ?>;
const BOOKING_ID = <?= json_encode($hike['id'] ?? null) ?>;
const GUIDE_USER_ID = <?= json_encode($currentUserId) ?>;
const START_LAT = <?= $hike['start_point_lat'] ?? 14.1147 ?>;
const START_LNG = <?= $hike['start_point_lng'] ?? 120.8892 ?>;
const MOUNTAIN_ID = <?= json_encode($hike['mountain_id'] ?? null) ?>;
const MOUNTAIN_NAME = <?= json_encode($hike['mountain_name'] ?? 'Unknown') ?>;
const WAYPOINTS = <?= json_encode($waypoints) ?>;

// ── STATE ───────────────────────────────────────────────
let map, guideMarker, trailLayer, traveledLayer, heatmapLayer;
let distMarkers = [], wpMarkers = [], hikerLeafletMarkers = {};
let watchId = null, guideIntervalId = null, hikerPollId = null;
let guidePosition = null;
let trailCoords = [];
let totalDistGuide = 0, lastGuidePos = null;
let startTime = Date.now();
let trailMarkersVisible = true, heatmapOn = false;
let hikeFinished = false;
let selectedHikerIdx = -1;
let layerIdx = 0;
let selectedCrowdLevel = null;
let waypointsVisible = true; // Add near your other state variables


const LAYERS = [
  { url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', label: '🗺 Voyager' },
  { url: 'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', label: '⬜ Light' },
  { url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', label: '🛰 Satellite' },
];

// Tab switching
function switchView(view) {
  const mapView = document.getElementById('mapView');
  const safetyView = document.getElementById('safetyView');
  const tabs = document.querySelectorAll('.view-tab');
  
  if (view === 'map') {
    mapView.classList.remove('hidden');
    safetyView.classList.add('hidden');
    tabs[0].classList.add('active');
    tabs[1].classList.remove('active');
    // Refresh map if needed
    if (map && typeof map.invalidateSize === 'function') {
      setTimeout(() => map.invalidateSize(), 100);
    }
  } else {
    mapView.classList.add('hidden');
    safetyView.classList.remove('hidden');
    tabs[0].classList.remove('active');
    tabs[1].classList.add('active');
    // Reload safety iframe if needed
    const iframe = safetyView.querySelector('iframe');
    if (iframe && iframe.src) {
      iframe.src = iframe.src;
    }
  }
}

// Crowd modal functions
function openCrowdModal() {
  document.getElementById('crowdModal').classList.add('open');
  selectedCrowdLevel = null;
  document.querySelectorAll('.crowd-option').forEach(opt => {
    opt.classList.remove('selected');
  });
}

function closeCrowdModal() {
  document.getElementById('crowdModal').classList.remove('open');
}

function selectCrowdLevel(level) {
  selectedCrowdLevel = level;
  document.querySelectorAll('.crowd-option').forEach(opt => {
    opt.classList.toggle('selected', opt.dataset.level === level);
  });
}


function updateHeatmapIntensity(level) {
  if (!heatmapLayer || !heatmapOn) return;
  
  // Adjust heatmap intensity based on crowd level
  let intensity = 0.5;
  switch(level) {
    case 'Low': intensity = 0.3; break;
    case 'Medium': intensity = 0.7; break;
    case 'High': intensity = 1.0; break;
  }
  
  // Rebuild heatmap with new intensity
  if (trailCoords.length) {
    const pts = trailCoords.map((coord, idx) => {
      let weight = intensity;
      if (idx > trailCoords.length * 0.6) weight = intensity * 1.2;
      else if (idx < trailCoords.length * 0.2) weight = intensity * 0.7;
      return [coord[0], coord[1], Math.min(weight, 1.0)];
    });
    
    if (heatmapOn && heatmapLayer) {
      map.removeLayer(heatmapLayer);
    }
    heatmapLayer = L.heatLayer(pts, { 
      radius: 30, 
      blur: 18, 
      maxZoom: 18,
      gradient: { 0.2: '#3B82F6', 0.5: '#10B981', 0.75: '#F59E0B', 1.0: '#EF4444' }
    });
    if (heatmapOn) {
      heatmapLayer.addTo(map);
    }
  }
}

// ── MAP INIT ────────────────────────────────────────────
function initMap() {
  if (!HIKE_DATA) return;

  map = L.map('leaflet-map', { zoomControl: true }).setView([START_LAT, START_LNG], 14);
  L.tileLayer(LAYERS[0].url, { attribution: '© OSM © CartoDB', subdomains: 'abcd', maxZoom: 19 }).addTo(map);

  drawTrail();
  placeWaypointMarkersFromDB();
  placeStartMarker();
  buildHeatmap();

  if (trailCoords.length) {
    map.fitBounds(L.latLngBounds(trailCoords).pad(0.1));
  }

  
  // Place stored hiker positions
  renderHikerListAndMarkers(BOOKING_HIKERS);

  // Start GPS for guide
  startGuideGPS();

  // Poll hikers every 15s
  hikerPollId = setInterval(fetchHikers, 15000);

  // Timer
  setInterval(tickTimer, 1000);
  updateMapTime();
  setInterval(updateMapTime, 1000);

  // Enable reporting crowds by clicking on map
  enableMapClickReporting();
    enableMapClickReporting();
  
  // Show initial instruction if heatmap is on by default
  if (heatmapOn) {
    setTimeout(() => {
      showToast('💡 Tip: Click on the trail to report crowd levels');
    }, 2000);
  }
}


// ── TIMER ───────────────────────────────────────────────
function tickTimer() {
  const d = Date.now() - startTime;
  const h = Math.floor(d / 3600000);
  const m = Math.floor((d % 3600000) / 60000);
  const s = Math.floor((d % 60000) / 1000);
  const el = document.getElementById('guideTimer');
  if (el) el.textContent = `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

function updateMapTime() {
  const t = new Date().toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  const el = document.getElementById('mapTimeSpan');
  const pl = document.getElementById('panelLastUpdate');
  if (el) el.textContent = t;
  if (pl) pl.textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
}

// ── TRAIL ───────────────────────────────────────────────
function drawTrail() {
  let lls = [];
  if (TRAIL_DATA && TRAIL_DATA.type === 'LineString') {
    lls = TRAIL_DATA.coordinates.map(c => [c[1], c[0]]);
  }
  if (!lls.length) return;

  trailCoords = lls;

  trailLayer = L.polyline(lls, {
    color: 'rgba(100, 70, 45, 0.45)',
    weight: 5, opacity: 1, lineCap: 'round', lineJoin: 'round',
    dashArray: '12, 8'
  }).addTo(map);

  traveledLayer = L.polyline([], {
    color: '#100600', weight: 5.5, opacity: 0.85,
    lineCap: 'round', lineJoin: 'round'
  }).addTo(map);

  addDistBadges(lls);
}

function addDistBadges(coords) {
  let acc = 0, last = coords[0], next = 0.5;
  for (let i = 1; i < coords.length; i++) {
    acc += calcDist(last[0], last[1], coords[i][0], coords[i][1]);
    if (acc >= next) {
      const icon = L.divIcon({
        html: `<div style="background:rgba(255,255,255,0.88);color:#100600;padding:2px 8px;border-radius:20px;font-size:0.58rem;font-weight:700;font-family:monospace;box-shadow:0 1px 6px rgba(0,0,0,0.12);white-space:nowrap;">${next.toFixed(1)} km</div>`,
        className: '', iconSize: [55, 18]
      });
      distMarkers.push(L.marker(coords[i], { icon, interactive: false }).addTo(map));
      next += 0.5;
    }
    last = coords[i];
  }
}

function placeStartMarker() {
  const icon = L.divIcon({
    html: `<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">
      <div style="width:44px;height:44px;border-radius:50%;background:#100600;display:flex;align-items:center;justify-content:center;font-size:20px;border:3px solid white;box-shadow:0 2px 14px rgba(16,6,0,0.35);">🏁</div>
      <div style="background:#100600;color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;font-weight:800;letter-spacing:0.5px;white-space:nowrap;">TRAILHEAD</div>
    </div>`,
    className: '', iconSize: [60, 64], iconAnchor: [30, 22], popupAnchor: [0, -30]
  });
  L.marker([START_LAT, START_LNG], { icon })
    .bindPopup(`<div class="popup-title">Trailhead — ${HIKE_DATA.mountain_name}</div><div class="popup-body">Starting point. Stay on marked trail.</div>`)
    .addTo(map);
}

function placeWaypointMarkers() {
  if (!trailCoords.length) return;
  const fractions = [0, 0.25, 0.5, 0.75, 1.0];
  const labels = ['Start', 'Checkpoint 1', 'Mid Trail', 'Near Summit', 'Summit'];
  const types = ['start', '', '', '', 'summit'];
  fractions.forEach((f, i) => {
    const idx = Math.min(Math.floor(f * trailCoords.length), trailCoords.length - 1);
    const [lat, lng] = trailCoords[idx];
    const dotClass = types[i];
    const icon = L.divIcon({
      html: `<div class="wp-marker-wrap">
        <div class="wp-dot ${dotClass}"></div>
        <div class="wp-label-tag">${labels[i]}</div>
      </div>`,
      className: '', iconSize: [70, 34], iconAnchor: [35, 7], popupAnchor: [0, -10]
    });
    L.marker([lat, lng], { icon })
      .bindPopup(`<div class="popup-title">${labels[i]}</div><div class="popup-body">Trail checkpoint</div>`)
      .addTo(map);
  });
}
// ── HEATMAP ──────────────────────────────────────────────
let heatmapPoints = [];
let heatmapAutoRefresh = null;

async function buildHeatmap() {
    if (!trailCoords.length || !MOUNTAIN_ID) return;
    
    // Ensure legend visibility matches heatmap state
    const legend = document.getElementById('heatmapLegend');
    if (legend) {
        if (heatmapOn) legend.classList.add('visible');
        else legend.classList.remove('visible');
    }
    
    try {
        const res = await fetch(`../api/detect_crowd_hotspots.php?mountain_id=${MOUNTAIN_ID}`);
        const data = await res.json();
        
        console.log('Heatmap data received:', data); // Debug log
        
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
            
            if (heatmapOn) {
                heatmapLayer.addTo(map);
                showToast(`🔥 Heatmap showing ${data.points.length} crowded area(s)`);
            }
            
            // Update crowd badge
            const highPoints = data.points.filter(p => p.intensity >= 0.7);
            const medPoints = data.points.filter(p => p.intensity >= 0.4 && p.intensity < 0.7);
            
            let overallLevel = 'Low';
            let crowdMessage = '';
            
            if (highPoints.length > 0) {
                overallLevel = 'High';
                crowdMessage = `${highPoints.length} high-traffic area(s)`;
            } else if (medPoints.length > 0) {
                overallLevel = 'Medium';
                crowdMessage = `${medPoints.length} moderate area(s)`;
            } else {
                crowdMessage = 'Trail is quiet';
            }
            
            const badge = document.getElementById('crowdBadge');
            if (badge) {
                badge.className = `crowd-badge ${overallLevel}`;
                badge.textContent = `${overallLevel} Crowd`;
                badge.title = crowdMessage;
            }
        } else {
            if (heatmapLayer && heatmapOn) {
                map.removeLayer(heatmapLayer);
            }
            heatmapPoints = [];
            if (heatmapOn) {
                showToast('No crowd reports available');
            }
        }
    } catch (e) {
        console.error('Heatmap fetch error:', e);
        showToast('Error loading crowd data');
    }
}

// Updated crowd report submission with location
async function submitCrowdLevel() {
    if (!selectedCrowdLevel) {
        showToast('Please select a crowd level');
        return;
    }
    if (!MOUNTAIN_ID) {
        showToast('No mountain selected');
        closeCrowdModal();
        return;
    }

    // Use guide's current position or clicked location
    let reportLat = guidePosition?.lat || START_LAT;
    let reportLng = guidePosition?.lng || START_LNG;
    
    // If guide clicked on map (optional enhancement), use that
    if (window._clickedLocation) {
        reportLat = window._clickedLocation.lat;
        reportLng = window._clickedLocation.lng;
        delete window._clickedLocation;
    }

    try {
        const res = await fetch('../api/update_crowd_level.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mountain_id: MOUNTAIN_ID,
                crowd_level: selectedCrowdLevel,
                location_lat: reportLat,
                location_lng: reportLng,
                segment_note: `Reported by ${GUIDE_NAME}`
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`✅ ${selectedCrowdLevel} crowd reported at ${data.segment || 'your location'}`);
            
            // Refresh heatmap
            setTimeout(() => buildHeatmap(), 500);
            
            closeCrowdModal();
        } else {
            showToast('Error: ' + (data.message || 'Could not update crowd level'));
        }
    } catch (e) {
        showToast('Network error');
    }
}

// Auto-refresh heatmap every 2 minutes
function startHeatmapAutoRefresh() {
    if (heatmapAutoRefresh) clearInterval(heatmapAutoRefresh);
    heatmapAutoRefresh = setInterval(() => {
        if (heatmapOn) {
            buildHeatmap();
        }
    }, 120000); // 2 minutes
}

// Modified toggleHeatmap function
function toggleHeatmap() {
    heatmapOn = !heatmapOn;
    const btn = document.getElementById('heatBtn');
    const legend = document.getElementById('heatmapLegend');
    
    if (heatmapOn) {
        if (heatmapPoints.length) {
            if (heatmapLayer) heatmapLayer.addTo(map);
        } else {
            buildHeatmap().then(() => {
                if (heatmapLayer) heatmapLayer.addTo(map);
            });
        }
        btn.classList.add('active');
        startHeatmapAutoRefresh();
        
        // Show legend
        if (legend) legend.classList.add('visible');
        
        // Show instructional message
        showToast('🔥 Heatmap enabled - Click anywhere on the trail to report crowd levels');
        
        // Change cursor to indicate clickable area
        map.getContainer().style.cursor = 'crosshair';
    } else {
        if (heatmapLayer) map.removeLayer(heatmapLayer);
        btn.classList.remove('active');
        if (heatmapAutoRefresh) clearInterval(heatmapAutoRefresh);
        showToast('Heatmap off');
        
        // Hide legend
        if (legend) legend.classList.remove('visible');
        
        // Reset cursor
        map.getContainer().style.cursor = '';
    }
}
function toggleTrailMarkers() {
  trailMarkersVisible = !trailMarkersVisible;
  distMarkers.forEach(m => trailMarkersVisible ? m.addTo(map) : map.removeLayer(m));
  document.getElementById('trailBtn').classList.toggle('active', !trailMarkersVisible);
  showToast(trailMarkersVisible ? 'Distance markers shown' : 'Distance markers hidden');
}

function cycleLayer() {
  layerIdx = (layerIdx + 1) % LAYERS.length;
  map.eachLayer(l => { if (l instanceof L.TileLayer) map.removeLayer(l); });
  L.tileLayer(LAYERS[layerIdx].url, { attribution: '© OSM', maxZoom: 19 }).addTo(map);
  [trailLayer, traveledLayer].forEach(l => l && l.addTo(map));
  showToast(LAYERS[layerIdx].label);
}

function centerMap() {
  if (guidePosition) {
    map.setView([guidePosition.lat, guidePosition.lng], 16);
  } else if (trailCoords.length) {
    map.fitBounds(L.latLngBounds(trailCoords).pad(0.1));
  } else {
    map.setView([START_LAT, START_LNG], 14);
  }
}

// ── GUIDE GPS ───────────────────────────────────────────
function startGuideGPS() {
  if (!navigator.geolocation) {
    showToast('Geolocation not available');
    return;
  }
  watchId = navigator.geolocation.watchPosition(onGuideLocation, onGuideLocationError, {
    enableHighAccuracy: true, timeout: 12000, maximumAge: 0
  });
  guideIntervalId = setInterval(reportGuideLocation, 10000);
  showToast('📍 GPS active — your location is tracked');
}

function onGuideLocation(pos) {
  const { latitude: lat, longitude: lng } = pos.coords;
  guidePosition = { lat, lng };

  if (!guideMarker) {
    const icon = L.divIcon({
      html: `<div class="guide-pin">
        <div class="guide-pin-label">YOU (GUIDE)</div>
        <div class="guide-pin-dot"></div>
      </div>`,
      className: '', iconSize: [90, 40], iconAnchor: [45, 32]
    });
    guideMarker = L.marker([lat, lng], { icon, zIndexOffset: 1000 })
      .bindPopup(`<div class="popup-title">Your Location</div><div class="popup-body">${GUIDE_NAME} · Trail Guide</div>`)
      .addTo(map);
  } else {
    guideMarker.setLatLng([lat, lng]);
  }

  if (trailCoords.length) {
    let closest = 0, minD = Infinity;
    for (let i = 0; i < trailCoords.length; i++) {
      const d = calcDist(lat, lng, trailCoords[i][0], trailCoords[i][1]);
      if (d < minD) { minD = d; closest = i; }
    }
    if (minD < 0.08) {
      traveledLayer.setLatLngs(trailCoords.slice(0, closest + 1));
    }
  }

  if (lastGuidePos) {
    totalDistGuide += calcDist(lastGuidePos.lat, lastGuidePos.lng, lat, lng);
  }
  lastGuidePos = { lat, lng };
}

function onGuideLocationError(err) {
  console.warn('Guide GPS error:', err.message);
}

function reportGuideLocation() {
  if (!guidePosition || !BOOKING_ID || !SESSION_TOKEN) return;
  fetch('../api/update_guide_location.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      session_token: SESSION_TOKEN,
      booking_id: BOOKING_ID,
      latitude: guidePosition.lat,
      longitude: guidePosition.lng
    })
  }).catch(() => {});
}

// ── FETCH HIKERS ─────────────────────────────────────────
async function fetchHikers() {
  if (!BOOKING_ID) return;
  try {
    const res = await fetch(`../api/get_guide_hikers.php?booking_id=${BOOKING_ID}`);
    const data = await res.json();
    if (data.success && data.hikers) {
      renderHikerListAndMarkers(data.hikers);
    }
  } catch (e) {}
}

// ── RENDER HIKERS ─────────────────────────────────────────
function renderHikerListAndMarkers(hikers) {
  const list = document.getElementById('trackList');
  if (!list) return;
  list.innerHTML = '';

  if (!hikers || !hikers.length) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#bbb;font-size:0.78rem;">No hikers in this booking.</div>';
    return;
  }

  hikers.forEach((h, i) => {
    const initials = (h.name || 'H').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
    const minutesAgo = h.minutes_ago != null ? parseInt(h.minutes_ago) : null;
    const isActive = h.is_active || (minutesAgo !== null && minutesAgo <= 10);
    const trackingOn = h.tracking_enabled !== false && h.location_tracking_enabled != 0;
    
    let statusClass = 'ts-off';
    let dotColor = '#ccc';
    let statusText = 'Tracking off';
    let borderClass = 'status-off';

    if (trackingOn && isActive) {
      statusClass = 'ts-safe';
      dotColor = '#1B7045';
      statusText = 'Active · ' + (minutesAgo === 0 ? 'Just now' : `${minutesAgo}m ago`);
      borderClass = 'status-safe';
    } else if (trackingOn && minutesAgo !== null) {
      statusClass = 'ts-warn';
      dotColor = '#C97B1A';
      statusText = `No update for ${minutesAgo}m`;
      borderClass = 'status-warn';
    }

    let locationText = 'Location unknown';
    if (h.latitude && h.longitude) {
      locationText = `${parseFloat(h.latitude).toFixed(5)}, ${parseFloat(h.longitude).toFixed(5)}`;
    }

    const item = document.createElement('div');
    item.className = 'track-item' + (i === selectedHikerIdx ? ' selected' : '');
    item.innerHTML = `
      <div class="track-avatar">${initials}</div>
      <div class="track-info">
        <div class="track-name">${escapeHtml(h.name || 'Hiker')}</div>
        <div class="track-location"><i class="fas fa-location-dot" style="font-size:0.55rem;margin-right:3px;"></i>${locationText}</div>
        <div class="track-location" style="margin-top:2px;color:${dotColor};"><i class="fas fa-circle" style="font-size:0.45rem;margin-right:3px;"></i>${statusText}</div>
      </div>
      <div class="track-status-dot ${statusClass}"></div>
    `;
    item.addEventListener('click', () => {
      selectedHikerIdx = i;
      renderHikerListAndMarkers(hikers);
      if (h.latitude && h.longitude) {
        map.setView([parseFloat(h.latitude), parseFloat(h.longitude)], 17);
        const mkr = hikerLeafletMarkers[h.user_id || h.name];
        if (mkr) mkr.openPopup();
      }
      showToast(`📍 ${h.name} — ${statusText}`);
    });
    list.appendChild(item);

    if (h.latitude && h.longitude) {
      const lat = parseFloat(h.latitude);
      const lng = parseFloat(h.longitude);
      const markerId = h.user_id || h.name;

      const hikerIcon = L.divIcon({
        html: `<div class="hiker-pin">
          <div class="hiker-pin-avatar ${borderClass}">${initials}</div>
          <div class="hiker-pin-name">${escapeHtml((h.name || 'H').split(' ')[0])}</div>
        </div>`,
        className: '', iconSize: [50, 52], iconAnchor: [25, 18]
      });

      const popupContent = `
        <div class="popup-title">${escapeHtml(h.name || 'Hiker')}</div>
        <div class="popup-body">
          ${isActive ? `Last seen: ${minutesAgo === 0 ? 'just now' : minutesAgo + ' min ago'}<br>` : ''}
          ${h.latitude ? `Coords: ${lat.toFixed(4)}, ${lng.toFixed(4)}` : ''}
        </div>
        <span class="popup-status-badge ${isActive ? 'psb-safe' : (trackingOn ? 'psb-warn' : 'psb-off')}">${statusText}</span>
      `;

      if (hikerLeafletMarkers[markerId]) {
        hikerLeafletMarkers[markerId].setLatLng([lat, lng]);
        hikerLeafletMarkers[markerId].setIcon(hikerIcon);
        hikerLeafletMarkers[markerId].setPopupContent(popupContent);
      } else {
        const m = L.marker([lat, lng], { icon: hikerIcon })
          .bindPopup(popupContent)
          .addTo(map);
        hikerLeafletMarkers[markerId] = m;
      }
    }
  });
}

// ── TABS ─────────────────────────────────────────────────
function showTab(tab) {
  document.querySelectorAll('.side-tab').forEach((t, i) => {
    t.classList.toggle('active', (tab === 'hikers' && i === 0) || (tab === 'info' && i === 1));
  });
  document.getElementById('hikersTab').classList.toggle('hidden', tab !== 'hikers');
  document.getElementById('infoTab').classList.toggle('active', tab === 'info');
}

// ── FINISH HIKE ───────────────────────────────────────────
async function finishHike() {
  if (hikeFinished) return;
  if (!confirm('End this hike session for all hikers?')) return;

  const btn = document.getElementById('finishBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finishing…';

  if (watchId) { navigator.geolocation.clearWatch(watchId); watchId = null; }
  if (guideIntervalId) { clearInterval(guideIntervalId); guideIntervalId = null; }
  if (hikerPollId) { clearInterval(hikerPollId); hikerPollId = null; }

  const durationSec = Math.floor((Date.now() - startTime) / 1000);

  try {
    const res = await fetch('../api/finish_hike.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id: BOOKING_ID,
        session_token: SESSION_TOKEN,
        distance: totalDistGuide,
        duration: durationSec,
        badges: []
      })
    });
    const data = await res.json();

    if (data.success) {
      hikeFinished = true;
      const s = data.summary;
      document.getElementById('compSub').textContent = `${s.mountain} — ${s.hike_date}`;
      document.getElementById('compDist').textContent = s.distance_km.toFixed(1);
      document.getElementById('compTime').textContent = s.duration;
      document.getElementById('completionOverlay').classList.add('open');
    } else {
      showToast('Error: ' + (data.message || 'Could not finish hike'));
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-flag-checkered"></i> Finish Hike';
    }
  } catch (e) {
    showToast('Network error — please try again');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-flag-checkered"></i> Finish Hike';
  }
}

// ── UTILS ─────────────────────────────────────────────────
function calcDist(lat1, lon1, lat2, lon2) {
  const R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let toastTimer;
function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

// Add style for WP markers
const style = document.createElement('style');
style.textContent = `
  .wp-marker-wrap { display: flex; flex-direction: column; align-items: center; gap: 3px; }
  .wp-dot { width: 14px; height: 14px; border-radius: 50%; border: 2.5px solid white; background: #C97B1A; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
  .wp-dot.summit { background: #1B7045; width: 18px; height: 18px; }
  .wp-dot.start { background: #100600; width: 18px; height: 18px; }
  .wp-label-tag { background: rgba(255,255,255,0.9); color: #100600; padding: 2px 8px; border-radius: 20px; font-size: 0.58rem; font-weight: 600; white-space: nowrap; box-shadow: 0 1px 4px rgba(0,0,0,0.12); }
  .popup-status-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; }
  .psb-safe { background: rgba(27,112,69,0.12); color: #1B7045; }
  .psb-warn { background: rgba(201,123,26,0.12); color: #C97B1A; }
  .psb-off { background: rgba(0,0,0,0.06); color: #888; }
  .topbar-icon-btn { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 6px 10px; border-radius: 8px; color: #666; }
  .topbar-icon-btn:hover { background: #f0ede8; }
  .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
`;
document.head.appendChild(style);

window.addEventListener('beforeunload', () => {
  if (watchId) navigator.geolocation.clearWatch(watchId);
  if (guideIntervalId) clearInterval(guideIntervalId);
  if (hikerPollId) clearInterval(hikerPollId);
});

document.addEventListener('DOMContentLoaded', () => {
  if (HIKE_DATA) initMap();
});


function placeWaypointMarkersFromDB() {
    if (!WAYPOINTS || !WAYPOINTS.length) return;
    
    // Define icon styles based on type - NO LABELS, just icons
    const iconConfigs = {
        'summit': {
            html: `<div class="waypoint-marker summit-marker">
                        <i class="fas fa-mountain"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'campsite': {
            html: `<div class="waypoint-marker campsite-marker">
                        <i class="fas fa-campground"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'viewpoint': {
            html: `<div class="waypoint-marker viewpoint-marker">
                        <i class="fas fa-eye"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'information': {
            html: `<div class="waypoint-marker information-marker">
                        <i class="fas fa-info-circle"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'peak': {
            html: `<div class="waypoint-marker peak-marker">
                        <i class="fas fa-flag-checkered"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'mountain_pass': {
            html: `<div class="waypoint-marker mountain_pass-marker">
                        <i class="fas fa-road"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'tree': {
            html: `<div class="waypoint-marker tree-marker">
                        <i class="fas fa-tree"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'water_source': {
            html: `<div class="waypoint-marker water-marker">
                        <i class="fas fa-water"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'rest_area': {
            html: `<div class="waypoint-marker rest-marker">
                        <i class="fas fa-chair"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'danger': {
            html: `<div class="waypoint-marker danger-marker">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'start': {
            html: `<div class="waypoint-marker start-marker">
                        <i class="fas fa-flag"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        },
        'default': {
            html: `<div class="waypoint-marker default-marker">
                        <i class="fas fa-map-pin"></i>
                    </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        }
    };
    
    WAYPOINTS.forEach(wp => {
        const config = iconConfigs[wp.type] || iconConfigs.default;
        const lat = parseFloat(wp.latitude);
        const lng = parseFloat(wp.longitude);
        
        const icon = L.divIcon({
            html: config.html,
            className: 'custom-waypoint-icon',
            iconSize: config.iconSize,
            iconAnchor: config.iconAnchor,
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
        
        const popupContent = `
            <div class="waypoint-popup">
                <strong><i class="fas ${getIconForType(wp.type)}"></i> ${escapeHtml(wp.name)}</strong>
                <div class="popup-detail">
                    ${getDisplayType(wp.type)}${elevationText}
                    ${wp.description ? `<br><span class="popup-desc">📝 ${escapeHtml(wp.description)}</span>` : ''}
                </div>
                <div class="popup-coords">
                    📍 ${lat.toFixed(5)}, ${lng.toFixed(5)}
                    ${wp.elevation ? `<br>📊 Elevation: ${Math.round(wp.elevation)}m` : ''}
                </div>
            </div>
        `;
        
        const marker = L.marker([lat, lng], { icon })
            .bindPopup(popupContent)
            .addTo(map);
        
        wpMarkers.push(marker);
    });
    
    console.log(`✅ Loaded ${WAYPOINTS.length} waypoints for ${MOUNTAIN_NAME}`);
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

function getDisplayType(type) {
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
}

// Function to toggle waypoint visibility
function toggleWaypoints() {
    waypointsVisible = !waypointsVisible;
    wpMarkers.forEach(m => waypointsVisible ? m.addTo(map) : map.removeLayer(m));
    document.getElementById('waypointBtn')?.classList.toggle('active', !waypointsVisible);
    showToast(waypointsVisible ? 'Waypoints shown' : 'Waypoints hidden');
}

let selectedTrailPoint = null;
let trailClickMarker = null;

function enableMapClickReporting() {
    // Create a custom cursor for trail clicks
    map.getContainer().style.cursor = 'crosshair';
    
    // Add click handler
    map.on('click', function(e) {
        const clickedPoint = e.latlng;
        
        // Find the closest point on the trail
        let closestPoint = null;
        let minDistance = Infinity;
        
        trailCoords.forEach((coord, idx) => {
            const distance = map.distance(clickedPoint, L.latLng(coord[0], coord[1]));
            if (distance < minDistance && distance < 100) { // Within 100 meters of trail
                minDistance = distance;
                closestPoint = coord;
            }
        });
        
        if (closestPoint && minDistance < 100) {
            // Save clicked location
            window._clickedLocation = { 
                lat: closestPoint[0], 
                lng: closestPoint[1] 
            };
            
            // Show visual feedback - temporary marker on the trail
            if (trailClickMarker) {
                map.removeLayer(trailClickMarker);
            }
            
            // Create a pulsing circle to show where you clicked
            trailClickMarker = L.circleMarker([closestPoint[0], closestPoint[1]], {
                radius: 12,
                color: '#100600',
                weight: 3,
                opacity: 1,
                fillColor: '#F59E0B',
                fillOpacity: 0.6,
                className: 'pulse-marker'
            }).addTo(map);
            
            // Remove the visual marker after 2 seconds
            setTimeout(() => {
                if (trailClickMarker) {
                    map.removeLayer(trailClickMarker);
                    trailClickMarker = null;
                }
            }, 2000);
            
            // Open crowd modal
            openCrowdModal();
            showToast('📍 Click on trail - select crowd level for this area');
        } else {
            showToast('❌ Click closer to the trail to report crowd level');
        }
    });
}

// Add CSS for the pulse animation
const pulseStyle = document.createElement('style');
pulseStyle.textContent = `
    .pulse-marker {
        animation: pulse-ring 0.8s ease-out;
    }
    @keyframes pulse-ring {
        0% {
            transform: scale(0.8);
            opacity: 0.8;
        }
        100% {
            transform: scale(1.5);
            opacity: 0;
        }
    }
`;
document.head.appendChild(pulseStyle);

// Call this in initMap after map is created
// (Moved inside initMap function)
</script>
</body>
</html>