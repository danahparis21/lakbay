<?php
// api/update_mountain.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE mountains SET
            name = ?,
            location = ?,
            description = ?,
            difficulty = ?,
            elevation = ?,
            registration_fee = ?,
            environmental_fee = ?,
            trail_length_km = ?,
            estimated_duration = ?,
            jumpOff = ?,
            weatherAdvisory = ?,
            peakTimes = ?,
            rules = ?,
            envReminders = ?,
            hazards = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['name'],
        $data['location'],
        $data['description'],
        $data['difficulty'],
        $data['elevation'],
        $data['registration_fee'],
        $data['environmental_fee'],
        $data['trail_length_km'],
        $data['estimated_duration'],
        $data['jumpOff'],
        $data['weatherAdvisory'],
        $data['peakTimes'],
        json_encode($data['rules']),
        json_encode($data['envReminders']),
        json_encode($data['hazards']),
        $data['status'],
        $data['id']
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>