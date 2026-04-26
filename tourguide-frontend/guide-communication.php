<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY Guide — Communication</title>
  <link rel="stylesheet" href="guide-shared.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .guide-content { padding: 0; flex: 1; display: flex; flex-direction: column; }

    .comm-layout {
      display: flex;
      flex: 1;
      height: calc(100vh - 60px);
      overflow: hidden;
    }

    /* ── THREAD LIST ── */
    .thread-list {
      width: 300px;
      border-right: 1px solid var(--line);
      display: flex;
      flex-direction: column;
      background: white;
      flex-shrink: 0;
    }
    .thread-list-header {
      padding: 16px 18px;
      border-bottom: 1px solid var(--line);
      display: flex;
      flex-direction: column;
      gap: 10px;
      background: var(--stone);
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

    /* tab filter */
    .thread-tabs {
      display: flex;
      border-bottom: 1px solid var(--line);
      padding: 0 6px;
      background: white;
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
    .thread-online-dot {
      position: absolute;
      bottom: 1px; right: 1px;
      width: 9px; height: 9px;
      background: #1B7045;
      border-radius: 50%;
      border: 2px solid white;
    }
    .thread-info { flex: 1; min-width: 0; }
    .thread-name {
      font-size: 0.83rem;
      font-weight: 600;
      color: var(--ink);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .thread-time {
      font-size: 0.62rem;
      color: var(--ink-4);
      font-family: 'DM Mono', monospace;
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
      width: 18px; height: 18px;
      border-radius: 50%;
      font-size: 0.6rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* ── CHAT AREA ── */
    .chat-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: var(--stone);
    }
    .chat-header {
      background: white;
      border-bottom: 1px solid var(--line);
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
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

    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 18px 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .msg-group { display: flex; flex-direction: column; gap: 4px; }
    .msg-group.mine { align-items: flex-end; }
    .msg-group.theirs { align-items: flex-start; }

    .msg-sender {
      font-size: 0.65rem;
      font-weight: 600;
      color: var(--ink-4);
      padding: 0 12px;
      margin-bottom: 2px;
    }
    .msg-bubble {
      max-width: 72%;
      padding: 10px 14px;
      border-radius: 18px;
      font-size: 0.82rem;
      line-height: 1.5;
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
      box-shadow: var(--shadow-sm);
    }
    .msg-time {
      font-size: 0.6rem;
      color: var(--ink-5);
      padding: 0 12px;
    }

    /* quick reply chips */
    .quick-replies {
      padding: 10px 20px 4px;
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .quick-chip {
      padding: 5px 12px;
      background: white;
      border: 1px solid var(--line);
      border-radius: 20px;
      font-size: 0.72rem;
      color: #100600;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.15s;
    }
    .quick-chip:hover { background: #100600; color: white; border-color: #100600; }

    /* message input */
    .chat-input-bar {
      background: white;
      border-top: 1px solid var(--line);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
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

    /* Date divider */
    .date-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 8px 0;
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
      margin: 4px 0;
      max-width: 320px;
    }
    .booking-request-card.mine { align-self: flex-end; }
    .brc-header { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #100600; margin-bottom: 8px; }
    .brc-row { display: flex; justify-content: space-between; font-size: 0.75rem; padding: 4px 0; border-bottom: 1px solid var(--stone-2); }
    .brc-row:last-of-type { border-bottom: none; }
    .brc-label { color: var(--ink-4); font-weight: 500; }
    .brc-val { color: var(--ink); font-weight: 600; }
    .brc-actions { display: flex; gap: 8px; margin-top: 10px; }
    .brc-actions .btn { flex: 1; justify-content: center; }

    /* empty state */
    .chat-empty {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      color: var(--ink-4);
    }
    .chat-empty i { font-size: 2.5rem; color: var(--stone-3); }
    .chat-empty p { font-size: 0.82rem; }

    @media (max-width: 768px) {
      .thread-list { width: 100%; position: absolute; z-index: 10; transform: translateX(0); transition: transform 0.3s; }
      .thread-list.hidden { transform: translateX(-100%); }
      .chat-area { width: 100%; }
      .comm-layout { position: relative; }
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
        <li><a href="guide-dashboard.html"><i class="fas fa-house"></i> Dashboard</a></li>
        <li><a href="guide-map.html"><i class="fas fa-map-location-dot"></i> Trail Map</a></li>
        <li><a href="guide-communication.html" class="active"><i class="fas fa-comments"></i> Communication</a></li>
        <li><a href="guide-safety.html"><i class="fas fa-shield-halved"></i> Safety</a></li>
      </ul>
      <div class="sidebar-divider"></div>
      <ul><li><a href="guide-profile.html"><i class="fas fa-circle-user"></i> My Profile</a></li></ul>
    </nav>
    <div class="sidebar-profile">
      <div class="sidebar-avatar">JD</div>
      <div class="sidebar-profile-info"><div class="sidebar-profile-name">John Dela Cruz</div><div class="sidebar-profile-role">Senior Trail Guide</div></div>
    </div>
  </aside>

  <div class="guide-main">
    <div class="guide-topbar">
      <div class="topbar-title">Communication</div>
      <div class="topbar-right">
        <button class="topbar-icon-btn" onclick="showToast('New message')"><i class="fas fa-pen-to-square"></i></button>
      </div>
    </div>

    <div class="guide-content">
      <div class="comm-layout">

        <!-- THREAD LIST -->
        <div class="thread-list" id="threadList">
          <div class="thread-list-header">
            <h3>Messages</h3>
            <div class="thread-search">
              <i class="fas fa-search"></i>
              <input type="text" id="searchInput" placeholder="Search conversations…" onkeyup="filterThreads()">
            </div>
          </div>
          <div class="thread-tabs">
            <div class="thread-tab active" data-tab="all" onclick="setActiveTab('all')">All</div>
            <div class="thread-tab" data-tab="bookings" onclick="setActiveTab('bookings')">Bookings</div>
            <div class="thread-tab" data-tab="unread" onclick="setActiveTab('unread')">Unread</div>
          </div>
          <div class="thread-items" id="threadItems"></div>
        </div>

        <!-- CHAT AREA -->
        <div class="chat-area" id="chatArea">
          <div class="chat-header" id="chatHeader">
            <button class="btn btn-ghost btn-sm" id="backBtn" style="display:none;padding:6px 10px;" onclick="showThreadList()"><i class="fas fa-arrow-left"></i></button>
            <div class="chat-header-avatar" id="chatAvatarHd">LS</div>
            <div class="chat-header-info">
              <div class="chat-header-name" id="chatName">Lea Santiago</div>
              <div class="chat-header-sub" id="chatSub">Mt. Batulao · Apr 25 · 5:00 AM</div>
            </div>
            <div class="chat-header-actions">
              <button class="topbar-icon-btn" title="View booking" onclick="showToast('Opening booking details…')"><i class="fas fa-calendar-check"></i></button>
              <button class="topbar-icon-btn" title="Call" onclick="showToast('Calling hiker…')"><i class="fas fa-phone"></i></button>
            </div>
          </div>

          <div class="chat-messages" id="chatMessages"></div>

          <div class="quick-replies" id="quickReplies">
            <div class="quick-chip" onclick="sendQuick('I\'m on my way!')">On my way!</div>
            <div class="quick-chip" onclick="sendQuick('Please be at the jump-off 15 mins early.')">Be 15 min early</div>
            <div class="quick-chip" onclick="sendQuick('Bring enough water and snacks for the hike.')">Bring supplies</div>
            <div class="quick-chip" onclick="sendQuick('Hike is confirmed. See you there!')">Confirmed ✓</div>
          </div>

          <div class="chat-input-bar">
            <div class="chat-input-wrap">
              <input type="text" id="msgInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter')sendMessage()">
              <button style="background:none;border:none;color:var(--ink-4);cursor:pointer;font-size:0.9rem;" onclick="showToast('Attach file')"><i class="fas fa-paperclip"></i></button>
            </div>
            <button class="chat-send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <nav class="guide-bottom-nav">
    <div class="bottom-nav-inner">
      <a href="guide-dashboard.html" class="bnav-item"><i class="fas fa-house"></i><span>Home</span></a>
      <a href="guide-map.html" class="bnav-item"><i class="fas fa-map-location-dot"></i><span>Map</span></a>
      <a href="guide-communication.html" class="bnav-item active"><i class="fas fa-comments"></i><span>Chats</span></a>
      <a href="guide-safety.html" class="bnav-item"><i class="fas fa-shield-halved"></i><span>Safety</span></a>
      <a href="guide-profile.html" class="bnav-item"><i class="fas fa-circle-user"></i><span>Profile</span></a>
    </div>
  </nav>
</div>

<div class="toast" id="toast"></div>

<script>
// Expanded dummy conversations with more threads for realistic filtering
const threadsData = [
  { id:0, name:'Lea Santiago', initials:'LS', sub:'Mt. Batulao · Apr 25', preview:'Got it! I\'ll be there at 4:45.', time:'9:41 AM', unread:0, online:true, hasRequest:false },
  { id:1, name:'Ben Torres',   initials:'BT', sub:'Mt. Talamitam · Apr 27', preview:'Booking Request: 5 hikers · Day Hike', time:'9:20 AM', unread:1, online:true, hasRequest:true },
  { id:2, name:'Nina Cruz',    initials:'NC', sub:'Mt. Batulao · Apr 30', preview:'What should we bring?', time:'Yesterday', unread:2, online:false, hasRequest:false },
  { id:3, name:'Carlos Vidal', initials:'CV', sub:'Mt. Batulao · May 3', preview:'Can we confirm the time?', time:'Yesterday', unread:0, online:false, hasRequest:false },
  { id:4, name:'Maya Flores',   initials:'MF', sub:'Mt. Pico de Loro · May 5', preview:'Booking Request: 3 pax · Overnight', time:'2 hours ago', unread:1, online:true, hasRequest:true },
  { id:5, name:'Ramon Cruz',    initials:'RC', sub:'Mt. Makiling · May 7', preview:'Thanks for the info!', time:'3 hours ago', unread:0, online:false, hasRequest:false },
  { id:6, name:'Sofia Reyes',   initials:'SR', sub:'Mt. Batulao · May 10', preview:'Booking Request: Group of 8', time:'5 hours ago', unread:1, online:true, hasRequest:true },
];

const chatsData = {
  0: [
    { from:'them', text:'Hi po! Pwede po ba malaman yung meeting point?', time:'8:30 AM' },
    { from:'me',   text:'Hello Lea! We will meet at the Batulao jump-off in Barangay Aga, Nasugbu.', time:'8:35 AM' },
    { from:'me',   text:'Please be there by 4:45 AM so we can start at 5 sharp.', time:'8:35 AM' },
    { from:'them', text:'Got it! I\'ll be there at 4:45. Thank you po!', time:'9:41 AM' },
  ],
  1: [
    { type:'request', from:'them', booking:{ mountain:'Mt. Talamitam', date:'Apr 27, 2025', time:'6:00 AM', pax:'5 people', type:'Day Hike' }, time:'9:20 AM' },
  ],
  2: [
    { from:'me',   text:'Hi Nina! Your booking for Apr 30 is confirmed. 😊', time:'Yesterday' },
    { from:'them', text:'Thank you! What should we bring for the hike?', time:'Yesterday' },
    { from:'them', text:'Also, is it okay if one of us has asthma?', time:'Yesterday' },
  ],
  3: [
    { from:'them', text:'Good morning po! Can we confirm our meeting time for May 3?', time:'Yesterday' },
    { from:'me',   text:'Yes, 5:00 AM at the jump-off. See you!', time:'Yesterday' },
  ],
  4: [
    { type:'request', from:'them', booking:{ mountain:'Mt. Pico de Loro', date:'May 5, 2025', time:'4:30 AM', pax:'3 people', type:'Overnight Trek' }, time:'2 hours ago' },
  ],
  5: [
    { from:'them', text:'Thanks for the trail tips! Really helpful.', time:'3 hours ago' },
    { from:'me',   text:'You\'re welcome! Enjoy the hike!', time:'2 hours ago' },
  ],
  6: [
    { type:'request', from:'them', booking:{ mountain:'Mt. Batulao', date:'May 10, 2025', time:'5:00 AM', pax:'8 people', type:'Day Hike + Picnic' }, time:'5 hours ago' },
  ],
};

let activeThread = 0;
let currentFilter = 'all'; // 'all', 'bookings', 'unread'
let searchQuery = '';

// Helper: Check if thread has booking request
function hasBookingRequest(threadId) {
  const msgs = chatsData[threadId] || [];
  return msgs.some(m => m.type === 'request');
}

// Helper: Check if thread has unread messages
function hasUnread(thread) {
  return thread.unread > 0;
}

// Filter threads based on current tab and search
function getFilteredThreads() {
  let filtered = [...threadsData];
  
  // Apply tab filter
  if (currentFilter === 'bookings') {
    filtered = filtered.filter(t => hasBookingRequest(t.id));
  } else if (currentFilter === 'unread') {
    filtered = filtered.filter(t => hasUnread(t));
  }
  
  // Apply search filter
  if (searchQuery.trim() !== '') {
    const query = searchQuery.toLowerCase();
    filtered = filtered.filter(t => 
      t.name.toLowerCase().includes(query) || 
      t.preview.toLowerCase().includes(query) ||
      t.sub.toLowerCase().includes(query)
    );
  }
  
  return filtered;
}

function renderThreadList() {
  const el = document.getElementById('threadItems');
  if (!el) return;
  
  const filteredThreads = getFilteredThreads();
  
  if (filteredThreads.length === 0) {
    el.innerHTML = `<div style="padding: 40px 20px; text-align: center; color: var(--ink-4);">
                      <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 8px; display: block;"></i>
                      No conversations found
                    </div>`;
    return;
  }
  
  el.innerHTML = '';
  filteredThreads.forEach(t => {
    const div = document.createElement('div');
    div.className = 'thread-item' + (t.id === activeThread ? ' active' : '');
    div.innerHTML = `
      <div class="thread-avatar">
        ${t.initials}
        ${t.online ? '<div class="thread-online-dot"></div>' : ''}
      </div>
      <div class="thread-info">
        <div class="thread-name">
          ${t.name}
          <span class="thread-time">${t.time}</span>
        </div>
        <div class="thread-preview">${t.preview}</div>
      </div>
      ${t.unread ? `<div class="thread-unread">${t.unread}</div>` : ''}`;
    div.addEventListener('click', () => openThread(t.id));
    el.appendChild(div);
  });
}

function renderChat(id) {
  const msgs = chatsData[id] || [];
  const el = document.getElementById('chatMessages');
  if (!el) return;
  el.innerHTML = '';

  let lastGroup = null;
  msgs.forEach(m => {
    if (m.type === 'request') {
      const wrap = document.createElement('div');
      wrap.style.display = 'flex';
      wrap.style.justifyContent = 'flex-start';
      wrap.innerHTML = `
        <div class="booking-request-card">
          <div class="brc-header"><i class="fas fa-calendar-check" style="margin-right:5px;"></i>Booking Request</div>
          <div class="brc-row"><span class="brc-label">Mountain</span><span class="brc-val">${m.booking.mountain}</span></div>
          <div class="brc-row"><span class="brc-label">Date</span><span class="brc-val">${m.booking.date}</span></div>
          <div class="brc-row"><span class="brc-label">Assembly</span><span class="brc-val">${m.booking.time}</span></div>
          <div class="brc-row"><span class="brc-label">Hikers</span><span class="brc-val">${m.booking.pax}</span></div>
          <div class="brc-row"><span class="brc-label">Type</span><span class="brc-val">${m.booking.type}</span></div>
          <div class="brc-actions">
            <button class="btn btn-outline btn-sm" onclick="declineBooking(${id})">Decline</button>
            <button class="btn btn-primary btn-sm" onclick="acceptBooking(${id})"><i class="fas fa-check"></i> Accept</button>
          </div>
        </div>`;
      el.appendChild(wrap);
      return;
    }

    const side = m.from === 'me' ? 'mine' : 'theirs';
    if (!lastGroup || lastGroup.dataset.side !== side) {
      lastGroup = document.createElement('div');
      lastGroup.className = `msg-group ${side}`;
      lastGroup.dataset.side = side;
      el.appendChild(lastGroup);
    }
    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';
    bubble.textContent = m.text;
    lastGroup.appendChild(bubble);
    const timeEl = document.createElement('div');
    timeEl.className = 'msg-time';
    timeEl.textContent = m.time;
    lastGroup.appendChild(timeEl);
  });

  el.scrollTop = el.scrollHeight;
}

function openThread(id) {
  activeThread = id;
  const t = threadsData.find(x => x.id === id);
  if (t) {
    t.unread = 0;
    document.getElementById('chatName').textContent = t.name;
    document.getElementById('chatSub').textContent = t.sub;
    document.getElementById('chatAvatarHd').textContent = t.initials;
  }
  renderThreadList();
  renderChat(id);

  // mobile: hide thread list
  if (window.innerWidth <= 768) {
    document.getElementById('threadList').classList.add('hidden');
    document.getElementById('backBtn').style.display = 'flex';
  }
}

function showThreadList() {
  document.getElementById('threadList').classList.remove('hidden');
  document.getElementById('backBtn').style.display = 'none';
}

function sendMessage() {
  const input = document.getElementById('msgInput');
  const text = input.value.trim();
  if (!text) return;
  if (!chatsData[activeThread]) chatsData[activeThread] = [];
  chatsData[activeThread].push({ from:'me', text, time: new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) });
  const thread = threadsData.find(x=>x.id===activeThread);
  if (thread) thread.preview = text;
  renderChat(activeThread);
  renderThreadList();
  input.value = '';
}

function sendQuick(text) {
  if (!chatsData[activeThread]) chatsData[activeThread] = [];
  chatsData[activeThread].push({ from:'me', text, time: new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) });
  renderChat(activeThread);
  const thread = threadsData.find(x=>x.id===activeThread);
  if (thread) thread.preview = text;
  renderThreadList();
}

function acceptBooking(threadId) {
  // Remove the booking request message from chat
  const msgs = chatsData[threadId];
  if (msgs) {
    const requestIndex = msgs.findIndex(m => m.type === 'request');
    if (requestIndex !== -1) {
      msgs.splice(requestIndex, 1);
      msgs.push({ from:'me', text: '✅ Booking accepted! See you on the trail!', time: new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) });
    }
  }
  // Update thread preview
  const thread = threadsData.find(t => t.id === threadId);
  if (thread) {
    thread.preview = 'Booking accepted ✓';
    thread.hasRequest = false;
  }
  renderChat(threadId);
  renderThreadList();
  showToast('Booking accepted! ✅');
}

function declineBooking(threadId) {
  const msgs = chatsData[threadId];
  if (msgs) {
    const requestIndex = msgs.findIndex(m => m.type === 'request');
    if (requestIndex !== -1) {
      msgs.splice(requestIndex, 1);
      msgs.push({ from:'me', text: '❌ Booking declined. Let me know if you need alternative dates.', time: new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) });
    }
  }
  const thread = threadsData.find(t => t.id === threadId);
  if (thread) {
    thread.preview = 'Booking declined';
    thread.hasRequest = false;
  }
  renderChat(threadId);
  renderThreadList();
  showToast('Booking declined');
}

function setActiveTab(tab) {
  currentFilter = tab;
  // Update active class on tabs
  document.querySelectorAll('.thread-tab').forEach(t => {
    if (t.getAttribute('data-tab') === tab) {
      t.classList.add('active');
    } else {
      t.classList.remove('active');
    }
  });
  renderThreadList();
  
  // If current active thread is no longer in filtered list, clear selection or select first
  const filtered = getFilteredThreads();
  if (filtered.length > 0 && !filtered.some(t => t.id === activeThread)) {
    openThread(filtered[0].id);
  } else if (filtered.length === 0) {
    // Clear chat area
    document.getElementById('chatMessages').innerHTML = `<div class="chat-empty"><i class="fas fa-comment-slash"></i><p>No conversations in this category</p></div>`;
  }
}

function filterThreads() {
  const searchInput = document.getElementById('searchInput');
  searchQuery = searchInput ? searchInput.value : '';
  renderThreadList();
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

// Initialize
renderThreadList();
renderChat(0);
</script>
</body>
</html>