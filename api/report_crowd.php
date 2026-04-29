<?php
// api/report_crowd.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['mountain_id']) || !isset($data['crowd_level'])) {
    echo json_encode(['success' => false, 'error' => 'Mountain ID and crowd level required']);
    exit();
}

// Validate crowd level
$valid_levels = ['Low', 'Medium', 'High'];
if (!in_array($data['crowd_level'], $valid_levels)) {
    echo json_encode(['success' => false, 'error' => 'Invalid crowd level']);
    exit();
}

try {
    // Insert new crowd report (expires in 2 hours)
    $stmt = $pdo->prepare("
        INSERT INTO crowd_reports (
            mountain_id, reported_by, crowd_level, latitude, longitude, 
            trail_segment_index, notes, created_at, expires_at
        ) VALUES (
            :mountain_id, :reported_by, :crowd_level, :latitude, :longitude,
            :trail_segment, :notes, NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR)
        )
    ");
    
    $stmt->execute([
        ':mountain_id' => $data['mountain_id'],
        ':reported_by' => $_SESSION['user_id'],
        ':crowd_level' => $data['crowd_level'],
        ':latitude' => $data['latitude'] ?? null,
        ':longitude' => $data['longitude'] ?? null,
        ':trail_segment' => $data['trail_segment_index'] ?? null,
        ':notes' => $data['notes'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'report_id' => $pdo->lastInsertId()]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>