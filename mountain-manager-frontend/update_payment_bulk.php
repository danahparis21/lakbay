<?php
// update_payment_bulk.php - Update multiple payment statuses
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_ids = $data['payment_ids'] ?? [];
$fee_type = $data['fee_type'] ?? null;
$status = $data['status'] ?? null;

if (empty($payment_ids) || !$fee_type || !in_array($status, ['unpaid', 'paid', 'waived'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$valid_fee_types = ['registration', 'environmental'];
if (!in_array($fee_type, $valid_fee_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid fee type']);
    exit();
}

$column = $fee_type === 'registration' ? 'registration_paid' : 'environmental_paid';
$paid_at_column = $fee_type === 'registration' ? 'registration_paid_at' : 'environmental_paid_at';
$current_time = date('Y-m-d H:i:s');
$placeholders = implode(',', array_fill(0, count($payment_ids), '?'));

try {
    $stmt = $pdo->prepare("
        UPDATE booking_payments 
        SET $column = ?, 
            $paid_at_column = CASE WHEN ? = 'paid' THEN ? ELSE NULL END,
            updated_at = NOW()
        WHERE id IN ($placeholders)
    ");
    
    $params = array_merge([$status, $status, $current_time], $payment_ids);
    $result = $stmt->execute($params);
    
    echo json_encode(['success' => $result]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>