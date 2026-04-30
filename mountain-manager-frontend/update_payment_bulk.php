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

$manager_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');
$column = $fee_type === 'registration' ? 'registration_paid' : 'environmental_paid';
$paid_at_column = $fee_type === 'registration' ? 'registration_paid_at' : 'environmental_paid_at';
$placeholders = implode(',', array_fill(0, count($payment_ids), '?'));

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, get all affected bookings and their details before updating
    $stmt = $pdo->prepare("
        SELECT DISTINCT bp.booking_id, b.user_id, b.booking_number, b.number_of_hikers, b.hike_date, b.guide_id,
               m.name as mountain_name, m.registration_fee, m.environmental_fee,
               (SELECT COUNT(*) FROM booking_payments WHERE booking_id = bp.booking_id) as total_payments,
               (SELECT COUNT(*) FROM booking_payments WHERE booking_id = bp.booking_id AND registration_paid = 'paid') as paid_reg,
               (SELECT COUNT(*) FROM booking_payments WHERE booking_id = bp.booking_id AND environmental_paid = 'paid') as paid_env
        FROM booking_payments bp
        JOIN bookings b ON bp.booking_id = b.id
        JOIN mountains m ON b.mountain_id = m.id
        WHERE bp.id IN ($placeholders)
    ");
    $stmt->execute($payment_ids);
    $affected_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update the payments
    $update_stmt = $pdo->prepare("
        UPDATE booking_payments 
        SET $column = ?, 
            $paid_at_column = CASE WHEN ? = 'paid' THEN ? ELSE NULL END,
            updated_at = NOW()
        WHERE id IN ($placeholders)
    ");
    
    $params = array_merge([$status, $status, $current_time], $payment_ids);
    $result = $update_stmt->execute($params);
    
    // Track which bookings are now fully paid
    $completed_bookings = [];
    
    foreach ($affected_data as $booking_info) {
        $booking_id = $booking_info['booking_id'];
        $has_env = $booking_info['environmental_fee'] > 0;
        $expected_paid = $booking_info['total_payments'] * ($has_env ? 2 : 1);
        
        // Recalculate current paid counts after update
        $check_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN registration_paid = 'paid' THEN 1 ELSE 0 END) as reg_paid,
                SUM(CASE WHEN environmental_paid = 'paid' THEN 1 ELSE 0 END) as env_paid
            FROM booking_payments 
            WHERE booking_id = :booking_id
        ");
        $check_stmt->execute([':booking_id' => $booking_id]);
        $current_counts = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        $actual_paid = $current_counts['reg_paid'] + ($has_env ? $current_counts['env_paid'] : 0);
        
        // If all fees are now paid, update booking status and send notification
        if ($actual_paid == $expected_paid && !in_array($booking_id, $completed_bookings)) {
            $update_booking = $pdo->prepare("
                UPDATE bookings 
                SET payment_status = 'paid',
                    updated_at = NOW()
                WHERE id = :booking_id AND payment_status != 'paid'
            ");
            $update_booking->execute([':booking_id' => $booking_id]);
            
            if ($update_booking->rowCount() > 0) {
                $completed_bookings[] = $booking_id;
                
                // Send encrypted notification to hiker
                $hike_date = date('F j, Y', strtotime($booking_info['hike_date']));
                $total_reg_fees = $booking_info['registration_fee'] * $booking_info['number_of_hikers'];
                $total_env_fees = $booking_info['environmental_fee'] * $booking_info['number_of_hikers'];
                $total_fees_paid = $total_reg_fees + $total_env_fees;
                
                $message_body = "✅ PAYMENT CONFIRMATION\n\n";
                $message_body .= "Your registration and environmental fees for booking #{$booking_info['booking_number']} have been FULLY PAID.\n\n";
                $message_body .= "🏔️ Mountain: {$booking_info['mountain_name']}\n";
                $message_body .= "📅 Hike Date: {$hike_date}\n";
                $message_body .= "👥 Number of Hikers: {$booking_info['number_of_hikers']}\n\n";
                $message_body .= "💰 Total Paid: ₱" . number_format($total_fees_paid, 2) . "\n\n";
                $message_body .= "Thank you for completing your payment! 🌄\n";
                $message_body .= "We look forward to seeing you on the trail. 🥾✨";
                
                $msg_stmt = $pdo->prepare("
                    INSERT INTO messages 
                        (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement, action_data)
                    VALUES 
                        (:sender_id, :receiver_id, AES_ENCRYPT(:body, :key), 0, NOW(), 'manager', 'hiker', 1, :action_data)
                ");
                
                $action_data = json_encode([
                    'type' => 'payment_completed',
                    'booking_id' => $booking_id,
                    'booking_number' => $booking_info['booking_number'],
                    'total_paid' => $total_fees_paid,
                    'paid_by_bulk_action' => true
                ]);
                
                $msg_stmt->execute([
                    ':sender_id' => $manager_id,
                    ':receiver_id' => $booking_info['user_id'],
                    ':body' => $message_body,
                    ':key' => MSG_AES_KEY,
                    ':action_data' => $action_data
                ]);
                
                // Also notify the guide if assigned
                if ($booking_info['guide_id']) {
                    $guide_stmt = $pdo->prepare("
                        SELECT user_id FROM guides WHERE id = :guide_id
                    ");
                    $guide_stmt->execute([':guide_id' => $booking_info['guide_id']]);
                    $guide_user = $guide_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($guide_user) {
                        $guide_message = "✅ PAYMENT UPDATE\n\n";
                        $guide_message .= "All registration and environmental fees for booking #{$booking_info['booking_number']} have been fully paid.\n\n";
                        $guide_message .= "🏔️ Mountain: {$booking_info['mountain_name']}\n";
                        $guide_message .= "📅 Hike Date: {$hike_date}\n";
                        $guide_message .= "👥 Hikers: {$booking_info['number_of_hikers']}\n\n";
                        $guide_message .= "Total collected: ₱" . number_format($total_fees_paid, 2) . "\n\n";
                        $guide_message .= "The booking is now ready for the scheduled hike. 🏔️";
                        
                        $guide_msg_stmt = $pdo->prepare("
                            INSERT INTO messages 
                                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
                            VALUES 
                                (:sender_id, :receiver_id, AES_ENCRYPT(:body, :key), 0, NOW(), 'manager', 'guide', 1)
                        ");
                        $guide_msg_stmt->execute([
                            ':sender_id' => $manager_id,
                            ':receiver_id' => $guide_user['user_id'],
                            ':body' => $guide_message,
                            ':key' => MSG_AES_KEY
                        ]);
                    }
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => $result,
        'updated_count' => count($payment_ids),
        'completed_bookings' => count($completed_bookings),
        'message' => count($completed_bookings) . ' booking(s) fully paid. Notification sent to hiker(s).'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>