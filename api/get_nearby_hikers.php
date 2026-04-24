<?php
// api/get_nearby_hikers.php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$sessionToken = $_GET['session_token'] ?? '';
$bookingId = $_GET['booking_id'] ?? '';

try {
    // Get all active hikers on the same mountain within last 5 minutes
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id, 
            u.name,
            ls.latitude, 
            ls.longitude,
            TIMESTAMPDIFF(MINUTE, ls.recorded_at, NOW()) as minutes_ago
        FROM location_history ls
        JOIN users u ON ls.user_id = u.id
        JOIN active_hike_sessions ahs ON ahs.session_token = ls.session_token
        WHERE ahs.booking_id = ?
        AND ls.recorded_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        GROUP BY ls.user_id
    ");
    $stmt->execute([$bookingId]);
    $hikers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'hikers' => $hikers]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}