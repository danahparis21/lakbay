<?php
$pageTitle  = 'Guide Dashboard — LAKBAY';
$activePage = '';
$isLoggedIn = true;
$userRole   = 'guide';
$base       = '../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
$guideName = 'Mang Jose Dela Cruz';
$assignedMountains = ['Mt. Batulao','Mt. Talamitam'];
?>

<div class="dashboard-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div class="sidebar-username"><?= htmlspecialchars($guideName) ?></div>
      <div class="sidebar-role">Local Guide</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <div class="sidebar-section-title">Guide Tools</div>
        <a href="dashboard-guide.php" class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
        <a href="#bookings"           class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>My Bookings <span class="sidebar-link-badge">3</span></a>
        <a href="#schedule"           class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Schedule</a>
        <a href="messages.php"        class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages <span class="sidebar-link-badge">1</span></a>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Account</div>
        <a href="profile.php"       class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>My Profile</a>
        <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
      </div>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="dashboard-main">
    <div class="dashboard-page-header">
      <h1>Guide Dashboard</h1>
      <p>Welcome back, <?= htmlspecialchars(explode(' ',$guideName)[1]) ?>! Assigned to: <?= implode(', ', $assignedMountains) ?></p>
    </div>

    <!-- Stats -->
    <div class="stats-row" style="grid-template-columns:repeat(3,1fr);">
      <div class="stat-card stat-teal">
        <div class="stat-card-label">Upcoming Hikes</div>
        <div class="stat-card-value">3</div>
        <div class="stat-card-sub">This week</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      </div>
      <div class="stat-card stat-moss">
        <div class="stat-card-label">Today's Hikes</div>
        <div class="stat-card-value">1</div>
        <div class="stat-card-sub">Active group</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      </div>
      <div class="stat-card stat-sage">
        <div class="stat-card-label">Total Hikes</div>
        <div class="stat-card-value">450</div>
        <div class="stat-card-sub">All-time record</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;" class="guide-grid">
      <!-- Bookings -->
      <div>
        <div class="card" id="bookings">
          <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3>Assigned Bookings</h3>
            <div class="filter-chips" style="gap:0.4rem;">
              <button class="chip active" style="font-size:0.78rem;padding:0.2rem 0.75rem;" onclick="filterBookings('all',this)">All</button>
              <button class="chip" style="font-size:0.78rem;padding:0.2rem 0.75rem;" onclick="filterBookings('approved',this)">Approved</button>
              <button class="chip" style="font-size:0.78rem;padding:0.2rem 0.75rem;" onclick="filterBookings('pending',this)">Pending</button>
            </div>
          </div>
          <div class="card-body">
            <div class="table-wrap">
              <table>
                <thead><tr><th>Mountain</th><th>Date</th><th>Hikers</th><th>Contact</th><th>Status</th></tr></thead>
                <tbody id="bookingTableBody">
                  <?php foreach($bookings as $b):
                    $mt = null; foreach($mountains as $m){ if($m['id']===$b['mountainId']){ $mt=$m; break; } }
                    $statusBadge = ['pending'=>'badge-yellow','approved'=>'badge-moss','rejected'=>'badge-red','completed'=>'badge-teal'][$b['status']]??'badge-sky';
                  ?>
                  <tr data-status="<?= $b['status'] ?>">
                    <td><strong><?= htmlspecialchars($mt['name']??'—') ?></strong></td>
                    <td><?= htmlspecialchars($b['date']) ?></td>
                    <td><?= $b['hikers'] ?> persons</td>
                    <td><?= htmlspecialchars($b['contact']) ?></td>
                    <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($b['status']) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Live Map -->
        <div class="card" style="margin-top:1.25rem;" id="schedule">
          <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3>Live Hiker Map</h3>
            <span class="badge badge-sage" style="font-size:0.78rem;">1 Active Group</span>
          </div>
          <div class="card-body">
            <div class="map-placeholder" style="height:220px;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
              <p>Live GPS tracking of active hikers on your assigned trails</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel -->
      <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <!-- Guide Profile Card -->
        <div class="card">
          <div class="card-header"><h4>My Guide Profile</h4></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <div class="guide-avatar" style="width:56px;height:56px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
              <div>
                <div style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($guideName) ?></div>
                <?= renderStars(4.9) ?>
              </div>
            </div>
            <hr class="divider">
            <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.88rem;">
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--teal);">Rate</span><span style="font-weight:600;">&#8369;800/group</span></div>
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--teal);">Max Hikers</span><span style="font-weight:600;">10 persons</span></div>
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--teal);">Total Hikes</span><span style="font-weight:600;">450</span></div>
            </div>
            <div>
              <div style="font-size:0.8rem;color:var(--teal);margin-bottom:0.4rem;">Assigned Mountains</div>
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                <?php foreach($assignedMountains as $am): ?>
                <span class="badge badge-outline"><?= $am ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <a href="profile.php" class="btn btn-outline btn-sm btn-block">Edit Profile</a>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
          <div class="card-header"><h4>Quick Actions</h4></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:0.75rem;">
            <a href="messages.php" class="btn btn-primary btn-block">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Message Hikers
            </a>
            <a href="#schedule" class="btn btn-outline btn-block">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              View Schedule
            </a>
            <a href="#" onclick="showToast('Availability updated!');return false;" class="btn btn-moss btn-block">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Set Availability
            </a>
          </div>
        </div>

        <!-- Weather -->
        <div class="weather-widget">
          <h4>Trail Weather</h4>
          <div class="weather-temp">26°C</div>
          <div class="weather-desc" style="font-size:0.85rem;margin-top:0.4rem;">Clear skies — good hiking conditions today on Batulao.</div>
          <div class="weather-meta"><span>UV: High</span><span>Wind: Light</span></div>
        </div>
      </div>
    </div>
  </main>
</div>

<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<style>@media(max-width:900px){.guide-grid{grid-template-columns:1fr!important;}}</style>
<script>
function filterBookings(status,el){
  document.querySelectorAll('.chip').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('#bookingTableBody tr').forEach(r=>{
    r.style.display = (status==='all'||r.dataset.status===status)?'':'none';
  });
}
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
