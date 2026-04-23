<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY — Messages</title>
<link rel="stylesheet" href="shared.css">
<style>
.messages-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  height: calc(100vh - var(--nav-h));
  overflow: hidden;
}
@media(max-width:768px){
  .messages-layout { grid-template-columns: 1fr; height: calc(100vh - var(--nav-h) - var(--nav-h)); }
  .msg-sidebar { display: block; }
  .msg-sidebar.hidden-mobile { display: none; }
  .msg-main.hidden-mobile { display: none; }
  .msg-main { display: flex; flex-direction: column; }
}

/* SIDEBAR */
.msg-sidebar {
  border-right: 1px solid rgba(90,122,90,0.12);
  display: flex; flex-direction: column;
  background: var(--white);
}
.msg-sidebar-hdr {
  padding: 20px 20px 16px;
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
.online-dot {
  position: absolute; bottom: 1px; right: 1px;
  width: 10px; height: 10px; border-radius: 50%;
  background: #4ade80; border: 2px solid var(--white);
}
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

/* MAIN CHAT */
.msg-main { display: flex; flex-direction: column; background: var(--cream); }
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

/* CHAT AREA */
.chat-area {
  flex: 1; overflow-y: auto; padding: 20px 24px;
  display: flex; flex-direction: column; gap: 14px;
}
.msg-bubble-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-bubble-row.mine { flex-direction: row-reverse; }
.msg-av-xs {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--forest); color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700; flex-shrink: 0;
}
.msg-bubble {
  max-width: 70%;
  padding: 12px 16px; border-radius: 20px;
  font-size: 13.5px; line-height: 1.5;
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

/* SYSTEM MSG */
.sys-msg {
  text-align: center; font-size: 11px; color: var(--stone);
  background: rgba(90,122,90,0.08);
  border-radius: 50px; padding: 6px 16px;
  align-self: center;
}

/* INPUT */
.chat-input-row {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 24px;
  background: var(--white);
  border-top: 1px solid rgba(90,122,90,0.1);
  flex-shrink: 0;
}
.chat-input {
  flex: 1; padding: 12px 18px; border-radius: 50px;
  border: 1.5px solid rgba(90,122,90,0.15);
  background: var(--sky); font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; color: var(--forest); outline: none; transition: .2s;
}
.chat-input:focus { border-color: var(--sage); background: var(--white); }
.send-btn {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--forest); color: var(--cream);
  border: none; cursor: pointer; display: flex;
  align-items: center; justify-content: center;
  transition: transform .15s;
  flex-shrink: 0;
}
.send-btn:hover { transform: scale(1.08); }

/* EMPTY STATE */
.chat-empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  color: var(--stone); padding: 40px;
}
.chat-empty-icon { font-size: 48px; margin-bottom: 16px; }

/* SYSTEM MESSAGES */
.sys-thread { border-left: 3px solid var(--gold); }
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
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      Explore
    </a>
    <a href="bookings.php" class="tab-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      Bookings
    </a>
    <a href="quiz.php" class="tab-link quiz-tab">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Quiz
    </a>
    <a href="messages.php" class="tab-link active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Messages
    </a>
  </div>
  <a href="hikerProfile.php class="user-btn">J</a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <span>Explore</span>
    </a>
    <a href="bookings.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <span>Bookings</span>
    </a>
    <a href="quiz.php" class="mob-nav-item quiz-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    </a>
    <a href="messages.php" class="mob-nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span>Messages</span>
    </a>
    <a href="profile.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Profile</span>
    </a>
  </div>
</nav>

<!-- MESSAGES LAYOUT -->
<div class="messages-layout" style="margin-top:var(--nav-h);">

  <!-- SIDEBAR -->
  <div class="msg-sidebar" id="msgSidebar">
    <div class="msg-sidebar-hdr">
      <h2>Messages</h2>
      <div class="msg-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" placeholder="Search conversations..." id="msgSearch" oninput="filterThreads(this.value)">
      </div>
    </div>
    <div class="msg-list" id="msgList"></div>
  </div>

  <!-- MAIN CHAT -->
  <div class="msg-main" id="msgMain">
    <div class="chat-empty" id="chatEmpty">
      <div class="chat-empty-icon">💬</div>
      <div style="font-weight:600;font-size:15px;color:var(--forest);margin-bottom:8px;">Your messages</div>
      <p style="font-size:13px;text-align:center;line-height:1.6;">Select a conversation to start chatting with your tour guide.</p>
    </div>
    <div id="chatView" style="display:none;flex:1;display:none;flex-direction:column;">
      <div class="msg-main-hdr">
        <button class="msg-back-btn" onclick="showSidebar()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        </button>
        <div class="msg-thread-av" id="chatHdrAv" style="width:40px;height:40px;font-size:14px;flex-shrink:0;border-radius:50%;background:var(--forest);color:var(--cream);display:flex;align-items:center;justify-content:center;font-weight:700;"></div>
        <div class="msg-hdr-info">
          <div class="msg-hdr-name" id="chatHdrName"></div>
          <div class="msg-hdr-sub" id="chatHdrSub"></div>
        </div>
      </div>
      <div class="chat-area" id="chatArea"></div>
      <div class="chat-input-row">
        <input class="chat-input" type="text" id="chatInput" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendMsg()">
        <button class="send-btn" onclick="sendMsg()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>
  </div>

</div>

<script>
const threads = [
  { id:1, name:"Maya Reyes", initials:"MR", online:true, role:"Tour Guide · Mt. Batulao", time:"10:42 AM", unread:2,
    messages:[
      {mine:false, text:"Hi! I confirmed your booking for Mt. Batulao on May 15. 😊", time:"10:30 AM"},
      {mine:true, text:"Great! What time should we meet?", time:"10:35 AM"},
      {mine:false, text:"Let's meet at the jump-off point by 5:00 AM. I'll be in a green jacket.", time:"10:38 AM"},
      {mine:false, text:"Please bring at least 2L of water and wear trail shoes. See you there!", time:"10:42 AM"}
    ]
  },
  { id:2, name:"LAKBAY System", initials:"🔔", online:false, role:"System Notifications", time:"Yesterday", unread:1, system:true,
    messages:[
      {mine:false, text:"🎉 Your booking BK001 for Mt. Batulao has been confirmed! Your guide Maya Reyes will be in touch shortly.", time:"Yesterday 9:00 AM"},
      {mine:false, text:"⚠️ Weather advisory: Light rain expected on May 14–15 in Nasugbu. Your guide has been notified. Stay updated!", time:"Yesterday 2:00 PM"}
    ]
  },
  { id:3, name:"John Dela Cruz", initials:"JD", online:false, role:"Tour Guide · Mt. Apayang", time:"Mon",
    messages:[
      {mine:true, text:"Hi John! I'm interested in hiking Apayang next month.", time:"Mon 3:00 PM"},
      {mine:false, text:"Hello! Sure, I'd love to guide you. What dates are you considering?", time:"Mon 3:15 PM"}
    ]
  }
];

let activeThread = null;
let allMessages = {};

function renderThreads(list) {
  const el = document.getElementById('msgList');
  el.innerHTML = list.map(t=>`
    <div class="msg-thread ${t.system?'sys-thread':''} ${activeThread===t.id?'active':''}" onclick="openThread(${t.id})">
      <div class="msg-thread-av">
        ${t.system?'🔔':t.initials}
        ${t.online?'<div class="online-dot"></div>':''}
      </div>
      <div class="msg-thread-info">
        <div class="msg-thread-name">${t.name}</div>
        <div class="msg-thread-preview">${t.messages[t.messages.length-1]?.text||''}</div>
      </div>
      <div class="msg-thread-meta">
        <div class="msg-thread-time">${t.time}</div>
        ${t.unread?`<div class="msg-unread">${t.unread}</div>`:''}
      </div>
    </div>
  `).join('');
}

function openThread(id) {
  activeThread = id;
  const t = threads.find(x=>x.id===id);
  t.unread = 0;
  renderThreads(threads);
  if(!allMessages[id]) allMessages[id] = [...t.messages];

  document.getElementById('chatEmpty').style.display = 'none';
  const cv = document.getElementById('chatView');
  cv.style.display = 'flex';

  document.getElementById('chatHdrAv').textContent = t.system?'🔔':t.initials;
  document.getElementById('chatHdrName').textContent = t.name;
  document.getElementById('chatHdrSub').textContent = t.online?'🟢 Online · '+t.role:t.role;

  renderMessages(id);

  // Mobile: hide sidebar
  if(window.innerWidth<=768) {
    document.getElementById('msgSidebar').classList.add('hidden-mobile');
  }
}

function renderMessages(id) {
  const msgs = allMessages[id] || [];
  const t = threads.find(x=>x.id===id);
  const ca = document.getElementById('chatArea');
  ca.innerHTML = `
    <div class="sys-msg">Booking conversation · ${t.role}</div>
    ${msgs.map(m=>m.mine?`
      <div class="msg-bubble-row mine">
        <div>
          <div class="msg-bubble mine">${m.text}</div>
          <div class="msg-time">${m.time}</div>
        </div>
      </div>
    `:`
      <div class="msg-bubble-row">
        <div class="msg-av-xs">${t.system?'🔔':t.initials}</div>
        <div>
          <div class="msg-bubble theirs">${m.text}</div>
          <div class="msg-time">${m.time}</div>
        </div>
      </div>
    `).join('')}
  `;
  ca.scrollTop = ca.scrollHeight;
}

function sendMsg() {
  const inp = document.getElementById('chatInput');
  const msg = inp.value.trim();
  if(!msg || !activeThread) return;
  if(!allMessages[activeThread]) allMessages[activeThread]=[];
  const now = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
  allMessages[activeThread].push({mine:true,text:msg,time:now});
  inp.value='';
  renderMessages(activeThread);

  // Auto-reply after delay
  setTimeout(()=>{
    const replies = ["I'll check on that! 😊","Noted! See you on the day.","Thanks for the message. I'll get back to you shortly.","Great! Stay hydrated and train well. 💪"];
    allMessages[activeThread].push({mine:false,text:replies[Math.floor(Math.random()*replies.length)],time:new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'})});
    renderMessages(activeThread);
  },1200);
}

function filterThreads(q) {
  const filtered = threads.filter(t=>t.name.toLowerCase().includes(q.toLowerCase()));
  renderThreads(filtered);
}

function showSidebar() {
  document.getElementById('msgSidebar').classList.remove('hidden-mobile');
  document.getElementById('chatView').style.display = 'none';
  document.getElementById('chatEmpty').style.display = 'flex';
  activeThread = null;
}

renderThreads(threads);
</script>
</body>
</html>
