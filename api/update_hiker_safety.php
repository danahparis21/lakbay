<?php
// api/update_hiker_safety.php
// Updates safe/unsafe status for a hiker in a booking
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$bookingId  = (int)($input['booking_id'] ?? 0);
$updatedBy  = $_SESSION['user_id'];
$markAllSafe = $input['mark_all_safe'] ?? false;

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit;
}

try {
    if ($markAllSafe) {
        // Mark ALL hikers in this booking as safe
        // First get all hikers
        $hikers = [];

        // Booking owner
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.name
            FROM bookings b JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($owner) $hikers[] = $owner;

        // Additional hikers
        $stmt = $pdo->prepare("
            SELECT bh.hiker_name as name, u.id as user_id
            FROM booking_hikers bh
            LEFT JOIN users u ON (u.name = bh.hiker_name AND u.role = 'hiker')
            WHERE bh.booking_id = ?
        ");
        $stmt->execute([$bookingId]);
        $extras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hikers = array_merge($hikers, $extras);

        $stmt = $pdo->prepare("
            INSERT INTO hiker_safety_status (booking_id, user_id, hiker_name, status, updated_by)
            VALUES (?, ?, ?, 'safe', ?)
            ON DUPLICATE KEY UPDATE status = 'safe', updated_by = ?, updated_at = NOW()
        ");
        foreach ($hikers as $h) {
            $stmt->execute([$bookingId, $h['user_id'] ?: null, $h['name'], $updatedBy, $updatedBy]);
        }

        echo json_encode(['success' => true, 'message' => 'All hikers marked safe']);

    } else {
        // Mark single hiker
        $userId    = !empty($input['user_id']) ? (int)$input['user_id'] : null;
        $hikerName = trim($input['hiker_name'] ?? '');
        $status    = in_array($input['status'] ?? '', ['safe', 'unsafe', 'unknown'])
                     ? $input['status'] : 'unknown';
        $notes     = $input['notes'] ?? null;

        if (!$hikerName) {
            echo json_encode(['success' => false, 'message' => 'Missing hiker_name']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO hiker_safety_status (booking_id, user_id, hiker_name, status, updated_by, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                user_id = VALUES(user_id),
                updated_by = VALUES(updated_by),
                notes = VALUES(notes),
                updated_at = NOW()
        ");
        $stmt->execute([$bookingId, $userId, $hikerName, $status, $updatedBy, $notes]);

        echo json_encode(['success' => true, 'status' => $status, 'hiker' => $hikerName]);
    }

} catch (PDOException $e) {
    error_log("update_hiker_safety error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}