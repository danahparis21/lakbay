<?php
// Process POST before any output
$editing = isset($_POST['save']);

$pageTitle='My Profile — LAKBAY'; $activePage=''; $isLoggedIn=true; $userRole='hiker'; $base='../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
?>
<div class="dashboard-layout">
  <aside class="sidebar" id="dashSidebar">
    <div class="sidebar-user"><div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div><div class="sidebar-username">Juan Dela Cruz</div><div class="sidebar-role">Hiker</div></div>
    <nav class="sidebar-nav"><div class="sidebar-section">
      <a href="dashboard.php"     class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
      <a href="trail-history.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Trail History</a>
      <a href="saved-trails.php"  class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Saved Trails</a>
      <a href="hiker-feed.php"    class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Community Feed</a>
      <a href="messages.php"      class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Messages</a>
      <a href="profile.php"       class="sidebar-link active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>Profile</a>
      <a href="modals/logout.php" class="sidebar-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a>
    </div></nav>
  </aside>

  <main class="dashboard-main">
    <?php if($editing): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Profile updated successfully!
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:1.5rem;align-items:start;" class="profile-grid">
      <!-- Profile Card -->
      <div class="card">
        <div class="profile-cover"></div>
        <div class="card-body" style="padding-top:3.5rem;">
          <div class="profile-avatar-wrap" style="position:relative;margin-top:-4rem;">
            <div class="profile-avatar-img" style="position:static;width:80px;height:80px;border-radius:50%;border:4px solid white;background:var(--sky);display:flex;align-items:center;justify-content:center;margin-bottom:0.75rem;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2" style="width:40px;height:40px;"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </div>
          </div>
          <h3 style="color:var(--navy);margin-bottom:0.15rem;">Juan Dela Cruz</h3>
          <span class="badge badge-teal" style="margin-bottom:1rem;">Hiker</span>
          <p style="font-size:0.88rem;margin-bottom:1rem;">Adventure seeker from Manila. Love exploring Nasugbu's mountains on weekends.</p>
          <hr class="divider">
          <div style="display:flex;justify-content:space-around;text-align:center;padding:0.5rem 0;">
            <div><div style="font-size:1.4rem;font-weight:700;color:var(--navy);font-family:var(--font-display);">5</div><div style="font-size:0.78rem;color:var(--teal);">Hikes</div></div>
            <div><div style="font-size:1.4rem;font-weight:700;color:var(--navy);font-family:var(--font-display);">2</div><div style="font-size:0.78rem;color:var(--teal);">Saved</div></div>
            <div><div style="font-size:1.4rem;font-weight:700;color:var(--navy);font-family:var(--font-display);">3</div><div style="font-size:0.78rem;color:var(--teal);">Posts</div></div>
          </div>
          <hr class="divider">
          <div style="font-size:0.85rem;color:var(--teal);display:flex;flex-direction:column;gap:0.4rem;">
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;display:inline;margin-right:0.3rem;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>juan@email.com</span>
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;display:inline;margin-right:0.3rem;"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>Manila, Philippines</span>
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;display:inline;margin-right:0.3rem;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Member since Jan 2026</span>
          </div>
          <button class="btn btn-outline btn-block" style="margin-top:1.25rem;" onclick="document.getElementById('editSection').scrollIntoView({behavior:'smooth'});">Edit Profile</button>
        </div>
      </div>

      <!-- Edit Form -->
      <div id="editSection">
        <div class="card" style="margin-bottom:1.25rem;">
          <div class="card-header"><h3>Edit Profile</h3></div>
          <div class="card-body">
            <form method="POST">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="profile-form-grid">
                <div class="form-group">
                  <label class="form-label">First Name</label>
                  <input class="form-control" type="text" name="fname" value="Juan">
                </div>
                <div class="form-group">
                  <label class="form-label">Last Name</label>
                  <input class="form-control" type="text" name="lname" value="Dela Cruz">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-control" type="email" name="email" value="juan@email.com">
              </div>
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input class="form-control" type="tel" name="phone" value="09171234567">
              </div>
              <div class="form-group">
                <label class="form-label">Location</label>
                <input class="form-control" type="text" name="location" value="Manila, Philippines">
              </div>
              <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea class="form-control" name="bio" rows="3">Adventure seeker from Manila. Love exploring Nasugbu's mountains on weekends.</textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Experience Level</label>
                <select class="form-control" name="experience">
                  <option>Beginner</option>
                  <option selected>Intermediate</option>
                  <option>Advanced</option>
                </select>
              </div>
              <button type="submit" name="save" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Save Changes
              </button>
            </form>
          </div>
        </div>

        <!-- Change Password -->
        <div class="card">
          <div class="card-header"><h3>Change Password</h3></div>
          <div class="card-body">
            <form onsubmit="showToast('Password updated!');return false;" style="max-width:400px;">
              <div class="form-group">
                <label class="form-label">Current Password</label>
                <input class="form-control" type="password" placeholder="Enter current password">
              </div>
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input class="form-control" type="password" placeholder="Enter new password">
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input class="form-control" type="password" placeholder="Repeat new password">
              </div>
              <button type="submit" class="btn btn-outline">Update Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
<style>@media(max-width:900px){.profile-grid{grid-template-columns:1fr!important;}.profile-form-grid{grid-template-columns:1fr!important;}}</style>
<script>document.getElementById('sidebarToggle').addEventListener('click',function(){ document.getElementById('dashSidebar').classList.toggle('open'); });</script>
<?php include_once $base.'includes/footer.php'; ?>
