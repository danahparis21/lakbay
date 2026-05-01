<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true, 'unread_count' => 0]);
    exit;
}

try {
    require_once __DIR__ . '/../config/db.php';
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0 AND receiver_role = 'hiker'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'] ?? 0;
    
    echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'unread_count' => 0]);
}
?>