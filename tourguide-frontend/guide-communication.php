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
    .thread-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: rgba(16,6,0,0.1);
      color: #100600;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 0.9rem;
      font-weight: 700;
      flex-shrink: 0;
      position: relative;
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
    .chat-header-sub { font-size: 0.68rem; color: var(--ink-4); margin-top: 1px; }
    .chat-header-actions { display: flex; gap: 8px; }

    /* CHAT MESSAGES - takes remaining space */
    .chat-messages-wrapper {
      flex: 1;
      overflow-y: auto;
      min-height: 0;
    }
    .chat-messages {
      padding: 18px 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .msg-group { display: flex; flex-direction: column; gap: 4px; max-width: 85%; }
    .msg-group.mine { align-items: flex-end; margin-left: auto; }
    .msg-group.theirs { align-items: flex-start; margin-right: auto; }

    .msg-sender {
      font-size: 0.65rem;
      font-weight: 600;
      color: var(--ink-4);
      padding: 0 12px;
      margin-bottom: 2px;
    }
    .msg-bubble {
      padding: 10px 14px;
      border-radius: 18px;
      font-size: 0.85rem;
      line-height: 1.5;
      word-break: break-word;
    }
    .msg-group.mine .msg-bubble {
      background: #100600;
      color: white;
      border-radius: 18px 18px 4px 18px;
    }
    .msg-group.theirs .msg-bubble {
      background: white;
      color: var(--ink-2);
      border-radius: 18px 18px 18px 4px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .msg-time {
      font-size: 0.6rem;
      color: var(--ink-5);
      padding: 0 12px;
      margin-top: 2px;
    }

    /* Date divider */
    .date-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 8px 0;
      width: 100%;
    }
    .date-divider span {
      font-size: 0.65rem;
      font-weight: 600;
      color: var(--ink-4);
      font-family: 'DM Mono', monospace;
      white-space: nowrap;
    }
    .date-divider::before, .date-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--line);
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
      <div class="sidebar-avatar"><?= $initials ?></div>
      <div class="sidebar-profile-info"><div class="sidebar-profile-name"><?= htmlspecialchars($guide['name']) ?></div><div class="sidebar-profile-role"><?= htmlspecialchars($guide['specialization'] ?? 'Trail Guide') ?></div></div>
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
        area.innerHTML = `<div class="chat-empty"><i class="fas fa-comment-dots"></i><p>No messages yet. Send a message to start the conversation!</p></div>`;
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
        
        // Date separator
        if (dateStr !== lastDate) {
            html += `<div class="date-divider"><span>${msgDate.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' })}</span></div>`;
            lastDate = dateStr;
        }
        
        const side = isMine ? 'mine' : 'theirs';
        
        // Check for booking request cards (action_data)
        if (msg.action_data && msg.action_data !== 'null' && msg.action_data !== '') {
            try {
                const ad = typeof msg.action_data === 'string' ? JSON.parse(msg.action_data) : msg.action_data;
                
                if (ad.type === 'booking_request') {
                    const status = ad.status || 'pending';
                    let actionsHtml = '';
                    
                    if (!isMine && status === 'pending') {
                        actionsHtml = `
                            <div class="brc-actions">
                                <button class="btn btn-outline btn-sm" onclick="handleBookingRequest(${msg.id}, 'decline', '${ad.booking_id}', '${escapeHtml(ad.hiker_name)}', ${ad.hiker_user_id})">Decline</button>
                                <button class="btn btn-primary btn-sm" onclick="handleBookingRequest(${msg.id}, 'accept', '${ad.booking_id}', '${escapeHtml(ad.hiker_name)}', ${ad.hiker_user_id})"><i class="fas fa-check"></i> Accept</button>
                            </div>
                        `;
                    } else if (status === 'approved') {
                        actionsHtml = `
                            <div class="join-status-badge approved" style="margin-bottom: 12px;">✓ Booking Accepted</div>
                            <div class="brc-actions">
                                <button class="btn btn-primary btn-sm" onclick="viewBookingDetails('${ad.booking_id}')" style="width: 100%;">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        `;
                    } else if (status === 'declined') {
                        actionsHtml = `<div class="join-status-badge denied">✕ Booking Declined</div>`;
                    } else if (!isMine && status === 'pending') {
                        actionsHtml = `<div class="join-status-badge pending">⏳ Pending Response</div>`;
                    }
                    
                    html += `
                        <div class="msg-group ${side}">
                            ${!isMine ? `<div class="msg-sender">${escapeHtml(msg.sender_name || 'Hiker')}</div>` : ''}
                            <div class="booking-request-card">
                                <div class="brc-header"><i class="fas fa-calendar-check"></i> Booking Request</div>
                                <div class="brc-row"><span class="brc-label">Mountain</span><span class="brc-val">${escapeHtml(ad.mountain_name || 'Unknown')}</span></div>
                                <div class="brc-row"><span class="brc-label">Date</span><span class="brc-val">${escapeHtml(ad.booking_date || 'N/A')}</span></div>
                                <div class="brc-row"><span class="brc-label">Hikers</span><span class="brc-val">${ad.pax || 1} person(s)</span></div>
                                <div class="brc-row"><span class="brc-label">Booking</span><span class="brc-val">#${escapeHtml(ad.booking_number || 'N/A')}</span></div>
                                ${actionsHtml}
                            </div>
                            <div class="msg-time">${timeStr}</div>
                        </div>
                    `;
                    return;
                }
            } catch (e) {
                console.error('Error parsing action_data', e);
            }
        }
        
        // Normal text message
        const bubbleClass = isMine ? 'msg-bubble mine' : 'msg-bubble theirs';
        html += `
            <div class="msg-group ${side}">
                ${!isMine ? `<div class="msg-sender">${escapeHtml(msg.sender_name || 'Hiker')}</div>` : ''}
                <div class="${bubbleClass}">${escapeHtml(msg.body || '')}</div>
                <div class="msg-time">${timeStr}</div>
            </div>
        `;
    });
    
    area.innerHTML = html;
}

async function sendMessage() {
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    if (!msg || !activeThread) return;
    
    const btn = document.querySelector('.chat-send-btn');
    input.disabled = true;
    btn.disabled = true;
    
    // Save current scroll position
    const wrapper = document.getElementById('chatMessagesWrapper');
    const wasAtBottom = wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight < 50;
    
    try {
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('recipient_id', activeThread);
        fd.append('body', msg);
        
        const res = await fetch('../api/guide_messages.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            input.value = '';
            await loadMessages(activeThread, true);
            
            // If user was at bottom, scroll to bottom after message loads
            if (wasAtBottom) {
                setTimeout(() => {
                    wrapper.scrollTop = wrapper.scrollHeight;
                }, 50);
            }
            
            await loadConversations(true);
            input.focus();
        } else {
            showToast(data.message || 'Failed to send message');
        }
    } catch (e) {
        showToast('Network error');
    } finally {
        input.disabled = false;
        btn.disabled = false;
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

async function handleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId) {
    if (!confirm(`Are you sure you want to ${action} this booking request?`)) return;
    
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
            setTimeout(() => {
                if (activeThread) loadMessages(activeThread, true);
                loadConversations();
            }, 600);
        }
    } catch (err) {
        showToast('Network error');
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