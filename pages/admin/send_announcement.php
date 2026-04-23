<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$message = $_POST['message'] ?? '';

// Get count of active guides
$stmt = $pdo->query("SELECT COUNT(*) FROM guides WHERE is_available = 1");
$guideCount = $stmt->fetchColumn();

// Save announcement
$stmt = $pdo->prepare("INSERT INTO announcements (message, recipient_count) VALUES (?, ?)");
$stmt->execute([$message, $guideCount]);

echo json_encode(['success' => true]);
?>