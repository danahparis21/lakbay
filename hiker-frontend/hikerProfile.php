<?php
// hiker frontend/hikerProfile.php - LAKBAY User Profile
// Fetches real data from database: user details, bookings, saved mountains

require_once __DIR__ . '/../config/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login and signup/login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUser = null;
$userInitial = '';

// Fetch current user details from users table
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header('Location: ../login and signup/login.php');
    exit;
}

// Generate user initials for avatar
$nameParts = explode(' ', trim($currentUser['name']));
$userInitial = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $userInitial .= strtoupper(substr($part, 0, 1));
    }
}
$userInitial = substr($userInitial, 0, 2);

// Fetch hiking history (day hikes + camping bookings combined)
$historyItems = [];

// Day hike bookings
$stmt = $pdo->prepare("
    SELECT b.*, m.name as mountain_name, m.location, m.image, m.start_point_lat, m.start_point_lng, 'day_hike' as booking_type 
    FROM bookings b
    JOIN mountains m ON b.mountain_id = m.id
    WHERE b.user_id = ? 
    ORDER BY b.hike_date DESC
");
$stmt->execute([$currentUserId]);
$dayHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($dayHikes as $hike) {
    $historyItems[] = [
        'id' => $hike['id'],
        'booking_number' => $hike['booking_number'],
        'mountain_name' => $hike['mountain_name'],
        'location' => $hike['location'],
        'date' => $hike['hike_date'],
        'type' => 'Day Hike',
        'status' => $hike['status'],
        'booking_type' => 'day_hike',
        'image' => $hike['image'],
        'lat' => $hike['start_point_lat'],
        'lng' => $hike['start_point_lng']
    ];
}

// Camping bookings
$stmt = $pdo->prepare("
    SELECT c.*, m.name as mountain_name, m.location, m.image, m.start_point_lat, m.start_point_lng
    FROM camping_bookings c
    JOIN mountains m ON c.mountain_id = m.id
    WHERE c.user_id = ? 
    ORDER BY c.start_date DESC
");
$stmt->execute([$currentUserId]);
$campingHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($campingHikes as $camping) {
    $nights = $camping['number_of_nights'] ?? 1;
    $historyItems[] = [
        'id' => $camping['id'],
        'booking_number' => $camping['booking_number'],
        'mountain_name' => $camping['mountain_name'],
        'location' => $camping['location'],
        'date' => $camping['start_date'],
        'type' => $nights . ' Night Camping',
        'status' => $camping['status'],
        'booking_type' => 'camping',
        'image' => $camping['image'],
        'lat' => $camping['start_point_lat'],
        'lng' => $camping['start_point_lng']
    ];
}

// Sort by date (newest first)
usort($historyItems, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// \$finishedHikes is now built directly from active_hike_sessions below (see stats block)

// Fetch saved mountains
$stmt = $pdo->prepare("
    SELECT s.*, m.name, m.location, m.elevation, m.duration, m.rating, m.image, m.difficulty
    FROM saved_mountains s
    JOIN mountains m ON s.mountain_id = m.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$currentUserId]);
$savedMountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all mountains for "add saved" modal
$stmtAllMountains = $pdo->query("SELECT * FROM mountains WHERE status = 'Open' ORDER BY name");
$allMountains = $stmtAllMountains->fetchAll(PDO::FETCH_ASSOC);

// ── Finished hike count: read from active_hike_sessions (status='finished')
// The bookings table status ('active','confirmed') does NOT reflect hike completion.
// active_hike_sessions.status = 'finished' is the authoritative finished state.
$stmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM active_hike_sessions ahs
    JOIN bookings b ON ahs.booking_id = b.id
    WHERE ahs.user_id = ? AND ahs.status = 'finished'
");
$stmt->execute([$currentUserId]);
$totalHikes    = (int)$stmt->fetchColumn();
$completedHikes = $totalHikes;

// Also rebuild $finishedHikes from active_hike_sessions for the adventures list
$stmt = $pdo->prepare("
    SELECT ahs.id, ahs.booking_id, ahs.end_time as date,
           m.name as mountain_name, m.location, m.image,
           m.start_point_lat as lat, m.start_point_lng as lng,
           b.booking_number, 'Day Hike' as type,
           'finished' as status, 'day_hike' as booking_type
    FROM active_hike_sessions ahs
    JOIN bookings b ON ahs.booking_id = b.id
    JOIN mountains m ON b.mountain_id = m.id
    WHERE ahs.user_id = ? AND ahs.status = 'finished'
    ORDER BY ahs.end_time DESC
");
$stmt->execute([$currentUserId]);
$finishedHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT ub.times_earned, b.name 
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
");
$stmt->execute([$currentUserId]);
$allUserBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total badge count (including duplicates - e.g., if earned 3x, count as 3)
$badgeCount = 0;
foreach ($allUserBadges as $badge) {
    $badgeCount += $badge['times_earned'];
}

// Also keep track of unique badges for display
$uniqueBadgeCount = count($allUserBadges);

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    // Use absolute path based on the current file's directory
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);  // Note: 0755 instead of 0777
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        // Try to set permissions
        chmod($uploadDir, 0755);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $fileName = 'user_' . $currentUserId . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($_FILES['avatar']['type'], $allowedTypes) && in_array($fileExt, $allowedExts) && $_FILES['avatar']['error'] === 0) {
        // Check file size (max 2MB)
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $errorMessage = "File too large. Maximum 2MB.";
        } else {
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                $avatarPath = '/uploads/avatars/' . $fileName;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatarPath, $currentUserId]);
                $currentUser['avatar'] = $avatarPath;
                $_SESSION['user_avatar'] = $avatarPath;
                
                // Refresh the page to show new avatar
                echo "<script>window.location.reload();</script>";
                exit;
            } else {
                $errorMessage = "Failed to save file. Please check directory permissions.";
                error_log("Upload failed - couldn't move file to: " . $uploadPath);
            }
        }
    } else {
        $errorMessage = "Invalid file type. Use JPG, PNG, GIF, or WEBP.";
    }
    
    // If we get here, there was an error
    echo "<script>alert('" . addslashes($errorMessage) . "'); window.location.reload();</script>";
    exit;
}

// Handle profile update (name, email, phone, home_region)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $home_region = trim($_POST['home_region'] ?? '');
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    
    // Check if email is taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $currentUserId]);
    if ($stmt->fetch()) {
        $errors[] = 'Email already in use by another account.';
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, home_region = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone ?: null, $home_region ?: null, $currentUserId]);
        $currentUser['name'] = $name;
        $currentUser['email'] = $email;
        $currentUser['phone'] = $phone;
        $currentUser['home_region'] = $home_region;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $successMessage = "Profile updated successfully!";
    }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $passwordErrors = [];
    
    // Verify current password
    if (!password_verify($current_password, $currentUser['password'])) {
        $passwordErrors[] = 'Current password is incorrect.';
    }
    
    // Validate new password
    if (empty($new_password)) {
        $passwordErrors[] = 'New password is required.';
    } elseif (strlen($new_password) < 8) {
        $passwordErrors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $passwordErrors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $passwordErrors[] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
        $passwordErrors[] = 'Password must contain at least one special character.';
    } elseif ($new_password !== $confirm_password) {
        $passwordErrors[] = 'New passwords do not match.';
    }
    
    if (empty($passwordErrors)) {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $currentUserId]);
        $passwordSuccess = "Password changed successfully!";
    }
}

// Handle add saved mountain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_saved') {
    $mountain_id = (int)($_POST['mountain_id'] ?? 0);
    
    // Check if already saved
    $stmt = $pdo->prepare("SELECT id FROM saved_mountains WHERE user_id = ? AND mountain_id = ?");
    $stmt->execute([$currentUserId, $mountain_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO saved_mountains (user_id, mountain_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$currentUserId, $mountain_id]);
        // Refresh saved mountains list
        $stmt = $pdo->prepare("
            SELECT s.*, m.name, m.location, m.elevation, m.duration, m.rating, m.image, m.difficulty
            FROM saved_mountains s
            JOIN mountains m ON s.mountain_id = m.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$currentUserId]);
        $savedMountains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle remove saved mountain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_saved') {
    $saved_id = (int)($_POST['saved_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM saved_mountains WHERE id = ? AND user_id = ?");
    $stmt->execute([$saved_id, $currentUserId]);
    
    // Refresh saved mountains list
    $stmt = $pdo->prepare("
        SELECT s.*, m.name, m.location, m.elevation, m.duration, m.rating, m.image, m.difficulty
        FROM saved_mountains s
        JOIN mountains m ON s.mountain_id = m.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$currentUserId]);
    $savedMountains = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle remove hike from history (cancel booking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $booking_type = $_POST['booking_type'] ?? '';
    
    if ($booking_type === 'day_hike') {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->execute([$booking_id, $currentUserId]);
    } elseif ($booking_type === 'camping') {
        $stmt = $pdo->prepare("UPDATE camping_bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->execute([$booking_id, $currentUserId]);
    }
    
    // Refresh history
    $historyItems = [];
    
    // After refreshing history, recalculate badge count
$stmt = $pdo->prepare("
    SELECT ub.times_earned 
    FROM user_badges ub
    WHERE ub.user_id = ?
");
$stmt->execute([$currentUserId]);
$allUserBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
$badgeCount = 0;
foreach ($allUserBadges as $badge) {
    $badgeCount += $badge['times_earned'];
}

    // Refetch day hikes
    $stmt = $pdo->prepare("
        SELECT b.*, m.name as mountain_name, m.location, m.image, m.start_point_lat, m.start_point_lng, 'day_hike' as booking_type 
        FROM bookings b
        JOIN mountains m ON b.mountain_id = m.id
        WHERE b.user_id = ? 
        ORDER BY b.hike_date DESC
    ");
    $stmt->execute([$currentUserId]);
    $dayHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dayHikes as $hike) {
        $historyItems[] = [
            'id' => $hike['id'],
            'booking_number' => $hike['booking_number'],
            'mountain_name' => $hike['mountain_name'],
            'location' => $hike['location'],
            'date' => $hike['hike_date'],
            'type' => 'Day Hike',
            'status' => $hike['status'],
            'booking_type' => 'day_hike',
            'image' => $hike['image'],
            'lat' => $hike['start_point_lat'],
            'lng' => $hike['start_point_lng']
        ];
    }
    
    // Refetch camping bookings
    $stmt = $pdo->prepare("
        SELECT c.*, m.name as mountain_name, m.location, m.image, m.start_point_lat, m.start_point_lng
        FROM camping_bookings c
        JOIN mountains m ON c.mountain_id = m.id
        WHERE c.user_id = ? 
        ORDER BY c.start_date DESC
    ");
    $stmt->execute([$currentUserId]);
    $campingHikes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($campingHikes as $camping) {
        $nights = $camping['number_of_nights'] ?? 1;
        $historyItems[] = [
            'id' => $camping['id'],
            'booking_number' => $camping['booking_number'],
            'mountain_name' => $camping['mountain_name'],
            'location' => $camping['location'],
            'date' => $camping['start_date'],
            'type' => $nights . ' Night Camping',
            'status' => $camping['status'],
            'booking_type' => 'camping',
            'image' => $camping['image'],
            'lat' => $camping['start_point_lat'],
            'lng' => $camping['start_point_lng']
        ];
    }
    
    usort($historyItems, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

$userBadges = [];
$stmt = $pdo->prepare("
    SELECT b.*, ub.earned_at, ub.times_earned, ub.mountain_name, ub.hike_date
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
");
$stmt->execute([$currentUserId]);
$userBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Group badges by name to show duplicates - FIXED to use times_earned
$badgeGroups = [];
foreach ($userBadges as $badge) {
    $key = $badge['name'];
    if (!isset($badgeGroups[$key])) {
        $badgeGroups[$key] = [
            'badge' => $badge,
            'count' => 0,
            'dates' => []
        ];
    }
    // FIX: Use times_earned from database instead of just incrementing by 1
    $badgeGroups[$key]['count'] += $badge['times_earned'];
    $badgeGroups[$key]['dates'][] = date('M j, Y', strtotime($badge['earned_at']));
}

// Calculate hiking insights
$totalDistance = 0;
$yearlyStats = [];
$mountainFrequency = [];

foreach ($historyItems as $item) {
    $year = date('Y', strtotime($item['date']));
    if (!isset($yearlyStats[$year])) {
        $yearlyStats[$year] = ['count' => 0, 'distance' => 0];
    }
    $yearlyStats[$year]['count']++;
    
    $mountainName = $item['mountain_name'];
    if (!isset($mountainFrequency[$mountainName])) {
        $mountainFrequency[$mountainName] = 0;
    }
    $mountainFrequency[$mountainName]++;
}

$currentYear = date('Y');
$lastYear = $currentYear - 1;
$currentYearCount = $yearlyStats[$currentYear]['count'] ?? 0;
$lastYearCount = $yearlyStats[$lastYear]['count'] ?? 0;
$growthPercent = $lastYearCount > 0 ? round(($currentYearCount - $lastYearCount) / $lastYearCount * 100) : 100;
$mostHikedMountain = !empty($mountainFrequency) ? array_keys($mountainFrequency, max($mountainFrequency))[0] : 'None yet';

// Get the image for the most hiked mountain
$mostHikedMountainImage = '';
if ($mostHikedMountain !== 'None yet') {
    foreach ($historyItems as $item) {
        if ($item['mountain_name'] === $mostHikedMountain) {
            $mostHikedMountainImage = $item['image'];
            break;
        }
    }
}
if (empty($mostHikedMountainImage)) {
    $mostHikedMountainImage = 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&q=80';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY — My Profile</title>
  <link rel="stylesheet" href="shared.css">
  <!-- Leaflet CSS for map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
   <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
  <!-- html2canvas for JPG export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    :root {
      --forest: #100600;
      --moss: #2a1a0a;
      --sage: #8a7a6a;
      --mist: #e8e0d8;
      --cream: #faf7f2;
      --stone: #6a5a4a;
      --bark: #3d2b1a;
      --sky: #f0ece8;
      --white: #ffffff;
      --danger: #c0392b;
      --danger-light: rgba(192,57,43,0.1);
      --success: #27ae60;
      --gold: #c9a84c;
      --glass: rgba(16,6,0,0.08);
      --glass-border: rgba(16,6,0,0.1);
      --radius: 20px;
      --radius-sm: 12px;
      --shadow: 0 8px 32px rgba(16,6,0,0.12);
      --shadow-lg: 0 20px 60px rgba(16,6,0,0.2);
      --nav-h: 74px;
    }
    @media(max-width:768px){ :root { --nav-h: 0px; } }

    .mobile-back-bar {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      background: rgba(250,247,242,0.95);
      backdrop-filter: blur(16px);
      padding: 12px 16px;
      z-index: 100;
      border-bottom: 1px solid rgba(16,6,0,0.08);
      align-items: center;
      gap: 12px;
    }
    .back-btn {
      background: transparent;
      border: none;
      font-size: 24px;
      cursor: pointer;
      padding: 8px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--forest);
      transition: 0.2s;
    }
    .back-btn:hover { background: rgba(16,6,0,0.08); }
    .mobile-back-bar h3 {
      font-family: 'Playfair Display', serif;
      font-size: 18px;
      font-weight: 600;
      color: var(--forest);
      margin: 0;
    }

    .profile-layout {
      display: flex;
      min-height: calc(100vh - var(--nav-h) - 40px);
      margin-top: var(--nav-h);
      gap: 28px;
      max-width: 1300px;
      margin-left: auto;
      margin-right: auto;
      padding: 0 24px;
    }

    .profile-sidebar {
      width: 280px;
      flex-shrink: 0;
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 24px 0;
      height: fit-content;
      position: sticky;
      top: calc(var(--nav-h) + 20px);
      border: 1px solid rgba(16,6,0,0.08);
    }
    .sidebar-avatar {
      text-align: center;
      padding: 0 20px 20px;
      border-bottom: 1px solid rgba(16,6,0,0.08);
      margin-bottom: 16px;
      position: relative;
    }
    .avatar-wrapper {
      position: relative;
      width: 100px;
      margin: 0 auto;
    }
    .avatar-circle {
      width: 100px;
      height: 100px;
      background: var(--forest);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 12px;
      color: var(--gold);
      font-size: 40px;
      font-weight: 700;
      font-family: 'Playfair Display', serif;
      overflow: hidden;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }
    .avatar-circle img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .camera-icon {
      position: absolute;
      bottom: 8px;
      right: 0;
      background: var(--gold);
      border-radius: 50%;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border: 2px solid var(--white);
      transition: 0.2s;
    }
    .camera-icon:hover { transform: scale(1.05); }
    .camera-icon svg {
      width: 16px;
      height: 16px;
      stroke: var(--forest);
      stroke-width: 2;
    }
    .sidebar-name {
      font-weight: 700;
      font-size: 18px;
      color: var(--forest);
    }
    .sidebar-email {
      font-size: 11px;
      color: var(--stone);
      margin-top: 4px;
    }
    .sidebar-nav {
      display: flex;
      flex-direction: column;
    }
    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 24px;
      color: var(--stone);
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s;
      border-left: 3px solid transparent;
      cursor: pointer;
    }
    .sidebar-link svg {
      width: 18px;
      height: 18px;
      stroke: currentColor;
      stroke-width: 1.8;
      fill: none;
    }
    .sidebar-link:hover {
      background: rgba(16,6,0,0.04);
      color: var(--forest);
    }
    .sidebar-link.active {
      background: rgba(16,6,0,0.06);
      color: var(--forest);
      border-left-color: var(--gold);
      font-weight: 600;
    }
    .sidebar-logout {
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid rgba(16,6,0,0.08);
    }
    .sidebar-logout .sidebar-link {
      color: var(--danger);
    }
    .sidebar-logout .sidebar-link:hover {
      background: rgba(192,57,43,0.08);
      color: var(--danger);
    }

    .profile-main {
      flex: 1;
      min-width: 0;
      padding-top: 20px;
    }
   .profile-section {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 28px;
    margin-bottom: 24px;
    border: 1px solid rgba(16,6,0,0.08);
    display: block !important;
}

.profile-section:not(.active-section) {
    display: none !important;
}
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 12px;
      border-bottom: 2px solid rgba(16,6,0,0.06);
      flex-wrap: wrap;
      gap: 12px;
    }
    .section-header h2 {
      font-family: 'Playfair Display', serif;
      font-size: 22px;
      font-weight: 600;
      color: var(--forest);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-header h2 svg {
      width: 24px;
      height: 24px;
      stroke: var(--gold);
      stroke-width: 1.8;
    }
    .section-header-actions {
      display: flex;
      gap: 10px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 32px;
      align-items: stretch;
    }
    .stat-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 24px 20px;
      text-align: center;
      transition: all 0.3s ease;
      border: 1px solid rgba(16,6,0,0.08);
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(16,6,0,0.15);
      border-color: var(--gold);
    }
    .stat-number {
      font-family: 'DM Mono', monospace;
      font-size: 32px;
      font-weight: 700;
      color: var(--forest);
    }
    .stat-label {
      font-size: 12px;
      color: var(--stone);
      margin-top: 6px;
      letter-spacing: 0.5px;
    }

    .info-row {
      display: flex;
      padding: 14px 0;
      border-bottom: 1px solid rgba(16,6,0,0.06);
    }
    .info-label {
      width: 110px;
      font-weight: 600;
      color: var(--stone);
      font-size: 13px;
    }
    .info-value {
      flex: 1;
      color: var(--forest);
      font-size: 14px;
    }
    .info-value.important {
      color: var(--danger);
      font-weight: 600;
    }
    .info-value small {
      font-size: 10px;
      color: var(--stone);
      display: block;
    }

    .saved-mtn-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 20px;
    }
    .saved-mtn-card {
      background: var(--white);
      border-radius: var(--radius-sm);
      border: 1px solid rgba(16,6,0,0.1);
      overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
      position: relative;
      box-shadow: var(--shadow);
    }
    .saved-mtn-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
    }
    .saved-mtn-img {
      height: 140px;
      background-size: cover;
      background-position: center;
      position: relative;
    }
    .saved-mtn-img-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(transparent 50%, rgba(16,6,0,0.6));
    }
    .saved-mtn-badge {
      position: absolute;
      top: 10px;
      left: 10px;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 50px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
    }
    .badge-easy { background: #27ae60; color: white; }
    .badge-moderate { background: #f39c12; color: white; }
    .badge-hard { background: #e74c3c; color: white; }
    .saved-mtn-body {
      padding: 14px;
    }
    .saved-mtn-name {
      font-family: 'Playfair Display', serif;
      font-size: 16px;
      font-weight: 600;
      color: var(--forest);
      margin-bottom: 4px;
    }
    .saved-mtn-loc {
      font-size: 11px;
      color: var(--stone);
      display: flex;
      align-items: center;
      gap: 4px;
      margin-bottom: 8px;
    }
    .saved-mtn-stats {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .saved-mtn-stat {
      font-size: 11px;
      color: var(--sage);
    }
    .saved-mtn-remove {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(0,0,0,0.5);
      border-radius: 50%;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      backdrop-filter: blur(4px);
      transition: 0.2s;
      z-index: 5;
    }
    .saved-mtn-remove:hover { background: var(--danger); }
    .saved-mtn-remove svg { width: 14px; height: 14px; stroke: white; }

    .history-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: none;
        overflow-y: visible;
        padding-right: 0;
    }

    .history-scroll-wrapper {
        max-height: 500px;
        overflow-y: auto;
        padding-right: 8px;
        scrollbar-width: thin;
        scrollbar-color: var(--gold) transparent;
    }

    .history-scroll-wrapper::-webkit-scrollbar {
        width: 4px;
    }

    .history-scroll-wrapper::-webkit-scrollbar-thumb {
        background-color: var(--gold);
        border-radius: 10px;
    }

    .history-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: rgba(16,6,0,0.02);
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
        flex-wrap: wrap;
        gap: 12px;
        cursor: pointer;
        border: 1px solid transparent;
    }
    .history-item:hover {
        background: rgba(16,6,0,0.06);
        border-color: rgba(201,168,76,0.3);
        transform: translateX(4px);
    }
    .history-info {
        flex: 1;
    }
    .history-info h4 {
        font-size: 15px;
        font-weight: 600;
        color: var(--forest);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .history-info p {
        font-size: 12px;
        color: var(--stone);
        margin-bottom: 4px;
    }
    .history-info small {
        font-size: 10px;
        color: var(--sage);
    }
    .history-status {
        font-size: 10px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 50px;
        white-space: nowrap;
    }
    .status-active { background: #27ae60; color: white; }
    .status-completed { background: #3498db; color: white; }
    .status-cancelled { background: #95a5a6; color: white; }
    .status-pending { background: #f39c12; color: white; }
    .status-joined { background: #8e44ad; color: white; }

    .setting-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 0;
      border-bottom: 1px solid rgba(16,6,0,0.06);
    }
    .setting-row:last-child { border-bottom: none; }
    .setting-info h4 {
      font-size: 15px;
      font-weight: 600;
      color: var(--forest);
      margin-bottom: 4px;
    }
    .setting-info p {
      font-size: 12px;
      color: var(--stone);
    }

    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 48px;
      height: 26px;
    }
    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: 0.25s;
      border-radius: 34px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 20px;
      width: 20px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: 0.25s;
      border-radius: 50%;
    }
    input:checked + .slider { background-color: var(--gold); }
    input:checked + .slider:before { transform: translateX(22px); }

    .btn-outline-small {
      background: transparent;
      border: 1.5px solid var(--forest);
      border-radius: 50px;
      padding: 6px 14px;
      font-size: 12px;
      font-weight: 600;
      color: var(--forest);
      cursor: pointer;
      transition: 0.2s;
    }
    .btn-outline-small:hover {
      background: var(--forest);
      color: var(--cream);
    }
    .btn-outline-danger {
      background: transparent;
      border: 1.5px solid var(--danger);
      border-radius: 50px;
      padding: 6px 14px;
      font-size: 12px;
      font-weight: 600;
      color: var(--danger);
      cursor: pointer;
      transition: 0.2s;
    }
    .btn-outline-danger:hover {
      background: var(--danger);
      color: white;
    }
    .empty-state {
      text-align: center;
      padding: 40px;
      color: var(--stone);
      font-size: 13px;
    }

    .modal-bg {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(16,6,0,0.6);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      visibility: hidden;
      opacity: 0;
      transition: 0.2s;
    }
    .modal-bg.open {
      visibility: visible;
      opacity: 1;
    }
    .modal {
      background: var(--white);
      border-radius: var(--radius);
      width: 90%;
      max-width: 480px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: var(--shadow-lg);
    }
    .modal-hdr {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid rgba(16,6,0,0.08);
    }
    .modal-title {
      font-family: 'Playfair Display', serif;
      font-size: 18px;
      font-weight: 600;
      color: var(--forest);
    }
    .modal-close {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: var(--stone);
      transition: 0.2s;
    }
    .modal-close:hover { color: var(--danger); }
    .modal-body {
      padding: 24px;
    }
    .inp {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid rgba(16,6,0,0.15);
      border-radius: 12px;
      font-family: inherit;
      font-size: 14px;
      transition: 0.2s;
    }
    .inp:focus {
      outline: none;
      border-color: var(--gold);
    }
    .inp-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--forest);
      margin-bottom: 6px;
    }
    .btn {
      padding: 12px 20px;
      border: none;
      border-radius: 50px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
      font-family: inherit;
    }
    .btn-primary {
      background: var(--forest);
      color: var(--cream);
    }
    .btn-primary:hover {
      background: var(--bark);
      transform: translateY(-1px);
    }
    .btn-outline {
      background: transparent;
      border: 1.5px solid var(--forest);
      color: var(--forest);
    }
    .btn-outline:hover {
      background: var(--forest);
      color: var(--cream);
    }
    .btn-full {
      width: 100%;
    }
    .confirm-buttons {
      display: flex;
      gap: 12px;
      margin-top: 24px;
    }
    .confirm-buttons .btn { flex: 1; }
    
    .password-requirements {
      font-size: 11px;
      color: var(--stone);
      margin-top: 8px;
      padding-left: 12px;
    }
    .password-requirements li {
      margin-bottom: 4px;
    }

    .mtn-option-list {
      max-height: 300px;
      overflow-y: auto;
    }
    .mtn-option {
      padding: 14px;
      border-bottom: 1px solid rgba(16,6,0,0.08);
      cursor: pointer;
      transition: 0.15s;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .mtn-option:hover { background: rgba(16,6,0,0.04); }
    .mtn-option-name { font-weight: 600; color: var(--forest); }
    .mtn-option-loc { font-size: 11px; color: var(--stone); }

    .toast {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: var(--forest);
      color: var(--cream);
      padding: 12px 24px;
      border-radius: 50px;
      font-size: 13px;
      font-weight: 600;
      z-index: 1100;
      transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      opacity: 0;
      white-space: nowrap;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
      backdrop-filter: blur(10px);
      background: rgba(16,6,0,0.9);
      border: 1px solid rgba(201,168,76,0.3);
    }
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    .toast.success { background: rgba(46,125,50,0.9); border-color: #2ed573; }
    .toast.error { background: rgba(192,57,43,0.9); border-color: #ff4757; }

    @media (max-width: 768px) {
      .mobile-back-bar { display: flex; }
      .desktop-nav { display: none; }
      .profile-layout { 
        flex-direction: column; 
        padding-top: 80px; 
        padding-bottom: 120px; 
        gap: 20px;
      }
      .profile-sidebar { width: 100%; }
      .sidebar-nav { 
        flex-direction: row; 
        flex-wrap: wrap; 
        gap: 4px; 
        padding: 0 16px;
        justify-content: center;
      }
      .sidebar-link { 
        padding: 8px 14px; 
        border-left: none; 
        border-bottom: 2px solid transparent;
        font-size: 12px;
      }
      .sidebar-link.active { 
        border-left-color: transparent; 
        border-bottom-color: var(--gold); 
      }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
      .stats-grid > div:last-child { grid-column: span 2; }
      .stat-card { padding: 12px; }
      .stat-number { font-size: 24px; }
      .profile-section { padding: 20px; }
      .section-header { flex-direction: column; align-items: flex-start; }
      .section-header h2 { font-size: 18px; }
      .info-row { flex-direction: column; gap: 4px; }
      .info-label { width: 100%; font-size: 11px; }
      .info-value { font-size: 14px; }
      .saved-mtn-grid { grid-template-columns: 1fr; }
      .toast { white-space: normal; text-align: center; max-width: 90%; }
    }

    .badges-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
    }
    .badge-card {
        background: linear-gradient(135deg, var(--forest), var(--moss));
        border-radius: var(--radius-sm);
        padding: 20px 16px;
        text-align: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(201,168,76,0.3);
    }
    .badge-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.2);
        border-color: var(--gold);
    }
    .badge-icon {
        font-size: 48px;
        margin-bottom: 12px;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
    }
    .badge-name {
        font-weight: 700;
        font-size: 14px;
        color: var(--gold);
        margin-bottom: 6px;
    }
    .badge-description {
        font-size: 11px;
        color: rgba(255,255,255,0.7);
        margin-bottom: 8px;
    }
    .badge-earned-count {
        font-size: 10px;
        color: var(--gold);
        background: rgba(201,168,76,0.2);
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        margin-top: 6px;
    }
    .badge-earned-date {
        font-size: 9px;
        color: rgba(255,255,255,0.5);
        margin-top: 8px;
    }
    .badge-card.duplicate {
        background: linear-gradient(135deg, #2a2a1a, #1a1a0a);
    }

    /* Bento Grid Dashboard Styles */
    .insights-dashboard {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: repeat(2, 160px);
        gap: 16px;
        margin-bottom: 24px;
    }

    .insight-card {
        background: var(--white);
        border-radius: var(--radius);
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(16,6,0,0.08);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .insight-card:hover {
        transform: scale(1.01);
        box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    }

    .insight-card.large { grid-column: span 2; grid-row: span 2; }
    .insight-card.medium { grid-column: span 2; }
    .insight-card.small { grid-column: span 1; }

    .insight-bg {
        position: absolute;
        inset: 0;
        background-size: cover;
        background-position: center;
        transition: transform 0.5s;
    }
    .insight-card:hover .insight-bg { transform: scale(1.1); }

    .insight-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, rgba(16,6,0,0.1), rgba(16,6,0,0.8));
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 20px;
        color: white;
    }

    .insight-stats { display: flex; flex-direction: column; gap: 2px; }
    .insight-number {
        font-size: 32px;
        font-weight: 800;
        font-family: 'Playfair Display', serif;
        color: var(--gold);
        line-height: 1;
    }
    .insight-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
    }

    #journeyMap {
        height: 100%;
        width: 100%;
        z-index: 1;
        background: var(--sky);
    }
    .map-badge {
        position: absolute;
        top: 16px;
        left: 16px;
        background: var(--forest);
        color: var(--gold);
        padding: 6px 12px;
        border-radius: 40px;
        font-size: 10px;
        font-weight: 700;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .trend-up .insight-number { color: #2ed573; }
    .trend-down .insight-number { color: #ff4757; }

    .story-summary {
        background: linear-gradient(135deg, rgba(201,168,76,0.1), rgba(26,46,26,0.05));
        border-radius: var(--radius-sm);
        padding: 20px;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        margin-bottom: 24px;
        border-left: 3px solid var(--gold);
    }
    .story-icon { font-size: 32px; }
    .story-text { flex: 1; font-size: 14px; line-height: 1.6; color: var(--forest); }

    @media (max-width: 992px) {
        .insights-dashboard { grid-template-columns: repeat(2, 1fr); grid-template-rows: auto; }
        .insight-card.large, .insight-card.medium, .insight-card.small { grid-column: span 2; height: 200px; }
        .insight-card.map-card { height: 300px; order: -1; }
    }

    /* Star Rating */
    .star-rating {
        display: flex;
        gap: 8px;
        margin: 8px 0;
    }
    .star-rating span {
        font-size: 32px;
        color: var(--mist);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .star-rating span:hover {
        transform: scale(1.2);
        color: var(--gold);
    }
    .star-rating span.active {
        color: var(--gold);
    }
    .star-rating.readonly {
        gap: 2px;
    }
    .star-rating.readonly span {
        font-size: 18px;
        cursor: default;
    }
    .star-rating.readonly span:hover {
        transform: none;
    }

    /* System Review Section Enhancements */
    #existingSystemReview {
        background: rgba(201, 168, 76, 0.05) !important;
        border: 1px solid rgba(201, 168, 76, 0.15);
        border-left: 4px solid var(--gold);
        box-shadow: var(--shadow);
        padding: 24px !important;
    }
    .review-data-row {
        margin-bottom: 16px;
    }
    .review-data-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--stone);
        font-weight: 700;
        margin-bottom: 6px;
        display: block;
    }
    .review-data-value {
        font-size: 14px;
        color: var(--forest);
        line-height: 1.6;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-badge.pending { background: #fff8e1; color: #f57f17; }
    .status-badge.approved { background: #e8f5e9; color: #2e7d32; }
    .status-badge.rejected { background: #ffebee; color: #c62828; }

    .info-note {
        background: rgba(16,6,0,0.03);
        padding: 16px;
        border-radius: var(--radius-sm);
        display: flex;
        gap: 12px;
        align-items: flex-start;
        color: var(--stone);
        font-size: 12px;
        line-height: 1.6;
        border: 1px solid rgba(16,6,0,0.05);
    }
    .info-note svg {
        width: 18px;
        height: 18px;
        stroke: var(--sage);
        stroke-width: 2;
        fill: none;
        flex-shrink: 0;
        margin-top: 1px;
    }


    /* ── RECENT ADVENTURES: finished hikes only ── */
    .adventures-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 4px;
    }
    .adventure-card {
        display: flex;
        align-items: center;
        gap: 16px;
        background: var(--white);
        border: 1px solid rgba(16,6,0,0.07);
        border-radius: 18px;
        padding: 18px 20px;
        box-shadow: 0 2px 12px rgba(16,6,0,0.04);
        transition: box-shadow 0.2s, transform 0.2s;
        cursor: default;
    }
    .adventure-card:hover {
        box-shadow: 0 6px 24px rgba(16,6,0,0.10);
        transform: translateY(-2px);
    }
    .adventure-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: var(--forest);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 22px;
    }
    .adventure-body {
        flex: 1;
        min-width: 0;
    }
    .adventure-name {
        font-size: 15px;
        font-weight: 700;
        color: var(--forest);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 3px;
    }
    .adventure-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .adventure-date {
        font-size: 12px;
        color: var(--stone);
        font-weight: 500;
    }
    .adventure-sep {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--mist);
        flex-shrink: 0;
    }
    .adventure-type {
        font-size: 11px;
        font-weight: 600;
        color: var(--sage);
        background: rgba(16,6,0,0.05);
        border-radius: 100px;
        padding: 2px 9px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .adventure-badge-count {
        font-size: 11px;
        color: var(--gold);
        font-weight: 600;
        background: rgba(201,168,76,0.1);
        border: 1px solid rgba(201,168,76,0.2);
        border-radius: 100px;
        padding: 2px 9px;
    }
    .adventure-cta {
        flex-shrink: 0;
    }
    .btn-view-summary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--forest);
        color: var(--white);
        font-size: 12px;
        font-weight: 600;
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
        padding: 9px 16px;
        border-radius: 100px;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 4px 14px rgba(16,6,0,0.18);
        transition: opacity 0.15s, transform 0.15s;
    }
    .btn-view-summary:hover {
        opacity: 0.85;
        transform: translateY(-1px);
    }
    .btn-view-summary svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2.2;
    }
    .adventures-empty {
        text-align: center;
        padding: 48px 24px;
        color: var(--sage);
    }
    .adventures-empty-icon {
        font-size: 44px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    .adventures-empty p {
        font-size: 14px;
        line-height: 1.6;
        color: var(--stone);
    }
    .adventures-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 0;
        border-bottom: none;
    }
    .adventures-section-header h3 {
        font-size: 15px;
        font-weight: 700;
        color: var(--forest);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .adventures-count-pill {
        background: rgba(16,6,0,0.06);
        color: var(--sage);
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 100px;
    }
  </style>
</head>
<body>

<div class="mobile-back-bar">
  <button class="back-btn" onclick="goBack()">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
  </button>
  <h3>My Profile</h3>
</div>

<?php
// Set current page for navbar highlighting
$currentPage = 'hikerProfile'; // Change per page: 'explore', 'bookings', 'quiz', 'messages', 'hikerProfile'
?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="profile-layout">
  <aside class="profile-sidebar">
    <div class="sidebar-avatar">
      <div class="avatar-wrapper">
        <div class="avatar-circle" id="avatarDisplay" style="<?php echo $currentUser['avatar'] ? 'background-image: url(' . htmlspecialchars($currentUser['avatar']) . '); background-size: cover; background-position: center;' : ''; ?>">
          <?php if (!$currentUser['avatar']): ?>
          <span id="avatarInitial"><?php echo htmlspecialchars($userInitial); ?></span>
          <?php endif; ?>
        </div>
        <form method="POST" enctype="multipart/form-data" id="avatarForm">
          <div class="camera-icon" onclick="document.getElementById('profilePicInput').click()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </div>
          <input type="file" id="profilePicInput" name="avatar" accept="image/*" style="display:none" onchange="this.form.submit()">
        </form>
      </div>
      <div class="sidebar-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
      <div class="sidebar-email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
    </div>
    <nav class="sidebar-nav">
       <div class="sidebar-link active" data-section="history">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>
        Hiking History
      </div>

      <div class="sidebar-link" data-section="personal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Personal Info
      </div>
     
      <div class="sidebar-link" data-section="saved">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Saved Mountains
      </div>
      <div class="sidebar-link" data-section="badges">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
        My Badges
    </div>
      <div class="sidebar-link" data-section="settings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Account Settings
      </div>
      <div class="sidebar-link" data-section="system_review">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  Rate Lakbay
</div>

      <div class="sidebar-logout">
        <div class="sidebar-link" id="logoutBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </div>
      </div>
    </nav>
  </aside>

  <main class="profile-main">
<!-- Stats Grid - Removed duplicate badges card -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $totalHikes; ?></div>
        <div class="stat-label">Total Hikes</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo count($savedMountains); ?></div>
        <div class="stat-label">Saved Peaks</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $badgeCount; ?></div>
        <div class="stat-label">Badges Earned</div>
    </div>
</div>
<div id="section-history" class="profile-section active-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>Hiking Journey</h2>
      </div>

      
      <div id="exportRegion" style="background: var(--cream); padding: 20px; border-radius: var(--radius);">
        <div class="insights-dashboard">
          <div class="insight-card large map-card">
            <div id="journeyMap"></div>
            <div class="map-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              MY PEAK CONQUESTS
            </div>
          </div>

          <div class="insight-card medium">
            <div class="insight-bg" style="background-image: url('https://images.unsplash.com/photo-1551632811-561732d1e306?w=800&q=80')"></div>
            <div class="insight-overlay">
              <div class="insight-stats">
                <div class="insight-number"><?php echo $totalHikes; ?></div>
                <div class="insight-label">Total Adventures</div>
              </div>
            </div>
          </div>

          <div class="insight-card small">
            <div class="insight-bg" style="background-image: url('<?php echo htmlspecialchars($mostHikedMountainImage); ?>')"></div>
            <div class="insight-overlay">
              <div class="insight-stats">
                <div class="insight-number" style="font-size: 14px; color: white;"><?php echo htmlspecialchars($mostHikedMountain); ?></div>
                <div class="insight-label">Home Peak</div>
              </div>
            </div>
          </div>

          <div class="insight-card small trend-<?php echo $growthPercent >= 0 ? 'up' : 'down'; ?>">
            <div class="insight-overlay" style="background: var(--forest); justify-content: center; align-items: center; text-align: center;">
              <div class="insight-stats">
                <div class="insight-number"><?php echo $growthPercent >= 0 ? '+' : ''; ?><?php echo $growthPercent; ?>%</div>
                <div class="insight-label">Yearly Growth</div>
              </div>
            </div>
          </div>
        </div>

        <div class="story-summary" style="margin-bottom: 0;">
          <div class="story-icon">🏔️</div>
          <div class="story-text">
            <strong style="color: var(--gold); display: block; margin-bottom: 4px;">THE LAKBAY CHRONICLES</strong>
            <?php if ($totalHikes == 0): ?>
                Your adventure hasn't started yet! Book your first hike to begin your journey.
            <?php elseif ($totalHikes == 1): ?>
                Welcome to the hiking family! Your journey has just begun. Every mountain tells a story, and yours is just starting.
            <?php elseif ($totalHikes >= 10): ?>
                Legendary hiker! You've conquered <?php echo $totalHikes; ?> mountains. <?php echo $completedHikes; ?> peaks completed shows true dedication. <?php echo $mostHikedMountain != 'None yet' ? $mostHikedMountain . ' feels like home now!' : ''; ?>
            <?php elseif ($totalHikes >= 5): ?>
                Impressive! You've reached <?php echo $totalHikes; ?> summits. <?php echo $growthPercent > 0 ? "That's $growthPercent% more than last year!" : "Keep the momentum going!"; ?>
            <?php else: ?>
                Great start! You've experienced <?php echo $totalHikes; ?> amazing hikes. <?php echo $completedHikes; ?> completed so far. The mountains are calling!
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="margin-top: 28px;">
        <div class="adventures-section-header">
          <h3>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M8 3 3 20h18L14 8l-2 4z"/></svg>
            Recent Adventures
            <span class="adventures-count-pill"><?php echo count($finishedHikes); ?> completed</span>
          </h3>
          <button class="btn btn-primary" style="padding: 9px 18px; font-size: 12px;" onclick="exportHikingJourney()">Share Journey</button>
        </div>

        <div class="adventures-list">
          <?php if (empty($finishedHikes)): ?>
            <div class="adventures-empty">
              <div class="adventures-empty-icon">⛰</div>
              <p>No completed hikes yet.<br>Finish your first adventure to see it here!</p>
            </div>
          <?php else: ?>
            <?php foreach ($finishedHikes as $item): ?>
              <div class="adventure-card">
                <div class="adventure-icon">⛰</div>
                <div class="adventure-body">
                  <div class="adventure-name"><?php echo htmlspecialchars($item['mountain_name']); ?></div>
                  <div class="adventure-meta">
                    <span class="adventure-date"><?php echo date('M j, Y', strtotime($item['date'])); ?></span>
                    <span class="adventure-sep"></span>
                    <span class="adventure-type"><?php echo htmlspecialchars($item['type']); ?></span>
                  </div>
                </div>
                <div class="adventure-cta">
                  <button class="btn-view-summary" onclick="viewActivity(<?php echo $item['booking_id']; ?>)">
                    <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    View Summary
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>     
    </div>

    <div id="section-personal" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Personal Information</h2>
        <div class="section-header-actions">
          <button class="btn-outline-small" onclick="openEditModal()">Edit Profile</button>
          <button class="btn-outline-small" onclick="openChangePasswordModal()">Change Password</button>
        </div>
      </div>
      
      <div id="personalInfoDisplay">
        <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?php echo htmlspecialchars($currentUser['name']); ?></div></div>
        <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?php echo htmlspecialchars($currentUser['email']); ?></div></div>
        <div class="info-row"><div class="info-label">Phone</div>
          <div class="info-value <?php echo empty($currentUser['phone']) ? 'important' : ''; ?>">
            <?php echo !empty($currentUser['phone']) ? htmlspecialchars($currentUser['phone']) : 'No phone number added yet — important for emergency contacts'; ?>
            <?php if (empty($currentUser['phone'])): ?>
              <small>Please add your mobile number for safety purposes</small>
            <?php endif; ?>
          </div>
        </div>
        <div class="info-row"><div class="info-label">Home Region</div><div class="info-value"><?php echo !empty($currentUser['home_region']) ? htmlspecialchars($currentUser['home_region']) : '—'; ?></div></div>
        <div class="info-row"><div class="info-label">Hiking Level</div><div class="info-value"><?php echo ucfirst(htmlspecialchars($currentUser['hiking_level'] ?? 'Not specified')); ?></div></div>
        <div class="info-row"><div class="info-label">Member Since</div><div class="info-value"><?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></div></div>
        <?php if (!empty($currentUser['last_login'])): ?>
        <div class="info-row"><div class="info-label">Last Login</div><div class="info-value"><?php echo date('M j, Y g:i A', strtotime($currentUser['last_login'])); ?></div></div>
        <?php endif; ?>
      </div>
    </div>

    <div id="section-saved" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Saved Mountains</h2>
        <button class="btn-outline-small" onclick="openAddSavedModal()">+ Add from Explore</button>
      </div>
      <div id="savedList" class="saved-mtn-grid">
        <?php if (empty($savedMountains)): ?>
          <div class="empty-state">No saved mountains. Favorite some peaks!</div>
        <?php else: ?>
          <?php foreach ($savedMountains as $mountain): ?>
            <div class="saved-mtn-card">
              <div class="saved-mtn-img" style="background-image: url('<?php echo htmlspecialchars($mountain['image'] ?? 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=80'); ?>')">
                <div class="saved-mtn-img-overlay"></div>
                <div class="saved-mtn-badge"><span class="badge badge-<?php echo strtolower($mountain['difficulty'] ?? 'moderate'); ?>"><?php echo htmlspecialchars($mountain['difficulty'] ?? 'Moderate'); ?></span></div>
                <form id="removeSavedForm-<?php echo $mountain['id']; ?>" method="POST" class="saved-mtn-remove" style="margin:0;">
                  <input type="hidden" name="action" value="remove_saved">
                  <input type="hidden" name="saved_id" value="<?php echo $mountain['id']; ?>">
                  <button type="button" style="background:transparent; border:none; cursor:pointer; width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:white;" onclick="confirmRemoveSaved('removeSavedForm-<?php echo $mountain['id']; ?>')">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </form>
              </div>
              <div class="saved-mtn-body">
                <div class="saved-mtn-name"><?php echo htmlspecialchars($mountain['name']); ?></div>
                <div class="saved-mtn-loc"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><?php echo htmlspecialchars($mountain['location'] ?? 'Unknown'); ?></div>
                <div class="saved-mtn-stats">
                  <span class="saved-mtn-stat">⛰ <?php echo htmlspecialchars($mountain['elevation'] ?? 'N/A'); ?></span>
                  <span class="saved-mtn-stat">⏱ <?php echo htmlspecialchars($mountain['duration'] ?? 'N/A'); ?></span>
                  <span class="saved-mtn-stat">★ <?php echo htmlspecialchars($mountain['rating'] ?? 'N/A'); ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div id="section-badges" class="profile-section">
        <div class="section-header">
            <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>My Badges</h2>
        </div>
        <div id="badgesContainer" class="badges-grid">
            <div class="loading-spinner">Loading your achievements...</div>
        </div>
    </div>

    <div id="section-settings" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Account Settings</h2>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Change Password</h4><p>Update your account password</p></div>
        <button class="btn-outline-small" onclick="openChangePasswordModal()">Change</button>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Two-Factor Authentication</h4><p>Add an extra layer of security to your account</p></div>
        <label class="toggle-switch"><input type="checkbox" id="twoFactorToggle" <?php echo $currentUser['two_factor_enabled'] ? 'checked' : ''; ?>><span class="slider"></span></label>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Delete Account</h4><p>Permanently delete your account and all data</p></div>
        <button class="btn-outline-danger" onclick="confirmDeleteAccount()">Delete Account</button>
      </div>
    </div>
    
<div id="section-system_review" class="profile-section">
  <div class="section-header">
    <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Rate LAKBAY</h2>
  </div>
  
  <div id="existingSystemReview" style="display:none; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
      <div>
        <h3 style="font-family:'Playfair Display', serif; font-size:18px; color:var(--forest); margin-bottom:4px;">Your Platform Review</h3>
        <p style="font-size:12px; color:var(--stone);">Thank you for helping us grow the hiking community.</p>
      </div>
      <button class="btn btn-sm btn-outline" onclick="enableEditSystemReview()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px; margin-right:6px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Review
      </button>
    </div>
    <div id="systemReviewExistingContent"></div>
  </div>
  
  <div id="systemReviewForm">
    <div class="story-summary" style="margin-bottom:24px;">
      <div class="story-icon">🌿</div>
      <div class="story-text">
        <strong>Help us improve!</strong> Your feedback directly impacts how we build Lakbay. We're constantly listening to our hiking community to make trail discovery safer and more enjoyable.
      </div>
    </div>
    
    <div class="field-group">
      <label class="field-label">Overall Experience</label>
      <div class="star-rating" id="systemStars">
        <span data-val="1">★</span><span data-val="2">★</span><span data-val="3">★</span><span data-val="4">★</span><span data-val="5">★</span>
      </div>
      <input type="hidden" id="systemRating" value="0">
    </div>
    
    <div class="field-group">
      <label class="field-label">Review Headline</label>
      <input type="text" id="systemTitle" class="inp" placeholder="Summarize your experience (e.g., Best hiking platform!)">
    </div>
    
    <div class="field-group">
      <label class="field-label">Detailed Feedback</label>
      <textarea id="systemComment" class="inp" rows="5" placeholder="What do you love about LAKBAY? What can we improve? Your thoughts on guides, booking, and trail info..."></textarea>
    </div>
    
    <div style="display:flex; justify-content:flex-end; margin-top:24px;">
      <button class="btn btn-primary" style="padding: 14px 40px;" onclick="submitSystemReview()" id="submitSystemBtn">Submit Review</button>
    </div>

    <div class="info-note" style="margin-top:32px;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>
        <strong>Privacy Note:</strong> Your review will be visible on our homepage after admin verification. Only hikers with completed adventures can share their story to ensure authentic community feedback.
      </div>
    </div>
  </div>
</div>
  </main>
  
</div>


<!-- Edit Profile Modal -->
<div class="modal-bg" id="editProfileModal">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title">Edit Profile</div>
      <button class="modal-close" onclick="closeModal('editProfileModal')">✕</button>
    </div>
    <form method="POST" action="">
      <div class="modal-body">
        <input type="hidden" name="action" value="update_profile">
        <div class="inp-label">Full Name</div>
        <input type="text" name="name" class="inp" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required>
        <div class="inp-label" style="margin-top:16px;">Email</div>
        <input type="email" name="email" class="inp" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
        <div class="inp-label" style="margin-top:16px;">Phone <span style="color: var(--danger);">*</span></div>
        <input type="text" name="phone" class="inp" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="e.g., +639123456789">
        <small style="color: var(--danger); display: block; margin-top: 4px;">Required for emergency contact</small>
        <div class="inp-label" style="margin-top:16px;">Home Region</div>
        <input type="text" name="home_region" class="inp" value="<?php echo htmlspecialchars($currentUser['home_region'] ?? ''); ?>" placeholder="e.g., Cavite, Philippines">
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:24px;">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal-bg" id="changePasswordModal">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title">Change Password</div>
      <button class="modal-close" onclick="closeModal('changePasswordModal')">✕</button>
    </div>
    <form method="POST" action="" onsubmit="return validatePasswordForm()">
      <div class="modal-body">
        <input type="hidden" name="action" value="change_password">
        <div class="inp-label">Current Password</div>
        <input type="password" name="current_password" id="current_password" class="inp" required>
        <div class="inp-label" style="margin-top:16px;">New Password</div>
        <input type="password" name="new_password" id="new_password" class="inp" required>
        <ul class="password-requirements" id="passwordReqs">
          <li>✓ At least 8 characters</li>
          <li>✓ One uppercase letter (A-Z)</li>
          <li>✓ One number (0-9)</li>
          <li>✓ One special character (!@#$%^&*)</li>
        </ul>
        <div class="inp-label" style="margin-top:16px;">Confirm New Password</div>
        <input type="password" name="confirm_password" id="confirm_password" class="inp" required>
        <div id="passwordError" class="error-message"></div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:24px;">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Saved Mountain Modal -->
<div class="modal-bg" id="addSavedModal">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title">Save a Mountain</div>
      <button class="modal-close" onclick="closeModal('addSavedModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="mtn-option-list">
        <?php foreach ($allMountains as $mountain): ?>
          <?php 
            $alreadySaved = false;
            foreach ($savedMountains as $saved) {
                if ($saved['mountain_id'] == $mountain['id']) {
                    $alreadySaved = true;
                    break;
                }
            }
          ?>
          <?php if (!$alreadySaved): ?>
          <form method="POST" class="mtn-option" style="margin:0;">
            <input type="hidden" name="action" value="add_saved">
            <input type="hidden" name="mountain_id" value="<?php echo $mountain['id']; ?>">
            <div>
              <div class="mtn-option-name"><?php echo htmlspecialchars($mountain['name']); ?></div>
              <div class="mtn-option-loc"><?php echo htmlspecialchars($mountain['location'] ?? ''); ?></div>
            </div>
            <button type="submit" class="btn-outline-small" style="padding: 4px 12px;">+ Save</button>
          </form>
          <?php else: ?>
          <div class="mtn-option" style="opacity: 0.6;">
            <div>
              <div class="mtn-option-name"><?php echo htmlspecialchars($mountain['name']); ?></div>
              <div class="mtn-option-loc">Already saved</div>
            </div>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Modals -->
<div class="modal-bg" id="logoutModal">
  <div class="modal" style="max-width: 380px;">
    <div class="modal-hdr"><div class="modal-title">Confirm Logout</div><button class="modal-close" onclick="closeModal('logoutModal')">✕</button></div>
    <div class="modal-body">
      <p style="margin-bottom: 8px;">Are you sure you want to logout?</p>
      <div class="confirm-buttons">
        <button class="btn btn-outline" onclick="closeModal('logoutModal')">Cancel</button>
        <form method="POST" action="logout.php" style="flex:1;"><button type="submit" class="btn btn-primary">Logout</button></form>
      </div>
    </div>
  </div>
</div>

<div class="modal-bg" id="confirmModal">
  <div class="modal" style="max-width: 400px; text-align: center;">
    <div class="modal-body" style="padding: 40px 28px;">
      <div style="font-size: 48px; margin-bottom: 20px;" id="confirmIcon">⚠️</div>
      <div class="modal-title" id="confirmTitle" style="margin-bottom: 12px;">Are you sure?</div>
      <p id="confirmText" style="font-size: 14px; color: var(--stone); margin-bottom: 28px;"></p>
      <div style="display: flex; gap: 12px;">
        <button class="btn btn-outline btn-full" onclick="closeConfirmModal()">Cancel</button>
        <button class="btn btn-primary btn-full" id="confirmBtn">Proceed</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function initSidebarNavigation() {
    const links = document.querySelectorAll('.sidebar-link[data-section]');
    const sections = ['history', 'personal', 'saved', 'badges', 'settings', 'system_review'];
    
    function showSection(sectionId) {
        // Hide all sections
        sections.forEach(s => {
            const section = document.getElementById(`section-${s}`);
            if(section) {
                section.classList.remove('active-section');
            }
        });
        
        // Show the selected section
        const activeSection = document.getElementById(`section-${sectionId}`);
        if(activeSection) {
            activeSection.classList.add('active-section');
        }
        
        // Update active state on sidebar links
        links.forEach(link => {
            if(link.getAttribute('data-section') === sectionId) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
        
        // Special handling for history section map
        if(sectionId === 'history') {
            setTimeout(initJourneyMap, 100);
        }
        if(sectionId === 'badges') {
            loadBadges();
        }
        if(sectionId === 'system_review') {
            setTimeout(() => {
                checkExistingSystemReview();
                initSystemStars();
            }, 100);
        }
    }
    
    // Add click event listeners
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const section = link.getAttribute('data-section');
            if(section) {
                showSection(section);
                // Update URL hash without scrolling
                window.location.hash = section;
            }
        });
    });
    
    // Check URL hash on load
    const hash = window.location.hash.substring(1);
    if(hash && sections.includes(hash)) {
        showSection(hash);
    } else {
        // Default to history section
        showSection('history');
    }
}
  function closeModal(modalId) { document.getElementById(modalId).classList.remove('open'); }
  function openEditModal() { document.getElementById('editProfileModal').classList.add('open'); }
  function openChangePasswordModal() { document.getElementById('changePasswordModal').classList.add('open'); }
  function openAddSavedModal() { document.getElementById('addSavedModal').classList.add('open'); }
  function openConfirm(title, text, onConfirm, icon = '⚠️', confirmText = 'Proceed', isDanger = true) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmText').textContent = text;
    document.getElementById('confirmIcon').textContent = icon;
    const btn = document.getElementById('confirmBtn');
    btn.textContent = confirmText;
    btn.style.background = isDanger ? 'var(--danger)' : 'var(--forest)';
    btn.onclick = () => { onConfirm(); closeConfirmModal(); };
    document.getElementById('confirmModal').classList.add('open');
  }
  function closeConfirmModal() { document.getElementById('confirmModal').classList.remove('open'); }
  function showToast(msg, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = `toast ${type}`;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  initSidebarNavigation();

  // Journey Map
  let journeyMap = null;
  const historyData = <?php echo json_encode($historyItems); ?>;
  function initJourneyMap() {
    if (journeyMap) return;
    const validHikes = historyData.filter(h => h.lat && h.lng && (h.status === 'finished' || h.status === 'completed' || h.status === 'active'));
    const center = validHikes.length > 0 ? [validHikes[0].lat, validHikes[0].lng] : [14.1333, 120.9167];
    journeyMap = L.map('journeyMap', { zoomControl: false, attributionControl: false }).setView(center, 10);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(journeyMap);
    const hikeIcon = L.divIcon({
        className: 'custom-div-icon',
        html: "<div style='background-color: #c9a84c; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;'></div>",
        iconSize: [12, 12], iconAnchor: [6, 6]
    });
    const bounds = [];
    validHikes.forEach(hike => {
        L.marker([hike.lat, hike.lng], { icon: hikeIcon }).addTo(journeyMap).bindPopup(`<strong>${hike.mountain_name}</strong><br>${hike.date}`);
        bounds.push([hike.lat, hike.lng]);
    });
    if (bounds.length > 1) journeyMap.fitBounds(bounds, { padding: [30, 30] });
  }

  document.querySelector('[data-section="history"]').addEventListener('click', () => setTimeout(initJourneyMap, 100));
  if (document.getElementById('section-history').classList.contains('active-section')) setTimeout(initJourneyMap, 100);

  function exportHikingJourney() {
    const region = document.getElementById('exportRegion');
    showToast("📸 Capturing your journey...", "info");
    setTimeout(() => {
        html2canvas(region, { useCORS: true, allowTaint: true, backgroundColor: '#faf7f2', scale: 2 }).then(canvas => {
            const link = document.createElement('a');
            link.download = `LAKBAY_Journey_<?php echo addslashes($currentUser['name']); ?>.jpg`;
            link.href = canvas.toDataURL('image/jpeg', 0.9);
            link.click();
            showToast("✨ Journey exported as JPG!", "success");
        });
    }, 500);
  }

  function loadBadges() {
    fetch('../api/get_user_badges.php').then(r => r.json()).then(data => {
        if (data.success && data.badges.length > 0) {
            document.getElementById('badgesContainer').innerHTML = data.badges.map(badge => `
                <div class="badge-card ${badge.count > 1 ? 'duplicate' : ''}" onclick="showToast('${badge.name} earned ${badge.count}x times!')">
                    <div class="badge-icon">${badge.icon}</div>
                    <div class="badge-name">${badge.name}</div>
                    <div class="badge-description">${badge.description || 'Achievement unlocked!'}</div>
                    ${badge.count > 1 ? `<div class="badge-earned-count">Earned ${badge.count}x times</div>` : ''}
                    <div class="badge-earned-date">${badge.dates[0]}</div>
                    ${badge.count > 1 && badge.dates.length > 1 ? `<div class="badge-earned-date" style="font-size: 8px;">+ ${badge.dates.length - 1} more</div>` : ''}
                </div>
            `).join('');
        } else {
            document.getElementById('badgesContainer').innerHTML = '<div class="empty-state">No badges yet. Start hiking!</div>';
        }
    }).catch(err => {
        console.error('Error loading badges:', err);
        document.getElementById('badgesContainer').innerHTML = '<div class="empty-state">Unable to load badges</div>';
    });
}
  document.querySelector('[data-section="badges"]').addEventListener('click', loadBadges);

 function showHikeDetails(hike) {
    // Generate different memory note based on hike status
    let memoryNote = '';
    let showHikeAgain = false;
    
    switch(hike.status) {
        case 'completed':
            memoryNote = '✨ What an incredible journey! The summit view was breathtaking, and every step was worth it. This mountain will always hold a special place in your heart. 🏔️';
            showHikeAgain = true;
            break;
        case 'active':
            memoryNote = '🌟 Your adventure is still unfolding! The trail awaits, and new memories are being made with every step. Keep going! 🥾';
            showHikeAgain = true;
            break;
        case 'pending':
            memoryNote = '⏳ Your planned adventure is coming soon! The mountains are waiting for you. Get ready for an unforgettable experience! 📅';
            showHikeAgain = true;
            break;
        case 'cancelled':
            memoryNote = ''; // No memory note for cancelled hikes
            showHikeAgain = false;
            break;
        default:
            memoryNote = 'Every summit reached is a victory. The mountains called, and you answered.';
            showHikeAgain = true;
    }
    
    const modalHtml = `
        <div class="modal" style="max-width: 500px;">
            <div class="modal-hdr">
                <div class="modal-title">${hike.mountain_name}</div>
                <button class="modal-close" onclick="closeModal('hikeDetailModal')">✕</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <div class="history-status status-${hike.status}">${hike.status.toUpperCase()}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date</div>
                    <div class="info-value">${new Date(hike.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Location</div>
                    <div class="info-value">${hike.location}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type</div>
                    <div class="info-value">${hike.type}</div>
                </div>
                ${hike.booking_number ? `
                <div class="info-row">
                    <div class="info-label">Booking ID</div>
                    <div class="info-value" style="font-family: monospace;">${hike.booking_number}</div>
                </div>
                ` : ''}
                
                ${memoryNote ? `
                <div style="background: linear-gradient(135deg, var(--sky), rgba(201,168,76,0.1)); border-radius: 12px; padding: 16px; margin-top: 20px;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: var(--gold);">📖 Memory Note</div>
                    <p style="font-size: 13px; line-height: 1.6; color: var(--forest);">${memoryNote}</p>
                </div>
                ` : ''}
                
                ${hike.status === 'cancelled' ? `
                <div style="background: rgba(192,57,43,0.1); border-radius: 12px; padding: 16px; margin-top: 20px; border-left: 3px solid var(--danger);">
                    <div style="font-weight: 600; margin-bottom: 4px; color: var(--danger);">⚠️ Cancelled Adventure</div>
                    <p style="font-size: 12px; color: var(--stone);">This hike was cancelled. We hope you can reschedule and conquer this peak another time! 🌄</p>
                </div>
                ` : ''}
                
                <div style="margin-top: 24px; display: flex; gap: 12px;">
                    <button class="btn btn-outline btn-full" onclick="closeModal('hikeDetailModal')">Close</button>
                    ${showHikeAgain && hike.status !== 'cancelled' ? `
                    <button class="btn btn-primary btn-full" onclick="window.location.href='explore.php'">
                        🏔️ Hike Again
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    
    let m = document.getElementById('hikeDetailModal');
    if (!m) {
        m = document.createElement('div');
        m.id = 'hikeDetailModal';
        m.className = 'modal-bg';
        document.body.appendChild(m);
    }
    m.innerHTML = modalHtml;
    m.classList.add('open');
    m.onclick = (e) => { if (e.target === m) m.classList.remove('open'); };
}
  function confirmCancelHike(formId) {
    openConfirm('Cancel Hike', 'Cancel this booking?', () => document.getElementById(formId).submit(), '🗑️', 'Yes, Cancel');
  }

  function confirmRemoveSaved(formId) {
    openConfirm('Remove Saved', 'Remove from saved peaks?', () => document.getElementById(formId).submit(), '❓', 'Remove');
  }

  document.getElementById('logoutBtn')?.addEventListener('click', () => document.getElementById('logoutModal').classList.add('open'));
  document.querySelectorAll('.modal-bg').forEach(bg => bg.addEventListener('click', (e) => { if(e.target === bg) bg.classList.remove('open'); }));


// ── SYSTEM REVIEW FUNCTIONS ──
let editingSystemReview = false;
let existingSystemReviewData = null;

function initSystemStars() {
    document.querySelectorAll('#systemStars span').forEach(star => {
        star.addEventListener('click', function() {
            const val = this.getAttribute('data-val');
            document.getElementById('systemRating').value = val;
            const stars = document.querySelectorAll('#systemStars span');
            stars.forEach(s => {
                s.classList.toggle('active', s.getAttribute('data-val') <= val);
            });
        });
    });
}

function checkExistingSystemReview() {
    fetch('../api/get_system_review.php', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success && result.review) {
            existingSystemReviewData = result.review;
            displayExistingSystemReview(existingSystemReviewData);
            document.getElementById('systemReviewForm').style.display = 'none';
            document.getElementById('existingSystemReview').style.display = 'block';
        } else {
            document.getElementById('systemReviewForm').style.display = 'block';
            document.getElementById('existingSystemReview').style.display = 'none';
        }
    })
    .catch(err => console.error('Error checking system review:', err));
}

function displayExistingSystemReview(review) {
    const contentDiv = document.getElementById('systemReviewExistingContent');
    const statusLabel = review.status === 'approved' ? 'Approved' : (review.status === 'pending' ? 'Pending Approval' : 'Rejected');
    const statusClass = review.status;
    
    const rating = parseInt(review.rating);
    const activeStars = '<span class="active">★</span>'.repeat(rating);
    const inactiveStars = '<span>★</span>'.repeat(5 - rating);
    
    contentDiv.innerHTML = `
        <div class="review-data-row">
            <span class="review-data-label">Your Rating</span>
            <div class="star-rating readonly">
                ${activeStars}${inactiveStars}
            </div>
        </div>
        <div class="review-data-row">
            <span class="review-data-label">Review Title</span>
            <div class="review-data-value" style="font-weight:700;">${review.title || 'No title provided'}</div>
        </div>
        <div class="review-data-row">
            <span class="review-data-label">Your Feedback</span>
            <div class="review-data-value">${review.comment}</div>
        </div>
        <div style="margin-top:24px; padding-top:16px; border-top:1px solid rgba(16,6,0,0.06); display:flex; justify-content:space-between; align-items:center;">
            <div class="status-badge ${statusClass}">
                ${review.status === 'approved' ? '✅' : (review.status === 'pending' ? '⏳' : '❌')} ${statusLabel}
            </div>
            <span style="font-size:11px; color:var(--stone);">Submitted on ${new Date(review.created_at).toLocaleDateString()}</span>
        </div>
    `;
}

function enableEditSystemReview() {
    editingSystemReview = true;
    document.getElementById('existingSystemReview').style.display = 'none';
    document.getElementById('systemReviewForm').style.display = 'block';
    
    if (existingSystemReviewData) {
        // Pre-fill stars
        const stars = document.querySelectorAll('#systemStars span');
        stars.forEach(star => {
            const val = parseInt(star.getAttribute('data-val'));
            if (val <= existingSystemReviewData.rating) {
                star.classList.add('active');
            }
        });
        document.getElementById('systemRating').value = existingSystemReviewData.rating;
        document.getElementById('systemTitle').value = existingSystemReviewData.title || '';
        document.getElementById('systemComment').value = existingSystemReviewData.comment;
        document.getElementById('submitSystemBtn').textContent = 'Update Review';
    }
}

function submitSystemReview() {
    const rating = parseInt(document.getElementById('systemRating').value);
    const title = document.getElementById('systemTitle').value.trim();
    const comment = document.getElementById('systemComment').value.trim();
    
    if (rating === 0) {
        showToast('Please select a rating', 'error');
        return;
    }
    if (!comment) {
        showToast('Please write your feedback', 'error');
        return;
    }
    
    const btn = document.getElementById('submitSystemBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting...';
    
    fetch('../api/save_system_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            rating: rating,
            title: title,
            comment: comment,
            review_id: existingSystemReviewData?.id || null,
            is_edit: editingSystemReview
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('✓ Review submitted! Awaiting admin approval.', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast(result.message || 'Error submitting review', 'error');
            btn.disabled = false;
            btn.textContent = editingSystemReview ? 'Update Review' : 'Submit Review';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = editingSystemReview ? 'Update Review' : 'Submit Review';
    });
}

// Load system review when section is clicked
document.querySelector('[data-section="system_review"]').addEventListener('click', () => {
    setTimeout(() => {
        checkExistingSystemReview();
        initSystemStars();
    }, 100);
});

function viewActivity(bookingId) {
    window.location.href = `view_activity.php?booking_id=${bookingId}`;
}

</script>
</body>
</html>