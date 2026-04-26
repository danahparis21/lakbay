<?php
// api/update_alert.php
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
ini_set('date.timezone', 'Asia/Manila');

header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$alertId = $data['id'];
$action = $data['action'];
$userId = $_SESSION['user_id'] ?? null;

try {
    if ($action === 'acknowledge') {
        $stmt = $pdo->prepare("
            UPDATE alerts 
            SET status = 'acknowledged', acknowledged_at = NOW(), acknowledged_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$userId, $alertId]);
    } else if ($action === 'resolve') {
        $stmt = $pdo->prepare("
            UPDATE alerts 
            SET status = 'resolved', resolved_at = NOW(), resolved_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$userId, $alertId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Alert update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>