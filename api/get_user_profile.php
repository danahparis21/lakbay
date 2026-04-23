<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Include database connection - CORRECTED PATH
$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(['success' => false, 'message' => 'Database config not found at: ' . $dbPath]);
    exit;
}

require_once $dbPath;

try {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT id, name, email, phone, avatar, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'avatar' => $user['avatar'] ?? '',
            'member_since' => $user['created_at']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>