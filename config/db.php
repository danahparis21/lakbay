<?php
$host   = 'db';
$dbname = 'lakbay';
$user   = 'user';
$pass   = 'password';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Load .env file from backend directory
$envFile = __DIR__ . '/../backend/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Define the AES key constant for messages
if (!defined('MSG_AES_KEY')) {
    $envKey = getenv('LAKBAY_MSG_KEY');
    define('MSG_AES_KEY', $envKey ?: 'change-this-to-a-32-char-secret!!');
}
?>