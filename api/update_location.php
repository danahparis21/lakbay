<?php
// api/update_location.php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$sessionToken = $data['session_token'] ?? '';
$bookingId = $data['booking_id'] ?? '';
$lat = $data['latitude'] ?? null;
$lng = $data['longitude'] ?? null;

if (!$sessionToken || !$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO location_history (user_id, booking_id, session_token, latitude, longitude, recorded_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), recorded_at = NOW()
    ");
    $stmt->execute([$userId, $bookingId, $sessionToken, $lat, $lng]);
    
    // Update active session last location
    $stmt = $pdo->prepare("
        UPDATE active_hike_sessions 
        SET last_latitude = ?, last_longitude = ?, last_location_update = NOW()
        WHERE session_token = ? AND user_id = ?
    ");
    $stmt->execute([$lat, $lng, $sessionToken, $userId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}