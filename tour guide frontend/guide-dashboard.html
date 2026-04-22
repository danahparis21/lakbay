<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Dashboard</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* ── BACKGROUND MESH ── */
    .guide-content {
      background:
        radial-gradient(ellipse 60% 40% at 80% 10%, rgba(16,6,0,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 10% 80%, rgba(16,6,0,0.03) 0%, transparent 60%);
    }

    /* ── STATS ROW ── */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 18px 20px;
      box-shadow: var(--glass-shadow);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 36px rgba(0,0,0,0.09);
    }
    .stat-card-label {
      font-size: 0.62rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--ink-4);
      margin-bottom: 8px;
    }
    .stat-card-val {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
      letter-spacing: -1px;
    }
    .stat-card-sub {
      font-size: 0.68rem;
      color: var(--ink-4);
      margin-top: 5px;
    }
    .stat-card-icon {
      width: 30px; height: 30px;
      border-radius: var(--r-sm);
      background: var(--primary-soft);
      color: var(--primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem;
      margin-bottom: 12px;
    }

    /* ── BOOKINGS ── */
    .booking-list { display: flex; flex-direction: column; gap: 10px; }
    .booking-item {
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
    }
    .booking-item:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-1px);
      background: white;
    }
    .booking-mountain-thumb {
      width: 48px; height: 48px;
      border-radius: var(--r-md);
      background-size: cover;
      background-position: center;
      flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    }
    .booking-info { flex: 1; min-width: 0; }
    .booking-name { font-size: 0.87rem; font-weight: 600; color: var(--ink); }
    .booking-meta {
      font-size: 0.7rem;
      color: var(--ink-4);
      margin-top: 3px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .booking-meta i { color: var(--ink-5); }
    .booking-actions { display: flex; gap: 6px; align-items: center; }

    /* ── STATUS PANEL ── */
    .status-panel {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      -webkit-backdrop-filter: var(--glass-blur);
      border: 1px solid var(--glass-border);
      border-radius: var(--r-xl);
      padding: 18px 20px;
      margin-bottom: 24px;
      box-shadow: var(--glass-shadow);
    }
    .status-panel-title {
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--ink-4);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .status-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .status-chip {
      padding: 7px 16px;
      border-radius: 40px;
      border: 1.5px solid var(--line);
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--ink-3);
      cursor: pointer;
      transition: all 0.15s;
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,0.5);
      backdrop-filter: blur(6px);
    }
    .status-chip:hover { border-color: var(--primary); color: var(--primary); background: rgba(255,255,255,0.8); }
    .status-chip.active-safe  { background: var(--green-lt); border-color: var(--green); color: var(--green); }
    .status-chip.active-trail { background: var(--amber-lt); border-color: var(--amber); color: var(--amber); }
    .status-chip.active-done  { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }

    /* ── ANNOUNCEMENTS ── */
    .announcement-card {
      background: rgba(255,255,255,0.65);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.55);
      border-radius: var(--r-lg);
      padding: 14px 16px;
      margin-bottom: 10px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.04);
      transition: all 0.18s;
    }
    .announcement-card:last-child { margin-bottom: 0; }
    .announcement-card:hover {
      background: white;
      box-shadow: var(--shadow-md);
      transform: translateY(-1px);
    }
    .announcement-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 7px;
    }
    .announcement-icon {
      width: 26px; height: 26px;
      border-radius: var(--r-sm);
      background: var(--primary);
      color: white;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.65rem;
      flex-shrink: 0;
    }
    .announcement-from {
      font-size: 0.68rem;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .announcement-time {
      font-family: 'DM Mono', monospace;
      font-size: 0.6rem;
      color: var(--ink-5);
      margin-left: auto;
    }
    .announcement-text {
      font-size: 0.8rem;
      color: var(--ink-2);
      line-height: 1.55;
    }

    /* ── DASHBOARD GRID ── */
    .dash-grid {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 20px;
    }
    .dash-col { display: flex; flex-direction: column; gap: 20px; }

    /* ── EMERGENCY BUTTON ── */
    .emergency-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      background: rgba(184,49,42,0.9);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.18);
      color: white;
      border-radius: var(--r-lg);
      padding: 14px 22px;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.18s;
      box-shadow: 0 4px 20px rgba(184,49,42,0.4);
    }
    .emergency-btn:hover {
      background: var(--red);
      transform: scale(1.03);
      box-shadow: 0 8px 28px rgba(184,49,42,0.5);
    }
    .emergency-btn i { font-size: 1.4rem; }
    .emergency-btn span { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }

    /* Live badge */
    .live-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: rgba(27,112,69,0.1);
      border: 1px solid rgba(27,112,69,0.2);
      color: var(--green);
      padding: 3px 10px;
      border-radius: 40px;
      font-size: 0.63rem;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .live-dot {
      width: 5px; height: 5px;
      background: var(--green);
      border-radius: 50%;
      animation: pulse-dot 2s infinite;
    }
    @keyframes pulse-dot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(0.7); }
    }

    @media (max-width: 1024px) { .dash-grid { grid-template-columns: 1fr; } }
    @media (max-width: 640px) {
      .stats-row { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 440px) {
      .stats-row { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
<div class="guide-app">

  <!-- SIDEBAR -->
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none">
        <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity="0.9"/>
        <path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity="0.3"/>
      </svg>
      <div>
        <div class="sidebar-logo-text">LAKBAY</div>
        <div class="sidebar-logo-sub">Guide Portal</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.html" class="active"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.html"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.html"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.html"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <div class="sidebar-section-label">Account</div>
      <ul>
        <li><a href="guide-profile.html"><i class="fas fa-circle-user"></i> My Profile</a></li>
      </ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar">JD</div>
      <div class="sidebar-profile-info">
        <div class="sidebar-profile-name">John Dela Cruz</div>
        <div class="sidebar-profile-role">Senior Trail Guide</div>
      </div>
      <button style="background:none;border:none;color:var(--ink-5);font-size:0.72rem;padding:4px;cursor:pointer;transition:color 0.15s;" title="Logout" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--ink-5)'">
        <i class="fas fa-right-from-bracket"></i>
      </button>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-right">
        <div class="topbar-time" id="liveTime"></div>
        <button class="topbar-icon-btn" title="Notifications"><i class="fas fa-bell"></i></button>
      </div>
    </div>

    <div class="guide-content">

      <!-- GREETING CARD -->
      <div class="greeting-card">
        <div class="greeting-row">
          <div class="greeting-left">
            <div class="greeting-eyebrow"><i class="fas fa-mountain" style="margin-right:5px;opacity:0.6;"></i>Guide Portal</div>
            <div class="greeting-title">Good morning,<br><span class="greeting-name">John 👋</span></div>
            <div class="greeting-message">You have <strong style="color:white;font-weight:600;">3 upcoming hikes</strong> this week</div>
            <div class="greeting-glass-pill">
              <i class="fas fa-calendar-check" style="font-size:0.75rem;opacity:0.8;"></i>
              Next: Mt. Batulao · Apr 25 · 5:00 AM
            </div>
            <div class="greeting-date" id="welcomeDate">
              <i class="fas fa-clock" style="font-size:0.6rem;"></i>
            </div>
          </div>
          <div class="greeting-right">
            <button class="emergency-btn" id="emergencyBtn">
              <i class="fas fa-triangle-exclamation"></i>
              <span>Emergency</span>
            </button>
          </div>
        </div>
      </div>

      <!-- STATS -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
          <div class="stat-card-label">Upcoming</div>
          <div class="stat-card-val">3</div>
          <div class="stat-card-sub">bookings this week</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-person-hiking"></i></div>
          <div class="stat-card-label">Total Hikers</div>
          <div class="stat-card-val">11</div>
          <div class="stat-card-sub">across all bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fas fa-mountain"></i></div>
          <div class="stat-card-label">Mountains</div>
          <div class="stat-card-val">2</div>
          <div class="stat-card-sub">assigned this month</div>
        </div>
      </div>

      <div class="dash-grid">
        <div class="dash-col">

          <!-- STATUS UPDATE -->
          <div class="status-panel">
            <div class="status-panel-title">
              <span style="width:6px;height:6px;background:var(--green);border-radius:50%;display:inline-block;box-shadow:0 0 0 3px var(--green-lt);"></span>
              Current Trail Status
            </div>
            <div class="status-chips">
              <button class="status-chip active-safe" id="sc-safe" onclick="setStatus('safe')">
                <i class="fas fa-shield-check"></i> Safe — At Basecamp
              </button>
              <button class="status-chip" id="sc-trail" onclick="setStatus('trail')">
                <i class="fas fa-person-hiking"></i> On Trail
              </button>
              <button class="status-chip" id="sc-done" onclick="setStatus('done')">
                <i class="fas fa-flag-checkered"></i> Hike Completed
              </button>
            </div>
          </div>

          <!-- UPCOMING BOOKINGS -->
          <div>
            <div class="section-header">
              <div class="section-title">Upcoming Bookings</div>
              <a href="guide-communication.html" class="see-all">See all <i class="fas fa-arrow-right" style="font-size:0.6rem;"></i></a>
            </div>
            <div class="booking-list" id="bookingList"></div>
          </div>

        </div>

        <!-- RIGHT COL -->
        <div class="dash-col">
          <div>
            <div class="section-header">
              <div class="section-title">Admin Broadcasts</div>
              <div class="live-badge"><span class="live-dot"></span> Live</div>
            </div>
            <div id="announcementList"></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- BOTTOM NAV -->
  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.html" class="bnav-item active"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.html" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.html" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-safety.html" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
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
      <p style="font-size:0.82rem;color:var(--ink-3);margin-bottom:16px;line-height:1.65;background:var(--red-lt);padding:10px 14px;border-radius:var(--r-md);border-left:3px solid var(--red);">This will immediately notify the admin and all emergency contacts.</p>
      <div class="form-group">
        <label class="form-label">Situation Type</label>
        <select class="form-control">
          <option>— Select —</option>
          <option>Medical Emergency</option>
          <option>Lost Hiker</option>
          <option>Weather Hazard</option>
          <option>Trail Accident</option>
          <option>Wildlife Encounter</option>
          <option>Other</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Location / Trail Point</label>
        <input type="text" class="form-control" placeholder="e.g. Ridge junction, km 3.5">
      </div>
      <div class="form-group">
        <label class="form-label">Details</label>
        <textarea class="form-control" rows="3" placeholder="Describe the emergency…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('emergencyModal')">Cancel</button>
      <button class="btn btn-danger" onclick="sendEmergency()"><i class="fas fa-paper-plane"></i> Send Alert</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const bookings = [
  { id:'BK-001', hiker:'Lea Santiago', mountain:'Mt. Batulao', date:'Apr 25', time:'5:00 AM', pax:3, status:'confirmed', photo:'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg?auto=compress&cs=tinysrgb&w=200' },
  { id:'BK-002', hiker:'Ben Torres', mountain:'Mt. Talamitam', date:'Apr 27', time:'6:00 AM', pax:5, status:'pending', photo:'https://images.pexels.com/photos/417074/pexels-photo-417074.jpeg?auto=compress&cs=tinysrgb&w=200' },
  { id:'BK-003', hiker:'Nina Cruz', mountain:'Mt. Batulao', date:'Apr 30', time:'5:30 AM', pax:2, status:'confirmed', photo:'https://images.pexels.com/photos/248797/pexels-photo-248797.jpeg?auto=compress&cs=tinysrgb&w=200' },
];
const announcements = [
  { from:'Admin', time:'9:41 AM', text:'Trail condition update: Mt. Batulao ridge trail has some slippery sections due to last night\'s rain. Extra caution advised.' },
  { from:'Admin', time:'Yesterday', text:'Reminder: All guides must submit post-hike safety reports within 2 hours of completion.' },
  { from:'Admin', time:'Apr 21', text:'New booking system update: Hikers can now upload their own medical disclosure forms. Please review before hikes.' },
];

const statusBadge = {
  confirmed: '<span class="badge badge-green"><i class="fas fa-circle" style="font-size:0.35rem;"></i> Confirmed</span>',
  pending:   '<span class="badge badge-amber"><i class="fas fa-circle" style="font-size:0.35rem;"></i> Pending</span>'
};

function renderBookings() {
  const el = document.getElementById('bookingList');
  el.innerHTML = '';
  bookings.forEach(b => {
    const div = document.createElement('div');
    div.className = 'booking-item';
    div.innerHTML = `
      <div class="booking-mountain-thumb" style="background-image:url('${b.photo}');"></div>
      <div class="booking-info">
        <div class="booking-name">${b.hiker}</div>
        <div class="booking-meta">
          <span><i class="fas fa-mountain"></i> ${b.mountain}</span>
          <span><i class="fas fa-calendar"></i> ${b.date} · ${b.time}</span>
          <span><i class="fas fa-users"></i> ${b.pax} pax</span>
        </div>
        <div style="margin-top:6px;">${statusBadge[b.status]}</div>
      </div>
      <div class="booking-actions">
        <button class="btn btn-ghost btn-sm btn-icon" onclick="viewBooking('${b.id}')" title="View"><i class="fas fa-eye"></i></button>
      </div>`;
    el.appendChild(div);
  });
}

function renderAnnouncements() {
  const el = document.getElementById('announcementList');
  el.innerHTML = '';
  announcements.forEach(a => {
    const div = document.createElement('div');
    div.className = 'announcement-card';
    div.innerHTML = `
      <div class="announcement-header">
        <div class="announcement-icon"><i class="fas fa-bullhorn"></i></div>
        <span class="announcement-from">${a.from}</span>
        <span class="announcement-time">${a.time}</span>
      </div>
      <div class="announcement-text">${a.text}</div>`;
    el.appendChild(div);
  });
}

let currentStatus = 'safe';
function setStatus(s) {
  currentStatus = s;
  document.getElementById('sc-safe').className  = 'status-chip' + (s==='safe'  ? ' active-safe'  : '');
  document.getElementById('sc-trail').className = 'status-chip' + (s==='trail' ? ' active-trail' : '');
  document.getElementById('sc-done').className  = 'status-chip' + (s==='done'  ? ' active-done'  : '');
  const labels = { safe:'Safe — At Basecamp', trail:'On Trail', done:'Hike Completed' };
  showToast(`Status updated: ${labels[s]}`);
}

document.getElementById('emergencyBtn').addEventListener('click', () => openModal('emergencyModal'));
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) closeModal(o.id); }));
function sendEmergency() { closeModal('emergencyModal'); showToast('🚨 Emergency alert sent to admin!'); }
function viewBooking(id) { showToast(`Opening booking ${id}…`); }

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

function updateTime() {
  const d  = new Date();
  const t  = d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
  const dt = d.toLocaleDateString('en-PH', {weekday:'long', month:'long', day:'numeric', year:'numeric'});
  document.getElementById('liveTime').textContent = t;
  const dateEl = document.getElementById('welcomeDate');
  dateEl.innerHTML = `<i class="fas fa-clock" style="font-size:0.6rem;"></i> ${dt}`;
}
updateTime(); setInterval(updateTime, 1000);
renderBookings();
renderAnnouncements();
</script>
</body>
</html>