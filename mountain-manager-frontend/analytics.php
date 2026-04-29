<?php
// analytics.php
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

// Get bookings for assigned mountains with payment data
$bookings = [];
$payment_data = [];
if (!empty($mountain_ids)) {
    $mountain_ids_placeholder = implode(',', array_fill(0, count($mountain_ids), '?'));
    
    // Get bookings with guide info
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
    
    // Get payment data for these bookings
    $booking_ids = array_column($bookings, 'id');
    if (!empty($booking_ids)) {
        $booking_ids_placeholder = implode(',', array_fill(0, count($booking_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT * FROM booking_payments 
            WHERE booking_id IN ($booking_ids_placeholder)
        ");
        $stmt->execute($booking_ids);
        $payment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payment_by_booking = [];
        foreach ($payment_data as $p) {
            $payment_by_booking[$p['booking_id']][] = $p;
        }
    }
}

// Get reviews for assigned mountains
$reviews = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as user_name
        FROM reviews r
        INNER JOIN users u ON r.user_id = u.id
        WHERE r.mountain_id IN ($mountain_ids_placeholder) AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate average rating per mountain
$avg_ratings = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT mountain_id, AVG(rating) as avg_rating, COUNT(*) as review_count
        FROM reviews
        WHERE mountain_id IN ($mountain_ids_placeholder) AND status = 'approved'
        GROUP BY mountain_id
    ");
    $stmt->execute($mountain_ids);
    $avg_ratings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($avg_ratings_raw as $ar) {
        $avg_ratings[$ar['mountain_id']] = [
            'avg' => round($ar['avg_rating'], 1),
            'count' => $ar['review_count']
        ];
    }
}

// Get guides who have worked on manager's mountains
$guides = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email,
            COUNT(b.id) as trip_count,
            SUM(b.number_of_hikers) as total_hikers
        FROM users u
        INNER JOIN bookings b ON b.guide_id = u.id
        WHERE b.mountain_id IN ($mountain_ids_placeholder)
        GROUP BY u.id, u.name, u.email
        ORDER BY trip_count DESC
    ");
    $stmt->execute($mountain_ids);
    $guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Prepare data for charts
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$current_year = date('Y');
$last_6_months = [];
for ($i = 5; $i >= 0; $i--) {
    $last_6_months[] = date('M', strtotime("-$i months"));
}

// Initialize trend data
$booking_trends = [];
$revenue_trends = [];
$hiker_trends = [];

foreach ($assigned_mountains as $mountain) {
    $booking_trends[$mountain['id']] = array_fill(0, 6, 0);
    $revenue_trends[$mountain['id']] = array_fill(0, 6, 0);
    $hiker_trends[$mountain['id']] = array_fill(0, 6, 0);
}

// Fill trend data from actual bookings
foreach ($bookings as $booking) {
    $hike_date = strtotime($booking['hike_date']);
    $booking_month = date('n', $hike_date) - 1;
    $booking_year = date('Y', $hike_date);
    
    $months_ago = (date('Y') * 12 + date('n')) - ($booking_year * 12 + date('n', $hike_date));
    if ($months_ago >= 0 && $months_ago < 6 && $booking['status'] !== 'cancelled') {
        $idx = 5 - $months_ago;
        $booking_trends[$booking['mountain_id']][$idx] += 1;
        $revenue_trends[$booking['mountain_id']][$idx] += (float)($booking['total_amount'] ?? 0);
        $hiker_trends[$booking['mountain_id']][$idx] += (int)($booking['number_of_hikers'] ?? 1);
    }
}

// Calculate weekday distribution
$weekday_data = [];
$weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
foreach ($assigned_mountains as $mountain) {
    $weekday_data[$mountain['id']] = array_fill(0, 7, 0);
}
foreach ($bookings as $booking) {
    if ($booking['status'] !== 'cancelled') {
        $weekday = date('w', strtotime($booking['hike_date']));
        $weekday_data[$booking['mountain_id']][$weekday] += 1;
    }
}

// Difficulty breakdown from mountains table
$difficulty_counts = ['easy' => 0, 'moderate' => 0, 'hard' => 0];
foreach ($assigned_mountains as $mountain) {
    $diff = strtolower($mountain['difficulty'] ?? 'moderate');
    if (isset($difficulty_counts[$diff])) {
        $difficulty_counts[$diff]++;
    } else {
        $difficulty_counts['moderate']++;
    }
}

// Status breakdown per mountain
$status_breakdown = [];
foreach ($assigned_mountains as $mountain) {
    $status_breakdown[$mountain['id']] = [
        'name' => $mountain['name'],
        'statuses' => [
            'pending' => 0,
            'waiting_payment' => 0,
            'active' => 0,
            'finished' => 0,
            'cancelled' => 0
        ]
    ];
}
foreach ($bookings as $booking) {
    $status = $booking['status'];
    if (isset($status_breakdown[$booking['mountain_id']]['statuses'][$status])) {
        $status_breakdown[$booking['mountain_id']]['statuses'][$status]++;
    }
}

// Calculate totals
$total_hikers = 0;
$total_revenue = 0;
$total_bookings = count($bookings);
$completed_bookings = 0;
foreach ($bookings as $b) {
    if ($b['status'] !== 'cancelled') {
        $total_hikers += (int)($b['number_of_hikers'] ?? 1);
        $total_revenue += (float)($b['total_amount'] ?? 0);
    }
    if ($b['status'] === 'finished') {
        $completed_bookings++;
    }
}

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

$manager_name = $manager['name'] ?? 'Manager';
$manager_role = $manager['role'] ?? 'manager';
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));

// Prepare JSON data for JavaScript
$mountains_json = json_encode($assigned_mountains);
$booking_trends_json = json_encode($booking_trends);
$revenue_trends_json = json_encode($revenue_trends);
$hiker_trends_json = json_encode($hiker_trends);
$weekday_data_json = json_encode($weekday_data);
$status_breakdown_json = json_encode($status_breakdown);
$guides_json = json_encode($guides);
$avg_ratings_json = json_encode($avg_ratings);
$difficulty_counts_json = json_encode($difficulty_counts);
$last_6_months_json = json_encode($last_6_months);
$weekdays_json = json_encode($weekdays);

function fmtMoney($amount) {
    return '₱' . number_format($amount, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LAKBAY Manager — Analytics</title>
<link rel="stylesheet" href="manager.css">
<style>
/* Analytics page specific styles only - NO sidebar styles */

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

/* Layout helpers */
.three-col { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin:20px 0; }
@media (max-width: 900px) { .three-col { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="app-shell">

<!-- SIDEBAR -->
<?php $activePage = 'analytics'; ?>
<?php include 'shared_sidebar.php'; ?>

<!-- Main Area -->
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
      <div class="topbar-avatar" id="topbarAvatar"><?php echo htmlspecialchars($manager_initials); ?></div>
    </div>
  </div>

  <div class="content" id="analyticsContent"></div>
</div>

<div class="chart-tooltip" id="tooltip"></div>
<div class="toast" id="toast"></div>

<script>
// Pass PHP data to JavaScript
const MANAGER = {
    name: "<?php echo addslashes($manager_name); ?>",
    initials: "<?php echo addslashes($manager_initials); ?>",
    role: "<?php echo addslashes($manager_role); ?>",
    mountainIds: <?php echo json_encode($mountain_ids); ?>
};

const MOUNTAINS = <?php echo $mountains_json; ?>;
const BOOKING_TRENDS = <?php echo $booking_trends_json; ?>;
const REVENUE_TRENDS = <?php echo $revenue_trends_json; ?>;
const HIKER_TRENDS = <?php echo $hiker_trends_json; ?>;
const WEEKDAY_DATA = <?php echo $weekday_data_json; ?>;
const STATUS_BREAKDOWN = <?php echo $status_breakdown_json; ?>;
const GUIDES = <?php echo $guides_json; ?>;
const AVG_RATINGS = <?php echo $avg_ratings_json; ?>;
const DIFFICULTY_COUNTS = <?php echo $difficulty_counts_json; ?>;
const LAST_6_MONTHS = <?php echo $last_6_months_json; ?>;
const WEEKDAYS = <?php echo $weekdays_json; ?>;

function fmtMoney(amount) {
    return '₱' + amount.toLocaleString();
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainArea').classList.toggle('expanded');
}

function showToast(msg, type) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast show ' + type;
    setTimeout(() => toast.className = 'toast', 2000);
}

function showTooltip(e, msg) {
    const t = document.getElementById('tooltip');
    t.textContent = msg;
    t.classList.add('show');
    t.style.left = (e.clientX + 14) + 'px';
    t.style.top = (e.clientY - 10) + 'px';
}

function hideTooltip() {
    document.getElementById('tooltip').classList.remove('show');
}

setInterval(() => {
    const el = document.getElementById('topbarDate');
    if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}, 1000);
document.getElementById('topbarDate').textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });

let activeRange = '6m';

function renderAnalytics() {
    const myMtns = MOUNTAINS;
    const bookings = <?php echo json_encode($bookings); ?>;
    const guides = GUIDES;
    
    const stats = myMtns.map(m => {
        const mb = bookings.filter(b => b.mountain_id === m.id && b.status !== 'cancelled');
        const hikers = mb.reduce((s, b) => s + (b.number_of_hikers || 1), 0);
        const revenue = mb.reduce((s, b) => s + (parseFloat(b.total_amount) || 0), 0);
        const completed = mb.filter(b => b.status === 'finished').length;
        const pending = mb.filter(b => b.status === 'pending' || b.status === 'waiting_payment').length;
        const rating = AVG_RATINGS[m.id] ? AVG_RATINGS[m.id].avg : '—';
        return { ...m, bookings: mb.length, hikers, revenue, completed, pending, rating };
    });
    
    const totalHikers = stats.reduce((s, m) => s + m.hikers, 0);
    const totalBookings = stats.reduce((s, m) => s + m.bookings, 0);
    
    document.getElementById('analyticsContent').innerHTML = `
        <div class="three-col">
            ${stats.slice(0, 2).map((m, i) => `
                <div class="insight-card">
                    <div class="insight-icon" style="background:rgba(201,168,76,.1);color:var(--gold);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>
                    </div>
                    <div>
                        <div class="insight-title">${escapeHtml(m.name)}</div>
                        <div class="insight-val">${m.hikers}</div>
                        <div class="insight-sub">Total hikers · ${m.bookings} bookings · ${m.completed} completed</div>
                        <div class="insight-trend trend-up">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 9 12 15 6 9"/></svg>
                            ${fmtMoney(m.revenue)} total fees · ★ ${m.rating}
                        </div>
                    </div>
                </div>
            `).join('')}
            <div class="insight-card">
                <div class="insight-icon" style="background:var(--green-bg);color:var(--green);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
                <div>
                    <div class="insight-title">Total Hikers</div>
                    <div class="insight-val">${totalHikers}</div>
                    <div class="insight-sub">Across all your mountains</div>
                    <div class="insight-trend trend-up">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="18 9 12 15 6 9"/></svg>
                        ${totalBookings} total bookings
                    </div>
                </div>
            </div>
        </div>
        
        <div class="compare-banner">
            ${stats.map((m, i) => `
                ${i > 0 ? '<div class="compare-divider"></div>' : ''}
                <div class="compare-col">
                    <div class="compare-label">Mountain ${i+1}</div>
                    <div class="compare-mtn">${escapeHtml(m.name)}</div>
                    <div class="compare-stat-row">
                        <div class="cs"><div class="cs-val">${m.bookings}</div><div class="cs-lbl">Bookings</div></div>
                        <div class="cs"><div class="cs-val">${m.hikers}</div><div class="cs-lbl">Hikers</div></div>
                        <div class="cs"><div class="cs-val">${fmtMoney(m.revenue)}</div><div class="cs-lbl">Fees</div></div>
                        <div class="cs"><div class="cs-val">${m.pending}</div><div class="cs-lbl">Pending</div></div>
                    </div>
                </div>
            `).join('')}
        </div>
        
        <div class="panel">
            <div class="panel-hdr">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Trends (Last 6 Months)
                </div>
                <div class="date-range-btns">
                    <div class="dr-btn ${activeRange === '1m' ? 'active' : ''}" onclick="setRange('1m')">1M</div>
                    <div class="dr-btn ${activeRange === '3m' ? 'active' : ''}" onclick="setRange('3m')">3M</div>
                    <div class="dr-btn ${activeRange === '6m' ? 'active' : ''}" onclick="setRange('6m')">6M</div>
                </div>
            </div>
            <div class="panel-body">
                <div class="section-tabs">
                    <div class="section-tab active" onclick="switchTab('bookings',this)">Bookings</div>
                    <div class="section-tab" onclick="switchTab('hikers',this)">Hikers</div>
                    <div class="section-tab" onclick="switchTab('revenue',this)">Revenue</div>
                </div>
                <div class="tab-content active" id="tab-bookings"><div class="chart-wrap" style="height:220px;">${drawLineChart('bookings')}</div></div>
                <div class="tab-content" id="tab-hikers"><div class="chart-wrap" style="height:220px;">${drawLineChart('hikers')}</div></div>
                <div class="tab-content" id="tab-revenue"><div class="chart-wrap" style="height:220px;">${drawLineChart('revenue')}</div></div>
                <div style="display:flex;gap:20px;margin-top:14px;flex-wrap:wrap;">
                    ${myMtns.map((m, i) => `<div style="display:flex;align-items:center;gap:7px;"><div style="width:24px;height:3px;background:${i === 0 ? 'var(--ink)' : 'var(--gold)'}"></div>${escapeHtml(m.name)}</div>`).join('')}
                </div>
            </div>
        </div>
        
        <div class="three-col">
            <div class="panel">
                <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>Difficulty Mix</div></div>
                <div class="panel-body" style="display:flex;flex-direction:column;align-items:center;gap:16px;">
                    <div style="width:160px;height:160px;">${drawDonut()}</div>
                    <div style="width:100%;">
                        ${[['easy', '#b8ae90'], ['moderate', '#887b58'], ['hard', '#5e5337']].map(([d, c]) => {
                            const cnt = DIFFICULTY_COUNTS[d] || 0;
                            const total = Object.values(DIFFICULTY_COUNTS).reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? Math.round(cnt / total * 100) : 0;
                            return `<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;"><div style="width:10px;height:10px;border-radius:50%;background:${c};"></div><div style="flex:1;">${d}</div><div style="font-weight:700;">${cnt}</div><div>${pct}%</div></div>`;
                        }).join('')}
                    </div>
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Guide Leaderboard</div></div>
                <div class="panel-body">
                    ${guides.slice(0, 5).map((g, i) => {
                        const trips = g.trip_count || 0;
                        const hikers = g.total_hikers || 0;
                        const maxTrips = Math.max(...guides.map(x => x.trip_count || 0), 1);
                        const initials = (g.name || '').split(' ').map(n => n[0]).join('').toUpperCase();
                        return `<div class="guide-row"><div class="guide-rank">${i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i + 1)}</div><div class="guide-av">${escapeHtml(initials)}</div><div class="guide-info"><div class="guide-name">${escapeHtml(g.name)}</div><div class="guide-meta">${hikers} hikers · ${trips} trips</div></div><div class="guide-bar-wrap"><div class="guide-bar"><div class="guide-bar-fill" style="width:${Math.round(trips / maxTrips * 100)}%;"></div></div></div><div class="guide-trips">${trips}</div></div>`;
                    }).join('')}
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>Busiest Days</div></div>
                <div class="panel-body">
                    ${myMtns.map(m => {
                        const data = WEEKDAY_DATA[m.id] || [0, 0, 0, 0, 0, 0, 0];
                        const max = Math.max(...data, 1);
                        return `<div style="margin-bottom:18px;"><div style="font-size:11px;font-weight:700;margin-bottom:8px;">${escapeHtml(m.name)}</div><div class="heatmap-grid">${WEEKDAYS.map(d => `<div class="heatmap-label">${d}</div>`).join('')}${data.map((v, i) => `<div class="heatmap-cell" style="background:rgba(16,6,0,${0.08 + (v / max) * 0.85});"></div>`).join('')}</div></div>`;
                    }).join('')}
                </div>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-hdr"><div class="panel-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Status Breakdown per Mountain</div></div>
            <div class="panel-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
                    ${myMtns.map(m => {
                        const statuses = STATUS_BREAKDOWN[m.id]?.statuses || { pending: 0, waiting_payment: 0, active: 0, finished: 0, cancelled: 0 };
                        const total = Object.values(statuses).reduce((a, b) => a + b, 0) || 1;
                        const statusColors = { pending: '#f59e0b', waiting_payment: '#f97316', active: '#3b82f6', finished: '#10b981', cancelled: '#ef4444' };
                        return `<div><div style="font-weight:700;margin-bottom:12px;">${escapeHtml(m.name)}</div>${Object.entries(statuses).map(([s, cnt]) => `<div style="margin-bottom:8px;"><div style="display:flex;justify-content:space-between;font-size:12px;"><span>${s.replace('_', ' ')}</span><span>${cnt}</span></div><div class="guide-bar"><div class="guide-bar-fill" style="width:${Math.round(cnt / total * 100)}%;background:${statusColors[s]};"></div></div></div>`).join('')}</div>`;
                    }).join('')}
                </div>
            </div>
        </div>
    `;
    
    setTimeout(() => {
        document.querySelectorAll('[data-w]').forEach(el => { el.style.width = el.dataset.w; });
        document.querySelectorAll('.guide-bar-fill').forEach(el => { const w = el.style.width; el.style.width = '0'; setTimeout(() => el.style.width = w, 50); });
    }, 200);
}

function drawLineChart(metric) {
    const myMtns = MOUNTAINS;
    let data;
    if (metric === 'bookings') data = BOOKING_TRENDS;
    else if (metric === 'hikers') data = HIKER_TRENDS;
    else data = REVENUE_TRENDS;
    
    const labels = LAST_6_MONTHS;
    const W = 600, H = 180, padL = 40, padR = 10, padT = 16, padB = 30;
    const iW = W - padL - padR, iH = H - padT - padB;
    
    let allVals = [];
    myMtns.forEach(m => { if (data[m.id]) allVals = allVals.concat(data[m.id]); });
    const maxV = Math.max(...allVals, 1);
    const minV = 0;
    const colors = ['#100600', '#c9a84c'];
    const x = (i) => padL + i / (labels.length - 1) * iW;
    const y = (v) => padT + iH - (v - minV) / (maxV - minV) * iH;
    
    let grid = '', yLabels = '', xLabels = '', areas = '', paths = '', dots = '';
    
    [0, 25, 50, 75, 100].forEach(pct => {
        const yy = padT + iH * (1 - pct / 100);
        const val = Math.round(minV + (maxV - minV) * pct / 100);
        const lbl = metric === 'revenue' ? fmtMoney(val) : val;
        grid += `<line x1="${padL}" y1="${yy}" x2="${W - padR}" y2="${yy}" stroke="rgba(16,6,0,.06)" stroke-width="1"/>`;
        yLabels += `<text x="${padL - 6}" y="${yy + 4}" text-anchor="end" class="chart-label">${lbl}</text>`;
    });
    
    labels.forEach((l, i) => { xLabels += `<text x="${x(i)}" y="${H - 6}" text-anchor="middle" class="chart-label">${l}</text>`; });
    
    myMtns.forEach((m, mi) => {
        const vals = data[m.id];
        if (!vals) return;
        const color = colors[mi] || '#888';
        const pts = vals.map((_, i) => ({ px: x(i), py: y(vals[i]) }));
        let aPath = `M${pts[0].px},${y(0)}`;
        pts.forEach(p => aPath += ` L${p.px},${p.py}`);
        aPath += ` L${pts[pts.length - 1].px},${y(0)} Z`;
        areas += `<path d="${aPath}" fill="${color}" opacity=".05"/>`;
        let lPath = `M${pts[0].px},${pts[0].py}`;
        for (let i = 1; i < pts.length; i++) {
            const cpx = (pts[i - 1].px + pts[i].px) / 2;
            lPath += ` C${cpx},${pts[i - 1].py} ${cpx},${pts[i].py} ${pts[i].px},${pts[i].py}`;
        }
        paths += `<path d="${lPath}" stroke="${color}" stroke-width="2.5" fill="none" class="line-path"/>`;
        pts.forEach((p, i) => {
            const lbl = metric === 'revenue' ? fmtMoney(vals[i]) : vals[i];
            dots += `<circle cx="${p.px}" cy="${p.py}" r="4" fill="${color}" stroke="white" stroke-width="2" class="line-dot" onmouseenter="showTooltip(event,'${escapeHtml(m.name)} · ${labels[i]} · ${lbl}')" onmouseleave="hideTooltip()"/>`;
        });
    });
    
    return `<svg viewBox="0 0 ${W} ${H}" class="chart-svg" preserveAspectRatio="none">${grid}${xLabels}${yLabels}${areas}${paths}${dots}</svg>`;
}

function drawDonut() {
    const colors = ['#b8ae90', '#887b58', '#5e5337'];
    const diffs = ['easy', 'moderate', 'hard'];
    const counts = diffs.map(d => DIFFICULTY_COUNTS[d] || 0);
    const total = counts.reduce((a, b) => a + b, 0) || 1;
    const cx = 80, cy = 80, r = 62, ri = 44;
    let angle = 0, segments = '';
    counts.forEach((v, i) => {
        const pct = v / total;
        const startA = angle, endA = angle + pct * 2 * Math.PI;
        const x1 = cx + r * Math.cos(startA), y1 = cy + r * Math.sin(startA);
        const x2 = cx + r * Math.cos(endA), y2 = cy + r * Math.sin(endA);
        const xi1 = cx + ri * Math.cos(startA), yi1 = cy + ri * Math.sin(startA);
        const xi2 = cx + ri * Math.cos(endA), yi2 = cy + ri * Math.sin(endA);
        const large = pct > 0.5 ? 1 : 0;
        if (v > 0) segments += `<path d="M${x1},${y1} A${r},${r} 0 ${large} 1 ${x2},${y2} L${xi2},${yi2} A${ri},${ri} 0 ${large} 0 ${xi1},${yi1} Z" fill="${colors[i]}" class="donut-segment"/>`;
        angle = endA;
    });
    return `<svg viewBox="0 0 160 160"><circle cx="80" cy="80" r="70" fill="var(--off)"/>${segments}<text x="80" y="76" text-anchor="middle" font-family="'DM Mono',monospace" font-size="20" font-weight="700" fill="var(--ink)">${MOUNTAINS.length}</text><text x="80" y="92" text-anchor="middle" font-size="10" fill="var(--ink3)">mountains</text></svg>`;
}

function switchTab(name, el) {
    document.querySelectorAll('.section-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}

function setRange(r) { activeRange = r; renderAnalytics(); }

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]));
}

renderAnalytics();
</script>
</body>
</html>