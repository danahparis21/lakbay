<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$mobile = $_POST['mobile'] ?? '';
$experience = $_POST['experience'] ?? 0;
$bio = $_POST['bio'] ?? '';
$mountains = json_decode($_POST['mountains'] ?? '[]', true);

try {
    $pdo->beginTransaction();
    
    // Insert into users table with role 'guide'
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'guide')");
    $stmt->execute([$name, $email, $hashedPassword]);
    $userId = $pdo->lastInsertId();
    
    // Insert into guides table
    $stmt = $pdo->prepare("INSERT INTO guides (user_id, specialization, years_experience, rating, total_trips, is_available, bio) VALUES (?, ?, ?, 5.0, 0, 1, ?)");
    $stmt->execute([$userId, 'General', $experience, $bio]);
    $guideId = $pdo->lastInsertId();
    
    // Insert guide-mountain assignments
    $stmt = $pdo->prepare("INSERT INTO guide_mountains (guide_id, mountain_id) VALUES (?, ?)");
    foreach ($mountains as $mountainId) {
        $stmt->execute([$guideId, $mountainId]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>