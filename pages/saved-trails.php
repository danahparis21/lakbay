<?php
$pageTitle='Saved Trails — LAKBAY'; $activePage=''; $isLoggedIn=true; $userRole='hiker'; $base='../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
$saved = [$mountains[0],$mountains[3]]; // Batulao + Talamitam saved
?>
<div class="dashboard-layout">
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user"><div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div><div class="sidebar-username">Juan Dela Cruz</div><div class="sidebar-role">Hiker</div></div>
    <nav class="sidebar-nav"><div class="sidebar-section">
      <a href="dashboard.php"     class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
      <a href="explore/"          class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Explore</a>
      <a href="trail-history.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Trail History</a>
      <a href="saved-trails.php"  class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Saved Trails</a>
      <a href="hiker-feed.php"    class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Community Feed</a>
      <a href="messages.php"      class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Messages</a>
      <a href="profile.php"       class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>Profile</a>
      <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
    </div></nav>
  </aside>
  <main class="dashboard-main">
    <div class="dashboard-page-header">
      <h1>Saved Trails</h1>
      <p>Your wishlist — <?= count($saved) ?> trail<?= count($saved)!==1?'s':'' ?> saved</p>
    </div>
    <?php if(empty($saved)): ?>
    <div class="empty-state"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><p>No saved trails yet. <a href="explore/" style="color:var(--teal);text-decoration:underline;">Explore mountains</a> to save your favorites.</p></div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach($saved as $m): ?>
      <div class="card mountain-card" id="saved-<?= $m['id'] ?>">
        <div class="mountain-card-img">
          <img src="<?= $m['image'] ?>" alt="<?= htmlspecialchars($m['name']) ?>" loading="lazy">
          <div class="mountain-card-img-overlay"></div>
          <div class="mountain-card-img-content">
            <h3><?= htmlspecialchars($m['name']) ?></h3>
            <?= getDifficultyBadge($m['difficulty']) ?>
          </div>
          <button onclick="unsave(<?= $m['id'] ?>)" style="position:absolute;top:0.75rem;right:0.75rem;background:none;padding:0.3rem;border-radius:50%;background:rgba(0,0,0,0.3);" title="Remove from saved">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="red" stroke="red" stroke-width="1" style="width:18px;height:18px;display:block;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </button>
        </div>
        <div class="card-body">
          <div class="mountain-meta">
            <span><?= $m['elevation'] ?></span>
            <span><?= $m['duration'] ?></span>
            <span>&#8369;<?= $m['fee'] ?></span>
          </div>
          <?= renderStars($m['rating']) ?>
          <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
            <a href="explore/mountain.php?id=<?= $m['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;">View</a>
            <a href="modals/booking.php?mountain=<?= $m['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">Book</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
<script>
function unsave(id){ document.getElementById('saved-'+id).style.display='none'; showToast('Removed from saved trails.'); }
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
