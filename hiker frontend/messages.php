<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is a hiker - try both possible session keys
$isHiker = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'hiker') {
    $isHiker = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hiker') {
    $isHiker = true;
}

if (!$isHiker) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$hikerId = $_SESSION['user_id'];
$hikerName = 'Hiker';
$userAvatar = null;

// Always fetch fresh data from DB to ensure name/avatar are correct
if (isset($pdo) && $pdo) {
    $stmt = $pdo->prepare("SELECT name, avatar FROM users WHERE id = ?");
    $stmt->execute([$hikerId]);
    $user = $stmt->fetch();
    if ($user) {
        $hikerName = $user['name'];
        $userAvatar = $user['avatar'];
    }
}

// Get user initials for avatar fallback (e.g. "Paris Hiker" -> "PH")
$nameParts = explode(' ', trim($hikerName));
$userInitial = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $userInitial .= strtoupper(substr($part, 0, 1));
    }
}
$userInitial = substr($userInitial, 0, 2);

// Check if guide parameter is passed
$preselectedGuide = isset($_GET['guide']) ? intval($_GET['guide']) : null;
$preselectedGuideName = isset($_GET['guide_name']) ? $_GET['guide_name'] : null;

$apiUrl = '../api/hiker_messages.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY — Messages</title>
<link rel="stylesheet" href="shared.css">
 <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
<style>
/* ===== PRESERVED ORIGINAL STYLES - NO COLOR CHANGES ===== */
.messages-layout {
  display: grid;
  grid-template-columns: 350px 1fr;
  position: fixed;
  top: 74px;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: hidden;
  background: var(--white);
  z-index: 800;
}
@media(max-width:768px){
  .messages-layout { 
    grid-template-columns: 1fr; 
    top: 0;
    bottom: 80px;
    margin-top: 0;
    position: fixed;
    inset: 0 0 80px 0;
  }
  .msg-sidebar { 
    position: absolute;
    inset: 0;
    z-index: 10;
    transition: transform 0.3s ease;
  }
  .msg-sidebar.hidden-mobile { 
    transform: translateX(-100%);
    pointer-events: none;
  }
  .msg-main { 
    position: absolute;
    inset: 0;
    z-index: 5;
    display: flex; 
    flex-direction: column; 
  }
}
.msg-sidebar {
  border-right: 1px solid rgba(90,122,90,0.12);
  display: flex; flex-direction: column;
  background: var(--white);
  min-height: 0;
}
.msg-sidebar-hdr {
  padding: 30px 20px 16px;
  border-bottom: 1px solid rgba(90,122,90,0.1);
  flex-shrink: 0;
}
.msg-sidebar-hdr h2 { font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 600; color: var(--forest); margin-bottom: 12px; }
.msg-search {
  display: flex; align-items: center; gap: 8px;
  background: var(--sky); border-radius: 50px; padding: 10px 16px;
}
.msg-search svg { color: var(--stone); flex-shrink: 0; }
.msg-search input { flex: 1; border: none; background: transparent; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--forest); outline: none; }
.msg-list { overflow-y: auto; flex: 1; }
.msg-thread {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 20px; cursor: pointer;
  border-bottom: 1px solid rgba(90,122,90,0.06);
  transition: background .15s; position: relative;
}
.msg-thread:hover { background: var(--sky); }
.msg-thread.active { background: rgba(26,46,26,0.05); }
.msg-thread-av {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--forest); color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700; flex-shrink: 0; position: relative;
}
.system-thread .msg-thread-av { background: #F5A623; }
.msg-thread-info { flex: 1; min-width: 0; }
.msg-thread-name { font-weight: 700; font-size: 13px; color: var(--forest); margin-bottom: 2px; }
.msg-thread-preview { font-size: 12px; color: var(--stone); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-thread-meta { text-align: right; flex-shrink: 0; }
.msg-thread-time { font-size: 10px; color: var(--stone); font-family: 'DM Mono', monospace; margin-bottom: 4px; }
.msg-unread {
  background: var(--forest); color: var(--cream);
  border-radius: 50px; font-size: 10px; font-weight: 700;
  padding: 2px 7px; display: inline-block;
}
.system-badge {
  display: inline-block;
  background: #F5A623;
  color: white;
  font-size: 9px;
  padding: 2px 8px;
  border-radius: 12px;
  margin-left: 8px;
  vertical-align: middle;
}
.msg-main { 
  display: flex; flex-direction: column; 
  background: var(--cream); 
  height: 100%;
  overflow: hidden;
  min-height: 0;
}
.msg-main-hdr {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 24px;
  background: var(--white);
  border-bottom: 1px solid rgba(90,122,90,0.1);
  flex-shrink: 0;
}
.msg-back-btn {
  display: none;
  background: none; border: none; cursor: pointer;
  color: var(--forest); padding: 4px;
}
@media(max-width:768px){ .msg-back-btn { display: flex; } }
.msg-hdr-info { flex: 1; }
.msg-hdr-name { font-weight: 700; font-size: 15px; color: var(--forest); }
.msg-hdr-sub { font-size: 11px; color: var(--sage); }

/* ===== IMPROVED CHAT AREA - RESPONSIVE BUBBLES, ALWAYS VISIBLE SEND BAR ===== */
.chat-area {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  /* Ensure smooth scrolling */
  scroll-behavior: smooth;
}
/* Responsive message bubbles - adjust max-width on different screens */
.msg-bubble-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-bubble-row.mine { flex-direction: row-reverse; }
.msg-av-xs {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--forest); color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700; flex-shrink: 0;
}
.msg-bubble {
  max-width: min(85%, 550px);
  padding: 12px 18px;
  border-radius: 20px;
  font-size: 14px;
  line-height: 1.65;
  word-break: break-word;
  width: auto;
}
@media(min-width: 1024px) {
  .msg-bubble {
    font-size: 15px;
    padding: 14px 24px;
    max-width: min(85%, 750px);
    min-width: 280px;
  }
}
@media(max-width: 480px) {
  .msg-bubble {
    max-width: min(85%, 750px);
    padding: 10px 14px;
    font-size: 13px;
    min-width: 180px;
  }
}
.msg-bubble.theirs {
  background: var(--white);
  color: var(--forest);
  border-radius: 4px 20px 20px 20px;
  box-shadow: 0 2px 8px rgba(26,46,26,0.08);
}
.msg-bubble.mine {
  background: var(--forest);
  color: var(--cream);
  border-radius: 20px 4px 20px 20px;
}
.msg-time { font-size: 10px; color: var(--stone); margin-top: 4px; text-align: right; font-family: 'DM Mono', monospace; }
.sys-msg {
  text-align: center; font-size: 11px; color: var(--stone);
  background: rgba(90,122,90,0.08);
  border-radius: 50px; padding: 6px 16px;
  align-self: center;
}
.system-announcement {
  background: #FFF9E6;
  border-left: 3px solid #F5A623;
  padding: 14px 16px;
  margin: 8px 0;
  border-radius: 12px;
}
.system-announcement-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.system-announcement-title {
  font-weight: 600;
  color: #D48C1A;
  font-size: 12px;
}
.system-announcement-time {
  font-size: 10px;
  color: #8A99AE;
}
.system-announcement-body {
  color: #4A4A4A;
  line-height: 1.5;
  font-size: 13px;
}

/* ===== SEND BAR - ALWAYS VISIBLE & CLICKABLE (FIXED POSITION BEHAVIOR) ===== */
.chat-input-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 24px;
  background: var(--white);
  border-top: 1px solid rgba(90,122,90,0.1);
  flex-shrink: 0;
  /* Ensure it stays above content */
  position: relative;
  z-index: 10;
}
@media(max-width: 768px) {
  .chat-input-row {
    padding: 12px 16px;
    padding-bottom: max(12px, env(safe-area-inset-bottom, 12px));
  }
}
.chat-input {
  flex: 1;
  padding: 12px 18px;
  border-radius: 50px;
  border: 1.5px solid rgba(90,122,90,0.15);
  background: var(--sky);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px;
  color: var(--forest);
  outline: none;
  transition: .2s;
}
.chat-input:focus { border-color: var(--sage); background: var(--white); }
.send-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--forest);
  color: var(--cream);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform .15s;
  flex-shrink: 0;
}
.send-btn:hover { transform: scale(1.08); }
.send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.chat-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--stone);
  padding: 40px;
  text-align: center;
}
.chat-empty-icon { font-size: 48px; margin-bottom: 16px; }
.loading-spinner {
  text-align: center;
  padding: 40px;
  color: #8A99AE;
}
.encryption-badge {
  font-size: 10px;
  color: #8A99AE;
  margin-left: 8px;
  font-family: monospace;
}

/* ── MESSAGE CARDS (PRESERVED PERFECTLY) ── */
.msg-card {
  width: 320px;
  max-width: 100%;
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0,0,0,0.06);
  margin: 6px 0;
  border: 1px solid rgba(0,0,0,0.08);
  text-align: left;
}
@media(min-width: 1024px) {
  .msg-card {
    width: 380px;
  }
  .msg-card-body {
    font-size: 14px;
    padding: 16px;
  }
}
@media(max-width: 480px) {
  .msg-card {
    width: 280px;
  }
}
.msg-card-hdr {
  padding: 10px 14px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: white;
}
.msg-card-body {
  padding: 14px;
  font-size: 13px;
  color: var(--forest);
  line-height: 1.5;
}
.msg-card.booking .msg-card-hdr { background: linear-gradient(135deg, #1b5e20, #2e7d32); }
.msg-card.cancel .msg-card-hdr { background: linear-gradient(135deg, #b71c1c, #c62828); }
.msg-card.nudge .msg-card-hdr { background: linear-gradient(135deg, #f57f17, #f9a825); }
.msg-card-icon { font-size: 14px; }
.msg-card-detail { 
  margin-top: 6px; 
  font-size: 12px; 
  color: var(--sage); 
  display: flex;
  align-items: center;
  gap: 6px;
}
.msg-card-detail i { width: 14px; text-align: center; opacity: 0.7; }

.user-btn {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--forest);
  color: var(--cream);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  text-decoration: none;
  background-size: cover;
  background-position: center;
}
.avatar-small {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background-size: cover;
  background-position: center;
}
</style>
</head>
<body>


<?php
// Set current page for navbar highlighting
$currentPage = 'messages'; // Change per page: 'explore', 'bookings', 'quiz', 'messages', 'hikerProfile'
?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- MESSAGES LAYOUT -->
<div class="messages-layout">
  <div class="msg-sidebar" id="msgSidebar">
    <div class="msg-sidebar-hdr">
      <h2>Messages <span class="encryption-badge">🔒 AES-256</span></h2>
      <div class="msg-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" placeholder="Search conversations..." id="msgSearch" oninput="filterThreads(this.value)">
      </div>
    </div>
    <div class="msg-list" id="msgList">
      <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading conversations...</div>
    </div>
  </div>

  <div class="msg-main" id="msgMain">
    <div class="chat-empty" id="chatEmpty">
      <div class="chat-empty-icon">💬</div>
      <div style="font-weight:600;font-size:15px;color:var(--forest);margin-bottom:8px;">Your messages</div>
      <p style="font-size:13px;line-height:1.6;">Select a conversation to start chatting with your tour guide.</p>
      <p style="font-size:11px;color:var(--stone);margin-top:12px;">🔒 All messages are end-to-end encrypted</p>
    </div>
    <div id="chatView" style="display:none;flex-direction:column;flex:1;min-height:0;">
      <div class="msg-main-hdr">
        <button class="msg-back-btn" onclick="showSidebar()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        </button>
        <div class="msg-thread-av" id="chatHdrAv" style="width:40px;height:40px;font-size:14px;flex-shrink:0;border-radius:50%;background:var(--forest);color:var(--cream);display:flex;align-items:center;justify-content:center;font-weight:700;"></div>
        <div class="msg-hdr-info">
          <div class="msg-hdr-name" id="chatHdrName"></div>
          <div class="msg-hdr-sub" id="chatHdrSub"></div>
        </div>
      </div>
      <div class="chat-area" id="chatArea"></div>
      <div class="chat-input-row">
        <input class="chat-input" type="text" id="chatInput" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendMsg()" autocomplete="off">
        <button class="send-btn" onclick="sendMsg()" id="sendMsgBtn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
// ===== LIVE DATABASE CONNECTION - NO MOCK DATA =====
let conversations = [];
let activeThread = null;
let activeThreadType = 'guide';
let pollInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadConversations();

    // Check for preselected guide from URL
    let urlParams = new URLSearchParams(window.location.search);
    let preselectedGuide = urlParams.get('guide');
    let preselectedGuideName = urlParams.get('guide_name');

    if (preselectedGuide && preselectedGuideName) {
        setTimeout(() => {
            const existingConv = conversations.find(c => c.id == preselectedGuide);
            if (existingConv) {
                openThread(preselectedGuide, 'guide');
            } else {
                conversations.push({
                    id: parseInt(preselectedGuide),
                    name: preselectedGuideName,
                    type: 'guide',
                    last_message: 'Start your conversation here',
                    unread_count: 0
                });
                renderConversations();
                openThread(preselectedGuide, 'guide');
            }
        }, 500);
    }
    
    // Poll every 10 seconds for new messages
    pollInterval = setInterval(() => {
        if (activeThread && activeThreadType === 'guide') {
            loadMessages(activeThread, true);
        }
        if (activeThread && activeThreadType === 'guide') {
            loadConversations(true);
        }
    }, 10000);
});

async function loadConversations(silent = false) {
    try {
        const response = await fetch('../api/hiker_messages.php?action=get_conversations');
        const data = await response.json();
        
        if (data.success) {
            conversations = data.conversations || [];
            if (!silent) {
                renderConversations();
            } else {
                updateUnreadCounts();
            }
        } else if (!silent) {
            document.getElementById('msgList').innerHTML = '<div class="loading-spinner">Error loading conversations</div>';
        }
    } catch (error) {
        if (!silent) {
            console.error('Error loading conversations:', error);
            document.getElementById('msgList').innerHTML = '<div class="loading-spinner">Error loading conversations</div>';
        }
    }
}

function updateUnreadCounts() {
    const threads = document.querySelectorAll('.msg-thread');
    threads.forEach(thread => {
        const threadId = thread.getAttribute('data-thread-id');
        if (threadId) {
            const conv = conversations.find(c => c.id == threadId);
            if (conv && conv.unread_count > 0) {
                const metaDiv = thread.querySelector('.msg-thread-meta');
                if (metaDiv && !metaDiv.querySelector('.msg-unread')) {
                    const unreadBadge = document.createElement('div');
                    unreadBadge.className = 'msg-unread';
                    unreadBadge.textContent = conv.unread_count;
                    metaDiv.appendChild(unreadBadge);
                }
            }
        }
    });
}

function renderConversations() {
    const container = document.getElementById('msgList');
    const searchTerm = document.getElementById('msgSearch').value.toLowerCase();
    
    const filtered = conversations.filter(conv => 
        conv.name && conv.name.toLowerCase().includes(searchTerm)
    );
    
    if (filtered.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:40px;color:#B0BAC8;">
                <i class="fas fa-comments" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                No conversations yet
                <p style="font-size: 12px; margin-top: 8px;">Book a tour to start messaging your guide</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = filtered.map(conv => {
        const isSystem = conv.type === 'system';
        const isActive = activeThread === (isSystem ? 'system' : conv.id) && activeThreadType === (isSystem ? 'system' : 'guide');
        return `
            <div class="msg-thread ${isSystem ? 'system-thread' : ''} ${isActive ? 'active' : ''}" 
                 data-thread-id="${conv.id}"
                 onclick="openThread('${isSystem ? 'system' : conv.id}', '${isSystem ? 'system' : 'guide'}')">
                <div class="msg-thread-av">
                    ${isSystem ? '📢' : (conv.name ? conv.name.charAt(0).toUpperCase() : '?')}
                </div>
                <div class="msg-thread-info">
                    <div class="msg-thread-name">
                        ${escapeHtml(conv.name || 'Unknown')}
                        ${isSystem ? '<span class="system-badge">Announcements</span>' : ''}
                    </div>
                    <div class="msg-thread-preview">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                </div>
                <div class="msg-thread-meta">
                    <div class="msg-thread-time">${formatTime(conv.last_message_time)}</div>
                    ${conv.unread_count > 0 ? `<div class="msg-unread">${conv.unread_count}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

async function openThread(id, type) {
    activeThread = id;
    activeThreadType = type;
    
    const conv = conversations.find(c => {
        if (type === 'system') return c.type === 'system';
        return c.id == id;
    });
    
    document.getElementById('chatEmpty').style.display = 'none';
    const chatView = document.getElementById('chatView');
    chatView.style.display = 'flex';
    
    if (conv) {
        document.getElementById('chatHdrAv').innerHTML = conv.type === 'system' ? '📢' : (conv.name ? conv.name.charAt(0).toUpperCase() : '?');
        document.getElementById('chatHdrName').innerHTML = escapeHtml(conv.name || 'System');
        document.getElementById('chatHdrSub').innerHTML = conv.type === 'system' ? '📢 Official Announcements & Alerts' : (conv.role || 'Tour Guide');
    }
    
    document.getElementById('chatInput').value = '';
    
    if (type === 'guide') {
        await loadMessages(id);
    } else if (type === 'system') {
        await loadAnnouncements();
    }
    
    if (window.innerWidth <= 768) {
        document.getElementById('msgSidebar').classList.add('hidden-mobile');
        document.getElementById('msgMain').style.zIndex = '20';
    }
}

async function loadMessages(guideId, silent = false) {
    const chatArea = document.getElementById('chatArea');
    if (!silent) {
        chatArea.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
    }
    
    try {
        const response = await fetch(`../api/hiker_messages.php?action=get_messages&guide_id=${guideId}`);
        const data = await response.json();
        
        if (data.success) {
            if (!silent || (data.messages && data.messages.length > 0)) {
                renderMessages(data.messages || []);
            }
            await markAsRead(guideId);
        } else if (!silent) {
            chatArea.innerHTML = '<div class="chat-empty">Error loading messages. Please try again.</div>';
        }
    } catch (error) {
        if (!silent) {
            console.error('Error loading messages:', error);
            chatArea.innerHTML = '<div class="chat-empty">Error loading messages. Please try again.</div>';
        }
    }
}

function renderMessages(messages) {
    const chatArea = document.getElementById('chatArea');
    
    if (!messages || messages.length === 0) {
        chatArea.innerHTML = `
            <div class="chat-empty">
                <div class="chat-empty-icon">💬</div>
                <div>No messages yet</div>
                <p style="font-size:12px;margin-top:8px;">Send a message to your guide to start the conversation</p>
            </div>
        `;
        return;
    }
    
    let lastDate = '';
    chatArea.innerHTML = messages.map(msg => {
        const isMine = msg.sender_role === 'hiker';
        const msgDate = new Date(msg.created_at);
        const dateStr = msgDate.toLocaleDateString();
        const timeStr = msgDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        let dateHtml = '';
        if (dateStr !== lastDate) {
            dateHtml = `<div class="sys-msg">${msgDate.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' })}</div>`;
            lastDate = dateStr;
        }
        
        let finalContent = '';
        const messageBody = msg.body || '';
        const rawBody = messageBody;
        
        // Preserved booking card detection
        if (rawBody.includes('I just booked a hike with you!')) {
            const mtn = rawBody.match(/Mountain: (.*?),/)?.[1] || 'Mountain';
            const date = rawBody.match(/Date: (.*?),/)?.[1] || 'Date';
            const pax = rawBody.match(/Hikers: (.*?) persons/)?.[1] || '?';
            finalContent = `
                <div class="msg-card booking">
                    <div class="msg-card-hdr"><i class="fas fa-mountain msg-card-icon"></i> New Booking</div>
                    <div class="msg-card-body">
                        <strong>${escapeHtml(mtn)}</strong>
                        <div class="msg-card-detail"><i class="far fa-calendar-alt"></i> ${escapeHtml(date)}</div>
                        <div class="msg-card-detail"><i class="fas fa-users"></i> ${escapeHtml(pax)} hikers</div>
                    </div>
                </div>
            `;
        } else if (rawBody.includes('I had to cancel my booking')) {
            const mtn = rawBody.match(/for (.*?) on/)?.[1] || 'Mountain';
            const date = rawBody.match(/on (.*?)\./)?.[1] || 'Date';
            finalContent = `
                <div class="msg-card cancel">
                    <div class="msg-card-hdr"><i class="fas fa-times-circle msg-card-icon"></i> Booking Cancelled</div>
                    <div class="msg-card-body">
                        <strong>${escapeHtml(mtn)}</strong>
                        <div class="msg-card-detail"><i class="far fa-calendar-alt"></i> ${escapeHtml(date)}</div>
                    </div>
                </div>
            `;
        } else if (rawBody.includes('friendly reminder about my upcoming hike booking')) {
            finalContent = `
                <div class="msg-card nudge">
                    <div class="msg-card-hdr"><i class="fas fa-bell msg-card-icon"></i> Nudge Reminder</div>
                    <div class="msg-card-body">Hiker is asking for an update on their booking. 👋</div>
                </div>
            `;
        } else {
            finalContent = `<div class="msg-bubble ${isMine ? 'mine' : 'theirs'}">${escapeHtml(messageBody)}</div>`;
        }
        
        const senderInitial = !isMine && msg.sender_name ? msg.sender_name.charAt(0).toUpperCase() : 'G';
        
        return dateHtml + `
            <div class="msg-bubble-row ${isMine ? 'mine' : ''}">
                ${!isMine ? `<div class="msg-av-xs">${senderInitial}</div>` : ''}
                <div>
                    ${finalContent}
                    <div class="msg-time">${timeStr}</div>
                </div>
            </div>
        `;
    }).join('');
    
    // Auto-scroll to bottom
    chatArea.scrollTop = chatArea.scrollHeight;
}

async function loadAnnouncements() {
    const chatArea = document.getElementById('chatArea');
    chatArea.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading announcements...</div>';
    
    try {
        const response = await fetch('../api/hiker_messages.php?action=get_announcements');
        const data = await response.json();
        
        if (data.success) {
            renderAnnouncements(data.announcements || []);
        } else {
            chatArea.innerHTML = '<div class="chat-empty">Error loading announcements. Please try again.</div>';
        }
    } catch (error) {
        console.error('Error loading announcements:', error);
        chatArea.innerHTML = '<div class="chat-empty">Error loading announcements. Please try again.</div>';
    }
}

function renderAnnouncements(announcements) {
    const chatArea = document.getElementById('chatArea');
    if (!announcements || announcements.length === 0) {
        chatArea.innerHTML = `<div class="chat-empty"><div class="chat-empty-icon">📢</div><div>No announcements yet</div></div>`;
        return;
    }
    chatArea.innerHTML = announcements.map(announcement => `
        <div class="system-announcement">
            <div class="system-announcement-header">
                <span class="system-announcement-title"><i class="fas fa-bullhorn"></i> LAKBAY System Announcement</span>
                <span class="system-announcement-time">${formatDateTime(announcement.created_at)}</span>
            </div>
            <div class="system-announcement-body">${escapeHtml(announcement.body)}</div>
        </div>
    `).join('');
    chatArea.scrollTop = chatArea.scrollHeight;
}

async function sendMsg() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message || !activeThread || activeThreadType !== 'guide') {
        if (activeThreadType === 'system') {
            alert('You cannot reply to system announcements. Please message your guide directly.');
        }
        return;
    }
    
    const sendBtn = document.getElementById('sendMsgBtn');
    input.disabled = true;
    sendBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('guide_id', activeThread);
        formData.append('body', message);
        
        const response = await fetch('../api/hiker_messages.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            await loadMessages(activeThread);
            await loadConversations();
            input.focus();
        } else {
            alert('Failed to send message: ' (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Failed to send message. Please try again.');
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
    }
}

async function markAsRead(guideId) {
    try {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('guide_id', guideId);
        await fetch('../api/hiker_messages.php', { method: 'POST', body: formData });
        await loadConversations(true);
    } catch (error) {
        console.error('Error marking as read:', error);
    }
}

function filterThreads(searchTerm) {
    if (!searchTerm.trim()) { renderConversations(); return; }
    const filtered = conversations.filter(conv => conv.name && conv.name.toLowerCase().includes(searchTerm.toLowerCase()));
    const container = document.getElementById('msgList');
    if (filtered.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:#B0BAC8;">No matching conversations</div>';
        return;
    }
    container.innerHTML = filtered.map(conv => {
        const isSystem = conv.type === 'system';
        return `<div class="msg-thread ${isSystem ? 'system-thread' : ''}" onclick="openThread('${isSystem ? 'system' : conv.id}', '${isSystem ? 'system' : 'guide'}')">
            <div class="msg-thread-av">${isSystem ? '📢' : (conv.name ? conv.name.charAt(0).toUpperCase() : '?')}</div>
            <div class="msg-thread-info"><div class="msg-thread-name">${escapeHtml(conv.name || 'Unknown')}${isSystem ? '<span class="system-badge">Announcements</span>' : ''}</div><div class="msg-thread-preview">${escapeHtml(conv.last_message || 'No messages yet')}</div></div>
            <div class="msg-thread-meta"><div class="msg-thread-time">${formatTime(conv.last_message_time)}</div>${conv.unread_count > 0 ? `<div class="msg-unread">${conv.unread_count}</div>` : ''}</div>
        </div>`;
    }).join('');
}

function showSidebar() {
    document.getElementById('msgSidebar').classList.remove('hidden-mobile');
    if (window.innerWidth <= 768) {
        document.getElementById('msgMain').style.zIndex = '5';
    }
    activeThread = null;
}

function formatTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    if (diff < 86400000) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    else if (diff < 604800000) return date.toLocaleDateString([], { weekday: 'short' });
    else return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function formatDateTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>
</body>
</html>