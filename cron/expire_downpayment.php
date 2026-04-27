<?php
require_once '../config/db.php';

// Expire unpaid downpayments after deadline
$stmt = $pdo->prepare("
    UPDATE bookings 
    SET status = 'cancelled', 
        downpayment_status = 'expired',
        payment_status = 'expired'
    WHERE downpayment_status = 'unpaid' 
    AND downpayment_deadline < NOW()
    AND status = 'active'
");
$stmt->execute();

echo "Expired " . $stmt->rowCount() . " bookings\n";