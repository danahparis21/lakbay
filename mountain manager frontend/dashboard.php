<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY Manager — Dashboard</title>
<link rel="stylesheet" href="manager.css">
<style>
/* ── HERO BANNER ── */
.hero-banner {
  background: var(--ink);
  border-radius: var(--r);
  padding: 28px 32px;
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 24px;
  position: relative;
  overflow: hidden;
}
.hero-banner::before {
  content: '';
  position: absolute;
  right: -40px; top: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(201,168,76,0.08);
}
.hero-banner::after {
  content: '';
  position: absolute;
  right: 120px; bottom: -60px;
  width: 140px; height: 140px;
  border-radius: 50%;
  background: rgba(255,255,255,0.03);
}
.hero-greeting { font-size: 12px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
.hero-name { font-family: 'Playfair Display', serif; font-size: clamp(20px,3vw,28px); font-weight: 700; color: var(--white); margin-bottom: 10px; }
.hero-mountains { display: flex; gap: 8px; flex-wrap: wrap; }
.hero-mtn-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 50px; padding: 6px 14px;
  font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.85);
  backdrop-filter: blur(4px);
}
.hero-mtn-badge svg { width: 12px; height: 12px; stroke: var(--gold); stroke-width: 2; }
.hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; position: relative; z-index: 1; }
.hero-date { font-family: 'DM Mono', monospace; font-size: 11px; color: rgba(255,255,255,0.4); }
.hero-quick-stat { text-align: right; }
.hero-qs-val { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: var(--gold); line-height: 1; }
.hero-qs-lbl { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 2px; letter-spacing: .8px; }
@media (max-width: 600px) { .hero-banner { grid-template-columns: 1fr; } .hero-right { align-items: flex-start; } }

/* ── MOUNTAIN OVERVIEW CARDS ── */
.mtn-overview-card {
  background: var(--white);
  border-radius: var(--r);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
}
.mtn-overview-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.mtn-card-hero {
  height: 120px;
  background-size: cover;
  background-position: center;
  position: relative;
}
.mtn-card-hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to bottom, rgba(16,6,0,0.2), rgba(16,6,0,0.75));
  display: flex; align-items: flex-end; padding: 14px;
}
.mtn-card-name { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: white; }
.mtn-card-sub { font-size: 11px; color: rgba(255,255,255,0.65); margin-top: 2px; }
.mtn-card-body { padding: 16px; }
.mtn-card-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.mtn-mini-stat { text-align: center; background: var(--off); border-radius: 9px; padding: 10px 8px; }
.mtn-mini-val { font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700; color: var(--ink); }
.mtn-mini-lbl { font-size: 9px; color: var(--ink3); margin-top: 2px; text-transform: uppercase; letter-spacing: .8px; }
.mtn-fee-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
.mtn-fee-row:last-child { border-bottom: none; }

/* ── RECENT BOOKINGS MINI ── */
.booking-mini {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 0; border-bottom: 1px solid var(--border);
}
.booking-mini:last-child { border-bottom: none; }
.booking-mini-num {
  font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 700;
  color: var(--ink); background: var(--off); border-radius: 6px;
  padding: 4px 8px; flex-shrink: 0;
}
.booking-mini-info { flex: 1; min-width: 0; }
.booking-mini-mountain { font-size: 13px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.booking-mini-meta { font-size: 11px; color: var(--ink3); margin-top: 1px; }
.booking-mini-right { text-align: right; flex-shrink: 0; }
.booking-mini-fee { font-size: 12px; font-weight: 700; color: var(--ink); font-family: 'DM Mono', monospace; }

/* ── COLLECTION RING ── */
.collection-section { display: flex; align-items: center; gap: 20px; padding: 20px 0; }
.collection-ring { flex-shrink: 0; }
.collection-breakdown { flex: 1; }
.collection-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.collection-row:last-child { border-bottom: none; }
.collection-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }

/* ── QUICK ACTIONS ── */
.quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.qa-btn {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 18px 10px; background: var(--off); border-radius: 14px;
  border: 1.5px solid var(--border); cursor: pointer; transition: all .15s;
  text-decoration: none; color: var(--ink); text-align: center;
}
.qa-btn:hover { background: var(--ink); color: var(--white); border-color: var(--ink); transform: translateY(-2px); box-shadow: var(--shadow); }
.qa-btn:hover .qa-icon { background: rgba(255,255,255,0.1); }
.qa-btn:hover svg { stroke: var(--white); }
.qa-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--white); display: flex; align-items: center; justify-content: center; transition: .15s; }
.qa-icon svg { width: 18px; height: 18px; stroke: var(--ink); stroke-width: 1.8; }
.qa-label { font-size: 12px; font-weight: 600; line-height: 1.3; }
@media (max-width: 640px) { .quick-actions { grid-template-columns: repeat(2, 1fr); } }
</style>
</head>
<body>
<div class="app-shell">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a href="dashboard.php" class="sidebar-brand">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity=".9"/><path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity=".35"/></svg>
    </div>
    <div class="sidebar-brand-text">
      <div class="sidebar-app-name">LAKBAY</div>
      <div class="sidebar-app-sub">Manager Portal</div>
    </div>
  </a>

  <div class="mountain-badge" id="mtnBadge">
    <div class="mountain-badge-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>
    </div>
    <div class="mountain-badge-text" id="mtnBadgeText">
      <div class="mountain-badge-name">Loading...</div>
      <div class="mountain-badge-role">Your Mountains</div>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span class="nav-text">Dashboard</span>
    </a>
    <a href="bookings_manager.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="nav-text">Bookings</span>
    </a>
    <a href="payments.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      <span class="nav-text">Payments</span>
    </a>
    <a href="hikers.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="nav-text">Hikers</span>
    </a>
    <div class="nav-divider"></div>
    <div class="nav-label">Mountain</div>
    <a href="guides.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span class="nav-text">Guides</span>
    </a>
    <a href="reports.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span class="nav-text">Reports</span>
    </a>
    <a href="advisories.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span class="nav-text">Advisories</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-footer-avatar" id="sfAvatar"></div>
    <div class="sidebar-footer-text">
      <div class="sidebar-footer-name" id="sfName"></div>
      <div class="sidebar-footer-role" id="sfRole"></div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main-area" id="mainArea">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Dashboard</div>
        <div class="topbar-page-sub" id="topbarSub">Overview of your mountains</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="notif-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
      <div class="topbar-user">
        <div class="topbar-avatar" id="topbarAvatar"></div>
        <div class="topbar-user-name" id="topbarName"></div>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content" id="dashContent"></div>
</div>
</div>

<div class="toast" id="toast"></div>

<!-- DATA -->
<?php include 'manager_data.php'; ?>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('mainArea').classList.toggle('expanded');
}

function updateClock() {
  const d = new Date();
  document.getElementById('topbarDate').textContent =
    d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase()
    + ' ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
setInterval(updateClock, 1000); updateClock();

function initUI() {
  const myMtns = getMyMountains();
  // Sidebar badge
  document.getElementById('mtnBadgeText').innerHTML = `
    <div class="mountain-badge-name">${myMtns.map(m=>m.name.replace('Mt. ','')).join(' & ')}</div>
    <div class="mountain-badge-role">Managing ${myMtns.length} mountain${myMtns.length>1?'s':''}</div>`;
  // Topbar
  document.getElementById('topbarAvatar').textContent = MANAGER.initials;
  document.getElementById('topbarName').textContent = MANAGER.name.split(' ')[0];
  document.getElementById('topbarSub').textContent = myMtns.map(m=>m.name).join(' · ');
  // Footer
  document.getElementById('sfAvatar').textContent = MANAGER.initials;
  document.getElementById('sfName').textContent = MANAGER.name;
  document.getElementById('sfRole').textContent = MANAGER.role;
  renderDashboard();
}

function renderDashboard() {
  const bookings = getMyBookings();
  const myMtns   = getMyMountains();
  const now = new Date();

  const totalBookings   = bookings.length;
  const pendingCount    = bookings.filter(b=>b.status==='pending').length;
  const confirmedCount  = bookings.filter(b=>b.status==='confirmed').length;
  const completedCount  = bookings.filter(b=>b.status==='completed').length;
  const totalRevExpected= bookings.filter(b=>b.status!=='cancelled').reduce((s,b)=>s+b.totalFee,0);
  const totalHikers     = bookings.filter(b=>b.status!=='cancelled').reduce((s,b)=>s+b.pax,0);

  // Payment stats across all bookings
  let paidReg=0, totalReg=0, paidEnv=0, totalEnv=0;
  bookings.filter(b=>b.status!=='cancelled').forEach(b=>{
    const mtn = ALL_MOUNTAINS.find(m=>m.id===b.mountainId);
    if(!mtn) return;
    b.hikers.forEach((h,i)=>{
      totalReg++;
      if(getPayStatus(b.id,i,'reg')==='paid') paidReg++;
      if(mtn.fees.env>0){ totalEnv++; if(getPayStatus(b.id,i,'env')==='paid') paidEnv++; }
    });
  });

  const collPct = totalReg > 0 ? Math.round((paidReg/totalReg)*100) : 0;

  document.getElementById('dashContent').innerHTML = `

    <!-- Hero Banner -->
    <div class="hero-banner">
      <div>
        <div class="hero-greeting">Good ${hour()}, Manager</div>
        <div class="hero-name">${MANAGER.name}</div>
        <div class="hero-mountains">
          ${myMtns.map(m=>`
            <div class="hero-mtn-badge">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>
              ${m.name}
            </div>`).join('')}
        </div>
      </div>
      <div class="hero-right">
        <div class="hero-date" id="heroClock"></div>
        <div class="hero-quick-stat">
          <div class="hero-qs-val">${totalHikers}</div>
          <div class="hero-qs-lbl">Total Hikers</div>
        </div>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon ink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="stat-val">${totalBookings}</div><div class="stat-label">Total Bookings</div></div>
        <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 15 12 9 6 15"/></svg> All mountains</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><div class="stat-val">${pendingCount}</div><div class="stat-label">Pending Response</div></div>
        <div class="stat-change ${pendingCount>2?'down':'up'}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="${pendingCount>2?'18 15 12 9 6 15':'18 9 12 15 6 9'}"/></svg>
          ${pendingCount>0 ? 'Needs attention' : 'All clear'}
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div><div class="stat-val">${confirmedCount + completedCount}</div><div class="stat-label">Active + Done</div></div>
        <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 9 12 15 6 9"/></svg> ${confirmedCount} confirmed · ${completedCount} completed</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div><div class="stat-val" style="font-size:22px;">${fmtMoney(totalRevExpected)}</div><div class="stat-label">Expected Revenue</div></div>
        <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 9 12 15 6 9"/></svg> Active bookings only</div>
      </div>
    </div>

    <!-- Mountains + Activity -->
    <div class="two-col">

      <!-- Mountain Overview Cards -->
      <div>
        <div style="font-size:12px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>
          Your Mountains
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
          ${myMtns.map(m => {
            const mb = bookings.filter(b=>b.mountainId===m.id&&b.status!=='cancelled');
            const pending = mb.filter(b=>b.status==='pending').length;
            const confirmed = mb.filter(b=>b.status==='confirmed').length;
            const hikerCount = mb.reduce((s,b)=>s+b.pax,0);
            const imgs = {1:'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=60',2:'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=60',3:'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&q=60',4:'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=600&q=60'};
            return `
              <div class="mtn-overview-card">
                <div class="mtn-card-hero" style="background-image:url('${imgs[m.id]}')">
                  <div class="mtn-card-hero-overlay">
                    <div>
                      <div class="mtn-card-name">${m.name}</div>
                      <div class="mtn-card-sub">${m.location} · ${m.elevation} · <span class="badge badge-${m.difficulty}" style="font-size:9px;padding:2px 7px;">${m.difficulty}</span></div>
                    </div>
                  </div>
                </div>
                <div class="mtn-card-body">
                  <div class="mtn-card-stats">
                    <div class="mtn-mini-stat"><div class="mtn-mini-val">${mb.length}</div><div class="mtn-mini-lbl">Bookings</div></div>
                    <div class="mtn-mini-stat"><div class="mtn-mini-val">${hikerCount}</div><div class="mtn-mini-lbl">Hikers</div></div>
                    <div class="mtn-mini-stat" style="${pending>0?'background:#fff8e1;':''}">
                      <div class="mtn-mini-val" style="${pending>0?'color:var(--amber);':''}">${pending}</div>
                      <div class="mtn-mini-lbl">Pending</div>
                    </div>
                  </div>
                  <div class="mtn-fee-row"><span style="font-size:12px;color:var(--ink3);">Registration fee</span><span style="font-size:12px;font-weight:700;font-family:'DM Mono',monospace;">${fmtMoney(m.fees.reg)}/head</span></div>
                  ${m.fees.env>0?`<div class="mtn-fee-row"><span style="font-size:12px;color:var(--ink3);">Environmental fee</span><span style="font-size:12px;font-weight:700;font-family:'DM Mono',monospace;">${fmtMoney(m.fees.env)}/head</span></div>`:''}
                  <div style="margin-top:12px;display:flex;gap:8px;">
                    <a href="bookings_manager.php?mtn=${m.id}" class="btn btn-primary btn-sm btn-full">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      View Bookings
                    </a>
                    <a href="payments.php?mtn=${m.id}" class="btn btn-outline btn-sm">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                      Fees
                    </a>
                  </div>
                </div>
              </div>`;
          }).join('')}
        </div>
      </div>

      <!-- Right column: Collection + Recent + Activity -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Payment Collection Ring -->
        <div class="panel">
          <div class="panel-hdr">
            <div class="panel-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              Fee Collection Rate
            </div>
          </div>
          <div class="panel-body">
            <div class="collection-section">
              <div class="collection-ring">
                <div class="ring-wrap">
                  <svg width="80" height="80" viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="33" stroke="#f4f1ec" stroke-width="8" fill="none"/>
                    <circle cx="40" cy="40" r="33" stroke="#100600" stroke-width="8" fill="none"
                      stroke-dasharray="${Math.round(2*Math.PI*33)}" stroke-dashoffset="${Math.round(2*Math.PI*33*(1-collPct/100))}"
                      stroke-linecap="round" style="transition:stroke-dashoffset .8s ease;"/>
                  </svg>
                  <div class="ring-val">${collPct}%</div>
                </div>
              </div>
              <div class="collection-breakdown">
                <div class="collection-row">
                  <span><span class="collection-dot" style="background:var(--green);"></span>Registration collected</span>
                  <span style="font-weight:700;font-size:13px;">${paidReg}/${totalReg}</span>
                </div>
                ${totalEnv>0?`<div class="collection-row">
                  <span><span class="collection-dot" style="background:var(--amber);"></span>Environmental collected</span>
                  <span style="font-weight:700;font-size:13px;">${paidEnv}/${totalEnv}</span>
                </div>`:''}
                <div class="collection-row">
                  <span><span class="collection-dot" style="background:var(--ink4);"></span>Outstanding</span>
                  <span style="font-weight:700;font-size:13px;">${totalReg-paidReg+(totalEnv-paidEnv)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Bookings -->
        <div class="panel">
          <div class="panel-hdr">
            <div class="panel-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Recent Bookings
            </div>
            <a href="bookings_manager.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <div class="panel-body" style="padding-top:8px;">
            ${bookings.slice(-5).reverse().map(b=>`
              <div class="booking-mini">
                <div class="booking-mini-num">${b.id}</div>
                <div class="booking-mini-info">
                  <div class="booking-mini-mountain">${b.mountain}</div>
                  <div class="booking-mini-meta">${fmtDate(b.date)} · ${b.pax} hiker${b.pax>1?'s':''} · ${b.guideName.split(' ')[0]}</div>
                </div>
                <div class="booking-mini-right">
                  <div class="badge badge-${b.status}">${b.status}</div>
                  <div class="booking-mini-fee">${fmtMoney(b.totalFee)}</div>
                </div>
              </div>`).join('')}
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="panel">
          <div class="panel-hdr">
            <div class="panel-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
              Quick Actions
            </div>
          </div>
          <div class="panel-body">
            <div class="quick-actions">
              <a href="bookings_manager.php" class="qa-btn">
                <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
                <div class="qa-label">Search Booking</div>
              </a>
              <a href="payments.php" class="qa-btn">
                <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="qa-label">Manage Fees</div>
              </a>
              <a href="hikers.php" class="qa-btn">
                <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                <div class="qa-label">View Hikers</div>
              </a>
              <a href="advisories.php" class="qa-btn">
                <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div>
                <div class="qa-label">Advisories</div>
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>
  `;

  // Live clock in hero
  setInterval(()=>{
    const el=document.getElementById('heroClock');
    if(el) el.textContent=new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  },1000);
}

function hour() {
  const h=new Date().getHours();
  return h<12?'morning':h<17?'afternoon':'evening';
}

initUI();
</script>
</body>
</html>
