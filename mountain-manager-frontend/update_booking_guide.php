<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'] ?? null;
$guide_id = $data['guide_id'] ?? null;

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$stmt = $pdo->prepare("UPDATE bookings SET guide_id = ?, updated_at = NOW() WHERE id = ?");
$result = $stmt->execute([$guide_id ?: null, $booking_id]);

echo json_encode(['success' => $result]);
?>