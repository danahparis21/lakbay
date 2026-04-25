<?php
// api/get_user_badges.php - Get user's earned badges
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get badges with proper grouping and sum of times_earned
$stmt = $pdo->prepare("
    SELECT 
        b.id,
        b.name, 
        b.icon, 
        b.description,
        SUM(ub.times_earned) as total_times_earned,
        GROUP_CONCAT(DISTINCT DATE(ub.earned_at) ORDER BY ub.earned_at DESC) as earned_dates
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    GROUP BY b.id, b.name, b.icon, b.description
    ORDER BY MAX(ub.earned_at) DESC
");
$stmt->execute([$userId]);
$badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($badges as $badge) {
    $dates = $badge['earned_dates'] ? explode(',', $badge['earned_dates']) : [];
    $formattedDates = array_map(function($date) {
        return date('M j, Y', strtotime($date));
    }, $dates);
    
    $grouped[] = [
        'id' => $badge['id'],
        'name' => $badge['name'],
        'icon' => $badge['icon'],
        'description' => $badge['description'],
        'count' => (int)$badge['total_times_earned'],
        'dates' => $formattedDates
    ];
}

echo json_encode(['success' => true, 'badges' => $grouped]);
?>