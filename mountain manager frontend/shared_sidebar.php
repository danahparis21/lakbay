<!-- shared_sidebar.php — include in every manager page -->
<!-- Usage: set $activePage before including: e.g. $activePage = 'payments'; -->
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
      <div class="mountain-badge-name" id="sideMtnName">Loading…</div>
      <div class="mountain-badge-role" id="sideMtnSub">Your Mountain</div>
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

    <a href="#" class="nav-item logout" onclick="confirmLogout()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      <span class="nav-text">Log out</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-footer-avatar" id="sfAvatar">JR</div>
    <div class="sidebar-footer-text">
      <div class="sidebar-footer-name" id="sfName">John Rivera</div>
      <div class="sidebar-footer-role">Mountain Manager</div>
    </div>
  </div>
</aside>

<!-- Logout confirm modal -->
<div class="modal-bg" id="logoutModal">
  <div class="modal anim-scale-in" style="max-width:380px;">
    <div class="modal-hdr" style="border-bottom:none;padding-bottom:8px;">
      <div class="modal-title">Log out?</div>
      <button class="modal-close" onclick="document.getElementById('logoutModal').classList.remove('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" style="padding-top:4px;">
      <p style="font-size:14px;color:var(--ink3);line-height:1.6;">You will be signed out of the LAKBAY Manager Portal. Any unsaved changes will be lost.</p>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button class="btn btn-ghost btn-full" onclick="document.getElementById('logoutModal').classList.remove('open')">Cancel</button>
        <button class="btn btn-danger btn-full" onclick="doLogout()" style="background:var(--red);color:white;border:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Log out
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function confirmLogout() { document.getElementById('logoutModal').classList.add('open'); }
function doLogout() {
  document.body.style.opacity='0';
  document.body.style.transition='opacity .4s';
  setTimeout(()=>{ window.location.href='dashboard.php'; },400);
}
function initSidebar() {
  const myMtns = getMyMountains();
  document.getElementById('sideMtnName').textContent = myMtns.map(m=>m.name).join(' & ');
  document.getElementById('sideMtnSub').textContent = `Managing ${myMtns.length} mountain${myMtns.length>1?'s':''}`;
  document.getElementById('sfAvatar').textContent = MANAGER.initials;
  document.getElementById('sfName').textContent = MANAGER.name;
}
</script>
