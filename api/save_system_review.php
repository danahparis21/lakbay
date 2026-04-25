<?php
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$rating = intval($input['rating'] ?? 0);
$title = trim($input['title'] ?? '');
$comment = trim($input['comment'] ?? '');
$reviewId = $input['review_id'] ?? null;
$isEdit = $input['is_edit'] ?? false;

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Comment is required']);
    exit;
}

// Get user info for the review
$stmt = $pdo->prepare("SELECT name, avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($reviewId && $isEdit) {
    // Update existing review
    $stmt = $pdo->prepare("
        UPDATE system_reviews 
        SET rating = ?, title = ?, comment = ?, status = 'pending', updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$rating, $title, $comment, $reviewId, $userId]);
} else {
    // Insert new review
    $stmt = $pdo->prepare("
        INSERT INTO system_reviews (user_id, user_name, user_avatar, rating, title, comment, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$userId, $user['name'], $user['avatar'], $rating, $title, $comment]);
}

echo json_encode(['success' => true, 'message' => 'Review submitted for approval']);
?>