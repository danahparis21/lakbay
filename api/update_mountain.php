<?php
// api/update_mountain.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Verify manager has permission for this mountain
$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT 1 FROM manager_mountains WHERE manager_id = ? AND mountain_id = ?");
$stmt->execute([$manager_id, $data['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    // Build update query dynamically based on what fields are provided
    $updateFields = [];
    $params = [];
    
    $fields = [
        'name', 'location', 'description', 'difficulty', 'elevation',
        'registration_fee', 'environmental_fee', 'trail_length_km',
        'estimated_duration', 'jumpOff', 'weatherAdvisory', 'peakTimes',
        'rules', 'envReminders', 'hazards', 'status'
    ];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            if ($field === 'rules' || $field === 'envReminders' || $field === 'hazards') {
                $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
            } else {
                $params[] = $data[$field];
            }
        }
    }
    
    $updateFields[] = "updated_at = NOW()";
    $params[] = $data['id'];
    
    $sql = "UPDATE mountains SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Mountain updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>