<?php
// api/mark_broadcast_read.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['broadcast_id'])) {
    echo json_encode(['success' => false, 'error' => 'Broadcast ID required']);
    exit();
}

try {
    // Check if already marked as read
    $stmt = $pdo->prepare("
        SELECT id FROM broadcast_read_status 
        WHERE broadcast_id = ? AND user_id = ? AND user_role = ?
    ");
    $stmt->execute([
        $data['broadcast_id'], 
        $_SESSION['user_id'], 
        $_SESSION['user_role'] ?? 'manager'
    ]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already marked as read']);
        exit();
    }
    
    // Mark as read
    $stmt = $pdo->prepare("
        INSERT INTO broadcast_read_status (broadcast_id, user_id, user_role, is_read, read_at)
        VALUES (?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([
        $data['broadcast_id'],
        $_SESSION['user_id'],
        $_SESSION['user_role'] ?? 'manager'
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>