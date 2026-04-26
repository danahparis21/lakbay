<?php
/**
 * finish_hike.php — Finish Hike API
 * 
 * Called when guide clicks "Finish Hike" in guide-map.php.
 */
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$bookingId    = $input['booking_id'] ?? null;
$sessionToken = $input['session_token'] ?? null;
$distance     = $input['distance'] ?? 0;
$duration     = $input['duration'] ?? 0;
$badges       = $input['badges'] ?? [];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get booking details
    $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.mountain_id, b.hike_date, b.hike_type, m.name as mountain_name
        FROM bookings b
        JOIN mountains m ON b.mountain_id = m.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Mark the booking as FINISHED (not completed!)
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'finished'
        WHERE id = ?
    ");
    $stmt->execute([$bookingId]);

    // If there's an active hike session, update it
    if ($sessionToken) {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM active_hike_sessions LIKE 'end_time'");
        $hasEndTime = $checkColumn->rowCount() > 0;
        
        $badgesJson = json_encode($badges);
        
        if ($hasEndTime) {
            $stmt = $pdo->prepare("
                UPDATE active_hike_sessions 
                SET status = 'finished',
                    end_time = NOW(),
                    total_distance = ?,
                    total_duration = ?,
                    badges_earned = ?
                WHERE booking_id = ? AND session_token = ? AND status = 'active'
            ");
            $stmt->execute([
                $distance,
                $duration,
                $badgesJson,
                $bookingId,
                $sessionToken
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE active_hike_sessions 
                SET status = 'finished'
                WHERE booking_id = ? AND session_token = ? AND status = 'active'
            ");
            $stmt->execute([$bookingId, $sessionToken]);
        }
    }

    $pdo->commit();

    // Build summary
    $hours = floor($duration / 3600);
    $mins  = floor(($duration % 3600) / 60);
    $timeStr = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";

    echo json_encode([
        'success'  => true,
        'message'  => 'Hike finished successfully!',
        'summary'  => [
            'mountain'      => $booking['mountain_name'],
            'distance_km'   => round((float)$distance, 2),
            'duration'      => $timeStr,
            'duration_sec'  => (int)$duration,
            'hike_date'     => $booking['hike_date'],
            'hike_type'     => $booking['hike_type'],
            'completed_at'  => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Finish hike error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>