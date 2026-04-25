<?php
// api/export_history.php - Export hiking history as story
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];

// Get history similar to profile
$stmt = $pdo->prepare("
    SELECT b.*, m.name as mountain_name 
    FROM bookings b
    JOIN mountains m ON b.mountain_id = m.id
    WHERE b.user_id = ? 
    ORDER BY b.hike_date DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$recentHikes = $stmt->fetchAll();

$totalHikes = count($recentHikes);
$completedHikes = count(array_filter($recentHikes, fn($h) => $h['status'] === 'completed'));

// Generate story
if ($totalHikes == 0) {
    $story = "Your adventure hasn't started yet! Book your first hike to begin your journey.";
} elseif ($totalHikes == 1) {
    $story = "Welcome to the hiking family! Your journey has just begun.";
} elseif ($totalHikes >= 10) {
    $story = "Legendary hiker! You've conquered $totalHikes mountains!";
} else {
    $story = "Great start! You've experienced $totalHikes amazing hikes.";
}

echo json_encode([
    'success' => true,
    'total_hikes' => $totalHikes,
    'completed_hikes' => $completedHikes,
    'badge_count' => 0,
    'story' => $story,
    'recent_hikes' => array_map(fn($h) => [
        'mountain' => $h['mountain_name'],
        'date' => date('M j, Y', strtotime($h['hike_date'])),
        'status' => $h['status']
    ], $recentHikes)
]);
?>