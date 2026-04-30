<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$manager_id = $_SESSION['user_id'];
$mountain_id = $_GET['mountain_id'] ?? null;

if (!$mountain_id) {
    echo json_encode(['success' => false, 'message' => 'Mountain ID required']);
    exit;
}

// Verify manager has access to this mountain
$stmt = $pdo->prepare("SELECT 1 FROM manager_mountains WHERE manager_id = ? AND mountain_id = ?");
$stmt->execute([$manager_id, $mountain_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $allHikers = [];
    
    // Get all active/confirmed bookings for this mountain
    $stmt = $pdo->prepare("
        SELECT b.id as booking_id, b.booking_number
        FROM bookings b
        WHERE b.mountain_id = ? 
        AND b.status IN ('active', 'confirmed')
        AND b.hike_date >= CURDATE()
    ");
    $stmt->execute([$mountain_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($bookings)) {
        echo json_encode(['success' => true, 'hikers' => [], 'count' => 0]);
        exit;
    }
    
    $bookingIds = array_column($bookings, 'booking_id');
    
    // Get booking owners (the users who made the booking)
    $placeholders = rtrim(str_repeat('?,', count($bookingIds)), ',');
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            b.id as booking_id,
            b.booking_number,
            u.id as user_id,
            u.name as hiker_name,
            u.avatar,
            u.location_tracking_enabled,
            g.name as guide_name,
            lh.latitude,
            lh.longitude,
            lh.recorded_at,
            TIMESTAMPDIFF(MINUTE, lh.recorded_at, NOW()) as minutes_ago
        FROM bookings b
        INNER JOIN users u ON b.user_id = u.id
        LEFT JOIN users g ON b.guide_id = g.id
        LEFT JOIN location_history lh ON u.id = lh.user_id AND b.id = lh.booking_id
        WHERE b.id IN ($placeholders)
    ");
    $stmt->execute($bookingIds);
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($owners as $owner) {
        $allHikers[] = $owner;
    }
    
    // Get additional hikers from booking_hikers table
    $stmt = $pdo->prepare("
        SELECT bh.booking_id, bh.hiker_name, u.id as user_id, u.location_tracking_enabled
        FROM booking_hikers bh
        LEFT JOIN users u ON u.name = bh.hiker_name AND u.role = 'hiker'
        WHERE bh.booking_id IN ($placeholders)
    ");
    $stmt->execute($bookingIds);
    $additional = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($additional as $add) {
        if (empty($add['hiker_name'])) continue;
        
        // Check if already exists
        $exists = false;
        foreach ($allHikers as $h) {
            if ($h['hiker_name'] == $add['hiker_name']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            // Get location if available
            $latitude = null;
            $longitude = null;
            $minutes_ago = null;
            
            if ($add['user_id']) {
                $locStmt = $pdo->prepare("
                    SELECT latitude, longitude, recorded_at,
                           TIMESTAMPDIFF(MINUTE, recorded_at, NOW()) as minutes_ago
                    FROM location_history
                    WHERE user_id = ? AND booking_id = ?
                    ORDER BY recorded_at DESC LIMIT 1
                ");
                $locStmt->execute([$add['user_id'], $add['booking_id']]);
                $loc = $locStmt->fetch(PDO::FETCH_ASSOC);
                if ($loc) {
                    $latitude = $loc['latitude'];
                    $longitude = $loc['longitude'];
                    $minutes_ago = $loc['minutes_ago'];
                }
            }
            
            $allHikers[] = [
                'booking_id' => $add['booking_id'],
                'booking_number' => null,
                'user_id' => $add['user_id'],
                'hiker_name' => $add['hiker_name'],
                'avatar' => null,
                'location_tracking_enabled' => $add['location_tracking_enabled'],
                'guide_name' => null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'minutes_ago' => $minutes_ago
            ];
        }
    }
    
    // Process results
    $processed = [];
    foreach ($allHikers as $h) {
        $minutesAgo = $h['minutes_ago'] !== null ? intval($h['minutes_ago']) : null;
        $isActive = ($minutesAgo !== null && $minutesAgo <= 15);
        $trackingOn = isset($h['location_tracking_enabled']) && $h['location_tracking_enabled'] == 1;
        
        $processed[] = [
            'booking_id' => $h['booking_id'],
            'booking_number' => $h['booking_number'],
            'name' => $h['hiker_name'],
            'user_id' => $h['user_id'],
            'avatar' => $h['avatar'],
            'guide_name' => $h['guide_name'],
            'latitude' => $h['latitude'] ? floatval($h['latitude']) : null,
            'longitude' => $h['longitude'] ? floatval($h['longitude']) : null,
            'minutes_ago' => $minutesAgo,
            'tracking_on' => $trackingOn,
            'is_active' => $isActive
        ];
    }
    
    echo json_encode([
        'success' => true,
        'hikers' => $processed,
        'count' => count($processed)
    ]);
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>