<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$guideId = $_GET['guide_id'] ?? 0;

if (!$guideId) {
    echo json_encode(['success' => false, 'message' => 'Guide ID is required']);
    exit;
}

try {
    // Get the user_id from guides table
    $stmt = $pdo->prepare("SELECT user_id FROM guides WHERE id = ?");
    $stmt->execute([$guideId]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($guide) {
        echo json_encode([
            'success' => true, 
            'user_id' => $guide['user_id'],
            'guide_id' => $guideId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Guide not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>