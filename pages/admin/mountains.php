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
    // Deterministic hash based on mountain name
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

// Booking type labels (default to 'both' if not in DB)
$bookingLabels = [
    'booking' => 'Booking Only',
    'walkin' => 'Walk-in Only',
    'both' => 'Walk-in & Booking'
];

// Handle AJAX update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_mountain') {
        $id = $_POST['id'] ?? 0;
        
        // Update only the fields that exist in your table
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['difficulty', 'crowdLevel', 'duration', 'fee', 'jumpOff', 'description', 
                          'rules', 'hazards', 'envReminders', 'peakTimes', 'weatherAdvisory', 'rating'];
        
        foreach ($allowedFields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                // Handle JSON fields
                if (in_array($field, ['rules', 'hazards', 'envReminders'])) {
                    $value = json_encode(array_filter(array_map('trim', explode("\n", $value))));
                }
                $updateFields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (!empty($updateFields)) {
            $params[] = $id;
            $sql = "UPDATE mountains SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'message' => 'Mountain updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
        }
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

  <style>
    /* Keep all your existing styles - they remain unchanged */
    .mtn-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; margin-bottom: 48px; }
    .mtn-card { background: white; border-radius: 28px; box-shadow: 0 8px 20px rgba(0,0,0,0.02), 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s ease; overflow: hidden; border: 1px solid #EFF2F8; display: flex; flex-direction: column; }
    .mtn-card:hover { transform: translateY(-3px); box-shadow: 0 20px 28px -12px rgba(0,0,0,0.12); }
    .mtn-photo { height: 170px; background-size: cover; background-position: center 30%; position: relative; }
    .mtn-badge-overlay { position: absolute; top: 14px; right: 16px; }
    .badge {
  font-size: 11px; 
  font-weight: 600; 
  padding: 4px 12px;
  border-radius: 40px; 
  color: white; 
  letter-spacing: 0.3px;
  display: inline-block;
}
.badge.green { background: #d1fae5; color: #065f46; }
.badge.amber { background: #fef3c7; color: #92400e; }
.badge.red   { background: #fee2e2; color: #991b1b; }
.badge.white { background: rgba(255, 255, 255, 0.7); color: white; }
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
    .photo-upload-area { border: 2px dashed #D8DDE6; border-radius: 14px; padding: 20px; text-align: center; cursor: pointer; transition: 0.15s; background: #FAFBFC; position: relative; }
    .photo-upload-area:hover { border-color: #111318; background: #F5F6F8; }
    .photo-upload-area input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .booking-options { display: flex; gap: 10px; }
    .booking-option { flex: 1; border: 1.5px solid #E5E9EF; border-radius: 12px; padding: 10px 10px; cursor: pointer; text-align: center; font-size: 12.5px; font-weight: 500; color: #5B6A7E; transition: 0.15s; }
    .booking-option.selected { background: #111318; color: white; border-color: #111318; }
    .form-section-label { grid-column: 1 / -1; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #9AA6B5; padding: 10px 0 2px; border-top: 1px solid #EFF2F6; margin-top: 4px; }
    .confirm-warn { background: #FFF8F0; border: 1.5px solid #F5CBA7; border-radius: 12px; padding: 12px 16px; font-size: 13px; color: #7D4E00; display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
    .confirm-box { background: #F8F9FB; border: 1.5px solid #E5E9EF; border-radius: 16px; padding: 20px 22px; }
    .confirm-row { display: flex; justify-content: space-between; font-size: 13px; padding: 7px 0; border-bottom: 1px solid #EDF0F4; }
    .preview-label { font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #9AA6B5; margin-bottom: 16px; }
    .preview-card-wrap { display: flex; justify-content: center; }
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
        <div class="logo-sub">wilderness intelligence</div>
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
      <form method="POST" action="/pages/modals/logout.php" style="margin:0;padding:0;display:block;">
        <button class="logout-btn" type="submit" style="width:100%;display:flex;align-items:center;gap:12px;padding:10px 16px;background:transparent;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.82rem;font-weight:400;color:#dc2626;cursor:pointer;">
          <i class="fas fa-right-from-bracket" style="width:16px;font-size:0.75rem;"></i> Log Out
        </button>
      </form>
      <div class="sidebar-footer">
        <div class="status-dot"></div> SYSTEM LIVE · V3
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-mountain"></i> Mountains</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user">
          <div class="avatar"><?= htmlspecialchars($adminInitial) ?></div>
          <?= htmlspecialchars($adminName) ?>
          <i class="fas fa-chevron-down" style="font-size:0.5rem;color:#8A99AE;"></i>
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
    
    // Better status badge color mapping
    if ($mountain['status'] === 'Open') {
        $statusClass = 'green';
    } elseif ($mountain['status'] === 'Limited') {
        $statusClass = 'amber';
    } elseif ($mountain['status'] === 'Closed') {
        $statusClass = 'red';
    } else {
        $statusClass = 'white';
    }
?>
        <div class="mtn-card" data-mountain-id="<?= $mountain['id'] ?>">
          <div class="mtn-photo" style="background-image:url('<?= htmlspecialchars($photoUrl) ?>');">
            <div class="mtn-badge-overlay"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($mountain['status']) ?></span></div>
          </div>
          <div class="mtn-info">
            <div class="mtn-name"><?= htmlspecialchars($mountain['name']) ?></div>
            <div class="mtn-detail"><i class="fas fa-arrow-up"></i> <?= htmlspecialchars($mountain['elevation']) ?> · ELEVATION</div>
            <div class="elev-bar"><div class="elev-fill" style="width:<?= $elevPercent ?>%"></div></div>
            <div class="mtn-detail"><i class="fas fa-signal"></i> <?= htmlspecialchars($mountain['difficulty']) ?> · ₱<?= number_format($mountain['fee']) ?> fee</div>
            <div class="mtn-detail"><i class="fas fa-clock"></i> <?= htmlspecialchars($mountain['duration']) ?> hrs &nbsp;·&nbsp; <i class="fas fa-users"></i> <?= htmlspecialchars($mountain['crowdLevel']) ?> Crowd</div>
            <div class="mtn-detail"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($mountain['location']) ?></div>
            <div class="mtn-detail"><i class="fas fa-star"></i> Rating: <?= htmlspecialchars($mountain['rating']) ?> ★</div>
            <div class="mtn-actions">
              <button class="btn edit-mtn-btn" data-id="<?= $mountain['id'] ?>" style="flex:1"><i class="fas fa-edit"></i> Edit</button>
              <button class="btn btn-ghost approv-mtn-btn" data-name="<?= htmlspecialchars($mountain['name']) ?>"><i class="fas fa-check-circle"></i> Approve</button>
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

<!-- MODAL (keep the same modal structure as before) -->
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

<script>
const mountainsData = <?php echo json_encode($mountains); ?>;
const imgBank = <?php echo json_encode($imgBank); ?>;
const crowdChartData = <?php echo json_encode($chartData); ?>;
const crowdChartLabels = <?php echo json_encode($chartLabels); ?>;

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

function openEditModal(id) {
  activeMtnId = id;
  const m = mountainsData.find(x => x.id === id);
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
  
  // Parse JSON fields
  try { document.getElementById('fRules').value = JSON.parse(m.rules || '[]').join('\n'); } catch(e) { document.getElementById('fRules').value = m.rules || ''; }
  try { document.getElementById('fEnvReminders').value = JSON.parse(m.envReminders || '[]').join('\n'); } catch(e) { document.getElementById('fEnvReminders').value = m.envReminders || ''; }
  try { document.getElementById('fHazards').value = JSON.parse(m.hazards || '[]').join('\n'); } catch(e) { document.getElementById('fHazards').value = m.hazards || ''; }
  
  document.getElementById('modalMtnName').textContent = m.name;
  showStep(1);
  document.getElementById('editModal').classList.add('open');
}

function closeModal() { document.getElementById('editModal').classList.remove('open'); }

function showStep(n) {
  ['Edit','Confirm','Preview'].forEach((_, i) => {
    const s = i + 1;
    document.getElementById('step' + ['Edit','Confirm','Preview'][i] + 'Body').style.display = s === n ? 'block' : 'none';
    document.getElementById('step' + ['Edit','Confirm','Preview'][i] + 'Footer').style.display = s === n ? 'flex' : 'none';
    const dot = document.getElementById('sdot' + s);
    if (dot) dot.className = 'step-dot' + (s < n ? ' done' : s === n ? ' active' : '');
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

function goBackToForm() { showStep(1); }

function goToPreview() {
  const m = originalMountain;
  const photoSrc = getPhoto(m);
  const difficulty = document.getElementById('fDifficulty').value;
  const fee = parseInt(document.getElementById('fFee').value);
  const crowd = document.getElementById('fCrowd').value;
  const duration = document.getElementById('fDuration').value;
  const statusClass = m.status === 'Open' ? 'green' : 'red';
  document.getElementById('previewCardWrap').innerHTML = `
    <div class="mtn-card" style="max-width:440px">
      <div class="mtn-photo" style="background-image:url('${photoSrc}');">
        <div class="mtn-badge-overlay"><span class="badge ${statusClass}">${m.status}</span></div>
      </div>
      <div class="mtn-info">
        <div class="mtn-name">${m.name}</div>
        <div class="mtn-detail"><i class="fas fa-arrow-up"></i> ${m.elevation} · ELEVATION</div>
        <div class="elev-bar"><div class="elev-fill" style="width:${Math.min(100, (parseInt(m.elevation) / 3000) * 100)}%"></div></div>
        <div class="mtn-detail"><i class="fas fa-signal"></i> ${difficulty} · ₱${fee} fee</div>
        <div class="mtn-detail"><i class="fas fa-clock"></i> ${duration} &nbsp;·&nbsp; <i class="fas fa-users"></i> ${crowd} Crowd</div>
        <div class="mtn-detail"><i class="fas fa-map-pin"></i> ${m.location}</div>
        <div class="mtn-detail"><i class="fas fa-star"></i> Rating: ${document.getElementById('fRating').value || m.rating} ★</div>
      </div>
    </div>`;
  showStep(3);
}

function saveChanges() {
  const formData = new FormData();
  formData.append('action', 'update_mountain');
  formData.append('id', activeMtnId);
  formData.append('difficulty', document.getElementById('fDifficulty').value);
  formData.append('crowdLevel', document.getElementById('fCrowd').value);
  formData.append('duration', document.getElementById('fDuration').value);
  formData.append('fee', document.getElementById('fFee').value);
  formData.append('jumpOff', document.getElementById('fJumpoff').value);
  formData.append('description', document.getElementById('fDescription').value);
  formData.append('rating', document.getElementById('fRating').value);
  formData.append('weatherAdvisory', document.getElementById('fWeather').value);
  formData.append('peakTimes', document.getElementById('fPeakTimes').value);
  formData.append('rules', document.getElementById('fRules').value);
  formData.append('envReminders', document.getElementById('fEnvReminders').value);
  formData.append('hazards', document.getElementById('fHazards').value);

  fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        alert(`✅ ${originalMountain.name} updated successfully.`);
        location.reload();
      } else alert('Error: ' + result.message);
    })
    .catch(() => alert('Error saving changes. Please try again.'));
}

function updateDate() {
  const d = new Date();
  document.getElementById('liveDate').textContent = d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() + '  ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
updateDate(); setInterval(updateDate, 1000);

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if(item.dataset.href) window.location.href = item.dataset.href; });
});

document.querySelectorAll('.edit-mtn-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(parseInt(btn.dataset.id))));
document.querySelectorAll('.approv-mtn-btn').forEach(btn => btn.addEventListener('click', () => alert(`✅ Opening approval submitted for ${btn.dataset.name}.`)));
document.getElementById('modalCloseBtn')?.addEventListener('click', closeModal);
document.getElementById('heatmapBtn')?.addEventListener('click', () => alert('🔥 Full interactive crowd heatmap view.'));

let crowdChart = null;
function initCrowdChart() {
  const ctx = document.getElementById('chartCrowd');
  if (!ctx) return;
  if (crowdChart) crowdChart.destroy();
  crowdChart = new Chart(ctx, {
    type: 'bar',
    data: { labels: crowdChartLabels, datasets: [{ data: crowdChartData, borderRadius: 8, barPercentage: 0.65, backgroundColor: 'rgba(17,19,24,0.85)' }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#EFF2F6' }, title: { display: true, text: 'avg visitors/day' } } } }
  });
}
initCrowdChart();
</script>
</body>
</html>