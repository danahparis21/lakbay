<?php
// api/detect_crowd_hotspots.php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$mountain_id = isset($_GET['mountain_id']) ? intval($_GET['mountain_id']) : 0;
if (!$mountain_id) {
    echo json_encode(['success' => false, 'message' => 'Mountain ID required']);
    exit;
}

try {
    // Get crowd reports with actual level values from your existing crowd_reports table
    $stmt = $pdo->prepare("
        SELECT 
            latitude, 
            longitude, 
            crowd_level,
            COUNT(*) as report_count,
            MAX(created_at) as last_reported
        FROM crowd_reports
        WHERE mountain_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
        GROUP BY latitude, longitude, crowd_level
        ORDER BY last_reported DESC
    ");
    $stmt->execute([$mountain_id]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group reports by location (cluster within ~50 meters)
    $clustered = [];
    $clusterRadius = 0.0005; // ~50 meters in degrees
    
    foreach ($reports as $report) {
        $lat = floatval($report['latitude']);
        $lng = floatval($report['longitude']);
        $level = $report['crowd_level'];
        
        // Find if this point belongs to an existing cluster
        $found = false;
        foreach ($clustered as &$cluster) {
            $dist = sqrt(pow($lat - $cluster['lat'], 2) + pow($lng - $cluster['lng'], 2));
            if ($dist < $clusterRadius) {
                // Update cluster with the MOST RECENT crowd level
                $cluster['report_count'] += intval($report['report_count']);
                $cluster['levels'][] = $level;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $clustered[] = [
                'lat' => $lat,
                'lng' => $lng,
                'levels' => [$level],
                'report_count' => intval($report['report_count'])
            ];
        }
    }
    
    // Convert clusters to heatmap points with intensity based on CROWD LEVEL
    $points = [];
    foreach ($clustered as $cluster) {
        // Determine the dominant crowd level
        $levelCounts = array_count_values($cluster['levels']);
        
        // Get the most frequent level, or highest level if tie
        arsort($levelCounts);
        $dominantLevel = key($levelCounts);
        
        // Convert crowd level to intensity value (0-1)
        // Low = 0.25, Medium = 0.65, High = 1.0
        $intensity = match($dominantLevel) {
            'Low' => 0.25,
            'Medium' => 0.65,
            'High' => 1.0,
            default => 0.25
        };
        
        $points[] = [
            'latitude' => $cluster['lat'],
            'longitude' => $cluster['lng'],
            'intensity' => $intensity,
            'crowd_level' => $dominantLevel,
            'report_count' => $cluster['report_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'points' => $points,
        'count' => count($points)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>