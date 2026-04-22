<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Profile</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
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
    .mtn-select-chip:hover { border-color: var(--forest); color: var(--forest); }
    .mtn-select-chip.selected { background: var(--forest); color: white; border-color: var(--forest); }

    /* save bar */
    .save-bar {
      background: var(--forest);
      border-radius: var(--r-lg);
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 20px;
      gap: 12px;
    }
    .save-bar-text {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.75);
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
        <li><a href="guide-dashboard.html"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.html"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.html"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.html"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.html" class="active"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar">JD</div>
      <div class="sidebar-profile-info"><div class="sidebar-profile-name">John Dela Cruz</div><div class="sidebar-profile-role">Senior Trail Guide</div></div>
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
                  <img id="profileAvatarImg" src="" alt="">
                  <span id="profileAvatarInitials">JD</span>
                </div>
                <button class="profile-avatar-edit-btn" onclick="document.getElementById('avatarFileInput').click()" title="Change photo">
                  <i class="fas fa-camera"></i>
                </button>
              </div>
              <div class="profile-name" id="profileNameDisplay">John Dela Cruz</div>
              <div class="profile-name-note">NAME CANNOT BE CHANGED</div>
              <div class="profile-role">Senior Trail Guide · LAKBAY</div>
            </div>
            <div class="profile-card-body">
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-envelope"></i></div>
                <div>
                  <div class="profile-info-label">Email</div>
                  <div class="profile-info-val" id="pi-email">john.delacruz@lakbay.ph</div>
                </div>
              </div>
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-phone"></i></div>
                <div>
                  <div class="profile-info-label">Mobile</div>
                  <div class="profile-info-val" id="pi-phone">+63 912 345 6789</div>
                </div>
              </div>
              <div class="profile-info-row">
                <div class="profile-info-icon"><i class="fas fa-cake-candles"></i></div>
                <div>
                  <div class="profile-info-label">Age</div>
                  <div class="profile-info-val" id="pi-age">32</div>
                </div>
              </div>
              <div class="profile-info-row" style="flex-direction:column;align-items:flex-start;gap:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="profile-info-icon"><i class="fas fa-mountain"></i></div>
                  <div>
                    <div class="profile-info-label">Assigned Mountains</div>
                  </div>
                </div>
                <div class="mountain-chips" id="profileMtnChips"></div>
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
              <input type="text" class="form-control" value="John Dela Cruz" readonly>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" id="editEmail" value="john.delacruz@lakbay.ph" placeholder="email@example.com">
              </div>
              <div class="form-group">
                <label class="form-label">Mobile Number</label>
                <input type="tel" class="form-control" id="editPhone" value="+63 912 345 6789" placeholder="+63 9XX XXX XXXX">
              </div>
            </div>

            <div class="form-group" style="max-width:160px;">
              <label class="form-label">Age</label>
              <input type="number" class="form-control" id="editAge" value="32" min="18" max="70" placeholder="Age">
            </div>

            <!-- MOUNTAINS -->
            <div class="form-section-title"><i class="fas fa-mountain" style="margin-right:6px;color:var(--forest);"></i>Mountains I Guide</div>
            <p style="font-size:0.75rem;color:var(--ink-3);margin-bottom:10px;">Select the mountains you are authorized and willing to guide on.</p>
            <div class="mountain-selector" id="mtnSelector"></div>

            <!-- SAVE BAR -->
            <div class="save-bar">
              <div class="save-bar-text"><strong>Ready to save?</strong> Your changes will be updated immediately.</div>
              <button class="btn btn-ghost" style="background:rgba(255,255,255,0.15);color:white;" onclick="saveProfile()">
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
      <a href="guide-dashboard.html" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.html" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.html" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-safety.html" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
      <a href="guide-profile.html" class="bnav-item active"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<div class="toast" id="toast"></div>

<script>
const allMountains = ['Mt. Batulao','Mt. Talamitam','Mt. Lantik','Mt. Apayang','Mt. Makiling','Mt. Pulag','Mt. Apo'];
let selectedMtns = new Set(['Mt. Batulao','Mt. Talamitam']);

function renderMtnSelector() {
  const el = document.getElementById('mtnSelector');
  el.innerHTML = '';
  allMountains.forEach(m => {
    const btn = document.createElement('button');
    btn.className = 'mtn-select-chip' + (selectedMtns.has(m) ? ' selected' : '');
    btn.textContent = m;
    btn.type = 'button';
    btn.addEventListener('click', () => {
      if (selectedMtns.has(m)) selectedMtns.delete(m);
      else selectedMtns.add(m);
      renderMtnSelector();
      renderProfileChips();
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
  selectedMtns.forEach(m => {
    const chip = document.createElement('div');
    chip.className = 'mountain-chip';
    chip.innerHTML = `<i class="fas fa-mountain" style="font-size:0.6rem;"></i>${m}`;
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
    // update sidebar avatar
    const avatarImg = document.getElementById('profileAvatarImg');
    avatarImg.src = e.target.result;
    avatarImg.style.display = 'block';
    document.getElementById('profileAvatarInitials').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function saveProfile() {
  const email = document.getElementById('editEmail').value.trim();
  const phone = document.getElementById('editPhone').value.trim();
  const age   = document.getElementById('editAge').value;

  // Basic validation
  if (!email || !email.includes('@')) { showToast('Please enter a valid email.'); return; }
  if (!phone) { showToast('Please enter your mobile number.'); return; }
  if (!age || age < 18 || age > 70) { showToast('Please enter a valid age (18–70).'); return; }

  // Update display card
  document.getElementById('pi-email').textContent = email;
  document.getElementById('pi-phone').textContent = phone;
  document.getElementById('pi-age').textContent = age;
  renderProfileChips();

  showToast('✅ Profile updated successfully!');
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
