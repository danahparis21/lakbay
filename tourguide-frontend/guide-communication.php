<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Manila');

// ── AUTH: require guide login ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── Get guide profile ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT g.id AS guide_id, g.specialization, 
           u.name, u.email, u.avatar, u.phone
    FROM guides g
    JOIN users u ON u.id = g.user_id
    WHERE g.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    die('Guide profile not found.');
}
$guide_id = $guide['guide_id'];

// Avatar initials
$name_parts = explode(' ', $guide['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY Guide — Communication</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    .guide-main {
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .guide-content { padding: 0; flex: 1; display: flex; flex-direction: column; overflow: hidden; height: 100%; }

    .comm-layout {
      display: flex;
      flex: 1;
      height: 100%;
      overflow: hidden;
      position: relative;
    }

    /* ── THREAD LIST ── */
    .thread-list {
      width: 320px;
      border-right: 1px solid var(--line);
      display: flex;
      flex-direction: column;
      background: white;
      flex-shrink: 0;
      height: 100%;
      overflow: hidden;
    }
    .thread-list-header {
      padding: 16px 18px;
      border-bottom: 1px solid var(--line);
      display: flex;
      flex-direction: column;
      gap: 10px;
      background: var(--stone);
      flex-shrink: 0;
    }
    .thread-list-header h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      font-weight: 600;
    }
    .thread-search {
      display: flex;
      align-items: center;
      gap: 8px;
      background: white;
      border: 1px solid var(--line);
      border-radius: 40px;
      padding: 7px 14px;
    }
    .thread-search input {
      border: none;
      background: none;
      outline: none;
      font-size: 0.78rem;
      width: 100%;
      color: var(--ink);
    }
    .thread-search i { color: var(--ink-4); font-size: 0.75rem; }

    .thread-tabs {
      display: flex;
      border-bottom: 1px solid var(--line);
      padding: 0 6px;
      background: white;
      flex-shrink: 0;
    }
    .thread-tab {
      flex: 1;
      padding: 10px 6px;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--ink-4);
      text-align: center;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: all 0.15s;
    }
    .thread-tab.active { color: #100600; border-bottom-color: #100600; }

    .thread-items {
      flex: 1;
      overflow-y: auto;
    }
    .thread-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      cursor: pointer;
      border-bottom: 1px solid var(--stone-2);
      transition: background 0.12s;
      position: relative;
    }
    .thread-item:hover { background: var(--stone); }
    .thread-item.active { background: rgba(16,6,0,0.06); }
    .thread-item.active::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 3px;
      background: #100600;
      border-radius: 0 2px 2px 0;
    }
    /* Fix avatar letter centering */
.thread-avatar,
.chat-header-avatar,
.msg-av-xs {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1;
}

.thread-avatar {
    font-size: 1rem;
    font-weight: 600;
}

.chat-header-avatar {
    font-size: 1rem;
    font-weight: 600;
}

.msg-av-xs {
    font-size: 0.75rem;
    font-weight: 600;
}
    .thread-info { flex: 1; min-width: 0; }
    .thread-name {
      font-size: 0.83rem;
      font-weight: 600;
      color: var(--ink);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .thread-time {
      font-size: 0.62rem;
      color: var(--ink-4);
      font-family: 'DM Mono', monospace;
      flex-shrink: 0;
    }
    .thread-preview {
      font-size: 0.72rem;
      color: var(--ink-3);
      margin-top: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .thread-unread {
      background: #100600;
      color: white;
      min-width: 18px;
      height: 18px;
      border-radius: 50%;
      font-size: 0.6rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0 5px;
      flex-shrink: 0;
    }

    /* ── CHAT AREA ── */
    .chat-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: var(--stone);
      height: 100%;
      overflow: hidden;
    }
    .chat-header {
      background: white;
      border-bottom: 1px solid var(--line);
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }
    .chat-header-avatar {
      width: 38px; height: 38px;
      border-radius: 50%;
      background: rgba(16,6,0,0.1);
      color: #100600;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 0.9rem;
      font-weight: 700;
    }
    .chat-header-info { flex: 1; }
    .chat-header-name { font-size: 0.9rem; font-weight: 600; color: var(--ink); }
    .chat-header-sub { font-size: 0.68rem; color: var(--ink-4); }

    /* ── CHAT MESSAGES ── */
    .chat-messages-wrapper {
      flex: 1;
      overflow-y: auto;
      min-height: 0;
      background: #f8f9fa;
    }
    .chat-messages {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .msg-bubble-row {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      width: 100%;
    }
    .msg-bubble-row.mine {
      justify-content: flex-end;
    }
    .msg-bubble-row.mine > div {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      max-width: 80%;
    }
    .msg-bubble-row:not(.mine) > div {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      max-width: 80%;
    }

    .msg-av-xs {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #e9ecef;
      color: #495057;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      flex-shrink: 0;
      border: 1px solid #dee2e6;
    }

    .msg-bubble {
      padding: 12px 16px;
      border-radius: 18px;
      font-size: 0.9rem;
      line-height: 1.5;
      word-break: break-word;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .msg-bubble.mine {
      background: #100600;
      color: white;
      border-bottom-right-radius: 4px;
    }
    .msg-bubble.theirs {
      background: white;
      color: #212529;
      border-bottom-left-radius: 4px;
      border: 1px solid #e9ecef;
    }

    .msg-time {
      font-size: 0.65rem;
      color: #adb5bd;
      margin-top: 4px;
      padding: 0 4px;
    }

    .sys-msg {
      text-align: center;
      font-size: 11px;
      color: #6c757d;
      background: #e9ecef;
      border-radius: 20px;
      padding: 4px 12px;
      align-self: center;
      margin: 12px 0;
      font-weight: 600;
      letter-spacing: 0.3px;
    }

    /* booking request card */
    .booking-request-card {
      background: white;
      border: 1.5px solid var(--line);
      border-radius: var(--r-lg);
      padding: 14px 16px;
      width: 320px;
      max-width: 100%;
    }
    .brc-header { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #100600; margin-bottom: 8px; }
    .brc-row { display: flex; justify-content: space-between; font-size: 0.75rem; padding: 4px 0; border-bottom: 1px solid var(--stone-2); }
    .brc-row:last-of-type { border-bottom: none; }
    .brc-label { color: var(--ink-4); font-weight: 500; }
    .brc-val { color: var(--ink); font-weight: 600; }
    .brc-actions { display: flex; gap: 8px; margin-top: 10px; }
    .brc-actions .btn { flex: 1; justify-content: center; }

    .join-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 50px;
      font-size: 12px;
      font-weight: 700;
    }
    .join-status-badge.approved { background: rgba(90,122,90,.12); color: #2D5016; }
    .join-status-badge.denied { background: rgba(92,58,58,.08); color: #5C3A2A; }
    .join-status-badge.pending { background: rgba(230,126,34,.1); color: #c96a10; }

    /* empty state */
    .chat-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      color: var(--ink-4);
      height: 100%;
      min-height: 300px;
    }
    .chat-empty i { font-size: 2.5rem; color: var(--stone-3); }
    .chat-empty p { font-size: 0.82rem; }

    /* quick replies */
    .quick-replies {
      padding: 8px 20px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      background: var(--stone);
      border-top: 1px solid rgba(0,0,0,0.05);
      flex-shrink: 0;
    }
    .quick-chip {
      padding: 6px 14px;
      background: white;
      border: 1px solid var(--line);
      border-radius: 30px;
      font-size: 0.7rem;
      color: #100600;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.15s;
    }
    .quick-chip:hover { background: #100600; color: white; border-color: #100600; }

    /* message input - STICKY AT BOTTOM */
    .chat-input-bar {
      background: white;
      border-top: 1px solid var(--line);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }
    .chat-input-wrap {
      flex: 1;
      background: var(--stone);
      border: 1.5px solid var(--line);
      border-radius: 24px;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: border-color 0.15s;
    }
    .chat-input-wrap:focus-within { border-color: #100600; background: white; }
    .chat-input-wrap input {
      flex: 1;
      border: none;
      background: none;
      outline: none;
      font-size: 0.85rem;
      font-family: 'DM Sans', sans-serif;
      color: var(--ink);
    }
    .chat-input-wrap input::placeholder { color: var(--ink-4); }
    .chat-send-btn {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: #100600;
      color: white;
      border: none;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem;
      transition: all 0.15s;
      flex-shrink: 0;
      cursor: pointer;
    }
    .chat-send-btn:hover { background: #2C1A10; transform: scale(1.05); }
    .chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Loading spinner */
    .loading-spinner {
      text-align: center;
      padding: 40px;
      color: var(--ink-4);
    }
    .loading-spinner i { margin-right: 8px; }

    @media (max-width: 768px) {
      .thread-list { 
        width: 100%; 
        position: absolute; 
        z-index: 10; 
        transform: translateX(0); 
        transition: transform 0.3s ease; 
        background: white; 
        height: 100%; 
      }
      .thread-list.hidden { transform: translateX(-100%); }
      .chat-area { width: 100%; }
      .comm-layout { position: relative; }
      .msg-group { max-width: 90%; }
      .booking-request-card { width: 280px; }
    }

    .toast {
      position: fixed;
      bottom: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #100600;
      color: white;
      padding: 10px 20px;
      border-radius: 40px;
      font-size: 0.8rem;
      z-index: 9999;
      opacity: 0;
      transition: 0.25s;
      pointer-events: none;
      white-space: nowrap;
    }
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }

    /* View Details button styling */
.brc-actions .btn-primary {
    background: #100600;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.brc-actions .btn-primary:hover {
    background: #2C1A10;
    transform: translateY(-1px);
}

.brc-actions .btn-primary i {
    font-size: 0.7rem;
}

.join-status-badge.approved {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
}
/* Payment Instructions Card - Blue Theme */
.msg-card.payment-instructions {
    background: white;
    border: 1px solid #e0e7ff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    width: 360px;
    max-width: 100%;
}

.msg-card.payment-instructions .msg-card-hdr {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
}

.msg-card.payment-instructions .msg-card-hdr-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.msg-card.payment-instructions .msg-card-hdr-sub {
    font-size: 10px;
    opacity: 0.85;
    margin-top: 2px;
}

.msg-card.payment-instructions .msg-card-icon {
    font-size: 22px;
    flex-shrink: 0;
}

.msg-card.payment-instructions .msg-card-body {
    padding: 16px;
}

.payment-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}

.payment-detail-row:last-child {
    border-bottom: none;
}

.payment-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
}

.payment-value {
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
}

.payment-value.amount {
    color: #2563eb;
    font-size: 18px;
}

.payment-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.payment-status.pending {
    background: #ffffffff;
    color: #cfbb35ff;
}

.payment-status.paid {
    background: #d1fae5;
    color: #059669;
}

.payment-status.overdue {
    background: #fee2e2;
    color: #dc2626;
}

.gcash-details {
    background: #f0f9ff;
    border-radius: 12px;
    padding: 12px;
    margin: 12px 0;
}

.gcash-details .gcash-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    padding: 4px 0;
}

.gcash-details .gcash-row i {
    width: 20px;
    color: #2563eb;
}

.payment-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.payment-actions .btn-payment {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-payment.primary {
    background: #2563eb;
    color: white;
}

.btn-payment.primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.btn-payment.secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.btn-payment.secondary:hover {
    background: #e5e7eb;
}

.qr-code-preview {
    text-align: center;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #e5e7eb;
}

.qr-code-preview img {
    max-width: 100px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    cursor: pointer;
}

.qr-code-preview small {
    display: block;
    margin-top: 6px;
    font-size: 10px;
    color: #9ca3af;
}

    /* ── MESSAGE CARDS ── */
    .msg-card {
      background: white;
      border: 1px solid #dee2e6;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      width: 320px;
      max-width: 100%;
    }
    .msg-card-hdr {
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      color: white;
    }
    .msg-card-icon { font-size: 1.2rem; }
    .msg-card-hdr-title { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px; }
    .msg-card-hdr-sub { font-size: 0.6rem; opacity: 0.8; margin-top: 1px; }
    .msg-card-body { padding: 16px; }
    .msg-card-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 8px; }
    .msg-card-detail { font-size: 0.8rem; margin-bottom: 6px; color: #495057; display: flex; align-items: center; gap: 8px; }
    
    .msg-card.payment-instructions .msg-card-hdr { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
    .msg-card.join-request .msg-card-hdr { background: linear-gradient(135deg, #100600 0%, #333 100%); }
    
    .payment-detail-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid #f1f3f5;
    }
    .payment-label { font-size: 0.7rem; color: #868e96; font-weight: 600; }
    .payment-value { font-size: 0.85rem; font-weight: 700; color: #212529; }
    .payment-value.amount { color: #2563eb; font-size: 1.1rem; }
    
    .gcash-details {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 10px;
      margin: 12px 0;
      border: 1px solid #e9ecef;
    }
    .gcash-row { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: #495057; padding: 3px 0; }
    .gcash-row i { color: #2563eb; width: 16px; }

    .payment-status {
      margin-left: auto;
      font-size: 0.6rem;
      font-weight: 800;
      padding: 4px 8px;
      border-radius: 20px;
    }
    
    .payment-actions, .join-action-btns {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }
    .btn-payment, .join-btn {
      flex: 1;
      padding: 8px;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 700;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .btn-payment.primary, .join-btn.approve { background: #100600; color: white; }
    .btn-payment.secondary, .join-btn.deny { background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }
    .btn-payment:hover, .join-btn:hover { opacity: 0.9; transform: translateY(-1px); }

    .join-status-badge {
      width: 100%;
      text-align: center;
      padding: 8px;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 700;
      margin-top: 10px;
    }
    .join-status-badge.approved { background: #d4edda; color: #155724; }
    .join-status-badge.denied { background: #f8d7da; color: #721c24; }
    .join-status-badge.pending { background: #fff3cd; color: #856404; }

    /* QR Code Toggle Button */
.qr-code-section {
    margin-top: 12px;
    text-align: center;
}

.qr-toggle-btn {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.qr-toggle-btn:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
}

.qr-toggle-btn i {
    color: #2563eb;
}
  </style>
</head>
<body>
<div class="guide-app">
  <aside class="guide-sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="white" opacity="0.9"/><path d="M14 16L18 8L24 22H14V16Z" fill="white" opacity="0.3"/></svg>
      <div><div class="sidebar-logo-text">LAKBAY</div><div class="sidebar-logo-sub">Guide Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <ul>
        <li><a href="guide-dashboard.php"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.php"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.php" class="active"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-bookings.php"><i class="fas fa-shield-halved"></i> Bookings</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.php"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
   <div class="sidebar-profile">
  <?php if (!empty($guide['avatar'])): ?>
    <img src="../<?= htmlspecialchars($guide['avatar']) ?>" class="sidebar-avatar" style="object-fit:cover;" alt="avatar">
  <?php else: ?>
    <div class="sidebar-avatar"><?= $initials ?></div>
  <?php endif; ?>
  <div class="sidebar-profile-info">
    <div class="sidebar-profile-name"><?= htmlspecialchars($guide['name']) ?></div>
    <div class="sidebar-profile-role"><?= htmlspecialchars($guide['specialization'] ?? 'Trail Guide') ?></div>
  </div>
  <a href="../login-and-signup/login.php" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;text-decoration:none;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </a>
</div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Communication</div>
      <div class="topbar-right">
        <button class="topbar-icon-btn" onclick="refreshConversations()"><i class="fas fa-rotate-right"></i></button>
      </div>
    </div>

    <div class="guide-content">
      <div class="comm-layout">

        <!-- THREAD LIST -->
        <div class="thread-list" id="threadList">
          <div class="thread-list-header">
            <h3>Messages <span style="font-size:10px;font-weight:normal;">🔒 AES-256</span></h3>
            <div class="thread-search">
              <i class="fas fa-search"></i>
              <input type="text" id="searchInput" placeholder="Search conversations…" onkeyup="filterThreads()">
            </div>
          </div>
          <div class="thread-tabs">
            <div class="thread-tab active" data-tab="all" onclick="setActiveTab('all')">All</div>
            <div class="thread-tab" data-tab="unread" onclick="setActiveTab('unread')">Unread</div>
          </div>
          <div class="thread-items" id="threadItems">
            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading conversations...</div>
          </div>
        </div>

        <!-- CHAT AREA -->
        <div class="chat-area" id="chatArea">
          <div class="chat-header" id="chatHeader">
            <button class="btn btn-ghost btn-sm" id="backBtn" style="display:none;padding:6px 10px;" onclick="showThreadList()"><i class="fas fa-arrow-left"></i></button>
            <div class="chat-header-avatar" id="chatAvatarHd">?</div>
            <div class="chat-header-info">
              <div class="chat-header-name" id="chatName">Select a conversation</div>
              <div class="chat-header-sub" id="chatSub"></div>
            </div>
            <div class="chat-header-actions">
              <button class="topbar-icon-btn" title="Refresh" onclick="if(activeThread) loadMessages(activeThread, true)"><i class="fas fa-rotate-right"></i></button>
            </div>
          </div>

          <!-- Messages wrapper with independent scroll -->
          <div class="chat-messages-wrapper" id="chatMessagesWrapper">
            <div class="chat-messages" id="chatMessages">
              <div class="chat-empty">
                <i class="fas fa-comments"></i>
                <p>Select a conversation to start messaging with hikers</p>
              </div>
            </div>
          </div>

          <div class="quick-replies" id="quickReplies" style="display:none;">
            <div class="quick-chip" onclick="sendQuick('I\'m on my way!')">On my way!</div>
            <div class="quick-chip" onclick="sendQuick('Be at jump-off 15 mins early')">Be 15 min early</div>
            <div class="quick-chip" onclick="sendQuick('Bring water and snacks')">Bring supplies</div>
            <div class="quick-chip" onclick="sendQuick('Hike confirmed! See you')">Confirmed ✓</div>
          </div>

          <div class="chat-input-bar" id="chatInputBar" style="display:none;">
            <div class="chat-input-wrap">
              <input type="text" id="msgInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter')sendMessage()">
            </div>
            <button class="chat-send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.php" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.php" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.php" class="bnav-item active"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-bookings.php" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Bookings</span></a>
      <a href="guide-profile.php" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<div class="toast" id="toast"></div>

<script>
const CURRENT_USER_ID = <?= json_encode($user_id) ?>;
const CURRENT_GUIDE_NAME = <?= json_encode($guide['name']) ?>;

let conversations = [];
let activeThread = null;
let pollInterval = null;
let currentFilter = 'all';
let searchQuery = '';

// Store scroll position for each thread
let scrollPositions = {};

// ────────────────────────────────────────────────────────────────────────────
// API Calls
// ────────────────────────────────────────────────────────────────────────────

async function loadConversations(silent = false) {
    try {
        const res = await fetch('../api/guide_messages.php?action=get_conversations');
        const data = await res.json();
        if (data.success) {
            conversations = data.conversations || [];
            if (!silent) renderThreadList();
            updateUnreadCounts();
        } else if (!silent) {
            document.getElementById('threadItems').innerHTML = '<div class="loading-spinner" style="text-align:center;padding:40px;color:var(--ink-4);">Error loading conversations</div>';
        }
    } catch (e) {
        if (!silent) document.getElementById('threadItems').innerHTML = '<div class="loading-spinner" style="text-align:center;padding:40px;color:var(--ink-4);">Network error</div>';
    }
}
function updateUnreadCounts() {
    document.querySelectorAll('.thread-item').forEach(el => {
        const id = parseInt(el.getAttribute('data-thread-id'));
        const conv = conversations.find(c => c.id === id);
        if (conv && conv.unread_count > 0) {
            const nameSpan = el.querySelector('.thread-name');
            const existingUnread = el.querySelector('.thread-unread');
            if (!existingUnread && nameSpan) {
                const badge = document.createElement('span');
                badge.className = 'thread-unread';
                badge.textContent = conv.unread_count;
                nameSpan.appendChild(badge);
            } else if (existingUnread) {
                existingUnread.textContent = conv.unread_count;
            }
        } else {
            const existingUnread = el.querySelector('.thread-unread');
            if (existingUnread) existingUnread.remove();
        }
    });
    
    // Update sidebar notification badge
    updateSidebarNotificationBadge();
}
function getFilteredThreads() {
    let filtered = [...conversations];
    
    if (currentFilter === 'unread') {
        filtered = filtered.filter(c => c.unread_count > 0);
    }
    
    if (searchQuery.trim() !== '') {
        const query = searchQuery.toLowerCase();
        filtered = filtered.filter(c => 
            (c.name || '').toLowerCase().includes(query) ||
            (c.last_message || '').toLowerCase().includes(query)
        );
    }
    
    return filtered;
}

function renderThreadList() {
    const container = document.getElementById('threadItems');
    if (!container) return;
    
    const filtered = getFilteredThreads();
    
    if (filtered.length === 0) {
        container.innerHTML = `<div class="loading-spinner" style="text-align:center;padding:40px;color:var(--ink-4);">
            <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
            No conversations found
        </div>`;
        return;
    }
    
    container.innerHTML = filtered.map(conv => {
        const isActive = activeThread === conv.id;
        const preview = conv.last_message || 'No messages yet';
        const timeStr = conv.last_message_time ? formatTime(conv.last_message_time) : '';
        const initials = (conv.name || '?').charAt(0).toUpperCase();
        
        return `
            <div class="thread-item ${isActive ? 'active' : ''}" data-thread-id="${conv.id}" onclick="openThread(${conv.id})">
                <div class="thread-avatar">
                    ${initials}
                </div>
                <div class="thread-info">
                    <div class="thread-name">
                        ${escapeHtml(conv.name || 'Unknown Hiker')}
                        <span class="thread-time">${timeStr}</span>
                    </div>
                    <div class="thread-preview">${escapeHtml(preview.substring(0, 60))}${preview.length > 60 ? '...' : ''}</div>
                </div>
                ${conv.unread_count > 0 ? `<div class="thread-unread">${conv.unread_count}</div>` : ''}
            </div>
        `;
    }).join('');
    
    // Re-highlight active thread
    if (activeThread) {
        const activeEl = container.querySelector(`.thread-item[data-thread-id="${activeThread}"]`);
        if (activeEl) activeEl.classList.add('active');
    }
}

function setActiveTab(tab) {
    currentFilter = tab;
    document.querySelectorAll('.thread-tab').forEach(t => {
        if (t.getAttribute('data-tab') === tab) {
            t.classList.add('active');
        } else {
            t.classList.remove('active');
        }
    });
    renderThreadList();
}

function filterThreads() {
    const searchInput = document.getElementById('searchInput');
    searchQuery = searchInput ? searchInput.value : '';
    renderThreadList();
}

async function openThread(hikerUserId) {
    // Save scroll position of current thread
    if (activeThread) {
        const wrapper = document.getElementById('chatMessagesWrapper');
        if (wrapper) {
            scrollPositions[activeThread] = wrapper.scrollTop;
        }
    }
    
    activeThread = hikerUserId;
    const conv = conversations.find(c => c.id === hikerUserId);
    
    if (conv) {
        document.getElementById('chatName').textContent = conv.name || 'Hiker';
        document.getElementById('chatSub').textContent = conv.role || 'Hiker';
        document.getElementById('chatAvatarHd').textContent = (conv.name || '?').charAt(0).toUpperCase();
    }
    
    // Show chat UI
    document.getElementById('quickReplies').style.display = 'flex';
    document.getElementById('chatInputBar').style.display = 'flex';
    
    await loadMessages(hikerUserId);
    await markAsRead(hikerUserId);
    renderThreadList();
    
    // Restore scroll position for this thread
    setTimeout(() => {
        const wrapper = document.getElementById('chatMessagesWrapper');
        if (wrapper && scrollPositions[activeThread] !== undefined) {
            wrapper.scrollTop = scrollPositions[activeThread];
        } else if (wrapper) {
            wrapper.scrollTop = wrapper.scrollHeight;
        }
    }, 100);
    
    // Mobile: hide thread list
    if (window.innerWidth <= 768) {
        document.getElementById('threadList').classList.add('hidden');
        document.getElementById('backBtn').style.display = 'flex';
    }
}

function showThreadList() {
    document.getElementById('threadList').classList.remove('hidden');
    document.getElementById('backBtn').style.display = 'none';
}

async function loadMessages(hikerUserId, preserveScroll = false) {
    const area = document.getElementById('chatMessages');
    const wrapper = document.getElementById('chatMessagesWrapper');
    
    let oldScrollTop = 0;
    let wasAtBottom = false;
    
    if (wrapper) {
        oldScrollTop = wrapper.scrollTop;
        wasAtBottom = wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight < 50;
    }
    
    // Only show spinner if not preserving scroll (first load or explicit refresh)
    if (!preserveScroll) {
        area.innerHTML = '<div class="loading-spinner" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
    }
    
    try {
        const res = await fetch(`../api/guide_messages.php?action=get_messages&user_id=${hikerUserId}`);
        const data = await res.json();
        
        if (data.success) {
            renderMessages(data.messages || []);
             startCountdownTimers(); // Add this line
            // Handle scroll position
            if (preserveScroll && wrapper) {
                if (wasAtBottom) {
                    wrapper.scrollTop = wrapper.scrollHeight;
                } else {
                    wrapper.scrollTop = oldScrollTop;
                }
            } else if (wrapper) {
                wrapper.scrollTop = wrapper.scrollHeight;
            }
        } else if (!preserveScroll) {
            area.innerHTML = '<div class="chat-empty"><i class="fas fa-exclamation-triangle"></i><p>Error loading messages</p></div>';
        }
    } catch (e) {
        if (!preserveScroll) {
            area.innerHTML = '<div class="chat-empty"><i class="fas fa-wifi"></i><p>Network error</p></div>';
        }
    }
}
function renderMessages(messages) {
    const area = document.getElementById('chatMessages');
    
    if (!messages || messages.length === 0) {
        area.innerHTML = `<div class="chat-empty"><div class="chat-empty-icon">💬</div><div>No messages yet</div><p style="font-size:12px;margin-top:8px;">Send a message to start the conversation</p></div>`;
        return;
    }

    let lastDate = '';
    let html = '';
    
    messages.forEach(msg => {
        const isMine = String(msg.sender_id) === String(CURRENT_USER_ID);
        const msgDate = new Date(msg.created_at);
        const dateStr = msgDate.toLocaleDateString();
        const timeStr = msgDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const senderInitial = !isMine && msg.sender_name ? msg.sender_name.charAt(0).toUpperCase() : '';

        // Add date separator
        if (dateStr !== lastDate) {
            html += `<div class="sys-msg">${msgDate.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' })}</div>`;
            lastDate = dateStr;
        }

       // 1. FIRST CHECK: Payment Instructions (Guide View - Read Only, No Buttons)
if (msg.action_data && msg.action_data !== 'null' && msg.action_data !== '') {
    try {
        const ad = typeof msg.action_data === 'string' ? JSON.parse(msg.action_data) : msg.action_data;
        
        if (ad.type === 'payment_instructions') {
            const downpayment = ad.downpayment_amount || '₱0.00';
            const gcashNumber = ad.gcash_number || 'Not set';
            const gcashName = ad.gcash_name || 'Not set';
            const bookingNumber = ad.booking_number || 'N/A';
            const totalAmount = ad.total_amount || 'N/A';
            const remaining = parseFloat(totalAmount) - parseFloat(downpayment);
            const qrCodeUrl = ad.qr_code_url || '../assets/images/gcash-qr.jpg';
            
            // Get downpayment deadline from the message or booking
            let deadlineTimestamp = null;
            let deadlineStr = ad.deadline || null;
            
            // Also check if there's a confirmation message body to display
            const confirmationText = msg.body || '';
            
            // QR Toggle functionality
            const uniqueId = 'qr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4);
            
            // Build countdown timer HTML if deadline exists
            let countdownHtml = '';
            if (deadlineStr) {
                deadlineTimestamp = new Date(deadlineStr).getTime();
                const uniqueTimerId = 'timer_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
                countdownHtml = `
                    <div class="countdown-timer" id="${uniqueTimerId}" data-deadline="${deadlineTimestamp}" style="background: #fff3cd; padding: 8px 12px; border-radius: 8px; margin: 10px 0; text-align: center;">
                        <div style="font-size: 11px; color: #856404; margin-bottom: 4px;">⏰ DOWNPAYMENT DEADLINE</div>
                        <div style="font-size: 16px; font-weight: 700; color: #d97706;" class="countdown-display"></div>
                    </div>
                `;
            }
            
            // Determine payment status badge
            let paymentStatusBadge = '';
            let paymentStatusText = 'AWAITING PAYMENT';
            let paymentStatusClass = 'pending';
            
            if (ad.payment_status === 'paid') {
                paymentStatusText = '✓ PAYMENT RECEIVED';
                paymentStatusClass = 'paid';
                paymentStatusBadge = `<span class="payment-status paid"><i class="fas fa-check-circle"></i> ${paymentStatusText}</span>`;
            } else if (ad.payment_status === 'expired') {
                paymentStatusText = '⏰ EXPIRED';
                paymentStatusClass = 'overdue';
                paymentStatusBadge = `<span class="payment-status overdue"><i class="fas fa-hourglass-end"></i> ${paymentStatusText}</span>`;
            } else {
                paymentStatusBadge = `<span class="payment-status pending"><i class="fas fa-clock"></i> ${paymentStatusText}</span>`;
            }
            
            let confirmationHtml = '';
            if (confirmationText && !confirmationText.includes('GCASH QR CODE')) {
                confirmationHtml = `
                    <div class="msg-card" style="border: none; margin-bottom: 12px;">
                        <div class="msg-card-hdr" style="background: #059669;">
                            <span class="msg-card-icon">🎉</span>
                            <div class="msg-card-hdr-label">
                                <div class="msg-card-hdr-title">BOOKING CONFIRMED</div>
                                <div class="msg-card-hdr-sub">Hike Confirmed</div>
                            </div>
                        </div>
                        <div class="msg-card-body">
                            <div class="msg-card-desc" style="white-space: pre-line; line-height: 1.5;">${escapeHtml(confirmationText)}</div>
                        </div>
                    </div>
                `;
            }
            
            const card = `
                ${confirmationHtml}
                <div class="msg-card payment-instructions">
                    <div class="msg-card-hdr">
                        <span class="msg-card-icon">💰</span>
                        <div class="msg-card-hdr-label">
                            <div class="msg-card-hdr-title">PAYMENT INSTRUCTIONS</div>
                            <div class="msg-card-hdr-sub">Booking #${escapeHtml(bookingNumber)}</div>
                        </div>
                        ${paymentStatusBadge}
                    </div>
                    <div class="msg-card-body">
                        <div class="payment-detail-row">
                            <span class="payment-label">Total Amount</span>
                            <span class="payment-value amount">₱${escapeHtml(totalAmount)}</span>
                        </div>
                        <div class="payment-detail-row">
                            <span class="payment-label">Downpayment Required</span>
                            <span class="payment-value amount" style="color:#059669;">₱${escapeHtml(downpayment)}</span>
                        </div>
                        <div class="payment-detail-row">
                            <span class="payment-label">Remaining Balance</span>
                            <span class="payment-value">₱${escapeHtml(remaining)}</span>
                        </div>
                        
                        ${countdownHtml}
                        
                        <div class="gcash-details">
                            <div class="gcash-row">
                                <i class="fas fa-mobile-alt"></i>
                                <span><strong>GCash Number:</strong> ${escapeHtml(gcashNumber)}</span>
                                <button onclick="copyGCashNumber('${escapeHtml(gcashNumber)}')" style="margin-left: auto; background: none; border: none; cursor: pointer; color: #2563eb;">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="gcash-row">
                                <i class="fas fa-user"></i>
                                <span><strong>Account Name:</strong> ${escapeHtml(gcashName)}</span>
                            </div>
                        </div>
                        
                        <div class="qr-code-section">
                            <button class="qr-toggle-btn" onclick="toggleQRCode('${uniqueId}')">
                                <i class="fas fa-qrcode"></i> Show/Hide QR Code
                            </button>
                            <div id="${uniqueId}" class="qr-code-preview" style="display: none;">
                                <img src="${escapeHtml(qrCodeUrl)}" alt="GCash QR Code" 
                                     onerror="this.style.display='none'"
                                     onclick="window.open('${escapeHtml(qrCodeUrl)}', '_blank')">
                                <small>Click image to enlarge</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="msg-time">${timeStr}</div>`;
            
            if (isMine) {
                html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
            } else {
                html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
            }
            return;
        }
        
        // 2. Booking Request Card - FIXED: Disable buttons after approval
        if (ad.type === 'booking_request') {
            const isGuide = String(msg.receiver_id) === String(CURRENT_USER_ID);
            const status = ad.status || 'pending';
            const handled = status === 'approved' || status === 'denied';

            let actionsHtml = '';
            if (isGuide && !handled) {
                actionsHtml = `
                    <div class="join-action-btns">
                        <button class="join-btn approve" onclick="handleBookingRequest(${msg.id},'accept','${ad.booking_id}','${escapeHtml(ad.hiker_name)}',${ad.hiker_user_id})">
                            <i class="fas fa-check"></i> Accept
                        </button>
                        <button class="join-btn deny" onclick="handleBookingRequest(${msg.id},'decline','${ad.booking_id}','${escapeHtml(ad.hiker_name)}',${ad.hiker_user_id})">
                            <i class="fas fa-times"></i> Decline
                        </button>
                    </div>`;
            } else if (handled) {
                // Show status badge instead of buttons when already handled
                const statusText = status === 'approved' ? '✓ BOOKING ACCEPTED' : '✕ BOOKING DECLINED';
                const statusClass = status === 'approved' ? 'approved' : 'denied';
                actionsHtml = `<div class="join-status-badge ${statusClass}" style="justify-content: center;">${statusText}</div>`;
            } else if (!isGuide && !handled) {
                actionsHtml = `<div class="join-status-badge pending"><i class="fas fa-clock"></i> Awaiting guide response</div>`;
            }

            const card = `
                <div class="msg-card join-request">
                    <div class="msg-card-hdr">
                        <span class="msg-card-icon">🏔️</span>
                        <div class="msg-card-hdr-label">
                            <div class="msg-card-hdr-title">NEW BOOKING REQUEST</div>
                            <div class="msg-card-hdr-sub">${isGuide ? 'Hiker wants to book a hike' : 'Booking request sent'}</div>
                        </div>
                    </div>
                    <div class="msg-card-body">
                        <div class="msg-card-title">${escapeHtml(ad.hiker_name)}</div>
                        <div class="msg-card-detail"><i class="fas fa-mountain"></i> ${escapeHtml(ad.mountain_name)}</div>
                        <div class="msg-card-detail"><i class="fas fa-calendar"></i> ${escapeHtml(ad.booking_date)}</div>
                        <div class="msg-card-detail"><i class="fas fa-users"></i> ${ad.pax} hiker(s)</div>
                        <div class="msg-card-detail"><i class="fas fa-tag"></i> #${escapeHtml(ad.booking_number)}</div>
                        ${actionsHtml}
                    </div>
                </div>
                <div class="msg-time">${timeStr}</div>`;

            if (isGuide) {
                html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
            } else {
                html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
            }
            return;
        }
    } catch (e) {
        console.error('action_data parse error', e);
    }
}

        // 3. System Announcement / Booking Confirmation Cards
        const isSystemAnnouncement = (msg.is_system_announcement == 1) || (msg.sender_role === 'guide' && msg.is_system_announcement == 1);
        
        if (isSystemAnnouncement || msg.sender_role === 'system') {
            let icon = '📢';
            let title = 'System Announcement';
            let subTitle = 'Official Update';
            let headerColor = 'background: #6b7280;';
            
            if (msg.body && (msg.body.includes('CONFIRMED') || msg.body.includes('confirmed'))) {
                icon = '✅';
                title = 'BOOKING CONFIRMED';
                subTitle = 'Hike Confirmed';
                headerColor = 'background: #059669;';
            } else if (msg.body && msg.body.includes('CANCELLED')) {
                icon = '❌';
                title = 'BOOKING CANCELLED';
                subTitle = 'Cancellation Notice';
                headerColor = 'background: #dc2626;';
            } else if (msg.body && msg.body.includes('reminder')) {
                icon = '🔔';
                title = 'REMINDER';
                subTitle = 'Friendly Reminder';
                headerColor = 'background: #d97706;';
            }
            
            const card = `
                <div class="msg-card" style="border: none;">
                    <div class="msg-card-hdr" style="${headerColor}">
                        <span class="msg-card-icon">${icon}</span>
                        <div class="msg-card-hdr-label">
                            <div class="msg-card-hdr-title">${title}</div>
                            <div class="msg-card-hdr-sub">${subTitle}</div>
                        </div>
                    </div>
                    <div class="msg-card-body">
                        <div class="msg-card-desc" style="white-space: pre-line; line-height: 1.5;">${escapeHtml(msg.body || '')}</div>
                    </div>
                </div>
                <div class="msg-time">${timeStr}</div>`;
            
            if (isMine) {
                html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
            } else {
                html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
            }
            return;
        }

        // 4. NORMAL TEXT MESSAGE BUBBLE
        const bubble = `<div class="msg-bubble ${isMine ? 'mine' : 'theirs'}">${escapeHtml(msg.body || '')}</div>`;
        
        if (isMine) {
            html += `
                <div class="msg-bubble-row mine">
                    <div>
                        ${bubble}
                        <div class="msg-time">${timeStr}</div>
                    </div>
                </div>`;
        } else {
            html += `
                <div class="msg-bubble-row">
                    <div class="msg-av-xs">${senderInitial}</div>
                    <div>
                        ${bubble}
                        <div class="msg-time">${timeStr}</div>
                    </div>
                </div>`;
        }
    });

    area.innerHTML = html;
     startCountdownTimers(); // Add this line

    const wrapper = document.getElementById('chatMessagesWrapper');
    if (wrapper) {
        wrapper.scrollTop = wrapper.scrollHeight;
    }
}

// Countdown timer for downpayment deadlines
function startCountdownTimers() {
    const timers = document.querySelectorAll('.countdown-timer');
    
    timers.forEach(timer => {
        const deadline = parseInt(timer.getAttribute('data-deadline'));
        const displayElement = timer.querySelector('.countdown-display');
        
        if (!deadline || !displayElement) return;
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = deadline - now;
            
            if (distance < 0) {
                displayElement.innerHTML = '⏰ EXPIRED';
                displayElement.style.color = '#dc2626';
                timer.style.background = '#fee2e2';
                clearInterval(timer.interval);
                return;
            }
            
            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            displayElement.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
            
            // Warning color when less than 1 hour
            if (distance < 3600000) {
                displayElement.style.color = '#dc2626';
                displayElement.style.fontWeight = '800';
            } else if (distance < 10800000) { // less than 3 hours
                displayElement.style.color = '#d97706';
            }
        }
        
        updateCountdown();
        timer.interval = setInterval(updateCountdown, 1000);
    });
}

// Call this after rendering messages
function startAllCountdowns() {
    startCountdownTimers();
}

// Override the existing renderMessages function to call countdown after rendering
// Add this at the end of renderMessages function, after setting innerHTML:
// startCountdownTimers();   


// QR Code Toggle Function
function toggleQRCode(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        if (element.style.display === 'none' || element.style.display === '') {
            element.style.display = 'block';
        } else {
            element.style.display = 'none';
        }
    }
}
// Helper functions for GCash actions
function copyGCashNumber(number) {
    navigator.clipboard.writeText(number).then(() => {
        showToast('✅ GCash number copied to clipboard!');
    }).catch(() => {
        showToast('Could not copy number');
    });
}

function markPaymentAsSent(messageId, bookingNumber) {
    if (confirm(`📱 Have you sent the downpayment for booking #${bookingNumber}?\n\nAfter confirming, the hiker will be notified and can upload their payment receipt.`)) {
        showToast('✅ Notifying hiker to upload payment receipt...');
        
        // Here you would call an API to:
        // 1. Update the booking payment status to 'awaiting_confirmation'
        // 2. Send a message to the hiker asking for receipt upload
        // 3. Update the payment card status
        
        setTimeout(() => {
            showToast('✅ Reminder sent to hiker. Please wait for receipt upload.');
        }, 1000);
    }
}

function sendQuick(text) {
    document.getElementById('msgInput').value = text;
    sendMessage();
}

async function markAsRead(hikerUserId) {
    try {
        const fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('sender_id', hikerUserId);
        await fetch('../api/guide_messages.php', { method: 'POST', body: fd });
        await loadConversations(true);
    } catch (e) {}
}

function updateSidebarNotificationBadge() {
    // Update the Communication tab in sidebar and bottom nav
    const totalUnread = conversations.reduce((sum, conv) => sum + (conv.unread_count || 0), 0);
    
    // Update sidebar navigation badge
    const sidebarLink = document.querySelector('.sidebar-nav a[href="guide-communication.php"]');
    if (sidebarLink) {
        let badge = sidebarLink.querySelector('.notification-badge');
        if (totalUnread > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                sidebarLink.appendChild(badge);
            }
            badge.textContent = totalUnread > 9 ? '9+' : totalUnread;
            badge.style.cssText = 'position: absolute; right: 12px; top: 8px; background: #dc2626; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 20px; min-width: 18px; text-align: center;';
            sidebarLink.style.position = 'relative';
        } else if (badge) {
            badge.remove();
        }
    }
    
    // Update bottom navigation badge
    const bottomLink = document.querySelector('.guide-bottom-nav a[href="guide-communication.php"]');
    if (bottomLink) {
        let badge = bottomLink.querySelector('.notification-badge');
        if (totalUnread > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                bottomLink.appendChild(badge);
            }
            badge.textContent = totalUnread > 9 ? '9+' : totalUnread;
            badge.style.cssText = 'position: absolute; top: -5px; right: 5px; background: #dc2626; color: white; font-size: 9px; font-weight: 700; padding: 2px 5px; border-radius: 20px; min-width: 16px; text-align: center;';
            bottomLink.style.position = 'relative';
        } else if (badge) {
            badge.remove();
        }
    }
}
let isProcessing = false;

async function handleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId) {
    if (isProcessing) {
        showToast('Please wait, processing...');
        return;
    }
    
    if (!confirm(`Are you sure you want to ${action} this booking request?`)) return;
    
    isProcessing = true;
    showToast('Processing...');
    
    const fd = new FormData();
    fd.append('action', `${action}_booking_request`);
    fd.append('request_id', messageId);
    fd.append('booking_id', bookingId);
    fd.append('hiker_name', hikerName);
    fd.append('hiker_user_id', hikerUserId);
    
    try {
        const res = await fetch('../api/guide_messages.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        showToast(data.message || (data.success ? 'Done!' : 'Failed'));
        if (data.success) {
            // Refresh messages and conversations to update the button state
            setTimeout(async () => {
                if (activeThread) {
                    await loadMessages(activeThread, true);
                }
                await loadConversations();
            }, 600);
        }
    } catch (err) {
        showToast('Network error');
    } finally {
        setTimeout(() => {
            isProcessing = false;
        }, 1000);
    }
}
function viewBookingDetails(bookingId) {
    // Store the current scroll position before navigating away
    if (activeThread) {
        const wrapper = document.getElementById('chatMessagesWrapper');
        if (wrapper) {
            scrollPositions[activeThread] = wrapper.scrollTop;
        }
    }
    
    // Navigate to guide-bookings.php with the booking ID as a parameter
    window.location.href = `guide-bookings.php?view_booking=${bookingId}`;
}

function refreshConversations() {
    loadConversations();
    if (activeThread) loadMessages(activeThread, true);
}

function formatTime(ts) {
    if (!ts) return '';
    const d = new Date(ts);
    const now = new Date();
    const diff = now - d;
    if (diff < 86400000) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (diff < 604800000) return d.toLocaleDateString([], { weekday: 'short' });
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

// Initialize
loadConversations().then(() => {
    // Check if we need to auto-open a specific conversation
    const urlParams = new URLSearchParams(window.location.search);
    const targetUserId = urlParams.get('user_id');
    if (targetUserId) {
        // Find the conversation with this user
        const conv = conversations.find(c => String(c.user_id) === String(targetUserId));
        if (conv) {
            selectThread(conv.user_id, conv.name);
        } else {
            // Fallback if not found in current list
            selectThread(targetUserId, 'Hiker');
        }
    }
});

// Polling for new messages (preserve scroll position)
pollInterval = setInterval(() => {
    if (activeThread) {
        const wrapper = document.getElementById('chatMessagesWrapper');
        const wasAtBottom = wrapper && (wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight < 50);
        loadMessages(activeThread, true).then(() => {
            if (wasAtBottom && wrapper) {
                wrapper.scrollTop = wrapper.scrollHeight;
            }
        });
        loadConversations(true);
    }
}, 10000);
</script>
</body>
</html>