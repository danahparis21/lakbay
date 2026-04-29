<?php
// bookings_manager.php - Database Integrated Version
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
$mountain_ids_placeholder = !empty($mountain_ids) ? implode(',', array_fill(0, count($mountain_ids), '?')) : 'NULL';

// Get bookings for assigned mountains only
$bookings = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            m.name as mountain_name,
            m.registration_fee,
            m.environmental_fee,
            u.name as guide_name,
            u.id as guide_user_id,
            hiker.name as hiker_name,
            hiker.email as hiker_email
        FROM bookings b
        INNER JOIN mountains m ON b.mountain_id = m.id
        LEFT JOIN users u ON b.guide_id = u.id
        LEFT JOIN users hiker ON b.user_id = hiker.id
        WHERE b.mountain_id IN ($mountain_ids_placeholder)
        ORDER BY b.hike_date DESC, b.created_at DESC
    ");
    $stmt->execute($mountain_ids);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all guides for the manager's mountains
$guides = [];
if (!empty($mountain_ids)) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.* 
        FROM users u
        INNER JOIN guide_mountains gm ON u.id = gm.guide_id
        WHERE gm.mountain_id IN ($mountain_ids_placeholder) AND u.role = 'guide'
        ORDER BY u.name
    ");
    $stmt->execute($mountain_ids);
    $guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper functions
function fmtDate($date) { 
    return $date ? date('M d, Y', strtotime($date)) : 'N/A'; 
}

function fmtTime($time) { 
    return $time ? date('g:i A', strtotime($time)) : 'N/A'; 
}

function fmtMoney($amt) { 
    return '₱' . number_format((float)$amt, 2); 
}

$manager_name = $manager['name'];
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));

// Calculate stats
$total_bookings = count($bookings);
$pending_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$active_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'active'));
$finished_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'finished' || $b['status'] === 'completed'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY Manager — Bookings</title>
<link rel="stylesheet" href="manager.css">
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

/* Log out button - red style */
.nav-item.logout-red {
  margin-top: 12px;
  border-top: 1px solid var(--border);
  border-radius: 0;
  color: #b91c1c;
}
.nav-item.logout-red:hover {
  background: rgba(185, 28, 28, 0.08);
  color: #b91c1c;
}
.nav-item.logout-red svg {
  stroke: #b91c1c;
}
.nav-item.logout-red:hover svg {
  stroke: #b91c1c;
}

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

.badge-warning {
    background: #fff3e0;
    color: #e65100;
}
.badge-active {
    background: #e8f5e9;
    color: #2e7d32;
}
.badge-finished {
    background: #e3f2fd;
    color: #1565c0;
}
.status-select {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1.5px solid var(--border2);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.status-select:hover {
    border-color: var(--gold);
}
.status-select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
}
.guide-select {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1.5px solid var(--border2);
    font-size: 12px;
    cursor: pointer;
}
.action-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px;
    border-radius: 6px;
    transition: all 0.2s;
    color: var(--ink3);
}
.action-btn:hover {
    background: var(--off);
    color: var(--gold);
}
.booking-number {
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    background: var(--off);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}
/* Logout button style */
.nav-item.logout {
  margin-top: 12px;
  border-radius: 0;
  color: #b91c1c;
}
.nav-item.logout:hover {
  background: rgba(185, 28, 28, 0.08);
  color: #b91c1c;
}
.nav-item.logout svg {
  stroke: #b91c1c;
}
.nav-item.logout:hover svg {
  stroke: #b91c1c;
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

<!-- SIDEBAR -->
<?php $activePage = 'bookings'; ?>
<?php include 'shared_sidebar.php'; ?>
<!-- MAIN -->
<div class="main-area" id="mainArea">
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Bookings</div>
        <div class="topbar-page-sub" id="bookingCount"><?= $total_bookings ?> bookings</div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar" id="topbarAvatar"><?= $manager_initials ?></div>
    </div>
  </div>

  <div class="content">
    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card" onclick="filterByStatus('')">
        <div class="stat-value"><?= $total_bookings ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
      <div class="stat-card pending" onclick="filterByStatus('pending')">
        <div class="stat-value"><?= $pending_bookings ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card confirmed" onclick="filterByStatus('active')">
        <div class="stat-value"><?= $active_bookings ?></div>
        <div class="stat-label">Active</div>
      </div>
      <div class="stat-card completed" onclick="filterByStatus('finished')">
        <div class="stat-value"><?= $finished_bookings ?></div>
        <div class="stat-label">Finished</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="panel">
      <div class="panel-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <div style="flex:1;min-width:200px;">
            <div class="inp-label" style="margin-bottom:5px;">Search</div>
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
              <input type="text" id="searchInp" placeholder="Booking #, hiker, guide..." onkeyup="applyFilters()">
            </div>
          </div>
          <div>
            <div class="inp-label">Status</div>
            <select class="select" id="filterStatus" onchange="applyFilters()">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="waiting_payment">Waiting Payment</option>
              <option value="active">Active</option>
              <option value="finished">Finished</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <button class="btn btn-outline btn-sm" onclick="clearFilters()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Bookings Table -->
    <div class="panel">
      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Booking #</th>
              <th>Hike Date</th>
              <th>Mountain</th>
              <th>Hiker</th>
              <th>Guide</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="bookingsTableBody">
            <?php foreach ($bookings as $booking): ?>
            <tr data-booking-id="<?= $booking['id'] ?>">
              <td><span class="booking-number"><?= htmlspecialchars($booking['booking_number']) ?></span></td>
              <td>
                <?= fmtDate($booking['hike_date']) ?><br>
                <small><?= fmtTime($booking['start_time']) ?></small>
               </td>
               <td><?= htmlspecialchars($booking['mountain_name']) ?></td>
               <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg, var(--ink), var(--ink2));color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;"><?= substr($booking['hiker_name'] ?? '?', 0, 2) ?></div>
                  <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($booking['hiker_name'] ?? 'Unknown') ?></div>
                    <div style="font-size:10px;color:var(--ink3);"><?= $booking['number_of_hikers'] ?> hiker(s)</div>
                  </div>
                </div>
               </td>
               <td>
                <select class="guide-select" data-booking-id="<?= $booking['id'] ?>" onchange="updateGuide(this)">
                  <option value="">Assign Guide</option>
                  <?php foreach ($guides as $guide): ?>
                  <option value="<?= $guide['id'] ?>" <?= $booking['guide_id'] == $guide['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($guide['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
               </td>
               <td class="total-fee"><?= fmtMoney($booking['total_amount'] ?? 0) ?></td>
               <td>
                <span class="payment-status <?= $booking['payment_status'] === 'paid' ? 'payment-paid' : ($booking['payment_status'] === 'partial' ? 'payment-partial' : 'payment-unpaid') ?>">
                  <?= ucfirst($booking['payment_status'] ?? 'pending') ?>
                </span>
               </td>
               <td>
                <select class="status-select" data-booking-id="<?= $booking['id'] ?>" onchange="updateStatus(this)">
                  <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="waiting_payment" <?php echo $booking['status'] === 'waiting_payment' ? 'selected' : ''; ?>>Waiting Payment</option>
                  <option value="active" <?php echo $booking['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                  <option value="finished" <?php echo $booking['status'] === 'finished' ? 'selected' : ''; ?>>Finished</option>
                  <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                  <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
               </td>
               <td>
                <button class="action-btn" onclick="viewBooking(<?= $booking['id'] ?>)" title="View Details">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M22 12c0 5.52-4.48 10-10 10S2 17.52 2 12 6.48 2 12 2s10 4.48 10 10z"/></svg>
                </button>
               </td>
             </tr>
            <?php endforeach; ?>
            <?php if (empty($bookings)): ?>
            <tr>
              <td colspan="9" style="text-align:center;padding:60px;">
                <div class="empty-state-icon" style="margin:0 auto 20px;">
                  <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <h3>No bookings found</h3>
                <p>No bookings have been made for your mountains yet.</p>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Popup -->
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
      <button class="btn btn-outline" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mainArea = document.getElementById('mainArea');
  sidebar.classList.toggle('collapsed');
  mainArea.classList.toggle('expanded');
}

function updateClock() {
  const d = new Date();
  const dateEl = document.getElementById('topbarDate');
  if (dateEl) {
    dateEl.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() + ' ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
  }
}

function filterByStatus(status) {
  document.getElementById('filterStatus').value = status;
  applyFilters();
}

function applyFilters() {
  const searchTerm = document.getElementById('searchInp').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value;
  const rows = document.querySelectorAll('#bookingsTableBody tr');
  let visibleCount = 0;
  
  rows.forEach(row => {
    if (row.querySelector('td[colspan]')) return;
    
    const bookingNumber = row.querySelector('.booking-number')?.textContent.toLowerCase() || '';
    const mountain = row.cells[2]?.textContent.toLowerCase() || '';
    const statusSelect = row.querySelector('.status-select');
    const status = statusSelect ? statusSelect.value : '';
    const hikerName = row.cells[3]?.querySelector('div div:first-child')?.textContent.toLowerCase() || '';
    
    let matches = true;
    if (searchTerm && !bookingNumber.includes(searchTerm) && !mountain.includes(searchTerm) && !hikerName.includes(searchTerm)) {
      matches = false;
    }
    if (statusFilter && status !== statusFilter) {
      matches = false;
    }
    
    row.style.display = matches ? '' : 'none';
    if (matches) visibleCount++;
  });
  
  document.getElementById('bookingCount').textContent = `${visibleCount} booking${visibleCount !== 1 ? 's' : ''}`;
}

function clearFilters() {
  document.getElementById('searchInp').value = '';
  document.getElementById('filterStatus').value = '';
  applyFilters();
  showToast('Filters cleared!');
}

function updateStatus(select) {
  const bookingId = select.dataset.bookingId;
  const newStatus = select.value;
  
  if (!confirm(`Change booking status to ${newStatus.toUpperCase()}?`)) {
    select.value = select.options[select.selectedIndex].getAttribute('data-original') || select.value;
    return;
  }
  
  fetch('update_booking_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: bookingId, status: newStatus })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(`Booking status updated to ${newStatus}`);
      location.reload();
    } else {
      showToast(data.error || 'Update failed', 'error');
    }
  })
  .catch(err => {
    showToast('Error updating status', 'error');
  });
}

function updateGuide(select) {
  const bookingId = select.dataset.bookingId;
  const guideId = select.value;
  
  fetch('update_booking_guide.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: bookingId, guide_id: guideId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('Guide assigned successfully!');
      location.reload();
    } else {
      showToast(data.error || 'Update failed', 'error');
    }
  })
  .catch(err => {
    showToast('Error assigning guide', 'error');
  });
}

function viewBooking(bookingId) {
  fetch(`get_booking_details.php?id=${bookingId}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const booking = data.booking;
        document.getElementById('modalTitle').innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg> Booking ${booking.booking_number}`;
        document.getElementById('modalSub').textContent = `${booking.mountain_name} · ${new Date(booking.hike_date).toLocaleDateString()}`;
        
        document.getElementById('modalBody').innerHTML = `
          <div class="info-section">
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg></div>
              <div><div class="info-label">Mountain</div><div class="info-value">${escapeHtml(booking.mountain_name)}</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
              <div><div class="info-label">Hike Date & Time</div><div class="info-value">${new Date(booking.hike_date).toLocaleDateString()} at ${booking.start_time || 'Not set'}</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
              <div><div class="info-label">Hiker</div><div class="info-value">${escapeHtml(booking.hiker_name)} (${booking.hiker_email})</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
              <div><div class="info-label">Group Size</div><div class="info-value">${booking.number_of_hikers} hiker${booking.number_of_hikers > 1 ? 's' : ''}</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
              <div><div class="info-label">Total Amount</div><div class="info-value">₱${parseFloat(booking.total_amount).toLocaleString()}</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
              <div><div class="info-label">Downpayment</div><div class="info-value">₱${parseFloat(booking.downpayment_amount || 0).toLocaleString()} (${booking.downpayment_status})</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
              <div><div class="info-label">Guide</div><div class="info-value">${escapeHtml(booking.guide_name || 'Not assigned')}</div></div>
            </div>
            ${booking.special_requests ? `
            <div class="info-row">
              <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
              <div><div class="info-label">Special Requests</div><div class="info-value">${escapeHtml(booking.special_requests)}</div></div>
            </div>` : ''}
          </div>
        `;
        
        document.getElementById('modalOverlay').classList.add('open');
      }
    })
    .catch(err => console.error('Error:', err));
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

function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  toast.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> ${message}`;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}

function closeModal(event) {
  if (event && event.target !== event.currentTarget && event.target !== document.getElementById('modalOverlay')) return;
  document.getElementById('modalOverlay').classList.remove('open');
}

// Save original values for status selects
document.querySelectorAll('.status-select').forEach(select => {
  select.setAttribute('data-original', select.value);
});

// Initialize
setInterval(updateClock, 1000);
updateClock();
</script>
</body>
</html>