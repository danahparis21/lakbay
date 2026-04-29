<?php
// mark_booking_paid.php - Mark all payments for a booking as paid
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'] ?? null;

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit();
}

$current_time = date('Y-m-d H:i:s');

try {
    // Mark all payments for this booking as paid
    $stmt = $pdo->prepare("
        UPDATE booking_payments 
        SET registration_paid = 'paid',
            registration_paid_at = :current_time,
            environmental_paid = 'paid',
            environmental_paid_at = :current_time,
            updated_at = NOW()
        WHERE booking_id = :booking_id
    ");
    $result = $stmt->execute([
        ':current_time' => $current_time,
        ':booking_id' => $booking_id
    ]);
    
    // Update booking payment status
    if ($result) {
        $stmt2 = $pdo->prepare("
            UPDATE bookings 
            SET payment_status = 'paid',
                updated_at = NOW()
            WHERE id = :booking_id
        ");
        $stmt2->execute([':booking_id' => $booking_id]);
    }
    
    echo json_encode(['success' => $result]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>