<?php

// hiker frontend/bookings.php - LAKBAY Bookings Page (Database Connected)
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();
// At top of file, BEFORE session_start()
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    // Capture any PHP errors as JSON instead of HTML
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "PHP Error: $errstr in $errfile on line $errline"
        ]);
        exit;
    });
    ob_start(); // Buffer output so stray warnings don't corrupt JSON
}



$currentUser = null;
$userInitial = 'J';
$currentUserId = null;
$user_name = 'Guest';

if (isset($_SESSION['user_id'])) {
    $currentUserId = $_SESSION['user_id'];
    
    // Use PDO from db.php
    if (isset($pdo) && $pdo) {
        $stmt = $pdo->prepare("SELECT id, name, email, avatar, hiking_level, home_region FROM users WHERE id = ?");
        $stmt->execute([$currentUserId]);
        $currentUser = $stmt->fetch();
        
        if ($currentUser) {
            $user_name = $currentUser['name'];  // This should now be "Pedro Hiker" for user ID 4
            $nameParts = explode(' ', trim($currentUser['name']));
            $userInitial = '';
            foreach ($nameParts as $part) {
                if (!empty($part)) {
                    $userInitial .= strtoupper(substr($part, 0, 1));
                }
            }
            $userInitial = substr($userInitial, 0, 2);
        }
    }
}

function getMountainsFromDB($pdo) {
    $mountains = [];
    try {
        $stmt = $pdo->query("
            SELECT id, name, location, difficulty, image, fee, 
                   registration_fee, environmental_fee, elevation 
            FROM mountains WHERE status = 'Open' ORDER BY name
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Map database fields to match the JavaScript expected format
            $mountains[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'location' => $row['location'],
                'difficulty' => strtolower($row['difficulty']),
                'image' => $row['image'] ?? 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&q=60',
                'fees' => [
                    'regFee' => intval($row['registration_fee'] ?? 150),
                    'envFee' => intval($row['environmental_fee'] ?? 120),
                    'guideDay' => 900,
                    'guideON' => 1600,
                    'campFee' => 50,
                    'parkDay' => 100,
                    'parkON' => 150
                ]
            ];
        }
    } catch (PDOException $e) {
        // Fallback to default mountains if query fails
    }
    
    // If no mountains in DB, return default ones
    if (empty($mountains)) {
        return [
            ['id'=>1,'name'=>'Mt. Batulao','location'=>'Nasugbu, Batangas','difficulty'=>'moderate','image'=>'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&q=60','fees'=>['regFee'=>150,'envFee'=>120,'guideDay'=>900,'guideON'=>1600,'campFee'=>50,'parkDay'=>100,'parkON'=>150]],
            ['id'=>2,'name'=>'Mt. Talamitam','location'=>'Nasugbu, Batangas','difficulty'=>'easy','image'=>'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=200&q=60','fees'=>['regFee'=>100,'envFee'=>0,'guideDay'=>700,'guideON'=>1100,'campFee'=>0,'parkDay'=>80,'parkON'=>80]],
            ['id'=>3,'name'=>'Mt. Apayang','location'=>'Batangas','difficulty'=>'hard','image'=>'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=200&q=60','fees'=>['regFee'=>200,'envFee'=>0,'guideDay'=>1200,'guideON'=>2000,'campFee'=>100,'parkDay'=>0,'parkON'=>0]],
            ['id'=>4,'name'=>'Mt. Lantik','location'=>'Alfonso, Cavite','difficulty'=>'moderate','image'=>'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=200&q=60','fees'=>['regFee'=>130,'envFee'=>100,'guideDay'=>900,'guideON'=>1500,'campFee'=>0,'parkDay'=>100,'parkON'=>100]],
            ['id'=>5,'name'=>'Mountain Trilogy','location'=>'Nasugbu, Batangas','difficulty'=>'hard','image'=>'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&q=60','fees'=>['regFee'=>350,'envFee'=>300,'guideDay'=>2500,'guideON'=>4000,'campFee'=>150,'parkDay'=>200,'parkON'=>250]]
        ];
    }
    return $mountains;
}

// --- Helper function to get guides from database ---
function getGuidesFromDB($pdo) {
    $guides = [];
    try {
        $stmt = $pdo->query("
            SELECT g.*, u.name as guide_name, u.avatar 
            FROM guides g 
            JOIN users u ON g.user_id = u.id 
            WHERE g.is_available = 1
            ORDER BY g.rating DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Get mountains this guide is assigned to
            $guideMountains = [];
            $stmt2 = $pdo->prepare("SELECT mountain_id FROM guide_mountains WHERE guide_id = ?");
            $stmt2->execute([$row['user_id']]);
            while ($m = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $guideMountains[] = $m['mountain_id'];
            }
            
            $guides[] = [
                'id' => $row['user_id'],
                'name' => $row['guide_name'],
                'initials' => substr(preg_replace('/[^A-Z]/', '', $row['guide_name']), 0, 2),
                'mountains' => $guideMountains,
                'rating' => floatval($row['rating']),
                'available' => 'Daily',
                'phone' => $row['phone'] ?? ''
            ];
        }
    } catch (PDOException $e) {
        // Fallback
    }
    
    // Fallback guides
    if (empty($guides)) {
        return [
            ['id'=>1,'name'=>'John Dela Cruz','initials'=>'JD','mountains'=>[1,3],'rating'=>4.9,'available'=>'Mon–Sat','phone'=>'+63 912 345 6789'],
            ['id'=>2,'name'=>'Maya Reyes','initials'=>'MR','mountains'=>[1,2],'rating'=>4.8,'available'=>'Daily','phone'=>'+63 923 456 7890'],
            ['id'=>3,'name'=>'Rico Cabanlit','initials'=>'RC','mountains'=>[4],'rating'=>5.0,'available'=>'Daily','phone'=>'+63 934 567 8901'],
            ['id'=>4,'name'=>'Elena Llorente','initials'=>'EL','mountains'=>[1],'rating'=>4.7,'available'=>'Wed–Sun','phone'=>'+63 945 678 9012']
        ];
    }
    return $guides;
}

// --- Get user's bookings from database to populate the JavaScript bookings array ---
function getUserBookingsFromDB($pdo, $currentUserId, $currentUserName) {
    $bookings = [];
    
    if (!$currentUserId) return $bookings;
    
    try {
        // Get regular bookings (hikes)
        $stmt = $pdo->prepare("
            SELECT 
                b.id, b.booking_number, b.mountain_id, b.guide_id,
                b.hike_date as date, b.hike_type as type, b.status,
                b.number_of_hikers as pax, b.total_amount as totalFee,
                b.special_requests as notes, b.created_at,
                (b.hike_type = 'overnight') as camping,
                m.name as mountain,
                u.name as guideName,
                SUBSTR(UPPER(REPLACE(u.name, ' ', '')), 1, 2) as guideInitials
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN guides g ON b.guide_id = g.user_id
            JOIN users u ON g.user_id = u.id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$currentUserId]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Get hikers for this booking
            $hikers = [];
            $stmt2 = $pdo->prepare("SELECT hiker_name FROM booking_hikers WHERE booking_id = ?");
            $stmt2->execute([$row['id']]);
            while ($h = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $hikers[] = $h['hiker_name'];
            }
            
            // Get nudges count
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as nudge_count, MAX(created_at) as last_nudge FROM booking_nudges WHERE booking_id = ?");
            $stmt2->execute([$row['id']]);
            $nudgeData = $stmt2->fetch(PDO::FETCH_ASSOC);

            // Check if user has reviewed this booking
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as has_reviewed FROM reviews WHERE booking_id = ? AND user_id = ?");
            $stmt2->execute([$row['id'], $currentUserId]);
            $hasReviewed = $stmt2->fetch()['has_reviewed'] > 0;
            
            // Use the actual booking_number from the database
            $bookingId = $row['booking_number'];
            
            $bookings[] = [
                'id' => $bookingId,
                'db_id' => $row['id'],
                'mountainId' => $row['mountain_id'],
                'mountain' => $row['mountain'],
                'date' => $row['date'],
                'time' => '06:00', // Default time
                'type' => $row['type'] == 'overnight' ? 'overnight' : 'day',
                'status' => $row['status'],
                'guideId' => $row['guide_id'],
                'guideName' => $row['guideName'],
                'guideInitials' => $row['guideInitials'] ?: substr($row['guideName'], 0, 2),
                'pax' => $row['pax'],
                'hikers' => $hikers,
                'totalFee' => floatval($row['totalFee']),
                'createdAt' => strtotime($row['created_at']) * 1000, // This should work if PHP timezone is set
                'nudges' => intval($nudgeData['nudge_count'] ?? 0),
                'lastNudge' => $nudgeData['last_nudge'] ? strtotime($nudgeData['last_nudge']) * 1000 : 0,
                'camping' => $row['camping'] == 1,
                'notes' => $row['notes'] ?? '',
                'hasReviewed' => $hasReviewed 
            ];
        }
        
        // Get joined hikes (where user is in booking_hikers)
        $stmt = $pdo->prepare("
    SELECT 
        b.id, b.booking_number, b.mountain_id, b.guide_id,
        b.hike_date as date, b.hike_type as type, 'joined' as status,
        b.number_of_hikers as pax, b.total_amount as totalFee,
        m.name as mountain,
        u.name as guideName,
        bh.hiker_name as joinedAs
    FROM booking_hikers bh
    JOIN bookings b ON bh.booking_id = b.id
    JOIN mountains m ON b.mountain_id = m.id
    JOIN guides g ON b.guide_id = g.user_id
    JOIN users u ON g.user_id = u.id
    WHERE bh.hiker_name = ? AND b.user_id != ?
");
// Use the current user's name from the database, not the session variable
$stmt->execute([$currentUserName, $currentUserId]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Use actual booking number for joined hikes too
            $bookingId = 'JO-' . $row['booking_number'];
            $bookings[] = [
                'id' => $bookingId,
                'db_id' => $row['id'],
                'mountainId' => $row['mountain_id'],
                'mountain' => $row['mountain'],
                'date' => $row['date'],
                'time' => '06:00',
                'type' => $row['type'] == 'overnight' ? 'overnight' : 'day',
                'status' => 'joined',
                'guideId' => $row['guide_id'],
                'guideName' => $row['guideName'],
                'guideInitials' => substr($row['guideName'], 0, 2),
                'pax' => $row['pax'],
                'hikers' => [],
                'totalFee' => 0,
                'createdAt' => time() * 1000,
                'nudges' => 0,
                'lastNudge' => 0,
                'camping' => false,
                'notes' => '',
                'joinedFromId' => $row['booking_number']
            ];
        }
        
    } catch (PDOException $e) {
        // If error, return empty array
    }
    
    return $bookings;
}

// Get data from database
$dbMountains = getMountainsFromDB($pdo);
$dbGuides = getGuidesFromDB($pdo);
$dbUserBookings = getUserBookingsFromDB($pdo, $currentUserId, $currentUser ? $currentUser['name'] : 'Guest');

// ── AUTO-FINISH FALLBACK ──
// Mark any 'active' hikes older than 24 hours as completed (safety net)
try {
    $pdo->exec("
        UPDATE bookings 
        SET status = 'finished',
            special_requests = CONCAT(IFNULL(special_requests, ''), ' [Auto-finished by system after 24h]')
        WHERE status = 'active' 
          AND hike_date < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
} catch (PDOException $e) {
    // Silently fail — not critical
    error_log('Auto-finish fallback error: ' . $e->getMessage());
}

// Handle AJAX Actions (Cancel, Nudge, Replace Guide, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_booking') {
        try {
            $bookingData = json_decode($_POST['data'] ?? '', true);

            if ($bookingData && $currentUserId) {
                $isCamping = ($bookingData['type'] === 'overnight');
                $year = date('Y');
                $prefix = $isCamping ? 'CK' : 'BK';

                // Count existing bookings with this prefix to generate number
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_number LIKE ?");
                $stmt->execute([$prefix . '-' . $year . '-%']);
                $count = $stmt->fetchColumn() + 1;
                $bookingNumber = $prefix . '-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

                // Use the total fee calculated by the frontend to ensure consistency
                $totalAmount = $bookingData['totalFee'] ?? 0;

                // Map frontend type to database ENUM
                $dbType = $bookingData['type'];
                if ($dbType === 'day' || $dbType === 'late') {
                    $dbType = 'day_hike';
                }

                $currentTime = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("
    INSERT INTO bookings (
        booking_number, user_id, mountain_id, guide_id,
        booking_date, hike_date, hike_type, number_of_hikers,
        total_amount, downpayment_amount, payment_status, status,
        special_requests, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending', 'pending', ?, ?, ?)
");
$stmt->execute([
    $bookingNumber, $currentUserId, $bookingData['mountainId'], $bookingData['guideId'],
    $currentTime, // booking_date
    $bookingData['date'], // hike_date
    $dbType, $bookingData['pax'],
    $totalAmount, $bookingData['notes'] ?? '',
    $currentTime, // created_at
    $currentTime  // updated_at
]);

                $dbBookingId = $pdo->lastInsertId();

                foreach ($bookingData['hikers'] as $hikerName) {
                    if (!empty($hikerName)) {
                        $stmt = $pdo->prepare("INSERT INTO booking_hikers (booking_id, hiker_name) VALUES (?, ?)");
                        $stmt->execute([$dbBookingId, $hikerName]);
                    }
                }

                if (ob_get_length()) ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking saved!',
                    'booking_id' => $bookingNumber,
                    'db_id' => $dbBookingId
                ]);
                exit;
            }
            throw new Exception('Invalid booking data or session');
        } catch (Exception $e) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
   if ($action === 'cancel_booking') {
    $bookingId = $_POST['booking_id'] ?? '';
    
    $currentTime = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = ? WHERE booking_number = ? AND user_id = ?");
$stmt->execute([$currentTime, $bookingId, $currentUserId]);
    
    if ($stmt->rowCount() === 0) {
        // Fallback: try numeric id extracted from BK017 format
        $numericId = preg_replace('/[^0-9]/', '', $bookingId);
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() 
                                WHERE id = ? AND user_id = ?");
        $stmt->execute([$numericId, $currentUserId]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking not found or already cancelled']);
    }
    exit;
}
    
    if ($action === 'nudge_guide') {
        $bookingId = $_POST['booking_id'] ?? '';
        $guideId = $_POST['guide_id'] ?? '';
        
        // Find booking by number or numeric id
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_number = ? OR id = ?");
        $stmt->execute([$bookingId, preg_replace('/[^0-9]/', '', $bookingId)]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }
        $numericId = $booking['id'];
        
        // Check nudge limit
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM booking_nudges WHERE booking_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
        $stmt->execute([$numericId]);
        $recentNudges = $stmt->fetch();
        
        if ($recentNudges['count'] >= 1) {
            echo json_encode(['success' => false, 'message' => 'Please wait 20 minutes before nudging again']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO booking_nudges (booking_id, user_id, guide_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$numericId, $currentUserId, $guideId]);
        
        echo json_encode(['success' => true, 'message' => 'Nudge sent to guide!']);
        exit;
    }
    
    if ($action === 'replace_guide') {
        $bookingId = $_POST['booking_id'] ?? '';
        $newGuideId = $_POST['new_guide_id'] ?? '';
        $numericId = preg_replace('/[^0-9]/', '', $bookingId);
        
        $currentTime = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("UPDATE bookings SET guide_id = ?, updated_at = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$newGuideId, $currentTime, $numericId, $currentUserId]);
        
        echo json_encode(['success' => true, 'message' => 'Guide replaced successfully']);
        exit;
    }
    
    if ($action === 'update_booking') {
        $bookingId = $_POST['booking_id'] ?? '';
        $newDate = $_POST['date'] ?? '';
        $newTime = $_POST['time'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $hikers = json_decode($_POST['hikers'] ?? '[]', true);
        
        $numericId = preg_replace('/[^0-9]/', '', $bookingId);
        
        $currentTime = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("UPDATE bookings SET hike_date = ?, special_requests = ?, updated_at = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$newDate, $notes, $currentTime, $numericId, $currentUserId]);
        
        // Update hikers
        $stmt = $pdo->prepare("DELETE FROM booking_hikers WHERE booking_id = ?");
        $stmt->execute([$numericId]);
        
        foreach ($hikers as $hikerName) {
            if (!empty($hikerName)) {
                $stmt = $pdo->prepare("INSERT INTO booking_hikers (booking_id, hiker_name) VALUES (?, ?)");
                $stmt->execute([$numericId, $hikerName]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Booking updated!']);
        exit;
    }
    
    if ($action === 'get_available_guides') {
        $bookingId = $_POST['booking_id'] ?? '';
        $numericId = preg_replace('/[^0-9]/', '', $bookingId);
        
        $stmt = $pdo->prepare("SELECT mountain_id, guide_id FROM bookings WHERE id = ?");
        $stmt->execute([$numericId]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, g.rating, g.years_experience
                FROM guides g
                JOIN users u ON g.user_id = u.id
                JOIN guide_mountains gm ON g.user_id = gm.guide_id
                WHERE gm.mountain_id = ? AND g.user_id != ? AND g.is_available = 1
            ");
            $stmt->execute([$booking['mountain_id'], $booking['guide_id']]);
            $guides = $stmt->fetchAll();
            echo json_encode(['success' => true, 'guides' => $guides]);
        } else {
            echo json_encode(['success' => false, 'guides' => []]);
        }
        exit;
    }
    if ($action === 'lookup_hike') {
        $bookingNumber = $_POST['booking_number'] ?? '';
        
        $stmt = $pdo->prepare("
            SELECT 
                b.id, b.booking_number, b.mountain_id, b.guide_id,
                b.hike_date as date, b.hike_type as type, b.status,
                b.number_of_hikers as pax,
                m.name as mountain,
                u.name as guideName,
                (b.hike_type = 'overnight') as camping
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN guides g ON b.guide_id = g.user_id
            JOIN users u ON g.user_id = u.id
            WHERE b.booking_number = ?
        ");
        $stmt->execute([$bookingNumber]);
        $hike = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hike) {
            // Get current hikers
            $stmt2 = $pdo->prepare("SELECT hiker_name FROM booking_hikers WHERE booking_id = ?");
            $stmt2->execute([$hike['id']]);
            $hike['hikers'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if current user is already in the list
            $hike['alreadyJoined'] = in_array($user_name, $hike['hikers']);
            
            echo json_encode(['success' => true, 'hike' => $hike]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Hike not found']);
        }
        exit;
    }
    if ($action === 'join_hike') {
        $bookingId = $_POST['booking_id'] ?? '';
        $bookingNumber = $_POST['booking_number'] ?? '';
        
        // Find the booking by booking_number
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_number = ?");
        $stmt->execute([$bookingNumber]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            // Check if already joined
            $stmt = $pdo->prepare("SELECT id FROM booking_hikers WHERE booking_id = ? AND hiker_name = ?");
            $stmt->execute([$booking['id'], $user_name]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You have already joined this hike']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO booking_hikers (booking_id, hiker_name) VALUES (?, ?)");
            $stmt->execute([$booking['id'], $user_name]);
            
            // Update number of hikers
            $stmt = $pdo->prepare("UPDATE bookings SET number_of_hikers = number_of_hikers + 1 WHERE id = ?");
            $stmt->execute([$booking['id']]);
            
            echo json_encode(['success' => true, 'message' => 'You joined the hike!']);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
   
    if ($action === 'get_existing_reviews') {
        $bookingId = $_POST['booking_id'] ?? '';
        
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_number = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        $numericId = $booking ? $booking['id'] : 0;
        
        $response = ['success' => true, 'mountain_review' => null, 'guide_review' => null];
        
        // Get mountain review
        $stmt = $pdo->prepare("SELECT id, rating, title, comment, media FROM reviews WHERE booking_id = ? AND user_id = ?");
        $stmt->execute([$numericId, $currentUserId]);
        $mtnReview = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mtnReview) {
            $mtnReview['media'] = json_decode($mtnReview['media'] ?? '[]', true);
            $response['mountain_review'] = $mtnReview;
        }
        
        // Get guide review
        $stmt = $pdo->prepare("SELECT id, rating, comment, photo_urls FROM guide_reviews WHERE booking_id = ? AND user_id = ?");
        $stmt->execute([$numericId, $currentUserId]);
        $guideReview = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($guideReview) {
            $guideReview['photo_urls'] = json_decode($guideReview['photo_urls'] ?? '[]', true);
            $response['guide_review'] = $guideReview;
        }
        
        echo json_encode($response);
        exit;
    }

    if ($action === 'save_review') {
        try {
            $data = json_decode($_POST['data'] ?? '', true);
            if (!$data || !$currentUserId) throw new Exception('Invalid data');
            
            $bookingId = $data['booking_id'];
            
            // Get booking details
            $stmt = $pdo->prepare("SELECT id, mountain_id, guide_id FROM bookings WHERE booking_number = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();
            
            if (!$booking) throw new Exception('Booking not found');
            $numericId = $booking['id'];
            
            // Handle Mountain Review
            if ($data['mountain_review']) {
                $mtnData = $data['mountain_review'];
                $media = json_encode($mtnData['media'] ?? []);
                
                if ($mtnData['review_id']) {
                    // Update existing mountain review
                    $stmt = $pdo->prepare("
                        UPDATE reviews SET 
                            rating = ?, title = ?, comment = ?, media = ?,
                            updated_at = NOW()
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $mtnData['rating'], $mtnData['title'], $mtnData['comment'],
                        $media, $mtnData['review_id'], $currentUserId
                    ]);
                } else {
                    // Insert new mountain review
                    $stmt = $pdo->prepare("
                        INSERT INTO reviews (
                            mountain_id, user_id, booking_id, rating, title, comment, 
                            media, is_verified_purchase, status, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'approved', NOW(), NOW())
                    ");
                    $stmt->execute([
                        $booking['mountain_id'], $currentUserId, $numericId,
                        $mtnData['rating'], $mtnData['title'], $mtnData['comment'],
                        $media
                    ]);
                }
            }
            
            // Handle Guide Review
            if ($data['guide_review']) {
                $guideData = $data['guide_review'];
                $photoUrls = json_encode($guideData['photo_urls'] ?? []);
                
                if ($guideData['review_id']) {
                    // Update existing guide review
                    $stmt = $pdo->prepare("
                        UPDATE guide_reviews SET 
                            rating = ?, comment = ?, photo_urls = ?,
                            updated_at = NOW()
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $guideData['rating'], $guideData['comment'],
                        $photoUrls, $guideData['review_id'], $currentUserId
                    ]);
                } else {
                    // Insert new guide review
                    $stmt = $pdo->prepare("
                        INSERT INTO guide_reviews (
                            guide_id, user_id, booking_id, rating, comment, 
                            photo_urls, is_verified_purchase, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, 1, 'approved', NOW())
                    ");
                    $stmt->execute([
                        $booking['guide_id'], $currentUserId, $numericId,
                        $guideData['rating'], $guideData['comment'], $photoUrls
                    ]);
                }
            }
            
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Get data for JavaScript
$mountainsJSON = json_encode($dbMountains);
$guidesJSON = json_encode($dbGuides);
$userBookingsJSON = json_encode($dbUserBookings);
$currentUserNameJS = json_encode($user_name);
$currentUserIdJS = json_encode($currentUserId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY — Bookings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="shared.css">
 <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── CSS RESET & VARIABLES ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --forest: #1a2e1a;
  --sage: #5c7a5c;
  --gold: #c9a84c;
  --cream: #faf8f3;
  --sky: #f0f4ef;
  --stone: #7a7060;
  --bark: #5d4037;
  --white: #ffffff;
  --danger: #c0392b;
  --radius: 16px;
  --radius-sm: 10px;
  --shadow: 0 2px 16px rgba(16,6,0,0.08);
  --shadow-lg: 0 8px 40px rgba(16,6,0,0.14);
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--cream);
  color: var(--forest);
  min-height: 100vh;
}

/* ── NAV ── */
.desktop-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(250,248,243,0.88); backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(16,6,0,0.06);
  height: 74px; display: flex; align-items: center;
  padding: 0 48px; gap: 40px;
  transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
}
.desktop-nav:hover { background: rgba(250,248,243,0.96); backdrop-filter: blur(24px); }
.brand {
  font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700;
  color: #100600; text-decoration: none; display: flex; align-items: center; gap: 8px;
  transition: all 0.35s;
}
.brand:hover { transform: scale(1.02); color: var(--gold); }
.brand svg { width: 32px; height: 32px; }
.tabs { display: flex; gap: 8px; flex: 1; justify-content: center; }
.tab-link {
  display: flex; align-items: center; gap: 8px; padding: 10px 24px;
  border-radius: 60px; font-size: 14px; font-weight: 600; color: var(--stone);
  text-decoration: none; transition: all 0.35s;
}
.tab-link:hover { background: rgba(198,164,59,0.12); color: #100600; transform: translateY(-2px); }
.tab-link.active { background: #100600; color: #faf8f3; box-shadow: 0 4px 12px rgba(16,6,0,0.2); }
.tab-link svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }
.user-btn {
  width: 44px; height: 44px; border-radius: 50%; background: #100600;
  color: #faf8f3; font-weight: 700; font-size: 15px;
  display: flex; align-items: center; justify-content: center; text-decoration: none;
  transition: all 0.35s;
}
.user-btn:hover { transform: scale(1.05); background: var(--gold); color: #100600; }
.mobile-nav { display: none; }
@media (max-width: 768px) {
  .desktop-nav { display: none; }
  .mobile-nav {
    display: block; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
    background: rgba(250,248,243,0.96); backdrop-filter: blur(20px);
    border-top: 1px solid rgba(16,6,0,0.06);
  }
  .mobile-nav-inner {
    display: flex; align-items: center; justify-content: space-around;
    padding: 10px 0 max(10px, env(safe-area-inset-bottom));
  }
  .mob-nav-item {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 600; color: var(--stone);
    text-decoration: none; padding: 6px 12px;
    transition: all 0.35s;
  }
  .mob-nav-item.active { color: #100600; transform: translateY(-2px); }
  .mob-nav-item svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 1.8; fill: none; }
  .mob-nav-item.quiz-center {
    width: 56px; height: 56px; border-radius: 50%; background: #100600;
    color: #faf8f3; padding: 0; display: flex; align-items: center; justify-content: center;
    margin-top: -20px; box-shadow: 0 4px 16px rgba(16,6,0,0.3);
  }
}

/* ── CONTAINER ── */
.container { max-width: 1100px; margin: 0 auto; padding: 0 24px; }

/* ── MODAL ── */
.modal-bg {
  position: fixed; inset: 0; z-index: 200; background: rgba(16,6,0,0.5);
  backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
}
.modal-bg.open { opacity: 1; pointer-events: all; }
.modal {
  background: var(--white); border-radius: 24px; width: 100%; max-height: 90vh;
  overflow-y: auto; box-shadow: var(--shadow-lg);
  transform: translateY(12px) scale(0.98);
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
.modal-bg.open .modal { transform: translateY(0) scale(1); }
.modal-hdr {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 24px 28px 0; position: sticky; top: 0; background: var(--white);
  z-index: 1; border-radius: 24px 24px 0 0;
}
.modal-title { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: var(--forest); }
.modal-close {
  width: 32px; height: 32px; border-radius: 50%; border: none;
  background: var(--sky); color: var(--stone); font-size: 14px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; flex-shrink: 0; margin-top: 2px;
}
.modal-close:hover { background: var(--forest); color: var(--cream); transform: rotate(90deg); }
.modal-body { padding: 20px 28px; }

/* ── BUTTONS ── */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  padding: 11px 20px; border-radius: 50px; font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px; font-weight: 700; cursor: pointer; border: none;
  transition: all 0.18s ease; text-decoration: none;
}
.btn:active { transform: scale(0.96); }
.btn-primary { background: var(--forest); color: var(--cream); }
.btn-primary:hover { background: #243824; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(26,46,26,0.25); }
.btn-primary:disabled { background: #ccc; color: #999; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-outline {
  background: transparent; color: var(--forest);
  border: 1.5px solid rgba(26,46,26,0.2);
}
.btn-outline:hover { background: var(--sky); border-color: var(--forest); color: var(--forest); }
.btn-danger { background: #fce4ec; color: var(--danger); border: 1.5px solid rgba(192,57,43,0.2); }
.btn-danger:hover { background: var(--danger); color: white; }
.btn-full { width: 100%; }
.btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 30px; }
.btn svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }
.btn-sm svg { width: 12px; height: 12px; }

.btn-glass-primary {
  display: inline-flex; align-items: center; justify-content: center; gap: 10px;
  padding: 14px 28px; border-radius: 50px; font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; font-weight: 700; cursor: pointer;
  border: 1.5px solid rgba(201,168,76,0.5);
  background: rgba(201,168,76,0.12); backdrop-filter: blur(12px);
  color: var(--gold); text-decoration: none; transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(201,168,76,0.15);
}
.btn-glass-primary:hover {
  background: rgba(201,168,76,0.25); border-color: rgba(201,168,76,0.8);
  transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.25);
}
.btn-glass-primary svg { width: 18px; height: 18px; stroke: var(--gold); stroke-width: 2; fill: none; }

/* ── BADGE ── */
.badge {
  display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px;
  border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.badge-easy { background: #e8f5e9; color: #2e7d32; }
.badge-moderate { background: #fff8e1; color: #f57f17; }
.badge-hard { background: #fce4ec; color: #c62828; }
.status-pending { background: #fff8e1; color: #f57f17; border: 1px solid rgba(245,127,23,0.2); }
.status-confirmed { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }
.status-completed { background: #e8eaf6; color: #283593; border: 1px solid rgba(40,53,147,0.2); }
.status-cancelled { background: #fce4ec; color: #c62828; border: 1px solid rgba(198,40,40,0.2); }
.status-joined { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }

/* ── REVIEW MODAL STARS ── */
.star-rating { display: flex; gap: 8px; font-size: 28px; color: #ddd; cursor: pointer; }
.star-rating span { transition: color 0.2s; }
.star-rating span:hover, .star-rating span.active { color: #f1c40f; }
.star-rating span:hover ~ span { color: #ddd; }

/* ── TOAST ── */
.toast {
  position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(20px);
  background: var(--forest); color: var(--cream); padding: 12px 24px;
  border-radius: 50px; font-size: 13px; font-weight: 600; z-index: 999;
  opacity: 0; pointer-events: none; transition: all 0.3s ease; white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ── INPUT STYLES ── */
.inp-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--sage); margin-bottom: 6px; display: block; }
.inp {
  width: 100%; padding: 12px 14px; border-radius: var(--radius-sm);
  border: 1.5px solid rgba(16,6,0,0.12); background: var(--white);
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; color: var(--forest);
  transition: all 0.2s; outline: none;
}
.inp:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }

/* ── BOOKINGS LAYOUT ── */
.bookings-layout { padding: 90px 0 80px; }
.bookings-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 28px; flex-wrap: wrap; gap: 16px;
}
.section-label { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--sage); margin-bottom: 8px; }
.section-title { font-family: 'Playfair Display', serif; font-size: clamp(28px, 5vw, 36px); font-weight: 700; color: var(--forest); }
.bookings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .bookings-grid { grid-template-columns: 1fr; } }

/* ── PAGE TABS ── */
.page-tabs { display: flex; border-bottom: 2px solid rgba(16,6,0,0.08); margin-bottom: 0; }
.page-tab {
  padding: 12px 24px; font-size: 14px; font-weight: 600; color: var(--stone);
  cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: .2s;
  display: flex; align-items: center; gap: 8px;
}
.page-tab svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 2; fill: none; }
.page-tab.active { color: var(--forest); border-color: var(--forest); }

/* ── FILTER TOOLBAR ── */
.filter-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 0 20px;
  flex-wrap: wrap;
}
.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 360px;
}
.search-wrap svg {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 16px; height: 16px;
  stroke: var(--stone); stroke-width: 2; fill: none;
  pointer-events: none;
}
.search-input {
  width: 100%;
  padding: 10px 14px 10px 40px;
  border: 1.5px solid rgba(16,6,0,0.1);
  border-radius: 50px;
  background: var(--white);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px;
  color: var(--forest);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.search-input::placeholder { color: var(--stone); opacity: 0.6; }
.search-input:focus {
  border-color: var(--forest);
  box-shadow: 0 0 0 3px rgba(26,46,26,0.08);
}
.search-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px; height: 20px;
  border-radius: 50%;
  background: rgba(16,6,0,0.08);
  border: none;
  cursor: pointer;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 0;
  color: var(--stone);
  font-size: 12px;
  line-height: 1;
  transition: background 0.15s;
}
.search-clear:hover { background: rgba(16,6,0,0.16); }
.search-clear.show { display: flex; }

.filter-chips {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
  flex-shrink: 0;
  max-width: 100%;
  padding-bottom: 2px;
}
.filter-chips::-webkit-scrollbar { display: none; }
.filter-chip {
  padding: 7px 16px;
  border-radius: 50px;
  font-size: 12px;
  font-weight: 600;
  font-family: 'Plus Jakarta Sans', sans-serif;
  cursor: pointer;
  border: 1.5px solid rgba(16,6,0,0.1);
  background: var(--white);
  color: var(--stone);
  white-space: nowrap;
  transition: all 0.18s ease;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 5px;
}
.filter-chip:hover { border-color: var(--forest); color: var(--forest); }
.filter-chip.active {
  background: var(--forest);
  color: var(--cream);
  border-color: var(--forest);
}
.filter-chip .chip-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px; height: 18px;
  border-radius: 50px;
  font-size: 10px;
  font-weight: 800;
  padding: 0 5px;
}
.filter-chip.active .chip-count {
  background: rgba(255,255,255,0.2);
  color: var(--cream);
}
.filter-chip:not(.active) .chip-count {
  background: rgba(16,6,0,0.06);
  color: var(--stone);
}
.filter-no-results {
  grid-column: 1 / -1;
  text-align: center;
  padding: 48px 20px;
  color: var(--stone);
}
.filter-no-results svg {
  width: 40px; height: 40px;
  stroke: var(--stone); stroke-width: 1.5; fill: none;
  margin-bottom: 12px;
  opacity: 0.5;
}
.filter-no-results p {
  font-size: 14px; font-weight: 600; margin-bottom: 4px;
}
.filter-no-results span {
  font-size: 12px; opacity: 0.7;
}

@media (max-width: 600px) {
  .filter-toolbar {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  .search-wrap { max-width: 100%; min-width: unset; }
}

/* ── BOOKING CARD ── */
.booking-card {
  background: var(--white); border-radius: 18px;
  border: 1px solid rgba(16,6,0,0.08); padding: 20px;
  box-shadow: var(--shadow); transition: transform .2s, box-shadow .2s; position: relative;
}
.booking-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.booking-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; gap: 10px; }
.booking-card-title { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: var(--forest); }
.booking-card-date { font-size: 12px; color: var(--stone); margin-top: 4px; display: flex; align-items: center; gap: 5px; }
.booking-card-date svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }
.booking-card-guide {
  display: flex; align-items: center; gap: 10px;
  background: var(--sky); border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px;
}
.guide-av-sm {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--forest); color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.booking-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 14px; }
.fee-total { font-size: 16px; font-weight: 700; color: var(--forest); margin-top: 4px; }
.countdown-timer {
  font-size: 11px; color: var(--danger); margin-top: 8px; padding: 7px 12px;
  background: rgba(192,57,43,0.07); border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;
}
.countdown-timer svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }
.nudge-count { font-size: 10px; color: var(--stone); margin-left: 8px; display: inline-flex; align-items: center; gap: 4px; }
.joined-badge {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(46,125,50,0.1); color: #2e7d32; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.8px; padding: 3px 8px; border-radius: 20px;
  border: 1px solid rgba(46,125,50,0.2); margin-left: 6px;
}

/* ── FLOW STEPS ── */
.flow-steps { display: flex; align-items: center; padding: 16px 28px 0; }
.flow-step { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 700; color: var(--stone); font-family: 'DM Mono', monospace; }
.flow-step.active { color: var(--forest); }
.flow-step.done { color: var(--sage); }
.flow-step .sn {
  width: 24px; height: 24px; border-radius: 50%;
  background: rgba(16,6,0,0.08); display: flex; align-items: center; justify-content: center; font-size: 10px;
}
.flow-step.active .sn { background: var(--forest); color: var(--cream); }
.flow-step.done .sn { background: var(--sage); color: var(--cream); }
.flow-line { flex: 1; height: 1px; background: rgba(16,6,0,0.1); margin: 0 8px; min-width: 16px; }
@media (max-width: 480px) { .flow-step span { display: none; } }

/* ── STEP 0: MODE CHOICE ── */
.mode-choice-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 4px 0; }
.mode-choice-card {
  display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px;
  padding: 28px 20px 24px; border-radius: 16px; border: 2px solid transparent;
  background: var(--sky); cursor: pointer; transition: all 0.22s ease;
}
.mode-choice-card:hover { border-color: var(--forest); background: var(--cream); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16,6,0,0.08); }
.mode-choice-card.selected { border-color: var(--forest); background: var(--cream); box-shadow: 0 0 0 3px rgba(16,6,0,0.06); }
.mode-choice-icon { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mode-choice-icon.join-icon { background: rgba(46,125,50,0.12); }
.mode-choice-icon.book-icon { background: rgba(201,168,76,0.15); }
.mode-choice-icon svg { width: 26px; height: 26px; stroke-width: 1.8; fill: none; }
.mode-choice-icon.join-icon svg { stroke: #2e7d32; }
.mode-choice-icon.book-icon svg { stroke: var(--gold); }
.mode-choice-title { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700; color: var(--forest); }
.mode-choice-desc { font-size: 12px; color: var(--stone); line-height: 1.55; }
@media (max-width: 480px) { .mode-choice-wrap { grid-template-columns: 1fr; } }

/* ── HIKING ID LOOKUP ── */
.hike-id-input-row { display: flex; gap: 10px; margin: 12px 0 6px; }
.hike-id-input-row input {
  flex: 1; padding: 12px 16px; border: 1.5px solid rgba(16,6,0,0.12);
  border-radius: var(--radius-sm); font-family: 'DM Mono', monospace; font-size: 15px;
  letter-spacing: 2px; color: var(--forest); background: var(--white);
  outline: none; transition: border-color 0.2s; text-transform: uppercase;
}
.hike-id-input-row input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
.hike-id-result { margin-top: 14px; }
.hike-id-found-card {
  background: var(--sky); border-radius: var(--radius-sm); border-left: 4px solid #2e7d32; padding: 14px 16px;
}
.hif-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #2e7d32; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.hif-label svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.hif-row { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px solid rgba(16,6,0,0.05); }
.hif-row:last-child { border-bottom: none; }
.hif-key { color: var(--stone); }
.hif-val { font-weight: 700; color: var(--forest); }
.hike-id-not-found {
  background: #fce4ec; border-radius: var(--radius-sm); border-left: 4px solid #c62828;
  padding: 12px 14px; font-size: 13px; color: #c62828; font-weight: 600;
  display: flex; align-items: center; gap: 8px;
}
.hike-id-not-found svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ── MOUNTAIN SELECT ── */
.mtn-select-card {
  display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px;
  border: 2px solid transparent; background: var(--sky); cursor: pointer; transition: .2s; margin-bottom: 10px;
}
.mtn-select-card:hover, .mtn-select-card.selected { border-color: var(--forest); background: var(--cream); }
.mtn-select-img { width: 56px; height: 56px; border-radius: 10px; background-size: cover; background-position: center; flex-shrink: 0; }
.mtn-select-name { font-weight: 700; font-size: 14px; color: var(--forest); }
.mtn-select-meta { font-size: 12px; color: var(--stone); margin-top: 3px; }

/* ── TYPE TOGGLE ── */
.type-toggle-group { margin-bottom: 18px; }
.type-toggle { display: flex; gap: 8px; background: rgba(16,6,0,0.04); padding: 6px; border-radius: 12px; }
.type-btn {
  flex: 1; padding: 11px 12px; text-align: center; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all 0.2s; color: var(--stone); background: transparent;
  border-radius: 9px; display: flex; align-items: center; justify-content: center; gap: 6px;
  border: none; font-family: 'Plus Jakarta Sans', sans-serif;
}
.type-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 1.8; flex-shrink: 0; }
.type-btn.active { background: var(--forest); color: var(--cream); box-shadow: 0 2px 8px rgba(26,46,26,0.2); }
.type-btn:hover:not(.active) { background: rgba(16,6,0,0.06); color: var(--forest); }

/* ── HIKERS MANAGEMENT ── */
.hikers-section { margin: 14px 0; padding: 16px; background: var(--sky); border-radius: 12px; }
.hiker-input-row { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.hiker-input-row input { flex: 1; min-width: 120px; padding: 10px 12px; border: 1.5px solid rgba(16,6,0,0.1); border-radius: var(--radius-sm); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--forest); outline: none; transition: .2s; background: var(--white); }
.hiker-input-row input:focus { border-color: var(--gold); }
.hiker-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.hiker-list-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 12px; background: var(--white); border-radius: 9px; gap: 8px;
  border: 1px solid rgba(16,6,0,0.06);
}
.hiker-list-item .hiker-info { display: flex; align-items: center; gap: 8px; flex: 1; }
.hiker-list-item .hiker-avatar {
  width: 30px; height: 30px; border-radius: 50%; background: var(--forest); color: var(--cream);
  font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.hiker-list-item.booker { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); }
.hiker-list-item.booker .hiker-avatar { background: var(--gold); }
.hiker-list-item .hiker-name { font-weight: 600; font-size: 13px; color: var(--forest); }
.hiker-list-item .hiker-tag { font-size: 10px; color: var(--stone); }
.hiker-actions { display: flex; gap: 4px; }
.hiker-action-btn {
  width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: .15s;
  background: rgba(16,6,0,0.05);
}
.hiker-action-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }
.hiker-action-btn.edit { color: var(--sage); }
.hiker-action-btn.edit:hover { background: rgba(92,122,92,0.15); }
.hiker-action-btn.remove { color: var(--danger); }
.hiker-action-btn.remove:hover { background: rgba(192,57,43,0.12); }
.hiker-count-display { font-size: 11px; color: var(--stone); margin-top: 8px; text-align: center; font-weight: 600; }

/* ── IMPROVED DATE/TIME PICKER ── */
.datetime-section { margin-bottom: 18px; }
.datetime-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 480px) { .datetime-row { grid-template-columns: 1fr; } }
.datetime-field { display: flex; flex-direction: column; gap: 6px; }

/* Custom styled date/time wrapper */
.custom-date-wrapper, .custom-time-wrapper {
  position: relative; overflow: hidden; border-radius: var(--radius-sm);
  border: 1.5px solid rgba(16,6,0,0.12); background: var(--white);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.custom-date-wrapper:focus-within, .custom-time-wrapper:focus-within {
  border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
}
.dt-icon-row {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; pointer-events: none; z-index: 1;
}
.dt-icon-row svg { width: 16px; height: 16px; stroke: var(--sage); fill: none; stroke-width: 1.8; }
.custom-date-wrapper input[type="date"],
.custom-time-wrapper input[type="time"] {
  width: 100%; padding: 12px 14px 12px 40px; border: none; background: transparent;
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; color: var(--forest);
  outline: none; cursor: pointer; appearance: none; -webkit-appearance: none;
}
.custom-date-wrapper input[type="date"]::-webkit-calendar-picker-indicator,
.custom-time-wrapper input[type="time"]::-webkit-calendar-picker-indicator {
  position: absolute; right: 10px; opacity: 0.5; cursor: pointer;
  width: 20px; height: 20px;
}

/* Date display preview */
.date-preview {
  background: linear-gradient(135deg, var(--sky), rgba(201,168,76,0.08));
  border-radius: 10px; padding: 12px 14px; margin-top: 8px;
  border: 1px solid rgba(201,168,76,0.2); display: none;
}
.date-preview.visible { display: flex; align-items: center; gap: 12px; }
.date-preview-icon {
  width: 44px; height: 44px; border-radius: 10px; background: var(--forest);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.date-preview-icon .month { font-size: 8px; font-weight: 700; color: rgba(250,248,243,0.7); text-transform: uppercase; letter-spacing: 1px; }
.date-preview-icon .day { font-size: 18px; font-weight: 700; color: var(--cream); line-height: 1; }
.date-preview-info { flex: 1; }
.date-preview-info .weekday { font-size: 13px; font-weight: 700; color: var(--forest); }
.date-preview-info .fulldate { font-size: 11px; color: var(--stone); margin-top: 2px; }

/* Time display preview */
.time-display {
  display: flex; align-items: center; gap: 8px;
  background: var(--sky); border-radius: 8px; padding: 8px 12px; margin-top: 8px;
  border: 1px dashed rgba(16,6,0,0.1); display: none;
}
.time-display.visible { display: flex; }
.time-display svg { width: 14px; height: 14px; stroke: var(--sage); fill: none; stroke-width: 2; }
.time-display span { font-size: 13px; font-weight: 600; color: var(--forest); font-family: 'DM Mono', monospace; }
.time-period { font-size: 10px; color: var(--stone); margin-left: 2px; font-weight: 600; }

/* ── GUIDE SELECT ── */
.guide-select-card { display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px; border: 2px solid transparent; background: var(--sky); cursor: pointer; transition: .2s; margin-bottom: 10px; }
.guide-select-card:hover, .guide-select-card.selected { border-color: var(--forest); background: var(--cream); }
.guide-select-info { flex: 1; }
.guide-select-name { font-weight: 700; font-size: 14px; color: var(--forest); }
.guide-select-meta { font-size: 12px; color: var(--stone); margin-top: 2px; }
.guide-avail { font-size: 11px; font-weight: 600; color: #2e7d32; margin-top: 3px; display: flex; align-items: center; gap: 4px; }
.guide-avail svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ── FEE ROWS ── */
.fee-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid rgba(16,6,0,0.06); font-size: 13px; color: var(--forest); }
.fee-row:last-child { border-bottom: none; }
.fee-row.total { font-weight: 700; font-size: 16px; padding-top: 12px; }
.fee-row svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; margin-right: 5px; }

/* ── WARNING / INFO CARDS ── */
.warning-card { background: linear-gradient(135deg, #fff8e1, #fff3cd); border: 1px solid rgba(245,167,0,0.3); border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; }
.warning-card h4 { font-size: 13px; font-weight: 700; color: var(--bark); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.warning-card h4 svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; }
.warning-card p { font-size: 12px; color: #5d4037; line-height: 1.7; }
.info-note { background: var(--sky); border-radius: 10px; padding: 12px; font-size: 12px; color: var(--stone); margin-bottom: 12px; display: flex; align-items: flex-start; gap: 8px; }
.info-note svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; margin-top: 1px; }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 48px 20px; grid-column: 1/-1; }
.empty-state svg { width: 48px; height: 48px; stroke: var(--stone); fill: none; stroke-width: 1.5; margin-bottom: 16px; }
.empty-state p { color: var(--stone); margin-bottom: 16px; }

/* ── SUMMARY BOX ── */
.summary-box { background: var(--sky); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.summary-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid rgba(16,6,0,0.06); font-size: 13px; }
.summary-row:last-child { border-bottom: none; }
.summary-row svg { width: 15px; height: 15px; stroke: var(--sage); fill: none; stroke-width: 1.8; flex-shrink: 0; }
.summary-row .sr-label { color: var(--stone); min-width: 90px; }
.summary-row .sr-val { font-weight: 600; color: var(--forest); }

/* ── BOOKING ID COPY ── */
.booking-id-row {
  display: flex; align-items: center; gap: 10px;
  background: linear-gradient(135deg, rgba(26,46,26,0.05), rgba(201,168,76,0.08));
  border: 1.5px solid rgba(201,168,76,0.3); border-radius: 10px; padding: 10px 14px;
  margin: 12px 0;
}
.booking-id-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--stone); }
.booking-id-value { font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700; color: var(--forest); flex: 1; }
.copy-btn {
  display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 30px;
  background: var(--forest); color: var(--cream); font-size: 11px; font-weight: 700;
  border: none; cursor: pointer; transition: all 0.18s; letter-spacing: 0.3px;
}
.copy-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.copy-btn:hover { background: #243824; transform: scale(1.04); }
.copy-btn.copied { background: #2e7d32; }

/* ── EDIT HIKER INLINE ── */
.hiker-edit-inline { display: none; gap: 6px; margin-top: 6px; }
.hiker-edit-inline.visible { display: flex; }
.hiker-edit-inline input { flex: 1; padding: 6px 10px; border: 1.5px solid var(--gold); border-radius: 7px; font-size: 12px; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; }
.hiker-edit-inline .save-edit { padding: 6px 12px; background: var(--forest); color: var(--cream); border: none; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; }

/* ── REPLACE GUIDE MODAL ── */
.guide-replace-option { display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px; border: 2px solid transparent; background: var(--sky); cursor: pointer; transition: .2s; margin-bottom: 10px; }
.guide-replace-option:hover, .guide-replace-option.selected { border-color: var(--forest); background: var(--cream); }

/* ── DETAIL VIEW (joined booking) ── */
.detail-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sage); margin: 16px 0 8px; }
.detail-hikers-list { display: flex; flex-wrap: wrap; gap: 8px; }
.detail-hiker-chip {
  display: flex; align-items: center; gap: 7px; padding: 6px 12px;
  background: var(--sky); border-radius: 30px; font-size: 12px; font-weight: 600; color: var(--forest);
  border: 1px solid rgba(16,6,0,0.07);
}
.detail-hiker-chip .dh-av {
  width: 22px; height: 22px; border-radius: 50%; background: var(--forest); color: var(--cream);
  font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center;
}
.advisory-box {
  background: linear-gradient(135deg, #fffde7, #fff9c4); border-radius: 12px;
  border: 1px solid rgba(245,167,0,0.25); padding: 14px 16px; margin-top: 8px;
}
.advisory-item { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: #5d4037; padding: 5px 0; }
.advisory-item svg { width: 13px; height: 13px; stroke: var(--gold); fill: none; stroke-width: 2; flex-shrink: 0; margin-top: 1px; }

/* ── EDIT BOOKING FORM ── */
.edit-section { margin-bottom: 20px; }
.edit-section .section-divider { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sage); padding: 10px 0 6px; border-top: 1px solid rgba(16,6,0,0.06); margin-top: 14px; }

@media (max-width: 768px) {
  .bookings-layout { padding: 80px 0 100px; }
  .bookings-header { flex-direction: column; align-items: flex-start; }
}
/* ── CUSTOM PHOTO UPLOAD ── */
.photo-upload-zone {
  position: relative;
  width: 90px;
  height: 90px;
  border: 2px dashed rgba(90,122,90,0.3);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: 0.2s;
  background: var(--sky);
}
.photo-upload-zone:hover { border-color: var(--forest); background: var(--white); }
.photo-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
}
.photo-upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: var(--sage);
  font-size: 11px;
  gap: 4px;
}
.photo-upload-placeholder i { font-size: 18px; }

.review-photo-preview {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 12px;
}
.photo-item {
  position: relative;
  width: 90px;
  height: 90px;
}
.photo-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.photo-remove {
  position: absolute;
  top: -6px;
  right: -6px;
  background: #ff5252;
  color: white;
  border: none;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Full-width slot above the 2-col grid */
.today-hike-slot {
  grid-column: 1 / -1;
}
 
/* Ambient pulse behind the card */
@keyframes todayGlow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(26,46,26,0.0),  0 8px 40px rgba(16,6,0,0.14); }
  50%       { box-shadow: 0 0 0 8px rgba(26,46,26,0.06), 0 8px 40px rgba(16,6,0,0.14); }
}
 
.today-hike-card {
  position: relative;
  background: var(--white);
  border-radius: 20px;
  border: 2px solid var(--forest);
  overflow: hidden;
  animation: todayGlow 3s ease-in-out infinite;
  transition: transform .2s;
}
.today-hike-card:hover { transform: translateY(-2px); }
 
/* Forest-green top stripe */
.today-hike-card::before {
  content: '';
  position: absolute;
  inset: 0 0 auto 0;
  height: 4px;
  background: linear-gradient(90deg, var(--forest) 0%, var(--sage) 60%, var(--gold) 100%);
}
 
/* Subtle diagonal texture on the banner half */
.today-banner {
  background: var(--forest);
  padding: 18px 24px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
 
.today-icon-ring {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(255,255,255,0.12);
  border: 1.5px solid rgba(255,255,255,0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
  animation: pulse-ring 2.4s cubic-bezier(0.4,0,0.6,1) infinite;
}
@keyframes pulse-ring {
  0%   { box-shadow: 0 0 0 0   rgba(255,255,255,0.35); }
  70%  { box-shadow: 0 0 0 12px rgba(255,255,255,0);    }
  100% { box-shadow: 0 0 0 0   rgba(255,255,255,0);     }
}
 
.today-banner-text { flex: 1; min-width: 0; }
.today-eyebrow {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.8px;
  color: rgba(255,255,255,0.6);
  margin-bottom: 3px;
}
.today-headline {
  font-family: 'Playfair Display', serif;
  font-size: clamp(17px, 3vw, 21px);
  font-weight: 700;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
 
/* Countdown chip */
.today-countdown {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 30px;
  padding: 6px 14px;
  font-family: 'DM Mono', monospace;
  font-size: 12px;
  font-weight: 500;
  color: rgba(255,255,255,0.9);
  flex-shrink: 0;
}
.today-countdown svg {
  width: 13px; height: 13px;
  stroke: rgba(255,255,255,0.7);
  fill: none; stroke-width: 2;
}
 
/* Card body — reuses existing booking-card styles */
.today-body {
  padding: 16px 20px 18px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px 20px;
  align-items: start;
}
 
.today-details { min-width: 0; }
 
.today-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--stone);
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.today-meta svg {
  width: 12px; height: 12px;
  stroke: var(--sage); fill: none; stroke-width: 2;
  flex-shrink: 0;
}
.today-meta .sep { color: rgba(16,6,0,0.2); }
 
/* Guide row inside today card */
.today-guide {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--sky);
  border-radius: 10px;
  padding: 10px 14px;
  margin-bottom: 12px;
}
 
/* CTA area */
.today-cta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: stretch;
  min-width: 140px;
}
 
/* START HIKE button — bigger, more prominent in today card */
.btn-start-hike {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 13px 20px;
  border-radius: 50px;
  background: var(--forest);
  color: var(--cream);
  font-size: 14px;
  font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: background .18s, transform .15s, box-shadow .18s;
  box-shadow: 0 4px 16px rgba(26,46,26,0.28);
  white-space: nowrap;
}
.btn-start-hike:hover {
  background: #243824;
  transform: translateY(-1px);
  box-shadow: 0 6px 22px rgba(26,46,26,0.36);
}
.btn-start-hike:active { transform: scale(0.97); }
.btn-start-hike svg {
  width: 15px; height: 15px;
  fill: var(--cream); flex-shrink: 0;
}
 
/* Fee note */
.today-fee {
  font-size: 13px;
  font-weight: 700;
  color: var(--forest);
}
.today-fee span {
  font-size: 11px;
  font-weight: 400;
  color: var(--stone);
}
 
/* Urgency strip at the bottom of the banner */
.today-urgency {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  background: rgba(201,168,76,0.08);
  border-top: 1px solid rgba(201,168,76,0.18);
  font-size: 12px;
  color: var(--bark);
  font-weight: 500;
}
.today-urgency svg {
  width: 13px; height: 13px;
  stroke: var(--gold); fill: none; stroke-width: 2;
  flex-shrink: 0;
}
 
@media (max-width: 560px) {
  .today-body {
    grid-template-columns: 1fr;
  }
  .today-cta {
    flex-direction: row;
    min-width: 0;
  }
  .btn-start-hike { flex: 1; }
  .today-countdown { display: none; }
}
 

</style>
</head>
<body>

<?php
// Set current page for navbar highlighting
$currentPage = 'bookings'; // Change per page: 'explore', 'bookings', 'quiz', 'messages', 'hikerProfile'
?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- PAGE -->
<div class="bookings-layout">
  <div class="container">
    <div class="bookings-header">
      <div>
        <div class="section-label">Your Adventures</div>
        <div class="section-title">Bookings</div>
      </div>
      <button class="btn-glass-primary" onclick="openBookingFlow()">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Book a Hike
      </button>
    </div>

    <div class="page-tabs">
      <div class="page-tab active" onclick="switchTab('current',this)">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>Current
      </div>
      <div class="page-tab" onclick="switchTab('history',this)">
        <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>History
      </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="filter-toolbar" id="filterToolbar">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" class="search-input" id="searchInput" placeholder="Search mountain, guide, or booking ID…" oninput="onSearchInput(this.value)">
        <button class="search-clear" id="searchClear" onclick="clearSearch()">&times;</button>
      </div>
      <div class="filter-chips" id="filterChips">
        <div class="filter-chip active" data-filter="all" onclick="setFilter('all',this)">All <span class="chip-count" id="countAll">0</span></div>
        <div class="filter-chip" data-filter="pending" onclick="setFilter('pending',this)">Pending <span class="chip-count" id="countPending">0</span></div>
        <div class="filter-chip" data-filter="confirmed" onclick="setFilter('confirmed',this)">Confirmed <span class="chip-count" id="countConfirmed">0</span></div>
        <div class="filter-chip" data-filter="active" onclick="setFilter('active',this)">Active <span class="chip-count" id="countActive">0</span></div>
        <div class="filter-chip" data-filter="joined" onclick="setFilter('joined',this)">Joined <span class="chip-count" id="countJoined">0</span></div>
        <!-- History-only chips (hidden by default) -->
        <div class="filter-chip" data-filter="finished" onclick="setFilter('finished',this)" style="display:none;">Finished <span class="chip-count" id="countFinished">0</span></div>
        <div class="filter-chip" data-filter="cancelled" onclick="setFilter('cancelled',this)" style="display:none;">Cancelled <span class="chip-count" id="countCancelled">0</span></div>
      </div>
    </div>

    <div id="tab-current"><div class="bookings-grid" id="currentBookings"></div></div>
    <div id="tab-history" style="display:none;"><div class="bookings-grid" id="historyBookings"></div></div>
  </div>
</div>

<!-- BOOKING FLOW MODAL -->
<div class="modal-bg" id="bookingModal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-hdr">
      <div>
        <div class="modal-title" id="flowTitle">Book a Hike</div>
        <div style="font-size:11px;color:var(--stone);margin-top:2px;" id="flowSubtitle">How would you like to proceed?</div>
      </div>
      <button class="modal-close" onclick="closeBookingModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="flow-steps" id="flowSteps" style="display:none;">
      <div class="flow-step active" id="fs1"><div class="sn">1</div><span>Mountain</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs2"><div class="sn">2</div><span>Details</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs3"><div class="sn">3</div><span>Guide</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs4"><div class="sn">4</div><span>Summary</span></div>
    </div>
    <div class="modal-body" id="flowBody"></div>
    <div style="display:flex;gap:10px;padding:0 28px 24px;" id="flowFooter"></div>
  </div>
</div>

<!-- SUCCESS MODAL -->
<div class="modal-bg" id="successModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-body">
      <div style="text-align:center;padding:12px 0 0;">
        <div style="width:72px;height:72px;background:rgba(46,125,50,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h2 id="successTitle" style="font-family:'Playfair Display',serif;font-size:24px;color:var(--forest);margin-bottom:8px;">Booking Created!</h2>
        <p id="successDesc" style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:16px;"></p>

        <!-- Booking ID with copy button -->
        <div id="successBookingIdRow" class="booking-id-row" style="text-align:left;">
          <div style="flex:1;">
            <div class="booking-id-label">Booking ID</div>
            <div class="booking-id-value" id="successBookingId">—</div>
          </div>
          <button class="copy-btn" id="copyIdBtn" onclick="copyBookingId()">
            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Copy
          </button>
        </div>

        <div class="summary-box" id="successSummary" style="text-align:left;"></div>
        <div id="successPolicy" class="warning-card" style="text-align:left;">
          <h4>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            What's next?
          </h4>
          <p>• Booking confirmed when guide accepts<br>• No guide response within 5 hours → replace or cancel<br>• Nudge guide every 20 min (max 10 nudges)</p>
        </div>
        <button class="btn btn-primary btn-full" onclick="closeSuccess()" style="margin-top:4px;">View My Bookings</button>
      </div>
    </div>
  </div>
</div>

<!-- JOIN SUCCESS MODAL -->
<div class="modal-bg" id="joinSuccessModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-body">
      <div style="text-align:center;padding:12px 0 0;">
        <div style="width:72px;height:72px;background:rgba(46,125,50,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:24px;color:var(--forest);margin-bottom:8px;">You're In!</h2>
        <p style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:16px;">You've successfully joined this hike. It's now on your bookings page.</p>
        <div class="summary-box" id="joinSuccessSummary" style="text-align:left;"></div>
        <button class="btn btn-primary btn-full" onclick="closeJoinSuccess()">View My Bookings</button>
      </div>
    </div>
  </div>
</div>

<!-- VIEW JOINED HIKE MODAL -->
<div class="modal-bg" id="viewJoinedModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-hdr">
      <div>
        <div class="modal-title" id="vjTitle">Hike Details</div>
        <div style="font-size:11px;color:var(--stone);margin-top:2px;">Read-only — joined booking</div>
      </div>
      <button class="modal-close" onclick="document.getElementById('viewJoinedModal').classList.remove('open')">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" id="vjBody"></div>
    <div style="padding:0 28px 24px;">
      <button class="btn btn-outline btn-full" onclick="document.getElementById('viewJoinedModal').classList.remove('open')">Close</button>
    </div>
  </div>
</div>

<!-- REPLACE GUIDE MODAL -->
<div class="modal-bg" id="replaceGuideModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-hdr">
      <div class="modal-title">Replace Tour Guide</div>
      <button class="modal-close" onclick="closeReplaceModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body"><div id="replaceGuideList"></div></div>
    <div style="padding:0 28px 24px;display:flex;gap:10px;">
      <button class="btn btn-outline btn-full" onclick="closeReplaceModal()">Cancel</button>
      <button class="btn btn-primary btn-full" onclick="confirmReplaceGuide()">Replace Guide</button>
    </div>
  </div>
</div>

<!-- EDIT BOOKING MODAL -->
<div class="modal-bg" id="editBookingModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-hdr">
      <div class="modal-title">Edit Booking</div>
      <button class="modal-close" onclick="closeEditModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" id="editModalBody"></div>
    <div style="padding:0 28px 24px;display:flex;gap:10px;">
      <button class="btn btn-outline btn-full" onclick="closeEditModal()">Cancel</button>
      <button class="btn btn-primary btn-full" onclick="saveBookingEdit()">Save Changes</button>
    </div>
  </div>
</div>

   <!-- Enhanced REVIEW MODAL -->
<div class="modal-bg" id="reviewModal">
  <div class="modal" style="max-width:550px; max-height:90vh; overflow-y:auto;">
    <div class="modal-hdr">
      <div class="modal-title">Share Your Experience</div>
      <button class="modal-close" onclick="closeReviewModal()">×</button>
    </div>
    <div class="modal-body" style="padding:20px 28px 28px;">
      <input type="hidden" id="revBookingId">
      <input type="hidden" id="existingMtnReviewId">
      <input type="hidden" id="existingGuideReviewId">
      
      <!-- Mountain Review -->
      <div style="margin-bottom:24px;">
        <div style="font-weight:700;font-size:15px;color:var(--forest);margin-bottom:4px;" id="revMtnName">Mt. Batulao</div>
        <div style="font-size:12px;color:var(--stone);margin-bottom:8px;">How was the trail and the view?</div>
        
        <!-- Existing Review Display -->
        <div id="existingMtnReview" style="display:none; background:var(--sky); border-radius:12px; padding:12px; margin-bottom:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700;">Your Review</span>
            <button class="btn btn-sm btn-outline" onclick="enableEditReview('mtn')">Edit Review</button>
          </div>
          <div id="mtnExistingContent"></div>
        </div>
        
        <!-- Review Form -->
        <div id="mtnReviewForm">
          <div class="star-rating" id="mtnStars">
            <span data-val="1">★</span><span data-val="2">★</span><span data-val="3">★</span><span data-val="4">★</span><span data-val="5">★</span>
          </div>
          <input type="hidden" id="mtnRating" value="0">
          <input type="text" id="mtnTitle" class="inp" placeholder="Review Title (e.g. Amazing sunrise!)" style="margin-top:12px;width:100%;">
          <textarea id="mtnComment" class="inp" rows="3" placeholder="Write your thoughts about the mountain..." style="margin-top:8px;resize:none;width:100%;"></textarea>
          
          <!-- Photo Upload -->
          <div style="margin-top:16px;">
            <label class="inp-label">Upload Photos (max 5)</label>
            <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
              <div class="photo-upload-zone">
                <input type="file" id="mtnPhotos" multiple accept="image/*" class="photo-input">
                <div class="photo-upload-placeholder">
                  <i class="fas fa-camera"></i>
                  <span>Add Photos</span>
                </div>
              </div>
              <div id="mtnPhotoPreview" class="review-photo-preview" style="margin-top:0;"></div>
            </div>
          </div>
        </div>
      </div>
      
      <div style="height:1px;background:rgba(16,6,0,0.06);margin-bottom:24px;"></div>
      
      <!-- Guide Review -->
      <div style="margin-bottom:24px;">
        <div style="font-weight:700;font-size:15px;color:var(--forest);margin-bottom:4px;" id="revGuideName">Maria Santos</div>
        <div style="font-size:12px;color:var(--stone);margin-bottom:8px;">How was your guide's assistance?</div>
        
        <!-- Existing Review Display -->
        <div id="existingGuideReview" style="display:none; background:var(--sky); border-radius:12px; padding:12px; margin-bottom:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700;">Your Review</span>
            <button class="btn btn-sm btn-outline" onclick="enableEditReview('guide')">Edit Review</button>
          </div>
          <div id="guideExistingContent"></div>
        </div>
        
        <!-- Review Form -->
        <div id="guideReviewForm">
          <div class="star-rating" id="guideStars">
            <span data-val="1">★</span><span data-val="2">★</span><span data-val="3">★</span><span data-val="4">★</span><span data-val="5">★</span>
          </div>
          <input type="hidden" id="guideRating" value="0">
          <textarea id="guideComment" class="inp" rows="3" placeholder="Write your feedback for the guide..." style="margin-top:12px;resize:none;width:100%;"></textarea>
          
          <!-- Guide Photo Upload -->
          <div style="margin-top:16px;">
            <label class="inp-label">Upload Photos (max 5)</label>
            <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
              <div class="photo-upload-zone">
                <input type="file" id="guidePhotos" multiple accept="image/*" class="photo-input">
                <div class="photo-upload-placeholder">
                  <i class="fas fa-camera"></i>
                  <span>Add Photos</span>
                </div>
              </div>
              <div id="guidePhotoPreview" class="review-photo-preview" style="margin-top:0;"></div>
            </div>
          </div>
        </div>
      </div>
      
      <button class="btn btn-primary btn-full" id="submitReviewBtn" onclick="submitReview()">Post Reviews</button>
    </div>
  </div>
</div>

<!-- ── CONFIRMATION MODAL ── -->
<div class="modal-bg" id="confirmModal">
  <div class="modal" style="max-width:400px; text-align:center;">
    <div class="modal-body" style="padding:40px 28px;">
      <div style="font-size:48px; margin-bottom:20px;" id="confirmIcon">⚠️</div>
      <div class="modal-title" id="confirmTitle" style="margin-bottom:12px;">Are you sure?</div>
      <p id="confirmText" style="font-size:14px; color:var(--stone); margin-bottom:28px;">This action cannot be undone.</p>
      <div style="display:flex; gap:12px;">
        <button class="btn btn-outline btn-full" onclick="closeConfirmModal()">No, Keep it</button>
        <button class="btn btn-danger btn-full" id="confirmBtn">Yes, Proceed</button>
      </div>
    </div>
  </div>
</div>

<!-- ── PROMPT MODAL ── -->
<div class="modal-bg" id="promptModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-hdr">
      <div class="modal-title" id="promptTitle">Enter Information</div>
      <button class="modal-close" onclick="closePromptModal()">×</button>
    </div>
    <div class="modal-body" style="padding:20px 28px 28px;">
      <p id="promptText" style="font-size:13px; color:var(--stone); margin-bottom:12px;"></p>
      <input type="text" id="promptInput" class="inp" style="width:100%; margin-bottom:20px;">
      <div style="display:flex; gap:12px;">
        <button class="btn btn-outline btn-full" onclick="closePromptModal()">Cancel</button>
        <button class="btn btn-primary btn-full" id="promptSubmitBtn">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>

<?php
// Output database data as JavaScript variables
echo "const mountains = " . json_encode($dbMountains) . ";\n";
echo "const guides = " . json_encode($dbGuides) . ";\n";
echo "const currentUserName = " . json_encode($user_name) . ";\n";
echo "const currentUserId = " . json_encode($currentUserId) . ";\n";
echo "let bookings = " . json_encode($dbUserBookings) . ";\n";
echo "let nextId = Math.max(...bookings.map(b => parseInt(b.id?.replace('BK', '')) || 0), 10) + 1;\n";
?>


function loadBookings() {
    // bookings is already populated from PHP/DB at page load.
    // Sync nextId with existing IDs
    const ids = bookings.map(b => parseInt((b.id || '').replace(/\D/g, '')) || 0);
    nextId = Math.max(10, ...ids) + 1;
}

function saveBookings() {
    // No-op: Bookings are persisted to the database via AJAX actions.
}

function genId() { return "BK" + String(nextId++).padStart(3,'0'); }

// ── FLOW STATE ──
let flowState = {};
let currentStep = 0;
let flowMode = null;
let foundHike = null;
let lastCreatedId = '';

function openBookingFlow() {
  // Use the actual logged-in user's name from PHP
  const loggedInUserName = currentUserName;
  flowState = {
    step: 1,
    mtn: null,
    type: 'day',
    pax: 1,
    solo: true,
    hikers: [loggedInUserName],
    bookerName: loggedInUserName,
    guide: null,
    date: '',
    time: '',
    camping: false
  };
  currentStep = 0;
  flowMode = null;
  foundHike = null;
  document.getElementById('flowSteps').style.display = 'none';
  document.getElementById('bookingModal').classList.add('open');
  renderStep(0);
}

function closeBookingModal() { document.getElementById('bookingModal').classList.remove('open'); }

function updateStepIndicators(n) {
  const el = document.getElementById('flowSteps');
  if(n === 0 || n === 'join') { el.style.display='none'; return; }
  el.style.display = 'flex';
  for(let i=1;i<=4;i++){
    const s=document.getElementById('fs'+i);
    if(!s) continue;
    s.className='flow-step';
    if(i<n) s.classList.add('done');
    else if(i===n) s.classList.add('active');
  }
}

function renderStep(n) {
  currentStep = n;
  updateStepIndicators(n);
  const body = document.getElementById('flowBody');
  const footer = document.getElementById('flowFooter');

  if(n === 0) {
    document.getElementById('flowTitle').textContent = "Let's get started";
    document.getElementById('flowSubtitle').textContent = 'What would you like to do?';
    body.innerHTML = `
      <div class="mode-choice-wrap">
        <div class="mode-choice-card" id="modeJoin" onclick="selectMode('join')">
          <div class="mode-choice-icon join-icon">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/></svg>
          </div>
          <div class="mode-choice-title">I already have a hike</div>
          <div class="mode-choice-desc">Enter a Booking ID to join an existing hike and track it here.</div>
        </div>
        <div class="mode-choice-card" id="modeBook" onclick="selectMode('book')">
          <div class="mode-choice-icon book-icon">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          </div>
          <div class="mode-choice-title">Book a new hike</div>
          <div class="mode-choice-desc">Choose a mountain, pick a guide, and set your adventure date.</div>
        </div>
      </div>`;
    footer.innerHTML = `<button class="btn btn-outline btn-full" onclick="closeBookingModal()">Cancel</button><button class="btn btn-primary btn-full" id="modeNextBtn" onclick="proceedFromMode()" disabled>Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n === 'join') {
    document.getElementById('flowTitle').textContent = 'Enter Booking ID';
    document.getElementById('flowSubtitle').textContent = 'Join an existing hike';
    body.innerHTML = `
      <div class="info-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Ask your hike organizer for the Booking ID (e.g. <strong>BK-2026-001</strong>).</span>
      </div>
      <div class="hike-id-input-row">
        <input type="text" id="hikeIdInput" placeholder="e.g. BK-2026-001" maxlength="15" oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')lookupHikeId()">
        <button class="btn btn-primary" onclick="lookupHikeId()">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          Look Up
        </button>
      </div>
      <div id="hikeIdResult"></div>`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(0)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" id="joinConfirmBtn" onclick="confirmJoinHike()" disabled>Join This Hike
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;
} else if(n===1) {
    document.getElementById('flowTitle').textContent = 'Book a Hike';
    document.getElementById('flowSubtitle').textContent = 'Choose your mountain';
    body.innerHTML = mountains.map(m=>`
      <div class="mtn-select-card" id="ms${m.id}" onclick="selectMtn(${m.id})">
        <div class="mtn-select-img" style="background-image:url('${m.image}')"></div>
        <div>
          <div class="mtn-select-name">${m.name}</div>
          <div class="mtn-select-meta">${m.location} · <span class="badge badge-${m.difficulty}">${m.difficulty}</span></div>
        </div>
      </div>`).join('');
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(0)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="nextStep()" id="nextBtn1" disabled>Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n===2) {
    document.getElementById('flowTitle').textContent = flowState.mtn?.name || '';
    document.getElementById('flowSubtitle').textContent = 'Hike details';
    const todayStr = new Date().toISOString().split('T')[0];
    body.innerHTML = `
      <div class="type-toggle-group">
        <div class="inp-label">Hike Type</div>
        <div class="type-toggle">
          <button type="button" class="type-btn ${flowState.type==='day'?'active':''}" onclick="setType('day')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            Day (12am–3pm)
          </button>
          <button type="button" class="type-btn ${flowState.type==='late'?'active':''}" onclick="setType('late')">
            <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            Late (4pm–12am)
          </button>
          <button type="button" class="type-btn ${flowState.type==='overnight'?'active':''}" onclick="setType('overnight')">
            <svg viewBox="0 0 24 24"><path d="M12 3v1M12 20v1M3 12h1M20 12h1"/><circle cx="12" cy="12" r="4"/><path d="M12 8a4 4 0 0 0 0 8"/></svg>
            Overnight
          </button>
        </div>
      </div>
      <div class="type-toggle-group">
        <div class="inp-label">Hiking Party</div>
        <div class="type-toggle">
          <button type="button" class="type-btn ${flowState.solo?'active':''}" onclick="setSolo(true)">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Solo
          </button>
          <button type="button" class="type-btn ${!flowState.solo?'active':''}" onclick="setSolo(false)">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Group
          </button>
        </div>
      </div>
      <div id="groupSection" style="display:${flowState.solo?'none':'block'};">
        <div class="hikers-section">
          <div class="inp-label">Add Hikers</div>
          <div class="hiker-input-row">
            <input type="text" id="hikerFirstName" placeholder="First name" maxlength="50">
            <input type="text" id="hikerLastName" placeholder="Last name" maxlength="50">
            <button class="btn btn-primary btn-sm" onclick="addHiker()">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add
            </button>
          </div>
          <div id="hikerListContainer" class="hiker-list"></div>
          <div class="hiker-count-display" id="hikerCount">${flowState.hikers.length}/12 hikers</div>
        </div>
      </div>
      ${flowState.type==='overnight'?`
      <div style="margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--sky);border-radius:10px;border:1.5px solid ${flowState.camping?'var(--forest)':'transparent'};">
          <input type="checkbox" id="campingCheck" ${flowState.camping?'checked':''} onchange="flowState.camping=this.checked;this.closest('label').style.borderColor=this.checked?'var(--forest)':'transparent'">
          <div>
            <div style="font-weight:600;font-size:13px;color:var(--forest);">Camping overnight</div>
            <div style="font-size:11px;color:var(--stone);">Additional ₱50/person camping fee</div>
          </div>
        </label>
      </div>`:''}
      <div class="datetime-section">
        <div class="inp-label" style="margin-bottom:10px;">Date &amp; Time</div>
        <div class="datetime-row">
          <div class="datetime-field">
            <div class="inp-label">Date</div>
            <div class="custom-date-wrapper">
              <div class="dt-icon-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
              <input type="date" id="hikeDate" value="${flowState.date}" min="${todayStr}" onchange="updateDatePreview(this.value)">
            </div>
            <div class="date-preview ${flowState.date?'visible':''}" id="datePreview"></div>
          </div>
          <div class="datetime-field">
            <div class="inp-label">Start Time</div>
            <div class="custom-time-wrapper">
              <div class="dt-icon-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
              <input type="time" id="hikeTime" value="${flowState.time}" 
                min="${flowState.type==='day'?'00:00':(flowState.type==='late'?'16:00':'00:00')}"
                max="${flowState.type==='day'?'15:00':(flowState.type==='late'?'23:59':'23:59')}"
                onchange="updateTimePreview(this.value)">
            </div>
            <div class="time-display ${flowState.time?'visible':''}" id="timeDisplay"></div>
            <div style="font-size:10px;color:var(--stone);margin-top:4px;">
                ${flowState.type==='day'?'Day Hike: 12am-3pm':(flowState.type==='late'?'Late Hike: 4pm-12am':'Flexible time')}
            </div>
          </div>
        </div>
      </div>`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(1)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="nextStep()">Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;
    if(flowState.solo) { updateHikersUI(); }
    else { updateHikersUI(); }
    if(flowState.date) updateDatePreview(flowState.date);
    if(flowState.time) updateTimePreview(flowState.time);

  } else if(n===3) {
    document.getElementById('flowTitle').textContent = 'Choose a Guide';
    document.getElementById('flowSubtitle').textContent = 'A tour guide is required';
    const avail = guides.filter(g=>g.mountains.includes(flowState.mtn.id));
    body.innerHTML = `
      <div class="warning-card" style="margin-bottom:14px;">
        <h4><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Tour Guide Required</h4>
        <p>For safety, all hikes require a licensed local tour guide.</p>
      </div>
      ${avail.map(g=>`
        <div class="guide-select-card" id="gs${g.id}" onclick="selectGuide(${g.id})">
          <div class="guide-av-sm">${g.initials}</div>
          <div class="guide-select-info">
            <div class="guide-select-name">${g.name}</div>
            <div class="guide-select-meta">★ ${g.rating} · ${g.mountains.map(mid=>mountains.find(m=>m.id===mid)?.name).join(', ')}</div>
            <div class="guide-avail"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Available ${g.available}</div>
          </div>
        </div>`).join('')}`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(2)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="nextStep()" id="nextGuideBtn" disabled>Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n===4) {
    document.getElementById('flowTitle').textContent = 'Review & Confirm';
    document.getElementById('flowSubtitle').textContent = 'Booking summary';
    const m=flowState.mtn,f=m.fees,pax=flowState.hikers.length;
    const isON=flowState.type==='overnight';
    const guideF=isON?f.guideON:f.guideDay;
    const regTotal=f.regFee*pax, envTotal=f.envFee?f.envFee*pax:0;
    const campF=(isON&&flowState.camping&&f.campFee)?f.campFee*pax:0;
    const total=regTotal+envTotal+guideF+campF;
    const typeNames={day:'Day Hike (12am–3pm)',late:'Late Hike (4pm–12am)',overnight:'Overnight'};
    body.innerHTML = `
      <div style="background:var(--sky);border-radius:12px;padding:16px;margin-bottom:16px;">
        <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${m.name}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="sr-label">Date & Time</span><span class="sr-val">${flowState.date}${flowState.time?' at '+flowState.time:''}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeNames[flowState.type]}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Hikers (${pax})</span><span class="sr-val">${flowState.hikers.join(', ')}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${flowState.guide?.name}</span></div>
      </div>
      <div style="background:var(--white);border-radius:12px;border:1px solid rgba(16,6,0,0.08);padding:16px;margin-bottom:14px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--sage);margin-bottom:10px;">Fee Breakdown</div>
        <div class="fee-row"><span>Registration (₱${f.regFee} × ${pax})</span><span>₱${regTotal}</span></div>
        ${envTotal?`<div class="fee-row"><span>Environmental fee (₱${f.envFee} × ${pax})</span><span>₱${envTotal}</span></div>`:''}
        <div class="fee-row"><span>Guide fee (${isON?'overnight':'day'})</span><span>₱${guideF}</span></div>
        ${campF?`<div class="fee-row"><span>Camping fee (₱${f.campFee} × ${pax})</span><span>₱${campF}</span></div>`:''}
        <div class="fee-row total"><span>Total</span><span>₱${total.toLocaleString()}</span></div>
      </div>
      <div class="warning-card">
        <h4><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Payment Policy</h4>
        <p><strong>No payment required now.</strong> Booking stays PENDING until the guide accepts. Payment is arranged directly with your guide after the hike.</p>
      </div>`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(3)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="createBooking()">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      Confirm Booking
    </button>`;
  }
}

// ── DATE/TIME PREVIEW ──
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function updateDatePreview(val) {
  const el = document.getElementById('datePreview');
  if(!el || !val) return;
  const d = new Date(val + 'T00:00:00');
  const month = MONTHS[d.getMonth()];
  const day = d.getDate();
  const weekday = DAYS[d.getDay()];
  const fulldate = `${month} ${day}, ${d.getFullYear()}`;
  el.className = 'date-preview visible';
  el.innerHTML = `
    <div class="date-preview-icon">
      <div class="month">${month}</div>
      <div class="day">${day}</div>
    </div>
    <div class="date-preview-info">
      <div class="weekday">${weekday}</div>
      <div class="fulldate">${fulldate}</div>
    </div>`;
}

function updateTimePreview(val) {
  const el = document.getElementById('timeDisplay');
  if(!el || !val) return;
  const [h, m] = val.split(':').map(Number);
  const period = h < 12 ? 'AM' : 'PM';
  const h12 = h === 0 ? 12 : h > 12 ? h - 12 : h;
  const fmt = `${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
  el.className = 'time-display visible';
  el.innerHTML = `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>${fmt} <span class="time-period">${period}</span></span>`;
}

// ── MODE SELECTION ──
function selectMode(mode) {
  flowMode = mode;
  document.getElementById('modeJoin').classList.toggle('selected', mode==='join');
  document.getElementById('modeBook').classList.toggle('selected', mode==='book');
  document.getElementById('modeNextBtn').disabled = false;
}
function proceedFromMode() {
  if(!flowMode) { showToast('Please choose an option'); return; }
  if(flowMode==='join') renderStep('join');
  else renderStep(1);
}
function lookupHikeId() {
  const input = (document.getElementById('hikeIdInput')?.value||'').trim().toUpperCase();
  const resultEl = document.getElementById('hikeIdResult');
  const confirmBtn = document.getElementById('joinConfirmBtn');
  if(!input) { showToast('Please enter a Booking ID'); return; }
  
  resultEl.innerHTML = `<div style="text-align:center;padding:20px;color:var(--stone);"><i class="fas fa-spinner fa-spin"></i> Searching...</div>`;
  confirmBtn.disabled = true;

  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: `action=lookup_hike&booking_number=${input}`
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) {
      const hike = data.hike;
      foundHike = {
          id: hike.booking_number,
          db_id: hike.id,
          mountain: hike.mountain,
          date: hike.date,
          type: hike.type,
          guideName: hike.guideName,
          hikers: hike.hikers,
          status: hike.status,
          guideInitials: hike.guideName.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase(),
          pax: hike.pax,
          mountainId: hike.mountain_id,
          guideId: hike.guide_id,
          camping: hike.camping
      };

      const typeMap = {day_hike:'Day Hike',late:'Late Hike',overnight:'Overnight'};
      const isAlreadyIn = hike.alreadyJoined;
      
      resultEl.innerHTML = `
        <div class="hike-id-found-card">
          <div class="hif-label"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Hike Found!</div>
          <div class="hif-row"><span class="hif-key">Booking ID</span><span class="hif-val">${hike.booking_number}</span></div>
          <div class="hif-row"><span class="hif-key">Mountain</span><span class="hif-val">${hike.mountain}</span></div>
          <div class="hif-row"><span class="hif-key">Date</span><span class="hif-val">${hike.date}</span></div>
          <div class="hif-row"><span class="hif-key">Type</span><span class="hif-val">${typeMap[hike.type]||hike.type}</span></div>
          <div class="hif-row"><span class="hif-key">Tour Guide</span><span class="hif-val">${hike.guideName}</span></div>
          <div class="hif-row"><span class="hif-key">Status</span><span class="hif-val">${hike.status.charAt(0).toUpperCase()+hike.status.slice(1)}</span></div>
          <div class="hif-row"><span class="hif-key">Current Hikers</span><span class="hif-val">${hike.hikers.join(', ')}</span></div>
          ${isAlreadyIn ? `<div style="margin-top:16px; padding:12px; background:rgba(74,222,128,0.1); color:#166534; border-radius:8px; font-size:13px; font-weight:600; text-align:center;">✓ You have already joined this hike</div>` : ''}
        </div>`;
      
      confirmBtn.disabled = isAlreadyIn;
      if (isAlreadyIn) {
          confirmBtn.innerHTML = `Already Joined <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`;
      } else {
          confirmBtn.innerHTML = `Join This Hike <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>`;
      }
    } else {
      foundHike = null;
      resultEl.innerHTML = `<div class="hike-id-not-found"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>${data.message || 'No hike found'}. Check the Booking ID and try again.</div>`;
      confirmBtn.disabled = true;
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Lookup failed. Try again.');
  });
}


// ── NORMAL FLOW HELPERS ──
function selectMtn(id) {
  flowState.mtn = mountains.find(m=>m.id===id);
  document.querySelectorAll('.mtn-select-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('ms'+id)?.classList.add('selected');
  document.getElementById('nextBtn1').disabled = false;
}
function setType(t) {
  flowState.type = t;
  if(currentStep===2) { saveStep2Temp(); renderStep(2); }
}
function setSolo(s) {
  flowState.solo = s;
  if(s) { flowState.hikers = [flowState.bookerName]; flowState.pax = 1; }
  if(currentStep===2) { saveStep2Temp(); renderStep(2); }
}
function saveStep2Temp() {
  const d = document.getElementById('hikeDate')?.value;
  const t = document.getElementById('hikeTime')?.value;
  if(d) flowState.date = d;
  if(t) flowState.time = t;
  const cc = document.getElementById('campingCheck');
  if(cc) flowState.camping = cc.checked;
}
function updateHikersUI() {
  const c = document.getElementById('hikerListContainer'); if(!c) return;
  c.innerHTML = flowState.hikers.map((h,i)=>`
    <div class="hiker-list-item ${i===0?'booker':''}">
      <div class="hiker-info">
        <div class="hiker-avatar">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>
        <div>
          <div class="hiker-name">${h}</div>
          <div class="hiker-tag">${i===0?'Booking organizer':('Hiker '+(i+1))}</div>
        </div>
      </div>
      ${i>0?`<div class="hiker-actions">
        <button class="hiker-action-btn edit" onclick="editHikerInline(${i})" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg>
        </button>
        <button class="hiker-action-btn remove" onclick="removeHiker(${i})" title="Remove">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>`:''}
    </div>
    <div class="hiker-edit-inline" id="hikeredit${i}">
      <input type="text" id="hikerEditVal${i}" value="${h}" placeholder="Full name">
      <button class="save-edit" onclick="saveHikerEdit(${i})">Save</button>
      <button class="save-edit" style="background:var(--sky);color:var(--stone);" onclick="document.getElementById('hikeredit${i}').classList.remove('visible')">Cancel</button>
    </div>`).join('');
  const cd = document.getElementById('hikerCount');
  if(cd) cd.textContent = `${flowState.hikers.length}/12 hikers`;
}
function addHiker() {
  const fn = document.getElementById('hikerFirstName')?.value.trim();
  const ln = document.getElementById('hikerLastName')?.value.trim();
  if(!fn) { showToast('Please enter a first name'); return; }
  if(flowState.hikers.length>=12) { showToast('Maximum 12 hikers'); return; }
  flowState.hikers.push(`${fn} ${ln}`.trim());
  flowState.pax = flowState.hikers.length;
  document.getElementById('hikerFirstName').value='';
  document.getElementById('hikerLastName').value='';
  updateHikersUI();
}
function removeHiker(i) {
  if(i===0) return;
  flowState.hikers.splice(i,1);
  flowState.pax = flowState.hikers.length;
  updateHikersUI();
}
function editHikerInline(i) {
  document.querySelectorAll('.hiker-edit-inline').forEach(el=>el.classList.remove('visible'));
  const el = document.getElementById('hikeredit'+i);
  if(el) el.classList.add('visible');
}
function saveHikerEdit(i) {
  const val = document.getElementById('hikerEditVal'+i)?.value.trim();
  if(!val) { showToast('Name cannot be empty'); return; }
  flowState.hikers[i] = val;
  updateHikersUI();
}
function selectGuide(id) {
  flowState.guide = guides.find(g=>g.id===id);
  document.querySelectorAll('.guide-select-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('gs'+id)?.classList.add('selected');
  document.getElementById('nextGuideBtn').disabled = false;
}
function nextStep() {
  if(currentStep===1&&!flowState.mtn) { showToast('Please select a mountain'); return; }
  if(currentStep===2) {
    const d = document.getElementById('hikeDate')?.value;
    const t = document.getElementById('hikeTime')?.value;
    if(!d) { showToast('Please select a date'); return; }
    
    // Time validation based on hike type
    if(t) {
        const [h,m] = t.split(':').map(Number);
        if(flowState.type === 'day' && h > 15) {
            showToast('Day hikes must start before 3:00 PM'); return;
        }
        if(flowState.type === 'late' && h < 16) {
            showToast('Late hikes must start after 4:00 PM'); return;
        }
    }
    
    flowState.date=d; flowState.time=t;
    flowState.pax=flowState.hikers.length;
    const cc = document.getElementById('campingCheck');
    if(cc) flowState.camping=cc.checked;
    renderStep(3); return;
  }
  renderStep(currentStep+1);
}

function copyBookingId() {
  const id = document.getElementById('successBookingId').textContent;
  navigator.clipboard.writeText(id).then(()=>{
    const btn = document.getElementById('copyIdBtn');
    btn.classList.add('copied');
    btn.innerHTML = `<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Copied!`;
    setTimeout(()=>{
      btn.classList.remove('copied');
      btn.innerHTML = `<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy`;
    },2000);
  }).catch(()=>showToast('ID: '+id));
}

function closeSuccess() { document.getElementById('successModal').classList.remove('open'); }
function closeJoinSuccess() { document.getElementById('joinSuccessModal').classList.remove('open'); }

function todayCard(b, now, FIVE_H, TWENTY_M, MAX_N) {
    const typeMap = { day: 'Day Hike · 12am–3pm', late: 'Late Hike · 4pm–12am', overnight: 'Overnight' };
    const isJoined = !!b.joinedFromId;
 
    // Live clock countdown to hike time
    let countdownHTML = '';
    if (b.time) {
        const [hh, mm] = b.time.split(':').map(Number);
        const hikeMs = new Date();
        hikeMs.setHours(hh, mm, 0, 0);
        const diff = hikeMs - Date.now();
        if (diff > 0) {
            const hrs  = Math.floor(diff / 3600000);
            const mins = Math.floor((diff % 3600000) / 60000);
            countdownHTML = `
                <div class="today-countdown">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    ${hrs > 0 ? hrs + 'h ' : ''}${mins}m away
                </div>`;
        } else {
            countdownHTML = `
                <div class="today-countdown" style="background:rgba(201,168,76,0.2);border-color:rgba(201,168,76,0.4);color:var(--gold);">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Hike time now!
                </div>`;
        }
    }
 
    const startUrl = `active-hike.php?booking_id=${b.db_id || b.id}`;
 
    // Secondary actions: message guide
    const msgBtn = `
        <a href="messages.php?guide=${b.guideId}&guide_name=${encodeURIComponent(b.guideName)}"
           class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Message Guide
        </a>`;
 
    // Cancel/Leave logic: only pending gets cancel, joined gets leave, confirmed/active get nothing
    let cancelBtn = '';
    if (isJoined) {
        cancelBtn = `<button class="btn btn-danger btn-sm"
                   onclick="cancelBooking('${b.id}','leave')">
               <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
               Leave
           </button>`;
    } else if (b.status === 'pending') {
        cancelBtn = `<button class="btn btn-danger btn-sm"
                   onclick="cancelBooking('${b.id}')">
               <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
               Cancel
           </button>`;
    }
    // confirmed / active → no cancel button
 
    const feeNote = !isJoined
        ? `<div class="today-fee">₱${b.totalFee.toLocaleString()} <span>pay after hike</span></div>`
        : `<div style="font-size:12px;color:var(--stone);">Fees managed by organizer</div>`;
 
    // Status badge colour override for today (show confirmed green prominently)
    const statusLabel = { pending:'Pending', confirmed:'Confirmed', active:'Active', joined:'Joined' }[b.status] || b.status;
 
    return `
    <div class="today-hike-card">
 
      <!-- Green top banner -->
      <div class="today-banner">
        <div class="today-icon-ring">⛰️</div>
        <div class="today-banner-text">
          <div class="today-eyebrow">🟢 You have a hike today</div>
          <div class="today-headline">${b.mountain}</div>
        </div>
        ${countdownHTML}
      </div>
 
      <!-- Urgency strip -->
      <div class="today-urgency">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Head to the trailhead and tap <strong style="margin:0 3px;">Start Hike</strong> to begin GPS tracking &amp; earn badges.
      </div>
 
      <!-- Body -->
      <div class="today-body">
 
        <!-- Left: details -->
        <div class="today-details">
          <div class="today-meta">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            ${b.date}${b.time ? ' · ' + b.time : ''}
            <span class="sep">·</span>
            ${typeMap[b.type] || b.type}
            <span class="sep">·</span>
            <span class="badge status-${b.status}">${statusLabel}</span>
            ${isJoined ? '<span class="joined-badge">Joined</span>' : ''}
          </div>
 
          <div class="today-guide">
            <div class="guide-av-sm">${b.guideInitials}</div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:13px;color:var(--forest);">${b.guideName}</div>
              <div style="font-size:11px;color:var(--stone);">
                ${b.pax} hiker(s) · #${b.id}${isJoined ? ' · via ' + b.joinedFromId : ''}
              </div>
            </div>
          </div>
 
          ${feeNote}
 
          <!-- Secondary actions inline -->
          <div class="booking-actions" style="margin-top:12px;">
            ${msgBtn}
            ${cancelBtn}
          </div>
        </div>
 
        <!-- Right: big CTA -->
        <div class="today-cta">
          <a href="${startUrl}" class="btn-start-hike">
            <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Start Hike
          </a>
          <div style="font-size:10px;text-align:center;color:var(--stone);line-height:1.4;padding:0 4px;">
            Enables GPS tracking &amp; checkpoint badges
          </div>
        </div>
 
      </div>
    </div>`;
}
function renderBookings(){
    const cc = document.getElementById('currentBookings');
    const hc = document.getElementById('historyBookings');
    const now = Date.now(), FIVE_H = 18000000, TWENTY_M = 1200000, MAX_N = 10;
 
    // Split into today vs the rest
    const todayDate = new Date();
    todayDate.setHours(0, 0, 0, 0);
 
    function isHikeToday(b) {
        try {
            if (!b.date) return false;
            const d = new Date(b.date);
            if (isNaN(d.getTime())) return false;
            d.setHours(0, 0, 0, 0);
            return d.getTime() === todayDate.getTime();
        } catch(e) { return false; }
    }
 
    // Separate arrays for different purposes
    const regularStatuses = ['pending', 'confirmed', 'active', 'joined'];  // For non-today current
    const todayStatuses = ['confirmed', 'active'];  // For today section only
    const historyStatuses = ['cancelled', 'completed', 'finished'];

    // ── Apply search filter ──
    const q = searchQuery.toLowerCase().trim();
    function matchesSearch(b) {
        if (!q) return true;
        const fields = [
            b.mountain, b.guideName, b.id, b.date, b.type,
            b.status, b.joinedFromId,
            ...(b.hikers || [])
        ].filter(Boolean);
        return fields.some(f => String(f).toLowerCase().includes(q));
    }
 
    // ── Count per status (for the filter chips) ──
    const currentTab = document.getElementById('tab-current').style.display !== 'none';
    const allBookingsForTab = bookings.filter(b =>
        currentTab ? regularStatuses.includes(b.status) : historyStatuses.includes(b.status)
    ).filter(matchesSearch);

    // Update chip counts
    const counts = {};
    allBookingsForTab.forEach(b => {
        counts[b.status] = (counts[b.status] || 0) + 1;
    });
    document.getElementById('countAll').textContent = allBookingsForTab.length;
    ['pending','confirmed','active','joined','finished','cancelled'].forEach(s => {
        const el = document.getElementById('count' + s.charAt(0).toUpperCase() + s.slice(1));
        if (el) el.textContent = counts[s] || 0;
    });

    // ── Apply status filter ──
    function matchesFilter(b) {
        if (activeFilter === 'all') return true;
        return b.status === activeFilter;
    }

    // "Today" cards: only confirmed or active (NOT pending)
    const todayBookings = bookings.filter(b =>
        todayStatuses.includes(b.status) && isHikeToday(b) && matchesSearch(b) && matchesFilter(b)
    );
 
    // Regular current: regularStatuses bookings NOT today (includes pending)
    const current = bookings.filter(b =>
        regularStatuses.includes(b.status) && !isHikeToday(b) && matchesSearch(b) && matchesFilter(b)
    );
 
    const history = bookings.filter(b =>
        historyStatuses.includes(b.status) && matchesSearch(b) && matchesFilter(b)
    );
 
    // Build current tab HTML
    const todayHTML = todayBookings.map(b => `
        <div class="today-hike-slot">
            ${todayCard(b, now, FIVE_H, TWENTY_M, MAX_N)}
        </div>
    `).join('');
 
    const regularHTML = current.length
        ? current.map(b => bookingCard(b, now, FIVE_H, TWENTY_M, MAX_N)).join('')
        : '';
 
    if (!todayHTML && !regularHTML) {
        if (q || activeFilter !== 'all') {
            cc.innerHTML = noFilterResults();
        } else {
            cc.innerHTML = emptyState();
        }
    } else {
        cc.innerHTML = todayHTML + regularHTML;
    }
 
    if (history.length) {
        hc.innerHTML = history.map(b => bookingCard(b, now, FIVE_H, TWENTY_M, MAX_N)).join('');
    } else {
        if (q || activeFilter !== 'all') {
            hc.innerHTML = noFilterResults();
        } else {
            hc.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">
                 <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                 <p>No booking history yet</p>
               </div>`;
        }
    }
}

function bookingCard(b, now, FIVE_H, TWENTY_M, MAX_N) {
  const ts = now - b.createdAt;
  const canReplace = b.status === 'pending' && ts >= FIVE_H;
  const isJoined = !!b.joinedFromId;
  const canNudge = b.status === 'pending' && !isJoined && ts < FIVE_H && b.nudges < MAX_N && (now - b.lastNudge) >= TWENTY_M;
  const rem = Math.max(0, FIVE_H - ts);
  const hL = Math.floor(rem / 3600000);
  const mL = Math.floor((rem % 3600000) / 60000);
  const showTimer = b.status === 'pending' && ts < FIVE_H;
  const typeMap = {day:'Day (12am–3pm)', late:'Late (4pm–12am)', overnight:'Overnight'};
  const joinedBadge = isJoined ? `<span class="joined-badge">Joined</span>` : '';
  
  // Check for today's active hike for START button with proper date handling
  let isToday = false;
  let canStart = false;

  try {
    const todayDate = new Date();
    todayDate.setHours(0, 0, 0, 0);
    
    let hikeDate = b.date ? new Date(b.date) : null;
    if (hikeDate && !isNaN(hikeDate.getTime())) {
      hikeDate.setHours(0, 0, 0, 0);
      isToday = hikeDate.getTime() === todayDate.getTime();
    }
    
    canStart = (b.status === 'confirmed' || b.status === 'active') && isToday;
    
    // Debug log - remove in production
    console.log(`Booking ${b.id}: date=${b.date}, isToday=${isToday}, status=${b.status}, canStart=${canStart}`);
  } catch(e) {
    console.error('Date parsing error:', e);
  }

  let startBtn = '';
  if (canStart) {
    startBtn = `
      <a href="active-hike.php?booking_id=${b.db_id || b.id}" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:white;margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        START HIKE
      </a>
    `;
  }
  
  // Status labels mapping
  const statusLabels = {pending:'Pending', confirmed:'Confirmed', completed:'Completed', cancelled:'Cancelled', joined:'Joined', active:'Active', finished:'Finished'};
  const statusLabel = statusLabels[b.status] || b.status;
  
  return `
    <div class="booking-card">
      <div class="booking-card-header">
        <div>
          <div class="booking-card-title">${b.mountain}${joinedBadge}</div>
          <div class="booking-card-date">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            ${b.date}${b.time ? ' · ' + b.time : ''} · ${typeMap[b.type] || b.type}
          </div>
        </div>
        <span class="badge status-${b.status}" style="white-space:nowrap;">${statusLabel}</span>
      </div>
      <div class="booking-card-guide">
        <div class="guide-av-sm">${b.guideInitials}</div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:13px;color:var(--forest);">${b.guideName}</div>
          <div style="font-size:11px;color:var(--stone);">
            ${b.pax} hiker(s) · #${b.id}${isJoined ? ' · via ' + b.joinedFromId : ''}
          </div>
        </div>
      </div>
      ${!isJoined ? `<div class="fee-total">₱${b.totalFee.toLocaleString()} <span style="font-size:11px;font-weight:400;color:var(--stone);">pay after hike</span></div>` : `<div style="font-size:12px;color:var(--stone);margin-top:4px;">Fees managed by organizer</div>`}
      ${showTimer ? `<div class="countdown-timer"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Guide response: ${hL}h ${mL}m remaining</div>` : ''}
      ${b.nudges > 0 ? `<div class="nudge-count"><svg viewBox="0 0 24 24" width="10" height="10" stroke="currentColor" fill="none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Nudges: ${b.nudges}/${MAX_N}</div>` : ''}
      
      <div class="booking-actions">
        ${startBtn}
        <a href="messages.php?guide=${b.guideId}&guide_name=${encodeURIComponent(b.guideName)}" class="btn btn-outline btn-sm">
          <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Message Guide
        </a>
        ${isJoined ? `
          <button class="btn btn-outline btn-sm" onclick="viewJoinedHike('${b.id}')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/></svg> View Details
          </button>
          <button class="btn btn-danger btn-sm" onclick="cancelBooking('${b.id}','leave')">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Leave Hike
          </button>
        ` : (b.status === 'pending' ? `
          ${canNudge ? `<button class="btn btn-outline btn-sm" onclick="nudgeGuide('${b.id}')">
            <svg width="12" height="12" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Nudge (${b.nudges + 1}/${MAX_N})
          </button>` : ''}
          <button class="btn btn-outline btn-sm" onclick="editBooking('${b.id}')">
            <svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg> Edit
          </button>
          ${canReplace ? `<button class="btn btn-outline btn-sm" onclick="openReplaceGuide('${b.id}')">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Replace Guide
          </button>` : ''}
          <button class="btn btn-danger btn-sm" onclick="cancelBooking('${b.id}')">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Cancel
          </button>
        ` : (b.status === 'confirmed' || b.status === 'active') ? `
          <button class="btn btn-outline btn-sm" onclick="viewActiveHikeDetails('${b.id}')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/></svg> View Details
          </button>
` 
          : (b.status === 'completed' || b.status === 'finished' || b.status === 'cancelled') ? `
          <button class="btn btn-outline btn-sm" onclick="viewActiveHikeDetails('${b.id}')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/></svg> View Details
          </button>
  ${(!b.hasReviewed && (b.status === 'completed' || b.status === 'finished')) ? `
    <button class="btn btn-primary btn-sm" onclick="openReviewModal('${b.id}')">
      <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> Write Review
    </button>
  ` : (b.hasReviewed && (b.status === 'completed' || b.status === 'finished')) ? `
    <button class="btn btn-outline btn-sm" onclick="openReviewModal('${b.id}')">
      <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> Edit Review
    </button>
  ` : ''}
` : '')}
      </div>
    </div>
  `;
}
// ── VIEW ACTIVE/CONFIRMED HIKE DETAILS (for future hikes) ──
function viewActiveHikeDetails(bookingId) {
  const b = bookings.find(x => x.id === bookingId);
  if (!b) return;
  
  const typeMap = { day: 'Day Hike (12am–3pm)', late: 'Late Hike (4pm–12am)', overnight: 'Overnight' };
  const statusLabel = { pending: 'Pending', confirmed: 'Confirmed', active: 'Active', joined: 'Joined' }[b.status] || b.status;
  const canStart = false; // Not today, so cannot start
  
  document.getElementById('vjTitle').textContent = b.mountain;
  document.getElementById('vjBody').innerHTML = `
    <div class="summary-box">
      <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${b.mountain}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><span class="sr-label">Date & Time</span><span class="sr-val">${b.date}${b.time ? ' at ' + b.time : ''}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeMap[b.type] || b.type}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${b.guideName}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/></svg><span class="sr-label">Booking ID</span><span class="sr-val" style="font-family:'DM Mono',monospace;">${b.id}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg><span class="sr-label">Status</span><span class="sr-val"><span class="badge status-${b.status}">${statusLabel}</span></span></div>
    </div>
    <div class="detail-section-label">Hiker List</div>
    <div class="detail-hikers-list">
      ${b.hikers.map(h => `<div class="detail-hiker-chip"><div class="dh-av">${h.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}</div>${h}</div>`).join('')}
    </div>
    ${!b.hasReviewed && (b.status === 'completed' || b.status === 'finished') ? `
    <div class="info-note" style="margin-top: 16px;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>Don't forget to leave a review for this hike!</span>
    </div>` : ''}
    ${(b.status === 'pending' || b.status === 'confirmed' || b.status === 'active' || b.status === 'joined') ? `
    <div class="info-note" style="margin-top:14px;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>This hike is scheduled for ${b.date}. The Start Hike button will appear on the day of your hike.</span>
    </div>` : ''}
    <div style="margin-top: 20px;">
      <a href="messages.php?guide=${b.guideId}&guide_name=${encodeURIComponent(b.guideName)}" class="btn btn-outline btn-full" style="margin-bottom: 8px;">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Message Guide
      </a>
    </div>`;
  
  document.getElementById('viewJoinedModal').classList.add('open');
}

function updateNudgeDisplay() {
    // This will refresh just the nudge counts without full re-render
    renderBookings();
}

// ── VIEW JOINED HIKE DETAILS ──
function viewJoinedHike(bookingId) {
  const b = bookings.find(x=>x.id===bookingId);
  if(!b) return;
  const orig = bookings.find(x=>x.id===b.joinedFromId);
  const typeMap={day:'Day Hike (12am–3pm)',late:'Late Hike (4pm–12am)',overnight:'Overnight'};
  document.getElementById('vjTitle').textContent = b.mountain;
  document.getElementById('vjBody').innerHTML = `
    <div class="summary-box">
      <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${b.mountain}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><span class="sr-label">Date & Time</span><span class="sr-val">${b.date}${b.time?' at '+b.time:''}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeMap[b.type]}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${b.guideName}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/></svg><span class="sr-label">Booking ID</span><span class="sr-val" style="font-family:'DM Mono',monospace;">${b.joinedFromId}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg><span class="sr-label">Status</span><span class="sr-val"><span class="badge status-${b.status}">${b.status}</span></span></div>
    </div>
    <div class="detail-section-label">Hiker List</div>
    <div class="detail-hikers-list">
      ${b.hikers.map(h=>`<div class="detail-hiker-chip"><div class="dh-av">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>${h}</div>`).join('')}
    </div>
    ${b.notes||orig?.notes?`
    <div class="detail-section-label">Notes & Advisories</div>
    <div class="advisory-box">
      ${(b.notes||orig?.notes||'').split('\n').filter(Boolean).map(line=>`
        <div class="advisory-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${line}</div>
      `).join('')}
    </div>`:''}
    <div class="info-note" style="margin-top:14px;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>You joined this hike. Fees and arrangements are handled by the booking organizer.</span>
    </div>`;
  document.getElementById('viewJoinedModal').classList.add('open');
}


let replaceBookingId=null, replaceGuideSelected=null;
function selectReplaceGuide(id) {
  replaceGuideSelected=id;
  document.querySelectorAll('.guide-replace-option').forEach(e=>e.classList.remove('selected'));
  document.querySelector(`.guide-replace-option[data-gid="${id}"]`)?.classList.add('selected');
}
function closeReplaceModal(){document.getElementById('replaceGuideModal').classList.remove('open');replaceGuideSelected=null;}

let editBookingId=null;
function editBooking(bookingId) {
  const b=bookings.find(x=>x.id===bookingId);
  if(!b||b.status!=='pending'){showToast('Only pending bookings can be edited');return;}
  editBookingId=bookingId;
  const todayStr=new Date().toISOString().split('T')[0];
  const hikerRows = b.hikers.map((h,i)=>`
    <div class="hiker-list-item ${i===0?'booker':''}" id="editHikerRow${i}" style="margin-bottom:6px;">
      <div class="hiker-info">
        <div class="hiker-avatar">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>
        <div>
          <div class="hiker-name" id="editHikerName${i}">${h}</div>
          <div class="hiker-tag">${i===0?'Organizer':'Hiker '+(i+1)}</div>
        </div>
      </div>
      ${i>0?`<div class="hiker-actions">
        <button class="hiker-action-btn edit" onclick="showEditHikerModal(${i},'${bookingId}')" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/></svg>
        </button>
        <button class="hiker-action-btn remove" onclick="removeEditHiker(${i},'${bookingId}')" title="Remove">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>`:''}
    </div>`).join('');
  document.getElementById('editModalBody').innerHTML = `
    <div class="edit-section">
      <div class="inp-label">Date</div>
      <div class="custom-date-wrapper" style="margin-bottom:6px;">
        <div class="dt-icon-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <input type="date" id="editDate" value="${b.date}" min="${todayStr}" onchange="updateEditDatePreview(this.value)">
      </div>
      <div class="date-preview ${b.date?'visible':''}" id="editDatePreview"></div>
    </div>
    <div class="edit-section">
      <div class="inp-label">Start Time</div>
      <div class="custom-time-wrapper" style="margin-bottom:6px;">
        <div class="dt-icon-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
        <input type="time" id="editTime" value="${b.time||''}" onchange="updateEditTimePreview(this.value)">
      </div>
      <div class="time-display ${b.time?'visible':''}" id="editTimeDisplay"></div>
    </div>
    <div class="edit-section">
      <div class="section-divider">Hiker Management</div>
      <div style="background:var(--sky);border-radius:12px;padding:14px;margin-top:8px;">
        <div id="editHikerList">${hikerRows}</div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px dashed rgba(16,6,0,0.1);">
          <div class="inp-label">Add Hiker</div>
          <div class="hiker-input-row">
            <input type="text" id="editHikerFn" placeholder="First name" style="padding:8px 10px;border:1.5px solid rgba(16,6,0,0.1);border-radius:8px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;outline:none;">
            <input type="text" id="editHikerLn" placeholder="Last name" style="padding:8px 10px;border:1.5px solid rgba(16,6,0,0.1);border-radius:8px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;outline:none;">
            <button class="btn btn-primary btn-sm" onclick="addEditHiker('${bookingId}')">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add
            </button>
          </div>
        </div>
        <div class="hiker-count-display" id="editHikerCount">${b.hikers.length}/12 hikers</div>
      </div>
    </div>
    <div class="edit-section">
      <div class="inp-label" style="margin-top:10px;">Notes / Reminders</div>
      <textarea id="editNotes" class="inp" rows="3" style="resize:vertical;" placeholder="e.g. Bring extra water, meet at trailhead...">${b.notes||''}</textarea>
    </div>`;
  if(b.date) updateEditDatePreview(b.date);
  if(b.time) updateEditTimePreview(b.time);
  document.getElementById('editBookingModal').classList.add('open');
}

function updateEditDatePreview(val) {
  const el=document.getElementById('editDatePreview'); if(!el||!val) return;
  const d=new Date(val+'T00:00:00');
  el.className='date-preview visible';
  el.innerHTML=`<div class="date-preview-icon"><div class="month">${MONTHS[d.getMonth()]}</div><div class="day">${d.getDate()}</div></div><div class="date-preview-info"><div class="weekday">${DAYS[d.getDay()]}</div><div class="fulldate">${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}</div></div>`;
}
function updateEditTimePreview(val) {
  const el=document.getElementById('editTimeDisplay'); if(!el||!val) return;
  const [h,m]=val.split(':').map(Number);
  const period=h<12?'AM':'PM', h12=h===0?12:h>12?h-12:h;
  el.className='time-display visible';
  el.innerHTML=`<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')} <span class="time-period">${period}</span></span>`;
}

function addEditHiker(bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const fn=document.getElementById('editHikerFn')?.value.trim();
  const ln=document.getElementById('editHikerLn')?.value.trim();
  if(!fn){showToast('Enter first name');return;}
  if(b.hikers.length>=12){showToast('Maximum 12 hikers');return;}
  b.hikers.push(`${fn} ${ln}`.trim());
  b.pax=b.hikers.length;
  saveBookings();
  refreshEditHikerList(bookingId);
  document.getElementById('editHikerFn').value='';
  document.getElementById('editHikerLn').value='';
  showToast('Hiker added');
}
function removeEditHiker(idx, bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  if(idx===0){showToast("Can't remove organizer");return;}
  
  openConfirmModal(
    'Remove Hiker?',
    `Are you sure you want to remove ${b.hikers[idx]} from this booking?`,
    '👤',
    'Remove Hiker',
    'btn-danger',
    () => {
      b.hikers.splice(idx,1); b.pax=b.hikers.length;
      saveBookings(); refreshEditHikerList(bookingId); showToast('Hiker removed');
    }
  );
}
let pendingPromptAction = null;
function openPromptModal(title, text, defaultValue, action) {
    document.getElementById('promptTitle').textContent = title;
    document.getElementById('promptText').textContent = text;
    document.getElementById('promptInput').value = defaultValue || '';
    pendingPromptAction = action;
    document.getElementById('promptModal').classList.add('open');
    setTimeout(() => document.getElementById('promptInput').focus(), 100);
}
function closePromptModal() { document.getElementById('promptModal').classList.remove('open'); }
document.getElementById('promptSubmitBtn').onclick = () => {
    const val = document.getElementById('promptInput').value.trim();
    if (pendingPromptAction) pendingPromptAction(val);
    closePromptModal();
};

function showEditHikerModal(idx, bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const name=b.hikers[idx];
  
  openPromptModal(
    'Edit Hiker Name',
    'Enter the full name of the hiker as it appears on their ID.',
    name,
    (newName) => {
        if(newName && newName.trim()){
            b.hikers[idx]=newName.trim(); saveBookings(); refreshEditHikerList(bookingId); showToast('Hiker updated');
        } else if (newName === "") {
            showToast('Name cannot be empty');
        }
    }
  );
}
function refreshEditHikerList(bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const el=document.getElementById('editHikerList'); if(!el) return;
  el.innerHTML=b.hikers.map((h,i)=>`
    <div class="hiker-list-item ${i===0?'booker':''}" style="margin-bottom:6px;">
      <div class="hiker-info">
        <div class="hiker-avatar">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>
        <div><div class="hiker-name">${h}</div><div class="hiker-tag">${i===0?'Organizer':'Hiker '+(i+1)}</div></div>
      </div>
      ${i>0?`<div class="hiker-actions">
        <button class="hiker-action-btn edit" onclick="showEditHikerModal(${i},'${bookingId}')" title="Edit"><svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/></svg></button>
        <button class="hiker-action-btn remove" onclick="removeEditHiker(${i},'${bookingId}')" title="Remove"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>`:''}
    </div>`).join('');
  const hc=document.getElementById('editHikerCount'); if(hc) hc.textContent=`${b.hikers.length}/12 hikers`;
}

function saveBookingEdit() {
  const b=bookings.find(x=>x.id===editBookingId); if(!b) return;
  const newDate=document.getElementById('editDate')?.value;
  const newTime=document.getElementById('editTime')?.value;
  const notes=document.getElementById('editNotes')?.value||'';
  if(!newDate){showToast('Please select a date');return;}
  b.date=newDate; b.time=newTime; b.notes=notes;
  // Recalculate fee
  const m=mountains.find(x=>x.id===b.mountainId);
  if(m){const isON=b.type==='overnight',f=m.fees;b.totalFee=f.regFee*b.pax+(f.envFee||0)+(isON?f.guideON:f.guideDay)+((isON&&b.camping&&f.campFee)?f.campFee*b.pax:0);}
  saveBookings(); renderBookings(); closeEditModal(); showToast('Booking updated!');
}
function closeEditModal(){document.getElementById('editBookingModal').classList.remove('open');}

let pendingConfirmAction = null;
function openConfirmModal(title, text, icon, btnText, btnClass, action) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmText').textContent = text;
  document.getElementById('confirmIcon').textContent = icon || '⚠️';
  const btn = document.getElementById('confirmBtn');
  btn.textContent = btnText || 'Yes, Proceed';
  btn.className = 'btn btn-full ' + (btnClass || 'btn-danger');
  pendingConfirmAction = action;
  document.getElementById('confirmModal').classList.add('open');
}
function closeConfirmModal() { document.getElementById('confirmModal').classList.remove('open'); }
document.getElementById('confirmBtn').onclick = () => {
  if (pendingConfirmAction) pendingConfirmAction();
  closeConfirmModal();
};

function cancelBooking(bookingId, mode) {
    const label = mode === 'leave' ? 'leave this hike' : 'cancel this booking';
    
    openConfirmModal(
      mode === 'leave' ? 'Leave Hike?' : 'Cancel Booking?',
      `Are you sure you want to ${label}? This will notify your guide and move the booking to your history.`,
      mode === 'leave' ? '🚪' : '❌',
      mode === 'leave' ? 'Yes, Leave' : 'Yes, Cancel',
      'btn-danger',
      () => {
        showToast('Processing cancellation...');
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: `action=cancel_booking&booking_id=${bookingId}`
        }).then(response => response.json()).then(result => {
            if (result.success) {
                const b = bookings.find(x => x.id === bookingId);
                if (b) {
                    b.status = 'cancelled';
                    showToast(mode === 'leave' ? '✓ You left the hike' : '✓ Booking cancelled');
                    renderBookings();
                    
                    fetch('../api/hiker_messages.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=send_system_message&guide_id=${b.guideId}&message=I had to ${mode === 'leave' ? 'leave' : 'cancel'} my booking for ${b.mountain} on ${b.date}. Sorry for the inconvenience! ❌`
                    });
                }
            } else {
                showToast(result.message || 'Failed to cancel booking.');
            }
        }).catch(err => {
            console.error(err);
            showToast('Network error.');
        });
      }
    );
}
function switchTab(tab,el){
  document.querySelectorAll('.page-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-current').style.display=tab==='current'?'block':'none';
  document.getElementById('tab-history').style.display=tab==='history'?'block':'none';

  // Reset filter & search
  activeFilter = 'all';
  searchQuery = '';
  document.getElementById('searchInput').value = '';
  document.getElementById('searchClear').classList.remove('show');

  // Toggle chip visibility per tab
  const currentChips = ['pending','confirmed','active','joined'];
  const historyChips = ['finished','cancelled'];
  document.querySelectorAll('.filter-chip[data-filter]').forEach(chip => {
    const f = chip.dataset.filter;
    if (f === 'all') { chip.style.display = ''; return; }
    if (tab === 'current') {
      chip.style.display = currentChips.includes(f) ? '' : 'none';
    } else {
      chip.style.display = historyChips.includes(f) ? '' : 'none';
    }
  });
  // Reset active chip
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  document.querySelector('.filter-chip[data-filter="all"]').classList.add('active');

  renderBookings();
}

function emptyState(){return`<div class="empty-state"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg><p>No current bookings</p><button class="btn btn-primary" onclick="openBookingFlow()">Book your first hike</button></div>`;}

function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) {
    console.warn('Toast element not found, using alert fallback:', msg);
    return;
  }
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

// ── CLICK-OUTSIDE TO CLOSE MODALS ──
['bookingModal','successModal','joinSuccessModal','viewJoinedModal','replaceGuideModal','editBookingModal', 'confirmModal', 'promptModal'].forEach(id=>{
  document.getElementById(id)?.addEventListener('click',e=>{
    if(e.target.id===id){
      document.getElementById(id).classList.remove('open');
    }
  });
});

function createBooking() {
    const m = flowState.mtn, f = m.fees, pax = flowState.hikers.length;
    const isON = flowState.type === 'overnight';
    const total = f.regFee * pax + (f.envFee ? f.envFee * pax : 0) + 
                  (isON ? f.guideON : f.guideDay) + 
                  ((isON && flowState.camping && f.campFee) ? f.campFee * pax : 0);
    const nb = {
        mountainId: m.id, mountain: m.name, date: flowState.date,
        time: flowState.time || '08:00', type: flowState.type, status: 'pending',
        guideId: flowState.guide.id, guideName: flowState.guide.name,
        guideInitials: flowState.guide.initials, pax,
        hikers: [...flowState.hikers], totalFee: total,
        createdAt: Date.now(), nudges: 0, lastNudge: 0,
        camping: flowState.camping || false, notes: ''
    };

    const btn = document.querySelector('#flowFooter .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

    console.log('Inserting booking data:', nb);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=save_booking&data=' + encodeURIComponent(JSON.stringify(nb))
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            nb.id = result.booking_id;
            bookings.unshift(nb);
            closeBookingModal();
            document.getElementById('successBookingId').textContent = nb.id;
            document.getElementById('successModal').classList.add('open');
            renderBookings();

            // Send booking confirmation message to guide
            fetch('../api/hiker_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_system_message&guide_id=${nb.guideId}&message=I just booked a hike with you! Mountain: ${nb.mountain}, Date: ${nb.date}, Hikers: ${nb.pax} persons. Looking forward to it! 🏔️`
            });
        } else {
            showToast('Error: ' + (result.message || 'Could not save booking'));
            if (btn) { btn.disabled = false; btn.textContent = 'Confirm Booking'; }
        }
    })
    .catch(err => {
        console.error('Booking save failed:', err);
        showToast('Network error — booking not saved. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm Booking'; }
    });
}

function nudgeGuide(bookingId) {
    const b = bookings.find(x => x.id === bookingId);
    if (!b || b.status !== 'pending') return showToast('Only pending bookings can be nudged');
    
    showToast(`Sending nudge to ${b.guideName}...`);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=nudge_guide&booking_id=${bookingId}&guide_id=${b.guideId}`
    }).then(response => response.json()).then(result => {
        if (result.success) {
            b.nudges++;
            b.lastNudge = Date.now();
            renderBookings();
            showToast(`🔔 Nudge sent to ${b.guideName}!`);

            // Send nudge message
            fetch('../api/hiker_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_system_message&guide_id=${b.guideId}&message=Hi! Just a friendly reminder about my upcoming hike booking. Let me know if you have any updates! 👋`
            });
        } else {
            showToast(result.message);
        }
    }).catch(err => {
        console.error(err);
        showToast('Network error.');
    });
}
function confirmJoinHike() {
    if (!foundHike) return showToast('No hike selected');
    
    // Check if already joined
    const alreadyJoined = bookings.some(b => b.joinedFromId === foundHike.id);
    if (alreadyJoined) {
        showToast('You have already joined this hike! ✓');
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=join_hike&booking_number=${foundHike.id}`
    }).then(response => response.json()).then(result => {
        if (result.success) {
            const joined = {
                id: foundHike.id, mountainId: foundHike.mountainId, mountain: foundHike.mountain,
                date: foundHike.date, time: foundHike.time, type: foundHike.type,
                status: 'joined', guideId: foundHike.guideId, guideName: foundHike.guideName,
                guideInitials: foundHike.guideInitials, pax: foundHike.pax, hikers: [...foundHike.hikers],
                totalFee: 0, createdAt: Date.now(), nudges: 0, lastNudge: 0,
                camping: foundHike.camping||false, joinedFromId: foundHike.id,
                notes: foundHike.notes||''
            };
            bookings.unshift(joined);
            closeBookingModal();
            renderBookings();
            showToast('✓ You successfully joined the hike!');
        } else {
            // Check if the error message indicates already joined
            if (result.message && result.message.includes('Could not join')) {
                showToast('You have already joined this hike ✓');
            } else {
                showToast(result.message || 'Could not join the hike');
            }
        }
    }).catch(err => {
        console.error(err);
        showToast('Error joining hike. Please try again.');
    });
}

function confirmReplaceGuide() {
    if (!replaceGuideSelected) return showToast('Select a guide first');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=replace_guide&booking_id=${replaceBookingId}&new_guide_id=${replaceGuideSelected}`
    }).then(response => response.json()).then(result => {
        if (result.success) {
            const b = bookings.find(x => x.id === replaceBookingId);
            const g = guides.find(x => x.id === replaceGuideSelected);
            if (b && g) {
                b.guideId = g.id; b.guideName = g.name; b.guideInitials = g.initials;
                b.status = 'pending'; b.nudges = 0;
                renderBookings();
                closeReplaceModal();
                showToast(`✓ Guide replaced with ${g.name}`);
            }
        } else {
            showToast(result.message);
        }
    }).catch(err => {
        console.error(err);
        showToast('Error replacing guide.');
    });
}

function openReplaceGuide(bookingId) {
    replaceBookingId = bookingId;
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=get_available_guides&booking_id=${bookingId}`
    }).then(response => response.json()).then(result => {
        if (result.success && result.guides.length > 0) {
            document.getElementById('replaceGuideList').innerHTML = result.guides.map(g => `
                <div class="guide-replace-option" onclick="selectReplaceGuide(${g.id})" data-gid="${g.id}">
                    <div class="guide-av-sm">${g.name.charAt(0)}</div>
                    <div><div class="guide-select-name">${g.name}</div><div class="guide-select-meta">★ ${g.rating} · ${g.years_experience} years</div></div>
                </div>
            `).join('');
            document.getElementById('replaceGuideModal').classList.add('open');
        } else {
            document.getElementById('replaceGuideList').innerHTML = '<div class="info-note"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>No other guides available for this mountain.</div>';
            document.getElementById('replaceGuideModal').classList.add('open');
        }
    }).catch(err => {
        console.error('Replace guide failed:', err);
        showToast('Network error.');
    });
}


// ── REVIEW SYSTEM ──
function openReviewModal(bookingId) {
    const b = bookings.find(x => x.id === bookingId);
    if (!b) return;
    
    document.getElementById('revBookingId').value = bookingId;
    document.getElementById('revMtnName').textContent = b.mountain;
    document.getElementById('revGuideName').textContent = b.guideName;
    
    // Reset stars
    resetStars('mtnStars', 'mtnRating');
    resetStars('guideStars', 'guideRating');
    document.getElementById('mtnTitle').value = '';
    document.getElementById('mtnComment').value = '';
    document.getElementById('guideComment').value = '';
    
    document.getElementById('reviewModal').classList.add('open');
}

function resetStars(containerId, hiddenId) {
    const stars = document.querySelectorAll(`#${containerId} span`);
    stars.forEach(s => s.classList.remove('active'));
    document.getElementById(hiddenId).value = "0";
}

// Add event listeners for star ratings
function initStars() {
    document.querySelectorAll('.star-rating span').forEach(star => {
        star.addEventListener('click', function() {
            const val = this.getAttribute('data-val');
            const container = this.parentElement;
            const hiddenId = container.id === 'mtnStars' ? 'mtnRating' : 'guideRating';
            
            document.getElementById(hiddenId).value = val;
            const stars = container.querySelectorAll('span');
            stars.forEach(s => {
                s.classList.toggle('active', s.getAttribute('data-val') <= val);
            });
        });
    });
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('open');
}
// Global variables for tracking edit mode
let editingMtnReview = false;
let editingGuideReview = false;
let existingMtnData = null;
let existingGuideData = null;
let uploadedMtnPhotos = [];
let uploadedGuidePhotos = [];

// Function to check if user has already reviewed
function checkExistingReviews(bookingId) {
    return fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=get_existing_reviews&booking_id=${bookingId}`
    }).then(response => response.json());
}

// Enhanced openReviewModal
function openReviewModal(bookingId) {
    const b = bookings.find(x => x.id === bookingId);
    if (!b) return;
    
    document.getElementById('revBookingId').value = bookingId;
    document.getElementById('revMtnName').textContent = b.mountain;
    document.getElementById('revGuideName').textContent = b.guideName;
    
    // Reset forms
    resetStars('mtnStars', 'mtnRating');
    resetStars('guideStars', 'guideRating');
    document.getElementById('mtnTitle').value = '';
    document.getElementById('mtnComment').value = '';
    document.getElementById('guideComment').value = '';
    document.getElementById('mtnPhotos').value = '';
    document.getElementById('guidePhotos').value = '';
    uploadedMtnPhotos = [];
    uploadedGuidePhotos = [];
    document.getElementById('mtnPhotoPreview').innerHTML = '';
    document.getElementById('guidePhotoPreview').innerHTML = '';
    
    // Reset edit mode flags
    editingMtnReview = false;
    editingGuideReview = false;
    existingMtnData = null;
    existingGuideData = null;
    
    // Show forms, hide existing review displays
    document.getElementById('mtnReviewForm').style.display = 'block';
    document.getElementById('guideReviewForm').style.display = 'block';
    document.getElementById('existingMtnReview').style.display = 'none';
    document.getElementById('existingGuideReview').style.display = 'none';
    document.getElementById('submitReviewBtn').textContent = 'Post Reviews';
    document.getElementById('submitReviewBtn').disabled = false;
    
    // Check for existing reviews
    checkExistingReviews(bookingId).then(result => {
        if (result.success) {
            if (result.mountain_review) {
                existingMtnData = result.mountain_review;
                displayExistingReview('mtn', existingMtnData);
            }
            if (result.guide_review) {
                existingGuideData = result.guide_review;
                displayExistingReview('guide', existingGuideData);
            }
            
            // If both reviews exist, disable submit button
            if (existingMtnData && existingGuideData && !editingMtnReview && !editingGuideReview) {
                document.getElementById('submitReviewBtn').disabled = true;
                document.getElementById('submitReviewBtn').textContent = 'Reviews Already Submitted';
            } else if (existingMtnData && !editingMtnReview) {
                document.getElementById('mtnReviewForm').style.display = 'none';
            }
            if (existingGuideData && !editingGuideReview) {
                document.getElementById('guideReviewForm').style.display = 'none';
            }
        }
    });
    
    document.getElementById('reviewModal').classList.add('open');
}

function displayExistingReview(type, reviewData) {
    const container = document.getElementById(`${type === 'mtn' ? 'existingMtnReview' : 'existingGuideReview'}`);
    const contentDiv = document.getElementById(`${type === 'mtn' ? 'mtnExistingContent' : 'guideExistingContent'}`);
    
    if (type === 'mtn') {
        // Display mountain review
        contentDiv.innerHTML = `
            <div style="margin-bottom:8px;">
                <span style="font-weight:700;">Rating:</span> 
                ${'★'.repeat(reviewData.rating)}${'☆'.repeat(5-reviewData.rating)}
            </div>
            <div style="margin-bottom:8px;">
                <span style="font-weight:700;">Title:</span> ${reviewData.title || 'No title'}
            </div>
            <div style="margin-bottom:8px;">
                <span style="font-weight:700;">Comment:</span> ${reviewData.comment || 'No comment'}
            </div>
        `;
        
        // Display existing photos
        if (reviewData.media && reviewData.media.length > 0) {
            contentDiv.innerHTML += `
                <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                    ${reviewData.media.map(photo => `
                        <img src="${photo}" style="width:70px;height:70px;object-fit:cover;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    `).join('')}
                </div>
            `;
        }
        
        document.getElementById(`existing${type === 'mtn' ? 'Mtn' : 'Guide'}Review`).style.display = 'block';
        document.getElementById(`${type}ReviewForm`).style.display = 'none';
    } else {
        // Display guide review
        contentDiv.innerHTML = `
            <div style="margin-bottom:8px;">
                <span style="font-weight:700;">Rating:</span> 
                ${'★'.repeat(reviewData.rating)}${'☆'.repeat(5-reviewData.rating)}
            </div>
            <div style="margin-bottom:8px;">
                <span style="font-weight:700;">Comment:</span> ${reviewData.comment || 'No comment'}
            </div>
        `;
        
        // Display existing photos
        if (reviewData.photo_urls && reviewData.photo_urls.length > 0) {
            contentDiv.innerHTML += `
                <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                    ${reviewData.photo_urls.map(photo => `
                        <img src="${photo}" style="width:70px;height:70px;object-fit:cover;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    `).join('')}
                </div>
            `;
        }
        
        document.getElementById(`existing${type === 'mtn' ? 'Mtn' : 'Guide'}Review`).style.display = 'block';
        document.getElementById(`${type}ReviewForm`).style.display = 'none';
    }
}

function enableEditReview(type) {
    if (type === 'mtn') {
        editingMtnReview = true;
        document.getElementById('existingMtnReview').style.display = 'none';
        document.getElementById('mtnReviewForm').style.display = 'block';
        
        // Pre-fill form with existing data
        if (existingMtnData) {
            // Pre-fill stars
            const stars = document.querySelectorAll('#mtnStars span');
            stars.forEach(star => {
                const val = parseInt(star.getAttribute('data-val'));
                if (val <= existingMtnData.rating) {
                    star.classList.add('active');
                }
            });
            document.getElementById('mtnRating').value = existingMtnData.rating;
            document.getElementById('mtnTitle').value = existingMtnData.title || '';
            document.getElementById('mtnComment').value = existingMtnData.comment || '';
            
            // Pre-fill photos
            if (existingMtnData.media) {
                const previewDiv = document.getElementById('mtnPhotoPreview');
                existingMtnData.media.forEach(photo => {
                    previewDiv.innerHTML += `
                        <div class="photo-item">
                            <img src="${photo}">
                            <button class="photo-remove" onclick="removePhotoFromEdit('mtn', '${photo}')">×</button>
                        </div>
                    `;
                });
            }
        }
        
        document.getElementById('submitReviewBtn').textContent = 'Update Reviews';
        document.getElementById('submitReviewBtn').disabled = false;
    } else {
        editingGuideReview = true;
        document.getElementById('existingGuideReview').style.display = 'none';
        document.getElementById('guideReviewForm').style.display = 'block';
        
        // Pre-fill form with existing data
        if (existingGuideData) {
            // Pre-fill stars
            const stars = document.querySelectorAll('#guideStars span');
            stars.forEach(star => {
                const val = parseInt(star.getAttribute('data-val'));
                if (val <= existingGuideData.rating) {
                    star.classList.add('active');
                }
            });
            document.getElementById('guideRating').value = existingGuideData.rating;
            document.getElementById('guideComment').value = existingGuideData.comment || '';
            
            // Pre-fill photos
            if (existingGuideData.photo_urls) {
                const previewDiv = document.getElementById('guidePhotoPreview');
                existingGuideData.photo_urls.forEach(photo => {
                    previewDiv.innerHTML += `
                        <div class="photo-item">
                            <img src="${photo}">
                            <button class="photo-remove" onclick="removePhotoFromEdit('guide', '${photo}')">×</button>
                        </div>
                    `;
                });
            }
        }
        
        document.getElementById('submitReviewBtn').textContent = 'Update Reviews';
        document.getElementById('submitReviewBtn').disabled = false;
    }
}

function removePhotoFromEdit(type, photoUrl) {
    if (type === 'mtn') {
        if (existingMtnData && existingMtnData.media) {
            existingMtnData.media = existingMtnData.media.filter(p => p !== photoUrl);
            // Remove from display
            const previewDiv = document.getElementById('mtnPhotoPreview');
            const images = previewDiv.querySelectorAll('div');
            images.forEach(img => {
                if (img.querySelector('img')?.src === photoUrl) {
                    img.remove();
                }
            });
        }
    } else if (type === 'guide') {
        if (existingGuideData && existingGuideData.photo_urls) {
            existingGuideData.photo_urls = existingGuideData.photo_urls.filter(p => p !== photoUrl);
            // Remove from display
            const previewDiv = document.getElementById('guidePhotoPreview');
            const images = previewDiv.querySelectorAll('div');
            images.forEach(img => {
                if (img.querySelector('img')?.src === photoUrl) {
                    img.remove();
                }
            });
        }
    }
}

// Photo upload handling
document.getElementById('mtnPhotos')?.addEventListener('change', function(e) {
    handlePhotoUpload(e.target.files, 'mtn');
});

document.getElementById('guidePhotos')?.addEventListener('change', function(e) {
    handlePhotoUpload(e.target.files, 'guide');
});

function handlePhotoUpload(files, type) {
    const previewDiv = document.getElementById(`${type}PhotoPreview`);
    
    Array.from(files).forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML += `
                    <div style="position:relative;">
                        <img src="${e.target.result}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                        <button onclick="removePhoto('${type}', this)" style="position:absolute;top:-8px;right:-8px;background:red;color:white;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;">×</button>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
            
            // Store file for upload
            if (type === 'mtn') {
                uploadedMtnPhotos.push(file);
            } else {
                uploadedGuidePhotos.push(file);
            }
        }
    });
}

function removePhoto(type, btn) {
    btn.closest('div').remove();
    // Remove from uploaded array (we'll re-build the array when submitting)
}

async function uploadPhotos(files) {
    if (!files || files.length === 0) return [];
    
    const formData = new FormData();
    for (let file of files) {
        formData.append('photos[]', file);
    }
    
    try {
        const response = await fetch('../api/upload_review_photos.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        return result.success ? result.files : [];
    } catch (error) {
        console.error('Photo upload error:', error);
        return [];
    }
}

// Enhanced submitReview function
async function submitReview() {
    const bookingId = document.getElementById('revBookingId').value;
    const mtnRating = parseInt(document.getElementById('mtnRating').value);
    const guideRating = parseInt(document.getElementById('guideRating').value);
    
    // Only validate if forms are visible (being submitted)
    let hasMtnReview = false;
    let hasGuideReview = false;
    
    if (document.getElementById('mtnReviewForm').style.display !== 'none' || editingMtnReview) {
        if (mtnRating === 0) {
            showToast('Please provide a rating for the mountain');
            return;
        }
        hasMtnReview = true;
    }
    
    if (document.getElementById('guideReviewForm').style.display !== 'none' || editingGuideReview) {
        if (guideRating === 0) {
            showToast('Please provide a rating for the guide');
            return;
        }
        hasGuideReview = true;
    }
    
    // Upload photos if any
    const mtnPhotoUrls = uploadedMtnPhotos.length > 0 ? await uploadPhotos(uploadedMtnPhotos) : [];
    const guidePhotoUrls = uploadedGuidePhotos.length > 0 ? await uploadPhotos(uploadedGuidePhotos) : [];
    
    // Combine with existing photos if editing
    let finalMtnPhotos = mtnPhotoUrls;
    let finalGuidePhotos = guidePhotoUrls;
    
    if (editingMtnReview && existingMtnData) {
        finalMtnPhotos = [...(existingMtnData.media || []), ...mtnPhotoUrls];
    }
    if (editingGuideReview && existingGuideData) {
        finalGuidePhotos = [...(existingGuideData.photo_urls || []), ...guidePhotoUrls];
    }
    
    const data = {
        booking_id: bookingId,
        mountain_review: hasMtnReview ? {
            rating: mtnRating,
            title: document.getElementById('mtnTitle')?.value || '',
            comment: document.getElementById('mtnComment')?.value || '',
            media: finalMtnPhotos,
            review_id: existingMtnData?.id || null
        } : null,
        guide_review: hasGuideReview ? {
            rating: guideRating,
            comment: document.getElementById('guideComment')?.value || '',
            photo_urls: finalGuidePhotos,
            review_id: existingGuideData?.id || null
        } : null,
        is_edit: editingMtnReview || editingGuideReview
    };
    
    const btn = document.getElementById('submitReviewBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=save_review&data=' + encodeURIComponent(JSON.stringify(data))
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('✓ Reviews saved successfully!');
            closeReviewModal();
            // Reload bookings to show updated review status
            location.reload();
        } else {
            showToast(result.message || 'Error saving reviews');
            btn.disabled = false;
            btn.textContent = editingMtnReview || editingGuideReview ? 'Update Reviews' : 'Post Reviews';
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error saving reviews');
        btn.disabled = false;
        btn.textContent = editingMtnReview || editingGuideReview ? 'Update Reviews' : 'Post Reviews';
    });
}

// ── SEARCH & FILTER STATE ──
let activeFilter = 'all';
let searchQuery = '';

function setFilter(filter, el) {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');
    activeFilter = filter;
    renderBookings();
}

function onSearchInput(val) {
    searchQuery = val;
    const clearBtn = document.getElementById('searchClear');
    if (val.trim()) {
        clearBtn.classList.add('show');
    } else {
        clearBtn.classList.remove('show');
    }
    renderBookings();
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    onSearchInput('');
    document.getElementById('searchInput').focus();
}

function noFilterResults() {
    return `<div class="filter-no-results">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <p>No matching bookings found</p>
        <span>Try adjusting your search or filters</span>
    </div>`;
}

// Initial load
loadBookings();
renderBookings();
initStars();
setInterval(renderBookings, 60000);
</script>
</body>
</html>