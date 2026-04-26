<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Safety</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* ── BACKGROUND ── */
    .guide-content {
      background:
        radial-gradient(ellipse 50% 40% at 90% 5%, rgba(184,49,42,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 5% 90%, rgba(16,6,0,0.03) 0%, transparent 60%);
    }

    /* ── SAFETY BANNER ── */
    .safety-banner {
      background: var(--primary-bg);
      border-radius: var(--r-2xl);
      padding: 26px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      gap: 16px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(16,6,0,0.28), 0 2px 8px rgba(16,6,0,0.16);
    }
    .safety-banner::before {
      content: '';
      position: absolute;
      top: -50px; right: -50px;
      width: 200px; height: 200px;
      background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }
    .safety-banner::after {
      content: '';
      position: absolute;
      bottom: -70px; left: 30%;
      width: 260px; height: 260px;
      background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }
    .safety-banner-content {
      position: relative;
      z-index: 1;
      flex: 1;
    }
    .safety-banner-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 0.6rem;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      color: rgba(255,255,255,0.4);
      margin-bottom: 6px;
    }
    .safety-banner-text h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.35rem;
      font-weight: 700;
      color: white;
      letter-spacing: -0.3px;
      margin-bottom: 6px;
    }
    .safety-banner-text p {
      font-size: 0.82rem;
      color: rgba(255,255,255,0.58);
      line-height: 1.4;
    }
    .safety-banner-pills {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 12px;
    }
    .safety-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.16);
      padding: 5px 12px;
      border-radius: 40px;
      font-size: 0.72rem;
      font-weight: 500;
      color: rgba(255,255,255,0.85);
    }
    .safety-banner-actions {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 8px;
      flex-shrink: 0;
    }
    .emergency-big-btn {
      display: flex;
      align-items: center;
      gap: 9px;
      background: rgba(184,49,42,0.92);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.18);
      color: white;
      border-radius: var(--r-lg);
      padding: 12px 22px;
      font-size: 0.84rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.18s;
      box-shadow: 0 4px 20px rgba(184,49,42,0.45);
      font-family: 'DM Sans', sans-serif;
      white-space: nowrap;
    }
    .emergency-big-btn:hover {
      background: var(--red);
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(184,49,42,0.55);
    }

    /* ── SESSION SELECTOR ── */
    .session-select-bar {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-lg);
      padding: 13px 18px;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
      flex-wrap: wrap;
      box-shadow: var(--glass-shadow);
    }
    .session-select-label {
      font-size: 0.68rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--ink-4);
      white-space: nowrap;
    }
    .session-select-bar select {
      flex: 1;
      border: 1.5px solid var(--line);
      border-radius: var(--r-md);
      padding: 7px 13px;
      font-size: 0.82rem;
      font-family: 'DM Sans', sans-serif;
      color: var(--ink);
      background: rgba(255,255,255,0.6);
      backdrop-filter: blur(4px);
      outline: none;
      min-width: 180px;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236B6B63' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      cursor: pointer;
      transition: border-color 0.15s;
    }
    .session-select-bar select:focus { border-color: var(--primary); outline: none; }

    /* ── SAFETY GRID ── */
    .safety-layout {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 20px;
    }

    /* ── HIKER SAFETY CARDS ── */
    .hiker-safety-list { display: flex; flex-direction: column; gap: 10px; }
    .hiker-safety-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-lg);
      padding: 14px 16px;
      display: flex;
      align-items: center;
      gap: 13px;
      transition: all 0.18s;
      box-shadow: var(--shadow-sm);
      position: relative;
      overflow: hidden;
    }
    .hiker-safety-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); background: white; }
    .hiker-safety-card.card-safe   { border-left: 3px solid var(--green); }
    .hiker-safety-card.card-unsafe { border-left: 3px solid var(--red); }
    .hiker-safety-card.card-unknown { border-left: 3px solid var(--stone-3); }
    .hs-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: var(--primary-soft);
      color: var(--primary);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 0.9rem;
      font-weight: 700;
      flex-shrink: 0;
      border: 2px solid rgba(16,6,0,0.08);
    }
    .hs-info { flex: 1; min-width: 0; }
    .hs-name { font-size: 0.87rem; font-weight: 600; color: var(--ink); }
    .hs-meta { font-size: 0.7rem; color: var(--ink-4); margin-top: 2px; }
    .hs-checkpoint {
      font-size: 0.68rem;
      color: var(--ink-3);
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .hs-actions { flex-shrink: 0; }
    .safety-toggle {
      display: flex;
      background: var(--stone-2);
      border-radius: 40px;
      padding: 3px;
      gap: 2px;
    }
    .stoggle-btn {
      padding: 5px 12px;
      border-radius: 40px;
      border: none;
      font-size: 0.7rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s;
      background: transparent;
      color: var(--ink-3);
      font-family: 'DM Sans', sans-serif;
    }
    .stoggle-btn.active-safe   { background: var(--green); color: white; box-shadow: 0 2px 8px rgba(27,112,69,0.3); }
    .stoggle-btn.active-unsafe { background: var(--red);   color: white; box-shadow: 0 2px 8px rgba(184,49,42,0.3); }

    /* ── CHECKPOINT CARD ── */
    .checkpoint-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 18px 20px;
      margin-bottom: 16px;
      box-shadow: var(--glass-shadow);
    }
    .cp-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }
    .cp-title {
      font-family: 'Playfair Display', serif;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--ink);
    }
    .cp-progress-track {
      position: relative;
      padding-left: 18px;
    }
    .cp-progress-track::before {
      content: '';
      position: absolute;
      left: 7px; top: 8px; bottom: 8px;
      width: 2px;
      background: var(--line);
      border-radius: 1px;
    }
    .cp-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 7px 0;
      position: relative;
    }
    .cp-dot {
      width: 14px; height: 14px;
      border-radius: 50%;
      border: 2px solid var(--line);
      background: white;
      flex-shrink: 0;
      margin-left: -18px;
      z-index: 1;
      position: relative;
      transition: all 0.2s;
    }
    .cp-dot.reached { background: var(--green); border-color: var(--green); box-shadow: 0 0 0 3px var(--green-lt); }
    .cp-dot.current { background: var(--amber); border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-lt); }
    .cp-item-name { font-size: 0.82rem; font-weight: 600; color: var(--ink); }
    .cp-item-sub  { font-size: 0.66rem; color: var(--ink-4); margin-top: 1px; }
    .cp-item-count {
      margin-left: auto;
      font-size: 0.66rem;
      font-weight: 600;
      color: var(--ink-4);
      white-space: nowrap;
    }

    /* ── INCIDENT LOG ── */
    .incident-list { display: flex; flex-direction: column; gap: 8px; }
    .incident-wrapper {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 16px 18px;
      box-shadow: var(--glass-shadow);
    }
    .incident-item {
      background: rgba(184,49,42,0.06);
      border: 1px solid rgba(184,49,42,0.12);
      border-radius: var(--r-md);
      padding: 11px 13px;
    }
    .incident-header { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
    .incident-type { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--red); }
    .incident-time { font-family: 'DM Mono', monospace; font-size: 0.6rem; color: var(--ink-5); margin-left: auto; }
    .incident-text { font-size: 0.77rem; color: var(--ink-2); line-height: 1.5; }
    #noIncidents {
      font-size: 0.78rem;
      color: var(--ink-4);
      padding: 16px 0;
      text-align: center;
    }

    /* Mark All Safe btn */
    .btn-forest {
      background: var(--green);
      color: white;
      box-shadow: 0 3px 10px rgba(27,112,69,0.25);
    }
    .btn-forest:hover { background: #155c38; box-shadow: 0 5px 16px rgba(27,112,69,0.35); transform: translateY(-1px); }

    @media (max-width: 1024px) { .safety-layout { grid-template-columns: 1fr; } }
    @media (max-width: 640px) {
      .safety-banner { flex-direction: column; align-items: flex-start; }
      .safety-banner-actions { align-items: flex-start; flex-direction: row; }
    }
  </style>
</head>
<body>
<div class="guide-app">
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none">
        <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity="0.9"/>
        <path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity="0.3"/>
      </svg>
      <div><div class="sidebar-logo-text">LAKBAY</div><div class="sidebar-logo-sub">Guide Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.html"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.html"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.html"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.html" class="active"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <div class="sidebar-section-label">Account</div>
      <ul><li><a href="guide-profile.html"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar">JD</div>
      <div class="sidebar-profile-info">
        <div class="sidebar-profile-name">John Dela Cruz</div>
        <div class="sidebar-profile-role">Senior Trail Guide</div>
      </div>
    </div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Safety Reporting</div>
      <div class="topbar-right">
        <div class="topbar-time" id="liveTime"></div>
        <button class="topbar-icon-btn" onclick="openModal('incidentModal')" title="Report Incident"><i class="fas fa-circle-exclamation"></i></button>
      </div>
    </div>

    <div class="guide-content">

      <!-- SAFETY BANNER -->
      <div class="safety-banner">
        <div class="safety-banner-content">
          <div class="safety-banner-eyebrow"><i class="fas fa-shield-halved" style="margin-right:5px;"></i>Safety Command Center</div>
          <div class="safety-banner-text">
            <h3>Hiker Safety Dashboard</h3>
            <p>Monitor all hikers in real-time and report incidents instantly.</p>
          </div>
          <div class="safety-banner-pills">
            <span class="safety-pill"><i class="fas fa-users" style="font-size:0.7rem;"></i> 3 Active Hikers</span>
            <span class="safety-pill"><i class="fas fa-location-dot" style="font-size:0.7rem;"></i> Ridge Junction</span>
            <span class="safety-pill"><i class="fas fa-clock" style="font-size:0.7rem;"></i> In Progress</span>
          </div>
        </div>
        <div class="safety-banner-actions">
          <button class="emergency-big-btn" onclick="openModal('emergencyModal')">
            <i class="fas fa-triangle-exclamation"></i> Emergency Alert
          </button>
        </div>
      </div>

      <!-- SESSION SELECTOR -->
      <div class="session-select-bar">
        <span class="session-select-label"><i class="fas fa-route" style="margin-right:5px;"></i> Active Hike</span>
        <select id="sessionSelect" onchange="showToast('Loading hike session…')">
          <option>BK-001 — Lea Santiago · Mt. Batulao · Apr 25 (3 pax)</option>
          <option>BK-002 — Ben Torres · Mt. Talamitam · Apr 27 (5 pax)</option>
          <option>BK-003 — Nina Cruz · Mt. Batulao · Apr 30 (2 pax)</option>
        </select>
        <span class="badge badge-green"><span style="width:5px;height:5px;background:var(--green);border-radius:50%;display:inline-block;"></span> In Progress</span>
      </div>

      <div class="safety-layout">
        <!-- LEFT: HIKER STATUS -->
        <div>
          <div class="section-header">
            <div class="section-title">Hiker Safety Status</div>
            <button class="btn btn-forest btn-sm" onclick="markAllSafe()"><i class="fas fa-shield-check"></i> Mark All Safe</button>
          </div>
          <div class="hiker-safety-list" id="hikerSafetyList"></div>
        </div>

        <!-- RIGHT: CHECKPOINT + INCIDENT LOG -->
        <div style="display:flex;flex-direction:column;gap:16px;">

          <!-- CHECKPOINT PROGRESS -->
          <div class="checkpoint-card">
            <div class="cp-header">
              <div class="cp-title">Trail Checkpoints</div>
              <span class="badge badge-amber"><i class="fas fa-location-dot" style="font-size:0.55rem;"></i> 2 / 4</span>
            </div>
            <div class="cp-progress-track">
              <div class="cp-item">
                <div class="cp-dot reached"></div>
                <div style="flex:1;">
                  <div class="cp-item-name">Jump-off Point</div>
                  <div class="cp-item-sub">5:00 AM · All present</div>
                </div>
                <div class="cp-item-count" style="color:var(--green);">3/3 ✓</div>
              </div>
              <div class="cp-item">
                <div class="cp-dot reached"></div>
                <div style="flex:1;">
                  <div class="cp-item-name">Lower Ridge</div>
                  <div class="cp-item-sub">6:45 AM · All accounted</div>
                </div>
                <div class="cp-item-count" style="color:var(--green);">3/3 ✓</div>
              </div>
              <div class="cp-item">
                <div class="cp-dot current"></div>
                <div style="flex:1;">
                  <div class="cp-item-name">Ridge Junction</div>
                  <div class="cp-item-sub">Current position</div>
                </div>
                <div class="cp-item-count" style="color:var(--amber);">2/3 ⚠</div>
              </div>
              <div class="cp-item">
                <div class="cp-dot"></div>
                <div style="flex:1;">
                  <div class="cp-item-name">Summit</div>
                  <div class="cp-item-sub">Est. 9:30 AM</div>
                </div>
                <div class="cp-item-count">—</div>
              </div>
            </div>
            <button class="btn btn-outline btn-sm" style="margin-top:12px;width:100%;justify-content:center;" onclick="showToast('Checkpoint logged! ✓')">
              <i class="fas fa-flag"></i> Log Current Checkpoint
            </button>
          </div>

          <!-- INCIDENT LOG -->
          <div class="incident-wrapper">
            <div class="section-header" style="margin-bottom:12px;">
              <div class="section-title" style="margin-bottom:0;">Incident Log</div>
              <button class="btn btn-ghost btn-sm" onclick="openModal('incidentModal')"><i class="fas fa-plus"></i> Report</button>
            </div>
            <div class="incident-list" id="incidentList"></div>
            <div id="noIncidents" style="font-size:0.78rem;color:var(--ink-4);padding:16px 0;text-align:center;display:none;">
              <i class="fas fa-check-circle" style="color:var(--green);margin-right:5px;"></i>No incidents reported
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
      <a href="guide-safety.html" class="bnav-item active"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
      <a href="guide-profile.html" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
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
    <div class="modal-body">
      <div style="font-size:0.8rem;color:var(--ink-3);margin-bottom:16px;line-height:1.65;background:var(--red-lt);padding:10px 14px;border-radius:var(--r-md);border-left:3px solid var(--red);">
        <strong style="color:var(--red);">⚠ Immediate notification</strong> — Admin and all emergency contacts will be alerted.
      </div>
      <div class="form-group">
        <label class="form-label">Situation Type</label>
        <select class="form-control"><option>Medical Emergency</option><option>Lost Hiker</option><option>Weather Hazard</option><option>Trail Accident</option><option>Wildlife Encounter</option><option>Other</option></select>
      </div>
      <div class="form-group">
        <label class="form-label">Hiker Involved</label>
        <select class="form-control"><option>All hikers</option><option>Lea Santiago</option><option>Marco Reyes</option><option>Ana Bautista</option></select>
      </div>
      <div class="form-group">
        <label class="form-label">Location / Checkpoint</label>
        <input type="text" class="form-control" placeholder="e.g. Ridge Junction km 3.2">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" rows="3" placeholder="Describe the situation…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('emergencyModal')">Cancel</button>
      <button class="btn btn-danger" onclick="sendEmergency()"><i class="fas fa-paper-plane"></i> Send Alert Now</button>
    </div>
  </div>
</div>

<!-- INCIDENT MODAL -->
<div class="modal-overlay" id="incidentModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-circle-exclamation" style="margin-right:8px;color:var(--amber);"></i>Report Incident</div>
      <button class="modal-close" onclick="closeModal('incidentModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Incident Type</label>
        <select class="form-control" id="incType"><option>Minor Injury</option><option>Hiker Fatigue</option><option>Lost Trail</option><option>Weather Issue</option><option>Equipment Problem</option><option>Other</option></select>
      </div>
      <div class="form-group">
        <label class="form-label">Location</label>
        <input type="text" class="form-control" id="incLocation" placeholder="e.g. Lower Ridge, km 1.8">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" id="incDesc" rows="3" placeholder="What happened?"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Notify Admin?</label>
        <select class="form-control" id="incNotify"><option value="yes">Yes — notify admin</option><option value="no">No — log only</option></select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('incidentModal')">Cancel</button>
      <button class="btn btn-amber" onclick="submitIncident()"><i class="fas fa-flag"></i> Submit Report</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const hikers = [
  { id:0, name:'Lea Santiago',  initials:'LS', trail:'BK-001 · Mt. Batulao', checkpoint:'Ridge Junction', safe:true },
  { id:1, name:'Marco Reyes',   initials:'MR', trail:'BK-001 · Mt. Batulao', checkpoint:'Ridge Junction', safe:true },
  { id:2, name:'Ana Bautista',  initials:'AB', trail:'BK-001 · Mt. Batulao', checkpoint:'Lower Ridge',    safe:null },
];
const incidents = [];

function renderHikerSafety() {
  const el = document.getElementById('hikerSafetyList');
  el.innerHTML = '';
  hikers.forEach((h, i) => {
    const status = h.safe === true ? 'card-safe' : h.safe === false ? 'card-unsafe' : 'card-unknown';
    const div = document.createElement('div');
    div.className = `hiker-safety-card ${status}`;
    div.innerHTML = `
      <div class="hs-avatar">${h.initials}</div>
      <div class="hs-info">
        <div class="hs-name">${h.name}</div>
        <div class="hs-meta">${h.trail}</div>
        <div class="hs-checkpoint"><i class="fas fa-location-dot" style="font-size:0.58rem;color:var(--ink-5);"></i>${h.checkpoint}</div>
      </div>
      <div class="hs-actions">
        <div class="safety-toggle">
          <button class="stoggle-btn ${h.safe===true?'active-safe':''}" onclick="setSafe(${i},true)"><i class="fas fa-shield-check"></i> Safe</button>
          <button class="stoggle-btn ${h.safe===false?'active-unsafe':''}" onclick="setSafe(${i},false)"><i class="fas fa-circle-xmark"></i> Unsafe</button>
        </div>
      </div>`;
    el.appendChild(div);
  });
}

function setSafe(i, val) {
  hikers[i].safe = val;
  renderHikerSafety();
  const label = val ? '✅ Marked safe' : '⚠️ Marked unsafe';
  showToast(`${label}: ${hikers[i].name}`);
  if (!val) setTimeout(() => openModal('emergencyModal'), 350);
}

function markAllSafe() {
  hikers.forEach(h => h.safe = true);
  renderHikerSafety();
  showToast('All hikers marked safe ✅');
}

function renderIncidents() {
  const el = document.getElementById('incidentList');
  const noEl = document.getElementById('noIncidents');
  el.innerHTML = '';
  if (!incidents.length) { noEl.style.display = 'block'; return; }
  noEl.style.display = 'none';
  incidents.forEach(inc => {
    const div = document.createElement('div');
    div.className = 'incident-item';
    div.innerHTML = `
      <div class="incident-header">
        <span class="incident-type"><i class="fas fa-circle-exclamation" style="margin-right:4px;"></i>${inc.type}</span>
        <span class="incident-time">${inc.time}</span>
      </div>
      <div class="incident-text">${inc.location ? '<strong>' + inc.location + '</strong> — ' : ''}${inc.desc}</div>`;
    el.appendChild(div);
  });
}

function submitIncident() {
  const type   = document.getElementById('incType').value;
  const loc    = document.getElementById('incLocation').value;
  const desc   = document.getElementById('incDesc').value || 'No description.';
  const notify = document.getElementById('incNotify').value;
  incidents.unshift({ type, location: loc, desc, time: new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) });
  renderIncidents();
  closeModal('incidentModal');
  showToast(notify==='yes' ? '📋 Incident logged & admin notified.' : '📋 Incident logged.');
}

function sendEmergency() { closeModal('emergencyModal'); showToast('🚨 Emergency alert sent to admin!'); }

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) closeModal(o.id); }));

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

function updateTime() {
  document.getElementById('liveTime').textContent = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
updateTime(); setInterval(updateTime, 1000);
renderHikerSafety();
renderIncidents();
</script>
</body>
</html>