<?php
/**
 * process_guide_update.php
 * AJAX endpoint for editing guide details and deactivating guides.
 *
 * Actions (POST):
 *   update     – updates guides + users tables + guide_mountains
 *   deactivate – sets guides.is_available = 0
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/db.php';

$action  = trim($_POST['action'] ?? '');
$guideId = (int)($_POST['guide_id'] ?? 0);

if ($guideId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid guide ID']);
    exit;
}

try {
    // Resolve user_id from guide_id
    $stmt = $pdo->prepare("SELECT user_id FROM guides WHERE id = ? LIMIT 1");
    $stmt->execute([$guideId]);
    $guideRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guideRow) {
        echo json_encode(['success' => false, 'message' => 'Guide not found']);
        exit;
    }
    $userId = (int)$guideRow['user_id'];

    // ──────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────
    if ($action === 'update') {
        $name           = trim($_POST['name']           ?? '');
        $phone          = trim($_POST['phone']          ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $yearsExpRaw    = $_POST['years_experience'] ?? '';
        $yearsExp       = ($yearsExpRaw !== '' && $yearsExpRaw !== null) ? (float)$yearsExpRaw : null;
        $bio            = trim($_POST['bio']            ?? '');
        $mountains      = $_POST['mountains'] ?? []; // array of mountain IDs

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit;
        }

        $pdo->beginTransaction();

        // Update users table
        $stmt = $pdo->prepare("
            UPDATE users SET name = ?, phone = ? WHERE id = ?
        ");
        $stmt->execute([$name, $phone ?: null, $userId]);

        // Update guides table
        $stmt = $pdo->prepare("
            UPDATE guides
            SET specialization = ?, years_experience = ?, bio = ?
            WHERE id = ?
        ");
        $stmt->execute([$specialization ?: null, $yearsExp, $bio ?: null, $guideId]);

        // Sync guide_mountains — delete old, insert new
        $stmt = $pdo->prepare("DELETE FROM guide_mountains WHERE guide_id = ?");
        $stmt->execute([$guideId]);

        if (!empty($mountains)) {
            $insertStmt = $pdo->prepare("INSERT INTO guide_mountains (guide_id, mountain_id) VALUES (?, ?)");
            foreach ($mountains as $mountainId) {
                $mountainId = (int)$mountainId;
                if ($mountainId > 0) {
                    $insertStmt->execute([$guideId, $mountainId]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Guide updated successfully']);

    // ──────────────────────────────────────────────────
    // DEACTIVATE (soft delete)
    // ──────────────────────────────────────────────────
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE guides SET is_available = 0 WHERE id = ?");
        $stmt->execute([$guideId]);
        echo json_encode(['success' => true, 'message' => 'Guide deactivated successfully']);

    // ──────────────────────────────────────────────────
    // REACTIVATE
    // ──────────────────────────────────────────────────
    } elseif ($action === 'reactivate') {
        $stmt = $pdo->prepare("UPDATE guides SET is_available = 1 WHERE id = ?");
        $stmt->execute([$guideId]);
        echo json_encode(['success' => true, 'message' => 'Guide reactivated successfully']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('process_guide_update.php error: ' . $e->getMessage());
    // Show actual error in development — replace with a generic message in production
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Add this to your existing process_guide_update.php
if ($_POST['action'] === 'approve_registration') {
    $guideId = $_POST['guide_id'];
    $stmt = $pdo->prepare("UPDATE guides SET is_approved = 1, is_available = 1, submitted_at = NULL WHERE id = ?");
    $stmt->execute([$guideId]);
    echo json_encode(['success' => true]);
    exit;
}

