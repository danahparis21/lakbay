<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY — Bookings</title>
<link rel="stylesheet" href="shared.css">
<style>
/* ── BOOKINGS LAYOUT ── */
.bookings-layout { padding: 36px 0 60px; }
.bookings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 768px) { .bookings-grid { grid-template-columns: 1fr; } }

/* ── BOOKING CARD ── */
.booking-card {
  background: var(--white);
  border-radius: var(--radius);
  border: 1px solid rgba(90,122,90,0.1);
  padding: 20px;
  box-shadow: var(--shadow);
  transition: transform .2s;
}
.booking-card:hover { transform: translateY(-2px); }
.booking-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
.booking-card-title { font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 600; color: var(--forest); }
.booking-card-date { font-size: 12px; color: var(--stone); margin-top: 3px; }
.booking-card-guide {
  display: flex; align-items: center; gap: 10px;
  background: var(--sky); border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px;
}
.guide-av-sm {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--forest); color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.booking-actions { display: flex; gap: 8px; margin-top: 14px; }
.fee-summary { font-size: 12px; color: var(--stone); margin-top: 8px; }
.fee-total { font-size: 16px; font-weight: 700; color: var(--forest); margin-top: 4px; }

/* ── NEW BOOKING FLOW MODAL ── */
.flow-steps { display: flex; align-items: center; padding: 0 28px 0; margin: 12px 0 0; }
.flow-step {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 600; color: var(--stone);
  font-family: 'DM Mono', monospace;
}
.flow-step.active { color: var(--forest); }
.flow-step.done { color: var(--sage); }
.flow-step .sn {
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(90,122,90,0.12);
  display: flex; align-items: center; justify-content: center; font-size: 10px;
}
.flow-step.active .sn { background: var(--forest); color: var(--cream); }
.flow-step.done .sn { background: var(--sage); color: var(--cream); }
.flow-line { flex: 1; height: 1px; background: rgba(90,122,90,0.15); margin: 0 8px; }
.flow-panel { display: none; }
.flow-panel.active { display: block; }

/* Mountain select */
.mtn-select-card {
  display: flex; align-items: center; gap: 14px;
  padding: 14px; border-radius: var(--radius-sm);
  border: 2px solid transparent; background: var(--sky);
  cursor: pointer; transition: .2s; margin-bottom: 10px;
}
.mtn-select-card:hover, .mtn-select-card.selected { border-color: var(--forest); background: var(--cream); }
.mtn-select-img { width: 56px; height: 56px; border-radius: 10px; background-size: cover; background-position: center; flex-shrink: 0; }
.mtn-select-name { font-weight: 700; font-size: 14px; color: var(--forest); }
.mtn-select-meta { font-size: 12px; color: var(--stone); margin-top: 2px; }

/* Type toggle */
.type-toggle { display: flex; gap: 0; border: 1.5px solid rgba(90,122,90,0.2); border-radius: var(--radius-sm); overflow: hidden; }
.type-btn {
  flex: 1; padding: 12px; text-align: center;
  font-size: 14px; font-weight: 600; cursor: pointer;
  transition: .2s; color: var(--stone); background: var(--white);
}
.type-btn.active { background: var(--forest); color: var(--cream); }

/* Hiker row */
.hiker-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px; background: var(--sky);
  border-radius: var(--radius-sm); margin-bottom: 8px;
}
.hiker-row .guide-av-sm { width: 32px; height: 32px; font-size: 11px; }
.hiker-name { flex: 1; font-size: 13px; font-weight: 600; color: var(--forest); }
.hiker-remove { background: none; border: none; color: var(--stone); cursor: pointer; font-size: 16px; }

/* Guide select */
.guide-select-card {
  display: flex; align-items: center; gap: 14px;
  padding: 14px; border-radius: var(--radius-sm);
  border: 2px solid transparent; background: var(--sky);
  cursor: pointer; transition: .2s; margin-bottom: 10px;
}
.guide-select-card:hover, .guide-select-card.selected { border-color: var(--forest); background: var(--cream); }
.guide-select-info { flex: 1; }
.guide-select-name { font-weight: 700; font-size: 14px; color: var(--forest); }
.guide-select-meta { font-size: 12px; color: var(--stone); margin-top: 2px; }
.guide-avail { font-size: 11px; font-weight: 600; color: #2e7d32; margin-top: 3px; }

/* Fee breakdown */
.fee-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 0; border-bottom: 1px solid rgba(90,122,90,0.1);
  font-size: 13px; color: var(--forest);
}
.fee-row:last-child { border-bottom: none; }
.fee-row.total { font-weight: 700; font-size: 16px; padding-top: 14px; }
.fee-row .note { font-size: 11px; color: var(--stone); }

/* Warning card */
.warning-card {
  background: linear-gradient(135deg, #fff8e1, #fff3cd);
  border: 1px solid rgba(245,167,0,0.3);
  border-radius: var(--radius-sm);
  padding: 16px; margin-bottom: 16px;
}
.warning-card h4 { font-size: 14px; font-weight: 700; color: var(--bark); margin-bottom: 6px; }
.warning-card p { font-size: 12px; color: #5d4037; line-height: 1.6; }

/* QR code */
.qr-card {
  background: var(--white);
  border: 1.5px solid rgba(90,122,90,0.15);
  border-radius: var(--radius);
  padding: 28px;
  text-align: center;
}
.qr-grid {
  display: grid;
  grid-template-columns: repeat(11,1fr);
  gap: 2px;
  width: 160px; height: 160px;
  margin: 16px auto;
  background: var(--white);
  padding: 8px;
  border: 2px solid var(--forest);
  border-radius: 8px;
}
.qr-cell { background: var(--forest); border-radius: 1px; }
.qr-cell.w { background: transparent; }

/* Success animation */
.success-page { text-align: center; padding: 40px 20px; }
.success-icon {
  width: 80px; height: 80px; border-radius: 50%;
  background: linear-gradient(135deg, var(--moss), var(--forest));
  margin: 0 auto 20px;
  display: flex; align-items: center; justify-content: center;
  font-size: 36px;
  animation: popIn .5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes popIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.confetti { font-size: 24px; animation: float 2s infinite alternate; }
@keyframes float { from { transform: translateY(0); } to { transform: translateY(-8px); } }

/* Empty state */
.empty-state { text-align: center; padding: 48px 20px; }
.empty-icon { font-size: 48px; margin-bottom: 16px; }

/* ── TABS ── */
.page-tabs {
  display: flex; gap: 0;
  border-bottom: 2px solid rgba(90,122,90,0.12);
  margin-bottom: 28px;
}
.page-tab {
  padding: 12px 24px; font-size: 14px; font-weight: 600;
  color: var(--stone); cursor: pointer;
  border-bottom: 2px solid transparent; margin-bottom: -2px; transition: .2s;
}
.page-tab.active { color: var(--forest); border-color: var(--forest); }
</style>
</head>
<body>

<!-- DESKTOP NAV -->
<nav class="desktop-nav">
  <a href="explore.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#1a2e1a" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#1a2e1a" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      Explore
    </a>
    <a href="bookings.php" class="tab-link active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      Bookings
    </a>
    <a href="quiz.php" class="tab-link quiz-tab">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Quiz
    </a>
    <a href="messages.php" class="tab-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Messages
    </a>
  </div>
  <a href="hikerProfile.php" class="user-btn">J</a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <span>Explore</span>
    </a>
    <a href="bookings.php" class="mob-nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <span>Bookings</span>
    </a>
    <a href="quiz.php" class="mob-nav-item quiz-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    </a>
    <a href="messages.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span>Messages</span>
    </a>
    <a href="profile.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Profile</span>
    </a>
  </div>
</nav>

<!-- PAGE -->
<div class="bookings-layout">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
      <div>
        <div class="section-label">🎒 Your Adventures</div>
        <div class="section-title">Bookings</div>
      </div>
      <button class="btn btn-primary" onclick="openBookingFlow()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Book a Hike
      </button>
    </div>

    <div class="page-tabs">
      <div class="page-tab active" onclick="switchBookingTab('current',this)">Current</div>
      <div class="page-tab" onclick="switchBookingTab('history',this)">History</div>
    </div>

    <!-- CURRENT BOOKINGS -->
    <div id="tab-current">
      <div class="bookings-grid" id="currentBookings"></div>
    </div>

    <!-- HISTORY -->
    <div id="tab-history" style="display:none;">
      <div class="bookings-grid" id="historyBookings"></div>
    </div>
  </div>
</div>

<!-- BOOKING FLOW MODAL -->
<div class="modal-bg" id="bookingModal">
  <div class="modal" style="max-width:580px;">
    <div class="modal-hdr">
      <div>
        <div class="modal-title" id="flowTitle">Book a Hike</div>
        <div style="font-size:11px;color:var(--stone);margin-top:2px;" id="flowSubtitle">Choose your mountain</div>
      </div>
      <button class="modal-close" onclick="closeBookingModal()">✕</button>
    </div>
    <div class="flow-steps" id="flowSteps">
      <div class="flow-step active" id="fs1"><div class="sn">1</div><span>Mountain</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs2"><div class="sn">2</div><span>Details</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs3"><div class="sn">3</div><span>Guide</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs4"><div class="sn">4</div><span>Fees</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs5"><div class="sn">5</div><span>Pay</span></div>
    </div>
    <div class="modal-body" id="flowBody"></div>
    <div style="display:flex;gap:10px;padding:0 28px 24px;" id="flowFooter"></div>
  </div>
</div>

<!-- SUCCESS MODAL -->
<div class="modal-bg" id="successModal">
  <div class="modal" style="max-width:440px;">
    <div class="modal-body">
      <div class="success-page">
        <div style="font-size:28px;margin-bottom:12px;">🎉 🏔️ 🎉</div>
        <div class="success-icon">✅</div>
        <h2 style="font-family:'Playfair Display',serif;font-size:24px;color:var(--forest);margin-bottom:8px;">Booking Confirmed!</h2>
        <p style="font-size:14px;color:var(--stone);line-height:1.6;margin-bottom:8px;">Your hike is booked! Your tour guide has been notified.</p>
        <div style="background:var(--sky);border-radius:var(--radius-sm);padding:14px;margin:16px 0;text-align:left;" id="successSummary"></div>
        <div style="background:#fff3e0;border-radius:var(--radius-sm);padding:12px;font-size:12px;color:#5d4037;margin-bottom:20px;text-align:left;">
          💰 <strong>Remaining balance</strong> will be settled directly with your tour guide after the hike is completed.
        </div>
        <div style="display:flex;gap:10px;">
          <button class="btn btn-outline btn-full" onclick="closeSuccess()">Close</button>
          <a href="messages.php" class="btn btn-primary btn-full">💬 Message Guide</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const mountains = [
  {id:1,name:"Mt. Batulao",location:"Nasugbu, Batangas",difficulty:"moderate",image:"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&q=60",
   fees:{regFee:150,envFee:120,guideDay:900,guideON:1600,campFee:50,parkDay:100,parkON:150}},
  {id:2,name:"Mt. Talamitam",location:"Nasugbu, Batangas",difficulty:"easy",image:"https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=200&q=60",
   fees:{regFee:100,envFee:0,guideDay:700,guideON:1100,campFee:0,parkDay:80,parkON:80}},
  {id:3,name:"Mt. Apayang",location:"Batangas",difficulty:"hard",image:"https://images.unsplash.com/photo-1519681393784-d120267933ba?w=200&q=60",
   fees:{regFee:200,envFee:0,guideDay:1200,guideON:2000,campFee:100,parkDay:0,parkON:0}},
  {id:4,name:"Mt. Lantik",location:"Alfonso, Cavite",difficulty:"moderate",image:"https://images.unsplash.com/photo-1501854140801-50d01698950b?w=200&q=60",
   fees:{regFee:130,envFee:100,guideDay:900,guideON:1500,campFee:0,parkDay:100,parkON:100}}
];

const guides = [
  {id:1,name:"John Dela Cruz",initials:"JD",mountains:[1,3],rating:4.9,available:"Mon–Sat"},
  {id:2,name:"Maya Reyes",initials:"MR",mountains:[1,2],rating:4.8,available:"Daily"},
  {id:3,name:"Rico Cabanlit",initials:"RC",mountains:[4],rating:5.0,available:"Daily"},
  {id:4,name:"Elena Llorente",initials:"EL",mountains:[1],rating:4.7,available:"Wed–Sun"}
];

const sampleBookings = [
  {id:"BK001",mountain:"Mt. Batulao",date:"May 15, 2025",type:"Day Hike",status:"confirmed",guide:"Maya Reyes",guideInit:"MR",pax:3,total:2850},
  {id:"BK002",mountain:"Mt. Talamitam",date:"June 2, 2025",type:"Overnight",status:"pending",guide:"John Dela Cruz",guideInit:"JD",pax:2,total:2450}
];
const sampleHistory = [
  {id:"BK000",mountain:"Mt. Lantik",date:"March 8, 2025",type:"Day Hike",status:"completed",guide:"Rico Cabanlit",guideInit:"RC",pax:4,total:2660}
];

// FLOW STATE
let flowState = { step:1, mtn:null, type:'day', pax:1, solo:true, hikers:[], guide:null, parking:false, date:'', time:'', wantGuide:true };
let currentStep = 1;

function openBookingFlow() {
  flowState = { step:1, mtn:null, type:'day', pax:1, solo:true, hikers:[], guide:null, parking:false, date:'', time:'', wantGuide:true };
  currentStep = 1;
  document.getElementById('bookingModal').classList.add('open');
  renderStep(1);
}
function closeBookingModal() { document.getElementById('bookingModal').classList.remove('open'); }

function updateStepIndicators(n) {
  for(let i=1;i<=5;i++){
    const el=document.getElementById('fs'+i);
    el.className='flow-step';
    if(i<n) el.classList.add('done');
    else if(i===n) el.classList.add('active');
  }
}

function renderStep(n) {
  currentStep = n;
  updateStepIndicators(n);
  const body = document.getElementById('flowBody');
  const footer = document.getElementById('flowFooter');

  if(n===1) {
    document.getElementById('flowTitle').textContent='Book a Hike';
    document.getElementById('flowSubtitle').textContent='Choose your mountain';
    body.innerHTML = mountains.map(m=>`
      <div class="mtn-select-card" id="ms${m.id}" onclick="selectMtn(${m.id})">
        <div class="mtn-select-img" style="background-image:url('${m.image}')"></div>
        <div>
          <div class="mtn-select-name">${m.name}</div>
          <div class="mtn-select-meta">${m.location} · <span class="badge badge-${m.difficulty}" style="font-size:10px;">${m.difficulty}</span></div>
        </div>
      </div>
    `).join('');
    footer.innerHTML = `<button class="btn btn-outline btn-full" onclick="closeBookingModal()">Cancel</button><button class="btn btn-primary btn-full" onclick="nextStep()" id="nextBtn1" disabled>Continue →</button>`;
  }

  else if(n===2) {
    document.getElementById('flowTitle').textContent = flowState.mtn.name;
    document.getElementById('flowSubtitle').textContent = 'Hike details';
    body.innerHTML = `
      <div style="margin-bottom:16px;">
        <div class="inp-label" style="margin-bottom:8px;">Hike type</div>
        <div class="type-toggle">
          <div class="type-btn active" id="btnDay" onclick="setType('day')">☀️ Day Hike</div>
          <div class="type-btn" id="btnON" onclick="setType('overnight')">🌙 Overnight</div>
        </div>
      </div>
      <div style="margin-bottom:16px;">
        <div class="inp-label" style="margin-bottom:8px;">Solo or Group?</div>
        <div class="type-toggle">
          <div class="type-btn active" id="btnSolo" onclick="setSolo(true)">👤 Solo</div>
          <div class="type-btn" id="btnGroup" onclick="setSolo(false)">👥 Group</div>
        </div>
      </div>
      <div id="groupSection" style="display:none;margin-bottom:16px;">
        <div class="inp-label" style="margin-bottom:8px;">Add hikers (search by username)</div>
        <div style="display:flex;gap:8px;margin-bottom:8px;">
          <input class="inp" id="hikerSearch" placeholder="@username" style="flex:1;">
          <button class="btn btn-primary btn-sm" onclick="addHiker()">Add</button>
        </div>
        <div id="hikerList"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div>
          <div class="inp-label">Date</div>
          <input type="date" class="inp" id="hikeDate" onchange="flowState.date=this.value" min="${new Date().toISOString().split('T')[0]}">
        </div>
        <div id="timeSection">
          <div class="inp-label" id="timeLbl">Departure time</div>
          <input type="time" class="inp" id="hikeTime" onchange="flowState.time=this.value">
        </div>
      </div>
      <div style="background:#e8f5e9;border-radius:var(--radius-sm);padding:12px;font-size:12px;color:#2e7d32;margin-bottom:8px;" id="noVehicleNote">
        🛺 <strong>Note:</strong> Hikers without a private vehicle will need to arrange tricycle transport to the trailhead — not included in booking fee.
      </div>
    `;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(1)">← Back</button><button class="btn btn-primary btn-full" onclick="nextStep()">Continue →</button>`;
    updateTimeLabel();
  }

  else if(n===3) {
    document.getElementById('flowTitle').textContent = 'Choose a Guide';
    document.getElementById('flowSubtitle').textContent = 'Available guides for your date';
    const available = guides.filter(g=>g.mountains.includes(flowState.mtn.id));
    body.innerHTML = `
      <div style="margin-bottom:16px;">
        <div class="inp-label" style="margin-bottom:8px;">Would you like a tour guide?</div>
        <div class="type-toggle">
          <div class="type-btn ${flowState.wantGuide?'active':''}" id="btnYesGuide" onclick="setWantGuide(true)">✅ Yes</div>
          <div class="type-btn ${!flowState.wantGuide?'active':''}" id="btnNoGuide" onclick="setWantGuide(false)">No guide</div>
        </div>
      </div>
      <div id="guideListSection">
        ${available.map(g=>`
          <div class="guide-select-card" id="gs${g.id}" onclick="selectGuide(${g.id})">
            <div class="guide-av-sm">${g.initials}</div>
            <div class="guide-select-info">
              <div class="guide-select-name">${g.name}</div>
              <div class="guide-select-meta">★ ${g.rating} · ${mountains.find(m=>g.mountains.includes(m.id))?.name||''}</div>
              <div class="guide-avail">🟢 Available ${g.available}</div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(2)">← Back</button><button class="btn btn-primary btn-full" onclick="nextStep()">Continue →</button>`;
  }

  else if(n===4) {
    document.getElementById('flowTitle').textContent = 'Fees Summary';
    document.getElementById('flowSubtitle').textContent = 'Review your booking costs';
    const m = flowState.mtn;
    const f = m.fees;
    const pax = flowState.solo ? 1 : Math.max(1, flowState.hikers.length+1);
    flowState.pax = pax;
    const isON = flowState.type==='overnight';
    const regTotal = f.regFee * pax;
    const envTotal = f.envFee ? f.envFee * pax : 0;
    const guideF = flowState.wantGuide ? (isON ? f.guideON : f.guideDay) : 0;
    const campF = isON && f.campFee ? f.campFee * pax : 0;
    let parkF = 0;
    let totalFee = regTotal + envTotal + guideF + campF;
    const downpayment = 400;

    body.innerHTML = `
      <div style="background:var(--white);border-radius:var(--radius-sm);border:1px solid rgba(90,122,90,0.1);padding:16px;margin-bottom:16px;">
        <div class="fee-row"><span>Registration fee (₱${f.regFee} × ${pax} pax)</span><span>₱${regTotal.toLocaleString()}</span></div>
        ${envTotal?`<div class="fee-row"><span>Environmental fee (₱${f.envFee} × ${pax} pax)</span><span>₱${envTotal.toLocaleString()}</span></div>`:''}
        ${flowState.wantGuide?`<div class="fee-row"><span>Guide fee (${isON?'overnight':'day hike'}, ${pax} pax)</span><span>₱${guideF.toLocaleString()}</span></div>`:''}
        ${campF?`<div class="fee-row"><span>Camping fee (₱${f.campFee} × ${pax} pax)</span><span>₱${campF.toLocaleString()}</span></div>`:''}
        ${(f.parkDay||f.parkON)?`<div class="fee-row"><span style="display:flex;align-items:center;gap:8px;">Parking <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" id="parkChk" onchange="toggleParking(this.checked)"> Add parking</label></span><span id="parkPrice">₱${isON?f.parkON:f.parkDay}</span></div>`:''}
        <div class="fee-row total"><span>TOTAL</span><span id="feeTotal">₱${totalFee.toLocaleString()}</span></div>
      </div>
      <div class="warning-card">
        <h4>⚠️ Non-Refundable Booking</h4>
        <p>Fake or cancelled bookings directly affect the schedule and livelihood of our local tour guides. By proceeding, you agree that your downpayment of <strong>₱400</strong> is non-refundable.</p>
      </div>
      <div style="background:var(--sky);border-radius:var(--radius-sm);padding:12px;font-size:12px;color:var(--stone);margin-bottom:8px;">
        💡 The remaining balance will be handed directly to your tour guide <strong>after the hike</strong>.
      </div>
    `;
    window._totalFee = totalFee;
    window._parkFees = {day:f.parkDay,on:f.parkON,isON};
    window._baseFee = totalFee;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(3)">← Back</button><button class="btn btn-gold btn-full" onclick="nextStep()">Pay Downpayment (₱400) →</button>`;
  }

  else if(n===5) {
    document.getElementById('flowTitle').textContent = 'Downpayment';
    document.getElementById('flowSubtitle').textContent = 'Scan QR to pay ₱400';
    body.innerHTML = `
      <div class="qr-card">
        <p style="font-size:13px;color:var(--stone);margin-bottom:4px;">Scan to pay via GCash / Maya</p>
        <h3 style="font-family:'DM Mono',monospace;font-size:28px;color:var(--forest);margin-bottom:4px;">₱400.00</h3>
        <p style="font-size:11px;color:var(--stone);">Non-refundable downpayment</p>
        <div style="background:var(--forest);width:160px;height:160px;margin:16px auto;border-radius:8px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;">
          <div style="background:white;width:140px;height:140px;border-radius:6px;display:grid;grid-template-columns:repeat(9,1fr);gap:1.5px;padding:6px;">
            ${generateQR()}
          </div>
        </div>
        <p style="font-size:12px;color:var(--sage);font-weight:600;">LAKBAY HIKERS · REF: BK${String(Date.now()).slice(-5)}</p>
        <div class="divider"></div>
        <div style="background:#e8f5e9;border-radius:var(--radius-sm);padding:10px;font-size:12px;color:#2e7d32;">
          ✅ Once payment is confirmed, your booking will be activated and your guide will be notified.
        </div>
      </div>
    `;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(4)">← Back</button><button class="btn btn-primary btn-full" onclick="confirmBooking()">I've Paid — Confirm Booking</button>`;
  }
}

function generateQR() {
  // Pseudo-random QR pattern for visual
  const pattern = [1,1,1,1,1,1,1,0,1,1,0,0,0,1,0,1,1,0,1,1,1,1,1,1,1,0,1,0,0,0,0,0,0,0,0,1,0,0,0,1,1,1,0,0,0,0,0,1,0,1,1,0,1,0,0,1,1,1,0,1,0,1,0,1,0,1,1,0,1,1,1,0,1,0,0,0,1,0,0,1,0];
  return pattern.map(p=>`<div style="background:${p?'#1a2e1a':'transparent'};border-radius:1px;"></div>`).join('');
}

function selectMtn(id) {
  flowState.mtn = mountains.find(m=>m.id===id);
  document.querySelectorAll('.mtn-select-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('ms'+id).classList.add('selected');
  document.getElementById('nextBtn1').disabled = false;
}

function setType(t) {
  flowState.type = t;
  document.getElementById('btnDay').classList.toggle('active', t==='day');
  document.getElementById('btnON').classList.toggle('active', t==='overnight');
  updateTimeLabel();
}

function updateTimeLabel() {
  const lbl = document.getElementById('timeLbl');
  if(lbl) lbl.textContent = flowState.type==='overnight' ? 'Meet guide at' : 'Departure time';
}

function setSolo(s) {
  flowState.solo = s;
  document.getElementById('btnSolo').classList.toggle('active', s);
  document.getElementById('btnGroup').classList.toggle('active', !s);
  document.getElementById('groupSection').style.display = s ? 'none' : 'block';
}

function addHiker() {
  const inp = document.getElementById('hikerSearch');
  const val = inp.value.trim().replace('@','');
  if(!val) return;
  if(flowState.hikers.includes(val)) { showToast('Hiker already added'); return; }
  flowState.hikers.push(val);
  inp.value = '';
  renderHikerList();
}

function renderHikerList() {
  document.getElementById('hikerList').innerHTML = flowState.hikers.map((h,i)=>`
    <div class="hiker-row">
      <div class="guide-av-sm">${h[0].toUpperCase()}</div>
      <div class="hiker-name">@${h}</div>
      <button class="hiker-remove" onclick="removeHiker(${i})">✕</button>
    </div>
  `).join('');
}

function removeHiker(i) { flowState.hikers.splice(i,1); renderHikerList(); }

function setWantGuide(w) {
  flowState.wantGuide = w;
  document.getElementById('btnYesGuide').classList.toggle('active', w);
  document.getElementById('btnNoGuide').classList.toggle('active', !w);
  document.getElementById('guideListSection').style.display = w ? 'block' : 'none';
}

function selectGuide(id) {
  flowState.guide = guides.find(g=>g.id===id);
  document.querySelectorAll('.guide-select-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('gs'+id).classList.add('selected');
}

function toggleParking(checked) {
  const {day,on,isON} = window._parkFees;
  const parkCost = isON ? on : day;
  const newTotal = window._baseFee + (checked ? parkCost : 0);
  document.getElementById('feeTotal').textContent = '₱'+newTotal.toLocaleString();
  flowState.parking = checked;
  window._totalFee = newTotal;
}

function nextStep() {
  if(currentStep===1 && !flowState.mtn) { showToast('Please select a mountain'); return; }
  if(currentStep===2 && !flowState.date) { showToast('Please select a date'); return; }
  renderStep(currentStep+1);
}

function confirmBooking() {
  closeBookingModal();
  const g = flowState.guide;
  document.getElementById('successSummary').innerHTML = `
    <div style="font-size:13px;line-height:2;">
      🏔️ <strong>${flowState.mtn.name}</strong><br>
      📅 ${flowState.date} · ${flowState.time || ''}<br>
      ${flowState.type==='overnight'?'🌙 Overnight':'☀️ Day hike'}<br>
      👥 ${flowState.pax} hiker(s)<br>
      ${g?`🧭 Guide: <strong>${g.name}</strong>`:'No guide booked'}<br>
      💰 Total: <strong>₱${(window._totalFee||0).toLocaleString()}</strong> (₱400 paid)
    </div>
  `;
  document.getElementById('successModal').classList.add('open');
  renderBookings();
}

function closeSuccess() { document.getElementById('successModal').classList.remove('open'); }

function switchBookingTab(tab, el) {
  document.querySelectorAll('.page-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-current').style.display = tab==='current'?'block':'none';
  document.getElementById('tab-history').style.display = tab==='history'?'block':'none';
}

function renderBookings() {
  const cur = document.getElementById('currentBookings');
  const hist = document.getElementById('historyBookings');
  cur.innerHTML = sampleBookings.map(b=>bookingCard(b)).join('') || emptyState('No current bookings');
  hist.innerHTML = sampleHistory.map(b=>bookingCard(b)).join('') || emptyState('No booking history yet');
}

function bookingCard(b) {
  const statusColors = {pending:'badge-pending',confirmed:'badge-confirmed',completed:'badge-completed'};
  return `
    <div class="booking-card">
      <div class="booking-card-header">
        <div>
          <div class="booking-card-title">${b.mountain}</div>
          <div class="booking-card-date">📅 ${b.date} · ${b.type}</div>
        </div>
        <span class="badge ${statusColors[b.status]}">${b.status}</span>
      </div>
      <div class="booking-card-guide">
        <div class="guide-av-sm">${b.guideInit}</div>
        <div>
          <div style="font-weight:700;font-size:13px;">🧭 ${b.guide}</div>
          <div style="font-size:11px;color:var(--stone);">${b.pax} hiker(s)</div>
        </div>
      </div>
      <div style="font-size:12px;color:var(--stone);">Ref: ${b.id}</div>
      <div class="fee-total">₱${b.total.toLocaleString()} <span style="font-size:12px;font-weight:400;color:var(--stone);">(₱400 paid · balance after hike)</span></div>
      <div class="booking-actions">
        <a href="messages.php" class="btn btn-outline btn-sm">💬 Message Guide</a>
        ${b.status==='pending'?'<button class="btn btn-primary btn-sm" onclick="showToast(\'Guide notified!\')">Nudge Guide</button>':''}
      </div>
    </div>
  `;
}

function emptyState(msg) {
  return `<div class="empty-state" style="grid-column:1/-1;"><div class="empty-icon">🏔️</div><p style="color:var(--stone);">${msg}</p><button class="btn btn-primary" style="margin-top:16px;" onclick="openBookingFlow()">Book your first hike</button></div>`;
}

function showToast(msg) {
  const t=document.getElementById('toast');
  t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2800);
}

document.getElementById('bookingModal').addEventListener('click',e=>{if(e.target===document.getElementById('bookingModal'))closeBookingModal();});
document.getElementById('successModal').addEventListener('click',e=>{if(e.target===document.getElementById('successModal'))closeSuccess();});

// Check if coming from explore page with preselected mountain
const preselected = localStorage.getItem('bookingMtn');
if(preselected) { localStorage.removeItem('bookingMtn'); setTimeout(openBookingFlow, 300); }

renderBookings();
</script>
</body>
</html>
