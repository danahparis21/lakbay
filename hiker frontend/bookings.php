<?php
// bookings.php - Lakbay Bookings (Improved)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY — Bookings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── CSS RESET & VARIABLES ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --forest: #1a2e1a;
  --sage: #5c7a5c;
  --gold: #c9a84c;
  --cream: #faf8f3;
  --sky: #f0f4ef;
  --stone: #7a7060;
  --bark: #5d4037;
  --white: #ffffff;
  --danger: #c0392b;
  --radius: 16px;
  --radius-sm: 10px;
  --shadow: 0 2px 16px rgba(16,6,0,0.08);
  --shadow-lg: 0 8px 40px rgba(16,6,0,0.14);
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--cream);
  color: var(--forest);
  min-height: 100vh;
}

/* ── NAV ── */
.desktop-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(250,248,243,0.92); backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(16,6,0,0.08);
  height: 64px; display: flex; align-items: center;
  padding: 0 32px; gap: 32px;
}
.brand {
  font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 700;
  color: var(--forest); text-decoration: none; display: flex; align-items: center; gap: 8px;
}
.brand svg { width: 28px; height: 28px; }
.tabs { display: flex; gap: 4px; flex: 1; justify-content: center; }
.tab-link {
  display: flex; align-items: center; gap: 6px; padding: 8px 16px;
  border-radius: 50px; font-size: 13px; font-weight: 600; color: var(--stone);
  text-decoration: none; transition: all 0.2s;
}
.tab-link:hover { background: var(--sky); color: var(--forest); }
.tab-link.active { background: var(--forest); color: var(--cream); }
.tab-link svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }
.user-btn {
  width: 36px; height: 36px; border-radius: 50%; background: var(--forest);
  color: var(--cream); font-weight: 700; font-size: 13px;
  display: flex; align-items: center; justify-content: center; text-decoration: none;
}
.mobile-nav { display: none; }
@media (max-width: 768px) {
  .desktop-nav { display: none; }
  .mobile-nav {
    display: block; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
    background: rgba(250,248,243,0.96); backdrop-filter: blur(16px);
    border-top: 1px solid rgba(16,6,0,0.08);
  }
  .mobile-nav-inner {
    display: flex; align-items: center; justify-content: space-around;
    padding: 8px 0 max(8px, env(safe-area-inset-bottom));
  }
  .mob-nav-item {
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600; color: var(--stone);
    text-decoration: none; padding: 4px 12px;
  }
  .mob-nav-item.active { color: var(--forest); }
  .mob-nav-item svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 1.8; fill: none; }
  .mob-nav-item.quiz-center {
    width: 52px; height: 52px; border-radius: 50%; background: var(--forest);
    color: var(--cream); padding: 0; display: flex; align-items: center; justify-content: center;
    margin-top: -16px; box-shadow: 0 4px 16px rgba(26,46,26,0.3);
  }
}

/* ── CONTAINER ── */
.container { max-width: 1100px; margin: 0 auto; padding: 0 24px; }

/* ── MODAL ── */
.modal-bg {
  position: fixed; inset: 0; z-index: 200; background: rgba(16,6,0,0.5);
  backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
}
.modal-bg.open { opacity: 1; pointer-events: all; }
.modal {
  background: var(--white); border-radius: 24px; width: 100%; max-height: 90vh;
  overflow-y: auto; box-shadow: var(--shadow-lg);
  transform: translateY(12px) scale(0.98);
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
.modal-bg.open .modal { transform: translateY(0) scale(1); }
.modal-hdr {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 24px 28px 0; position: sticky; top: 0; background: var(--white);
  z-index: 1; border-radius: 24px 24px 0 0;
}
.modal-title { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: var(--forest); }
.modal-close {
  width: 32px; height: 32px; border-radius: 50%; border: none;
  background: var(--sky); color: var(--stone); font-size: 14px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; flex-shrink: 0; margin-top: 2px;
}
.modal-close:hover { background: var(--forest); color: var(--cream); transform: rotate(90deg); }
.modal-body { padding: 20px 28px; }

/* ── BUTTONS ── */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  padding: 11px 20px; border-radius: 50px; font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px; font-weight: 700; cursor: pointer; border: none;
  transition: all 0.18s ease; text-decoration: none;
}
.btn:active { transform: scale(0.96); }
.btn-primary { background: var(--forest); color: var(--cream); }
.btn-primary:hover { background: #243824; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(26,46,26,0.25); }
.btn-primary:disabled { background: #ccc; color: #999; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-outline {
  background: transparent; color: var(--forest);
  border: 1.5px solid rgba(26,46,26,0.2);
}
.btn-outline:hover { background: var(--sky); border-color: var(--forest); }
.btn-danger { background: #fce4ec; color: var(--danger); border: 1.5px solid rgba(192,57,43,0.2); }
.btn-danger:hover { background: var(--danger); color: white; }
.btn-full { width: 100%; }
.btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 30px; }
.btn svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }
.btn-sm svg { width: 12px; height: 12px; }

.btn-glass-primary {
  display: inline-flex; align-items: center; justify-content: center; gap: 10px;
  padding: 14px 28px; border-radius: 50px; font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; font-weight: 700; cursor: pointer;
  border: 1.5px solid rgba(201,168,76,0.5);
  background: rgba(201,168,76,0.12); backdrop-filter: blur(12px);
  color: var(--gold); text-decoration: none; transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(201,168,76,0.15);
}
.btn-glass-primary:hover {
  background: rgba(201,168,76,0.25); border-color: rgba(201,168,76,0.8);
  transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.25);
}
.btn-glass-primary svg { width: 18px; height: 18px; stroke: var(--gold); stroke-width: 2; fill: none; }

/* ── BADGE ── */
.badge {
  display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px;
  border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.badge-easy { background: #e8f5e9; color: #2e7d32; }
.badge-moderate { background: #fff8e1; color: #f57f17; }
.badge-hard { background: #fce4ec; color: #c62828; }
.status-pending { background: #fff8e1; color: #f57f17; border: 1px solid rgba(245,127,23,0.2); }
.status-confirmed { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }
.status-completed { background: #e8eaf6; color: #283593; border: 1px solid rgba(40,53,147,0.2); }
.status-cancelled { background: #fce4ec; color: #c62828; border: 1px solid rgba(198,40,40,0.2); }
.status-joined { background: #e8f5e9; color: #2e7d32; border: 1px solid rgba(46,125,50,0.2); }

/* ── TOAST ── */
.toast {
  position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(20px);
  background: var(--forest); color: var(--cream); padding: 12px 24px;
  border-radius: 50px; font-size: 13px; font-weight: 600; z-index: 999;
  opacity: 0; pointer-events: none; transition: all 0.3s ease; white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ── INPUT STYLES ── */
.inp-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--sage); margin-bottom: 6px; display: block; }
.inp {
  width: 100%; padding: 12px 14px; border-radius: var(--radius-sm);
  border: 1.5px solid rgba(16,6,0,0.12); background: var(--white);
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; color: var(--forest);
  transition: all 0.2s; outline: none;
}
.inp:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }

/* ── BOOKINGS LAYOUT ── */
.bookings-layout { padding: 80px 0 80px; }
.bookings-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 28px; flex-wrap: wrap; gap: 16px;
}
.section-label { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--sage); margin-bottom: 8px; }
.section-title { font-family: 'Playfair Display', serif; font-size: clamp(28px, 5vw, 36px); font-weight: 700; color: var(--forest); }
.bookings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .bookings-grid { grid-template-columns: 1fr; } }

/* ── PAGE TABS ── */
.page-tabs { display: flex; border-bottom: 2px solid rgba(16,6,0,0.08); margin-bottom: 28px; }
.page-tab {
  padding: 12px 24px; font-size: 14px; font-weight: 600; color: var(--stone);
  cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: .2s;
  display: flex; align-items: center; gap: 8px;
}
.page-tab svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 2; fill: none; }
.page-tab.active { color: var(--forest); border-color: var(--forest); }

/* ── BOOKING CARD ── */
.booking-card {
  background: var(--white); border-radius: 18px;
  border: 1px solid rgba(16,6,0,0.08); padding: 20px;
  box-shadow: var(--shadow); transition: transform .2s, box-shadow .2s; position: relative;
}
.booking-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.booking-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; gap: 10px; }
.booking-card-title { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: var(--forest); }
.booking-card-date { font-size: 12px; color: var(--stone); margin-top: 4px; display: flex; align-items: center; gap: 5px; }
.booking-card-date svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }
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
.booking-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 14px; }
.fee-total { font-size: 16px; font-weight: 700; color: var(--forest); margin-top: 4px; }
.countdown-timer {
  font-size: 11px; color: var(--danger); margin-top: 8px; padding: 7px 12px;
  background: rgba(192,57,43,0.07); border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;
}
.countdown-timer svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }
.nudge-count { font-size: 10px; color: var(--stone); margin-left: 8px; display: inline-flex; align-items: center; gap: 4px; }
.joined-badge {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(46,125,50,0.1); color: #2e7d32; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.8px; padding: 3px 8px; border-radius: 20px;
  border: 1px solid rgba(46,125,50,0.2); margin-left: 6px;
}

/* ── FLOW STEPS ── */
.flow-steps { display: flex; align-items: center; padding: 16px 28px 0; }
.flow-step { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 700; color: var(--stone); font-family: 'DM Mono', monospace; }
.flow-step.active { color: var(--forest); }
.flow-step.done { color: var(--sage); }
.flow-step .sn {
  width: 24px; height: 24px; border-radius: 50%;
  background: rgba(16,6,0,0.08); display: flex; align-items: center; justify-content: center; font-size: 10px;
}
.flow-step.active .sn { background: var(--forest); color: var(--cream); }
.flow-step.done .sn { background: var(--sage); color: var(--cream); }
.flow-line { flex: 1; height: 1px; background: rgba(16,6,0,0.1); margin: 0 8px; min-width: 16px; }
@media (max-width: 480px) { .flow-step span { display: none; } }

/* ── STEP 0: MODE CHOICE ── */
.mode-choice-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 4px 0; }
.mode-choice-card {
  display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px;
  padding: 28px 20px 24px; border-radius: 16px; border: 2px solid transparent;
  background: var(--sky); cursor: pointer; transition: all 0.22s ease;
}
.mode-choice-card:hover { border-color: var(--forest); background: var(--cream); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16,6,0,0.08); }
.mode-choice-card.selected { border-color: var(--forest); background: var(--cream); box-shadow: 0 0 0 3px rgba(16,6,0,0.06); }
.mode-choice-icon { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mode-choice-icon.join-icon { background: rgba(46,125,50,0.12); }
.mode-choice-icon.book-icon { background: rgba(201,168,76,0.15); }
.mode-choice-icon svg { width: 26px; height: 26px; stroke-width: 1.8; fill: none; }
.mode-choice-icon.join-icon svg { stroke: #2e7d32; }
.mode-choice-icon.book-icon svg { stroke: var(--gold); }
.mode-choice-title { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700; color: var(--forest); }
.mode-choice-desc { font-size: 12px; color: var(--stone); line-height: 1.55; }
@media (max-width: 480px) { .mode-choice-wrap { grid-template-columns: 1fr; } }

/* ── HIKING ID LOOKUP ── */
.hike-id-input-row { display: flex; gap: 10px; margin: 12px 0 6px; }
.hike-id-input-row input {
  flex: 1; padding: 12px 16px; border: 1.5px solid rgba(16,6,0,0.12);
  border-radius: var(--radius-sm); font-family: 'DM Mono', monospace; font-size: 15px;
  letter-spacing: 2px; color: var(--forest); background: var(--white);
  outline: none; transition: border-color 0.2s; text-transform: uppercase;
}
.hike-id-input-row input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
.hike-id-result { margin-top: 14px; }
.hike-id-found-card {
  background: var(--sky); border-radius: var(--radius-sm); border-left: 4px solid #2e7d32; padding: 14px 16px;
}
.hif-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #2e7d32; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.hif-label svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.hif-row { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px solid rgba(16,6,0,0.05); }
.hif-row:last-child { border-bottom: none; }
.hif-key { color: var(--stone); }
.hif-val { font-weight: 700; color: var(--forest); }
.hike-id-not-found {
  background: #fce4ec; border-radius: var(--radius-sm); border-left: 4px solid #c62828;
  padding: 12px 14px; font-size: 13px; color: #c62828; font-weight: 600;
  display: flex; align-items: center; gap: 8px;
}
.hike-id-not-found svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ── MOUNTAIN SELECT ── */
.mtn-select-card {
  display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px;
  border: 2px solid transparent; background: var(--sky); cursor: pointer; transition: .2s; margin-bottom: 10px;
}
.mtn-select-card:hover, .mtn-select-card.selected { border-color: var(--forest); background: var(--cream); }
.mtn-select-img { width: 56px; height: 56px; border-radius: 10px; background-size: cover; background-position: center; flex-shrink: 0; }
.mtn-select-name { font-weight: 700; font-size: 14px; color: var(--forest); }
.mtn-select-meta { font-size: 12px; color: var(--stone); margin-top: 3px; }

/* ── TYPE TOGGLE ── */
.type-toggle-group { margin-bottom: 18px; }
.type-toggle { display: flex; gap: 8px; background: rgba(16,6,0,0.04); padding: 6px; border-radius: 12px; }
.type-btn {
  flex: 1; padding: 11px 12px; text-align: center; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all 0.2s; color: var(--stone); background: transparent;
  border-radius: 9px; display: flex; align-items: center; justify-content: center; gap: 6px;
  border: none; font-family: 'Plus Jakarta Sans', sans-serif;
}
.type-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 1.8; flex-shrink: 0; }
.type-btn.active { background: var(--forest); color: var(--cream); box-shadow: 0 2px 8px rgba(26,46,26,0.2); }
.type-btn:hover:not(.active) { background: rgba(16,6,0,0.06); color: var(--forest); }

/* ── HIKERS MANAGEMENT ── */
.hikers-section { margin: 14px 0; padding: 16px; background: var(--sky); border-radius: 12px; }
.hiker-input-row { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.hiker-input-row input { flex: 1; min-width: 120px; padding: 10px 12px; border: 1.5px solid rgba(16,6,0,0.1); border-radius: var(--radius-sm); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--forest); outline: none; transition: .2s; background: var(--white); }
.hiker-input-row input:focus { border-color: var(--gold); }
.hiker-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.hiker-list-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 12px; background: var(--white); border-radius: 9px; gap: 8px;
  border: 1px solid rgba(16,6,0,0.06);
}
.hiker-list-item .hiker-info { display: flex; align-items: center; gap: 8px; flex: 1; }
.hiker-list-item .hiker-avatar {
  width: 30px; height: 30px; border-radius: 50%; background: var(--forest); color: var(--cream);
  font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.hiker-list-item.booker { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); }
.hiker-list-item.booker .hiker-avatar { background: var(--gold); }
.hiker-list-item .hiker-name { font-weight: 600; font-size: 13px; color: var(--forest); }
.hiker-list-item .hiker-tag { font-size: 10px; color: var(--stone); }
.hiker-actions { display: flex; gap: 4px; }
.hiker-action-btn {
  width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: .15s;
  background: rgba(16,6,0,0.05);
}
.hiker-action-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }
.hiker-action-btn.edit { color: var(--sage); }
.hiker-action-btn.edit:hover { background: rgba(92,122,92,0.15); }
.hiker-action-btn.remove { color: var(--danger); }
.hiker-action-btn.remove:hover { background: rgba(192,57,43,0.12); }
.hiker-count-display { font-size: 11px; color: var(--stone); margin-top: 8px; text-align: center; font-weight: 600; }

/* ── IMPROVED DATE/TIME PICKER ── */
.datetime-section { margin-bottom: 18px; }
.datetime-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 480px) { .datetime-row { grid-template-columns: 1fr; } }
.datetime-field { display: flex; flex-direction: column; gap: 6px; }

/* Custom styled date/time wrapper */
.custom-date-wrapper, .custom-time-wrapper {
  position: relative; overflow: hidden; border-radius: var(--radius-sm);
  border: 1.5px solid rgba(16,6,0,0.12); background: var(--white);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.custom-date-wrapper:focus-within, .custom-time-wrapper:focus-within {
  border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
}
.dt-icon-row {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; pointer-events: none; z-index: 1;
}
.dt-icon-row svg { width: 16px; height: 16px; stroke: var(--sage); fill: none; stroke-width: 1.8; }
.custom-date-wrapper input[type="date"],
.custom-time-wrapper input[type="time"] {
  width: 100%; padding: 12px 14px 12px 40px; border: none; background: transparent;
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; color: var(--forest);
  outline: none; cursor: pointer; appearance: none; -webkit-appearance: none;
}
.custom-date-wrapper input[type="date"]::-webkit-calendar-picker-indicator,
.custom-time-wrapper input[type="time"]::-webkit-calendar-picker-indicator {
  position: absolute; right: 10px; opacity: 0.5; cursor: pointer;
  width: 20px; height: 20px;
}

/* Date display preview */
.date-preview {
  background: linear-gradient(135deg, var(--sky), rgba(201,168,76,0.08));
  border-radius: 10px; padding: 12px 14px; margin-top: 8px;
  border: 1px solid rgba(201,168,76,0.2); display: none;
}
.date-preview.visible { display: flex; align-items: center; gap: 12px; }
.date-preview-icon {
  width: 44px; height: 44px; border-radius: 10px; background: var(--forest);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.date-preview-icon .month { font-size: 8px; font-weight: 700; color: rgba(250,248,243,0.7); text-transform: uppercase; letter-spacing: 1px; }
.date-preview-icon .day { font-size: 18px; font-weight: 700; color: var(--cream); line-height: 1; }
.date-preview-info { flex: 1; }
.date-preview-info .weekday { font-size: 13px; font-weight: 700; color: var(--forest); }
.date-preview-info .fulldate { font-size: 11px; color: var(--stone); margin-top: 2px; }

/* Time display preview */
.time-display {
  display: flex; align-items: center; gap: 8px;
  background: var(--sky); border-radius: 8px; padding: 8px 12px; margin-top: 8px;
  border: 1px dashed rgba(16,6,0,0.1); display: none;
}
.time-display.visible { display: flex; }
.time-display svg { width: 14px; height: 14px; stroke: var(--sage); fill: none; stroke-width: 2; }
.time-display span { font-size: 13px; font-weight: 600; color: var(--forest); font-family: 'DM Mono', monospace; }
.time-period { font-size: 10px; color: var(--stone); margin-left: 2px; font-weight: 600; }

/* ── GUIDE SELECT ── */
.guide-select-card { display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px; border: 2px solid transparent; background: var(--sky); cursor: pointer; transition: .2s; margin-bottom: 10px; }
.guide-select-card:hover, .guide-select-card.selected { border-color: var(--forest); background: var(--cream); }
.guide-select-info { flex: 1; }
.guide-select-name { font-weight: 700; font-size: 14px; color: var(--forest); }
.guide-select-meta { font-size: 12px; color: var(--stone); margin-top: 2px; }
.guide-avail { font-size: 11px; font-weight: 600; color: #2e7d32; margin-top: 3px; display: flex; align-items: center; gap: 4px; }
.guide-avail svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ── FEE ROWS ── */
.fee-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid rgba(16,6,0,0.06); font-size: 13px; color: var(--forest); }
.fee-row:last-child { border-bottom: none; }
.fee-row.total { font-weight: 700; font-size: 16px; padding-top: 12px; }
.fee-row svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; margin-right: 5px; }

/* ── WARNING / INFO CARDS ── */
.warning-card { background: linear-gradient(135deg, #fff8e1, #fff3cd); border: 1px solid rgba(245,167,0,0.3); border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; }
.warning-card h4 { font-size: 13px; font-weight: 700; color: var(--bark); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.warning-card h4 svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; }
.warning-card p { font-size: 12px; color: #5d4037; line-height: 1.7; }
.info-note { background: var(--sky); border-radius: 10px; padding: 12px; font-size: 12px; color: var(--stone); margin-bottom: 12px; display: flex; align-items: flex-start; gap: 8px; }
.info-note svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; margin-top: 1px; }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 48px 20px; grid-column: 1/-1; }
.empty-state svg { width: 48px; height: 48px; stroke: var(--stone); fill: none; stroke-width: 1.5; margin-bottom: 16px; }
.empty-state p { color: var(--stone); margin-bottom: 16px; }

/* ── SUMMARY BOX ── */
.summary-box { background: var(--sky); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.summary-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid rgba(16,6,0,0.06); font-size: 13px; }
.summary-row:last-child { border-bottom: none; }
.summary-row svg { width: 15px; height: 15px; stroke: var(--sage); fill: none; stroke-width: 1.8; flex-shrink: 0; }
.summary-row .sr-label { color: var(--stone); min-width: 90px; }
.summary-row .sr-val { font-weight: 600; color: var(--forest); }

/* ── BOOKING ID COPY ── */
.booking-id-row {
  display: flex; align-items: center; gap: 10px;
  background: linear-gradient(135deg, rgba(26,46,26,0.05), rgba(201,168,76,0.08));
  border: 1.5px solid rgba(201,168,76,0.3); border-radius: 10px; padding: 10px 14px;
  margin: 12px 0;
}
.booking-id-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--stone); }
.booking-id-value { font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700; color: var(--forest); flex: 1; }
.copy-btn {
  display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 30px;
  background: var(--forest); color: var(--cream); font-size: 11px; font-weight: 700;
  border: none; cursor: pointer; transition: all 0.18s; letter-spacing: 0.3px;
}
.copy-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.copy-btn:hover { background: #243824; transform: scale(1.04); }
.copy-btn.copied { background: #2e7d32; }

/* ── EDIT HIKER INLINE ── */
.hiker-edit-inline { display: none; gap: 6px; margin-top: 6px; }
.hiker-edit-inline.visible { display: flex; }
.hiker-edit-inline input { flex: 1; padding: 6px 10px; border: 1.5px solid var(--gold); border-radius: 7px; font-size: 12px; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; }
.hiker-edit-inline .save-edit { padding: 6px 12px; background: var(--forest); color: var(--cream); border: none; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; }

/* ── REPLACE GUIDE MODAL ── */
.guide-replace-option { display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px; border: 2px solid transparent; background: var(--sky); cursor: pointer; transition: .2s; margin-bottom: 10px; }
.guide-replace-option:hover, .guide-replace-option.selected { border-color: var(--forest); background: var(--cream); }

/* ── DETAIL VIEW (joined booking) ── */
.detail-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sage); margin: 16px 0 8px; }
.detail-hikers-list { display: flex; flex-wrap: wrap; gap: 8px; }
.detail-hiker-chip {
  display: flex; align-items: center; gap: 7px; padding: 6px 12px;
  background: var(--sky); border-radius: 30px; font-size: 12px; font-weight: 600; color: var(--forest);
  border: 1px solid rgba(16,6,0,0.07);
}
.detail-hiker-chip .dh-av {
  width: 22px; height: 22px; border-radius: 50%; background: var(--forest); color: var(--cream);
  font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center;
}
.advisory-box {
  background: linear-gradient(135deg, #fffde7, #fff9c4); border-radius: 12px;
  border: 1px solid rgba(245,167,0,0.25); padding: 14px 16px; margin-top: 8px;
}
.advisory-item { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: #5d4037; padding: 5px 0; }
.advisory-item svg { width: 13px; height: 13px; stroke: var(--gold); fill: none; stroke-width: 2; flex-shrink: 0; margin-top: 1px; }

/* ── EDIT BOOKING FORM ── */
.edit-section { margin-bottom: 20px; }
.edit-section .section-divider { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sage); padding: 10px 0 6px; border-top: 1px solid rgba(16,6,0,0.06); margin-top: 14px; }

@media (max-width: 768px) {
  .bookings-layout { padding: 80px 0 100px; }
  .bookings-header { flex-direction: column; align-items: flex-start; }
}
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
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore
    </a>
    <a href="bookings.php" class="tab-link active">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings
    </a>
    <a href="quiz.php" class="tab-link">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz
    </a>
    <a href="messages.php" class="tab-link">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages
    </a>
  </div>
  <a href="hikerProfile.php" class="user-btn">J</a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span></a>
    <a href="bookings.php" class="mob-nav-item active"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span></a>
    <a href="quiz.php" class="mob-nav-item quiz-center"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></a>
    <a href="messages.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="profile.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
  </div>
</nav>

<!-- PAGE -->
<div class="bookings-layout">
  <div class="container">
    <div class="bookings-header">
      <div>
        <div class="section-label">Your Adventures</div>
        <div class="section-title">Bookings</div>
      </div>
      <button class="btn-glass-primary" onclick="openBookingFlow()">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Book a Hike
      </button>
    </div>

    <div class="page-tabs">
      <div class="page-tab active" onclick="switchTab('current',this)">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>Current
      </div>
      <div class="page-tab" onclick="switchTab('history',this)">
        <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/></svg>History
      </div>
    </div>

    <div id="tab-current"><div class="bookings-grid" id="currentBookings"></div></div>
    <div id="tab-history" style="display:none;"><div class="bookings-grid" id="historyBookings"></div></div>
  </div>
</div>

<!-- BOOKING FLOW MODAL -->
<div class="modal-bg" id="bookingModal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-hdr">
      <div>
        <div class="modal-title" id="flowTitle">Book a Hike</div>
        <div style="font-size:11px;color:var(--stone);margin-top:2px;" id="flowSubtitle">How would you like to proceed?</div>
      </div>
      <button class="modal-close" onclick="closeBookingModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="flow-steps" id="flowSteps" style="display:none;">
      <div class="flow-step active" id="fs1"><div class="sn">1</div><span>Mountain</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs2"><div class="sn">2</div><span>Details</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs3"><div class="sn">3</div><span>Guide</span></div>
      <div class="flow-line"></div>
      <div class="flow-step" id="fs4"><div class="sn">4</div><span>Summary</span></div>
    </div>
    <div class="modal-body" id="flowBody"></div>
    <div style="display:flex;gap:10px;padding:0 28px 24px;" id="flowFooter"></div>
  </div>
</div>

<!-- SUCCESS MODAL -->
<div class="modal-bg" id="successModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-body">
      <div style="text-align:center;padding:12px 0 0;">
        <div style="width:72px;height:72px;background:rgba(46,125,50,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h2 id="successTitle" style="font-family:'Playfair Display',serif;font-size:24px;color:var(--forest);margin-bottom:8px;">Booking Created!</h2>
        <p id="successDesc" style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:16px;"></p>

        <!-- Booking ID with copy button -->
        <div id="successBookingIdRow" class="booking-id-row" style="text-align:left;">
          <div style="flex:1;">
            <div class="booking-id-label">Booking ID</div>
            <div class="booking-id-value" id="successBookingId">—</div>
          </div>
          <button class="copy-btn" id="copyIdBtn" onclick="copyBookingId()">
            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Copy
          </button>
        </div>

        <div class="summary-box" id="successSummary" style="text-align:left;"></div>
        <div id="successPolicy" class="warning-card" style="text-align:left;">
          <h4>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            What's next?
          </h4>
          <p>• Booking confirmed when guide accepts<br>• No guide response within 5 hours → replace or cancel<br>• Nudge guide every 20 min (max 10 nudges)</p>
        </div>
        <button class="btn btn-primary btn-full" onclick="closeSuccess()" style="margin-top:4px;">View My Bookings</button>
      </div>
    </div>
  </div>
</div>

<!-- JOIN SUCCESS MODAL -->
<div class="modal-bg" id="joinSuccessModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-body">
      <div style="text-align:center;padding:12px 0 0;">
        <div style="width:72px;height:72px;background:rgba(46,125,50,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:24px;color:var(--forest);margin-bottom:8px;">You're In!</h2>
        <p style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:16px;">You've successfully joined this hike. It's now on your bookings page.</p>
        <div class="summary-box" id="joinSuccessSummary" style="text-align:left;"></div>
        <button class="btn btn-primary btn-full" onclick="closeJoinSuccess()">View My Bookings</button>
      </div>
    </div>
  </div>
</div>

<!-- VIEW JOINED HIKE MODAL -->
<div class="modal-bg" id="viewJoinedModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-hdr">
      <div>
        <div class="modal-title" id="vjTitle">Hike Details</div>
        <div style="font-size:11px;color:var(--stone);margin-top:2px;">Read-only — joined booking</div>
      </div>
      <button class="modal-close" onclick="document.getElementById('viewJoinedModal').classList.remove('open')">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" id="vjBody"></div>
    <div style="padding:0 28px 24px;">
      <button class="btn btn-outline btn-full" onclick="document.getElementById('viewJoinedModal').classList.remove('open')">Close</button>
    </div>
  </div>
</div>

<!-- REPLACE GUIDE MODAL -->
<div class="modal-bg" id="replaceGuideModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-hdr">
      <div class="modal-title">Replace Tour Guide</div>
      <button class="modal-close" onclick="closeReplaceModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body"><div id="replaceGuideList"></div></div>
    <div style="padding:0 28px 24px;display:flex;gap:10px;">
      <button class="btn btn-outline btn-full" onclick="closeReplaceModal()">Cancel</button>
      <button class="btn btn-primary btn-full" onclick="confirmReplaceGuide()">Replace Guide</button>
    </div>
  </div>
</div>

<!-- EDIT BOOKING MODAL -->
<div class="modal-bg" id="editBookingModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-hdr">
      <div class="modal-title">Edit Booking</div>
      <button class="modal-close" onclick="closeEditModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" id="editModalBody"></div>
    <div style="padding:0 28px 24px;display:flex;gap:10px;">
      <button class="btn btn-outline btn-full" onclick="closeEditModal()">Cancel</button>
      <button class="btn btn-primary btn-full" onclick="saveBookingEdit()">Save Changes</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── DATA ──
const mountains = [
  {id:1,name:"Mt. Batulao",location:"Nasugbu, Batangas",difficulty:"moderate",image:"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&q=60",fees:{regFee:150,envFee:120,guideDay:900,guideON:1600,campFee:50,parkDay:100,parkON:150}},
  {id:2,name:"Mt. Talamitam",location:"Nasugbu, Batangas",difficulty:"easy",image:"https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=200&q=60",fees:{regFee:100,envFee:0,guideDay:700,guideON:1100,campFee:0,parkDay:80,parkON:80}},
  {id:3,name:"Mt. Apayang",location:"Batangas",difficulty:"hard",image:"https://images.unsplash.com/photo-1519681393784-d120267933ba?w=200&q=60",fees:{regFee:200,envFee:0,guideDay:1200,guideON:2000,campFee:100,parkDay:0,parkON:0}},
  {id:4,name:"Mt. Lantik",location:"Alfonso, Cavite",difficulty:"moderate",image:"https://images.unsplash.com/photo-1501854140801-50d01698950b?w=200&q=60",fees:{regFee:130,envFee:100,guideDay:900,guideON:1500,campFee:0,parkDay:100,parkON:100}}
];
const guides = [
  {id:1,name:"John Dela Cruz",initials:"JD",mountains:[1,3],rating:4.9,available:"Mon–Sat",phone:"+63 912 345 6789"},
  {id:2,name:"Maya Reyes",initials:"MR",mountains:[1,2],rating:4.8,available:"Daily",phone:"+63 923 456 7890"},
  {id:3,name:"Rico Cabanlit",initials:"RC",mountains:[4],rating:5.0,available:"Daily",phone:"+63 934 567 8901"},
  {id:4,name:"Elena Llorente",initials:"EL",mountains:[1],rating:4.7,available:"Wed–Sun",phone:"+63 945 678 9012"}
];

const DUMMY_BOOKINGS = [
  {id:"BK001",mountainId:1,mountain:"Mt. Batulao",date:"2025-06-10",time:"06:00",type:"day",status:"pending",guideId:1,guideName:"John Dela Cruz",guideInitials:"JD",pax:3,hikers:["Carlo Mendoza","Ana Santos","Liza Reyes"],totalFee:3870,createdAt:Date.now()-7200000,nudges:2,lastNudge:Date.now()-1800000,camping:false,notes:"Bring extra water — dry season trail."},
  {id:"BK002",mountainId:2,mountain:"Mt. Talamitam",date:"2025-06-15",time:"14:00",type:"late",status:"confirmed",guideId:2,guideName:"Maya Reyes",guideInitials:"MR",pax:2,hikers:["Ben Torres","Clara Lim"],totalFee:1500,createdAt:Date.now()-86400000,nudges:0,lastNudge:0,camping:false,notes:"Perfect sunset hike."},
  {id:"BK003",mountainId:4,mountain:"Mt. Lantik",date:"2025-06-20",time:"05:30",type:"overnight",status:"confirmed",guideId:3,guideName:"Rico Cabanlit",guideInitials:"RC",pax:4,hikers:["Diana Cruz","Nico Bautista","Sam Villanueva","Pat Ocampo"],totalFee:5200,createdAt:Date.now()-172800000,nudges:0,lastNudge:0,camping:true,notes:"Camp at summit ridge. Bring -5°C sleeping bag."},
  {id:"BK004",mountainId:1,mountain:"Mt. Batulao",date:"2025-06-25",time:"07:00",type:"day",status:"pending",guideId:4,guideName:"Elena Llorente",guideInitials:"EL",pax:1,hikers:["Marco Reyes"],totalFee:1170,createdAt:Date.now()-300000,nudges:0,lastNudge:0,camping:false,notes:"Solo hike, trail A."},
  {id:"BK005",mountainId:3,mountain:"Mt. Apayang",date:"2025-07-01",time:"05:00",type:"overnight",status:"confirmed",guideId:1,guideName:"John Dela Cruz",guideInitials:"JD",pax:5,hikers:["Rica Santos","Jon Cruz","Mel Aquino","Toni Blas","Kim Reyes"],totalFee:8200,createdAt:Date.now()-259200000,nudges:0,lastNudge:0,camping:true,notes:"Advanced trail. Physical fitness required."},
  {id:"BK006",mountainId:2,mountain:"Mt. Talamitam",date:"2025-07-05",time:"15:00",type:"late",status:"pending",guideId:2,guideName:"Maya Reyes",guideInitials:"MR",pax:2,hikers:["Luis Garcia","Mia Torres"],totalFee:1500,createdAt:Date.now()-600000,nudges:1,lastNudge:Date.now()-2400000,camping:false,notes:"Sunset view hike."},
  {id:"BK007",mountainId:4,mountain:"Mt. Lantik",date:"2025-07-10",time:"06:00",type:"day",status:"confirmed",guideId:3,guideName:"Rico Cabanlit",guideInitials:"RC",pax:6,hikers:["Grace Lim","Chris Tan","Bea Sy","Joel Gomez","Leah Flores","Ray Perez"],totalFee:5980,createdAt:Date.now()-345600000,nudges:0,lastNudge:0,camping:false,notes:"Group of 6, bring packed lunch."},
  {id:"BK008",mountainId:1,mountain:"Mt. Batulao",date:"2025-04-01",time:"06:00",type:"day",status:"completed",guideId:1,guideName:"John Dela Cruz",guideInitials:"JD",pax:2,hikers:["Ana Cruz","Bob Reyes"],totalFee:2820,createdAt:Date.now()-2592000000,nudges:0,lastNudge:0,camping:false,notes:""},
  {id:"BK009",mountainId:2,mountain:"Mt. Talamitam",date:"2025-03-15",time:"13:00",type:"late",status:"cancelled",guideId:2,guideName:"Maya Reyes",guideInitials:"MR",pax:1,hikers:["Tony Sta Ana"],totalFee:800,createdAt:Date.now()-3456000000,nudges:0,lastNudge:0,camping:false,notes:""},
  {id:"BK010",mountainId:3,mountain:"Mt. Apayang",date:"2025-07-20",time:"04:00",type:"overnight",status:"pending",guideId:1,guideName:"John Dela Cruz",guideInitials:"JD",pax:3,hikers:["Nadia Villanueva","Mark Ocampo","Sean Ong"],totalFee:6600,createdAt:Date.now()-180000,nudges:0,lastNudge:0,camping:true,notes:"Technical trail with ropes."}
];

let bookings = [];
let nextId = 11;

function loadBookings() {
  const saved = localStorage.getItem('lakbay_bk_v3');
  if(saved) {
    bookings = JSON.parse(saved);
    const ids = bookings.map(b => parseInt((b.id||'').replace('BK',''))||0);
    nextId = Math.max(11, ...ids) + 1;
  } else {
    bookings = JSON.parse(JSON.stringify(DUMMY_BOOKINGS));
    // Add user's own bookings
    bookings.push({id:"BK003",mountainId:4,mountain:"Mt. Lantik",date:"2025-05-18",time:"10:00",type:"day",status:"completed",guideId:3,guideName:"Rico Cabanlit",guideInitials:"RC",pax:3,hikers:["Jamie Rivera","John Doe","Jane Smith"],totalFee:2660,createdAt:Date.now()-86400000,nudges:0,lastNudge:0,camping:false,notes:""});
    saveBookings();
  }
}
function saveBookings() { localStorage.setItem('lakbay_bk_v3', JSON.stringify(bookings)); }
function genId() { return "BK" + String(nextId++).padStart(3,'0'); }

// ── FLOW STATE ──
let flowState = {};
let currentStep = 0;
let flowMode = null;
let foundHike = null;
let lastCreatedId = '';

function openBookingFlow() {
  flowState = {step:1,mtn:null,type:'day',pax:1,solo:true,hikers:["Jamie Rivera"],bookerName:"Jamie Rivera",guide:null,date:'',time:'',camping:false};
  currentStep = 0; flowMode = null; foundHike = null;
  document.getElementById('flowSteps').style.display = 'none';
  document.getElementById('bookingModal').classList.add('open');
  renderStep(0);
}
function closeBookingModal() { document.getElementById('bookingModal').classList.remove('open'); }

function updateStepIndicators(n) {
  const el = document.getElementById('flowSteps');
  if(n === 0 || n === 'join') { el.style.display='none'; return; }
  el.style.display = 'flex';
  for(let i=1;i<=4;i++){
    const s=document.getElementById('fs'+i);
    if(!s) continue;
    s.className='flow-step';
    if(i<n) s.classList.add('done');
    else if(i===n) s.classList.add('active');
  }
}

function renderStep(n) {
  currentStep = n;
  updateStepIndicators(n);
  const body = document.getElementById('flowBody');
  const footer = document.getElementById('flowFooter');

  if(n === 0) {
    document.getElementById('flowTitle').textContent = "Let's get started";
    document.getElementById('flowSubtitle').textContent = 'What would you like to do?';
    body.innerHTML = `
      <div class="mode-choice-wrap">
        <div class="mode-choice-card" id="modeJoin" onclick="selectMode('join')">
          <div class="mode-choice-icon join-icon">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/></svg>
          </div>
          <div class="mode-choice-title">I already have a hike</div>
          <div class="mode-choice-desc">Enter a Booking ID to join an existing hike and track it here.</div>
        </div>
        <div class="mode-choice-card" id="modeBook" onclick="selectMode('book')">
          <div class="mode-choice-icon book-icon">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          </div>
          <div class="mode-choice-title">Book a new hike</div>
          <div class="mode-choice-desc">Choose a mountain, pick a guide, and set your adventure date.</div>
        </div>
      </div>`;
    footer.innerHTML = `<button class="btn btn-outline btn-full" onclick="closeBookingModal()">Cancel</button><button class="btn btn-primary btn-full" id="modeNextBtn" onclick="proceedFromMode()" disabled>Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n === 'join') {
    document.getElementById('flowTitle').textContent = 'Enter Booking ID';
    document.getElementById('flowSubtitle').textContent = 'Join an existing hike';
    body.innerHTML = `
      <div class="info-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Ask your hike organizer for the Booking ID (e.g. <strong>BK001</strong> through <strong>BK010</strong>). Test with any of those!</span>
      </div>
      <div class="hike-id-input-row">
        <input type="text" id="hikeIdInput" placeholder="e.g. BK001" maxlength="10" oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')lookupHikeId()">
        <button class="btn btn-primary" onclick="lookupHikeId()">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          Look Up
        </button>
      </div>
      <div id="hikeIdResult"></div>`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(0)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" id="joinConfirmBtn" onclick="confirmJoinHike()" disabled>Join This Hike
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n===1) {
    document.getElementById('flowTitle').textContent = 'Book a Hike';
    document.getElementById('flowSubtitle').textContent = 'Choose your mountain';
    body.innerHTML = mountains.map(m=>`
      <div class="mtn-select-card" id="ms${m.id}" onclick="selectMtn(${m.id})">
        <div class="mtn-select-img" style="background-image:url('${m.image}')"></div>
        <div>
          <div class="mtn-select-name">${m.name}</div>
          <div class="mtn-select-meta">${m.location} · <span class="badge badge-${m.difficulty}">${m.difficulty}</span></div>
        </div>
      </div>`).join('');
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(0)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="nextStep()" id="nextBtn1" disabled>Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n===2) {
    document.getElementById('flowTitle').textContent = flowState.mtn?.name || '';
    document.getElementById('flowSubtitle').textContent = 'Hike details';
    const todayStr = new Date().toISOString().split('T')[0];
    body.innerHTML = `
      <div class="type-toggle-group">
        <div class="inp-label">Hike Type</div>
        <div class="type-toggle">
          <button type="button" class="type-btn ${flowState.type==='day'?'active':''}" onclick="setType('day')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            Day (12am–3pm)
          </button>
          <button type="button" class="type-btn ${flowState.type==='late'?'active':''}" onclick="setType('late')">
            <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            Late (4pm–12am)
          </button>
          <button type="button" class="type-btn ${flowState.type==='overnight'?'active':''}" onclick="setType('overnight')">
            <svg viewBox="0 0 24 24"><path d="M12 3v1M12 20v1M3 12h1M20 12h1"/><circle cx="12" cy="12" r="4"/><path d="M12 8a4 4 0 0 0 0 8"/></svg>
            Overnight
          </button>
        </div>
      </div>
      <div class="type-toggle-group">
        <div class="inp-label">Hiking Party</div>
        <div class="type-toggle">
          <button type="button" class="type-btn ${flowState.solo?'active':''}" onclick="setSolo(true)">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Solo
          </button>
          <button type="button" class="type-btn ${!flowState.solo?'active':''}" onclick="setSolo(false)">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Group
          </button>
        </div>
      </div>
      <div id="groupSection" style="display:${flowState.solo?'none':'block'};">
        <div class="hikers-section">
          <div class="inp-label">Add Hikers</div>
          <div class="hiker-input-row">
            <input type="text" id="hikerFirstName" placeholder="First name" maxlength="50">
            <input type="text" id="hikerLastName" placeholder="Last name" maxlength="50">
            <button class="btn btn-primary btn-sm" onclick="addHiker()">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add
            </button>
          </div>
          <div id="hikerListContainer" class="hiker-list"></div>
          <div class="hiker-count-display" id="hikerCount">${flowState.hikers.length}/12 hikers</div>
        </div>
      </div>
      ${flowState.type==='overnight'?`
      <div style="margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--sky);border-radius:10px;border:1.5px solid ${flowState.camping?'var(--forest)':'transparent'};">
          <input type="checkbox" id="campingCheck" ${flowState.camping?'checked':''} onchange="flowState.camping=this.checked;this.closest('label').style.borderColor=this.checked?'var(--forest)':'transparent'">
          <div>
            <div style="font-weight:600;font-size:13px;color:var(--forest);">Camping overnight</div>
            <div style="font-size:11px;color:var(--stone);">Additional ₱50/person camping fee</div>
          </div>
        </label>
      </div>`:''}
      <div class="datetime-section">
        <div class="inp-label" style="margin-bottom:10px;">Date &amp; Time</div>
        <div class="datetime-row">
          <div class="datetime-field">
            <div class="inp-label">Date</div>
            <div class="custom-date-wrapper">
              <div class="dt-icon-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
              <input type="date" id="hikeDate" value="${flowState.date}" min="${todayStr}" onchange="updateDatePreview(this.value)">
            </div>
            <div class="date-preview ${flowState.date?'visible':''}" id="datePreview"></div>
          </div>
          <div class="datetime-field">
            <div class="inp-label">Start Time</div>
            <div class="custom-time-wrapper">
              <div class="dt-icon-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
              <input type="time" id="hikeTime" value="${flowState.time}" onchange="updateTimePreview(this.value)">
            </div>
            <div class="time-display ${flowState.time?'visible':''}" id="timeDisplay"></div>
          </div>
        </div>
      </div>`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(1)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="nextStep()">Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;
    if(flowState.solo) { updateHikersUI(); }
    else { updateHikersUI(); }
    if(flowState.date) updateDatePreview(flowState.date);
    if(flowState.time) updateTimePreview(flowState.time);

  } else if(n===3) {
    document.getElementById('flowTitle').textContent = 'Choose a Guide';
    document.getElementById('flowSubtitle').textContent = 'A tour guide is required';
    const avail = guides.filter(g=>g.mountains.includes(flowState.mtn.id));
    body.innerHTML = `
      <div class="warning-card" style="margin-bottom:14px;">
        <h4><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Tour Guide Required</h4>
        <p>For safety, all hikes require a licensed local tour guide.</p>
      </div>
      ${avail.map(g=>`
        <div class="guide-select-card" id="gs${g.id}" onclick="selectGuide(${g.id})">
          <div class="guide-av-sm">${g.initials}</div>
          <div class="guide-select-info">
            <div class="guide-select-name">${g.name}</div>
            <div class="guide-select-meta">★ ${g.rating} · ${g.mountains.map(mid=>mountains.find(m=>m.id===mid)?.name).join(', ')}</div>
            <div class="guide-avail"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Available ${g.available}</div>
          </div>
        </div>`).join('')}`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(2)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="nextStep()" id="nextGuideBtn" disabled>Continue
      <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>`;

  } else if(n===4) {
    document.getElementById('flowTitle').textContent = 'Review & Confirm';
    document.getElementById('flowSubtitle').textContent = 'Booking summary';
    const m=flowState.mtn,f=m.fees,pax=flowState.hikers.length;
    const isON=flowState.type==='overnight';
    const guideF=isON?f.guideON:f.guideDay;
    const regTotal=f.regFee*pax, envTotal=f.envFee?f.envFee*pax:0;
    const campF=(isON&&flowState.camping&&f.campFee)?f.campFee*pax:0;
    const total=regTotal+envTotal+guideF+campF;
    const typeNames={day:'Day Hike (12am–3pm)',late:'Late Hike (4pm–12am)',overnight:'Overnight'};
    body.innerHTML = `
      <div style="background:var(--sky);border-radius:12px;padding:16px;margin-bottom:16px;">
        <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${m.name}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="sr-label">Date & Time</span><span class="sr-val">${flowState.date}${flowState.time?' at '+flowState.time:''}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeNames[flowState.type]}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Hikers (${pax})</span><span class="sr-val">${flowState.hikers.join(', ')}</span></div>
        <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${flowState.guide?.name}</span></div>
      </div>
      <div style="background:var(--white);border-radius:12px;border:1px solid rgba(16,6,0,0.08);padding:16px;margin-bottom:14px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--sage);margin-bottom:10px;">Fee Breakdown</div>
        <div class="fee-row"><span>Registration (₱${f.regFee} × ${pax})</span><span>₱${regTotal}</span></div>
        ${envTotal?`<div class="fee-row"><span>Environmental fee (₱${f.envFee} × ${pax})</span><span>₱${envTotal}</span></div>`:''}
        <div class="fee-row"><span>Guide fee (${isON?'overnight':'day'})</span><span>₱${guideF}</span></div>
        ${campF?`<div class="fee-row"><span>Camping fee (₱${f.campFee} × ${pax})</span><span>₱${campF}</span></div>`:''}
        <div class="fee-row total"><span>Total</span><span>₱${total.toLocaleString()}</span></div>
      </div>
      <div class="warning-card">
        <h4><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Payment Policy</h4>
        <p><strong>No payment required now.</strong> Booking stays PENDING until the guide accepts. Payment is arranged directly with your guide after the hike.</p>
      </div>`;
    footer.innerHTML = `<button class="btn btn-outline" onclick="renderStep(3)">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back
    </button><button class="btn btn-primary btn-full" onclick="createBooking()">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      Confirm Booking
    </button>`;
  }
}

// ── DATE/TIME PREVIEW ──
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function updateDatePreview(val) {
  const el = document.getElementById('datePreview');
  if(!el || !val) return;
  const d = new Date(val + 'T00:00:00');
  const month = MONTHS[d.getMonth()];
  const day = d.getDate();
  const weekday = DAYS[d.getDay()];
  const fulldate = `${month} ${day}, ${d.getFullYear()}`;
  el.className = 'date-preview visible';
  el.innerHTML = `
    <div class="date-preview-icon">
      <div class="month">${month}</div>
      <div class="day">${day}</div>
    </div>
    <div class="date-preview-info">
      <div class="weekday">${weekday}</div>
      <div class="fulldate">${fulldate}</div>
    </div>`;
}

function updateTimePreview(val) {
  const el = document.getElementById('timeDisplay');
  if(!el || !val) return;
  const [h, m] = val.split(':').map(Number);
  const period = h < 12 ? 'AM' : 'PM';
  const h12 = h === 0 ? 12 : h > 12 ? h - 12 : h;
  const fmt = `${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
  el.className = 'time-display visible';
  el.innerHTML = `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>${fmt} <span class="time-period">${period}</span></span>`;
}

// ── MODE SELECTION ──
function selectMode(mode) {
  flowMode = mode;
  document.getElementById('modeJoin').classList.toggle('selected', mode==='join');
  document.getElementById('modeBook').classList.toggle('selected', mode==='book');
  document.getElementById('modeNextBtn').disabled = false;
}
function proceedFromMode() {
  if(!flowMode) { showToast('Please choose an option'); return; }
  if(flowMode==='join') renderStep('join');
  else renderStep(1);
}

// ── HIKING ID LOOKUP ──
function lookupHikeId() {
  const input = (document.getElementById('hikeIdInput')?.value||'').trim().toUpperCase();
  const resultEl = document.getElementById('hikeIdResult');
  const confirmBtn = document.getElementById('joinConfirmBtn');
  if(!input) { showToast('Please enter a Booking ID'); return; }
  const hike = bookings.find(b => b.id === input);
  if(hike) {
    foundHike = hike;
    const typeMap = {day:'Day Hike',late:'Late Hike',overnight:'Overnight'};
    resultEl.innerHTML = `
      <div class="hike-id-found-card">
        <div class="hif-label"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Hike Found!</div>
        <div class="hif-row"><span class="hif-key">Booking ID</span><span class="hif-val">${hike.id}</span></div>
        <div class="hif-row"><span class="hif-key">Mountain</span><span class="hif-val">${hike.mountain}</span></div>
        <div class="hif-row"><span class="hif-key">Date</span><span class="hif-val">${hike.date}${hike.time?' at '+hike.time:''}</span></div>
        <div class="hif-row"><span class="hif-key">Type</span><span class="hif-val">${typeMap[hike.type]||hike.type}</span></div>
        <div class="hif-row"><span class="hif-key">Tour Guide</span><span class="hif-val">${hike.guideName}</span></div>
        <div class="hif-row"><span class="hif-key">Status</span><span class="hif-val">${hike.status.charAt(0).toUpperCase()+hike.status.slice(1)}</span></div>
        <div class="hif-row"><span class="hif-key">Current Hikers</span><span class="hif-val">${hike.hikers.join(', ')}</span></div>
      </div>`;
    confirmBtn.disabled = false;
  } else {
    foundHike = null;
    resultEl.innerHTML = `<div class="hike-id-not-found"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>No hike found with ID "<strong>${input}</strong>". Try BK001 to BK010.</div>`;
    confirmBtn.disabled = true;
  }
}

function confirmJoinHike() {
  if(!foundHike) { showToast('No hike selected'); return; }
  const joined = {
    id: genId(), mountainId: foundHike.mountainId, mountain: foundHike.mountain,
    date: foundHike.date, time: foundHike.time, type: foundHike.type,
    status: 'joined', guideId: foundHike.guideId, guideName: foundHike.guideName,
    guideInitials: foundHike.guideInitials, pax: foundHike.pax, hikers: [...foundHike.hikers],
    totalFee: 0, createdAt: Date.now(), nudges: 0, lastNudge: 0,
    camping: foundHike.camping||false, joinedFromId: foundHike.id,
    notes: foundHike.notes||''
  };
  bookings.unshift(joined);
  saveBookings();
  closeBookingModal();
  const typeMap = {day:'Day Hike',late:'Late Hike',overnight:'Overnight'};
  document.getElementById('joinSuccessSummary').innerHTML = `
    <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${foundHike.mountain}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><span class="sr-label">Date</span><span class="sr-val">${foundHike.date}${foundHike.time?' at '+foundHike.time:''}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeMap[foundHike.type]}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${foundHike.guideName}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/></svg><span class="sr-label">Original ID</span><span class="sr-val" style="font-family:'DM Mono',monospace;">${foundHike.id}</span></div>`;
  document.getElementById('joinSuccessModal').classList.add('open');
  renderBookings();
}

// ── NORMAL FLOW HELPERS ──
function selectMtn(id) {
  flowState.mtn = mountains.find(m=>m.id===id);
  document.querySelectorAll('.mtn-select-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('ms'+id)?.classList.add('selected');
  document.getElementById('nextBtn1').disabled = false;
}
function setType(t) {
  flowState.type = t;
  if(currentStep===2) { saveStep2Temp(); renderStep(2); }
}
function setSolo(s) {
  flowState.solo = s;
  if(s) { flowState.hikers = [flowState.bookerName]; flowState.pax = 1; }
  if(currentStep===2) { saveStep2Temp(); renderStep(2); }
}
function saveStep2Temp() {
  const d = document.getElementById('hikeDate')?.value;
  const t = document.getElementById('hikeTime')?.value;
  if(d) flowState.date = d;
  if(t) flowState.time = t;
  const cc = document.getElementById('campingCheck');
  if(cc) flowState.camping = cc.checked;
}
function updateHikersUI() {
  const c = document.getElementById('hikerListContainer'); if(!c) return;
  c.innerHTML = flowState.hikers.map((h,i)=>`
    <div class="hiker-list-item ${i===0?'booker':''}">
      <div class="hiker-info">
        <div class="hiker-avatar">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>
        <div>
          <div class="hiker-name">${h}</div>
          <div class="hiker-tag">${i===0?'Booking organizer':('Hiker '+(i+1))}</div>
        </div>
      </div>
      ${i>0?`<div class="hiker-actions">
        <button class="hiker-action-btn edit" onclick="editHikerInline(${i})" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg>
        </button>
        <button class="hiker-action-btn remove" onclick="removeHiker(${i})" title="Remove">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>`:''}
    </div>
    <div class="hiker-edit-inline" id="hikeredit${i}">
      <input type="text" id="hikerEditVal${i}" value="${h}" placeholder="Full name">
      <button class="save-edit" onclick="saveHikerEdit(${i})">Save</button>
      <button class="save-edit" style="background:var(--sky);color:var(--stone);" onclick="document.getElementById('hikeredit${i}').classList.remove('visible')">Cancel</button>
    </div>`).join('');
  const cd = document.getElementById('hikerCount');
  if(cd) cd.textContent = `${flowState.hikers.length}/12 hikers`;
}
function addHiker() {
  const fn = document.getElementById('hikerFirstName')?.value.trim();
  const ln = document.getElementById('hikerLastName')?.value.trim();
  if(!fn) { showToast('Please enter a first name'); return; }
  if(flowState.hikers.length>=12) { showToast('Maximum 12 hikers'); return; }
  flowState.hikers.push(`${fn} ${ln}`.trim());
  flowState.pax = flowState.hikers.length;
  document.getElementById('hikerFirstName').value='';
  document.getElementById('hikerLastName').value='';
  updateHikersUI();
}
function removeHiker(i) {
  if(i===0) return;
  flowState.hikers.splice(i,1);
  flowState.pax = flowState.hikers.length;
  updateHikersUI();
}
function editHikerInline(i) {
  document.querySelectorAll('.hiker-edit-inline').forEach(el=>el.classList.remove('visible'));
  const el = document.getElementById('hikeredit'+i);
  if(el) el.classList.add('visible');
}
function saveHikerEdit(i) {
  const val = document.getElementById('hikerEditVal'+i)?.value.trim();
  if(!val) { showToast('Name cannot be empty'); return; }
  flowState.hikers[i] = val;
  updateHikersUI();
}
function selectGuide(id) {
  flowState.guide = guides.find(g=>g.id===id);
  document.querySelectorAll('.guide-select-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('gs'+id)?.classList.add('selected');
  document.getElementById('nextGuideBtn').disabled = false;
}
function nextStep() {
  if(currentStep===1&&!flowState.mtn) { showToast('Please select a mountain'); return; }
  if(currentStep===2) {
    const d = document.getElementById('hikeDate')?.value;
    const t = document.getElementById('hikeTime')?.value;
    if(!d) { showToast('Please select a date'); return; }
    flowState.date=d; flowState.time=t;
    flowState.pax=flowState.hikers.length;
    const cc = document.getElementById('campingCheck');
    if(cc) flowState.camping=cc.checked;
    renderStep(3); return;
  }
  renderStep(currentStep+1);
}
function createBooking() {
  const m=flowState.mtn, f=m.fees, pax=flowState.hikers.length;
  const isON=flowState.type==='overnight';
  const total=f.regFee*pax+(f.envFee?f.envFee*pax:0)+(isON?f.guideON:f.guideDay)+((isON&&flowState.camping&&f.campFee)?f.campFee*pax:0);
  const nb = {
    id: genId(), mountainId:m.id, mountain:m.name, date:flowState.date,
    time:flowState.time||(flowState.type==='day'?'08:00':(flowState.type==='late'?'16:00':'12:00')),
    type:flowState.type, status:'pending', guideId:flowState.guide.id,
    guideName:flowState.guide.name, guideInitials:flowState.guide.initials,
    pax, hikers:[...flowState.hikers], totalFee:total, createdAt:Date.now(),
    nudges:0, lastNudge:0, camping:flowState.camping||false, notes:''
  };
  bookings.unshift(nb);
  saveBookings();
  lastCreatedId = nb.id;
  closeBookingModal();
  // Show success
  document.getElementById('successBookingId').textContent = nb.id;
  document.getElementById('successTitle').textContent = 'Booking Created!';
  document.getElementById('successDesc').textContent = 'Your booking is now PENDING. The guide will review your request.';
  document.getElementById('successPolicy').style.display = '';
  const typeNames={day:'Day Hike (12am–3pm)',late:'Late Hike (4pm–12am)',overnight:'Overnight'};
  document.getElementById('successSummary').innerHTML = `
    <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${nb.mountain}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><span class="sr-label">Date & Time</span><span class="sr-val">${nb.date} at ${nb.time}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeNames[nb.type]}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span class="sr-label">Hikers (${pax})</span><span class="sr-val">${nb.hikers.join(', ')}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${nb.guideName}</span></div>
    <div class="summary-row"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span class="sr-label">Total</span><span class="sr-val">₱${nb.totalFee.toLocaleString()}</span></div>`;
  document.getElementById('successModal').classList.add('open');
  renderBookings();
}

function copyBookingId() {
  const id = document.getElementById('successBookingId').textContent;
  navigator.clipboard.writeText(id).then(()=>{
    const btn = document.getElementById('copyIdBtn');
    btn.classList.add('copied');
    btn.innerHTML = `<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Copied!`;
    setTimeout(()=>{
      btn.classList.remove('copied');
      btn.innerHTML = `<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy`;
    },2000);
  }).catch(()=>showToast('ID: '+id));
}

function closeSuccess() { document.getElementById('successModal').classList.remove('open'); }
function closeJoinSuccess() { document.getElementById('joinSuccessModal').classList.remove('open'); }

// ── RENDER BOOKING CARDS ──
function renderBookings() {
  const cc = document.getElementById('currentBookings');
  const hc = document.getElementById('historyBookings');
  const now = Date.now(), FIVE_H = 18000000, TWENTY_M = 1200000, MAX_N = 10;
  const current = bookings.filter(b=>b.status!=='completed'&&b.status!=='cancelled');
  const history = bookings.filter(b=>b.status==='completed'||b.status==='cancelled');
  cc.innerHTML = current.length ? current.map(b=>bookingCard(b,now,FIVE_H,TWENTY_M,MAX_N)).join('') : emptyState();
  hc.innerHTML = history.length ? history.map(b=>bookingCard(b,now,FIVE_H,TWENTY_M,MAX_N)).join('') : `<div class="empty-state" style="grid-column:1/-1;"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg><p>No booking history yet</p></div>`;
}

function bookingCard(b, now, FIVE_H, TWENTY_M, MAX_N) {
  const ts=now-b.createdAt, canReplace=b.status==='pending'&&ts>=FIVE_H;
  const canNudge=b.status==='pending'&&ts<FIVE_H&&b.nudges<MAX_N&&(now-b.lastNudge)>=TWENTY_M;
  const rem=Math.max(0,FIVE_H-ts), hL=Math.floor(rem/3600000), mL=Math.floor((rem%3600000)/60000);
  const showTimer=b.status==='pending'&&ts<FIVE_H;
  const typeMap={day:'Day (12am–3pm)',late:'Late (4pm–12am)',overnight:'Overnight'};
  const joinedBadge = b.joinedFromId ? `<span class="joined-badge">Joined</span>` : '';
  const isJoined = !!b.joinedFromId;
  return `
    <div class="booking-card">
      <div class="booking-card-header">
        <div>
          <div class="booking-card-title">${b.mountain}${joinedBadge}</div>
          <div class="booking-card-date">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            ${b.date}${b.time?' · '+b.time:''} · ${typeMap[b.type]||b.type}
          </div>
        </div>
        <span class="badge status-${b.status}" style="white-space:nowrap;">${{pending:'Pending',confirmed:'Confirmed',completed:'Completed',cancelled:'Cancelled',joined:'Joined'}[b.status]||b.status}</span>
      </div>
      <div class="booking-card-guide">
        <div class="guide-av-sm">${b.guideInitials}</div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:13px;color:var(--forest);">${b.guideName}</div>
          <div style="font-size:11px;color:var(--stone);">
            ${b.pax} hiker(s) · #${b.id}${isJoined?' · via '+b.joinedFromId:''}
          </div>
        </div>
      </div>
      ${!isJoined?`<div class="fee-total">₱${b.totalFee.toLocaleString()} <span style="font-size:11px;font-weight:400;color:var(--stone);">pay after hike</span></div>`:`<div style="font-size:12px;color:var(--stone);margin-top:4px;">Fees managed by organizer</div>`}
      ${showTimer?`<div class="countdown-timer"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Guide response: ${hL}h ${mL}m remaining</div>`:''}
      ${b.nudges>0?`<div class="nudge-count"><svg viewBox="0 0 24 24" width="10" height="10" stroke="currentColor" fill="none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Nudges: ${b.nudges}/${MAX_N}</div>`:''}
      <div class="booking-actions">
        <a href="messages.php?guide=${b.guideId}" class="btn btn-outline btn-sm">
          <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Message Guide
        </a>
        ${isJoined?`
          <button class="btn btn-outline btn-sm" onclick="viewJoinedHike('${b.id}')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/></svg> View Details
          </button>
          <button class="btn btn-danger btn-sm" onclick="cancelBooking('${b.id}','leave')">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Leave Hike
          </button>
        `:`
          ${b.status==='pending'?`
            ${canNudge?`<button class="btn btn-outline btn-sm" onclick="nudgeGuide('${b.id}')"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Nudge (${b.nudges+1}/${MAX_N})</button>`:''}
            <button class="btn btn-outline btn-sm" onclick="editBooking('${b.id}')">
              <svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg> Edit
            </button>
            ${canReplace?`<button class="btn btn-outline btn-sm" onclick="openReplaceGuide('${b.id}')"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Replace Guide</button>`:''}
            <button class="btn btn-danger btn-sm" onclick="cancelBooking('${b.id}')">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Cancel
            </button>
          `:''}
        `}
      </div>
    </div>`;
}

// ── VIEW JOINED HIKE DETAILS ──
function viewJoinedHike(bookingId) {
  const b = bookings.find(x=>x.id===bookingId);
  if(!b) return;
  const orig = bookings.find(x=>x.id===b.joinedFromId);
  const typeMap={day:'Day Hike (12am–3pm)',late:'Late Hike (4pm–12am)',overnight:'Overnight'};
  document.getElementById('vjTitle').textContent = b.mountain;
  document.getElementById('vjBody').innerHTML = `
    <div class="summary-box">
      <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M4 10l8-6 8 6"/><rect x="4" y="10" width="16" height="12" rx="2"/></svg><span class="sr-label">Mountain</span><span class="sr-val">${b.mountain}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><span class="sr-label">Date & Time</span><span class="sr-val">${b.date}${b.time?' at '+b.time:''}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg><span class="sr-label">Type</span><span class="sr-val">${typeMap[b.type]}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sr-label">Guide</span><span class="sr-val">${b.guideName}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/></svg><span class="sr-label">Booking ID</span><span class="sr-val" style="font-family:'DM Mono',monospace;">${b.joinedFromId}</span></div>
      <div class="summary-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg><span class="sr-label">Status</span><span class="sr-val"><span class="badge status-${b.status}">${b.status}</span></span></div>
    </div>
    <div class="detail-section-label">Hiker List</div>
    <div class="detail-hikers-list">
      ${b.hikers.map(h=>`<div class="detail-hiker-chip"><div class="dh-av">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>${h}</div>`).join('')}
    </div>
    ${b.notes||orig?.notes?`
    <div class="detail-section-label">Notes & Advisories</div>
    <div class="advisory-box">
      ${(b.notes||orig?.notes||'').split('\n').filter(Boolean).map(line=>`
        <div class="advisory-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${line}</div>
      `).join('')}
    </div>`:''}
    <div class="info-note" style="margin-top:14px;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>You joined this hike. Fees and arrangements are handled by the booking organizer.</span>
    </div>`;
  document.getElementById('viewJoinedModal').classList.add('open');
}

// ── NUDGE / REPLACE / EDIT / CANCEL ──
function nudgeGuide(bookingId) {
  const b=bookings.find(x=>x.id===bookingId);
  if(!b||b.status!=='pending') return;
  const now=Date.now(), FIVE_H=18000000, TWENTY_M=1200000, MAX_N=10;
  if(now-b.createdAt>=FIVE_H){showToast('Response period expired — you can replace the guide.');renderBookings();return;}
  if(b.nudges>=MAX_N){showToast('Max nudges reached. Replace your guide.');return;}
  if(now-b.lastNudge<TWENTY_M){showToast(`Wait ${Math.ceil((TWENTY_M-(now-b.lastNudge))/60000)} more min before nudging.`);return;}
  b.nudges++; b.lastNudge=now; saveBookings(); renderBookings();
  showToast(`Nudge sent to ${b.guideName}!`);
}

let replaceBookingId=null, replaceGuideSelected=null;
function openReplaceGuide(bookingId) {
  replaceBookingId=bookingId;
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const avail=guides.filter(g=>g.id!==b.guideId&&g.mountains.includes(b.mountainId));
  document.getElementById('replaceGuideList').innerHTML = avail.length?avail.map(g=>`
    <div class="guide-replace-option" onclick="selectReplaceGuide(${g.id})" data-gid="${g.id}">
      <div class="guide-av-sm">${g.initials}</div>
      <div><div class="guide-select-name">${g.name}</div><div class="guide-select-meta">★ ${g.rating} · Available ${g.available}</div></div>
    </div>`).join(''):'<div class="info-note"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>No other guides available for this mountain.</div>';
  document.getElementById('replaceGuideModal').classList.add('open');
}
function selectReplaceGuide(id) {
  replaceGuideSelected=id;
  document.querySelectorAll('.guide-replace-option').forEach(e=>e.classList.remove('selected'));
  document.querySelector(`.guide-replace-option[data-gid="${id}"]`)?.classList.add('selected');
}
function confirmReplaceGuide() {
  if(!replaceGuideSelected){showToast('Select a guide first');return;}
  const b=bookings.find(x=>x.id===replaceBookingId);
  const g=guides.find(x=>x.id===replaceGuideSelected);
  if(!b||!g) return;
  b.guideId=g.id; b.guideName=g.name; b.guideInitials=g.initials;
  b.status='pending'; b.createdAt=Date.now(); b.nudges=0; b.lastNudge=0;
  saveBookings(); renderBookings(); closeReplaceModal(); showToast(`Guide replaced with ${g.name}`);
}
function closeReplaceModal(){document.getElementById('replaceGuideModal').classList.remove('open');replaceGuideSelected=null;}

let editBookingId=null;
function editBooking(bookingId) {
  const b=bookings.find(x=>x.id===bookingId);
  if(!b||b.status!=='pending'){showToast('Only pending bookings can be edited');return;}
  editBookingId=bookingId;
  const todayStr=new Date().toISOString().split('T')[0];
  const hikerRows = b.hikers.map((h,i)=>`
    <div class="hiker-list-item ${i===0?'booker':''}" id="editHikerRow${i}" style="margin-bottom:6px;">
      <div class="hiker-info">
        <div class="hiker-avatar">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>
        <div>
          <div class="hiker-name" id="editHikerName${i}">${h}</div>
          <div class="hiker-tag">${i===0?'Organizer':'Hiker '+(i+1)}</div>
        </div>
      </div>
      ${i>0?`<div class="hiker-actions">
        <button class="hiker-action-btn edit" onclick="showEditHikerModal(${i},'${bookingId}')" title="Edit">
          <svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/></svg>
        </button>
        <button class="hiker-action-btn remove" onclick="removeEditHiker(${i},'${bookingId}')" title="Remove">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>`:''}
    </div>`).join('');
  document.getElementById('editModalBody').innerHTML = `
    <div class="edit-section">
      <div class="inp-label">Date</div>
      <div class="custom-date-wrapper" style="margin-bottom:6px;">
        <div class="dt-icon-row"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <input type="date" id="editDate" value="${b.date}" min="${todayStr}" onchange="updateEditDatePreview(this.value)">
      </div>
      <div class="date-preview ${b.date?'visible':''}" id="editDatePreview"></div>
    </div>
    <div class="edit-section">
      <div class="inp-label">Start Time</div>
      <div class="custom-time-wrapper" style="margin-bottom:6px;">
        <div class="dt-icon-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
        <input type="time" id="editTime" value="${b.time||''}" onchange="updateEditTimePreview(this.value)">
      </div>
      <div class="time-display ${b.time?'visible':''}" id="editTimeDisplay"></div>
    </div>
    <div class="edit-section">
      <div class="section-divider">Hiker Management</div>
      <div style="background:var(--sky);border-radius:12px;padding:14px;margin-top:8px;">
        <div id="editHikerList">${hikerRows}</div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px dashed rgba(16,6,0,0.1);">
          <div class="inp-label">Add Hiker</div>
          <div class="hiker-input-row">
            <input type="text" id="editHikerFn" placeholder="First name" style="padding:8px 10px;border:1.5px solid rgba(16,6,0,0.1);border-radius:8px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;outline:none;">
            <input type="text" id="editHikerLn" placeholder="Last name" style="padding:8px 10px;border:1.5px solid rgba(16,6,0,0.1);border-radius:8px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;outline:none;">
            <button class="btn btn-primary btn-sm" onclick="addEditHiker('${bookingId}')">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add
            </button>
          </div>
        </div>
        <div class="hiker-count-display" id="editHikerCount">${b.hikers.length}/12 hikers</div>
      </div>
    </div>
    <div class="edit-section">
      <div class="inp-label" style="margin-top:10px;">Notes / Reminders</div>
      <textarea id="editNotes" class="inp" rows="3" style="resize:vertical;" placeholder="e.g. Bring extra water, meet at trailhead...">${b.notes||''}</textarea>
    </div>`;
  if(b.date) updateEditDatePreview(b.date);
  if(b.time) updateEditTimePreview(b.time);
  document.getElementById('editBookingModal').classList.add('open');
}

function updateEditDatePreview(val) {
  const el=document.getElementById('editDatePreview'); if(!el||!val) return;
  const d=new Date(val+'T00:00:00');
  el.className='date-preview visible';
  el.innerHTML=`<div class="date-preview-icon"><div class="month">${MONTHS[d.getMonth()]}</div><div class="day">${d.getDate()}</div></div><div class="date-preview-info"><div class="weekday">${DAYS[d.getDay()]}</div><div class="fulldate">${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}</div></div>`;
}
function updateEditTimePreview(val) {
  const el=document.getElementById('editTimeDisplay'); if(!el||!val) return;
  const [h,m]=val.split(':').map(Number);
  const period=h<12?'AM':'PM', h12=h===0?12:h>12?h-12:h;
  el.className='time-display visible';
  el.innerHTML=`<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')} <span class="time-period">${period}</span></span>`;
}

function addEditHiker(bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const fn=document.getElementById('editHikerFn')?.value.trim();
  const ln=document.getElementById('editHikerLn')?.value.trim();
  if(!fn){showToast('Enter first name');return;}
  if(b.hikers.length>=12){showToast('Maximum 12 hikers');return;}
  b.hikers.push(`${fn} ${ln}`.trim());
  b.pax=b.hikers.length;
  saveBookings();
  refreshEditHikerList(bookingId);
  document.getElementById('editHikerFn').value='';
  document.getElementById('editHikerLn').value='';
  showToast('Hiker added');
}
function removeEditHiker(idx, bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  if(idx===0){showToast("Can't remove organizer");return;}
  if(confirm(`Remove ${b.hikers[idx]}?`)){
    b.hikers.splice(idx,1); b.pax=b.hikers.length;
    saveBookings(); refreshEditHikerList(bookingId); showToast('Hiker removed');
  }
}
function showEditHikerModal(idx, bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const name=b.hikers[idx];
  const newName=prompt(`Edit hiker name:`,name);
  if(newName&&newName.trim()){
    b.hikers[idx]=newName.trim(); saveBookings(); refreshEditHikerList(bookingId); showToast('Hiker updated');
  }
}
function refreshEditHikerList(bookingId) {
  const b=bookings.find(x=>x.id===bookingId); if(!b) return;
  const el=document.getElementById('editHikerList'); if(!el) return;
  el.innerHTML=b.hikers.map((h,i)=>`
    <div class="hiker-list-item ${i===0?'booker':''}" style="margin-bottom:6px;">
      <div class="hiker-info">
        <div class="hiker-avatar">${h.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div>
        <div><div class="hiker-name">${h}</div><div class="hiker-tag">${i===0?'Organizer':'Hiker '+(i+1)}</div></div>
      </div>
      ${i>0?`<div class="hiker-actions">
        <button class="hiker-action-btn edit" onclick="showEditHikerModal(${i},'${bookingId}')" title="Edit"><svg viewBox="0 0 24 24"><path d="M17 3l4 4-7 7H10v-4l7-7z"/></svg></button>
        <button class="hiker-action-btn remove" onclick="removeEditHiker(${i},'${bookingId}')" title="Remove"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>`:''}
    </div>`).join('');
  const hc=document.getElementById('editHikerCount'); if(hc) hc.textContent=`${b.hikers.length}/12 hikers`;
}

function saveBookingEdit() {
  const b=bookings.find(x=>x.id===editBookingId); if(!b) return;
  const newDate=document.getElementById('editDate')?.value;
  const newTime=document.getElementById('editTime')?.value;
  const notes=document.getElementById('editNotes')?.value||'';
  if(!newDate){showToast('Please select a date');return;}
  b.date=newDate; b.time=newTime; b.notes=notes;
  // Recalculate fee
  const m=mountains.find(x=>x.id===b.mountainId);
  if(m){const isON=b.type==='overnight',f=m.fees;b.totalFee=f.regFee*b.pax+(f.envFee||0)+(isON?f.guideON:f.guideDay)+((isON&&b.camping&&f.campFee)?f.campFee*b.pax:0);}
  saveBookings(); renderBookings(); closeEditModal(); showToast('Booking updated!');
}
function closeEditModal(){document.getElementById('editBookingModal').classList.remove('open');}

function cancelBooking(bookingId, mode) {
  const label=mode==='leave'?'leave this hike':'cancel this booking';
  if(confirm(`Are you sure you want to ${label}?`)){
    const b=bookings.find(x=>x.id===bookingId);
    if(b){b.status='cancelled';saveBookings();renderBookings();showToast(mode==='leave'?'Left the hike':'Booking cancelled');}
  }
}

function switchTab(tab,el){
  document.querySelectorAll('.page-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-current').style.display=tab==='current'?'block':'none';
  document.getElementById('tab-history').style.display=tab==='history'?'block':'none';
}

function emptyState(){return`<div class="empty-state"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg><p>No current bookings</p><button class="btn btn-primary" onclick="openBookingFlow()">Book your first hike</button></div>`;}

function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),3000);}

// ── CLICK-OUTSIDE TO CLOSE MODALS ──
['bookingModal','successModal','joinSuccessModal','viewJoinedModal','replaceGuideModal','editBookingModal'].forEach(id=>{
  document.getElementById(id)?.addEventListener('click',e=>{
    if(e.target.id===id){
      if(id==='bookingModal') closeBookingModal();
      else if(id==='successModal') closeSuccess();
      else if(id==='joinSuccessModal') closeJoinSuccess();
      else if(id==='replaceGuideModal') closeReplaceModal();
      else if(id==='editBookingModal') closeEditModal();
      else document.getElementById(id).classList.remove('open');
    }
  });
});

loadBookings();
renderBookings();
setInterval(renderBookings, 60000);
</script>
</body>
</html>