<?php
require_once __DIR__ . '/../config/db.php';

$username = $_GET['username'] ?? '';
$available = true;

if ($username && strlen($username) >= 3) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $available = false;
    }
}

header('Content-Type: application/json');
echo json_encode(['taken' => !$available]);
?>