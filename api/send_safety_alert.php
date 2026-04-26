<?php
// api/send_safety_alert.php
// Inserts a safety alert into the safety_alerts table
// Optionally notifies admin (via any notification system you have)
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

$bookingId    = !empty($input['booking_id']) ? (int)$input['booking_id'] : null;
$type         = $input['type']         ?? 'safety';
$severity     = $input['severity']     ?? 'medium';
$title        = $input['title']        ?? 'Safety Alert';
$description  = $input['description']  ?? '';
$location     = $input['location']     ?? null;
$trailName    = $input['trail_name']   ?? null;
$reporterName = $input['reporter_name'] ?? ($_SESSION['name'] ?? 'Guide');
$reporterRole = $input['reporter_role'] ?? 'guide';
$reportedBy   = $_SESSION['user_id'];
$notifyAdmin  = $input['notify_admin'] ?? true;
$hikerInvolved = $input['hiker_involved'] ?? null;

// Lat/lng — accept from payload or fall back to null
$latitude  = isset($input['latitude'])  && is_numeric($input['latitude'])  ? (float)$input['latitude']  : null;
$longitude = isset($input['longitude']) && is_numeric($input['longitude']) ? (float)$input['longitude'] : null;

// Validate severity
if (!in_array($severity, ['low','medium','high','critical'])) $severity = 'medium';

try {
    $stmt = $pdo->prepare("
        INSERT INTO safety_alerts
            (booking_id, title, description, location, trail_name, type, severity, status,
             reported_by, reporter_name, reporter_role, hiker_involved, latitude, longitude, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $bookingId, $title, $description, $location, $trailName,
        $type, $severity, $reportedBy, $reporterName, $reporterRole,
        $hikerInvolved, $latitude, $longitude
    ]);
    $alertId = $pdo->lastInsertId();

    // ── Optional: Notify admin via your existing notification system ──
    // If you have a notifications table, insert here:
    if ($notifyAdmin) {
        try {
            // Find admin users
            $adminStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','manager') LIMIT 5");
            $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($admins as $adminId) {
                // Try to insert into notifications table if it exists
                $pdo->prepare("
                    INSERT IGNORE INTO notifications (user_id, title, message, type, reference_id, created_at)
                    VALUES (?, ?, ?, 'safety_alert', ?, NOW())
                ")->execute([
                    $adminId,
                    $title,
                    substr($description, 0, 200),
                    $alertId
                ]);
            }
        } catch (PDOException $e) {
            // Notifications table may not exist — skip silently
        }
    }

    echo json_encode([
        'success'  => true,
        'alert_id' => $alertId,
        'message'  => 'Alert saved',
        'notified' => $notifyAdmin
    ]);

} catch (PDOException $e) {
    error_log("send_safety_alert error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}