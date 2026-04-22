<?php
$pageTitle  = 'Trail History — LAKBAY';
$activePage = '';
$isLoggedIn = true;
$userRole   = 'hiker';
$base       = '../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
?>
<div class="dashboard-layout">
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div class="sidebar-username">Juan Dela Cruz</div>
      <div class="sidebar-role">Hiker</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <a href="dashboard.php"     class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
        <a href="explore/"          class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Explore</a>
        <a href="trail-history.php" class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Trail History</a>
        <a href="saved-trails.php"  class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Saved Trails</a>
        <a href="hiker-feed.php"    class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Community Feed</a>
        <a href="messages.php"      class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Messages</a>
        <a href="profile.php"       class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>Profile</a>
        <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
      </div>
    </nav>
  </aside>
  <main class="dashboard-main">
    <div class="dashboard-page-header">
      <h1>Trail History</h1>
      <p>Your past hikes and upcoming bookings</p>
    </div>

    <div class="tabs">
      <button class="tab-btn active" onclick="switchTab('past',this)">Past Hikes</button>
      <button class="tab-btn" onclick="switchTab('upcoming',this)">Upcoming</button>
      <button class="tab-btn" onclick="switchTab('pending',this)">Pending</button>
    </div>

    <div class="tab-panel active" id="tab-past">
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php
        $myHikes=[
          ['mountain'=>1,'date'=>'2026-03-15','guide'=>'Mang Jose Dela Cruz','hikers'=>4,'status'=>'completed','rating'=>5],
          ['mountain'=>2,'date'=>'2026-02-28','guide'=>'Ate Nena Santos','hikers'=>2,'status'=>'completed','rating'=>4],
          ['mountain'=>4,'date'=>'2026-01-20','guide'=>'Kuya Marco Lim','hikers'=>6,'status'=>'completed','rating'=>5],
        ];
        foreach($myHikes as $h):
          $mt=null; foreach($mountains as $m){ if($m['id']===$h['mountain']){ $mt=$m; break; } }
        ?>
        <div class="card card--flat">
          <div class="card-body" style="display:flex;gap:1rem;align-items:flex-start;">
            <img src="<?= $mt['image'] ?>" alt="" style="width:100px;height:75px;object-fit:cover;border-radius:var(--radius);flex-shrink:0;">
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                <div>
                  <h4 style="color:var(--navy);margin-bottom:0.2rem;"><?= htmlspecialchars($mt['name']) ?></h4>
                  <p style="font-size:0.85rem;margin:0;"><?= $h['date'] ?> · Guide: <?= htmlspecialchars($h['guide']) ?> · <?= $h['hikers'] ?> hikers</p>
                </div>
                <span class="badge badge-teal">Completed</span>
              </div>
              <div style="margin-top:0.5rem;display:flex;align-items:center;gap:1rem;">
                <?= renderStars($h['rating']) ?>
                <a href="explore/mountain.php?id=<?= $mt['id'] ?>" class="btn btn-outline btn-sm">View Trail</a>
                <a href="modals/booking.php?mountain=<?= $mt['id'] ?>" class="btn btn-primary btn-sm">Book Again</a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-panel" id="tab-upcoming">
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach(array_filter($bookings,fn($b)=>$b['status']==='approved') as $b):
          $mt=null; foreach($mountains as $m){ if($m['id']===$b['mountainId']){ $mt=$m; break; } }
        ?>
        <div class="card card--flat">
          <div class="card-body" style="display:flex;gap:1rem;align-items:flex-start;">
            <img src="<?= $mt['image'] ?>" alt="" style="width:100px;height:75px;object-fit:cover;border-radius:var(--radius);flex-shrink:0;">
            <div style="flex:1;">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                <div>
                  <h4 style="color:var(--navy);margin-bottom:0.2rem;"><?= htmlspecialchars($mt['name']) ?></h4>
                  <p style="font-size:0.85rem;margin:0;"><?= $b['date'] ?> · <?= $b['hikers'] ?> hikers</p>
                  <p style="font-size:0.85rem;margin:0.2rem 0 0;"><?= htmlspecialchars($b['details']) ?></p>
                </div>
                <span class="badge badge-moss">Confirmed</span>
              </div>
              <div style="margin-top:0.75rem;">
                <button class="btn btn-danger btn-sm" onclick="showToast('Booking cancellation submitted.','error');">Cancel Booking</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-panel" id="tab-pending">
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach(array_filter($bookings,fn($b)=>$b['status']==='pending') as $b):
          $mt=null; foreach($mountains as $m){ if($m['id']===$b['mountainId']){ $mt=$m; break; } }
        ?>
        <div class="card card--flat">
          <div class="card-body" style="display:flex;gap:1rem;align-items:flex-start;">
            <img src="<?= $mt['image'] ?>" alt="" style="width:100px;height:75px;object-fit:cover;border-radius:var(--radius);flex-shrink:0;">
            <div style="flex:1;">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                <div>
                  <h4 style="color:var(--navy);margin-bottom:0.2rem;"><?= htmlspecialchars($mt['name']) ?></h4>
                  <p style="font-size:0.85rem;margin:0;"><?= $b['date'] ?> · <?= $b['hikers'] ?> hikers · <?= $b['contact'] ?></p>
                </div>
                <span class="badge badge-yellow">Pending Review</span>
              </div>
              <div class="alert alert-info" style="margin-top:0.75rem;font-size:0.83rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                Awaiting confirmation from the mountain manager. Usually within 24 hours.
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
<script>
function switchTab(id,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-'+id).classList.add('active');
}
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
