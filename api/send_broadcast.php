<?php
// api/send_broadcast.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['message']) || empty(trim($data['message']))) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit();
}

$recipient_role = $data['recipient_role'] ?? 'all';

// Validate recipient role
$valid_roles = ['all', 'all_guides', 'all_hikers', 'specific_guide'];
if (!in_array($recipient_role, $valid_roles)) {
    $recipient_role = 'all';
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO broadcasts (message, sender_id, recipient_role, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        trim($data['message']),
        $_SESSION['user_id'],
        $recipient_role
    ]);
    
    $broadcast_id = $pdo->lastInsertId();
    
    // Optional: Notify users via WebSocket or push notification here
    
    echo json_encode(['success' => true, 'broadcast_id' => $broadcast_id]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>