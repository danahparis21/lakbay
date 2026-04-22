<?php
$pageTitle  = 'Tourism Admin Dashboard — LAKBAY';
$activePage = '';
$isLoggedIn = true;
$userRole   = 'tourism';
$base       = '../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
$adminName = 'Admin Reyes';
$totalBookings = count($bookings);
$pendingCount  = count(array_filter($bookings,fn($b)=>$b['status']==='pending'));
$completedCount= count(array_filter($bookings,fn($b)=>$b['status']==='completed'));
?>

<div class="dashboard-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div class="sidebar-username"><?= htmlspecialchars($adminName) ?></div>
      <div class="sidebar-role">Tourism Admin</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <div class="sidebar-section-title">Administration</div>
        <a href="dashboard-admin.php" class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Overview</a>
        <a href="#users"              class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>User Management</a>
        <a href="#mountains"          class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>Mountain Oversight</a>
        <a href="#bookings"           class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>All Bookings</a>
        <a href="#reports"            class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>Reports</a>
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
    <div class="dashboard-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1>Tourism Admin Dashboard</h1>
        <p>Nasugbu Hiking Trail Management System — <?= date('F Y') ?></p>
      </div>
      <button class="btn btn-primary" onclick="showToast('Report downloaded!');">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download Report
      </button>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card stat-teal">
        <div class="stat-card-label">Total Hikers (Registered)</div>
        <div class="stat-card-value">1,245</div>
        <div class="stat-card-sub">+12% this month</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
      </div>
      <div class="stat-card stat-moss">
        <div class="stat-card-label">Active Guides</div>
        <div class="stat-card-value"><?= count($guides) ?></div>
        <div class="stat-card-sub">Verified &amp; active</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      </div>
      <div class="stat-card stat-sage">
        <div class="stat-card-label">Total Bookings</div>
        <div class="stat-card-value"><?= $totalBookings ?></div>
        <div class="stat-card-sub"><?= $pendingCount ?> pending review</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-card-label">Revenue (Est.)</div>
        <div class="stat-card-value">&#8369;45K</div>
        <div class="stat-card-sub">This month</div>
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
      </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" class="admin-charts-grid">
      <!-- Booking Trends -->
      <div class="card">
        <div class="card-header"><h3>Booking Trends (6 Months)</h3></div>
        <div class="card-body">
          <div style="display:flex;align-items:flex-end;gap:0.6rem;height:160px;padding-top:1rem;">
            <?php
            $months=['Jan'=>45,'Feb'=>52,'Mar'=>48,'Apr'=>65,'May'=>78,'Jun'=>61];
            $mx=max($months);
            foreach($months as $mo=>$v):
              $h=round(($v/$mx)*130);
            ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;flex:1;">
              <span style="font-size:0.68rem;color:var(--teal);font-weight:600;"><?= $v ?></span>
              <div style="width:100%;height:<?= $h ?>px;background:var(--teal);border-radius:4px 4px 0 0;opacity:0.8;"></div>
              <span style="font-size:0.68rem;color:var(--teal);"><?= $mo ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Mountain Distribution -->
      <div class="card">
        <div class="card-header"><h3>Hiker Distribution by Mountain</h3></div>
        <div class="card-body">
          <?php
          $dist=[['Mt. Batulao',120,'var(--teal)'],['Mt. Talamitam',95,'var(--moss)'],['Mt. Apayang',85,'var(--sage)'],['Mt. Lantik',45,'var(--blue)']];
          $dtotal = array_sum(array_column($dist,1));
          foreach($dist as [$dn,$dv,$dc]): $pct=round($dv/$dtotal*100); ?>
          <div style="margin-bottom:0.75rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.25rem;">
              <span style="color:var(--navy);font-weight:500;"><?= $dn ?></span>
              <span style="color:var(--teal);"><?= $dv ?> hikers (<?= $pct ?>%)</span>
            </div>
            <div style="height:8px;background:var(--sky);border-radius:999px;overflow:hidden;">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $dc ?>;border-radius:999px;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- User Management -->
    <div class="card" id="users" style="margin-bottom:1.5rem;">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>User Management</h3>
        <div style="display:flex;gap:0.5rem;">
          <div class="input-icon-wrap"><svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input class="form-control form-control-sm" type="text" placeholder="Search users…" style="padding-left:2.5rem;font-size:0.85rem;padding-top:0.45rem;padding-bottom:0.45rem;width:200px;"></div>
          <button class="btn btn-primary btn-sm" onclick="showToast('Add user feature coming soon.');">Add User</button>
        </div>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php
              $sampleUsers=[
                ['Juan Dela Cruz','Hiker','juan@email.com','Active'],
                ['Ana Reyes','Hiker','ana@email.com','Active'],
                ['Mang Jose Dela Cruz','Guide','jose@email.com','Active'],
                ['Kuya Bong Reyes','Guide','bong@email.com','Active'],
                ['Maria Santos','Manager','maria@email.com','Active'],
                ['Carlos Tan','Hiker','carlos@email.com','Inactive'],
              ];
              foreach($sampleUsers as [$uname,$urole,$uemail,$ustatus]):
                $roleBadge=['Hiker'=>'badge-blue','Guide'=>'badge-moss','Manager'=>'badge-teal','Tourism'=>'badge-yellow'][$urole]??'badge-sky';
              ?>
              <tr>
                <td><strong><?= $uname ?></strong></td>
                <td><span class="badge <?= $roleBadge ?>"><?= $urole ?></span></td>
                <td style="font-size:0.88rem;color:var(--teal);"><?= $uemail ?></td>
                <td><span class="badge <?= $ustatus==='Active'?'badge-moss':'badge-sky' ?>"><?= $ustatus ?></span></td>
                <td>
                  <div class="table-actions">
                    <button class="btn btn-outline btn-sm" onclick="showToast('Editing <?= $uname ?>...');">Edit</button>
                    <button class="btn btn-danger btn-sm" onclick="showToast('User suspended.','error');">Suspend</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Mountain Oversight -->
    <div class="card" id="mountains" style="margin-bottom:1.5rem;">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3>Mountain Oversight</h3>
        <button class="btn btn-primary btn-sm" onclick="showToast('Mountain settings panel coming soon.');">Manage Mountains</button>
      </div>
      <div class="card-body">
        <div class="grid-4" style="gap:1rem;">
          <?php foreach($mountains as $m): ?>
          <div class="card card--flat" style="border:1.5px solid var(--sky);">
            <img src="<?= $m['image'] ?>" alt="" style="width:100%;height:90px;object-fit:cover;">
            <div class="card-body" style="padding:0.85rem;">
              <div style="font-weight:700;font-size:0.92rem;color:var(--navy);margin-bottom:0.25rem;"><?= htmlspecialchars($m['name']) ?></div>
              <?= getDifficultyBadge($m['difficulty']) ?>
              <div style="font-size:0.8rem;color:var(--teal);margin-top:0.4rem;"><?= getCrowdBadge($m['crowdLevel']) ?></div>
              <div style="display:flex;gap:0.4rem;margin-top:0.6rem;">
                <button class="btn btn-outline btn-sm" style="flex:1;font-size:0.78rem;" onclick="showToast('Editing <?= htmlspecialchars($m['name']) ?>...');">Edit</button>
                <button class="btn btn-primary btn-sm" style="flex:1;font-size:0.78rem;" onclick="showToast('Advisory posted.');">Alert</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- All Bookings -->
    <div class="card" id="bookings" style="margin-bottom:1.5rem;">
      <div class="card-header"><h3>All Booking Records</h3></div>
      <div class="card-body">
        <div class="tabs" style="margin-bottom:1rem;">
          <button class="tab-btn active" onclick="switchTab2('all',this)">All</button>
          <button class="tab-btn" onclick="switchTab2('pending',this)">Pending</button>
          <button class="tab-btn" onclick="switchTab2('approved',this)">Approved</button>
          <button class="tab-btn" onclick="switchTab2('completed',this)">Completed</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Hiker</th><th>Mountain</th><th>Date</th><th>Hikers</th><th>Guide</th><th>Status</th></tr></thead>
            <tbody id="allBookingBody">
              <?php foreach($bookings as $b):
                $mt=null; foreach($mountains as $m){ if($m['id']===$b['mountainId']){ $mt=$m; break; } }
                $gd=null; foreach($guides as $g){ if($g['id']===$b['guideId']){ $gd=$g; break; } }
                $sb=['pending'=>'badge-yellow','approved'=>'badge-moss','rejected'=>'badge-red','completed'=>'badge-teal'][$b['status']]??'badge-sky';
              ?>
              <tr data-status="<?= $b['status'] ?>">
                <td><strong><?= $b['id'] ?></strong></td>
                <td><?= htmlspecialchars($b['user']) ?></td>
                <td><?= htmlspecialchars($mt['name']??'—') ?></td>
                <td><?= $b['date'] ?></td>
                <td><?= $b['hikers'] ?></td>
                <td style="font-size:0.85rem;"><?= htmlspecialchars($gd['name']??'—') ?></td>
                <td><span class="badge <?= $sb ?>"><?= ucfirst($b['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Reports -->
    <div class="card" id="reports">
      <div class="card-header"><h3>Generate Reports</h3></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;" class="report-grid">
          <?php
          $reports=[
            ['Monthly Hiker Summary','Total hiker counts per mountain per month.','badge-teal'],
            ['Guide Performance','Ratings, hike counts, and feedback for each guide.','badge-moss'],
            ['Revenue Report','Trail fee and guide fee collection breakdown.','badge-sage'],
            ['Environmental Compliance','Leave No Trace adherence and incident reports.','badge-blue'],
            ['Trail Condition Log','Hazard reports and condition updates by managers.','badge-yellow'],
            ['Booking Analytics','Conversion rates, cancellations, and trends.','badge-teal'],
          ];
          foreach($reports as [$rt,$rd,$rb]): ?>
          <div class="card card--flat" style="border:1.5px solid var(--sky);">
            <div class="card-body">
              <span class="badge <?= $rb ?>" style="margin-bottom:0.75rem;"><?= $rb === 'badge-yellow'?'Advisory':'Data' ?></span>
              <h4 style="font-size:0.95rem;margin-bottom:0.3rem;"><?= $rt ?></h4>
              <p style="font-size:0.83rem;margin-bottom:1rem;"><?= $rd ?></p>
              <button class="btn btn-outline btn-sm btn-block" onclick="showToast('Downloading <?= $rt ?>...');">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<style>@media(max-width:900px){.admin-charts-grid{grid-template-columns:1fr!important;}.report-grid{grid-template-columns:1fr 1fr!important;}}</style>
<script>
function switchTab2(status,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#allBookingBody tr').forEach(r=>{
    r.style.display=(status==='all'||r.dataset.status===status)?'':'none';
  });
}
document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });
</script>
<?php include_once $base.'includes/footer.php'; ?>
