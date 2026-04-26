<?php
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$mountain_id = $_GET['mountain_id'] ?? null;
if (!$mountain_id) {
    echo json_encode(['success' => false, 'message' => 'Mountain ID required']);
    exit;
}

try {
    // Get current heatmap data (manual reports override)
    $stmt = $pdo->prepare("
        SELECT latitude, longitude, intensity, source, hiker_count
        FROM crowd_heatmap_data
        WHERE mountain_id = ? 
        AND updated_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY source DESC, updated_at DESC
    ");
    $stmt->execute([$mountain_id]);
    $heatmapData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get automatic detections from recent hiker locations
    $stmt = $pdo->prepare("
        SELECT 
            ROUND(lh.latitude, 5) as latitude,
            ROUND(lh.longitude, 5) as longitude,
            COUNT(DISTINCT lh.user_id) as hiker_count,
            MAX(lh.recorded_at) as latest_update
        FROM location_history lh
        JOIN bookings b ON lh.booking_id = b.id
        WHERE b.mountain_id = ? 
        AND lh.recorded_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND lh.latitude IS NOT NULL
        GROUP BY ROUND(lh.latitude, 5), ROUND(lh.longitude, 5)
        HAVING hiker_count >= 2
    ");
    $stmt->execute([$mountain_id]);
    $autoDetections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine both sources (manual takes priority)
    $points = [];
    $manualLocations = [];
    
    // First, add manual reports
    foreach ($heatmapData as $data) {
        if ($data['source'] === 'manual') {
            $manualLocations[] = [
                'lat' => $data['latitude'],
                'lng' => $data['longitude']
            ];
            $points[] = [
                'latitude' => (float)$data['latitude'],
                'longitude' => (float)$data['longitude'],
                'intensity' => (float)$data['intensity'],
                'source' => 'manual',
                'hiker_count' => 0
            ];
        }
    }
    
    // Then add automatic detections (if not near manual reports)
    foreach ($autoDetections as $detection) {
        // Calculate intensity based on hiker count
        $intensity = min(1.0, $detection['hiker_count'] / 15);
        
        // Check if near any manual report (within 50 meters)
        $nearManual = false;
        foreach ($manualLocations as $manual) {
            $distance = sqrt(
                pow($manual['lat'] - $detection['latitude'], 2) + 
                pow($manual['lng'] - $detection['longitude'], 2)
            ) * 111000;
            
            if ($distance < 50) {
                $nearManual = true;
                break;
            }
        }
        
        if (!$nearManual && $intensity > 0.1) {
            $points[] = [
                'latitude' => (float)$detection['latitude'],
                'longitude' => (float)$detection['longitude'],
                'intensity' => $intensity,
                'source' => 'automatic',
                'hiker_count' => (int)$detection['hiker_count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'points' => $points,
        'count' => count($points),
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => 'Asia/Manila'
    ]);
    
} catch (PDOException $e) {
    error_log("Heatmap detection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>