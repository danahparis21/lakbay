<?php
$pageTitle  = 'My Dashboard — LAKBAY';
$activePage = 'dashboard';
$isLoggedIn = true;
$userRole   = 'hiker';
$base       = '../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
$userName = 'Juan Dela Cruz';
?>

<div class="dashboard-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="dashSidebar" role="navigation" aria-label="Hiker navigation">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div class="sidebar-username"><?= htmlspecialchars($userName) ?></div>
      <div class="sidebar-role">Hiker</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <div class="sidebar-section-title">Main</div>
        <a href="dashboard.php"       class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
        <a href="explore/"            class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Explore Mountains</a>
        <a href="explore/?quiz=1"     class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Find My Trail</a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-section-title">My Activity</div>
        <a href="trail-history.php"   class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Trail History</a>
        <a href="saved-trails.php"    class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Saved Trails</a>
        <a href="hiker-feed.php"      class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Community Feed</a>
        <a href="messages.php"        class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Messages <span class="sidebar-link-badge">2</span></a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Account</div>
        <a href="profile.php"         class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>My Profile</a>
        <a href="modals/logout.php"   class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
      </div>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="dashboard-main" id="dashMain">
    <div class="dashboard-page-header">
      <h1>Hello, <?= htmlspecialchars(explode(' ',$userName)[0]) ?>!</h1>
      <p>Ready for your next adventure in Nasugbu?</p>
    </div>

    <!-- Stats -->
    <div class="stats-row" style="grid-template-columns:repeat(3,1fr);">
      <div class="stat-card stat-teal">
        <div class="stat-card-label">Total Hikes</div>
        <div class="stat-card-value">5</div>
        <div class="stat-card-sub">Completed trails</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg></div>
      </div>
      <div class="stat-card stat-moss">
        <div class="stat-card-label">Saved Trails</div>
        <div class="stat-card-value">2</div>
        <div class="stat-card-sub">Wishlisted mountains</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
      </div>
      <div class="stat-card stat-sage">
        <div class="stat-card-label">Pending Bookings</div>
        <div class="stat-card-value">1</div>
        <div class="stat-card-sub">Awaiting confirmation</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid-4" style="margin-bottom:2rem;">
      <?php
      $actions = [
        ['href'=>'explore/?quiz=1','bg'=>'var(--teal)','icon'=>'<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>','title'=>'Find My Trail','desc'=>'Take the quiz'],
        ['href'=>'trail-history.php','bg'=>'var(--moss)','icon'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>','title'=>'Trail History','desc'=>'View past hikes'],
        ['href'=>'saved-trails.php','bg'=>'var(--sage)','icon'=>'<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>','title'=>'Saved Trails','desc'=>'Your favorites'],
        ['href'=>'hiker-feed.php','bg'=>'#3a7ca5','icon'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>','title'=>'Community Feed','desc'=>'Posts & updates'],
      ];
      foreach($actions as $a): ?>
      <a href="<?= $a['href'] ?>" class="card" style="cursor:pointer;">
        <div class="card-body">
          <div style="width:48px;height:48px;background:<?= $a['bg'] ?>;border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:0.75rem;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="width:22px;height:22px;"><?= $a['icon'] ?></svg>
          </div>
          <h4 style="color:var(--navy);"><?= $a['title'] ?></h4>
          <p style="font-size:0.85rem;margin:0.2rem 0 0;"><?= $a['desc'] ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Mountains + Search -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
      <h3 style="color:var(--navy);margin:0;">Explore Mountains</h3>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <div class="input-icon-wrap" style="width:240px;">
          <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="form-control" type="text" id="dashSearch" placeholder="Search mountains…" style="padding-right:1rem;">
        </div>
        <a href="explore/?quiz=1" class="btn btn-primary btn-sm">Find My Trail</a>
      </div>
    </div>

    <div class="grid-3" id="dashMtGrid">
      <?php foreach($mountains as $m): ?>
      <a href="explore/mountain.php?id=<?= $m['id'] ?>" class="card mountain-card" data-name="<?= strtolower($m['name']) ?>">
        <div class="mountain-card-img">
          <img src="<?= $m['image'] ?>" alt="<?= htmlspecialchars($m['name']) ?>" loading="lazy">
          <div class="mountain-card-img-overlay"></div>
          <div class="mountain-card-img-content">
            <h3><?= htmlspecialchars($m['name']) ?></h3>
            <?= getDifficultyBadge($m['difficulty']) ?>
          </div>
        </div>
        <div class="card-body">
          <div class="mountain-meta">
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= $m['duration'] ?></span>
            <span>&#8369;<?= $m['fee'] ?></span>
          </div>
          <?= renderStars($m['rating']) ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<!-- Sidebar mobile toggle -->
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<script>
document.getElementById('dashSearch').addEventListener('input', function(){
  var q = this.value.toLowerCase();
  document.querySelectorAll('#dashMtGrid .mountain-card').forEach(function(c){
    c.style.display = c.dataset.name.includes(q) ? '' : 'none';
  });
});
(function(){
  var btn = document.getElementById('sidebarToggle');
  var sb  = document.getElementById('dashSidebar');
  if(btn) btn.addEventListener('click', function(){ sb.classList.toggle('open'); });
})();
</script>

<?php include_once $base.'includes/footer.php'; ?>
