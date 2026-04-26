<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LAKBAY Manager — Analytics</title>
<link rel="stylesheet" href="manager.css">
<style>
/* ── CHART CONTAINERS ── */
.chart-wrap { position:relative; width:100%; }
.chart-svg { width:100%; overflow:visible; }
.chart-tooltip {
  position:fixed; background:var(--ink); color:var(--white);
  padding:8px 14px; border-radius:10px; font-size:12px; font-weight:600;
  pointer-events:none; z-index:999; opacity:0; transition:opacity .15s;
  box-shadow:0 4px 16px rgba(16,6,0,.3); white-space:nowrap;
}
.chart-tooltip.show { opacity:1; }

/* ── BAR CHART ── */
.bar-group rect { cursor:pointer; transition:opacity .15s; }
.bar-group:hover rect { opacity:.85; }
.chart-label { font-family:'Plus Jakarta Sans',sans-serif; font-size:11px; fill:var(--ink3); }
.chart-val-label { font-family:'DM Mono',monospace; font-size:10px; fill:var(--ink); font-weight:700; }

/* ── LINE CHART ── */
.line-path { fill:none; stroke-linejoin:round; stroke-linecap:round; }
.area-path { stroke:none; }
.line-dot { cursor:pointer; transition:r .15s; }
.line-dot:hover { r:6; }

/* ── DONUT ── */
.donut-segment { cursor:pointer; transition:opacity .15s, transform .15s; transform-origin:center; }
.donut-segment:hover { opacity:.85; }

/* ── INSIGHT CARDS ── */
.insight-card {
  background:var(--white); border-radius:14px; border:1px solid var(--border);
  padding:18px; box-shadow:var(--shadow); display:flex; gap:14px; align-items:flex-start;
  transition:transform .2s, box-shadow .2s;
}
.insight-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
.insight-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.insight-icon svg { width:18px; height:18px; stroke:currentColor; stroke-width:2; }
.insight-title { font-size:12px; font-weight:700; color:var(--ink); margin-bottom:4px; }
.insight-val { font-family:'Playfair Display',serif; font-size:22px; font-weight:700; color:var(--ink); margin-bottom:4px; }
.insight-sub { font-size:11px; color:var(--ink3); line-height:1.5; }
.insight-trend { display:flex; align-items:center; gap:4px; font-size:11px; font-weight:700; margin-top:6px; }
.trend-up { color:var(--green); }
.trend-down { color:var(--red); }
.insight-trend svg { width:12px; height:12px; stroke:currentColor; stroke-width:2.5; }

/* ── GUIDE TABLE ── */
.guide-row {
  display:flex; align-items:center; gap:14px; padding:12px 0;
  border-bottom:1px solid var(--border); transition:background .12s;
  cursor:default;
}
.guide-row:last-child { border-bottom:none; }
.guide-row:hover { background:var(--off); margin:0 -22px; padding:12px 22px; border-radius:8px; }
.guide-rank { font-family:'DM Mono',monospace; font-size:13px; font-weight:700; color:var(--ink3); width:20px; }
.guide-av { width:36px; height:36px; border-radius:50%; background:var(--ink); color:var(--gold); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
.guide-info { flex:1; }
.guide-name { font-weight:700; font-size:13px; color:var(--ink); }
.guide-meta { font-size:11px; color:var(--ink3); margin-top:1px; }
.guide-bar-wrap { width:100px; }
.guide-bar { height:6px; border-radius:3px; background:var(--off); overflow:hidden; }
.guide-bar-fill { height:100%; border-radius:3px; background:var(--gold); transition:width .8s cubic-bezier(.4,0,.2,1); }
.guide-trips { font-family:'DM Mono',monospace; font-size:12px; font-weight:700; color:var(--ink); min-width:40px; text-align:right; }

/* ── HEATMAP ── */
.heatmap-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
.heatmap-cell {
  aspect-ratio:1; border-radius:4px; cursor:pointer;
  transition:transform .15s, opacity .15s;
}
.heatmap-cell:hover { transform:scale(1.2); opacity:.9; }
.heatmap-label { font-size:9px; color:var(--ink3); text-align:center; padding-bottom:2px; }

/* ── DATE RANGE ── */
.date-range-btns { display:flex; gap:0; border:1.5px solid var(--border2); border-radius:10px; overflow:hidden; }
.dr-btn { padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer; transition:.15s; border:none; background:transparent; color:var(--ink3); }
.dr-btn:hover { background:var(--off); color:var(--ink); }
.dr-btn.active { background:var(--ink); color:var(--white); }

/* ── COMPARE BANNER ── */
.compare-banner {
  background:linear-gradient(135deg,var(--ink) 0%,#2a1a0a 100%);
  border-radius:var(--r); padding:22px 28px;
  display:grid; grid-template-columns:1fr 1fr; gap:20px;
  position:relative; overflow:hidden;
}
.compare-banner::before { content:''; position:absolute; right:-30px; top:-30px; width:120px; height:120px; border-radius:50%; background:rgba(201,168,76,.08); }
.compare-col { }
.compare-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:rgba(255,255,255,.4); margin-bottom:6px; }
.compare-mtn { font-family:'Playfair Display',serif; font-size:18px; font-weight:700; color:var(--white); margin-bottom:10px; }
.compare-stat-row { display:flex; gap:16px; flex-wrap:wrap; }
.cs { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:10px 14px; }
.cs-val { font-family:'DM Mono',monospace; font-size:16px; font-weight:700; color:var(--gold); }
.cs-lbl { font-size:9px; color:rgba(255,255,255,.4); text-transform:uppercase; letter-spacing:.8px; margin-top:2px; }
.compare-divider { width:1px; background:rgba(255,255,255,.08); align-self:stretch; }
@media(max-width:600px){ .compare-banner{grid-template-columns:1fr;} .compare-divider{display:none;} }

/* ── TABS ── */
.section-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:20px; }
.section-tab { padding:10px 20px; font-size:13px; font-weight:600; cursor:pointer; color:var(--ink3); border-bottom:2px solid transparent; margin-bottom:-2px; transition:.15s; }
.section-tab.active { color:var(--ink); border-color:var(--ink); }
.section-tab:hover:not(.active) { color:var(--ink); }
.tab-content { display:none; animation:fadeIn .25s ease; }
.tab-content.active { display:block; }
</style>
</head>
<body>
<div class="app-shell">

<?php $activePage='analytics'; include 'shared_sidebar.php'; ?>

<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Analytics</div>
        <div class="topbar-page-sub">Performance insights for your mountains</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar" id="topbarAvatar">JR</div>
    </div>
  </div>

  <div class="content" id="analyticsContent"></div>
</div>

<div class="chart-tooltip" id="tooltip"></div>
<div class="toast" id="toast"></div>
<?php include 'manager_data.php'; ?>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); document.getElementById('mainArea').classList.toggle('expanded'); }
setInterval(()=>{ const el=document.getElementById('topbarDate'); if(el) el.textContent=new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}); },1000);

function init() {
  initSidebar();
  document.getElementById('topbarAvatar').textContent = MANAGER.initials;
  renderAnalytics();
}

/* ── MOCK TREND DATA ── */
const months = ['Nov','Dec','Jan','Feb','Mar','Apr'];
const bookingTrends = {
  1: [4,7,6,9,12,10],   // Batulao
  2: [2,3,4,5,3,6]       // Talamitam
};
const revTrends = {
  1: [12000,21000,18000,27000,36000,30000],
  2: [4000,6000,8000,10000,6000,12000]
};
const hikerTrends = {
  1: [16,28,22,36,48,40],
  2: [6,9,12,15,9,18]
};
const diffBreakdown = {
  1: { easy:5,moderate:28,hard:12 },
  2: { easy:22,moderate:8,hard:0  }
};
const weekdayData = {
  1: [3,8,12,10,14,22,18],   // Sun..Sat
  2: [2,4,6,5,7,12,10]
};
const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

let activeRange = '6m';

function renderAnalytics() {
  const myMtns = getMyMountains();
  const bookings = getMyBookings().filter(b=>b.status!=='cancelled');
  const guides = ALL_GUIDES.filter(g=>g.mountainIds.some(mid=>MANAGER.mountainIds.includes(mid)));

  // Stats per mountain
  const stats = myMtns.map(m=>{
    const mb = bookings.filter(b=>b.mountainId===m.id);
    const hikers = mb.reduce((s,b)=>s+b.pax,0);
    const revenue = mb.reduce((s,b)=>s+b.totalFee,0);
    const completed = mb.filter(b=>b.status==='completed').length;
    return {...m,mb,hikers,revenue,completed};
  });

  document.getElementById('analyticsContent').innerHTML = `

    <!-- Insights row -->
    <div class="three-col">
      ${stats.map((m,i)=>`
        <div class="insight-card anim-fade-up d${i+1}">
          <div class="insight-icon" style="background:rgba(201,168,76,.1);color:var(--gold);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>
          </div>
          <div>
            <div class="insight-title">${m.name}</div>
            <div class="insight-val">${m.hikers}</div>
            <div class="insight-sub">Total hikers · ${m.mb.length} bookings · ${m.completed} completed</div>
            <div class="insight-trend trend-up">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 9 12 15 6 9"/></svg>
              ${fmtMoney(m.revenue)} total fees
            </div>
          </div>
        </div>`).join('')}
      <div class="insight-card anim-fade-up d3">
        <div class="insight-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div>
          <div class="insight-title">Total Hikers</div>
          <div class="insight-val">${stats.reduce((s,m)=>s+m.hikers,0)}</div>
          <div class="insight-sub">Across all your mountains this season</div>
          <div class="insight-trend trend-up">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 9 12 15 6 9"/></svg>
            +18% vs last month
          </div>
        </div>
      </div>
    </div>

    <!-- Date range + Compare banner -->
    <div class="compare-banner anim-fade-up d2">
      ${stats.map((m,i)=>`
        ${i>0?'<div class="compare-divider"></div>':''}
        <div class="compare-col">
          <div class="compare-label">Mountain ${i+1}</div>
          <div class="compare-mtn">${m.name}</div>
          <div class="compare-stat-row">
            <div class="cs"><div class="cs-val">${m.mb.length}</div><div class="cs-lbl">Bookings</div></div>
            <div class="cs"><div class="cs-val">${m.hikers}</div><div class="cs-lbl">Hikers</div></div>
            <div class="cs"><div class="cs-val">${fmtMoney(m.revenue)}</div><div class="cs-lbl">Fees</div></div>
            <div class="cs"><div class="cs-val">${m.mb.filter(b=>b.status==='pending').length}</div><div class="cs-lbl">Pending</div></div>
          </div>
        </div>`).join('')}
    </div>

    <!-- Main charts section -->
    <div class="panel anim-fade-up d3">
      <div class="panel-hdr">
        <div class="panel-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Trends
        </div>
        <div class="date-range-btns">
          <div class="dr-btn ${activeRange==='1m'?'active':''}" onclick="setRange('1m')">1M</div>
          <div class="dr-btn ${activeRange==='3m'?'active':''}" onclick="setRange('3m')">3M</div>
          <div class="dr-btn ${activeRange==='6m'?'active':''}" onclick="setRange('6m')">6M</div>
        </div>
      </div>
      <div class="panel-body">
        <div class="section-tabs">
          <div class="section-tab active" onclick="switchTab('bookings',this)">Bookings</div>
          <div class="section-tab" onclick="switchTab('hikers',this)">Hikers</div>
          <div class="section-tab" onclick="switchTab('revenue',this)">Revenue</div>
        </div>
        <div class="tab-content active" id="tab-bookings">
          <div class="chart-wrap" style="height:220px;">${drawLineChart('bookings')}</div>
        </div>
        <div class="tab-content" id="tab-hikers">
          <div class="chart-wrap" style="height:220px;">${drawLineChart('hikers')}</div>
        </div>
        <div class="tab-content" id="tab-revenue">
          <div class="chart-wrap" style="height:220px;">${drawLineChart('revenue')}</div>
        </div>
        <!-- Legend -->
        <div style="display:flex;gap:20px;margin-top:14px;flex-wrap:wrap;">
          ${myMtns.map((m,i)=>`<div style="display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:var(--ink3);">
            <div style="width:24px;height:3px;border-radius:2px;background:${i===0?'var(--ink)':'var(--gold)'}"></div>${m.name}</div>`).join('')}
        </div>
      </div>
    </div>

    <!-- Bottom row: donut + guide leaderboard + weekday heatmap -->
    <div class="three-col">

      <!-- Difficulty donut -->
      <div class="panel anim-fade-up d4">
        <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>Difficulty Mix</div></div>
        <div class="panel-body" style="display:flex;flex-direction:column;align-items:center;gap:16px;">
          <div style="position:relative;width:160px;height:160px;">${drawDonut(stats)}</div>
          <div style="width:100%;">
            ${[['easy',var_green='#b8ae90'],['moderate','#887b58'],['hard','#5e5337b6']].map(([d,c])=>{
              const cnt=stats.reduce((s,m)=>s+(diffBreakdown[m.id]?.[d]||0),0);
              const total=stats.reduce((s,m)=>s+Object.values(diffBreakdown[m.id]||{}).reduce((a,b)=>a+b,0),0);
              const pct=total>0?Math.round(cnt/total*100):0;
              return `<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:10px;height:10px;border-radius:50%;background:${c};flex-shrink:0;"></div>
                <div style="flex:1;font-size:12px;font-weight:600;color:var(--ink);text-transform:capitalize;">${d}</div>
                <div style="font-family:'DM Mono',monospace;font-size:12px;font-weight:700;color:var(--ink);">${cnt}</div>
                <div style="font-size:11px;color:var(--ink3);">${pct}%</div>
              </div>`;
            }).join('')}
          </div>
        </div>
      </div>

      <!-- Guide leaderboard -->
      <div class="panel anim-fade-up d5">
        <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Guide Leaderboard</div></div>
        <div class="panel-body">
          ${guides.map((g,i)=>{
            const trips=bookings.filter(b=>b.guideId===g.id).length;
            const hikers=bookings.filter(b=>b.guideId===g.id).reduce((s,b)=>s+b.pax,0);
            const maxTrips=Math.max(...guides.map(x=>bookings.filter(b=>b.guideId===x.id).length),1);
            return `<div class="guide-row">
              <div class="guide-rank">${i===0?'🥇':i===1?'🥈':i===2?'🥉':(i+1)}</div>
              <div class="guide-av">${g.initials}</div>
              <div class="guide-info">
                <div class="guide-name">${g.name}</div>
                <div class="guide-meta">★ ${g.rating} · ${hikers} hikers</div>
              </div>
              <div class="guide-bar-wrap">
                <div class="guide-bar"><div class="guide-bar-fill" style="width:${Math.round(trips/maxTrips*100)}%;"></div></div>
              </div>
              <div class="guide-trips">${trips}</div>
            </div>`;
          }).join('')}
        </div>
      </div>

      <!-- Weekday heatmap -->
      <div class="panel anim-fade-up d6">
        <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Busiest Days</div></div>
        <div class="panel-body">
          ${myMtns.map(m=>{
            const data=weekdayData[m.id]||[0,0,0,0,0,0,0];
            const max=Math.max(...data,1);
            return `<div style="margin-bottom:18px;">
              <div style="font-size:11px;font-weight:700;color:var(--ink3);margin-bottom:8px;">${m.name}</div>
              <div class="heatmap-grid">
                ${weekdays.map(d=>`<div class="heatmap-label">${d}</div>`).join('')}
                ${data.map((v,i)=>{
                  const pct=v/max;
                  const alpha=.08+pct*.85;
                  return `<div class="heatmap-cell" style="background:rgba(16,6,0,${alpha.toFixed(2)});" onclick="showToast('${weekdays[i]}: ${v} bookings','info')" title="${weekdays[i]}: ${v} bookings"></div>`;
                }).join('')}
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:4px;font-size:9px;color:var(--ink4);">
                <span>Less</span><div style="display:flex;gap:3px;">${[.08,.3,.55,.75,.92].map(a=>`<div style="width:12px;height:8px;border-radius:2px;background:rgba(16,6,0,${a});"></div>`).join('')}</div><span>More</span>
              </div>
            </div>`;
          }).join('')}
          <div style="background:var(--off);border-radius:10px;padding:12px;margin-top:4px;">
            <div style="font-size:11px;font-weight:700;color:var(--ink);margin-bottom:4px;">Peak Day</div>
            <div style="font-size:13px;color:var(--ink3);">Saturday is your busiest day across all mountains.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Booking Status Breakdown -->
    <div class="panel anim-fade-up d7">
      <div class="panel-hdr">
        <div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Status Breakdown per Mountain</div>
      </div>
      <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
          ${stats.map(m=>{
            const statuses=['pending','confirmed','completed','cancelled'];
            const colors={'pending':'var(--amber)','confirmed':'var(--green)','completed':'var(--blue)','cancelled':'var(--red)'};
            const total=m.mb.length||1;
            return `<div>
              <div style="font-size:13px;font-weight:700;color:var(--ink);margin-bottom:12px;">${m.name}</div>
              ${statuses.map(s=>{
                const cnt=m.mb.filter(b=>b.status===s).length;
                const pct=Math.round(cnt/total*100);
                return `<div style="margin-bottom:8px;">
                  <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                    <span style="color:var(--ink3);text-transform:capitalize;">${s}</span>
                    <span style="font-weight:700;color:var(--ink);font-family:'DM Mono',monospace;">${cnt} <span style="color:var(--ink4);font-weight:400;">(${pct}%)</span></span>
                  </div>
                  <div style="height:6px;border-radius:3px;background:rgba(16,6,0,.08);overflow:hidden;">
                    <div style="height:100%;border-radius:3px;background:${colors[s]};width:0;transition:width .8s cubic-bezier(.4,0,.2,1);" data-w="${pct}%"></div>
                  </div>
                </div>`;
              }).join('')}
            </div>`;
          }).join('')}
        </div>
      </div>
    </div>
  `;

  // Animate bar fills
  setTimeout(()=>{
    document.querySelectorAll('[data-w]').forEach(el=>{ el.style.width=el.dataset.w; });
    document.querySelectorAll('.guide-bar-fill').forEach(el=>{ const w=el.style.width; el.style.width='0'; setTimeout(()=>el.style.width=w,50); });
  },200);
}

/* ── LINE CHART SVG ── */
function drawLineChart(metric) {
  const myMtns = getMyMountains();
  const datasets = {
    bookings: bookingTrends,
    hikers:   hikerTrends,
    revenue:  revTrends
  };
  const data = datasets[metric] || bookingTrends;
  const labels = months;
  const W=600,H=180,padL=40,padR=10,padT=16,padB=30;
  const iW=W-padL-padR, iH=H-padT-padB;

  let allVals=[];
  myMtns.forEach(m=>{ if(data[m.id]) allVals=allVals.concat(data[m.id]); });
  const maxV=Math.max(...allVals,1);
  const minV=0;

  const colors=['#100600','#c9a84c'];
  const x=(i)=>padL+i/(labels.length-1)*iW;
  const y=(v)=>padT+iH-(v-minV)/(maxV-minV)*iH;

  let paths='',areas='',dots='',grid='',xLabels='',yLabels='';

  // Grid
  [0,25,50,75,100].forEach(pct=>{
    const yy=padT+iH*(1-pct/100);
    const val=Math.round(minV+(maxV-minV)*pct/100);
    const lbl=metric==='revenue'?fmtMoney(val):val;
    grid+=`<line x1="${padL}" y1="${yy}" x2="${W-padR}" y2="${yy}" stroke="rgba(16,6,0,.06)" stroke-width="1"/>`;
    yLabels+=`<text x="${padL-6}" y="${yy+4}" text-anchor="end" class="chart-label">${lbl}</text>`;
  });

  // X labels
  labels.forEach((l,i)=>{
    xLabels+=`<text x="${x(i)}" y="${H-6}" text-anchor="middle" class="chart-label">${l}</text>`;
  });

  // Lines per mountain
  myMtns.forEach((m,mi)=>{
    const vals=data[m.id];
    if(!vals) return;
    const color=colors[mi]||'#888';
    const pts=vals.map((_,i)=>({px:x(i),py:y(vals[i])}));

    // Area path
    let aPath=`M${pts[0].px},${y(0)}`;
    pts.forEach(p=>aPath+=` L${p.px},${p.py}`);
    aPath+=` L${pts[pts.length-1].px},${y(0)} Z`;
    areas+=`<path d="${aPath}" fill="${color}" opacity=".05" class="area-path"/>`;

    // Line path
    let lPath=`M${pts[0].px},${pts[0].py}`;
    for(let i=1;i<pts.length;i++){
      const cpx=(pts[i-1].px+pts[i].px)/2;
      lPath+=` C${cpx},${pts[i-1].py} ${cpx},${pts[i].py} ${pts[i].px},${pts[i].py}`;
    }
    paths+=`<path d="${lPath}" stroke="${color}" stroke-width="2.5" class="line-path" style="stroke-dasharray:${W*2};stroke-dashoffset:${W*2};animation:drawLine 1s ease forwards;animation-delay:${mi*.2}s;"/>`;

    // Dots
    pts.forEach((p,i)=>{
      const lbl=metric==='revenue'?fmtMoney(vals[i]):vals[i];
      dots+=`<circle cx="${p.px}" cy="${p.py}" r="4" fill="${color}" stroke="white" stroke-width="2" class="line-dot"
        onmouseenter="showTooltip(event,'${m.name} · ${labels[i]} · ${lbl}')"
        onmouseleave="hideTooltip()" onclick="showToast('${m.name}: ${labels[i]} = ${lbl}','info')"/>`;
    });
  });

  return `<svg viewBox="0 0 ${W} ${H}" class="chart-svg" preserveAspectRatio="none">
    <style>
      @keyframes drawLine { to { stroke-dashoffset:0; } }
    </style>
    ${grid}${xLabels}${yLabels}${areas}${paths}${dots}
  </svg>`;
}

/* ── DONUT ── */
function drawDonut(stats) {
  const colors=['#b8ae90','#887b58','#5e5337b6'];
  const diffs=['easy','moderate','hard'];
  const counts=diffs.map(d=>stats.reduce((s,m)=>s+(diffBreakdown[m.id]?.[d]||0),0));
  const total=counts.reduce((a,b)=>a+b,0)||1;
  const cx=80,cy=80,r=62,ri=44;
  let angle=0;
  let segments='';
  counts.forEach((v,i)=>{
    const pct=v/total;
    const startA=angle,endA=angle+pct*2*Math.PI;
    const x1=cx+r*Math.cos(startA),y1=cy+r*Math.sin(startA);
    const x2=cx+r*Math.cos(endA),y2=cy+r*Math.sin(endA);
    const xi1=cx+ri*Math.cos(startA),yi1=cy+ri*Math.sin(startA);
    const xi2=cx+ri*Math.cos(endA),yi2=cy+ri*Math.sin(endA);
    const large=pct>.5?1:0;
    if(v>0) segments+=`<path d="M${x1},${y1} A${r},${r} 0 ${large} 1 ${x2},${y2} L${xi2},${yi2} A${ri},${ri} 0 ${large} 0 ${xi1},${yi1} Z"
      fill="${colors[i]}" class="donut-segment"
      onmouseenter="showTooltip(event,'${diffs[i]}: ${v} bookings (${Math.round(pct*100)}%)')"
      onmouseleave="hideTooltip()"/>`;
    angle=endA;
  });
  return `<svg viewBox="0 0 160 160" style="width:160px;height:160px;">
    ${segments}
    <text x="80" y="76" text-anchor="middle" font-family="'DM Mono',monospace" font-size="20" font-weight="700" fill="var(--ink)">${total}</text>
    <text x="80" y="92" text-anchor="middle" font-size="10" fill="var(--ink3)">bookings</text>
  </svg>`;
}

function switchTab(name,el) {
  document.querySelectorAll('.section-tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
}
function setRange(r) { activeRange=r; renderAnalytics(); }

function showTooltip(e,msg) {
  const t=document.getElementById('tooltip');
  t.textContent=msg; t.classList.add('show');
  t.style.left=(e.clientX+14)+'px'; t.style.top=(e.clientY-10)+'px';
}
function hideTooltip() { document.getElementById('tooltip').classList.remove('show'); }

init();
</script>
</body>
</html>
