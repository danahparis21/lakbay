<?php
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');

// Start transaction to ensure both updates happen together
$pdo->beginTransaction();

try {
    // Cancel expired waiting_payment bookings
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'cancelled',
            downpayment_status = 'expired',
            payment_status = 'cancelled',
            updated_at = NOW()
        WHERE status = 'waiting_payment' 
          AND downpayment_deadline < NOW()
          AND downpayment_status != 'paid'
    ");
    $stmt->execute();
    $cancelledCount = $stmt->rowCount();
    
    // For each cancelled booking, release the guide if they're not on any active hike
    if ($cancelledCount > 0) {
        $stmt2 = $pdo->prepare("
            UPDATE guides g
            SET is_available = 1,
                currently_on_hike = 0
            WHERE user_id IN (
                SELECT DISTINCT g.user_id
                FROM guides g
                JOIN bookings b ON b.guide_id = g.id
                WHERE b.status = 'cancelled'
                  AND b.downpayment_status = 'expired'
                  AND NOT EXISTS (
                      SELECT 1 FROM bookings b2 
                      WHERE b2.guide_id = g.id 
                        AND b2.status IN ('active', 'confirmed', 'waiting_payment')
                  )
            )
        ");
        $stmt2->execute();
    }
    
    // Also expire any unpaid but not yet confirmed bookings (safety)
    $stmt3 = $pdo->prepare("
        UPDATE bookings 
        SET status = 'cancelled',
            downpayment_status = 'expired',
            updated_at = NOW()
        WHERE status = 'pending' 
          AND downpayment_deadline < NOW()
          AND downpayment_deadline IS NOT NULL
    ");
    $stmt3->execute();
    
    $pdo->commit();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cancelled " . $cancelledCount . " expired bookings\n";
    echo "[" . date('Y-m-d H:i:s') . "] Also cancelled " . $stmt3->rowCount() . " pending expired bookings\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}