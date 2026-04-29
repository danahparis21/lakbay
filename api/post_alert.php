<?php
// api/post_alert.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO alerts (
            mountain_id, title, description, location, trail_name, type, severity, 
            status, reported_by, reporter_name, reporter_role, latitude, longitude, created_at
        ) VALUES (
            :mountain_id, :title, :description, :location, :trail_name, :type, :severity,
            'active', :reported_by, :reporter_name, :reporter_role, :latitude, :longitude, NOW()
        )
    ");
    
    $stmt->execute([
        ':mountain_id' => $data['mountain_id'] ?? null,
        ':title' => $data['title'],
        ':description' => $data['description'],
        ':location' => $data['location'] ?? null,
        ':trail_name' => $data['trail_name'] ?? null,
        ':type' => $data['type'] ?? 'safety',
        ':severity' => $data['severity'] ?? 'medium',
        ':reported_by' => $_SESSION['user_id'],
        ':reporter_name' => $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Manager',
        ':reporter_role' => $_SESSION['user_role'],
        ':latitude' => $data['latitude'] ?? null,
        ':longitude' => $data['longitude'] ?? null
    ]);
    
    $alert_id = $pdo->lastInsertId();
    
    echo json_encode(['success' => true, 'alert_id' => $alert_id]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>