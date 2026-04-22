<?php
$pageTitle='Community Feed — LAKBAY'; $activePage=''; $isLoggedIn=true; $userRole='hiker'; $base='../';
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
      <a href="hiker-feed.php"    class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Community Feed</a>
      <a href="messages.php"      class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Messages</a>
      <a href="profile.php"       class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>Profile</a>
      <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
    </div></nav>
  </aside>
  <main class="dashboard-main">
    <div class="dashboard-page-header">
      <h1>Community Feed</h1>
      <p>Posts and updates from fellow hikers in Nasugbu</p>
    </div>

    <!-- Create Post -->
    <div class="card card--flat" style="margin-bottom:1.5rem;">
      <div class="card-body">
        <h4 style="margin-bottom:0.75rem;">Share Your Experience</h4>
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
          <textarea class="form-control" rows="2" placeholder="What was your hike like? Share with the community…" id="postText"></textarea>
          <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <select class="form-control" style="width:auto;flex:1;max-width:220px;" id="postMtn">
              <option value="">Select Mountain</option>
              <?php foreach($mountains as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary" onclick="createPost()">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Post
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Posts -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;" id="feedList">
      <?php foreach($posts as $p): ?>
      <div class="card card--flat post-card" id="post-<?= $p['id'] ?>">
        <div class="card-body">
          <div class="post-header">
            <div class="post-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
            <div>
              <div class="post-user"><?= htmlspecialchars($p['user']) ?></div>
              <div class="post-meta"><a href="explore/mountain.php?id=<?php foreach($mountains as $m){ if($m['name']===$p['mountain']) echo $m['id']; } ?>" style="color:var(--teal);"><?= htmlspecialchars($p['mountain']) ?></a> · <?= $p['date'] ?></div>
            </div>
          </div>
          <p class="post-caption"><?= htmlspecialchars($p['caption']) ?></p>
          <img src="<?= $p['image'] ?>" alt="" class="post-img" loading="lazy">
          <div class="post-actions">
            <button class="post-action-btn" id="like-<?= $p['id'] ?>" onclick="toggleLike(<?= $p['id'] ?>,<?= $p['likes'] ?>)">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              <span id="likes-<?= $p['id'] ?>"><?= $p['likes'] ?></span>
            </button>
            <button class="post-action-btn" onclick="showToast('Comment feature coming soon.')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Comment
            </button>
            <button class="post-action-btn" onclick="showToast('Link copied!')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
              Share
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
<script>
var liked={};
function toggleLike(id,base){
  var btn=document.getElementById('like-'+id);
  var cnt=document.getElementById('likes-'+id);
  if(liked[id]){ liked[id]=false; btn.classList.remove('liked'); cnt.textContent=base; }
  else { liked[id]=true; btn.classList.add('liked'); cnt.textContent=base+1; }
}
function createPost(){
  var txt=document.getElementById('postText').value.trim();
  var mtn=document.getElementById('postMtn');
  if(!txt){ showToast('Please write something first.','error'); return; }
  var mname=mtn.options[mtn.selectedIndex].text||'Nasugbu';
  var el=document.createElement('div');
  el.className='card card--flat post-card';
  el.innerHTML='<div class="card-body"><div class="post-header"><div class="post-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div><div><div class="post-user">Juan Dela Cruz</div><div class="post-meta">'+mname+' · Just now</div></div></div><p class="post-caption">'+txt+'</p><div class="post-actions"><button class="post-action-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>0</button></div></div>';
  document.getElementById('feedList').prepend(el);
  document.getElementById('postText').value='';
  showToast('Post shared with the community!');
}
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
