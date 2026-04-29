<?php
// api/acknowledge_alert.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['alert_id'])) {
    echo json_encode(['success' => false, 'error' => 'Alert ID required']);
    exit();
}

try {
    // Check if already acknowledged
    $stmt = $pdo->prepare("
        SELECT id FROM alert_acknowledgements 
        WHERE alert_id = ? AND user_id = ?
    ");
    $stmt->execute([$data['alert_id'], $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already acknowledged']);
        exit();
    }
    
    // Insert acknowledgement
    $stmt = $pdo->prepare("
        INSERT INTO alert_acknowledgements (alert_id, user_id, user_name, acknowledged_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $data['alert_id'],
        $_SESSION['user_id'],
        $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Manager'
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>