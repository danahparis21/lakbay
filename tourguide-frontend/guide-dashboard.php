<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Manila');
ini_set('date.timezone', 'Asia/Manila');

// ── AUTH: require guide login ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── 1. Get guide profile (guides table joined with users) ──────────────────
$stmt = $pdo->prepare("
    SELECT g.id AS guide_id, g.specialization, g.years_experience, g.rating,
           g.total_trips, g.is_available, g.trail_status, g.bio,
           u.name, u.email, u.avatar, u.phone, u.role
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

// ── 2. Stats: total bookings, total hikers, distinct mountains ─────────────
$stmt = $pdo->prepare("
    SELECT
        COUNT(*)                        AS total_bookings,
        COALESCE(SUM(number_of_hikers), 0) AS total_hikers,
        COUNT(DISTINCT mountain_id)     AS total_mountains
    FROM bookings
    WHERE guide_id = ?
      AND status NOT IN ('cancelled')
");
$stmt->execute([$guide_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
// ── 3. Upcoming confirmed bookings for this guide ──────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id, b.booking_number, b.hike_date, b.start_time, b.hike_type,
           b.number_of_hikers, b.status, b.user_id as hiker_user_id,
           u.name AS hiker_name,
           m.name AS mountain_name, m.image AS mountain_image,
           m.jumpOff
    FROM bookings b
    JOIN users u       ON u.id = b.user_id
    JOIN mountains m   ON m.id = b.mountain_id
    WHERE b.guide_id = ?
      AND b.hike_date >= CURDATE()
      AND b.status NOT IN ('cancelled', 'finished')
    ORDER BY b.hike_date ASC
    LIMIT 50
");
$stmt->execute([$guide_id]);
$upcoming_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT b.id, b.booking_number, b.hike_date, b.start_time, b.hike_type,
           b.number_of_hikers, b.status, b.user_id as hiker_user_id,
           u.name AS hiker_name,
           m.name AS mountain_name, m.image AS mountain_image,
           m.jumpOff
    FROM bookings b
    JOIN users u       ON u.id = b.user_id
    JOIN mountains m   ON m.id = b.mountain_id
    WHERE b.guide_id = ?
    ORDER BY b.hike_date ASC, b.created_at DESC
");
$stmt->execute([$guide_id]);
$all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$all_bookings_json = json_encode($all_bookings);


// Get active bookings for overlap detection
$stmt = $pdo->prepare("
    SELECT hike_date, id 
    FROM bookings 
    WHERE guide_id = ? AND status = 'active'
");
$stmt->execute([$guide_id]);
$active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 4. All bookings (for calendar & overlap detection) ─────────
$stmt = $pdo->prepare("
    SELECT b.id, b.booking_number, b.hike_date, b.number_of_hikers, b.status,
           m.name AS mountain_name
    FROM bookings b
    JOIN mountains m ON m.id = b.mountain_id
    WHERE b.guide_id = ?
      AND b.status IN ('active', 'pending', 'cancelled', 'finished')
    ORDER BY b.hike_date ASC
");
$stmt->execute([$guide_id]);
$calendar_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a set of occupied dates for quick JS lookup (confirmed only for overlap)
$occupied_dates = array_map(fn($b) => $b['hike_date'], array_filter($calendar_bookings, fn($b) => $b['status'] === 'active'));

// Pass calendar data to JS
$calendar_json   = json_encode($calendar_bookings);
$occupied_json   = json_encode(array_values(array_unique($occupied_dates)));

// ── 5. Pending bookings — flag overlaps with confirmed dates ───────────────
$stmt = $pdo->prepare("
    SELECT b.id, b.booking_number, b.hike_date, b.number_of_hikers, b.status, b.user_id as hiker_user_id,
           u.name AS hiker_name,
           m.name AS mountain_name, m.image AS mountain_image
    FROM bookings b
    JOIN users u       ON u.id = b.user_id
    JOIN mountains m   ON m.id = b.mountain_id
    WHERE b.guide_id = ?
      AND b.status = 'pending'
      AND b.hike_date >= CURDATE()
    ORDER BY b.hike_date ASC
");
$stmt->execute([$guide_id]);
$pending_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tag each pending booking with overlap info
foreach ($pending_bookings as &$pb) {
    $pb['overlaps'] = in_array($pb['hike_date'], $occupied_dates);
}
unset($pb);

// ── 5.5 Add overlap detection for pending bookings ───────────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id, b.booking_number, b.hike_date, b.start_time, b.number_of_hikers, b.status, b.user_id as hiker_user_id,
           u.name AS hiker_name,
           m.name AS mountain_name, m.image AS mountain_image
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN mountains m ON m.id = b.mountain_id
    WHERE b.guide_id = ?
      AND b.status = 'pending'
      AND b.hike_date >= CURDATE()
    ORDER BY b.hike_date ASC
");
$stmt->execute([$guide_id]);
$pending_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active bookings for overlap detection
$stmt = $pdo->prepare("
    SELECT hike_date, start_time, id 
    FROM bookings 
    WHERE guide_id = ? AND status = 'active'
");
$stmt->execute([$guide_id]);
$active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tag each pending booking with overlap info
foreach ($pending_bookings as &$pb) {
    $pb['overlaps'] = false;
    $pb['similar_schedule'] = [];
    
    foreach ($active_bookings as $ab) {
        if ($pb['hike_date'] === $ab['hike_date']) {
            $pb['overlaps'] = true;
            // Check if start times are similar (within 2 hours)
            if ($pb['start_time'] && $ab['start_time']) {
                $pb_time = strtotime($pb['start_time']);
                $ab_time = strtotime($ab['start_time']);
                if (abs($pb_time - $ab_time) <= 7200) { // 2 hours difference
                    $pb['similar_schedule'][] = $ab['id'];
                }
            }
        }
    }
}
unset($pb);

// ── 6. Admin broadcasts for guides ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id, b.message, b.created_at,
           u.name AS sender_name,
           COALESCE(rs.is_read, 0) AS is_read
    FROM broadcasts b
    JOIN users u ON u.id = b.sender_id
    LEFT JOIN broadcast_read_status rs
           ON rs.broadcast_id = b.id
          AND rs.user_id = ?
    WHERE b.recipient_role IN ('all_guides', 'all')
    ORDER BY b.created_at DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 7. Assigned mountains for this guide (MOVED UP for stats card) ──────────────────────────────────
$stmt = $pdo->prepare("
    SELECT m.id, m.name, m.image, m.location, m.difficulty, m.elevation
    FROM guide_mountains gm
    JOIN mountains m ON m.id = gm.mountain_id
    WHERE gm.guide_id = ?
");
$stmt->execute([$guide_id]);
$my_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 8. Handle EMERGENCY POST ──────────────────────────────────────────────
$emergency_sent = false;
$emergency_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'emergency') {
    $sit_type    = trim($_POST['situation_type'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $details     = trim($_POST['details'] ?? '');
    $severity    = 'critical';  // Emergency always critical
    
    // Map dropdown values to database ENUM
    $type_map = [
        'Medical Emergency' => 'medical',
        'Lost Hiker' => 'safety',
        'Weather Hazard' => 'weather',
        'Trail Accident' => 'trail',
        'Wildlife Encounter' => 'animal',
        'Other' => 'other'
    ];
    
    if ($sit_type && $details) {
        $title = "EMERGENCY: $sit_type";
        $desc  = $details . ($location ? " | Location: $location" : '');
        $db_type = $type_map[$sit_type] ?? 'other';
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts
                (title, description, location, type, severity, status,
                 reported_by, reporter_name, reporter_role, created_at)
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?, 'guide', NOW())
        ");
        $stmt->execute([$title, $desc, $location, $db_type, $severity, $user_id, $guide['name']]);
        $emergency_sent = true;
    } else {
        $emergency_error = 'Please fill in the required fields.';
    }
}

// ── Helper ─────────────────────────────────────────────────────────────────
function time_ago(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->days === 0)  return date('g:i A', strtotime($datetime));
    if ($diff->days === 1)  return 'Yesterday';
    if ($diff->days < 7)   return $diff->days . 'd ago';
    return date('M j', strtotime($datetime));
}

function fmt_date(string $d): string {
    return date('M j', strtotime($d));
}

// Avatar initials
$name_parts = explode(' ', $guide['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Next hike
$next_hike = $upcoming_bookings[0] ?? null;

// Handle AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $new_status = $_POST['status'] ?? 'safe';
    
    // Map status to database values
    $valid_statuses = ['safe', 'on_trail', 'completed'];
    $db_status = in_array($new_status, $valid_statuses) ? $new_status : 'safe';
    
    // Also update is_available based on status (1 = available, 0 = busy)
    $is_available = ($db_status === 'on_trail') ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE guides SET trail_status = ?, is_available = ? WHERE user_id = ?");
    $success = $stmt->execute([$db_status, $is_available, $user_id]);
    
    echo json_encode(['success' => $success, 'status' => $db_status]);
    exit;
}

// Handle marking broadcast as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    header('Content-Type: application/json');
    $broadcast_id = $_POST['broadcast_id'] ?? 0;
    
    // Check if already marked as read
    $stmt = $pdo->prepare("SELECT id FROM broadcast_read_status WHERE broadcast_id = ? AND user_id = ?");
    $stmt->execute([$broadcast_id, $user_id]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO broadcast_read_status (broadcast_id, user_id, user_role, is_read, read_at) VALUES (?, ?, 'guide', 1, NOW())");
        $stmt->execute([$broadcast_id, $user_id]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// ── Handle CONFIRM booking (UPDATED with payment instructions) ──
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
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }
        
        if ($bk['status'] === 'active') {
            echo json_encode(['success' => false, 'message' => 'Booking is already active']);
            exit;
        }
        
        // Get guide fee
        $stmt_fee = $pdo->prepare("
            SELECT CASE WHEN ? = 'overnight' THEN gm.guide_fee_overnight ELSE gm.guide_fee_day END as guide_fee
            FROM guide_mountain_rates gm
            WHERE gm.guide_id = (SELECT id FROM guides WHERE user_id = ?) AND gm.mountain_id = ?
            LIMIT 1
        ");
        $stmt_fee->execute([$bk['hike_type'], $user_id, $bk['mountain_id']]);
        $fee_row = $stmt_fee->fetch();
        $guideFee = $fee_row ? $fee_row['guide_fee'] : ($bk['hike_type'] === 'overnight' ? 1500 : 801);
        
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
        
        // Update booking
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
        
        $stmt_instructions = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role, action_data, is_system_announcement)
            VALUES (?, ?, AES_ENCRYPT(?, ?), 0, NOW(), 'guide', 'hiker', ?, 0)
        ");
        $stmt_instructions->execute([$user_id, $bk['user_id'], $instructionsMessage, MSG_AES_KEY, $actionData]);
        
        echo json_encode(['success' => true, 'message' => 'Booking confirmed! Payment instructions sent to hiker.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Handle CANCEL booking (UPDATED with proper messaging) ──
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
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
// ── NEW: Handle SEND MESSAGE to hiker ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message_to_hiker') {
    if (ob_get_length()) ob_clean();
    error_reporting(0);
    header('Content-Type: application/json');
    
    try {
        $booking_id = $_POST['booking_id'] ?? 0;
        $message_body = trim($_POST['message_body'] ?? '');
        
        if (empty($message_body)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }
        
        // Get hiker user_id from booking
        $stmt = $pdo->prepare("SELECT user_id, booking_number FROM bookings WHERE id = ? AND guide_id = ?");
        $stmt->execute([$booking_id, $guide_id]);
        $bk = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bk) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or permission denied']);
            exit;
        }
        
        $hiker_user_id = $bk['user_id'];
        $guide_name = $guide['name'];
        $bk_num = $bk['booking_number'];
        
        $full_message = "📩 Message from your Guide ({$guide_name}) regarding booking {$bk_num}:\n\n{$message_body}";
        
        $stmt_msg = $pdo->prepare("
            INSERT INTO messages 
                (sender_id, receiver_id, body, is_read, created_at, sender_role, receiver_role)
            VALUES (?, ?, AES_ENCRYPT(?, :key), 0, NOW(), 'guide', 'hiker')
        ");
        $stmt_msg->execute([$user_id, $hiker_user_id, $full_message]);
        $stmt_msg->bindParam(':key', MSG_AES_KEY);
        $stmt_msg->execute();
        
        echo json_encode(['success' => true, 'message' => 'Message sent to hiker']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

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
// ── 9. Fetch alerts created by this guide ─────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, title, description, location, type, severity, status, created_at,
           CASE 
               WHEN status = 'active' THEN 'Active'
               WHEN status = 'acknowledged' THEN 'Acknowledged'
               WHEN status = 'resolved' THEN 'Resolved'
               ELSE status
           END as status_label
    FROM alerts
    WHERE reported_by = ? OR reporter_role = 'guide'
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$my_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Dashboard</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    .guide-content {
      background:
        radial-gradient(ellipse 60% 40% at 80% 10%, rgba(16,6,0,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 10% 80%, rgba(16,6,0,0.03) 0%, transparent 60%);
    }
    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 18px 20px;
      box-shadow: var(--glass-shadow);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(0,0,0,0.09); }
    .stat-card-label { font-size: 0.62rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--ink-4); margin-bottom: 8px; }
    .stat-card-val { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: var(--ink); line-height: 1; letter-spacing: -1px; }
    .stat-card-sub { font-size: 0.68rem; color: var(--ink-4); margin-top: 5px; }
    .stat-card-icon { width: 30px; height: 30px; border-radius: var(--r-sm); background: var(--primary-soft); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; margin-bottom: 12px; }

    /* Bookings */
    .booking-list { display: flex; flex-direction: column; gap: 10px; }
    .booking-item { background: var(--glass-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); border-radius: var(--r-lg); padding: 14px 16px; display: flex; align-items: center; gap: 13px; transition: all 0.18s; box-shadow: var(--shadow-sm); }
    .booking-item:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); background: white; }
    .booking-mountain-thumb { width: 48px; height: 48px; border-radius: var(--r-md); background-size: cover; background-position: center; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
    .booking-info { flex: 1; min-width: 0; }
    .booking-name { font-size: 0.87rem; font-weight: 600; color: var(--ink); }
    .booking-meta { font-size: 0.7rem; color: var(--ink-4); margin-top: 3px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .booking-meta i { color: var(--ink-5); }
    
    /* Updated booking actions with three buttons */
    .booking-actions {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-shrink: 0;
    }
    .btn-icon-sm {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(0,0,0,0.04);
        border: 1px solid var(--line);
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }
    .btn-icon-sm:hover {
        transform: translateY(-1px);
    }
    .btn-view:hover { background: var(--primary-soft); color: var(--primary); border-color: var(--primary); }
    .btn-message:hover { background: #34599510; color: #345995; border-color: #345995; }
    .btn-cancel:hover { background: var(--red-lt); color: var(--red); border-color: var(--red); }
    .btn-confirm:hover { background: var(--green-lt); color: var(--green); border-color: var(--green); }

    /* Overlap warning */
    .overlap-warn {
      display: flex; align-items: center; gap: 5px;
      background: #fff3cd; border: 1px solid #ffc107;
      color: #856404; border-radius: 6px;
      padding: 4px 10px; font-size: 0.68rem; font-weight: 600;
      margin-top: 5px;
    }

    /* Status panel */
    .status-panel { background: var(--glass-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); border-radius: var(--r-xl); padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--glass-shadow); }
    .status-panel-title { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--ink-4); margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
    .status-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .status-chip { padding: 7px 16px; border-radius: 40px; border: 1.5px solid var(--line); font-size: 0.78rem; font-weight: 600; color: var(--ink-3); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.5); backdrop-filter: blur(6px); }
    .status-chip:hover { border-color: var(--primary); color: var(--primary); background: rgba(255,255,255,0.8); }
    .status-chip.active-safe  { background: var(--green-lt);    border-color: var(--green);   color: var(--green); }
    .status-chip.active-trail { background: var(--amber-lt);    border-color: var(--amber);   color: var(--amber); }
    .status-chip.active-done  { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }

    /* Announcements */
    .announcement-card { background: rgba(255,255,255,0.65); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.55); border-radius: var(--r-lg); padding: 14px 16px; margin-bottom: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); transition: all 0.18s; }
    .announcement-card:last-child { margin-bottom: 0; }
    .announcement-card.unread { border-left: 3px solid var(--primary); }
    .announcement-card:hover { background: white; box-shadow: var(--shadow-md); transform: translateY(-1px); }
    .announcement-header { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; }
    .announcement-icon { width: 26px; height: 26px; border-radius: var(--r-sm); background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; flex-shrink: 0; }
    .announcement-from { font-size: 0.68rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }
    .announcement-time { font-family: 'DM Mono', monospace; font-size: 0.6rem; color: var(--ink-5); margin-left: auto; }
    .announcement-text { font-size: 0.8rem; color: var(--ink-2); line-height: 1.55; }
    .unread-dot { width: 7px; height: 7px; background: var(--primary); border-radius: 50%; display: inline-block; margin-left: 4px; }

    /* Dashboard grid */
    .dash-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
    .dash-col { display: flex; flex-direction: column; gap: 20px; }

    /* Emergency button */
    .emergency-btn { display: flex; flex-direction: column; align-items: center; gap: 4px; background: rgba(184,49,42,0.9); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.18); color: white; border-radius: var(--r-lg); padding: 14px 22px; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all 0.18s; box-shadow: 0 4px 20px rgba(184,49,42,0.4); }
    .emergency-btn:hover { background: var(--red); transform: scale(1.03); box-shadow: 0 8px 28px rgba(184,49,42,0.5); }
    .emergency-btn i { font-size: 1.4rem; }
    .emergency-btn span { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }

    /* Live badge */
    .live-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(27,112,69,0.1); border: 1px solid rgba(27,112,69,0.2); color: var(--green); padding: 3px 10px; border-radius: 40px; font-size: 0.63rem; font-weight: 700; letter-spacing: 0.3px; }
    .live-dot { width: 5px; height: 5px; background: var(--green); border-radius: 50%; animation: pulse-dot 2s infinite; }
    @keyframes pulse-dot { 0%, 100% { opacity:1; transform:scale(1); } 50% { opacity:0.5; transform:scale(0.7); } }

    /* ── CALENDAR ── */
    .calendar-card { background: var(--glass-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); border-radius: var(--r-xl); padding: 18px 20px; box-shadow: var(--glass-shadow); }
    .cal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .cal-title { font-size: 0.85rem; font-weight: 700; color: var(--ink); }
    .cal-nav button { background: none; border: none; color: var(--ink-4); cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: all 0.15s; font-size: 0.8rem; }
    .cal-nav button:hover { background: var(--primary-soft); color: var(--primary); }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
    .cal-day-label { text-align: center; font-size: 0.55rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--ink-5); padding: 4px 0; }
    .cal-day { text-align: center; font-size: 0.9rem; padding: 12px 2px; border-radius: 8px; cursor: pointer; color: var(--ink-3); transition: all 0.12s; position: relative; }
    .cal-day.today { background: var(--primary-soft); color: var(--primary); font-weight: 700; }
    .cal-day.occupied { background: rgba(46, 125, 50, 0.15); color: var(--green); font-weight: 700; }
.cal-day.occupied:hover { background: rgba(46, 125, 50, 0.25); }
    .cal-day.other-month { opacity: 0.2; cursor: default; }
    .cal-day.occupied:hover { background: var(--green-lt); }
    .cal-day.selected { background: var(--primary) !important; color: white !important; }
    .cal-day.selected.occupied { color: white; }
    
    /* Dots */
.cal-dots { display: flex; justify-content: center; gap: 3px; margin-top: 4px; height: 6px; }
.cal-dot { width: 6px; height: 6px; border-radius: 50%; box-shadow: 0 0 4px rgba(0,0,0,0.1); }
.dot-active { background: var(--green); }
.dot-pending   { background: var(--amber); }
.dot-cancelled { background: var(--ink-5); opacity: 0.5; }
.dot-finished  { background: #000; }
    
    .cal-legend { display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap; }
    .cal-legend-item { display: flex; align-items: center; gap: 4px; font-size: 0.6rem; color: var(--ink-4); }
    .cal-legend-dot { width: 8px; height: 8px; border-radius: 50%; }

    .badge-blue { background: rgba(52, 89, 149, 0.1); color: #345995; }

    .detail-section { margin-bottom: 24px; }
.detail-section-title {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--ink-4);
    padding-bottom: 8px;
    border-bottom: 1px solid var(--line);
    margin-bottom: 16px;
}
.detail-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.detail-item { display: flex; flex-direction: column; gap: 4px; }
.detail-item.span2 { grid-column: span 2; }
.detail-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--ink-4);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.detail-val { font-size: 0.85rem; color: var(--ink); font-weight: 500; }

    .btn-success {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--green); color: white; border: none;
        padding: 10px 20px; border-radius: var(--r-md);
        font-weight: 700; cursor: pointer; transition: all 0.2s;
        font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
    }
    .btn-success:hover { background: #1a5c3a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(27,112,69,0.2); }
    
    .btn-danger {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--red); color: white; border: none;
        padding: 10px 20px; border-radius: var(--r-md);
        font-weight: 700; cursor: pointer; transition: all 0.2s;
        font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
    }
    .btn-danger:hover { background: #a02822; transform: translateY(-1px); }
    
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--ink-5); color: white; border: none;
        padding: 10px 20px; border-radius: var(--r-md);
        font-weight: 700; cursor: pointer; transition: all 0.2s;
        font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
    }
    
    .badge-green { background: rgba(30, 123, 72, 0.1); color: #1E7B48; }
    .badge-amber { background: rgba(230, 126, 34, 0.1); color: #c96a10; }
    .badge-gray { background: #eef2f8; color: #6e7483; }

    .hikers-table { width: 100%; border-collapse: collapse; margin-top: 8px; min-width: 500px; }
    .hikers-table th { text-align: left; font-size: 0.65rem; color: var(--ink-4); text-transform: uppercase; padding: 12px 10px; border-bottom: 1.5px solid var(--line); }
    .hikers-table td { font-size: 0.8rem; color: var(--ink-2); padding: 12px 10px; border-bottom: 1px solid var(--line); }
    .hikers-table tr:last-child td { border-bottom: none; }

    /* Message Modal Styles */
    .message-textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        resize: vertical;
        min-height: 100px;
    }
    .message-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-soft);
    }
    .char-count {
        font-size: 0.7rem;
        color: var(--ink-4);
        text-align: right;
        margin-top: 4px;
    }

@media (max-width: 600px) {
    .detail-grid { grid-template-columns: 1fr; }
    .detail-item.span2 { grid-column: span 1; }
}

    @media (max-width: 1024px) { .dash-grid { grid-template-columns: 1fr; } }
    @media (max-width: 640px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 440px) { .stats-row { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 560px) {
        .booking-actions { flex-direction: column; gap: 4px; }
        .btn-icon-sm { width: 28px; height: 28px; font-size: 0.7rem; }
    }

    /* ========== REDESIGNED TODAY CARD — DEEP BROWN, GREETING CARD FONT STYLES ========== */
    .today-hike-card {
      background: linear-gradient(135deg, #4a3320 0%, #2e1f12 100%);
      border-radius: 28px;
      margin-bottom: 28px;
      box-shadow: 0 20px 35px -12px rgba(0,0,0,0.35);
      border: 1px solid rgba(201,168,76,0.35);
      transition: transform 0.25s ease, box-shadow 0.3s ease;
      overflow: hidden;
      position: relative;
    }
    .today-hike-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 28px 40px -15px rgba(0,0,0,0.45);
    }
    .today-hike-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--gold), #f5d97a, var(--gold));
    }
    .today-banner {
      padding: 20px 28px 12px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      border-bottom: 1px solid rgba(201,168,76,0.25);
    }
    .banner-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    /* animated mountain icon — bigger and pulsating */
    .mountain-icon-pulse {
      background: rgba(201,168,76,0.22);
      width: 60px;
      height: 60px;
      border-radius: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      backdrop-filter: blur(8px);
      box-shadow: 0 0 0 0 rgba(201,168,76,0.6);
      animation: pulseMtnBrown 1.8s infinite;
    }
    @keyframes pulseMtnBrown {
      0% { box-shadow: 0 0 0 0 rgba(201,168,76,0.5); transform: scale(1);}
      70% { box-shadow: 0 0 0 14px rgba(201,168,76,0); transform: scale(1.03);}
      100% { box-shadow: 0 0 0 0 rgba(201,168,76,0); transform: scale(1);}
    }
    .banner-text {
      display: flex;
      flex-direction: column;
    }
    .today-eyebrow {
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #f5e2b0;
      opacity: 0.95;
      font-family: 'DM Sans', sans-serif;
    }
    .today-headline {
      font-size: 1.65rem;
      font-weight: 800;
      font-family: 'Playfair Display', serif;
      color: white;
      letter-spacing: -0.3px;
      line-height: 1.2;
      margin-top: 6px;
    }
    .today-countdown {
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(12px);
      padding: 8px 20px;
      border-radius: 60px;
      font-size: 0.85rem;
      font-weight: 700;
      color: #ffefb9;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      border: 1px solid rgba(201,168,76,0.6);
      font-family: 'DM Sans', sans-serif;
    }
    .today-countdown svg {
      width: 16px;
      height: 16px;
      stroke: #f9e2a1;
      fill: none;
      stroke-width: 2.2;
    }
    /* Main body - compact but readable */
    .today-body-compact {
      padding: 22px 28px 26px 28px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
    }
    .info-stack {
      display: flex;
      flex-direction: column;
      gap: 14px;
      flex: 2;
    }
    .meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      align-items: center;
    }
    .meta-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,250,235,0.12);
      padding: 6px 16px;
      border-radius: 50px;
      font-size: 0.8rem;
      font-weight: 600;
      color: #fdf3e0;
      font-family: 'DM Sans', sans-serif;
    }
    .meta-badge svg {
      width: 15px;
      height: 15px;
      stroke: var(--gold);
      fill: none;
      stroke-width: 2;
    }
    .hiker-chip {
      background: rgba(201,168,76,0.22);
      padding: 6px 14px;
      border-radius: 40px;
      font-size: 0.8rem;
      font-weight: 700;
      color: #ffe6b3;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-family: 'DM Sans', sans-serif;
    }
    .fee-chip {
      background: rgba(0,0,0,0.4);
      color: #ffefcf;
      font-weight: 800;
      font-size: 0.85rem;
      padding: 6px 14px;
      border-radius: 40px;
      letter-spacing: 0.3px;
      font-family: 'DM Sans', sans-serif;
    }
    .btn-start-hike-redesign {
      background: linear-gradient(105deg, #ffefc4, #e7bc6a);
      border: none;
      padding: 14px 34px;
      border-radius: 60px;
      font-weight: 800;
      font-size: 1rem;
      color: #2c1c0e;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      box-shadow: 0 10px 18px -8px rgba(0,0,0,0.4);
      font-family: 'DM Sans', sans-serif;
    }
    .btn-start-hike-redesign:hover {
      transform: scale(1.02);
      background: linear-gradient(105deg, #fff3df, #f5cd70);
      color: #1f1308;
      box-shadow: 0 16px 25px -10px black;
    }
    .btn-start-hike-redesign svg {
      width: 20px;
      height: 20px;
      fill: #3e2a1f;
    }
    .helper-text {
      font-size: 9px;
      text-align: center;
      margin-top: 8px;
      color: #e2cfaa;
      letter-spacing: 0.4px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
    }
    @media (max-width: 720px) {
      .today-body-compact { flex-direction: column; align-items: stretch; }
      .btn-start-hike-redesign { justify-content: center; }
      .today-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
    }

    /* GREETING CARD MOBILE OPTIMIZATION */
    @media (max-width: 580px) {
      .greeting-row { 
        flex-direction: row !important; 
        align-items: center !important; 
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        gap: 15px !important;
      }
      .greeting-right { 
        flex-direction: row !important; 
        gap: 8px !important; 
        align-items: center !important;
        margin-top: 0 !important;
      }
      .emergency-btn { 
        padding: 0 !important; 
        width: 44px !important; 
        height: 44px !important; 
        border-radius: 14px !important; 
        justify-content: center !important;
        gap: 0 !important;
        flex-shrink: 0 !important;
      }
      .emergency-btn span { display: none !important; }
      .emergency-btn i { font-size: 1.15rem !important; }
      
      .greeting-title { font-size: 1.3rem !important; line-height: 1.1 !important; }
      .greeting-message { font-size: 0.78rem !important; opacity: 0.8 !important; }
      .greeting-glass-pill, .greeting-date { display: none !important; }
    }
    /* Scrollable filtered bookings container */
#filteredBookingsList {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 8px;
}

#filteredBookingsList::-webkit-scrollbar {
    width: 6px;
}

#filteredBookingsList::-webkit-scrollbar-track {
    background: var(--line);
    border-radius: 10px;
}

#filteredBookingsList::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

/* Scrollable filtered bookings container */
#filteredBookingsList {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 8px;
}

#filteredBookingsList::-webkit-scrollbar {
    width: 6px;
}

#filteredBookingsList::-webkit-scrollbar-track {
    background: var(--line);
    border-radius: 10px;
}

#filteredBookingsList::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

/* Scrollable upcoming bookings container */
#upcomingBookingsList {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 8px;
}

#upcomingBookingsList::-webkit-scrollbar {
    width: 6px;
}

#upcomingBookingsList::-webkit-scrollbar-track {
    background: var(--line);
    border-radius: 10px;
}

#upcomingBookingsList::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

  </style>
</head>
<body>
<div class="guide-app">

  <!-- SIDEBAR -->
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none">
        <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity="0.9"/>
        <path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity="0.3"/>
      </svg>
      <div>
        <div class="sidebar-logo-text">LAKBAY</div>
        <div class="sidebar-logo-sub">Guide Portal</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.php" class="active"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.php"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.php"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-bookings.php"><i class="fas fa-shield-halved"></i> Bookings</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <div class="sidebar-section-label">Account</div>
      <ul>
        <li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li>
      </ul>
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
  <a href="../login-and-signup/login.php" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;text-decoration:none;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </a>
</div>
  </aside>

  <!-- MAIN -->
  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-right">
        <div class="topbar-time" id="liveTime"></div>
        <button class="topbar-icon-btn" title="Notifications"><i class="fas fa-bell"></i></button>
      </div>
    </div>

    <div class="guide-content">

      <?php if ($emergency_sent): ?>
      <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-size:0.82rem;">
        🚨 Emergency alert sent successfully.
      </div>
      <?php endif; ?>

      <!-- GREETING CARD -->
      <div class="greeting-card">
        <div class="greeting-row">
          <div class="greeting-left">
            <div class="greeting-eyebrow"><i class="fas fa-mountain" style="margin-right:5px;opacity:0.6;"></i>Guide Portal</div>
            <div class="greeting-title">Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>,<br>
              <span class="greeting-name"><?= htmlspecialchars(explode(' ', $guide['name'])[0]) ?> 👋</span>
            </div>
            <?php $upcoming_count = count($upcoming_bookings); ?>
            <div class="greeting-message">You have <strong style="color:white;font-weight:600;"><?= $upcoming_count ?> upcoming <?= $upcoming_count === 1 ? 'hike' : 'hikes' ?></strong> scheduled</div>
            <?php if ($next_hike): ?>
            <div class="greeting-glass-pill">
              <i class="fas fa-calendar-check" style="font-size:0.75rem;opacity:0.8;"></i>
              Next: <?= htmlspecialchars($next_hike['mountain_name']) ?> · <?= fmt_date($next_hike['hike_date']) ?>
            </div>
            <?php endif; ?>
            <div class="greeting-date" id="welcomeDate">
              <i class="fas fa-clock" style="font-size:0.6rem;"></i>
            </div>
          </div>
          <div class="greeting-right" style="display: flex; gap: 10px;">
    <button class="emergency-btn" id="emergencyBtn">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Emergency</span>
    </button>
    <button class="emergency-btn" id="viewAlertsBtn" style="background: rgba(42, 21, 0, 0.9); box-shadow: none;">
        <i class="fas fa-bell"></i>
        <span>My Alerts</span>
    </button>
</div>
        </div>
      </div>

      <!-- REDESIGNED TODAY HIKE CARD (Deep brown, greeting card font style, bright icons) -->
      <?php
      $today = date('Y-m-d');
      $stmt_today = $pdo->prepare("
          SELECT b.id, b.booking_number, b.hike_date, b.hike_type, b.number_of_hikers, b.status,
                 b.total_amount as total_fee, b.guide_id, b.user_id as hiker_id,
                 u.name AS hiker_name, m.name AS mountain_name
          FROM bookings b
          JOIN users u ON u.id = b.user_id
          JOIN mountains m ON b.mountain_id = m.id
          WHERE b.guide_id = ? AND b.hike_date = ? AND b.status IN ('active', 'confirmed')
          ORDER BY b.hike_date ASC LIMIT 1
      ");
      $stmt_today->execute([$guide_id, $today]);
      $today_hike = $stmt_today->fetch(PDO::FETCH_ASSOC);
      
      if ($today_hike):
          $demo_time = ($today_hike['hike_type'] === 'overnight') ? '16:00' : '07:30';
          $now_ts = time();
          $hike_ts = strtotime($today . ' ' . $demo_time);
          $countdown_html = '';
          if ($hike_ts > $now_ts) {
              $diff_sec = $hike_ts - $now_ts;
              $hrs = floor($diff_sec / 3600);
              $mins = floor(($diff_sec % 3600) / 60);
              $countdown_html = '<div class="today-countdown"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'.($hrs>0?$hrs.'h ':'').$mins.'m to go</div>';
          } else {
              $countdown_html = '<div class="today-countdown" style="background:#c9a84c; color:#2c1c0e;"><svg viewBox="0 0 24 24" style="stroke:#2c1c0e;"><polygon points="5 3 19 12 5 21 5 3"/></svg>LIVE · Active Now</div>';
          }
          $start_url = "guide-map.php?booking_id={$today_hike['id']}";
      ?>
      <div class="today-hike-card">
        <div class="today-banner">
          <div class="banner-left">
            <div class="mountain-icon-pulse">⛰️</div>
            <div class="banner-text">
              <div class="today-eyebrow"><i class="fas fa-route" style="margin-right: 6px;"></i> ACTIVE HIKE · GUIDE PRIORITY</div>
              <div class="today-headline"><?= htmlspecialchars($today_hike['mountain_name']) ?></div>
            </div>
          </div>
          <?= $countdown_html ?>
        </div>
        <div class="today-body-compact">
          <div class="info-stack">
            <div class="meta-row">
              <div class="meta-badge"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?= date('M j, Y', strtotime($today_hike['hike_date'])) ?> · <?= $demo_time ?></div>
              <div class="meta-badge"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> <?= (int)$today_hike['number_of_hikers'] ?> hikers</div>
              <div class="meta-badge"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> #<?= htmlspecialchars($today_hike['booking_number']) ?></div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
              <div class="hiker-chip"><i class="fas fa-user-check"></i> <?= htmlspecialchars($today_hike['hiker_name']) ?></div>
              <div class="fee-chip"><i class="fas fa-coins"></i> ₱ <?= number_format($today_hike['total_fee'], 0) ?> (total)</div>
              <div class="hiker-chip"><i class="fas fa-flag-checkered"></i> <?= ucfirst(str_replace('_', ' ', $today_hike['hike_type'])) ?></div>
            </div>
          </div>
          <div>
            <a href="<?= $start_url ?>" class="btn-start-hike-redesign"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg> START HIKE</a>
            <div class="helper-text"><i class="fas fa-satellite-dish"></i> GPS & checkpoint tracking enabled</div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="today-hike-card" style="background: linear-gradient(135deg, #4a3828, #2f241b);">
        <div class="today-banner">
          <div class="banner-left"><div class="mountain-icon-pulse" style="animation: none;">🏔️</div><div class="banner-text"><div class="today-eyebrow">📅 NO ACTIVE HIKE TODAY</div><div class="today-headline" style="font-size: 1.3rem;">Ready for next adventure</div></div></div>
        </div>
        <div class="today-body-compact"><div class="info-stack"><div style="color:#f0e2cd;">Relax or prepare gear — no scheduled hikes for today.</div></div><a href="guide-map.php" class="btn-start-hike-redesign" style="background:#5e4a34;">View Map</a></div>
      </div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
          <div class="stat-card-label">Total Bookings</div>
          <div class="stat-card-val"><?= (int)$stats['total_bookings'] ?></div>
          <div class="stat-card-sub">assigned to you</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-person-hiking"></i></div>
          <div class="stat-card-label">Total Hikers</div>
          <div class="stat-card-val"><?= (int)$stats['total_hikers'] ?></div>
          <div class="stat-card-sub">across all bookings</div>
        </div>
        <div class="stat-card">
    <div class="stat-card-icon"><i class="fas fa-mountain"></i></div>
    <div class="stat-card-label">My Mountains</div>
    <div style="margin-top: 8px;">
        <?php if (empty($my_mountains)): ?>
            <div class="stat-card-sub" style="font-size: 0.7rem;">No mountains assigned yet</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <?php foreach ($my_mountains as $mt): ?>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem;">
                        <i class="fas fa-tree" style="color: var(--primary); font-size: 0.65rem;"></i>
                        <span style="font-weight: 500;"><?= htmlspecialchars($mt['name']) ?></span>
                        <span style="font-size: 0.6rem; color: var(--ink-4);">(<?= htmlspecialchars($mt['difficulty']) ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
      </div>

      <div class="dash-grid">
        <div class="dash-col">

          <!-- STATUS UPDATE -->
          <div class="status-panel">
            <div class="status-panel-title">
              <span style="width:6px;height:6px;background:var(--green);border-radius:50%;display:inline-block;box-shadow:0 0 0 3px var(--green-lt);"></span>
              Current Trail Status
            </div>
            <div class="status-chips">
              <button class="status-chip <?= ($guide['trail_status'] === 'safe' || empty($guide['trail_status'])) ? 'active-safe' : '' ?>" id="sc-safe" onclick="setStatus('safe')">
                <i class="fas fa-shield-check"></i> Safe — At Basecamp
              </button>
              <button class="status-chip <?= ($guide['trail_status'] === 'on_trail') ? 'active-trail' : '' ?>" id="sc-on_trail" onclick="setStatus('on_trail')">
                <i class="fas fa-person-hiking"></i> On Trail
              </button>
              <button class="status-chip <?= ($guide['trail_status'] === 'completed') ? 'active-done' : '' ?>" id="sc-completed" onclick="setStatus('completed')">
                <i class="fas fa-flag-checkered"></i> Hike Completed
              </button>
            </div>
          </div>

                 <!-- UPCOMING BOOKINGS -->
          <div>
            <div class="section-header">
              <div class="section-title">Upcoming Bookings</div>
              <a href="guide-communication.php" class="see-all">See all <i class="fas fa-arrow-right" style="font-size:0.6rem;"></i></a>
            </div>
            <div class="booking-list" id="upcomingBookingsList">
              <?php if (empty($upcoming_bookings)): ?>
                <div style="text-align:center;padding:30px;color:var(--ink-4);font-size:0.82rem;">
                  <i class="fas fa-calendar-xmark" style="font-size:1.8rem;margin-bottom:8px;display:block;opacity:0.3;"></i>
                  No upcoming bookings
                </div>
              <?php else: ?>
                <?php foreach ($upcoming_bookings as $bk): 
    // Check if this pending booking overlaps with any active booking on same date AND same time
    $has_overlap = false;
    $overlap_message = '';
    
    if ($bk['status'] === 'pending') {
        foreach ($active_bookings as $ab) {
            if ($bk['hike_date'] === $ab['hike_date']) {
                $has_overlap = true;
                // Check if times are similar/overlapping
                if (!empty($bk['start_time']) && !empty($ab['start_time'])) {
                    $bk_time = substr($bk['start_time'], 0, 5);
                    $ab_time = substr($ab['start_time'], 0, 5);
                    $overlap_message = " SIMILAR/OVERLAPPING SCHEDULE - You have another booking at {$ab_time} on this date!";
                } else {
                    $overlap_message = " SIMILAR/OVERLAPPING SCHEDULE - You have another booking on this date!";
                }
                break;
            }
        }
    }
    
    $thumb_bg = !empty($bk['mountain_image']) ? $bk['mountain_image'] : 'https://images.unsplash.com/photo-1613144492511-59984f1cdeb3?w=200';
    $badge_class = $bk['status'] === 'active' ? 'badge badge-green' : ($bk['status'] === 'pending' ? 'badge badge-amber' : 'badge');
    $display_time = !empty($bk['start_time']) ? date('g:i A', strtotime($bk['start_time'])) : 'Time TBD';
?>
<div class="booking-item" data-date="<?= $bk['hike_date'] ?>" style="<?= $has_overlap ? 'border-left: 3px solid #ffc107;' : '' ?>">
    <div class="booking-mountain-thumb" style="background-image:url('<?= htmlspecialchars($thumb_bg) ?>');"></div>
    <div class="booking-info">
        <div class="booking-name"><?= htmlspecialchars($bk['hiker_name']) ?></div>
        <div class="booking-meta">
            <span><i class="fas fa-mountain"></i> <?= htmlspecialchars($bk['mountain_name']) ?></span>
            <span><i class="fas fa-calendar"></i> <?= fmt_date($bk['hike_date']) ?></span>
            <span><i class="fas fa-clock"></i> <?= $display_time ?></span>
            <span><i class="fas fa-users"></i> <?= (int)$bk['number_of_hikers'] ?> pax</span>
            <span><i class="fas fa-tag"></i> <?= ucfirst(str_replace('_', ' ', $bk['hike_type'])) ?></span>
        </div>
        <div style="font-family: 'DM Mono', monospace; font-size: 0.65rem; color: var(--ink-4); margin-top: 6px;">
            <i class="fas fa-ticket-alt"></i> Booking ID: <?= htmlspecialchars($bk['booking_number']) ?>
        </div>
        <div style="margin-top:6px;">
            <span class="<?= $badge_class ?>">
                <i class="fas fa-circle" style="font-size:0.35rem;"></i>
                <?= ucfirst($bk['status']) ?>
            </span>
        </div>
        <?php if ($has_overlap && $overlap_message): ?>
        <div class="overlap-warn" style="margin-top: 8px;">
            <i class="fas fa-triangle-exclamation"></i>
            <?= $overlap_message ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="booking-actions">
        <button class="btn-icon-sm btn-view" title="View Details" onclick="viewBookingDetails(<?= $bk['id'] ?>, '<?= htmlspecialchars($bk['booking_number']) ?>')">
            <i class="fas fa-eye"></i>
        </button>
        <button class="btn-icon-sm btn-message" title="Send Message" onclick="location.href='guide-communication.php?user_id=<?= $bk['hiker_user_id'] ?>'">
            <i class="fas fa-comment-dots"></i>
        </button>
    </div>
</div>
<?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- FILTERED BOOKINGS DISPLAY (shows when clicking calendar) -->
          <div id="filteredBookingsContainer" style="display: none;">
            <div class="section-header">
              <div class="section-title" id="filteredBookingsTitle"></div>
              <button class="see-all" onclick="closeFilteredView()" style="background:none;border:none;color:var(--primary);cursor:pointer;">
                <i class="fas fa-times"></i> Close
              </button>
            </div>
            <div id="filteredBookingsList" class="booking-list"></div>
          </div>

          <!-- CALENDAR VIEW -->
          <div>
            <div class="section-header">
              <div class="section-title">My Schedule Calendar</div>
            </div>
            <div class="calendar-card">
              <div class="cal-header">
                <button class="cal-nav" onclick="calPrev()"><i class="fas fa-chevron-left"></i></button>
                <div class="cal-title" id="calTitle"></div>
                <button class="cal-nav" onclick="calNext()"><i class="fas fa-chevron-right"></i></button>
              </div>
              <div class="cal-grid" id="calGrid"></div>
              <div class="cal-legend">
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:var(--primary);"></div> Today</div>
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:var(--green);"></div> Confirmed</div>
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:var(--amber);"></div> Pending</div>
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:var(--ink-5);"></div> Cancelled</div>
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#000;"></div> Finished</div>
              </div>
            </div>
          </div>


        </div>

                <!-- RIGHT COL -->
        <div class="dash-col">
          <div>
            <div class="section-header">
              <div class="section-title">Admin Broadcasts</div>
              <div class="live-badge"><span class="live-dot"></span> Live</div>
            </div>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
              <?php if (empty($broadcasts)): ?>
                <div style="text-align:center;padding:30px;color:var(--ink-4);font-size:0.82rem;">
                  <i class="fas fa-bullhorn" style="font-size:1.8rem;margin-bottom:8px;display:block;opacity:0.3;"></i>
                  No broadcasts yet
                </div>
              <?php else: ?>
                <?php foreach ($broadcasts as $bc): ?>
                <div class="announcement-card <?= !$bc['is_read'] ? 'unread' : '' ?>" data-id="<?= $bc['id'] ?>">
                  <div class="announcement-header">
                    <div class="announcement-icon"><i class="fas fa-bullhorn"></i></div>
                    <span class="announcement-from">
                      <?= htmlspecialchars($bc['sender_name']) ?>
                      <?php if (!$bc['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
                    </span>
                    <span class="announcement-time"><?= time_ago($bc['created_at']) ?></span>
                  </div>
                  <div class="announcement-text"><?= htmlspecialchars($bc['message']) ?></div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- PERFORMANCE INSIGHTS SECTION - MOVED INSIDE RIGHT COL -->
          <div class="status-panel" style="margin-bottom: 24px;">
            <div class="status-panel-title">
              <i class="fas fa-chart-line" style="margin-right: 8px;"></i>
              Performance Insights
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
              <?php
              // Calculate insights (keep all the PHP code)
              $total_completed = 0;
              
              $stmt_insight = $pdo->prepare("SELECT status FROM bookings WHERE guide_id = ?");
              $stmt_insight->execute([$guide_id]);
              $all_guide_bookings = $stmt_insight->fetchAll(PDO::FETCH_ASSOC);
              $total_completed = count(array_filter($all_guide_bookings, function($b) { return $b['status'] === 'finished'; }));
              $completion_rate = $stats['total_bookings'] > 0 ? round(($total_completed / $stats['total_bookings']) * 100) : 0;
              
              // Get average rating from guide_reviews
              $stmt_insight = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM guide_reviews WHERE guide_id = ? AND status = 'approved'");
              $stmt_insight->execute([$guide_id]);
              $review_data = $stmt_insight->fetch(PDO::FETCH_ASSOC);
              $avg_rating = $review_data['avg_rating'] ? round($review_data['avg_rating'], 1) : 0;
              $review_count = $review_data['review_count'] ?? 0;
              
              // Get upcoming count
              $upcoming_count = count($upcoming_bookings);
              
              // Generate insight messages
              $insights = [];
              if ($completion_rate >= 80) {
                $insights[] = ['icon' => '🎯', 'text' => "Great completion rate! You've finished {$completion_rate}% of your assigned hikes."];
              } elseif ($completion_rate >= 50) {
                $insights[] = ['icon' => '📈', 'text' => "You're on track with {$completion_rate}% completion rate. Keep going!"];
              } else {
                $insights[] = ['icon' => '🚀', 'text' => "Focus on completing more hikes to build your reputation."];
              }
              
              if ($avg_rating >= 4.5 && $review_count > 0) {
                $insights[] = ['icon' => '⭐', 'text' => "Excellent rating of {$avg_rating}/5 from {$review_count} reviews! Hikers love you."];
              } elseif ($avg_rating >= 3.5 && $review_count > 0) {
                $insights[] = ['icon' => '👍', 'text' => "Good rating of {$avg_rating}/5. Keep exceeding expectations!"];
              } elseif ($review_count > 0) {
                $insights[] = ['icon' => '💪', 'text' => "Your rating is {$avg_rating}/5. Focus on communication and safety to improve."];
              }
              
              if ($upcoming_count > 3) {
                $insights[] = ['icon' => '📅', 'text' => "Busy schedule ahead! You have {$upcoming_count} upcoming hikes."];
              } elseif ($upcoming_count == 0) {
                $insights[] = ['icon' => '📢', 'text' => "No upcoming hikes. Make yourself available to get more bookings!"];
              }
              
              if ($stats['total_hikers'] > 20) {
                $insights[] = ['icon' => '👥', 'text' => "You've guided {$stats['total_hikers']} hikers! Amazing experience builder."];
              }
              ?>
              <?php foreach ($insights as $insight): ?>
              <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: rgba(255,255,255,0.5); border-radius: var(--r-md);">
                <div style="font-size: 1.4rem;"><?= $insight['icon'] ?></div>
                <div style="font-size: 0.75rem; color: var(--ink-2); line-height: 1.4;"><?= htmlspecialchars($insight['text']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

  <!-- BOTTOM NAV -->
  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.php" class="bnav-item active"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.php" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.php" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-bookings.php" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Bookings</span></a>
      <a href="guide-profile.php" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<!-- EMERGENCY MODAL -->
<div class="modal-overlay" id="emergencyModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);"><i class="fas fa-triangle-exclamation" style="margin-right:8px;"></i>Emergency Alert</div>
      <button class="modal-close" onclick="closeModal('emergencyModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST" action="guide-dashboard.php">
      <input type="hidden" name="action" value="emergency">
      <div class="modal-body">
        <p style="font-size:0.82rem;color:var(--ink-3);margin-bottom:16px;line-height:1.65;background:var(--red-lt);padding:10px 14px;border-radius:var(--r-md);border-left:3px solid var(--red);">This will immediately notify the admin and all emergency contacts.</p>
        <div class="form-group">
          <label class="form-label">Situation Type <span style="color:var(--red);">*</span></label>
          <select class="form-control" name="situation_type" required>
            <option value="">— Select —</option>
            <option>Medical Emergency</option>
            <option>Lost Hiker</option>
            <option>Weather Hazard</option>
            <option>Trail Accident</option>
            <option>Wildlife Encounter</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Location / Trail Point</label>
          <input type="text" class="form-control" name="location" placeholder="e.g. Ridge junction, km 3.5">
        </div>
        <div class="form-group">
          <label class="form-label">Details <span style="color:var(--red);">*</span></label>
          <textarea class="form-control" name="details" rows="3" placeholder="Describe the emergency…" required></textarea>
        </div>
        <?php if ($emergency_error): ?>
        <div style="color:var(--red);font-size:0.78rem;margin-top:8px;"><?= htmlspecialchars($emergency_error) ?></div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('emergencyModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-paper-plane"></i> Send Alert</button>
      </div>
    </form>
  </div>
</div>

<!-- ALERTS MODAL -->
<div class="modal-overlay" id="alertsModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-bell"></i> My Emergency Alerts</div>
            <button class="modal-close" onclick="closeModal('alertsModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
            <?php if (empty($my_alerts)): ?>
                <div style="text-align:center;padding:40px;color:var(--ink-4);">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>No alerts reported yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($my_alerts as $alert): ?>
                <div class="announcement-card" style="margin-bottom: 12px;">
                    <div class="announcement-header">
                        <div class="announcement-icon" style="background: <?= 
                            $alert['severity'] == 'critical' ? 'var(--red)' : 
                            ($alert['severity'] == 'high' ? 'var(--amber)' : 'var(--primary)') 
                        ?>;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <span class="announcement-from"><?= htmlspecialchars($alert['type']) ?> · <?= $alert['severity'] ?></span>
                        <span class="announcement-time"><?= time_ago($alert['created_at']) ?></span>
                    </div>
                    <div class="announcement-text">
                        <strong><?= htmlspecialchars($alert['title']) ?></strong><br>
                        <?= htmlspecialchars($alert['description']) ?>
                        <?php if ($alert['location']): ?>
                            <br><small>📍 <?= htmlspecialchars($alert['location']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 8px;">
                        <span class="badge badge-<?= 
                            $alert['status'] == 'active' ? 'amber' : 
                            ($alert['status'] == 'acknowledged' ? 'blue' : 'gray') 
                        ?>">
                            <?= $alert['status_label'] ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeModal('alertsModal')">Close</button>
        </div>
    </div>
</div>

<!-- BOOKING DETAILS MODAL -->
<div class="modal-overlay" id="bookingDetailsModal">
    <div class="modal" style="max-width: 650px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-calendar-check"></i> Booking Details</div>
            <button class="modal-close" onclick="closeModal('bookingDetailsModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="bookingDetailsBody" style="max-height: 500px; overflow-y: auto;">
            <!-- Content loaded via JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeModal('bookingDetailsModal')">Close</button>
        </div>
    </div>
</div>


<!-- CANCEL MODAL -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red);"><i class="fas fa-times-circle"></i> Cancel Booking</div>
            <button class="modal-close" onclick="closeModal('cancelModal')"><i class="fas fa-xmark"></i></button>
        </div>
        <form id="cancelForm" onsubmit="cancelBooking(event)">
            <input type="hidden" id="cancelBookingId" value="">
            <div class="modal-body">
                <p style="margin-bottom: 16px;">Are you sure you want to cancel booking <strong id="cancelBookingNumber"></strong>?</p>
                <div class="form-group">
                    <label class="form-label">Reason for cancellation</label>
                    <textarea id="cancelReason" class="message-textarea" rows="3" placeholder="Provide a reason for cancelling this booking..." maxlength="300" required></textarea>
                    <div class="char-count"><span id="cancelCharCount">0</span>/300 characters</div>
                </div>
                <p class="overlap-warn" style="margin-top: 12px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    This action cannot be undone. The hiker will be notified.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('cancelModal')">Keep Booking</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── Calendar data from PHP ──────────────────────────────────────────────────
const occupiedDates = <?= $occupied_json ?>;
const calendarBookings = <?= $calendar_json ?>;

// ── Calendar ────────────────────────────────────────────────────────────────
const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
let calYear, calMonth;
const today = new Date();

function initCal() {
  calYear  = today.getFullYear();
  calMonth = today.getMonth();
  renderCal();
}

function renderCal() {
  document.getElementById('calTitle').textContent = MONTHS[calMonth] + ' ' + calYear;
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  DAYS.forEach(d => {
    const lbl = document.createElement('div');
    lbl.className = 'cal-day-label';
    lbl.textContent = d;
    grid.appendChild(lbl);
  });
  const first = new Date(calYear, calMonth, 1).getDay();
  const last  = new Date(calYear, calMonth + 1, 0).getDate();
  for (let i = 0; i < first; i++) {
    const blank = document.createElement('div');
    blank.className = 'cal-day other-month';
    grid.appendChild(blank);
  }
  for (let d = 1; d <= last; d++) {
    const cell = document.createElement('div');
    const iso  = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    let cls = 'cal-day';
    if (d === today.getDate() && calMonth === today.getMonth() && calYear === today.getFullYear()) cls += ' today';
    if (occupiedDates.includes(iso)) {
      cls += ' occupied';
    }
    cell.className = cls;
    cell.textContent = d;
    cell.dataset.iso = iso;
    
    // Status Dots - show a dot for EACH booking
const dayBookings = calendarBookings.filter(b => b.hike_date === iso);
if (dayBookings.length > 0) {
    const dotsContainer = document.createElement('div');
    dotsContainer.className = 'cal-dots';
    
    // Show a dot for EACH booking
    dayBookings.forEach(booking => {
        const dot = document.createElement('div');
        let dotColor = '';
        if (booking.status === 'active') dotColor = 'dot-active';
        else if (booking.status === 'pending') dotColor = 'dot-pending';
        else if (booking.status === 'cancelled') dotColor = 'dot-cancelled';
        else if (booking.status === 'finished') dotColor = 'dot-finished';
        dot.className = `cal-dot ${dotColor}`;
        dotsContainer.appendChild(dot);
    });
    cell.appendChild(dotsContainer);
    
    // Tooltip
    const summary = dayBookings.map(b => `${b.mountain_name} (${b.status})`).join(', ');
    cell.title = summary;
}

    // Highlight selected date
    if (selectedDate === iso) cell.classList.add('selected');

    cell.addEventListener('click', () => {
      if (cell.classList.contains('other-month')) return;
      filterByDate(iso);
    });
    grid.appendChild(cell);
  }
}

let selectedDate = null;
let allBookings = <?= $all_bookings_json ?>;
function filterByDate(iso) {
    const header = document.querySelector('.section-title');
    const upcomingContainer = document.getElementById('upcomingBookingsList');
    const filteredContainer = document.getElementById('filteredBookingsContainer');
    const filteredList = document.getElementById('filteredBookingsList');
    const filteredTitle = document.getElementById('filteredBookingsTitle');
    
    if (selectedDate === iso) {
        // Unclick: Reset to show upcoming bookings
        selectedDate = null;
        upcomingContainer.style.display = 'flex';
        filteredContainer.style.display = 'none';
        header.textContent = "Upcoming Bookings";
    } else {
        // Click: Show all bookings for this date
        selectedDate = iso;
        
        // Hide upcoming container, show filtered container
        upcomingContainer.style.display = 'none';
        filteredContainer.style.display = 'block';
        
        // Get all bookings for this date
        const dayBookings = allBookings.filter(b => b.hike_date === iso);
        
        const dateStr = new Date(iso + 'T00:00:00').toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
        const statusCounts = {
            active: dayBookings.filter(b => b.status === 'active').length,
            pending: dayBookings.filter(b => b.status === 'pending').length,
            finished: dayBookings.filter(b => b.status === 'finished').length,
            cancelled: dayBookings.filter(b => b.status === 'cancelled').length
        };
        
        filteredTitle.innerHTML = `Bookings for ${dateStr} 
            <span style="font-size:0.7rem; font-weight:normal; color:var(--ink-4);">
                (${statusCounts.active} confirmed, ${statusCounts.pending} pending, 
                 ${statusCounts.finished} finished, ${statusCounts.cancelled} cancelled)
            </span>`;
        
        if (dayBookings.length === 0) {
            filteredList.innerHTML = `<div style="text-align:center;padding:40px;color:var(--ink-4);">
                <i class="fas fa-calendar-xmark" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                No bookings found for this date.
            </div>`;
        } else {
            // Sort by status (pending first, then active, then others)
            const sortedBookings = [...dayBookings].sort((a, b) => {
                const order = { 'pending': 1, 'active': 2, 'finished': 3, 'cancelled': 4 };
                return (order[a.status] || 5) - (order[b.status] || 5);
            });
            
            // Get active bookings on this date for overlap detection
            const activeOnThisDate = sortedBookings.filter(b => b.status === 'active');
            
            filteredList.innerHTML = sortedBookings.map(bk => {
                const thumb_bg = !empty(bk.mountain_image) ? bk.mountain_image : 'https://images.unsplash.com/photo-1613144492511-59984f1cdeb3?w=200';
                const badge_class = match(bk.status, {
                    'active': 'badge badge-green',
                    'pending': 'badge badge-amber',
                    'finished': 'badge',
                    'cancelled': 'badge'
                });
                const statusIcon = bk.status === 'finished' ? '<i class="fas fa-check-circle"></i>' : 
                                  (bk.status === 'cancelled' ? '<i class="fas fa-ban"></i>' : 
                                  (bk.status === 'active' ? '<i class="fas fa-check-circle"></i>' : 
                                  '<i class="fas fa-clock"></i>'));
                
                const displayTime = bk.start_time ? bk.start_time.substring(0,5) : 'TBD';
                const displayTimeFormatted = bk.start_time ? new Date('2000-01-01T' + bk.start_time).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'Time TBD';
                
                // Check for overlap (only for pending bookings)
                let overlapWarning = '';
                if (bk.status === 'pending' && activeOnThisDate.length > 0) {
                    // Check if any active booking has same or similar time
                    let hasOverlap = false;
                    let activeTime = '';
                    for (let ab of activeOnThisDate) {
                        if (ab.start_time) {
                            const abTime = ab.start_time.substring(0,5);
                            const bkTime = bk.start_time ? bk.start_time.substring(0,5) : '';
                            if (bkTime === abTime) {
                                hasOverlap = true;
                                activeTime = abTime;
                                break;
                            } else if (bkTime && abTime) {
                                // Check if times are within 2 hours
                                const bkHour = parseInt(bkTime.split(':')[0]);
                                const abHour = parseInt(abTime.split(':')[0]);
                                if (Math.abs(bkHour - abHour) <= 2) {
                                    hasOverlap = true;
                                    activeTime = abTime;
                                    break;
                                }
                            }
                        }
                    }
                    if (hasOverlap) {
                        overlapWarning = `<div class="overlap-warn" style="margin-top: 8px;">
                            <i class="fas fa-triangle-exclamation"></i>
                             SIMILAR/OVERLAPPING SCHEDULE - You have a confirmed booking at ${activeTime} on this date!
                        </div>`;
                    }
                }
                
                // Show appropriate action buttons based on status
                let actionButtons = '';
if (bk.status === 'active') {
    actionButtons = `
        <button class="btn-icon-sm btn-message" title="Send Message" onclick="location.href='guide-communication.php?user_id=${bk.hiker_user_id}'">
            <i class="fas fa-comment-dots"></i>
        </button>
    `;
} else if (bk.status === 'finished') {
    // ADD THIS FOR FINISHED BOOKINGS
    actionButtons = `
        <button class="btn-icon-sm btn-view" title="View Hike Summary" onclick="viewGuideActivity(${bk.id})">
            <i class="fas fa-chart-line"></i>
        </button>
       
    `;
} else if (bk.status === 'pending') {
    actionButtons = `
         <button class="btn-icon-sm btn-message" title="Send Message" onclick="location.href='guide-communication.php?user_id=${bk.hiker_user_id}'">
            <i class="fas fa-comment-dots"></i>
        </button>
    `;
}
                
                return `
                <div class="booking-item" style="${bk.status === 'pending' && activeOnThisDate.length > 0 ? 'border-left: 3px solid #ffc107;' : ''}">
                    <div class="booking-mountain-thumb" style="background-image:url('${escapeHtml(thumb_bg)}');"></div>
                    <div class="booking-info">
                        <div class="booking-name">${escapeHtml(bk.hiker_name)}</div>
                        <div class="booking-meta">
                            <span><i class="fas fa-mountain"></i> ${escapeHtml(bk.mountain_name)}</span>
                            <span><i class="fas fa-calendar"></i> ${fmtDate2(bk.hike_date)}</span>
                            <span><i class="fas fa-clock"></i> ${displayTimeFormatted}</span>
                            <span><i class="fas fa-users"></i> ${bk.number_of_hikers} pax</span>
                            <span><i class="fas fa-tag"></i> ${bk.hike_type === 'day_hike' ? 'Day Hike' : (bk.hike_type === 'overnight' ? 'Overnight' : bk.hike_type)}</span>
                        </div>
                        <div style="font-family: 'DM Mono', monospace; font-size: 0.65rem; color: var(--ink-4); margin-top: 6px;">
                            <i class="fas fa-ticket-alt"></i> Booking ID: ${escapeHtml(bk.booking_number)}
                        </div>
                        <div style="margin-top:6px;">
                            <span class="${badge_class}">
                                ${statusIcon}
                                ${bk.status.charAt(0).toUpperCase() + bk.status.slice(1)}
                            </span>
                        </div>
                        ${overlapWarning}
                    </div>
                    <div class="booking-actions">
                        <button class="btn-icon-sm btn-view" title="View Details" onclick="viewBookingDetails(${bk.id}, '${escapeHtml(bk.booking_number)}')">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${actionButtons}
                    </div>
                </div>`;
            }).join('');
        }
        
        header.textContent = "Upcoming Bookings"; // Keep original header text
    }
    renderCal(); // Re-render to update 'selected' class
}

function fmtDate2(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function closeFilteredView() {
    selectedDate = null;
    const upcomingContainer = document.getElementById('upcomingBookingsList');
    const filteredContainer = document.getElementById('filteredBookingsContainer');
    const header = document.querySelector('.section-title');
    
    upcomingContainer.style.display = 'flex';
    filteredContainer.style.display = 'none';
    header.textContent = "Upcoming Bookings";
    renderCal();
}

function match(status, classes) {
    return classes[status] || 'badge';
}

function empty(str) {
    return !str || str === '';
}

function calPrev() { calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCal(); }
function calNext() { calMonth++; if (calMonth > 11) { calMonth = 0;  calYear++; } renderCal(); }
initCal();

// ── Status ──────────────────────────────────────────────────────────────────
let currentStatus = '<?= $guide['trail_status'] ?: 'safe' ?>';
function setStatus(s) {
    currentStatus = s;
    document.getElementById('sc-safe').className     = 'status-chip' + (s === 'safe'     ? ' active-safe'  : '');
    document.getElementById('sc-on_trail').className = 'status-chip' + (s === 'on_trail' ? ' active-trail' : '');
    document.getElementById('sc-completed').className = 'status-chip' + (s === 'completed' ? ' active-done'  : '');
    
    // Send AJAX to update database
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'update_status',
            status: s
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const labels = { safe:' Status: Available (At Basecamp)', on_trail:'🚶 Status: On Trail', completed:'🏁 Status: Hike Completed' };
            showToast(labels[s]);
        } else {
            showToast(' Failed to update status');
        }
    })
    .catch(() => showToast(' Network error updating status'));
}

// ── Modal ───────────────────────────────────────────────────────────────────
document.getElementById('emergencyBtn').addEventListener('click', () => openModal('emergencyModal'));
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) closeModal(o.id); }));

// ── Toast ────────────────────────────────────────────────────────────────────
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

// ── Live clock ───────────────────────────────────────────────────────────────
function updateTime() {
  const d  = new Date();
  const t  = d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
  const dt = d.toLocaleDateString('en-PH', {weekday:'long', month:'long', day:'numeric', year:'numeric'});
  document.getElementById('liveTime').textContent = t;
  document.getElementById('welcomeDate').innerHTML = `<i class="fas fa-clock" style="font-size:0.6rem;"></i> ${dt}`;
}
updateTime(); setInterval(updateTime, 1000);
document.addEventListener('DOMContentLoaded', function() {
    const broadcastCards = document.querySelectorAll('.announcement-card.unread');
    broadcastCards.forEach(card => {
        const broadcastId = card.dataset.id;
        if (broadcastId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'mark_read',
                    broadcast_id: broadcastId
                })
            });
            card.classList.remove('unread');
        }
    });
});

function viewBookingDetails(bookingId, bookingNumber) {
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

            // Calculate guide fee based on hike type (use fee from database if available)
            let guideFee = booking.guide_fee || (booking.hike_type === 'overnight' ? 1500 : 801);
            let downpaymentPaid = parseFloat(booking.downpayment_amount || 0);
            let remainingGuideFee = guideFee - downpaymentPaid;

            // Downpayment status badge
            let downpaymentBadge = '';
            if (booking.downpayment_status === 'paid') downpaymentBadge = '<span class="badge badge-green">Paid</span>';
            else if (booking.downpayment_status === 'expired') downpaymentBadge = '<span class="badge badge-gray">Expired</span>';
            else downpaymentBadge = '<span class="badge badge-amber">Unpaid</span>';

            // Guide payment status badge
            let guidePaymentBadge = '';
            if (booking.guide_payment_status === 'paid') guidePaymentBadge = '<span class="badge badge-green">Guide Fee Paid</span>';
            else if (remainingGuideFee <= 0) guidePaymentBadge = '<span class="badge badge-green">Fully Paid</span>';
            else guidePaymentBadge = '<span class="badge badge-amber">Pending (₱' + remainingGuideFee.toLocaleString() + ')</span>';
            
            const paymentBadgeClass = booking.payment_status === 'paid' ? 'badge-green' : 
                                      (booking.payment_status === 'pending' ? 'badge-amber' : 'badge-gray');
            
            let actionButtons = '';
            if (booking.status === 'pending') {
                actionButtons = `
                    <div style="display: flex; gap: 12px; margin-top: 20px;">
                        <button class="btn btn-success" onclick="confirmBooking(${booking.id}, '${booking.booking_number}')" style="flex: 1;">
                            <i class="fas fa-check-circle"></i> Confirm Booking
                        </button>
                        <button class="btn btn-danger" onclick="showCancelModalFromDetails(${booking.id}, '${booking.booking_number}')" style="flex: 1;">
                            <i class="fas fa-times-circle"></i> Cancel Booking
                        </button>
                    </div>
                `;
            } else if (booking.status === 'active') {
                actionButtons = `
                    <div style="margin-top: 20px;">
                        <button class="btn btn-primary" onclick="location.href='guide-communication.php?user_id=${booking.hiker_user_id}'" style="width: 100%;">
                            <i class="fas fa-comment-dots"></i> Send Message to Hiker
                        </button>
                    </div>
                `;
            }
            
            document.getElementById('bookingDetailsBody').innerHTML = `
                <div class="detail-section">
                    <div class="detail-section-title">Booking Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Booking Number</div>
                            <div class="detail-val"><strong>${escapeHtml(booking.booking_number)}</strong></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-val">
                                <span class="badge badge-${booking.status === 'active' || booking.status === 'confirmed' ? 'green' : (booking.status === 'pending' ? 'amber' : 'gray')}">
                                    ${booking.status === 'active' ? 'Confirmed' : (booking.status.charAt(0).toUpperCase() + booking.status.slice(1))}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Payment Status</div>
                            <div class="detail-val"><span class="badge ${paymentBadgeClass}">${booking.payment_status ? booking.payment_status.charAt(0).toUpperCase() + booking.payment_status.slice(1) : 'Pending'}</span></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Hike Date</div>
                            <div class="detail-val">${new Date(booking.hike_date).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                        </div>
                        <div class="detail-item">
    <div class="detail-label">Start Time</div>
    <div class="detail-val">${booking.start_time ? booking.start_time.substring(0,5) : 'Not specified'}</div>
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
                            <div class="detail-label">Hikers</div>
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
                    <div class="detail-section-title">Tour Guide Fee Breakdown</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Total Guide Fee</div>
                            <div class="detail-val">₱${guideFee.toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Downpayment Status</div>
                            <div class="detail-val">${downpaymentBadge}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Downpayment Amount</div>
                            <div class="detail-val">₱${downpaymentPaid.toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Guide Payment Status</div>
                            <div class="detail-val">${guidePaymentBadge}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Remaining Balance (For Guide)</div>
                            <div class="detail-val" style="color:var(--forest); font-weight:700;">₱${remainingGuideFee.toLocaleString()}</div>
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
                <div class="detail-section" style="margin-bottom:0;">
                    <div class="detail-section-title">Additional Hikers (${booking.additional_hikers.length})</div>
                    <div style="overflow-x:auto;">
                        <table class="hikers-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Emergency Contact</th>
                                    <th>Emergency Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${booking.additional_hikers.map(hiker => `
                                <tr>
                                    <td>${escapeHtml(hiker.hiker_name)}</td>
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

function confirmBooking(bookingId, bookingNumber) {
    if (confirm(`Are you sure you want to confirm booking ${bookingNumber}? This will notify the hiker that you've accepted the booking.`)) {
        showToast('Confirming booking...');
        
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
                showToast(`✓ Booking ${bookingNumber} confirmed!`);
                closeModal('bookingDetailsModal');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Failed to confirm booking');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error confirming booking');
        });
    }
}

function showCancelModal(bookingId, bookingNumber) {
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancelBookingNumber').textContent = bookingNumber;
    document.getElementById('cancelReason').value = '';
    document.getElementById('cancelCharCount').textContent = '0';
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



// Character counter for cancel modal
const cancelReason = document.getElementById('cancelReason');
const cancelCharCount = document.getElementById('cancelCharCount');
if (cancelReason && cancelCharCount) {
    cancelReason.addEventListener('input', function() {
        cancelCharCount.textContent = this.value.length;
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}
function viewGuideActivity(bookingId) {
    window.location.href = `guide-view-activity.php?booking_id=${bookingId}`;
}

document.getElementById('viewAlertsBtn')?.addEventListener('click', () => openModal('alertsModal'));
<?php if ($emergency_error): ?>
openModal('emergencyModal');
<?php endif; ?>
</script>
</body>
</html>