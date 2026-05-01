<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Manila');

// ── AUTH: require guide login ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── Get guide profile ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT g.id AS guide_id, g.specialization, g.years_experience, g.rating, g.total_trips,
           u.name, u.email, u.avatar, u.phone
    FROM guides g
    JOIN users u ON u.id = g.user_id
    WHERE g.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    die('Guide profile not found.');
}
$guide_id = $guide['guide_id'];

// ── Stats: Total bookings, fully paid bookings, earnings, pending count ──
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status IN ('active', 'confirmed', 'finished') THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'finished' AND guide_payment_status = 'paid' THEN 1 ELSE 0 END) as fully_paid_bookings
    FROM bookings
    WHERE guide_id = ? AND status != 'cancelled'
");
$stmt->execute([$guide_id]);
$booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate earnings and pending count (including waiting_payment status)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(
            CASE 
                WHEN b.guide_payment_status = 'paid' AND b.status = 'finished' THEN
                    CASE 
                        WHEN b.hike_type = 'overnight' 
                        THEN COALESCE((SELECT guide_fee_overnight FROM guide_mountain_rates WHERE guide_id = b.guide_id AND mountain_id = b.mountain_id LIMIT 1), 1500)
                        ELSE COALESCE((SELECT guide_fee_day FROM guide_mountain_rates WHERE guide_id = b.guide_id AND mountain_id = b.mountain_id LIMIT 1), 801)
                    END
                ELSE 0
            END
        ), 0) as total_earnings,
        COUNT(CASE WHEN (b.guide_payment_status != 'paid' AND b.status = 'finished') OR b.status = 'waiting_payment' THEN 1 END) as pending_payments_count,
        COALESCE(SUM(CASE WHEN b.guide_payment_status = 'paid' THEN 
            CASE 
                WHEN b.hike_type = 'overnight' 
                THEN COALESCE((SELECT guide_fee_overnight FROM guide_mountain_rates WHERE guide_id = b.guide_id AND mountain_id = b.mountain_id LIMIT 1), 1500)
                ELSE COALESCE((SELECT guide_fee_day FROM guide_mountain_rates WHERE guide_id = b.guide_id AND mountain_id = b.mountain_id LIMIT 1), 801)
            END
        ELSE 0 END), 0) as paid_earnings
    FROM bookings b
    WHERE b.guide_id = ? AND b.status IN ('finished', 'waiting_payment')
");
$stmt->execute([$guide_id]);
$earning_stats = $stmt->fetch(PDO::FETCH_ASSOC);


// ── All bookings (FIXED - added guide_payment_status and mountain_id) ──
$stmt = $pdo->prepare("
    SELECT 
        b.id, b.booking_number, b.mountain_id, b.guide_id,
        b.hike_date, b.start_time, b.hike_type, b.status, b.number_of_hikers,
        b.total_amount, b.downpayment_amount, b.payment_status, b.special_requests,
        b.downpayment_status, b.guide_payment_status,
        b.user_id as hiker_user_id,
        m.name as mountain_name, m.image as mountain_image,
        u.name as hiker_name
    FROM bookings b
    JOIN mountains m ON b.mountain_id = m.id
    JOIN users u ON b.user_id = u.id
    WHERE b.guide_id = ?
    ORDER BY b.hike_date DESC, b.created_at DESC
");
$stmt->execute([$guide_id]);
$all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Reviews (FIXED - was querying bookings instead of guide_reviews) ──
$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, r.is_verified_purchase,
           u.name as reviewer_name, u.avatar as reviewer_avatar,
           b.booking_number, m.name as mountain_name
    FROM guide_reviews r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN bookings b ON b.id = r.booking_id
    LEFT JOIN mountains m ON m.id = b.mountain_id
    WHERE r.guide_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([$guide_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as total_reviews
    FROM guide_reviews
    WHERE guide_id = ? AND status = 'approved'
");
$stmt->execute([$guide_id]);
$rating_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$avg_rating = round($rating_stats['avg_rating'], 1);
$total_reviews = $rating_stats['total_reviews'];

// Avatar initials
$name_parts = explode(' ', $guide['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));


// ── Handle AJAX: Get booking details ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_booking_details') {
    if (ob_get_length()) ob_clean();
    error_reporting(0);
    header('Content-Type: application/json');
    
    try {
        $booking_id = $_POST['booking_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT 
                b.id, b.booking_number, b.mountain_id, b.guide_id,
                b.hike_date, b.start_time, b.hike_type, b.status, b.number_of_hikers,
                b.total_amount, b.downpayment_amount, b.payment_status, b.special_requests,
                b.downpayment_status, b.guide_payment_status,
                b.user_id as hiker_user_id,
                m.name as mountain_name,
                u.name as hiker_name, u.email as hiker_email, u.phone as hiker_phone
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND b.guide_id = ?
        ");
        $stmt->execute([$booking_id, $guide_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            // Get the correct guide fee from guide_mountain_rates
            $stmt_fee = $pdo->prepare("
                SELECT 
                    CASE WHEN ? = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END as guide_fee
                FROM guide_mountain_rates gm
                WHERE gm.guide_id = ? AND gm.mountain_id = ?
                LIMIT 1
            ");
            $stmt_fee->execute([$booking['hike_type'], $booking['guide_id'], $booking['mountain_id']]);
            $fee_row = $stmt_fee->fetch();
            
            $guideFee = $fee_row ? floatval($fee_row['guide_fee']) : ($booking['hike_type'] === 'overnight' ? 1500 : 801);
            $booking['guide_fee'] = $guideFee;
            
            // Get additional hikers
            $stmt2 = $pdo->prepare("
                SELECT hiker_name, age, emergency_contact_name, emergency_contact_number
                FROM booking_hikers
                WHERE booking_id = ?
            ");
            $stmt2->execute([$booking_id]);
            $booking['additional_hikers'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'booking' => $booking]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


// ── Handle AJAX: Confirm booking with payment instructions (UPDATED to match guide_messages.php) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_booking') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $booking_id = $_POST['booking_id'] ?? 0;
        
        // Get booking details with mountain and guide fee info
        $stmt = $pdo->prepare("
            SELECT b.user_id, b.booking_number, b.total_amount, 
                   b.hike_date, b.hike_type, b.mountain_id, b.status,
                   m.name as mountain_name
            FROM bookings b
            JOIN mountains m ON m.id = b.mountain_id
            WHERE b.id = ? AND b.guide_id = (SELECT id FROM guides WHERE user_id = ?)
        ");
        $stmt->execute([$booking_id, $user_id]);
        $bk = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bk) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or permission denied']);
            exit;
        }
        
        // Check if already active
        if ($bk['status'] === 'active') {
            echo json_encode(['success' => false, 'message' => 'Booking is already active']);
            exit;
        }
        
        // Get the correct guide fee from guide_mountain_rates
        $stmt_fee = $pdo->prepare("
            SELECT CASE WHEN ? = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END as guide_fee
            FROM guide_mountain_rates gm
            WHERE gm.guide_id = (SELECT id FROM guides WHERE user_id = ?) AND gm.mountain_id = ?
            LIMIT 1
        ");
        $stmt_fee->execute([$bk['hike_type'], $user_id, $bk['mountain_id']]);
        $fee_row = $stmt_fee->fetch();
        $guideFee = $fee_row ? $fee_row['guide_fee'] : ($bk['hike_type'] === 'overnight' ? 1500 : 801);
        
        // Calculate downpayment (20% of guide fee, minimum ₱200)
        $downpayment = max(200, round($guideFee * 0.2));
        $deadline = date('Y-m-d H:i:s', strtotime('+5 hours'));
        
        // Get guide's GCash details
        $stmt_guide = $pdo->prepare("
            SELECT u.name, g.gcash_name, g.gcash_number, g.gcash_qr_code 
            FROM guides g
            JOIN users u ON u.id = g.user_id
            WHERE g.user_id = ?
        ");
        $stmt_guide->execute([$user_id]);
        $guide_info = $stmt_guide->fetch(PDO::FETCH_ASSOC);
        
        $guide_name = $guide_info['name'];
        $gcash_number = $guide_info['gcash_number'] ?? '0999 999 9999';
        $gcash_name = $guide_info['gcash_name'] ?? $guide_name;
        $gcash_qr = !empty($guide_info['gcash_qr_code']) ? '../' . $guide_info['gcash_qr_code'] : '../assets/images/gcash-qr.jpg';
        
        // Update booking: set to waiting_payment status with downpayment details
        $stmt = $pdo->prepare("
            UPDATE bookings SET 
                status = 'waiting_payment', 
                downpayment_deadline = ?,
                downpayment_amount = ?,
                downpayment_status = 'unpaid',
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$deadline, $downpayment, $booking_id]);
        
        $remainingToGuide = $guideFee - $downpayment;
        $total_amount = number_format($bk['total_amount'], 2);
        $hike_date = date('F j, Y', strtotime($bk['hike_date']));
        
        // MESSAGE 1: BOOKING CONFIRMED (System announcement)
        $confirmedMessage = "🎉 BOOKING CONFIRMED\n";
        $confirmedMessage .= "Booking #{$bk['booking_number']} · {$guide_name}\n\n";
        $confirmedMessage .= "✓ CONFIRMED\n\n";
        $confirmedMessage .= "📅 Hike Date: {$hike_date}\n";
        $confirmedMessage .= "📍 Mountain: " . htmlspecialchars($bk['mountain_name']) . "\n";
        $confirmedMessage .= "💰 Total Amount: ₱{$total_amount}\n";
        $confirmedMessage .= "🏔️ Tour Guide Fee: ₱" . number_format($guideFee, 2) . "\n";
        $confirmedMessage .= "💵 Downpayment Required: ₱" . number_format($downpayment, 2) . "\n";
        $confirmedMessage .= "📦 Remaining Balance (to guide): ₱" . number_format($remainingToGuide, 2) . "\n\n";
        $confirmedMessage .= "⏰ Complete downpayment within 5 hours to secure your booking.\n";
        $confirmedMessage .= "Payment instructions have been sent below. 🏔️";
        
        // Insert confirmation message as system announcement
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', 1)
        ");
        $stmt_msg->execute([$user_id, $bk['user_id'], $confirmedMessage, MSG_AES_KEY]);
        
        // MESSAGE 2: PAYMENT INSTRUCTIONS CARD
        $instructionsMessage = "💰 Please complete your downpayment using the details below.";
        
        $actionData = json_encode([
            'type' => 'payment_instructions',
            'gcash_number' => $gcash_number,
            'gcash_name' => $gcash_name,
            'qr_code_url' => $gcash_qr,
            'downpayment_amount' => number_format($downpayment, 2),
            'remaining_balance' => number_format($remainingToGuide, 2),
            'booking_number' => $bk['booking_number'],
            'total_amount' => $total_amount,
            'deadline' => $deadline,
            'guide_fee' => $guideFee,
            'hike_type' => $bk['hike_type'],
            'payment_status' => 'unpaid',
        ]);
        
        // Insert payment instructions as action_data message
        $stmt_instructions = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, action_data, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', ?, 0)
        ");
        $stmt_instructions->execute([$user_id, $bk['user_id'], $instructionsMessage, MSG_AES_KEY, $actionData]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Booking confirmed! Payment instructions sent to hiker.',
            'booking_number' => $bk['booking_number']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ── Handle AJAX: Cancel booking (UPDATED to match guide_messages.php) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $booking_id = $_POST['booking_id'] ?? 0;
        $cancel_reason = $_POST['cancel_reason'] ?? 'Booking cancelled by guide.';
        
        // Get booking details before updating
        $stmt = $pdo->prepare("
            SELECT b.user_id, b.booking_number, b.mountain_id, m.name as mountain_name
            FROM bookings b
            JOIN mountains m ON m.id = b.mountain_id
            WHERE b.id = ? AND b.guide_id = (SELECT id FROM guides WHERE user_id = ?)
        ");
        $stmt->execute([$booking_id, $user_id]);
        $bk = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bk) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or permission denied']);
            exit;
        }
        
        // Update booking status to 'cancelled'
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$booking_id]);
        
        // Get guide name
        $stmt_guide = $pdo->prepare("SELECT u.name FROM guides g JOIN users u ON u.id = g.user_id WHERE g.user_id = ?");
        $stmt_guide->execute([$user_id]);
        $guide_name = $stmt_guide->fetchColumn();
        
        // Send cancellation message to hiker
        $msg_body = "❌ **BOOKING CANCELLED**\n\n";
        $msg_body .= "Your booking #{$bk['booking_number']} has been CANCELLED by the guide.\n\n";
        $msg_body .= "📍 Mountain: {$bk['mountain_name']}\n";
        $msg_body .= "👤 Guide: {$guide_name}\n\n";
        $msg_body .= "📝 Reason: {$cancel_reason}\n\n";
        $msg_body .= "Please contact support if you have questions or need to rebook.\n\n";
        $msg_body .= "We hope to see you on another adventure soon! 🏔️";
        
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', 1)
        ");
        $stmt_msg->execute([$user_id, $bk['user_id'], $msg_body, MSG_AES_KEY]);
        
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ── Handle AJAX: Finish hike ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finish_hike') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $booking_id = $_POST['booking_id'] ?? 0;
        
        // Get booking details
        $stmt = $pdo->prepare("
            SELECT b.user_id, b.booking_number, b.guide_id
            FROM bookings b
            WHERE b.id = ? AND b.guide_id = (SELECT id FROM guides WHERE user_id = ?) AND b.status = 'active'
        ");
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or not active']);
            exit;
        }
        
        // Update booking status to 'finished'
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'finished', 
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$booking_id]);
        
        // Send notification to hiker
        $finishMessage = "🏁 **HIKE COMPLETED!**\n\n";
        $finishMessage .= "Your hike with booking #{$booking['booking_number']} has been marked as finished by the guide.\n\n";
        $finishMessage .= "Thank you for hiking with us! We hope you had a great experience.\n\n";
        $finishMessage .= "⭐ Don't forget to leave a review for your guide!\n\n";
        $finishMessage .= "See you on your next adventure! 🏔️";
        
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', 1)
        ");
        $stmt_msg->execute([$user_id, $booking['user_id'], $finishMessage, MSG_AES_KEY]);
        
        echo json_encode(['success' => true, 'message' => 'Hike marked as finished! Hiker has been notified.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Handle AJAX: Confirm guide fee payment (after hike) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_guide_payment') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $booking_id = $_POST['booking_id'] ?? 0;
        $guide_fee_amount = $_POST['guide_fee_amount'] ?? 0;
        
        // Get booking details
        $stmt = $pdo->prepare("
            SELECT b.user_id, b.booking_number, b.guide_id, b.guide_payment_status,
                   b.downpayment_amount, b.mountain_id, b.hike_type,
                   g.user_id as guide_user_id
            FROM bookings b
            JOIN guides g ON g.id = b.guide_id
            WHERE b.id = ? AND b.guide_id = (SELECT id FROM guides WHERE user_id = ?)
        ");
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }
        
        // Get guide fee from mountain rates
        $stmt_fee = $pdo->prepare("
            SELECT CASE WHEN ? = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END as guide_fee
            FROM guide_mountain_rates gm
            WHERE gm.guide_id = ? AND gm.mountain_id = ?
            LIMIT 1
        ");
        $stmt_fee->execute([$booking['hike_type'], $booking['guide_id'], $booking['mountain_id']]);
        $fee_row = $stmt_fee->fetch();
        $guideFee = $fee_row ? $fee_row['guide_fee'] : ($booking['hike_type'] === 'overnight' ? 1500 : 801);
        
        $remainingGuideFee = $guideFee - $booking['downpayment_amount'];
        
        // Update guide_payment_status and overall payment_status
        $stmt = $pdo->prepare("
            UPDATE bookings SET 
                guide_payment_status = 'paid',
                payment_status = 'paid',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$booking_id]);
        
        // Send confirmation message to hiker
        $guide_name = $guide['name'];
        $confirmMessage = "💰 **GUIDE FEE PAID!**\n\n";
        $confirmMessage .= "You have successfully paid the remaining guide fee of ₱" . number_format($remainingGuideFee, 2) . " for booking #{$booking['booking_number']}.\n\n";
        $confirmMessage .= "✓ Booking is now COMPLETED!\n\n";
        $confirmMessage .= "Thank you for hiking with {$guide_name}! ⭐\n\n";
        $confirmMessage .= "We hope you had a wonderful experience. Don't forget to leave a review! 🏔️";
        
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', 1)
        ");
        $stmt_msg->execute([$user_id, $booking['user_id'], $confirmMessage, MSG_AES_KEY]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Guide fee payment confirmed! Booking is now completed.',
            'remaining_amount' => $remainingGuideFee
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
// Helper functions
function fmt_date(string $d): string {
    return date('M j, Y', strtotime($d));
}

function get_payment_badge($payment_status, $guide_payment_status = null) {
    if ($payment_status === 'paid' && $guide_payment_status === 'paid') {
        return '<span class="badge badge-green"><i class="fas fa-check-circle"></i> Fully Paid</span>';
    }
    if ($payment_status === 'partial') {
        return '<span class="badge badge-amber"><i class="fas fa-hourglass-half"></i> Downpayment Paid</span>';
    }
    if ($payment_status === 'paid') {
        return '<span class="badge badge-green"><i class="fas fa-check-circle"></i> Paid</span>';
    }
    if ($payment_status === 'pending') {
        return '<span class="badge badge-amber"><i class="fas fa-clock"></i> Pending</span>';
    }
    return '<span class="badge badge-gray"><i class="fas fa-times-circle"></i> Unpaid</span>';
}
function get_status_badge($status) {
    if ($status === 'active') return '<span class="badge badge-green"><i class="fas fa-check-circle"></i> Confirmed</span>';
    if ($status === 'pending') return '<span class="badge badge-amber"><i class="fas fa-clock"></i> Pending</span>';
    if ($status === 'finished') return '<span class="badge badge-blue"><i class="fas fa-flag-checkered"></i> Finished</span>';
    if ($status === 'cancelled') return '<span class="badge badge-gray"><i class="fas fa-ban"></i> Cancelled</span>';
    return '<span class="badge">' . ucfirst($status) . '</span>';
}

function render_stars($rating) {
    $full = floor($rating);
    $half = $rating - $full >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
    $stars = '';
    for ($i = 0; $i < $full; $i++) $stars .= '<i class="fas fa-star" style="color:#f5b042;"></i>';
    if ($half) $stars .= '<i class="fas fa-star-half-alt" style="color:#f5b042;"></i>';
    for ($i = 0; $i < $empty; $i++) $stars .= '<i class="far fa-star" style="color:#f5b042;"></i>';
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY Guide — Bookings</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    .guide-main {
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .guide-content { 
      padding: 20px 24px; 
      flex: 1; 
      display: flex; 
      flex-direction: column; 
      overflow-y: auto;
      background:
        radial-gradient(ellipse 60% 40% at 80% 10%, rgba(16,6,0,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 10% 80%, rgba(16,6,0,0.03) 0%, transparent 60%);
    }

    /* Stats Row - Responsive */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 16px;
      box-shadow: var(--glass-shadow);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--r-sm);
      background: var(--primary-soft);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      margin-bottom: 10px;
    }
    .stat-card-label { 
      font-size: 0.65rem; 
      font-weight: 600; 
      text-transform: uppercase; 
      letter-spacing: 0.5px; 
      color: var(--ink-4); 
      margin-bottom: 6px; 
    }
    .stat-card-val { 
      font-family: 'Playfair Display', serif; 
      font-size: 1.5rem; 
      font-weight: 700; 
      color: var(--ink); 
      line-height: 1.2; 
    }
    .stat-card-sub { 
      font-size: 0.6rem; 
      color: var(--ink-4); 
      margin-top: 4px; 
    }
    
    .bookings-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 24px;
      margin-top: 24px;
      flex: 1;
      min-height: 0;
    }

    /* Bookings Container */
    .bookings-container {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: 0;
    }
    .section-header {
      padding: 16px 18px 12px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      font-weight: 700;
      color: var(--ink);
    }
    .filter-group {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .filter-btn {
      padding: 5px 12px;
      border-radius: 40px;
      background: var(--stone);
      border: 1px solid var(--line);
      font-size: 0.65rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      color: var(--ink-3);
    }
    .filter-btn:hover {
      background: var(--stone-2);
      color: var(--ink);
      transform: translateY(-1px);
    }
    .filter-btn:active {
      transform: translateY(1px);
    }
    .filter-btn.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(16,6,0,0.2);
    }

    /* Mobile Booking Cards (hidden on desktop, shown on mobile) */
    .bookings-mobile-list {
      display: none;
      flex-direction: column;
      gap: 12px;
      padding: 16px;
    }
    .booking-mobile-card {
      background: white;
      border-radius: var(--r-lg);
      padding: 14px;
      border: 1px solid var(--line);
      box-shadow: var(--shadow-sm);
    }
    .booking-mobile-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }
    .booking-mobile-thumb {
      width: 50px;
      height: 50px;
      border-radius: var(--r-md);
      background-size: cover;
      background-position: center;
      flex-shrink: 0;
    }
    .booking-mobile-info {
      flex: 1;
    }
    .booking-mobile-mountain {
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--ink);
    }
    .booking-mobile-number {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      color: var(--ink-4);
      margin-top: 2px;
    }
    .booking-mobile-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid var(--stone-2);
      font-size: 0.75rem;
    }
    .booking-mobile-row:last-child {
      border-bottom: none;
    }
    .booking-mobile-label {
      color: var(--ink-4);
      font-weight: 500;
    }
    .booking-mobile-value {
      color: var(--ink);
      font-weight: 600;
    }
    .booking-mobile-actions {
      display: flex;
      gap: 6px;
      margin-top: 12px;
      flex-wrap: nowrap;
      overflow-x: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
      padding-bottom: 4px;
    }
    .booking-mobile-actions::-webkit-scrollbar { display: none; }
    
    .mobile-action-btn {
      flex: 1;
      min-width: 0;
      padding: 6px 4px;
      border-radius: 8px;
      background: var(--stone);
      border: 1px solid var(--line);
      font-size: 0.62rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2px;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .mobile-action-btn:hover {
      background: var(--stone-2);
      color: var(--ink);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .mobile-action-btn:active {
      transform: translateY(1px);
      box-shadow: none;
    }
    .mobile-action-btn i { font-size: 0.8rem; }

    /* Desktop Table */
    .bookings-table-wrapper {
      overflow-x: auto;
      flex: 1;
      display: block;
    }
    .bookings-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 700px;
    }
    .bookings-table th {
      text-align: left;
      padding: 12px 14px;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--ink-4);
      border-bottom: 1px solid var(--line);
      background: rgba(255,255,255,0.5);
    }
    .bookings-table td {
      padding: 14px;
      font-size: 0.78rem;
      color: var(--ink-2);
      border-bottom: 1px solid var(--line);
      vertical-align: middle;
    }
    .booking-actions-cell {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }
    .action-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: rgba(0,0,0,0.04);
      border: 1px solid var(--line);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      color: var(--ink-3);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .action-icon:hover {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16,6,0,0.2);
    }
    .action-icon:active {
      transform: translateY(0);
    }
    .booking-info-cell {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .mountain-thumb {
      width: 36px;
      height: 36px;
      border-radius: var(--r-sm);
      background-size: cover;
      background-position: center;
      flex-shrink: 0;
    }

    /* Reviews Sidebar - Responsive */
    .reviews-sidebar {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .rating-summary {
      padding: 16px;
      text-align: center;
      border-bottom: 1px solid var(--line);
    }
    .rating-number {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 800;
      color: #f5b042;
      line-height: 1;
    }
    .rating-stars {
      font-size: 0.85rem;
      margin: 6px 0;
    }
    .reviews-list {
      flex: 1;
      overflow-y: auto;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .review-item {
      border-bottom: 1px solid var(--line);
      padding-bottom: 12px;
    }
    .review-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
    }
    .review-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-soft);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.8rem;
    }
    .reviewer-name {
      font-weight: 600;
      font-size: 0.75rem;
      color: var(--ink);
    }
    .review-meta {
      font-size: 0.6rem;
      color: var(--ink-4);
    }
    .verified-badge {
      display: inline-block;
      background: rgba(30, 123, 72, 0.1);
      color: #1E7B48;
      font-size: 0.55rem;
      padding: 2px 6px;
      border-radius: 20px;
      margin-left: 6px;
    }
    .review-stars {
      font-size: 0.65rem;
      margin-bottom: 5px;
    }
    .review-comment {
      font-size: 0.7rem;
      color: var(--ink-2);
      line-height: 1.45;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 8px;
      border-radius: 40px;
      font-size: 0.6rem;
      font-weight: 600;
    }
    .badge-green { background: rgba(30, 123, 72, 0.1); color: #1E7B48; }
    .badge-amber { background: rgba(230, 126, 34, 0.1); color: #c96a10; }
    .badge-blue { background: rgba(52, 89, 149, 0.1); color: #345995; }
    .badge-gray { background: #eef2f8; color: #6e7483; }

    /* Mobile Responsive */
    @media (max-width: 1100px) {
      .bookings-layout { 
        grid-template-columns: 1fr;
        gap: 24px;
        flex: none;
        height: auto;
      }
      .reviews-sidebar { width: 100%; }
      .bookings-container { min-height: auto; }
    }

    @media (max-width: 900px) {
      .guide-main { 
        height: auto; 
        min-height: 100vh; 
        overflow: visible; 
      }
      .guide-content { 
        padding: 16px; 
        overflow-y: visible;
        flex: none;
        height: auto;
      }
      .stats-row { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 12px;
        margin-bottom: 20px;
      }
      /* Hide desktop table on mobile */
      .bookings-table-wrapper { display: none; }
      /* Show mobile cards */
      .bookings-mobile-list { display: flex; }
      
      .bookings-layout {
        display: flex;
        flex-direction: column;
        gap: 24px;
      }
      .bookings-container {
        overflow: visible;
        flex: none;
      }
      .reviews-sidebar {
        overflow: visible;
        flex: none;
      }
      .reviews-list {
        max-height: 500px; /* Give it some height but allow it to be part of page scroll */
        overflow-y: auto;
      }
    }
    
    @media (max-width: 480px) {
      .guide-content { padding: 12px; }
      .stats-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .stat-card { padding: 10px; }
      .stat-card-val { font-size: 1.1rem; }
      .stat-card-label { font-size: 0.6rem; }
      .section-header { flex-direction: column; align-items: stretch; gap: 12px; }
      .filter-group { 
        justify-content: flex-start; 
        overflow-x: auto; 
        padding-bottom: 8px;
        scrollbar-width: none; 
      }
      .filter-group::-webkit-scrollbar { display: none; }
      .filter-btn { white-space: nowrap; flex-shrink: 0; }
      .booking-mobile-actions { flex-direction: row; }
      .mobile-action-btn { width: auto; }
      .bookings-mobile-list { padding: 12px; }
    }

    /* Modal styles */
    .detail-section { margin-bottom: 20px; }
    .detail-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--ink-4);
      padding-bottom: 6px;
      border-bottom: 1px solid var(--line);
      margin-bottom: 12px;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .detail-item { display: flex; flex-direction: column; gap: 3px; }
    .detail-label { font-size: 0.6rem; font-weight: 600; color: var(--ink-4); text-transform: uppercase; }
    .detail-val { font-size: 0.8rem; color: var(--ink); font-weight: 500; }

    .btn {
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 0.85rem;
    }
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    .btn:active {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    .btn-primary { 
      background: var(--primary); 
      color: white; 
      border: none; 
    }
    .btn-primary:hover {
      background: var(--primary-light);
    }
    .btn-ghost { 
      background: var(--stone); 
      border: 1px solid var(--line); 
      color: var(--ink-2);
    }
    .btn-ghost:hover {
      background: var(--stone-2);
    }
    .btn-danger { 
      background: var(--red); 
      color: white; 
      border: none; 
      box-shadow: 0 4px 12px rgba(184,49,42,0.2);
    }
    .btn-danger:hover {
      background: #9e2920;
      box-shadow: 0 8px 20px rgba(184,49,42,0.3);
    }

    /* Mobile Tabs */
    .mobile-tabs {
      display: none;
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: 40px;
      padding: 4px;
      margin-bottom: 20px;
      box-shadow: var(--shadow-sm);
    }
    .mobile-tab-btn {
      flex: 1;
      padding: 10px;
      border-radius: 30px;
      border: none;
      background: transparent;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--ink-3);
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .mobile-tab-btn:not(.active):hover {
      background: rgba(0,0,0,0.03);
      color: var(--ink);
    }
    .mobile-tab-btn:active {
      transform: scale(0.96);
    }
    .mobile-tab-btn.active {
      background: var(--primary);
      color: white;
      box-shadow: 0 4px 12px rgba(16,6,0,0.2);
    }

    @media (max-width: 900px) {
      .mobile-tabs { display: flex; }
      
      /* Toggle visibility based on active tab */
      .bookings-layout.show-bookings .reviews-sidebar { display: none; }
      .bookings-layout.show-ratings .bookings-container { display: none; }
      .bookings-layout.show-ratings .reviews-sidebar { display: flex; }
    }

    .toast {
      position: fixed;
      bottom: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #100600;
      color: white;
      padding: 10px 20px;
      border-radius: 40px;
      font-size: 0.8rem;
      z-index: 9999;
      opacity: 0;
      transition: 0.25s;
      pointer-events: none;
      white-space: nowrap;
    }
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    /* Payment instructions styling */
.payment-instructions {
    background: linear-gradient(135deg, #f0f7e8 0%, #e8f0e0 100%);
    border-radius: var(--r-lg);
    padding: 16px;
    margin-top: 16px;
    border-left: 4px solid var(--green);
}
.payment-instructions h4 {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--green);
    margin-bottom: 12px;
}
.payment-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed rgba(0,0,0,0.05);
    font-size: 0.78rem;
}
.payment-detail-label {
    font-weight: 600;
    color: var(--ink-4);
}
.payment-detail-value {
    font-weight: 700;
    color: var(--ink);
}
.gcash-qr-preview {
    text-align: center;
    margin-top: 12px;
}
.gcash-qr-preview img {
    max-width: 120px;
    border-radius: 12px;
    border: 2px solid var(--white);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
/* Add this to your existing styles */
.hikers-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.7rem;
}
.hikers-table th {
    text-align: left;
    padding: 8px 6px;
    background: var(--stone);
    font-weight: 600;
    color: var(--ink-3);
    border-bottom: 1px solid var(--line);
}
.hikers-table td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--line);
    color: var(--ink-2);
}
  </style>
</head>
<body>
<div class="guide-app">
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="white" opacity="0.9"/><path d="M14 16L18 8L24 22H14V16Z" fill="white" opacity="0.3"/></svg>
      <div><div class="sidebar-logo-text">LAKBAY</div><div class="sidebar-logo-sub">Guide Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.php"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.php"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.php"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-bookings.php" class="active"><i class="fas fa-shield-halved"></i> Bookings</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
   <div class="sidebar-profile">
  <?php if (!empty($guide['avatar'])): ?>
    <img src="../<?= htmlspecialchars($guide['avatar']) ?>" class="sidebar-avatar" style="object-fit:cover;" alt="avatar">
  <?php else: ?>
    <div class="sidebar-avatar"><?= $initials ?></div>
  <?php endif; ?>
  <div class="sidebar-profile-info">
    <div class="sidebar-profile-name"><?= htmlspecialchars($guide['name']) ?></div>
    <div class="sidebar-profile-role"><?= htmlspecialchars($guide['specialization'] ?? 'Trail Guide') ?></div>
  </div>
  <button onclick="confirmLogout()" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </button>
</div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Bookings</div>
      <div class="topbar-right">
        <div class="topbar-time" id="liveTime"></div>
        <button class="topbar-icon-btn" onclick="refreshPage()"><i class="fas fa-rotate-right"></i></button>
      </div>
    </div>

    <div class="guide-content">

      <!-- STATS CARDS -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-card-content">
            <div class="stat-card-label">Total Bookings</div>
            <div class="stat-card-val"><?= (int)$booking_stats['total_bookings'] ?></div>
            <div class="stat-card-sub">All bookings (not cancelled)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-card-content">
            <div class="stat-card-label">Confirmed Bookings</div>
            <div class="stat-card-val"><?= (int)$booking_stats['fully_paid_bookings'] ?></div>
            <div class="stat-card-sub">Bookings with guide fee fully paid</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-card-content">
            <div class="stat-card-label">Total Earnings</div>
            <div class="stat-card-val">₱<?= number_format($earning_stats['total_earnings'], 0) ?></div>
            <div class="stat-card-sub">Total guide fees from finished hikes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-card-content">
            <div class="stat-card-label">Pending Payments</div>
            <div class="stat-card-val"><?= (int)$earning_stats['pending_payments_count'] ?></div>
            <div class="stat-card-sub">Bookings waiting for guide fee payment</div>
        </div>
    </div>
</div>
      <!-- MOBILE TABS (only shown on small screens) -->
      <div class="mobile-tabs">
        <button class="mobile-tab-btn active" onclick="switchMobileTab('bookings', this)">
          <i class="fas fa-calendar-check"></i> Bookings
        </button>
        <button class="mobile-tab-btn" onclick="switchMobileTab('ratings', this)">
          <i class="fas fa-star"></i> Reviews & Ratings
        </button>
      </div>

      <!-- MAIN LAYOUT -->
      <div class="bookings-layout show-bookings">
        
        <!-- LEFT: Bookings -->
        <div class="bookings-container">
          <div class="section-header">
            <div class="section-title">All Bookings</div>
            <div class="filter-group">
              <button class="filter-btn active" data-filter="all" onclick="filterBookings('all')">All</button>
              <button class="filter-btn" data-filter="pending" onclick="filterBookings('pending')">Pending</button>
              <button class="filter-btn" data-filter="active" onclick="filterBookings('active')">Confirmed</button>
              <button class="filter-btn" data-filter="finished" onclick="filterBookings('finished')">Finished</button>
            </div>
          </div>
          
          <!-- DESKTOP TABLE VIEW -->
          <div class="bookings-table-wrapper">
            <table class="bookings-table" id="bookingsTable">
              <thead>
                <tr><th>Booking</th><th>Hiker</th><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Payment</th><th>Actions</th></tr>
              </thead>
              <tbody id="bookingsTableBody">
                <?php foreach ($all_bookings as $bk): ?>
                <tr data-status="<?= $bk['status'] ?>">
                  <td>
                    <div class="booking-info-cell">
                      <div class="mountain-thumb" style="background-image:url('<?= htmlspecialchars($bk['mountain_image'] ?? 'https://images.unsplash.com/photo-1613144492511-59984f1cdeb3?w=60') ?>');"></div>
                      <div class="booking-details">
                        <div class="booking-mountain"><?= htmlspecialchars($bk['mountain_name']) ?></div>
                        <div class="booking-number">#<?= htmlspecialchars($bk['booking_number']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><strong><?= htmlspecialchars($bk['hiker_name']) ?></strong></td>
                  <td><?= date('M j, Y g:i A', strtotime($bk['hike_date'] . ' ' . ($bk['start_time'] ?? '00:00'))) ?></td>
                  
                  <td><?= ucfirst(str_replace('_', ' ', $bk['hike_type'])) ?></td>
                  <td>₱<?= number_format($bk['total_amount'], 0) ?></td>
                  <td><?= get_status_badge($bk['status']) ?></td>
                  <td><?= get_payment_badge($bk['payment_status'], $bk['guide_payment_status'] ?? 'unpaid') ?></td>
                  <td>
                    <div class="booking-actions-cell">
                      <button class="action-icon action-view" onclick="viewBookingDetails(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-eye"></i></button>
                      <button class="action-icon action-chat" onclick="chatWithHiker(<?= $bk['hiker_user_id'] ?>, '<?= htmlspecialchars($bk['hiker_name']) ?>')"><i class="fas fa-comment-dots"></i></button>
                      <?php if ($bk['status'] === 'pending'): ?>
                      <button class="action-icon action-approve" onclick="confirmBooking(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-check-circle"></i></button>
                      <button class="action-icon action-cancel" onclick="showCancelModal(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-times-circle"></i></button>
                      <?php endif; ?>
                      <?php if ($bk['payment_status'] !== 'paid' && $bk['status'] === 'active'): ?>
                      <button class="action-icon action-payment" onclick="requestPayment(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>', <?= $bk['total_amount'] ?>, <?= $bk['downpayment_amount'] ?? 0 ?>)"><i class="fas fa-credit-card"></i></button>
                      <?php endif; ?>
                      <?php if ($bk['status'] === 'finished' && ($bk['guide_payment_status'] ?? 'unpaid') !== 'paid'): ?>
<button class="action-icon action-payment" onclick="confirmGuideFeePayment(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>', <?= $bk['total_amount'] ?>, <?= $bk['downpayment_amount'] ?? 0 ?>, '<?= $bk['hike_type'] ?>', <?= $bk['mountain_id'] ?>)">
    <i class="fas fa-money-bill-wave"></i>
</button>
<?php endif; ?>
                    </div>
                   </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($all_bookings)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;">No bookings yet</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- MOBILE CARDS VIEW -->
          <div class="bookings-mobile-list" id="bookingsMobileList">
            <?php foreach ($all_bookings as $bk): ?>
            <div class="booking-mobile-card" data-status="<?= $bk['status'] ?>">
              <div class="booking-mobile-header">
                <div class="booking-mobile-thumb" style="background-image:url('<?= htmlspecialchars($bk['mountain_image'] ?? 'https://images.unsplash.com/photo-1613144492511-59984f1cdeb3?w=60') ?>');"></div>
                <div class="booking-mobile-info">
                  <div class="booking-mobile-mountain"><?= htmlspecialchars($bk['mountain_name']) ?></div>
                  <div class="booking-mobile-number">#<?= htmlspecialchars($bk['booking_number']) ?></div>
                </div>
              </div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Hiker:</span><span class="booking-mobile-value"><?= htmlspecialchars($bk['hiker_name']) ?></span></div>
             <div class="booking-mobile-row"><span class="booking-mobile-label">Date/Time:</span><span class="booking-mobile-value"><?= date('M j, Y g:i A', strtotime($bk['hike_date'] . ' ' . ($bk['start_time'] ?? '00:00'))) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Type:</span><span class="booking-mobile-value"><?= ucfirst(str_replace('_', ' ', $bk['hike_type'])) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Amount:</span><span class="booking-mobile-value">₱<?= number_format($bk['total_amount'], 0) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Status:</span><span class="booking-mobile-value"><?= get_status_badge($bk['status']) ?></span></div>
              <div class="booking-mobile-row"><span class="booking-mobile-label">Payment:</span><span class="booking-mobile-value"><?= get_payment_badge($bk['payment_status'], $bk['guide_payment_status'] ?? 'unpaid') ?></span></div>
              
              <div class="booking-mobile-actions">
                <button class="mobile-action-btn" onclick="viewBookingDetails(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-eye"></i> View</button>
                <button class="mobile-action-btn" onclick="chatWithHiker(<?= $bk['hiker_user_id'] ?>, '<?= htmlspecialchars($bk['hiker_name']) ?>')"><i class="fas fa-comment-dots"></i> Chat</button>
                <?php if ($bk['status'] === 'pending'): ?>
                <button class="mobile-action-btn" style="background:var(--green-lt);color:var(--green);" onclick="confirmBooking(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-check-circle"></i> Approve</button>
                <button class="mobile-action-btn" style="background:var(--red-lt);color:var(--red);" onclick="showCancelModal(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')"><i class="fas fa-times-circle"></i> Cancel</button>
                <?php endif; ?>
                <?php if ($bk['payment_status'] !== 'paid' && $bk['status'] === 'active'): ?>
                <button class="mobile-action-btn" style="background:#f5b04220;color:#e67e22;" onclick="requestPayment(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>', <?= $bk['total_amount'] ?>, <?= $bk['downpayment_amount'] ?? 0 ?>)"><i class="fas fa-credit-card"></i> Payment</button>
                <?php endif; ?>
                <?php if ($bk['status'] === 'finished' && ($bk['guide_payment_status'] ?? 'unpaid') !== 'paid'): ?>
<button class="mobile-action-btn" style="background:#2563eb20;color:#2563eb;" onclick="confirmGuideFeePayment(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>', <?= $bk['total_amount'] ?>, <?= $bk['downpayment_amount'] ?? 0 ?>, '<?= $bk['hike_type'] ?>', <?= $bk['mountain_id'] ?>)">
    <i class="fas fa-money-bill-wave"></i> Pay Guide Fee
</button>
<?php endif; ?>

              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($all_bookings)): ?>
            <div style="text-align:center;padding:40px;color:var(--ink-4);">No bookings yet</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT: Reviews Sidebar -->
        <div class="reviews-sidebar">
          <div class="rating-summary">
            <div class="rating-number"><?= $avg_rating ?></div>
            <div class="rating-stars"><?= render_stars($avg_rating) ?></div>
            <div class="rating-count">Based on <?= $total_reviews ?> <?= $total_reviews === 1 ? 'review' : 'reviews' ?></div>
          </div>
          <div class="reviews-list">
            <?php if (empty($reviews)): ?>
              <div class="no-reviews" style="text-align:center;padding:30px;color:var(--ink-4);">
                <i class="fas fa-star" style="font-size:2rem;opacity:0.3;margin-bottom:10px;display:block;"></i>
                <p>No reviews yet</p>
              </div>
            <?php else: ?>
              <?php foreach ($reviews as $review): ?>
              <div class="review-item">
                <div class="review-header">
                  <div class="review-avatar"><?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?></div>
                  <div class="review-info">
                    <div class="reviewer-name">
                      <?= htmlspecialchars($review['reviewer_name']) ?>
                      <?php if ($review['is_verified_purchase'] == 1): ?>
                      <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                      <?php endif; ?>
                    </div>
                    <div class="review-meta"><?= fmt_date($review['created_at']) ?> · <?= htmlspecialchars($review['mountain_name'] ?? 'Hike') ?></div>
                  </div>
                </div>
                <div class="review-stars"><?= render_stars($review['rating']) ?></div>
                <div class="review-comment"><?= htmlspecialchars($review['comment'] ?? 'No comment provided.') ?></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>

  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.php" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.php" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.php" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-bookings.php" class="bnav-item active"><i class="fas fa-shield-halved"></i><span>Bookings</span></a>
      <a href="guide-profile.php" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<!-- Modals (same as before) -->
<div class="modal-overlay" id="bookingDetailsModal">
  <div class="modal" style="max-width: 650px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-calendar-check"></i> Booking Details</div>
      <button class="modal-close" onclick="closeModal('bookingDetailsModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="bookingDetailsBody"></div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="closeModal('bookingDetailsModal')">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="confirmModal">
  <div class="modal" style="max-width: 450px;">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--green);"><i class="fas fa-check-circle"></i> Confirm Booking</div>
      <button class="modal-close" onclick="closeModal('confirmModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <p>Are you sure you want to confirm booking <strong id="confirmBookingNumber"></strong>?</p>
      <p style="font-size:0.75rem; color:var(--ink-4); margin-top:10px;">This will notify the hiker and set the status to "Confirmed".</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('confirmModal')">Go Back</button>
      <button type="button" class="btn btn-primary" id="confirmActionBtn" style="background:var(--green); color:white;">Yes, Confirm</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="cancelModal">
  <div class="modal" style="max-width: 450px;">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);"><i class="fas fa-times-circle"></i> Cancel Booking</div>
      <button class="modal-close" onclick="closeModal('cancelModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <form id="cancelForm" onsubmit="cancelBooking(event)">
      <input type="hidden" id="cancelBookingId" value="">
      <div class="modal-body">
        <p>Cancel booking <strong id="cancelBookingNumber"></strong>?</p>
        <textarea id="cancelReason" class="form-control" rows="3" placeholder="Reason..." required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('cancelModal')">Keep</button>
        <button type="submit" class="btn btn-danger">Cancel</button>
      </div>
    </form>
  </div>
</div>

<style>
@keyframes slideUpIn { from{opacity:0;transform:translateX(-50%) translateY(20px);}to{opacity:1;transform:translateX(-50%) translateY(0);} }
</style>

<div class="toast" id="toast"></div>

<script>
// ============================================
// WORKING LOGOUT MODAL
// ============================================
function confirmLogout() {
    const existingModal = document.getElementById('standaloneLogoutModal');
    if (existingModal) existingModal.remove();
    
    const modal = document.createElement('div');
    modal.id = 'standaloneLogoutModal';
    modal.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        background: rgba(0, 0, 0, 0.6) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        z-index: 9999999 !important;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 20px; padding: 24px; max-width: 400px; width: 90%; margin: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); font-family: 'DM Sans', sans-serif;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1a1a18; margin: 0;">Log out of Guide Portal?</h3>
            </div>
            <div style="margin-bottom: 24px; color: #666; font-size: 0.85rem; line-height: 1.5;">
                You will be redirected to the login page.
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button id="logoutCancelBtn" style="padding: 10px 20px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: 1px solid #ddd; background: #f0f0f0; color: #666; font-family: inherit;">Cancel</button>
                <button id="logoutConfirmBtn" style="padding: 10px 20px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: none; background: #dc2626; color: white; font-family: inherit;">Log Out</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('logoutCancelBtn').onclick = function() { modal.remove(); };
    document.getElementById('logoutConfirmBtn').onclick = function() {
        window.location.href = '../login-and-signup/login.php';
    };
    modal.onclick = function(e) { if (e.target === modal) modal.remove(); };
}

// ============================================
// BEAUTIFUL DYNAMIC CONFIRM MODAL
// ============================================
function showConfirmModal(options) {
    const { title, message, confirmText, confirmColor, onConfirm, cancelText = 'Cancel' } = options;
    
    const existingModal = document.getElementById('dynamicConfirmModal');
    if (existingModal) existingModal.remove();
    
    const modalDiv = document.createElement('div');
    modalDiv.id = 'dynamicConfirmModal';
    
    modalDiv.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:999999;">
            <div style="background:white;border-radius:20px;padding:24px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:${confirmColor === '#dc2626' ? '#fef2f2' : (confirmColor === '#1E7B48' ? '#d1fae5' : '#fef3c7')};color:${confirmColor};display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas ${confirmColor === '#dc2626' ? 'fa-exclamation-triangle' : (confirmColor === '#1E7B48' ? 'fa-check-circle' : 'fa-question-circle')}"></i>
                    </div>
                    <h3 style="font-size:1.1rem;font-weight:700;color:#1a1a18;margin:0;">${title}</h3>
                </div>
                <div style="margin-bottom:24px;color:#666;font-size:0.85rem;line-height:1.5;">
                    <p>${message}</p>
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button id="modalCancelBtn" style="padding:10px 20px;border-radius:10px;font-size:0.8rem;font-weight:600;cursor:pointer;border:1px solid #ddd;background:#f0f0f0;color:#666;font-family:inherit;">${cancelText}</button>
                    <button id="modalConfirmBtn" style="padding:10px 20px;border-radius:10px;font-size:0.8rem;font-weight:600;cursor:pointer;border:none;background:${confirmColor};color:white;font-family:inherit;">${confirmText}</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modalDiv);
    
    document.getElementById('modalCancelBtn').onclick = function() { modalDiv.remove(); };
    document.getElementById('modalConfirmBtn').onclick = function() {
        modalDiv.remove();
        if (onConfirm) onConfirm();
    };
    modalDiv.onclick = function(e) { if (e.target === modalDiv) modalDiv.remove(); };
}

// ============================================
// UPDATED FUNCTIONS USING NEW MODAL
// ============================================

function confirmBooking(bookingId, bookingNumber) {
    showConfirmModal({
        title: 'Confirm Booking?',
        message: `Booking ${bookingNumber} will be confirmed and payment instructions sent to the hiker.`,
        confirmText: 'Yes, Confirm',
        confirmColor: '#1E7B48',
        onConfirm: () => _doConfirmBooking(bookingId, bookingNumber)
    });
}



function confirmGuideFeePayment(bookingId, bookingNumber, totalAmount, downpaymentAmount, hikeType, mountainId) {
    let guideFee = hikeType === 'overnight' ? 1500 : 801;
    let remainingBalance = guideFee - downpaymentAmount;
    
    showConfirmModal({
        title: 'Confirm Guide Fee Payment?',
        message: `Remaining guide fee of ₱${remainingBalance.toLocaleString()} for booking ${bookingNumber} will be marked as fully paid and completed.`,
        confirmText: 'Confirm Payment',
        confirmColor: '#2563eb',
        onConfirm: () => _doConfirmGuideFeePayment(bookingId, bookingNumber, remainingBalance)
    });
}


function showCancelModal(bookingId, bookingNumber) {
    // Create a modal with textarea for reason
    const existingModal = document.getElementById('cancelReasonModal');
    if (existingModal) existingModal.remove();
    
    const modalDiv = document.createElement('div');
    modalDiv.id = 'cancelReasonModal';
    modalDiv.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:999999;">
            <div style="background:white;border-radius:20px;padding:24px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h3 style="font-size:1.1rem;font-weight:700;color:#1a1a18;margin:0;">Cancel Booking ${bookingNumber}?</h3>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:8px;">Reason for cancellation</label>
                    <textarea id="cancelReasonInput" class="form-control" rows="3" placeholder="Please provide a reason for cancelling this booking..." style="width:100%;padding:12px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:14px;font-family:inherit;"></textarea>
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button id="cancelModalCloseBtn" style="padding:10px 20px;border-radius:10px;font-size:0.8rem;font-weight:600;cursor:pointer;border:1px solid #ddd;background:#f0f0f0;color:#666;">Go Back</button>
                    <button id="cancelModalConfirmBtn" style="padding:10px 20px;border-radius:10px;font-size:0.8rem;font-weight:600;cursor:pointer;border:none;background:#dc2626;color:white;">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modalDiv);
    
    document.getElementById('cancelModalCloseBtn').onclick = function() { modalDiv.remove(); };
    document.getElementById('cancelModalConfirmBtn').onclick = function() {
        const reason = document.getElementById('cancelReasonInput').value;
        if (!reason.trim()) {
            showToast('Please provide a reason for cancellation');
            return;
        }
        modalDiv.remove();
        _doCancelBooking(bookingId, bookingNumber, reason);
    };
    modalDiv.onclick = function(e) { if (e.target === modalDiv) modalDiv.remove(); };
}

function initUnreadBadgePoller() {
    function fetchAndUpdateBadge() {
        fetch('../api/guide_messages.php?action=get_conversations')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var total = (data.conversations || []).reduce(function(s, c) { return s + (c.unread_count || 0); }, 0);
                ['.sidebar-nav a[href="guide-communication.php"]', '.guide-bottom-nav a[href="guide-communication.php"]'].forEach(function(sel, i) {
                    var link = document.querySelector(sel);
                    if (!link) return;
                    link.style.position = 'relative';
                    var badge = link.querySelector('.notif-badge');
                    if (total > 0) {
                        if (!badge) { badge = document.createElement('span'); badge.className = 'notif-badge'; link.appendChild(badge); }
                        badge.textContent = total > 9 ? '9+' : total;
                        badge.style.cssText = i === 0
                            ? 'position:absolute;right:12px;top:8px;background:#dc2626;color:white;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;min-width:18px;text-align:center;'
                            : 'position:absolute;top:-5px;right:5px;background:#dc2626;color:white;font-size:9px;font-weight:700;padding:2px 5px;border-radius:20px;min-width:16px;text-align:center;';
                    } else if (badge) { badge.remove(); }
                });
            }).catch(function() {});
    }
    fetchAndUpdateBadge();
    setInterval(fetchAndUpdateBadge, 30000);
}
initUnreadBadgePoller();
const CURRENT_USER_ID = <?= json_encode($user_id) ?>;

function filterBookings(status) {
  // Desktop filter
  const rows = document.querySelectorAll('#bookingsTableBody tr');
  // Mobile filter
  const mobileCards = document.querySelectorAll('.booking-mobile-card');
  const buttons = document.querySelectorAll('.filter-btn');
  
  buttons.forEach(btn => {
    if (btn.getAttribute('data-filter') === status) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
  
  // Desktop
  rows.forEach(row => {
    if (status === 'all') {
      row.style.display = '';
    } else {
      row.style.display = row.getAttribute('data-status') === status ? '' : 'none';
    }
  });
  
  // Mobile
  mobileCards.forEach(card => {
    if (status === 'all') {
      card.style.display = '';
    } else {
      card.style.display = card.getAttribute('data-status') === status ? '' : 'none';
    }
  });
}
function viewBookingDetails(bookingId, bookingNumber) {
    showToast('Loading...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'get_booking_details',
            booking_id: bookingId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const booking = data.booking;
            const typeMap = { day_hike: 'Day Hike', overnight: 'Overnight', multi_day: 'Multi-Day' };
            
            const hikeDateTime = booking.start_time 
                ? new Date(booking.hike_date + 'T' + booking.start_time).toLocaleString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                  })
                : new Date(booking.hike_date).toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                  });
            
            // Calculate guide fee based on hike type (use fee from database if available)
let guideFee = booking.guide_fee || (booking.hike_type === 'overnight' ? 1500 : 801);
            let downpaymentPaid = parseFloat(booking.downpayment_amount || 0);
            let remainingGuideFee = guideFee - downpaymentPaid;
            
            // Status badges
            let statusBadge = '';
            if (booking.status === 'active') statusBadge = '<span class="badge badge-green">Confirmed</span>';
            else if (booking.status === 'pending') statusBadge = '<span class="badge badge-amber">Pending</span>';
            else if (booking.status === 'finished') statusBadge = '<span class="badge badge-blue">Finished</span>';
            else if (booking.status === 'cancelled') statusBadge = '<span class="badge badge-gray">Cancelled</span>';
            else statusBadge = '<span class="badge badge-gray">' + booking.status + '</span>';
            
            // Payment status badge
            let paymentBadge = '';
            if (booking.payment_status === 'paid') paymentBadge = '<span class="badge badge-green">Paid</span>';
            else if (booking.payment_status === 'partial') paymentBadge = '<span class="badge badge-amber">Partial (Downpayment Paid)</span>';
            else if (booking.payment_status === 'pending') paymentBadge = '<span class="badge badge-amber">Pending</span>';
            else paymentBadge = '<span class="badge badge-gray">Unpaid</span>';
            
            // Downpayment status badge
            let downpaymentBadge = '';
            if (booking.downpayment_status === 'paid') downpaymentBadge = '<span class="badge badge-green">Paid</span>';
            else if (booking.downpayment_status === 'expired') downpaymentBadge = '<span class="badge badge-gray">Expired</span>';
            else downpaymentBadge = '<span class="badge badge-amber">Unpaid</span>';
            
            // Guide payment status badge (for after hike)
            let guidePaymentBadge = '';
            if (booking.guide_payment_status === 'paid') guidePaymentBadge = '<span class="badge badge-green">Guide Fee Paid</span>';
            else if (remainingGuideFee <= 0) guidePaymentBadge = '<span class="badge badge-green">Fully Paid</span>';
            else guidePaymentBadge = '<span class="badge badge-amber">Pending (₱' + remainingGuideFee.toLocaleString() + ')</span>';
            
            // Format deadline if exists
            let deadlineHtml = '';
            if (booking.downpayment_deadline) {
                const deadlineDate = new Date(booking.downpayment_deadline).toLocaleString('en-PH', {
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                });
                deadlineHtml = `<div class="detail-item">
                    <div class="detail-label">Downpayment Deadline</div>
                    <div class="detail-val" style="color: #e67e22;">${deadlineDate}</div>
                </div>`;
            }
            
            let actionButtons = '';
            if (booking.status === 'pending') {
                actionButtons = `
                    <div style="display: flex; gap: 12px; margin-top: 20px;">
                        <button class="btn btn-primary" onclick="confirmBookingWithPayment(${booking.id}, '${booking.booking_number}')" style="flex: 1; background: var(--green);">
                            <i class="fas fa-check-circle"></i> Confirm & Request Downpayment
                        </button>
                        <button class="btn btn-danger" onclick="showCancelModalFromDetails(${booking.id}, '${booking.booking_number}')" style="flex: 1;">
                            <i class="fas fa-times-circle"></i> Cancel Booking
                        </button>
                    </div>
                `;
            } else if (booking.status === 'active') {
                actionButtons = `
                    <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="location.href='guide-communication.php?user_id=${booking.hiker_user_id}'" style="flex: 1;">
                            <i class="fas fa-comment-dots"></i> Send Message to Hiker
                        </button>
                        <button class="btn btn-success" onclick="finishHike(${booking.id}, '${booking.booking_number}')" style="flex: 1; background: #1E7B48;">
                            <i class="fas fa-flag-checkered"></i> Mark Hike as Finished
                        </button>
                    </div>
                `;
            } else if (booking.status === 'finished' && booking.guide_payment_status !== 'paid' && remainingGuideFee > 0) {
                actionButtons = `
                    <div style="margin-top: 20px;">
                        <button class="btn btn-primary" onclick="confirmGuideFeePayment(${booking.id}, '${booking.booking_number}', ${booking.total_amount}, ${downpaymentPaid}, '${booking.hike_type}', ${booking.mountain_id})" style="width: 100%; background: #2563eb;">
                            <i class="fas fa-money-bill-wave"></i> Confirm Guide Fee Payment (₱${remainingGuideFee.toLocaleString()})
                        </button>
                    </div>
                `;
            }
            
            document.getElementById('bookingDetailsBody').innerHTML = `
                <style>
                    .hikers-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 0.7rem;
                    }
                    .hikers-table th {
                        text-align: left;
                        padding: 8px 6px;
                        background: var(--stone);
                        font-weight: 600;
                        color: var(--ink-3);
                        border-bottom: 1px solid var(--line);
                    }
                    .hikers-table td {
                        padding: 8px 6px;
                        border-bottom: 1px solid var(--line);
                        color: var(--ink-2);
                    }
                    .status-row {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 12px;
                        margin-top: 8px;
                    }
                    .status-item {
                        flex: 1;
                        min-width: 120px;
                    }
                    .status-label {
                        font-size: 0.55rem;
                        font-weight: 600;
                        color: var(--ink-4);
                        text-transform: uppercase;
                        margin-bottom: 4px;
                    }
                </style>
                
                <div class="detail-section">
                    <div class="detail-section-title">Booking Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Booking Number</div>
                            <div class="detail-val"><strong>${escapeHtml(booking.booking_number)}</strong></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Hike Date & Time</div>
                            <div class="detail-val">${hikeDateTime}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Hike Type</div>
                            <div class="detail-val">${typeMap[booking.hike_type] || booking.hike_type}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Mountain</div>
                            <div class="detail-val">${escapeHtml(booking.mountain_name)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Number of Hikers</div>
                            <div class="detail-val">${booking.number_of_hikers} person(s)</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-val">₱${parseFloat(booking.total_amount).toLocaleString()}</div>
                        </div>
                        ${booking.special_requests ? `
                        <div class="detail-item span2">
                            <div class="detail-label">Special Requests</div>
                            <div class="detail-val">${escapeHtml(booking.special_requests)}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="detail-section">
                    <div class="detail-section-title">Payment Status</div>
                    <div class="status-row">
                        <div class="status-item">
                            <div class="status-label">Booking Status</div>
                            <div>${statusBadge}</div>
                        </div>
                        <div class="status-item">
                            <div class="status-label">Payment Status</div>
                            <div>${paymentBadge}</div>
                        </div>
                        <div class="status-item">
                            <div class="status-label">Downpayment Status</div>
                            <div>${downpaymentBadge}</div>
                        </div>
                    </div>
                    ${deadlineHtml}
                </div>
                
                <div class="detail-section">
                    <div class="detail-section-title">Tour Guide Fee Breakdown</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Tour Guide Fee</div>
                            <div class="detail-val">₱${guideFee.toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Downpayment Paid</div>
                            <div class="detail-val" style="color: var(--green);">₱${downpaymentPaid.toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Remaining Guide Fee</div>
                            <div class="detail-val" style="color: ${remainingGuideFee > 0 ? '#e67e22' : '#1E7B48'}; font-weight: 700;">
                                ₱${remainingGuideFee.toLocaleString()}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Guide Fee Status</div>
                            <div class="detail-val">${guidePaymentBadge}</div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <div class="detail-section-title">Hiker Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Name</div>
                            <div class="detail-val"><strong>${escapeHtml(booking.hiker_name)}</strong></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-val">${escapeHtml(booking.hiker_email || 'Not provided')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone</div>
                            <div class="detail-val">${escapeHtml(booking.hiker_phone || 'Not provided')}</div>
                        </div>
                    </div>
                </div>
                
                ${booking.additional_hikers && booking.additional_hikers.length > 0 ? `
                <div class="detail-section">
                    <div class="detail-section-title">Additional Hikers (${booking.additional_hikers.length})</div>
                    <div style="overflow-x:auto;">
                        <table class="hikers-table">
                            <thead>
                                <tr><th>Name</th><th>Age</th><th>Emergency Contact</th><th>Emergency Phone</th></tr>
                            </thead>
                            <tbody>
                                ${booking.additional_hikers.map(hiker => `
                                <tr>
                                    <td><strong>${escapeHtml(hiker.hiker_name)}</strong></td>
                                    <td>${hiker.age || 'N/A'}</td>
                                    <td>${escapeHtml(hiker.emergency_contact_name || 'N/A')}</td>
                                    <td>${escapeHtml(hiker.emergency_contact_number || 'N/A')}</td>
                                </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
                
                ${actionButtons}
            `;
            openModal('bookingDetailsModal');
        } else {
            showToast(data.message || 'Could not load booking details');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error loading booking details');
    });
}

function _doConfirmBooking(bookingId, bookingNumber) {
        showToast('Confirming booking and sending payment details...');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'confirm_booking',
                booking_id: bookingId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`✓ Booking ${bookingNumber} confirmed! Payment instructions sent to hiker.`);
                closeModal('bookingDetailsModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to confirm booking');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error confirming booking');
        });
}
function _doCancelBooking(bookingId, bookingNumber, cancelReason) {
    showToast('Cancelling booking...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'cancel_booking',
            booking_id: bookingId,
            cancel_reason: cancelReason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`✗ Booking ${bookingNumber} cancelled`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Failed to cancel booking');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error cancelling booking');
    });
}


// Remove the old confirmBookingWithPayment function if it exists (or keep it as alias)
function confirmBookingWithPayment(bookingId, bookingNumber) {
    confirmBooking(bookingId, bookingNumber);
}


function showCancelModal(bookingId, bookingNumber) {
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancelBookingNumber').textContent = bookingNumber;
    document.getElementById('cancelReason').value = '';
    openModal('cancelModal');
}

function showCancelModalFromDetails(bookingId, bookingNumber) {
    closeModal('bookingDetailsModal');
    showCancelModal(bookingId, bookingNumber);
}

function cancelBooking(event) {
    event.preventDefault();
    const bookingId = document.getElementById('cancelBookingId').value;
    const bookingNumber = document.getElementById('cancelBookingNumber').textContent;
    const cancelReason = document.getElementById('cancelReason').value;
    
    if (!cancelReason.trim()) {
        showToast('Please provide a reason for cancellation');
        return;
    }
    
    showToast('Cancelling booking...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'cancel_booking',
            booking_id: bookingId,
            cancel_reason: cancelReason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`✗ Booking ${bookingNumber} cancelled`);
            closeModal('cancelModal');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Failed to cancel booking');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error cancelling booking');
    });
}

function requestPayment(bookingId, bookingNumber, totalAmount, downpayment) {
  const remaining = totalAmount - downpayment;
  showConfirmToast({
    title: 'Request Payment?',
    message: `Send a ₱${remaining.toLocaleString()} payment request for booking ${bookingNumber}?`,
    icon: 'fa-money-bill-wave',
    iconBg: 'rgba(37,99,235,0.1)',
    iconColor: '#2563eb',
    confirmLabel: 'Send Request',
    confirmBg: '#2563eb',
    onConfirm: () => showToast('Payment request sent!')
  });
}

function chatWithHiker(hikerId, hikerName) {
  window.location.href = `guide-communication.php?user=${hikerId}&name=${encodeURIComponent(hikerName)}`;
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function refreshPage() { location.reload(); }
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
 {
    function fetchAndUpdateBadge() {
        fetch('../api/guide_messages.php?action=get_conversations')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const total = (data.conversations || []).reduce((s, c) => s + (c.unread_count || 0), 0);
                ['.sidebar-nav a[href="guide-communication.php"]', '.guide-bottom-nav a[href="guide-communication.php"]'].forEach((sel, i) => {
                    const link = document.querySelector(sel);
                    if (!link) return;
                    link.style.position = 'relative';
                    let badge = link.querySelector('.notif-badge');
                    if (total > 0) {
                        if (!badge) { badge = document.createElement('span'); badge.className = 'notif-badge'; link.appendChild(badge); }
                        badge.textContent = total > 9 ? '9+' : total;
                        badge.style.cssText = i === 0
                            ? 'position:absolute;right:12px;top:8px;background:#dc2626;color:white;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;min-width:18px;text-align:center;'
                            : 'position:absolute;top:-5px;right:5px;background:#dc2626;color:white;font-size:9px;font-weight:700;padding:2px 5px;border-radius:20px;min-width:16px;text-align:center;';
                    } else if (badge) { badge.remove(); }
                });
            }).catch(() => {});
    }
    fetchAndUpdateBadge();
    setInterval(fetchAndUpdateBadge, 30000);
}
function switchMobileTab(tab, btn) {
  const layout = document.querySelector('.bookings-layout');
  const buttons = document.querySelectorAll('.mobile-tab-btn');
  
  buttons.forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  
  if (tab === 'bookings') {
    layout.classList.remove('show-ratings');
    layout.classList.add('show-bookings');
  } else {
    layout.classList.remove('show-bookings');
    layout.classList.add('show-ratings');
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}
function updateTime() {
  document.getElementById('liveTime').textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}
updateTime();
setInterval(updateTime, 1000);
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target === o) closeModal(o.id); }));

const viewBookingId = new URLSearchParams(location.search).get('view_booking');
if (viewBookingId) setTimeout(() => viewBookingDetails(viewBookingId, ''), 500);

function finishHike(bookingId, bookingNumber) {
    showConfirmToast({
        title: 'Mark Hike as Finished?',
        message: `Booking ${bookingNumber} will be marked as completed. The guide will become available again.`,
        icon: 'fa-flag-checkered',
        iconBg: 'rgba(27,112,69,0.1)',
        iconColor: 'var(--green)',
        confirmLabel: 'Yes, Finish',
        confirmBg: '#1E7B48',
        onConfirm: () => _doFinishHike(bookingId, bookingNumber)
    });
}
function _doFinishHike(bookingId, bookingNumber) {
        showToast('Completing hike...');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'finish_hike',
                booking_id: bookingId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('✓ Hike completed! Guide is now available.');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to finish hike');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error finishing hike');
        });
}

function _doConfirmGuideFeePayment(bookingId, bookingNumber, remainingBalance) {
        showToast('Confirming guide fee payment...');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'confirm_guide_payment',
                booking_id: bookingId,
                guide_fee_amount: remainingBalance
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`✓ Guide fee of ₱${data.remaining_amount?.toLocaleString()} confirmed! Booking completed.`);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to confirm payment');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error confirming payment');
        });
}
</script>

</body>
</html>