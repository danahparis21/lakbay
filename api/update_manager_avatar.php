<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['avatar'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_size = 2 * 1024 * 1024; // 2MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Image must be less than 2MB']);
    exit();
}

$upload_dir = '../uploads/avatars/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'manager_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $avatar_url = 'uploads/avatars/' . $filename;
    
    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$avatar_url, $_SESSION['user_id']]);
    
    $_SESSION['user_avatar'] = $avatar_url;
    
    echo json_encode(['success' => true, 'message' => 'Avatar updated', 'avatar_url' => $avatar_url]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>