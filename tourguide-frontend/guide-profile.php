<?php
session_start();
require_once '../config/db.php';

// ── AUTH: require guide login ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── Fetch user and guide details ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.name, u.email, u.phone, u.avatar,
           g.id as guide_id, g.specialization, g.years_experience, g.bio
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

// ── Fetch all mountains for selector ─────────────────────────────────────
$stmt = $pdo->query("SELECT id, name FROM mountains ORDER BY name ASC");
$all_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Avatar initials
$name_parts = explode(' ', $guide_data['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $specialization = $_POST['specialization'] ?? '';
        $years_experience = (int)($_POST['years_experience'] ?? 0);
        $bio = $_POST['bio'] ?? '';
        $mtn_ids = isset($_POST['mountains']) ? explode(',', $_POST['mountains']) : [];
        
        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$email, $phone, $user_id]);
        
        // Update guides table
        $stmt = $pdo->prepare("UPDATE guides SET specialization = ?, years_experience = ?, bio = ? WHERE user_id = ?");
        $stmt->execute([$specialization, $years_experience, $bio, $user_id]);
        
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
        
        // Update mountains
        $pdo->prepare("DELETE FROM guide_mountains WHERE guide_id = ?")->execute([$guide_id]);
        if (!empty($mtn_ids)) {
            $stmt = $pdo->prepare("INSERT INTO guide_mountains (guide_id, mountain_id) VALUES (?, ?)");
            foreach ($mtn_ids as $mid) {
                if (!empty($mid)) $stmt->execute([$guide_id, $mid]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
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
    :root {
      --forest: #1a1a18; /* Dark gray/ink */
      --forest-lt: #f0ede8; /* Stone/light gray */
    }

    /* ── PROFILE LAYOUT ── */
    .profile-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 24px;
      max-width: 900px;
    }

    /* ── PROFILE CARD ── */
    .profile-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: var(--r-xl);
      overflow: hidden;
    }
    .profile-card-top {
      background: var(--forest);
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
      width: 88px; height: 88px;
      border-radius: 50%;
      border: 4px solid rgba(255,255,255,0.3);
      background: var(--forest-lt);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      color: var(--forest);
      overflow: hidden;
    }
    .profile-avatar-img img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .profile-avatar-edit-btn {
      position: absolute;
      bottom: 2px; right: 2px;
      width: 28px; height: 28px;
      border-radius: 50%;
      background: white;
      border: none;
      display: flex; align-items: center; justify-content: center;
      color: var(--forest);
      font-size: 0.7rem;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      transition: all 0.15s;
    }
    .profile-avatar-edit-btn:hover { background: var(--forest); color: white; }
    .profile-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.2rem;
      font-weight: 700;
      color: white;
      margin-bottom: 4px;
    }
    .profile-name-note {
      font-size: 0.65rem;
      color: rgba(255,255,255,0.4);
      font-family: 'DM Mono', monospace;
      letter-spacing: 0.5px;
    }
    .profile-role {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.65);
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
      border-radius: var(--r-md);
      background: var(--stone);
    }
    .profile-info-icon {
      width: 28px; height: 28px;
      border-radius: var(--r-sm);
      background: var(--forest-lt);
      color: var(--forest);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem;
      flex-shrink: 0;
    }
    .profile-info-label { font-size: 0.65rem; color: var(--ink-4); margin-bottom: 1px; }
    .profile-info-val { font-size: 0.8rem; font-weight: 600; color: var(--ink); }

    /* mountain chips */
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
      background: var(--forest-lt);
      border: 1px solid rgba(27,58,45,0.15);
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--forest);
    }
    .mountain-chip .chip-remove {
      background: none;
      border: none;
      color: var(--ink-4);
      cursor: pointer;
      font-size: 0.65rem;
      padding: 0 0 0 2px;
      transition: color 0.12s;
    }
    .mountain-chip .chip-remove:hover { color: var(--red); }

    /* ── EDIT FORM ── */
    .edit-form-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: var(--r-xl);
      padding: 24px;
    }
    .edit-form-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.05rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .edit-form-title i { color: var(--forest); font-size: 0.9rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    /* photo upload */
    .photo-upload-zone {
      border: 2px dashed var(--stone-3);
      border-radius: var(--r-lg);
      padding: 24px;
      text-align: center;
      cursor: pointer;
      transition: all 0.15s;
      background: var(--stone);
      position: relative;
      margin-bottom: 20px;
    }
    .photo-upload-zone:hover { border-color: var(--forest); background: var(--forest-lt); }
    .photo-upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
    .photo-upload-icon { font-size: 1.8rem; color: var(--stone-3); margin-bottom: 8px; }
    .photo-upload-zone:hover .photo-upload-icon { color: var(--forest); }
    .photo-upload-text { font-size: 0.8rem; color: var(--ink-3); }
    .photo-upload-text strong { color: var(--ink); }
    .photo-upload-sub { font-size: 0.68rem; color: var(--ink-4); margin-top: 3px; }
    .photo-preview-wrap {
      width: 80px; height: 80px;
      border-radius: 50%;
      overflow: hidden;
      margin: 0 auto 8px;
      border: 3px solid var(--forest);
      display: none;
    }
    .photo-preview-wrap img { width: 100%; height: 100%; object-fit: cover; }

    /* section divider */
    .form-section-title {
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--ink-4);
      padding: 12px 0 8px;
      border-top: 1px solid var(--line);
      margin-top: 4px;
      margin-bottom: 2px;
    }

    /* mountain selector */
    .mountain-selector {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 6px;
    }
    .mtn-select-chip {
      padding: 6px 14px;
      border-radius: 20px;
      border: 1.5px solid var(--line);
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--ink-3);
      cursor: pointer;
      transition: all 0.15s;
      background: white;
      font-family: 'DM Sans', sans-serif;
    }
    .mtn-select-chip:hover { border-color: var(--ink); color: var(--ink); }
    .mtn-select-chip.selected { background: #3A3A35; color: white; border-color: #3A3A35; }

    /* save bar */
    .save-bar {
      background: #100600; /* Solid dark background for readability */
      border-radius: var(--r-lg);
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

    @media (max-width: 900px) {
      .profile-layout { grid-template-columns: 1fr; }
      .form-row { grid-template-columns: 1fr; }
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
      <div class="sidebar-avatar"><?= $initials ?></div>
      <div class="sidebar-profile-info"><div class="sidebar-profile-name"><?= htmlspecialchars($guide_data['name']) ?></div><div class="sidebar-profile-role"><?= htmlspecialchars($guide_data['specialization'] ?? 'Trail Guide') ?></div></div>
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
      <div class="profile-layout">

        <!-- LEFT: PROFILE CARD -->
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
              <div class="profile-name" id="profileNameDisplay"><?= htmlspecialchars($guide_data['name']) ?></div>
              <div class="profile-name-note">NAME CANNOT BE CHANGED</div>
              <div class="profile-role"><?= htmlspecialchars($guide_data['specialization'] ?? 'Trail Guide') ?> · LAKBAY</div>
            </div>
            <div class="profile-card-body">
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-envelope"></i></div>
                <div>
                  <div class="profile-info-label">Email</div>
                  <div class="profile-info-val" id="pi-email"><?= htmlspecialchars($guide_data['email']) ?></div>
                </div>
              </div>
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-phone"></i></div>
                <div>
                  <div class="profile-info-label">Mobile</div>
                  <div class="profile-info-val" id="pi-phone"><?= htmlspecialchars($guide_data['phone'] ?? 'Not set') ?></div>
                </div>
              </div>
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-briefcase"></i></div>
                <div>
                  <div class="profile-info-label">Experience</div>
                  <div class="profile-info-val" id="pi-experience"><?= (int)$guide_data['years_experience'] ?> Years</div>
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
                    <div class="mountain-chip"><i class="fas fa-mountain" style="font-size:0.6rem;"></i><?= htmlspecialchars($m['name']) ?></div>
                  <?php endforeach; ?>
                  <?php if (empty($assigned_mountains)): ?>
                    <span style="font-size:0.72rem;color:var(--ink-4);">None assigned</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: EDIT FORM -->
        <div>
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

            <!-- BASIC INFO (name = readonly) -->
            <div class="form-group">
              <label class="form-label">Full Name <span style="color:var(--ink-4);font-style:italic;text-transform:none;letter-spacing:0;">(read-only)</span></label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($guide_data['name']) ?>" readonly>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" id="editEmail" value="<?= htmlspecialchars($guide_data['email']) ?>" placeholder="email@example.com">
              </div>
              <div class="form-group">
                <label class="form-label">Mobile Number</label>
                <input type="tel" class="form-control" id="editPhone" value="<?= htmlspecialchars($guide_data['phone'] ?? '') ?>" placeholder="+63 9XX XXX XXXX">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Specialization</label>
                <input type="text" class="form-control" id="editSpecialization" value="<?= htmlspecialchars($guide_data['specialization'] ?? '') ?>" placeholder="e.g. Mountain Trekking">
              </div>
              <div class="form-group">
                <label class="form-label">Years of Experience</label>
                <input type="number" class="form-control" id="editExperience" value="<?= (int)$guide_data['years_experience'] ?>" min="0" max="50" placeholder="Years">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Short Bio</label>
              <textarea class="form-control" id="editBio" rows="3" placeholder="Tell us about your experience..." maxlength="500"><?= htmlspecialchars($guide_data['bio'] ?? '') ?></textarea>
            </div>

            <!-- MOUNTAINS -->
            <div class="form-section-title"><i class="fas fa-mountain" style="margin-right:6px;color:var(--forest);"></i>Mountains I Guide</div>
            <p style="font-size:0.75rem;color:var(--ink-3);margin-bottom:10px;">Select the mountains you are authorized and willing to guide on.</p>
            <div class="mountain-selector" id="mtnSelector"></div>

            <!-- SAVE BAR -->
            <div class="save-bar">
              <div class="save-bar-text"><strong>Ready to save?</strong> Your changes will be updated immediately.</div>
              <button class="btn btn-primary" style="background:white; color:#100600; border:none; padding:10px 24px; font-weight:700;" id="saveBtn" onclick="saveProfile()">
                <i class="fas fa-check"></i> Save Changes
              </button>
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
  el.innerHTML = '';
  allMountains.forEach(m => {
    const btn = document.createElement('button');
    btn.className = 'mtn-select-chip' + (selectedMtns.has(String(m.id)) || selectedMtns.has(Number(m.id)) ? ' selected' : '');
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
    chip.innerHTML = `<i class="fas fa-mountain" style="font-size:0.6rem;"></i>${m.name}`;
    el.appendChild(chip);
  });
}

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
    // show in upload zone
    const preview = document.getElementById('photoPreviewWrap');
    document.getElementById('photoPreviewImg').src = e.target.result;
    preview.style.display = 'block';
    document.getElementById('photoIcon').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

async function saveProfile() {
  const email = document.getElementById('editEmail').value.trim();
  const phone = document.getElementById('editPhone').value.trim();
  const specialization = document.getElementById('editSpecialization').value.trim();
  const experience = document.getElementById('editExperience').value;
  const bio = document.getElementById('editBio').value.trim();
  const avatarInput = document.getElementById('avatarFileInput');

  // Basic validation
  if (!email || !email.includes('@')) { showToast('Please enter a valid email.'); return; }
  if (!phone) { showToast('Please enter your mobile number.'); return; }

  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const formData = new FormData();
  formData.append('email', email);
  formData.append('phone', phone);
  formData.append('specialization', specialization);
  formData.append('years_experience', experience);
  formData.append('bio', bio);
  formData.append('mountains', Array.from(selectedMtns).join(','));
  
  if (avatarInput.files[0]) {
    formData.append('avatar', avatarInput.files[0]);
  }

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
      btn.innerHTML = '<i class="fas fa-check"></i> Save Changes';
    }
  } catch (err) {
    showToast('❌ Network error. Please try again.');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Changes';
  }
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

renderMtnSelector();
renderProfileChips();
</script>
</body>
</html>
