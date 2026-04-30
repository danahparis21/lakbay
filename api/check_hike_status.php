<?php
// api/check_hike_status.php
require_once __DIR__ . '/../config/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$bookingId = $_GET['booking_id'] ?? null;
if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.status, 
               b.hike_date,
               m.name as mountain_name,
               (SELECT COUNT(*) FROM booking_hikers WHERE booking_id = b.id) as hiker_count
        FROM bookings b
        JOIN mountains m ON b.mountain_id = m.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'status' => $booking['status'],
        'summary' => $booking['status'] === 'finished' ? [
            'mountain' => $booking['mountain_name'],
            'completed_at' => date('Y-m-d H:i:s'),
            'message' => 'Hike completed by your guide!'
        ] : null
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>