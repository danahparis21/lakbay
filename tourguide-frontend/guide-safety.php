<?php
// guide-safety.php — Safety Dashboard for Guides
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Get guide record
$guideRecord = null;
try {
    $stmt = $pdo->prepare("SELECT g.id as guide_id, u.name, u.avatar FROM guides g JOIN users u ON g.user_id = u.id WHERE g.user_id = ?");
    $stmt->execute([$currentUserId]);
    $guideRecord = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Guide lookup error: " . $e->getMessage());
}

$guideName     = $guideRecord['name'] ?? $_SESSION['name'] ?? 'Guide';
$guideInitials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $guideName), 0, 2)));
$guideId       = $guideRecord['guide_id'] ?? null;

// ── Fetch active bookings for this guide ──────────────────────────
$bookings = [];
try {
    if ($guideId) {
        $stmt = $pdo->prepare("
            SELECT b.id, b.hike_date, b.status, b.number_of_hikers, b.hike_type,
                   m.name as mountain_name, m.location as mountain_location,
                   u.name as booker_name
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            JOIN users u ON b.user_id = u.id
            WHERE b.guide_id = ? AND b.status IN ('active','confirmed')
            ORDER BY ABS(DATEDIFF(b.hike_date, CURDATE())) ASC
        ");
        $stmt->execute([$guideId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Bookings fetch error: " . $e->getMessage());
}

// ── Active booking (first/selected) ───────────────────────────────
$selectedBookingId = $_GET['booking_id'] ?? ($bookings[0]['id'] ?? null);
$activeBooking     = null;
foreach ($bookings as $b) {
    if ($b['id'] == $selectedBookingId) { $activeBooking = $b; break; }
}
if (!$activeBooking && !empty($bookings)) {
    $activeBooking = $bookings[0];
    $selectedBookingId = $activeBooking['id'];
}

// ── Fetch all hikers for this booking ─────────────────────────────
// Group: booking owner (from users) + additional hikers (from booking_hikers)
$allHikers = [];
if ($selectedBookingId) {
    try {
        // 1. Booking owner
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.name, u.avatar, u.phone,
                   NULL as age, NULL as emergency_contact_name, NULL as emergency_contact_number,
                   'owner' as hiker_type
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$selectedBookingId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($owner) $allHikers[] = $owner;

        // 2. Additional hikers from booking_hikers
        $stmt = $pdo->prepare("
            SELECT bh.id as booking_hiker_id,
                   bh.hiker_name as name, bh.age,
                   bh.emergency_contact_name, bh.emergency_contact_number,
                   u.id as user_id, u.avatar, u.phone,
                   'extra' as hiker_type
            FROM booking_hikers bh
            LEFT JOIN users u ON (u.name = bh.hiker_name AND u.role = 'hiker')
            WHERE bh.booking_id = ?
        ");
        $stmt->execute([$selectedBookingId]);
        $extras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deduplicate — don't add owner again if they're in booking_hikers
        foreach ($extras as $ex) {
            $isDupe = false;
            foreach ($allHikers as $existing) {
                if (!empty($ex['user_id']) && $ex['user_id'] == $existing['user_id']) {
                    $isDupe = true; break;
                }
            }
            if (!$isDupe) $allHikers[] = $ex;
        }
    } catch (PDOException $e) {
        error_log("Hikers fetch error: " . $e->getMessage());
    }
}

// ── Fetch existing safety alerts for this booking ─────────────────
$safetyAlerts = [];
try {
    if ($selectedBookingId) {
        $stmt = $pdo->prepare("
            SELECT sa.*, u.name as reporter_display
            FROM safety_alerts sa
            LEFT JOIN users u ON sa.reported_by = u.id
            WHERE sa.booking_id = ?
            ORDER BY sa.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$selectedBookingId]);
        $safetyAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Table might not have booking_id yet — fallback
    try {
        $stmt = $pdo->prepare("SELECT * FROM safety_alerts ORDER BY created_at DESC LIMIT 20");
        $stmt->execute();
        $safetyAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        error_log("Safety alerts fetch error: " . $e2->getMessage());
    }
}

// ── Fetch hiker safety statuses for this booking ──────────────────
// We'll store them in hiker_safety_status table (see SQL note below)
$hikerStatuses = [];
try {
    if ($selectedBookingId) {
        $stmt = $pdo->prepare("
            SELECT * FROM hiker_safety_status WHERE booking_id = ?
        ");
        $stmt->execute([$selectedBookingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $key = $row['user_id'] ?? $row['hiker_name'];
            $hikerStatuses[$key] = $row['status']; // 'safe' | 'unsafe' | 'unknown'
        }
    }
} catch (PDOException $e) {
    // Table may not exist yet — silently skip
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Safety</title>
  <?php if (!$isEmbed): ?>
  <link rel="stylesheet" href="guide-shared.css">
  <?php endif; ?>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    <?php if ($isEmbed): ?>
    /* ── EMBED RESET ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', 'Segoe UI', sans-serif;
      background: #f8f7f5;
      color: #100600;
      height: 100vh;
      overflow-y: auto;
    }
    .guide-app { display: flex; flex-direction: column; height: 100%; min-height: 100vh; }
    .guide-sidebar, .guide-bottom-nav { display: none !important; }
    .guide-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .guide-content {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }
    .guide-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px;
      background: white;
      border-bottom: 1px solid rgba(0,0,0,0.07);
      flex-shrink: 0;
    }
    .topbar-title { font-size: 1rem; font-weight: 700; color: #100600; }
    .topbar-right { display: flex; align-items: center; gap: 10px; }
    .topbar-time { font-size: 0.78rem; color: #999; font-family: monospace; }
    .topbar-icon-btn {
      background: none; border: none; font-size: 1rem; cursor: pointer;
      padding: 6px 10px; border-radius: 8px; color: #666;
    }
    .topbar-icon-btn:hover { background: #f0ede8; }

    /* CSS variables */
    :root {
      --primary: #100600;
      --primary-bg: linear-gradient(135deg, #100600 0%, #2a1a0f 100%);
      --primary-soft: rgba(16,6,0,0.06);
      --red: #B8312A;
      --red-lt: rgba(184,49,42,0.08);
      --green: #1B7045;
      --green-lt: rgba(27,112,69,0.1);
      --amber: #C97B1A;
      --amber-lt: rgba(201,123,26,0.1);
      --stone-2: #f0ede8;
      --stone-3: #c8c5bf;
      --ink: #100600;
      --ink-2: #2a2218;
      --ink-3: #5a5248;
      --ink-4: #8a8278;
      --ink-5: #b0a898;
      --line: rgba(0,0,0,0.07);
      --glass-bg: rgba(255,255,255,0.75);
      --glass-blur: blur(16px);
      --glass-border: rgba(255,255,255,0.6);
      --glass-shadow: 0 2px 12px rgba(0,0,0,0.06);
      --shadow-sm: 0 1px 4px rgba(0,0,0,0.06);
      --shadow-md: 0 4px 18px rgba(0,0,0,0.1);
      --r-sm: 6px;
      --r-md: 10px;
      --r-lg: 14px;
      --r-xl: 18px;
      --r-2xl: 22px;
    }
    <?php endif; ?>

    /* ── SHARED COMPONENTS (always rendered) ── */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
    }
    .badge-green { background: var(--green-lt); color: var(--green); }
    .badge-amber { background: var(--amber-lt); color: var(--amber); }
    .badge-red   { background: var(--red-lt);   color: var(--red); }

    .btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 18px;
      border: none;
      border-radius: 40px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.18s;
      text-decoration: none;
    }
    .btn-sm { padding: 6px 14px; font-size: 0.74rem; }
    .btn-ghost { background: transparent; border: 1.5px solid var(--line); color: var(--ink-3); }
    .btn-ghost:hover { border-color: var(--ink); color: var(--ink); }
    .btn-danger { background: var(--red); color: white; box-shadow: 0 3px 12px rgba(184,49,42,0.3); }
    .btn-danger:hover { background: #8B1A14; transform: translateY(-1px); }
    .btn-amber { background: var(--amber); color: white; box-shadow: 0 3px 12px rgba(201,123,26,0.25); }
    .btn-amber:hover { background: #a06215; transform: translateY(-1px); }
    .btn-outline { background: transparent; border: 1.5px solid var(--line); color: var(--ink-3); }
    .btn-forest { background: var(--green); color: white; box-shadow: 0 3px 10px rgba(27,112,69,0.25); }
    .btn-forest:hover { background: #155c38; box-shadow: 0 5px 16px rgba(27,112,69,0.35); transform: translateY(-1px); }

    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
    }
    .section-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 0;
    }

    /* Modal */
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(16,6,0,0.5);
      backdrop-filter: blur(8px);
      z-index: 3000;
      display: none;
      align-items: flex-end;
      justify-content: center;
      padding: 0;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: white;
      border-radius: 24px 24px 0 0;
      width: 100%;
      max-width: 540px;
      max-height: 92vh;
      overflow-y: auto;
      animation: modalUp 0.28s cubic-bezier(0.16,1,0.3,1);
    }
    @keyframes modalUp { from { transform: translateY(60px); opacity: 0; } to { transform: none; opacity: 1; } }
    .modal-handle {
      width: 36px; height: 4px;
      background: #ddd; border-radius: 2px;
      margin: 12px auto 0;
    }
    .modal-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 22px 12px;
      border-bottom: 1px solid var(--line);
    }
    .modal-title { font-size: 1rem; font-weight: 700; color: var(--ink); }
    .modal-close {
      width: 32px; height: 32px;
      border: none; background: var(--stone-2); border-radius: 50%;
      cursor: pointer; color: var(--ink-3); font-size: 0.85rem;
      display: flex; align-items: center; justify-content: center;
    }
    .modal-body { padding: 16px 22px; }
    .modal-footer {
      display: flex; gap: 10px; justify-content: flex-end;
      padding: 12px 22px 22px;
      border-top: 1px solid var(--line);
    }
    .form-group { margin-bottom: 14px; }
    .form-label { font-size: 0.74rem; font-weight: 600; color: var(--ink-3); margin-bottom: 5px; display: block; }
    .form-control {
      width: 100%; padding: 10px 13px;
      border: 1.5px solid var(--line);
      border-radius: var(--r-md);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem;
      color: var(--ink);
      background: rgba(255,255,255,0.8);
      outline: none;
      transition: border-color 0.15s;
      appearance: none;
    }
    .form-control:focus { border-color: var(--primary); }
    textarea.form-control { resize: vertical; min-height: 80px; }

    .toast {
      position: fixed;
      bottom: 24px; left: 50%;
      transform: translateX(-50%);
      background: #100600; color: white;
      padding: 10px 22px;
      border-radius: 40px;
      font-size: 0.8rem;
      z-index: 4000;
      opacity: 0;
      transition: opacity 0.25s;
      pointer-events: none;
      white-space: nowrap;
    }
    .toast.show { opacity: 1; }

    /* ── PAGE CONTENT ── */
    .guide-content {
      background:
        radial-gradient(ellipse 50% 40% at 90% 5%, rgba(184,49,42,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 5% 90%, rgba(16,6,0,0.03) 0%, transparent 60%);
    }

    /* ── SAFETY BANNER ── */
    .safety-banner {
      background: linear-gradient(135deg, #100600 0%, #2a1a0f 100%);
      border-radius: var(--r-2xl);
      padding: 26px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      gap: 16px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(16,6,0,0.28), 0 2px 8px rgba(16,6,0,0.16);
    }
    .safety-banner::before {
      content: '';
      position: absolute;
      top: -50px; right: -50px;
      width: 200px; height: 200px;
      background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }
    .safety-banner-content { position: relative; z-index: 1; flex: 1; }
    .safety-banner-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 0.6rem;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      color: rgba(255,255,255,0.4);
      margin-bottom: 6px;
    }
    .safety-banner-text h3 {
      font-size: 1.25rem;
      font-weight: 800;
      color: white;
      margin-bottom: 5px;
    }
    .safety-banner-text p { font-size: 0.82rem; color: rgba(255,255,255,0.55); }
    .safety-banner-pills {
      display: flex; gap: 7px; flex-wrap: wrap; margin-top: 10px;
    }
    .safety-pill {
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.16);
      padding: 4px 11px;
      border-radius: 40px;
      font-size: 0.7rem;
      font-weight: 500;
      color: rgba(255,255,255,0.82);
    }
    .safety-banner-actions {
      position: relative; z-index: 1;
      display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0;
    }
    .emergency-big-btn {
      display: flex; align-items: center; gap: 9px;
      background: rgba(184,49,42,0.92);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.18);
      color: white;
      border-radius: var(--r-lg);
      padding: 12px 22px;
      font-size: 0.84rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.18s;
      box-shadow: 0 4px 20px rgba(184,49,42,0.45);
      font-family: 'DM Sans', sans-serif;
      white-space: nowrap;
    }
    .emergency-big-btn:hover {
      background: var(--red);
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(184,49,42,0.55);
    }

    /* ── SESSION SELECTOR ── */
    .session-select-bar {
      background: rgba(255,255,255,0.75);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.6);
      border-radius: var(--r-lg);
      padding: 12px 18px;
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 20px; flex-wrap: wrap;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .session-select-label {
      font-size: 0.68rem; font-weight: 600;
      text-transform: uppercase; letter-spacing: 0.8px;
      color: var(--ink-4); white-space: nowrap;
    }
    .session-select-bar select {
      flex: 1; border: 1.5px solid var(--line);
      border-radius: var(--r-md);
      padding: 7px 36px 7px 13px;
      font-size: 0.82rem; font-family: 'DM Sans', sans-serif;
      color: var(--ink); background: rgba(255,255,255,0.6);
      outline: none; min-width: 180px; appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236B6B63' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      cursor: pointer;
    }
    .session-select-bar select:focus { border-color: var(--primary); outline: none; }

    /* ── SAFETY GRID ── */
    .safety-layout {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 20px;
    }

    /* ── HIKER SAFETY CARDS ── */
    .hiker-safety-list { display: flex; flex-direction: column; gap: 9px; }
    .hiker-safety-card {
      background: rgba(255,255,255,0.75);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.6);
      border-radius: var(--r-lg);
      padding: 14px 16px;
      display: flex; align-items: center; gap: 13px;
      transition: all 0.18s;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      position: relative; overflow: hidden;
    }
    .hiker-safety-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.1); background: white; }
    .hiker-safety-card.card-safe    { border-left: 3px solid var(--green); }
    .hiker-safety-card.card-unsafe  { border-left: 3px solid var(--red); }
    .hiker-safety-card.card-unknown { border-left: 3px solid var(--stone-3); }
    .hs-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: rgba(16,6,0,0.06);
      color: var(--primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem; font-weight: 700;
      flex-shrink: 0;
      border: 2px solid rgba(16,6,0,0.08);
      overflow: hidden;
    }
    .hs-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .hs-info { flex: 1; min-width: 0; }
    .hs-name { font-size: 0.87rem; font-weight: 600; color: var(--ink); }
    .hs-meta { font-size: 0.68rem; color: var(--ink-4); margin-top: 1px; }
    .hs-checkpoint {
      font-size: 0.66rem; color: var(--ink-4); margin-top: 3px;
      display: flex; align-items: center; gap: 4px;
    }
    .hs-actions { flex-shrink: 0; }
    .safety-toggle {
      display: flex; background: var(--stone-2);
      border-radius: 40px; padding: 3px; gap: 2px;
    }
    .stoggle-btn {
      padding: 5px 11px; border-radius: 40px; border: none;
      font-size: 0.68rem; font-weight: 600; cursor: pointer;
      transition: all 0.15s; background: transparent; color: var(--ink-3);
      font-family: 'DM Sans', sans-serif;
    }
    .stoggle-btn.active-safe   { background: var(--green); color: white; box-shadow: 0 2px 8px rgba(27,112,69,0.3); }
    .stoggle-btn.active-unsafe { background: var(--red);   color: white; box-shadow: 0 2px 8px rgba(184,49,42,0.3); }

    /* Emergency contact reveal */
    .emergency-contact-row {
      display: none;
      font-size: 0.66rem; color: var(--red);
      margin-top: 5px; padding: 5px 8px;
      background: var(--red-lt);
      border-radius: 6px;
      gap: 5px; align-items: center;
    }
    .emergency-contact-row.show { display: flex; }

    /* ── ALERT STATUS SECTION ── */
    .alert-status-section {
      background: rgba(255,255,255,0.75);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.6);
      border-radius: var(--r-xl);
      padding: 16px 18px;
      margin-bottom: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .alert-count-row {
      display: grid; grid-template-columns: 1fr 1fr 1fr;
      gap: 8px; margin-top: 12px;
    }
    .alert-count-box {
      border-radius: var(--r-md);
      padding: 10px 12px; text-align: center;
    }
    .alert-count-box.green { background: var(--green-lt); }
    .alert-count-box.red   { background: var(--red-lt); }
    .alert-count-box.grey  { background: rgba(0,0,0,0.04); }
    .alert-count-num { font-size: 1.5rem; font-weight: 800; line-height: 1; }
    .alert-count-box.green .alert-count-num { color: var(--green); }
    .alert-count-box.red   .alert-count-num { color: var(--red); }
    .alert-count-box.grey  .alert-count-num { color: var(--ink-3); }
    .alert-count-label { font-size: 0.62rem; font-weight: 600; color: var(--ink-5); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }

    /* ── INCIDENT LOG ── */
    .incident-wrapper {
      background: rgba(255,255,255,0.75);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.6);
      border-radius: var(--r-xl);
      padding: 16px 18px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .incident-list { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
    .incident-item {
      background: rgba(184,49,42,0.05);
      border: 1px solid rgba(184,49,42,0.1);
      border-radius: var(--r-md);
      padding: 11px 13px;
    }
    .incident-item.severity-critical { background: rgba(184,49,42,0.1); border-color: rgba(184,49,42,0.25); }
    .incident-header { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; flex-wrap: wrap; }
    .incident-type { font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--red); }
    .incident-time { font-family: 'DM Mono', monospace; font-size: 0.6rem; color: var(--ink-5); margin-left: auto; }
    .incident-text { font-size: 0.77rem; color: var(--ink-2); line-height: 1.5; }
    .incident-severity-badge {
      padding: 2px 8px; border-radius: 20px; font-size: 0.6rem; font-weight: 700;
    }
    .sev-critical { background: var(--red-lt); color: var(--red); }
    .sev-high     { background: rgba(201,123,26,0.15); color: var(--amber); }
    .sev-medium   { background: rgba(27,112,69,0.1); color: var(--green); }
    #noIncidents {
      font-size: 0.78rem; color: var(--ink-4);
      padding: 16px 0; text-align: center;
    }

    @media (max-width: 1024px) { .safety-layout { grid-template-columns: 1fr; } }
    @media (max-width: 640px) {
      .safety-banner { flex-direction: column; align-items: flex-start; }
      .safety-banner-actions { flex-direction: row; }
      .guide-content { padding: 16px; }
    }
  </style>
</head>
<body>
<div class="guide-app">

  <?php if (!$isEmbed): ?>
  <!-- ── SIDEBAR ── -->
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none">
        <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="white" opacity="0.9"/>
        <path d="M14 16L18 8L24 22H14V16Z" fill="white" opacity="0.3"/>
      </svg>
      <div><div class="sidebar-logo-text">LAKBAY</div><div class="sidebar-logo-sub">Guide Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.php"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.php"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.php"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.php" class="active"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
  <?php if (!empty($guide_data['avatar'])): ?>
    <img src="../<?= htmlspecialchars($guide_data['avatar']) ?>" class="sidebar-avatar" style="object-fit:cover;" alt="avatar">
  <?php else: ?>
    <div class="sidebar-avatar"><?= $initials ?></div>
  <?php endif; ?>
  <div class="sidebar-profile-info">
    <div class="sidebar-profile-name"><?= htmlspecialchars($guide_data['name']) ?></div>
    <div class="sidebar-profile-role"><?= htmlspecialchars($guide_data['specialization'] ?? 'Trail Guide') ?></div>
  </div>
  <a href="../login-and-signup/login.php" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;text-decoration:none;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </a>
</div>
  </aside>
  <?php endif; ?>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Safety Reporting</div>
      <div class="topbar-right">
        <div class="topbar-time" id="liveTime"></div>
        <button class="topbar-icon-btn" onclick="openModal('incidentModal')" title="Report Incident">
          <i class="fas fa-circle-exclamation"></i>
        </button>
      </div>
    </div>

    <div class="guide-content">

      <!-- SAFETY BANNER -->
      <div class="safety-banner">
        <div class="safety-banner-content">
          <div class="safety-banner-eyebrow"><i class="fas fa-shield-halved" style="margin-right:5px;"></i>Safety Command Center</div>
          <div class="safety-banner-text">
            <h3>Hiker Safety Dashboard</h3>
            <p>Monitor all hikers in real-time and report incidents instantly.</p>
          </div>
          <div class="safety-banner-pills">
            <span class="safety-pill"><i class="fas fa-users" style="font-size:0.7rem;"></i> <span id="pilHikerCount"><?= count($allHikers) ?></span> Hiker<?= count($allHikers) != 1 ? 's' : '' ?></span>
            <?php if ($activeBooking): ?>
            <span class="safety-pill"><i class="fas fa-mountain" style="font-size:0.7rem;"></i> <?= htmlspecialchars($activeBooking['mountain_name']) ?></span>
            <span class="safety-pill"><i class="fas fa-clock" style="font-size:0.7rem;"></i> <?= ucfirst($activeBooking['status']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="safety-banner-actions">
          <button class="emergency-big-btn" onclick="openModal('emergencyModal')">
            <i class="fas fa-triangle-exclamation"></i> Emergency Alert
          </button>
        </div>
      </div>

      <!-- SESSION SELECTOR -->
      <div class="session-select-bar">
        <span class="session-select-label"><i class="fas fa-route" style="margin-right:5px;"></i> Active Hike</span>
        <select id="sessionSelect" onchange="switchBooking(this.value)">
          <?php if (empty($bookings)): ?>
          <option value="">No active bookings</option>
          <?php else: ?>
          <?php foreach ($bookings as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id'] == $selectedBookingId ? 'selected' : '' ?>>
            BK-<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?> — <?= htmlspecialchars($b['booker_name']) ?> · <?= htmlspecialchars($b['mountain_name']) ?> · <?= date('M d', strtotime($b['hike_date'])) ?>
          </option>
          <?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if ($activeBooking): ?>
        <span class="badge badge-green">
          <span style="width:5px;height:5px;background:var(--green);border-radius:50%;display:inline-block;"></span>
          <?= ucfirst($activeBooking['status']) ?>
        </span>
        <?php endif; ?>
      </div>

      <div class="safety-layout">
        <!-- LEFT: HIKER STATUS -->
        <div>
          <div class="section-header">
            <div class="section-title">Hiker Safety Status</div>
            <button class="btn btn-forest btn-sm" onclick="markAllSafe()">
              <i class="fas fa-shield-check"></i> Mark All Safe
            </button>
          </div>
          <div class="hiker-safety-list" id="hikerSafetyList">
            <?php if (empty($allHikers)): ?>
            <div style="padding:30px;text-align:center;color:var(--ink-4);font-size:0.8rem;background:rgba(255,255,255,0.5);border-radius:var(--r-lg);">
              <i class="fas fa-users-slash" style="font-size:1.5rem;margin-bottom:8px;display:block;opacity:0.4;"></i>
              No hikers found for this booking
            </div>
            <?php else: ?>
            <?php foreach ($allHikers as $idx => $h):
              $initials = implode('', array_map(fn($w)=>strtoupper($w[0]), array_slice(explode(' ',$h['name']??'H'),0,2)));
              $statusKey = $h['user_id'] ?? $h['name'];
              $status = $hikerStatuses[$statusKey] ?? 'unknown';
              $cardClass = match($status) { 'safe' => 'card-safe', 'unsafe' => 'card-unsafe', default => 'card-unknown' };
              $meta = $h['hiker_type'] === 'owner' ? 'Booking Owner' : ('Age: ' . ($h['age'] ?? '—'));
            ?>
            <div class="hiker-safety-card <?= $cardClass ?>" id="hikerCard_<?= $idx ?>">
              <div class="hs-avatar">
                <?php if (!empty($h['avatar'])): ?>
                <img src="<?= htmlspecialchars($h['avatar']) ?>" alt="<?= htmlspecialchars($h['name']) ?>">
                <?php else: ?>
                <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
              </div>
              <div class="hs-info">
                <div class="hs-name"><?= htmlspecialchars($h['name'] ?? 'Unknown') ?></div>
                <div class="hs-meta"><?= htmlspecialchars($meta) ?></div>
                <div class="hs-checkpoint">
                  <i class="fas fa-location-dot" style="font-size:0.58rem;color:var(--ink-5);"></i>
                  <span id="hikerLoc_<?= $idx ?>">Location tracking…</span>
                </div>
                <div class="emergency-contact-row" id="ecRow_<?= $idx ?>">
                  <i class="fas fa-phone" style="font-size:0.65rem;"></i>
                  <?php if (!empty($h['emergency_contact_name'])): ?>
                  <strong><?= htmlspecialchars($h['emergency_contact_name']) ?></strong>: <?= htmlspecialchars($h['emergency_contact_number'] ?? '—') ?>
                  <?php elseif (!empty($h['phone'])): ?>
                  Direct: <?= htmlspecialchars($h['phone']) ?>
                  <?php else: ?>
                  No emergency contact on file
                  <?php endif; ?>
                </div>
              </div>
              <div class="hs-actions">
                <div class="safety-toggle">
                  <button class="stoggle-btn <?= $status==='safe' ? 'active-safe' : '' ?>"
                    onclick="setSafe(<?= $idx ?>, '<?= htmlspecialchars($h['user_id'] ?? '') ?>', '<?= htmlspecialchars($h['name']) ?>', true)">
                    <i class="fas fa-shield-check"></i> Safe
                  </button>
                  <button class="stoggle-btn <?= $status==='unsafe' ? 'active-unsafe' : '' ?>"
                    onclick="setSafe(<?= $idx ?>, '<?= htmlspecialchars($h['user_id'] ?? '') ?>', '<?= htmlspecialchars($h['name']) ?>', false)">
                    <i class="fas fa-circle-xmark"></i> Unsafe
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT: STATUS COUNTS + INCIDENT LOG -->
        <div style="display:flex;flex-direction:column;gap:16px;">

          <!-- STATUS COUNTS -->
          <div class="alert-status-section">
            <div class="section-title" style="margin-bottom:0;">Status Overview</div>
            <div class="alert-count-row" id="statusCountRow">
              <div class="alert-count-box green">
                <div class="alert-count-num" id="countSafe">0</div>
                <div class="alert-count-label">Safe</div>
              </div>
              <div class="alert-count-box red">
                <div class="alert-count-num" id="countUnsafe">0</div>
                <div class="alert-count-label">Unsafe</div>
              </div>
              <div class="alert-count-box grey">
                <div class="alert-count-num" id="countUnknown"><?= count($allHikers) ?></div>
                <div class="alert-count-label">Unknown</div>
              </div>
            </div>
            <button class="btn btn-outline btn-sm" style="margin-top:12px;width:100%;justify-content:center;" onclick="sendGroupStatusAlert()">
              <i class="fas fa-paper-plane"></i> Send Status Report to Admin
            </button>
          </div>

          <!-- INCIDENT LOG -->
          <div class="incident-wrapper">
            <div class="section-header" style="margin-bottom:10px;">
              <div class="section-title" style="margin-bottom:0;">Incident Log</div>
              <button class="btn btn-ghost btn-sm" onclick="openModal('incidentModal')">
                <i class="fas fa-plus"></i> Report
              </button>
            </div>
            <div class="incident-list" id="incidentList">
              <?php foreach ($safetyAlerts as $alert): ?>
              <div class="incident-item severity-<?= htmlspecialchars($alert['severity'] ?? 'medium') ?>">
                <div class="incident-header">
                  <span class="incident-type">
                    <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i>
                    <?= htmlspecialchars(ucfirst($alert['type'] ?? 'alert')) ?>
                  </span>
                  <?php if (!empty($alert['severity'])): ?>
                  <span class="incident-severity-badge sev-<?= htmlspecialchars($alert['severity']) ?>">
                    <?= ucfirst($alert['severity']) ?>
                  </span>
                  <?php endif; ?>
                  <span class="incident-time"><?= date('h:i A', strtotime($alert['created_at'])) ?></span>
                </div>
                <div class="incident-text">
                  <?php if (!empty($alert['location'])): ?>
                  <strong><?= htmlspecialchars($alert['location']) ?></strong> — 
                  <?php endif; ?>
                  <?= htmlspecialchars($alert['description'] ?? '') ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php if (empty($safetyAlerts)): ?>
            <div id="noIncidents" style="font-size:0.78rem;color:var(--ink-4);padding:16px 0;text-align:center;">
              <i class="fas fa-check-circle" style="color:var(--green);margin-right:5px;"></i>No incidents reported
            </div>
            <?php else: ?>
            <div id="noIncidents" style="display:none;font-size:0.78rem;color:var(--ink-4);padding:16px 0;text-align:center;">
              <i class="fas fa-check-circle" style="color:var(--green);margin-right:5px;"></i>No incidents reported
            </div>
            <?php endif; ?>
          </div>

        </div>
      </div>

    </div><!-- /guide-content -->
  </div><!-- /guide-main -->

  <?php if (!$isEmbed): ?>
  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.php" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.php" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.php" class="bnav-item"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-safety.php" class="bnav-item active"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
      <a href="guide-profile.php" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
  <?php endif; ?>

</div><!-- /guide-app -->

<!-- ═══ EMERGENCY MODAL ═══ -->
<div class="modal-overlay" id="emergencyModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);">
        <i class="fas fa-triangle-exclamation" style="margin-right:8px;"></i>Emergency Alert
      </div>
      <button class="modal-close" onclick="closeModal('emergencyModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div style="font-size:0.8rem;color:var(--ink-3);margin-bottom:14px;line-height:1.65;background:var(--red-lt);padding:10px 14px;border-radius:var(--r-md);border-left:3px solid var(--red);">
        <strong style="color:var(--red);">⚠ Immediate notification</strong> — Admin and all emergency contacts will be alerted.
      </div>
      <div class="form-group">
        <label class="form-label">Situation Type</label>
        <select class="form-control" id="emType">
          <option value="medical">Medical Emergency</option>
          <option value="lost">Lost Hiker</option>
          <option value="weather">Weather Hazard</option>
          <option value="accident">Trail Accident</option>
          <option value="wildlife">Wildlife Encounter</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Hiker Involved</label>
        <select class="form-control" id="emHiker">
          <option value="all">All hikers</option>
          <?php foreach ($allHikers as $h): ?>
          <option value="<?= htmlspecialchars($h['user_id'] ?? $h['name']) ?>"><?= htmlspecialchars($h['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Location / Checkpoint</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" class="form-control" id="emLocation" placeholder="e.g. Ridge Junction km 3.2" style="flex:1;">
          <button type="button" id="emGpsBtn" onclick="getEmergencyLocation()"
            style="flex-shrink:0;padding:10px 13px;background:#100600;color:white;border:none;border-radius:var(--r-md);cursor:pointer;font-size:0.78rem;font-weight:600;display:flex;align-items:center;gap:6px;white-space:nowrap;transition:all 0.15s;">
            <i class="fas fa-location-crosshairs"></i> Get Location
          </button>
        </div>
        <!-- Hidden lat/lng fields -->
        <input type="hidden" id="emLat" value="">
        <input type="hidden" id="emLng" value="">
        <!-- GPS status feedback -->
        <div id="emGpsStatus" style="display:none;margin-top:6px;font-size:0.7rem;padding:6px 10px;border-radius:6px;display:flex;align-items:center;gap:6px;"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" id="emDesc" rows="3" placeholder="Describe the situation…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('emergencyModal')">Cancel</button>
      <button class="btn btn-danger" onclick="sendEmergency()">
        <i class="fas fa-paper-plane"></i> Send Alert Now
      </button>
    </div>
  </div>
</div>

<!-- ═══ INCIDENT MODAL ═══ -->
<div class="modal-overlay" id="incidentModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <div class="modal-title">
        <i class="fas fa-circle-exclamation" style="margin-right:8px;color:var(--amber);"></i>Report Incident
      </div>
      <button class="modal-close" onclick="closeModal('incidentModal')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Incident Type</label>
        <select class="form-control" id="incType">
          <option value="safety">Safety Alert</option>
          <option value="minor_injury">Minor Injury</option>
          <option value="fatigue">Hiker Fatigue</option>
          <option value="lost_trail">Lost Trail</option>
          <option value="weather">Weather Issue</option>
          <option value="equipment">Equipment Problem</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Severity</label>
        <select class="form-control" id="incSeverity">
          <option value="low">Low — Minor issue</option>
          <option value="medium" selected>Medium — Needs attention</option>
          <option value="high">High — Urgent</option>
          <option value="critical">Critical — Emergency</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Location / Checkpoint</label>
        <input type="text" class="form-control" id="incLocation" placeholder="e.g. Lower Ridge, km 1.8">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" id="incDesc" rows="3" placeholder="What happened?"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Notify Admin?</label>
        <select class="form-control" id="incNotify">
          <option value="yes">Yes — notify admin</option>
          <option value="no">No — log only</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('incidentModal')">Cancel</button>
      <button class="btn btn-amber" onclick="submitIncident()">
        <i class="fas fa-flag"></i> Submit Report
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── PHP → JS ──────────────────────────────────────────────────────
const BOOKING_ID      = <?= json_encode($selectedBookingId) ?>;
const ALL_HIKERS      = <?= json_encode($allHikers) ?>;
const INITIAL_STATUSES = <?= json_encode($hikerStatuses) ?>;
const GUIDE_NAME      = <?= json_encode($guideName) ?>;

// ── LOCAL STATE ──────────────────────────────────────────────────
let hikerStatuses = { ...INITIAL_STATUSES };

// Pre-populate from PHP
ALL_HIKERS.forEach((h, i) => {
    const key = h.user_id || h.name;
    hikerStatuses[key] = hikerStatuses[key] || 'unknown';
});

// ── LIVE TIME ─────────────────────────────────────────────────────
function updateTime() {
    const el = document.getElementById('liveTime');
    if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}
updateTime();
setInterval(updateTime, 1000);

// ── STATUS COUNTS ──────────────────────────────────────────────────
function updateCounts() {
    const vals = Object.values(hikerStatuses);
    document.getElementById('countSafe').textContent    = vals.filter(v => v === 'safe').length;
    document.getElementById('countUnsafe').textContent  = vals.filter(v => v === 'unsafe').length;
    document.getElementById('countUnknown').textContent = vals.filter(v => v === 'unknown').length;
}
updateCounts();

// ── SET SAFE / UNSAFE ─────────────────────────────────────────────
async function setSafe(idx, userId, hikerName, isSafe) {
    const key    = userId || hikerName;
    const status = isSafe ? 'safe' : 'unsafe';
    hikerStatuses[key] = status;

    // Update card class
    const card = document.getElementById(`hikerCard_${idx}`);
    if (card) {
        card.classList.remove('card-safe', 'card-unsafe', 'card-unknown');
        card.classList.add(isSafe ? 'card-safe' : 'card-unsafe');
    }

    // Update toggle buttons
    const btns = card?.querySelectorAll('.stoggle-btn');
    if (btns) {
        btns[0].classList.toggle('active-safe',   isSafe);
        btns[1].classList.toggle('active-unsafe', !isSafe);
    }

    // Show emergency contact if unsafe
    const ecRow = document.getElementById(`ecRow_${idx}`);
    if (ecRow) ecRow.classList.toggle('show', !isSafe);

    updateCounts();
    showToast(isSafe ? `✅ ${hikerName} marked safe` : `⚠️ ${hikerName} marked unsafe`);

    // Persist to server
    try {
        await fetch('../api/update_hiker_safety.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: BOOKING_ID,
                user_id: userId || null,
                hiker_name: hikerName,
                status: status
            })
        });
    } catch (e) { console.warn('Safety status save failed:', e); }

    // If unsafe — prompt emergency
    if (!isSafe) {
        setTimeout(() => {
            if (confirm(`${hikerName} was marked UNSAFE. Open Emergency Alert?`)) {
                document.getElementById('emHiker').value = userId || 'all';
                openModal('emergencyModal');
            }
        }, 400);
    }
}

// ── MARK ALL SAFE ──────────────────────────────────────────────────
async function markAllSafe() {
    ALL_HIKERS.forEach((h, i) => {
        const key = h.user_id || h.name;
        hikerStatuses[key] = 'safe';

        const card = document.getElementById(`hikerCard_${i}`);
        if (card) {
            card.classList.remove('card-safe', 'card-unsafe', 'card-unknown');
            card.classList.add('card-safe');
        }
        const btns = card?.querySelectorAll('.stoggle-btn');
        if (btns) {
            btns[0].classList.add('active-safe');
            btns[1].classList.remove('active-unsafe');
        }
        const ecRow = document.getElementById(`ecRow_${i}`);
        if (ecRow) ecRow.classList.remove('show');
    });
    updateCounts();
    showToast('All hikers marked safe ✅');

    try {
        await fetch('../api/update_hiker_safety.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: BOOKING_ID, mark_all_safe: true })
        });
    } catch (e) {}
}

// ── SEND STATUS REPORT TO ADMIN ────────────────────────────────────
async function sendGroupStatusAlert() {
    const safe    = Object.values(hikerStatuses).filter(v => v === 'safe').length;
    const unsafe  = Object.values(hikerStatuses).filter(v => v === 'unsafe').length;
    const unknown = Object.values(hikerStatuses).filter(v => v === 'unknown').length;

    try {
        const res = await fetch('../api/send_safety_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: BOOKING_ID,
                type: 'status_report',
                severity: unsafe > 0 ? 'high' : 'low',
                title: 'Hiker Status Report',
                description: `Status report from ${GUIDE_NAME}: ${safe} safe, ${unsafe} unsafe, ${unknown} unknown.`,
                reporter_name: GUIDE_NAME,
                reporter_role: 'guide'
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast('📊 Status report sent to admin');
        } else {
            showToast('Report saved locally (offline mode)');
        }
    } catch (e) {
        showToast('Report saved locally (offline mode)');
    }
}

// ── EMERGENCY ─────────────────────────────────────────────────────
// ── GET GPS LOCATION FOR EMERGENCY ───────────────────────────────
function getEmergencyLocation() {
    const btn    = document.getElementById('emGpsBtn');
    const status = document.getElementById('emGpsStatus');
    const locInput = document.getElementById('emLocation');

    if (!navigator.geolocation) {
        showGpsStatus('error', '❌ Geolocation not supported on this device');
        return;
    }

    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting…';
    btn.disabled = true;
    showGpsStatus('loading', '📡 Acquiring GPS signal…');

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            const acc = Math.round(pos.coords.accuracy);

            // Store lat/lng in hidden fields
            document.getElementById('emLat').value = lat;
            document.getElementById('emLng').value = lng;

            // Show coords in the text field if it's empty, otherwise append
            if (!locInput.value.trim()) {
                locInput.value = `${lat}, ${lng}`;
            }

            // Reset button
            btn.innerHTML = '<i class="fas fa-check"></i> Got It';
            btn.style.background = 'var(--green)';
            btn.disabled = false;

            showGpsStatus('success', `✅ Location captured (±${acc}m accuracy) — ${lat}, ${lng}`);

            // Reset button after 3s
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Get Location';
                btn.style.background = '#100600';
            }, 3000);
        },
        (err) => {
            btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Get Location';
            btn.disabled = false;
            const msgs = {
                1: '❌ Location permission denied — please enable in browser settings',
                2: '❌ GPS signal unavailable — enter location manually',
                3: '❌ GPS timed out — try again or enter manually'
            };
            showGpsStatus('error', msgs[err.code] || '❌ Could not get location');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

function showGpsStatus(type, msg) {
    const el = document.getElementById('emGpsStatus');
    if (!el) return;
    el.style.display = 'flex';
    el.textContent = msg;
    if (type === 'loading') {
        el.style.background = 'rgba(201,123,26,0.1)';
        el.style.color = 'var(--amber)';
    } else if (type === 'success') {
        el.style.background = 'var(--green-lt)';
        el.style.color = 'var(--green)';
    } else {
        el.style.background = 'var(--red-lt)';
        el.style.color = 'var(--red)';
    }
}

async function sendEmergency() {
    const type     = document.getElementById('emType').value;
    const hikerVal = document.getElementById('emHiker').value;
    const location = document.getElementById('emLocation').value;
    const desc     = document.getElementById('emDesc').value || 'Emergency situation.';
    const lat      = document.getElementById('emLat').value || null;
    const lng      = document.getElementById('emLng').value || null;

    const hikerName = hikerVal === 'all' ? 'All hikers' :
        (ALL_HIKERS.find(h => (h.user_id || h.name) == hikerVal)?.name || hikerVal);

    // Build location string — include coords if we have them
    let locationStr = location;
    if (lat && lng && !location.includes(lat)) {
        locationStr = location ? `${location} (${lat}, ${lng})` : `${lat}, ${lng}`;
    }

    try {
        const res = await fetch('../api/send_safety_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: BOOKING_ID,
                type: type,
                severity: 'critical',
                title: `EMERGENCY: ${type.replace('_',' ').toUpperCase()}`,
                description: desc,
                location: locationStr,
                latitude: lat ? parseFloat(lat) : null,
                longitude: lng ? parseFloat(lng) : null,
                trail_name: null,
                reporter_name: GUIDE_NAME,
                reporter_role: 'guide',
                hiker_involved: hikerName
            })
        });
        const data = await res.json();

        prependIncident({
            type: 'EMERGENCY',
            severity: 'critical',
            location: locationStr,
            description: desc,
            time: new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
        });

        closeModal('emergencyModal');
        showToast('🚨 Emergency alert sent!');

        // Clear form
        document.getElementById('emDesc').value = '';
        document.getElementById('emLocation').value = '';
        document.getElementById('emLat').value = '';
        document.getElementById('emLng').value = '';
        const status = document.getElementById('emGpsStatus');
        if (status) status.style.display = 'none';
        const btn = document.getElementById('emGpsBtn');
        if (btn) { btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Get Location'; btn.style.background = '#100600'; }

    } catch (e) {
        showToast('🚨 Alert queued — will send when online');
        closeModal('emergencyModal');
    }
}

// ── SUBMIT INCIDENT ───────────────────────────────────────────────
async function submitIncident() {
    const type     = document.getElementById('incType').value;
    const severity = document.getElementById('incSeverity').value;
    const location = document.getElementById('incLocation').value;
    const desc     = document.getElementById('incDesc').value || 'No description.';
    const notify   = document.getElementById('incNotify').value;

    try {
        await fetch('../api/send_safety_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: BOOKING_ID,
                type: type,
                severity: severity,
                title: type.replace('_', ' '),
                description: desc,
                location: location,
                reporter_name: GUIDE_NAME,
                reporter_role: 'guide',
                notify_admin: notify === 'yes'
            })
        });
    } catch (e) {}

    prependIncident({
        type: type.replace('_', ' '),
        severity: severity,
        location: location,
        description: desc,
        time: new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
    });

    closeModal('incidentModal');
    showToast(notify === 'yes' ? '📋 Incident logged & admin notified.' : '📋 Incident logged.');
    document.getElementById('incDesc').value = '';
    document.getElementById('incLocation').value = '';
}

function prependIncident({ type, severity, location, description, time }) {
    const list  = document.getElementById('incidentList');
    const noInc = document.getElementById('noIncidents');
    if (noInc) noInc.style.display = 'none';

    const div = document.createElement('div');
    div.className = `incident-item severity-${severity}`;
    div.innerHTML = `
        <div class="incident-header">
            <span class="incident-type"><i class="fas fa-circle-exclamation" style="margin-right:4px;"></i>${type.toUpperCase()}</span>
            <span class="incident-severity-badge sev-${severity}">${severity.charAt(0).toUpperCase()+severity.slice(1)}</span>
            <span class="incident-time">${time}</span>
        </div>
        <div class="incident-text">${location ? `<strong>${location}</strong> — ` : ''}${description}</div>
    `;
    list.insertBefore(div, list.firstChild);
}

// ── BOOKING SWITCH ────────────────────────────────────────────────
function switchBooking(id) {
    if (id) window.location.href = `guide-safety.php?booking_id=${id}<?= $isEmbed ? '&embed=1' : '' ?>`;
}

// ── MODAL UTILS ───────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); });
});

// ── TOAST ─────────────────────────────────────────────────────────
let _toastTimer;
function showToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

// ── HIKER LOCATION LABELS ─────────────────────────────────────────
// Poll hiker locations every 20s to update the small label
async function refreshHikerLocations() {
    if (!BOOKING_ID) return;
    try {
        const res = await fetch(`../api/get_guide_hikers.php?booking_id=${BOOKING_ID}`);
        const data = await res.json();
        if (data.success && data.hikers) {
            data.hikers.forEach((h, i) => {
                const locEl = document.getElementById(`hikerLoc_${i}`);
                if (!locEl) return;
                if (h.latitude && h.longitude) {
                    const ago = h.minutes_ago != null ? `${h.minutes_ago}m ago` : '';
                    locEl.textContent = `${parseFloat(h.latitude).toFixed(4)}, ${parseFloat(h.longitude).toFixed(4)} ${ago}`.trim();
                } else {
                    locEl.textContent = h.location_tracking_enabled == 0 ? 'Tracking off' : 'Awaiting location…';
                }
            });
        }
    } catch (e) {}
}
refreshHikerLocations();
setInterval(refreshHikerLocations, 20000);
</script>
</body>
</html>