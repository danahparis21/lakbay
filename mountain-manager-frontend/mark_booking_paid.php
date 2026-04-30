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
$manager_id = $_SESSION['user_id'];

try {
    // First, get booking details for the notification message
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.booking_number,
            b.user_id,
            b.mountain_id,
            b.hike_date,
            b.number_of_hikers,
            b.total_amount,
            b.guide_id,
            m.name as mountain_name,
            m.registration_fee,
            m.environmental_fee,
            (SELECT COUNT(*) FROM booking_payments WHERE booking_id = b.id) as total_payments,
            (SELECT COUNT(*) FROM booking_payments WHERE booking_id = b.id AND registration_paid = 'paid') as paid_reg_count,
            (SELECT COUNT(*) FROM booking_payments WHERE booking_id = b.id AND environmental_paid = 'paid') as paid_env_count
        FROM bookings b
        INNER JOIN mountains m ON b.mountain_id = m.id
        WHERE b.id = :booking_id
    ");
    $stmt->execute([':booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }
    
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
            WHERE id = :booking_id AND payment_status != 'paid'
        ");
        $stmt2->execute([':booking_id' => $booking_id]);
        
        // Send notification message to the hiker (user) using AES_ENCRYPT
        $hike_date = date('F j, Y', strtotime($booking['hike_date']));
        
        // Calculate total fees paid
        $total_reg_fees = $booking['registration_fee'] * $booking['number_of_hikers'];
        $total_env_fees = $booking['environmental_fee'] * $booking['number_of_hikers'];
        $total_fees_paid = $total_reg_fees + $total_env_fees;
        
        // Create the notification message
        $message_body = "✅ PAYMENT CONFIRMATION\n\n";
        $message_body .= "Your registration and environmental fees for booking #{$booking['booking_number']} have been FULLY PAID.\n\n";
        $message_body .= "🏔️ Mountain: {$booking['mountain_name']}\n";
        $message_body .= "📅 Hike Date: {$hike_date}\n";
        $message_body .= "👥 Number of Hikers: {$booking['number_of_hikers']}\n\n";
        $message_body .= "💰 Fees Paid:\n";
        $message_body .= "• Registration Fee: ₱" . number_format($total_reg_fees, 2) . "\n";
        if ($booking['environmental_fee'] > 0) {
            $message_body .= "• Environmental Fee: ₱" . number_format($total_env_fees, 2) . "\n";
        }
        $message_body .= "• Total Paid: ₱" . number_format($total_fees_paid, 2) . "\n\n";
        $message_body .= "Thank you for completing your payment! 🌄\n";
        $message_body .= "We look forward to seeing you on the trail. 🥾✨";
        
        // Insert as system announcement message with AES_ENCRYPT
        $stmt3 = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement, action_data)
            VALUES 
                (:sender_id, :receiver_id, AES_ENCRYPT(:body, :key), 0, NOW(), 'manager', 'hiker', 1, :action_data)
        ");
        
        // Add action data for potential receipt generation
        $action_data = json_encode([
            'type' => 'payment_completed',
            'booking_id' => $booking_id,
            'booking_number' => $booking['booking_number'],
            'total_paid' => $total_fees_paid,
            'registration_fees' => $total_reg_fees,
            'environmental_fees' => $total_env_fees,
            'number_of_hikers' => $booking['number_of_hikers'],
            'mountain_name' => $booking['mountain_name'],
            'hike_date' => $booking['hike_date'],
            'paid_at' => $current_time,
            'paid_by_manager_id' => $manager_id
        ]);
        
        $stmt3->execute([
            ':sender_id' => $manager_id,
            ':receiver_id' => $booking['user_id'],
            ':body' => $message_body,
            ':key' => MSG_AES_KEY,
            ':action_data' => $action_data
        ]);
        
        // Also send a notification to the guide if assigned
        if ($booking['guide_id']) {
            // Get guide's user_id
            $stmt4 = $pdo->prepare("
                SELECT user_id FROM guides WHERE id = :guide_id
            ");
            $stmt4->execute([':guide_id' => $booking['guide_id']]);
            $guide_user = $stmt4->fetch(PDO::FETCH_ASSOC);
            
            if ($guide_user) {
                $guide_message = "✅ PAYMENT UPDATE\n\n";
                $guide_message .= "All registration and environmental fees for booking #{$booking['booking_number']} have been fully paid.\n\n";
                $guide_message .= "🏔️ Mountain: {$booking['mountain_name']}\n";
                $guide_message .= "📅 Hike Date: {$hike_date}\n";
                $guide_message .= "👥 Hikers: {$booking['number_of_hikers']}\n\n";
                $guide_message .= "Total collected: ₱" . number_format($total_fees_paid, 2) . "\n\n";
                $guide_message .= "The booking is now ready for the scheduled hike. 🏔️";
                
                $stmt5 = $pdo->prepare("
                    INSERT INTO messages 
                        (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
                    VALUES 
                        (:sender_id, :receiver_id, AES_ENCRYPT(:body, :key), 0, NOW(), 'manager', 'guide', 1)
                ");
                $stmt5->execute([
                    ':sender_id' => $manager_id,
                    ':receiver_id' => $guide_user['user_id'],
                    ':body' => $guide_message,
                    ':key' => MSG_AES_KEY
                ]);
            }
        }
    }
    
    echo json_encode([
        'success' => $result,
        'booking_number' => $booking['booking_number'],
        'message' => 'All fees marked as paid. Notification sent to hiker.'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>