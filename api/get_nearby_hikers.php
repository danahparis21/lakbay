<?php
// api/get_nearby_hikers.php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$sessionToken = $_GET['session_token'] ?? '';
$bookingId = $_GET['booking_id'] ?? '';
$mountainId = $_GET['mountain_id'] ?? '';

try {
    // Get all active hikers on the same mountain within last 5 minutes (excluding current user)
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id, 
            u.name,
            ls.latitude, 
            ls.longitude,
            TIMESTAMPDIFF(MINUTE, ls.recorded_at, NOW()) as minutes_ago,
            ls.recorded_at
        FROM location_history ls
        JOIN users u ON ls.user_id = u.id
        JOIN active_hike_sessions ahs ON ahs.session_token = ls.session_token
        JOIN bookings b ON ahs.booking_id = b.id
        WHERE b.mountain_id = ?
        AND ls.user_id != ?
        AND ls.recorded_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        GROUP BY ls.user_id
        ORDER BY ls.recorded_at DESC
    ");
    $stmt->execute([$mountainId, $currentUserId]);
    $hikers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'hikers' => $hikers, 'count' => count($hikers)]);
} catch (PDOException $e) {
    error_log("Nearby hikers error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>