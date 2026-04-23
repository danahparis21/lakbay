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
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        exit;
    }
    
    // Check if email already exists for another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account']);
        exit;
    }
    
    // Handle avatar upload
    $avatarPath = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $fileType = $_FILES['avatar']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP, and GIF are allowed.']);
            exit;
        }
        
        // Check file size (2MB max)
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 2MB.']);
            exit;
        }
        
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
            $avatarPath = '/uploads/avatars/' . $filename;
        }
    }
    
    // Update database
    if ($avatarPath) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?");
        $success = $stmt->execute([$name, $email, $phone, $avatarPath, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $success = $stmt->execute([$name, $email, $phone, $userId]);
    }
    
    if ($success) {
        // Update session variables
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;
        if ($avatarPath) {
            $_SESSION['user_avatar'] = $avatarPath;
        }
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>