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
    html { font-size: 15px; }

    :root {
        --primary:        #100600;
        --primary-light:  #2C1A10;
        --primary-soft:   rgba(16, 6, 0, 0.07);
        --pearl:          #FFFFFF;
        --pearl-warm:     #FEFCF8;
        --bg-white:       #F8F7F5;
        --bg-offwhite:    #F4F2EF;
        --stone:          #F0EDE8;
        --stone-2:        #E8E3DC;
        --stone-3:        #D4CEC5;
        --ink:            #1A1A18;
        --ink-2:          #3A3A35;
        --ink-3:          #6B6B63;
        --ink-4:          #9A9A90;
        --ink-5:          #C8C8BF;
        --white:          #FFFFFF;
        --amber:          #C97B1A;
        --amber-lt:       #FDF3E0;
        --red:            #B8312A;
        --red-lt:         #FDECEA;
        --green:          #1B7045;
        --green-lt:       rgba(27, 112, 69, 0.10);
        --line:           #ECEAE6;
        --line-pearl:     rgba(0,0,0,0.055);
        --glass-bg:       rgba(255, 255, 255, 0.72);
        --glass-border:   rgba(255, 255, 255, 0.45);
        --shadow-sm:      0 1px 4px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);
        --shadow-md:      0 4px 16px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.04);
        --shadow-lg:      0 16px 48px rgba(0,0,0,0.10), 0 4px 16px rgba(0,0,0,0.06);
        --r-sm:   8px;
        --r-md:   14px;
        --r-lg:   20px;
        --r-xl:   28px;
    }

    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg-offwhite);
        color: var(--ink);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    /* ── PAGE SHELL ── */
    .page-wrap {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 24px 16px 48px;
        gap: 0;
    }

    /* ── TOP NAV ── */
    .top-nav {
        width: 100%;
        max-width: 520px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--ink-3);
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        padding: 8px 14px 8px 10px;
        border-radius: 100px;
        background: var(--white);
        border: 1px solid var(--line);
        box-shadow: var(--shadow-sm);
        transition: background 0.15s, color 0.15s;
    }
    .back-btn:hover { background: var(--stone); color: var(--primary); }
    .back-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; }

    .share-btn-top {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--white);
        background: var(--primary);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        padding: 9px 16px;
        border-radius: 100px;
        border: none;
        box-shadow: var(--shadow-md);
        cursor: pointer;
        transition: opacity 0.15s, transform 0.15s;
    }
    .share-btn-top:hover { opacity: 0.88; transform: translateY(-1px); }
    .share-btn-top svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }

    /* ── ACTIVITY CARD ── */
    .activity-card {
        width: 100%;
        max-width: 520px;
        background: var(--white);
        border: 1px solid var(--line);
        border-radius: var(--r-xl);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    /* ── CARD HERO ── */
    .card-hero {
        background: var(--primary);
        padding: 32px 28px 28px;
        position: relative;
        overflow: hidden;
    }
    .card-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 60% at 70% 110%, rgba(201,123,26,0.18) 0%, transparent 70%),
            radial-gradient(ellipse 60% 80% at 20% -10%, rgba(255,255,255,0.06) 0%, transparent 60%);
        pointer-events: none;
    }
    .hero-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
    }
    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 100px;
        padding: 4px 11px;
        font-size: 11px;
        font-weight: 600;
        color: rgba(255,255,255,0.85);
        letter-spacing: 0.4px;
    }
    .hero-badge-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #C97B1A;
        flex-shrink: 0;
    }
    .hero-date {
        font-size: 12px;
        color: rgba(255,255,255,0.45);
        margin-left: auto;
    }

    .hero-mountain {
        font-size: 28px;
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.5px;
        line-height: 1.15;
        margin-bottom: 6px;
    }
    .hero-sub {
        font-size: 13px;
        color: rgba(255,255,255,0.5);
    }

    /* ── MAP SECTION ── */
    .map-section {
        position: relative;
        height: 220px;
        background: var(--stone);
        overflow: hidden;
    }
    #activityMap {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }
    /* topo-style light tile overlay label */
    .map-label {
        position: absolute;
        bottom: 12px;
        left: 14px;
        z-index: 500;
        background: rgba(255,255,255,0.88);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 100px;
        padding: 4px 11px;
        font-size: 11px;
        font-weight: 600;
        color: var(--ink-3);
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .map-label::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--amber);
    }
    .leaflet-control-attribution { display: none !important; }

    /* ── PRIMARY STAT ── */
    .primary-stat {
        padding: 28px 28px 0;
        display: flex;
        align-items: flex-end;
        gap: 16px;
        border-bottom: 1px solid var(--line);
        padding-bottom: 24px;
    }
    .primary-stat-block { flex: 1; }
    .primary-stat-value {
        font-family: 'DM Mono', monospace;
        font-size: 52px;
        font-weight: 500;
        color: var(--primary);
        line-height: 1;
        letter-spacing: -2px;
    }
    .primary-stat-unit {
        font-size: 16px;
        font-weight: 600;
        color: var(--ink-3);
        letter-spacing: 0;
        margin-left: 3px;
    }
    .primary-stat-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--ink-4);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 4px;
    }
    .primary-stat-divider {
        width: 1px;
        height: 56px;
        background: var(--line);
        flex-shrink: 0;
    }
    .secondary-quick {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .secondary-quick-item {
        display: flex;
        flex-direction: column;
    }
    .sq-value {
        font-family: 'DM Mono', monospace;
        font-size: 17px;
        font-weight: 500;
        color: var(--primary);
        line-height: 1;
    }
    .sq-label {
        font-size: 10px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--ink-4);
        margin-top: 2px;
    }

    /* ── STATS GRID ── */
    .stats-section {
        padding: 20px 20px 0;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .stat-box {
        background: var(--bg-offwhite);
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        padding: 14px 16px;
        position: relative;
        overflow: hidden;
    }
    .stat-box::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 2px;
        background: var(--primary);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }
    .stat-box:hover::after { transform: scaleX(1); }
    .stat-box.full-width { grid-column: span 2; }

    .stat-box-icon {
        width: 28px; height: 28px;
        border-radius: var(--r-sm);
        background: var(--primary-soft);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 10px;
        font-size: 13px;
        color: var(--primary);
    }
    .stat-box-value {
        font-family: 'DM Mono', monospace;
        font-size: 22px;
        font-weight: 500;
        color: var(--primary);
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-box-label {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--ink-4);
    }

    /* badges box special */
    .stat-box.badges-box {
        background: var(--amber-lt);
        border-color: rgba(201,123,26,0.2);
    }
    .stat-box.badges-box .stat-box-icon {
        background: rgba(201,123,26,0.15);
        color: var(--amber);
    }
    .stat-box.badges-box .stat-box-value { color: var(--amber); }

    /* ── BRANDING STRIP ── */
    .brand-strip {
        margin: 20px 20px 0;
        padding: 14px 18px;
        background: var(--stone);
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .brand-logo {
        font-size: 16px;
        font-weight: 800;
        color: var(--primary);
        letter-spacing: -0.5px;
    }
    .brand-divider {
        width: 1px;
        height: 16px;
        background: var(--stone-3);
        flex-shrink: 0;
    }
    .brand-tagline {
        font-size: 11px;
        color: var(--ink-4);
        font-weight: 500;
    }
    .brand-icon {
        margin-left: auto;
        font-size: 18px;
        opacity: 0.5;
    }

    /* ── ACTION BUTTONS ── */
    .action-row {
        padding: 20px;
        display: flex;
        gap: 10px;
    }
    .btn {
        flex: 1;
        padding: 13px 16px;
        border-radius: 100px;
        font-size: 13px;
        font-weight: 600;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        transition: all 0.15s;
        border: none;
    }
    .btn-outline {
        background: transparent;
        border: 1.5px solid var(--line);
        color: var(--ink-2);
    }
    .btn-outline:hover { border-color: var(--stone-3); background: var(--bg-offwhite); }
    .btn-filled {
        background: var(--primary);
        color: var(--white);
        box-shadow: 0 4px 16px rgba(16,6,0,0.2);
    }
    .btn-filled:hover { opacity: 0.88; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,6,0,0.25); }
    .btn-filled:active { transform: scale(0.98); }
    .btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }

    /* ── STRAVA-LIKE PACE/ELEV STRIP ── */
    .detail-strip {
        margin: 0 20px;
        padding: 16px 18px;
        background: var(--bg-offwhite);
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .strip-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        gap: 2px;
    }
    .strip-item + .strip-item {
        border-left: 1px solid var(--line);
    }
    .strip-val {
        font-family: 'DM Mono', monospace;
        font-size: 15px;
        font-weight: 500;
        color: var(--primary);
        letter-spacing: -0.3px;
    }
    .strip-lbl {
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--ink-4);
    }

    /* ── TOAST ── */
    .toast {
        position: fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%) translateY(60px);
        background: var(--primary);
        color: white;
        padding: 11px 22px;
        border-radius: 100px;
        font-size: 13px;
        font-weight: 600;
        z-index: 9999;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        white-space: nowrap;
        pointer-events: none;
        box-shadow: 0 8px 24px rgba(16,6,0,0.25);
    }
    .toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .toast.success { background: var(--green); }
    .toast.error   { background: var(--red); }

    /* ── ENTRANCE ANIMATIONS ── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .activity-card {
        animation: fadeUp 0.45s cubic-bezier(0.16,1,0.3,1) both;
    }
    .primary-stat { animation: fadeUp 0.45s 0.05s cubic-bezier(0.16,1,0.3,1) both; }
    .detail-strip { animation: fadeUp 0.45s 0.1s cubic-bezier(0.16,1,0.3,1) both; }
    .stats-section { animation: fadeUp 0.45s 0.12s cubic-bezier(0.16,1,0.3,1) both; }

    /* ── SHARE CANVAS (hidden, only for export) ── */
    #shareCanvas { display: none; }

    /* ── RESPONSIVE ── */
    @media (max-width: 480px) {
        .primary-stat-value { font-size: 44px; }
        .hero-mountain { font-size: 24px; }
        .map-section { height: 200px; }
    }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Top nav -->
    <nav class="top-nav">
        <a href="hikerProfile.php" class="back-btn">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
        </a>
        <button class="share-btn-top" onclick="shareActivity()">
            <svg viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
            Save &amp; Share
        </button>
    </nav>

    <!-- Main card -->
    <div class="activity-card" id="activityCard">

        <!-- Hero header -->
        <div class="card-hero">
            <div class="hero-meta">
                <span class="hero-badge">
                    <span class="hero-badge-dot"></span>
                    Completed Hike
                </span>
                <span class="hero-date"><?= $summary['completed_at'] ?></span>
            </div>
            <div class="hero-mountain">⛰ <?= htmlspecialchars($summary['mountain']) ?></div>
            <div class="hero-sub">Finished at <?= $summary['completed_time'] ?> · <?= $summary['badges_count'] > 0 ? $summary['badges_count'] . ' badge' . ($summary['badges_count'] > 1 ? 's' : '') . ' earned' : 'No badges yet' ?></div>
        </div>

        <!-- Map -->
        <div class="map-section">
            <div id="activityMap"></div>
            <div class="map-label"><?= htmlspecialchars($summary['mountain']) ?> Trail</div>
        </div>

        <!-- Primary stat (distance, big) -->
        <div class="primary-stat">
            <div class="primary-stat-block">
                <div>
                    <span class="primary-stat-value"><?= $summary['distance_km'] ?></span>
                    <span class="primary-stat-unit">km</span>
                </div>
                <div class="primary-stat-label">Distance Hiked</div>
            </div>
            <div class="primary-stat-divider"></div>
            <div class="secondary-quick">
                <div class="secondary-quick-item">
                    <span class="sq-value"><?= $summary['duration'] ?></span>
                    <span class="sq-label">Duration</span>
                </div>
                <div class="secondary-quick-item">
                    <span class="sq-value"><?= $summary['pace'] ?>/km</span>
                    <span class="sq-label">Avg Pace</span>
                </div>
            </div>
        </div>

        <!-- Strava-like horizontal strip -->
        <div class="detail-strip" style="margin-top: 16px;">
            <div class="strip-item">
                <span class="strip-val"><?= $summary['duration_full'] ?></span>
                <span class="strip-lbl">Moving Time</span>
            </div>
            <div class="strip-item">
                <span class="strip-val"><?= $summary['avg_speed_kmh'] ?></span>
                <span class="strip-lbl">km/h</span>
            </div>
            <div class="strip-item">
                <span class="strip-val"><?= $summary['pace'] ?></span>
                <span class="strip-lbl">Pace /km</span>
            </div>
            <div class="strip-item">
                <span class="strip-val"><?= $summary['badges_count'] ?></span>
                <span class="strip-lbl">Badges</span>
            </div>
        </div>

        <!-- Stats grid -->
        <div class="stats-section" style="margin-top: 12px;">
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-box-icon"><i class="fas fa-route"></i></div>
                    <div class="stat-box-value"><?= $summary['distance_km'] ?> <span style="font-size:12px;font-family:'DM Sans',sans-serif;color:var(--ink-4);">km</span></div>
                    <div class="stat-box-label">Total Distance</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-box-value"><?= $summary['duration'] ?></div>
                    <div class="stat-box-label">Hike Duration</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-icon"><i class="fas fa-gauge-high"></i></div>
                    <div class="stat-box-value"><?= $summary['pace'] ?><span style="font-size:12px;font-family:'DM Sans',sans-serif;color:var(--ink-4);"> /km</span></div>
                    <div class="stat-box-label">Average Pace</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-icon"><i class="fas fa-bolt"></i></div>
                    <div class="stat-box-value"><?= $summary['avg_speed_kmh'] ?><span style="font-size:12px;font-family:'DM Sans',sans-serif;color:var(--ink-4);"> km/h</span></div>
                    <div class="stat-box-label">Avg Speed</div>
                </div>
                <div class="stat-box full-width badges-box">
                    <div class="stat-box-icon"><i class="fas fa-medal"></i></div>
                    <div class="stat-box-value"><?= $summary['badges_count'] ?> <span style="font-size:14px;font-family:'DM Sans',sans-serif;">badge<?= $summary['badges_count'] !== 1 ? 's' : '' ?></span></div>
                    <div class="stat-box-label">Earned This Hike</div>
                </div>
            </div>
        </div>

        <!-- Branding strip -->
        <div class="brand-strip">
            <span class="brand-logo">LAKBAY</span>
            <span class="brand-divider"></span>
            <span class="brand-tagline">Trail recorded with Lakbay</span>
            <span class="brand-icon">⛰</span>
        </div>

        <!-- Actions -->
        <div class="action-row">
            <button class="btn btn-outline" onclick="shareActivity()">
                <svg viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
                Share
            </button>
            <a href="bookings.php" class="btn btn-filled">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12l7-7 7 7"/></svg>
                Book Another Hike
            </a>
        </div>

    </div><!-- /activity-card -->

</div><!-- /page-wrap -->

<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── DATA FROM PHP ──────────────────────────────────────────
const trailCoords  = <?= json_encode($trailCoords) ?>;
const mountainName = <?= json_encode($summary['mountain']) ?>;
const distance     = <?= $summary['distance_km'] ?>;
const duration     = <?= json_encode($summary['duration']) ?>;
const durationFull = <?= json_encode($summary['duration_full']) ?>;
const pace         = <?= json_encode($summary['pace']) ?>;
const speed        = <?= $summary['avg_speed_kmh'] ?>;
const badgesCount  = <?= $summary['badges_count'] ?>;
const completedAt  = <?= json_encode($summary['completed_at']) ?>;
const completedTime= <?= json_encode($summary['completed_time']) ?>;

// ── MAP INIT ───────────────────────────────────────────────
function initMap() {
    const el = document.getElementById('activityMap');
    if (!trailCoords || trailCoords.length === 0) {
        el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9A9A90;font-size:13px;">No trail data available</div>';
        return;
    }

    const map = L.map('activityMap', {
        zoomControl: false,
        attributionControl: false,
        dragging: false,
        touchZoom: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false
    }).setView(trailCoords[0], 13);

    // Light topo tile — matches the warm, refined aesthetic
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    // Trail polyline — warm primary color
    L.polyline(trailCoords, {
        color: '#100600',
        weight: 3.5,
        opacity: 0.85,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    // Start marker
    const startIcon = L.divIcon({
        html: '<div style="background:#1B7045;width:11px;height:11px;border-radius:50%;border:2.5px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.2);"></div>',
        className: '', iconSize: [11, 11], iconAnchor: [5.5, 5.5]
    });
    L.marker(trailCoords[0], { icon: startIcon }).addTo(map);

    // End marker
    const endIcon = L.divIcon({
        html: '<div style="background:#B8312A;width:13px;height:13px;border-radius:50%;border:2.5px solid white;box-shadow:0 2px 8px rgba(184,49,42,0.4);"></div>',
        className: '', iconSize: [13, 13], iconAnchor: [6.5, 6.5]
    });
    L.marker(trailCoords[trailCoords.length - 1], { icon: endIcon }).addTo(map);

    map.fitBounds(L.latLngBounds(trailCoords).pad(0.12));
    setTimeout(() => map.invalidateSize(), 120);
}

// ── SHARE / SAVE (transparent background, IG-story ready) ──
async function shareActivity() {
    showToast('🎨 Crafting your share card…', 'info');

    const canvas = document.createElement('canvas');
    const ctx    = canvas.getContext('2d');
    const W = 420, H = 760;
    canvas.width = W; canvas.height = H;

    // Transparent background
    ctx.clearRect(0, 0, W, H);

    // ── Card background (rounded rect, warm white) ──
    const cardX = 0, cardY = 0, cardW = W, cardH = H, r = 0;
    ctx.beginPath();
    ctx.moveTo(cardX + r, cardY);
    ctx.lineTo(cardX + cardW - r, cardY);
    ctx.quadraticCurveTo(cardX + cardW, cardY, cardX + cardW, cardY + r);
    ctx.lineTo(cardX + cardW, cardY + cardH - r);
    ctx.quadraticCurveTo(cardX + cardW, cardY + cardH, cardX + cardW - r, cardY + cardH);
    ctx.lineTo(cardX + r, cardY + cardH);
    ctx.quadraticCurveTo(cardX, cardY + cardH, cardX, cardY + cardH - r);
    ctx.lineTo(cardX, cardY + r);
    ctx.quadraticCurveTo(cardX, cardY, cardX + r, cardY);
    ctx.closePath();
    ctx.fillStyle = '#FFFFFF';
    ctx.fill();

    // ── Hero panel (dark primary) ──
    ctx.fillStyle = '#100600';
    roundRect(ctx, 0, 0, W, 160, {tl: 0, tr: 0, bl: 0, br: 0});
    ctx.fill();

    // Subtle amber glow in hero
    const grd = ctx.createRadialGradient(W * 0.75, 160, 10, W * 0.75, 160, 180);
    grd.addColorStop(0, 'rgba(201,123,26,0.22)');
    grd.addColorStop(1, 'rgba(201,123,26,0)');
    ctx.fillStyle = grd;
    roundRect(ctx, 0, 0, W, 160, {tl: 0, tr: 0, bl: 0, br: 0});
    ctx.fill();

    // "Completed Hike" badge
    ctx.fillStyle = 'rgba(255,255,255,0.13)';
    roundRect(ctx, 24, 24, 118, 22, 11);
    ctx.fill();
    ctx.font = '500 10px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.8)';
    ctx.fillText('COMPLETED HIKE', 36, 39);

    // Date (top right)
    ctx.font = '400 11px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.4)';
    ctx.textAlign = 'right';
    ctx.fillText(completedAt, W - 24, 38);

    // Mountain name
    ctx.textAlign = 'left';
    ctx.font = '700 26px "DM Sans", sans-serif';
    ctx.fillStyle = '#FFFFFF';
    ctx.fillText('⛰ ' + mountainName, 24, 100);

    // Sub line
    ctx.font = '400 12px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.5)';
    ctx.fillText('Finished at ' + completedTime, 24, 124);

    // ── Trail map area ──
    const mapY = 160, mapH = 200;
    ctx.fillStyle = '#F4F2EF';
    ctx.fillRect(0, mapY, W, mapH);

    if (trailCoords && trailCoords.length > 1) {
        let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
        trailCoords.forEach(c => {
            minLat = Math.min(minLat, c[0]); maxLat = Math.max(maxLat, c[0]);
            minLng = Math.min(minLng, c[1]); maxLng = Math.max(maxLng, c[1]);
        });

        const pad = 36;
        const mW = W - pad * 2, mH = mapH - pad * 2;
        const latR = maxLat - minLat || 0.001;
        const lngR = maxLng - minLng || 0.001;
        const scale = Math.min(mW / lngR, mH / latR);
        const offsetX = pad + (mW - lngR * scale) / 2;
        const offsetY = mapY + pad + (mH - latR * scale) / 2;

        const toX = lng => offsetX + (lng - minLng) * scale;
        const toY = lat => offsetY + mapH - pad * 2 - (lat - minLat) * scale;

        // Trail shadow
        ctx.beginPath();
        trailCoords.forEach((c, i) => {
            const x = toX(c[1]), y = toY(c[0]);
            i === 0 ? ctx.moveTo(x, y + 2) : ctx.lineTo(x, y + 2);
        });
        ctx.strokeStyle = 'rgba(16,6,0,0.12)';
        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();

        // Trail line
        ctx.beginPath();
        trailCoords.forEach((c, i) => {
            const x = toX(c[1]), y = toY(c[0]);
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = '#100600';
        ctx.lineWidth = 3;
        ctx.stroke();

        // Start dot (green)
        const sx = toX(trailCoords[0][1]), sy = toY(trailCoords[0][0]);
        ctx.beginPath(); ctx.arc(sx, sy, 7, 0, Math.PI * 2);
        ctx.fillStyle = '#1B7045'; ctx.fill();
        ctx.beginPath(); ctx.arc(sx, sy, 3.5, 0, Math.PI * 2);
        ctx.fillStyle = 'white'; ctx.fill();

        // End dot (red)
        const ex = toX(trailCoords[trailCoords.length-1][1]);
        const ey = toY(trailCoords[trailCoords.length-1][0]);
        ctx.beginPath(); ctx.arc(ex, ey, 8, 0, Math.PI * 2);
        ctx.fillStyle = '#B8312A'; ctx.fill();
        ctx.beginPath(); ctx.arc(ex, ey, 4, 0, Math.PI * 2);
        ctx.fillStyle = 'white'; ctx.fill();
    }

    // Mountain label pill on map
    ctx.fillStyle = 'rgba(255,255,255,0.92)';
    roundRect(ctx, 18, mapY + mapH - 34, 130, 22, 11);
    ctx.fill();
    ctx.font = '600 10px "DM Sans", sans-serif';
    ctx.fillStyle = '#6B6B63';
    ctx.fillText(mountainName + ' Trail', 30, mapY + mapH - 18);

    // ── Primary stat ──
    const statY = mapY + mapH + 20;
    ctx.font = '500 54px "DM Mono", monospace';
    ctx.fillStyle = '#100600';
    ctx.textAlign = 'center';
    ctx.fillText(String(distance), W / 2 - 20, statY + 54);
    ctx.font = '600 16px "DM Sans", sans-serif';
    ctx.fillStyle = '#9A9A90';
    ctx.fillText('km', W / 2 + 48, statY + 54);
    ctx.font = '500 11px "DM Sans", sans-serif';
    ctx.fillStyle = '#9A9A90';
    ctx.fillText('DISTANCE HIKED', W / 2, statY + 74);

    // Divider
    ctx.fillStyle = '#ECEAE6';
    ctx.fillRect(40, statY + 88, W - 80, 1);

    // ── Horizontal stats strip ──
    const stripY = statY + 104;
    const stripItems = [
        { val: durationFull, lbl: 'Moving Time' },
        { val: speed + ' km/h', lbl: 'Avg Speed' },
        { val: pace + '/km', lbl: 'Pace' },
    ];
    const colW = (W - 80) / stripItems.length;
    stripItems.forEach((item, i) => {
        const cx = 40 + colW * i + colW / 2;
        ctx.font = '500 15px "DM Mono", monospace';
        ctx.fillStyle = '#100600';
        ctx.textAlign = 'center';
        ctx.fillText(item.val, cx, stripY + 18);
        ctx.font = '500 9px "DM Sans", sans-serif';
        ctx.fillStyle = '#9A9A90';
        ctx.fillText(item.lbl.toUpperCase(), cx, stripY + 33);

        if (i < stripItems.length - 1) {
            ctx.fillStyle = '#ECEAE6';
            ctx.fillRect(40 + colW * (i + 1), stripY, 1, 38);
        }
    });

    // ── Stat boxes grid ──
    const gridY = stripY + 58;
    const boxW = (W - 80 - 10) / 2, boxH = 72;
    const boxes = [
        { val: distance + ' km', lbl: 'Total Distance' },
        { val: duration, lbl: 'Hike Duration' },
        { val: pace + ' /km', lbl: 'Average Pace' },
        { val: speed + ' km/h', lbl: 'Avg Speed' },
    ];
    boxes.forEach((b, i) => {
        const col = i % 2, row = Math.floor(i / 2);
        const bx = 40 + col * (boxW + 10);
        const by = gridY + row * (boxH + 10);
        ctx.fillStyle = '#F4F2EF';
        roundRect(ctx, bx, by, boxW, boxH, 12);
        ctx.fill();
        ctx.font = '500 18px "DM Mono", monospace';
        ctx.fillStyle = '#100600';
        ctx.textAlign = 'left';
        ctx.fillText(b.val, bx + 12, by + 32);
        ctx.font = '600 9px "DM Sans", sans-serif';
        ctx.fillStyle = '#9A9A90';
        ctx.fillText(b.lbl.toUpperCase(), bx + 12, by + 52);
    });

    // Badges box (full width, amber)
    const badgeY = gridY + 2 * (boxH + 10);
    const badgeBoxW = W - 80;
    ctx.fillStyle = '#FDF3E0';
    roundRect(ctx, 40, badgeY, badgeBoxW, 60, 12);
    ctx.fill();
    ctx.font = '500 20px "DM Mono", monospace';
    ctx.fillStyle = '#C97B1A';
    ctx.textAlign = 'left';
    ctx.fillText(badgesCount + ' badge' + (badgesCount !== 1 ? 's' : '') + ' earned', 56, badgeY + 32);
    ctx.font = '600 9px "DM Sans", sans-serif';
    ctx.fillStyle = '#C97B1A';
    ctx.fillText('EARNED THIS HIKE', 56, badgeY + 50);

    // ── LAKBAY branding footer ──
    const footY = badgeY + 78;
    ctx.fillStyle = '#F0EDE8';
    ctx.fillRect(0, footY, W, H - footY);
    ctx.fillStyle = '#ECEAE6';
    ctx.fillRect(0, footY, W, 1);

    ctx.font = '800 15px "DM Sans", sans-serif';
    ctx.fillStyle = '#100600';
    ctx.textAlign = 'center';
    ctx.fillText('LAKBAY', W / 2 - 60, footY + 30);

    ctx.fillStyle = '#D4CEC5';
    ctx.fillRect(W / 2 - 30, footY + 18, 1, 16);

    ctx.font = '400 11px "DM Sans", sans-serif';
    ctx.fillStyle = '#9A9A90';
    ctx.fillText('Trail recorded with Lakbay', W / 2 + 20, footY + 30);

    // Convert to blob
    canvas.toBlob(async (blob) => {
        const mobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        if (mobile && navigator.share && navigator.canShare) {
            try {
                const file = new File([blob], 'lakbay_activity.png', { type: 'image/png' });
                await navigator.share({ title: 'My Lakbay Hike', text: `I conquered ${mountainName}!`, files: [file] });
                showToast('✨ Shared successfully!', 'success');
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
        const a = document.createElement('a');
        a.download = 'lakbay_activity.png';
        a.href = url; a.click();
        URL.revokeObjectURL(url);
        showToast('📸 Saved to downloads!', 'success');
    }
}

// ── HELPERS ────────────────────────────────────────────────
function roundRect(ctx, x, y, w, h, r) {
    if (typeof r === 'number') r = { tl: r, tr: r, br: r, bl: r };
    ctx.beginPath();
    ctx.moveTo(x + r.tl, y);
    ctx.lineTo(x + w - r.tr, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r.tr);
    ctx.lineTo(x + w, y + h - r.br);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r.br, y + h);
    ctx.lineTo(x + r.bl, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r.bl);
    ctx.lineTo(x, y + r.tl);
    ctx.quadraticCurveTo(x, y, x + r.tl, y);
    ctx.closePath();
}

let toastTimer;
function showToast(msg, type = 'info') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (type !== 'info' ? ' ' + type : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.className = 'toast'; }, 3200);
}

setTimeout(initMap, 150);
</script>
</body>
</html>