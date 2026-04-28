<?php
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');

// Cancel bookings where downpayment deadline has passed
$stmt = $pdo->prepare("
    UPDATE bookings 
    SET status = 'cancelled',
        downpayment_status = 'expired',
        updated_at = NOW()
    WHERE status = 'waiting_payment' 
      AND downpayment_deadline < NOW()
      AND downpayment_status != 'paid'
");
$stmt->execute();

echo "Cancelled " . $stmt->rowCount() . " expired bookings\n";