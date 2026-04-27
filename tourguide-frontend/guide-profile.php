<?php
session_start();
require_once '../config/db.php';

// ── AUTH: require guide login ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.name, u.email, u.phone, u.avatar,
           g.id as guide_id, g.specialization, g.years_experience, g.bio,
           g.gcash_name, g.gcash_number, g.gcash_qr_code
    FROM users u
    LEFT JOIN guides g ON g.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$guide_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide_data) {
    die('Guide profile not found.');
}

$guide_id = $guide_data['guide_id'];

// ── Fetch assigned mountains ──────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT m.id, m.name
    FROM guide_mountains gm
    JOIN mountains m ON m.id = gm.mountain_id
    WHERE gm.guide_id = ?
");
$stmt->execute([$guide_id]);
$assigned_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);
$assigned_mtn_ids = array_map(fn($m) => $m['id'], $assigned_mountains);
$assigned_mtn_names = array_map(fn($m) => $m['name'], $assigned_mountains);


// ── Fetch existing rates for assigned mountains ──────────────────────────
$rates = [];
if (!empty($assigned_mtn_ids)) {
    $placeholders = implode(',', array_fill(0, count($assigned_mtn_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT * FROM guide_mountain_rates 
        WHERE guide_id = ? AND mountain_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$guide_id], $assigned_mtn_ids));
    $existing_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($existing_rates as $rate) {
        $rates[$rate['mountain_id']][$rate['trail_type']] = $rate;
    }
}

// ── Fetch all mountains for selector ─────────────────────────────────────
$stmt = $pdo->query("SELECT id, name FROM mountains ORDER BY name ASC");
$all_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Avatar initials
$name_parts = explode(' ', $guide_data['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // ACTION 1: Update basic profile (email, phone, specialization, bio, GCash)
        if ($action === 'update_profile') {
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $specialization = $_POST['specialization'] ?? '';
            $years_experience = (int)($_POST['years_experience'] ?? 0);
            $bio = $_POST['bio'] ?? '';
            $gcash_name = $_POST['gcash_name'] ?? '';
            $gcash_number = $_POST['gcash_number'] ?? '';
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$email, $phone, $user_id]);
            
            // Update guides table
            $stmt = $pdo->prepare("UPDATE guides SET specialization = ?, years_experience = ?, bio = ?, gcash_name = ?, gcash_number = ? WHERE user_id = ?");
            $stmt->execute([$specialization, $years_experience, $bio, $gcash_name, $gcash_number, $user_id]);
            
            // Handle Avatar Upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $file_name = 'guide_' . $user_id . '_' . time() . '.' . $file_ext;
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                    $avatar_path = 'uploads/avatars/' . $file_name;
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$avatar_path, $user_id]);
                }
            }
            
            // Handle QR Code upload
            if (isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/qr_codes/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_ext = pathinfo($_FILES['gcash_qr']['name'], PATHINFO_EXTENSION);
                $file_name = 'gcash_' . $guide_id . '_' . time() . '.' . $file_ext;
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $target_file)) {
                    $gcash_qr = 'uploads/qr_codes/' . $file_name;
                    $stmt = $pdo->prepare("UPDATE guides SET gcash_qr_code = ? WHERE user_id = ?");
                    $stmt->execute([$gcash_qr, $user_id]);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            exit;
        }
        
        // ACTION 2: Update mountain assignments only
        if ($action === 'update_mountains') {
            $mtn_ids = isset($_POST['mountains']) ? explode(',', $_POST['mountains']) : [];
            
            // Delete existing assignments
            $pdo->prepare("DELETE FROM guide_mountains WHERE guide_id = ?")->execute([$guide_id]);
            
            // Insert new assignments
            if (!empty($mtn_ids)) {
                $stmt = $pdo->prepare("INSERT INTO guide_mountains (guide_id, mountain_id) VALUES (?, ?)");
                foreach ($mtn_ids as $mid) {
                    if (!empty($mid)) $stmt->execute([$guide_id, $mid]);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Mountains updated successfully']);
            exit;
        }
        
        // ACTION 3: Update fees for a specific mountain
        if ($action === 'update_fees') {
            $mountain_id = $_POST['mountain_id'] ?? 0;
            $day_fee = $_POST['day_fee'] ?? 800;
            $night_fee = $_POST['night_fee'] ?? 1500;
            $max_pax = $_POST['max_pax'] ?? 5;
            
            // Check if rate exists
            $stmt = $pdo->prepare("SELECT id FROM guide_mountain_rates WHERE guide_id = ? AND mountain_id = ? AND trail_type = 'standard'");
            $stmt->execute([$guide_id, $mountain_id]);
            
            if ($stmt->fetch()) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE guide_mountain_rates SET guide_fee_day = ?, guide_fee_overnight = ?, max_pax = ? WHERE guide_id = ? AND mountain_id = ? AND trail_type = 'standard'");
                $stmt->execute([$day_fee, $night_fee, $max_pax, $guide_id, $mountain_id]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO guide_mountain_rates (guide_id, mountain_id, trail_type, max_pax, guide_fee_day, guide_fee_overnight) VALUES (?, ?, 'standard', ?, ?, ?)");
                $stmt->execute([$guide_id, $mountain_id, $max_pax, $day_fee, $night_fee]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Fees updated successfully']);
            exit;
        }
        
        // If no valid action, return error
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Profile</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
       /* 3-COLUMN LAYOUT */
    .profile-layout-3col {
      display: grid;
      grid-template-columns: 280px 1fr 380px;
      gap: 24px;
      max-width: 1400px;
    }
    
    @media (max-width: 1100px) {
      .profile-layout-3col {
        grid-template-columns: 280px 1fr;
      }
      .col-fees { grid-column: span 2; }
    }
    
    @media (max-width: 768px) {
      .profile-layout-3col {
        grid-template-columns: 1fr;
      }
      .col-fees { grid-column: span 1; }
    }
    
    /* Fee card styling */
    .fee-card {
      background: #f8f9fa;
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 16px;
      border: 1px solid #e0e0e0;
      transition: all 0.2s;
    }
    .fee-card.saved {
      border-left: 3px solid #2e7d32;
      background: #f0f8f0;
    }
    .fee-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
      padding-bottom: 8px;
      border-bottom: 1px solid #e0e0e0;
    }
    .fee-card-title {
      font-weight: 700;
      font-size: 0.9rem;
      color: #1a1a18;
    }
    .fee-status {
      font-size: 0.6rem;
      padding: 3px 8px;
      border-radius: 20px;
      background: #fff3cd;
      color: #856404;
    }
    .fee-status.saved {
      background: #d4edda;
      color: #155724;
    }
    .fee-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 10px;
    }
    .fee-field {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .fee-field label {
      font-size: 0.65rem;
      font-weight: 600;
      color: #555;
      text-transform: uppercase;
    }
    .fee-field input {
      padding: 8px 12px;
      border-radius: 8px;
      border: 1px solid #ddd;
      font-size: 0.85rem;
      color: #1a1a18;
      background: white;
    }
    .fee-help {
      font-size: 0.6rem;
      color: #777;
      margin-top: 4px;
    }
    .save-fee-btn {
      margin-top: 12px;
      width: 100%;
      padding: 8px;
      background: #1a1a18;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .save-fee-btn:hover {
      background: #3a3a35;
      transform: translateY(-1px);
    }
    .save-fee-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    /* Mountain selector section */
    .mountains-section {
      background: white;
      border: 1px solid #e0e0e0;
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 20px;
    }
    .section-header-with-save {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .section-header-with-save div div:first-child {
      font-weight: 700;
      color: #1a1a18;
      margin-bottom: 4px;
    }
    .section-header-with-save div div:last-child {
      font-size: 0.7rem;
      color: #666;
    }
    .btn-save-mountains {
      padding: 8px 16px;
      background: #1a1a18;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-save-mountains:hover {
      background: #3a3a35;
    }
    
    /* PROFILE CARD */
    .profile-card {
      background: white;
      border: 1px solid #e0e0e0;
      border-radius: 16px;
      overflow: hidden;
    }
    .profile-card-top {
      background: #1a1a18;
      padding: 32px 20px 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
    }
    .profile-avatar-wrap {
      position: relative;
      margin-bottom: 14px;
    }
    .profile-avatar-img {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      border: 4px solid rgba(255,255,255,0.3);
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      color: #1a1a18;
      overflow: hidden;
    }
    .profile-avatar-img img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .profile-avatar-edit-btn {
      position: absolute;
      bottom: 2px;
      right: 2px;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: white;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #1a1a18;
      font-size: 0.7rem;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      transition: all 0.15s;
    }
    .profile-avatar-edit-btn:hover { background: #1a1a18; color: white; }
    .profile-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.2rem;
      font-weight: 700;
      color: white;
      margin-bottom: 4px;
    }
    .profile-name-note {
      font-size: 0.65rem;
      color: rgba(255,255,255,0.5);
      font-family: 'DM Mono', monospace;
      letter-spacing: 0.5px;
    }
    .profile-role {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.7);
      margin-top: 4px;
    }
    .profile-card-body {
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .profile-info-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      border-radius: 10px;
      background: #f0f0f0;
    }
    .profile-info-icon {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background: #e0e0e0;
      color: #1a1a18;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
      flex-shrink: 0;
    }
    .profile-info-label { 
      font-size: 0.65rem; 
      color: #666; 
      margin-bottom: 1px; 
    }
    .profile-info-val { 
      font-size: 0.8rem; 
      font-weight: 600; 
      color: #1a1a18; 
    }
    
    /* Mountain chips */
    .mountain-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 4px;
    }
    .mountain-chip {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 20px;
      background: #e8e8e8;
      border: 1px solid #ccc;
      font-size: 0.72rem;
      font-weight: 600;
      color: #1a1a18;
    }
    
    /* EDIT FORM */
    .edit-form-card {
      background: white;
      border: 1px solid #e0e0e0;
      border-radius: 16px;
      padding: 24px;
    }
    .edit-form-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.05rem;
      font-weight: 600;
      color: #1a1a18;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .edit-form-title i { color: #1a1a18; font-size: 0.9rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { margin-bottom: 16px; }
    .form-label {
      font-size: 0.7rem;
      font-weight: 600;
      color: #1a1a18;
      margin-bottom: 6px;
      display: block;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .form-control {
      width: 100%;
      padding: 10px 14px;
      border-radius: 10px;
      border: 1px solid #ddd;
      font-size: 0.85rem;
      color: #1a1a18;
      background: white;
      font-family: inherit;
    }
    .form-control:focus {
      outline: none;
      border-color: #1a1a18;
    }
    .form-control[readonly] {
      background: #f5f5f5;
      color: #666;
    }
    textarea.form-control {
      resize: vertical;
      font-family: inherit;
    }
    
    /* Photo upload */
    .photo-upload-zone {
      border: 2px dashed #ccc;
      border-radius: 16px;
      padding: 24px;
      text-align: center;
      cursor: pointer;
      transition: all 0.15s;
      background: #f8f9fa;
      position: relative;
      margin-bottom: 20px;
    }
    .photo-upload-zone:hover { border-color: #1a1a18; background: #f0f0f0; }
    .photo-upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
    .photo-upload-icon { font-size: 1.8rem; color: #999; margin-bottom: 8px; }
    .photo-upload-zone:hover .photo-upload-icon { color: #1a1a18; }
    .photo-upload-text { font-size: 0.8rem; color: #666; }
    .photo-upload-text strong { color: #1a1a18; }
    .photo-upload-sub { font-size: 0.68rem; color: #888; margin-top: 3px; }
    .photo-preview-wrap {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      overflow: hidden;
      margin: 0 auto 8px;
      border: 3px solid #1a1a18;
      display: none;
    }
    .photo-preview-wrap img { width: 100%; height: 100%; object-fit: cover; }
    
    /* Form section divider */
    .form-section-title {
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #1a1a18;
      padding: 12px 0 8px;
      border-top: 1px solid #e0e0e0;
      margin-top: 8px;
      margin-bottom: 4px;
    }
    
    /* Mountain selector chips */
    .mountain-selector {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 6px;
    }
    .mtn-select-chip {
      padding: 6px 14px;
      border-radius: 20px;
      border: 1.5px solid #ddd;
      font-size: 0.78rem;
      font-weight: 500;
      color: #1a1a18;
      cursor: pointer;
      transition: all 0.15s;
      background: white;
      font-family: 'DM Sans', sans-serif;
    }
    .mtn-select-chip:hover { border-color: #1a1a18; background: #f5f5f5; }
    .mtn-select-chip.selected { background: #1a1a18; color: white; border-color: #1a1a18; }
    
    /* Save bar */
    .save-bar {
      background: #1a1a18;
      border-radius: 16px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 28px;
      gap: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .save-bar-text {
      font-size: 0.85rem;
      color: rgba(255,255,255,0.9);
    }
    .save-bar-text strong { color: white; }
    
    /* GCash Card */
    .gcash-card {
      background: linear-gradient(135deg, #00B4D8 0%, #0077B6 100%);
      border-radius: 16px;
      padding: 20px;
      margin: 12px 0 20px;
    }
    .gcash-field label {
      color: white;
      font-weight: 600;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .gcash-field input {
      background: rgba(255,255,255,0.95);
      border: none;
      color: #1a1a18;
      padding: 10px 14px;
      border-radius: 10px;
      width: 100%;
      font-size: 0.85rem;
    }
    .gcash-field input::placeholder { color: #999; }
    
    /* QR Upload */
    .qr-upload-zone {
      border: 2px dashed rgba(255,255,255,0.3);
      border-radius: 12px;
      padding: 16px;
      text-align: center;
      cursor: pointer;
      transition: all 0.15s;
      position: relative;
      background: rgba(255,255,255,0.1);
    }
    .qr-upload-zone:hover {
      border-color: rgba(255,255,255,0.6);
      background: rgba(255,255,255,0.2);
    }
    .qr-upload-zone input[type=file] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
    }
    .qr-preview-wrap {
      position: relative;
      display: inline-block;
      margin: 0 auto;
    }
    .qr-preview-wrap img {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 12px;
      border: 2px solid white;
    }
    .qr-remove-btn {
      position: absolute;
      top: -8px;
      right: -8px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #dc3545;
      color: white;
      border: none;
      cursor: pointer;
      font-size: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .qr-upload-icon {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      color: white;
    }
    .qr-upload-icon i { font-size: 32px; }
    .qr-upload-icon span { font-size: 0.85rem; font-weight: 600; }
    .qr-upload-icon small { font-size: 0.65rem; opacity: 0.8; }
    
    /* Toast */
    .toast {
      position: fixed;
      bottom: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: #1a1a18;
      color: white;
      padding: 12px 24px;
      border-radius: 50px;
      font-size: 13px;
      font-weight: 600;
      z-index: 999;
      opacity: 0;
      pointer-events: none;
      transition: all 0.3s ease;
      white-space: nowrap;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    
    /* Responsive */
    @media (max-width: 900px) {
      .form-row { grid-template-columns: 1fr; }
      .profile-layout-3col { gap: 16px; }
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
        <li><a href="guide-bookings.php"><i class="fas fa-shield-halved"></i> Bookings</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php" class="active"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
  <?php if (!empty($guide_data['avatar'])): ?>
    <img src="../<?= htmlspecialchars($guide_data['avatar']) ?>" class="sidebar-avatar" style="object-fit:cover;" alt="avatar">
  <?php else: ?>
    <div class="sidebar-avatar"><?= $initials ?></div>
  <?php endif; ?>
  <div class="sidebar-profile-info">
    <div class="sidebar-profile-name"><?= htmlspecialchars($guide_data['name']) ?></div>
    <div class="sidebar-profile-role"><?= htmlspecialchars($guide_data['specialization'] ?? 'Trail Guide') ?></div>
  </div>
  <a href="../login-and-signup/login.php" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;text-decoration:none;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </a>
</div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">My Profile</div>
      <div class="topbar-right">
        <button class="topbar-icon-btn" onclick="showToast('Logging out…')"><i class="fas fa-right-from-bracket"></i></button>
      </div>
    </div>

    <div class="guide-content">
      <div class="profile-layout-3col">

        <!-- COLUMN 1: PROFILE CARD -->
        <div>
          <div class="profile-card">
            <div class="profile-card-top">
              <div class="profile-avatar-wrap">
                <div class="profile-avatar-img" id="profileAvatar">
                  <?php if ($guide_data['avatar']): ?>
                    <img id="profileAvatarImg" src="../<?= htmlspecialchars($guide_data['avatar']) ?>" alt="" style="display:block;">
                    <span id="profileAvatarInitials" style="display:none;"><?= $initials ?></span>
                  <?php else: ?>
                    <img id="profileAvatarImg" src="" alt="" style="display:none;">
                    <span id="profileAvatarInitials"><?= $initials ?></span>
                  <?php endif; ?>
                </div>
                <button class="profile-avatar-edit-btn" onclick="document.getElementById('avatarFileInput').click()" title="Change photo">
                  <i class="fas fa-camera"></i>
                </button>
              </div>
              <div class="profile-name"><?= htmlspecialchars($guide_data['name']) ?></div>
              <div class="profile-name-note">NAME CANNOT BE CHANGED</div>
              <div class="profile-role"><?= htmlspecialchars($guide_data['specialization'] ?? 'Trail Guide') ?> · LAKBAY</div>
            </div>
            <div class="profile-card-body">
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-envelope"></i></div>
                <div>
                  <div class="profile-info-label">Email</div>
                  <div class="profile-info-val"><?= htmlspecialchars($guide_data['email']) ?></div>
                </div>
              </div>
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-phone"></i></div>
                <div>
                  <div class="profile-info-label">Mobile</div>
                  <div class="profile-info-val"><?= htmlspecialchars($guide_data['phone'] ?? 'Not set') ?></div>
                </div>
              </div>
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-briefcase"></i></div>
                <div>
                  <div class="profile-info-label">Experience</div>
                  <div class="profile-info-val"><?= (int)$guide_data['years_experience'] ?> Years</div>
                </div>
              </div>
              <div class="profile-info-row" style="flex-direction:column;align-items:flex-start;gap:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="profile-info-icon"><i class="fas fa-mountain"></i></div>
                  <div>
                    <div class="profile-info-label">Assigned Mountains</div>
                  </div>
                </div>
                <div class="mountain-chips" id="profileMtnChips">
                  <?php foreach ($assigned_mountains as $m): ?>
                    <div class="mountain-chip"><?= htmlspecialchars($m['name']) ?></div>
                  <?php endforeach; ?>
                  <?php if (empty($assigned_mountains)): ?>
                    <span style="font-size:0.72rem;color:var(--ink-4);">None assigned</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- COLUMN 2: EDIT PROFILE FORM -->
        <div class="edit-form-card">
          <div class="edit-form-title"><i class="fas fa-pen-to-square"></i> Edit Profile</div>

          <!-- PHOTO -->
          <div class="photo-upload-zone" id="photoZone">
            <input type="file" id="avatarFileInput" accept="image/*" onchange="handlePhotoUpload(this)">
            <div class="photo-preview-wrap" id="photoPreviewWrap">
              <img id="photoPreviewImg" src="" alt="">
            </div>
            <div class="photo-upload-icon" id="photoIcon"><i class="fas fa-image"></i></div>
            <div class="photo-upload-text"><strong>Click to upload</strong> profile photo</div>
            <div class="photo-upload-sub">JPG, PNG, WEBP · Max 5 MB</div>
          </div>
          <div id="photoError" style="color:var(--red);font-size:0.72rem;display:none;margin-top:-12px;margin-bottom:12px;"></div>

          <!-- BASIC INFO -->
          <div class="form-group">
            <label class="form-label">Full Name <span style="color:var(--ink-4);font-style:italic;">(read-only)</span></label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($guide_data['name']) ?>" readonly>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" id="editEmail" value="<?= htmlspecialchars($guide_data['email']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Mobile Number</label>
              <input type="tel" class="form-control" id="editPhone" value="<?= htmlspecialchars($guide_data['phone'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Specialization</label>
              <input type="text" class="form-control" id="editSpecialization" value="<?= htmlspecialchars($guide_data['specialization'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Years of Experience</label>
              <input type="number" class="form-control" id="editExperience" value="<?= (int)$guide_data['years_experience'] ?>" min="0" max="50">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Short Bio</label>
            <textarea class="form-control" id="editBio" rows="3" maxlength="500"><?= htmlspecialchars($guide_data['bio'] ?? '') ?></textarea>
          </div>

          <!-- GCASH SECTION -->
          <div class="form-section-title"><i class="fas fa-mobile-alt" style="margin-right:6px;"></i>GCash Payment Info</div>
          <div class="gcash-card">
            <div class="gcash-field">
              <label class="form-label">GCash Account Name</label>
              <input type="text" class="form-control" id="gcashName" value="<?= htmlspecialchars($guide_data['gcash_name'] ?? '') ?>">
            </div>
            <div class="gcash-field" style="margin-top:12px;">
              <label class="form-label">GCash Mobile Number</label>
              <input type="tel" class="form-control" id="gcashNumber" value="<?= htmlspecialchars($guide_data['gcash_number'] ?? '') ?>">
            </div>
            <div class="gcash-field" style="margin-top:12px;">
              <label class="form-label">GCash QR Code (Optional)</label>
              <div class="qr-upload-zone" id="qrUploadZone">
                <input type="file" id="qrFileInput" accept="image/*" onchange="handleQRUpload(this)">
                <div class="qr-preview-wrap" id="qrPreviewWrap" style="<?= !empty($guide_data['gcash_qr_code']) ? 'display:block;' : 'display:none;' ?>">
                  <img id="qrPreviewImg" src="<?= !empty($guide_data['gcash_qr_code']) ? '../' . htmlspecialchars($guide_data['gcash_qr_code']) : '' ?>" alt="QR Code">
                  <button type="button" class="qr-remove-btn" onclick="removeQRCode()"><i class="fas fa-times"></i></button>
                </div>
                <div id="qrUploadIcon" style="<?= !empty($guide_data['gcash_qr_code']) ? 'display:none;' : 'display:flex;' ?>" class="qr-upload-icon">
                  <i class="fas fa-qrcode"></i>
                  <span>Upload QR Code</span>
                  <small>JPG, PNG (Max 2MB)</small>
                </div>
              </div>
            </div>
          </div>

          <!-- SAVE PROFILE BUTTON -->
          <div class="save-bar">
            <div class="save-bar-text"><strong>Save Profile Changes</strong> (Email, Phone, Bio, GCash)</div>
            <button class="btn btn-primary" style="background:white; color:#100600; border:none; padding:10px 24px; font-weight:700;" id="saveProfileBtn" onclick="saveProfile()">
              <i class="fas fa-check"></i> Save Profile
            </button>
          </div>
        </div>

        <!-- COLUMN 3: MOUNTAINS & FEES -->
        <div class="col-fees">
          <!-- MOUNTAIN SELECTION SECTION -->
          <div class="mountains-section">
            <div class="section-header-with-save">
              <div>
                <div style="font-weight: 700; margin-bottom: 4px;">Mountains I Guide</div>
                <div style="font-size: 0.7rem; color: var(--ink-4);">Select mountains you are authorized to guide</div>
              </div>
              <button class="btn-save-mountains" id="saveMountainsBtn" onclick="saveMountains()">
                <i class="fas fa-save"></i> Save Mountains
              </button>
            </div>
            <div class="mountain-selector" id="mtnSelector"></div>
            <div id="mtnSaveMessage" style="font-size:0.7rem; margin-top:10px; display:none;"></div>
          </div>

          <!-- FEE SETTINGS SECTION (only shows assigned mountains) -->
          <div class="mountains-section">
            <div class="section-header-with-save">
              <div>
                <div style="font-weight: 700; margin-bottom: 4px;">My Guide Fees</div>
                <div style="font-size: 0.7rem; color: var(--ink-4);">Set your rates per mountain (per group)</div>
              </div>
            </div>
            <div id="feeSettingsContainer">
              <?php if (empty($assigned_mountains)): ?>
                <div class="fee-card" style="text-align:center; color:var(--ink-4);">
                  <i class="fas fa-info-circle"></i> Select and save mountains first, then set your fees here.
                </div>
              <?php else: ?>
                <?php foreach ($assigned_mountains as $mountain): ?>
                  <?php 
                    $mtn_rates = $rates[$mountain['id']]['standard'] ?? null;
                    $day_fee = $mtn_rates['guide_fee_day'] ?? 800;
                    $night_fee = $mtn_rates['guide_fee_overnight'] ?? 1500;
                    $max_pax = $mtn_rates['max_pax'] ?? 5;
                    $has_saved = $mtn_rates !== null;
                  ?>
                  <div class="fee-card <?= $has_saved ? 'saved' : '' ?>" data-mountain-id="<?= $mountain['id'] ?>" data-mountain-name="<?= htmlspecialchars($mountain['name']) ?>">
                    <div class="fee-card-header">
                      <div class="fee-card-title"><?= htmlspecialchars($mountain['name']) ?></div>
                      <div class="fee-status <?= $has_saved ? 'saved' : '' ?>">
                        <?php if ($has_saved): ?>
                          <i class="fas fa-check-circle"></i> Saved
                        <?php else: ?>
                          <i class="fas fa-clock"></i> Default
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="fee-row">
                      <div class="fee-field">
                        <label>Day Hike Fee (4am-2pm)</label>
                        <input type="number" class="form-control day-fee" value="<?= $day_fee ?>" step="50">
                      </div>
                      <div class="fee-field">
                        <label>Overnight Fee (2pm-6pm)</label>
                        <input type="number" class="form-control night-fee" value="<?= $night_fee ?>" step="50">
                      </div>
                    </div>
                    <div class="fee-row">
                      <div class="fee-field">
                        <label>Max Pax per Guide</label>
                        <input type="number" class="form-control max-pax" value="<?= $max_pax ?>" min="1" max="15">
                        <div class="fee-help">Extra hikers require another guide</div>
                      </div>
                    </div>
                    <button class="save-fee-btn" onclick="saveFeeForMountain(this, <?= $mountain['id'] ?>)">
                      <i class="fas fa-save"></i> Save Fees for <?= htmlspecialchars($mountain['name']) ?>
                    </button>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
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
      <a href="guide-bookings.php" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Bookings</span></a>
      <a href="guide-profile.php" class="bnav-item active"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<div class="toast" id="toast"></div>

<script>
const allMountains = <?= json_encode($all_mountains) ?>;
let selectedMtns = new Set(<?= json_encode($assigned_mtn_ids) ?>);

function renderMtnSelector() {
  const el = document.getElementById('mtnSelector');
  if (!el) return;
  el.innerHTML = '';
  allMountains.forEach(m => {
    const btn = document.createElement('button');
    btn.className = 'mtn-select-chip' + (selectedMtns.has(Number(m.id)) ? ' selected' : '');
    btn.textContent = m.name;
    btn.type = 'button';
    btn.addEventListener('click', () => {
      const mid = Number(m.id);
      if (selectedMtns.has(mid)) selectedMtns.delete(mid);
      else selectedMtns.add(mid);
      renderMtnSelector();
    });
    el.appendChild(btn);
  });
}

function renderProfileChips() {
  const el = document.getElementById('profileMtnChips');
  if (!el) return;
  el.innerHTML = '';
  if (!selectedMtns.size) {
    el.innerHTML = '<span style="font-size:0.72rem;color:var(--ink-4);">None assigned</span>';
    return;
  }
  selectedMtns.forEach(mid => {
    const m = allMountains.find(x => Number(x.id) === Number(mid));
    if (!m) return;
    const chip = document.createElement('div');
    chip.className = 'mountain-chip';
    chip.innerHTML = '<i class="fas fa-mountain" style="font-size:0.6rem;"></i>' + m.name;
    el.appendChild(chip);
  });
}

// Save Mountains (AJAX, then refresh fee section)
async function saveMountains() {
  const btn = document.getElementById('saveMountainsBtn');
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  
  const formData = new FormData();
  formData.append('action', 'update_mountains');
  formData.append('mountains', Array.from(selectedMtns).join(','));
  
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    
    if (data.success) {
      showToast('✅ Mountains updated!');
      // Reload the page to refresh fee cards
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast('❌ ' + (data.message || 'Error saving mountains'));
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  } catch (err) {
    showToast('❌ Network error');
    btn.disabled = false;
    btn.innerHTML = originalHtml;
  }
}

// Save Profile (basic info + GCash)
async function saveProfile() {
  const email = document.getElementById('editEmail').value.trim();
  const phone = document.getElementById('editPhone').value.trim();
  const specialization = document.getElementById('editSpecialization').value.trim();
  const experience = document.getElementById('editExperience').value;
  const bio = document.getElementById('editBio').value.trim();
  const gcashName = document.getElementById('gcashName').value.trim();
  const gcashNumber = document.getElementById('gcashNumber').value.trim();
  const avatarInput = document.getElementById('avatarFileInput');
  const qrInput = document.getElementById('qrFileInput');

  if (!email || !email.includes('@')) { showToast('Please enter a valid email.'); return; }
  if (!phone) { showToast('Please enter your mobile number.'); return; }

  const btn = document.getElementById('saveProfileBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const formData = new FormData();
  formData.append('action', 'update_profile');
  formData.append('email', email);
  formData.append('phone', phone);
  formData.append('specialization', specialization);
  formData.append('years_experience', experience);
  formData.append('bio', bio);
  formData.append('gcash_name', gcashName);
  formData.append('gcash_number', gcashNumber);
  
  if (avatarInput.files[0]) formData.append('avatar', avatarInput.files[0]);
  if (qrInput.files[0]) formData.append('gcash_qr', qrInput.files[0]);

  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    
    if (data.success) {
      showToast('✅ Profile updated successfully!');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('❌ ' + (data.message || 'Error saving profile'));
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Save Profile';
    }
  } catch (err) {
    showToast('❌ Network error. Please try again.');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Profile';
  }
}

// Save fees for a specific mountain
async function saveFeeForMountain(button, mountainId) {
  const card = button.closest('.fee-card');
  const dayFee = card.querySelector('.day-fee').value;
  const nightFee = card.querySelector('.night-fee').value;
  const maxPax = card.querySelector('.max-pax').value;
  
  button.disabled = true;
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  
  const formData = new FormData();
  formData.append('action', 'update_fees');
  formData.append('mountain_id', mountainId);
  formData.append('day_fee', dayFee);
  formData.append('night_fee', nightFee);
  formData.append('max_pax', maxPax);
  
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    
    if (data.success) {
      card.classList.add('saved');
      const statusDiv = card.querySelector('.fee-status');
      statusDiv.classList.add('saved');
      statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Saved';
      showToast('✅ Fees saved for ' + card.dataset.mountainName);
    } else {
      showToast('❌ ' + (data.message || 'Error saving fees'));
    }
  } catch (err) {
    showToast('❌ Network error');
  } finally {
    button.disabled = false;
    button.innerHTML = '<i class="fas fa-save"></i> Save Fees for ' + card.dataset.mountainName;
  }
}

// Photo handlers
function handlePhotoUpload(input) {
  const file = input.files[0];
  const errEl = document.getElementById('photoError');
  errEl.style.display = 'none';
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    errEl.textContent = `File too large (${(file.size/1024/1024).toFixed(1)} MB). Max is 5 MB.`;
    errEl.style.display = 'block';
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('photoPreviewWrap');
    document.getElementById('photoPreviewImg').src = e.target.result;
    preview.style.display = 'block';
    document.getElementById('photoIcon').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function handleQRUpload(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    showToast('QR code image must be less than 2MB');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('qrPreviewImg').src = e.target.result;
    document.getElementById('qrPreviewWrap').style.display = 'block';
    document.getElementById('qrUploadIcon').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function removeQRCode() {
  document.getElementById('qrFileInput').value = '';
  document.getElementById('qrPreviewWrap').style.display = 'none';
  document.getElementById('qrUploadIcon').style.display = 'flex';
  document.getElementById('qrPreviewImg').src = '';
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

// Initialize
renderMtnSelector();
renderProfileChips();
</script>
</body>
</html>