<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = $_POST['message'];
    $senderId = $_SESSION['user_id'];
    $recipientType = $_POST['recipient_type'] ?? 'all_guides';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO broadcasts (message, sender_id, recipient_role) VALUES (?, ?, ?)");
        $stmt->execute([$message, $senderId, $recipientType]);
        
        echo json_encode(['success' => true, 'message' => 'Announcement sent']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>