<?php
// profile.php - Lakbay User Profile
// Features: Profile picture upload, mobile back button, mountain cards for saved mountains, logout to landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY — My Profile</title>
  <link rel="stylesheet" href="shared.css">
  <style>
    /* Override root colors for #100600 theme */
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
      --gold: #c9a84c;
      --glass: rgba(16,6,0,0.08);
      --glass-border: rgba(16,6,0,0.1);
      --glass-dark: rgba(16,6,0,0.5);
      --radius: 20px;
      --radius-sm: 12px;
      --shadow: 0 8px 32px rgba(16,6,0,0.12);
      --shadow-lg: 0 20px 60px rgba(16,6,0,0.2);
      --nav-h: 74px;
    }
    @media(max-width:768px){ :root { --nav-h: 0px; } }

    /* Mobile Back Button */
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

    /* Profile Page Layout - Sidebar + Main */
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

    /* Sidebar Navigation */
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

    /* Main Content Area */
    .profile-main {
      flex: 1;
      min-width: 0;
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

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: rgba(16,6,0,0.03);
      border-radius: var(--radius-sm);
      padding: 20px;
      text-align: center;
      transition: 0.2s;
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

    /* Info rows - one feature per row on mobile */
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

    /* Mountain Cards for Saved Mountains */
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
      cursor: pointer;
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
      margin-bottom: 12px;
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
    }
    .saved-mtn-remove:hover { background: var(--danger); }
    .saved-mtn-remove svg { width: 14px; height: 14px; stroke: white; }

    /* History list items */
    .history-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .history-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 16px;
      background: rgba(16,6,0,0.02);
      border-radius: var(--radius-sm);
      transition: 0.2s;
    }
    .history-item:hover { background: rgba(16,6,0,0.05); }
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

    /* Settings rows */
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

    /* Toggle Switch */
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

    /* Buttons */
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

    /* Modal */
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
    .confirm-buttons {
      display: flex;
      gap: 12px;
      margin-top: 24px;
    }
    .confirm-buttons .btn { flex: 1; }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .mobile-back-bar { display: flex; }
      .desktop-nav { display: none; }
      .profile-layout { 
        flex-direction: column; 
        margin-top: 60px; 
        padding: 0 16px;
        gap: 20px;
      }
      .profile-sidebar { 
        width: 100%; 
        position: static; 
        top: auto;
        padding: 20px 0;
      }
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
      .sidebar-logout { 
        margin-top: 0; 
        padding-top: 0; 
        border-top: none;
      }
      .stats-grid { 
        grid-template-columns: repeat(3, 1fr); 
        gap: 10px;
      }
      .stat-card { padding: 12px; }
      .stat-number { font-size: 24px; }
      .profile-section { padding: 20px; }
      .section-header { flex-direction: column; gap: 12px; align-items: flex-start; }
      .section-header h2 { font-size: 18px; }
      /* One feature per row for personal info */
      .info-row { flex-direction: column; gap: 4px; }
      .info-label { width: 100%; font-size: 11px; }
      .info-value { font-size: 14px; }
      /* Saved mountains grid */
      .saved-mtn-grid { grid-template-columns: 1fr; }
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
  <a href="hikerProfile.php" class="user-btn active">J</a>
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
        <div class="avatar-circle" id="avatarDisplay">
          <span id="avatarInitial">J</span>
        </div>
        <div class="camera-icon" onclick="document.getElementById('profilePicInput').click()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <input type="file" id="profilePicInput" accept="image/*" style="display:none" onchange="uploadProfilePicture(this)">
      </div>
      <div class="sidebar-name" id="sidebarName">Jamie Rivera</div>
      <div class="sidebar-email" id="sidebarEmail">jamie@lakbay.ph</div>
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
      <div class="sidebar-link" data-section="notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
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
      <div class="stat-card"><div class="stat-number" id="totalHikesStat">0</div><div class="stat-label">Total Hikes</div></div>
      <div class="stat-card"><div class="stat-number" id="totalSavedStat">0</div><div class="stat-label">Saved Peaks</div></div>
      <div class="stat-card"><div class="stat-number" id="badgeCountStat">4</div><div class="stat-label">Badges Earned</div></div>
    </div>

    <!-- Section 1: Personal Information -->
    <div id="section-personal" class="profile-section active-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Personal Information</h2>
        <button class="btn-outline-small" onclick="openEditModal()">Edit Profile</button>
      </div>
      <div id="personalInfoDisplay"></div>
    </div>

    <!-- Section 2: Hiking History -->
    <div id="section-history" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>Hiking History</h2>
        <button class="btn-outline-small" onclick="openAddHistoryModal()">+ Record Hike</button>
      </div>
      <div id="historyList" class="history-list"></div>
    </div>

    <!-- Section 3: Saved Mountains (with mountain cards) -->
    <div id="section-saved" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Saved Mountains</h2>
        <button class="btn-outline-small" onclick="openAddSavedModal()">+ Add from Explore</button>
      </div>
      <div id="savedList" class="saved-mtn-grid"></div>
    </div>

    <!-- Section 4: Location Permission -->
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

    <!-- Section 5: Notifications & Alerts -->
    <div id="section-notifications" class="profile-section">
      <div class="section-header">
        <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Notifications & Alerts</h2>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Weather alerts for saved mountains</h4><p>Receive push when weather conditions change.</p></div>
        <label class="toggle-switch"><input type="checkbox" id="weatherAlertToggle"><span class="slider"></span></label>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Crowd level alerts</h4><p>Notify me when popular mountains become highly crowded.</p></div>
        <label class="toggle-switch"><input type="checkbox" id="crowdAlertToggle"><span class="slider"></span></label>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Booking reminders</h4><p>Remind me 24h before a guided hike.</p></div>
        <label class="toggle-switch"><input type="checkbox" id="bookingReminderToggle"><span class="slider"></span></label>
      </div>
      <div class="setting-row">
        <div class="setting-info"><h4>Newsletter & trail tips</h4><p>Weekly mountain inspiration and safety tips.</p></div>
        <label class="toggle-switch"><input type="checkbox" id="newsletterToggle"><span class="slider"></span></label>
      </div>
    </div>
  </main>
</div>

<!-- MODALS -->
<div class="modal-bg" id="editProfileModal"><div class="modal"><div class="modal-hdr"><div class="modal-title">Edit Profile</div><button class="modal-close" onclick="closeModal('editProfileModal')">✕</button></div><div class="modal-body"><div class="inp-label">Full Name</div><input type="text" id="editName" class="inp"><div class="inp-label" style="margin-top:16px;">Email</div><input type="email" id="editEmail" class="inp"><div class="inp-label" style="margin-top:16px;">Phone</div><input type="text" id="editPhone" class="inp"><div class="inp-label" style="margin-top:16px;">Home Region</div><input type="text" id="editRegion" class="inp"><button class="btn btn-primary btn-full" style="margin-top:24px;" onclick="savePersonalInfo()">Save Changes</button></div></div></div>

<div class="modal-bg" id="addHistoryModal"><div class="modal"><div class="modal-hdr"><div class="modal-title">Record a Hike</div><button class="modal-close" onclick="closeModal('addHistoryModal')">✕</button></div><div class="modal-body"><div class="inp-label">Mountain</div><select id="historyMtnSelect" class="inp"></select><div class="inp-label" style="margin-top:12px;">Date hiked</div><input type="date" id="historyDate" class="inp"><div class="inp-label" style="margin-top:12px;">Notes</div><input type="text" id="historyNotes" class="inp" placeholder="Great experience!"><button class="btn btn-primary btn-full" style="margin-top:24px;" onclick="addHikeEntry()">Add to History</button></div></div></div>

<div class="modal-bg" id="addSavedModal"><div class="modal"><div class="modal-hdr"><div class="modal-title">Save a Mountain</div><button class="modal-close" onclick="closeModal('addSavedModal')">✕</button></div><div class="modal-body"><div class="mtn-option-list" id="savedMtnList"></div></div></div></div>

<div class="modal-bg" id="logoutModal"><div class="modal" style="max-width: 380px;"><div class="modal-hdr"><div class="modal-title">Confirm Logout</div><button class="modal-close" onclick="closeModal('logoutModal')">✕</button></div><div class="modal-body"><p style="margin-bottom: 8px;">Are you sure you want to logout?</p><p style="font-size: 12px; color: var(--stone);">Your profile data will remain saved locally.</p><div class="confirm-buttons"><button class="btn btn-outline" onclick="closeModal('logoutModal')">Cancel</button><button class="btn btn-primary" onclick="performLogout()">Logout</button></div></div></div></div>

<div class="toast" id="toast"></div>

<script>
  // Data models
  let userProfile = { fullName: "Jamie Rivera", email: "jamie@lakbay.ph", phone: "+63 912 345 6789", homeRegion: "Cavite, Philippines", profilePic: null };
  let hikingHistory = [];
  let savedMountains = [];
  let preferences = { locationEnabled: false, weatherAlerts: true, crowdAlerts: false, bookingReminders: true, newsletter: true };

  const mountainsDB = [
    { id:1, name:"Mt. Batulao", location:"Nasugbu, Batangas", elevation:"811m", time:"4-5 hrs", difficulty:"moderate", rating:4.8, image:"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=70" },
    { id:2, name:"Mt. Talamitam", location:"Nasugbu, Batangas", elevation:"630m", time:"3-4 hrs", difficulty:"easy", rating:4.6, image:"https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=400&q=70" },
    { id:3, name:"Mt. Apayang", location:"Batangas", elevation:"980m", time:"6-7 hrs", difficulty:"hard", rating:4.9, image:"https://images.unsplash.com/photo-1519681393784-d120267933ba?w=400&q=70" },
    { id:4, name:"Mt. Lantik", location:"Alfonso, Cavite", elevation:"710m", time:"4-5 hrs", difficulty:"moderate", rating:4.7, image:"https://images.unsplash.com/photo-1501854140801-50d01698950b?w=400&q=70" }
  ];

  function loadData() {
    if(localStorage.getItem('lakbay_profile')) userProfile = JSON.parse(localStorage.getItem('lakbay_profile'));
    if(localStorage.getItem('lakbay_history')) hikingHistory = JSON.parse(localStorage.getItem('lakbay_history'));
    if(localStorage.getItem('lakbay_saved')) savedMountains = JSON.parse(localStorage.getItem('lakbay_saved'));
    if(localStorage.getItem('lakbay_prefs')) preferences = JSON.parse(localStorage.getItem('lakbay_prefs'));
    document.getElementById('locationToggle').checked = preferences.locationEnabled;
    document.getElementById('weatherAlertToggle').checked = preferences.weatherAlerts;
    document.getElementById('crowdAlertToggle').checked = preferences.crowdAlerts;
    document.getElementById('bookingReminderToggle').checked = preferences.bookingReminders;
    document.getElementById('newsletterToggle').checked = preferences.newsletter;
    updateLocationMsg();
    renderAll();
    loadProfilePicture();
  }

  function saveAll() {
    localStorage.setItem('lakbay_profile', JSON.stringify(userProfile));
    localStorage.setItem('lakbay_history', JSON.stringify(hikingHistory));
    localStorage.setItem('lakbay_saved', JSON.stringify(savedMountains));
    localStorage.setItem('lakbay_prefs', JSON.stringify(preferences));
    renderAll();
  }

  function loadProfilePicture() {
    const savedPic = localStorage.getItem('lakbay_profilePic');
    if(savedPic) {
      const avatarDiv = document.querySelector('#avatarDisplay');
      avatarDiv.style.backgroundImage = `url(${savedPic})`;
      avatarDiv.style.backgroundSize = 'cover';
      avatarDiv.style.backgroundPosition = 'center';
      const initialSpan = avatarDiv.querySelector('#avatarInitial');
      if(initialSpan) initialSpan.style.display = 'none';
    }
  }

  function uploadProfilePicture(input) {
    if(input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const imgData = e.target.result;
        localStorage.setItem('lakbay_profilePic', imgData);
        const avatarDiv = document.querySelector('#avatarDisplay');
        avatarDiv.style.backgroundImage = `url(${imgData})`;
        avatarDiv.style.backgroundSize = 'cover';
        avatarDiv.style.backgroundPosition = 'center';
        const initialSpan = avatarDiv.querySelector('#avatarInitial');
        if(initialSpan) initialSpan.style.display = 'none';
        showToast("Profile picture updated!");
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function renderAll() {
    // Personal info
    document.getElementById('personalInfoDisplay').innerHTML = `
      <div class="info-row"><div class="info-label">Full Name</div><div class="info-value">${escapeHtml(userProfile.fullName)}</div></div>
      <div class="info-row"><div class="info-label">Email</div><div class="info-value">${escapeHtml(userProfile.email)}</div></div>
      <div class="info-row"><div class="info-label">Phone</div><div class="info-value">${escapeHtml(userProfile.phone || '—')}</div></div>
      <div class="info-row"><div class="info-label">Home Region</div><div class="info-value">${escapeHtml(userProfile.homeRegion || '—')}</div></div>
    `;
    document.getElementById('sidebarName').innerText = userProfile.fullName;
    document.getElementById('sidebarEmail').innerText = userProfile.email;
    const profileBtn = document.getElementById('profileBtn');
    if(profileBtn) profileBtn.innerText = userProfile.fullName.charAt(0).toUpperCase();
    document.getElementById('totalHikesStat').innerText = hikingHistory.length;
    document.getElementById('totalSavedStat').innerText = savedMountains.length;

    // History list
    const historyContainer = document.getElementById('historyList');
    if(!hikingHistory.length) historyContainer.innerHTML = '<div class="empty-state">No hikes recorded yet. Start your journey!</div>';
    else historyContainer.innerHTML = hikingHistory.map((h, idx) => `
      <div class="history-item">
        <div class="history-info"><h4>${escapeHtml(h.mountainName)}</h4><p>${escapeHtml(h.date)} · ${escapeHtml(h.notes || 'No notes')}</p></div>
        <button class="btn-icon" onclick="removeHistory(${idx})"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
      </div>
    `).join('');

    // Saved mountains as mountain cards
    const savedContainer = document.getElementById('savedList');
    if(!savedMountains.length) savedContainer.innerHTML = '<div class="empty-state">No saved mountains. Favorite some peaks!</div>';
    else savedContainer.innerHTML = savedMountains.map((m, idx) => `
      <div class="saved-mtn-card" onclick="viewMountain(${m.id})">
        <div class="saved-mtn-img" style="background-image: url('${m.image || 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=70'}')">
          <div class="saved-mtn-img-overlay"></div>
          <div class="saved-mtn-badge"><span class="badge badge-${m.difficulty}">${m.difficulty}</span></div>
          <div class="saved-mtn-remove" onclick="event.stopPropagation(); removeSaved(${idx})"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></div>
        </div>
        <div class="saved-mtn-body">
          <div class="saved-mtn-name">${escapeHtml(m.name)}</div>
          <div class="saved-mtn-loc"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>${escapeHtml(m.location)}</div>
          <div class="saved-mtn-stats"><span class="saved-mtn-stat">⛰ ${m.elevation}</span><span class="saved-mtn-stat">⏱ ${m.time}</span><span class="saved-mtn-stat">★ ${m.rating}</span></div>
        </div>
      </div>
    `).join('');
  }

  function viewMountain(id) {
    showToast("Opening mountain details...");
    // In a full app, this would open a modal or navigate
  }

  function removeHistory(idx) { hikingHistory.splice(idx,1); saveAll(); showToast("Hike removed"); }
  function removeSaved(idx) { savedMountains.splice(idx,1); saveAll(); showToast("Mountain removed"); }

  function addHikeEntry() {
    const mtnId = parseInt(document.getElementById('historyMtnSelect').value);
    const mtn = mountainsDB.find(m => m.id === mtnId);
    if(!mtn) return showToast("Select a mountain");
    const date = document.getElementById('historyDate').value;
    if(!date) return showToast("Select a date");
    const notes = document.getElementById('historyNotes').value.trim() || "Completed hike";
    hikingHistory.push({ mountainId: mtn.id, mountainName: mtn.name, date, notes });
    saveAll();
    closeModal('addHistoryModal');
    document.getElementById('historyDate').value = '';
    document.getElementById('historyNotes').value = '';
    showToast(`Added ${mtn.name} to history`);
  }

  function addSavedMountain(mtn) {
    if(savedMountains.some(s => s.id === mtn.id)) return showToast(`${mtn.name} already saved`);
    savedMountains.push(mtn);
    saveAll();
    closeModal('addSavedModal');
    showToast(`${mtn.name} saved to favorites`);
  }

  function openAddHistoryModal() {
    const select = document.getElementById('historyMtnSelect');
    select.innerHTML = mountainsDB.map(m => `<option value="${m.id}">${escapeHtml(m.name)} (${m.difficulty})</option>`);
    document.getElementById('addHistoryModal').classList.add('open');
  }

  function openAddSavedModal() {
    const container = document.getElementById('savedMtnList');
    container.innerHTML = mountainsDB.map(m => `
      <div class="mtn-option" onclick="addSavedMountain(${JSON.stringify(m).replace(/"/g, '&quot;')})">
        <div><div class="mtn-option-name">${escapeHtml(m.name)}</div><div class="mtn-option-loc">${escapeHtml(m.location)}</div></div>
        <span style="color:var(--gold);">+ Save</span>
      </div>
    `).join('');
    document.getElementById('addSavedModal').classList.add('open');
  }

  function openEditModal() {
    document.getElementById('editName').value = userProfile.fullName;
    document.getElementById('editEmail').value = userProfile.email;
    document.getElementById('editPhone').value = userProfile.phone || '';
    document.getElementById('editRegion').value = userProfile.homeRegion || '';
    document.getElementById('editProfileModal').classList.add('open');
  }

  function savePersonalInfo() {
    userProfile.fullName = document.getElementById('editName').value.trim() || "Explorer";
    userProfile.email = document.getElementById('editEmail').value.trim();
    userProfile.phone = document.getElementById('editPhone').value.trim();
    userProfile.homeRegion = document.getElementById('editRegion').value.trim();
    saveAll();
    closeModal('editProfileModal');
    showToast("Profile updated!");
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
  }

  function updateLocationMsg() {
    const msgDiv = document.getElementById('locationStatusMsg');
    if(preferences.locationEnabled) {
      msgDiv.innerHTML = "Location tracking is ACTIVE. You'll get trail-specific alerts & route suggestions.";
      if("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition((pos) => {
          msgDiv.innerHTML += `<br><span style="font-size:10px;">Last known: ${pos.coords.latitude.toFixed(2)}, ${pos.coords.longitude.toFixed(2)}</span>`;
        }, () => {});
      }
    } else {
      msgDiv.innerHTML = "Location sharing is OFF. Turn on to enable smart safety features and trail tracking.";
    }
  }

  // Sidebar navigation
  function initSidebarNavigation() {
    const links = document.querySelectorAll('.sidebar-link[data-section]');
    const sections = ['personal', 'history', 'saved', 'location', 'notifications'];
    
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

  // Go back to previous page
function goBack() {
  // Try to use browser history first
  if (document.referrer && document.referrer.includes(window.location.hostname)) {
    window.history.back();
  } else {
    // If no valid referrer, go to explore page
    window.location.href = "explore.php";
  }
}
  // Logout to lakbayLanding.php
function performLogout() {
  // Clear all user data from localStorage
  localStorage.removeItem('lakbay_profile');
  localStorage.removeItem('lakbay_history');
  localStorage.removeItem('lakbay_saved');
  localStorage.removeItem('lakbay_prefs');
  localStorage.removeItem('lakbay_profilePic');
  
  showToast("Logged out successfully!");
  setTimeout(() => {
    // Redirect to login page (2 levels up from hiker frontend folder)
    window.location.href = "../login and signup/login.php";
  }, 800);
}

  // Toggle listeners
  document.getElementById('locationToggle').addEventListener('change', (e) => {
    preferences.locationEnabled = e.target.checked;
    saveAll();
    updateLocationMsg();
    showToast(preferences.locationEnabled ? "Location tracking enabled" : "Location tracking disabled");
  });
  document.getElementById('weatherAlertToggle').addEventListener('change', (e) => { preferences.weatherAlerts = e.target.checked; saveAll(); showToast(e.target.checked ? "Weather alerts ON" : "Weather alerts OFF"); });
  document.getElementById('crowdAlertToggle').addEventListener('change', (e) => { preferences.crowdAlerts = e.target.checked; saveAll(); showToast(e.target.checked ? "Crowd alerts ON" : "Crowd alerts OFF"); });
  document.getElementById('bookingReminderToggle').addEventListener('change', (e) => { preferences.bookingReminders = e.target.checked; saveAll(); showToast(e.target.checked ? "Booking reminders ON" : "Booking reminders OFF"); });
  document.getElementById('newsletterToggle').addEventListener('change', (e) => { preferences.newsletter = e.target.checked; saveAll(); showToast(e.target.checked ? "Newsletter subscribed" : "Newsletter unsubscribed"); });

  document.getElementById('logoutBtn').addEventListener('click', () => {
    document.getElementById('logoutModal').classList.add('open');
  });

  function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m){if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }
  function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.innerText = msg;
    toast.classList.add('show');
    setTimeout(()=>toast.classList.remove('show'), 2500);
  }

  document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', function(e) { if(e.target === bg) bg.classList.remove('open'); });
  });

  loadData();
  initSidebarNavigation();
</script>
</body>
</html>