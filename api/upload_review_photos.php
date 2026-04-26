<?php
// api/upload_review_photos.php
session_start();
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
ini_set('date.timezone', 'Asia/Manila');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/reviews/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$uploadedFiles = [];
foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . uniqid() . '_' . basename($_FILES['photos']['name'][$key]);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($tmpName, $targetPath)) {
            $uploadedFiles[] = '/uploads/reviews/' . $fileName;
        }
    }
}

echo json_encode(['success' => true, 'files' => $uploadedFiles]);
?>