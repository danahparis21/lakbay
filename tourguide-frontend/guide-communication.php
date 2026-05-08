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
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

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

/* Nudge / Reminder Card */
.msg-card.booking-update {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    width: 320px;
    max-width: 100%;
}

.msg-card.booking-update .msg-card-hdr {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
}

.msg-card.booking-update .msg-card-detail {
    font-size: 0.8rem;
    margin-bottom: 6px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}

.msg-card.booking-update .msg-card-detail i {
    width: 16px;
    color: #d97706;
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
  <button onclick="confirmLogout()" style="background:none;border:none;color:var(--ink-5);font-size:0.9rem;padding:8px;cursor:pointer;transition:color 0.15s;display:flex;align-items:center;" title="Logout" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--ink-5)'">
    <i class="fas fa-sign-out-alt"></i>
  </button>
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


<style>
@keyframes slideUpIn {
    from { opacity:0; transform:translateX(-50%) translateY(20px); }
    to   { opacity:1; transform:translateX(-50%) translateY(0); }
}
</style>

<div class="toast" id="toast"></div>

<script>

// ============================================
// WORKING LOGOUT MODAL - USED IN profile & map
// ============================================
function confirmLogout() {
    // Remove any existing modal
    const existingModal = document.getElementById('standaloneLogoutModal');
    if (existingModal) existingModal.remove();
    
    // Create modal container
    const modal = document.createElement('div');
    modal.id = 'standaloneLogoutModal';
    
    // Apply styles directly
    modal.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        background: rgba(0, 0, 0, 0.6) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        z-index: 9999999 !important;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 20px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            margin: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            font-family: 'DM Sans', sans-serif;
        ">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    background: #fef2f2;
                    color: #dc2626;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                ">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1a1a18; margin: 0;">Log out of Guide Portal?</h3>
            </div>
            <div style="margin-bottom: 24px; color: #666; font-size: 0.85rem; line-height: 1.5;">
                You will be redirected to the login page.
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button id="logoutCancelBtn" style="
                    padding: 10px 20px;
                    border-radius: 10px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    cursor: pointer;
                    border: 1px solid #ddd;
                    background: #f0f0f0;
                    color: #666;
                    font-family: inherit;
                ">Cancel</button>
                <button id="logoutConfirmBtn" style="
                    padding: 10px 20px;
                    border-radius: 10px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    cursor: pointer;
                    border: none;
                    background: #dc2626;
                    color: white;
                    font-family: inherit;
                ">Log Out</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Event listeners
    document.getElementById('logoutCancelBtn').onclick = function() { modal.remove(); };
    document.getElementById('logoutConfirmBtn').onclick = function() {
        window.location.href = '../login-and-signup/login.php';
    };
    modal.onclick = function(e) { if (e.target === modal) modal.remove(); };
}

// ============================================
// BEAUTIFUL DYNAMIC CONFIRM MODAL (with input support)
// ============================================
function showConfirmModal(options) {
    const { title, message, confirmText, confirmColor, onConfirm, cancelText = 'Cancel', showInput = false, inputLabel = '', inputPlaceholder = '', inputType = 'text' } = options;
    
    const existingModal = document.getElementById('dynamicConfirmModal');
    if (existingModal) existingModal.remove();
    
    const modalDiv = document.createElement('div');
    modalDiv.id = 'dynamicConfirmModal';
    
    let inputHtml = '';
    if (showInput) {
        inputHtml = `
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 8px;">${inputLabel}</label>
                <input type="${inputType}" id="modalInput" placeholder="${inputPlaceholder}" style="width: 100%; padding: 12px; border: 1.5px solid #e5e7eb; border-radius: 12px; font-size: 14px; outline: none; transition: border-color 0.2s;">
            </div>
        `;
    }
    
    modalDiv.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:999999;">
            <div style="background:white;border-radius:20px;padding:24px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:${confirmColor === '#dc2626' ? '#fef2f2' : (confirmColor === '#059669' ? '#d1fae5' : '#fef3c7')};color:${confirmColor};display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas ${confirmColor === '#dc2626' ? 'fa-exclamation-triangle' : (confirmColor === '#059669' ? 'fa-check-circle' : 'fa-question-circle')}"></i>
                    </div>
                    <h3 style="font-size:1.1rem;font-weight:700;color:#1a1a18;margin:0;">${title}</h3>
                </div>
                <div style="margin-bottom:24px;color:#666;font-size:0.85rem;line-height:1.5;">
                    <p>${message}</p>
                </div>
                ${inputHtml}
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button id="modalCancelBtn" style="padding:10px 20px;border-radius:10px;font-size:0.8rem;font-weight:600;cursor:pointer;border:1px solid #ddd;background:#f0f0f0;color:#666;font-family:inherit;">${cancelText}</button>
                    <button id="modalConfirmBtn" style="padding:10px 20px;border-radius:10px;font-size:0.8rem;font-weight:600;cursor:pointer;border:none;background:${confirmColor};color:white;font-family:inherit;">${confirmText}</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modalDiv);
    
    // Focus input if exists
    if (showInput) {
        const input = document.getElementById('modalInput');
        if (input) setTimeout(() => input.focus(), 100);
    }
    
    document.getElementById('modalCancelBtn').onclick = function() {
        modalDiv.remove();
    };
    
    document.getElementById('modalConfirmBtn').onclick = function() {
        const inputValue = showInput ? document.getElementById('modalInput')?.value : null;
        modalDiv.remove();
        if (onConfirm) onConfirm(inputValue);
    };
    
    // Close when clicking outside
    modalDiv.onclick = function(e) {
        if (e.target === modalDiv) {
            modalDiv.remove();
        }
    };
}

// ============================================
// IMPROVED REJECT PAYMENT PROOF (with modal)
// ============================================
function rejectPaymentProof(bookingNumber, messageId) {
    showConfirmModal({
        title: 'Reject Payment Proof?',
        message: `Are you sure you want to reject the payment proof for booking #${bookingNumber}? The hiker will be notified to resubmit.`,
        confirmText: 'Yes, Reject',
        confirmColor: '#dc2626',
        onConfirm: () => {
            showToast('Rejecting payment proof...');
            
            const fd = new FormData();
            fd.append('action', 'reject_payment');
            fd.append('booking_number', bookingNumber);
            fd.append('message_id', messageId);
            
            fetch('../api/guide_messages.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('❌ Payment rejected. Hiker has been notified.');
                        if (activeThread) loadMessages(activeThread, true);
                    } else {
                        showToast('❌ Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('❌ Network error');
                });
        }
    });
}

// ============================================
// IMPROVED DECLINE BOOKING (with reason input)
// ============================================
function declineBookingWithReason(messageId, action, bookingId, hikerName, hikerUserId) {
    showConfirmModal({
        title: `Decline ${hikerName}'s Booking?`,
        message: `Please provide a reason for declining this booking request.`,
        confirmText: 'Decline Booking',
        confirmColor: '#dc2626',
        showInput: true,
        inputLabel: 'Reason for declining',
        inputPlaceholder: 'e.g., Guide not available for this date',
        onConfirm: (reason) => {
            if (!reason || reason.trim() === '') {
                showToast('Please provide a reason for declining');
                return;
            }
            _proceedHandleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId, reason);
        }
    });
}

// ============================================
// IMPROVED HANDLE BOOKING REQUEST
// ============================================
let isProcessing = false;

async function handleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId) {
    if (isProcessing) {
        showToast('Please wait, processing...');
        return;
    }
    
    if (action === 'accept') {
        showConfirmModal({
            title: 'Accept Booking Request?',
            message: `Payment instructions will be sent to ${hikerName}. The hiker will need to pay a downpayment to confirm.`,
            confirmText: 'Accept & Confirm',
            confirmColor: '#059669',
            onConfirm: () => _proceedHandleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId, '')
        });
    } else {
        declineBookingWithReason(messageId, action, bookingId, hikerName, hikerUserId);
    }
}

function initUnreadBadgePoller() {
    function fetchAndUpdateBadge() {
        fetch('../api/guide_messages.php?action=get_conversations')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var total = (data.conversations || []).reduce(function(s, c) { return s + (c.unread_count || 0); }, 0);
                ['.sidebar-nav a[href="guide-communication.php"]', '.guide-bottom-nav a[href="guide-communication.php"]'].forEach(function(sel, i) {
                    var link = document.querySelector(sel);
                    if (!link) return;
                    link.style.position = 'relative';
                    var badge = link.querySelector('.notif-badge');
                    if (total > 0) {
                        if (!badge) { badge = document.createElement('span'); badge.className = 'notif-badge'; link.appendChild(badge); }
                        badge.textContent = total > 9 ? '9+' : total;
                        badge.style.cssText = i === 0
                            ? 'position:absolute;right:12px;top:8px;background:#dc2626;color:white;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;min-width:18px;text-align:center;'
                            : 'position:absolute;top:-5px;right:5px;background:#dc2626;color:white;font-size:9px;font-weight:700;padding:2px 5px;border-radius:20px;min-width:16px;text-align:center;';
                    } else if (badge) { badge.remove(); }
                });
            }).catch(function() {});
    }
    fetchAndUpdateBadge();
    setInterval(fetchAndUpdateBadge, 30000);
}
initUnreadBadgePoller();
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
            await renderMessages(data.messages || []);  // Add await
    startCountdownTimers();
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
async function renderMessages(messages) {
    const area = document.getElementById('chatMessages');
    
    if (!messages || messages.length === 0) {
        area.innerHTML = `<div class="chat-empty"><div class="chat-empty-icon">💬</div><div>No messages yet</div><p style="font-size:12px;margin-top:8px;">Send a message to start the conversation</p></div>`;
        return;
    }

    let lastDate = '';
    let html = '';
    
    for (const msg of messages) {
      

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

          if (msg.source_type === 'nudge') {
    const reminderMessage = "Hi! Just a friendly reminder about my upcoming hike booking. Let me know if you have any updates! 👋";
    
    // Build additional details if available
    let detailsHtml = '';
    if (msg.booking_number) {
        detailsHtml += `<div class="msg-card-detail"><i class="fas fa-ticket-alt"></i> Booking #${escapeHtml(msg.booking_number)}</div>`;
    }
    if (msg.mountain_name) {
        detailsHtml += `<div class="msg-card-detail"><i class="fas fa-mountain"></i> ${escapeHtml(msg.mountain_name)}</div>`;
    }
    if (msg.hike_date) {
        const hikeDate = new Date(msg.hike_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
        detailsHtml += `<div class="msg-card-detail"><i class="fas fa-calendar"></i> ${escapeHtml(hikeDate)}</div>`;
    }
    
    const card = `
        <div class="msg-card booking-update">
            <div class="msg-card-hdr" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                <span class="msg-card-icon">🔔</span>
                <div class="msg-card-hdr-label">
                    <div class="msg-card-hdr-title">REMINDER</div>
                    <div class="msg-card-hdr-sub">Friendly Reminder from ${escapeHtml(msg.sender_name || 'Hiker')}</div>
                </div>
            </div>
            <div class="msg-card-body">
                <div class="msg-card-desc" style="white-space: pre-line; line-height: 1.5;">${escapeHtml(reminderMessage)}</div>
                ${detailsHtml}
            </div>
        </div>
        <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;
    
    if (isMine) {
        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
    } else {
        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
    }
    continue; // Skip all other message type checks
}
// 0. FIRST CHECK: PAYMENT PROOF SUBMITTED (regular text message, NOT action_data)
if (msg.body && msg.body.includes('PAYMENT PROOF SUBMITTED')) {
    const bodyText = msg.body || '';
    
    const refMatch = bodyText.match(/Reference Number:\s*([^\n]+)/);
    const referenceNumber = refMatch ? refMatch[1] : 'N/A';
    
    // Extract booking number
    const bookingMatch = bodyText.match(/booking #([^\s\.]+)/);
    let bookingNumber = bookingMatch ? bookingMatch[1] : 'N/A';
    bookingNumber = bookingNumber.replace(/[.,;:!?]$/, '');
    
    // Check booking status using the new endpoint
    let showVerifyButtons = true;
    let paymentStatus = 'pending';
    
    try {
        const statusRes = await fetch(`../api/guide_messages.php?action=get_booking_by_number&booking_number=${encodeURIComponent(bookingNumber)}`);
        const statusData = await statusRes.json();
        
        if (statusData.success) {
            paymentStatus = statusData.downpayment_status;
            if (paymentStatus === 'paid' || paymentStatus === 'rejected') {
                showVerifyButtons = false;
            }
        }
    } catch(e) {
        console.error('Error checking booking status:', e);
    }
    
    // IMPROVED: Handle proof_image via separate endpoint
    let proofImageHtml = '';
    if (msg.proof_image && msg.proof_image !== 'NULL' && msg.proof_image !== 'null') {
        const imageUrl = `../api/guide_messages.php?action=get_proof_image&message_id=${msg.id}`;
        
        proofImageHtml = `
            <div class="proof-image-preview" style="margin-top:8px;text-align:center;">
                <img src="${imageUrl}" alt="Payment Proof" style="max-width:100%;max-height:200px;border-radius:12px;border:1px solid #e5e7eb;cursor:pointer;background:#f8f9fa;" 
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\' stroke-width=\'1\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Crect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\' ry=\'2\'%3E%3C/rect%3E%3Ccircle cx=\'8.5\' cy=\'8.5\' r=\'1.5\' fill=\'%23999\'%3E%3C/circle%3E%3Cpolyline points=\'21 15 16 10 5 21\'%3E%3C/polyline%3E%3C/svg%3E'; this.style.objectFit='contain'; this.style.height='100px';"
                     onclick="window.open('${imageUrl}', '_blank')">
                <small style="display:block;margin-top:4px;font-size:9px;color:#9ca3af;">Click to view full image</small>
            </div>
        `;
    } else {
        const imgMatch = bodyText.match(/data:image\/[^;]+;base64,[A-Za-z0-9+/=]+/);
        if (imgMatch) {
            proofImageHtml = `
                <div class="proof-image-preview" style="margin-top:8px;text-align:center;">
                    <img src="${imgMatch[0]}" alt="Payment Proof" style="max-width:100%;max-height:200px;border-radius:12px;border:1px solid #e5e7eb;cursor:pointer;" onclick="window.open('${imgMatch[0]}', '_blank')">
                    <small style="display:block;margin-top:4px;font-size:9px;color:#9ca3af;">Click to view full image</small>
                </div>
            `;
        } else {
            proofImageHtml = '<div style="font-size:12px;color:#6b7280;padding:12px;background:#f9fafb;border-radius:8px;text-align:center;">💰 No image uploaded</div>';
        }
    }
    
    // Determine status badge based on actual booking status
    let statusBadgeHtml = '';
    let paymentActionsHtml = '';
    
    if (paymentStatus === 'paid') {
        statusBadgeHtml = `<span class="payment-status-badge" style="background:#059669;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;color:white;"><i class="fas fa-check-circle"></i> PAYMENT CONFIRMED</span>`;
        paymentActionsHtml = `<div style="text-align:center;padding:10px;background:#d1fae5;border-radius:8px;color:#059669;font-size:12px;font-weight:600;">
            <i class="fas fa-check-circle"></i> This payment has already been verified and confirmed.
        </div>`;
    } else if (paymentStatus === 'rejected') {
        statusBadgeHtml = `<span class="payment-status-badge" style="background:#dc2626;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;color:white;"><i class="fas fa-times-circle"></i> PAYMENT REJECTED</span>`;
        paymentActionsHtml = `<div style="text-align:center;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:12px;font-weight:600;">
            <i class="fas fa-times-circle"></i> This payment was rejected. The hiker has been notified to resubmit.
        </div>`;
    } else {
        statusBadgeHtml = `<span class="payment-status-badge" style="background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;">PENDING VERIFICATION</span>`;
        paymentActionsHtml = `
            <div class="payment-actions" style="display:flex;gap:10px;margin-top:16px;">
                <button class="btn-payment primary" onclick="verifyAndConfirmPayment('${escapeHtml(bookingNumber)}', ${msg.id})" style="flex:1;padding:10px;border-radius:8px;font-size:12px;font-weight:700;border:none;background:#059669;color:white;cursor:pointer;">
                    <i class="fas fa-check-circle"></i> Verify & Confirm Payment
                </button>
                <button class="btn-payment secondary" onclick="rejectPaymentProof('${escapeHtml(bookingNumber)}', ${msg.id})" style="flex:1;padding:10px;border-radius:8px;font-size:12px;font-weight:700;border:1px solid #e5e7eb;background:#f3f4f6;cursor:pointer;">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
            </div>
        `;
    }
    
    const card = `
        <div class="msg-card payment-instructions-card" style="width:360px;max-width:100%;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.1);background:white;">
            <div class="msg-card-hdr" style="background:linear-gradient(135deg, #059669 0%, #047857 100%);padding:12px 16px;display:flex;align-items:center;gap:10px;color:white;">
                <span class="msg-card-icon" style="font-size:20px;">💵</span>
                <div class="msg-card-hdr-label" style="flex:1;">
                    <div class="msg-card-hdr-title" style="font-size:11px;font-weight:800;letter-spacing:0.5px;">PAYMENT PROOF SUBMITTED</div>
                    <div class="msg-card-hdr-sub" style="font-size:10px;opacity:0.85;">Booking #${escapeHtml(bookingNumber)}</div>
                </div>
                ${statusBadgeHtml}
            </div>
            <div class="msg-card-body" style="padding:16px;">
                <div class="payment-detail-row" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e5e7eb;">
                    <span class="payment-label" style="font-size:12px;color:#6b7280;">📝 Reference Number</span>
                    <span class="payment-value" style="font-size:13px;font-weight:700;color:#1f2937;">${escapeHtml(referenceNumber)}</span>
                </div>
                
                <div class="gcash-details" style="background:#f0fdf4;border-radius:12px;padding:12px;margin:12px 0;">
                    <div class="gcash-row" style="display:flex;align-items:center;gap:8px;font-size:12px;padding:4px 0;">
                        <i class="fas fa-receipt" style="color:#059669;width:20px;"></i>
                        <span><strong>Payment Proof:</strong></span>
                    </div>
                    ${proofImageHtml}
                </div>
                
                ${paymentActionsHtml}
            </div>
        </div>
        <div class="msg-time" style="margin-top:4px;">${timeStr}</div>`;
    
    if (isMine) {
        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
    } else {
        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
    }
    continue;
}
        // 0.5. PAYMENT CONFIRMED / REJECTED / RESUBMIT REQUIRED CARDS
if (msg.body && (msg.body.includes('PAYMENT CONFIRMED') || 
                 msg.body.includes('PAYMENT PROOF REJECTED') ||
                 msg.body.includes('PAYMENT REQUIRED AGAIN'))) {
    
    const isConfirmed = msg.body.includes('PAYMENT CONFIRMED');
    const isRejected = msg.body.includes('PAYMENT PROOF REJECTED');
    const isResubmit = msg.body.includes('PAYMENT REQUIRED AGAIN');
    
    let icon = isConfirmed ? '✅' : (isRejected ? '❌' : '💰');
    let title = 'PAYMENT CONFIRMED';
    let bgColor = 'linear-gradient(135deg, #059669 0%, #047857 100%)';
    
    if (isRejected) {
        title = 'PAYMENT REJECTED';
        bgColor = 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)';
    } else if (isResubmit) {
        title = 'PAYMENT REQUIRED AGAIN';
        bgColor = 'linear-gradient(135deg, #d97706 0%, #b45309 100%)';
    }
    
    // Extract booking number
    const bookingMatch = msg.body.match(/booking #([^\s\.]+)/);
    let bookingNumber = bookingMatch ? bookingMatch[1] : 'N/A';
    bookingNumber = bookingNumber.replace(/[.,;:!?]$/, '');
    
    // Build summary rows for key amounts
    let summaryHtml = '';
    if (isConfirmed) {
        const dpVerifiedMatch = msg.body.match(/downpayment of ₱([\d,]+(?:\.\d{2})?)/i);
        const remainingMatch  = msg.body.match(/[Rr]emaining balance[^:]*:\s*₱([\d,]+(?:\.\d{2})?)/);
        if (dpVerifiedMatch) {
            summaryHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #d1fae5;">
                <span style="font-size:12px;color:#059669;font-weight:600;">💰 Downpayment Verified</span>
                <span style="font-size:15px;font-weight:800;color:#059669;">₱${dpVerifiedMatch[1]}</span>
            </div>`;
        }
        if (remainingMatch) {
            summaryHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #e5e7eb;">
                <span style="font-size:12px;color:#6b7280;">📦 Remaining Balance (to guide)</span>
                <span style="font-size:14px;font-weight:700;color:#374151;">₱${remainingMatch[1]}</span>
            </div>`;
        }
    } else if (isRejected) {
        const dpMatch  = msg.body.match(/[Dd]ownpayment [Rr]equired[:\s]*₱([\d,]+(?:\.\d{2})?)/);
        const feeMatch = msg.body.match(/[Gg]uide [Ff]ee[:\s]*₱([\d,]+(?:\.\d{2})?)/);
        if (dpMatch) {
            summaryHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #fee2e2;">
                <span style="font-size:12px;color:#dc2626;font-weight:600;">💰 Downpayment Required</span>
                <span style="font-size:15px;font-weight:800;color:#dc2626;">₱${dpMatch[1]}</span>
            </div>`;
        }
        if (feeMatch) {
            summaryHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #e5e7eb;">
                <span style="font-size:12px;color:#6b7280;">🏔️ Tour Guide Fee</span>
                <span style="font-size:14px;font-weight:700;color:#374151;">₱${feeMatch[1]}</span>
            </div>`;
        }
    }

    // Split body into lines, skip title/booking-number lines already shown in header/summary
    const bodyLinesHtml = msg.body
        .replace(/\*\*/g, '')
        .split('\n')
        .map(l => l.trim())
        .filter(l => l.length > 0)
        .filter(l => !l.match(/^(✅|❌|💰)\s*(DOWNPAYMENT CONFIRMED|PAYMENT REJECTED|PAYMENT PROOF REJECTED|PAYMENT REQUIRED AGAIN)/))
        .filter(l => !l.match(/^[Bb]ooking #/))
        .filter(l => !l.match(/^[Dd]ownpayment [Rr]equired/))
        .filter(l => !l.match(/^[Rr]emaining balance/i))
        .filter(l => !l.match(/^\(Tour Guide Fee/))
        .filter(l => !l.match(/^🏔️ Tour Guide Fee/))
        .map(l => `<div style="padding:3px 0;font-size:13px;color:#374151;line-height:1.6;">${escapeHtml(l)}</div>`)
        .join('');

    const card = `
        <div class="msg-card payment-instructions-card" style="width:360px;max-width:100%;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.1);background:white;">
            <div class="msg-card-hdr" style="background:${bgColor};padding:12px 16px;display:flex;align-items:center;gap:10px;color:white;">
                <span class="msg-card-icon" style="font-size:20px;">${icon}</span>
                <div class="msg-card-hdr-label" style="flex:1;">
                    <div class="msg-card-hdr-title" style="font-size:11px;font-weight:800;letter-spacing:0.5px;">${title}</div>
                    <div class="msg-card-hdr-sub" style="font-size:10px;opacity:0.85;">Booking #${escapeHtml(bookingNumber)}</div>
                </div>
            </div>
            <div class="msg-card-body" style="padding:16px;">
                ${summaryHtml}
                <div style="margin-top:${summaryHtml ? '12px' : '0'};padding-top:${summaryHtml ? '4px' : '0'};">
                    ${bodyLinesHtml}
                </div>
            </div>
        </div>
        <div class="msg-time" style="margin-top:4px;">${timeStr}</div>`;
    
    if (isMine) {
        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
    } else {
        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
    }
    continue;
}


        // THEN Check for action_data messages (payment instructions, booking requests)
        if (msg.action_data && msg.action_data !== 'null' && msg.action_data !== '') {
            try {
                const ad = typeof msg.action_data === 'string' ? JSON.parse(msg.action_data) : msg.action_data;
                
                // 1. Payment Instructions (Guide View - Read Only, No Buttons)
if (ad.type === 'payment_instructions') {
    const gcashNumber = ad.gcash_number || 'Not set';
    const gcashName = ad.gcash_name || 'Not set';
    const bookingNumber = ad.booking_number || 'N/A';
    const totalAmount = ad.total_amount || 'N/A';
    const qrCodeUrl = ad.qr_code_url || '../assets/images/gcash-qr.jpg';
    
    // Calculate guide fee based on hike type
    let guideFee = ad.guide_fee || 0;
    if (!guideFee) {
        guideFee = ad.hike_type === 'overnight' ? 1500 : 801;
    }
    
    // Use stored downpayment_amount if available, otherwise calculate
    let downpayment;
    if (ad.downpayment_amount) {
        downpayment = parseFloat(String(ad.downpayment_amount).replace(/,/g, ''));
    } else {
        downpayment = Math.max(200, Math.round(guideFee * 0.2));
    }
    // Remaining balance: use stored value if available, otherwise calculate
    const remainingBalance = ad.remaining_balance
        ? parseFloat(String(ad.remaining_balance).replace(/,/g, ''))
        : (Number(guideFee) - downpayment);
    
    let deadlineTimestamp = null;
    let deadlineStr = ad.deadline || null;
    
    const confirmationText = msg.body || '';
    const uniqueId = 'qr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4);
    
    let countdownHtml = '';
    if (deadlineStr) {
        // PHP stores deadline in Asia/Manila (UTC+8). Force correct parsing by appending offset.
        const deadlineNormalized = deadlineStr.replace(' ', 'T') + (deadlineStr.includes('+') ? '' : '+08:00');
        deadlineTimestamp = new Date(deadlineNormalized).getTime();
        const uniqueTimerId = 'timer_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        countdownHtml = `
            <div class="countdown-timer" id="${uniqueTimerId}" data-deadline="${deadlineTimestamp}" style="background: #fff3cd; padding: 8px 12px; border-radius: 8px; margin: 10px 0; text-align: center;">
                <div style="font-size: 11px; color: #856404; margin-bottom: 4px;">⏰ DOWNPAYMENT DEADLINE</div>
                <div style="font-size: 16px; font-weight: 700; color: #d97706;" class="countdown-display"></div>
            </div>
        `;
    }
    
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
} else if (ad.payment_status === 'rejected') {
    paymentStatusText = '❌ REJECTED';
    paymentStatusClass = 'overdue';
    paymentStatusBadge = `<span class="payment-status overdue"><i class="fas fa-times-circle"></i> ${paymentStatusText}</span>`;
} else {
    paymentStatusBadge = `<span class="payment-status pending"><i class="fas fa-clock"></i> ${paymentStatusText}</span>`;
}
    
    let confirmationHtml = '';
if (confirmationText && confirmationText.includes('BOOKING CONFIRMED')) {
    const bkNumMatch  = confirmationText.match(/booking #(\S+)/i);
    const dateMatch   = confirmationText.match(/Hike Date:\s*(.+)/);
    const mtnMatch    = confirmationText.match(/Mountain:\s*(.+)/);
    const totalMatch  = confirmationText.match(/Total Amount:\s*\u20b1([\d,]+(?:\.\d{2})?)/);
    const dpMatch     = confirmationText.match(/Downpayment Required:\s*\u20b1([\d,]+(?:\.\d{2})?)/);
    const balMatch    = confirmationText.match(/Remaining Balance:\s*\u20b1([\d,]+(?:\.\d{2})?)/);
    const guideMatch  = confirmationText.match(/confirmed by (.+?)\./);

    const cfBkNum = bkNumMatch ? bkNumMatch[1].replace(/[.,;:!?]$/, '') : (ad.booking_number || '');
    const cfDate  = dateMatch  ? dateMatch[1].trim()  : '';
    const cfMtn   = mtnMatch   ? mtnMatch[1].trim()   : '';
    const cfTotal = totalMatch ? totalMatch[1]         : (ad.total_amount || '');
    const cfDp    = dpMatch    ? dpMatch[1]            : '';
    const cfBal   = balMatch   ? balMatch[1]           : '';
    const cfGuide = guideMatch ? guideMatch[1].trim()  : '';

    const detailRows = [
        cfDate  ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4C5} Hike Date</span><span style="font-weight:700;font-size:12px;">${escapeHtml(cfDate)}</span></div>` : '',
        cfMtn   ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4CD} Mountain</span><span style="font-weight:700;font-size:12px;text-align:right;max-width:58%;">${escapeHtml(cfMtn)}</span></div>` : '',
        cfTotal ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4B0} Total Amount</span><span style="font-weight:700;font-size:13px;">\u{20B1}${escapeHtml(cfTotal)}</span></div>` : '',
        cfDp    ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4B5} Downpayment</span><span style="font-weight:800;font-size:13px;color:#fef08a;">\u{20B1}${escapeHtml(cfDp)}</span></div>` : '',
        cfBal   ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;"><span style="opacity:0.8;font-size:12px;">\u{1F4E6} Remaining Balance</span><span style="font-weight:700;font-size:12px;">\u{20B1}${escapeHtml(cfBal)}</span></div>` : '',
    ].filter(Boolean).join('');

    confirmationHtml = `
        <div style="width:360px;max-width:100%;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(5,150,105,0.25);margin-bottom:10px;">
            <div style="background:linear-gradient(135deg,#059669 0%,#047857 100%);padding:14px 16px;display:flex;align-items:center;gap:12px;color:white;">
                <span style="font-size:28px;">\u{1F389}</span>
                <div style="flex:1;">
                    <div style="font-size:12px;font-weight:800;letter-spacing:0.6px;text-transform:uppercase;">BOOKING CONFIRMED</div>
                    <div style="font-size:10px;opacity:0.85;">Booking #${escapeHtml(cfBkNum)}${cfGuide ? ' &middot; ' + escapeHtml(cfGuide) : ''}</div>
                </div>
                <span style="background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;">\u2713 CONFIRMED</span>
            </div>
            <div style="background:linear-gradient(135deg,#047857 0%,#065f46 100%);color:white;">
                ${detailRows}
            </div>
            <div style="background:#f0fdf4;padding:10px 14px;display:flex;align-items:center;gap:8px;">
                <span style="font-size:14px;">\u23F0</span>
                <span style="font-size:11px;color:#065f46;font-weight:600;">Complete downpayment within 5 hours to secure your booking</span>
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
                    <span class="payment-value amount">${escapeHtml(totalAmount)}</span>
                </div>
                <div class="payment-detail-row">
                    <span class="payment-label">Tour Guide Fee</span>
                    <span class="payment-value">₱${escapeHtml(guideFee)}</span>
                </div>
                <div class="payment-detail-row">
                    <span class="payment-label">Downpayment Required</span>
                    <span class="payment-value amount" style="color:#059669;">₱${downpayment.toFixed(2)}</span>
                </div>
                <div class="payment-detail-row">
                    <span class="payment-label">Remaining Balance (to guide)</span>
                    <span class="payment-value">₱${remainingBalance.toFixed(2)}</span>
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
                
                <div class="info-note" style="background:#fef3c7;border-radius:8px;padding:10px;margin-top:12px;text-align:center;">
                    <span style="font-size:11px;color:#d97706;">⚠️ Downpayment is 20% of guide fee (min ₱200). Hiker must pay this amount to confirm the booking.</span>
                </div>
            </div>
        </div>
        <div class="msg-time">${timeStr}</div>`;
    
    if (isMine) {
        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
    } else {
        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
    }
    continue;
}
                
                // 2. Booking Request Card - Check actual booking status from database
if (ad.type === 'booking_request') {
    const isGuide = String(msg.receiver_id) === String(CURRENT_USER_ID);
    
    // Fetch actual booking status from database
    let bookingStatus = ad.status || 'pending';
    let bookingNumber = ad.booking_number || '';
    
    // If we're the guide, check the actual database status
    if (isGuide) {
        try {
            const statusRes = await fetch(`../api/guide_messages.php?action=get_booking_status&booking_id=${ad.booking_id}`);
            const statusData = await statusRes.json();
            if (statusData.success && statusData.status) {
                bookingStatus = statusData.status;
                // If booking is cancelled, mark as denied in action_data
                if (bookingStatus === 'cancelled') {
                    bookingStatus = 'cancelled';
                }
            }
        } catch(e) {
            console.error('Error fetching booking status:', e);
        }
    }
    
    const handled = (bookingStatus === 'approved' || bookingStatus === 'denied' || bookingStatus === 'cancelled' || bookingStatus === 'active');
    
    let finalStatus = bookingStatus;
    let finalStatusText = '';
    let finalStatusClass = '';
    let showButtons = (isGuide && !handled && bookingStatus === 'pending');
    
    if (finalStatus === 'approved' || finalStatus === 'active') {
        finalStatusText = '✓ BOOKING ACCEPTED & CONFIRMED';
        finalStatusClass = 'approved';
    } else if (finalStatus === 'denied') {
        finalStatusText = '✕ BOOKING DECLINED';
        finalStatusClass = 'denied';
    } else if (finalStatus === 'cancelled') {
        finalStatusText = '✕ BOOKING CANCELLED BY HIKER';
        finalStatusClass = 'denied';
    } else {
        finalStatusText = '⏳ AWAITING YOUR RESPONSE';
        finalStatusClass = 'pending';
    }
    
    let actionsHtml = '';
    if (showButtons) {
        actionsHtml = `
            <div class="join-action-btns">
                <button class="join-btn approve" onclick="handleBookingRequest(${msg.id},'accept','${ad.booking_id}','${escapeHtml(ad.hiker_name)}',${ad.hiker_user_id})">
                    <i class="fas fa-check"></i> Accept & Confirm
                </button>
                <button class="join-btn deny" onclick="handleBookingRequest(${msg.id},'decline','${ad.booking_id}','${escapeHtml(ad.hiker_name)}',${ad.hiker_user_id})">
                    <i class="fas fa-times"></i> Decline
                </button>
            </div>`;
    } else if (handled) {
        actionsHtml = `<div class="join-status-badge ${finalStatusClass}" style="justify-content: center; display: flex; align-items: center; gap: 8px;">
            <i class="fas ${(finalStatus === 'approved' || finalStatus === 'active') ? 'fa-check-circle' : 'fa-times-circle'}"></i>
            ${finalStatusText}
        </div>`;
    }

    const card = `
        <div class="msg-card join-request">
            <div class="msg-card-hdr">
                <span class="msg-card-icon">🏔️</span>
                <div class="msg-card-hdr-label">
                    <div class="msg-card-hdr-title">${showButtons ? 'NEW BOOKING REQUEST' : (finalStatus === 'cancelled' ? 'BOOKING CANCELLED' : 'BOOKING REQUEST')}</div>
                    <div class="msg-card-hdr-sub">${isGuide ? (showButtons ? 'Hiker wants to book a hike' : (finalStatus === 'cancelled' ? 'This booking was cancelled by the hiker' : 'Request processed')) : 'Booking request sent'}</div>
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
                continue;
            }
        } catch (e) {
            console.error('action_data parse error', e, msg.action_data);
        }
    }

        // 3. System Announcement / Booking Confirmation Cards
const isSystemAnnouncement = (msg.is_system_announcement == 1) || (msg.sender_role === 'guide' && msg.is_system_announcement == 1);

if (isSystemAnnouncement || msg.sender_role === 'system') {
    let icon = '📢';
    let title = 'System Announcement';
    let subTitle = 'Official Update';
    let headerColor = 'background: #6b7280;';
    
    // Check if this is a booking confirmation message - render it as a styled card
    if (msg.body && msg.body.includes('BOOKING CONFIRMED')) {
        const bkNumMatch  = msg.body.match(/booking #(\S+)/i);
        const dateMatch   = msg.body.match(/Hike Date:\s*(.+)/);
        const mtnMatch    = msg.body.match(/Mountain:\s*(.+)/);
        const totalMatch  = msg.body.match(/Total Amount:\s*\u20b1([\d,]+(?:\.\d{2})?)/);
        const dpMatch     = msg.body.match(/Downpayment Required:\s*\u20b1([\d,]+(?:\.\d{2})?)/);
        const balMatch    = msg.body.match(/Remaining Balance:\s*\u20b1([\d,]+(?:\.\d{2})?)/);
        const guideMatch  = msg.body.match(/confirmed by (.+?)\./);

        const cfBkNum = bkNumMatch ? bkNumMatch[1].replace(/[.,;:!?]$/, '') : '';
        const cfDate  = dateMatch  ? dateMatch[1].trim()  : '';
        const cfMtn   = mtnMatch   ? mtnMatch[1].trim()   : '';
        const cfTotal = totalMatch ? totalMatch[1]         : '';
        const cfDp    = dpMatch    ? dpMatch[1]            : '';
        const cfBal   = balMatch   ? balMatch[1]           : '';
        const cfGuide = guideMatch ? guideMatch[1].trim()  : '';

        const sysDetailRows = [
            cfDate  ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4C5} Hike Date</span><span style="font-weight:700;font-size:12px;">${escapeHtml(cfDate)}</span></div>` : '',
            cfMtn   ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4CD} Mountain</span><span style="font-weight:700;font-size:12px;text-align:right;max-width:58%;">${escapeHtml(cfMtn)}</span></div>` : '',
            cfTotal ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4B0} Total Amount</span><span style="font-weight:700;font-size:13px;">\u{20B1}${escapeHtml(cfTotal)}</span></div>` : '',
            cfDp    ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,0.12);"><span style="opacity:0.8;font-size:12px;">\u{1F4B5} Downpayment</span><span style="font-weight:800;font-size:13px;color:#fef08a;">\u{20B1}${escapeHtml(cfDp)}</span></div>` : '',
            cfBal   ? `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;"><span style="opacity:0.8;font-size:12px;">\u{1F4E6} Remaining Balance</span><span style="font-weight:700;font-size:12px;">\u{20B1}${escapeHtml(cfBal)}</span></div>` : '',
        ].filter(Boolean).join('');

        const styledCard = `
            <div style="width:360px;max-width:100%;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(5,150,105,0.25);">
                <div style="background:linear-gradient(135deg,#059669 0%,#047857 100%);padding:14px 16px;display:flex;align-items:center;gap:12px;color:white;">
                    <span style="font-size:28px;">\u{1F389}</span>
                    <div style="flex:1;">
                        <div style="font-size:12px;font-weight:800;letter-spacing:0.6px;text-transform:uppercase;">BOOKING CONFIRMED</div>
                        <div style="font-size:10px;opacity:0.85;">Booking #${escapeHtml(cfBkNum)}${cfGuide ? ' &middot; ' + escapeHtml(cfGuide) : ''}</div>
                    </div>
                    <span style="background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;">\u2713 CONFIRMED</span>
                </div>
                <div style="background:linear-gradient(135deg,#047857 0%,#065f46 100%);color:white;">
                    ${sysDetailRows}
                </div>
                <div style="background:#f0fdf4;padding:10px 14px;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:14px;">\u23F0</span>
                    <span style="font-size:11px;color:#065f46;font-weight:600;">Complete downpayment within 5 hours to secure your booking</span>
                </div>
            </div>
            <div class="msg-time">${timeStr}</div>`;
        
        if (isMine) {
            html += `<div class="msg-bubble-row mine"><div>${styledCard}</div></div>`;
        } else {
            html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${styledCard}</div></div>`;
        }
        continue;
    }
    
    // For other system announcements, use the default card
    if (msg.body && msg.body.includes('CANCELLED')) {
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
                <div>${(msg.body || '').replace(/\*\*/g, '').split('\n').map(l => l.trim()).filter(l => l.length > 0).map(l => `<div style="padding:3px 0;font-size:13px;color:#4A5568;line-height:1.6;">${escapeHtml(l)}</div>`).join('')}</div>
            </div>
        </div>
        <div class="msg-time">${timeStr}</div>`;
    
    if (isMine) {
        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
    } else {
        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
    }
    continue;
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
    }  // <-- This closes the for loop (NOT });)

    // After the for loop, set the HTML
    area.innerHTML = html;
    startCountdownTimers();

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
    showConfirmToast({
        title: 'Confirm Payment Sent?',
        message: `Have you sent the downpayment for booking #${bookingNumber}? The hiker will be notified to upload their receipt.`,
        icon: 'fa-mobile-alt',
        iconBg: 'rgba(37,99,235,0.1)',
        iconColor: '#2563eb',
        confirmLabel: 'Yes, I Sent It',
        confirmBg: '#2563eb',
        onConfirm: () => {
            showToast('✅ Notifying hiker to upload payment receipt...');
            setTimeout(() => {
                showToast('✅ Reminder sent to hiker. Please wait for receipt upload.');
            }, 1000);
        }
    });
}

async function sendMessage() {
    const input = document.getElementById('msgInput');
    const body = input ? input.value.trim() : '';
    if (!body || !activeThread) return;

    input.value = '';
    input.disabled = true;

    try {
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('recipient_id', activeThread);  // ✅ This is correct
        fd.append('body', body);

        const res = await fetch('../api/guide_messages.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            await loadMessages(activeThread);  // ❌ This line uses activeThread correctly
            // But note: loadMessages already has activeThread as parameter
        } else {
            showToast('Failed to send message');
            input.value = body;
        }
    } catch (e) {
        showToast('Error sending message');
        input.value = body;
    } finally {
        input.disabled = false;
        input.focus();
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


async function _proceedHandleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId, cancelReason) {
    if (!isProcessing) isProcessing = true;
    
    const fd = new FormData();
    fd.append('action', `${action}_booking_request`);
    fd.append('request_id', messageId);
    fd.append('booking_id', bookingId);
    fd.append('hiker_name', hikerName);
    fd.append('hiker_user_id', hikerUserId);
    if (cancelReason) {
        fd.append('reason', cancelReason);
    }
    
    try {
        const res = await fetch('../api/guide_messages.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            if (action === 'accept') {
                showToast(`✅ Booking ${data.booking_number || bookingId} confirmed! Payment instructions sent.`);
            } else {
                showToast(`❌ Booking declined.`);
            }
            
            // Refresh messages and conversations to update the button state
            setTimeout(async () => {
                if (activeThread) {
                    await loadMessages(activeThread, true);
                }
                await loadConversations();
            }, 600);
        } else {
            showToast(data.message || 'Failed to process request');
        }
    } catch (err) {
        console.error('Error:', err);
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

// Modal variables
let currentVerifyBookingNumber = null;
let currentVerifyMessageId = null;

function verifyAndConfirmPayment(bookingNumber, messageId) {
    currentVerifyBookingNumber = bookingNumber;
    currentVerifyMessageId = messageId;
    
    // Open modal to enter downpayment amount
    openVerifyModal(bookingNumber);
}

function openVerifyModal(bookingNumber) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('verifyPaymentModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'verifyPaymentModal';
        modal.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;';
        modal.innerHTML = `
            <div style="background:white; border-radius:16px; width:90%; max-width:400px; overflow:hidden;">
                <div style="background:linear-gradient(135deg, #059669 0%, #047857 100%);padding:16px 20px;color:white;">
                    <h3 style="margin:0;font-size:16px;">💵 Verify Downpayment</h3>
                    <p style="margin:4px 0 0;font-size:12px;opacity:0.9;">Booking #<span id="verifyBookingNumber"></span></p>
                </div>
                <div style="padding:20px;">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Downpayment Amount</label>
                        <input type="number" id="downpaymentAmount" class="inp" placeholder="₱" style="width:100%;padding:12px;border:1.5px solid #e5e7eb;border-radius:12px;">
                    </div>
                    <p style="font-size:11px;color:#6b7280;margin-bottom:16px;">⚠️ Verify that the amount matches the receipt before confirming.</p>
                    <div style="display:flex;gap:12px;">
                        <button class="btn btn-outline" style="flex:1;" onclick="closeVerifyModal()">Cancel</button>
                        <button class="btn btn-primary" style="flex:1;background:#059669;" onclick="submitVerification()">Confirm Payment</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('verifyBookingNumber').textContent = bookingNumber;
    document.getElementById('downpaymentAmount').value = '';
    modal.style.display = 'flex';
}

function closeVerifyModal() {
    const modal = document.getElementById('verifyPaymentModal');
    if (modal) modal.style.display = 'none';
}

function submitVerification() {
    const amount = document.getElementById('downpaymentAmount').value.trim();
    
    if (!amount) {
        showToast('❌ Please enter the downpayment amount');
        return;
    }
    
    closeVerifyModal();
    
    showToast('Verifying payment...');
    
    const fd = new FormData();
    fd.append('action', 'verify_payment');
    fd.append('booking_number', currentVerifyBookingNumber);
    fd.append('message_id', currentVerifyMessageId);
    fd.append('downpayment_amount', amount);
    
    fetch('../api/guide_messages.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Payment confirmed! Hiker has been notified.');
                if (activeThread) loadMessages(activeThread, true);
            } else {
                showToast('❌ Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            showToast('❌ Network error');
        });
}

</script>
</body>
</html>