<?php
// api/guide_messages.php
date_default_timezone_set('Asia/Manila');  // ✅ ADD THIS LINE AT THE TOP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$isGuide = false;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'guide') $isGuide = true;

if (!$isGuide) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Guide only']);
    exit;
}

$guideId = $_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_conversations':      getConversations($pdo, $guideId); break;
        case 'get_messages':           getMessages($pdo, $guideId, $_GET['user_id'] ?? null); break;
        case 'send_message':           sendMessage($pdo, $guideId); break;
        case 'mark_read':              markAsRead($pdo, $guideId); break;
        case 'accept_booking_request': acceptBookingRequest($pdo, $guideId); break;
        case 'decline_booking_request': declineBookingRequest($pdo, $guideId); break;
        case 'verify_payment': verifyPayment($pdo, $guideId); break;
        case 'reject_payment': rejectPayment($pdo, $guideId); break;
        case 'get_booking_status': getBookingStatus($pdo, $guideId); break;
        default: echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getBookingStatus($pdo, $guideId) {
    $bookingId = $_GET['booking_id'] ?? 0;
    
    // Get guide's database ID
    $stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
    $stmt->execute([$guideId]);
    $guide = $stmt->fetch();
    
    if (!$guide) {
        echo json_encode(['success' => false, 'message' => 'Guide not found']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT b.status, b.booking_number 
        FROM bookings b 
        WHERE b.id = ? AND b.guide_id = ?
    ");
    $stmt->execute([$bookingId, $guide['id']]);
    $booking = $stmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'status' => $booking ? $booking['status'] : 'not_found',
        'booking_number' => $booking ? $booking['booking_number'] : null
    ]);
}

function getConversations($pdo, $guideId) {
    $sql = "
        SELECT DISTINCT
            u.id,
            u.name,
            u.role,
            COALESCE(
                (SELECT CAST(AES_DECRYPT(m2.body, :key) AS CHAR)
                 FROM messages m2
                 WHERE (m2.sender_id = :guideId AND m2.receiver_id = u.id)
                    OR (m2.sender_id = u.id AND m2.receiver_id = :guideId)
                 ORDER BY m2.created_at DESC LIMIT 1),
                'No messages yet'
            ) as last_message,
            (SELECT MAX(m2.created_at)
             FROM messages m2
             WHERE (m2.sender_id = :guideId AND m2.receiver_id = u.id)
                OR (m2.sender_id = u.id AND m2.receiver_id = :guideId)
            ) as last_message_time,
            (SELECT COUNT(*)
             FROM messages m2
             WHERE m2.sender_id = u.id 
               AND m2.receiver_id = :guideId
               AND (m2.is_read = 0 OR m2.is_read IS NULL)
            ) as unread_count
        FROM messages m
        INNER JOIN users u ON (u.id = m.sender_id OR u.id = m.receiver_id)
        WHERE (m.sender_id = :guideId OR m.receiver_id = :guideId)
          AND u.id != :guideId
          AND u.role = 'hiker'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':guideId' => $guideId, ':key' => MSG_AES_KEY]);
    $conversations = $stmt->fetchAll();

    foreach ($conversations as &$conv) {
        $lm = $conv['last_message'] ?? '';
        if (!$lm || $lm === 'No messages yet') {
            $conv['last_message'] = 'No messages yet';
        } else {
            $conv['last_message'] = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $lm);
            if (strlen($conv['last_message']) > 100) {
                $conv['last_message'] = substr($conv['last_message'], 0, 97) . '...';
            }
        }
        $conv['unread_count'] = (int)$conv['unread_count'];
    }

    usort($conversations, function($a, $b) {
        return strtotime($b['last_message_time'] ?? '2000-01-01') - strtotime($a['last_message_time'] ?? '2000-01-01');
    });

    echo json_encode(['success' => true, 'conversations' => $conversations]);
}

function getMessages($pdo, $guideId, $hikerUserId) {
    if (!$hikerUserId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }

    // Get regular messages with source_type field
    $sql = "
        SELECT
            m.id,
            m.sender_id,
            m.receiver_id,
            CASE
                WHEN m.sender_id = :hikerUserId THEN (SELECT role FROM users WHERE id = :hikerUserId)
                ELSE m.sender_role
            END as sender_role,
            CASE WHEN m.body IS NULL THEN '[Empty message]'
                 ELSE CAST(AES_DECRYPT(m.body, :key) AS CHAR)
            END as body,
            m.created_at,
            m.is_read,
            m.action_data,
            m.is_system_announcement,
            (SELECT name FROM users WHERE id = m.sender_id) as sender_name,
            'message' as source_type
        FROM messages m
        WHERE (m.sender_id = :hikerUserId AND m.receiver_id = :guideId)
           OR (m.sender_id = :guideId AND m.receiver_id = :hikerUserId)
        ORDER BY m.created_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hikerUserId' => $hikerUserId, ':guideId' => $guideId, ':key' => MSG_AES_KEY]);
    $messages = $stmt->fetchAll();

    // ========== ADD NUDGE FETCHING HERE ==========
    // Fetch nudges from booking_nudges table for bookings with this hiker
    $nudgeSql = "
        SELECT 
            bn.id as nudge_id,
            bn.booking_id,
            bn.user_id as sender_id,
            bn.guide_id as guide_id,
            bn.created_at,
            b.mountain_id,
            m.name as mountain_name,
            b.booking_number,
            u.name as sender_name,
            g.user_id as guide_user_id,
            b.hike_date
        FROM booking_nudges bn
        JOIN bookings b ON bn.booking_id = b.id
        JOIN mountains m ON b.mountain_id = m.id
        JOIN users u ON bn.user_id = u.id
        JOIN guides g ON bn.guide_id = g.id
        WHERE (bn.user_id = :hikerUserId AND g.user_id = :guideId)
           OR (bn.user_id = :guideId AND b.user_id = :hikerUserId)
        ORDER BY bn.created_at ASC
    ";
    
    $stmt2 = $pdo->prepare($nudgeSql);
    $stmt2->execute([':hikerUserId' => $hikerUserId, ':guideId' => $guideId]);
    $nudges = $stmt2->fetchAll();
    
    // Convert nudges to message format
    foreach ($nudges as $nudge) {
        $isMine = ($nudge['sender_id'] == $guideId);
        
        // Build a descriptive message for the nudge
        $nudgeMessage = "🔔 **Reminder from " . ($isMine ? "you" : $nudge['sender_name']) . "**\n\n";
        $nudgeMessage .= "Friendly reminder about the hike booking:\n";
        $nudgeMessage .= "📍 Mountain: " . $nudge['mountain_name'] . "\n";
        $nudgeMessage .= "🏷️ Booking #" . $nudge['booking_number'] . "\n";
        if ($nudge['hike_date']) {
            $nudgeMessage .= "📅 Hike Date: " . date('F j, Y', strtotime($nudge['hike_date'])) . "\n";
        }
        $nudgeMessage .= "\nPlease check the booking details and provide updates if any. 👋";
        
        $messages[] = [
            'id' => 'nudge_' . $nudge['nudge_id'],
            'sender_id' => $nudge['sender_id'],
            'receiver_id' => ($isMine ? $hikerUserId : $guideId),
            'sender_role' => ($nudge['sender_id'] == $guideId ? 'guide' : 'hiker'),
            'body' => $nudgeMessage,  // ← Add a meaningful message!
            'created_at' => $nudge['created_at'],
            'is_read' => 0,
            'action_data' => null,
            'sender_name' => $nudge['sender_name'],
            'source_type' => 'nudge',
            'nudge_id' => $nudge['nudge_id'],
            'booking_id' => $nudge['booking_id'],
            'mountain_name' => $nudge['mountain_name'],
            'booking_number' => $nudge['booking_number'],
            'hike_date' => $nudge['hike_date'] ?? null
        ];
    }
    
    // Sort all messages by created_at
    usort($messages, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    // Clean up messages
    foreach ($messages as &$msg) {
        if (!$msg['body']) $msg['body'] = '[Empty message]';
        if (!$msg['sender_name']) $msg['sender_name'] = $msg['sender_role'] === 'guide' ? 'Guide' : 'Hiker';
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
}
function sendMessage($pdo, $guideId) {
    $recipientId = $_POST['recipient_id'] ?? null;
    $body = $_POST['body'] ?? '';
    
    if (!$recipientId || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at)
        VALUES (:sid, :rid, AES_ENCRYPT(:body, :key), 'guide', 'hiker', NOW())
    ");
    $ok = $stmt->execute([
        ':sid' => $guideId,
        ':rid' => $recipientId,
        ':body' => $body,
        ':key' => MSG_AES_KEY
    ]);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Message sent' : 'Failed to send message']);
}

function markAsRead($pdo, $guideId) {
    $senderId = $_POST['sender_id'] ?? null;
    
    if ($senderId) {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = :sid AND receiver_id = :rid AND (is_read = 0 OR is_read IS NULL)");
        $stmt->execute([':sid' => $senderId, ':rid' => $guideId]);
    }
    echo json_encode(['success' => true]);
}

function acceptBookingRequest($pdo, $guideId) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $hikerUserId = (int)($_POST['hiker_user_id'] ?? 0);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get booking details with mountain and guide fee info
        $stmt = $pdo->prepare("
            SELECT b.user_id, b.booking_number, b.total_amount, 
                   b.hike_date, b.hike_type, b.mountain_id,
                   m.name as mountain_name
            FROM bookings b
            JOIN mountains m ON m.id = b.mountain_id
            WHERE b.id = ? AND b.guide_id = (SELECT id FROM guides WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $guideId]);
        $bk = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bk) {
            throw new Exception('Booking not found or permission denied');
        }
        
        // Get the correct guide fee from guide_mountain_rates
        $stmt_fee = $pdo->prepare("
            SELECT CASE WHEN ? = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END as guide_fee
            FROM guide_mountain_rates gm
            WHERE gm.guide_id = (SELECT id FROM guides WHERE user_id = ?) AND gm.mountain_id = ?
            LIMIT 1
        ");
        $stmt_fee->execute([$bk['hike_type'], $guideId, $bk['mountain_id']]);
        $fee_row = $stmt_fee->fetch();
        $guideFee = $fee_row ? $fee_row['guide_fee'] : ($bk['hike_type'] === 'overnight' ? 1500 : 801);
        
        // Calculate downpayment (20% of guide fee, minimum ₱200)
        $downpayment = max(200, round($guideFee * 0.2));
        
        // Set deadline to 5 hours from NOW (Asia/Manila time)
        date_default_timezone_set('Asia/Manila');
        $deadline = date('Y-m-d H:i:s', strtotime('+5 hours'));
        
        // Get guide's name and GCash details
        $stmt_guide = $pdo->prepare("
            SELECT u.name, g.gcash_name, g.gcash_number, g.gcash_qr_code 
            FROM guides g
            JOIN users u ON u.id = g.user_id
            WHERE g.user_id = ?
        ");
        $stmt_guide->execute([$guideId]);
        $guide_info = $stmt_guide->fetch(PDO::FETCH_ASSOC);
        
        $guide_name = $guide_info['name'];
        $gcash_number = $guide_info['gcash_number'] ?? '0999 999 9999';
        $gcash_name = $guide_info['gcash_name'] ?? $guide_name;
        $gcash_qr = !empty($guide_info['gcash_qr_code']) ? '../' . $guide_info['gcash_qr_code'] : '../assets/images/gcash-qr.jpg';
        
        // UPDATE: Set status to 'waiting_payment' instead of 'active'
        $stmt = $pdo->prepare("UPDATE bookings SET 
    status = 'waiting_payment', 
    downpayment_deadline = ?,
    downpayment_amount = ?,
    downpayment_status = 'unpaid',  
    updated_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$deadline, $downpayment, $bookingId]);


        
        // Remaining balance = guide fee minus downpayment (what hiker still owes guide after hike)
        $remainingToGuide = $guideFee - $downpayment;
        $total_amount     = number_format($bk['total_amount'], 2);
        $hike_date        = date('F j, Y', strtotime($bk['hike_date']));
        
        // MESSAGE 1: BOOKING CONFIRMED (System announcement)
        $confirmedMessage  = "🎉 BOOKING CONFIRMED\n";
        $confirmedMessage .= "Booking #{$bk['booking_number']} · {$guide_name}\n\n";
        $confirmedMessage .= "✓ CONFIRMED\n\n";
        $confirmedMessage .= "📅 Hike Date: {$hike_date}\n";
        $confirmedMessage .= "📍 Mountain: " . htmlspecialchars($bk['mountain_name']) . "\n";
        $confirmedMessage .= "💰 Total Amount: ₱{$total_amount}\n";
        $confirmedMessage .= "🏔️ Tour Guide Fee: ₱" . number_format($guideFee, 2) . "\n";
        $confirmedMessage .= "💵 Downpayment Required: ₱" . number_format($downpayment, 2) . "\n";
        $confirmedMessage .= "📦 Remaining Balance (to guide): ₱" . number_format($remainingToGuide, 2) . "\n\n";
        $confirmedMessage .= "⏰ Complete downpayment within 5 hours to secure your booking.\n";
        $confirmedMessage .= "Payment instructions have been sent below. 🏔️";
        
        // Insert confirmation message as system announcement
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', 1)
        ");
        $stmt_msg->execute([$guideId, $bk['user_id'], $confirmedMessage, MSG_AES_KEY]);
        
        // MESSAGE 2: PAYMENT INSTRUCTIONS CARD (Blue card with GCash and countdown)
        $instructionsMessage = "💰 Please complete your downpayment using the details below.";
        
        $actionData = json_encode([
            'type'               => 'payment_instructions',
            'gcash_number'       => $gcash_number,
            'gcash_name'         => $gcash_name,
            'qr_code_url'        => $gcash_qr,
            'downpayment_amount' => number_format($downpayment, 2),
            'remaining_balance'  => number_format($remainingToGuide, 2),
            'booking_number'     => $bk['booking_number'],
            'total_amount'       => $total_amount,
            'deadline'           => $deadline,
            'guide_fee'          => $guideFee,
            'hike_type'          => $bk['hike_type'],
            'payment_status'     => 'unpaid',
        ]);
        
        // Insert payment instructions as action_data message
        $stmt_instructions = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, action_data, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', ?, 0)
        ");
        $stmt_instructions->execute([$guideId, $bk['user_id'], $instructionsMessage, MSG_AES_KEY, $actionData]);
        
        // Update the original booking request message to show approved status
        $stmt_update = $pdo->prepare("
            UPDATE messages 
            SET action_data = JSON_SET(COALESCE(action_data, '{}'), '$.status', 'approved')
            WHERE id = ? AND action_data IS NOT NULL
        ");
        $stmt_update->execute([$requestId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Booking confirmed! Waiting for downpayment.',
            'booking_number' => $bk['booking_number']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function declineBookingRequest($pdo, $guideId) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $hikerUserId = (int)($_POST['hiker_user_id'] ?? 0);
    $cancel_reason = $_POST['reason'] ?? 'Booking request declined by guide.';
    
    try {
        $pdo->beginTransaction();
        
        // Get booking details
        $stmt = $pdo->prepare("
            SELECT user_id, booking_number, mountain_id
            FROM bookings 
            WHERE id = ? AND guide_id = (SELECT id FROM guides WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $guideId]);
        $bk = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bk) {
            throw new Exception('Booking not found or permission denied');
        }
        
        // Get mountain name
        $stmt_mtn = $pdo->prepare("SELECT name FROM mountains WHERE id = ?");
        $stmt_mtn->execute([$bk['mountain_id']]);
        $mountain_name = $stmt_mtn->fetchColumn() ?: 'Unknown Mountain';
        
        // Update booking status to 'cancelled'
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$bookingId]);
        
        // Get guide name
        $stmt_guide = $pdo->prepare("SELECT u.name FROM guides g JOIN users u ON u.id = g.user_id WHERE g.user_id = ?");
        $stmt_guide->execute([$guideId]);
        $guide_name = $stmt_guide->fetchColumn();
        
        // Send cancellation message to hiker with beautiful card-like format
        $msg_body = "❌ **BOOKING CANCELLED**\n\n";
        $msg_body .= "Your booking #{$bk['booking_number']} has been CANCELLED by the guide.\n\n";
        $msg_body .= "📍 Mountain: {$mountain_name}\n";
        $msg_body .= "👤 Guide: {$guide_name}\n\n";
        $msg_body .= "📝 Reason: {$cancel_reason}\n\n";
        $msg_body .= "Please contact support if you have questions or need to rebook.\n\n";
        $msg_body .= "We hope to see you on another adventure soon! 🏔️";
        
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', 1)
        ");
        $stmt_msg->execute([$guideId, $bk['user_id'], $msg_body, MSG_AES_KEY]);
        
        // Update the original booking request message
        $stmt_update = $pdo->prepare("
            UPDATE messages 
            SET action_data = JSON_SET(COALESCE(action_data, '{}'), '$.status', 'denied')
            WHERE id = ? AND action_data IS NOT NULL
        ");
        $stmt_update->execute([$requestId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Booking declined successfully!',
            'booking_number' => $bk['booking_number']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


function verifyPayment($pdo, $guideId) {
    $bookingNumber = $_POST['booking_number'] ?? '';
    $messageId = $_POST['message_id'] ?? 0;
    $downpaymentAmount = $_POST['downpayment_amount'] ?? 0;
    
    $bookingNumber = ltrim($bookingNumber, '#');
    $bookingNumber = trim($bookingNumber);
    $bookingNumber = preg_replace('/[.,;:!?]$/', '', $bookingNumber);
    
    if (!$bookingNumber) {
        echo json_encode(['success' => false, 'message' => 'Missing booking number']);
        return;
    }
    
    // Get booking details including guide fee
    $stmt = $pdo->prepare("
        SELECT b.id, b.guide_id, b.user_id, b.total_amount, b.hike_date, b.hike_type, b.mountain_id,
               COALESCE(
                   (SELECT CASE WHEN b.hike_type = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END
                    FROM guide_mountain_rates gm
                    JOIN guides g2 ON g2.id = gm.guide_id
                    WHERE g2.user_id = ? AND gm.mountain_id = b.mountain_id LIMIT 1),
                   801
               ) as guide_fee
        FROM bookings b
        WHERE b.booking_number = ?
    ");
    $stmt->execute([$guideId, $bookingNumber]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found: ' . $bookingNumber]);
        return;
    }
    
    // Verify guide owns this booking
    $stmt = $pdo->prepare("SELECT id, user_id FROM guides WHERE user_id = ?");
    $stmt->execute([$guideId]);
    $guide = $stmt->fetch();
    
    if (!$guide || $booking['guide_id'] != $guide['id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - You are not the guide for this booking']);
        return;
    }
    
    // Remaining balance = guide fee minus the downpayment already paid (what hiker still owes guide)
    $guideFee         = $booking['guide_fee'];
    $remainingBalance = $guideFee - $downpaymentAmount;
    
    $stmt = $pdo->prepare("UPDATE bookings SET 
        status = 'active', 
        downpayment_status = 'paid', 
        downpayment_amount = ?,
        payment_status = 'partial',
        updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$downpaymentAmount, $booking['id']]);
    
    // Lock the guide - set as unavailable during the hike
    $stmt = $pdo->prepare("UPDATE guides SET 
        is_available = 0,
        currently_on_hike = 1
        WHERE user_id = ?
    ");
    $stmt->execute([$guideId]);
    
    // Notify hiker — downpayment confirmed, hike is active
    $hikeDate       = date('F j, Y', strtotime($booking['hike_date']));
    $confirmMessage  = "✅ DOWNPAYMENT CONFIRMED!\n\n";
    $confirmMessage .= "Your downpayment of ₱" . number_format($downpaymentAmount, 2) . " for booking #{$bookingNumber} has been verified.\n\n";
    $confirmMessage .= "💰 Remaining balance to pay guide after hike: ₱" . number_format($remainingBalance, 2) . "\n\n";
    $confirmMessage .= "🏔️ Your hike on {$hikeDate} is now ACTIVE!\n";
    $confirmMessage .= "You can now start the hike from your bookings page.\n\n";
    $confirmMessage .= "Happy hiking! 🥾✨";
    
    date_default_timezone_set('Asia/Manila');
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), 1)
    ");
    $stmt->execute([$guideId, $booking['user_id'], $confirmMessage, MSG_AES_KEY]);
    
    echo json_encode(['success' => true, 'message' => 'Payment verified! Hike is now active.']);
}

function rejectPayment($pdo, $guideId) {
    $bookingNumber = $_POST['booking_number'] ?? '';
    $messageId = $_POST['message_id'] ?? 0;
    
    // Remove # prefix if present and clean the string
    $bookingNumber = ltrim($bookingNumber, '#');
    $bookingNumber = trim($bookingNumber);
    $bookingNumber = preg_replace('/[.,;:!?]$/', '', $bookingNumber);
    
    if (!$bookingNumber) {
        echo json_encode(['success' => false, 'message' => 'Missing booking number']);
        return;
    }
    
    error_log("rejectPayment - Looking for booking: " . $bookingNumber);
    
    // Get booking details with guide fee from guide_mountain_rates
    $stmt = $pdo->prepare("
        SELECT b.id, b.guide_id, b.user_id, b.total_amount, b.hike_type, b.booking_number, b.mountain_id,
               g.id as guide_db_id, g.user_id as guide_user_id,
               g.gcash_number, g.gcash_name, g.gcash_qr_code,
               COALESCE(
                   (SELECT CASE WHEN b.hike_type = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END
                    FROM guide_mountain_rates gm 
                    WHERE gm.guide_id = g.id AND gm.mountain_id = b.mountain_id LIMIT 1),
                   801
               ) as guide_fee
        FROM bookings b 
        JOIN guides g ON b.guide_id = g.id
        WHERE b.booking_number = ?
    ");
    $stmt->execute([$bookingNumber]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found: ' . $bookingNumber]);
        return;
    }
    
    // Verify guide owns this booking
    if ($booking['guide_user_id'] != $guideId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - You are not the guide for this booking']);
        return;
    }
    
    // Fix: reset to 'unpaid' so hiker can resubmit (not 'rejected' which is not a valid enum value)
    $stmt = $pdo->prepare("UPDATE bookings SET 
        status = 'waiting_payment', 
        downpayment_status = 'unpaid',
        updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$booking['id']]);
    
    // Fix: release the guide — they're available again since payment wasn't confirmed
    $stmt = $pdo->prepare("UPDATE guides SET 
        is_available = 1,
        currently_on_hike = 0
        WHERE user_id = ?
    ");
    $stmt->execute([$guideId]);
    
    // Get the correct guide fee from the query result
    $guideFee         = $booking['guide_fee'];
    $downpayment      = max(200, round($guideFee * 0.2));
    $remainingToGuide = $guideFee - $downpayment;
    
    // Set deadline to 5 hours from NOW (must set timezone BEFORE computing date)
    date_default_timezone_set('Asia/Manila');
    $deadline = date('Y-m-d H:i:s', strtotime('+5 hours'));
    
    // MESSAGE 1: REJECTION NOTIFICATION (Red Card)
    $rejectionMessage  = "❌ PAYMENT REJECTED\n";
    $rejectionMessage .= "Booking #{$booking['booking_number']}\n\n";
    $rejectionMessage .= "Your payment proof was rejected. Please submit a valid proof of payment.\n\n";
    $rejectionMessage .= "💰 Downpayment Required: ₱" . number_format($downpayment, 2) . "\n";
    $rejectionMessage .= "🏔️ Tour Guide Fee: ₱" . number_format($guideFee, 2) . " (20% downpayment)\n\n";
    $rejectionMessage .= "New payment instructions have been sent below. See you on the trail! 🥾✨";
    
    // Send rejection notification as a system message (red card)
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), 1)
    ");
    $stmt->execute([$guideId, $booking['user_id'], $rejectionMessage, MSG_AES_KEY]);
    
    // MESSAGE 2: NEW PAYMENT INSTRUCTIONS CARD (Blue card with GCash details and countdown)
    $instructionsMessage = "💰 Please complete your downpayment using the details below.";
    
    // Create action_data for the payment instructions
    $actionData = json_encode([
        'type'               => 'payment_instructions',
        'gcash_number'       => $booking['gcash_number'],
        'gcash_name'         => $booking['gcash_name'],
        'qr_code_url'        => $booking['gcash_qr_code'] ? '../' . $booking['gcash_qr_code'] : '../assets/images/gcash-qr.jpg',
        'downpayment_amount' => number_format($downpayment, 2),
        'remaining_balance'  => number_format($remainingToGuide, 2),
        'booking_number'     => $booking['booking_number'],
        'total_amount'       => number_format($booking['total_amount'], 2),
        'deadline'           => $deadline,
        'guide_fee'          => $guideFee,
        'hike_type'          => $booking['hike_type'],
        'payment_status'     => 'unpaid',
    ]);
    
    // Send payment instructions as a card message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, action_data, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), ?, 0)
    ");
    $stmt->execute([$guideId, $booking['user_id'], $instructionsMessage, MSG_AES_KEY, $actionData]);
    
    echo json_encode(['success' => true, 'message' => 'Payment rejected. Booking reset to waiting for payment.']);
}
function completeHike($pdo, $guideId) {
    $bookingId = $_POST['booking_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'finished',
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ? AND guide_id = (SELECT id FROM guides WHERE user_id = ?)
    ");
    $stmt->execute([$bookingId, $guideId]);
    
    // Release the guide (make available again, but still waiting for final payment)
    $stmt = $pdo->prepare("UPDATE guides SET 
        currently_on_hike = 0
        WHERE user_id = ?
    ");
    $stmt->execute([$guideId]);
    
    echo json_encode(['success' => true, 'message' => 'Hike marked as finished']);
}

function confirmFinalPayment($pdo, $guideId) {
    $bookingId = $_POST['booking_id'] ?? 0;
    $finalAmount = $_POST['final_amount'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'completed',
            payment_status = 'paid',
            updated_at = NOW()
        WHERE id = ? AND guide_id = (SELECT id FROM guides WHERE user_id = ?)
    ");
    $stmt->execute([$bookingId, $guideId]);
    
    // Release the guide completely - make available for new bookings
    $stmt = $pdo->prepare("UPDATE guides SET 
        is_available = 1
        WHERE user_id = ?
    ");
    $stmt->execute([$guideId]);
    
    echo json_encode(['success' => true, 'message' => 'Final payment confirmed. Booking completed!']);
}

?>