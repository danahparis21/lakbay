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
    SELECT b.*, m.name as mountain_name, m.location, m.image, 'day_hike' as booking_type 
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
        'image' => $hike['image']
    ];
}

// Camping bookings
$stmt = $pdo->prepare("
    SELECT c.*, m.name as mountain_name, m.location, m.image 
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
        'image' => $camping['image']
    ];
}

// Sort by date (newest first)
usort($historyItems, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

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

// Calculate total hikes count (completed + active + cancelled)
$totalHikes = count($historyItems);

// Calculate completed hikes count
$completedHikes = 0;
foreach ($historyItems as $item) {
    if ($item['status'] === 'completed' || $item['status'] === 'active') {
        $completedHikes++;
    }
}

// Badges count (based on achievements)
$badgeCount = 0;
if ($completedHikes >= 1) $badgeCount++;
if ($completedHikes >= 5) $badgeCount++;
if ($completedHikes >= 10) $badgeCount++;
if ($currentUser['hiking_level'] !== 'beginner') $badgeCount++;
if (count($savedMountains) >= 3) $badgeCount++;

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExt = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $fileName = 'user_' . $currentUserId . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($_FILES['avatar']['type'], $allowedTypes) && $_FILES['avatar']['error'] === 0) {
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
            $avatarPath = '/uploads/avatars/' . $fileName;
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$avatarPath, $currentUserId]);
            $currentUser['avatar'] = $avatarPath;
            $_SESSION['user_avatar'] = $avatarPath;
        }
    }
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
    
    // Refetch day hikes
    $stmt = $pdo->prepare("
        SELECT b.*, m.name as mountain_name, m.location, m.image, 'day_hike' as booking_type 
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
            'image' => $hike['image']
        ];
    }
    
    // Refetch camping bookings
    $stmt = $pdo->prepare("
        SELECT c.*, m.name as mountain_name, m.location, m.image 
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
            'image' => $camping['image']
        ];
    }
    
    usort($historyItems, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY — My Profile</title>
  <link rel="stylesheet" href="shared.css">
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
      padding-top: 20px; /* Aligns with sidebar's top offset */
    }
    .profile-section {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 28px;
      margin-bottom: 24px;
      border: 1px solid rgba(16,6,0,0.08);
      display: none;
    }
    .profile-section.active-section {
      display: block;
      animation: fadeIn 0.25s ease;
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
      max-height: 420px;
      overflow-y: auto;
      padding-right: 8px;
      scrollbar-width: thin;
      scrollbar-color: var(--gold) transparent;
    }
    .history-list::-webkit-scrollbar {
      width: 4px;
    }
    .history-list::-webkit-scrollbar-thumb {
      background-color: var(--gold);
      border-radius: 10px;
    }
    .history-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 16px;
      background: rgba(16,6,0,0.02);
      border-radius: var(--radius-sm);
      transition: 0.2s;
      flex-wrap: wrap;
      gap: 12px;
    }
    .history-item:hover { background: rgba(16,6,0,0.05); }
    .history-info {
      flex: 1;
    }
    .history-info h4 {
      font-size: 15px;
      font-weight: 600;
      color: var(--forest);
      margin-bottom: 4px;
    }
    .history-info p {
      font-size: 11px;
      color: var(--stone);
    }
    .history-status {
      font-size: 10px;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 50px;
    }
    .status-active { background: #27ae60; color: white; }
    .status-completed { background: #3498db; color: white; }
    .status-cancelled { background: #95a5a6; color: white; }
    .status-pending { background: #f39c12; color: white; }

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
    .btn-icon {
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 6px;
      border-radius: 8px;
      color: var(--danger);
      transition: 0.2s;
    }
    .btn-icon:hover { background: rgba(192,57,43,0.1); }

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
    .error-message {
      color: var(--danger);
      font-size: 12px;
      margin-top: 6px;
    }
    .success-message {
      color: var(--success);
      font-size: 12px;
      margin-top: 6px;
    }
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
      z-index: 1100;
      transition: 0.3s;
      opacity: 0;
      white-space: nowrap;
    }
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }

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
  </style>
</head>
<body>

<!-- MOBILE BACK BUTTON BAR -->
<div class="mobile-back-bar">
  <button class="back-btn" onclick="goBack()">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
  </button>
  <h3>My Profile</h3>
</div>

<!-- DESKTOP NAV -->
<nav class="desktop-nav">
  <a href="../index.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore
    </a>
    <a href="bookings.php" class="tab-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings
    </a>
    <a href="quiz.php" class="tab-link">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz
    </a>
    <a href="messages.php" class="tab-link">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages
    </a>
  </div>
  <a href="hikerProfile.php" class="user-btn active"><?php echo htmlspecialchars($userInitial); ?></a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span></a>
    <a href="bookings.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span></a>
    <a href="quiz.php" class="mob-nav-item quiz-center"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></a>
    <a href="messages.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="hikerProfile.php" class="mob-nav-item active"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
  </div>
</nav>

<!-- PROFILE LAYOUT -->
<div class="profile-layout">
  <!-- Sidebar Navigation -->
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
      <div class="sidebar-link active" data-section="personal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Personal Info
      </div>
      <div class="sidebar-link" data-section="history">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>
        Hiking History
      </div>
      <div class="sidebar-link" data-section="saved">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Saved Mountains
      </div>
      <div class="sidebar-link" data-section="location">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Location & Tracking
      </div>
      <div class="sidebar-link" data-section="settings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Account Settings
      </div>
      <div class="sidebar-logout">
        <div class="sidebar-link" id="logoutBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </div>
      </div>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="profile-main">
    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-number"><?php echo $totalHikes; ?></div><div class="stat-label">Total Hikes</div></div>
      <div class="stat-card"><div class="stat-number"><?php echo count($savedMountains); ?></div><div class="stat-label">Saved Peaks</div></div>
      <div class="stat-card"><div class="stat-number"><?php echo $badgeCount; ?></div><div class="stat-label">Badges Earned</div></div>
    </div>

    <!-- Section 1: Personal Information -->
    <div id="section-personal" class="profile-section active-section">
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

    <!-- Section 2: Hiking History -->
    <div id="section-history" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>Hiking History</h2>
      </div>
      <div id="historyList" class="history-list">
        <?php if (empty($historyItems)): ?>
          <div class="empty-state">No hikes recorded yet. Make your first booking!</div>
        <?php else: ?>
          <?php foreach ($historyItems as $item): ?>
            <div class="history-item">
              <div class="history-info">
                <h4><?php echo htmlspecialchars($item['mountain_name']); ?></h4>
                <p><?php echo date('F j, Y', strtotime($item['date'])); ?> · <?php echo htmlspecialchars($item['type']); ?></p>
                <p><small><?php echo htmlspecialchars($item['location']); ?></small></p>
              </div>
              <div>
                <span class="history-status status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span>
              </div>
              <?php if ($item['status'] === 'active' || $item['status'] === 'pending'): ?>
              <form method="POST" style="margin:0;" onsubmit="return confirm('Cancel this booking?');">
                <input type="hidden" name="action" value="cancel_booking">
                <input type="hidden" name="booking_id" value="<?php echo $item['id']; ?>">
                <input type="hidden" name="booking_type" value="<?php echo $item['booking_type']; ?>">
                <button type="submit" class="btn-outline-danger" style="padding: 4px 12px; font-size: 11px;">Cancel</button>
              </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Section 3: Saved Mountains -->
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
                <form method="POST" class="saved-mtn-remove" onsubmit="return confirm('Remove from saved?');" style="margin:0;">
                  <input type="hidden" name="action" value="remove_saved">
                  <input type="hidden" name="saved_id" value="<?php echo $mountain['id']; ?>">
                  <button type="submit" style="background:transparent; border:none; cursor:pointer; width:100%; height:100%; display:flex; align-items:center; justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
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

    <!-- Section 4: Location & Tracking -->
    <div id="section-location" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>Location & Tracking</h2>
      </div>
      <div class="setting-row">
        <div class="setting-info">
          <h4>Share real-time location</h4>
          <p>Allow Lakbay to track your location during active hikes for safety & trail recommendations.</p>
        </div>
        <label class="toggle-switch"><input type="checkbox" id="locationToggle"><span class="slider"></span></label>
      </div>
      <div id="locationStatusMsg" style="font-size: 12px; color: var(--sage); margin-top: 16px; padding: 12px; background: rgba(16,6,0,0.03); border-radius: 10px;"></div>
    </div>

    <!-- Section 5: Account Settings -->
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
  </main>
</div>

<!-- MODALS -->

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
        <?php if (isset($passwordSuccess)): ?>
          <div class="success-message"><?php echo $passwordSuccess; ?></div>
        <?php endif; ?>
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

<!-- Delete Account Modal -->
<div class="modal-bg" id="deleteAccountModal">
  <div class="modal" style="max-width: 400px;">
    <div class="modal-hdr">
      <div class="modal-title">Delete Account</div>
      <button class="modal-close" onclick="closeModal('deleteAccountModal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom: 12px;">Are you absolutely sure?</p>
      <p style="font-size: 12px; color: var(--stone); margin-bottom: 20px;">This action <strong>cannot be undone</strong>. This will permanently delete your account and all associated data including bookings, saved mountains, and hiking history.</p>
      <div class="confirm-buttons">
        <button class="btn btn-outline" onclick="closeModal('deleteAccountModal')">Cancel</button>
        <form method="POST" action="delete_account.php" style="flex:1;" onsubmit="return confirm('This cannot be undone. Delete your account permanently?');">
          <button type="submit" class="btn btn-primary" style="background: var(--danger); color: white;">Delete Permanently</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Logout Modal -->
<div class="modal-bg" id="logoutModal">
  <div class="modal" style="max-width: 380px;">
    <div class="modal-hdr">
      <div class="modal-title">Confirm Logout</div>
      <button class="modal-close" onclick="closeModal('logoutModal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom: 8px;">Are you sure you want to logout?</p>
      <p style="font-size: 12px; color: var(--stone);">You'll need to login again to access your account.</p>
      <div class="confirm-buttons">
        <button class="btn btn-outline" onclick="closeModal('logoutModal')">Cancel</button>
        <form method="POST" action="logout.php" style="flex:1;">
          <button type="submit" class="btn btn-primary">Logout</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  // Sidebar navigation
  function initSidebarNavigation() {
    const links = document.querySelectorAll('.sidebar-link[data-section]');
    const sections = ['personal', 'history', 'saved', 'location', 'settings'];
    
    function showSection(sectionId) {
      sections.forEach(s => {
        const section = document.getElementById(`section-${s}`);
        if(section) section.classList.remove('active-section');
      });
      const activeSection = document.getElementById(`section-${sectionId}`);
      if(activeSection) activeSection.classList.add('active-section');
      
      links.forEach(link => {
        if(link.getAttribute('data-section') === sectionId) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }
    
    links.forEach(link => {
      link.addEventListener('click', (e) => {
        const section = link.getAttribute('data-section');
        if(section) showSection(section);
      });
    });
  }

  function goBack() {
    if (document.referrer && document.referrer.includes(window.location.hostname)) {
      window.history.back();
    } else {
      window.location.href = "explore.php";
    }
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
  }

  function openEditModal() {
    document.getElementById('editProfileModal').classList.add('open');
  }

  function openChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('open');
    // Clear form fields
    document.getElementById('current_password').value = '';
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    const errorDiv = document.getElementById('passwordError');
    if (errorDiv) errorDiv.innerHTML = '';
  }

  function openAddSavedModal() {
    document.getElementById('addSavedModal').classList.add('open');
  }

  function confirmDeleteAccount() {
    document.getElementById('deleteAccountModal').classList.add('open');
  }

  function validatePasswordForm() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const errorDiv = document.getElementById('passwordError');
    
    // Password validation
    if (newPassword.length < 8) {
      errorDiv.innerHTML = 'Password must be at least 8 characters.';
      return false;
    }
    if (!/[A-Z]/.test(newPassword)) {
      errorDiv.innerHTML = 'Password must contain at least one uppercase letter.';
      return false;
    }
    if (!/[0-9]/.test(newPassword)) {
      errorDiv.innerHTML = 'Password must contain at least one number.';
      return false;
    }
    if (!/[^a-zA-Z0-9]/.test(newPassword)) {
      errorDiv.innerHTML = 'Password must contain at least one special character.';
      return false;
    }
    if (newPassword !== confirmPassword) {
      errorDiv.innerHTML = 'New passwords do not match.';
      return false;
    }
    
    errorDiv.innerHTML = '';
    return true;
  }

  // Location tracking
  let locationEnabled = localStorage.getItem('locationEnabled') === 'true';
  const locationToggle = document.getElementById('locationToggle');
  const locationMsg = document.getElementById('locationStatusMsg');
  
  if (locationToggle) {
    locationToggle.checked = locationEnabled;
  }
  
  function updateLocationMsg() {
    if (locationEnabled) {
      locationMsg.innerHTML = "Location tracking is ACTIVE. You'll get trail-specific alerts & route suggestions.";
      if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition((pos) => {
          locationMsg.innerHTML += `<br><span style="font-size:10px;">Last known: ${pos.coords.latitude.toFixed(2)}, ${pos.coords.longitude.toFixed(2)}</span>`;
        }, () => {});
      }
    } else {
      locationMsg.innerHTML = "Location sharing is OFF. Turn on to enable smart safety features and trail tracking.";
    }
  }
  
  if (locationToggle) {
    locationToggle.addEventListener('change', (e) => {
      locationEnabled = e.target.checked;
      localStorage.setItem('locationEnabled', locationEnabled);
      updateLocationMsg();
      showToast(locationEnabled ? "Location tracking enabled" : "Location tracking disabled");
    });
  }
  
  updateLocationMsg();

  // Two-factor toggle
  const twoFactorToggle = document.getElementById('twoFactorToggle');
  if (twoFactorToggle) {
    twoFactorToggle.addEventListener('change', async (e) => {
      const enabled = e.target.checked;
      // You can implement AJAX to update two_factor_enabled in database
      showToast(enabled ? "Two-factor authentication enabled" : "Two-factor authentication disabled");
    });
  }

  function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.innerText = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2500);
  }

  // Display success/error messages from PHP
  <?php if (isset($successMessage)): ?>
    showToast("<?php echo addslashes($successMessage); ?>");
  <?php endif; ?>
  
  <?php if (isset($passwordSuccess)): ?>
    showToast("<?php echo addslashes($passwordSuccess); ?>");
    closeModal('changePasswordModal');
  <?php endif; ?>
  
  <?php if (isset($errors) && !empty($errors)): ?>
    showToast("<?php echo addslashes(implode(', ', $errors)); ?>");
  <?php endif; ?>
  
  <?php if (isset($passwordErrors) && !empty($passwordErrors)): ?>
    showToast("<?php echo addslashes(implode(', ', $passwordErrors)); ?>");
  <?php endif; ?>

  // Modal background click to close
  document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', function(e) {
      if (e.target === bg) bg.classList.remove('open');
    });
  });

  // Logout button
  document.getElementById('logoutBtn')?.addEventListener('click', () => {
    document.getElementById('logoutModal').classList.add('open');
  });

  initSidebarNavigation();
</script>
</body>
</html>