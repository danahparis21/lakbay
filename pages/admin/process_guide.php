<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // First, create the user account
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $phone = $_POST['phone'];
        $role = 'guide';
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $phone, $role]);
        $userId = $pdo->lastInsertId();
        
        // Then, create the guide profile
        $specialization = $_POST['specialization'] ?? null;
        $years_experience = $_POST['years_experience'] ?? null;
        $bio = $_POST['bio'] ?? null;
        
        $stmt = $pdo->prepare("INSERT INTO guides (user_id, specialization, years_experience, bio, is_available, rating, total_trips) VALUES (?, ?, ?, ?, 1, 0.0, 0)");
        $stmt->execute([$userId, $specialization, $years_experience, $bio]);
        $guideId = $pdo->lastInsertId();
        
        // Assign mountains to the guide
        if (isset($_POST['mountains']) && is_array($_POST['mountains'])) {
            $stmt = $pdo->prepare("INSERT INTO guide_mountains (guide_id, mountain_id) VALUES (?, ?)");
            foreach ($_POST['mountains'] as $mountainId) {
                $stmt->execute([$guideId, $mountainId]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Guide created successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>