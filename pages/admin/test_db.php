<?php
require 'c:/Users/63945/Documents/lakbay_docker/lakbay/config/db.php';
$stmt = $pdo->query('SELECT id, name FROM mountains');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
