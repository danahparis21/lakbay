<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Fetch mountains from database
$stmt = $pdo->prepare("SELECT * FROM mountains ORDER BY name");
$stmt->execute();
$mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each mountain, get current active bookings count
foreach ($mountains as &$mountain) {
    // Count active day hike bookings for this mountain (today or future)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_count 
        FROM bookings 
        WHERE mountain_id = ? AND status = 'active' AND hike_date >= CURDATE()
    ");
    $stmt->execute([$mountain['id']]);
    $mountain['active_hikers_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'] ?? 0;
    
    // Count camping bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as camping_count 
        FROM camping_bookings 
        WHERE mountain_id = ? AND status = 'active' AND start_date >= CURDATE()
    ");
    $stmt->execute([$mountain['id']]);
    $mountain['active_camping'] = $stmt->fetch(PDO::FETCH_ASSOC)['camping_count'] ?? 0;
}
unset($mountain); // FIX: Prevent pass-by-reference bug in subsequent loops

// If no mountains exist, add sample data
if (empty($mountains)) {
    $sampleMountains = [
        ['name' => 'Mt. Batulao', 'elevation' => '811 MASL', 'difficulty' => 'Intermediate', 'fee' => 250, 'status' => 'Open', 'location' => 'Nasugbu, Batangas', 'crowdLevel' => 'High', 'duration' => '3–4 hours', 'jumpOff' => 'Barangay Evercrest, Nasugbu', 'description' => 'Known for its iconic rolling hills and stunning panoramic views.', 'rating' => 4.7, 'weatherAdvisory' => 'Clear skies expected. Temperature: 24–28°C.', 'peakTimes' => 'Weekends 6AM–9AM. Less crowded on weekdays.', 'rules' => json_encode(['Register at barangay hall before the hike', 'No littering', 'Stay on designated trails']), 'envReminders' => json_encode(['Bring reusable water bottles', 'Pack out all trash', 'Leave No Trace']), 'hazards' => json_encode(['Steep and slippery trails during rainy season', 'Exposed ridges', 'Limited water sources'])],
        ['name' => 'Mt. Pulag', 'elevation' => '2922 MASL', 'difficulty' => 'Moderate', 'fee' => 500, 'status' => 'Open', 'location' => 'Bokod, Benguet', 'crowdLevel' => 'Very High', 'duration' => '2–3 days', 'jumpOff' => 'Ambangeg, Bokod, Benguet', 'description' => 'The highest peak in Luzon, famous for its sea of clouds.', 'rating' => 4.9, 'weatherAdvisory' => 'Cold temperatures, possible rain.', 'peakTimes' => 'Feb–Apr, Nov–Dec', 'rules' => json_encode(['Permit required', 'Guide required', 'No campfires']), 'envReminders' => json_encode(['Strict Leave No Trace', 'Pack out all waste', 'No picking of plants']), 'hazards' => json_encode(['Extreme cold', 'Altitude sickness', 'Strong winds'])],
        ['name' => 'Mt. Makiling', 'elevation' => '1090 MASL', 'difficulty' => 'Moderate', 'fee' => 300, 'status' => 'Open', 'location' => 'Los Baños, Laguna', 'crowdLevel' => 'Moderate', 'duration' => '6–8 hours', 'jumpOff' => 'UP Land Grants, Los Baños', 'description' => 'A mystical mountain known for rich biodiversity.', 'rating' => 4.5, 'weatherAdvisory' => 'Humid with chance of afternoon rain.', 'peakTimes' => 'Dec–May', 'rules' => json_encode(['Guided climbs required', 'No overnight camping without permit']), 'envReminders' => json_encode(['Protect the watershed', 'No hunting or collecting']), 'hazards' => json_encode(['Slippery trails', 'Limited water sources'])],
        ['name' => 'Mt. Apo', 'elevation' => '2954 MASL', 'difficulty' => 'Difficult', 'fee' => 800, 'status' => 'Open', 'location' => 'Davao del Sur', 'crowdLevel' => 'High', 'duration' => '3–4 days', 'jumpOff' => 'Sta. Cruz, Davao del Sur', 'description' => 'The highest mountain in the Philippines.', 'rating' => 4.8, 'weatherAdvisory' => 'Variable weather, prepare for rain and cold.', 'peakTimes' => 'Apr–Jun, Oct–Dec', 'rules' => json_encode(['Permit required', 'Must have certified guide', 'No littering']), 'envReminders' => json_encode(['Strict waste management', 'No picking of endangered plants']), 'hazards' => json_encode(['Extreme weather', 'Rocky sections', 'Altitude sickness'])],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO mountains (name, elevation, difficulty, fee, status, location, crowdLevel, duration, jumpOff, description, rating, weatherAdvisory, peakTimes, rules, envReminders, hazards) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sampleMountains as $mountain) {
        $stmt->execute([
            $mountain['name'], $mountain['elevation'], $mountain['difficulty'], $mountain['fee'],
            $mountain['status'], $mountain['location'], $mountain['crowdLevel'], $mountain['duration'],
            $mountain['jumpOff'], $mountain['description'], $mountain['rating'], $mountain['weatherAdvisory'],
            $mountain['peakTimes'], $mountain['rules'], $mountain['envReminders'], $mountain['hazards']
        ]);
    }
    
    // Refresh mountains list
    $stmt = $pdo->prepare("SELECT * FROM mountains ORDER BY name");
    $stmt->execute();
    $mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate elevation percentage for display
$maxElevation = !empty($mountains) ? max(array_map(function($m) {
    return (int) preg_replace('/[^0-9]/', '', $m['elevation']);
}, $mountains)) : 3000;

// Default image bank for mountains without images
$imgBank = [
    "https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop",
    "https://images.pexels.com/photos/248797/pexels-photo-248797.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop",
    "https://images.pexels.com/photos/417074/pexels-photo-417074.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop",
    "https://images.pexels.com/photos/912110/pexels-photo-912110.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop"
];

// Helper function to get photo URL
function getMountainPhoto($mountain, $imgBank) {
    if (!empty($mountain['image'])) {
        return $mountain['image'];
    }
    $hash = 0;
    for ($i = 0; $i < strlen($mountain['name']); $i++) {
        $hash = (($hash << 5) - $hash) + ord($mountain['name'][$i]);
        $hash = $hash & $hash;
    }
    return $imgBank[abs($hash) % count($imgBank)];
}

// Parse JSON fields safely
function parseJsonField($field) {
    if (empty($field)) return [];
    $decoded = json_decode($field, true);
    return is_array($decoded) ? $decoded : [$field];
}
// Handle AJAX requests - MUST be before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // Disable error reporting for AJAX requests to prevent HTML output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    header('Content-Type: application/json');
    
    try {
        // Handle getting activity data
        if (isset($_POST['action']) && $_POST['action'] === 'get_activity') {
            $mountain_id = $_POST['mountain_id'] ?? 0;
            
            if (!$mountain_id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            // Get active day hikes with details
            $stmt = $pdo->prepare("
                SELECT b.*, 
                       u.name as user_name, 
                       gu.name as guide_name
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN guides g ON b.guide_id = g.id
                LEFT JOIN users gu ON g.user_id = gu.id
                WHERE b.mountain_id = ? AND b.status = 'active' AND b.hike_date >= CURDATE()
                ORDER BY b.hike_date ASC
            ");
            $stmt->execute([$mountain_id]);
            $dayHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get active camping bookings with details
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       u.name as user_name, 
                       gu.name as guide_name
                FROM camping_bookings c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN guides g ON c.guide_id = g.id
                LEFT JOIN users gu ON g.user_id = gu.id
                WHERE c.mountain_id = ? AND c.status = 'active' AND c.start_date >= CURDATE()
                ORDER BY c.start_date ASC
            ");
            $stmt->execute([$mountain_id]);
            $campingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total counts for summary
            $stmt = $pdo->prepare("
                SELECT SUM(number_of_hikers) as total_hikers 
                FROM bookings 
                WHERE mountain_id = ? AND status = 'active' AND hike_date >= CURDATE()
            ");
            $stmt->execute([$mountain_id]);
            $hikerCount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT SUM(number_of_hikers) as total_campers 
                FROM camping_bookings 
                WHERE mountain_id = ? AND status = 'active' AND start_date >= CURDATE()
            ");
            $stmt->execute([$mountain_id]);
            $camperCount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'day_hikes' => $dayHikes,
                'camping_bookings' => $campingBookings,
                'summary' => [
                    'total_hikes' => count($dayHikes),
                    'total_campers' => (int)($camperCount['total_campers'] ?? 0),
                    'total_hikers' => (int)($hikerCount['total_hikers'] ?? 0)
                ]
            ]);
            exit;
        }
        
        // Handle updating mountain
        if (isset($_POST['action']) && $_POST['action'] === 'update_mountain') {
            $id = $_POST['id'] ?? 0;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            // Define allowed values for ENUM fields
            $allowedDifficulties = ['Easy', 'Easy to Moderate', 'Moderate', 'Difficult', 'Very Difficult'];
            $allowedCrowdLevels = ['Low', 'Moderate', 'High', 'Very High'];
            
            $updateFields = [];
            $params = [];
            
            // Validate and process difficulty
            if (isset($_POST['difficulty'])) {
                $difficulty = trim($_POST['difficulty']);
                if (!in_array($difficulty, $allowedDifficulties)) {
                    echo json_encode(['success' => false, 'message' => "Invalid difficulty value. Allowed: " . implode(', ', $allowedDifficulties)]);
                    exit;
                }
                $updateFields[] = "difficulty = ?";
                $params[] = $difficulty;
            }
            
            // Validate and process crowdLevel
            if (isset($_POST['crowdLevel'])) {
                $crowdLevel = trim($_POST['crowdLevel']);
                if (!in_array($crowdLevel, $allowedCrowdLevels)) {
                    echo json_encode(['success' => false, 'message' => "Invalid crowd level. Allowed: " . implode(', ', $allowedCrowdLevels)]);
                    exit;
                }
                $updateFields[] = "crowdLevel = ?";
                $params[] = $crowdLevel;
            }
            
            // Process other fields
            if (isset($_POST['duration'])) {
                $updateFields[] = "duration = ?";
                $params[] = trim($_POST['duration']);
            }
            
            if (isset($_POST['fee'])) {
                $fee = floatval($_POST['fee']);
                if ($fee < 0) {
                    echo json_encode(['success' => false, 'message' => 'Fee cannot be negative']);
                    exit;
                }
                $updateFields[] = "fee = ?";
                $params[] = $fee;
            }
            
            if (isset($_POST['jumpOff'])) {
                $updateFields[] = "jumpOff = ?";
                $params[] = trim($_POST['jumpOff']);
            }
            
            if (isset($_POST['description'])) {
                $updateFields[] = "description = ?";
                $params[] = trim($_POST['description']);
            }
            
            if (isset($_POST['rating'])) {
                $rating = floatval($_POST['rating']);
                if ($rating < 0 || $rating > 5) {
                    echo json_encode(['success' => false, 'message' => 'Rating must be between 0 and 5']);
                    exit;
                }
                $updateFields[] = "rating = ?";
                $params[] = $rating;
            }
            
            if (isset($_POST['weatherAdvisory'])) {
                $updateFields[] = "weatherAdvisory = ?";
                $params[] = trim($_POST['weatherAdvisory']);
            }
            
            if (isset($_POST['peakTimes'])) {
                $updateFields[] = "peakTimes = ?";
                $params[] = trim($_POST['peakTimes']);
            }
            
            // Handle JSON fields
            if (isset($_POST['rules'])) {
                $rulesText = trim($_POST['rules']);
                $rulesArray = $rulesText ? array_filter(array_map('trim', explode("\n", $rulesText))) : [];
                $updateFields[] = "rules = ?";
                $params[] = json_encode(array_values($rulesArray));
            }
            
            if (isset($_POST['envReminders'])) {
                $envText = trim($_POST['envReminders']);
                $envArray = $envText ? array_filter(array_map('trim', explode("\n", $envText))) : [];
                $updateFields[] = "envReminders = ?";
                $params[] = json_encode(array_values($envArray));
            }
            
            if (isset($_POST['hazards'])) {
                $hazardsText = trim($_POST['hazards']);
                $hazardsArray = $hazardsText ? array_filter(array_map('trim', explode("\n", $hazardsText))) : [];
                $updateFields[] = "hazards = ?";
                $params[] = json_encode(array_values($hazardsArray));
            }
            
            if (!empty($updateFields)) {
                $params[] = $id;
                $sql = "UPDATE mountains SET " . implode(', ', $updateFields) . " WHERE id = ?";
                
                // For debugging - log the query and values
                error_log("SQL: " . $sql);
                error_log("Params: " . print_r($params, true));
                
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    echo json_encode(['success' => true, 'message' => 'Mountain updated successfully']);
                } else {
                    $error = $stmt->errorInfo();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $error[2]]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}
// Get crowd data for chart
$crowdValues = [
    'Low' => 50,
    'Moderate' => 150,
    'High' => 250,
    'Very High' => 350
];

$chartLabels = [];
$chartData = [];
foreach ($mountains as $mountain) {
    $chartLabels[] = $mountain['name'];
    $crowdLevel = $mountain['crowdLevel'] ?? 'Moderate';
    $chartData[] = $crowdValues[$crowdLevel] ?? 150;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Mountains</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="shared.css">
  <!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet Heatmap plugin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
  <style>
    /* Keep your existing styles here - they remain the same */
    .mtn-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; margin-bottom: 48px; }
    .mtn-card { background: white; border-radius: 28px; box-shadow: 0 8px 20px rgba(0,0,0,0.02), 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s ease; overflow: hidden; border: 1px solid #EFF2F8; display: flex; flex-direction: column; }
    .mtn-card:hover { transform: translateY(-3px); box-shadow: 0 20px 28px -12px rgba(0,0,0,0.12); }
    .mtn-photo { height: 170px; background-size: cover; background-position: center 30%; position: relative; }
    .mtn-badge-overlay { position: absolute; top: 14px; right: 16px; }
    .badge { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 40px; color: white; letter-spacing: 0.3px; display: inline-block; }
    .badge.green { background: #d1fae5; color: #065f46; }
    .badge.amber { background: #fef3c7; color: #92400e; }
    .badge.red   { background: #fee2e2; color: #991b1b; }
    .badge.white { background: rgba(0,0,0,0.6); color: white; }
    .mtn-info { padding: 20px 20px 18px; }
    .mtn-name { font-size: 1.6rem; font-weight: 600; font-family: 'Cormorant Garamond', serif; margin-bottom: 12px; }
    .mtn-detail { font-size: 13px; color: #5B6A7E; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    .elev-bar { background: #EFF2F6; height: 6px; border-radius: 12px; margin: 12px 0; overflow: hidden; }
    .elev-fill { background: #111318; height: 100%; border-radius: 12px; }
    .mtn-actions { display: flex; gap: 10px; margin-top: 14px; }
    .mtn-actions .btn { font-size: 12px; padding: 7px 12px; }
    @media (max-width: 780px) { .mtn-grid { gap: 20px; } .mtn-name { font-size: 1.3rem; } }
    @media (max-width: 640px) { .mtn-grid { grid-template-columns: 1fr; } }
    .btn { border: none; background: transparent; font-family: 'Inter', sans-serif; font-weight: 500; padding: 8px 18px; border-radius: 40px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; }
    .btn-primary { background: #111318; color: white; }
    .btn-primary:hover { background: #2C2F3A; }
    .btn-ghost { background: #F0F2F5; color: #1F2A3A; }
    .btn-ghost:hover { background: #E2E6EC; }
    .btn-success { background: #1E7B48; color: white; }
    .btn-warning { background: #fef3c7; color: #92400e; }
    .btn-warning:hover { background: #fde68a; }
    .active-hikers-badge { display: inline-flex; align-items: center; gap: 5px; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-left: 8px; }
    .active-hikers-badge i { font-size: 9px; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(10,12,18,0.55); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 28px; width: 100%; max-width: 660px; max-height: 90vh; overflow-y: auto; box-shadow: 0 32px 64px rgba(0,0,0,0.18); display: flex; flex-direction: column; }
    .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 24px 28px 16px; border-bottom: 1px solid #EFF2F6; position: sticky; top: 0; background: white; z-index: 2; }
    .modal-title { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: #111318; }
    .modal-subtitle { font-size: 11px; color: #8A99AE; margin-top: 3px; font-family: 'DM Mono', monospace; letter-spacing: 0.5px; }
    .modal-close { width: 36px; height: 36px; border-radius: 50%; border: none; background: #F2F4F8; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #5B6A7E; font-size: 14px; transition: 0.15s; flex-shrink: 0; }
    .step-indicator { display: flex; align-items: center; padding: 16px 28px 0; }
    .step-dot { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #B0BAC8; font-family: 'DM Mono', monospace; white-space: nowrap; }
    .step-dot.active { color: #111318; }
    .step-dot.done { color: #1E7B48; }
    .step-dot span { width: 24px; height: 24px; border-radius: 50%; background: #EFF2F6; color: #9AA6B5; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
    .step-dot.active span { background: #111318; color: white; }
    .step-dot.done span { background: #1E7B48; color: white; }
    .step-line { flex: 1; height: 1px; background: #E5E9EF; margin: 0 10px; }
    .modal-body { padding: 20px 28px 28px; flex: 1; }
    .modal-footer { padding: 16px 28px 24px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #EFF2F6; position: sticky; bottom: 0; background: white; z-index: 2; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { font-size: 11px; font-weight: 600; letter-spacing: 0.8px; text-transform: uppercase; color: #6C7A8E; }
    .form-control { border: 1.5px solid #E5E9EF; border-radius: 12px; padding: 10px 14px; font-size: 13.5px; font-family: 'Inter', sans-serif; color: #111318; background: #FAFBFC; outline: none; transition: border-color 0.15s; width: 100%; }
    .form-control:focus { border-color: #111318; background: white; }
    textarea.form-control { resize: vertical; min-height: 80px; }
    .fee-wrap { position: relative; }
    .fee-prefix { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 14px; font-weight: 600; color: #111318; }
    .fee-wrap .form-control { padding-left: 28px; }
    .activity-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
    .stat-card { background: #F8F9FB; border-radius: 16px; padding: 12px; text-align: center; border: 1px solid #EFF2F6; }
    .stat-number { font-size: 24px; font-weight: 700; color: #111318; }
    .stat-label { font-size: 11px; color: #8A99AE; margin-top: 4px; }
    .activity-section { margin-bottom: 24px; }
    .section-header { font-size: 14px; font-weight: 600; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #EFF2F6; }
    .activity-list { max-height: 300px; overflow-y: auto; }
    .activity-item { padding: 12px; border-bottom: 1px solid #EFF2F6; transition: background 0.15s; }
    .activity-item:hover { background: #F8F9FB; }
    .activity-item:last-child { border-bottom: none; }
    .activity-type { font-size: 11px; font-weight: 600; color: #1E7B48; margin-bottom: 6px; }
    .activity-detail { font-size: 13px; color: #111318; font-weight: 500; }
    .activity-meta { font-size: 11px; color: #8A99AE; margin-top: 6px; display: flex; gap: 12px; flex-wrap: wrap; }
    .badge-status { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .badge-status.paid { background: #d1fae5; color: #065f46; }
    .badge-status.pending { background: #fef3c7; color: #92400e; }
    .empty-activity { text-align: center; padding: 40px 20px; color: #8A99AE; }
  </style>
</head>
<body data-page="mountains">
<div class="app">

  <aside class="sidebar">
    <div>
      <div class="logo">
        <div class="logo-wordmark">
          <div class="logo-icon">
            <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#111318" opacity="0.9"/>
              <path d="M14 16L18 8L24 22H14V16Z" fill="#111318" opacity="0.35"/>
            </svg>
          </div>
          LAKBAY
        </div>
        <div class="logo-sub">wilderness: Silence beneath steps</div>
      </div>
      <div style="margin-bottom:8px; padding-left:28px;">
        <div class="nav-section-label">Navigation</div>
      </div>
      <ul class="nav-list">
        <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item active" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
      </ul>
    </div>
    <div>
      <button class="logout-btn" onclick="showLogoutModal()" style="width:100%;display:flex;align-items:center;gap:12px;padding:10px 16px;background:transparent;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.82rem;font-weight:400;color:#dc2626;cursor:pointer;">
        <i class="fas fa-right-from-bracket" style="width:16px;font-size:0.75rem;"></i> 
        Log Out
      </button>
      <div class="sidebar-footer">
        <div class="status-dot"></div> 
        TEAM AURIX
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-mountain"></i> Mountains</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user" style="cursor: pointer;">
          <div class="avatar" id="topbarAvatar">
            <?php if (!empty($_SESSION['user_avatar'])): ?>
              <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
              <?= htmlspecialchars($adminInitial) ?>
            <?php endif; ?>
          </div>
          <?= htmlspecialchars($adminName) ?>
          <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="section-header">
        <div class="section-title">Mountain Registry</div>
      </div>
      <div class="mtn-grid">
        <?php foreach ($mountains as $mountain): 
            $elevNum = (int) preg_replace('/[^0-9]/', '', $mountain['elevation']);
            $elevPercent = $maxElevation > 0 ? min(100, round(($elevNum / $maxElevation) * 100)) : 50;
            $photoUrl = getMountainPhoto($mountain, $imgBank);
            
            if ($mountain['status'] === 'Open') {
                $statusClass = 'green';
            } elseif ($mountain['status'] === 'Limited') {
                $statusClass = 'amber';
            } elseif ($mountain['status'] === 'Closed') {
                $statusClass = 'red';
            } else {
                $statusClass = 'white';
            }
            
            $totalActive = ($mountain['active_hikers_today'] ?? 0) + ($mountain['active_camping'] ?? 0);
        ?>
        <div class="mtn-card" data-mountain-id="<?= $mountain['id'] ?>">
          <div class="mtn-photo" style="background-image:url('<?= htmlspecialchars($photoUrl) ?>');">
            <div class="mtn-badge-overlay"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($mountain['status']) ?></span></div>
          </div>
          <div class="mtn-info">
            <div class="mtn-name">
              <?= htmlspecialchars($mountain['name']) ?>
              <?php if ($totalActive > 0): ?>
                <span class="active-hikers-badge"><i class="fas fa-person-hiking"></i> <?= $totalActive ?> active</span>
              <?php endif; ?>
            </div>
            <div class="mtn-detail"><i class="fas fa-arrow-up"></i> <?= htmlspecialchars($mountain['elevation']) ?> · ELEVATION</div>
            <div class="elev-bar"><div class="elev-fill" style="width:<?= $elevPercent ?>%"></div></div>
            <div class="mtn-detail"><i class="fas fa-signal"></i> <?= htmlspecialchars($mountain['difficulty']) ?> · ₱<?= number_format($mountain['fee']) ?> fee</div>
            <div class="mtn-detail"><i class="fas fa-clock"></i> <?= htmlspecialchars($mountain['duration']) ?> &nbsp;·&nbsp; <i class="fas fa-users"></i> <?= htmlspecialchars($mountain['crowdLevel']) ?> Crowd</div>
            <div class="mtn-detail"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($mountain['location']) ?></div>
            <div class="mtn-detail"><i class="fas fa-star"></i> Rating: <?= htmlspecialchars($mountain['rating']) ?> ★</div>
            <div class="mtn-actions">
              <button class="btn edit-mtn-btn" data-id="<?= $mountain['id'] ?>" style="flex:1"><i class="fas fa-edit"></i> Edit</button>
              <button class="btn btn-warning view-activity-btn" data-id="<?= $mountain['id'] ?>" data-name="<?= htmlspecialchars($mountain['name']) ?>" style="flex:1">
                <i class="fas fa-eye"></i> View Activity
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title"><i class="fas fa-chart-simple"></i> Crowd Index — Daily Average</span>
          <button class="btn btn-ghost" id="heatmapBtn"><i class="fas fa-fire"></i> Full heatmap</button>
        </div>
        <div class="chart-wrap" style="height:160px"><canvas id="chartCrowd"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Activity Modal -->
<div class="modal-overlay" id="activityModal">
  <div class="modal-box" style="max-width: 600px;">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="activityModalTitle">Mountain Activity</div>
        <div class="modal-subtitle">Current and Upcoming Bookings</div>
      </div>
      <button class="modal-close" onclick="closeActivityModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="activityModalBody">
      <div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading activity data...</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeActivityModal()">Close</button>
      <button class="btn btn-primary" id="refreshActivityBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modalMtnName">Edit Mountain</div>
        <div class="modal-subtitle" id="modalStepLabel">STEP 1 OF 3 · EDIT DETAILS</div>
      </div>
      <button class="modal-close" id="modalCloseBtn"><i class="fas fa-times"></i></button>
    </div>
    <div class="step-indicator">
      <div class="step-dot active" id="sdot1"><span>1</span>&nbsp;Edit</div>
      <div class="step-line"></div>
      <div class="step-dot" id="sdot2"><span>2</span>&nbsp;Confirm</div>
      <div class="step-line"></div>
      <div class="step-dot" id="sdot3"><span>3</span>&nbsp;Preview</div>
    </div>
    <div class="modal-body" id="stepEditBody">
      <div class="form-grid">
        <div class="form-group full"><label class="form-label">Mountain Name</label><input type="text" class="form-control" id="fName" readonly><input type="hidden" id="fId"></div>
        <div class="form-group"><label class="form-label">Difficulty</label><select class="form-control" id="fDifficulty"><option>Easy</option><option>Easy to Moderate</option><option>Moderate</option><option>Difficult</option><option>Very Difficult</option></select></div>
        <div class="form-group"><label class="form-label">Crowd Level</label><select class="form-control" id="fCrowd"><option>Low</option><option>Moderate</option><option>High</option><option>Very High</option></select></div>
        <div class="form-group"><label class="form-label">Elevation</label><input type="text" class="form-control" id="fElevation" readonly></div>
        <div class="form-group"><label class="form-label">Duration</label><input type="text" class="form-control" id="fDuration" placeholder="e.g., 3-4 hours"></div>
        <div class="form-group"><label class="form-label">Trail Fee</label><div class="fee-wrap"><span class="fee-prefix">₱</span><input type="number" class="form-control" id="fFee"></div></div>
        <div class="form-group"><label class="form-label">Jump-off Location</label><input type="text" class="form-control" id="fJumpoff"></div>
        <div class="form-group full"><label class="form-label">Description</label><textarea class="form-control" id="fDescription" rows="3"></textarea></div>
        <div class="form-group"><label class="form-label">Rating</label><input type="number" step="0.1" class="form-control" id="fRating" placeholder="0-5"></div>
        <div class="form-group full"><label class="form-label">Weather Advisory</label><textarea class="form-control" id="fWeather" rows="2"></textarea></div>
        <div class="form-group full"><label class="form-label">Peak Times</label><textarea class="form-control" id="fPeakTimes" rows="2"></textarea></div>
        <div class="form-group full"><label class="form-label">Rules (one per line)</label><textarea class="form-control" id="fRules" rows="3"></textarea></div>
        <div class="form-group full"><label class="form-label">Environmental Reminders (one per line)</label><textarea class="form-control" id="fEnvReminders" rows="3"></textarea></div>
        <div class="form-group full"><label class="form-label">Hazards (one per line)</label><textarea class="form-control" id="fHazards" rows="3"></textarea></div>
      </div>
    </div>
    <div class="modal-footer" id="stepEditFooter">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" onclick="goToConfirm()">Review Changes</button>
    </div>
    <div class="modal-body" id="stepConfirmBody" style="display:none;">
      <div class="confirm-warn"><i class="fas fa-triangle-exclamation"></i> Are you sure you want to save these changes?</div>
      <div class="confirm-box"><div class="confirm-box-title">Summary of Changes</div><div id="confirmRows"></div></div>
    </div>
    <div class="modal-footer" id="stepConfirmFooter" style="display:none;">
      <button class="btn btn-ghost" onclick="goBackToForm()">Back to Form</button>
      <button class="btn btn-primary" onclick="goToPreview()">Preview Card</button>
    </div>
    <div class="modal-body" id="stepPreviewBody" style="display:none;">
      <div class="preview-label">Card Preview</div>
      <div class="preview-card-wrap" id="previewCardWrap"></div>
    </div>
    <div class="modal-footer" id="stepPreviewFooter" style="display:none;">
      <button class="btn btn-ghost" onclick="goToConfirm()">Back</button>
      <button class="btn btn-success" onclick="saveChanges()">Save Changes</button>
    </div>
  </div>
</div>

<!-- Heatmap Modal -->
<div class="modal-overlay" id="heatmapModal">
  <div class="modal-box" style="max-width: 900px; max-height: 85vh;">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="heatmapModalTitle">Traffic Heatmap</div>
        <div class="modal-subtitle">Real-time crowd density visualization</div>
      </div>
      <button class="modal-close" onclick="closeHeatmapModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding: 0;">
      <div style="padding: 12px 20px; background: #F8F9FB; border-bottom: 1px solid #EFF2F6;">
        <select id="mountainSelect" class="form-control" style="max-width: 300px; display: inline-block; margin-right: 10px;">
          <option value="">Select a mountain...</option>
          <?php foreach ($mountains as $mountain): ?>
            <option value="<?= $mountain['id'] ?>" data-name="<?= htmlspecialchars($mountain['name']) ?>">
              <?= htmlspecialchars($mountain['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" id="loadHeatmapBtn" style="padding: 8px 16px;">
          <i class="fas fa-map-marked-alt"></i> Load Heatmap
        </button>
        <div style="float: right; font-size: 12px; color: #8A99AE; margin-top: 8px;">
          <span><i class="fas fa-thermometer-full" style="color: #ff0000;"></i> High Traffic</span>
          <span style="margin-left: 12px;"><i class="fas fa-thermometer-half" style="color: #ffa500;"></i> Medium Traffic</span>
          <span style="margin-left: 12px;"><i class="fas fa-thermometer-empty" style="color: #ffcc00;"></i> Low Traffic</span>
        </div>
      </div>
      <div id="heatmapContainer" style="height: 500px; width: 100%; background: #f0f0f0; position: relative;">
        <div id="map" style="height: 100%; width: 100%;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <div style="flex: 1; font-size: 12px; color: #6C7A8E;">
        <i class="fas fa-info-circle"></i> Heatmap shows crowd density based on current bookings and historical data
      </div>
      <button class="btn btn-ghost" onclick="closeHeatmapModal()">Close</button>
    </div>
  </div>
</div>

<script>
const mountainsData = <?php echo json_encode($mountains); ?>;
const imgBank = <?php echo json_encode($imgBank); ?>;
const crowdChartData = <?php echo json_encode($chartData); ?>;
const crowdChartLabels = <?php echo json_encode($chartLabels); ?>;

let currentMountainId = null;

function getPhoto(mtn) {
  if (mtn.image && mtn.image !== 'null') return mtn.image;
  let h = 0;
  for (let i = 0; i < mtn.name.length; i++) {
    h = ((h << 5) - h) + mtn.name.charCodeAt(i);
    h = h & h;
  }
  return imgBank[Math.abs(h) % imgBank.length];
}

let activeMtnId = null;
let originalMountain = null;

// Activity Modal Functions
function openActivityModal(mountainId, mountainName) {
  currentMountainId = mountainId;
  const modal = document.getElementById('activityModal');
  const title = document.getElementById('activityModalTitle');
  title.textContent = `${mountainName} - Activity`;
  modal.classList.add('open');
  loadActivityData(mountainId);
}

function closeActivityModal() {
  document.getElementById('activityModal').classList.remove('open');
}

function loadActivityData(mountainId) {
  const body = document.getElementById('activityModalBody');
  body.innerHTML = '<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading activity data...</div>';
  
  // Fetch real data from the database via AJAX
  const formData = new FormData();
  formData.append('action', 'get_activity');
  formData.append('mountain_id', mountainId);
  
  fetch(window.location.href, { 
    method: 'POST', 
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData 
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return response.json();
  })
  .then(result => {
    if (result.success) {
      displayActivityData(result);
    } else {
      body.innerHTML = `<div class="empty-activity"><i class="fas fa-exclamation-triangle"></i><p>Error: ${result.message || 'Failed to load activity data'}</p></div>`;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    body.innerHTML = '<div class="empty-activity"><i class="fas fa-exclamation-triangle"></i><p>Failed to load activity data. Please check the console for errors.</p></div>';
  });
}

function displayActivityData(data) {
  const body = document.getElementById('activityModalBody');
  const dayHikes = data.day_hikes || [];
  const campingBookings = data.camping_bookings || [];
  const summary = data.summary || { total_hikes: 0, total_hikers: 0, total_campers: 0 };
  
  let html = `
    <div class="activity-stats">
      <div class="stat-card">
        <div class="stat-number">${summary.total_hikes}</div>
        <div class="stat-label">Active Day Hikes</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">${summary.total_hikers || 0}</div>
        <div class="stat-label">Total Hikers</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">${summary.total_campers || 0}</div>
        <div class="stat-label">Total Campers</div>
      </div>
    </div>
  `;
  
  // Day Hikes Section
  html += `<div class="activity-section">
    <div class="section-header"><i class="fas fa-hiking"></i> Day Hike Bookings (${dayHikes.length})</div>
    <div class="activity-list">
  `;
  
  if (dayHikes.length === 0) {
    html += `<div class="empty-activity"><i class="fas fa-info-circle"></i><p>No active day hikes scheduled</p></div>`;
  } else {
    dayHikes.forEach(hike => {
      html += `
        <div class="activity-item">
          <div class="activity-type">
            <i class="fas fa-calendar-day"></i> Booking #${hike.booking_number || 'N/A'}
            <span class="badge-status ${hike.payment_status || 'pending'}">${hike.payment_status || 'pending'}</span>
          </div>
          <div class="activity-detail">
            <strong>${hike.number_of_hikers || 0}</strong> hiker(s) · Guide: ${hike.guide_name || 'Not assigned'}
          </div>
          <div class="activity-meta">
            <span><i class="fas fa-calendar"></i> Hike Date: ${hike.hike_date || 'N/A'}</span>
            <span><i class="fas fa-tag"></i> ₱${parseFloat(hike.total_amount || 0).toLocaleString()}</span>
          </div>
          ${hike.special_requests ? `<div class="activity-meta"><i class="fas fa-comment"></i> ${escapeHtml(hike.special_requests)}</div>` : ''}
        </div>
      `;
    });
  }
  
  html += `</div></div>`;
  
  // Camping Section
  html += `<div class="activity-section">
    <div class="section-header"><i class="fas fa-campground"></i> Camping Bookings (${campingBookings.length})</div>
    <div class="activity-list">
  `;
  
  if (campingBookings.length === 0) {
    html += `<div class="empty-activity"><i class="fas fa-info-circle"></i><p>No active camping trips scheduled</p></div>`;
  } else {
    campingBookings.forEach(camping => {
      const nights = camping.number_of_nights || 0;
      html += `
        <div class="activity-item">
          <div class="activity-type">
            <i class="fas fa-tent"></i> Booking #${camping.booking_number || 'N/A'}
            <span class="badge-status ${camping.payment_status || 'pending'}">${camping.payment_status || 'pending'}</span>
          </div>
          <div class="activity-detail">
            <strong>${camping.number_of_hikers || 0}</strong> camper(s) · ${nights} night(s) · Guide: ${camping.guide_name || 'Not assigned'}
          </div>
          <div class="activity-meta">
            <span><i class="fas fa-calendar-alt"></i> ${camping.start_date || 'N/A'} → ${camping.end_date || 'N/A'}</span>
            <span><i class="fas fa-map-marker-alt"></i> ${camping.campsite_name || 'No campsite specified'}</span>
          </div>
          <div class="activity-meta">
            <span><i class="fas fa-tag"></i> ₱${parseFloat(camping.total_amount || 0).toLocaleString()}</span>
            ${camping.equipment_rental ? `<span><i class="fas fa-box"></i> Equipment Rental Included</span>` : ''}
          </div>
          ${camping.special_requests ? `<div class="activity-meta"><i class="fas fa-comment"></i> ${escapeHtml(camping.special_requests)}</div>` : ''}
        </div>
      `;
    });
  }
  
  html += `</div></div>`;
  
  body.innerHTML = html;
}

// Helper function to escape HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Edit Modal functions
function openEditModal(id) {
  activeMtnId = id;
  const m = mountainsData.find(x => x.id == id);
  if (!m) return;
  
  originalMountain = {...m};
  document.getElementById('fId').value = m.id;
  document.getElementById('fName').value = m.name;
  document.getElementById('fElevation').value = m.elevation;
  document.getElementById('fDifficulty').value = m.difficulty;
  document.getElementById('fCrowd').value = m.crowdLevel;
  document.getElementById('fDuration').value = m.duration;
  document.getElementById('fFee').value = m.fee;
  document.getElementById('fJumpoff').value = m.jumpOff;
  document.getElementById('fDescription').value = m.description || '';
  document.getElementById('fRating').value = m.rating || '';
  document.getElementById('fWeather').value = m.weatherAdvisory || '';
  document.getElementById('fPeakTimes').value = m.peakTimes || '';
  
  try { 
    let rules = JSON.parse(m.rules || '[]');
    document.getElementById('fRules').value = Array.isArray(rules) ? rules.join('\n') : m.rules || '';
  } catch(e) { 
    document.getElementById('fRules').value = m.rules || ''; 
  }
  
  try { 
    let envReminders = JSON.parse(m.envReminders || '[]');
    document.getElementById('fEnvReminders').value = Array.isArray(envReminders) ? envReminders.join('\n') : m.envReminders || '';
  } catch(e) { 
    document.getElementById('fEnvReminders').value = m.envReminders || ''; 
  }
  
  try { 
    let hazards = JSON.parse(m.hazards || '[]');
    document.getElementById('fHazards').value = Array.isArray(hazards) ? hazards.join('\n') : m.hazards || '';
  } catch(e) { 
    document.getElementById('fHazards').value = m.hazards || ''; 
  }
  
  document.getElementById('modalMtnName').textContent = m.name;
  showStep(1);
  document.getElementById('editModal').classList.add('open');
}

function closeModal() { 
  document.getElementById('editModal').classList.remove('open'); 
}

function showStep(n) {
  const steps = ['Edit', 'Confirm', 'Preview'];
  steps.forEach((step, i) => {
    const s = i + 1;
    const body = document.getElementById(`step${step}Body`);
    const footer = document.getElementById(`step${step}Footer`);
    if (body) body.style.display = s === n ? 'block' : 'none';
    if (footer) footer.style.display = s === n ? 'flex' : 'none';
    const dot = document.getElementById(`sdot${s}`);
    if (dot) {
      dot.className = 'step-dot';
      if (s < n) dot.classList.add('done');
      if (s === n) dot.classList.add('active');
    }
  });
  const labels = ['STEP 1 OF 3 · EDIT DETAILS', 'STEP 2 OF 3 · CONFIRM CHANGES', 'STEP 3 OF 3 · PREVIEW CARD'];
  document.getElementById('modalStepLabel').textContent = labels[n - 1];
}

function goToConfirm() {
  const rows = [
    ['Difficulty', originalMountain.difficulty, document.getElementById('fDifficulty').value],
    ['Crowd Level', originalMountain.crowdLevel, document.getElementById('fCrowd').value],
    ['Duration', originalMountain.duration, document.getElementById('fDuration').value],
    ['Trail Fee', '₱' + originalMountain.fee, '₱' + document.getElementById('fFee').value],
    ['Jump-off', originalMountain.jumpOff, document.getElementById('fJumpoff').value],
    ['Rating', originalMountain.rating || '-', document.getElementById('fRating').value || '-']
  ];
  const container = document.getElementById('confirmRows');
  container.innerHTML = '';
  rows.forEach(([label, oldVal, newVal]) => {
    const changed = String(oldVal || '') !== String(newVal || '');
    const row = document.createElement('div');
    row.className = 'confirm-row';
    row.innerHTML = `<span class="ck">${label}</span><span class="cv" style="${changed ? 'color:#1E7B48;' : ''}">${newVal}${changed ? ' <span style="font-size:10px;">(changed)</span>' : ''}</span>`;
    container.appendChild(row);
  });
  showStep(2);
}

function goBackToForm() { 
  showStep(1); 
}

function goToPreview() {
  const m = originalMountain;
  const photoSrc = getPhoto(m);
  const difficulty = document.getElementById('fDifficulty').value;
  const fee = parseInt(document.getElementById('fFee').value) || 0;
  const crowd = document.getElementById('fCrowd').value;
  const duration = document.getElementById('fDuration').value;
  const statusClass = m.status === 'Open' ? 'green' : 'red';
  const elevNum = parseInt(m.elevation) || 1000;
  const maxElev = 3000;
  const elevPercent = Math.min(100, (elevNum / maxElev) * 100);
  document.getElementById('previewCardWrap').innerHTML = `
    <div class="mtn-card" style="max-width:440px">
      <div class="mtn-photo" style="background-image:url('${photoSrc}');">
        <div class="mtn-badge-overlay"><span class="badge ${statusClass}">${m.status}</span></div>
      </div>
      <div class="mtn-info">
        <div class="mtn-name">${m.name}</div>
        <div class="mtn-detail"><i class="fas fa-arrow-up"></i> ${m.elevation} · ELEVATION</div>
        <div class="elev-bar"><div class="elev-fill" style="width:${elevPercent}%"></div></div>
        <div class="mtn-detail"><i class="fas fa-signal"></i> ${difficulty} · ₱${fee} fee</div>
        <div class="mtn-detail"><i class="fas fa-clock"></i> ${duration} &nbsp;·&nbsp; <i class="fas fa-users"></i> ${crowd} Crowd</div>
        <div class="mtn-detail"><i class="fas fa-map-pin"></i> ${m.location}</div>
        <div class="mtn-detail"><i class="fas fa-star"></i> Rating: ${document.getElementById('fRating').value || m.rating} ★</div>
      </div>
    </div>`;
  showStep(3);
}
function saveChanges() {
  // Get values and ensure they're valid
  const difficulty = document.getElementById('fDifficulty').value;
  const crowdLevel = document.getElementById('fCrowd').value;
  const fee = parseFloat(document.getElementById('fFee').value);
  
  // Validate before sending
  const allowedDifficulties = ['Easy', 'Easy to Moderate', 'Moderate', 'Difficult', 'Very Difficult'];
  const allowedCrowdLevels = ['Low', 'Moderate', 'High', 'Very High'];
  
  if (!allowedDifficulties.includes(difficulty)) {
    alert('Invalid difficulty value. Please select from the dropdown.');
    return;
  }
  
  if (!allowedCrowdLevels.includes(crowdLevel)) {
    alert('Invalid crowd level. Please select from the dropdown.');
    return;
  }
  
  if (isNaN(fee) || fee < 0) {
    alert('Please enter a valid fee amount.');
    return;
  }
  
  const rating = parseFloat(document.getElementById('fRating').value);
  if (!isNaN(rating) && (rating < 0 || rating > 5)) {
    alert('Rating must be between 0 and 5');
    return;
  }
  
  const formData = new FormData();
  formData.append('action', 'update_mountain');
  formData.append('id', activeMtnId);
  formData.append('difficulty', difficulty);
  formData.append('crowdLevel', crowdLevel);
  formData.append('duration', document.getElementById('fDuration').value);
  formData.append('fee', fee);
  formData.append('jumpOff', document.getElementById('fJumpoff').value);
  formData.append('description', document.getElementById('fDescription').value);
  formData.append('rating', rating || 0);
  formData.append('weatherAdvisory', document.getElementById('fWeather').value);
  formData.append('peakTimes', document.getElementById('fPeakTimes').value);
  formData.append('rules', document.getElementById('fRules').value);
  formData.append('envReminders', document.getElementById('fEnvReminders').value);
  formData.append('hazards', document.getElementById('fHazards').value);

  fetch(window.location.href, { 
    method: 'POST', 
    headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
    body: formData 
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      alert(`✅ ${originalMountain.name} updated successfully.`);
      location.reload();
    } else {
      alert('Error: ' + (result.message || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error saving changes. Please try again.\n' + error);
  });
}

function updateDate() {
  const d = new Date();
  document.getElementById('liveDate').textContent = d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() + '  ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
updateDate(); 
setInterval(updateDate, 1000);

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { 
    if(item.dataset.href) window.location.href = item.dataset.href; 
  });
});

// Event Listeners
document.querySelectorAll('.edit-mtn-btn').forEach(btn => {
  btn.addEventListener('click', () => openEditModal(parseInt(btn.dataset.id)));
});

document.querySelectorAll('.view-activity-btn').forEach(btn => {
  btn.addEventListener('click', () => openActivityModal(parseInt(btn.dataset.id), btn.dataset.name));
});

document.getElementById('modalCloseBtn')?.addEventListener('click', closeModal);

document.getElementById('refreshActivityBtn')?.addEventListener('click', () => {
  if (currentMountainId) {
    loadActivityData(currentMountainId);
  }
});

// Chart
let crowdChart = null;
function initCrowdChart() {
  const ctx = document.getElementById('chartCrowd');
  if (!ctx) return;
  if (crowdChart) crowdChart.destroy();
  crowdChart = new Chart(ctx, {
    type: 'bar',
    data: { 
      labels: crowdChartLabels, 
      datasets: [{ 
        data: crowdChartData, 
        borderRadius: 8, 
        barPercentage: 0.65, 
        backgroundColor: 'rgba(17,19,24,0.85)' 
      }] 
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false, 
      plugins: { legend: { display: false } }, 
      scales: { 
        x: { grid: { display: false } }, 
        y: { 
          grid: { color: '#EFF2F6' }, 
          title: { display: true, text: 'avg visitors/day' } 
        } 
      } 
    }
  });
}
initCrowdChart();

// Make topbar user clickable
document.addEventListener('DOMContentLoaded', function() {
  const topbarUser = document.querySelector('.topbar-user');
  if (topbarUser) {
    topbarUser.addEventListener('click', function(e) {
      e.preventDefault();
      if (typeof openProfileModal === 'function') {
        openProfileModal();
      } else {
        console.error('openProfileModal function not found!');
        alert('Profile modal function not loaded. Please refresh the page.');
      }
    });
  }
});

// Heatmap variables
let heatmapMap = null;
let heatLayer = null;
let currentMarkers = [];
// Mountain coordinates (realistic locations for Nasugbu mountains)
const mountainLocations = {
  1: { // Mt. Batulao
    name: 'Mt. Batulao',
    lat: 14.0408,
    lng: 120.8014,
    zoom: 14,
    trails: [
      { lat: 14.0538, lng: 120.8197, name: 'Evercrest Golf Course Jump-off' },
      { lat: 14.0505, lng: 120.8115, name: 'Fork (Old/New Trail)' },
      { lat: 14.0475, lng: 120.8080, name: 'Camp 1' },
      { lat: 14.0450, lng: 120.8055, name: 'Camp 8' },
      { lat: 14.0408, lng: 120.8014, name: 'Summit (Peak 10)' }
    ]
  },
  2: { // Mt. Apayang
    name: 'Mt. Apayang',
    lat: 14.0890,
    lng: 120.7554,
    zoom: 14,
    trails: [
      { lat: 14.1020, lng: 120.7410, name: 'Sitio Bayabasan Jump-off' },
      { lat: 14.0980, lng: 120.7450, name: 'River Crossing' },
      { lat: 14.0935, lng: 120.7500, name: 'Grassland' },
      { lat: 14.0890, lng: 120.7554, name: 'Mt. Apayang Summit' }
    ]
  },
  3: { // Mt. Lantik
    name: 'Mt. Lantik',
    lat: 14.0620,
    lng: 120.7675,
    zoom: 14,
    trails: [
      { lat: 14.0750, lng: 120.7580, name: 'Barangay Papaya Jump-off' },
      { lat: 14.0700, lng: 120.7600, name: 'Woodland' },
      { lat: 14.0650, lng: 120.7635, name: 'Steep Ascent' },
      { lat: 14.0620, lng: 120.7675, name: 'Mt. Lantik Summit' }
    ]
  },
  4: { // Mt. Talamitam
    name: 'Mt. Talamitam',
    lat: 14.0931,
    lng: 120.7533,
    zoom: 14,
    trails: [
      { lat: 14.0365, lng: 120.7835, name: 'Starting Point' },
      { lat: 14.0370, lng: 120.7840, name: 'Rolling Hills' },
      { lat: 14.0375, lng: 120.7845, name: 'Camp 1' },
      { lat: 14.0380, lng: 120.7850, name: 'Viewpoint' },
      { lat: 14.0385, lng: 120.7855, name: 'Peak' },
      { lat: 14.0390, lng: 120.7860, name: 'Descent' },
      { lat: 14.0395, lng: 120.7865, name: 'Exit Point' }
    ]
  }
};

// Generate realistic heatmap data along the trail path
function generateHeatmapData(mountainId) {
  const mountain = mountainLocations[mountainId];
  if (!mountain) return [];
  
  const heatData = [];
  const now = new Date();
  const hour = now.getHours();
  const isWeekend = now.getDay() === 0 || now.getDay() === 6;
  
  // Base crowd factor (0-1) based on time of day and day of week
  let baseCrowdFactor = isWeekend ? (hour >= 6 && hour <= 17 ? 0.8 : 0.5) : (hour >= 8 && hour <= 16 ? 0.6 : 0.4);
  
  // Generate interpolated points along the trail path segments
  for (let i = 0; i < mountain.trails.length - 1; i++) {
    const p1 = mountain.trails[i];
    const p2 = mountain.trails[i+1];
    
    // Number of interpolated points between p1 and p2 (denser line)
    const numPoints = 80; 
    
    for (let j = 0; j <= numPoints; j++) {
      const fraction = j / numPoints;
      const lat = p1.lat + (p2.lat - p1.lat) * fraction;
      const lng = p1.lng + (p2.lng - p1.lng) * fraction;
      
      // Determine intensity based on proximity to start, middle, or peak
      let intensity = baseCrowdFactor;
      
      if (i === mountain.trails.length - 2 && fraction > 0.7) {
          // Approaching summit
          intensity = Math.min(1, baseCrowdFactor + 0.3);
      } else if (i === 0 && fraction < 0.3) {
          // At jump-off
          intensity = Math.min(1, baseCrowdFactor + 0.15);
      } else {
          // Mid-trail
          intensity = Math.max(0.1, baseCrowdFactor - 0.2);
      }
      
      // Add slight random noise to intensity
      intensity = Math.min(1, Math.max(0.1, intensity + (Math.random() * 0.2 - 0.1)));
      
      // Add main point on the trail
      heatData.push([lat, lng, intensity]);
      
      // Add small lateral spread around the trail (simulating trail width and scattered hikers)
      if (j % 3 === 0) {
          heatData.push([
              lat + (Math.random() - 0.5) * 0.0004, 
              lng + (Math.random() - 0.5) * 0.0004, 
              intensity * 0.7
          ]);
      }
    }
  }
  
  // Add heavy clusters exactly at the waypoints
  mountain.trails.forEach((trail, index) => {
      let nodeIntensity = baseCrowdFactor + 0.2;
      if (index === mountain.trails.length - 1) nodeIntensity += 0.2; // Summit
      
      for (let k = 0; k < 15; k++) {
          heatData.push([
              trail.lat + (Math.random() - 0.5) * 0.0006, 
              trail.lng + (Math.random() - 0.5) * 0.0006, 
              Math.min(1, nodeIntensity)
          ]);
      }
  });
  
  return heatData;
}

// Initialize heatmap for a mountain
function initHeatmap(mountainId) {
  const mountain = mountainLocations[mountainId];
  if (!mountain) return;
  
  // Update modal title
  document.getElementById('heatmapModalTitle').innerHTML = 
    `<i class="fas fa-fire"></i> ${mountain.name} - Traffic Heatmap`;
  
  // Clear existing markers
  if (currentMarkers) {
    currentMarkers.forEach(marker => {
      if (marker.remove) marker.remove();
    });
    currentMarkers = [];
  }
  
  // Remove existing heat layer
  if (heatLayer && heatmapMap) {
    heatmapMap.removeLayer(heatLayer);
  }
  
  // Initialize or re-center map
  if (!heatmapMap) {
    heatmapMap = L.map('map').setView([mountain.lat, mountain.lng], mountain.zoom);
    // Use an Esri World Imagery (Satellite view) for realistic geographic mapping
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
      maxZoom: 18
    }).addTo(heatmapMap);
  } else {
    heatmapMap.setView([mountain.lat, mountain.lng], mountain.zoom);
  }
  
  // Remove existing path if any
  if (window.currentPath) heatmapMap.removeLayer(window.currentPath);
  
  // Draw actual trail path using polylines
  const trailCoordinates = mountain.trails.map(t => [t.lat, t.lng]);
  window.currentPath = L.polyline(trailCoordinates, {
    color: '#ffeb3b', 
    weight: 2, 
    opacity: 0.5, 
    dashArray: '5, 8'
  }).addTo(heatmapMap);
  
  // Generate dummy heatmap data
  const heatData = generateHeatmapData(mountainId);
  
  // Add heat layer
  heatLayer = L.heatLayer(heatData, {
    radius: 18,
    blur: 12,
    maxZoom: 17,
    minOpacity: 0.4,
    gradient: {
      0.3: '#4caf50',  // Green for low traffic
      0.5: '#ffeb3b',  // Yellow for medium
      0.7: '#ff9800',  // Orange for high
      0.9: '#f44336'   // Red for very high traffic
    }
  }).addTo(heatmapMap);
  
  // Add markers for trail points with popup info
  mountain.trails.forEach(trail => {
    // Determine crowd level at this point
    let crowdLevel = 'Low';
    let crowdColor = '#10b981';
    
    // Sample some points from heatData to estimate crowd level at this location
    const nearbyPoints = heatData.filter(point => {
      const latDiff = Math.abs(point[0] - trail.lat);
      const lngDiff = Math.abs(point[1] - trail.lng);
      return latDiff < 0.001 && lngDiff < 0.001;
    });
    
    const avgIntensity = nearbyPoints.reduce((sum, p) => sum + (p[2] || 0), 0) / (nearbyPoints.length || 1);
    
    if (avgIntensity > 0.7) {
      crowdLevel = 'Very High';
      crowdColor = '#dc2626';
    } else if (avgIntensity > 0.5) {
      crowdLevel = 'High';
      crowdColor = '#f59e0b';
    } else if (avgIntensity > 0.3) {
      crowdLevel = 'Moderate';
      crowdColor = '#fbbf24';
    } else {
      crowdLevel = 'Low';
      crowdColor = '#10b981';
    }
    
    const marker = L.marker([trail.lat, trail.lng])
      .bindPopup(`
        <div style="font-family: 'Inter', sans-serif; min-width: 150px;">
          <strong style="font-size: 14px;">📍 ${trail.name}</strong><br/>
          <span style="font-size: 12px; color: #6B7280;">
            <i class="fas fa-users"></i> Crowd Level: 
            <span style="color: ${crowdColor}; font-weight: 600;">${crowdLevel}</span>
          </span><br/>
          <span style="font-size: 11px; color: #9CA3AF;">
            Last updated: ${new Date().toLocaleTimeString()}
          </span>
          <hr style="margin: 8px 0;">
          <span style="font-size: 11px;">
            💡 ${avgIntensity > 0.6 ? 'Expect heavy traffic, start early!' : 
                      avgIntensity > 0.3 ? 'Moderate traffic, good time to hike!' : 
                      'Light traffic, perfect conditions!'}
          </span>
        </div>
      `)
      .addTo(heatmapMap);
    
    currentMarkers.push(marker);
  });
  
  // Add a legend control
  const legend = L.control({ position: 'bottomright' });
  legend.onAdd = function() {
    const div = L.DomUtil.create('div', 'info legend');
    div.style.backgroundColor = 'white';
    div.style.padding = '10px';
    div.style.borderRadius = '8px';
    div.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
    div.style.fontFamily = "'Inter', sans-serif";
    div.style.fontSize = '12px';
    div.innerHTML = `
      <strong style="font-size: 13px;">Crowd Density</strong><br/>
      <i style="background: #00ff00; width: 12px; height: 12px; display: inline-block; border-radius: 2px;"></i> Low<br/>
      <i style="background: #ffff00; width: 12px; height: 12px; display: inline-block; border-radius: 2px;"></i> Medium<br/>
      <i style="background: #ffa500; width: 12px; height: 12px; display: inline-block; border-radius: 2px;"></i> High<br/>
      <i style="background: #ff0000; width: 12px; height: 12px; display: inline-block; border-radius: 2px;"></i> Very High
    `;
    return div;
  };
  legend.addTo(heatmapMap);
  
  // Store legend to remove later if needed
  if (window.currentLegend) window.currentLegend.remove();
  window.currentLegend = legend;
}

// Open heatmap modal
function openHeatmapModal() {
  const modal = document.getElementById('heatmapModal');
  modal.classList.add('open');
  
  // Reset map if exists
  if (heatmapMap) {
    heatmapMap.remove();
    heatmapMap = null;
  }
}

function closeHeatmapModal() {
  const modal = document.getElementById('heatmapModal');
  modal.classList.remove('open');
  
  // Clean up map
  if (heatmapMap) {
    heatmapMap.remove();
    heatmapMap = null;
  }
}

// Load heatmap for selected mountain
function loadSelectedHeatmap() {
  const select = document.getElementById('mountainSelect');
  const mountainId = select.value;
  
  if (!mountainId) {
    alert('Please select a mountain first');
    return;
  }
  
  initHeatmap(parseInt(mountainId));
}

// Update the heatmap button event listener
document.getElementById('heatmapBtn')?.addEventListener('click', () => {
  openHeatmapModal();
});

// Add event listener for load button
document.getElementById('loadHeatmapBtn')?.addEventListener('click', loadSelectedHeatmap);

// Also load heatmap when mountain is selected from dropdown
document.getElementById('mountainSelect')?.addEventListener('change', function() {
  if (this.value) {
    loadSelectedHeatmap();
  }
});

</script>

<!-- Include Profile Modal -->
<?php 
$modalPath = __DIR__ . '/profile-modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
} else {
    $altPath = 'admin/profile-modal.php';
    if (file_exists($altPath)) {
        include_once $altPath;
    }
}
?>

<!-- Include Logout Modal -->
<?php 
$logoutModalPath = __DIR__ . '/../../includes/logout-modal.php';
if (file_exists($logoutModalPath)) {
    include_once $logoutModalPath;
} else {
    $altLogoutPath = '../includes/logout-modal.php';
    if (file_exists($altLogoutPath)) {
        include_once $altLogoutPath;
    }
}
?>
</body>
</html>