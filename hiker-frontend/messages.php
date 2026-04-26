<?php
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$isHiker = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'hiker') $isHiker = true;
elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hiker') $isHiker = true;

if (!$isHiker) { header('Location: login.php'); exit; }

require_once __DIR__ . '/../config/db.php';

$hikerId = $_SESSION['user_id'];
$hikerName = 'Hiker';
$userAvatar = null;

if (isset($pdo) && $pdo) {
    $stmt = $pdo->prepare("SELECT name, avatar FROM users WHERE id = ?");
    $stmt->execute([$hikerId]);
    $user = $stmt->fetch();
    if ($user) { $hikerName = $user['name']; $userAvatar = $user['avatar']; }
}

$nameParts = explode(' ', trim($hikerName));
$userInitial = '';
foreach ($nameParts as $part) { if (!empty($part)) $userInitial .= strtoupper(substr($part, 0, 1)); }
$userInitial = substr($userInitial, 0, 2);

$preselectedGuide = isset($_GET['guide']) ? intval($_GET['guide']) : null;
$preselectedGuideName = isset($_GET['guide_name']) ? $_GET['guide_name'] : null;
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
/* ─── LAYOUT ─── */
.messages-layout {
  display: grid; grid-template-columns: 350px 1fr;
  position: fixed; top: 74px; left: 0; right: 0; bottom: 0;
  overflow: hidden; background: var(--white); z-index: 800;
}
@media(max-width:768px){
  .messages-layout { grid-template-columns:1fr; top:0; bottom:80px; position:fixed; inset:0 0 80px 0; }
  .msg-sidebar { position:absolute; inset:0; z-index:10; transition:transform .3s ease; }
  .msg-sidebar.hidden-mobile { transform:translateX(-100%); pointer-events:none; }
  .msg-main { position:absolute; inset:0; z-index:5; display:flex; flex-direction:column; }
}

/* ─── SIDEBAR ─── */
.msg-sidebar { border-right:1px solid rgba(90,122,90,.12); display:flex; flex-direction:column; background:var(--white); min-height:0; }
.msg-sidebar-hdr { padding:30px 20px 16px; border-bottom:1px solid rgba(90,122,90,.1); flex-shrink:0; }
.msg-sidebar-hdr h2 { font-family:'Playfair Display',serif; font-size:20px; font-weight:600; color:var(--forest); margin-bottom:12px; }
.msg-search { display:flex; align-items:center; gap:8px; background:var(--sky); border-radius:50px; padding:10px 16px; }
.msg-search svg { color:var(--stone); flex-shrink:0; }
.msg-search input { flex:1; border:none; background:transparent; font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; color:var(--forest); outline:none; }
.msg-list { overflow-y:auto; flex:1; }
.msg-thread { display:flex; align-items:center; gap:12px; padding:14px 20px; cursor:pointer; border-bottom:1px solid rgba(90,122,90,.06); transition:background .15s; position:relative; }
.msg-thread:hover { background:var(--sky); }
.msg-thread.active { background:rgba(26,46,26,.05); }
.msg-thread-av { width:44px; height:44px; border-radius:50%; background:var(--forest); color:var(--cream); display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:700; flex-shrink:0; }
.system-thread .msg-thread-av { background:var(--sage); }
.msg-thread-info { flex:1; min-width:0; }
.msg-thread-name { font-weight:700; font-size:13px; color:var(--forest); margin-bottom:2px; }
.msg-thread-preview { font-size:12px; color:var(--stone); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.msg-thread-meta { text-align:right; flex-shrink:0; }
.msg-thread-time { font-size:10px; color:var(--stone); font-family:'DM Mono',monospace; margin-bottom:4px; }
.msg-unread { background:var(--forest); color:var(--cream); border-radius:50px; font-size:10px; font-weight:700; padding:2px 7px; display:inline-block; }
.system-badge { display:inline-block; background:var(--sage); color:white; font-size:9px; padding:2px 8px; border-radius:12px; margin-left:8px; vertical-align:middle; }

/* ─── MAIN PANEL ─── */
.msg-main { display:flex; flex-direction:column; background:var(--cream); height:100%; overflow:hidden; min-height:0; }
.msg-main-hdr { display:flex; align-items:center; gap:12px; padding:14px 24px; background:var(--white); border-bottom:1px solid rgba(90,122,90,.1); flex-shrink:0; }
.msg-back-btn { display:none; background:none; border:none; cursor:pointer; color:var(--forest); padding:4px; }
@media(max-width:768px){ .msg-back-btn { display:flex; } }
.msg-hdr-info { flex:1; }
.msg-hdr-name { font-weight:700; font-size:15px; color:var(--forest); }
.msg-hdr-sub { font-size:11px; color:var(--sage); }

/* ─── CHAT AREA ─── */
.chat-area { 
    flex:1; 
    overflow-y:auto; 
    padding:20px 24px; 
    display:flex; 
    flex-direction:column; 
    gap:14px; 
    scroll-behavior: auto;  /* Changed from 'smooth' to 'auto' */
}
/* ─── BUBBLE ROWS ─── */
.msg-bubble-row {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    width: 100%;
}
/* Mine: push to right on desktop AND mobile */
.msg-bubble-row.mine {
    justify-content: flex-end;
}
/* Theirs: stay left */
.msg-bubble-row:not(.mine) {
    justify-content: flex-start;
}

/* Make sure the inner div doesn't stretch full width */
.msg-bubble-row > div {
    max-width: 70%;
    display: flex;
    flex-direction: column;
}

/* On desktop, also ensure mine messages are right-aligned */
@media(min-width: 769px) {
    .msg-bubble-row.mine > div {
        align-items: flex-end;
    }
    .msg-bubble-row:not(.mine) > div {
        align-items: flex-start;
    }
}

/* For cards - ensure they don't stretch */
.msg-bubble-row.mine .msg-card {
    margin-left: auto;
}
.msg-bubble-row:not(.mine) .msg-card {
    margin-right: auto;
}

.msg-av-xs {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--forest);
    color: var(--cream);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 2px;
}

/* ─── TEXT BUBBLES ─── */
.msg-bubble {
    max-width: 100%;
    padding: 12px 16px;
    border-radius: 20px;
    font-size: 14px;
    line-height: 1.65;
    word-break: break-word;
}
.msg-bubble.theirs { 
    background: var(--white); 
    color: var(--forest); 
    border-radius: 4px 20px 20px 20px; 
    box-shadow: 0 2px 8px rgba(26,46,26,.08); 
}
.msg-bubble.mine { 
    background: var(--forest); 
    color: var(--cream); 
    border-radius: 20px 4px 20px 20px; 
}

.msg-time { 
    font-size: 10px; 
    color: var(--stone); 
    margin-top: 4px; 
    text-align: right; 
    font-family: 'DM Mono', monospace; 
}
.msg-bubble-row.mine .msg-time {
    text-align: right;
}
.msg-bubble-row:not(.mine) .msg-time {
    text-align: left;
}

.sys-msg { 
    text-align: center; 
    font-size: 11px; 
    color: var(--stone); 
    background: rgba(90,122,90,.08); 
    border-radius: 50px; 
    padding: 6px 16px; 
    align-self: center; 
}


/* ─── SYSTEM ANNOUNCEMENT CARD (same as join request) ─── */
.msg-card.system-announcement-card .msg-card-hdr { background: #1A3D2B; }
.msg-card.booking-cancel .msg-card-hdr { background: #5C3A2A; }
.msg-card.booking-update .msg-card-hdr { background: #3A6B40; }

/* ─── INPUT ROW ─── */
.chat-input-row { display:flex; align-items:center; gap:10px; padding:14px 24px; background:var(--white); border-top:1px solid rgba(90,122,90,.1); flex-shrink:0; position:relative; z-index:10; }
@media(max-width:768px){ .chat-input-row { padding:12px 16px; padding-bottom:max(12px,env(safe-area-inset-bottom,12px)); } }
.chat-input { flex:1; padding:12px 18px; border-radius:50px; border:1.5px solid rgba(90,122,90,.15); background:var(--sky); font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; color:var(--forest); outline:none; transition:.2s; }
.chat-input:focus { border-color:var(--sage); background:var(--white); }
.send-btn { width:44px; height:44px; border-radius:50%; background:var(--forest); color:var(--cream); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform .15s; flex-shrink:0; }
.send-btn:hover { transform:scale(1.08); }
.send-btn:disabled { opacity:.5; cursor:not-allowed; }

/* ─── EMPTY / LOADING ─── */
.chat-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--stone); padding:40px; text-align:center; }
.chat-empty-icon { font-size:48px; margin-bottom:16px; }
.loading-spinner { text-align:center; padding:40px; color:#8A99AE; }
.encryption-badge { font-size:10px; color:#8A99AE; margin-left:8px; font-family:monospace; }

/* ─── ACTION CARDS ─── */
.msg-card {
  width: 320px;
  max-width: 100%;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 14px rgba(26,46,26,.10);
  margin: 4px 0;
  border: 1px solid rgba(90,122,90,.13);
  text-align: left;
  background: var(--white);
}
@media(min-width:1024px){ .msg-card { width:360px; } }
@media(max-width:480px) { .msg-card { width:280px; } }

.msg-card-hdr {
  padding: 11px 14px;
  display: flex;
  align-items: center;
  gap: 9px;
  color: #fff;
}
.msg-card-hdr-label { flex:1; }
.msg-card-hdr-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; line-height: 1.2; }
.msg-card-hdr-sub { font-size: 10px; opacity: .75; margin-top: 1px; }
.msg-card-icon { font-size: 18px; flex-shrink: 0; }

.msg-card.join-request .msg-card-hdr { background: #2D5016; }
.msg-card-body { padding:14px; font-size:13px; color:var(--forest); line-height:1.6; }
.msg-card-title { font-weight:700; font-size:15px; margin-bottom:6px; }
.msg-card-desc  { font-size:13px; color:#4A5568; margin-bottom:10px; line-height:1.5; }
.msg-card-detail { font-size:12px; color:var(--sage); display:flex; align-items:center; gap:6px; margin-top:5px; }
.msg-card-detail i { width:14px; text-align:center; opacity:.7; }
.join-action-btns { display:flex; gap:10px; margin-top:14px; }
.join-btn { flex:1; padding:9px 0; border-radius:50px; border:none; font-size:12px; font-weight:700; cursor:pointer; }
.join-btn.approve { background:var(--forest); color:var(--cream); }
.join-btn.deny    { background:transparent; color:var(--forest); border:1.5px solid rgba(26,46,26,.25); }
.join-status-badge { display:inline-flex; align-items:center; gap:6px; margin-top:12px; padding:7px 14px; border-radius:50px; font-size:12px; font-weight:700; }
.join-status-badge.approved { background:rgba(90,122,90,.12); color:#2D5016; }
.join-status-badge.denied   { background:rgba(92,58,58,.08); color:#5C3A2A; }
.msg-card-footer { font-size:11px; color:var(--stone); margin-top:10px; padding-top:8px; border-top:1px solid rgba(90,122,90,.08); }

/* ─── SYSTEM ANNOUNCEMENTS ─── */
.system-announcement { background:rgba(90,122,90,.06); border-left:3px solid var(--sage); padding:14px 16px; margin:8px 0; border-radius:0 12px 12px 0; }
.system-announcement-header { display:flex; justify-content:space-between; margin-bottom:8px; }
.system-announcement-title { font-weight:700; color:var(--forest); font-size:11px; text-transform:uppercase; letter-spacing:.6px; }
.system-announcement-time { font-size:10px; color:var(--stone); font-family:'DM Mono',monospace; }
.system-announcement-body { color:var(--forest); line-height:1.6; font-size:13px; }
</style>
</head>
<body>

<?php $currentPage = 'messages'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="messages-layout">
  <div class="msg-sidebar" id="msgSidebar">
    <div class="msg-sidebar-hdr">
      <h2>Messages <span class="encryption-badge">🔒 AES-256</span></h2>
      <div class="msg-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" placeholder="Search conversations…" id="msgSearch" oninput="filterThreads(this.value)">
      </div>
    </div>
    <div class="msg-list" id="msgList">
      <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
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
        <input class="chat-input" type="text" id="chatInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter')sendMsg()" autocomplete="off">
        <button class="send-btn" onclick="sendMsg()" id="sendMsgBtn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
const CURRENT_USER_ID = <?php echo json_encode($hikerId); ?>;

let conversations = [];
let activeThread = null;
let activeThreadType = 'guide';
let pollInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadConversations();

    const params = new URLSearchParams(window.location.search);
    const pg = params.get('guide'), pgn = params.get('guide_name');
    if (pg && pgn) {
        setTimeout(() => {
            if (!conversations.find(c => c.id == pg)) {
                conversations.push({ id: parseInt(pg), name: pgn, type: 'guide', last_message: '', unread_count: 0 });
                renderConversations();
            }
            openThread(pg, 'guide');
        }, 500);
    }

    pollInterval = setInterval(() => {
        if (activeThread && activeThreadType === 'guide') {
            loadMessages(activeThread, true);
            loadConversations(true);
        }
    }, 10000);
});

async function loadConversations(silent = false) {
    try {
        const res = await fetch('../api/hiker_messages.php?action=get_conversations');
        const data = await res.json();
        if (data.success) {
            conversations = data.conversations || [];
            silent ? updateUnreadCounts() : renderConversations();
        } else if (!silent) {
            document.getElementById('msgList').innerHTML = '<div class="loading-spinner">Error loading conversations</div>';
        }
    } catch (e) {
        if (!silent) document.getElementById('msgList').innerHTML = '<div class="loading-spinner">Error loading conversations</div>';
    }
}

function updateUnreadCounts() {
    document.querySelectorAll('.msg-thread').forEach(el => {
        const id = el.getAttribute('data-thread-id');
        const conv = conversations.find(c => c.id == id);
        if (conv?.unread_count > 0) {
            const meta = el.querySelector('.msg-thread-meta');
            if (meta && !meta.querySelector('.msg-unread')) {
                const b = document.createElement('div');
                b.className = 'msg-unread';
                b.textContent = conv.unread_count;
                meta.appendChild(b);
            }
        }
    });
}

function previewText(conv) {
    const raw = conv.last_message || '';
    if (!raw || raw === 'null') return '📋 Join request';
    return raw;
}

function renderConversations() {
    const container = document.getElementById('msgList');
    const search = document.getElementById('msgSearch').value.toLowerCase();
    const filtered = conversations.filter(c => c.name?.toLowerCase().includes(search));

    if (!filtered.length) {
        container.innerHTML = `<div style="text-align:center;padding:40px;color:#B0BAC8;"><i class="fas fa-comments" style="font-size:32px;margin-bottom:12px;display:block;"></i>No conversations yet<p style="font-size:12px;margin-top:8px;">Book a tour to start messaging your guide</p></div>`;
        return;
    }
    container.innerHTML = filtered.map(conv => {
        const sys = conv.type === 'system';
        const active = (sys ? activeThread === 'system' : activeThread == conv.id) && activeThreadType === (sys ? 'system' : 'guide');
        const preview = sys ? (conv.last_message || 'No announcements yet') : previewText(conv);
        return `<div class="msg-thread${sys ? ' system-thread' : ''}${active ? ' active' : ''}" data-thread-id="${conv.id}" onclick="openThread('${sys ? 'system' : conv.id}','${sys ? 'system' : 'guide'}')">
            <div class="msg-thread-av">${sys ? '📢' : (conv.name?.charAt(0).toUpperCase() || '?')}</div>
            <div class="msg-thread-info">
                <div class="msg-thread-name">${esc(conv.name || 'Unknown')}${sys ? '<span class="system-badge">Announcements</span>' : ''}</div>
                <div class="msg-thread-preview">${esc(preview)}</div>
            </div>
            <div class="msg-thread-meta">
                <div class="msg-thread-time">${fmtTime(conv.last_message_time)}</div>
                ${conv.unread_count > 0 ? `<div class="msg-unread">${conv.unread_count}</div>` : ''}
            </div>
        </div>`;
    }).join('');
}

async function openThread(id, type) {
    activeThread = id;
    activeThreadType = type;
    const conv = conversations.find(c => type === 'system' ? c.type === 'system' : c.id == id);
    document.getElementById('chatEmpty').style.display = 'none';
    const chatView = document.getElementById('chatView');
    chatView.style.display = 'flex';
    if (conv) {
        document.getElementById('chatHdrAv').innerHTML = conv.type === 'system' ? '📢' : (conv.name?.charAt(0).toUpperCase() || '?');
        document.getElementById('chatHdrName').innerHTML = esc(conv.name || 'System');
        document.getElementById('chatHdrSub').innerHTML = conv.type === 'system' ? '📢 Official Announcements & Alerts' : (conv.role || 'Tour Guide');
    }
    document.getElementById('chatInput').value = '';
    if (type === 'guide') await loadMessages(id);
    else await loadAnnouncements();
    if (window.innerWidth <= 768) {
        document.getElementById('msgSidebar').classList.add('hidden-mobile');
        document.getElementById('msgMain').style.zIndex = '20';
    }
}

async function loadMessages(userId, silent = false) {
    const area = document.getElementById('chatArea');
    if (!silent) area.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading messages…</div>';
    try {
        const res = await fetch(`../api/hiker_messages.php?action=get_messages&user_id=${userId}`);
        const data = await res.json();
        if (data.success) {
            renderMessages(data.messages || []);
            await markAsRead(userId);
        } else if (!silent) {
            area.innerHTML = '<div class="chat-empty">Error loading messages.</div>';
        }
    } catch (e) {
        if (!silent) area.innerHTML = '<div class="chat-empty">Error loading messages.</div>';
    }
}
function renderMessages(messages) {
    const area = document.getElementById('chatArea');
    if (!messages?.length) {
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

        // Check for booking confirmation messages (with action_data)
        if (msg.action_data && msg.action_data !== 'null' && msg.action_data !== '') {
            try {
                const ad = typeof msg.action_data === 'string' ? JSON.parse(msg.action_data) : msg.action_data;

                if (ad.type === 'booking_request') {
                    const isGuide = String(msg.receiver_id) === String(CURRENT_USER_ID); // Guide receives the request
                    const status = ad.status || 'pending';
                    const handled = status === 'approved' || status === 'denied';

                    let actionsHtml = '';
                    if (isGuide && !handled) {
                        actionsHtml = `
                            <div class="join-action-btns">
                                <button class="join-btn approve" onclick="handleBookingRequest(${msg.id},'approve','${ad.booking_id}','${esc(ad.hiker_name)}',${ad.hiker_user_id})">Approve</button>
                                <button class="join-btn deny" onclick="handleBookingRequest(${msg.id},'deny','${ad.booking_id}','${esc(ad.hiker_name)}',${ad.hiker_user_id})">Deny</button>
                            </div>
                            <div class="msg-card-footer">Respond within 24 hours</div>`;
                    } else if (handled) {
                        actionsHtml = `<div class="join-status-badge ${status}">${status === 'approved' ? '✓ Approved' : '✕ Denied'}</div>`;
                    } else if (!isGuide && !handled) {
                        actionsHtml = `<div class="join-status-badge" style="background:rgba(90,122,90,.08);color:var(--stone);">⏳ Awaiting guide response</div>`;
                    }

                    const card = `
                        <div class="msg-card join-request">
                            <div class="msg-card-hdr">
                                <span class="msg-card-icon">🏔️</span>
                                <div class="msg-card-hdr-label">
                                    <div class="msg-card-hdr-title">New Booking Request</div>
                                    <div class="msg-card-hdr-sub">${isGuide ? 'A hiker wants to book a hike with you' : 'You sent a booking request'}</div>
                                </div>
                            </div>
                            <div class="msg-card-body">
                                <div class="msg-card-title">${esc(ad.hiker_name)}</div>
                                <div class="msg-card-desc">${isGuide ? 'wants to book a hike with you.' : 'Your booking request has been sent to the guide.'}</div>
                                <div class="msg-card-detail"><i class="fas fa-mountain"></i> Mountain: ${esc(ad.mountain_name)}</div>
                                <div class="msg-card-detail"><i class="fas fa-calendar"></i> Date: ${esc(ad.booking_date)}</div>
                                <div class="msg-card-detail"><i class="fas fa-users"></i> Hikers: ${ad.pax} person(s)</div>
                                <div class="msg-card-detail"><i class="fas fa-tag"></i> Booking ID: ${esc(ad.booking_number)}</div>
                                ${actionsHtml}
                            </div>
                        </div>
                        <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;

                    if (isGuide) {
                        // Guide receives the request - on LEFT side
                        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
                    } else {
                        // Hiker sent the request - on RIGHT side (mine)
                        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
                    }
                    return;
                }

                if (ad.type === 'join_request') {
                    const isOwner = String(msg.receiver_id) === String(CURRENT_USER_ID);
                    const status = ad.status || 'pending';
                    const handled = status === 'approved' || status === 'denied';

                    let actionsHtml = '';
                    if (isOwner && !handled) {
                        actionsHtml = `
                            <div class="join-action-btns">
                                <button class="join-btn approve" onclick="handleJoinRequest(${msg.id},'approve','${ad.booking_id}','${esc(ad.requester_name)}',${ad.requester_user_id})">Approve</button>
                                <button class="join-btn deny" onclick="handleJoinRequest(${msg.id},'deny','${ad.booking_id}','${esc(ad.requester_name)}',${ad.requester_user_id})">Deny</button>
                            </div>
                            <div class="msg-card-footer">This request expires in 24 hours</div>`;
                    } else if (handled) {
                        actionsHtml = `<div class="join-status-badge ${status}">${status === 'approved' ? '✓ Approved' : '✕ Denied'}</div>`;
                    } else if (!isOwner && !handled) {
                        actionsHtml = `<div class="join-status-badge" style="background:rgba(90,122,90,.08);color:var(--stone);">⏳ Awaiting response</div>`;
                    }

                    const card = `
                        <div class="msg-card join-request">
                            <div class="msg-card-hdr">
                                <span class="msg-card-icon">${isOwner ? '👥' : '📤'}</span>
                                <div class="msg-card-hdr-label">
                                    <div class="msg-card-hdr-title">Join Request</div>
                                    <div class="msg-card-hdr-sub">${isOwner ? 'Someone wants to join your hike' : 'You sent a join request'}</div>
                                </div>
                            </div>
                            <div class="msg-card-body">
                                <div class="msg-card-title">${esc(ad.requester_name)}</div>
                                <div class="msg-card-desc">${isOwner ? 'wants to join your booked hike.' : 'Your request has been sent to the booking owner.'}</div>
                                <div class="msg-card-detail"><i class="fas fa-tag"></i> Booking: ${esc(ad.booking_number)}</div>
                                <div class="msg-card-detail"><i class="fas fa-mountain"></i> ${esc(ad.mountain_name || 'Mountain')}</div>
                                ${actionsHtml}
                            </div>
                        </div>
                        <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;

                    if (isOwner) {
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

        // Check for join request APPROVAL/DENIAL replies
        if (msg.body && (msg.body.includes('APPROVED') || msg.body.includes('DENIED')) && msg.body.includes('join request')) {
            const isApproved = msg.body.includes('APPROVED');
            const cardType = isApproved ? 'booking-update' : 'booking-cancel';
            const icon = isApproved ? '✅' : '❌';
            const title = isApproved ? 'Join Request Approved' : 'Join Request Denied';
            const subTitle = isApproved ? 'You can now join the hike' : 'Request Declined';
            
            const card = `
                <div class="msg-card ${cardType}">
                    <div class="msg-card-hdr">
                        <span class="msg-card-icon">${icon}</span>
                        <div class="msg-card-hdr-label">
                            <div class="msg-card-hdr-title">${title}</div>
                            <div class="msg-card-hdr-sub">${subTitle}</div>
                        </div>
                    </div>
                    <div class="msg-card-body">
                        <div class="msg-card-desc">${esc(msg.body || '')}</div>
                    </div>
                </div>
                <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;
            
            if (isMine) {
                html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
            } else {
                html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
            }
            return;
        }

        // Check for cancellation messages
        if (msg.body && (msg.body.includes('cancel') || msg.body.includes('Cancel') || msg.body.includes('left my booking') || msg.body.includes('cancelled'))) {
            const card = `
                <div class="msg-card booking-cancel">
                    <div class="msg-card-hdr">
                        <span class="msg-card-icon">❌</span>
                        <div class="msg-card-hdr-label">
                            <div class="msg-card-hdr-title">Booking Cancelled</div>
                            <div class="msg-card-hdr-sub">Hiker Cancellation</div>
                        </div>
                    </div>
                    <div class="msg-card-body">
                        <div class="msg-card-desc">${esc(msg.body || '')}</div>
                    </div>
                </div>
                <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;
            
            if (isMine) {
                html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
            } else {
                html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
            }
            return;
        }
// Check for nudge messages
if (msg.source_type === 'nudge') {
    const isMine = String(msg.sender_id) === String(CURRENT_USER_ID);
    
    // Use the original friendly reminder message
    const reminderMessage = "Hi! Just a friendly reminder about my upcoming hike booking. Let me know if you have any updates! 👋";
    
    const card = `
        <div class="msg-card booking-update">
            <div class="msg-card-hdr">
                <span class="msg-card-icon">🔔</span>
                <div class="msg-card-hdr-label">
                    <div class="msg-card-hdr-title">Reminder</div>
                    <div class="msg-card-hdr-sub">Friendly Reminder</div>
                </div>
            </div>
            <div class="msg-card-body">
                <div class="msg-card-desc">${esc(reminderMessage)}</div>
            </div>
        </div>
        <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;
    
    if (isMine) {
        html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
    } else {
        html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
    }
    return;
}

        // Check if this is a system announcement 
        const isSystemAnnouncement = (msg.is_system_announcement == 1) || 
                                      (msg.sender_role === 'guide' && msg.is_system_announcement == 1) ||
                                      (msg.body && (msg.body.includes('reminder') || msg.body.includes('confirmed') || msg.body.includes('Confirmed')));
        
        if (isSystemAnnouncement || msg.is_system_announcement == 1 || msg.sender_role === 'system') {
            let cardType = 'system-announcement-card';
            let icon = '📢';
            let title = 'System Announcement';
            let subTitle = 'Official Update';
            
            if (msg.body && (msg.body.includes('confirmed') || msg.body.includes('Confirmed'))) {
                cardType = 'booking-update';
                icon = '✅';
                title = 'Booking Confirmed';
                subTitle = 'Tour Update';
            } else if (msg.body && msg.body.includes('reminder')) {
                cardType = 'booking-update';
                icon = '🔔';
                title = 'Reminder';
                subTitle = 'Friendly Reminder';
            }
            
            const card = `
                <div class="msg-card ${cardType}">
                    <div class="msg-card-hdr">
                        <span class="msg-card-icon">${icon}</span>
                        <div class="msg-card-hdr-label">
                            <div class="msg-card-hdr-title">${title}</div>
                            <div class="msg-card-hdr-sub">${subTitle}</div>
                        </div>
                    </div>
                    <div class="msg-card-body">
                        <div class="msg-card-desc">${esc(msg.body || '')}</div>
                    </div>
                </div>
                <div class="msg-time" style="margin-top: 4px;">${timeStr}</div>`;
            
            if (isMine) {
                html += `<div class="msg-bubble-row mine"><div>${card}</div></div>`;
            } else {
                html += `<div class="msg-bubble-row"><div class="msg-av-xs">${senderInitial}</div><div>${card}</div></div>`;
            }
            return;
        }

        // Normal text message bubble
        const bubble = `<div class="msg-bubble ${isMine ? 'mine' : 'theirs'}">${esc(msg.body || '')}</div>`;
        
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
    area.scrollTop = area.scrollHeight;
}

// Add this function to handle booking request responses
function handleBookingRequest(messageId, action, bookingId, hikerName, hikerUserId) {
    if (!confirm(`Are you sure you want to ${action} this booking request?`)) return;
    
    document.querySelectorAll('.join-btn').forEach(b => b.disabled = true);
    
    const fd = new FormData();
    fd.append('action', `${action}_booking_request`);
    fd.append('request_id', messageId);
    fd.append('booking_id', bookingId);
    fd.append('hiker_name', hikerName);
    fd.append('hiker_user_id', hikerUserId);
    
    fetch('../api/hiker_messages.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? 'Done!' : 'Failed'));
            if (result.success) {
                setTimeout(() => {
                    if (activeThread) loadMessages(activeThread);
                }, 600);
            } else {
                document.querySelectorAll('.join-btn').forEach(b => b.disabled = false);
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error');
            document.querySelectorAll('.join-btn').forEach(b => b.disabled = false);
        });
}
// Call this function when you need to show a system announcement card
function showSystemAnnouncement(message, type = 'announcement') {
    // type can be: 'announcement', 'cancellation', 'update'
    const cardType = type === 'cancellation' ? 'booking-cancel' : (type === 'update' ? 'booking-update' : 'system-announcement-card');
    const icon = type === 'cancellation' ? '⚠️' : (type === 'update' ? '🔄' : '📢');
    const title = type === 'cancellation' ? 'Booking Cancelled' : (type === 'update' ? 'Booking Updated' : 'System Announcement');
    
    const card = `
        <div class="msg-card ${cardType}">
            <div class="msg-card-hdr">
                <span class="msg-card-icon">${icon}</span>
                <div class="msg-card-hdr-label">
                    <div class="msg-card-hdr-title">${title}</div>
                    <div class="msg-card-hdr-sub">Official Update</div>
                </div>
            </div>
            <div class="msg-card-body">
                <div class="msg-card-desc">${esc(message)}</div>
            </div>
        </div>`;
    
    const chatArea = document.getElementById('chatArea');
    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    chatArea.innerHTML += `
        <div class="msg-bubble-row">
            <div>
                ${card}
                <div class="msg-time">${timeStr}</div>
            </div>
        </div>`;
    chatArea.scrollTop = chatArea.scrollHeight;
}
async function loadAnnouncements() {
    const area = document.getElementById('chatArea');
    area.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading announcements…</div>';
    try {
        const res = await fetch('../api/hiker_messages.php?action=get_announcements');
        const data = await res.json();
        if (data.success) renderAnnouncements(data.announcements || []);
        else area.innerHTML = '<div class="chat-empty">Error loading announcements.</div>';
    } catch (e) {
        area.innerHTML = '<div class="chat-empty">Error loading announcements.</div>';
    }
}

function renderAnnouncements(list) {
    const area = document.getElementById('chatArea');
    if (!list?.length) {
        area.innerHTML = `<div class="chat-empty"><div class="chat-empty-icon">📢</div><div>No announcements yet</div></div>`;
        return;
    }
    area.innerHTML = list.map(a => `
        <div class="system-announcement">
            <div class="system-announcement-header">
                <span class="system-announcement-title">LAKBAY Announcement</span>
                <span class="system-announcement-time">${fmtDateTime(a.created_at)}</span>
            </div>
            <div class="system-announcement-body">${esc(a.body)}</div>
        </div>`).join('');
    area.scrollTop = area.scrollHeight;
}

async function sendMsg() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg || !activeThread) return;
    
    if (activeThreadType === 'system') {
        alert('You cannot reply to system announcements.');
        return;
    }
    
    const btn = document.getElementById('sendMsgBtn');
    input.disabled = btn.disabled = true;
    
    try {
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('recipient_id', activeThread);
        fd.append('recipient_type', activeThreadType);
        fd.append('body', msg);
        
        const res = await fetch('../api/hiker_messages.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            input.value = '';
            await loadMessages(activeThread);
            await loadConversations();
            input.focus();
        } else {
            alert('Failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Failed to send message.');
    } finally {
        input.disabled = btn.disabled = false;
    }
}

async function markAsRead(recipientId) {
    try {
        const fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('recipient_id', recipientId);
        fd.append('recipient_type', activeThreadType);
        await fetch('../api/hiker_messages.php', { method: 'POST', body: fd });
        await loadConversations(true);
    } catch (e) {}
}

function handleJoinRequest(messageId, action, bookingId, requesterName, requesterUserId) {
    if (!confirm(`Are you sure you want to ${action} this join request?`)) return;
    
    document.querySelectorAll('.join-btn').forEach(b => b.disabled = true);
    
    const fd = new FormData();
    fd.append('action', `${action}_join_request`);
    fd.append('request_id', messageId);
    fd.append('booking_id', bookingId);
    fd.append('requester_name', requesterName);
    fd.append('requester_user_id', requesterUserId);
    
    fetch('../api/hiker_messages.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? 'Done!' : 'Failed'));
            if (result.success) {
                setTimeout(() => {
                    if (activeThread) loadMessages(activeThread);
                }, 600);
            } else {
                document.querySelectorAll('.join-btn').forEach(b => b.disabled = false);
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error');
            document.querySelectorAll('.join-btn').forEach(b => b.disabled = false);
        });
}

function filterThreads(term) {
    if (!term.trim()) {
        loadConversations();
        return;
    }
    const filtered = conversations.filter(c => c.name?.toLowerCase().includes(term.toLowerCase()));
    const container = document.getElementById('msgList');
    if (!filtered.length) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:#B0BAC8;">No matching conversations</div>';
        return;
    }
    container.innerHTML = filtered.map(conv => {
        const sys = conv.type === 'system';
        const preview = sys ? (conv.last_message || 'No announcements yet') : previewText(conv);
        return `<div class="msg-thread${sys ? ' system-thread' : ''}" onclick="openThread('${sys ? 'system' : conv.id}','${sys ? 'system' : 'guide'}')">
            <div class="msg-thread-av">${sys ? '📢' : (conv.name?.charAt(0).toUpperCase() || '?')}</div>
            <div class="msg-thread-info">
                <div class="msg-thread-name">${esc(conv.name || 'Unknown')}${sys ? '<span class="system-badge">Announcements</span>' : ''}</div>
                <div class="msg-thread-preview">${esc(preview)}</div>
            </div>
            <div class="msg-thread-meta">
                <div class="msg-thread-time">${fmtTime(conv.last_message_time)}</div>
                ${conv.unread_count > 0 ? `<div class="msg-unread">${conv.unread_count}</div>` : ''}
            </div>
        </div>`;
    }).join('');
}

function showSidebar() {
    document.getElementById('msgSidebar').classList.remove('hidden-mobile');
    if (window.innerWidth <= 768) document.getElementById('msgMain').style.zIndex = '5';
    activeThread = null;
}

function fmtTime(ts) {
    if (!ts) return '';
    const d = new Date(ts), now = new Date(), diff = now - d;
    if (diff < 86400000) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (diff < 604800000) return d.toLocaleDateString([], { weekday: 'short' });
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function fmtDateTime(ts) {
    if (!ts) return '';
    return new Date(ts).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showToast(msg) {
    let t = document.querySelector('.toast-message');
    if (!t) {
        t = document.createElement('div');
        t.className = 'toast-message';
        t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:var(--forest);color:#fff;padding:12px 24px;border-radius:50px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 3000);
}
</script>
</body>
</html>