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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
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
      position: relative;
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
      position: relative;
    }

    /* ── MAP CANVAS ── */
    .map-canvas {
      flex: 1;
      position: relative;
      overflow: hidden;
      background: #1a1208;
      min-height: 0;
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

    /* ── SIDE PANEL (Bottom Sheet on Mobile) ── */
    .map-side-panel {
      width: 300px;
      background: rgba(255,255,255,0.98);
      border-left: 1px solid rgba(0,0,0,0.06);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      transition: none;
    }
    
    .side-panel-header {
      padding: 16px 18px;
      border-bottom: 1px solid rgba(0,0,0,0.05);
      background: rgba(248,247,245,0.95);
      flex-shrink: 0;
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
      flex-shrink: 0;
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
      flex-shrink: 0;
    }
    .legend-title { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #ccc; margin-bottom: 7px; }
    .legend-item { display: flex; align-items: center; gap: 9px; font-size: 0.68rem; color: #888; margin-bottom: 5px; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

    /* ── FINISH HIKE SECTION ── */
    .finish-section {
      padding: 12px 14px;
      border-top: 1px solid rgba(0,0,0,0.05);
      background: rgba(255,255,255,0.95);
      flex-shrink: 0;
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
    
    .information-marker i { color: #3498DB; }
    .peak-marker i { color: #2ECC71; }
    .mountain_pass-marker i { color: #9B59B6; }
    .tree-marker i { color: #27AE60; }
    .start-marker i { color: #1ABC9C; }
    .rest-marker i { color: #F39C12; }

    /* ── MOBILE RESPONSIVE ── */
    @media (max-width: 768px) {
      /* Map takes full height */
      .map-layout {
        flex-direction: column;
        position: relative;
        height: 100%;
      }

      /* Map fills the available space */
      .map-canvas {
        flex: 1;
        min-height: 0;
        height: auto;
      }

      /* Bottom sheet styles - FIXED */
      .map-side-panel {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: auto;
        min-height: 70px;
        max-height: 70vh;
        border-left: none;
        border-top: 1px solid rgba(0,0,0,0.12);
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -2px 20px rgba(0,0,0,0.15);
        z-index: 20;
        transition: height 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        overflow-y: auto;
        backdrop-filter: blur(20px);
        background: rgba(255,255,255,0.98);
      }

      /* Collapsed state */
      .map-side-panel.collapsed {
        min-height: 70px;
        max-height: 70px;
      }
      
      /* Expanded state */
      .map-side-panel.expanded {
        min-height: 50vh;
        max-height: 70vh;
      }

      /* Drag handle */
      .panel-drag-handle {
        display: flex !important;
        justify-content: center;
        align-items: center;
        padding: 12px 0 8px;
        cursor: grab;
        touch-action: none;
        flex-shrink: 0;
        background: rgba(255,255,255,0.95);
        border-radius: 20px 20px 0 0;
        position: sticky;
        top: 0;
        z-index: 21;
      }
      
      .panel-drag-handle:active {
        cursor: grabbing;
      }
      
      .panel-drag-handle::before {
        content: '';
        display: block;
        width: 40px;
        height: 4px;
        background: #ccc;
        border-radius: 2px;
        transition: background 0.2s;
      }
      
      .panel-drag-handle:hover::before {
        background: #999;
      }

      /* Scrollable content inside sheet */
      .side-panel-header,
      .side-tabs,
      .hikers-tab-content,
      .info-tab-content,
      .legend,
      .finish-section {
        transition: opacity 0.2s;
      }
      
      .collapsed .side-panel-header,
      .collapsed .side-tabs,
      .collapsed .hikers-tab-content,
      .collapsed .info-tab-content,
      .collapsed .legend,
      .collapsed .finish-section {
        display: none;
      }
      
      .expanded .side-panel-header,
      .expanded .side-tabs,
      .expanded .hikers-tab-content,
      .expanded .info-tab-content,
      .expanded .legend,
      .expanded .finish-section {
        display: flex;
      }
      
      .expanded .hikers-tab-content,
      .expanded .info-tab-content {
        display: flex;
        flex-direction: column;
      }

      /* Status bar adjustments */
      .map-status-bar {
        top: 12px;
        left: 12px;
        right: auto;
        max-width: calc(100% - 70px);
        font-size: 0.7rem;
        padding: 6px 12px;
        z-index: 15;
      }
      
      .map-status-text {
        font-size: 0.7rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
      }
      
      .crowd-indicator {
        display: flex;
        margin-left: 8px;
        padding-left: 8px;
      }
      
      .crowd-badge {
        font-size: 0.65rem;
        padding: 2px 6px;
      }

      /* FABs repositioned */
      .map-fabs {
        top: auto;
        bottom: 80px;
        right: 12px;
      }
      
      /* Toggle button for collapsed/expanded */
      .panel-toggle-fab {
        position: absolute;
        bottom: 85px;
        right: 12px;
        z-index: 15;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(10px);
        border-radius: 50%;
        box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s;
      }
      
      .panel-toggle-fab:active {
        transform: scale(0.95);
      }
      
      .panel-toggle-fab i {
        font-size: 1rem;
        color: #100600;
      }

      /* Heatmap legend adjust position */
      .heatmap-legend {
        bottom: 85px;
        right: 60px;
        padding: 8px 10px;
        min-width: 110px;
      }
      
      .heatmap-legend-title {
        font-size: 0.6rem;
        margin-bottom: 5px;
      }
      
      .heatmap-legend-item {
        font-size: 0.6rem;
        margin-bottom: 3px;
      }

      /* Legend inside sheet */
      .legend {
        display: block;
        border-top: 1px solid rgba(0,0,0,0.05);
        padding: 10px 14px;
      }
      
      .legend-title {
        font-size: 0.55rem;
        margin-bottom: 5px;
      }
      
      .legend-item {
        font-size: 0.6rem;
        margin-bottom: 3px;
      }
      
      .legend-dot {
        width: 8px;
        height: 8px;
      }

      /* Scroll indicators */
      .hiker-track-list::-webkit-scrollbar {
        width: 3px;
      }
      
      .hiker-track-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
      }
      
      .hiker-track-list::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
      }
    }

    /* Fix for very small screens */
    @media (max-width: 480px) {
      .map-status-text {
        font-size: 0.65rem;
        max-width: 120px;
      }
      
      .map-status-sub {
        font-size: 0.55rem;
      }
      
      .crowd-indicator {
        display: none;
      }
      
      .map-fab {
        width: 34px;
        height: 34px;
        font-size: 0.75rem;
      }
      
      .panel-toggle-fab {
        width: 36px;
        height: 36px;
        bottom: 75px;
      }
      
      .map-side-panel.expanded {
        max-height: 65vh;
      }
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
        <li><a href="guide-bookings.php"><i class="fas fa-shield-halved"></i> Bookings </a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
  <?php if (!empty($guideRecord['avatar'])): ?>
    <img src="../<?= htmlspecialchars($guideRecord['avatar']) ?>" class="sidebar-avatar" style="object-fit:cover;" alt="avatar">
  <?php else: ?>
    <div class="sidebar-avatar"><?= $guideInitials ?></div>
  <?php endif; ?>
  <div class="sidebar-profile-info">
    <div class="sidebar-profile-name"><?= htmlspecialchars($guideRecord['name']) ?></div>
    <div class="sidebar-profile-role"><?= htmlspecialchars($guideRecord['specialization'] ?? 'Trail Guide') ?></div>
  </div>
  <a href="../login-and-signup/login.php" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;text-decoration:none;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </a>
</div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">
        Trail Management
      </div>
      <div class="topbar-right">
        <button class="topbar-icon-btn" title="Refresh" onclick="location.reload()"><i class="fas fa-rotate"></i></button>
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

          <!-- SIDE PANEL (Bottom Sheet on Mobile) -->
          <div class="map-side-panel collapsed" id="sidePanel">
            <!-- Drag handle -->
            <div class="panel-drag-handle" id="panelDragHandle"></div>
            
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
          
          <!-- Mobile toggle FAB -->
          <div class="panel-toggle-fab" id="panelToggleFab" onclick="togglePanel()">
            <i class="fas fa-chevron-up"></i>
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
let waypointsVisible = true;
let isPanelExpanded = false;
let panelStartY = 0;
let panelStartHeight = 0;
let isDragging = false;

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
    if (map && typeof map.invalidateSize === 'function') {
      setTimeout(() => map.invalidateSize(), 100);
    }
  } else {
    mapView.classList.add('hidden');
    safetyView.classList.remove('hidden');
    tabs[0].classList.remove('active');
    tabs[1].classList.add('active');
    const iframe = safetyView.querySelector('iframe');
    if (iframe && iframe.src) {
      iframe.src = iframe.src;
    }
  }
}

// Mobile bottom sheet functions
function initBottomSheet() {
  const panel = document.getElementById('sidePanel');
  const handle = document.getElementById('panelDragHandle');
  const toggleFab = document.getElementById('panelToggleFab');
  
  if (!panel) return;
  
  // Set initial collapsed state
  isPanelExpanded = false;
  panel.classList.add('collapsed');
  panel.classList.remove('expanded');
  
  if (toggleFab) {
    toggleFab.style.display = 'flex';
    const icon = toggleFab.querySelector('i');
    if (icon) icon.className = 'fas fa-chevron-up';
  }
  
  // Only add drag functionality on mobile
  if (window.innerWidth <= 768 && handle) {
    handle.addEventListener('touchstart', (e) => {
      isDragging = true;
      panelStartY = e.touches[0].clientY;
      panelStartHeight = panel.offsetHeight;
      panel.style.transition = 'none';
      e.preventDefault();
    });
    
    window.addEventListener('touchmove', (e) => {
      if (!isDragging) return;
      
      const deltaY = panelStartY - e.touches[0].clientY;
      let newHeight = panelStartHeight + deltaY;
      
      // Constrain height
      const minHeight = 70;
      const maxHeight = window.innerHeight * 0.7;
      newHeight = Math.min(maxHeight, Math.max(minHeight, newHeight));
      
      panel.style.height = newHeight + 'px';
      
      // Determine if expanded or collapsed based on height
      const wasExpanded = isPanelExpanded;
      isPanelExpanded = newHeight > 150;
      
      if (wasExpanded !== isPanelExpanded) {
        if (isPanelExpanded) {
          panel.classList.add('expanded');
          panel.classList.remove('collapsed');
          if (toggleFab) {
            const icon = toggleFab.querySelector('i');
            if (icon) icon.className = 'fas fa-chevron-down';
          }
        } else {
          panel.classList.add('collapsed');
          panel.classList.remove('expanded');
          if (toggleFab) {
            const icon = toggleFab.querySelector('i');
            if (icon) icon.className = 'fas fa-chevron-up';
          }
        }
      }
      
      e.preventDefault();
    });
    
    window.addEventListener('touchend', () => {
      if (!isDragging) return;
      isDragging = false;
      panel.style.transition = 'height 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1)';
      
      // Snap to either collapsed or expanded
      const currentHeight = panel.offsetHeight;
      const midPoint = 120;
      
      if (currentHeight > midPoint) {
        // Expand
        panel.style.height = '';
        isPanelExpanded = true;
        panel.classList.add('expanded');
        panel.classList.remove('collapsed');
        if (toggleFab) {
          toggleFab.style.display = 'flex';
          const icon = toggleFab.querySelector('i');
          if (icon) icon.className = 'fas fa-chevron-down';
        }
      } else {
        // Collapse
        panel.style.height = '';
        isPanelExpanded = false;
        panel.classList.add('collapsed');
        panel.classList.remove('expanded');
        if (toggleFab) {
          toggleFab.style.display = 'flex';
          const icon = toggleFab.querySelector('i');
          if (icon) icon.className = 'fas fa-chevron-up';
        }
      }
      
      // Refresh map
      setTimeout(() => {
        if (map && typeof map.invalidateSize === 'function') {
          map.invalidateSize();
        }
      }, 300);
    });
  }
}

function togglePanel() {
  const panel = document.getElementById('sidePanel');
  const toggleFab = document.getElementById('panelToggleFab');
  
  if (!panel) return;
  
  if (isPanelExpanded) {
    // Collapse
    panel.classList.remove('expanded');
    panel.classList.add('collapsed');
    isPanelExpanded = false;
    if (toggleFab) {
      const icon = toggleFab.querySelector('i');
      if (icon) icon.className = 'fas fa-chevron-up';
    }
  } else {
    // Expand
    panel.classList.remove('collapsed');
    panel.classList.add('expanded');
    isPanelExpanded = true;
    if (toggleFab) {
      const icon = toggleFab.querySelector('i');
      if (icon) icon.className = 'fas fa-chevron-down';
    }
  }
  
  // Refresh map after animation
  setTimeout(() => {
    if (map && typeof map.invalidateSize === 'function') {
      map.invalidateSize();
    }
  }, 300);
}

// Window resize handler
function handleResize() {
  const panel = document.getElementById('sidePanel');
  const toggleFab = document.getElementById('panelToggleFab');
  
  if (window.innerWidth > 768) {
    // Desktop: reset panel
    if (panel) {
      panel.classList.remove('collapsed', 'expanded');
      panel.style.height = '';
    }
    if (toggleFab) toggleFab.style.display = 'none';
  } else {
    // Mobile
    if (panel && !isPanelExpanded) {
      panel.classList.add('collapsed');
      panel.classList.remove('expanded');
    } else if (panel && isPanelExpanded) {
      panel.classList.add('expanded');
      panel.classList.remove('collapsed');
    }
    if (toggleFab) toggleFab.style.display = 'flex';
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
  
  renderHikerListAndMarkers(BOOKING_HIKERS);
  startGuideGPS();
  hikerPollId = setInterval(fetchHikers, 15000);
  setInterval(tickTimer, 1000);
  updateMapTime();
  setInterval(updateMapTime, 1000);
  enableMapClickReporting();
  
  // Initialize bottom sheet for mobile
  initBottomSheet();
  handleResize();
  window.addEventListener('resize', handleResize);
  
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

// ── HEATMAP ──────────────────────────────────────────────
let heatmapPoints = [];
let heatmapAutoRefresh = null;

async function buildHeatmap() {
    if (!trailCoords.length || !MOUNTAIN_ID) return;
    
    const legend = document.getElementById('heatmapLegend');
    if (legend) {
        if (heatmapOn) legend.classList.add('visible');
        else legend.classList.remove('visible');
    }
    
    try {
        const res = await fetch(`../api/detect_crowd_hotspots.php?mountain_id=${MOUNTAIN_ID}`);
        const data = await res.json();
        
        if (data.success && data.points && data.points.length) {
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
                    0.2: '#10B981',
                    0.4: '#84CC16',
                    0.6: '#F59E0B',
                    0.8: '#EF4444',
                    1.0: '#7F1D1D'
                }
            });
            
            if (heatmapOn) {
                heatmapLayer.addTo(map);
                showToast(`🔥 Heatmap showing ${data.points.length} crowded area(s)`);
            }
            
            const highPoints = data.points.filter(p => p.intensity >= 0.7);
            const medPoints = data.points.filter(p => p.intensity >= 0.4 && p.intensity < 0.7);
            
            let overallLevel = 'Low';
            
            if (highPoints.length > 0) {
                overallLevel = 'High';
            } else if (medPoints.length > 0) {
                overallLevel = 'Medium';
            }
            
            const badge = document.getElementById('crowdBadge');
            if (badge) {
                badge.className = `crowd-badge ${overallLevel}`;
                badge.textContent = `${overallLevel} Crowd`;
            }
        } else {
            if (heatmapLayer && heatmapOn) {
                map.removeLayer(heatmapLayer);
            }
            heatmapPoints = [];
        }
    } catch (e) {
        console.error('Heatmap fetch error:', e);
    }
}

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

    let reportLat = guidePosition?.lat || START_LAT;
    let reportLng = guidePosition?.lng || START_LNG;
    
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
            showToast(`✅ ${selectedCrowdLevel} crowd reported`);
            setTimeout(() => buildHeatmap(), 500);
            closeCrowdModal();
        } else {
            showToast('Error: ' + (data.message || 'Could not update crowd level'));
        }
    } catch (e) {
        showToast('Network error');
    }
}

function startHeatmapAutoRefresh() {
    if (heatmapAutoRefresh) clearInterval(heatmapAutoRefresh);
    heatmapAutoRefresh = setInterval(() => {
        if (heatmapOn) {
            buildHeatmap();
        }
    }, 120000);
}

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
        if (legend) legend.classList.add('visible');
        showToast('🔥 Heatmap enabled - Click on trail to report crowd levels');
        map.getContainer().style.cursor = 'crosshair';
    } else {
        if (heatmapLayer) map.removeLayer(heatmapLayer);
        btn.classList.remove('active');
        if (heatmapAutoRefresh) clearInterval(heatmapAutoRefresh);
        showToast('Heatmap off');
        if (legend) legend.classList.remove('visible');
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

// ── CUSTOM CONFIRM MODAL ──
let _confirmResolver = null;
function showConfirm(title, msg) {
  return new Promise((resolve) => {
    // Create modal elements if they don't exist
    let modal = document.getElementById('confirmModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'confirmModal';
      modal.className = 'confirm-modal';
      modal.innerHTML = `
        <div class="confirm-card">
          <div class="confirm-icon"><i class="fas fa-flag-checkered"></i></div>
          <div class="confirm-title" id="confirmTitle">Finish Hike?</div>
          <div class="confirm-msg" id="confirmMsg">Are you sure you want to end this hike session for all hikers?</div>
          <div class="confirm-btns">
            <button class="confirm-btn confirm-btn-cancel" onclick="_resolveConfirm(false)">Cancel</button>
            <button class="confirm-btn confirm-btn-proceed" onclick="_resolveConfirm(true)">End Session</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      // Add styles if not present
      if (!document.querySelector('#confirmStyles')) {
        const style = document.createElement('style');
        style.id = 'confirmStyles';
        style.textContent = `
          .confirm-modal {
            position: fixed;
            inset: 0;
            background: rgba(16, 6, 0, 0.7);
            backdrop-filter: blur(12px);
            z-index: 7000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
          }
          .confirm-modal.open { display: flex; }
          .confirm-card {
            background: rgba(255,255,255,0.98);
            border-radius: 28px;
            width: 100%;
            max-width: 340px;
            padding: 32px 28px 28px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
            animation: confirmPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(4px);
          }
          @keyframes confirmPop {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
          }
          .confirm-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, rgba(184,49,42,0.12), rgba(184,49,42,0.05));
            color: #B8312A;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
          }
          .confirm-title { font-size: 1.3rem; font-weight: 800; color: #100600; margin-bottom: 10px; letter-spacing: -0.3px; }
          .confirm-msg { font-size: 0.85rem; color: #666; line-height: 1.5; margin-bottom: 28px; }
          .confirm-btns { display: flex; gap: 12px; }
          .confirm-btn {
            flex: 1; padding: 12px 16px; border-radius: 40px; border: none;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'DM Sans', sans-serif;
          }
          .confirm-btn-cancel { background: #f0ede8; color: #666; }
          .confirm-btn-cancel:hover { background: #e5e2dd; transform: translateY(-1px); }
          .confirm-btn-proceed { background: linear-gradient(135deg, #B8312A, #8B1A14); color: white; box-shadow: 0 4px 14px rgba(184,49,42,0.3); }
          .confirm-btn-proceed:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(184,49,42,0.4); }
          .confirm-btn-proceed:active { transform: translateY(0); }
        `;
        document.head.appendChild(style);
      }
    }
    
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent = msg;
    modal.classList.add('open');
    _confirmResolver = resolve;
  });
}

function _resolveConfirm(val) {
  const modal = document.getElementById('confirmModal');
  if (modal) modal.classList.remove('open');
  if (_confirmResolver) _confirmResolver(val);
  _confirmResolver = null;
}

// ── BEAUTIFUL TOAST SYSTEM ──
let _toastTimers = new Map();
let _toastCount = 0;

function showToast(msg, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
    
    // Add toast styles
    if (!document.querySelector('#toastStyles')) {
      const style = document.createElement('style');
      style.id = 'toastStyles';
      style.textContent = `
        .toast-container {
          position: fixed;
          bottom: 28px;
          left: 50%;
          transform: translateX(-50%);
          z-index: 6000;
          display: flex;
          flex-direction: column-reverse;
          gap: 10px;
          align-items: center;
          pointer-events: none;
          width: max-content;
          max-width: min(92vw, 380px);
        }
        .toast {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 12px 20px 12px 16px;
          border-radius: 60px;
          font-size: 0.82rem;
          font-weight: 500;
          font-family: 'DM Sans', sans-serif;
          pointer-events: auto;
          box-shadow: 0 8px 24px rgba(0,0,0,0.18), 0 2px 6px rgba(0,0,0,0.08);
          backdrop-filter: blur(20px);
          border: 1px solid rgba(255,255,255,0.15);
          opacity: 0;
          transform: translateY(20px) scale(0.95);
          transition: opacity 0.25s cubic-bezier(0.16,1,0.3,1), transform 0.25s cubic-bezier(0.16,1,0.3,1);
          max-width: 100%;
          white-space: normal;
          word-break: break-word;
          letter-spacing: -0.2px;
        }
        .toast.show {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
        .toast.hide {
          opacity: 0;
          transform: translateY(10px) scale(0.96);
        }
        .toast-info {
          background: rgba(20, 12, 8, 0.92);
          color: rgba(255,255,240,0.95);
          border-left: 3px solid #8a8278;
        }
        .toast-success {
          background: rgba(16, 60, 40, 0.92);
          color: #c8f0dc;
          border-left: 3px solid #1B7045;
        }
        .toast-warning {
          background: rgba(80, 55, 20, 0.92);
          color: #fdebb3;
          border-left: 3px solid #C97B1A;
        }
        .toast-error {
          background: rgba(90, 25, 18, 0.92);
          color: #fcc5c5;
          border-left: 3px solid #B8312A;
        }
        .toast-icon {
          font-size: 1rem;
          flex-shrink: 0;
        }
        .toast-msg {
          line-height: 1.4;
          flex: 1;
        }
        @media (max-width: 768px) {
          .toast-container {
            bottom: 95px;
            max-width: min(90vw, 340px);
          }
          .toast {
            padding: 10px 16px 10px 14px;
            font-size: 0.75rem;
          }
          .toast-icon {
            font-size: 0.85rem;
          }
        }
      `;
      document.head.appendChild(style);
    }
  }

  const icons = {
    info:    'fas fa-circle-info',
    success: 'fas fa-circle-check',
    warning: 'fas fa-triangle-exclamation',
    error:   'fas fa-circle-xmark'
  };

  // Auto-detect type from emoji/keywords
  if (type === 'info') {
    if (/✅|🎉|saved|success|done|shown|enabled|reported|marked|updated|added|completed/.test(msg)) type = 'success';
    else if (/⚠️|💡|warning|tip|crowd|heatmap|click/.test(msg)) type = 'warning';
    else if (/❌|error|fail|could not|cannot|network|missing/.test(msg)) type = 'error';
  }

  const id = ++_toastCount;
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.id = `toast-${id}`;
  toast.innerHTML = `<i class="toast-icon ${icons[type] || icons.info}"></i><span class="toast-msg">${msg}</span>`;

  container.appendChild(toast);

  requestAnimationFrame(() => {
    requestAnimationFrame(() => toast.classList.add('show'));
  });

  const timer = setTimeout(() => {
    toast.classList.add('hide');
    toast.classList.remove('show');
    setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
    _toastTimers.delete(id);
  }, 3500);
  _toastTimers.set(id, timer);
}
async function finishHike() {
  if (hikeFinished) return;
  
  // Debug: Log what we're sending
  console.log('=== FINISH HIKE DEBUG ===');
  console.log('BOOKING_ID:', BOOKING_ID);
  console.log('SESSION_TOKEN:', SESSION_TOKEN);
  console.log('totalDistGuide:', totalDistGuide);
  console.log('durationSec:', Math.floor((Date.now() - startTime) / 1000));
  console.log('========================');
  
  const confirmed = await showConfirm(
    'Complete Hike Session?', 
    'Are you sure you want to end this hike? All hikers will be notified and tracking will stop.'
  );
  if (!confirmed) return;

  const btn = document.getElementById('finishBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finishing...';

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
      showToast('🎉 Hike completed successfully!', 'success');
    } else {
      showToast('❌ ' + (data.message || 'Could not finish hike'), 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-flag-checkered"></i> Finish Hike';
    }
  } catch (e) {
    console.error('Finish hike error:', e);
    showToast('❌ Network error — please try again', 'error');
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

function placeWaypointMarkersFromDB() {
    if (!WAYPOINTS || !WAYPOINTS.length) return;
    
    const iconConfigs = {
        'summit': { html: `<div class="waypoint-marker summit-marker"><i class="fas fa-mountain"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'campsite': { html: `<div class="waypoint-marker campsite-marker"><i class="fas fa-campground"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'viewpoint': { html: `<div class="waypoint-marker viewpoint-marker"><i class="fas fa-eye"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'information': { html: `<div class="waypoint-marker information-marker"><i class="fas fa-info-circle"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'peak': { html: `<div class="waypoint-marker peak-marker"><i class="fas fa-flag-checkered"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'mountain_pass': { html: `<div class="waypoint-marker mountain_pass-marker"><i class="fas fa-road"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'tree': { html: `<div class="waypoint-marker tree-marker"><i class="fas fa-tree"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'water_source': { html: `<div class="waypoint-marker water-marker"><i class="fas fa-water"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'rest_area': { html: `<div class="waypoint-marker rest-marker"><i class="fas fa-chair"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'danger': { html: `<div class="waypoint-marker danger-marker"><i class="fas fa-triangle-exclamation"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'start': { html: `<div class="waypoint-marker start-marker"><i class="fas fa-flag"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] },
        'default': { html: `<div class="waypoint-marker default-marker"><i class="fas fa-map-pin"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] }
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
        
        let elevationText = '';
        if (wp.elevation) {
            elevationText = ` · ${Math.round(wp.elevation)}m`;
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

function toggleWaypoints() {
    waypointsVisible = !waypointsVisible;
    wpMarkers.forEach(m => waypointsVisible ? m.addTo(map) : map.removeLayer(m));
    document.getElementById('waypointBtn')?.classList.toggle('active', !waypointsVisible);
    showToast(waypointsVisible ? 'Waypoints shown' : 'Waypoints hidden');
}

let trailClickMarker = null;

function enableMapClickReporting() {
    map.getContainer().style.cursor = 'crosshair';
    
    map.on('click', function(e) {
        const clickedPoint = e.latlng;
        
        let closestPoint = null;
        let minDistance = Infinity;
        
        trailCoords.forEach((coord, idx) => {
            const distance = map.distance(clickedPoint, L.latLng(coord[0], coord[1]));
            if (distance < minDistance && distance < 100) {
                minDistance = distance;
                closestPoint = coord;
            }
        });
        
        if (closestPoint && minDistance < 100) {
            window._clickedLocation = { 
                lat: closestPoint[0], 
                lng: closestPoint[1] 
            };
            
            if (trailClickMarker) {
                map.removeLayer(trailClickMarker);
            }
            
            trailClickMarker = L.circleMarker([closestPoint[0], closestPoint[1]], {
                radius: 12,
                color: '#100600',
                weight: 3,
                opacity: 1,
                fillColor: '#F59E0B',
                fillOpacity: 0.6,
                className: 'pulse-marker'
            }).addTo(map);
            
            setTimeout(() => {
                if (trailClickMarker) {
                    map.removeLayer(trailClickMarker);
                    trailClickMarker = null;
                }
            }, 2000);
            
            openCrowdModal();
            showToast('📍 Select crowd level for this area');
        }
    });
}

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

window.addEventListener('beforeunload', () => {
  if (watchId) navigator.geolocation.clearWatch(watchId);
  if (guideIntervalId) clearInterval(guideIntervalId);
  if (hikerPollId) clearInterval(hikerPollId);
});

document.addEventListener('DOMContentLoaded', () => {
  if (HIKE_DATA) initMap();
});
</script>
</body>
</html>