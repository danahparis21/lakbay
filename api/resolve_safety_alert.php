<?php
// api/resolve_safety_alert.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['alert_id'])) {
    echo json_encode(['success' => false, 'error' => 'Alert ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE safety_alerts 
        SET status = 'resolved', resolved_at = NOW(), resolved_by = ?
        WHERE id = ? AND status = 'active'
    ");
    
    $stmt->execute([$_SESSION['user_id'], $data['alert_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Alert not found or already resolved']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>