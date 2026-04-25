<?php
// manager_data.php - Data functions
session_start();

// Single mountain for this manager
function getManagerMountain() {
    return [
        'id' => 'mt1', 
        'name' => 'Mt. Pulag', 
        'location' => 'Benguet', 
        'fees' => ['reg' => 500, 'env' => 100],
        'description' => 'Highest peak in Luzon, famous for sea of clouds'
    ];
}

function getMyBookings() {
    return [
        [
            'id' => 'BK001', 'mountainId' => 'mt1', 'mountain' => 'Mt. Pulag',
            'date' => '2026-05-15', 'time' => '08:00', 'type' => 'day',
            'pax' => 4, 'hikers' => ['Juan Dela Cruz', 'Maria Santos', 'Jose Rizal', 'Andres Bonifacio'],
            'guideId' => 'g1', 'guideName' => 'Mark Reyes', 'guideInitials' => 'MR',
            'totalFee' => 2400, 'status' => 'confirmed', 'camping' => false,
            'specialRequests' => 'Need extra water bottles',
            'contactNumber' => '09123456789'
        ],
        [
            'id' => 'BK002', 'mountainId' => 'mt1', 'mountain' => 'Mt. Pulag',
            'date' => '2026-06-20', 'time' => '14:00', 'type' => 'overnight',
            'pax' => 3, 'hikers' => ['Sarah Geronimo', 'Christian Bautista', 'Regine Velasquez'],
            'guideId' => 'g2', 'guideName' => 'Carla Abellana', 'guideInitials' => 'CA',
            'totalFee' => 4500, 'status' => 'pending', 'camping' => true,
            'specialRequests' => 'Vegetarian meals',
            'contactNumber' => '09876543210'
        ],
        [
            'id' => 'BK003', 'mountainId' => 'mt1', 'mountain' => 'Mt. Pulag',
            'date' => '2026-07-10', 'time' => '10:00', 'type' => 'day',
            'pax' => 2, 'hikers' => ['Mike Reyes', 'Lisa Santos'],
            'guideId' => 'g1', 'guideName' => 'Mark Reyes', 'guideInitials' => 'MR',
            'totalFee' => 1200, 'status' => 'completed', 'camping' => false,
            'specialRequests' => '',
            'contactNumber' => '09234567890'
        ]
    ];
}

$MANAGER = (object)['name' => 'John Rivera', 'initials' => 'JR', 'mountainId' => 'mt1', 'mountainName' => 'Mt. Pulag'];
$ALL_GUIDES = [
    ['id' => 'g1', 'name' => 'Mark Reyes', 'rating' => 4.8, 'phone' => '09123456789', 'experience' => '8 years'],
    ['id' => 'g2', 'name' => 'Carla Abellana', 'rating' => 4.9, 'phone' => '09876543210', 'experience' => '6 years']
];

function loadBookings() { return getMyBookings(); }
function saveBookings($bookings) { /* Save to database */ }

// Store payment statuses in session for demo
if (!isset($_SESSION['payment_statuses'])) {
    $_SESSION['payment_statuses'] = [];
}

function getPayStatus($id, $idx, $type) {
    $key = $id . '_' . $idx . '_' . $type;
    return isset($_SESSION['payment_statuses'][$key]) ? $_SESSION['payment_statuses'][$key] : 'unpaid';
}

function setPayStatus($id, $idx, $type, $status) {
    $key = $id . '_' . $idx . '_' . $type;
    $_SESSION['payment_statuses'][$key] = $status;
}

function addLog($msg, $type) { 
    if (!isset($_SESSION['logs'])) $_SESSION['logs'] = [];
    array_unshift($_SESSION['logs'], ['msg' => $msg, 'type' => $type, 'time' => date('Y-m-d H:i:s')]);
}

function fmtDate($date) { return date('M d, Y', strtotime($date)); }
function fmtTime($time) { return date('g:i A', strtotime($time)); }
function fmtMoney($amt) { return '₱' . number_format($amt, 2); }
function initials($name) { return implode('', array_map(function($n) { return $n[0]; }, explode(' ', $name))); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY Manager — <?= getManagerMountain()['name'] ?> Bookings</title>
<style>
/* Base styles */
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --ink: #100600;
  --ink2: #3a2a1a;
  --ink3: #7a6a5a;
  --ink4: #b0a090;
  --white: #ffffff;
  --off: #faf9f7;
  --surface: #f4f1ec;
  --border: rgba(16,6,0,0.09);
  --border2: rgba(16,6,0,0.15);
  --gold: #c9a84c;
  --gold2: #e8c96a;
  --green: #2e7d32;
  --green-bg: #e8f5e9;
  --red: #c62828;
  --red-bg: #fce4ec;
  --amber: #e65100;
  --amber-bg: #fff3e0;
  --blue: #1565c0;
  --blue-bg: #e3f2fd;
  --r: 18px;
  --r-sm: 10px;
  --shadow: 0 4px 24px rgba(16,6,0,0.08);
  --shadow-lg: 0 16px 48px rgba(16,6,0,0.14);
  --shadow-xl: 0 24px 64px rgba(16,6,0,0.18);
  --glass: rgba(255,255,255,0.72);
  --sidebar-w: 260px;
  --sidebar-w-sm: 72px;
  --topbar-h: 64px;
  --mobile-nav-h: 60px;
}

html, body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--surface); color: var(--ink); min-height: 100vh; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(16,6,0,0.15); border-radius: 2px; }

/* Layout */
.app-shell { display: flex; min-height: 100vh; }

/* Sidebar - Desktop */
.sidebar {
  width: var(--sidebar-w);
  background: #F8F6F0;
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 200;
  transition: width .25s ease;
  overflow: hidden;
}
.sidebar.collapsed { width: var(--sidebar-w-sm); }
.sidebar.collapsed .nav-label,
.sidebar.collapsed .nav-text,
.sidebar.collapsed .sidebar-brand-text,
.sidebar.collapsed .sidebar-footer-text,
.sidebar.collapsed .mountain-badge-text { display: none; }
.sidebar.collapsed .sidebar-brand { justify-content: center; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 14px 0; }
.sidebar.collapsed .nav-item svg { margin: 0; }
.sidebar.collapsed .mountain-badge { justify-content: center; padding: 12px; }

.sidebar-brand {
  display: flex; align-items: center; gap: 12px;
  padding: 22px 24px 18px;
  border-bottom: 1px solid rgba(16,6,0,0.08);
  text-decoration: none;
}
.sidebar-logo {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--gold); display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sidebar-logo svg { width: 20px; height: 20px; }
.sidebar-brand-text { line-height: 1.2; }
.sidebar-app-name { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700; color: var(--ink); letter-spacing: .3px; }
.sidebar-app-sub { font-size: 9px; color: rgba(16,6,0,0.35); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 1px; }

.mountain-badge {
  display: flex; align-items: center; gap: 10px;
  margin: 14px 16px;
  background: linear-gradient(135deg, rgba(201,168,76,0.1), rgba(201,168,76,0.05));
  border: 1px solid rgba(201,168,76,0.3);
  border-radius: 12px;
  padding: 12px 14px;
}
.mountain-badge-icon {
  width: 34px; height: 34px; border-radius: 9px;
  background: var(--gold); display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.mountain-badge-icon svg { width: 16px; height: 16px; stroke: var(--ink); }
.mountain-badge-text { overflow: hidden; }
.mountain-badge-name { font-size: 12px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mountain-badge-role { font-size: 10px; color: rgba(16,6,0,0.5); margin-top: 1px; }

.nav-section { padding: 0 0 8px; flex: 1; overflow-y: auto; }
.nav-label { font-size: 9px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; color: rgba(16,6,0,0.35); padding: 16px 24px 6px; }
.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 24px; font-size: 13px; font-weight: 500;
  color: rgba(16,6,0,0.6); cursor: pointer; text-decoration: none;
  transition: all .15s; border-left: 3px solid transparent;
  white-space: nowrap;
}
.nav-item:hover { color: var(--ink); background: rgba(16,6,0,0.04); transform: translateX(2px); }
.nav-item.active { color: var(--ink); background: rgba(201,168,76,0.1); border-left-color: var(--gold); }
.nav-item svg { width: 17px; height: 17px; stroke: currentColor; stroke-width: 1.8; flex-shrink: 0; }
.nav-divider { height: 1px; background: rgba(16,6,0,0.08); margin: 8px 20px; }

.sidebar-footer {
  padding: 16px 20px; border-top: 1px solid rgba(16,6,0,0.08);
  display: flex; align-items: center; gap: 10px;
}
.sidebar-footer-avatar {
  width: 34px; height: 34px; border-radius: 50%; background: var(--gold);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: var(--ink); flex-shrink: 0;
}
.sidebar-footer-text { overflow: hidden; }
.sidebar-footer-name { font-size: 12px; font-weight: 700; color: var(--ink); }
.sidebar-footer-role { font-size: 10px; color: rgba(16,6,0,0.5); }

/* Main Area */
.main-area {
  flex: 1;
  margin-left: var(--sidebar-w);
  display: flex; flex-direction: column;
  min-height: 100vh;
  transition: margin-left .25s ease;
}
.main-area.expanded { margin-left: var(--sidebar-w-sm); }

/* Topbar */
.topbar {
  height: var(--topbar-h);
  background: var(--glass);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  position: sticky;
  top: 0;
  z-index: 100;
}
.topbar-left { display: flex; align-items: center; gap: 14px; }
.sidebar-toggle {
  width: 36px; height: 36px; border-radius: 9px; border: 1px solid var(--border2);
  background: var(--white); cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: var(--ink); transition: all .15s;
}
.sidebar-toggle:hover { background: var(--ink); color: var(--white); transform: rotate(90deg); }
.sidebar-toggle svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; }
.topbar-page-title { font-size: 15px; font-weight: 700; color: var(--ink); }
.topbar-page-sub { font-size: 11px; color: var(--ink3); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.topbar-date { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--ink4); padding: 6px 12px; background: var(--off); border-radius: 20px; }
.topbar-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--ink); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; cursor: pointer; transition: transform .2s; }
.topbar-avatar:hover { transform: scale(1.05); }

/* Mobile Bottom Navigation */
.mobile-bottom-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: var(--mobile-nav-h);
  background: var(--white);
  border-top: 1px solid var(--border);
  box-shadow: 0 -4px 12px rgba(0,0,0,0.05);
  z-index: 150;
  justify-content: space-around;
  align-items: center;
  padding: 8px 16px;
}
.mobile-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px 12px;
  border-radius: 12px;
  transition: all 0.2s;
  color: var(--ink3);
  text-decoration: none;
}
.mobile-nav-item svg {
  width: 22px;
  height: 22px;
  stroke: currentColor;
  stroke-width: 1.8;
}
.mobile-nav-item span {
  font-size: 10px;
  font-weight: 500;
}
.mobile-nav-item.active {
  color: var(--gold);
  background: rgba(201,168,76,0.1);
}
.mobile-nav-item:hover {
  background: var(--off);
}

/* Hide desktop sidebar on mobile */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  .main-area {
    margin-left: 0 !important;
  }
  .mobile-bottom-nav {
    display: flex;
  }
  .content {
    padding-bottom: calc(var(--mobile-nav-h) + 16px) !important;
  }
  .topbar {
    padding: 0 16px;
  }
}

/* Content */
.content { flex: 1; padding: 28px; display: flex; flex-direction: column; gap: 24px; }

/* Stats Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.stat-card {
  background: var(--white);
  border-radius: var(--r);
  border: 1px solid var(--border);
  padding: 20px;
  box-shadow: var(--shadow);
  text-align: center;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  border-radius: 50%;
  background: currentColor;
  opacity: 0;
  transform: translate(-50%, -50%);
  transition: width 0.6s, height 0.6s;
}
.stat-card:active::before {
  width: 300px;
  height: 300px;
  opacity: 0.1;
}
.stat-card:hover { transform: translateY(-4px) scale(1.02); box-shadow: var(--shadow-lg); }
.stat-value { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: var(--ink); line-height: 1; }
.stat-label { font-size: 11px; font-weight: 600; color: var(--ink3); margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: center; gap: 4px; }
.stat-card.pending .stat-value { color: var(--amber); }
.stat-card.confirmed .stat-value { color: var(--green); }
.stat-card.completed .stat-value { color: var(--blue); }

/* Badges */
.badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 600;
}
.badge-pending { background: var(--amber-bg); color: var(--amber); }
.badge-confirmed { background: var(--green-bg); color: var(--green); }
.badge-completed { background: var(--blue-bg); color: var(--blue); }
.badge-cancelled { background: var(--red-bg); color: var(--red); }
.badge-day { background: rgba(201,168,76,0.12); color: #7a5a10; }
.badge-overnight { background: rgba(16,6,0,0.07); color: var(--ink); }

/* Panel */
.panel {
  background: var(--white);
  border-radius: var(--r);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  overflow: hidden;
  transition: box-shadow 0.3s;
}
.panel:hover { box-shadow: var(--shadow-lg); }
.panel-body { padding: 20px 22px; }

/* Data Table */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead tr { background: var(--off); }
.data-table th { padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); }
.data-table td { padding: 16px; font-size: 13px; color: var(--ink); border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tbody tr { cursor: pointer; transition: all 0.2s; }
.data-table tbody tr:hover { background: var(--off); transform: scale(1.01); box-shadow: var(--shadow); }
.data-table tbody tr:active { transform: scale(0.99); }
.data-table tbody tr:last-child td { border-bottom: none; }

@media (max-width: 768px) {
  .data-table th, .data-table td {
    padding: 10px 12px;
    font-size: 12px;
  }
}

/* Buttons */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  padding: 8px 16px; border-radius: 8px; font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s;
  position: relative;
  overflow: hidden;
}
.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  border-radius: 50%;
  background: rgba(255,255,255,0.3);
  transform: translate(-50%, -50%);
  transition: width 0.4s, height 0.4s;
}
.btn:active::before {
  width: 200px;
  height: 200px;
}
.btn-primary { background: var(--ink); color: var(--white); }
.btn-primary:hover { background: var(--ink2); transform: translateY(-2px); box-shadow: var(--shadow); }
.btn-outline { background: transparent; border: 1.5px solid var(--border2); color: var(--ink); }
.btn-outline:hover { background: var(--ink); color: var(--white); border-color: var(--ink); transform: translateY(-2px); }
.btn-sm { padding: 6px 12px; font-size: 11px; }
.btn-xs { padding: 4px 10px; font-size: 10px; border-radius: 6px; }

/* Search Box */
.search-box {
  display: flex; align-items: center; gap: 10px;
  background: var(--off); border: 1.5px solid var(--border2);
  border-radius: 50px; padding: 8px 16px;
  transition: all 0.3s;
}
.search-box:focus-within { border-color: var(--gold); background: var(--white); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); transform: scale(1.02); }
.search-box svg { width: 14px; height: 14px; stroke: var(--ink4); stroke-width: 2; flex-shrink: 0; }
.search-box input { flex: 1; border: none; background: transparent; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--ink); outline: none; }
.search-box input::placeholder { color: var(--ink4); }

/* Select */
.select {
  appearance: none; padding: 8px 32px 8px 12px; border-radius: var(--r-sm);
  border: 1.5px solid var(--border2); background: var(--white);
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--ink);
  outline: none; cursor: pointer; transition: all 0.2s;
}
.select:hover { border-color: var(--gold); }
.select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }

/* Filter Row */
.filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 16px; }
.chip {
  padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
  border: 1.5px solid var(--border2); background: var(--white); color: var(--ink3);
  cursor: pointer; transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.chip svg {
  width: 12px;
  height: 12px;
  stroke: currentColor;
}
.chip:hover { border-color: var(--gold); color: var(--gold); transform: translateY(-2px); }
.chip.active { background: var(--gold); color: var(--ink); border-color: var(--gold); box-shadow: var(--shadow); }

/* Modal Popup */
.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}
.modal-overlay.open { display: flex; }
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.modal-container {
  background: var(--white);
  border-radius: 24px;
  width: 100%;
  max-width: 750px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-xl);
  animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
}
@keyframes modalSlideIn {
  from { transform: scale(0.9) translateY(-30px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}
.modal-header {
  padding: 20px 24px;
  background: linear-gradient(135deg, var(--ink) 0%, var(--ink2) 100%);
  color: var(--white);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}
.modal-header h2 { font-family: 'Playfair Display', serif; font-size: 20px; margin: 0; display: flex; align-items: center; gap: 8px; }
.modal-header-sub { font-size: 12px; opacity: 0.8; margin-top: 4px; font-family: 'DM Mono', monospace; }
.modal-close {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.1);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  transition: all 0.2s;
}
.modal-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }
.modal-body { flex: 1; overflow-y: auto; padding: 24px; background: var(--surface); }
.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  background: var(--white);
  flex-shrink: 0;
}

/* Status Display (Non-clickable) */
.status-display {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}
.status-display.pending { background: var(--amber-bg); color: var(--amber); }
.status-display.confirmed { background: var(--green-bg); color: var(--green); }
.status-display.completed { background: var(--blue-bg); color: var(--blue); }
.status-display.cancelled { background: var(--red-bg); color: var(--red); }

/* Info Section */
.info-section {
  background: var(--off);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 24px;
}
.info-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}
.info-row:last-child { border-bottom: none; }
.info-icon {
  width: 40px; height: 40px; border-radius: 12px;
  background: var(--white);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: var(--shadow);
}
.info-icon svg { width: 18px; height: 18px; stroke: var(--gold); }
.info-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--ink3); margin-bottom: 4px; }
.info-value { font-size: 14px; font-weight: 600; color: var(--ink); }

/* Hiker Section */
.hiker-section { margin-top: 20px; }
.hiker-section-title {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.payment-progress {
  background: var(--green-bg);
  color: var(--green);
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}
.hiker-card {
  background: var(--white);
  border-radius: 16px;
  padding: 16px;
  margin-bottom: 12px;
  border: 1px solid var(--border);
  transition: all 0.2s;
  cursor: pointer;
}
.hiker-card:hover { transform: translateX(4px); box-shadow: var(--shadow); border-color: var(--gold); }
.hiker-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}
.hiker-avatar {
  width: 48px; height: 48px; border-radius: 50%;
  background: linear-gradient(135deg, var(--ink) 0%, var(--ink2) 100%);
  color: var(--gold);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 700;
  transition: transform 0.2s;
}
.hiker-card:hover .hiker-avatar { transform: scale(1.05); }
.hiker-avatar.booker { background: linear-gradient(135deg, var(--gold) 0%, var(--gold2) 100%); color: var(--ink); }
.hiker-name { font-weight: 700; font-size: 15px; color: var(--ink); }
.hiker-role { font-size: 11px; color: var(--ink3); margin-top: 2px; }
.fee-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.fee-card {
  background: var(--off);
  border-radius: 12px;
  padding: 14px;
  transition: all 0.2s;
}
.fee-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.fee-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}
.fee-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--ink3); letter-spacing: 0.5px; }
.fee-amount { font-family: 'DM Mono', monospace; font-size: 16px; font-weight: 700; color: var(--ink); }
.fee-buttons { display: flex; gap: 8px; }
.fee-btn {
  flex: 1; padding: 8px; border-radius: 8px; font-size: 11px; font-weight: 600;
  cursor: pointer; border: 1.5px solid transparent; text-align: center;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  background: var(--white);
  transition: all 0.2s;
}
.fee-btn svg { width: 12px; height: 12px; }
.fee-btn:hover { transform: translateY(-2px); }
.fee-btn:active { transform: translateY(0); }
.fee-btn.paid { border-color: rgba(46,125,50,0.3); color: var(--green); }
.fee-btn.paid.active { background: var(--green); color: white; box-shadow: 0 2px 8px rgba(46,125,50,0.3); }
.fee-btn.unpaid { border-color: rgba(198,40,40,0.3); color: var(--red); }
.fee-btn.unpaid.active { background: var(--red); color: white; box-shadow: 0 2px 8px rgba(198,40,40,0.3); }
.fee-btn.waived { border-color: rgba(21,101,192,0.3); color: var(--blue); }
.fee-btn.waived.active { background: var(--blue); color: white; box-shadow: 0 2px 8px rgba(21,101,192,0.3); }

/* Confetti Animation */
.confetti {
  position: fixed;
  width: 10px;
  height: 10px;
  position: absolute;
  animation: confetti-fall 3s linear forwards;
}
@keyframes confetti-fall {
  to { transform: translateY(100vh) rotate(360deg); opacity: 0; }
}

/* Toast */
.toast {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: linear-gradient(135deg, var(--ink) 0%, var(--ink2) 100%);
  color: white;
  padding: 14px 24px;
  border-radius: 50px;
  font-size: 13px;
  font-weight: 500;
  opacity: 0;
  transition: all 0.3s;
  z-index: 1100;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: var(--shadow-lg);
  transform: translateX(100%);
}
.toast.show { opacity: 1; transform: translateX(0); }

/* Empty State */
.empty-state { text-align: center; padding: 80px 20px; }
.empty-state-icon { width: 80px; height: 80px; border-radius: 40px; background: var(--off); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; animation: bounce 2s infinite; }
@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}
.empty-state-icon svg { width: 40px; height: 40px; stroke: var(--ink4); }
.empty-state h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }

/* Loading Shimmer */
@keyframes shimmer {
  0% { background-position: -1000px 0; }
  100% { background-position: 1000px 0; }
}
.loading-row {
  background: linear-gradient(90deg, var(--off) 25%, var(--border) 50%, var(--off) 75%);
  background-size: 1000px 100%;
  animation: shimmer 2s infinite;
  height: 60px;
  border-radius: 8px;
  margin-bottom: 8px;
}

/* Responsive */
@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .content { padding: 16px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .fee-grid { grid-template-columns: 1fr; }
  .data-table { font-size: 12px; }
  .data-table th, .data-table td { padding: 10px 12px; }
}
@media (max-width: 640px) {
  .stats-grid { grid-template-columns: 1fr; }
  .filter-row { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 8px; }
  .modal-container { max-width: 95%; }
  .modal-body { padding: 16px; }
  .toast { left: 20px; right: 20px; bottom: 20px; justify-content: center; }
}
</style>
</head>
<body>
<div class="app-shell">

<!-- SIDEBAR - Desktop -->
<aside class="sidebar" id="sidebar">
  <a href="#" class="sidebar-brand">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity=".9"/><path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity=".35"/></svg>
    </div>
    <div class="sidebar-brand-text">
      <div class="sidebar-app-name">LAKBAY</div>
      <div class="sidebar-app-sub">Manager Portal</div>
    </div>
  </a>
  <div class="mountain-badge">
    <div class="mountain-badge-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg></div>
    <div class="mountain-badge-text" id="mtnBadge"></div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span class="nav-text">Dashboard</span>
    </a>
    <a href="#" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="nav-text">Bookings</span>
    </a>
    <a href="#" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      <span class="nav-text">Payments</span>
    </a>
    <div class="nav-divider"></div>
    <div class="nav-label">Reports</div>
    <a href="#" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span class="nav-text">Analytics</span>
    </a>
    <a href="#" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
      <span class="nav-text">Advisories</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-footer-avatar" id="userAvatar"><?= substr($MANAGER->name, 0, 2) ?></div>
    <div class="sidebar-footer-text">
      <div class="sidebar-footer-name" id="sfName"></div>
      <div class="sidebar-footer-role">Mountain Manager</div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Bookings</div>
        <div class="topbar-page-sub" id="bookingCount">Loading...</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar" id="topbarAvatar" onclick="showGreeting()"></div>
    </div>
  </div>

  <div class="content">
    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid"></div>

    <!-- Search and Filters Panel -->
    <div class="panel">
      <div class="panel-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <div style="flex:1;min-width:200px;">
            <div class="inp-label" style="margin-bottom:5px;">Search</div>
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
              <input type="text" id="searchInp" placeholder="Booking ID, hiker, guide..." oninput="debouncedApplyFilters()">
            </div>
          </div>
          <div>
            <div class="inp-label">Status</div>
            <select class="select" id="filterStatus" onchange="applyFilters()">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div>
            <div class="inp-label">Type</div>
            <select class="select" id="filterType" onchange="applyFilters()">
              <option value="">All Types</option>
              <option value="day">Day Hike</option>
              <option value="late">Late Hike</option>
              <option value="overnight">Overnight</option>
            </select>
          </div>
          <button class="btn btn-outline btn-sm" onclick="clearFilters()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Clear
          </button>
        </div>
        <div class="filter-row" id="statusChips">
          <div class="chip active" data-status="">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
            All
          </div>
          <div class="chip" data-status="pending">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Pending
          </div>
          <div class="chip" data-status="confirmed">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
            Confirmed
          </div>
          <div class="chip" data-status="completed">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Completed
          </div>
          <div class="chip" data-status="cancelled">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Cancelled
          </div>
        </div>
      </div>
    </div>

    <!-- Bookings Table -->
    <div class="panel">
      <div style="overflow-x:auto;">
        <table class="data-table" id="bookingsTable">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Date & Time</th>
              <th>Type</th>
              <th>Hikers</th>
              <th>Guide</th>
              <th>Total Fee</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tableBody"></tbody>
        </table>
        <div id="tableEmpty" class="empty-state" style="display:none;">
          <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
          <h3>No bookings found</h3>
          <p>Try adjusting your search or filters</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav" id="mobileBottomNav">
  <a href="dashboard.php" class="mobile-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Home</span>
  </a>
  <button class="mobile-nav-item active" onclick="scrollToTop()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <span>Bookings</span>
  </button>
  <button class="mobile-nav-item" onclick="toggleMobileSidebar()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    <span>Menu</span>
  </button>
</div>

<!-- MODAL POPUP -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal-container" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div>
        <h2 id="modalTitle">Booking Details</h2>
        <div class="modal-header-sub" id="modalSub"></div>
      </div>
      <button class="modal-close" onclick="closeModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" id="saveChangesBtn" onclick="saveChanges()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
        Save Changes
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let allBookings = [];
let filteredBookings = [];
let currentBooking = null;
let pendingChanges = {};

function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mainArea = document.getElementById('mainArea');
  sidebar.classList.toggle('collapsed');
  mainArea.classList.toggle('expanded');
}

function toggleMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  sidebar.classList.toggle('mobile-open');
}

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

let debounceTimer;
function debouncedApplyFilters() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => applyFilters(), 300);
}

function showGreeting() {
  const hour = new Date().getHours();
  let greeting = '';
  if (hour < 12) greeting = 'Good Morning!';
  else if (hour < 18) greeting = 'Good Afternoon!';
  else greeting = 'Good Evening!';
  showToast(`${greeting} ${MANAGER.name.split(' ')[0]}!`);
}

function init() {
  const mountain = <?= json_encode(getManagerMountain()) ?>;
  document.getElementById('mtnBadge').innerHTML = `<div class="mountain-badge-name">${mountain.name}</div><div class="mountain-badge-role">${mountain.location}</div>`;
  document.getElementById('sfName').textContent = MANAGER.name;
  document.getElementById('topbarAvatar').textContent = MANAGER.initials;
  document.getElementById('userAvatar').textContent = MANAGER.initials;

  allBookings = getMyBookings();
  applyFilters();
  updateStats();
  
  setTimeout(() => showToast(`Welcome back, ${MANAGER.name.split(' ')[0]}!`), 500);
}

function updateStats() {
  const stats = {
    total: allBookings.length,
    pending: allBookings.filter(b => b.status === 'pending').length,
    confirmed: allBookings.filter(b => b.status === 'confirmed').length,
    completed: allBookings.filter(b => b.status === 'completed').length
  };
  
  document.getElementById('statsGrid').innerHTML = `
    <div class="stat-card" onclick="filterByStatus('')">
      <div class="stat-value">${stats.total}</div>
      <div class="stat-label">Total Bookings</div>
    </div>
    <div class="stat-card pending" onclick="filterByStatus('pending')">
      <div class="stat-value">${stats.pending}</div>
      <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card confirmed" onclick="filterByStatus('confirmed')">
      <div class="stat-value">${stats.confirmed}</div>
      <div class="stat-label">Confirmed</div>
    </div>
    <div class="stat-card completed" onclick="filterByStatus('completed')">
      <div class="stat-value">${stats.completed}</div>
      <div class="stat-label">Completed</div>
    </div>
  `;
}

function filterByStatus(status) {
  document.getElementById('filterStatus').value = status;
  document.querySelectorAll('#statusChips .chip').forEach(chip => {
    chip.classList.toggle('active', chip.dataset.status === status);
  });
  applyFilters();
  showToast(`Showing ${status || 'all'} bookings`);
}

function applyFilters() {
  const searchTerm = document.getElementById('searchInp').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value;
  const typeFilter = document.getElementById('filterType').value;

  filteredBookings = allBookings.filter(booking => {
    if (statusFilter && booking.status !== statusFilter) return false;
    if (typeFilter && booking.type !== typeFilter) return false;
    if (searchTerm) {
      const searchable = `${booking.id} ${booking.mountain} ${booking.guideName} ${booking.hikers.join(' ')}`.toLowerCase();
      if (!searchable.includes(searchTerm)) return false;
    }
    return true;
  });

  renderTable();
  document.getElementById('bookingCount').textContent = `${filteredBookings.length} booking${filteredBookings.length !== 1 ? 's' : ''} found`;
}

function renderTable() {
  const tbody = document.getElementById('tableBody');
  const empty = document.getElementById('tableEmpty');
  
  if (filteredBookings.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  
  empty.style.display = 'none';
  
  const typeLabels = { day: 'Day', late: 'Late', overnight: 'Overnight' };
  
  tbody.innerHTML = filteredBookings.map(booking => `
    <tr onclick="openModal('${booking.id}')">
      <td><span style="font-family:'DM Mono',monospace;font-size:12px;font-weight:700;background:var(--off);padding:4px 10px;border-radius:6px;">${booking.id}</span></td>
      <td>
        <div style="font-size:13px;font-weight:600;">${fmtDate(booking.date)}</div>
        <div style="font-size:11px;color:var(--ink3);">${fmtTime(booking.time)}</div>
      </td>
      <td><span class="badge badge-${booking.type}">${typeLabels[booking.type] || booking.type}</span></td>
      <td>
        <div style="display:flex;align-items:center;gap:6px;">
          <div style="display:flex;">
            ${booking.hikers.slice(0,3).map((h,i)=>`
              <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg, var(--ink), var(--ink2));color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;border:2px solid var(--white);margin-left:${i>0?'-6px':'0'};">${initials(h)}</div>
            `).join('')}
            ${booking.pax > 3 ? `<div style="width:26px;height:26px;border-radius:50%;background:var(--off);color:var(--ink3);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;border:2px solid var(--white);margin-left:-6px;">+${booking.pax-3}</div>` : ''}
          </div>
          <span style="font-size:12px;color:var(--ink3);">${booking.pax} hiker${booking.pax > 1 ? 's' : ''}</span>
        </div>
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg, var(--ink), var(--ink2));color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">${booking.guideInitials}</div>
          <span style="font-size:12px;font-weight:600;">${escapeHtml(booking.guideName)}</span>
        </div>
      </td>
      <td>
        <div style="font-family:'DM Mono',monospace;font-size:14px;font-weight:700;">${fmtMoney(booking.totalFee)}</div>
      </td>
      <td><span class="badge badge-${booking.status}">${booking.status}</span></td>
      <td>
        <button class="btn btn-primary btn-xs" onclick="event.stopPropagation();openModal('${booking.id}')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:12px;height:12px;"><polyline points="9 18 15 12 9 6"/></svg>
          Manage
        </button>
      </td>
    </tr>
  `).join('');
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    return m;
  });
}

function chipClick(el, status) {
  document.querySelectorAll('#statusChips .chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('filterStatus').value = status;
  applyFilters();
}

function clearFilters() {
  document.getElementById('searchInp').value = '';
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterType').value = '';
  document.querySelectorAll('#statusChips .chip').forEach((c, i) => c.classList.toggle('active', i === 0));
  applyFilters();
  showToast('All filters cleared!');
}

function showToast(message) {
  const toast = document.getElementById('toast');
  toast.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> ${message}`;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}

function createConfetti() {
  for (let i = 0; i < 50; i++) {
    const confetti = document.createElement('div');
    confetti.className = 'confetti';
    confetti.style.left = Math.random() * 100 + '%';
    confetti.style.backgroundColor = `hsl(${Math.random() * 360}, 70%, 50%)`;
    confetti.style.width = Math.random() * 8 + 4 + 'px';
    confetti.style.height = Math.random() * 8 + 4 + 'px';
    confetti.style.position = 'fixed';
    confetti.style.top = '-10px';
    confetti.style.animationDuration = Math.random() * 2 + 1 + 's';
    document.body.appendChild(confetti);
    setTimeout(() => confetti.remove(), 3000);
  }
}

function openModal(bookingId) {
  const booking = allBookings.find(b => b.id === bookingId);
  if (!booking) return;
  
  currentBooking = JSON.parse(JSON.stringify(booking));
  pendingChanges = {};
  
  const mtn = <?= json_encode(getManagerMountain()) ?>;
  const typeMap = { day: 'Day Hike (12am-3pm)', late: 'Late Hike (4pm-12am)', overnight: 'Overnight (with camping)' };
  const guide = ALL_GUIDES.find(g => g.id === booking.guideId);
  
  document.getElementById('modalTitle').innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg> ${booking.mountain}`;
  document.getElementById('modalSub').textContent = `${booking.id} · ${fmtDate(booking.date)} at ${fmtTime(booking.time)}`;
  
  document.getElementById('modalBody').innerHTML = `
    <div style="margin-bottom:20px;">
      <div class="inp-label" style="margin-bottom:8px;">Current Status</div>
      <div class="status-display ${booking.status}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14">${booking.status === 'pending' ? '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' : booking.status === 'confirmed' ? '<polyline points="20 6 9 17 4 12"/>' : booking.status === 'completed' ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' : '<circle cx="12" cy="12" r="10"/><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'}</svg>
        <span>${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span>
      </div>
    </div>
    
    <div class="info-section">
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg></div>
        <div><div class="info-label">Mountain</div><div class="info-value">${escapeHtml(booking.mountain)} • ${mtn.location}</div></div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="info-label">Date & Time</div><div class="info-value">${fmtDate(booking.date)} at ${fmtTime(booking.time)}</div></div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div><div class="info-label">Hike Type</div><div class="info-value">${typeMap[booking.type]}</div></div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div>
          <div class="info-label">Tour Guide</div>
          <div class="info-value">${escapeHtml(booking.guideName)}</div>
          ${guide ? `<div style="font-size:11px;color:var(--ink3);margin-top:4px;">⭐ ${guide.rating} ★ (${guide.experience} experience) • 📞 ${guide.phone}</div>` : ''}
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div><div class="info-label">Total Fee</div><div class="info-value">${fmtMoney(booking.totalFee)}</div></div>
      </div>
      ${booking.specialRequests ? `
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
        <div><div class="info-label">Special Requests</div><div class="info-value">${escapeHtml(booking.specialRequests)}</div></div>
      </div>` : ''}
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
        <div><div class="info-label">Contact Number</div><div class="info-value">${booking.contactNumber}</div></div>
      </div>
    </div>
    
    <div class="hiker-section">
      <div class="hiker-section-title">
        <span>Hikers & Payment Status</span>
        <span class="payment-progress" id="modalPayProgress"></span>
      </div>
      <div id="hikerPaymentList">
        ${renderHikerPayments(booking, mtn)}
      </div>
    </div>
  `;
  
  updateModalPayProgress();
  document.getElementById('modalOverlay').classList.add('open');
}

function renderHikerPayments(booking, mtn) {
  return booking.hikers.map((hiker, idx) => {
    const regStatus = getPayStatus(booking.id, idx, 'reg');
    const envStatus = mtn?.fees?.env > 0 ? getPayStatus(booking.id, idx, 'env') : null;
    const hasEnv = mtn?.fees?.env > 0;
    
    return `
      <div class="hiker-card" data-hiker-idx="${idx}">
        <div class="hiker-header">
          <div class="hiker-avatar ${idx === 0 ? 'booker' : ''}">${initials(hiker)}</div>
          <div>
            <div class="hiker-name">${escapeHtml(hiker)}</div>
            <div class="hiker-role">${idx === 0 ? 'Booking Organizer' : 'Group Member'}</div>
          </div>
        </div>
        <div class="fee-grid">
          <div class="fee-card">
            <div class="fee-header">
              <span class="fee-label">Registration Fee</span>
              <span class="fee-amount">${fmtMoney(mtn?.fees?.reg || 0)}</span>
            </div>
            <div class="fee-buttons">
              <button class="fee-btn paid ${regStatus === 'paid' ? 'active' : ''}" onclick="updateTempPayment(${idx}, 'reg', 'paid', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg> Paid</button>
              <button class="fee-btn unpaid ${regStatus === 'unpaid' ? 'active' : ''}" onclick="updateTempPayment(${idx}, 'reg', 'unpaid', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg> Unpaid</button>
              <button class="fee-btn waived ${regStatus === 'waived' ? 'active' : ''}" onclick="updateTempPayment(${idx}, 'reg', 'waived', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg> Waived</button>
            </div>
          </div>
          ${hasEnv ? `
          <div class="fee-card">
            <div class="fee-header">
              <span class="fee-label">Environmental Fee</span>
              <span class="fee-amount">${fmtMoney(mtn.fees.env)}</span>
            </div>
            <div class="fee-buttons">
              <button class="fee-btn paid ${envStatus === 'paid' ? 'active' : ''}" onclick="updateTempPayment(${idx}, 'env', 'paid', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg> Paid</button>
              <button class="fee-btn unpaid ${envStatus === 'unpaid' ? 'active' : ''}" onclick="updateTempPayment(${idx}, 'env', 'unpaid', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg> Unpaid</button>
              <button class="fee-btn waived ${envStatus === 'waived' ? 'active' : ''}" onclick="updateTempPayment(${idx}, 'env', 'waived', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg> Waived</button>
            </div>
          </div>
          ` : ''}
        </div>
      </div>
    `;
  }).join('');
}

function updateTempPayment(hikerIdx, feeType, status, event) {
  event.stopPropagation();
  
  const key = `${hikerIdx}_${feeType}`;
  if (!pendingChanges.payments) pendingChanges.payments = {};
  pendingChanges.payments[key] = status;
  
  const card = document.querySelector(`.hiker-card[data-hiker-idx="${hikerIdx}"]`);
  const feeSection = feeType === 'reg' ? card.querySelector('.fee-card:first-child') : card.querySelector('.fee-card:last-child');
  feeSection.querySelectorAll('.fee-btn').forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');
  
  event.target.style.transform = 'scale(0.95)';
  setTimeout(() => { event.target.style.transform = ''; }, 150);
  
  updateModalPayProgress();
}

function updateModalPayProgress() {
  const booking = currentBooking;
  const mtn = <?= json_encode(getManagerMountain()) ?>;
  let total = 0, paid = 0;
  
  booking.hikers.forEach((_, idx) => {
    total++;
    let regStatus = getPayStatus(booking.id, idx, 'reg');
    if (pendingChanges.payments && pendingChanges.payments[`${idx}_reg`]) {
      regStatus = pendingChanges.payments[`${idx}_reg`];
    }
    if (regStatus === 'paid') paid++;
    
    if (mtn?.fees?.env > 0) {
      total++;
      let envStatus = getPayStatus(booking.id, idx, 'env');
      if (pendingChanges.payments && pendingChanges.payments[`${idx}_env`]) {
        envStatus = pendingChanges.payments[`${idx}_env`];
      }
      if (envStatus === 'paid') paid++;
    }
  });
  
  const progressEl = document.getElementById('modalPayProgress');
  if (progressEl) {
    progressEl.textContent = `${paid}/${total} Fees Collected`;
    if (paid === total && total > 0) {
      progressEl.style.background = 'var(--green)';
      progressEl.style.color = 'white';
    } else {
      progressEl.style.background = 'var(--green-bg)';
      progressEl.style.color = 'var(--green)';
    }
  }
}

function saveChanges() {
  let changesMade = false;
  
  if (pendingChanges.payments) {
    for (const [key, status] of Object.entries(pendingChanges.payments)) {
      const [hikerIdx, feeType] = key.split('_');
      setPayStatus(currentBooking.id, parseInt(hikerIdx), feeType, status);
      changesMade = true;
    }
  }
  
  if (changesMade) {
    createConfetti();
    showToast('Payment status updated successfully!');
    addLog(`${MANAGER.name} updated payment status for ${currentBooking.id}`, 'green');
  } else {
    showToast('No changes to save');
  }
  
  applyFilters();
  updateStats();
  closeModal();
}

function closeModal(event) {
  if (event && event.target !== event.currentTarget && event.target !== document.getElementById('modalOverlay')) return;
  document.getElementById('modalOverlay').classList.remove('open');
  currentBooking = null;
  pendingChanges = {};
}

setInterval(() => {
  const el = document.getElementById('topbarDate');
  if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}, 1000);

document.querySelectorAll('#statusChips .chip').forEach(chip => {
  chip.addEventListener('click', () => chipClick(chip, chip.dataset.status));
});

// Close mobile sidebar when clicking outside
document.addEventListener('click', function(event) {
  const sidebar = document.getElementById('sidebar');
  const isClickInsideSidebar = sidebar.contains(event.target);
  const isMobileMenuButton = event.target.closest('.mobile-nav-item') && event.target.closest('.mobile-nav-item').querySelector('span')?.textContent === 'Menu';
  
  if (!isClickInsideSidebar && !isMobileMenuButton && sidebar.classList.contains('mobile-open')) {
    sidebar.classList.remove('mobile-open');
  }
});

const MANAGER = <?= json_encode($MANAGER) ?>;
const ALL_GUIDES = <?= json_encode($ALL_GUIDES) ?>;

function getMyBookings() { return <?= json_encode(getMyBookings()) ?>; }
function fmtDate(date) { return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
function fmtTime(time) { 
  const [hours, minutes] = time.split(':');
  const date = new Date();
  date.setHours(parseInt(hours), parseInt(minutes));
  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}
function fmtMoney(amt) { return '₱' + amt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function initials(name) { return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase(); }
function getPayStatus(id, idx, type) { 
  const key = id + '_' + idx + '_' + type;
  return typeof sessionStorage !== 'undefined' && sessionStorage.getItem(key) || 'unpaid';
}
function setPayStatus(id, idx, type, status) { 
  const key = id + '_' + idx + '_' + type;
  if (typeof sessionStorage !== 'undefined') {
    sessionStorage.setItem(key, status);
  }
}
function addLog(msg, type) { console.log('Log:', msg, type); }

init();
</script>
</body>
</html>