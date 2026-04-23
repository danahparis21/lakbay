<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName    = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Fetch guides with user information
$stmt = $pdo->prepare("
    SELECT g.*, u.name, u.email 
    FROM guides g 
    JOIN users u ON g.user_id = u.id 
    ORDER BY g.rating DESC
");
$stmt->execute();
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch mountains for the checklist
$stmt = $pdo->query("SELECT id, name FROM mountains ORDER BY name");
$mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to get guide's mountains (you'll need a guide_mountains junction table)
function getGuideMountains($pdo, $guideId) {
    $stmt = $pdo->prepare("
        SELECT m.name 
        FROM guide_mountains gm 
        JOIN mountains m ON gm.mountain_id = m.id 
        WHERE gm.guide_id = ?
    ");
    $stmt->execute([$guideId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Guides</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="shared.css">
  <style>
    /* ─── RESPONSIVE LAYOUT OVERRIDES ─── */
    
    /* Global stacked layout for mobile */
    @media (max-width: 992px) {
      .two-col { 
        display: flex; 
        flex-direction: column; 
        gap: 24px; 
      }
    }

    /* Sidebar Responsiveness */
    @media (max-width: 850px) {
      .sidebar { 
        width: 70px; 
        padding: 20px 10px; 
      }
      .logo-wordmark, .logo-sub, .nav-section-label, .sidebar-footer, .nav-item span { 
        display: none; 
      }
      .nav-item { 
        justify-content: center; 
        padding: 15px 0;
        font-size: 1.2rem;
      }
      .nav-item i { margin-right: 0; }
      .main { margin-left: 70px; }
    }

    /* Top Buttons and Header Responsiveness */
    @media (max-width: 600px) {
      .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
      }
      .section-header div { width: 100%; }
      
      /* Make buttons take equal space on mobile or shrink labels */
      .btn-responsive-text { display: none; } /* Hide text, show icon only */
      
      .btn { 
        flex: 1; 
        justify-content: center; 
        padding: 12px;
      }

      .topbar-date { display: none; } /* Hide date on very small screens */
    }

    /* Guide Row Responsiveness */
    @media (max-width: 480px) {
      .guide-row {
        padding: 12px !important;
      }
      .guide-avatar {
        width: 36px !important;
        height: 36px !important;
        font-size: 12px !important;
      }
      .guide-name { font-size: 14px; }
      .guide-meta { font-size: 11px; }
      
      /* Message Button: Icon only on mobile */
      .msg-btn-text { display: none; }
      .btn-ghost { padding: 10px; min-width: 40px; }
    }

    /* ─── MODAL STYLES (Previously defined) ─── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(10,12,18,0.55); backdrop-filter: blur(4px);
      z-index: 1000; align-items: center; justify-content: center; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #fff; border-radius: 28px;
      width: 100%; max-width: 540px; max-height: 90vh; overflow-y: auto;
      box-shadow: 0 32px 64px rgba(0,0,0,0.18);
      display: flex; flex-direction: column;
    }
    .modal-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 24px 28px 16px; border-bottom: 1px solid #EFF2F6;
      position: sticky; top: 0; background: white; z-index: 2;
    }
    .modal-title { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: #111318; }
    .modal-close {
      width: 36px; height: 36px; border-radius: 50%; border: none;
      background: #F2F4F8; cursor: pointer; display: flex; align-items: center;
      justify-content: center; color: #5B6A7E; transition: 0.15s;
    }
    .modal-body { padding: 20px 28px 28px; flex: 1; }
    .modal-footer {
      padding: 16px 28px 24px; display: flex; justify-content: flex-end; gap: 10px;
      border-top: 1px solid #EFF2F6; position: sticky; bottom: 0; background: white; z-index: 2;
    }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { font-size: 11px; font-weight: 600; letter-spacing: 0.8px; text-transform: uppercase; color: #6C7A8E; }
    .form-label .req { color: #E67E22; margin-left: 2px; }
    .form-control {
      border: 1.5px solid #E5E9EF; border-radius: 12px; padding: 10px 14px;
      font-size: 13.5px; font-family: 'Inter', sans-serif; background: #FAFBFC; outline: none; transition: 0.15s;
    }
    .mtn-checklist {
      display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
      background: #F8F9FB; padding: 16px; border-radius: 14px; border: 1.5px solid #E5E9EF;
    }
    .check-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #111318; cursor: pointer; }
    .confirm-box { background: #F8F9FB; border: 1.5px solid #E5E9EF; border-radius: 16px; padding: 18px; }
    .confirm-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #EDF0F4; font-size: 13px; }
    .step-indicator { display: flex; align-items: center; padding: 16px 28px 0; }
    .step-dot { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #B0BAC8; font-family: 'DM Mono', monospace; }
    .step-dot.active { color: #111318; }
    .step-dot span { width: 24px; height: 24px; border-radius: 50%; background: #EFF2F6; display: flex; align-items: center; justify-content: center; font-size: 11px; }
    .step-dot.active span { background: #111318; color: white; }
    .step-line { flex: 1; height: 1px; background: #E5E9EF; margin: 0 10px; }

    /* ─── TOP GUIDE CARD ─── */
    .top-guide-card {
      background: #111318; border-radius: 16px; padding: 18px 20px;
      margin: 16px 16px 8px; position: relative; overflow: hidden;
    }
    .top-guide-card::before {
      content: ''; position: absolute; right: -24px; top: -24px;
      width: 110px; height: 110px; border-radius: 50%;
      background: rgba(255,255,255,0.04);
    }
    .top-guide-label {
      font-size: 9px; letter-spacing: 1.5px; text-transform: uppercase;
      color: rgba(255,255,255,0.4); margin-bottom: 10px;
    }
    .top-guide-row { display: flex; align-items: center; gap: 14px; }
    .top-guide-avatar {
      width: 48px; height: 48px; border-radius: 50%;
      background: rgba(255,255,255,0.12); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-weight: 600; font-size: 16px; flex-shrink: 0;
    }
    .top-guide-name { font-size: 16px; font-weight: 600; color: #fff; }
    .top-guide-sub { font-size: 12px; color: rgba(255,255,255,0.45); margin-top: 2px; }
    .top-guide-rating { font-size: 13px; color: #FBBF24; margin-top: 4px; }

    /* ─── TOP GUIDE PER MOUNTAIN ─── */
    .mtn-top-section { padding: 16px 0 0; }
    .mtn-top-item {
      display: flex; align-items: center; gap: 14px;
      padding: 12px 16px; border-bottom: 1px solid #EFF2F6;
    }
    .mtn-top-item:last-child { border-bottom: none; }
    .mtn-tag {
      font-size: 10px; font-weight: 600; letter-spacing: 0.6px;
      background: #F2F4F8; color: #5B6A7E; padding: 4px 10px;
      border-radius: 20px; white-space: nowrap; min-width: 80px; text-align: center;
    }
    .mtn-top-info { flex: 1; }
    .mtn-top-name { font-size: 13px; font-weight: 600; color: #111318; }
    .mtn-top-meta { font-size: 11px; color: #5B6A7E; margin-top: 1px; }
    .mtn-top-avatar {
      width: 34px; height: 34px; border-radius: 50%; background: #111318;
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 600; flex-shrink: 0;
    }

    /* ─── BROADCAST MODAL ─── */
    .broadcast-log-item {
      padding: 14px 0; border-bottom: 1px solid #EFF2F6;
    }
    .broadcast-log-item:last-child { border-bottom: none; }
    .broadcast-log-msg { font-size: 13px; color: #111318; line-height: 1.5; }
    .broadcast-log-ts { font-size: 11px; color: #8A99AE; margin-top: 4px; font-family: 'DM Mono', monospace; }
    .broadcast-log-recipients { font-size: 11px; color: #5B6A7E; margin-top: 3px; }
    .broadcast-empty { text-align: center; color: #B0BAC8; font-size: 13px; padding: 32px 0; }

    /* ─── SEND CONFIRMATION MODAL ─── */
    .confirm-modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(10,12,18,0.55); backdrop-filter: blur(4px);
      z-index: 2000; align-items: center; justify-content: center; padding: 20px;
    }
    .confirm-modal-overlay.open { display: flex; }
    .confirm-modal-box {
      background: #fff; border-radius: 24px; width: 100%; max-width: 420px;
      box-shadow: 0 32px 64px rgba(0,0,0,0.18); padding: 28px;
    }
    .confirm-modal-icon {
      width: 48px; height: 48px; border-radius: 50%; background: #FEF3C7;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; margin-bottom: 14px;
    }
    .confirm-modal-title { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; color: #111318; margin-bottom: 6px; }
    .confirm-modal-sub { font-size: 13px; color: #5B6A7E; line-height: 1.5; margin-bottom: 8px; }
    .confirm-modal-preview {
      background: #F8F9FB; border: 1.5px solid #E5E9EF; border-radius: 12px;
      padding: 12px 14px; font-size: 13px; color: #111318; font-style: italic;
      margin-bottom: 20px; line-height: 1.5;
    }
    .confirm-modal-footer { display: flex; justify-content: flex-end; gap: 10px; }

    /* Logout Button Styles - Matching nav-item exactly */
    .logout-btn {
      display: flex;
      align-items: center;
      gap: 12px;
      width: 100%;
      padding: 10px 16px;
      margin: 8px 0;
      background: transparent;
      border: none;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 0.82rem;
      font-weight: 400;
      color: #dc2626;
      cursor: pointer;
      transition: all 0.2s;
      text-align: left;
    }

    .logout-btn i {
      width: 16px;
      font-size: 0.75rem;
      color: #dc2626;
      transition: all 0.2s;
    }

    .logout-btn:hover {
      background: #fef2f2;
      color: #b91c1c;
    }

    .logout-btn:hover i {
      color: #b91c1c;
    }

    /* Remove default form spacing */
    .sidebar-footer form {
      margin: 0;
      padding: 0;
    }
  </style>
</head>
<body data-page="guides">
<div class="app">

  <aside class="sidebar">
    <div>
      <div class="logo">
        <div class="logo-wordmark">
          <div class="logo-icon">
            <svg viewBox="0 0 28 28" fill="none">
              <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#111318" opacity="0.9"/>
              <path d="M14 16L18 8L24 22H14V16Z" fill="#111318" opacity="0.35"/>
            </svg>
          </div>
          LAKBAY
        </div>
        <div class="logo-sub">wilderness intelligence</div>
      </div>
      <div class="nav-section-label">Navigation</div>
      <ul class="nav-list">
        <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
        <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
        <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
        <li class="nav-item active" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
        <div class="nav-divider"></div>
        <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
        <li class="nav-item" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
        <li class="nav-item" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
      </ul>
    </div>
    <div>
      <form method="POST" action="/pages/modals/logout.php" style="margin:0;padding:0;display:block;">
        <button class="logout-btn" type="submit">
          <i class="fas fa-right-from-bracket"></i> 
          Log Out
        </button>
      </form>
      <div class="sidebar-footer">
        <div class="status-dot"></div> 
        SYSTEM LIVE · V3
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="page-heading"><i class="fas fa-chalkboard-user"></i> Guides</div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-user">
          <div class="avatar"><?= $adminInitial ?></div>
          <?= htmlspecialchars($adminName) ?>
          <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="section-header">
        <div class="section-title">Tour Guides</div>
        <div style="display:flex; gap:8px;">
          <button class="btn btn-primary" id="addGuideBtn">
            <i class="fas fa-user-plus"></i> <span class="btn-responsive-text">Add Guide</span>
          </button>
          <button class="btn" id="broadcastBtn">
            <i class="fas fa-bullhorn"></i> <span class="btn-responsive-text">Broadcast</span>
          </button>
        </div>
      </div>

      <div class="two-col">
        <div>
          <div class="panel">
            <div class="panel-header">
              <span class="panel-title">Active Guides</span>
              <span class="panel-badge" id="guideCount"><?= count($guides) ?> on roster</span>
            </div>
            <!-- TOP PERFORMING GUIDE CARD -->
            <div id="topGuideCardWrapper"></div>
            <div id="guidesContainer">
              <!-- Dynamic Guides Render Here -->
            </div>
          </div>

          <!-- TOP GUIDE PER MOUNTAIN -->
          <div class="panel" style="margin-top: 20px;">
            <div class="panel-header">
              <span class="panel-title">Top Guide per Mountain</span>
            </div>
            <div class="mtn-top-section" id="mtnTopContainer"></div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header"><span class="panel-title">Send Announcement</span></div>
          <textarea id="announceTxt" rows="5" class="form-control" style="width:100%; margin-bottom:12px;" placeholder="Write announcement...">New safety protocol: mandatory radio check at 7AM daily.</textarea>
          <button class="btn btn-primary" id="sendAnnounceBtn" style="width:100%"><i class="fas fa-paper-plane"></i> Send to All</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL (Add Guide) -->
<div class="modal-overlay" id="addGuideModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title">Onboard Guide</div>
        <div class="modal-subtitle" id="modalStepLabel">STEP 1 OF 2 · REGISTRATION</div>
      </div>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="step-indicator">
      <div class="step-dot active" id="gdot1"><span>1</span>&nbsp;Form</div>
      <div class="step-line"></div>
      <div class="step-dot" id="gdot2"><span>2</span>&nbsp;Confirm</div>
    </div>
    <div class="modal-body" id="gStep1">
      <div class="form-grid">
        <div class="form-group full"><label class="form-label">Full Name <span class="req">*</span></label><input type="text" class="form-control" id="gName" placeholder="Juan Luna"></div>
        <div class="form-group"><label class="form-label">Age <span class="req">*</span></label><input type="number" class="form-control" id="gAge" placeholder="25"></div>
        <div class="form-group"><label class="form-label">Mobile <span class="req">*</span></label><input type="tel" class="form-control" id="gMobile" placeholder="0917-000-0000"></div>
        <div class="form-group full"><label class="form-label">Email <span style="font-weight:400;color:#8A99AE;">(Optional)</span></label><input type="email" class="form-control" id="gEmail" placeholder="juan@example.com"></div>
        <div class="form-group full">
          <label class="form-label">Mountains <span class="req">*</span></label>
          <div class="mtn-checklist">
            <?php foreach ($mountains as $mountain): ?>
            <label class="check-item"><input type="checkbox" name="gMtn" value="<?= htmlspecialchars($mountain['name']) ?>"> <?= htmlspecialchars($mountain['name']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-body" id="gStep2" style="display:none;"><div class="confirm-box" id="confirmSummary"></div></div>
    <div class="modal-footer" id="gFooter1">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" onclick="validateAndReview()">Review <i class="fas fa-arrow-right"></i></button>
    </div>
    <div class="modal-footer" id="gFooter2" style="display:none;">
      <button class="btn btn-ghost" onclick="goBackToForm()">Edit</button>
      <button class="btn btn-success" onclick="saveNewGuide()">Confirm</button>
    </div>
  </div>
</div>

<!-- BROADCAST MODAL -->
<div class="modal-overlay" id="broadcastModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title">Broadcast Log</div>
        <div class="modal-subtitle">ALL ANNOUNCEMENTS · NEWEST FIRST</div>
      </div>
      <button class="modal-close" onclick="closeBroadcast()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="broadcastLogBody">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<!-- SEND CONFIRMATION MODAL -->
<div class="confirm-modal-overlay" id="sendConfirmModal">
  <div class="confirm-modal-box">
    <div class="confirm-modal-icon">📣</div>
    <div class="confirm-modal-title">Send Announcement?</div>
    <div class="confirm-modal-sub">This message will be sent to all <strong id="confirmRecipientCount">4</strong> active guides on the roster.</div>
    <div class="confirm-modal-preview" id="confirmMsgPreview"></div>
    <div class="confirm-modal-footer">
      <button class="btn btn-ghost" onclick="closeSendConfirm()">Cancel</button>
      <button class="btn btn-primary" onclick="confirmSendAnnouncement()"><i class="fas fa-paper-plane"></i> Send Now</button>
    </div>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
const guidesFromDB = <?php echo json_encode($guides); ?>;
const mountainsList = <?php echo json_encode($mountains); ?>;

let guidesList = guidesFromDB.length > 0 ? guidesFromDB.map(g => ({
  id: g.id,
  name: g.name,
  mountains: [], // You'll need to populate from guide_mountains table
  bookings: g.total_trips || 0,
  rating: parseFloat(g.rating) || 4.5
})) : [
  { id: 1, name: "John Dela Cruz", mountains: ["Mt. Batulao"], bookings: 12, rating: 4.9 },
  { id: 2, name: "Maya Reyes", mountains: ["Mt. Makiling"], bookings: 8, rating: 4.8 },
  { id: 3, name: "Rico Cabanlit", mountains: ["Mt. Pulag"], bookings: 15, rating: 5.0 },
  { id: 4, name: "Elena Llorente", mountains: ["Mt. Apo"], bookings: 6, rating: 4.7 }
];

// Announcements log — newest first
let announcementLog = [
  { message: "New safety protocol: mandatory radio check at 7AM daily.", timestamp: new Date(Date.now() - 86400000), recipients: 4 }
];

function getTopGuide() {
  return guidesList.reduce((best, g) => g.rating > best.rating ? g : best, guidesList[0]);
}

function renderTopGuideCard() {
  const top = getTopGuide();
  const initials = top.name.split(' ').map(n => n[0]).join('').toUpperCase();
  const topGuideCardWrapper = document.getElementById('topGuideCardWrapper');
  if (topGuideCardWrapper) {
    topGuideCardWrapper.innerHTML = `
      <div class="top-guide-card">
        <div class="top-guide-label">⭐ Top Performing Guide</div>
        <div class="top-guide-row">
          <div class="top-guide-avatar">${initials}</div>
          <div>
            <div class="top-guide-name">${escapeHtml(top.name)}</div>
            <div class="top-guide-sub">${top.mountains.join(', ') || 'All mountains'} · ${top.bookings} bookings</div>
            <div class="top-guide-rating">${'★'.repeat(Math.round(top.rating))} ${top.rating}</div>
          </div>
        </div>
      </div>
    `;
  }
}

function renderMtnTopGuides() {
  const container = document.getElementById('mtnTopContainer');
  if (!container) return;
  
  const mountainNames = mountainsList.map(m => m.name);
  container.innerHTML = '';
  mountainNames.forEach(mtn => {
    const guidesForMtn = guidesList.filter(g => g.mountains.includes(mtn));
    if (!guidesForMtn.length) return;
    const top = guidesForMtn.reduce((best, g) => g.rating > best.rating ? g : best, guidesForMtn[0]);
    const initials = top.name.split(' ').map(n => n[0]).join('').toUpperCase();
    const item = document.createElement('div');
    item.className = 'mtn-top-item';
    item.innerHTML = `
      <div class="mtn-top-avatar">${initials}</div>
      <div class="mtn-top-info">
        <div class="mtn-top-name">${escapeHtml(top.name)}</div>
        <div class="mtn-top-meta">★ ${top.rating} · ${top.bookings} bookings</div>
      </div>
      <div class="mtn-tag">${escapeHtml(mtn)}</div>
    `;
    container.appendChild(item);
  });
}

function renderGuides() {
  const container = document.getElementById('guidesContainer');
  if (!container) return;
  
  container.innerHTML = '';
  guidesList.forEach(g => {
    const initials = g.name.split(' ').map(n => n[0]).join('').toUpperCase();
    const row = document.createElement('div');
    row.className = 'guide-row';
    row.style.cssText = "display:flex; align-items:center; gap:16px; padding:16px; border-bottom:1px solid #EFF2F6;";
    row.innerHTML = `
      <div class="guide-avatar" style="width:44px; height:44px; background:#111318; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:600; flex-shrink:0;">${initials}</div>
      <div class="guide-info" style="flex:1; min-width:0;">
        <div class="guide-name" style="font-weight:600; color:#111318; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(g.name)}</div>
        <div class="guide-meta" style="font-size:12px; color:#5B6A7E;">${g.mountains.join(', ') || 'All mountains'} · ★ ${g.rating}</div>
      </div>
      <button class="btn btn-ghost" onclick="alert('Message ${escapeHtml(g.name)}')">
        <i class="fas fa-comment"></i> <span class="msg-btn-text">Message</span>
      </button>
    `;
    container.appendChild(row);
  });
  const guideCountSpan = document.getElementById('guideCount');
  if (guideCountSpan) guideCountSpan.textContent = `${guidesList.length} on roster`;
  renderTopGuideCard();
  renderMtnTopGuides();
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    return m;
  });
}

// Sidebar Navigation
document.querySelectorAll('.nav-item').forEach(item => {
  const href = item.getAttribute('data-href');
  if (href) {
    item.addEventListener('click', () => { 
      window.location.href = href; 
    });
  }
});

// Modal & Form Functions
const modal = document.getElementById('addGuideModal');
const addGuideBtn = document.getElementById('addGuideBtn');
if (addGuideBtn) {
  addGuideBtn.addEventListener('click', () => { if (modal) modal.classList.add('open'); showStep(1); });
}
function closeModal() { if (modal) modal.classList.remove('open'); }
function showStep(n) {
  const gStep1 = document.getElementById('gStep1');
  const gFooter1 = document.getElementById('gFooter1');
  const gStep2 = document.getElementById('gStep2');
  const gFooter2 = document.getElementById('gFooter2');
  const gdot1 = document.getElementById('gdot1');
  const gdot2 = document.getElementById('gdot2');
  
  if (gStep1) gStep1.style.display = n === 1 ? 'block' : 'none';
  if (gFooter1) gFooter1.style.display = n === 1 ? 'flex' : 'none';
  if (gStep2) gStep2.style.display = n === 2 ? 'block' : 'none';
  if (gFooter2) gFooter2.style.display = n === 2 ? 'flex' : 'none';
  if (gdot1) gdot1.classList.toggle('active', n === 1);
  if (gdot2) gdot2.classList.toggle('active', n === 2);
}

function validateAndReview() {
  const name = document.getElementById('gName')?.value.trim();
  const age = document.getElementById('gAge')?.value.trim();
  const mobile = document.getElementById('gMobile')?.value.trim();
  const mtns = [...document.querySelectorAll('input[name="gMtn"]:checked')].map(c => c.value);

  if (!name) return alert("Full Name is required.");
  if (!age) return alert("Age is required.");
  if (!mobile) return alert("Mobile number is required.");
  if (mtns.length === 0) return alert("Please select at least one mountain.");

  const email = document.getElementById('gEmail')?.value.trim();
  const confirmSummary = document.getElementById('confirmSummary');
  if (confirmSummary) {
    confirmSummary.innerHTML = `
      <div class="confirm-row"><span>Name</span><strong>${escapeHtml(name)}</strong></div>
      <div class="confirm-row"><span>Age</span><strong>${escapeHtml(age)}</strong></div>
      <div class="confirm-row"><span>Mobile</span><strong>${escapeHtml(mobile)}</strong></div>
      <div class="confirm-row"><span>Email</span><strong>${escapeHtml(email) || '—'}</strong></div>
      <div class="confirm-row"><span>Mountains</span><strong>${mtns.join(', ')}</strong></div>
    `;
  }
  showStep(2);
}

function goBackToForm() { showStep(1); }

function saveNewGuide() {
  const name = document.getElementById('gName')?.value.trim();
  const mtns = [...document.querySelectorAll('input[name="gMtn"]:checked')].map(c => c.value);
  const newId = guidesList.length + 1;
  guidesList.push({ id: newId, name: name, mountains: mtns.length ? mtns : ["New"], bookings: 0, rating: 5.0 });
  renderGuides();
  closeModal();
}

// ─── SEND ANNOUNCEMENT with Confirmation ───
const sendAnnounceBtn = document.getElementById('sendAnnounceBtn');
if (sendAnnounceBtn) {
  sendAnnounceBtn.addEventListener('click', () => {
    const msg = document.getElementById('announceTxt')?.value.trim();
    if (!msg) return alert("Please write an announcement first.");
    const confirmMsgPreview = document.getElementById('confirmMsgPreview');
    const confirmRecipientCount = document.getElementById('confirmRecipientCount');
    if (confirmMsgPreview) confirmMsgPreview.textContent = msg;
    if (confirmRecipientCount) confirmRecipientCount.textContent = guidesList.length;
    const sendConfirmModal = document.getElementById('sendConfirmModal');
    if (sendConfirmModal) sendConfirmModal.classList.add('open');
  });
}

function closeSendConfirm() {
  const sendConfirmModal = document.getElementById('sendConfirmModal');
  if (sendConfirmModal) sendConfirmModal.classList.remove('open');
}

function confirmSendAnnouncement() {
  const msg = document.getElementById('announceTxt')?.value.trim();
  announcementLog.unshift({ message: msg, timestamp: new Date(), recipients: guidesList.length });
  const announceTxt = document.getElementById('announceTxt');
  if (announceTxt) announceTxt.value = '';
  closeSendConfirm();
  alert('Announcement sent to all guides!');
}

// ─── BROADCAST MODAL ───
const broadcastBtn = document.getElementById('broadcastBtn');
if (broadcastBtn) {
  broadcastBtn.addEventListener('click', () => {
    renderBroadcastLog();
    const broadcastModal = document.getElementById('broadcastModal');
    if (broadcastModal) broadcastModal.classList.add('open');
  });
}

function closeBroadcast() {
  const broadcastModal = document.getElementById('broadcastModal');
  if (broadcastModal) broadcastModal.classList.remove('open');
}

function renderBroadcastLog() {
  const body = document.getElementById('broadcastLogBody');
  if (!body) return;
  
  if (announcementLog.length === 0) {
    body.innerHTML = '<div style="text-align:center;color:#B0BAC8;font-size:13px;padding:32px 0;">No announcements sent yet.</div>';
    return;
  }
  body.innerHTML = announcementLog.map(a => {
    const ts = a.timestamp.toLocaleString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    return `
      <div class="broadcast-log-item">
        <div class="broadcast-log-msg">${escapeHtml(a.message)}</div>
        <div class="broadcast-log-ts">${ts}</div>
        <div class="broadcast-log-recipients"><i class="fas fa-users" style="font-size:10px;"></i> Sent to ${a.recipients} guide${a.recipients !== 1 ? 's' : ''}</div>
      </div>
    `;
  }).join('');
}

function updateDate() {
  const d = new Date();
  const liveDate = document.getElementById('liveDate');
  if (liveDate) {
    liveDate.textContent = d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase() + ' ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
  }
}
setInterval(updateDate, 1000); 
updateDate();
renderGuides();
</script>
</body>
</html>