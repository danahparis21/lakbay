<?php
/**
 * finish_hike.php — Finish Hike API
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
$duration     = (int)round($input['duration'] ?? 0);  // ← FIXED HERE
$badges       = $input['badges'] ?? [];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get booking details
    $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.mountain_id, m.name as mountain_name
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

    // Get all location points for this session to calculate more stats
    $stmt = $pdo->prepare("
        SELECT latitude, longitude, recorded_at 
        FROM location_history 
        WHERE session_token = ? 
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$sessionToken]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate max speed
    $maxSpeed = 0;
    for ($i = 1; $i < count($points); $i++) {
        $lat1 = $points[$i-1]['latitude'];
        $lon1 = $points[$i-1]['longitude'];
        $lat2 = $points[$i]['latitude'];
        $lon2 = $points[$i]['longitude'];
        
        $dist = calculateDistance($lat1, $lon1, $lat2, $lon2);
        $timeDiff = strtotime($points[$i]['recorded_at']) - strtotime($points[$i-1]['recorded_at']);
        
        if ($timeDiff > 0) {
            $speed = ($dist / $timeDiff) * 3.6; // km/h
            $maxSpeed = max($maxSpeed, $speed);
        }
    }
    
    $avgSpeed = $distance > 0 ? ($distance / ($duration / 3600)) : 0;
$paceMinutes = $distance > 0 ? ($duration / 60) / $distance : 0;
// Safe pace formatting without implicit float conversion
$paceMinutesInt = (int)floor($paceMinutes);
$paceSecondsInt = (int)round(($paceMinutes - $paceMinutesInt) * 60);
$paceFormatted = $paceMinutesInt . ":" . str_pad((string)$paceSecondsInt, 2, "0", STR_PAD_LEFT);
    // Mark booking as finished
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'finished' WHERE id = ?");
    $stmt->execute([$bookingId]);

    // Update active_hike_sessions with all stats
    $badgesJson = json_encode($badges);
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

    $pdo->commit();

    // Build summary with Strava-like stats
    $hours = floor($duration / 3600);
    $mins = floor(($duration % 3600) / 60);
    $timeStr = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";

    echo json_encode([
        'success'  => true,
        'message'  => 'Hike finished successfully!',
        'summary'  => [
            'mountain'       => $booking['mountain_name'],
            'distance_km'    => round((float)$distance, 2),
            'duration'       => $timeStr,
            'duration_sec'   => (int)round($duration),
            'avg_speed_kmh'  => round($avgSpeed, 1),
            'max_speed_kmh'  => round($maxSpeed, 1),
            'pace'           => $paceFormatted,
            'badges_count'   => count($badges),
            'completed_at'   => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Finish hike error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}
?>