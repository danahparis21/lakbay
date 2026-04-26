<?php
// api/get_guide_hikers.php - Get all hikers for guide's booking
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guide') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$bookingId = $_GET['booking_id'] ?? 0;

if (!$bookingId) {
    echo json_encode(['success' => false, 'error' => 'Booking ID required']);
    exit;
}

try {
    // Get all hikers for this booking
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            COALESCE(bh.hiker_name, u.name) as name,
            u.id as user_id,
            u.avatar,
            u.location_tracking_enabled,
            ls.latitude,
            ls.longitude,
            ls.recorded_at,
            TIMESTAMPDIFF(MINUTE, ls.recorded_at, NOW()) as minutes_ago
        FROM bookings b
        LEFT JOIN booking_hikers bh ON b.id = bh.booking_id
        LEFT JOIN users u ON (bh.hiker_name = u.name AND u.role = 'hiker') 
            OR (b.user_id = u.id AND u.role = 'hiker')
        LEFT JOIN location_history ls ON u.id = ls.user_id AND ls.booking_id = b.id
        WHERE b.id = ? AND u.id IS NOT NULL
        GROUP BY u.id
        ORDER BY ls.recorded_at DESC
    ");
    $stmt->execute([$bookingId]);
    $hikers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($hikers as $hiker) {
        $result[] = [
            'name' => $hiker['name'],
            'user_id' => $hiker['user_id'],
            'avatar' => $hiker['avatar'],
            'latitude' => $hiker['latitude'],
            'longitude' => $hiker['longitude'],
            'recorded_at' => $hiker['recorded_at'],
            'minutes_ago' => $hiker['minutes_ago'],
            'tracking_enabled' => $hiker['location_tracking_enabled'] == 1,
            'is_active' => $hiker['minutes_ago'] !== null && $hiker['minutes_ago'] <= 10
        ];
    }
    
    echo json_encode(['success' => true, 'hikers' => $result]);
    
} catch (PDOException $e) {
    error_log("Error fetching guide hikers: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>