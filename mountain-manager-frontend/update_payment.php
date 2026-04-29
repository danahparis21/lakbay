<?php
// update_payment.php - Update single payment status
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_id = $data['payment_id'] ?? null;
$fee_type = $data['fee_type'] ?? null;
$status = $data['status'] ?? null;

$valid_fee_types = ['registration', 'environmental'];
$valid_statuses = ['unpaid', 'paid', 'waived'];

if (!$payment_id || !$fee_type || !in_array($fee_type, $valid_fee_types) || !in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Determine which column to update
$column = $fee_type === 'registration' ? 'registration_paid' : 'environmental_paid';
$paid_at_column = $fee_type === 'registration' ? 'registration_paid_at' : 'environmental_paid_at';
$current_time = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("
        UPDATE booking_payments 
        SET $column = :status, 
            $paid_at_column = CASE WHEN :status = 'paid' THEN :current_time ELSE NULL END,
            updated_at = NOW()
        WHERE id = :payment_id
    ");
    $result = $stmt->execute([
        ':status' => $status,
        ':current_time' => $current_time,
        ':payment_id' => $payment_id
    ]);
    
    echo json_encode(['success' => $result]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>