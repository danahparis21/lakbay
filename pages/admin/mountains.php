<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// ========== HANDLE AJAX REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    try {
        // Get activity data
        if (isset($_POST['action']) && $_POST['action'] === 'get_activity') {
            $mountain_id = $_POST['mountain_id'] ?? 0;
            
            if (!$mountain_id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, u.name as user_name, gu.name as guide_name
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN users gu ON b.guide_id = gu.id
                WHERE b.mountain_id = ? AND b.status = 'active' AND b.hike_date >= CURDATE()
                ORDER BY b.hike_date ASC
            ");
            $stmt->execute([$mountain_id]);
            $dayHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT SUM(number_of_hikers) as total_hikers 
                FROM bookings 
                WHERE mountain_id = ? AND status = 'active' AND hike_date >= CURDATE()
            ");
            $stmt->execute([$mountain_id]);
            $hikerCount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'day_hikes' => $dayHikes,
                'summary' => [
                    'total_hikes' => count($dayHikes),
                    'total_hikers' => (int)($hikerCount['total_hikers'] ?? 0)
                ]
            ]);
            exit;
        }
        
        // Get crowd reports
        if (isset($_POST['action']) && $_POST['action'] === 'get_crowd_reports') {
            $mountain_id = $_POST['mountain_id'] ?? 0;
            
            if (!$mountain_id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    cr.*,
                    u.name as reporter_name,
                    u.role as reporter_role
                FROM crowd_reports cr
                LEFT JOIN users u ON cr.reported_by = u.id
                WHERE cr.mountain_id = ?
                ORDER BY cr.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$mountain_id]);
            $crowdReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'reports' => $crowdReports
            ]);
            exit;
        }
        
        // Get heatmap data with trail
        if (isset($_POST['action']) && $_POST['action'] === 'get_heatmap_data') {
            $mountain_id = $_POST['mountain_id'] ?? 0;
            
            if (!$mountain_id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM mountains WHERE id = ?");
            $stmt->execute([$mountain_id]);
            $mountain = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $trailCoordinates = [];
            $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE mountain_id = ? OR fileId = ? ORDER BY idx ASC");
            $fileIdMap = [1 => 'BATULAO', 2 => 'APAYANG', 3 => 'LANTIK', 4 => 'TALAMITAM'];
            $fileId = $fileIdMap[$mountain_id] ?? null;
            $stmt->execute([$mountain_id, $fileId]);
            $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($trackPoints as $p) {
                $trailCoordinates[] = [(float)$p['lon'], (float)$p['lat']];
            }
            
            $stmt = $pdo->prepare("
                SELECT id, name, type, latitude, longitude, elevation, description 
                FROM trail_waypoints 
                WHERE mountain_id = ? AND is_active = 1
                ORDER BY order_index ASC
            ");
            $stmt->execute([$mountain_id]);
            $waypoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT latitude, longitude, crowd_level, created_at, notes
                FROM crowd_reports
                WHERE mountain_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute([$mountain_id]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $heatmapPoints = [];
            $crowdIntensity = ['Low' => 0.3, 'Medium' => 0.6, 'High' => 0.9, 'Very High' => 1.0];
            
            foreach ($reports as $report) {
                $intensity = $crowdIntensity[$report['crowd_level']] ?? 0.5;
                $heatmapPoints[] = [
                    'lat' => (float)$report['latitude'],
                    'lng' => (float)$report['longitude'],
                    'intensity' => $intensity,
                    'crowd_level' => $report['crowd_level'],
                    'notes' => $report['notes'],
                    'reported_at' => $report['created_at']
                ];
            }
            
            if (empty($heatmapPoints) && !empty($trailCoordinates)) {
                foreach ($trailCoordinates as $idx => $coord) {
                    $intensity = 0.2 + (sin($idx * 0.2) * 0.3);
                    $heatmapPoints[] = [
                        'lat' => $coord[1],
                        'lng' => $coord[0],
                        'intensity' => min(0.9, max(0.2, $intensity)),
                        'crowd_level' => $intensity > 0.6 ? 'High' : ($intensity > 0.3 ? 'Medium' : 'Low'),
                        'notes' => 'Simulated crowd data',
                        'reported_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'points' => $heatmapPoints,
                'trail' => $trailCoordinates,
                'waypoints' => $waypoints,
                'mountain' => [
                    'name' => $mountain['name'],
                    'lat' => (float)($mountain['start_point_lat'] ?? $trailCoordinates[0][1] ?? 14.0583),
                    'lng' => (float)($mountain['start_point_lng'] ?? $trailCoordinates[0][0] ?? 120.8320),
                    'description' => $mountain['description'],
                    'difficulty' => $mountain['difficulty'],
                    'elevation' => $mountain['elevation']
                ]
            ]);
            exit;
        }
        
        // ========== UPDATE MOUNTAIN ==========
        if (isset($_POST['action']) && $_POST['action'] === 'update_mountain') {
            $id = $_POST['id'] ?? 0;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            $allowedDifficulties = ['Easy', 'Easy to Moderate', 'Moderate', 'Difficult', 'Very Difficult'];
            $allowedCrowdLevels = ['Low', 'Moderate', 'High', 'Very High'];
            $allowedStatuses = ['Open', 'Limited', 'Closed'];
            
            $updateFields = [];
            $params = [];
            
            if (isset($_POST['name'])) {
                $updateFields[] = "name = ?";
                $params[] = trim($_POST['name']);
            }
            
            if (isset($_POST['location'])) {
                $updateFields[] = "location = ?";
                $params[] = trim($_POST['location']);
            }
            
            if (isset($_POST['elevation'])) {
                $updateFields[] = "elevation = ?";
                $params[] = trim($_POST['elevation']);
            }
            
            if (isset($_POST['difficulty']) && in_array($_POST['difficulty'], $allowedDifficulties)) {
                $updateFields[] = "difficulty = ?";
                $params[] = $_POST['difficulty'];
            }
            
            if (isset($_POST['crowdLevel']) && in_array($_POST['crowdLevel'], $allowedCrowdLevels)) {
                $updateFields[] = "crowdLevel = ?";
                $params[] = $_POST['crowdLevel'];
            }
            
            if (isset($_POST['duration'])) {
                $updateFields[] = "duration = ?";
                $params[] = trim($_POST['duration']);
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
                $updateFields[] = "rating = ?";
                $params[] = floatval($_POST['rating']);
            }
            
            if (isset($_POST['weatherAdvisory'])) {
                $updateFields[] = "weatherAdvisory = ?";
                $params[] = trim($_POST['weatherAdvisory']);
            }
            
            if (isset($_POST['peakTimes'])) {
                $updateFields[] = "peakTimes = ?";
                $params[] = trim($_POST['peakTimes']);
            }
            
            if (isset($_POST['status']) && in_array($_POST['status'], $allowedStatuses)) {
                $updateFields[] = "status = ?";
                $params[] = $_POST['status'];
            }
            
            if (isset($_POST['rules'])) {
                $rulesArray = array_filter(array_map('trim', explode("\n", trim($_POST['rules']))));
                $updateFields[] = "rules = ?";
                $params[] = json_encode(array_values($rulesArray));
            }
            
            if (isset($_POST['envReminders'])) {
                $envArray = array_filter(array_map('trim', explode("\n", trim($_POST['envReminders']))));
                $updateFields[] = "envReminders = ?";
                $params[] = json_encode(array_values($envArray));
            }
            
            if (isset($_POST['hazards'])) {
                $hazardsArray = array_filter(array_map('trim', explode("\n", trim($_POST['hazards']))));
                $updateFields[] = "hazards = ?";
                $params[] = json_encode(array_values($hazardsArray));
            }
            
            if (isset($_POST['trail_length_km'])) {
                $updateFields[] = "trail_length_km = ?";
                $params[] = floatval($_POST['trail_length_km']);
            }
            
            if (isset($_POST['estimated_duration'])) {
                $updateFields[] = "estimated_duration = ?";
                $params[] = floatval($_POST['estimated_duration']);
            }
            
            if (isset($_POST['registration_fee'])) {
                $updateFields[] = "registration_fee = ?";
                $params[] = floatval($_POST['registration_fee']);
            }
            
            if (isset($_POST['environmental_fee'])) {
                $updateFields[] = "environmental_fee = ?";
                $params[] = floatval($_POST['environmental_fee']);
            }
            
            if (isset($_POST['start_point_lat'])) {
                $updateFields[] = "start_point_lat = ?";
                $params[] = floatval($_POST['start_point_lat']);
            }
            
            if (isset($_POST['start_point_lng'])) {
                $updateFields[] = "start_point_lng = ?";
                $params[] = floatval($_POST['start_point_lng']);
            }
            
            if (!empty($updateFields)) {
                $params[] = $id;
                $sql = "UPDATE mountains SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    echo json_encode(['success' => true, 'message' => 'Mountain updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
            }
            exit;
        }
        
        // Get single mountain data for edit modal
        if (isset($_POST['action']) && $_POST['action'] === 'get_mountain') {
            $id = $_POST['id'] ?? 0;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM mountains WHERE id = ?");
            $stmt->execute([$id]);
            $mountain = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mountain) {
                $mountain['rules'] = json_decode($mountain['rules'] ?? '[]', true);
                $mountain['envReminders'] = json_decode($mountain['envReminders'] ?? '[]', true);
                $mountain['hazards'] = json_decode($mountain['hazards'] ?? '[]', true);
                
                echo json_encode(['success' => true, 'mountain' => $mountain]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Mountain not found']);
            }
            exit;
        }
        
        // Assign manager
        if (isset($_POST['action']) && $_POST['action'] === 'assign_manager') {
            $mountain_id = $_POST['mountain_id'] ?? 0;
            $manager_id = $_POST['manager_id'] ?? 0;
            $send_email = isset($_POST['send_email']) ? true : false;
            
            if (!$mountain_id || !$manager_id) {
                echo json_encode(['success' => false, 'message' => 'Mountain and Manager are required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM manager_mountains WHERE mountain_id = ? AND manager_id = ?");
            $stmt->execute([$mountain_id, $manager_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Already assigned']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO manager_mountains (manager_id, mountain_id, assigned_date, is_primary) VALUES (?, ?, NOW(), 0)");
            if ($stmt->execute([$manager_id, $mountain_id])) {
                if ($send_email) {
                    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $stmt->execute([$manager_id]);
                    $manager = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt = $pdo->prepare("SELECT name FROM mountains WHERE id = ?");
                    $stmt->execute([$mountain_id]);
                    $mountain = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($manager && $manager['email']) {
                        sendAssignmentEmail($manager['email'], $manager['name'], $mountain['name']);
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Manager assigned successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign manager']);
            }
            exit;
        }
        
        // Remove manager
        if (isset($_POST['action']) && $_POST['action'] === 'remove_manager') {
            $assignment_id = $_POST['assignment_id'] ?? 0;
            
            if (!$assignment_id) {
                echo json_encode(['success' => false, 'message' => 'Assignment ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM manager_mountains WHERE id = ?");
            if ($stmt->execute([$assignment_id])) {
                echo json_encode(['success' => true, 'message' => 'Manager removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove']);
            }
            exit;
        }
        
        // Create new manager
        if (isset($_POST['action']) && $_POST['action'] === 'create_manager') {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if (empty($name) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Name and email required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }
            
            $defaultPassword = 'Password123!';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, role, password, created_at, hiking_level, location_tracking_enabled) 
                VALUES (?, ?, ?, 'manager', ?, NOW(), 'beginner', 1)
            ");
            
            if ($stmt->execute([$name, $email, $phone, $hashedPassword])) {
                $newManagerId = $pdo->lastInsertId();
                sendWelcomeEmail($email, $name, $defaultPassword);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Manager created',
                    'manager_id' => $newManagerId,
                    'manager_name' => $name,
                    'manager_email' => $email
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create manager']);
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

// ========== EMAIL FUNCTIONS ==========
function sendWelcomeEmail($email, $name, $password) {
    $subject = "Welcome to LAKBAY - Manager Account Created";
    $message = "<html><body><h2>Welcome to LAKBAY!</h2><p>Dear $name,</p><p>Your manager account has been created.</p><p>Email: $email<br>Password: $password</p><p>Please change your password after first login.</p></body></html>";
    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: LAKBAY System <noreply@lakbay.com>\r\n";
    @mail($email, $subject, $message, $headers);
}

function sendAssignmentEmail($email, $managerName, $mountainName) {
    $subject = "LAKBAY - Assigned to manage $mountainName";
    $message = "<html><body><h2>Mountain Assignment</h2><p>Dear $managerName,</p><p>You have been assigned as manager for $mountainName.</p></body></html>";
    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: LAKBAY System <noreply@lakbay.com>\r\n";
    @mail($email, $subject, $message, $headers);
}

// Fetch mountains
$stmt = $pdo->prepare("SELECT * FROM mountains ORDER BY name");
$stmt->execute();
$mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all managers
$stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE role = 'manager' ORDER BY name");
$stmt->execute();
$allManagers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch manager assignments
$managerAssignments = [];
foreach ($mountains as $mountain) {
    $stmt = $pdo->prepare("
        SELECT mm.id as assignment_id, mm.manager_id, mm.assigned_date, u.name, u.email 
        FROM manager_mountains mm
        JOIN users u ON mm.manager_id = u.id
        WHERE mm.mountain_id = ?
    ");
    $stmt->execute([$mountain['id']]);
    $managerAssignments[$mountain['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Default image bank
$imgBank = [
    "https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop",
    "https://images.pexels.com/photos/248797/pexels-photo-248797.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop",
    "https://images.pexels.com/photos/417074/pexels-photo-417074.jpeg?auto=compress&cs=tinysrgb&w=800&h=500&fit=crop"
];

function getMountainPhoto($mountain, $imgBank) {
    if (!empty($mountain['image'])) return $mountain['image'];
    $hash = 0;
    for ($i = 0; $i < strlen($mountain['name']); $i++) {
        $hash = (($hash << 5) - $hash) + ord($mountain['name'][$i]);
    }
    return $imgBank[abs($hash) % count($imgBank)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Mountains Manager</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
  <link rel="stylesheet" href="shared.css">
 <style>
  .mtn-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; margin-bottom: 48px; }
    .mtn-card { background: white; border-radius: 28px; overflow: hidden; border: 1px solid #EFF2F8; }
    .mtn-photo { height: 170px; background-size: cover; background-position: center; position: relative; }
    .mtn-badge-overlay { position: absolute; top: 14px; right: 16px; }
    .badge { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 40px; display: inline-block; }
    .badge.green { background: #d1fae5; color: #065f46; }
    .badge.amber { background: #fef3c7; color: #92400e; }
    .badge.red { background: #fee2e2; color: #991b1b; }
    .mtn-info { padding: 20px; }
    .mtn-name { font-size: 1.6rem; font-weight: 600; font-family: 'Cormorant Garamond', serif; margin-bottom: 8px; }
    .mtn-detail { font-size: 13px; color: #5B6A7E; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
    .manager-list { margin-top: 12px; padding-top: 12px; border-top: 1px solid #EFF2F6; }
    .manager-tag { display: inline-flex; align-items: center; gap: 6px; background: #F0F2F5; padding: 4px 10px; border-radius: 20px; font-size: 11px; margin-right: 8px; margin-bottom: 8px; }
    .manager-tag i { cursor: pointer; color: #dc2626; opacity: 0.6; }
    .mtn-actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
    .btn { border: none; font-family: 'Inter', sans-serif; font-weight: 500; padding: 8px 18px; border-radius: 40px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
    .btn-primary { background: #111318; color: white; }
    .btn-ghost { background: #F0F2F5; color: #1F2A3A; }
    .btn-outline { background: transparent; border: 1px solid #E2E6EC; }
    .btn-warning { background: #c9a84c; color: #111318; }
    .btn-success { background: #1E7B48; color: white; }
    
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(10,12,18,0.55); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 28px; width: 100%; max-width: 800px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
    .modal-box-large { max-width: 800px !important; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #EFF2F6; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .modal-title { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; }
    .modal-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: #F2F4F8; cursor: pointer; }
    .modal-body { flex: 1; overflow-y: auto; padding: 24px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #EFF2F6; display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }
    
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .full-width { grid-column: 1 / -1; }
    .form-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6C7A8E; display: block; margin-bottom: 6px; }
    .form-control, .form-select, .form-textarea { width: 100%; border: 1.5px solid #E5E9EF; border-radius: 12px; padding: 10px 14px; font-size: 13px; font-family: 'Inter', sans-serif; outline: none; background: white; }
    .form-control:focus, .form-select:focus, .form-textarea:focus { border-color: #111318; }
    .form-textarea { resize: vertical; min-height: 80px; }
    
    /* Heatmap Modal - Side by Side Layout */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(10,12,18,0.55); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 28px; width: 100%; max-width: 1300px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #EFF2F6; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .modal-title { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; }
    .modal-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: #F2F4F8; cursor: pointer; }
    .modal-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
    
    /* Two column layout inside modal */
    .heatmap-layout { display: flex; flex: 1; min-height: 550px; }
    .map-column { flex: 2; position: relative; background: #f0f0f0; }
    .reports-column { flex: 1; border-left: 1px solid #EFF2F6; display: flex; flex-direction: column; background: white; }
    .reports-header { padding: 16px 20px; background: #F8F9FB; border-bottom: 1px solid #EFF2F6; }
    .reports-header h4 { font-size: 0.9rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 8px; }
    .reports-list { flex: 1; overflow-y: auto; }
    .heatmap-container { height: 100%; width: 100%; position: relative; }
    .heatmap-map { height: 100%; width: 100%; }
    .map-controls { position: absolute; top: 10px; right: 10px; z-index: 1000; display: flex; gap: 8px; }
    .map-btn { background: rgba(255,255,255,0.95); border: none; border-radius: 8px; padding: 6px 12px; cursor: pointer; font-size: 0.7rem; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .map-btn:hover { background: #111318; color: white; }
    .map-legend { position: absolute; bottom: 10px; right: 10px; background: rgba(255,255,255,0.95); border-radius: 10px; padding: 8px 12px; font-size: 0.6rem; z-index: 1000; }
    .legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
    
    /* Crowd Reports List */
    .crowd-report-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-bottom: 1px solid #EFF2F6; cursor: pointer; transition: background 0.15s; }
    .crowd-report-item:hover { background: #F8F9FB; }
    .crowd-report-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .crowd-report-icon.level-Low { background: rgba(27,112,69,0.15); color: #1B7045; }
    .crowd-report-icon.level-Medium { background: rgba(201,123,26,0.15); color: #C97B1A; }
    .crowd-report-icon.level-High { background: rgba(184,49,42,0.15); color: #B8312A; }
    .crowd-report-content { flex: 1; min-width: 0; }
    .crowd-report-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; }
    .crowd-report-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; }
    .crowd-report-badge.Low { background: rgba(27,112,69,0.15); color: #1B7045; }
    .crowd-report-badge.Medium { background: rgba(201,123,26,0.15); color: #C97B1A; }
    .crowd-report-badge.High { background: rgba(184,49,42,0.15); color: #B8312A; }
    .crowd-report-details { font-size: 0.7rem; color: #8A99AE; display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
    .crowd-report-notes { font-size: 0.68rem; color: #5B6A7E; margin-top: 6px; padding-top: 4px; border-top: 1px dashed rgba(0,0,0,0.05); }
    .empty-state { padding: 40px 20px; text-align: center; color: #8A99AE; }
    
    .manager-select-list { max-height: 200px; overflow-y: auto; border: 1px solid #E5E9EF; border-radius: 12px; }
    .manager-option { padding: 12px; cursor: pointer; border-bottom: 1px solid #E5E9EF; display: flex; justify-content: space-between; align-items: center; }
    .manager-option:hover { background: #F8F9FB; }
    .manager-option.selected { background: #e0e7ff; }
    
    @media (max-width: 900px) { .heatmap-layout { flex-direction: column; } .reports-column { border-left: none; border-top: 1px solid #EFF2F6; max-height: 300px; } .mtn-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }
    @media (max-width: 900px) { .heatmap-layout { flex-direction: column; } .reports-column { border-left: none; border-top: 1px solid #EFF2F6; max-height: 300px; } .mtn-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body data-page="mountains">
<div class="app">

 <!-- SIDEBAR -->
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
            <li class="nav-item" data-href="/pages/admin/reviews.php"><i class="fas fa-star"></i> Reviews</li>
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

  <!-- MAIN -->
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
      <div class="mtn-grid">
        <?php foreach ($mountains as $mountain): 
            $photoUrl = getMountainPhoto($mountain, $imgBank);
            $statusClass = $mountain['status'] === 'Open' ? 'green' : ($mountain['status'] === 'Limited' ? 'amber' : 'red');
            $managers = $managerAssignments[$mountain['id']] ?? [];
        ?>
        <div class="mtn-card">
          <div class="mtn-photo" style="background-image:url('<?= htmlspecialchars($photoUrl) ?>');"><div class="mtn-badge-overlay"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($mountain['status']) ?></span></div></div>
          <div class="mtn-info">
            <div class="mtn-name"><?= htmlspecialchars($mountain['name']) ?></div>
            <div class="mtn-detail"><i class="fas fa-arrow-up"></i> <?= htmlspecialchars($mountain['elevation']) ?></div>
            <div class="mtn-detail"><i class="fas fa-signal"></i> <?= htmlspecialchars($mountain['difficulty']) ?></div>
            <div class="mtn-detail"><i class="fas fa-clock"></i> <?= htmlspecialchars($mountain['duration']) ?> · <i class="fas fa-users"></i> <?= htmlspecialchars($mountain['crowdLevel']) ?> Crowd</div>
            <div class="mtn-detail"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($mountain['location']) ?></div>
            <div class="mtn-detail"><i class="fas fa-ticket-alt"></i> Registration: ₱<?= number_format($mountain['registration_fee']) ?> | Environmental: ₱<?= number_format($mountain['environmental_fee']) ?></div>
            
            <div class="manager-list">
              <div class="mtn-detail" style="font-size:11px; font-weight:600;">ASSIGNED MANAGERS</div>
              <?php if (empty($managers)): ?>
                <span class="manager-tag" style="background:#F0F2F5;">No managers assigned</span>
              <?php else: ?>
                <?php foreach ($managers as $manager): ?>
                  <span class="manager-tag"><i class="fas fa-user-check"></i> <?= htmlspecialchars($manager['name']) ?><i class="fas fa-times-circle" onclick="removeManager(<?= $manager['assignment_id'] ?>, '<?= htmlspecialchars($mountain['name']) ?>')" style="margin-left: 5px; cursor: pointer;"></i></span>
                <?php endforeach; ?>
              <?php endif; ?>
              <button class="btn btn-outline" style="padding:4px 12px; font-size:11px; margin-top:8px;" onclick="openAssignModal(<?= $mountain['id'] ?>, '<?= htmlspecialchars($mountain['name']) ?>')"><i class="fas fa-plus"></i> Assign Manager</button>
            </div>
            
            <div class="mtn-actions">
              <button class="btn edit-mtn-btn" data-id="<?= $mountain['id'] ?>" data-name="<?= htmlspecialchars($mountain['name']) ?>" style="flex:1"><i class="fas fa-edit"></i> Edit</button>
              <button class="btn btn-warning crowd-heatmap-btn" data-id="<?= $mountain['id'] ?>" data-name="<?= htmlspecialchars($mountain['name']) ?>" style="flex:1"><i class="fas fa-fire"></i> Crowd Heatmap</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- EDIT MOUNTAIN MODAL -->
<div class="modal-overlay" id="editMountainModal">
  <div class="modal-box modal-box-large">
    <div class="modal-header">
      <div class="modal-title" id="editModalTitle">Edit Mountain</div>
      <button class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editMountainId">
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Mountain Name</label>
          <input type="text" class="form-control" id="editName">
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Location</label>
          <input type="text" class="form-control" id="editLocation">
        </div>
        <div>
          <label class="form-label">Elevation (MASL)</label>
          <input type="text" class="form-control" id="editElevation" placeholder="e.g., 811 MASL">
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Difficulty</label>
          <select class="form-select" id="editDifficulty">
            <option value="Easy">Easy</option>
            <option value="Easy to Moderate">Easy to Moderate</option>
            <option value="Moderate">Moderate</option>
            <option value="Difficult">Difficult</option>
            <option value="Very Difficult">Very Difficult</option>
          </select>
        </div>
        <div>
          <label class="form-label">Crowd Level</label>
          <select class="form-select" id="editCrowdLevel">
            <option value="Low">Low</option>
            <option value="Moderate">Moderate</option>
            <option value="High">High</option>
            <option value="Very High">Very High</option>
          </select>
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Duration</label>
          <input type="text" class="form-control" id="editDuration" placeholder="e.g., 3-4 hours">
        </div>
        <div>
          <label class="form-label">Status</label>
          <select class="form-select" id="editStatus">
            <option value="Open">Open</option>
            <option value="Limited">Limited</option>
            <option value="Closed">Closed</option>
          </select>
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Registration Fee (₱)</label>
          <input type="number" class="form-control" id="editRegistrationFee">
        </div>
        <div>
          <label class="form-label">Environmental Fee (₱)</label>
          <input type="number" class="form-control" id="editEnvironmentalFee">
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Trail Length (km)</label>
          <input type="number" step="0.1" class="form-control" id="editTrailLength">
        </div>
        <div>
          <label class="form-label">Est. Duration (hours)</label>
          <input type="number" step="0.5" class="form-control" id="editEstimatedDuration">
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Jump Off Point</label>
          <input type="text" class="form-control" id="editJumpOff">
        </div>
        <div>
          <label class="form-label">Rating (0-5)</label>
          <input type="number" step="0.1" class="form-control" id="editRating">
        </div>
      </div>
      
      <div class="form-row">
        <div>
          <label class="form-label">Start Point Latitude</label>
          <input type="text" step="0.00001" class="form-control" id="editStartPointLat">
        </div>
        <div>
          <label class="form-label">Start Point Longitude</label>
          <input type="text" step="0.00001" class="form-control" id="editStartPointLng">
        </div>
      </div>
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" id="editDescription" rows="3"></textarea>
        </div>
      </div>
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Weather Advisory</label>
          <textarea class="form-textarea" id="editWeatherAdvisory" rows="2"></textarea>
        </div>
      </div>
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Peak Times</label>
          <textarea class="form-textarea" id="editPeakTimes" rows="2"></textarea>
        </div>
      </div>
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Rules (one per line)</label>
          <textarea class="form-textarea" id="editRules" rows="3"></textarea>
        </div>
      </div>
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Environmental Reminders (one per line)</label>
          <textarea class="form-textarea" id="editEnvReminders" rows="3"></textarea>
        </div>
      </div>
      
      <div class="form-row">
        <div class="full-width">
          <label class="form-label">Hazards (one per line)</label>
          <textarea class="form-textarea" id="editHazards" rows="3"></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
      <button class="btn btn-success" onclick="saveMountain()">Save Changes</button>
    </div>
  </div>
</div>

<!-- Heatmap & Crowd Reports Modal - Side by Side Layout -->
<div class="modal-overlay" id="heatmapModal">
  <div class="modal-box">
    <div class="modal-header">
      <div><div class="modal-title" id="heatmapModalTitle">Crowd Heatmap</div><div class="modal-subtitle" style="font-size: 11px; color: #8A99AE; margin-top: 4px;">Trail map with crowd density overlay</div></div>
      <button class="modal-close" onclick="closeHeatmapModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="heatmap-layout">
        <!-- Map Column -->
        <div class="map-column">
          <div class="heatmap-container">
            <div id="heatmapMap" class="heatmap-map"></div>
            <div class="map-controls">
              <button class="map-btn" onclick="resetHeatmapView()"><i class="fas fa-home"></i> Reset View</button>
              <button class="map-btn" onclick="toggleHeatmapLayer()"><i class="fas fa-fire"></i> Toggle Heatmap</button>
            </div>
            <div class="map-legend">
              <div class="map-legend-item"><div class="legend-dot" style="background: #10B981;"></div> Low Traffic</div>
              <div class="map-legend-item"><div class="legend-dot" style="background: #F59E0B;"></div> Medium Traffic</div>
              <div class="map-legend-item"><div class="legend-dot" style="background: #EF4444;"></div> High Traffic</div>
              <div class="map-legend-item"><div class="legend-dot" style="background: #7F1D1D;"></div> Very High</div>
              <div class="map-legend-item"><div class="legend-dot" style="background: #100600;"></div> Trail Path</div>
            </div>
          </div>
        </div>
        
        <!-- Reports Column -->
        <div class="reports-column">
          <div class="reports-header">
            <h4><i class="fas fa-chart-line"></i> Recent Crowd Reports</h4>
            <button class="btn btn-ghost" style="padding: 4px 12px; font-size: 11px; margin-top: 8px;" onclick="refreshHeatmap()"><i class="fas fa-sync-alt"></i> Refresh</button>
          </div>
          <div class="reports-list" id="crowdReportsList">
            <div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading reports...</div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <div class="crowd-stats" id="crowdStats" style="flex:1; font-size:0.7rem;"><span class="badge" style="background:#c9a84c20; color:#c9a84c;"><i class="fas fa-chart-line"></i> Loading stats...</span></div>
      <button class="btn btn-ghost" onclick="closeHeatmapModal()">Close</button>
    </div>
  </div>
</div>

<!-- ASSIGN MANAGER MODAL -->
<div class="modal-overlay" id="assignModal"><div class="modal-box" style="max-width: 500px;"><div class="modal-header"><div class="modal-title" id="assignModalTitle">Assign Manager</div><button class="modal-close" onclick="closeAssignModal()"><i class="fas fa-times"></i></button></div><div class="modal-body"><input type="hidden" id="assignMountainId"><div class="form-group"><label>Select Manager</label><div class="manager-select-list" id="managerSelectList"><?php foreach ($allManagers as $manager): ?><div class="manager-option" data-id="<?= $manager['id'] ?>" data-name="<?= htmlspecialchars($manager['name']) ?>" data-email="<?= htmlspecialchars($manager['email']) ?>"><div><strong><?= htmlspecialchars($manager['name']) ?></strong><br><small><?= htmlspecialchars($manager['email']) ?></small></div><input type="radio" name="selected_manager" value="<?= $manager['id'] ?>"></div><?php endforeach; ?></div></div><div class="form-group"><label><input type="checkbox" id="sendEmailCheckbox" checked> Send email notification</label></div><hr><div class="form-group"><button class="btn btn-primary" style="width:100%;" onclick="openCreateManagerModal()">Create New Manager</button></div></div><div class="modal-footer"><button class="btn btn-ghost" onclick="closeAssignModal()">Cancel</button><button class="btn btn-primary" onclick="confirmAssignManager()">Assign</button></div></div></div>

<!-- CREATE MANAGER MODAL -->
<div class="modal-overlay" id="createManagerModal"><div class="modal-box" style="max-width: 500px;"><div class="modal-header"><div class="modal-title">Create Manager</div><button class="modal-close" onclick="closeCreateManagerModal()"><i class="fas fa-times"></i></button></div><div class="modal-body"><div class="form-group"><label>Full Name *</label><input type="text" class="form-control" id="newManagerName"></div><div class="form-group"><label>Email *</label><input type="email" class="form-control" id="newManagerEmail"></div><div class="form-group"><label>Phone</label><input type="text" class="form-control" id="newManagerPhone"></div><div class="form-group" style="background:#F0FDF4; padding:12px; border-radius:12px;"><small>Default password: <strong>Password123!</strong><br>Manager can change after login.</small></div></div><div class="modal-footer"><button class="btn btn-ghost" onclick="closeCreateManagerModal()">Cancel</button><button class="btn btn-primary" onclick="createNewManager()">Create</button></div></div></div>

<script>
let currentMountainId = null;
let currentMountainName = '';
let heatmapMap = null;
let heatLayer = null;
let trailLayer = null;

// ========== EDIT MOUNTAIN FUNCTIONS ==========
function openEditModal(mountainId, mountainName) {
  currentMountainId = mountainId;
  document.getElementById('editModalTitle').innerHTML = `Edit ${mountainName}`;
  document.getElementById('editMountainModal').classList.add('open');
  
  const formData = new FormData();
  formData.append('action', 'get_mountain');
  formData.append('id', mountainId);
  
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      const m = result.mountain;
      document.getElementById('editMountainId').value = m.id;
      document.getElementById('editName').value = m.name || '';
      document.getElementById('editLocation').value = m.location || '';
      document.getElementById('editElevation').value = m.elevation || '';
      document.getElementById('editDifficulty').value = m.difficulty || 'Moderate';
      document.getElementById('editCrowdLevel').value = m.crowdLevel || 'Moderate';
      document.getElementById('editDuration').value = m.duration || '';
      document.getElementById('editRegistrationFee').value = m.registration_fee || 0;
      document.getElementById('editEnvironmentalFee').value = m.environmental_fee || 0;
      document.getElementById('editTrailLength').value = m.trail_length_km || '';
      document.getElementById('editEstimatedDuration').value = m.estimated_duration || '';
      document.getElementById('editJumpOff').value = m.jumpOff || '';
      document.getElementById('editRating').value = m.rating || '';
      document.getElementById('editStatus').value = m.status || 'Open';
      document.getElementById('editStartPointLat').value = m.start_point_lat || '';
      document.getElementById('editStartPointLng').value = m.start_point_lng || '';
      document.getElementById('editDescription').value = m.description || '';
      document.getElementById('editWeatherAdvisory').value = m.weatherAdvisory || '';
      document.getElementById('editPeakTimes').value = m.peakTimes || '';
      
      let rules = typeof m.rules === 'string' ? JSON.parse(m.rules || '[]') : (m.rules || []);
      let envReminders = typeof m.envReminders === 'string' ? JSON.parse(m.envReminders || '[]') : (m.envReminders || []);
      let hazards = typeof m.hazards === 'string' ? JSON.parse(m.hazards || '[]') : (m.hazards || []);
      
      document.getElementById('editRules').value = Array.isArray(rules) ? rules.join('\n') : '';
      document.getElementById('editEnvReminders').value = Array.isArray(envReminders) ? envReminders.join('\n') : '';
      document.getElementById('editHazards').value = Array.isArray(hazards) ? hazards.join('\n') : '';
    } else {
      alert('Error loading mountain data: ' + result.message);
      closeEditModal();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to load mountain data');
  });
}

function closeEditModal() {
  document.getElementById('editMountainModal').classList.remove('open');
}

function saveMountain() {
  const formData = new FormData();
  formData.append('action', 'update_mountain');
  formData.append('id', document.getElementById('editMountainId').value);
  formData.append('name', document.getElementById('editName').value);
  formData.append('location', document.getElementById('editLocation').value);
  formData.append('elevation', document.getElementById('editElevation').value);
  formData.append('difficulty', document.getElementById('editDifficulty').value);
  formData.append('crowdLevel', document.getElementById('editCrowdLevel').value);
  formData.append('duration', document.getElementById('editDuration').value);
  formData.append('registration_fee', document.getElementById('editRegistrationFee').value);
  formData.append('environmental_fee', document.getElementById('editEnvironmentalFee').value);
  formData.append('trail_length_km', document.getElementById('editTrailLength').value);
  formData.append('estimated_duration', document.getElementById('editEstimatedDuration').value);
  formData.append('jumpOff', document.getElementById('editJumpOff').value);
  formData.append('rating', document.getElementById('editRating').value);
  formData.append('status', document.getElementById('editStatus').value);
  formData.append('start_point_lat', document.getElementById('editStartPointLat').value);
  formData.append('start_point_lng', document.getElementById('editStartPointLng').value);
  formData.append('description', document.getElementById('editDescription').value);
  formData.append('weatherAdvisory', document.getElementById('editWeatherAdvisory').value);
  formData.append('peakTimes', document.getElementById('editPeakTimes').value);
  formData.append('rules', document.getElementById('editRules').value);
  formData.append('envReminders', document.getElementById('editEnvReminders').value);
  formData.append('hazards', document.getElementById('editHazards').value);
  
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      alert('Mountain updated successfully!');
      closeEditModal();
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to save mountain');
  });
}

// ========== HEATMAP MODAL FUNCTIONS ==========
function openHeatmapModal(mountainId, mountainName) {
  currentMountainId = mountainId;
  currentMountainName = mountainName;
  document.getElementById('heatmapModalTitle').innerHTML = `<i class="fas fa-fire"></i> ${mountainName} - Trail Heatmap`;
  document.getElementById('heatmapModal').classList.add('open');
  loadHeatmapData(mountainId);
  loadCrowdReports(mountainId);
}

function closeHeatmapModal() {
  document.getElementById('heatmapModal').classList.remove('open');
  if (heatmapMap) { heatmapMap.remove(); heatmapMap = null; }
  heatLayer = null;
  trailLayer = null;
  waypointMarkers = [];
}

async function loadHeatmapData(mountainId) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_heatmap_data');
    formData.append('mountain_id', mountainId);
    const response = await fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await response.json();
    if (data.success) {
      currentHeatmapPoints = data.points || [];
      initHeatmapMap(data);
      updateCrowdStats(data.points);
    } else { console.error('Failed to load heatmap data'); }
  } catch (error) { console.error('Error loading heatmap:', error); }
}

function initHeatmapMap(data) {
  const mapContainer = document.getElementById('heatmapMap');
  if (!mapContainer) return;
  if (heatmapMap) heatmapMap.remove();
  
  const centerLat = data.mountain?.lat || 14.0583;
  const centerLng = data.mountain?.lng || 120.8320;
  heatmapMap = L.map('heatmapMap').setView([centerLat, centerLng], 13);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution: '© OpenStreetMap, © CartoDB', subdomains: 'abcd', maxZoom: 19 }).addTo(heatmapMap);
  
  // Draw Trail
  if (data.trail && data.trail.length > 0) {
    const trailCoords = data.trail.map(c => [c[1], c[0]]);
    trailLayer = L.polyline(trailCoords, { color: '#100600', weight: 5, opacity: 0.85, lineCap: 'round', lineJoin: 'round' }).addTo(heatmapMap);
    heatmapMap.fitBounds(L.latLngBounds(trailCoords).pad(0.15));
  }
  
  // Add Waypoints
  if (data.waypoints && data.waypoints.length > 0) {
    data.waypoints.forEach(wp => {
      const iconHtml = `<div style="display:flex;align-items:center;justify-content:center;width:24px;height:24px;background:white;border-radius:50%;border:2px solid #c9a84c;box-shadow:0 2px 4px rgba(0,0,0,0.2);"><i class="fas fa-${wp.type === 'summit' ? 'mountain' : 'map-pin'}" style="font-size:10px;color:#c9a84c;"></i></div>`;
      const marker = L.marker([parseFloat(wp.latitude), parseFloat(wp.longitude)], { icon: L.divIcon({ html: iconHtml, iconSize: [24, 24], className: '' }) }).bindPopup(`<strong>${wp.name}</strong><br>${wp.type}${wp.elevation ? ` · ${wp.elevation}m` : ''}${wp.description ? `<br>${wp.description}` : ''}`).addTo(heatmapMap);
      waypointMarkers.push(marker);
    });
  }
  
  // Add Heatmap Layer
  if (data.points && data.points.length > 0) {
    const heatData = data.points.map(p => [p.lat, p.lng, p.intensity || 0.5]);
    heatLayer = L.heatLayer(heatData, { radius: 25, blur: 15, maxZoom: 17, minOpacity: 0.4, gradient: { 0.3: '#10B981', 0.5: '#84CC16', 0.6: '#F59E0B', 0.8: '#EF4444', 1.0: '#7F1D1D' } }).addTo(heatmapMap);
  }
}

function resetHeatmapView() {
  if (heatmapMap && trailLayer) {
    const bounds = trailLayer.getBounds();
    if (bounds.isValid()) heatmapMap.fitBounds(bounds.pad(0.1));
    else heatmapMap.setZoom(13);
  } else if (heatmapMap) heatmapMap.setZoom(13);
}

function toggleHeatmapLayer() {
  if (heatmapMap && heatLayer) {
    if (heatmapMap.hasLayer(heatLayer)) heatmapMap.removeLayer(heatLayer);
    else heatmapMap.addLayer(heatLayer);
  }
}

function updateCrowdStats(points) {
  const statsDiv = document.getElementById('crowdStats');
  if (!statsDiv) return;
  if (!points || points.length === 0) { statsDiv.innerHTML = '<span class="badge" style="background:#fee2e2; color:#991b1b;">No crowd data</span>'; return; }
  const avgIntensity = points.reduce((sum, p) => sum + (p.intensity || 0), 0) / points.length;
  let crowdStatus = '', statusColor = '';
  if (avgIntensity > 0.7) { crowdStatus = 'High Traffic'; statusColor = '#EF4444'; }
  else if (avgIntensity > 0.4) { crowdStatus = 'Medium Traffic'; statusColor = '#F59E0B'; }
  else { crowdStatus = 'Low Traffic'; statusColor = '#10B981'; }
  statsDiv.innerHTML = `<span class="badge" style="background: ${statusColor}20; color: ${statusColor};"><i class="fas fa-chart-line"></i> ${crowdStatus} · ${points.length} data points</span>`;
}

async function loadCrowdReports(mountainId) {
  const reportsList = document.getElementById('crowdReportsList');
  if (!reportsList) return;
  try {
    const formData = new FormData();
    formData.append('action', 'get_crowd_reports');
    formData.append('mountain_id', mountainId);
    const response = await fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await response.json();
    if (data.success && data.reports && data.reports.length > 0) reportsList.innerHTML = renderCrowdReports(data.reports);
    else reportsList.innerHTML = '<div class="empty-state"><i class="fas fa-chart-line"></i><p>No crowd reports yet</p><small>Reports appear when guides submit crowd levels</small></div>';
  } catch (error) { reportsList.innerHTML = '<div class="empty-state">Error loading reports</div>'; }
}

function renderCrowdReports(reports) {
  return reports.map(report => {
    const levelClass = report.crowd_level;
    const levelIcon = report.crowd_level === 'Low' ? 'fa-smile' : (report.crowd_level === 'Medium' ? 'fa-meh' : 'fa-frown');
    const reportDate = new Date(report.created_at).toLocaleString('en-PH');
    const reporterDisplay = report.reporter_role === 'guide' ? `<i class="fas fa-user-check"></i> Guide: ${escapeHtml(report.reporter_name)}` : `<i class="fas fa-robot"></i> ${escapeHtml(report.reporter_name || 'System')}`;
    const hasLocation = report.latitude && report.longitude;
    return `<div class="crowd-report-item" ${hasLocation ? `onclick="zoomToReportLocation(${report.latitude}, ${report.longitude})"` : ''}>
      <div class="crowd-report-icon level-${levelClass}"><i class="fas ${levelIcon}"></i></div>
      <div class="crowd-report-content">
        <div class="crowd-report-header"><span class="crowd-report-badge ${levelClass}">${report.crowd_level}</span></div>
        <div class="crowd-report-details"><span class="crowd-report-reporter">${reporterDisplay}</span><span class="crowd-report-time"><i class="far fa-clock"></i> ${reportDate}</span>${hasLocation ? `<span class="crowd-report-location" onclick="event.stopPropagation(); zoomToReportLocation(${report.latitude}, ${report.longitude})"><i class="fas fa-map-marker-alt"></i> View on map</span>` : ''}</div>
        ${report.notes ? `<div class="crowd-report-notes"><i class="fas fa-comment"></i> ${escapeHtml(report.notes)}</div>` : ''}
      </div>
    </div>`;
  }).join('');
}

function refreshHeatmap() { if (currentMountainId) { loadHeatmapData(currentMountainId); loadCrowdReports(currentMountainId); } }
function zoomToReportLocation(lat, lng) { if (heatmapMap) heatmapMap.setView([lat, lng], 16); }
function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
// ========== MANAGER FUNCTIONS ==========
function openAssignModal(mountainId, mountainName) {
  currentMountainId = mountainId;
  currentMountainName = mountainName;
  document.getElementById('assignModalTitle').innerHTML = `Assign Manager to ${mountainName}`;
  document.getElementById('assignMountainId').value = mountainId;
  document.getElementById('assignModal').classList.add('open');
}

function closeAssignModal() { document.getElementById('assignModal').classList.remove('open'); }
function openCreateManagerModal() { closeAssignModal(); document.getElementById('createManagerModal').classList.add('open'); }
function closeCreateManagerModal() { document.getElementById('createManagerModal').classList.remove('open'); if (currentMountainId) openAssignModal(currentMountainId, currentMountainName); }

function createNewManager() {
  const name = document.getElementById('newManagerName').value.trim();
  const email = document.getElementById('newManagerEmail').value.trim();
  const phone = document.getElementById('newManagerPhone').value.trim();
  if (!name || !email) { alert('Please fill in Name and Email'); return; }
  const formData = new FormData();
  formData.append('action', 'create_manager');
  formData.append('name', name);
  formData.append('email', email);
  formData.append('phone', phone);
  fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        alert(`${result.manager_name} created!\nEmail: ${result.manager_email}\nPassword: Password123!`);
        closeCreateManagerModal();
        location.reload();
      } else { alert('Error: ' + result.message); }
    })
    .catch(error => { alert('Failed to create manager'); });
}

function confirmAssignManager() {
  const selectedRadio = document.querySelector('.manager-option input[type="radio"]:checked');
  if (!selectedRadio) { alert('Please select a manager'); return; }
  const managerId = selectedRadio.value;
  const sendEmail = document.getElementById('sendEmailCheckbox').checked;
  const formData = new FormData();
  formData.append('action', 'assign_manager');
  formData.append('mountain_id', currentMountainId);
  formData.append('manager_id', managerId);
  if (sendEmail) formData.append('send_email', '1');
  fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
    .then(response => response.json())
    .then(result => {
      if (result.success) { alert(result.message); location.reload(); }
      else { alert('Error: ' + result.message); }
    })
    .catch(error => { alert('Failed to assign manager'); });
}

function removeManager(assignmentId, mountainName) {
  if (confirm(`Remove this manager from ${mountainName}?`)) {
    const formData = new FormData();
    formData.append('action', 'remove_manager');
    formData.append('assignment_id', assignmentId);
    fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
      .then(response => response.json())
      .then(result => { if (result.success) location.reload(); else alert('Error: ' + result.message); });
  }
}

// ========== UTILITIES ==========
function updateDate() {
  const d = new Date();
  const dateElem = document.getElementById('liveDate');
  if (dateElem) dateElem.textContent = d.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' }).toUpperCase() + '  ' + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}
updateDate();
setInterval(updateDate, 1000);

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if(item.dataset.href) window.location.href = item.dataset.href; });
});

document.querySelectorAll('.edit-mtn-btn').forEach(btn => {
  btn.addEventListener('click', () => { openEditModal(parseInt(btn.dataset.id), btn.dataset.name); });
});

document.querySelectorAll('.crowd-heatmap-btn').forEach(btn => {
  btn.addEventListener('click', () => { openHeatmapModal(parseInt(btn.dataset.id), btn.dataset.name); });
});

function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
function openProfileModal() { alert('Profile modal'); }
function showLogoutModal() { alert('Logout modal'); }

// Manager option click handler
document.querySelectorAll('.manager-option').forEach(opt => {
  opt.addEventListener('click', function() {
    const radio = this.querySelector('input[type="radio"]');
    if (radio) { radio.checked = true;
      document.querySelectorAll('.manager-option').forEach(o => o.classList.remove('selected'));
      this.classList.add('selected');
    }
  });
});
</script>

<?php include_once __DIR__ . '/profile-modal.php'; include_once __DIR__ . '/../../includes/logout-modal.php'; ?>
</body>
</html>