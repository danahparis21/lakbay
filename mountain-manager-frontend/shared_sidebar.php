<?php
// shared_sidebar.php — include in every manager page
// Usage: set $activePage before including: e.g. $activePage = 'payments';

// Make sure we have mountain data (in case parent didn't define it)
if (!isset($assigned_mountains) && isset($manager_id)) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM mountains m
        INNER JOIN manager_mountains mm ON m.id = mm.mountain_id
        WHERE mm.manager_id = ?
        ORDER BY m.name
    ");
    $stmt->execute([$manager_id]);
    $assigned_mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$mtn_names = array_column($assigned_mountains ?? [], 'name');
$mtn_display = !empty($mtn_names) ? implode(' & ', $mtn_names) : 'No Mountain';
$mtn_count = count($assigned_mountains ?? []);
?>

<aside class="sidebar" id="sidebar">
  <a href="dashboard.php" class="sidebar-brand">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none">
        <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity=".9"/>
        <path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity=".35"/>
      </svg>
    </div>
    <div class="sidebar-brand-text">
      <div class="sidebar-app-name">LAKBAY</div>
      <div class="sidebar-app-sub">Manager Portal</div>
    </div>
  </a>

  <div class="mountain-badge" id="mtnBadgeSidebar">
    <div class="mountain-badge-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M8 3l4 8 5-5 5 15H2L8 3z"/>
      </svg>
    </div>
    <div class="mountain-badge-text">
      <div class="mountain-badge-name" id="sideMtnName"><?= htmlspecialchars($mtn_display) ?></div>
      <div class="mountain-badge-role" id="sideMtnSub">Managing <?= $mtn_count ?> mountain<?= $mtn_count !== 1 ? 's' : '' ?></div>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item <?= ($activePage??'')==='dashboard' ? 'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/>
        <rect x="14" y="14" width="7" height="7" rx="1"/>
      </svg>
      <span class="nav-text">Dashboard</span>
    </a>
    <a href="bookings_manager.php" class="nav-item <?= ($activePage??'')==='bookings' ? 'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      <span class="nav-text">Bookings</span>
    </a>
    <a href="payments.php" class="nav-item <?= ($activePage??'')==='payments' ? 'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
      <span class="nav-text">Payments</span>
    </a>

    <div class="nav-divider"></div>
    <div class="nav-label">Reports</div>

    <a href="analytics.php" class="nav-item <?= ($activePage??'')==='analytics' ? 'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <line x1="18" y1="20" x2="18" y2="10"/>
        <line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6" y1="20" x2="6" y2="14"/>
      </svg>
      <span class="nav-text">Analytics</span>
    </a>
    <a href="advisories.php" class="nav-item <?= ($activePage??'')==='advisories' ? 'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <span class="nav-text">Advisories</span>
    </a>

    <div class="nav-divider"></div>

    <a href="#" class="nav-item logout" onclick="event.preventDefault(); confirmLogout()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      <span class="nav-text">Log out</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-footer-avatar" id="sfAvatar"><?= $manager_initials ?? 'JR' ?></div>
    <div class="sidebar-footer-text">
      <div class="sidebar-footer-name" id="sfName"><?= htmlspecialchars($manager_name ?? 'Mountain Manager') ?></div>
      <div class="sidebar-footer-role">Mountain Manager</div>
    </div>
  </div>
</aside>

<!-- Logout confirm modal -->
<div id="logoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center;">
  <div style="background: white; border-radius: 24px; max-width: 400px; width: 90%; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
    <div style="padding: 20px 24px 0; display: flex; justify-content: space-between; align-items: center;">
      <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #100600;">Log out?</h3>
      <button onclick="closeLogoutModal()" style="width: 32px; height: 32px; border-radius: 50%; background: #f4f1ec; border: none; cursor: pointer;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div style="padding: 8px 24px 24px;">
      <p style="font-size: 14px; color: #7a6a5a; line-height: 1.6;">You will be signed out of the LAKBAY Manager Portal. Any unsaved changes will be lost.</p>
      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button onclick="closeLogoutModal()" style="flex: 1; padding: 10px; border-radius: 10px; font-weight: 600; border: 1.5px solid rgba(16,6,0,0.15); background: transparent; cursor: pointer;">Cancel</button>
        <button onclick="doLogout()" style="flex: 1; padding: 10px; border-radius: 10px; font-weight: 600; border: none; background: #c62828; color: white; cursor: pointer;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14" style="display: inline; margin-right: 6px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Log out
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function confirmLogout() {
  document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
  document.getElementById('logoutModal').style.display = 'none';
}

function doLogout() {
  window.location.href = '../login-and-signup/login.php';
}

// Unified click listener for sidebar toggle and outside clicks
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  const mainArea = document.getElementById('mainArea');
  const isToggleBtn = e.target.closest('.sidebar-toggle');
  
  if (isToggleBtn) {
    // Handle toggle button click
    if (sidebar) {
      if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
      } else {
        sidebar.classList.toggle('collapsed');
        if (mainArea) mainArea.classList.toggle('expanded');
      }
    }
  } else if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('mobile-open')) {
    // Handle click outside open sidebar on mobile
    if (!sidebar.contains(e.target)) {
      sidebar.classList.remove('mobile-open');
    }
  }
  
  // Logout modal click outside
  const logoutModal = document.getElementById('logoutModal');
  if (e.target === logoutModal) {
    closeLogoutModal();
  }
});

// Close sidebar when clicking on a nav link on mobile
document.querySelectorAll('.nav-item').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) {
      document.getElementById('sidebar')?.classList.remove('mobile-open');
    }
  });
});
</script>
