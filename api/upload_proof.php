<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Disable HTML error output and ensure clean JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);     // Log them instead

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded';
    if (isset($_FILES['proof_image']['error'])) {
        switch ($_FILES['proof_image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'File only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'No file selected';
                break;
            default:
                $errorMsg = 'Upload error: ' . $_FILES['proof_image']['error'];
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['proof_image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG and PNG files are allowed']);
    exit;
}

// Check file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
    exit;
}

// Create uploads directory if not exists
$uploadDir = __DIR__ . '/../uploads/proofs/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate safe filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'proof_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $imageUrl = '../uploads/proofs/' . $filename;
    echo json_encode(['success' => true, 'image_url' => $imageUrl]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Check directory permissions.']);
}
?>