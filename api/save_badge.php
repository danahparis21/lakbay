<?php
// api/save_badge.php - Save earned badges
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$badgeName = $data['badge_name'] ?? '';
$icon = $data['icon'] ?? '';
$location = $data['location'] ?? '';
$type = $data['type'] ?? '';
$mountainName = $data['mountain_name'] ?? '';
$bookingId = $data['booking_id'] ?? '';

// Find badge ID
$stmt = $pdo->prepare("SELECT id FROM badges WHERE name = ?");
$stmt->execute([$badgeName]);
$badge = $stmt->fetch();

if (!$badge) {
    // Insert new badge if not exists
    $stmt = $pdo->prepare("INSERT INTO badges (name, icon, description, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$badgeName, $icon, "Earned at $location", 'trail']);
    $badgeId = $pdo->lastInsertId();
} else {
    $badgeId = $badge['id'];
}

// Check if already earned today for this hike
$stmt = $pdo->prepare("
    SELECT id, times_earned FROM user_badges 
    WHERE user_id = ? AND badge_id = ? AND DATE(earned_at) = CURDATE() AND mountain_name = ?
");
$stmt->execute([$userId, $badgeId, $mountainName]);
$existing = $stmt->fetch();

if ($existing) {
    // Update count
    $stmt = $pdo->prepare("
        UPDATE user_badges 
        SET times_earned = times_earned + 1, earned_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$existing['id']]);
    echo json_encode(['success' => true, 'is_new' => false, 'times_earned' => $existing['times_earned'] + 1]);
} else {
    // Insert new
    $stmt = $pdo->prepare("
        INSERT INTO user_badges (user_id, badge_id, earned_at, hike_date, mountain_name, times_earned) 
        VALUES (?, ?, NOW(), CURDATE(), ?, 1)
    ");
    $stmt->execute([$userId, $badgeId, $mountainName]);
    echo json_encode(['success' => true, 'is_new' => true, 'times_earned' => 1]);
}
?>