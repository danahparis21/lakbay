<?php
// api/guide_messages.php
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
        default: echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
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
            (SELECT name FROM users WHERE id = m.sender_id) as sender_name
        FROM messages m
        WHERE (m.sender_id = :hikerUserId AND m.receiver_id = :guideId)
           OR (m.sender_id = :guideId AND m.receiver_id = :hikerUserId)
        ORDER BY m.created_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hikerUserId' => $hikerUserId, ':guideId' => $guideId, ':key' => MSG_AES_KEY]);
    $messages = $stmt->fetchAll();

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
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'active' WHERE id = ? AND guide_id IN (SELECT id FROM guides WHERE user_id = ?)");
    $stmt->execute([$bookingId, $guideId]);
    
    $msgStmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $msgStmt->execute([$requestId]);
    $msgRow = $msgStmt->fetch();
    
    if ($msgRow) {
        $ad = json_decode($msgRow['action_data'], true) ?: [];
        $ad['status'] = 'approved';
        $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?")->execute([json_encode($ad), $requestId]);
    }
    
    $approvalMessage = "✅ Your booking has been ACCEPTED by the guide!\n\nYour hike is confirmed. See you on the trail! 🏔️";
    $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), 1)
    ")->execute([$guideId, $hikerUserId, $approvalMessage, MSG_AES_KEY]);
    
    echo json_encode(['success' => true, 'message' => 'Booking accepted!']);
}

function declineBookingRequest($pdo, $guideId) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $hikerUserId = (int)($_POST['hiker_user_id'] ?? 0);
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND guide_id IN (SELECT id FROM guides WHERE user_id = ?)");
    $stmt->execute([$bookingId, $guideId]);
    
    $msgStmt = $pdo->prepare("SELECT action_data FROM messages WHERE id = ?");
    $msgStmt->execute([$requestId]);
    $msgRow = $msgStmt->fetch();
    
    if ($msgRow) {
        $ad = json_decode($msgRow['action_data'], true) ?: [];
        $ad['status'] = 'declined';
        $pdo->prepare("UPDATE messages SET action_data = ? WHERE id = ?")->execute([json_encode($ad), $requestId]);
    }
    
    $declineMessage = "❌ Your booking request has been DECLINED.\n\nPlease try booking another date or guide. 🏔️";
    $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at, is_system_announcement)
        VALUES (?, ?, AES_ENCRYPT(?, ?), 'guide', 'hiker', NOW(), 1)
    ")->execute([$guideId, $hikerUserId, $declineMessage, MSG_AES_KEY]);
    
    echo json_encode(['success' => true, 'message' => 'Booking declined']);
}
?>