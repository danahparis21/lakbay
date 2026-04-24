<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if user is a hiker
$isHiker = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'hiker') {
    $isHiker = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hiker') {
    $isHiker = true;
}

if (!$isHiker) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Hiker only']);
    exit;
}

$hikerId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_conversations':
            getConversations($pdo, $hikerId);
            break;
        case 'get_messages':
            $guideId = $_GET['guide_id'] ?? null;
            getMessages($pdo, $hikerId, $guideId);
            break;
        case 'send_message':
            sendMessage($pdo, $hikerId);
            break;
        case 'mark_read':
            markAsRead($pdo, $hikerId);
            break;
        case 'get_announcements':
            getAnnouncements($pdo, $hikerId);
            break;
        case 'send_system_message':
            sendSystemMessageAction($pdo, $hikerId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getConversations($pdo, $hikerId) {
    // CHANGED: Use g.user_id instead of g.id to match the user_id from bookings
    $sql = "
        SELECT DISTINCT
            g.user_id as id,
            u.name,
            'guide' as type,
            COALESCE(
                (SELECT CAST(AES_DECRYPT(m.body, :key) AS CHAR)
                 FROM messages m 
                 WHERE ((m.sender_id = g.user_id AND m.receiver_id = :hikerId)
                    OR (m.sender_id = :hikerId AND m.receiver_id = g.user_id))
                 ORDER BY m.created_at DESC 
                 LIMIT 1),
                'No messages yet'
            ) as last_message,
            (SELECT MAX(m.created_at)
             FROM messages m 
             WHERE ((m.sender_id = g.user_id AND m.receiver_id = :hikerId)
                OR (m.sender_id = :hikerId AND m.receiver_id = g.user_id))
            ) as last_message_time,
            (SELECT COUNT(*)
             FROM messages m 
             WHERE m.sender_id = g.user_id 
                AND m.receiver_id = :hikerId 
                AND (m.is_read = 0 OR m.is_read IS NULL)
            ) as unread_count
        FROM guides g
        INNER JOIN users u ON g.user_id = u.id
        WHERE EXISTS (
            SELECT 1 FROM messages m 
            WHERE (m.sender_id = g.user_id AND m.receiver_id = :hikerId)
               OR (m.sender_id = :hikerId AND m.receiver_id = g.user_id)
        )
        ORDER BY last_message_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':hikerId' => $hikerId,
        ':key' => MSG_AES_KEY
    ]);
    $conversations = $stmt->fetchAll();
    
    // Clean up the data
    foreach ($conversations as &$conv) {
        if (!$conv['last_message'] || $conv['last_message'] === '') {
            $conv['last_message'] = 'No messages yet';
        }
        if (!$conv['name']) {
            $conv['name'] = 'Tour Guide';
        }
        // Convert unread_count to integer
        $conv['unread_count'] = (int)$conv['unread_count'];
    }
    
    echo json_encode(['success' => true, 'conversations' => $conversations]);
}

function getMessages($pdo, $hikerId, $guideId) {
    if (!$guideId) {
        echo json_encode(['success' => false, 'message' => 'Guide ID required']);
        return;
    }
    
    // CHANGED: Using user_id directly, and joining guides on user_id
    $sql = "
        SELECT 
            m.id,
            m.sender_id,
            CASE 
                WHEN m.sender_id = :guideId THEN 'guide'
                WHEN m.sender_id = :hikerId THEN 'hiker'
                ELSE m.sender_role
            END as sender_role,
            m.receiver_id,
            CASE 
                WHEN m.body IS NULL THEN '[Empty message]'
                ELSE CAST(AES_DECRYPT(m.body, :key) AS CHAR)
            END as body,
            m.created_at,
            m.is_read,
            u.name as sender_name
        FROM messages m
        LEFT JOIN guides g ON m.sender_id = g.user_id
        LEFT JOIN users u ON g.user_id = u.id
        WHERE (m.sender_id = :guideId AND m.receiver_id = :hikerId)
           OR (m.sender_id = :hikerId AND m.receiver_id = :guideId)
        ORDER BY m.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':guideId' => $guideId,
        ':hikerId' => $hikerId,
        ':key' => MSG_AES_KEY
    ]);
    
    $messages = $stmt->fetchAll();
    
    // Process messages
    foreach ($messages as &$msg) {
        if (!$msg['body'] || $msg['body'] === '') {
            $msg['body'] = '[Encrypted message]';
        }
        if (!$msg['sender_name']) {
            $msg['sender_name'] = $msg['sender_role'] === 'guide' ? 'Guide' : 'You';
        }
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function sendMessage($pdo, $hikerId) {
    $guideId = $_POST['guide_id'] ?? null;
    $body = $_POST['body'] ?? '';
    
    if (!$guideId || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Set Philippine Timezone
    date_default_timezone_set('Asia/Manila');
    $currentTime = date('Y-m-d H:i:s');
    
    // CHANGED: Verify guide exists by user_id, not guides.id
    $checkStmt = $pdo->prepare("SELECT user_id FROM guides WHERE user_id = ?");
    $checkStmt->execute([$guideId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid guide ID']);
        return;
    }
    
    $sql = "
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at)
        VALUES (:senderId, :receiverId, AES_ENCRYPT(:body, :key), 'hiker', 'guide', :createdAt)
    ";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        ':senderId' => $hikerId,
        ':receiverId' => $guideId,
        ':body' => $body,
        ':key' => MSG_AES_KEY,
        ':createdAt' => $currentTime
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Message sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function sendSystemMessageAction($pdo, $hikerId) {
    $guideId = $_POST['guide_id'] ?? null;
    $message = $_POST['message'] ?? '';
    
    if (!$guideId || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Set Philippine Timezone
    date_default_timezone_set('Asia/Manila');
    $currentTime = date('Y-m-d H:i:s');
    
    // CHANGED: Verify guide exists by user_id
    $checkStmt = $pdo->prepare("SELECT user_id FROM guides WHERE user_id = ?");
    $checkStmt->execute([$guideId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid guide ID']);
        return;
    }
    
    $sql = "
        INSERT INTO messages (sender_id, receiver_id, body, sender_role, receiver_role, created_at)
        VALUES (:senderId, :receiverId, AES_ENCRYPT(:body, :key), 'hiker', 'guide', :createdAt)
    ";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        ':senderId' => $hikerId,
        ':receiverId' => $guideId,
        ':body' => $message,
        ':key' => MSG_AES_KEY,
        ':createdAt' => $currentTime
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'System message sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function markAsRead($pdo, $hikerId) {
    $guideId = $_POST['guide_id'] ?? null;
    
    if ($guideId) {
        $sql = "
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = :guideId 
            AND receiver_id = :hikerId 
            AND (is_read = 0 OR is_read IS NULL)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':guideId' => $guideId, ':hikerId' => $hikerId]);
    }
    
    echo json_encode(['success' => true]);
}

function getAnnouncements($pdo, $hikerId) {
    $sql = "
        SELECT 
            b.id,
            b.message as body,
            b.created_at,
            0 as is_read
        FROM broadcasts b
        WHERE b.recipient_role IN ('all_hikers', 'all_guides')
        ORDER BY b.created_at DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'announcements' => $announcements]);
}
?>