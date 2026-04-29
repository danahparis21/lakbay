<?php
// dashboard.php - Fixed version
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$manager_id = $_SESSION['user_id'];

// Get manager's assigned mountains
$stmt = $pdo->prepare("
    SELECT m.* 
    FROM mountains m
    INNER JOIN manager_mountains mm ON m.id = mm.mountain_id
    WHERE mm.manager_id = ?
    ORDER BY m.name
");
$stmt->execute([$manager_id]);
$assigned_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mountain_ids = array_column($assigned_mountains, 'id');
$mountain_ids_placeholder = implode(',', array_fill(0, count($mountain_ids), '?'));

// Get bookings for assigned mountains only
$bookings = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            m.name as mountain_name,
            u.name as guide_name,
            u.id as guide_user_id
        FROM bookings b
        INNER JOIN mountains m ON b.mountain_id = m.id
        LEFT JOIN users u ON b.guide_id = u.id
        WHERE b.mountain_id IN ($mountain_ids_placeholder)
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

// Format mountains for JavaScript
$mountains_json = json_encode(array_map(function($m) {
    return [
        'id' => $m['id'],
        'name' => $m['name'],
        'location' => $m['location'],
        'elevation' => $m['elevation'],
        'difficulty' => $m['difficulty'],
        'fees' => [
            'reg' => (int)$m['registration_fee'],
            'env' => (int)$m['environmental_fee']
        ]
    ];
}, $assigned_mountains));

// Format bookings for JavaScript
$bookings_json = json_encode(array_map(function($b) {
    $hikers = [];
    for ($i = 0; $i < ($b['number_of_hikers'] ?? 1); $i++) {
        $hikers[] = ['name' => "Hiker " . ($i+1)];
    }
    
    return [
        'id' => $b['id'],
        'bookingNumber' => $b['booking_number'],
        'mountainId' => $b['mountain_id'],
        'mountain' => $b['mountain_name'],
        'guideId' => $b['guide_id'],
        'guideName' => $b['guide_name'] ?? 'Unassigned',
        'date' => $b['hike_date'],
        'pax' => (int)$b['number_of_hikers'],
        'totalFee' => (float)($b['total_amount'] ?? 0),
        'status' => $b['status'],
        'downpaymentStatus' => $b['downpayment_status'],
        'paymentStatus' => $b['payment_status'],
        'hikers' => $hikers
    ];
}, $bookings));

// Manager info for JS
$manager_name = $manager['name'];
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));

$manager_role = ucfirst($manager['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY Manager — Dashboard</title>
<link rel="stylesheet" href="manager.css">

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY Manager — Dashboard</title>
<link rel="stylesheet" href="manager.css">
<style>
/* Dashboard specific styles only - NOT sidebar styles */
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

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 20px; }
.stat-card { background: var(--white); border-radius: var(--r); border: 1px solid var(--border); padding: 20px; display: flex; align-items: center; gap: 16px; transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon.ink { background: var(--ink); color: white; }
.stat-icon.amber { background: var(--amber-bg); color: var(--amber); }
.stat-icon.green { background: var(--green-bg); color: var(--green); }
.stat-icon.gold { background: rgba(201,168,76,0.12); color: var(--gold); }
.stat-icon svg { width: 22px; height: 22px; stroke: currentColor; }
.stat-val { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; line-height: 1; }
.stat-label { font-size: 11px; color: var(--ink3); margin-top: 4px; }
.stat-change { font-size: 11px; display: flex; align-items: center; gap: 4px; margin-top: 4px; }
.stat-change svg { width: 12px; height: 12px; }
.stat-change.up { color: var(--green); }
.stat-change.down { color: var(--red); }

.two-col { display: grid; grid-template-columns: 1fr 0.9fr; gap: 24px; margin-top: 24px; }
@media (max-width: 1024px) { .two-col { grid-template-columns: 1fr; } }

.mtn-overview-card { background: var(--white); border-radius: var(--r); border: 1px solid var(--border); overflow: hidden; transition: transform .2s; margin-bottom: 16px; }
.mtn-overview-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.mtn-card-hero { height: 120px; background-size: cover; background-position: center; position: relative; }
.mtn-card-hero-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(16,6,0,0.2), rgba(16,6,0,0.75)); display: flex; align-items: flex-end; padding: 14px; }
.mtn-card-name { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: white; }
.mtn-card-sub { font-size: 11px; color: rgba(255,255,255,0.65); margin-top: 2px; }
.mtn-card-body { padding: 16px; }
.mtn-card-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.mtn-mini-stat { text-align: center; background: var(--off); border-radius: 9px; padding: 10px 8px; }
.mtn-mini-val { font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700; color: var(--ink); }
.mtn-mini-lbl { font-size: 9px; color: var(--ink3); margin-top: 2px; text-transform: uppercase; letter-spacing: .8px; }
.mtn-fee-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
.mtn-fee-row:last-child { border-bottom: none; }

.booking-mini { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
.booking-mini:last-child { border-bottom: none; }
.booking-mini-num { font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 700; color: var(--ink); background: var(--off); border-radius: 6px; padding: 4px 8px; flex-shrink: 0; }
.booking-mini-info { flex: 1; min-width: 0; }
.booking-mini-mountain { font-size: 13px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.booking-mini-meta { font-size: 11px; color: var(--ink3); margin-top: 1px; }
.booking-mini-right { text-align: right; flex-shrink: 0; }
.booking-mini-fee { font-size: 12px; font-weight: 700; color: var(--ink); font-family: 'DM Mono', monospace; }

.collection-section { display: flex; align-items: center; gap: 20px; padding: 20px 0; }
.collection-ring { flex-shrink: 0; position: relative; }
.ring-wrap { position: relative; width: 80px; height: 80px; }
.ring-val { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700; }
.collection-breakdown { flex: 1; }
.collection-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.collection-row:last-child { border-bottom: none; }
.collection-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }

.quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.qa-btn { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 18px 10px; background: var(--off); border-radius: 14px; border: 1.5px solid var(--border); cursor: pointer; transition: all .15s; text-decoration: none; color: var(--ink); text-align: center; }
.qa-btn:hover { background: var(--ink); color: var(--white); border-color: var(--ink); transform: translateY(-2px); }
.qa-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--white); display: flex; align-items: center; justify-content: center; }
.qa-icon svg { width: 18px; height: 18px; stroke: var(--ink); stroke-width: 1.8; }
.qa-btn:hover .qa-icon svg { stroke: var(--white); }
.qa-label { font-size: 12px; font-weight: 600; line-height: 1.3; }
@media (max-width: 640px) { .quick-actions { grid-template-columns: repeat(2, 1fr); } }

.btn-full { width: 100%; justify-content: center; }
</style>
</head>
<body>
<div class="app-shell">

<?php $activePage = 'dashboard'; ?>
<?php include 'shared_sidebar.php'; ?>

<div class="main-area" id="mainArea">
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
      <div class="topbar-avatar" id="topbarAvatar"><?= $manager_initials ?></div>
    </div>
  </div>

  <div class="content" id="dashContent"></div>
</div>
</div>

<div class="toast" id="toast"></div>
<script>
// Data from PHP
const ALL_MOUNTAINS = <?= $mountains_json ?>;
const ALL_BOOKINGS = <?= $bookings_json ?>;
const MANAGER = {
    id: <?= $manager_id ?>,
    name: '<?= addslashes($manager_name) ?>',
    initials: '<?= $manager_initials ?>',
    role: '<?= $manager_role ?>',
    email: '<?= addslashes($manager['email']) ?>'
};

function getMyMountains() { return ALL_MOUNTAINS; }
function getMyBookings() { return ALL_BOOKINGS; }

function getPayStatus(bookingId, hikerIndex, feeType) {
    const booking = ALL_BOOKINGS.find(b => b.id === bookingId);
    if (!booking) return 'unpaid';
    if (booking.paymentStatus === 'paid') return 'paid';
    if (booking.downpaymentStatus === 'paid' && feeType === 'reg') return 'paid';
    return 'unpaid';
}

function fmtMoney(amount) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 0 }).format(amount);
}

function fmtDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainArea = document.getElementById('mainArea');
    if (sidebar && mainArea) {
        sidebar.classList.toggle('collapsed');
        mainArea.classList.toggle('expanded');
    }
}

function updateClock() {
    const d = new Date();
    const dateEl = document.getElementById('topbarDate');
    if (dateEl) {
        dateEl.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() + ' ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    }
}

function hour() {
    const h = new Date().getHours();
    return h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening';
}

function renderDashboard() {
    const bookings = getMyBookings();
    const myMtns = getMyMountains();
    
    const totalBookings = bookings.length;
    const pendingCount = bookings.filter(b => b.status === 'pending').length;
    const confirmedCount = bookings.filter(b => b.status === 'confirmed').length;
    const completedCount = bookings.filter(b => b.status === 'completed').length;
    const totalRevExpected = bookings.filter(b => b.status !== 'cancelled').reduce((s, b) => s + b.totalFee, 0);
    const totalHikers = bookings.filter(b => b.status !== 'cancelled').reduce((s, b) => s + b.pax, 0);
    
    let paidReg = 0, totalReg = 0, paidEnv = 0, totalEnv = 0;
    bookings.filter(b => b.status !== 'cancelled').forEach(b => {
        const mtn = ALL_MOUNTAINS.find(m => m.id === b.mountainId);
        if (!mtn) return;
        for (let i = 0; i < b.pax; i++) {
            totalReg++;
            if (getPayStatus(b.id, i, 'reg') === 'paid') paidReg++;
            if (mtn.fees.env > 0) {
                totalEnv++;
                if (getPayStatus(b.id, i, 'env') === 'paid') paidEnv++;
            }
        }
    });
    
    const collPct = totalReg > 0 ? Math.round((paidReg / totalReg) * 100) : 0;
    const imgs = {
        1: 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=60',
        2: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=60',
        3: 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&q=60',
        4: 'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=600&q=60'
    };
    
    document.getElementById('dashContent').innerHTML = `
        <div class="hero-banner">
            <div>
                <div class="hero-greeting">Good ${hour()}, Manager</div>
                <div class="hero-name">${MANAGER.name}</div>
                <div class="hero-mountains">
                    ${myMtns.map(m => `<div class="hero-mtn-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>${m.name}</div>`).join('')}
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
        
        <div class="stat-grid">
            <div class="stat-card"><div class="stat-icon ink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg></div><div><div class="stat-val">${totalBookings}</div><div class="stat-label">Total Bookings</div></div></div>
            <div class="stat-card"><div class="stat-icon amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="stat-val">${pendingCount}</div><div class="stat-label">Pending Response</div></div></div>
            <div class="stat-card"><div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="stat-val">${confirmedCount + completedCount}</div><div class="stat-label">Active + Done</div></div></div>
            <div class="stat-card"><div class="stat-icon gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div><div class="stat-val" style="font-size:22px;">${fmtMoney(totalRevExpected)}</div><div class="stat-label">Expected Revenue</div></div></div>
        </div>
        
        <div class="two-col">
            <div>
                <div style="font-size:12px;font-weight:700;color:var(--ink3);margin-bottom:12px;">🏔️ Your Mountains</div>
                ${myMtns.map(m => {
                    const mb = bookings.filter(b => b.mountainId === m.id && b.status !== 'cancelled');
                    const pending = mb.filter(b => b.status === 'pending').length;
                    const hikerCount = mb.reduce((s, b) => s + b.pax, 0);
                    return `
                        <div class="mtn-overview-card">
                            <div class="mtn-card-hero" style="background-image:url('${imgs[m.id] || imgs[1]}')">
                                <div class="mtn-card-hero-overlay">
                                    <div><div class="mtn-card-name">${m.name}</div><div class="mtn-card-sub">${m.location} · ${m.elevation}</div></div>
                                </div>
                            </div>
                            <div class="mtn-card-body">
                                <div class="mtn-card-stats">
                                    <div class="mtn-mini-stat"><div class="mtn-mini-val">${mb.length}</div><div class="mtn-mini-lbl">Bookings</div></div>
                                    <div class="mtn-mini-stat"><div class="mtn-mini-val">${hikerCount}</div><div class="mtn-mini-lbl">Hikers</div></div>
                                    <div class="mtn-mini-stat" style="${pending > 0 ? 'background:#fff8e1;' : ''}"><div class="mtn-mini-val" style="${pending > 0 ? 'color:var(--amber);' : ''}">${pending}</div><div class="mtn-mini-lbl">Pending</div></div>
                                </div>
                                <div class="mtn-fee-row"><span>Registration fee</span><span>${fmtMoney(m.fees.reg)}/head</span></div>
                                ${m.fees.env > 0 ? `<div class="mtn-fee-row"><span>Environmental fee</span><span>${fmtMoney(m.fees.env)}/head</span></div>` : ''}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            
            <div>
                <div class="panel"><div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Fee Collection Rate</div></div><div class="panel-body"><div class="collection-section"><div class="collection-ring"><div class="ring-wrap"><svg width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="33" stroke="#f4f1ec" stroke-width="8" fill="none"/><circle cx="40" cy="40" r="33" stroke="#100600" stroke-width="8" fill="none" stroke-dasharray="207.3" stroke-dashoffset="${207.3 * (1 - collPct / 100)}" stroke-linecap="round"/></svg><div class="ring-val">${collPct}%</div></div></div><div class="collection-breakdown"><div class="collection-row"><span><span class="collection-dot" style="background:var(--green);"></span>Registration collected</span><span>${paidReg}/${totalReg}</span></div>${totalEnv > 0 ? `<div class="collection-row"><span><span class="collection-dot" style="background:var(--amber);"></span>Environmental collected</span><span>${paidEnv}/${totalEnv}</span></div>` : ''}</div></div></div></div>
                
                <div class="panel"><div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Recent Bookings</div><a href="bookings_manager.php" class="btn btn-outline btn-sm">View All</a></div><div class="panel-body">${bookings.slice(0, 5).map(b => `<div class="booking-mini"><div class="booking-mini-num">${b.bookingNumber || b.id}</div><div class="booking-mini-info"><div class="booking-mini-mountain">${b.mountain}</div><div class="booking-mini-meta">${fmtDate(b.date)} · ${b.pax} hiker${b.pax > 1 ? 's' : ''}</div></div><div class="booking-mini-right"><div class="badge badge-${b.status}">${b.status}</div><div class="booking-mini-fee">${fmtMoney(b.totalFee)}</div></div></div>`).join('')}${bookings.length === 0 ? '<div style="text-align:center;padding:20px;">No bookings yet</div>' : ''}</div></div>
                
                <div class="panel"><div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>Quick Actions</div></div><div class="panel-body"><div class="quick-actions"><a href="bookings_manager.php" class="qa-btn"><div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div><div class="qa-label">Search Booking</div></a><a href="payments.php" class="qa-btn"><div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="qa-label">Manage Fees</div></a><a href="advisories.php" class="qa-btn"><div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div><div class="qa-label">Advisories</div></a><a href="analytics.php" class="qa-btn"><div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="qa-label">Reports</div></a></div></div></div>
            </div>
        </div>
    `;
    
    setInterval(() => { const el = document.getElementById('heroClock'); if (el) el.textContent = new Date().toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', second:'2-digit'}); }, 1000);
}

setInterval(updateClock, 1000);
updateClock();

// Set topbar avatar
document.getElementById('topbarAvatar').textContent = MANAGER.initials;
renderDashboard();
</script>
</body>
</html>