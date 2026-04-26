<?php
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$mountain_id = $data['mountain_id'] ?? null;
$crowd_level = $data['crowd_level'] ?? null;
$location_lat = $data['location_lat'] ?? null;
$location_lng = $data['location_lng'] ?? null;
$segment_note = $data['segment_note'] ?? '';

if (!$mountain_id || !$crowd_level || !$location_lat || !$location_lng) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Convert crowd level to intensity
    $intensity = match($crowd_level) {
        'High' => 1.0,
        'Medium' => 0.7,
        'Low' => 0.3,
        default => 0.5
    };
    
    // First, expire old manual reports at this exact location
    $stmt = $pdo->prepare("
        UPDATE crowd_reports 
        SET expires_at = NOW() 
        WHERE mountain_id = ? 
        AND ABS(latitude - ?) < 0.0005 
        AND ABS(longitude - ?) < 0.0005
        AND expires_at > NOW()
    ");
    $stmt->execute([$mountain_id, $location_lat, $location_lng]);
    
    // Insert new report (expires in 2 hours)
    $stmt = $pdo->prepare("
        INSERT INTO crowd_reports (mountain_id, reported_by, crowd_level, latitude, longitude, notes, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())
    ");
    $stmt->execute([
        $mountain_id,
        $_SESSION['user_id'],
        $crowd_level,
        $location_lat,
        $location_lng,
        $segment_note ?: "Reported at " . date('g:i A')
    ]);
    
    // Update or insert into heatmap data (REPLACE not INSERT)
    $stmt = $pdo->prepare("
        INSERT INTO crowd_heatmap_data (mountain_id, latitude, longitude, intensity, hiker_count, source, detected_at, updated_at)
        VALUES (?, ?, ?, ?, 0, 'manual', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
        intensity = VALUES(intensity),
        source = 'manual',
        updated_at = NOW()
    ");
    $stmt->execute([$mountain_id, $location_lat, $location_lng, $intensity]);
    
    echo json_encode([
        'success' => true,
        'message' => "Crowd level updated to: $crowd_level",
        'segment' => 'trail area',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Crowd report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>