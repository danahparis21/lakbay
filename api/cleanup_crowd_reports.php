<?php
// Run this via cron every hour
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
ini_set('date.timezone', 'Asia/Manila');

try {
    // Delete expired reports
    $stmt = $pdo->prepare("DELETE FROM crowd_reports WHERE expires_at < NOW()");
    $stmt->execute();
    
    // Also clean up old heatmap data (older than 4 hours)
    $stmt = $pdo->prepare("DELETE FROM crowd_heatmap_data WHERE updated_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)");
    $stmt->execute();
    
    echo "Cleaned up " . $stmt->rowCount() . " expired reports\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>