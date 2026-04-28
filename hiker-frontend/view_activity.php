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
    // Fetch the finished hike session
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
    
    // Get trail coordinates for the map
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
    
    // Get waypoints
    $waypoints = [];
    $stmt = $pdo->prepare("SELECT name, type, latitude, longitude, elevation FROM trail_waypoints WHERE mountain_id = ? AND is_active = 1 ORDER BY order_index ASC");
    $stmt->execute([$mountainId]);
    $waypoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse badges earned
    $badgesEarned = json_decode($session['badges_earned'], true) ?? [];
    
    // Format time
    $durationSec = $session['total_duration'];
    $hours = floor($durationSec / 3600);
    $mins = floor(($durationSec % 3600) / 60);
    $timeStr = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
    
    // Calculate pace
    $distance = $session['total_distance'];
    $paceMinutes = $distance > 0 ? ($durationSec / 60) / $distance : 0;
    $paceMinutesInt = (int)floor($paceMinutes);
    $paceSecondsInt = (int)round(($paceMinutes - $paceMinutesInt) * 60);
    $paceFormatted = $paceMinutesInt . ":" . str_pad((string)$paceSecondsInt, 2, "0", STR_PAD_LEFT);
    
    // Calculate avg speed
    $avgSpeed = $distance > 0 ? ($distance / ($durationSec / 3600)) : 0;
    
    $summary = [
        'mountain' => $session['mountain_name'],
        'distance_km' => round((float)$distance, 2),
        'duration' => $timeStr,
        'avg_speed_kmh' => round($avgSpeed, 1),
        'pace' => $paceFormatted,
        'badges_count' => count($badgesEarned),
        'completed_at' => date('F j, Y', strtotime($session['end_time']))
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
    <title>Activity — <?= htmlspecialchars($session['mountain_name']) ?> | LAKBAY</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --forest: #0f1f0f;
            --forest-mid: #1a2e1a;
            --mint: #00e5b4;
            --success: #06d6a0;
            --text-muted-dark: rgba(255,255,255,0.5);
            --radius-lg: 26px;
            --radius-sm: 12px;
        }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--forest);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .activity-card {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            background: #111d11;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px;
            padding: 32px 24px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--mint);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        .completion-emoji { font-size: 56px; text-align: center; margin-bottom: 16px; }
        .completion-title {
            font-family: 'Syne', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 6px;
        }
        .completion-sub {
            font-size: 13px;
            color: var(--text-muted-dark);
            text-align: center;
            margin-bottom: 20px;
        }
        
        #activityMap {
            height: 180px;
            width: 100%;
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
            background: #1a2e1a;
        }
        
        .completion-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .comp-stat {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-sm);
            padding: 14px 12px;
            text-align: center;
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
        .comp-stat.full-width {
            grid-column: span 2;
        }
        
        .lakbay-brand {
            margin: 16px 0;
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: center;
        }
        .lakbay-brand span {
            font-family: 'Syne', sans-serif;
            font-size: 18px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5b4, #06d6a0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .completion-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        .btn-secondary {
            flex: 1;
            padding: 12px 16px;
            border-radius: 100px;
            border: 1px solid rgba(255,255,255,0.15);
            background: transparent;
            color: rgba(255,255,255,0.65);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary {
            flex: 1;
            padding: 12px 16px;
            border-radius: 100px;
            border: none;
            background: linear-gradient(135deg, #00e5b4, #06d6a0);
            color: var(--forest);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }
        
        .leaflet-control-attribution { display: none !important; }
    </style>
</head>
<body>

<div class="activity-card">
    <a href="hikerProfile.php" class="back-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to Profile
    </a>
    
    <div class="completion-emoji">🏔️</div>
    <div class="completion-title"><?= htmlspecialchars($summary['mountain']) ?></div>
    <div class="completion-sub">Completed on <?= $summary['completed_at'] ?></div>
    
    <div id="activityMap"></div>
    
    <div class="completion-stats">
        <div class="comp-stat">
            <div class="comp-stat-val"><?= $summary['distance_km'] ?></div>
            <div class="comp-stat-label">km hiked</div>
        </div>
        <div class="comp-stat">
            <div class="comp-stat-val"><?= $summary['duration'] ?></div>
            <div class="comp-stat-label">duration</div>
        </div>
        <div class="comp-stat">
            <div class="comp-stat-val"><?= $summary['pace'] ?></div>
            <div class="comp-stat-label">pace /km</div>
        </div>
        <div class="comp-stat">
            <div class="comp-stat-val"><?= $summary['avg_speed_kmh'] ?></div>
            <div class="comp-stat-label">avg speed</div>
        </div>
        <div class="comp-stat full-width">
            <div class="comp-stat-val"><?= $summary['badges_count'] ?></div>
            <div class="comp-stat-label">badges earned</div>
        </div>
    </div>
    
    <div class="lakbay-brand">
        <span>LAKBAY</span> <span style="color: rgba(255,255,255,0.3);">|</span> <span style="font-size: 11px; color: var(--text-muted-dark);">Trail recorded with Lakbay</span>
    </div>
    
    <div class="completion-actions">
        <button class="btn-secondary" onclick="shareActivity()">Share</button>
        <a href="bookings.php" class="btn-primary">Book Another Hike</a>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const trailCoords = <?= json_encode($trailCoords) ?>;
const mountainName = <?= json_encode($summary['mountain']) ?>;
const distance = <?= $summary['distance_km'] ?>;
const duration = <?= json_encode($summary['duration']) ?>;
const pace = <?= json_encode($summary['pace']) ?>;
const speed = <?= $summary['avg_speed_kmh'] ?>;
const badgesCount = <?= $summary['badges_count'] ?>;
const completedDate = <?= json_encode($summary['completed_at']) ?>;

function initMap() {
    if (!trailCoords || trailCoords.length === 0) {
        document.getElementById('activityMap').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,0.5);">No trail data available</div>';
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
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);
    
    L.polyline(trailCoords, {
        color: '#00e5b4',
        weight: 4,
        opacity: 1,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);
    
    const startIcon = L.divIcon({
        html: '<div style="background: #00e5b4; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
        className: '',
        iconSize: [12, 12],
        iconAnchor: [6, 6]
    });
    L.marker(trailCoords[0], { icon: startIcon }).bindPopup('🏁 Start').addTo(map);
    
    const endIcon = L.divIcon({
        html: '<div style="background: #ffd700; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white;"></div>',
        className: '',
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });
    L.marker(trailCoords[trailCoords.length - 1], { icon: endIcon }).bindPopup('🏔️ Finish').addTo(map);
    
    map.fitBounds(L.latLngBounds(trailCoords).pad(0.1));
    setTimeout(() => map.invalidateSize(), 100);
}

async function shareActivity() {
    showToast("🎨 Creating your share image...", "info");
    
    // Create a canvas to draw the share image
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    // Set canvas size (fits Instagram story dimensions 9:16 ratio)
    canvas.width = 400;
    canvas.height = 710;
    
    // Background
    ctx.fillStyle = '#111d11';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Draw decorative top line
    ctx.fillStyle = '#00e5b4';
    ctx.fillRect(0, 0, canvas.width, 4);
    
    // Emoji
    ctx.font = '48px "Segoe UI Emoji", "Apple Color Emoji"';
    ctx.fillStyle = '#ffffff';
    ctx.fillText('🏔️', canvas.width/2 - 25, 70);
    
    // Title
    ctx.font = 'bold 22px "Syne", "Playfair Display", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.fillText(mountainName, canvas.width/2, 110);
    
    // Date
    ctx.font = '12px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.5)';
    ctx.fillText(`Completed on ${completedDate}`, canvas.width/2, 135);
    
    // Draw map area (simplified trail preview)
    ctx.fillStyle = '#1a2e1a';
    ctx.fillRect(20, 155, canvas.width - 40, 160);
    ctx.fillStyle = '#0f1f0f';
    ctx.fillRect(22, 157, canvas.width - 44, 156);
    
    // Draw trail on canvas
    if (trailCoords && trailCoords.length > 0) {
        // Calculate bounds
        let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
        trailCoords.forEach(c => {
            minLat = Math.min(minLat, c[0]);
            maxLat = Math.max(maxLat, c[0]);
            minLng = Math.min(minLng, c[1]);
            maxLng = Math.max(maxLng, c[1]);
        });
        
        const latRange = maxLat - minLat;
        const lngRange = maxLng - minLng;
        const padding = 30;
        const mapX = 30;
        const mapY = 165;
        const mapW = canvas.width - 60;
        const mapH = 140;
        
        ctx.beginPath();
        ctx.strokeStyle = '#00e5b4';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        
        for (let i = 0; i < trailCoords.length; i++) {
            const x = mapX + ((trailCoords[i][1] - minLng) / lngRange) * mapW;
            const y = mapY + mapH - ((trailCoords[i][0] - minLat) / latRange) * mapH;
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();
        
        // Draw start marker
        const startX = mapX + ((trailCoords[0][1] - minLng) / lngRange) * mapW;
        const startY = mapY + mapH - ((trailCoords[0][0] - minLat) / latRange) * mapH;
        ctx.fillStyle = '#00e5b4';
        ctx.beginPath();
        ctx.arc(startX, startY, 6, 0, 2 * Math.PI);
        ctx.fill();
        ctx.fillStyle = 'white';
        ctx.beginPath();
        ctx.arc(startX, startY, 3, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw end marker
        const endX = mapX + ((trailCoords[trailCoords.length - 1][1] - minLng) / lngRange) * mapW;
        const endY = mapY + mapH - ((trailCoords[trailCoords.length - 1][0] - minLat) / latRange) * mapH;
        ctx.fillStyle = '#ffd700';
        ctx.beginPath();
        ctx.arc(endX, endY, 7, 0, 2 * Math.PI);
        ctx.fill();
        ctx.fillStyle = 'white';
        ctx.beginPath();
        ctx.arc(endX, endY, 3, 0, 2 * Math.PI);
        ctx.fill();
    }
    
    // Stats grid
    const stats = [
        { label: 'km hiked', value: distance },
        { label: 'duration', value: duration },
        { label: 'pace /km', value: pace },
        { label: 'avg speed', value: speed + ' km/h' },
        { label: 'badges earned', value: badgesCount, fullWidth: true }
    ];
    
    let statY = 335;
    const statW = (canvas.width - 60) / 2;
    
    stats.forEach((stat, index) => {
        const isFullWidth = stat.fullWidth;
        const x = isFullWidth ? 20 : 20 + (index % 2) * (statW + 10);
        const w = isFullWidth ? canvas.width - 40 : statW;
        
        ctx.fillStyle = 'rgba(255,255,255,0.05)';
        ctx.fillRect(x, statY, w, 60);
        ctx.strokeStyle = 'rgba(255,255,255,0.08)';
        ctx.strokeRect(x, statY, w, 60);
        
        ctx.font = 'bold 20px "DM Mono", monospace';
        ctx.fillStyle = '#00e5b4';
        ctx.textAlign = 'center';
        ctx.fillText(String(stat.value), x + w/2, statY + 30);
        
        ctx.font = '9px "DM Sans", sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,0.5)';
        ctx.fillText(stat.label.toUpperCase(), x + w/2, statY + 50);
        
        if (index === 1) statY += 70;
        if (index === 3) statY += 70;
    });
    
    // LAKBAY branding
    const brandY = statY + 20;
    ctx.font = 'bold 16px "Syne", sans-serif';
    const gradient = ctx.createLinearGradient(0, brandY, 100, brandY);
    gradient.addColorStop(0, '#00e5b4');
    gradient.addColorStop(1, '#06d6a0');
    ctx.fillStyle = gradient;
    ctx.fillText('LAKBAY', canvas.width/2 - 30, brandY);
    
    ctx.font = '9px "DM Sans", sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.3)';
    ctx.fillText('|', canvas.width/2, brandY);
    
    ctx.fillStyle = 'rgba(255,255,255,0.4)';
    ctx.fillText('Trail recorded with Lakbay', canvas.width/2 + 10, brandY);
    
    // Convert to blob and share/copy
    canvas.toBlob(async (blob) => {
        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        
        if (isMobile && navigator.share && navigator.canShare && navigator.canShare({ files: [new File([], 'image.png')] })) {
            try {
                const file = new File([blob], 'lakbay_activity.png', { type: 'image/png' });
                await navigator.share({
                    title: 'My Lakbay Hike',
                    text: `I conquered ${mountainName}!`,
                    files: [file]
                });
                showToast("✨ Shared successfully!", "success");
            } catch (err) {
                if (err.name !== 'AbortError') {
                    await copyImageToClipboard(blob);
                }
            }
        } else {
            await copyImageToClipboard(blob);
        }
    }, 'image/png');
}

async function copyImageToClipboard(blob) {
    try {
        await navigator.clipboard.write([
            new ClipboardItem({
                [blob.type]: blob
            })
        ]);
        showToast("📸 Image copied to clipboard! Press Ctrl+V to paste.", "success");
    } catch (err) {
        // Fallback: download the image
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.download = 'lakbay_activity.png';
        link.href = url;
        link.click();
        URL.revokeObjectURL(url);
        showToast("📸 Image saved! Check your downloads folder.", "success");
    }
}

function showToast(msg, type = 'info') {
    let toast = document.getElementById('shareToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'shareToast';
        toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1a2e1a;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            z-index: 10000;
            transition: all 0.3s ease;
            opacity: 0;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0,229,180,0.3);
            white-space: nowrap;
        `;
        document.body.appendChild(toast);
    }
    
    toast.textContent = msg;
    toast.style.background = type === 'success' ? '#2e7d32' : (type === 'error' ? '#c62828' : '#1a2e1a');
    toast.style.transform = 'translateX(-50%) translateY(0)';
    toast.style.opacity = '1';
    
    setTimeout(() => {
        toast.style.transform = 'translateX(-50%) translateY(100px)';
        toast.style.opacity = '0';
    }, 3000);
}

setTimeout(initMap, 100);
</script>
</body>
</html>