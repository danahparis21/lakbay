<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Trail Map</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* map page is full-height */
    .guide-content { padding: 0; flex: 1; display: flex; flex-direction: column; }

    .map-layout {
      display: flex;
      flex: 1;
      height: calc(100vh - 60px);
      overflow: hidden;
    }

    /* ── MAP CANVAS ── */
    .map-canvas {
      flex: 1;
      background: #F4F1EA;  /* warm cream base that fits white/minimal palette */
      position: relative;
      overflow: hidden;
    }
    .map-bg {
      position: absolute; inset: 0;
      background:
        /* Soft terrain gradients - warm earth tones that complement #100600 */
        radial-gradient(ellipse at 25% 35%, rgba(210, 190, 160, 0.25) 0%, transparent 55%),
        radial-gradient(ellipse at 70% 65%, rgba(180, 155, 125, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 85% 20%, rgba(200, 175, 145, 0.18) 0%, transparent 40%),
        linear-gradient(145deg, #F0EDE5 0%, #EAE6DD 40%, #E3DFD4 100%);
    }

    /* Topographic contour lines (elegant, subtle) */
    .map-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: 
        repeating-linear-gradient(0deg, rgba(100, 80, 55, 0.04) 0px, rgba(100, 80, 55, 0.04) 1px, transparent 1px, transparent 40px),
        repeating-linear-gradient(90deg, rgba(100, 80, 55, 0.04) 0px, rgba(100, 80, 55, 0.04) 1px, transparent 1px, transparent 40px),
        repeating-linear-gradient(45deg, rgba(100, 80, 55, 0.03) 0px, rgba(100, 80, 55, 0.03) 1px, transparent 1px, transparent 60px),
        repeating-linear-gradient(135deg, rgba(100, 80, 55, 0.03) 0px, rgba(100, 80, 55, 0.03) 1px, transparent 1px, transparent 60px);
      pointer-events: none;
    }

    /* Decorative trail path */
    .map-trail-svg {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      pointer-events: none;
    }
    /* Map controls */
    .map-controls {
      position: absolute;
      right: 16px; top: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      z-index: 10;
    }
    .map-ctrl-btn {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(0,0,0,0.06);
      border-radius: var(--r-md);
      display: flex; align-items: center; justify-content: center;
      color: var(--ink-2);
      font-size: 0.9rem;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      transition: all 0.15s;
    }
    .map-ctrl-btn:hover { background: #100600; color: white; border-color: #100600; }

    /* Status bar on map — glassmorphism */
    .map-status-bar {
      position: absolute;
      top: 16px; left: 16px;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(12px);
      border-radius: var(--r-lg);
      padding: 10px 18px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      z-index: 10;
      border: 1px solid rgba(255,255,255,0.6);
    }
    .live-dot {
      width: 8px; height: 8px;
      background: #E74C3C;
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(231,76,60,0.4); }
      50% { box-shadow: 0 0 0 6px rgba(231,76,60,0); }
    }
    .map-status-text {
      font-size: 0.78rem;
      font-weight: 600;
      color: #100600;
    }
    .map-status-sub {
      font-size: 0.65rem;
      color: var(--ink-4);
    }

    /* Hiker markers */
    .hiker-marker {
      position: absolute;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      cursor: pointer;
      transition: transform 0.2s;
      z-index: 20;
    }
    .hiker-marker:hover { transform: scale(1.15); z-index: 30; }
    .hiker-marker-avatar {
      width: 38px; height: 38px;
      border-radius: 50%;
      border: 3px solid white;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0,0,0,0.15);
      background: #F0EDE8;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 0.85rem;
      font-weight: 700;
      color: #100600;
    }
    .hiker-marker-name {
      font-size: 0.6rem;
      font-weight: 600;
      color: white;
      background: rgba(16, 6, 0, 0.75);
      backdrop-filter: blur(4px);
      padding: 2px 8px;
      border-radius: 20px;
      white-space: nowrap;
    }
    .hiker-marker-avatar.safe-border { border-color: #1B7045; }
    .hiker-marker-avatar.warn-border { border-color: #C97B1A; }
    .hiker-marker-avatar.critical-border { border-color: #B8312A; }

    /* Guide marker */
    .guide-marker {
      position: absolute;
      z-index: 25;
      cursor: pointer;
    }
    .guide-marker-dot {
      width: 18px; height: 18px;
      background: #100600;
      border-radius: 50%;
      border: 3px solid white;
      box-shadow: 0 0 0 4px rgba(16, 6, 0, 0.2), 0 2px 12px rgba(0,0,0,0.2);
      animation: guidePulse 2s infinite;
    }
    @keyframes guidePulse {
      0%, 100% { box-shadow: 0 0 0 4px rgba(16, 6, 0, 0.2), 0 2px 12px rgba(0,0,0,0.2); }
      50% { box-shadow: 0 0 0 10px rgba(16, 6, 0, 0), 0 2px 12px rgba(0,0,0,0.2); }
    }
    .guide-marker-label {
      position: absolute;
      top: -24px; left: 50%;
      transform: translateX(-50%);
      font-size: 0.6rem;
      font-weight: 700;
      color: white;
      background: #100600;
      padding: 2px 10px;
      border-radius: 20px;
      white-space: nowrap;
      letter-spacing: 0.3px;
    }

    /* Trail checkpoint labels — glass style */
    .checkpoint {
      position: absolute;
      display: flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,0.2);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.35);
      border-radius: 30px;
      padding: 4px 12px;
      transition: all 0.15s;
    }
    .checkpoint-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: rgba(255,255,255,0.8);
    }
    .checkpoint-dot.summit { background: #1B7045; }
    .checkpoint-dot.ridge { background: #C97B1A; }
    .checkpoint-label {
      font-size: 0.6rem;
      color: #444443;
      font-family: 'DM Mono', monospace;
      font-weight: 500;
      letter-spacing: 0.3px;
    }

    /* ── SIDE PANEL ── */
    .map-side-panel {
      width: 300px;
      background: rgba(255,255,255,0.96);
      backdrop-filter: blur(4px);
      border-left: 1px solid rgba(0,0,0,0.06);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .side-panel-header {
      padding: 16px 18px;
      border-bottom: 1px solid rgba(0,0,0,0.05);
      background: rgba(248,247,245,0.8);
    }
    .side-panel-header h3 {
      font-family: 'Playfair Display', serif;
      font-size: 0.95rem;
      font-weight: 700;
      color: #100600;
    }
    .side-panel-header p {
      font-size: 0.68rem;
      color: var(--ink-4);
      margin-top: 2px;
    }
    .hiker-track-list {
      flex: 1;
      overflow-y: auto;
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .track-item {
      background: rgba(248,247,245,0.7);
      border-radius: var(--r-md);
      padding: 12px 14px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: all 0.2s;
      border: 1.5px solid transparent;
    }
    .track-item:hover { background: rgba(16,6,0,0.05); border-color: rgba(16,6,0,0.15); }
    .track-item.selected { background: rgba(16,6,0,0.08); border-color: #100600; }
    .track-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: #F0EDE8;
      color: #100600;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 0.85rem;
      font-weight: 700;
      flex-shrink: 0;
    }
    .track-info { flex: 1; min-width: 0; }
    .track-name { font-size: 0.82rem; font-weight: 600; color: var(--ink); }
    .track-location { font-size: 0.68rem; color: var(--ink-4); margin-top: 2px; }
    .track-status-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .ts-safe { background: #1B7045; box-shadow: 0 0 0 2px rgba(27,112,69,0.2); }
    .ts-moving { background: #C97B1A; box-shadow: 0 0 0 2px rgba(201,123,26,0.2); }
    .ts-behind { background: #B8312A; box-shadow: 0 0 0 2px rgba(184,49,42,0.2); }

    .legend {
      padding: 14px 18px;
      border-top: 1px solid rgba(0,0,0,0.05);
      background: rgba(255,255,255,0.9);
    }
    .legend-title {
      font-size: 0.62rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--ink-4);
      margin-bottom: 8px;
    }
    .legend-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.7rem;
      color: var(--ink-3);
      margin-bottom: 6px;
    }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

    /* mobile map: hide side panel, show bottom sheet */
    @media (max-width: 768px) {
      .map-layout { height: calc(100vh - 60px - 64px); flex-direction: column; }
      .map-side-panel { width: 100%; height: 200px; border-left: none; border-top: 1px solid rgba(0,0,0,0.05); }
      .hiker-track-list { flex-direction: row; flex-wrap: nowrap; overflow-x: auto; gap: 8px; padding: 8px 12px; }
      .track-item { min-width: 160px; }
      .map-status-bar { top: 12px; left: 12px; padding: 6px 14px; }
      .map-status-text { font-size: 0.7rem; }
    }

    /* Elevation marker styling */
    .elevation-marker {
      position: absolute;
      font-size: 0.55rem;
      font-family: 'DM Mono', monospace;
      color: rgba(16,6,0,0.45);
      background: rgba(255,255,255,0.5);
      backdrop-filter: blur(4px);
      padding: 2px 6px;
      border-radius: 12px;
      pointer-events: none;
      white-space: nowrap;
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
        <li><a href="guide-map.html" class="active"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.html"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.html"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.html"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar">JD</div>
      <div class="sidebar-profile-info"><div class="sidebar-profile-name">John Dela Cruz</div><div class="sidebar-profile-role">Senior Trail Guide</div></div>
    </div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Live Trail Map</div>
      <div class="topbar-right">
        <span class="badge badge-primary" style="background:#10060010; color:#100600;"><span class="live-dot" style="width:6px;height:6px;display:inline-block;margin-right:6px; background:#100600;"></span> Tracking Active</span>
        <button class="topbar-icon-btn" title="Refresh" onclick="showToast('Location updated')"><i class="fas fa-rotate"></i></button>
      </div>
    </div>

    <div class="guide-content">
      <div class="map-layout">

        <!-- MAP -->
        <div class="map-canvas" id="mapCanvas">
          <div class="map-bg"></div>

          <!-- Trail SVG path — elegant trail lines -->
          <svg class="map-trail-svg" viewBox="0 0 800 600" preserveAspectRatio="none">
            <!-- Main trail path — warm brown stroke -->
            <path d="M 80 520 Q 150 480 200 420 Q 260 350 300 300 Q 360 240 420 200 Q 480 160 540 130 Q 600 110 660 90 Q 720 75 760 60"
              stroke="rgba(100, 70, 45, 0.25)" stroke-width="4.5" fill="none" stroke-dasharray="10,8" stroke-linecap="round"/>
            <!-- Secondary trail -->
            <path d="M 200 420 Q 240 400 280 410 Q 320 420 360 390"
              stroke="rgba(100, 70, 45, 0.2)" stroke-width="2.5" fill="none" stroke-dasharray="6,6"/>
            <!-- Alternative path -->
            <path d="M 540 130 Q 560 160 590 180 Q 630 210 680 200 Q 720 190 760 180"
              stroke="rgba(100, 70, 45, 0.15)" stroke-width="2" fill="none" stroke-dasharray="5,7"/>
            <!-- Topographic contour rings (elegant) -->
            <ellipse cx="420" cy="300" rx="200" ry="120" stroke="rgba(80, 60, 40, 0.08)" stroke-width="1.5" fill="none"/>
            <ellipse cx="420" cy="300" rx="150" ry="85" stroke="rgba(80, 60, 40, 0.06)" stroke-width="1.5" fill="none"/>
            <ellipse cx="420" cy="300" rx="90" ry="50" stroke="rgba(80, 60, 40, 0.05)" stroke-width="1" fill="none"/>
            <ellipse cx="580" cy="150" rx="100" ry="60" stroke="rgba(80, 60, 40, 0.05)" stroke-width="1" fill="none"/>
            <!-- Summit marker -->
            <circle cx="760" cy="60" r="8" fill="rgba(16,6,0,0.12)" stroke="rgba(16,6,0,0.25)" stroke-width="1.5"/>
            <circle cx="80" cy="520" r="6" fill="rgba(16,6,0,0.1)" stroke="rgba(16,6,0,0.2)" stroke-width="1"/>
          </svg>

          <!-- Elevation markers -->
          <div class="elevation-marker" style="left:12%;bottom:22%;">480m</div>
          <div class="elevation-marker" style="left:35%;top:48%;">620m</div>
          <div class="elevation-marker" style="left:58%;top:32%;">780m</div>
          <div class="elevation-marker" style="right:8%;top:5%;">960m ⛰️</div>

          <!-- Status bar -->
          <div class="map-status-bar">
            <div>
              <div class="live-dot"></div>
            </div>
            <div>
              <div class="map-status-text">Mt. Batulao — Active Trek</div>
              <div class="map-status-sub">5 hikers tracked · <span id="mapTime"></span></div>
            </div>
          </div>

          <!-- Checkpoints with refined glass look -->
          <div class="checkpoint" style="left:8%;bottom:16%;">
            <div class="checkpoint-dot"></div>
            <span class="checkpoint-label">Trailhead / Jump-off</span>
          </div>
          <div class="checkpoint" style="left:38%;top:48%;">
            <div class="checkpoint-dot ridge"></div>
            <span class="checkpoint-label">Ridge Camp · 620m</span>
          </div>
          <div class="checkpoint" style="right:6%;top:6%;">
            <div class="checkpoint-dot summit"></div>
            <span class="checkpoint-label">Summit Peak · 960m</span>
          </div>
          <div class="checkpoint" style="left:58%;top:34%;">
            <div class="checkpoint-dot"></div>
            <span class="checkpoint-label">Rest Point</span>
          </div>

          <!-- Hiker markers (positioned by %) -->
          <div class="hiker-marker" style="left:16%;bottom:26%;" onclick="selectHiker(0)">
            <div class="hiker-marker-avatar safe-border">LS</div>
            <div class="hiker-marker-name">Lea S.</div>
          </div>
          <div class="hiker-marker" style="left:24%;bottom:36%;" onclick="selectHiker(1)">
            <div class="hiker-marker-avatar safe-border">BT</div>
            <div class="hiker-marker-name">Ben T.</div>
          </div>
          <div class="hiker-marker" style="left:34%;top:50%;" onclick="selectHiker(2)">
            <div class="hiker-marker-avatar warn-border">NC</div>
            <div class="hiker-marker-name">Nina C.</div>
          </div>
          <div class="hiker-marker" style="left:47%;top:42%;" onclick="selectHiker(3)">
            <div class="hiker-marker-avatar safe-border">CV</div>
            <div class="hiker-marker-name">Carlos V.</div>
          </div>
          <div class="hiker-marker" style="left:57%;top:34%;" onclick="selectHiker(4)">
            <div class="hiker-marker-avatar safe-border">MR</div>
            <div class="hiker-marker-name">Marco R.</div>
          </div>

          <!-- Guide marker (#100600 primary) -->
          <div class="guide-marker" style="left:31%;top:49%;">
            <div class="guide-marker-label">YOU</div>
            <div class="guide-marker-dot"></div>
          </div>

          <!-- Map controls -->
          <div class="map-controls">
            <button class="map-ctrl-btn" onclick="showToast('Zoom in')"><i class="fas fa-plus"></i></button>
            <button class="map-ctrl-btn" onclick="showToast('Zoom out')"><i class="fas fa-minus"></i></button>
            <button class="map-ctrl-btn" onclick="showToast('Centering on your location…')"><i class="fas fa-location-crosshairs"></i></button>
            <button class="map-ctrl-btn" onclick="showToast('Full screen map')"><i class="fas fa-expand"></i></button>
          </div>
        </div>

        <!-- SIDE PANEL -->
        <div class="map-side-panel">
          <div class="side-panel-header">
            <h3><i class="fas fa-hiking" style="margin-right:6px; font-size:0.8rem;"></i> Hiker Positions</h3>
            <p id="panelTime">Updated just now</p>
          </div>
          <div class="hiker-track-list" id="trackList"></div>
          <div class="legend">
            <div class="legend-title">Trail Legend</div>
            <div class="legend-item"><div class="legend-dot" style="background:#1B7045;"></div> Safe / On schedule</div>
            <div class="legend-item"><div class="legend-dot" style="background:#C97B1A;"></div> Moving / Behind</div>
            <div class="legend-item"><div class="legend-dot" style="background:#B8312A;"></div> Needs attention</div>
            <div class="legend-item"><div class="legend-dot" style="background:#100600; border:2px solid white; width:12px; height:12px;"></div> Guide (you)</div>
            <div class="legend-item" style="margin-top:4px;"><i class="fas fa-mountain" style="font-size:0.7rem; color:#100600;"></i><span style="margin-left:6px;">Main trail · 4.8km to summit</span></div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.html" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.html" class="bnav-item active"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.html" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-safety.html" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
      <a href="guide-profile.html" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<div class="toast" id="toast"></div>

<script>
const hikers = [
  { name:'Lea Santiago', initials:'LS', location:'Lower Trail · km 1.2', status:'safe', lastSeen:'2 min ago' },
  { name:'Ben Torres',   initials:'BT', location:'Lower Trail · km 1.5', status:'safe', lastSeen:'1 min ago' },
  { name:'Nina Cruz',    initials:'NC', location:'Junction · km 2.1',    status:'moving', lastSeen:'Just now' },
  { name:'Carlos Vidal', initials:'CV', location:'Ridge Path · km 2.8',  status:'safe', lastSeen:'4 min ago' },
  { name:'Marco Reyes',  initials:'MR', location:'Ridge Path · km 3.1',  status:'safe', lastSeen:'3 min ago' },
];
const statusClass = { safe:'ts-safe', moving:'ts-moving', behind:'ts-behind' };

function renderTrackList(selectedIdx = -1) {
  const el = document.getElementById('trackList');
  if (!el) return;
  el.innerHTML = '';
  hikers.forEach((h, i) => {
    const div = document.createElement('div');
    div.className = 'track-item' + (i === selectedIdx ? ' selected' : '');
    div.innerHTML = `
      <div class="track-avatar">${h.initials}</div>
      <div class="track-info">
        <div class="track-name">${h.name}</div>
        <div class="track-location"><i class="fas fa-location-dot" style="font-size:0.55rem;margin-right:3px;"></i>${h.location}</div>
        <div class="track-location" style="margin-top:2px;"><i class="fas fa-clock" style="font-size:0.55rem;margin-right:3px;"></i>${h.lastSeen}</div>
      </div>
      <div class="track-status-dot ${statusClass[h.status]}"></div>`;
    div.addEventListener('click', () => selectHiker(i));
    el.appendChild(div);
  });
}

function selectHiker(i) {
  renderTrackList(i);
  showToast(`📍 ${hikers[i].name} — ${hikers[i].location}`);
}

function updateMapTime() {
  const d = new Date();
  const t = d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
  const timeSpan = document.getElementById('mapTime');
  const panelSpan = document.getElementById('panelTime');
  if (timeSpan) timeSpan.textContent = t;
  if (panelSpan) panelSpan.textContent = 'Updated ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
}
updateMapTime(); 
setInterval(updateMapTime, 1000);

function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

renderTrackList();
</script>
</body>
</html>