<?php
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, rating, title, comment, status, created_at 
    FROM system_reviews 
    WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId]);
$review = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'review' => $review]);
?>