<!-- manager_nav.php — shared sidebar + topbar + data -->
<script>
/* ═══════════════════════════════════════
   SHARED DATA — Mountain Manager App
═══════════════════════════════════════ */

// Manager profile — in production this comes from session/auth
const MANAGER = {
  id: 1,
  name: "Roberto Manalang",
  initials: "RM",
  role: "Mountain Manager",
  mountainIds: [1, 2]   // this manager handles Mt. Batulao & Mt. Talamitam
};

const ALL_MOUNTAINS = [
  { id:1, name:"Mt. Batulao",   location:"Nasugbu, Batangas", difficulty:"moderate", elevation:"811m", area:"Private Property",
    fees:{ reg:150, env:120, guideDay:900, guideON:1600, campFee:50, parkDay:100, parkON:150 } },
  { id:2, name:"Mt. Talamitam", location:"Nasugbu, Batangas", difficulty:"easy",     elevation:"630m", area:"Barangay-managed",
    fees:{ reg:100, env:0,   guideDay:700, guideON:1100, campFee:0,  parkDay:80,  parkON:80  } },
  { id:3, name:"Mt. Apayang",   location:"Batangas",           difficulty:"hard",     elevation:"980m", area:"DENR Protected",
    fees:{ reg:200, env:0,   guideDay:1200,guideON:2000, campFee:100,parkDay:0,   parkON:0   } },
  { id:4, name:"Mt. Lantik",    location:"Alfonso, Cavite",    difficulty:"moderate", elevation:"710m", area:"Community-managed",
    fees:{ reg:130, env:100, guideDay:900, guideON:1500, campFee:0,  parkDay:100, parkON:100 } }
];

const ALL_GUIDES = [
  { id:1, name:"John Dela Cruz", initials:"JD", mountainIds:[1,3], rating:4.9, phone:"+63 912 345 6789" },
  { id:2, name:"Maya Reyes",     initials:"MR", mountainIds:[1,2], rating:4.8, phone:"+63 923 456 7890" },
  { id:3, name:"Rico Cabanlit",  initials:"RC", mountainIds:[4],   rating:5.0, phone:"+63 934 567 8901" },
  { id:4, name:"Elena Llorente", initials:"EL", mountainIds:[1],   rating:4.7, phone:"+63 945 678 9012" }
];

// Payment status per hiker per fee type: 'paid' | 'unpaid' | 'waived'
// Stored in localStorage key: `pay_${bookingId}_${hikerIdx}_${feeType}`
const PAY_STORE_KEY = 'lakbay_mgr_payments';

function loadPayments() {
  const s = localStorage.getItem(PAY_STORE_KEY);
  return s ? JSON.parse(s) : {};
}
function savePayments(p) { localStorage.setItem(PAY_STORE_KEY, JSON.stringify(p)); }
function getPayKey(bookingId, hikerIdx, feeType) { return `${bookingId}_${hikerIdx}_${feeType}`; }
function getPayStatus(bookingId, hikerIdx, feeType) {
  const p = loadPayments();
  return p[getPayKey(bookingId, hikerIdx, feeType)] || 'unpaid';
}
function setPayStatus(bookingId, hikerIdx, feeType, status) {
  const p = loadPayments();
  p[getPayKey(bookingId, hikerIdx, feeType)] = status;
  savePayments(p);
}

// Activity log
const LOG_KEY = 'lakbay_mgr_log';
function loadLog() { const s=localStorage.getItem(LOG_KEY); return s?JSON.parse(s):[]; }
function addLog(msg, type='ink') {
  const log = loadLog();
  log.unshift({ msg, type, time: new Date().toISOString() });
  if(log.length>50) log.pop();
  localStorage.setItem(LOG_KEY, JSON.stringify(log));
}

// Bookings seeded
const SEED_BOOKINGS = [
  { id:"BK001", mountainId:1, mountain:"Mt. Batulao",   date:"2025-05-20", time:"06:00", type:"day",       status:"pending",   guideId:1, guideName:"John Dela Cruz", guideInitials:"JD", pax:3, hikers:["Jamie Rivera","Maria Santos","Carlo Tan"],   totalFee:4170, createdAt:"2025-04-28T07:00:00Z", camping:false },
  { id:"BK002", mountainId:2, mountain:"Mt. Talamitam", date:"2025-05-25", time:"07:00", type:"day",       status:"confirmed", guideId:2, guideName:"Maya Reyes",      guideInitials:"MR", pax:1, hikers:["Jamie Rivera"],                               totalFee:700,  createdAt:"2025-04-28T09:00:00Z", camping:false },
  { id:"BK003", mountainId:1, mountain:"Mt. Batulao",   date:"2025-05-18", time:"04:00", type:"overnight", status:"completed", guideId:1, guideName:"John Dela Cruz",  guideInitials:"JD", pax:4, hikers:["Alex Cruz","Bea Santos","Carl Tan","Diana Lim"],totalFee:6780, createdAt:"2025-04-20T06:00:00Z", camping:true  },
  { id:"BK004", mountainId:2, mountain:"Mt. Talamitam", date:"2025-06-01", time:"07:00", type:"day",       status:"pending",   guideId:2, guideName:"Maya Reyes",      guideInitials:"MR", pax:5, hikers:["Rosa M.","Sam V.","Tina A.","Uri B.","Vera C."],totalFee:4200, createdAt:"2025-04-29T08:00:00Z", camping:false },
  { id:"BK005", mountainId:1, mountain:"Mt. Batulao",   date:"2025-06-07", time:"16:00", type:"late",      status:"confirmed", guideId:4, guideName:"Elena Llorente",  guideInitials:"EL", pax:2, hikers:["Marco Reyes","Sophie Garcia"],                totalFee:2220, createdAt:"2025-04-29T10:00:00Z", camping:false },
  { id:"BK006", mountainId:1, mountain:"Mt. Batulao",   date:"2025-04-14", time:"05:30", type:"day",       status:"completed", guideId:2, guideName:"Maya Reyes",      guideInitials:"MR", pax:6, hikers:["Ana L.","Ben U.","Cara T.","Dan R.","Eva S.","Fin W."],totalFee:7320, createdAt:"2025-04-10T07:00:00Z", camping:false },
  { id:"BK007", mountainId:2, mountain:"Mt. Talamitam", date:"2025-06-14", time:"08:00", type:"day",       status:"pending",   guideId:2, guideName:"Maya Reyes",      guideInitials:"MR", pax:2, hikers:["Gina P.","Hans M."],                          totalFee:1400, createdAt:"2025-04-30T11:00:00Z", camping:false },
  { id:"BK008", mountainId:1, mountain:"Mt. Batulao",   date:"2025-06-20", time:"04:00", type:"overnight", status:"confirmed", guideId:1, guideName:"John Dela Cruz",  guideInitials:"JD", pax:3, hikers:["Iris K.","Jake L.","Kim N."],                  totalFee:5490, createdAt:"2025-04-30T13:00:00Z", camping:true  },
  { id:"BK009", mountainId:2, mountain:"Mt. Talamitam", date:"2025-05-29", time:"06:00", type:"day",       status:"cancelled", guideId:2, guideName:"Maya Reyes",      guideInitials:"MR", pax:3, hikers:["Lena O.","Mike P.","Nina Q."],                 totalFee:2100, createdAt:"2025-04-25T09:00:00Z", camping:false },
  { id:"BK010", mountainId:1, mountain:"Mt. Batulao",   date:"2025-07-04", time:"04:00", type:"overnight", status:"pending",   guideId:1, guideName:"John Dela Cruz",  guideInitials:"JD", pax:8, hikers:["Oscar R.","Petra S.","Quinn T.","Rita U.","Sam V.","Tess W.","Uma X.","Vic Y."],totalFee:12160, createdAt:"2025-04-30T15:00:00Z", camping:true  }
];

function loadBookings() {
  const s = localStorage.getItem('lakbay_mgr_bookings');
  return s ? JSON.parse(s) : JSON.parse(JSON.stringify(SEED_BOOKINGS));
}
function saveBookings(b) { localStorage.setItem('lakbay_mgr_bookings', JSON.stringify(b)); }

// Manager's mountains only
function getMyMountains() { return ALL_MOUNTAINS.filter(m => MANAGER.mountainIds.includes(m.id)); }
function getMyBookings()  {
  return loadBookings().filter(b => MANAGER.mountainIds.includes(b.mountainId));
}

// Utilities
function fmtDate(d) {
  if(!d) return '—';
  const dt = new Date(d+'T00:00:00');
  return dt.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
}
function fmtTime(t) {
  if(!t) return '';
  const [h,m]=t.split(':').map(Number);
  return `${h%12||12}:${String(m).padStart(2,'0')} ${h>=12?'PM':'AM'}`;
}
function fmtMoney(n) { return '₱'+Number(n).toLocaleString(); }
function initials(name) { return name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2); }
function relTime(iso) {
  const d=Math.floor((Date.now()-new Date(iso))/60000);
  if(d<1) return 'just now';
  if(d<60) return d+'m ago';
  if(d<1440) return Math.floor(d/60)+'h ago';
  return Math.floor(d/1440)+'d ago';
}

function showToast(msg, icon='check') {
  const t=document.getElementById('toast');
  if(!t) return;
  const icons = {
    check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>'
  };
  t.innerHTML = (icons[icon]||icons.check) + msg;
  t.classList.add('show');
  clearTimeout(t._to);
  t._to = setTimeout(()=>t.classList.remove('show'), 3000);
}
</script>
