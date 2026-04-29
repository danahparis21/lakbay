<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$booking_id = $_GET['id'] ?? null;

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        b.*,
        m.name as mountain_name,
        hiker.name as hiker_name,
        hiker.email as hiker_email,
        guide.name as guide_name
    FROM bookings b
    LEFT JOIN mountains m ON b.mountain_id = m.id
    LEFT JOIN users hiker ON b.user_id = hiker.id
    LEFT JOIN users guide ON b.guide_id = guide.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'booking' => $booking]);
?>