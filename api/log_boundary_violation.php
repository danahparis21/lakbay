<?php
// api/log_boundary_violation.php
require_once __DIR__ . '/../config/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$booking_id = $data['booking_id'] ?? null;
$user_id = $data['user_id'] ?? null;
$hiker_name = $data['hiker_name'] ?? '';
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$distance_from_trail = $data['distance_from_trail'] ?? null;

if (!$booking_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO boundary_violations 
        (booking_id, user_id, hiker_name, latitude, longitude, distance_from_trail, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$booking_id, $user_id, $hiker_name, $latitude, $longitude, $distance_from_trail]);
    
    // Also insert notification for admin/safety
    $stmt2 = $pdo->prepare("
        INSERT INTO safety_alerts 
        (booking_id, alert_type, message, severity, created_at)
        VALUES (?, 'boundary_violation', ?, 'high', NOW())
    ");
    $message = "Hiker '$hiker_name' went off trail by " . round($distance_from_trail * 1000) . " meters";
    $stmt2->execute([$booking_id, $message]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>