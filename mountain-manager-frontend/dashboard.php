<?php
// dashboard.php - Complete version with Mountain Maps
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$manager_id = $_SESSION['user_id'];

// Get manager's assigned mountains with all details
$stmt = $pdo->prepare("
    SELECT m.* 
    FROM mountains m
    INNER JOIN manager_mountains mm ON m.id = mm.mountain_id
    WHERE mm.manager_id = ?
    ORDER BY m.name
");
$stmt->execute([$manager_id]);
$assigned_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mountain_ids = array_column($assigned_mountains, 'id');


// Get bookings for assigned mountains only
$bookings = [];
if (!empty($mountain_ids)) {
    $ids_placeholder = implode(',', array_fill(0, count($mountain_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            m.name as mountain_name,
            u.name as guide_name,
            u.id as guide_user_id
        FROM bookings b
        INNER JOIN mountains m ON b.mountain_id = m.id
        LEFT JOIN users u ON b.guide_id = u.id
        WHERE b.mountain_id IN ($ids_placeholder)
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

// Get trail tracks for each mountain
$trailData = [];
foreach ($assigned_mountains as $mountain) {
    $fileIdMap = [1 => 'BATULAO', 2 => 'APAYANG', 3 => 'LANTIK', 4 => 'TALAMITAM'];
    $fileId = $fileIdMap[$mountain['id']] ?? null;
    
    $trackPoints = [];
    if ($fileId) {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE fileId = ? ORDER BY idx ASC");
        $stmt->execute([$fileId]);
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($trackPoints)) {
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE mountain_id = ? ORDER BY idx ASC");
        $stmt->execute([$mountain['id']]);
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $coordinates = [];
    foreach ($trackPoints as $p) {
        $coordinates[] = [(float)$p['lon'], (float)$p['lat']];
    }
    $trailData[$mountain['id']] = [
        'type' => 'LineString',
        'coordinates' => $coordinates
    ];
}

// Get waypoints for each mountain
$waypointsData = [];
foreach ($assigned_mountains as $mountain) {
    $stmt = $pdo->prepare("
        SELECT id, name, type, latitude, longitude, elevation, description, order_index 
        FROM trail_waypoints 
        WHERE mountain_id = ? AND is_active = 1
        ORDER BY order_index ASC, id ASC
    ");
    $stmt->execute([$mountain['id']]);
    $waypointsData[$mountain['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Format mountains for JavaScript
$mountains_json = json_encode(array_map(function($m) {
    return [
        'id' => $m['id'],
        'name' => $m['name'],
        'description' => $m['description'],
        'location' => $m['location'],
        'elevation' => $m['elevation'],
        'difficulty' => $m['difficulty'],
        'image' => $m['image'],
        'jumpOff' => $m['jumpOff'],
        'duration' => $m['duration'],
        'fees' => [
            'reg' => (int)$m['registration_fee'],
            'env' => (int)$m['environmental_fee']
        ],
        'trail_length_km' => (float)($m['trail_length_km'] ?? 0),
        'estimated_duration' => (float)($m['estimated_duration'] ?? 0),
        'start_point_lat' => (float)$m['start_point_lat'],
        'start_point_lng' => (float)$m['start_point_lng'],
        'crowdLevel' => $m['crowdLevel'] ?? 'Low',
        'weatherAdvisory' => $m['weatherAdvisory'],
        'peakTimes' => $m['peakTimes'],
        'rules' => json_decode($m['rules'] ?? '[]', true),
        'status' => $m['status']
    ];
}, $assigned_mountains));

// Format bookings for JavaScript
$bookings_json = json_encode(array_map(function($b) {
    return [
        'id' => $b['id'],
        'bookingNumber' => $b['booking_number'],
        'mountainId' => $b['mountain_id'],
        'mountain' => $b['mountain_name'],
        'guideId' => $b['guide_id'],
        'guideName' => $b['guide_name'] ?? 'Unassigned',
        'date' => $b['hike_date'],
        'pax' => (int)$b['number_of_hikers'],
        'totalFee' => (float)($b['total_amount'] ?? 0),
        'status' => $b['status'],
        'paymentStatus' => $b['payment_status']
    ];
}, $bookings));
// Get crowd reports for manager's mountains
$crowdReports = [];
if (!empty($mountain_ids)) {
    $ids_placeholder = implode(',', array_fill(0, count($mountain_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT 
            cr.*,
            m.name as mountain_name,
            u.name as reporter_name,
            u.role as reporter_role
        FROM crowd_reports cr
        INNER JOIN mountains m ON cr.mountain_id = m.id
        LEFT JOIN users u ON cr.reported_by = u.id
        WHERE cr.mountain_id IN ($ids_placeholder)
        ORDER BY cr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($mountain_ids);
    $crowdReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Format crowd reports for JavaScript
$crowdReports_json = json_encode(array_map(function($report) {
    return [
        'id' => $report['id'],
        'mountain_name' => $report['mountain_name'],
        'crowd_level' => $report['crowd_level'],
        'location_lat' => isset($report['latitude']) && $report['latitude'] ? floatval($report['latitude']) : null,
        'location_lng' => isset($report['longitude']) && $report['longitude'] ? floatval($report['longitude']) : null,
        'reporter_name' => $report['reporter_name'] ?? 'System',
        'reporter_role' => $report['reporter_role'] ?? 'auto',
        'reported_at' => $report['created_at'],  // Using created_at instead of reported_at
        'notes' => $report['notes'] ?? ''
    ];
}, $crowdReports));
// Manager info
$manager_name = $manager['name'];
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));

$manager_role = ucfirst($manager['role']);

// Get real statistics for manager's mountains
$stats = [];

// Total bookings
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE mountain_id IN ($ids_placeholder)");
$stmt->execute($mountain_ids);
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Completed hikes
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE mountain_id IN ($ids_placeholder) AND status = 'completed'");
$stmt->execute($mountain_ids);
$stats['completed_hikes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending responses
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE mountain_id IN ($ids_placeholder) AND status = 'pending'");
$stmt->execute($mountain_ids);
$stats['pending_responses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Expected revenue (from confirmed and active bookings)
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM bookings WHERE mountain_id IN ($ids_placeholder) AND status IN ('confirmed', 'active', 'completed')");
$stmt->execute($mountain_ids);
$stats['expected_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total hikers (from all non-cancelled bookings)
$stmt = $pdo->prepare("SELECT SUM(number_of_hikers) as total FROM bookings WHERE mountain_id IN ($ids_placeholder) AND status != 'cancelled'");
$stmt->execute($mountain_ids);
$stats['total_hikers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Peak booking hour
$stmt = $pdo->prepare("
    SELECT HOUR(created_at) as hour, COUNT(*) as count 
    FROM bookings 
    WHERE mountain_id IN ($ids_placeholder) 
    GROUP BY HOUR(created_at) 
    ORDER BY count DESC 
    LIMIT 1
");
$stmt->execute($mountain_ids);
$peakHour = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['peak_booking_hour'] = $peakHour ? date('g:i A', strtotime($peakHour['hour'] . ':00')) : 'N/A';

// Today's bookings
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM bookings 
    WHERE mountain_id IN ($ids_placeholder) 
    AND DATE(created_at) = CURDATE()
");
$stmt->execute($mountain_ids);
$stats['today_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Most popular mountain
$stmt = $pdo->prepare("
    SELECT m.name, COUNT(b.id) as booking_count 
    FROM mountains m
    INNER JOIN bookings b ON m.id = b.mountain_id
    WHERE m.id IN ($ids_placeholder)
    GROUP BY m.id
    ORDER BY booking_count DESC
    LIMIT 1
");
$stmt->execute($mountain_ids);
$popularMountain = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['popular_mountain'] = $popularMountain['name'] ?? 'N/A';

// Average hikers per booking
$stmt = $pdo->prepare("
    SELECT AVG(number_of_hikers) as avg_hikers 
    FROM bookings 
    WHERE mountain_id IN ($ids_placeholder) AND status != 'cancelled'
");
$stmt->execute($mountain_ids);
$stats['avg_hikers'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_hikers'] ?? 0, 1);

// Generate AI insights based on data
$aiInsights = [];

// Peak time insight
$insight = "Based on historical data, your peak booking hour is around {$stats['peak_booking_hour']}. ";
$insight .= "Consider being most responsive during this time to maximize conversions.";
$aiInsights[] = $insight;

// Popular mountain insight
if ($stats['popular_mountain'] != 'N/A') {
    $insight = "{$stats['popular_mountain']} is your most-booked mountain with the highest demand. ";
    $insight .= "Consider promoting special packages or allocating more guides here.";
    $aiInsights[] = $insight;
}

// Booking prediction
$avgDaily = round($stats['total_bookings'] / 30, 1); // Average over last 30 days
$insight = "You're averaging {$avgDaily} bookings per day. ";
if ($stats['today_bookings'] < $avgDaily) {
    $insight .= "Today has {$stats['today_bookings']} bookings so far, which is below average. ";
    $insight .= "Consider running a promotion to boost tonight's bookings.";
} elseif ($stats['today_bookings'] > $avgDaily) {
    $insight .= "Great! Today already has {$stats['today_bookings']} bookings, above your daily average of {$avgDaily}. ";
    $insight .= "Keep up the momentum!";
} else {
    $insight .= "Today's booking volume ({$stats['today_bookings']}) is on par with your average.";
}
$aiInsights[] = $insight;

// Completion rate insight
$completionRate = $stats['total_bookings'] > 0 ? round(($stats['completed_hikes'] / $stats['total_bookings']) * 100) : 0;
$insight = "Your completion rate is {$completionRate}% with {$stats['completed_hikes']} completed hikes out of {$stats['total_bookings']} total bookings. ";
if ($completionRate < 50) {
    $insight .= "There's room for improvement - consider following up with pending bookings.";
} elseif ($completionRate > 80) {
    $insight .= "Excellent completion rate! Your operations are running smoothly.";
} else {
    $insight .= "Good completion rate, keep maintaining quality service.";
}
$aiInsights[] = $insight;

// Random tip rotation
$tips = [
    "Tip: Respond to pending bookings within 2 hours to increase confirmation rates by 40%.",
    "Tip: Early morning (6-8 AM) and late afternoon (4-6 PM) are the most active booking times.",
    "Tip: Consider offering group discounts to increase average hikers per booking (currently {$stats['avg_hikers']}).",
    "Tip: Send weather updates to confirmed bookings to reduce last-minute cancellations."
];
$aiInsights[] = $tips[array_rand($tips)];

// Actual collected revenue (from paid bookings)
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM bookings WHERE mountain_id IN ($ids_placeholder) AND payment_status = 'paid'");
$stmt->execute($mountain_ids);
$stats['actual_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Default images
$defaultImages = [
    1 => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=80',
    2 => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=80',
    3 => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&q=80',
    4 => 'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=600&q=80'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY Manager — Dashboard</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

<link rel="stylesheet" href="manager.css">
<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mainArea = document.getElementById('mainArea');
  if (sidebar) {
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle('mobile-open');
    } else {
      sidebar.classList.toggle('collapsed');
      if (mainArea) mainArea.classList.toggle('expanded');
    }
  }
}
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Dashboard specific styles */
.hero-banner {
  background: var(--ink);
  border-radius: var(--r);
  padding: 28px 32px;
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 24px;
  position: relative;
  overflow: hidden;
}
.hero-banner::before {
  content: '';
  position: absolute;
  right: -40px; top: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(201,168,76,0.08);
}
.hero-banner::after {
  content: '';
  position: absolute;
  right: 120px; bottom: -60px;
  width: 140px; height: 140px;
  border-radius: 50%;
  background: rgba(255,255,255,0.03);
}
.hero-greeting { font-size: 12px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
.hero-name { font-family: 'Playfair Display', serif; font-size: clamp(20px,3vw,28px); font-weight: 700; color: var(--white); margin-bottom: 10px; }
.hero-mountains { display: flex; gap: 8px; flex-wrap: wrap; }
.hero-mtn-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 50px; padding: 6px 14px;
  font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.85);
  backdrop-filter: blur(4px);
}
.hero-mtn-badge svg { width: 12px; height: 12px; stroke: var(--gold); stroke-width: 2; }
.hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; position: relative; z-index: 1; }
.hero-date { font-family: 'DM Mono', monospace; font-size: 11px; color: rgba(255,255,255,0.4); }
.hero-quick-stat { text-align: right; }
.hero-qs-val { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: var(--gold); line-height: 1; }
.hero-qs-lbl { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 2px; letter-spacing: .8px; }
@media (max-width: 600px) { .hero-banner { grid-template-columns: 1fr; } .hero-right { align-items: flex-start; } }

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 20px; margin-bottom: 24px; }
.stat-card { background: var(--white); border-radius: var(--r); border: 1px solid var(--border); padding: 20px; display: flex; align-items: center; gap: 16px; transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon.ink { background: var(--ink); color: white; }
.stat-icon.amber { background: var(--amber-bg); color: var(--amber); }
.stat-icon.green { background: var(--green-bg); color: var(--green); }
.stat-icon.gold { background: rgba(201,168,76,0.12); color: var(--gold); }
.stat-icon svg { width: 22px; height: 22px; stroke: currentColor; }
.stat-val { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; line-height: 1; }
.stat-label { font-size: 11px; color: var(--ink3); margin-top: 4px; }

.two-col { display: grid; grid-template-columns: 1fr 0.9fr; gap: 24px; margin-top: 0; }
@media (max-width: 1024px) { .two-col { grid-template-columns: 1fr; } }

/* Mountain Cards */
.mountain-cards-grid {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.mountain-card {
  background: var(--white);
  border-radius: var(--r);
  border: 1px solid var(--border);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.mountain-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}
.mountain-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(16,6,0,0.02), rgba(16,6,0,0.04));
  border-bottom: 1px solid var(--border);
  cursor: pointer;
}
.mountain-card-header:hover {
  background: linear-gradient(135deg, rgba(16,6,0,0.04), rgba(16,6,0,0.06));
}
.mountain-title {
  display: flex;
  align-items: baseline;
  gap: 12px;
  flex-wrap: wrap;
}
.mountain-title h3 {
  font-family: 'Playfair Display', serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}
.mountain-badges {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.difficulty-badge {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}
.difficulty-Easy { background: #e8f5e9; color: #2e7d32; }
.difficulty-Moderate { background: #fff8e1; color: #f57f17; }
.difficulty-Hard { background: #fce4ec; color: #c62828; }
.crowd-badge {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
}
.crowd-Low { background: rgba(27,112,69,0.15); color: #1B7045; }
.crowd-Medium { background: rgba(201,123,26,0.15); color: #C97B1A; }
.crowd-High { background: rgba(184,49,42,0.15); color: #B8312A; }
.mountain-header-actions { display: flex; gap: 8px; }
.btn-icon {
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 0.75rem;
  color: var(--ink3);
  transition: all 0.15s;
}
.btn-icon:hover {
  background: var(--ink);
  color: white;
  border-color: var(--ink);
}
.mountain-preview {
  display: flex;
  gap: 20px;
  padding: 16px 20px;
  background: var(--off);
  border-bottom: 1px solid var(--border);
}
.mountain-preview-img {
  width: 80px;
  height: 80px;
  border-radius: 12px;
  background-size: cover;
  background-position: center;
  flex-shrink: 0;
}
.mountain-preview-stats {
  flex: 1;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}
.preview-stat { text-align: center; }
.preview-stat-val {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  font-family: 'DM Mono', monospace;
}
.preview-stat-label {
  font-size: 9px;
  color: var(--ink3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.mountain-card-body {
  padding: 0 20px 20px 20px;
  display: none;
}
.mountain-card-body.expanded { display: block; }

/* Map Container */
.mountain-map-container {
  margin: 16px 0;
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border);
}
.mountain-map {
  height: 400px;
  width: 100%;
  background: #1a1208;
}
.map-controls {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 10;
  display: flex;
  gap: 8px;
}
.map-btn {
  background: rgba(255,255,255,0.95);
  border: none;
  border-radius: 8px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 0.7rem;
  font-weight: 600;
  backdrop-filter: blur(4px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  transition: all 0.15s;
}
.map-btn:hover {
  background: var(--ink);
  color: white;
}
.map-legend {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background: rgba(255,255,255,0.95);
  border-radius: 10px;
  padding: 8px 12px;
  font-size: 0.6rem;
  backdrop-filter: blur(4px);
  z-index: 10;
}
.map-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 3px;
}
.legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.legend-dot.trail { background: #100600; }
.legend-dot.waypoint { background: #c9a84c; }
.legend-dot.start { background: #1ABC9C; }

/* Waypoint Marker Styles */
.waypoint-marker {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.1s ease;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}
.waypoint-marker:hover { transform: scale(1.15); }
.waypoint-marker i { font-size: 22px; }
.summit-marker i { color: #E74C3C; text-shadow: 0 0 4px rgba(231,76,60,0.3); }
.campsite-marker i { color: #F39C12; }
.viewpoint-marker i { color: #3498DB; }
.information-marker i { color: #1ABC9C; }
.peak-marker i { color: #2ECC71; }
.danger-marker i { color: #E74C3C; }
.rest-marker i { color: #E67E22; }
.start-marker i { color: #1ABC9C; }
.default-marker i { color: #95A5A6; }

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
}
.popup-coords {
  font-size: 0.6rem;
  color: #999;
  margin-top: 8px;
  padding-top: 5px;
  border-top: 1px solid rgba(0,0,0,0.05);
  font-family: monospace;
}

.mountain-details {
  margin-top: 16px;
  background: var(--off);
  border-radius: 12px;
  padding: 16px;
}
.mountain-details h4 {
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 12px;
  color: var(--ink);
}
.detail-row {
  display: flex;
  margin-bottom: 10px;
  font-size: 13px;
}
.detail-label {
  width: 120px;
  font-weight: 600;
  color: var(--ink3);
  flex-shrink: 0;
}
.detail-value { color: var(--ink); flex: 1; }
.detail-list {
  list-style: none;
  padding-left: 0;
  margin: 0;
}
.detail-list li {
  padding: 3px 0;
  font-size: 12px;
  color: var(--ink2);
}
.detail-list li:before {
  content: "•";
  color: var(--gold);
  font-weight: bold;
  display: inline-block;
  width: 1em;
  margin-left: -1em;
}

/* Panel styles */
.panel {
  background: var(--white);
  border-radius: var(--r);
  border: 1px solid var(--border);
  margin-bottom: 20px;
  overflow: hidden;
}
.panel-hdr {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: var(--off);
  border-bottom: 1px solid var(--border);
}
.panel-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}
.panel-title svg { width: 18px; height: 18px; stroke: currentColor; }
.panel-body { padding: 16px 20px; }
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}
.badge-pending { background: #fff8e1; color: #f57f17; }
.badge-confirmed { background: #e8f5e9; color: #2e7d32; }
.badge-active { background: #e8f5e9; color: #2e7d32; }
.badge-completed { background: #e8eaf6; color: #283593; }
.badge-cancelled { background: #fce4ec; color: #c62828; }
.btn-outline {
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 30px;
  padding: 5px 12px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  color: var(--ink);
  transition: all 0.15s;
}
.btn-outline:hover {
  background: var(--ink);
  color: white;
  border-color: var(--ink);
}
.btn-sm { padding: 4px 10px; font-size: 10px; }

.collection-section {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}
.collection-ring { flex-shrink: 0; position: relative; }
.ring-wrap { position: relative; width: 80px; height: 80px; }
.ring-val {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-family: 'DM Mono', monospace;
  font-size: 18px;
  font-weight: 700;
}
.collection-breakdown { flex: 1; }
.collection-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 7px 0;
  border-bottom: 1px solid var(--border);
  font-size: 12px;
}
.collection-row:last-child { border-bottom: none; }
.collection-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 8px;
}

.booking-mini {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}
.booking-mini:last-child { border-bottom: none; }
.booking-mini-num {
  font-family: 'DM Mono', monospace;
  font-size: 10px;
  font-weight: 700;
  color: var(--ink);
  background: var(--off);
  border-radius: 6px;
  padding: 3px 8px;
  flex-shrink: 0;
}
.booking-mini-info { flex: 1; min-width: 0; }
.booking-mini-mountain {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.booking-mini-meta {
  font-size: 10px;
  color: var(--ink3);
  margin-top: 1px;
}
.booking-mini-right { text-align: right; flex-shrink: 0; }
.booking-mini-fee {
  font-size: 11px;
  font-weight: 700;
  color: var(--ink);
  margin-top: 2px;
}

.quick-actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.qa-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 14px 8px;
  background: var(--off);
  border-radius: 12px;
  border: 1.5px solid var(--border);
  cursor: pointer;
  transition: all .15s;
  text-decoration: none;
  color: var(--ink);
  text-align: center;
}
.qa-btn:hover {
  background: var(--ink);
  color: var(--white);
  border-color: var(--ink);
  transform: translateY(-2px);
}
.qa-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--white);
  display: flex;
  align-items: center;
  justify-content: center;
}
.qa-icon svg {
  width: 16px;
  height: 16px;
  stroke: var(--ink);
  stroke-width: 1.8;
}
.qa-btn:hover .qa-icon svg { stroke: var(--white); }
.qa-label { font-size: 10px; font-weight: 600; line-height: 1.3; }

/* Edit Modal */
.modal-bg {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(8px);
  z-index: 1000;
  display: none;
  align-items: center;
  justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
  background: var(--white);
  border-radius: 24px;
  width: 90%;
  max-width: 700px;
  max-height: 85vh;
  overflow-y: auto;
}
.modal-hdr {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: var(--white);
}
.modal-title {
  font-family: 'Playfair Display', serif;
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
}
.modal-close {
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: var(--ink3);
}
.modal-body { padding: 24px; }
.form-group { margin-bottom: 16px; }
.form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink3);
  margin-bottom: 6px;
}
.form-input, .form-select, .form-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: inherit;
  font-size: 13px;
  transition: border-color 0.15s;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none;
  border-color: var(--gold);
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}
.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  position: sticky;
  bottom: 0;
  background: var(--white);
}
.btn {
  padding: 10px 20px;
  border-radius: 30px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.15s;
}
.btn-primary {
  background: var(--ink);
  color: white;
}
.btn-primary:hover {
  background: #2a1a0a;
  transform: translateY(-1px);
}
.btn-outline {
  background: transparent;
  border: 1.5px solid var(--border);
  color: var(--ink);
}
.btn-outline:hover { background: var(--off); }

.toast {
  position: fixed;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%) translateY(100px);
  background: var(--ink);
  color: white;
  padding: 12px 24px;
  border-radius: 50px;
  font-size: 13px;
  font-weight: 600;
  z-index: 1100;
  transition: all 0.3s;
  opacity: 0;
  white-space: nowrap;
}
.toast.show {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
}

/* Add to existing styles in dashboard.php */
.hiker-marker {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    cursor: pointer;
}
.hiker-marker-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid white;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.hiker-marker-avatar.active {
    border-color: #1B7045;
    animation: pulse-green 2s infinite;
}
.hiker-marker-avatar.stale {
    border-color: #C97B1A;
}
.hiker-marker-name {
    background: rgba(16,6,0,0.8);
    color: white;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 0.5rem;
    font-weight: 600;
    white-space: nowrap;
    backdrop-filter: blur(4px);
}

.guide-marker {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.guide-marker-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #100600;
    border: 3px solid #c9a84c;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.guide-marker-label {
    background: #100600;
    color: #c9a84c;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 0.5rem;
    font-weight: 700;
    white-space: nowrap;
}

@keyframes pulse-green {
    0%, 100% { box-shadow: 0 0 0 0 rgba(27,112,69,0.4); }
    50% { box-shadow: 0 0 0 8px rgba(27,112,69,0); }
}

.tracking-panel {
    background: var(--white);
    border-radius: var(--r);
    border: 1px solid var(--border);
    margin-top: 16px;
    overflow: hidden;
}
.tracking-panel-header {
    padding: 12px 16px;
    background: var(--off);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tracking-panel-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}
.hiker-list {
    max-height: 300px;
    overflow-y: auto;
}
.hiker-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.hiker-item:hover {
    background: var(--off);
}
.hiker-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}
.hiker-avatar.active {
    border: 2px solid #1B7045;
}
.hiker-info {
    flex: 1;
    min-width: 0;
}
.hiker-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ink);
}
.hiker-booking {
    font-size: 0.65rem;
    color: var(--ink3);
}
.hiker-status {
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
    flex-shrink: 0;
}
.status-active {
    background: rgba(27,112,69,0.15);
    color: #1B7045;
}
.status-stale {
    background: rgba(201,123,26,0.15);
    color: #C97B1A;
}
.status-off {
    background: rgba(0,0,0,0.08);
    color: #888;
}
.hiker-location {
    font-size: 0.55rem;
    color: var(--ink3);
    font-family: monospace;
    margin-top: 2px;
}
/* Fix map controls z-index - add near your existing .map-controls styles */
.mountain-map-container {
    position: relative;
    z-index: 1;
}

.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000 !important;  /* Increased z-index */
    display: flex;
    gap: 8px;
}

.map-legend {
    position: absolute;
    bottom: 10px;
    right: 10px;
    z-index: 1000 !important;  /* Increased z-index */
    background: rgba(255,255,255,0.95);
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 0.6rem;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* Ensure leaflet controls don't overlap */
.leaflet-control-container .leaflet-top,
.leaflet-control-container .leaflet-bottom {
    z-index: 500;
}
.mountain-details {
    margin-top: 20px;
    background: linear-gradient(135deg, #faf9f7 0%, #f5f3ef 100%);
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.06);
}

.mountain-details-header {
    background: linear-gradient(135deg, #100600 0%, #2a1a0a 100%);
    padding: 16px 20px;
    color: white;
}

.mountain-details-header h4 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: #c9a84c;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mountain-details-header h4 i {
    font-size: 1.1rem;
    color: #c9a84c;
}

.mountain-details-content {
    padding: 20px;
}

.mountain-description {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    border-left: 3px solid #c9a84c;
    line-height: 1.6;
    font-size: 0.85rem;
    color: #444;
}

.mountain-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card-simple {
    background: white;
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card-simple:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-icon-simple {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f0ede8, #e8e4df);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon-simple i {
    font-size: 1.1rem;
    color: #100600;
}

.stat-info-simple {
    flex: 1;
}

.stat-label-simple {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #999;
    margin-bottom: 4px;
}

.stat-value-simple {
    font-size: 1rem;
    font-weight: 700;
    color: #100600;
    font-family: 'DM Mono', monospace;
}

.stat-unit-simple {
    font-size: 0.7rem;
    font-weight: 400;
    color: #999;
}

.info-section {
    margin-bottom: 16px;
}

.info-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #c9a84c;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-section-title i {
    font-size: 0.8rem;
}

.info-card {
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid rgba(0,0,0,0.05);
    margin-bottom: 12px;
}

.info-card p {
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.5;
    color: #555;
}

.rules-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.rules-list li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 0;
    font-size: 0.8rem;
    color: #555;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.rules-list li:last-child {
    border-bottom: none;
}

.rules-list li i {
    color: #c9a84c;
    font-size: 0.7rem;
    margin-top: 3px;
    flex-shrink: 0;
}

.badge-custom {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    background: rgba(201,168,76,0.12);
    color: #c9a84c;
}

.badge-custom i {
    font-size: 0.65rem;
}

/* Crowd Reports Panel Styles */
.crowd-reports-panel {
    background: var(--white);
    border-radius: var(--r);
    border: 1px solid var(--border);
    margin-bottom: 20px;
    overflow: hidden;
}

.crowd-report-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
    cursor: pointer;
}

.crowd-report-item:hover {
    background: var(--off);
}

.crowd-report-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
}

.crowd-report-icon.level-Low {
    background: rgba(27,112,69,0.15);
    color: #1B7045;
}

.crowd-report-icon.level-Medium {
    background: rgba(201,123,26,0.15);
    color: #C97B1A;
}

.crowd-report-icon.level-High {
    background: rgba(184,49,42,0.15);
    color: #B8312A;
}

.crowd-report-content {
    flex: 1;
    min-width: 0;
}

.crowd-report-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 6px;
}

.crowd-report-mountain {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--ink);
}

.crowd-report-badge {
    font-size: 0.65rem;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
}

.crowd-report-badge.Low {
    background: rgba(27,112,69,0.15);
    color: #1B7045;
}

.crowd-report-badge.Medium {
    background: rgba(201,123,26,0.15);
    color: #C97B1A;
}

.crowd-report-badge.High {
    background: rgba(184,49,42,0.15);
    color: #B8312A;
}

.crowd-report-details {
    font-size: 0.7rem;
    color: var(--ink3);
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 4px;
}

.crowd-report-details i {
    width: 14px;
    font-size: 0.65rem;
}

.crowd-report-reporter {
    display: flex;
    align-items: center;
    gap: 4px;
}

.crowd-report-time {
    display: flex;
    align-items: center;
    gap: 4px;
}

.crowd-report-location {
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    color: var(--gold);
}

.crowd-report-location:hover {
    text-decoration: underline;
}

.crowd-report-notes {
    font-size: 0.68rem;
    color: var(--ink2);
    margin-top: 6px;
    padding-top: 4px;
    border-top: 1px dashed rgba(0,0,0,0.05);
}

.empty-state {
    padding: 30px 20px;
    text-align: center;
    color: var(--ink3);
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}
/* Scrollable crowd reports list */
.crowd-reports-list {
    max-height: 400px;
    overflow-y: auto;
}

/* Custom scrollbar styling */
.crowd-reports-list::-webkit-scrollbar {
    width: 4px;
}

.crowd-reports-list::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
    border-radius: 4px;
}

.crowd-reports-list::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 4px;
}

.crowd-reports-list::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.3);
}

/* AI Insights Panel */
.ai-insights-panel {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: var(--r);
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.ai-insights-panel .panel-hdr {
    background: rgba(255,255,255,0.15);
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.ai-insights-panel .panel-title {
    color: white;
}

.ai-insights-panel .panel-title i {
    color: #FFD700;
}

.ai-badge {
    background: rgba(255,255,255,0.2);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    color: white;
}

.ai-insights-list {
    padding: 16px 20px;
}

.ai-insight-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    color: white;
    font-size: 0.85rem;
    line-height: 1.5;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.ai-insight-item:last-child {
    border-bottom: none;
}

.ai-insight-item i {
    color: #FFD700;
    font-size: 1rem;
    margin-top: 2px;
    flex-shrink: 0;
}

/* Stats container spacing */
.stats-container {
    margin-bottom: 24px;
}

/* Analytics Insights Panel - Matching design */
.analytics-panel {
    background: var(--white);
    border-radius: var(--r);
    border: 1px solid var(--border);
    margin-bottom: 20px;
    overflow: hidden;
}

.analytics-panel .panel-hdr {
    background: var(--off);
}

.analytics-insights-list {
    max-height: 300px;
    overflow-y: auto;
    padding: 0;
}

.analytics-insight-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 0.8rem;
    line-height: 1.5;
    color: var(--ink2);
    transition: background 0.15s;
}

.analytics-insight-item:hover {
    background: var(--off);
}

.analytics-insight-item:last-child {
    border-bottom: none;
}

.analytics-insight-item i {
    color: var(--gold);
    font-size: 1rem;
    margin-top: 2px;
    flex-shrink: 0;
}

/* Custom scrollbar for analytics panel */
.analytics-insights-list::-webkit-scrollbar {
    width: 4px;
}

.analytics-insights-list::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
    border-radius: 4px;
}

.analytics-insights-list::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 4px;
}

</style>
</head>
<body>
<div class="app-shell">

<?php $activePage = 'dashboard'; ?>
<?php include 'shared_sidebar.php'; ?>

<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Dashboard</div>
        <div class="topbar-page-sub" id="topbarSub">Manage your mountains, view trails and waypoints</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar" id="topbarAvatar"><?= $manager_initials ?></div>
    </div>
  </div>

  <div class="content" id="dashContent"></div>
</div>
</div>

<!-- Edit Mountain Modal -->
<div class="modal-bg" id="editMountainModal">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title" id="editModalTitle">Edit Mountain</div>
      <button class="modal-close" onclick="closeEditModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editMountainId">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Mountain Name</label><input type="text" id="editName" class="form-input"></div>
        <div class="form-group"><label class="form-label">Location</label><input type="text" id="editLocation" class="form-input"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea id="editDescription" class="form-textarea" rows="3"></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Difficulty</label><select id="editDifficulty" class="form-select"><option value="Easy">Easy</option><option value="Moderate">Moderate</option><option value="Hard">Hard</option></select></div>
        <div class="form-group"><label class="form-label">Elevation (MASL)</label><input type="number" id="editElevation" class="form-input"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Registration Fee (₱)</label><input type="number" id="editRegFee" class="form-input"></div>
        <div class="form-group"><label class="form-label">Environmental Fee (₱)</label><input type="number" id="editEnvFee" class="form-input"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Trail Length (km)</label><input type="number" step="0.1" id="editTrailLength" class="form-input"></div>
        <div class="form-group"><label class="form-label">Est. Duration (hours)</label><input type="number" step="0.5" id="editDuration" class="form-input"></div>
      </div>
      <div class="form-group"><label class="form-label">Jump Off Point</label><input type="text" id="editJumpOff" class="form-input"></div>
      <div class="form-group"><label class="form-label">Weather Advisory</label><textarea id="editWeatherAdvisory" class="form-textarea" rows="2"></textarea></div>
      <div class="form-group"><label class="form-label">Peak Times</label><textarea id="editPeakTimes" class="form-textarea" rows="2"></textarea></div>
      <div class="form-group"><label class="form-label">Rules (one per line)</label><textarea id="editRules" class="form-textarea" rows="3"></textarea></div>
      <div class="form-group"><label class="form-label">Status</label><select id="editStatus" class="form-select"><option value="Open">Open</option><option value="Closed">Closed</option><option value="Restricted">Restricted</option></select></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveMountain()">Save Changes</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
// Data from PHP
const ALL_MOUNTAINS = <?= $mountains_json ?>;
const ALL_BOOKINGS = <?= $bookings_json ?>;
const TRAIL_DATA = <?= json_encode($trailData) ?>;
const WAYPOINTS_DATA = <?= json_encode($waypointsData) ?>;
const DEFAULT_IMAGES = <?= json_encode($defaultImages) ?>;
const MANAGER = {
    id: <?= $manager_id ?>,
    name: <?= json_encode($manager_name) ?>,
    initials: <?= json_encode($manager_initials) ?>,
    role: <?= json_encode($manager_role) ?>
};
const CROWD_REPORTS = <?= $crowdReports_json ?>;
const STATS = <?= json_encode($stats) ?>;
const AI_INSIGHTS = <?= json_encode($aiInsights) ?>;

let mountainMaps = {};

function fmtMoney(amount) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 0 }).format(amount);
}

function fmtDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainArea = document.getElementById('mainArea');
    if (sidebar && mainArea) {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
            mainArea.classList.toggle('expanded');
        }
    }
}

function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function getIconForType(type) {
    const icons = {
        'summit': 'fa-mountain',
        'campsite': 'fa-campground',
        'viewpoint': 'fa-eye',
        'information': 'fa-info-circle',
        'peak': 'fa-flag-checkered',
        'danger': 'fa-triangle-exclamation',
        'rest': 'fa-chair',
        'start': 'fa-flag'
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
        'danger': 'Danger Zone',
        'rest': 'Rest Area',
        'start': 'Starting Point'
    };
    return typeMap[type] || type.charAt(0).toUpperCase() + type.slice(1);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Fixed initMountainMap - properly clean up before creating new map
function initMountainMap(mountainId, lat, lng, trailDataObj, waypointsDataObj, mountainName) {
    const mapContainer = document.getElementById(`map-${mountainId}`);
    if (!mapContainer) {
        console.log(`Map container not found for mountain ${mountainId}`);
        return;
    }
    
    // PROPERLY CLEAN UP EXISTING MAP
    if (mountainMaps[mountainId]) {
        console.log(`Cleaning up existing map for mountain ${mountainId}`);
        
        // Stop polling for this mountain if it's the current one
        if (currentMountainId === mountainId) {
            stopHikerPolling();
        }
        
        // Remove heatmap layer if exists
        if (heatmapLayers && heatmapLayers[mountainId]) {
            try {
                if (mountainMaps[mountainId].hasLayer(heatmapLayers[mountainId])) {
                    mountainMaps[mountainId].removeLayer(heatmapLayers[mountainId]);
                }
            } catch(e) {}
            delete heatmapLayers[mountainId];
        }
        
        // Clear hiker markers
        Object.keys(activeHikerMarkers).forEach(key => {
            if (key.startsWith(`${mountainId}_`)) {
                try {
                    if (mountainMaps[mountainId].hasLayer(activeHikerMarkers[key])) {
                        mountainMaps[mountainId].removeLayer(activeHikerMarkers[key]);
                    }
                } catch(e) {}
                delete activeHikerMarkers[key];
            }
        });
        
        // Remove the map
        try {
            mountainMaps[mountainId].remove();
        } catch(e) {}
        delete mountainMaps[mountainId];
    }
    
    // Clear the container's inner HTML to ensure clean slate
    mapContainer.innerHTML = '';
    
    // Create new map
    const map = L.map(`map-${mountainId}`).setView([lat, lng], 13);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap, © CartoDB',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);
    
    // Handle trail data
    let trailCoords = [];
    if (trailDataObj && typeof trailDataObj === 'object' && trailDataObj.coordinates && trailDataObj.coordinates.length > 0) {
        trailCoords = trailDataObj.coordinates.map(c => [c[1], c[0]]);
        L.polyline(trailCoords, {
            color: '#100600',
            weight: 5,
            opacity: 0.85,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(map);
        
        if (trailCoords.length > 0) {
            map.fitBounds(L.latLngBounds(trailCoords).pad(0.15));
        }
    } else {
        map.setView([lat, lng], 14);
    }
    
    // Start marker
    const startIcon = L.divIcon({
        html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
            <div style="width:36px;height:36px;border-radius:50%;background:#1ABC9C;display:flex;align-items:center;justify-content:center;font-size:16px;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.2);">🏁</div>
            <div style="background:#100600;color:white;padding:2px 8px;border-radius:20px;font-size:0.55rem;font-weight:700;">TRAILHEAD</div>
        </div>`,
        className: '',
        iconSize: [50, 52],
        iconAnchor: [25, 26]
    });
    L.marker([lat, lng], { icon: startIcon })
        .bindPopup(`<strong>Trailhead</strong><br>${escapeHtml(mountainName)}`)
        .addTo(map);
    
    // Waypoints
    if (waypointsDataObj && typeof waypointsDataObj === 'object' && waypointsDataObj.length > 0) {
        waypointsDataObj.forEach(wp => {
            const iconHtml = `<div class="waypoint-marker ${wp.type}-marker"><i class="fas ${getIconForType(wp.type)}"></i></div>`;
            const wpIcon = L.divIcon({
                html: iconHtml,
                className: '',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14]
            });
            
            let popupContent = `<div class="waypoint-popup">
                <strong><i class="fas ${getIconForType(wp.type)}"></i> ${escapeHtml(wp.name)}</strong>
                <div class="popup-detail">
                    ${getDisplayType(wp.type)}${wp.elevation ? ` · ${Math.round(wp.elevation)}m` : ''}
                    ${wp.description ? `<br><span class="popup-desc">📝 ${escapeHtml(wp.description)}</span>` : ''}
                </div>
                <div class="popup-coords">
                    📍 ${parseFloat(wp.latitude).toFixed(5)}, ${parseFloat(wp.longitude).toFixed(5)}
                </div>
            </div>`;
            
            L.marker([parseFloat(wp.latitude), parseFloat(wp.longitude)], { icon: wpIcon })
                .bindPopup(popupContent)
                .addTo(map);
        });
    }
    
    // Store map reference
    mountainMaps[mountainId] = map;
    
    // Add heatmap button after map is ready
    setTimeout(() => {
        if (mountainMaps[mountainId] === map) {
            addHeatmapToMountainMap(mountainId);
        }
    }, 300);
    
    // Start polling for hikers
    startHikerPolling(mountainId);
    
    console.log(`✅ Map loaded for ${mountainName}`);
}

function openEditModal(mountainId) {
    const mountain = ALL_MOUNTAINS.find(m => m.id === mountainId);
    if (!mountain) return;
    
    document.getElementById('editMountainId').value = mountain.id;
    document.getElementById('editName').value = mountain.name || '';
    document.getElementById('editLocation').value = mountain.location || '';
    document.getElementById('editDescription').value = mountain.description || '';
    document.getElementById('editDifficulty').value = mountain.difficulty || 'Moderate';
    document.getElementById('editElevation').value = mountain.elevation || '';
    document.getElementById('editRegFee').value = mountain.fees?.reg || 0;
    document.getElementById('editEnvFee').value = mountain.fees?.env || 0;
    document.getElementById('editTrailLength').value = mountain.trail_length_km || '';
    document.getElementById('editDuration').value = mountain.estimated_duration || '';
    document.getElementById('editJumpOff').value = mountain.jumpOff || '';
    document.getElementById('editWeatherAdvisory').value = mountain.weatherAdvisory || '';
    document.getElementById('editPeakTimes').value = mountain.peakTimes || '';
    document.getElementById('editRules').value = Array.isArray(mountain.rules) ? mountain.rules.join('\n') : '';
    document.getElementById('editStatus').value = mountain.status || 'Open';
    
    document.getElementById('editModalTitle').textContent = `Edit ${mountain.name}`;
    document.getElementById('editMountainModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editMountainModal').classList.remove('open');
}

async function saveMountain() {
    const mountainId = document.getElementById('editMountainId').value;
    
    const data = {
        id: mountainId,
        name: document.getElementById('editName').value,
        location: document.getElementById('editLocation').value,
        description: document.getElementById('editDescription').value,
        difficulty: document.getElementById('editDifficulty').value,
        elevation: document.getElementById('editElevation').value,
        registration_fee: document.getElementById('editRegFee').value,
        environmental_fee: document.getElementById('editEnvFee').value,
        trail_length_km: document.getElementById('editTrailLength').value,
        estimated_duration: document.getElementById('editDuration').value,
        jumpOff: document.getElementById('editJumpOff').value,
        weatherAdvisory: document.getElementById('editWeatherAdvisory').value,
        peakTimes: document.getElementById('editPeakTimes').value,
        rules: document.getElementById('editRules').value.split('\n').filter(r => r.trim()),
        status: document.getElementById('editStatus').value
    };
    
    try {
        const response = await fetch('../api/update_mountain.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            showToast('Mountain updated successfully!');
            closeEditModal();
            location.reload();
        } else {
            showToast(result.message || 'Error updating mountain');
        }
    } catch (error) {
        showToast('Network error');
    }
}

function updateClock() {
    const d = new Date();
    const dateEl = document.getElementById('topbarDate');
    if (dateEl) {
        dateEl.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() + ' ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    }
}

function hour() {
    const h = new Date().getHours();
    return h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening';
}
// Add these variables at the top with your other state variables
let activeHikerMarkers = {};
let hikerPollInterval = null;
let currentMountainId = null;
let guideMarkerRef = null;
let currentGuideLocation = null;

// Function to fetch active hikers for a mountain
async function fetchActiveHikers(mountainId) {
    if (!mountainId) return;
    
    try {
        const response = await fetch(`../api/get_manager_active_hikers.php?mountain_id=${mountainId}`);
        const data = await response.json();
        
        if (data.success && data.hikers) {
            updateHikerList(mountainId, data.hikers);
            updateHikerMarkersOnMap(mountainId, data.hikers);
        }
    } catch (error) {
        console.error('Error fetching hikers:', error);
    }
}

// Update the hiker list in the UI
function updateHikerList(mountainId, hikers) {
    const panel = document.getElementById(`tracking-panel-${mountainId}`);
    if (!panel) return;
    
    const listContainer = panel.querySelector('.hiker-list');
    if (!listContainer) return;
    
    if (!hikers || hikers.length === 0) {
        listContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--ink3);">No active hikers at the moment</div>';
        return;
    }
    
    listContainer.innerHTML = '';
    
    hikers.forEach(hiker => {
        const isActive = hiker.is_active && hiker.tracking_on;
        const statusClass = isActive ? 'status-active' : (hiker.minutes_ago ? 'status-stale' : 'status-off');
        const statusText = isActive ? 'Active' : (hiker.minutes_ago ? `${hiker.minutes_ago}m ago` : 'No signal');
        
        const locationText = hiker.latitude && hiker.longitude 
            ? `${hiker.latitude.toFixed(5)}, ${hiker.longitude.toFixed(5)}`
            : 'Location unknown';
        
        const item = document.createElement('div');
        item.className = 'hiker-item';
        item.innerHTML = `
            <div class="hiker-avatar ${isActive ? 'active' : ''}">
                ${(hiker.name || 'H').charAt(0).toUpperCase()}
            </div>
            <div class="hiker-info">
                <div class="hiker-name">${escapeHtml(hiker.name || 'Hiker')}</div>
                <div class="hiker-booking">Booking #${hiker.booking_number || hiker.booking_id}</div>
                <div class="hiker-location"><i class="fas fa-map-marker-alt" style="font-size: 0.45rem;"></i> ${locationText}</div>
            </div>
            <div class="hiker-status ${statusClass}">${statusText}</div>
        `;
        
        item.addEventListener('click', () => {
            if (hiker.latitude && hiker.longitude && mountainMaps[mountainId]) {
                mountainMaps[mountainId].setView([hiker.latitude, hiker.longitude], 16);
                const marker = activeHikerMarkers[`${mountainId}_${hiker.user_id || hiker.name}`];
                if (marker) marker.openPopup();
            }
        });
        
        listContainer.appendChild(item);
    });
}

// Update hiker markers on the map
function updateHikerMarkersOnMap(mountainId, hikers) {
    const map = mountainMaps[mountainId];
    if (!map) return;
    
    // Clear existing markers for this mountain
    Object.keys(activeHikerMarkers).forEach(key => {
        if (key.startsWith(`${mountainId}_`)) {
            map.removeLayer(activeHikerMarkers[key]);
            delete activeHikerMarkers[key];
        }
    });
    
    if (!hikers || !hikers.length) return;
    
    hikers.forEach(hiker => {
        if (!hiker.latitude || !hiker.longitude) return;
        
        const isActive = hiker.is_active && hiker.tracking_on;
        const markerId = `${mountainId}_${hiker.user_id || hiker.name}`;
        const initials = (hiker.name || 'H').charAt(0).toUpperCase();
        
        const hikerIcon = L.divIcon({
            html: `<div class="hiker-marker">
                <div class="hiker-marker-avatar ${isActive ? 'active' : (hiker.minutes_ago ? 'stale' : '')}">${initials}</div>
                <div class="hiker-marker-name">${escapeHtml((hiker.name || 'Hiker').split(' ')[0])}</div>
            </div>`,
            className: '',
            iconSize: [44, 48],
            iconAnchor: [22, 24]
        });
        
        const popupContent = `
            <div style="min-width: 180px;">
                <strong>${escapeHtml(hiker.name || 'Hiker')}</strong>
                <div style="font-size: 0.7rem; color: #666; margin-top: 5px;">
                    ${isActive ? '📍 Active on trail' : (hiker.minutes_ago ? `⏰ Last seen ${hiker.minutes_ago} minutes ago` : '📡 No signal')}
                    ${hiker.guide_name ? `<br>👨‍🏫 Guide: ${escapeHtml(hiker.guide_name)}` : ''}
                </div>
                <div style="font-size: 0.6rem; color: #999; margin-top: 8px;">
                    📍 ${hiker.latitude.toFixed(5)}, ${hiker.longitude.toFixed(5)}
                </div>
            </div>
        `;
        
        const marker = L.marker([hiker.latitude, hiker.longitude], { icon: hikerIcon })
            .bindPopup(popupContent)
            .addTo(map);
        
        activeHikerMarkers[markerId] = marker;
    });
}

// Start polling for hiker locations
function startHikerPolling(mountainId) {
    if (hikerPollInterval) {
        clearInterval(hikerPollInterval);
    }
    
    currentMountainId = mountainId;
    
    // Fetch immediately
    fetchActiveHikers(mountainId);
    
    // Then every 15 seconds
    hikerPollInterval = setInterval(() => {
        if (currentMountainId === mountainId) {
            fetchActiveHikers(mountainId);
        }
    }, 15000);
}

// Stop hiker polling
function stopHikerPolling() {
    if (hikerPollInterval) {
        clearInterval(hikerPollInterval);
        hikerPollInterval = null;
    }
    currentMountainId = null;
}

// Store heatmap layers separately
let heatmapLayers = {};

// Fixed addHeatmapToMountainMap function
function addHeatmapToMountainMap(mountainId) {
    const map = mountainMaps[mountainId];
    if (!map) {
        console.log(`Map for mountain ${mountainId} not ready yet`);
        return;
    }
    
    // Find the map container and controls
    const mapContainer = document.getElementById(`map-${mountainId}`);
    if (!mapContainer) return;
    
    const controlsDiv = mapContainer.parentElement?.querySelector('.map-controls');
    if (!controlsDiv) return;
    
    // Don't add duplicate buttons
    if (controlsDiv.querySelector('.heatmap-btn')) return;
    
    const heatmapBtn = document.createElement('button');
    heatmapBtn.className = 'map-btn heatmap-btn';
    heatmapBtn.innerHTML = '<i class="fas fa-fire"></i> Heatmap';
    heatmapBtn.style.background = '#100600';
    heatmapBtn.style.color = 'white';
    
    let heatmapActive = false;
    
    heatmapBtn.onclick = async (e) => {
        e.stopPropagation();
        
        // Get the CURRENT map reference (not the one from closure)
        const currentMap = mountainMaps[mountainId];
        if (!currentMap) {
            showToast('Map not ready. Please refresh.');
            return;
        }
        
        heatmapActive = !heatmapActive;
        
        if (heatmapActive) {
            heatmapBtn.style.background = '#c9a84c';
            heatmapBtn.style.color = '#100600';
            const success = await loadAndShowHeatmap(mountainId, currentMap);
            if (!success) {
                heatmapActive = false;
                heatmapBtn.style.background = '#100600';
                heatmapBtn.style.color = 'white';
            }
        } else {
            // Remove heatmap
            if (heatmapLayers[mountainId]) {
                if (currentMap.hasLayer(heatmapLayers[mountainId])) {
                    currentMap.removeLayer(heatmapLayers[mountainId]);
                }
                delete heatmapLayers[mountainId];
            }
            heatmapBtn.style.background = '#100600';
            heatmapBtn.style.color = 'white';
            showToast('Heatmap disabled');
        }
    };
    
    controlsDiv.appendChild(heatmapBtn);
    console.log(`✅ Heatmap button added for mountain ${mountainId}`);
}

// Fixed loadAndShowHeatmap function
async function loadAndShowHeatmap(mountainId, map) {
    if (!map) {
        console.error('No map provided for heatmap');
        showToast('Map not available');
        return false;
    }
    
    try {
        const response = await fetch(`../api/detect_crowd_hotspots.php?mountain_id=${mountainId}`);
        const data = await response.json();
        
        console.log('Heatmap data:', data);
        
        // Remove existing heatmap layer for this mountain
        if (heatmapLayers[mountainId]) {
            try {
                if (map.hasLayer(heatmapLayers[mountainId])) {
                    map.removeLayer(heatmapLayers[mountainId]);
                }
            } catch(e) {}
            delete heatmapLayers[mountainId];
        }
        
        if (data.success && data.points && data.points.length > 0) {
            const heatmapPoints = data.points.map(p => [p.latitude, p.longitude, p.intensity]);
            
            const heatLayer = L.heatLayer(heatmapPoints, {
                radius: 35,
                blur: 15,
                maxZoom: 17,
                minOpacity: 0.4,
                gradient: {
                    0.2: '#10B981',
                    0.4: '#84CC16',
                    0.6: '#F59E0B',
                    0.8: '#EF4444',
                    1.0: '#7F1D1D'
                }
            });
            
            heatLayer.addTo(map);
            heatmapLayers[mountainId] = heatLayer;
            
            const highPoints = data.points.filter(p => p.intensity >= 0.7).length;
            if (highPoints > 0) {
                showToast(`🔥 ${highPoints} high-density crowd area(s) detected`);
            } else {
                showToast(`🌡️ Heatmap loaded - ${data.points.length} crowd data points`);
            }
            return true;
        } else {
            showToast('No crowd data available for this trail');
            return false;
        }
    } catch (error) {
        console.error('Error loading heatmap:', error);
        showToast('Error loading heatmap data');
        return false;
    }
}

// Fixed initMountainMap - ensure map is properly stored before adding heatmap button
function initMountainMap(mountainId, lat, lng, trailDataObj, waypointsDataObj, mountainName) {
    const mapContainer = document.getElementById(`map-${mountainId}`);
    if (!mapContainer) {
        console.log(`Map container not found for mountain ${mountainId}`);
        return;
    }
    
    // Clean up existing map
    if (mountainMaps[mountainId]) {
        console.log(`Cleaning up existing map for mountain ${mountainId}`);
        
        if (currentMountainId === mountainId) {
            stopHikerPolling();
        }
        
        if (heatmapLayers[mountainId]) {
            try {
                if (mountainMaps[mountainId].hasLayer(heatmapLayers[mountainId])) {
                    mountainMaps[mountainId].removeLayer(heatmapLayers[mountainId]);
                }
            } catch(e) {}
            delete heatmapLayers[mountainId];
        }
        
        Object.keys(activeHikerMarkers).forEach(key => {
            if (key.startsWith(`${mountainId}_`)) {
                try {
                    if (mountainMaps[mountainId].hasLayer(activeHikerMarkers[key])) {
                        mountainMaps[mountainId].removeLayer(activeHikerMarkers[key]);
                    }
                } catch(e) {}
                delete activeHikerMarkers[key];
            }
        });
        
        try {
            mountainMaps[mountainId].remove();
        } catch(e) {}
        delete mountainMaps[mountainId];
    }
    
    // Clear container and create new map
    mapContainer.innerHTML = '';
    
    const map = L.map(`map-${mountainId}`).setView([lat, lng], 13);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap, © CartoDB',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);
    
    // Handle trail data
    let trailCoords = [];
    if (trailDataObj && typeof trailDataObj === 'object' && trailDataObj.coordinates && trailDataObj.coordinates.length > 0) {
        trailCoords = trailDataObj.coordinates.map(c => [c[1], c[0]]);
        L.polyline(trailCoords, {
            color: '#100600',
            weight: 5,
            opacity: 0.85,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(map);
        
        if (trailCoords.length > 0) {
            map.fitBounds(L.latLngBounds(trailCoords).pad(0.15));
        }
    } else {
        map.setView([lat, lng], 14);
    }
    
    // Start marker
    const startIcon = L.divIcon({
        html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
            <div style="width:36px;height:36px;border-radius:50%;background:#1ABC9C;display:flex;align-items:center;justify-content:center;font-size:16px;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.2);">🏁</div>
            <div style="background:#100600;color:white;padding:2px 8px;border-radius:20px;font-size:0.55rem;font-weight:700;">TRAILHEAD</div>
        </div>`,
        className: '',
        iconSize: [50, 52],
        iconAnchor: [25, 26]
    });
    L.marker([lat, lng], { icon: startIcon })
        .bindPopup(`<strong>Trailhead</strong><br>${escapeHtml(mountainName)}`)
        .addTo(map);
    
    // Waypoints
    if (waypointsDataObj && typeof waypointsDataObj === 'object' && waypointsDataObj.length > 0) {
        waypointsDataObj.forEach(wp => {
            const iconHtml = `<div class="waypoint-marker ${wp.type}-marker"><i class="fas ${getIconForType(wp.type)}"></i></div>`;
            const wpIcon = L.divIcon({
                html: iconHtml,
                className: '',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14]
            });
            
            let popupContent = `<div class="waypoint-popup">
                <strong><i class="fas ${getIconForType(wp.type)}"></i> ${escapeHtml(wp.name)}</strong>
                <div class="popup-detail">
                    ${getDisplayType(wp.type)}${wp.elevation ? ` · ${Math.round(wp.elevation)}m` : ''}
                    ${wp.description ? `<br><span class="popup-desc">📝 ${escapeHtml(wp.description)}</span>` : ''}
                </div>
                <div class="popup-coords">
                    📍 ${parseFloat(wp.latitude).toFixed(5)}, ${parseFloat(wp.longitude).toFixed(5)}
                </div>
            </div>`;
            
            L.marker([parseFloat(wp.latitude), parseFloat(wp.longitude)], { icon: wpIcon })
                .bindPopup(popupContent)
                .addTo(map);
        });
    }
    
    // Store map reference BEFORE adding heatmap button
    mountainMaps[mountainId] = map;
    
    // Add heatmap button after map is ready and stored
    setTimeout(() => {
        // Verify the map still exists and is the current one
        if (mountainMaps[mountainId] === map) {
            addHeatmapToMountainMap(mountainId);
        }
    }, 300);
    
    // Start polling for hikers
    startHikerPolling(mountainId);
    
    console.log(`✅ Map loaded for ${mountainName}`);
}

// Fixed toggleMountain function
function toggleMountain(mountainId) {
    const body = document.getElementById(`mountain-body-${mountainId}`);
    if (body) {
        const isExpanded = body.classList.contains('expanded');
        
        if (!isExpanded) {
            // Close all other expanded mountains first
            document.querySelectorAll('.mountain-card-body').forEach(b => {
                const otherId = parseInt(b.id.replace('mountain-body-', ''));
                if (b.id !== `mountain-body-${mountainId}` && otherId) {
                    b.classList.remove('expanded');
                    if (currentMountainId === otherId) {
                        stopHikerPolling();
                    }
                    // Clean up heatmap for other mountains
                    if (heatmapLayers[otherId]) {
                        const otherMap = mountainMaps[otherId];
                        if (otherMap && otherMap.hasLayer && otherMap.hasLayer(heatmapLayers[otherId])) {
                            try {
                                otherMap.removeLayer(heatmapLayers[otherId]);
                            } catch(e) {}
                        }
                        delete heatmapLayers[otherId];
                    }
                }
            });
            
            body.classList.add('expanded');
            const mountain = ALL_MOUNTAINS.find(m => m.id === mountainId);
            if (mountain) {
                setTimeout(() => {
                    const trailData = TRAIL_DATA[mountainId] || { coordinates: [] };
                    const waypoints = WAYPOINTS_DATA[mountainId] || [];
                    initMountainMap(mountainId, mountain.start_point_lat, mountain.start_point_lng, 
                        trailData, waypoints, mountain.name);
                }, 200);
            }
        } else {
            // Collapsed - stop polling and clean up heatmap
            if (currentMountainId === mountainId) {
                stopHikerPolling();
            }
            if (heatmapLayers[mountainId]) {
                const currentMap = mountainMaps[mountainId];
                if (currentMap && currentMap.hasLayer && currentMap.hasLayer(heatmapLayers[mountainId])) {
                    try {
                        currentMap.removeLayer(heatmapLayers[mountainId]);
                    } catch(e) {}
                }
                delete heatmapLayers[mountainId];
            }
            body.classList.remove('expanded');
        }
    }
}
function renderCrowdReports() {
    if (!CROWD_REPORTS || CROWD_REPORTS.length === 0) {
        return `
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No crowd reports yet</p>
                <small style="font-size: 0.7rem;">Reports will appear here when guides submit crowd levels</small>
            </div>
        `;
    }
    
    return CROWD_REPORTS.map(report => {
        const levelClass = report.crowd_level;
        const levelIcon = report.crowd_level === 'Low' ? 'fa-smile' : (report.crowd_level === 'Medium' ? 'fa-meh' : 'fa-frown');
        const reportDate = new Date(report.reported_at).toLocaleString('en-PH');
        const reporterDisplay = report.reporter_role === 'guide' ? 
            `<i class="fas fa-user-check"></i> Guide: ${escapeHtml(report.reporter_name)}` : 
            `<i class="fas fa-robot"></i> ${escapeHtml(report.reporter_name)}`;
        
        const hasLocation = report.location_lat && report.location_lng;
        
        return `
            <div class="crowd-report-item" ${hasLocation ? `onclick="zoomToReportLocation(${report.location_lat}, ${report.location_lng})"` : ''}>
                <div class="crowd-report-icon level-${levelClass}">
                    <i class="fas ${levelIcon}"></i>
                </div>
                <div class="crowd-report-content">
                    <div class="crowd-report-header">
                        <span class="crowd-report-mountain">🏔️ ${escapeHtml(report.mountain_name)}</span>
                        <span class="crowd-report-badge ${levelClass}">${report.crowd_level}</span>
                    </div>
                    <div class="crowd-report-details">
                        <span class="crowd-report-reporter">
                            ${reporterDisplay}
                        </span>
                        <span class="crowd-report-time">
                            <i class="far fa-clock"></i> ${reportDate}
                        </span>
                        ${hasLocation ? `
                        <span class="crowd-report-location" onclick="event.stopPropagation(); zoomToReportLocation(${report.location_lat}, ${report.location_lng})">
                            <i class="fas fa-map-marker-alt"></i> View on map
                        </span>
                        ` : ''}
                    </div>
                    ${report.notes ? `<div class="crowd-report-notes"><i class="fas fa-comment"></i> ${escapeHtml(report.notes)}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
}
function zoomToReportLocation(lat, lng) {
    // Find which mountain is currently expanded
    let expandedMountain = null;
    for (const m of ALL_MOUNTAINS) {
        const body = document.getElementById(`mountain-body-${m.id}`);
        if (body && body.classList.contains('expanded')) {
            expandedMountain = m;
            break;
        }
    }
    
    if (expandedMountain && mountainMaps[expandedMountain.id]) {
        mountainMaps[expandedMountain.id].setView([lat, lng], 16);
        showToast(`📍 Zoomed to report location on ${expandedMountain.name}`);
    } else {
        showToast('Please expand a mountain map first to see the location');
    }
}
function renderDashboard() {
    const bookings = ALL_BOOKINGS;
    const myMtns = ALL_MOUNTAINS;
    
    const totalBookings = bookings.length;
    const pendingCount = bookings.filter(b => b.status === 'pending').length;
    const completedCount = bookings.filter(b => b.status === 'completed').length;
    const totalRevExpected = bookings.filter(b => b.status !== 'cancelled').reduce((s, b) => s + b.totalFee, 0);
    const totalHikers = bookings.filter(b => b.status !== 'cancelled').reduce((s, b) => s + b.pax, 0);
    
    let paidReg = 0, totalReg = 0;
    bookings.forEach(b => {
        if (b.status === 'cancelled') return;
        totalReg += b.pax;
        if (b.paymentStatus === 'paid') paidReg += b.pax;
    });
    const collPct = totalReg > 0 ? Math.round((paidReg / totalReg) * 100) : 0;
    
    
let mountainsHtml = '';
for (const m of myMtns) {
    const mb = bookings.filter(b => b.mountainId === m.id && b.status !== 'cancelled');
    const pending = mb.filter(b => b.status === 'pending').length;
    const hikerCount = mb.reduce((s, b) => s + b.pax, 0);
    const crowdClass = `crowd-${m.crowdLevel || 'Low'}`;
    const difficultyClass = `difficulty-${m.difficulty || 'Moderate'}`;
    const mountainImage = DEFAULT_IMAGES[m.id] || DEFAULT_IMAGES[1];
    
    // Get the data as JavaScript objects
    const trailDataObj = TRAIL_DATA[m.id] || { coordinates: [] };
    const waypointsObj = WAYPOINTS_DATA[m.id] || [];
    
    mountainsHtml += `
        <div class="mountain-card">
            <div class="mountain-card-header" onclick="toggleMountain(${m.id})">
                <div class="mountain-title">
                    <h3>🏔️ ${escapeHtml(m.name)}</h3>
                    <div class="mountain-badges">
                        <span class="difficulty-badge ${difficultyClass}">${m.difficulty || 'Moderate'}</span>
                        <span class="crowd-badge ${crowdClass}">${m.crowdLevel || 'Low'} Crowd</span>
                    </div>
                </div>
                <div class="mountain-header-actions">
                    <button class="btn-icon" onclick="event.stopPropagation(); openEditModal(${m.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>
            </div>
            <div class="mountain-preview">
                <div class="mountain-preview-img" style="background-image:url('${mountainImage}')"></div>
                <div class="mountain-preview-stats">
                    <div class="preview-stat"><div class="preview-stat-val">${mb.length}</div><div class="preview-stat-label">Bookings</div></div>
                    <div class="preview-stat"><div class="preview-stat-val">${hikerCount}</div><div class="preview-stat-label">Hikers</div></div>
                    <div class="preview-stat"><div class="preview-stat-val" style="${pending > 0 ? 'color:#f57f17;' : ''}">${pending}</div><div class="preview-stat-label">Pending</div></div>
                    <div class="preview-stat"><div class="preview-stat-val">${fmtMoney(m.fees.reg)}</div><div class="preview-stat-label">Reg Fee</div></div>
                </div>
            </div>
            <div class="mountain-card-body" id="mountain-body-${m.id}">
                <div class="mountain-map-container">
                    <div id="map-${m.id}" class="mountain-map"></div>
                    <div class="map-controls">
                        <button class="map-btn" onclick="event.stopPropagation(); initMountainMap(${m.id}, ${m.start_point_lat}, ${m.start_point_lng}, TRAIL_DATA[${m.id}] || {coordinates: []}, WAYPOINTS_DATA[${m.id}] || [], '${escapeHtml(m.name).replace(/'/g, "\\'")}')">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="map-legend">
                        <div class="map-legend-item"><div class="legend-dot trail"></div> <span>Trail</span></div>
                        <div class="map-legend-item"><div class="legend-dot waypoint"></div> <span>Waypoint</span></div>
                        <div class="map-legend-item"><div class="legend-dot start"></div> <span>Trailhead</span></div>
                        <div class="map-legend-item"><div class="legend-dot" style="background: linear-gradient(135deg, #667eea, #764ba2);"></div> <span>Hiker (live)</span></div>
                    </div>
                </div>
                
                <!-- TRACKING PANEL - Live Hikers -->
                <div class="tracking-panel" id="tracking-panel-${m.id}">
                    <div class="tracking-panel-header">
                        <div class="tracking-panel-title">
                            <i class="fas fa-users"></i> Live Hiker Tracking
                            <span class="live-dot" style="width: 6px; height: 6px;"></span>
                        </div>
                        <span style="font-size: 0.6rem; color: var(--ink3);">Updates every 15s</span>
                    </div>
                    <div class="hiker-list">
                        <div style="padding: 20px; text-align: center; color: var(--ink3);">
                            <i class="fas fa-spinner fa-spin"></i> Loading hikers...
                        </div>
                    </div>
                </div>
                
                <div class="mountain-details">
                    <div class="mountain-details-header">
                        <h4>
                            <i class="fas fa-info-circle"></i> 
                            About ${escapeHtml(m.name)}
                        </h4>
                    </div>
                    <div class="mountain-details-content">
                        <div class="mountain-description">
                            <i class="fas fa-quote-left" style="color: #c9a84c; margin-right: 8px; opacity: 0.6;"></i>
                            ${escapeHtml(m.description || 'No description available.')}
                        </div>
                        
                        <div class="mountain-stats-grid">
                            <div class="stat-card-simple">
                                <div class="stat-icon-simple"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="stat-info-simple">
                                    <div class="stat-label-simple">Location</div>
                                    <div class="stat-value-simple">${escapeHtml(m.location || 'N/A')}</div>
                                </div>
                            </div>
                            <div class="stat-card-simple">
                                <div class="stat-icon-simple"><i class="fas fa-mountain"></i></div>
                                <div class="stat-info-simple">
                                    <div class="stat-label-simple">Elevation</div>
                                    <div class="stat-value-simple">${escapeHtml(m.elevation || 'N/A')} <span class="stat-unit-simple">MASL</span></div>
                                </div>
                            </div>
                            <div class="stat-card-simple">
                                <div class="stat-icon-simple"><i class="fas fa-route"></i></div>
                                <div class="stat-info-simple">
                                    <div class="stat-label-simple">Trail Length</div>
                                    <div class="stat-value-simple">${m.trail_length_km || '?'} <span class="stat-unit-simple">km</span></div>
                                </div>
                            </div>
                            <div class="stat-card-simple">
                                <div class="stat-icon-simple"><i class="fas fa-clock"></i></div>
                                <div class="stat-info-simple">
                                    <div class="stat-label-simple">Est. Duration</div>
                                    <div class="stat-value-simple">${m.estimated_duration || '?'} <span class="stat-unit-simple">hours</span></div>
                                </div>
                            </div>
                            <div class="stat-card-simple">
                                <div class="stat-icon-simple"><i class="fas fa-flag-checkered"></i></div>
                                <div class="stat-info-simple">
                                    <div class="stat-label-simple">Jump Off Point</div>
                                    <div class="stat-value-simple">${escapeHtml(m.jumpOff || 'N/A')}</div>
                                </div>
                            </div>
                            <div class="stat-card-simple">
                                <div class="stat-icon-simple"><i class="fas fa-tags"></i></div>
                                <div class="stat-info-simple">
                                    <div class="stat-label-simple">Fees</div>
                                    <div class="stat-value-simple">₱${m.fees.reg} <span class="stat-unit-simple">reg</span> + ₱${m.fees.env} <span class="stat-unit-simple">env</span></div>
                                </div>
                            </div>
                        </div>
                        
                        ${m.weatherAdvisory ? `
                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-cloud-sun"></i> Weather Advisory
                            </div>
                            <div class="info-card">
                                <p><i class="fas fa-exclamation-triangle" style="color: #f39c12; margin-right: 8px;"></i> ${escapeHtml(m.weatherAdvisory)}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${m.peakTimes ? `
                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-chart-line"></i> Peak Times
                            </div>
                            <div class="info-card">
                                <p><i class="fas fa-calendar-alt" style="color: #3498db; margin-right: 8px;"></i> ${escapeHtml(m.peakTimes)}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${m.rules && m.rules.length ? `
                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-gavel"></i> Rules & Guidelines
                            </div>
                            <div class="info-card">
                                <ul class="rules-list">
                                    ${m.rules.map(r => `<li><i class="fas fa-check-circle"></i> ${escapeHtml(r)}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}
    
   document.getElementById('dashContent').innerHTML = `
    <div class="hero-banner">
        <div>
            <div class="hero-greeting">Good ${hour()}, Manager</div>
            <div class="hero-name">${MANAGER.name}</div>
            <div class="hero-mountains">
                ${myMtns.map(m => `<div class="hero-mtn-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>${escapeHtml(m.name)}</div>`).join('')}
            </div>
        </div>
        <div class="hero-right">
            ${myMtns.length > 0 ? `<div class="hero-quick-stat">
                <div class="hero-qs-val">${STATS.total_hikers || 0}</div>
                <div class="hero-qs-lbl">Total Hikers</div>
            </div>` : ''}
        </div>
    </div>
    
    <div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
        </div>
        <div>
            <div class="stat-val">${STATS.total_bookings || 0}</div>
            <div class="stat-label">Total Bookings</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-val" style="font-size:22px;">${fmtMoney(STATS.actual_revenue || 0)}</div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
            <div class="stat-val" style="font-size:22px;">${fmtMoney(STATS.expected_revenue || 0)}</div>
            <div class="stat-label">Expected Revenue</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <div class="stat-val">${STATS.completed_hikes || 0}</div>
            <div class="stat-label">Completed Hikes</div>
        </div>
    </div>
</div>

    <div class="two-col">
        <div>
           
            <div class="mountain-cards-grid">
                ${mountainsHtml}
            </div>
        </div>
        
        <div>
            <div class="panel">
                <div class="panel-hdr">
                    <div class="panel-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Fee Collection Rate
                    </div>
                </div>
                <div class="panel-body">
                    <div class="collection-section">
                        <div class="collection-ring">
                            <div class="ring-wrap">
                                <svg width="80" height="80" viewBox="0 0 80 80">
                                    <circle cx="40" cy="40" r="33" stroke="#f4f1ec" stroke-width="8" fill="none"/>
                                    <circle cx="40" cy="40" r="33" stroke="#100600" stroke-width="8" fill="none" stroke-dasharray="207.3" stroke-dashoffset="${207.3 * (1 - collPct / 100)}" stroke-linecap="round"/>
                                </svg>
                                <div class="ring-val">${collPct}%</div>
                            </div>
                        </div>
                        <div class="collection-breakdown">
                            <div class="collection-row">
                                <span><span class="collection-dot" style="background:var(--green);"></span>Registration collected</span>
                                <span>${paidReg}/${totalReg}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Insights Panel moved here -->
            <div class="analytics-panel">
                <div class="panel-hdr">
                    <div class="panel-title">
                        <i class="fas fa-chart-line"></i> Analytics Insights
                    </div>
                </div>
                <div class="analytics-insights-list">
                    ${AI_INSIGHTS.map(insight => `<div class="analytics-insight-item"><i class="fas fa-lightbulb"></i> ${escapeHtml(insight)}</div>`).join('')}
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-hdr">
                    <div class="panel-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Recent Bookings
                    </div>
                    <a href="bookings_manager.php" class="btn-outline btn-sm" style="text-decoration:none;">View All</a>
                </div>
                <div class="panel-body">
                    ${bookings.slice(0, 5).map(b => `<div class="booking-mini"><div class="booking-mini-num">${b.bookingNumber || b.id}</div><div class="booking-mini-info"><div class="booking-mini-mountain">${escapeHtml(b.mountain)}</div><div class="booking-mini-meta">${fmtDate(b.date)} · ${b.pax} hiker${b.pax > 1 ? 's' : ''}</div></div><div class="booking-mini-right"><div class="badge badge-${b.status}">${b.status}</div><div class="booking-mini-fee">${fmtMoney(b.totalFee)}</div></div></div>`).join('')}
                    ${bookings.length === 0 ? '<div style="text-align:center;padding:20px;">No bookings yet</div>' : ''}
                </div>
            </div>
            
            <!-- Crowd Reports Panel -->
            <div class="crowd-reports-panel">
                <div class="panel-hdr">
                    <div class="panel-title">
                        <i class="fas fa-chart-line"></i> Crowd Level Reports
                    </div>
                    <span style="font-size: 0.6rem; color: var(--ink3);">Live updates from guides</span>
                </div>
                <div class="crowd-reports-list" id="crowdReportsList">
                    ${renderCrowdReports()}
                </div>
            </div>
        </div>
    </div>
`;
    
    setTimeout(() => {
    if (myMtns.length > 0) {
        // Option 1: Expand the first mountain
        toggleMountain(myMtns[0].id);
        
        // Option 2: Expand ALL mountains (uncomment if you want this)
        // myMtns.forEach(m => toggleMountain(m.id));
    }
}, 500);
    setInterval(() => {
        const el = document.getElementById('heroClock');
        if (el) el.textContent = new Date().toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    }, 1000);
}
setInterval(updateClock, 1000);
updateClock();
document.getElementById('topbarAvatar').textContent = MANAGER.initials;
renderDashboard();
</script>

</body>
</html>