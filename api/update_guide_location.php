<?php
// api/update_guide_location.php - Update guide's location
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
ini_set('date.timezone', 'Asia/Manila');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guide') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$sessionToken = $data['session_token'] ?? '';
$bookingId = $data['booking_id'] ?? '';
$lat = $data['latitude'] ?? null;
$lng = $data['longitude'] ?? null;

if (!$sessionToken || !$bookingId || !$lat || !$lng) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Insert into location_history (reuse same table for guides too!)
    $stmt = $pdo->prepare("
        INSERT INTO location_history (user_id, booking_id, session_token, latitude, longitude, recorded_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $bookingId, $sessionToken, $lat, $lng]);
    
    // Update active_hike_sessions
    $stmt = $pdo->prepare("
        UPDATE active_hike_sessions 
        SET last_latitude = ?, last_longitude = ?, last_location_update = NOW()
        WHERE session_token = ? AND user_id = ?
    ");
    $stmt->execute([$lat, $lng, $sessionToken, $userId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Error updating guide location: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>