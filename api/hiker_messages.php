<?php
// api/hiker_messages.php
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

$isHiker = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'hiker') $isHiker = true;
elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hiker') $isHiker = true;

if (!$isHiker) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Hiker only']);
    exit;
}

$hikerId = $_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_conversations':       getConversations($pdo, $hikerId);                break;
        case 'get_messages':            getMessages($pdo, $hikerId, $_GET['user_id'] ?? $_GET['guide_id'] ?? null); break;
        case 'send_message':            sendMessage($pdo, $hikerId);                     break;
        case 'mark_read':               markAsRead($pdo, $hikerId);                      break;
        case 'get_announcements':       getAnnouncements($pdo, $hikerId);                break;
        case 'send_system_message':     sendSystemMessageAction($pdo, $hikerId);         break;
        case 'approve_join_request':    approveJoinRequest($pdo, $hikerId);              break;
        case 'deny_join_request':       denyJoinRequest($pdo, $hikerId);        
        case 'send_booking_request':    sendBookingRequest($pdo, $hikerId);    break;  
        case 'approve_booking_request':    approveBookingRequest($pdo, $hikerId);    break;
case 'deny_booking_request':       denyBookingRequest($pdo, $hikerId);       break;
case 'submit_payment_proof': submitPaymentProof($pdo, $hikerId); break;
        default: echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/* ──────────────────────────────────────────────
   GET CONVERSATIONS
────────────────────────────────────────────── */
function getConversations($pdo, $hikerId) {
    $pdo->exec("SET sql_mode = ''");

    $sql = "
        SELECT DISTINCT
            CASE 
                WHEN m.sender_id = :hikerId THEN m.receiver_id
                ELSE m.sender_id
            END as id,
            u.name,
            u.role,
            COALESCE(
                (SELECT CAST(AES_DECRYPT(m2.body, :key) AS CHAR)
                 FROM messages m2
                 WHERE (m2.sender_id = :hikerId AND m2.receiver_id = u.id)
                    OR (m2.sender_id = u.id AND m2.receiver_id = :hikerId)
                 ORDER BY m2.created_at DESC LIMIT 1),
                'No messages yet'
            ) as last_message,
            (SELECT MAX(m2.created_at)
             FROM messages m2
             WHERE (m2.sender_id = :hikerId AND m2.receiver_id = u.id)
                OR (m2.sender_id = u.id AND m2.receiver_id = :hikerId)
            ) as last_message_time,
            (SELECT COUNT(*)
             FROM messages m2
             WHERE m2.sender_id = u.id 
               AND m2.receiver_id = :hikerId
               AND (m2.is_read = 0 OR m2.is_read IS NULL)
            ) as unread_count
        FROM messages m
        INNER JOIN users u ON (u.id = m.sender_id OR u.id = m.receiver_id)
        WHERE (m.sender_id = :hikerId OR m.receiver_id = :hikerId)
          AND u.id != :hikerId
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hikerId' => $hikerId, ':key' => MSG_AES_KEY]);
    $conversations = $stmt->fetchAll();

    // Format the conversations
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
        $conv['display_role'] = $conv['role'] === 'guide' ? 'Tour Guide' : 'Fellow Hiker';
        $conv['unread_count'] = (int)$conv['unread_count'];
    }

    // Sort by last_message_time
    usort($conversations, function($a, $b) {
        return strtotime($b['last_message_time'] ?? '2000-01-01') - strtotime($a['last_message_time'] ?? '2000-01-01');
    });

    echo json_encode(['success' => true, 'conversations' => $conversations]);
}
function sendBookingRequest($pdo, $hikerId) {
    $guideId = $_POST['guide_id'] ?? null;
    $body = $_POST['body'] ?? '';
    $actionData = $_POST['action_data'] ?? '';
    
    if (!$guideId || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    date_default_timezone_set('Asia/Manila');
    
    // Verify guide exists
    $check = $pdo->prepare("SELECT user_id FROM guides WHERE id = ?");
    $check->execute([$guideId]);
    $guide = $check->fetch();
    
    if (!$guide) {
        echo json_encode(['success' => false, 'message' => 'Invalid guide ID']);
        return;
    }
    
    $guideUserId = $guide['user_id'];
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, action_data)
        VALUES (:sid, :rid, AES_ENCRYPT(:body, :key), 'hiker', 'guide', :now, :actionData)
    ");
    $ok = $stmt->execute([
        ':sid' => $hikerId,
        ':rid' => $guideUserId,
        ':body' => $body,
        ':key' => MSG_AES_KEY,
        ':now' => date('Y-m-d H:i:s'),
        ':actionData' => $actionData
    ]);
    
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Booking request sent to guide' : 'Failed to send request']);
}

function approveBookingRequest($pdo, $hikerId) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $hikerUserId = (int)($_POST['hiker_user_id'] ?? 0);
    
    // Verify this user is the guide for this booking
    $stmt = $pdo->prepare("
        SELECT b.id, g.user_id as guide_user_id 
        FROM bookings b 
        JOIN guides g ON b.guide_id = g.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    if (!$booking || $booking['guide_user_id'] != $hikerId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    // Update booking status to confirmed
    $pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ?")->execute([$bookingId]);
    
    // Update action_data in message
    $msgStmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $msgStmt->execute([$requestId]);
    $msgRow = $msgStmt->fetch();
    if ($msgRow) {
        $ad = json_decode($msgRow['action_data'], true) ?: [];
        $ad['status'] = 'approved';
        $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?")->execute([json_encode($ad), $requestId]);
    }
    
    // Notify hiker of approval
    $approvalMessage = "✅ **Booking Approved!**\n\nYour booking request has been approved by the guide.\n\nYour hike is now confirmed! 🏔️";
    $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), 1)
    ")->execute([$hikerId, $hikerUserId, $approvalMessage, MSG_AES_KEY]);
    
    echo json_encode(['success' => true, 'message' => 'Booking approved!']);
}

function denyBookingRequest($pdo, $hikerId) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $hikerUserId = (int)($_POST['hiker_user_id'] ?? 0);
    
    // Verify this user is the guide for this booking
    $stmt = $pdo->prepare("
        SELECT b.id, g.user_id as guide_user_id 
        FROM bookings b 
        JOIN guides g ON b.guide_id = g.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    if (!$booking || $booking['guide_user_id'] != $hikerId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    // Update booking status to cancelled
    $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?")->execute([$bookingId]);
    
    // Update action_data in message
    $msgStmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $msgStmt->execute([$requestId]);
    $msgRow = $msgStmt->fetch();
    if ($msgRow) {
        $ad = json_decode($msgRow['action_data'], true) ?: [];
        $ad['status'] = 'denied';
        $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?")->execute([json_encode($ad), $requestId]);
    }
    
    // Notify hiker of denial
    $denialMessage = "❌ **Booking Denied**\n\nSorry, your booking request has been declined by the guide.\n\nPlease try booking another date or guide. 🏔️";
    $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), 1)
    ")->execute([$hikerId, $hikerUserId, $denialMessage, MSG_AES_KEY]);
    
    echo json_encode(['success' => true, 'message' => 'Booking denied']);
}
/* ──────────────────────────────────────────────
   GET MESSAGES
────────────────────────────────────────────── */
function getMessages($pdo, $hikerId, $otherUserId) {
    debug_log('=== GET MESSAGES CALLED ===');
    debug_log('otherUserId param: ' . $otherUserId);
    debug_log('hikerId: ' . $hikerId);
    
    if (!$otherUserId) { 
        debug_log('ERROR: No user ID provided');
        echo json_encode(['success'=>false,'message'=>'User ID required']); 
        return; 
    }

    // First, verify the other user exists
    $userCheck = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $userCheck->execute([$otherUserId]);
    $otherUser = $userCheck->fetch();
    
    if (!$otherUser) {
        echo json_encode(['success'=>false,'message'=>'User not found']);
        return;
    }

    // Get messages between the two users (using user IDs directly)
    $sql = "
        SELECT
            m.id,
            m.sender_id,
            m.receiver_id,
            CASE
                WHEN m.sender_id = :otherUserId THEN (SELECT role FROM users WHERE id = :otherUserId)
                WHEN m.sender_id = :hikerId     THEN 'hiker'
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
        WHERE (m.sender_id = :otherUserId AND m.receiver_id = :hikerId)
           OR (m.sender_id = :hikerId     AND m.receiver_id = :otherUserId)
        ORDER BY m.created_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':otherUserId'=>$otherUserId, ':hikerId'=>$hikerId, ':key'=>MSG_AES_KEY]);
    $messages = $stmt->fetchAll();
    // Now fetch nudges from booking_nudges table for bookings with this guide
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
    WHERE (bn.user_id = :hikerId AND g.user_id = :otherUserId)
       OR (bn.user_id = :otherUserId AND g.user_id = :hikerId)
    ORDER BY bn.created_at ASC
    ";
    
    $stmt2 = $pdo->prepare($nudgeSql);
    $stmt2->execute([':hikerId' => $hikerId, ':otherUserId' => $otherUserId]);
    $nudges = $stmt2->fetchAll();
    
    // Convert nudges to message format
    foreach ($nudges as $nudge) {
        $isMine = ($nudge['sender_id'] == $hikerId);
        
        $messages[] = [
            'id' => 'nudge_' . $nudge['nudge_id'],
            'sender_id' => $nudge['sender_id'],
            'receiver_id' => ($isMine ? $otherUserId : $hikerId),
            'sender_role' => 'hiker',
            'body' => '',
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
    
    foreach ($messages as &$msg) {
        if (!$msg['body']) $msg['body'] = '[Empty message]';
        if (!$msg['sender_name']) $msg['sender_name'] = $msg['sender_role'] === 'guide' ? 'Guide' : 'Hiker';
    }

    // SINGLE echo at the end
    echo json_encode(['success'=>true,'messages'=>$messages]);
}
/* ──────────────────────────────────────────────
   SEND MESSAGE
────────────────────────────────────────────── */
function sendMessage($pdo, $hikerId) {
    // Debug: Log all POST data received
    debug_log('=== SEND MESSAGE CALLED ===');
    debug_log('POST data: ' . print_r($_POST, true));
    debug_log('hikerId from session: ' . $hikerId);
    
    $recipientId = $_POST['recipient_id'] ?? $_POST['guide_id'] ?? null;
    $body = $_POST['body'] ?? '';
    
    debug_log('Parsed recipient_id: ' . $recipientId);
    debug_log('Body length: ' . strlen($body));
    
    if (!$recipientId || empty($body)) { 
        debug_log('ERROR: Missing recipient ID or body');
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); 
        return; 
    }

    date_default_timezone_set('Asia/Manila');

    // Get the recipient's role from users table
    $check = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $check->execute([$recipientId]);
    $recipient = $check->fetch();
    
    if (!$recipient) { 
        debug_log('ERROR: User not found: ' . $recipientId);
        echo json_encode(['success'=>false,'message'=>'User not found: ' . $recipientId]); 
        return; 
    }
    
    $receiverRole = $recipient['role'];
    
    // Insert message - no guide verification needed!
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at)
        VALUES (:sid, :rid, AES_ENCRYPT(:body, :key), 'hiker', :receiverRole, :now)");
    $ok = $stmt->execute([
        ':sid' => $hikerId,
        ':rid' => $recipientId,
        ':body' => $body,
        ':key' => MSG_AES_KEY,
        ':receiverRole' => $receiverRole,
        ':now' => date('Y-m-d H:i:s')
    ]);

    debug_log('Insert result: ' . ($ok ? 'SUCCESS' : 'FAILED'));
    debug_log('=====================================');

    echo json_encode(['success'=>$ok,'message'=>$ok?'Message sent':'Failed to send message']);
}
/* ──────────────────────────────────────────────
   SEND SYSTEM MESSAGE
────────────────────────────────────────────── */
function sendSystemMessageAction($pdo, $hikerId) {
    $guideId = $_POST['guide_id'] ?? null;
    $message = $_POST['message']  ?? '';
    if (!$guideId || empty($message)) { echo json_encode(['success'=>false,'message'=>'Missing required fields']); return; }

    date_default_timezone_set('Asia/Manila');

    $check = $pdo->prepare("SELECT user_id FROM guides WHERE user_id = ?");
    $check->execute([$guideId]);
    if (!$check->fetch()) { echo json_encode(['success'=>false,'message'=>'Invalid guide ID']); return; }

    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at)
        VALUES (:sid, :rid, AES_ENCRYPT(:body, :key), 'hiker', 'guide', :now)");
    $ok = $stmt->execute([':sid'=>$hikerId,':rid'=>$guideId,':body'=>$message,':key'=>MSG_AES_KEY,':now'=>date('Y-m-d H:i:s')]);

    echo json_encode(['success'=>$ok,'message'=>$ok?'System message sent':'Failed to send message']);
}

/* ──────────────────────────────────────────────
   MARK AS READ
────────────────────────────────────────────── */
function markAsRead($pdo, $hikerId) {
    $recipientId = $_POST['recipient_id'] ?? $_POST['guide_id'] ?? null;
    $recipientType = $_POST['recipient_type'] ?? 'guide';
    
    if ($recipientId) {
        $stmt = $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=:sid AND receiver_id=:hid AND (is_read=0 OR is_read IS NULL)");
        $stmt->execute([':sid'=>$recipientId, ':hid'=>$hikerId]);
    }
    echo json_encode(['success'=>true]);
}

/* ──────────────────────────────────────────────
   GET ANNOUNCEMENTS
────────────────────────────────────────────── */
function getAnnouncements($pdo, $hikerId) {
    $stmt = $pdo->prepare("
        SELECT id, message as body, created_at, 0 as is_read
        FROM broadcasts
        WHERE recipient_role IN ('all_hikers','all_guides')
        ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    echo json_encode(['success'=>true,'announcements'=>$stmt->fetchAll()]);
}

/* ──────────────────────────────────────────────
   APPROVE JOIN REQUEST
   KEY FIX: update action_data status in the message row so the
   card re-renders with a badge instead of buttons after reload.
────────────────────────────────────────────── */
function approveJoinRequest($pdo, $hikerId) {
    $requestId       = (int)($_POST['request_id']       ?? 0);
    $bookingId       = (int)($_POST['booking_id']       ?? 0);
    $requesterName   =       $_POST['requester_name']   ?? '';
    $requesterUserId = (int)($_POST['requester_user_id'] ?? 0);

    // Verify ownership - check if current user owns this booking
    $stmt = $pdo->prepare("SELECT user_id, booking_number FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    if (!$booking || $booking['user_id'] != $hikerId) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized - You are not the booking owner']);
        return;
    }

    // Update join_requests table
    $stmt = $pdo->prepare("UPDATE join_requests SET status = 'approved', updated_at = NOW() WHERE booking_id = ? AND requester_user_id = ?");
    $stmt->execute([$bookingId, $requesterUserId]);
    
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("UPDATE join_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
    }

    // Add to booking_hikers if not already there
$stmt = $pdo->prepare("SELECT id FROM booking_hikers WHERE booking_id = ? AND hiker_name = ?");
$stmt->execute([$bookingId, $requesterName]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO booking_hikers (booking_id, hiker_name) VALUES (?, ?)");
    $stmt->execute([$bookingId, $requesterName]);
    $newHikerId = $pdo->lastInsertId();
    
    // Get mountain fees for this booking
    $feeStmt = $pdo->prepare("
        SELECT m.registration_fee, m.environmental_fee 
        FROM mountains m
        JOIN bookings b ON b.mountain_id = m.id
        WHERE b.id = ?
    ");
    $feeStmt->execute([$bookingId]);
    $mountain = $feeStmt->fetch(PDO::FETCH_ASSOC);
    
    $regFee = $mountain['registration_fee'] ?? 150;
    $envFee = $mountain['environmental_fee'] ?? 120;
    
    // ✅ NEW STRUCTURE: ONE row per hiker with both fees
    $payStmt = $pdo->prepare("
        INSERT INTO booking_payments (
            booking_id, source_type, source_id, 
            registration_amount, registration_paid,
            environmental_amount, environmental_paid
        ) VALUES (?, 'booking_hiker', ?, ?, 'unpaid', ?, 'unpaid')
    ");
    $payStmt->execute([$bookingId, $newHikerId, $regFee, $envFee]);
    
    // Update number of hikers
    $stmt = $pdo->prepare("UPDATE bookings SET number_of_hikers = number_of_hikers + 1 WHERE id = ?");
    $stmt->execute([$bookingId]);
}

    // Update action_data in the message
    $msgStmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $msgStmt->execute([$requestId]);
    $msgRow = $msgStmt->fetch();
    if ($msgRow) {
        $ad = json_decode($msgRow['action_data'], true) ?: [];
        $ad['status'] = 'approved';
        $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?")->execute([json_encode($ad), $requestId]);
    }

    // Notify requester
    date_default_timezone_set('Asia/Manila');
    $body = "✅ Your join request has been APPROVED!\n\nYou can now see the hike in your bookings list.\n\nHappy hiking! 🏔️";
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at) VALUES (?, ?, AES_ENCRYPT(?, ?), 'hiker', 'hiker', NOW())");
    $stmt->execute([$hikerId, $requesterUserId, $body, MSG_AES_KEY]);

    echo json_encode(['success'=>true,'message'=>'Join request approved! The hiker has been added.']);
}

/* ──────────────────────────────────────────────
   DENY JOIN REQUEST
   KEY FIX: same — update action_data status to denied.
────────────────────────────────────────────── */
function denyJoinRequest($pdo, $hikerId) {
    $requestId       = (int)($_POST['request_id']       ?? 0);
    $bookingId       = (int)($_POST['booking_id']       ?? 0);
    $requesterName   =       $_POST['requester_name']   ?? '';
    $requesterUserId = (int)($_POST['requester_user_id'] ?? 0);

    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    if (!$booking || $booking['user_id'] != $hikerId) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized - You are not the booking owner']);
        return;
    }

    // Update join_requests table
    $stmt = $pdo->prepare("UPDATE join_requests SET status = 'denied', updated_at = NOW() WHERE booking_id = ? AND requester_user_id = ?");
    $stmt->execute([$bookingId, $requesterUserId]);
    
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("UPDATE join_requests SET status = 'denied', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
    }

    // Update action_data in the message
    $msgStmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $msgStmt->execute([$requestId]);
    $msgRow = $msgStmt->fetch();
    if ($msgRow) {
        $ad = json_decode($msgRow['action_data'], true) ?: [];
        $ad['status'] = 'denied';
        $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?")->execute([json_encode($ad), $requestId]);
    }

    // Notify requester
    date_default_timezone_set('Asia/Manila');
    $body = "❌ Your join request has been DENIED.\n\nThe organizer has decided not to add you to this hike.\n\nYou can try joining other hikes instead. 🏔️";
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at) VALUES (?, ?, AES_ENCRYPT(?, ?), 'hiker', 'hiker', NOW())");
    $stmt->execute([$hikerId, $requesterUserId, $body, MSG_AES_KEY]);

    echo json_encode(['success'=>true,'message'=>'Join request denied.']);
}

function submitPaymentProof($pdo, $hikerId) {
    $bookingNumber = $_POST['booking_number'] ?? '';
    $messageId = $_POST['message_id'] ?? 0;
    $referenceNumber = $_POST['reference_number'] ?? '';
    $proofImageUrl = $_POST['proof_image_url'] ?? '';
    
    if (!$bookingNumber || !$referenceNumber || !$proofImageUrl) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Get booking details
    $stmt = $pdo->prepare("SELECT id, guide_id, user_id FROM bookings WHERE booking_number = ?");
    $stmt->execute([$bookingNumber]);
    $booking = $stmt->fetch();
    
    if (!$booking || $booking['user_id'] != $hikerId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    // Get guide's user_id
    $stmt = $pdo->prepare("SELECT user_id FROM guides WHERE id = ?");
    $stmt->execute([$booking['guide_id']]);
    $guide = $stmt->fetch();
    $guideUserId = $guide['user_id'];
    
    // Update booking downpayment status
    $stmt = $pdo->prepare("UPDATE bookings SET downpayment_status = 'pending_approval' WHERE id = ?");
    $stmt->execute([$booking['id']]);
    
    // Update the action_data in the original message
    $stmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    if ($msg) {
        $ad = json_decode($msg['action_data'], true) ?: [];
        $ad['payment_status'] = 'pending_approval';
        $ad['payment_reference'] = $referenceNumber;
        $ad['proof_image_url'] = $proofImageUrl;
        $stmt = $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?");
        $stmt->execute([json_encode($ad), $messageId]);
    }
    
    // Send message to guide with proof
    $proofMessage = "💵 **PAYMENT PROOF SUBMITTED**\n\n";
    $proofMessage .= "Hiker has paid the downpayment for booking #{$bookingNumber}.\n\n";
    $proofMessage .= "📝 Reference Number: {$referenceNumber}\n";
    $proofMessage .= "🖼️ Proof: {$proofImageUrl}\n\n";
    $proofMessage .= "Please verify and confirm the payment.";
    
    date_default_timezone_set('Asia/Manila');
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'hiker', 'guide', NOW())
    ");
    $stmt->execute([$hikerId, $guideUserId, $proofMessage, MSG_AES_KEY]);
    
    echo json_encode(['success' => true, 'message' => 'Payment proof submitted. Guide will verify.']);
}
?>