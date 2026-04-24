<?php
/**
 * process_message.php
 * AJAX endpoint for the LAKBAY messaging feature.
 *
 * Actions (POST/GET):
 *   fetch     – GET  ?action=fetch&guide_id=X   → returns decrypted messages
 *   send      – POST action=send, guide_id, body → encrypts + inserts
 *   mark_read – POST action=mark_read, guide_id  → marks messages as read
 *
 * Encryption: MySQL AES_ENCRYPT / AES_DECRYPT (AES-128-ECB by default in MySQL 5.7,
 * AES-256 available via block_encryption_mode='aes-256-cbc').
 * The key is NEVER stored in the DB — it lives only in PHP/server config.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ─── Auth guard ───────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/db.php';

// ─── AES Key (same as messaging-modal.php) ─────────────────
if (!defined('MSG_AES_KEY')) {
    $envKey = getenv('LAKBAY_MSG_KEY');
    define('MSG_AES_KEY', $envKey ?: 'change-this-to-a-32-char-secret!!');
}

$currentUserId = (int) $_SESSION['user_id'];
$action        = $_REQUEST['action'] ?? '';

// ─── Set AES-256-CBC mode (MySQL 5.7.4+) ──────────────────
// Uncomment if your MySQL supports block_encryption_mode:
// $pdo->exec("SET block_encryption_mode = 'aes-256-cbc'");

try {
    switch ($action) {

        // ──────────────────────────────────────────────────
        // FETCH: load full thread between admin and guide
        // ──────────────────────────────────────────────────
        case 'fetch':
            $guideUserId = getGuideUserId($pdo, $_GET['guide_id'] ?? 0);
            if (!$guideUserId) {
                echo json_encode(['success' => false, 'message' => 'Guide not found']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    CAST(AES_DECRYPT(m.body, :key) AS CHAR) AS body,
                    m.is_read,
                    m.created_at
                FROM messages m
                WHERE
                    (m.sender_id = :me AND m.receiver_id = :guide)
                    OR
                    (m.sender_id = :guide2 AND m.receiver_id = :me2)
                ORDER BY m.created_at ASC
                LIMIT 200
            ");
            $stmt->execute([
                ':key'    => MSG_AES_KEY,
                ':me'     => $currentUserId,
                ':guide'  => $guideUserId,
                ':guide2' => $guideUserId,
                ':me2'    => $currentUserId,
            ]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Null body means wrong key or corrupted data — surface it clearly
            foreach ($messages as &$msg) {
                if ($msg['body'] === null) {
                    $msg['body'] = '[decryption error]';
                }
            }
            unset($msg);

            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        // ──────────────────────────────────────────────────
        // SEND: encrypt and insert a new message
        // ──────────────────────────────────────────────────
        case 'send':
            $guideUserId = getGuideUserId($pdo, $_POST['guide_id'] ?? 0);
            $body        = trim($_POST['body'] ?? '');

            if (!$guideUserId) {
                echo json_encode(['success' => false, 'message' => 'Guide not found']);
                exit;
            }
            if ($body === '') {
                echo json_encode(['success' => false, 'message' => 'Message body is empty']);
                exit;
            }
            if (mb_strlen($body) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Message too long (max 2000 chars)']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, body, created_at)
                VALUES (:sender, :receiver, AES_ENCRYPT(:body, :key), NOW())
            ");
            $stmt->execute([
                ':sender'   => $currentUserId,
                ':receiver' => $guideUserId,
                ':body'     => $body,
                ':key'      => MSG_AES_KEY,
            ]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        // ──────────────────────────────────────────────────
        // MARK_READ: mark guide→admin messages as read
        // ──────────────────────────────────────────────────
        case 'mark_read':
            $guideUserId = getGuideUserId($pdo, $_POST['guide_id'] ?? 0);
            if (!$guideUserId) {
                echo json_encode(['success' => false, 'message' => 'Guide not found']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE messages
                SET is_read = 1
                WHERE sender_id = :guide AND receiver_id = :me AND is_read = 0
            ");
            $stmt->execute([':guide' => $guideUserId, ':me' => $currentUserId]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (PDOException $e) {
    error_log('process_message.php PDO error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error — check server logs']);
}

// ─── Helper: resolve guide_id (guides.id) → user_id ──────
function getGuideUserId(PDO $pdo, $guideId): int {
    $guideId = (int) $guideId;
    if ($guideId <= 0) return 0;

    $stmt = $pdo->prepare("SELECT user_id FROM guides WHERE id = ? LIMIT 1");
    $stmt->execute([$guideId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['user_id'] : 0;
}