<?php
$pageTitle='Messages — LAKBAY'; $activePage=''; $isLoggedIn=true; $userRole='hiker'; $base='../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
?>
<div class="dashboard-layout">
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user"><div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div><div class="sidebar-username">Juan Dela Cruz</div><div class="sidebar-role">Hiker</div></div>
    <nav class="sidebar-nav"><div class="sidebar-section">
      <a href="dashboard.php"     class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
      <a href="explore/"          class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Explore</a>
      <a href="trail-history.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Trail History</a>
      <a href="saved-trails.php"  class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Saved Trails</a>
      <a href="hiker-feed.php"    class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Community Feed</a>
      <a href="messages.php"      class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Messages <span class="sidebar-link-badge">2</span></a>
      <a href="profile.php"       class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>Profile</a>
      <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
    </div></nav>
  </aside>

  <main class="dashboard-main" style="padding:0;">
    <div class="card" style="margin:1.5rem;height:calc(100vh - 64px - 3rem);min-height:500px;display:flex;flex-direction:column;">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <h3 style="margin:0;">Messages</h3>
        <button class="btn btn-primary btn-sm" onclick="showToast('New conversation feature coming soon.');">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          New Message
        </button>
      </div>
      <div style="display:grid;grid-template-columns:300px 1fr;flex:1;overflow:hidden;" class="msg-layout">
        <!-- Conversation List -->
        <div class="chat-list">
          <div class="chat-list-header">Conversations</div>
          <?php
          $convos=[
            ['id'=>1,'name'=>'Mang Jose Dela Cruz','role'=>'Guide','last'=>'See you at the jump-off at 5AM!','time'=>'9:30 AM','unread'=>true],
            ['id'=>2,'name'=>'Mt. Batulao Manager','role'=>'Manager','last'=>'Your booking for Apr 25 is confirmed.','time'=>'Yesterday','unread'=>true],
            ['id'=>3,'name'=>'Kuya Bong Reyes','role'=>'Guide','last'=>'Thanks for the review!','time'=>'Apr 17','unread'=>false],
            ['id'=>4,'name'=>'LAKBAY Support','role'=>'System','last'=>'Welcome to LAKBAY! How can we help?','time'=>'Apr 10','unread'=>false],
          ];
          foreach($convos as $c): ?>
          <div class="chat-item <?= $c['id']===1?'active':'' ?>" onclick="openChat(<?= $c['id'] ?>,'<?= addslashes($c['name']) ?>','<?= $c['role'] ?>')" id="conv-<?= $c['id'] ?>">
            <div class="chat-item-avatar">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div class="chat-item-name" style="<?= $c['unread']?'font-weight:700;':'' ?>"><?= htmlspecialchars($c['name']) ?></div>
                <div class="chat-item-time"><?= $c['time'] ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:0.3rem;">
                <div class="chat-item-preview"><?= htmlspecialchars($c['last']) ?></div>
                <?php if($c['unread']): ?><div style="width:8px;height:8px;background:var(--teal);border-radius:50%;flex-shrink:0;"></div><?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Chat Window -->
        <div class="chat-window" id="chatWindow">
          <div class="chat-header" id="chatHeader">
            <div class="chat-item-avatar">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </div>
            <div>
              <div style="font-weight:600;color:var(--navy);" id="chatName">Mang Jose Dela Cruz</div>
              <div style="font-size:0.8rem;color:var(--teal);" id="chatRole">Guide</div>
            </div>
          </div>
          <div class="chat-messages" id="chatMessages">
            <div class="msg recv"><div class="msg-text">Good morning! Just confirming our hike for April 25 on Mt. Batulao.</div><div class="msg-time">9:00 AM</div></div>
            <div class="msg sent"><div class="msg-text">Good morning, Mang Jose! Yes, confirmed. How early should we arrive?</div><div class="msg-time">9:15 AM</div></div>
            <div class="msg recv"><div class="msg-text">Please be at the Barangay Hall by 5AM for registration. I'll meet you there.</div><div class="msg-time">9:20 AM</div></div>
            <div class="msg sent"><div class="msg-text">Perfect, we'll be there. Should we bring anything specific?</div><div class="msg-time">9:25 AM</div></div>
            <div class="msg recv"><div class="msg-text">Bring at least 2 liters of water each, sunscreen, and light snacks. See you at the jump-off at 5AM!</div><div class="msg-time">9:30 AM</div></div>
          </div>
          <div class="chat-input-area">
            <input class="form-control" type="text" id="msgInput" placeholder="Type a message…" onkeydown="if(event.key==='Enter')sendMsg()">
            <button class="btn btn-primary" onclick="sendMsg()">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>

<style>
@media(max-width:768px){
  .msg-layout{grid-template-columns:1fr!important;}
  #chatWindow{display:none;}
  .msg-layout.chat-open .chat-list{display:none;}
  .msg-layout.chat-open #chatWindow{display:flex;}
}
</style>
<script>
function sendMsg(){
  var inp=document.getElementById('msgInput');
  var txt=inp.value.trim();
  if(!txt) return;
  var msgs=document.getElementById('chatMessages');
  var now=new Date();
  var t=(now.getHours()%12||12)+':'+(now.getMinutes()<10?'0':'')+now.getMinutes()+' '+(now.getHours()<12?'AM':'PM');
  var el=document.createElement('div');
  el.className='msg sent';
  el.innerHTML='<div class="msg-text">'+txt+'</div><div class="msg-time">'+t+'</div>';
  msgs.appendChild(el);
  msgs.scrollTop=msgs.scrollHeight;
  inp.value='';
  // Simulate reply
  setTimeout(function(){
    var r=document.createElement('div');
    r.className='msg recv';
    r.innerHTML='<div class="msg-text">Got it! See you on the trail.</div><div class="msg-time">'+t+'</div>';
    msgs.appendChild(r);
    msgs.scrollTop=msgs.scrollHeight;
  },1200);
}

function openChat(id,name,role){
  document.querySelectorAll('.chat-item').forEach(c=>c.classList.remove('active'));
  document.getElementById('conv-'+id).classList.add('active');
  document.getElementById('chatName').textContent=name;
  document.getElementById('chatRole').textContent=role;
  document.getElementById('chatMessages').scrollTop=99999;
  document.querySelector('.msg-layout').classList.add('chat-open');
}

document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
document.getElementById('chatMessages').scrollTop=99999;
</script>
<?php include_once $base.'includes/footer.php'; ?>
