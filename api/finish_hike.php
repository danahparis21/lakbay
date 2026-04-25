<?php
/**
 * finish_hike.php — Finish Hike API
 * 
 * Called when user clicks "Finish Hike" in active-hike.php.
 * Stops the active session, saves route stats, and marks booking as completed.
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

if (!$bookingId || !$sessionToken) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id or session_token']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verify the booking belongs to this user
    $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.mountain_id, b.hike_date, b.hike_type, m.name as mountain_name
        FROM bookings b
        JOIN mountains m ON b.mountain_id = m.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$bookingId, $userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    if (!in_array($booking['status'], ['active', 'confirmed', 'pending'])) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking is not in an active state']);
        exit;
    }

    // 2. Mark the booking as finished
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'finished'
        WHERE id = ?
    ");
    $stmt->execute([$bookingId]);

    // 3. End the active hike session
    $checkColumn = $pdo->query("SHOW COLUMNS FROM active_hike_sessions LIKE 'end_time'");
    $hasEndTime = $checkColumn->rowCount() > 0;
    
    // Convert badges array to JSON for storage
    $badgesJson = json_encode($badges);
    
    if ($hasEndTime) {
        $stmt = $pdo->prepare("
            UPDATE active_hike_sessions 
            SET status = 'finished',
                end_time = NOW(),
                total_distance = ?,
                total_duration = ?,
                badges_earned = ?
            WHERE booking_id = ? AND user_id = ? AND session_token = ? AND status = 'active'
        ");
        $stmt->execute([
            $distance,
            $duration,
            $badgesJson,
            $bookingId,
            $userId,
            $sessionToken
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE active_hike_sessions 
            SET status = 'finished'
            WHERE booking_id = ? AND user_id = ? AND session_token = ? AND status = 'active'
        ");
        $stmt->execute([$bookingId, $userId, $sessionToken]);
    }

    // 4. Get mountain name for response
    $stmt = $pdo->prepare("SELECT name FROM mountains WHERE id = ?");
    $stmt->execute([$booking['mountain_id']]);
    $mountain = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    // Build summary
    $hours = floor($duration / 3600);
    $mins  = floor(($duration % 3600) / 60);
    $timeStr = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";

    echo json_encode([
        'success'  => true,
        'message'  => 'Hike completed successfully!',
        'summary'  => [
            'mountain'      => $mountain['name'] ?? 'Unknown',
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