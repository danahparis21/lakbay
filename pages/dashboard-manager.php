<?php
$pageTitle  = 'Manager Dashboard — LAKBAY';
$activePage = '';
$isLoggedIn = true;
$userRole   = 'manager';
$base       = '../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
$managerName = 'Maria Santos';
$assignedMtn = 'Mt. Batulao';
$mtnId = 1;
$pending  = array_filter($bookings, fn($b)=>$b['status']==='pending');
$approved = array_filter($bookings, fn($b)=>$b['status']==='approved');
?>

<div class="dashboard-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div class="sidebar-username"><?= htmlspecialchars($managerName) ?></div>
      <div class="sidebar-role">Mountain Manager</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <div class="sidebar-section-title">Management</div>
        <a href="dashboard-manager.php" class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
        <a href="#pending"              class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Pending Bookings <span class="sidebar-link-badge"><?= count($pending) ?></span></a>
        <a href="#guides"               class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Manage Guides</a>
        <a href="#trail"                class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Trail Updates</a>
        <a href="messages.php"          class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages</a>
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
      <h1>Manager Dashboard</h1>
      <p>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline;margin-right:0.3rem;"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>
        Managing <?= $assignedMtn ?> — <?= date('l, F j, Y') ?>
      </p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card stat-teal">
        <div class="stat-card-label">Pending Bookings</div>
        <div class="stat-card-value"><?= count($pending) ?></div>
        <div class="stat-card-sub">Needs review</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      </div>
      <div class="stat-card stat-moss">
        <div class="stat-card-label">Approved Today</div>
        <div class="stat-card-value"><?= count($approved) ?></div>
        <div class="stat-card-sub">Confirmed hikes</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg></div>
      </div>
      <div class="stat-card stat-sage">
        <div class="stat-card-label">Hikers on Trail</div>
        <div class="stat-card-value">12</div>
        <div class="stat-card-sub">Live count</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-card-label">Active Guides</div>
        <div class="stat-card-value">3</div>
        <div class="stat-card-sub">On mountain today</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" class="mgr-top-grid">
      <!-- Live Map -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <h3>Live Hiker Map</h3>
          <span class="badge badge-sage">12 Active</span>
        </div>
        <div class="card-body">
          <div class="map-placeholder" style="height:220px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
            <p>Real-time GPS tracking of hikers on <?= $assignedMtn ?></p>
          </div>
        </div>
      </div>

      <!-- Weekly Chart -->
      <div class="card">
        <div class="card-header"><h3>Weekly Hiker Count</h3></div>
        <div class="card-body">
          <div style="display:flex;align-items:flex-end;gap:0.5rem;height:180px;padding-top:1rem;">
            <?php
            $days = ['Mon'=>15,'Tue'=>12,'Wed'=>18,'Thu'=>14,'Fri'=>22,'Sat'=>35,'Sun'=>30];
            $max = max($days);
            foreach($days as $day=>$val):
              $h = round(($val/$max)*150);
            ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;flex:1;">
              <span style="font-size:0.7rem;color:var(--teal);font-weight:600;"><?= $val ?></span>
              <div style="width:100%;height:<?= $h ?>px;background:var(--teal);border-radius:4px 4px 0 0;opacity:<?= $day==='Sat'||$day==='Sun'?1:0.65 ?>;transition:var(--transition);" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=<?= $day==='Sat'||$day==='Sun'?1:0.65 ?>"></div>
              <span style="font-size:0.72rem;color:var(--teal);"><?= $day ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Bookings Table -->
    <div class="card" id="pending" style="margin-bottom:1.5rem;">
      <div class="card-header"><h3>Pending Booking Requests</h3></div>
      <div class="card-body">
        <?php if(count($pending)===0): ?>
        <div class="empty-state" style="padding:2rem;"><p>No pending bookings at this time.</p></div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Booking ID</th><th>Hiker</th><th>Date</th><th>Hikers</th><th>Details</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($pending as $b): ?>
              <tr>
                <td><strong><?= $b['id'] ?></strong></td>
                <td><?= htmlspecialchars($b['user']) ?></td>
                <td><?= $b['date'] ?></td>
                <td><?= $b['hikers'] ?></td>
                <td style="max-width:200px;font-size:0.85rem;"><?= htmlspecialchars($b['details']) ?></td>
                <td>
                  <div class="table-actions">
                    <button class="btn btn-success btn-sm" onclick="approveBooking('<?= $b['id'] ?>',this)">Approve</button>
                    <button class="btn btn-danger btn-sm" onclick="rejectBooking('<?= $b['id'] ?>',this)">Reject</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Guide Management -->
    <div class="card" id="guides" style="margin-bottom:1.5rem;">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>Assigned Guides</h3>
        <button class="btn btn-primary btn-sm" onclick="showToast('Guide assignment feature coming soon.')">Assign Guide</button>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Guide Name</th><th>Mountains</th><th>Rate</th><th>Max Hikers</th><th>Rating</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach($guides as $g): ?>
              <tr>
                <td><strong><?= htmlspecialchars($g['name']) ?></strong></td>
                <td><?= implode(', ', array_map(fn($id)=>$mountains[array_search($id,array_column($mountains,'id'))]['name']??'—', $g['mountains'])) ?></td>
                <td>&#8369;<?= $g['rate'] ?></td>
                <td><?= $g['maxHikers'] ?></td>
                <td><?= renderStars($g['rating']) ?></td>
                <td><span class="badge badge-moss">Active</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Trail Update -->
    <div class="card" id="trail">
      <div class="card-header"><h3>Post Trail Advisory</h3></div>
      <div class="card-body" style="max-width:600px;">
        <div class="form-group">
          <label class="form-label">Advisory Type</label>
          <select class="form-control">
            <option>Weather Warning</option>
            <option>Trail Closure</option>
            <option>Hazard Alert</option>
            <option>General Update</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea class="form-control" rows="3" placeholder="Describe the current trail conditions or advisory…"></textarea>
        </div>
        <button class="btn btn-primary" onclick="showToast('Advisory posted to all hikers.');return false;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Post Advisory
        </button>
      </div>
    </div>
  </main>
</div>

<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<style>@media(max-width:900px){.mgr-top-grid{grid-template-columns:1fr!important;}}</style>
<script>
function approveBooking(id,btn){
  var row=btn.closest('tr');
  row.querySelector('td:last-child').innerHTML='<span class="badge badge-moss">Approved</span>';
  showToast('Booking '+id+' approved!');
}
function rejectBooking(id,btn){
  var row=btn.closest('tr');
  row.querySelector('td:last-child').innerHTML='<span class="badge badge-red">Rejected</span>';
  showToast('Booking '+id+' rejected.','error');
}
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
