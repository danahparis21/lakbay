<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LAKBAY Manager — Payments</title>
<link rel="stylesheet" href="manager.css">
<style>
/* ── PAGE-SPECIFIC ── */
.pay-stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
@media(max-width:900px){ .pay-stats-grid{grid-template-columns:repeat(2,1fr);} }
@media(max-width:480px){ .pay-stats-grid{grid-template-columns:1fr;} }

/* Big stat cards */
.big-stat {
  background:var(--white); border-radius:var(--r);
  border:1px solid var(--border); box-shadow:var(--shadow);
  padding:22px; display:flex; flex-direction:column; gap:10px;
  position:relative; overflow:hidden; cursor:pointer;
  transition:transform .2s, box-shadow .2s;
}
.big-stat:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.big-stat::after {
  content:''; position:absolute; bottom:-20px; right:-20px;
  width:80px; height:80px; border-radius:50%;
  background:var(--accent-color,var(--ink)); opacity:.05;
}
.big-stat-icon {
  width:42px; height:42px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
}
.big-stat-icon svg { width:20px; height:20px; stroke:currentColor; stroke-width:2; }
.big-stat-num { font-family:'Playfair Display',serif; font-size:30px; font-weight:700; color:var(--ink); line-height:1; }
.big-stat-label { font-size:12px; color:var(--ink3); font-weight:500; }
.big-stat-bar { height:4px; border-radius:2px; background:rgba(16,6,0,.08); overflow:hidden; margin-top:4px; }
.big-stat-bar-fill { height:100%; border-radius:2px; transition:width .8s cubic-bezier(.4,0,.2,1); }

/* Progress ring */
.ring-container { display:flex; align-items:center; gap:20px; }
.ring-svg { transform:rotate(-90deg); }
.ring-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.ring-pct { font-family:'DM Mono',monospace; font-size:18px; font-weight:700; color:var(--ink); }
.ring-sub { font-size:9px; color:var(--ink3); text-transform:uppercase; letter-spacing:.8px; }

/* Payment table */
.pay-table-wrap { overflow-x:auto; }
.pay-table { width:100%; border-collapse:separate; border-spacing:0; }
.pay-table thead tr { background:var(--off); }
.pay-table th {
  padding:10px 14px; text-align:left;
  font-size:10px; font-weight:700; color:var(--ink3);
  text-transform:uppercase; letter-spacing:1px;
  border-bottom:2px solid var(--border); white-space:nowrap;
  position:sticky; top:0; background:var(--off); z-index:2;
}
.pay-table td { padding:0; border-bottom:1px solid var(--border); vertical-align:middle; }
.pay-row { display:flex; align-items:center; padding:12px 14px; gap:12px; transition:background .12s; cursor:pointer; }
.pay-row:hover { background:rgba(16,6,0,.02); }

/* Hiker avatar */
.hk-av {
  width:32px; height:32px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:11px; font-weight:700;
  background:var(--ink); color:var(--gold);
}
.hk-av.organizer { background:var(--gold); color:var(--ink); }

/* Fee status badge */
.fee-badge {
  display:inline-flex; align-items:center; gap:5px;
  padding:5px 12px; border-radius:50px; font-size:11px; font-weight:700;
  cursor:pointer; transition:all .15s; border:1.5px solid transparent;
  white-space:nowrap; user-select:none;
}
.fee-badge svg { width:10px; height:10px; stroke:currentColor; stroke-width:2.5; flex-shrink:0; }
.fee-badge.paid   { background:var(--green-bg); color:var(--green); border-color:rgba(46,125,50,.25); }
.fee-badge.unpaid { background:var(--red-bg);   color:var(--red);   border-color:rgba(198,40,40,.25); }
.fee-badge.waived { background:var(--blue-bg);  color:var(--blue);  border-color:rgba(21,101,192,.25); }
.fee-badge:hover { filter:brightness(.93); transform:scale(.97); }

/* Click-cycle tooltip */
.cycle-hint { font-size:10px; color:var(--ink4); font-style:italic; }

/* Booking group header */
.booking-group-hdr {
  background:linear-gradient(135deg,var(--ink) 0%,#2a1a0a 100%);
  padding:14px 18px; display:flex; align-items:center; gap:14px; cursor:pointer;
  user-select:none; transition:opacity .15s;
}
.booking-group-hdr:hover { opacity:.93; }
.bgh-id { font-family:'DM Mono',monospace; font-size:13px; font-weight:700; color:var(--gold); background:rgba(201,168,76,.15); padding:4px 10px; border-radius:6px; }
.bgh-mtn { font-size:13px; font-weight:700; color:var(--white); flex:1; }
.bgh-meta { font-size:11px; color:rgba(255,255,255,.5); }
.bgh-progress { display:flex; align-items:center; gap:8px; }
.bgh-pbar { width:80px; height:6px; border-radius:3px; background:rgba(255,255,255,.15); overflow:hidden; }
.bgh-pbar-fill { height:100%; border-radius:3px; background:var(--gold); transition:width .6s ease; }
.bgh-pct { font-family:'DM Mono',monospace; font-size:11px; color:var(--gold); min-width:34px; text-align:right; }
.bgh-chevron { color:rgba(255,255,255,.4); transition:transform .25s; }
.bgh-chevron.open { transform:rotate(180deg); }
.bgh-chevron svg { width:15px; height:15px; stroke:currentColor; stroke-width:2; }
.booking-group-body { overflow:hidden; transition:max-height .35s cubic-bezier(.4,0,.2,1), opacity .25s; max-height:2000px; opacity:1; }
.booking-group-body.collapsed { max-height:0; opacity:0; }

/* Bulk action bar */
.bulk-bar {
  background:var(--ink); border-radius:12px; padding:12px 18px;
  display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
  animation:fadeUp .3s ease;
}
.bulk-bar-label { color:var(--white); font-size:13px; font-weight:600; }
.bulk-actions { display:flex; gap:8px; flex-wrap:wrap; }
.bulk-btn {
  padding:7px 16px; border-radius:50px; font-size:12px; font-weight:700;
  cursor:pointer; border:none; transition:all .15s; display:flex; align-items:center; gap:5px;
}
.bulk-btn svg { width:12px; height:12px; stroke:currentColor; stroke-width:2.5; }
.bulk-btn.paid   { background:var(--green); color:white; }
.bulk-btn.unpaid { background:var(--red);   color:white; }
.bulk-btn.waived { background:var(--blue);  color:white; }
.bulk-btn:hover { filter:brightness(1.1); transform:translateY(-1px); }

/* Filter pills */
.filter-pills { display:flex; gap:8px; flex-wrap:wrap; }
.f-pill {
  padding:7px 16px; border-radius:50px; font-size:12px; font-weight:600;
  border:1.5px solid var(--border2); background:var(--white); color:var(--ink3);
  cursor:pointer; transition:all .15s; display:flex; align-items:center; gap:6px;
}
.f-pill svg { width:12px; height:12px; stroke:currentColor; stroke-width:2; }
.f-pill:hover { border-color:var(--ink); color:var(--ink); }
.f-pill.active { background:var(--ink); color:var(--white); border-color:var(--ink); }

/* Mountain tab pills */
.mtn-tabs { display:flex; gap:0; background:var(--off); border-radius:50px; padding:4px; border:1px solid var(--border); }
.mtn-tab {
  padding:8px 20px; border-radius:50px; font-size:13px; font-weight:600;
  cursor:pointer; transition:all .2s; color:var(--ink3); white-space:nowrap;
}
.mtn-tab.active { background:var(--ink); color:var(--white); box-shadow:0 2px 8px rgba(16,6,0,.2); }
.mtn-tab:hover:not(.active) { color:var(--ink); }

/* Summary row at bottom of booking group */
.booking-summary-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 18px; background:rgba(16,6,0,.025);
  border-top:1px solid var(--border); flex-wrap:wrap; gap:8px;
}
.bsr-label { font-size:11px; color:var(--ink3); }
.bsr-val { font-family:'DM Mono',monospace; font-size:13px; font-weight:700; color:var(--ink); }

/* Checkbox */
.cb {
  width:16px; height:16px; border-radius:4px; border:1.5px solid var(--border2);
  background:var(--white); cursor:pointer; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  transition:all .12s; accent-color:var(--ink);
}
input[type=checkbox] { width:16px; height:16px; accent-color:var(--ink); cursor:pointer; flex-shrink:0; }

/* Pulse dot */
.pulse-dot {
  width:8px; height:8px; border-radius:50%; background:var(--red);
  animation:pulse 1.5s infinite;
}
@keyframes pulse { 0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(198,40,40,.4);} 50%{opacity:.7;box-shadow:0 0 0 6px rgba(198,40,40,0);} }
</style>
</head>
<body>
<div class="app-shell">

<!-- SIDEBAR -->
<?php $activePage='payments'; include 'shared_sidebar.php'; ?>

<!-- MAIN -->
<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Payments</div>
        <div class="topbar-page-sub" id="paySubtitle">Fee collection overview</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar" id="topbarAvatar">JR</div>
    </div>
  </div>

  <div class="content" id="payContent">
    <!-- rendered by JS -->
  </div>
</div>

<div class="toast" id="toast"></div>

<?php include 'manager_data.php'; ?>
<script>
/* ─── init ─── */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('mainArea').classList.toggle('expanded');
}
setInterval(()=>{ const el=document.getElementById('topbarDate'); if(el) el.textContent=new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}); },1000);

let activeMtnFilter = 'all';
let activePayFilter = 'all';
let selectedHikers  = new Set(); // "bookingId_hikerIdx"

function init() {
  initSidebar();
  document.getElementById('topbarAvatar').textContent = MANAGER.initials;
  renderPage();
}

/* ─── render entire page ─── */
function renderPage() {
  const myBookings = getMyBookings().filter(b=>b.status!=='cancelled');
  const myMtns     = getMyMountains();

  // Compute global payment stats
  let totalReg=0,paidReg=0,totalEnv=0,paidEnv=0,waivedReg=0,waivedEnv=0;
  myBookings.forEach(b=>{
    const mtn=ALL_MOUNTAINS.find(m=>m.id===b.mountainId);
    b.hikers.forEach((_,i)=>{
      const rs=getPayStatus(b.id,i,'reg'), es=mtn?.fees.env>0?getPayStatus(b.id,i,'env'):null;
      totalReg++;
      if(rs==='paid')   paidReg++;
      if(rs==='waived') waivedReg++;
      if(mtn?.fees.env>0){ totalEnv++; if(es==='paid') paidEnv++; if(es==='waived') waivedEnv++; }
    });
  });
  const unpaidReg = totalReg-paidReg-waivedReg;
  const unpaidEnv = totalEnv-paidEnv-waivedEnv;
  const totalOut  = unpaidReg+unpaidEnv;
  const totalCollected = (paidReg+paidEnv);
  const totalFees = totalReg+totalEnv;
  const collPct   = totalFees>0?Math.round((paidReg+paidEnv)/totalFees*100):0;

  // Expected money collected vs total
  let moneyCollected=0, moneyTotal=0;
  myBookings.forEach(b=>{
    const mtn=ALL_MOUNTAINS.find(m=>m.id===b.mountainId);
    b.hikers.forEach((_,i)=>{
      moneyTotal += (mtn?.fees.reg||0);
      if(getPayStatus(b.id,i,'reg')==='paid') moneyCollected += (mtn?.fees.reg||0);
      if(mtn?.fees.env>0){
        moneyTotal += mtn.fees.env;
        if(getPayStatus(b.id,i,'env')==='paid') moneyCollected += mtn.fees.env;
      }
    });
  });

  const html = `
    <!-- Mountain filter tabs -->
    <div class="anim-fade-up" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div class="mtn-tabs" id="mtnTabs">
        <div class="mtn-tab ${activeMtnFilter==='all'?'active':''}" onclick="setMtnFilter('all')">All Mountains</div>
        ${myMtns.map(m=>`<div class="mtn-tab ${activeMtnFilter==m.id?'active':''}" onclick="setMtnFilter(${m.id})">${m.name}</div>`).join('')}
      </div>
      <div class="filter-pills" id="payFilterPills">
        <div class="f-pill ${activePayFilter==='all'?'active':''}" onclick="setPayFilter('all')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h16"/></svg> All
        </div>
        <div class="f-pill ${activePayFilter==='unpaid'?'active':''}" onclick="setPayFilter('unpaid')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Unpaid
          ${totalOut>0?`<span style="background:var(--red);color:white;border-radius:50px;padding:1px 7px;font-size:10px;">${totalOut}</span>`:''}
        </div>
        <div class="f-pill ${activePayFilter==='paid'?'active':''}" onclick="setPayFilter('paid')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg> Paid
        </div>
        <div class="f-pill ${activePayFilter==='waived'?'active':''}" onclick="setPayFilter('waived')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg> Waived
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="pay-stats-grid">
      ${statCard('Collection Rate', collPct+'%', 'rgba(201,168,76,.12)', 'var(--gold)', collPct, 'd1',
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`)}
      ${statCard('Fees Collected', fmtMoney(moneyCollected), 'var(--green-bg)', 'var(--green)', Math.round(moneyCollected/Math.max(1,moneyTotal)*100), 'd2',
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`)}
      ${statCard('Pending Fees', totalOut+' items', 'var(--red-bg)', 'var(--red)', 100-collPct, 'd3',
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`)}
      ${statCard('Total Hikers', myBookings.reduce((s,b)=>s+b.pax,0)+' hikers', 'rgba(16,6,0,.06)', 'var(--ink)', 100, 'd4',
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`)}
    </div>

    <!-- Bulk action bar -->
    <div id="bulkBar" style="display:none;">
      <div class="bulk-bar">
        <span class="bulk-bar-label" id="bulkLabel">0 hikers selected</span>
        <div class="bulk-actions">
          <button class="bulk-btn paid" onclick="bulkMark('paid')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg> Mark Paid
          </button>
          <button class="bulk-btn unpaid" onclick="bulkMark('unpaid')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/></svg> Mark Unpaid
          </button>
          <button class="bulk-btn waived" onclick="bulkMark('waived')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg> Waive
          </button>
          <button class="bulk-btn" style="background:rgba(255,255,255,.15);color:white;" onclick="clearSelection()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Payment table grouped by booking -->
    <div class="panel anim-fade-up d2" id="payTable"></div>
  `;

  document.getElementById('payContent').innerHTML = html;
  renderPayTable(myBookings);
  // Animate stat bars
  setTimeout(()=>{
    document.querySelectorAll('.big-stat-bar-fill').forEach(el=>{
      el.style.width = el.dataset.pct+'%';
    });
  },100);
}

function statCard(label, val, iconBg, iconColor, pct, delay, iconSvg) {
  return `
    <div class="big-stat anim-fade-up ${delay}" onclick="handleStatClick('${label}')">
      <div class="big-stat-icon" style="background:${iconBg};color:${iconColor};">${iconSvg}</div>
      <div>
        <div class="big-stat-num">${val}</div>
        <div class="big-stat-label">${label}</div>
      </div>
      <div class="big-stat-bar">
        <div class="big-stat-bar-fill" data-pct="${Math.min(100,pct)}" style="width:0%;background:${iconColor};"></div>
      </div>
    </div>`;
}

function handleStatClick(label) {
  if(label.includes('Pending')) setPayFilter('unpaid');
  else if(label.includes('Collected')) setPayFilter('paid');
  else setPayFilter('all');
}

/* ─── booking groups ─── */
function renderPayTable(bookings) {
  const container = document.getElementById('payTable');
  if(!bookings.length){ container.innerHTML=`<div class="empty-state"><div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><h3>No payment records</h3><p>No active bookings found for your mountains.</p></div>`; return; }

  let filteredBookings = bookings;
  if(activeMtnFilter!=='all') filteredBookings = filteredBookings.filter(b=>b.mountainId==activeMtnFilter);

  const groups = filteredBookings.map(b => buildGroup(b)).join('');
  container.innerHTML = groups || `<div class="empty-state"><div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div><h3>No bookings match filters</h3></div>`;
}

function buildGroup(b) {
  const mtn = ALL_MOUNTAINS.find(m=>m.id===b.mountainId);
  const hasEnv = mtn?.fees.env>0;
  let paidCount=0, totalFeeCount=0;
  b.hikers.forEach((_,i)=>{
    totalFeeCount++;
    if(getPayStatus(b.id,i,'reg')==='paid') paidCount++;
    if(hasEnv){ totalFeeCount++; if(getPayStatus(b.id,i,'env')==='paid') paidCount++; }
  });
  const pct = totalFeeCount>0?Math.round(paidCount/totalFeeCount*100):0;
  const typeMap={day:'Day Hike',late:'Late Hike',overnight:'Overnight'};

  // Filter hikers by payment status
  let hikerRows = b.hikers.map((h,i)=>{
    const rs = getPayStatus(b.id,i,'reg');
    const es = hasEnv?getPayStatus(b.id,i,'env'):null;
    if(activePayFilter!=='all'){
      if(activePayFilter==='unpaid'&&rs!=='unpaid'&&(es===null||es!=='unpaid')) return '';
      if(activePayFilter==='paid'&&rs!=='paid'&&(es===null||es!=='paid')) return '';
      if(activePayFilter==='waived'&&rs!=='waived'&&(es===null||es!=='waived')) return '';
    }
    const key=`${b.id}_${i}`;
    const checked=selectedHikers.has(key);
    return `
      <tr>
        <td>
          <div class="pay-row">
            <input type="checkbox" ${checked?'checked':''} onchange="toggleSelect('${b.id}',${i},this)" onclick="event.stopPropagation()">
            <div class="hk-av ${i===0?'organizer':''}">${initials(h)}</div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;font-size:13px;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${h}</div>
              <div style="font-size:10px;color:var(--ink3);">${i===0?'Organizer':'Hiker '+(i+1)}</div>
            </div>
            <!-- Registration -->
            <div style="min-width:120px;">
              <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--ink3);margin-bottom:4px;">Registration · ${fmtMoney(mtn?.fees.reg||0)}</div>
              <span class="fee-badge ${rs}" onclick="cycleFee('${b.id}',${i},'reg',this);event.stopPropagation();" title="Click to cycle: unpaid → paid → waived">
                ${feeIcon(rs)} ${rs.charAt(0).toUpperCase()+rs.slice(1)}
              </span>
            </div>
            <!-- Environmental -->
            ${hasEnv?`
            <div style="min-width:130px;">
              <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--ink3);margin-bottom:4px;">Environmental · ${fmtMoney(mtn.fees.env)}</div>
              <span class="fee-badge ${es}" onclick="cycleFee('${b.id}',${i},'env',this);event.stopPropagation();" title="Click to cycle: unpaid → paid → waived">
                ${feeIcon(es)} ${es.charAt(0).toUpperCase()+es.slice(1)}
              </span>
            </div>`:`
            <div style="min-width:130px;font-size:11px;color:var(--ink4);font-style:italic;">No env. fee</div>`}
          </div>
        </td>
      </tr>`;
  }).join('');

  if(!hikerRows.trim()) return '';

  // Collection total for this booking
  let bCollected=0,bTotal=0;
  b.hikers.forEach((_,i)=>{
    bTotal+=(mtn?.fees.reg||0);
    if(getPayStatus(b.id,i,'reg')==='paid') bCollected+=(mtn?.fees.reg||0);
    if(hasEnv){ bTotal+=mtn.fees.env; if(getPayStatus(b.id,i,'env')==='paid') bCollected+=mtn.fees.env; }
  });

  return `
    <div class="booking-group" style="border-bottom:2px solid var(--border);animation:fadeUp .4s ease both;">
      <div class="booking-group-hdr" onclick="toggleGroup('${b.id}')">
        <span class="bgh-id">${b.id}</span>
        <div>
          <div class="bgh-mtn">${b.mountain}</div>
          <div class="bgh-meta">${fmtDate(b.date)} · ${fmtTime(b.time)} · ${typeMap[b.type]||b.type} · ${b.pax} hiker${b.pax>1?'s':''}</div>
        </div>
        <span class="badge badge-${b.status}" style="flex-shrink:0;">${b.status}</span>
        <div class="bgh-progress">
          <div class="bgh-pbar"><div class="bgh-pbar-fill" style="width:${pct}%"></div></div>
          <div class="bgh-pct">${pct}%</div>
        </div>
        <button class="btn btn-xs" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.15);" onclick="markAllInBooking('${b.id}');event.stopPropagation();">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          All Paid
        </button>
        <div class="bgh-chevron open" id="chev_${b.id}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="booking-group-body" id="grpBody_${b.id}">
        <table class="pay-table">
          <thead>
            <tr>
              <th style="padding:10px 14px;">
                <div style="display:flex;align-items:center;gap:12px;">
                  <input type="checkbox" onchange="toggleGroupSelect('${b.id}',${b.hikers.length},this)">
                  <span>Hiker</span>
                  <span style="margin-left:auto;margin-right:12px;">Registration</span>
                  ${hasEnv?`<span style="margin-right:12px;">Environmental</span>`:''}
                </div>
              </th>
            </tr>
          </thead>
          <tbody>${hikerRows}</tbody>
        </table>
        <div class="booking-summary-row">
          <div>
            <span class="bsr-label">Collected so far</span>
            <span class="bsr-val" style="margin-left:10px;">${fmtMoney(bCollected)} / ${fmtMoney(bTotal)}</span>
          </div>
          <div style="display:flex;gap:8px;">
            <a href="messages_manager.php?booking=${b.id}" class="btn btn-ghost btn-xs">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Message Guide
            </a>
            <button class="btn btn-primary btn-xs" onclick="markAllInBooking('${b.id}')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
              Mark All Paid
            </button>
          </div>
        </div>
      </div>
    </div>`;
}

function feeIcon(s) {
  if(s==='paid')   return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>`;
  if(s==='waived') return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>`;
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
}

function cycleFee(bookingId, hikerIdx, feeType, el) {
  const cur = getPayStatus(bookingId, hikerIdx, feeType);
  const next = cur==='unpaid'?'paid':cur==='paid'?'waived':'unpaid';
  setPayStatus(bookingId, hikerIdx, feeType, next);
  // Animate badge
  el.style.transform='scale(.88)';
  el.className=`fee-badge ${next}`;
  el.innerHTML=`${feeIcon(next)} ${next.charAt(0).toUpperCase()+next.slice(1)}`;
  setTimeout(()=>{ el.style.transform=''; }, 150);
  updateGroupProgress(bookingId);
  addLog(`Fee ${feeType} → ${next} for hiker in ${bookingId}`, next==='paid'?'green':next==='waived'?'ink':'amber');
  showToast(`${feeType.charAt(0).toUpperCase()+feeType.slice(1)} fee → ${next}`, 'check');
  renderPageStats();
}

function updateGroupProgress(bookingId) {
  const b = getMyBookings().find(x=>x.id===bookingId);
  if(!b) return;
  const mtn=ALL_MOUNTAINS.find(m=>m.id===b.mountainId);
  const hasEnv=mtn?.fees.env>0;
  let paid=0,total=0;
  b.hikers.forEach((_,i)=>{
    total++; if(getPayStatus(b.id,i,'reg')==='paid') paid++;
    if(hasEnv){ total++; if(getPayStatus(b.id,i,'env')==='paid') paid++; }
  });
  const pct=total>0?Math.round(paid/total*100):0;
  const pbar=document.querySelector(`#grpBody_${bookingId}`)?.previousElementSibling?.querySelector('.bgh-pbar-fill');
  const pctEl=document.querySelector(`#grpBody_${bookingId}`)?.previousElementSibling?.querySelector('.bgh-pct');
  if(pbar) pbar.style.width=pct+'%';
  if(pctEl) pctEl.textContent=pct+'%';
}

function renderPageStats() {
  // Just re-render the stat bars
  const myBookings=getMyBookings().filter(b=>b.status!=='cancelled');
  let totalReg=0,paidReg=0,totalEnv=0,paidEnv=0;
  myBookings.forEach(b=>{
    const mtn=ALL_MOUNTAINS.find(m=>m.id===b.mountainId);
    b.hikers.forEach((_,i)=>{
      totalReg++; if(getPayStatus(b.id,i,'reg')==='paid') paidReg++;
      if(mtn?.fees.env>0){ totalEnv++; if(getPayStatus(b.id,i,'env')==='paid') paidEnv++; }
    });
  });
  const collPct=totalReg+totalEnv>0?Math.round((paidReg+paidEnv)/(totalReg+totalEnv)*100):0;
  document.querySelectorAll('.big-stat-bar-fill').forEach((el,i)=>{
    if(i===0) el.style.width=collPct+'%';
  });
}

function markAllInBooking(bookingId) {
  const b=getMyBookings().find(x=>x.id===bookingId);
  const mtn=ALL_MOUNTAINS.find(m=>m.id===b?.mountainId);
  if(!b) return;
  b.hikers.forEach((_,i)=>{
    setPayStatus(bookingId,i,'reg','paid');
    if(mtn?.fees.env>0) setPayStatus(bookingId,i,'env','paid');
  });
  // Refresh badges in this group
  document.querySelectorAll(`#grpBody_${bookingId} .fee-badge`).forEach(el=>{
    el.className='fee-badge paid';
    el.innerHTML=`${feeIcon('paid')} Paid`;
  });
  updateGroupProgress(bookingId);
  addLog(`All fees paid for ${bookingId}`, 'green');
  showToast(`All fees for ${bookingId} marked as paid!`, 'check');
  renderPageStats();
}

function toggleGroup(id) {
  const body=document.getElementById('grpBody_'+id);
  const chev=document.getElementById('chev_'+id);
  body?.classList.toggle('collapsed');
  chev?.classList.toggle('open');
}

/* ─── selection ─── */
function toggleSelect(bookingId, hikerIdx, cb) {
  const key=`${bookingId}_${hikerIdx}`;
  if(cb.checked) selectedHikers.add(key); else selectedHikers.delete(key);
  updateBulkBar();
}
function toggleGroupSelect(bookingId, pax, masterCb) {
  for(let i=0;i<pax;i++){
    const key=`${bookingId}_${i}`;
    if(masterCb.checked) selectedHikers.add(key); else selectedHikers.delete(key);
  }
  document.querySelectorAll(`#grpBody_${bookingId} input[type=checkbox]:not(.master)`).forEach(cb=>cb.checked=masterCb.checked);
  updateBulkBar();
}
function clearSelection() { selectedHikers.clear(); updateBulkBar(); document.querySelectorAll('input[type=checkbox]').forEach(cb=>cb.checked=false); }
function updateBulkBar() {
  const bar=document.getElementById('bulkBar');
  const lbl=document.getElementById('bulkLabel');
  if(!bar||!lbl) return;
  bar.style.display=selectedHikers.size>0?'block':'none';
  lbl.textContent=`${selectedHikers.size} hiker${selectedHikers.size>1?'s':''} selected`;
}
function bulkMark(status) {
  selectedHikers.forEach(key=>{
    const [bid,idx]=key.split('_');
    const i=parseInt(idx);
    const b=getMyBookings().find(x=>x.id===bid);
    const mtn=ALL_MOUNTAINS.find(m=>m.id===b?.mountainId);
    setPayStatus(bid,i,'reg',status);
    if(mtn?.fees.env>0) setPayStatus(bid,i,'env',status);
  });
  addLog(`Bulk marked ${selectedHikers.size} hikers as ${status}`,'green');
  showToast(`${selectedHikers.size} hikers marked as ${status}!`,'check');
  clearSelection();
  renderPage();
}

/* ─── filters ─── */
function setMtnFilter(val) { activeMtnFilter=val; renderPage(); }
function setPayFilter(val) { activePayFilter=val; renderPage(); }

init();
</script>
</body>
</html>
