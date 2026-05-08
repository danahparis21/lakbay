<?php
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');

// Add this debug line to verify timezone is correct
error_log("Cron running at: " . date('Y-m-d H:i:s') . " (Asia/Manila)");

// Start transaction
$pdo->beginTransaction();

try {
    $totalUpdated = 0;
    
    // =============================================
    // 1. CANCEL EXPIRED WAITING_PAYMENT BOOKINGS
    // =============================================
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
    $cancelledCount = $stmt->rowCount();
    $totalUpdated += $cancelledCount;
    
    // =============================================
    // 2. CANCEL EXPIRED PENDING BOOKINGS (not accepted by guide)
    // =============================================
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'cancelled',
            downpayment_status = 'expired',
            updated_at = NOW()
        WHERE status = 'pending' 
          AND (downpayment_deadline < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
          AND downpayment_status != 'paid'
    ");
    $stmt->execute();
    $pendingCancelled = $stmt->rowCount();
    $totalUpdated += $pendingCancelled;
    
    // =============================================
    // 3. MARK ACTIVE HIKES AS FINISHED (hike date has passed)
    // =============================================
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'finished',
            updated_at = NOW(),
            completed_at = NOW()
        WHERE status = 'active' 
          AND hike_date < CURDATE()
    ");
    $stmt->execute();
    $finishedCount = $stmt->rowCount();
    $totalUpdated += $finishedCount;
    
    // =============================================
    // 4. MARK ACTIVE HIKES AS FINISHED (same day, but time passed)
    // =============================================
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'finished',
            updated_at = NOW(),
            completed_at = NOW()
        WHERE status = 'active' 
          AND hike_date = CURDATE()
          AND CONCAT(hike_date, ' ', start_time) < NOW()
    ");
    $stmt->execute();
    $finishedTodayCount = $stmt->rowCount();
    $totalUpdated += $finishedTodayCount;
    
    // =============================================
    // 5. CANCEL EXPIRED CONFIRMED BOOKINGS (confirmed but never paid)
    // =============================================
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'cancelled',
            downpayment_status = 'expired',
            updated_at = NOW()
        WHERE status = 'confirmed' 
          AND downpayment_deadline < NOW()
          AND downpayment_status != 'paid'
    ");
    $stmt->execute();
    $confirmedCancelled = $stmt->rowCount();
    $totalUpdated += $confirmedCancelled;
    
    // =============================================
    // 6. RELEASE GUIDES (if they have no active bookings)
    //    FIXED: Use a temporary table approach to avoid the MySQL error
    // =============================================
    $stmt = $pdo->prepare("
        UPDATE guides g
        SET g.is_available = 1,
            g.currently_on_hike = 0
        WHERE NOT EXISTS (
            SELECT 1 
            FROM bookings b 
            WHERE b.guide_id = g.id 
              AND b.status IN ('active', 'confirmed', 'waiting_payment')
        )
    ");
    $stmt->execute();
    $guidesReleased = $stmt->rowCount();
    
    $pdo->commit();
    
    // Output results
    echo "[" . date('Y-m-d H:i:s') . "] ========== CRON SUMMARY ==========\n";
    echo "📍 Current time (Asia/Manila): " . date('Y-m-d H:i:s') . "\n";
    echo "1️⃣ Expired waiting_payment cancelled: {$cancelledCount}\n";
    echo "2️⃣ Expired pending cancelled: {$pendingCancelled}\n";
    echo "3️⃣ Past date active → finished: {$finishedCount}\n";
    echo "4️⃣ Past time active → finished: {$finishedTodayCount}\n";
    echo "5️⃣ Expired confirmed cancelled: {$confirmedCancelled}\n";
    echo "6️⃣ Guides released: {$guidesReleased}\n";
    echo "📊 Total bookings updated: {$totalUpdated}\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Cron error: " . $e->getMessage());
    echo "❌ Error: " . $e->getMessage() . "\n";
}