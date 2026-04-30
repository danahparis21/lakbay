<?php
// frontend/view_activity.php - View past hike summary
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$sessionId = $_GET['session_id'] ?? $_GET['id'] ?? '';
$bookingId = $_GET['booking_id'] ?? '';

if (empty($sessionId) && empty($bookingId)) {
    header('Location: hikerProfile.php');
    exit;
}

try {
    if (!empty($sessionId)) {
        $stmt = $pdo->prepare("
            SELECT ahs.*, b.mountain_id, m.name as mountain_name, m.trail_data
            FROM active_hike_sessions ahs
            JOIN bookings b ON ahs.booking_id = b.id
            JOIN mountains m ON b.mountain_id = m.id
            WHERE ahs.id = ? AND ahs.user_id = ? AND ahs.status = 'finished'
        ");
        $stmt->execute([$sessionId, $currentUserId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ahs.*, b.mountain_id, m.name as mountain_name, m.trail_data
            FROM active_hike_sessions ahs
            JOIN bookings b ON ahs.booking_id = b.id
            JOIN mountains m ON b.mountain_id = m.id
            WHERE ahs.booking_id = ? AND ahs.user_id = ? AND ahs.status = 'finished'
            ORDER BY ahs.end_time DESC LIMIT 1
        ");
        $stmt->execute([$bookingId, $currentUserId]);
    }

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        header('Location: hikerProfile.php?error=no_activity_found');
        exit;
    }

    $trackPoints = [];
    $mountainId = $session['mountain_id'];

    if ($mountainId == 4) {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'TALAMITAM' ORDER BY idx ASC");
        $stmt->execute();
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($mountainId == 2) {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'APAYANG' ORDER BY idx ASC");
        $stmt->execute();
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($mountainId == 3) {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'LANTIK' ORDER BY idx ASC");
        $stmt->execute();
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($mountainId == 1) {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = 'BATULAO' ORDER BY idx ASC");
        $stmt->execute();
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE mountain_id = ? ORDER BY idx ASC");
        $stmt->execute([$mountainId]);
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $trailCoords = [];
    foreach ($trackPoints as $point) {
        $trailCoords[] = [(float)$point['lat'], (float)$point['lon']];
    }

    $waypoints = [];
    $stmt = $pdo->prepare("SELECT name, type, latitude, longitude, elevation FROM trail_waypoints WHERE mountain_id = ? AND is_active = 1 ORDER BY order_index ASC");
    $stmt->execute([$mountainId]);
    $waypoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $badgesEarned = json_decode($session['badges_earned'], true) ?? [];

    $durationSec = $session['total_duration'];
    $hours = floor($durationSec / 3600);
    $mins = floor(($durationSec % 3600) / 60);
    $secs = $durationSec % 60;
    $timeStr = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
    $timeStrFull = $hours > 0 ? sprintf('%d:%02d:%02d', $hours, $mins, $secs) : sprintf('%d:%02d', $mins, $secs);

    $distance = $session['total_distance'];
    $paceMinutes = $distance > 0 ? ($durationSec / 60) / $distance : 0;
    $paceMinutesInt = (int)floor($paceMinutes);
    $paceSecondsInt = (int)round(($paceMinutes - $paceMinutesInt) * 60);
    $paceFormatted = $paceMinutesInt . ":" . str_pad((string)$paceSecondsInt, 2, "0", STR_PAD_LEFT);

    $avgSpeed = $distance > 0 ? ($distance / ($durationSec / 3600)) : 0;

    $summary = [
        'mountain'       => $session['mountain_name'],
        'distance_km'    => round((float)$distance, 2),
        'duration'       => $timeStr,
        'duration_full'  => $timeStrFull,
        'avg_speed_kmh'  => round($avgSpeed, 1),
        'pace'           => $paceFormatted,
        'badges_count'   => count($badgesEarned),
        'completed_at'   => date('F j, Y', strtotime($session['end_time'])),
        'completed_time' => date('g:i A', strtotime($session['end_time'])),
    ];

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die("Database error");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($summary['mountain']) ?> — LAKBAY</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23100600' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --primary:      #100600;
        --primary-soft: rgba(16,6,0,0.07);
        --bg:           #F4F2EF;
        --white:        #FFFFFF;
        --stone:        #F0EDE8;
        --line:         #ECEAE6;
        --ink:          #1A1A18;
        --ink-3:        #6B6B63;
        --ink-4:        #9A9A90;
        --amber:        #C97B1A;
        --amber-lt:     #FDF3E0;
        --green:        #1B7045;
        --red:          #B8312A;
        --shadow-md:    0 4px 20px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
        --shadow-lg:    0 16px 48px rgba(0,0,0,0.10), 0 4px 16px rgba(0,0,0,0.06);
        --r-md:         14px;
        --r-xl:         28px;
    }

    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        color: var(--ink);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 24px 16px 48px;
        -webkit-font-smoothing: antialiased;
    }

    /* ── TOP NAV ── */
    .top-nav {
        width: 100%;
        max-width: 420px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
    }
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--ink-3);
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        padding: 7px 14px 7px 10px;
        border-radius: 100px;
        background: var(--white);
        border: 1px solid var(--line);
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        transition: background 0.15s;
    }
    .back-btn:hover { background: var(--stone); color: var(--primary); }
    .back-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }

    .share-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--white);
        background: var(--primary);
        font-size: 13px;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 100px;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(16,6,0,0.22);
        transition: opacity 0.15s, transform 0.15s;
        font-family: 'DM Sans', sans-serif;
    }
    .share-btn:hover { opacity: 0.88; transform: translateY(-1px); }
    .share-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2.2; }

    /* ── CARD ── */
    .activity-card {
        width: 100%;
        max-width: 420px;
        background: var(--white);
        border: 1px solid var(--line);
        border-radius: var(--r-xl);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        animation: fadeUp 0.4s cubic-bezier(0.16,1,0.3,1) both;
    }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── HERO ── */
    .card-hero {
        background: var(--primary);
        padding: 28px 24px 22px;
        position: relative;
        overflow: hidden;
    }
    .card-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse 70% 70% at 85% 120%, rgba(201,123,26,0.2) 0%, transparent 65%);
        pointer-events: none;
    }
    .hero-eyebrow {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(255,255,255,0.11);
        border: 1px solid rgba(255,255,255,0.16);
        border-radius: 100px;
        padding: 3px 10px;
        font-size: 10px;
        font-weight: 600;
        color: rgba(255,255,255,0.8);
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .hero-chip-dot {
        width: 5px; height: 5px;
        border-radius: 50%;
        background: var(--amber);
    }
    .hero-date {
        font-size: 11px;
        color: rgba(255,255,255,0.4);
    }
    .hero-mountain {
        font-size: 26px;
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.4px;
        line-height: 1.15;
    }
    .hero-sub {
        font-size: 12px;
        color: rgba(255,255,255,0.45);
        margin-top: 5px;
    }

    /* ── MAP ── */
    .map-wrap {
        position: relative;
        height: 230px;
        background: #e8e5df;
    }
    #activityMap { position: absolute; inset: 0; width: 100%; height: 100%; }
    .leaflet-control-attribution { display: none !important; }

    /* ── STATS ROW ── */
    .stats-row {
        display: flex;
        align-items: center;
        padding: 22px 24px;
        gap: 0;
        border-bottom: 1px solid var(--line);
    }
    .stat-col {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .stat-col.big { flex: 1; }
    .stat-col.big .stat-val {
        font-size: 44px;
        letter-spacing: -2px;
        line-height: 1;
    }
    .stat-val {
        font-family: 'DM Mono', monospace;
        font-size: 20px;
        font-weight: 500;
        color: var(--primary);
        line-height: 1.1;
    }
    .stat-val-unit {
        font-size: 13px;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink-4);
        font-weight: 500;
        margin-left: 2px;
    }
    .stat-lbl {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: var(--ink-4);
        margin-top: 1px;
    }
    .stat-divider {
        width: 1px;
        height: 56px;
        background: var(--line);
        flex-shrink: 0;
        margin: 0 22px;
    }
    .stat-right-stack {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 12px;
    }

    /* ── BADGES ROW ── */
    .badges-row {
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid var(--line);
    }
    .badge-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: var(--amber-lt);
        display: flex; align-items: center; justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .badge-text-val {
        font-family: 'DM Mono', monospace;
        font-size: 17px;
        font-weight: 500;
        color: var(--amber);
    }
    .badge-text-lbl {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--amber);
        opacity: 0.7;
    }

    /* ── BRAND + ACTIONS ── */
    .card-footer {
        padding: 16px 24px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .brand-mark {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .brand-name {
        font-size: 14px;
        font-weight: 800;
        color: var(--primary);
        letter-spacing: -0.3px;
    }
    .brand-sep { width: 1px; height: 14px; background: var(--line); }
    .brand-sub {
        font-size: 10px;
        color: var(--ink-4);
        font-weight: 500;
    }
    .btn-book {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--primary);
        color: var(--white);
        font-size: 12px;
        font-weight: 600;
        font-family: 'DM Sans', sans-serif;
        padding: 9px 16px;
        border-radius: 100px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity 0.15s;
    }
    .btn-book:hover { opacity: 0.85; }

    /* ── TOAST ── */
    .toast {
        position: fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%) translateY(50px);
        background: var(--primary);
        color: white;
        padding: 11px 22px;
        border-radius: 100px;
        font-size: 13px;
        font-weight: 600;
        z-index: 9999;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        pointer-events: none;
        white-space: nowrap;
        box-shadow: 0 8px 24px rgba(16,6,0,0.25);
        font-family: 'DM Sans', sans-serif;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.success { background: var(--green); }
    .toast.error   { background: var(--red); }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="hikerProfile.php" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
    </a>
    <button class="share-btn" onclick="shareActivity()">
        <svg viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
        Save for IG
    </button>
</nav>

<div class="activity-card" id="activityCard">

    <!-- Hero -->
    <div class="card-hero">
        <div class="hero-eyebrow">
            <span class="hero-chip"><span class="hero-chip-dot"></span>Completed</span>
            <span class="hero-date"><?= $summary['completed_at'] ?></span>
        </div>
        <div class="hero-mountain">⛰ <?= htmlspecialchars($summary['mountain']) ?></div>
        <div class="hero-sub">Finished at <?= $summary['completed_time'] ?></div>
    </div>

    <!-- Trail map -->
    <div class="map-wrap">
        <div id="activityMap"></div>
    </div>

    <!-- Stats: distance (big) | duration + pace -->
    <div class="stats-row">
        <div class="stat-col big">
            <div class="stat-val"><?= $summary['distance_km'] ?><span class="stat-val-unit">km</span></div>
            <div class="stat-lbl">Distance</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-right-stack">
            <div class="stat-col">
                <div class="stat-val"><?= $summary['duration'] ?></div>
                <div class="stat-lbl">Duration</div>
            </div>
            <div class="stat-col">
                <div class="stat-val"><?= $summary['pace'] ?><span class="stat-val-unit">/km</span></div>
                <div class="stat-lbl">Avg Pace</div>
            </div>
        </div>
    </div>

    <!-- Badges -->
    <div class="badges-row">
        <div class="badge-icon">🏅</div>
        <div>
            <div class="badge-text-val"><?= $summary['badges_count'] ?> badge<?= $summary['badges_count'] !== 1 ? 's' : '' ?></div>
            <div class="badge-text-lbl">Earned this hike</div>
        </div>
    </div>

    <!-- Brand + book CTA -->
    <div class="card-footer">
        <div class="brand-mark">
            <span class="brand-name">LAKBAY</span>
            <span class="brand-sep"></span>
            <span class="brand-sub">Trail recorded with Lakbay</span>
        </div>
        <a href="bookings.php" class="btn-book">Book again</a>
    </div>

</div><!-- /activity-card -->

<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const trailCoords  = <?= json_encode($trailCoords) ?>;
const mountainName = <?= json_encode($summary['mountain']) ?>;
const distance     = <?= $summary['distance_km'] ?>;
const duration     = <?= json_encode($summary['duration']) ?>;
const pace         = <?= json_encode($summary['pace']) ?>;
const badgesCount  = <?= $summary['badges_count'] ?>;
const completedAt  = <?= json_encode($summary['completed_at']) ?>;
const completedTime= <?= json_encode($summary['completed_time']) ?>;

/* ── MAP ── */
function initMap() {
    if (!trailCoords || trailCoords.length === 0) {
        document.getElementById('activityMap').innerHTML =
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9A9A90;font-size:13px;">No trail data</div>';
        return;
    }
    const map = L.map('activityMap', {
        zoomControl: false, attributionControl: false,
        dragging: false, touchZoom: false,
        scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false
    }).setView(trailCoords[0], 13);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '', subdomains: 'abcd', maxZoom: 19
    }).addTo(map);

    L.polyline(trailCoords, { color: '#100600', weight: 3, opacity: 0.85, lineCap: 'round', lineJoin: 'round' }).addTo(map);

    const dot = (color, size) => L.divIcon({
        html: `<div style="background:${color};width:${size}px;height:${size}px;border-radius:50%;border:2.5px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.2);"></div>`,
        className: '', iconSize: [size, size], iconAnchor: [size/2, size/2]
    });
    L.marker(trailCoords[0], { icon: dot('#1B7045', 11) }).addTo(map);
    L.marker(trailCoords[trailCoords.length-1], { icon: dot('#B8312A', 13) }).addTo(map);
    map.fitBounds(L.latLngBounds(trailCoords).pad(0.12));
    setTimeout(() => map.invalidateSize(), 150);
}

/* ── SHARE: transparent canvas, Strava-style ── */
async function shareActivity() {
    showToast('Creating your card…', 'info');

    // Canvas dimensions — portrait, generous padding
    const W = 420, PAD = 32;
    const mapH   = 280; // trail drawing area
    const statsH = 120; // distance / duration / pace
    const badgeH =  60; // badge line
    const footH  =  52; // LAKBAY branding
    const H = mapH + statsH + badgeH + footH;

    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    // ── FULLY TRANSPARENT — no background whatsoever ──
    ctx.clearRect(0, 0, W, H);

    /* ── 1. Trail map (outline only, no map tile background) ── */
    if (trailCoords && trailCoords.length > 1) {
        let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
        trailCoords.forEach(c => {
            minLat = Math.min(minLat, c[0]); maxLat = Math.max(maxLat, c[0]);
            minLng = Math.min(minLng, c[1]); maxLng = Math.max(maxLng, c[1]);
        });
        const mp = 36;
        const mW = W - mp * 2, mH = mapH - mp * 2;
        const latR = maxLat - minLat || 0.001, lngR = maxLng - minLng || 0.001;
        const scale = Math.min(mW / lngR, mH / latR);
        const offX = mp + (mW - lngR * scale) / 2;
        const offY = mp + (mH - latR * scale) / 2;
        const tx = lng => offX + (lng - minLng) * scale;
        const ty = lat => offY + mH - (lat - minLat) * scale;

        // Trail line — dark, solid, rounded
        ctx.beginPath();
        trailCoords.forEach((c, i) => {
            i === 0 ? ctx.moveTo(tx(c[1]), ty(c[0])) : ctx.lineTo(tx(c[1]), ty(c[0]));
        });
        ctx.strokeStyle = '#100600';
        ctx.lineWidth = 3.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();

        // Start dot (green)
        const sx = tx(trailCoords[0][1]), sy = ty(trailCoords[0][0]);
        ctx.beginPath(); ctx.arc(sx, sy, 7, 0, Math.PI * 2);
        ctx.fillStyle = '#1B7045'; ctx.fill();
        ctx.beginPath(); ctx.arc(sx, sy, 3.5, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,0.9)'; ctx.fill();

        // End dot (red)
        const ex = tx(trailCoords[trailCoords.length-1][1]);
        const ey = ty(trailCoords[trailCoords.length-1][0]);
        ctx.beginPath(); ctx.arc(ex, ey, 8, 0, Math.PI * 2);
        ctx.fillStyle = '#B8312A'; ctx.fill();
        ctx.beginPath(); ctx.arc(ex, ey, 4, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,0.9)'; ctx.fill();

        // Mountain name label — small pill, barely-there
        ctx.font = '600 10px "DM Sans", sans-serif';
        ctx.fillStyle = 'rgba(16,6,0,0.55)';
        ctx.textAlign = 'left';
        ctx.fillText('⛰ ' + mountainName, PAD, mapH - 12);
    }

    /* ── 2. Thin separator line ── */
    ctx.strokeStyle = 'rgba(16,6,0,0.1)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(PAD, mapH);
    ctx.lineTo(W - PAD, mapH);
    ctx.stroke();

    /* ── 3. Stats — transparent, dark text only ── */
    const sY = mapH + 20;

    // Big distance
    ctx.font = '500 52px "DM Mono", monospace';
    ctx.fillStyle = '#100600';
    ctx.textAlign = 'left';
    ctx.fillText(String(distance), PAD, sY + 52);

    ctx.font = '500 15px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(16,6,0,0.45)';
    // measure to place "km" right after the number
    ctx.font = '500 52px "DM Mono", monospace';
    const numW = ctx.measureText(String(distance)).width;
    ctx.font = '500 15px "DM Sans", sans-serif';
    ctx.fillText('km', PAD + numW + 6, sY + 50);

    ctx.font = '600 9px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(16,6,0,0.35)';
    ctx.fillText('DISTANCE', PAD, sY + 68);

    // Vertical separator
    const divX = W / 2 + 16;
    ctx.strokeStyle = 'rgba(16,6,0,0.12)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(divX, sY + 4); ctx.lineTo(divX, sY + 86); ctx.stroke();

    // Right: duration + pace
    const rX = divX + 22;
    ctx.font = '500 22px "DM Mono", monospace';
    ctx.fillStyle = '#100600';
    ctx.fillText(duration, rX, sY + 34);
    ctx.font = '600 9px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(16,6,0,0.35)';
    ctx.fillText('DURATION', rX, sY + 48);

    ctx.font = '500 22px "DM Mono", monospace';
    ctx.fillStyle = '#100600';
    ctx.fillText(pace + ' /km', rX, sY + 76);
    ctx.font = '600 9px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(16,6,0,0.35)';
    ctx.fillText('AVG PACE', rX, sY + 90);

    /* ── 4. Separator ── */
    const bY = mapH + statsH;
    ctx.strokeStyle = 'rgba(16,6,0,0.08)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD, bY); ctx.lineTo(W - PAD, bY); ctx.stroke();

    /* ── 5. Badge line ── */
    ctx.font = '16px serif';
    ctx.textAlign = 'left';
    ctx.fillText('🏅', PAD, bY + 36);

    ctx.font = '500 17px "DM Mono", monospace';
    ctx.fillStyle = '#C97B1A';
    ctx.fillText(badgesCount + ' badge' + (badgesCount !== 1 ? 's' : ''), PAD + 26, bY + 36);

    ctx.font = '600 9px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(201,123,26,0.55)';
    ctx.fillText('EARNED THIS HIKE', PAD + 26, bY + 51);

    /* ── 6. LAKBAY branding ── */
    const fY = bY + badgeH + 8;
    ctx.font = '800 13px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(16,6,0,0.7)';
    ctx.textAlign = 'left';
    ctx.fillText('LAKBAY', PAD, fY + 24);

    ctx.strokeStyle = 'rgba(16,6,0,0.15)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD + 60, fY + 12); ctx.lineTo(PAD + 60, fY + 28); ctx.stroke();

    ctx.font = '400 10px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(16,6,0,0.35)';
    ctx.fillText('Trail recorded with Lakbay', PAD + 70, fY + 24);

    /* ── Export ── */
    canvas.toBlob(async (blob) => {
        const mobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        if (mobile && navigator.share && navigator.canShare) {
            try {
                const file = new File([blob], 'lakbay_hike.png', { type: 'image/png' });
                await navigator.share({ title: 'My Lakbay Hike', text: `I conquered ${mountainName}! ⛰️`, files: [file] });
                showToast('Shared! ✨', 'success');
            } catch (err) {
                if (err.name !== 'AbortError') await copyToClipboard(blob);
            }
        } else {
            await copyToClipboard(blob);
        }
    }, 'image/png');
}

async function copyToClipboard(blob) {
    try {
        await navigator.clipboard.write([new ClipboardItem({ [blob.type]: blob })]);
        showToast('📸 Copied! Paste into your IG story.', 'success');
    } catch {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.download = 'lakbay_hike.png'; a.href = url; a.click();
        URL.revokeObjectURL(url);
        showToast('📸 Saved to downloads!', 'success');
    }
}

function roundRect(ctx, x, y, w, h, r) {
    if (typeof r === 'number') r = { tl: r, tr: r, br: r, bl: r };
    ctx.beginPath();
    ctx.moveTo(x + r.tl, y);
    ctx.lineTo(x + w - r.tr, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r.tr);
    ctx.lineTo(x + w, y + h - r.br); ctx.quadraticCurveTo(x + w, y + h, x + w - r.br, y + h);
    ctx.lineTo(x + r.bl, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r.bl);
    ctx.lineTo(x, y + r.tl); ctx.quadraticCurveTo(x, y, x + r.tl, y);
    ctx.closePath();
}

let _toastTimer;
function showToast(msg, type = 'info') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (type !== 'info' ? ' ' + type : '');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => { t.className = 'toast'; }, 3400);
}

setTimeout(initMap, 150);
</script>
</body>
</html>