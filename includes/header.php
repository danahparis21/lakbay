<?php
$pageTitle  = $pageTitle  ?? 'LAKBAY — Nasugbu Trail System';
$activePage = $activePage ?? 'home';
$isLoggedIn = $isLoggedIn ?? false;
$userRole   = $userRole   ?? 'hiker';

// $base MUST be set by the calling page before including this file.
// e.g. index.php sets $base = '';
//      pages/dashboard.php sets $base = '../';
//      pages/explore/index.php sets $base = '../../';
// This ensures assets load correctly regardless of server setup.
if (!isset($base)) $base = '';
$GLOBALS['base'] = $base;

$dashLinks = [
  'hiker'   => $base.'pages/dashboard.php',
  'guide'   => $base.'pages/dashboard-guide.php',
  'manager' => $base.'pages/dashboard-manager.php',
  'tourism' => $base.'pages/dashboard-admin.php',
];
$dashHref = $dashLinks[$userRole] ?? ($base.'pages/dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="Explore hiking trails in Nasugbu, Batangas with LAKBAY.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>assets/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
</head>
<body>

<nav class="navbar" role="navigation" aria-label="Main navigation">
  <div class="navbar-inner">
    <a href="<?= $base ?>index.php" class="navbar-brand" aria-label="LAKBAY Home">
      <div class="navbar-logo" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/><circle cx="19" cy="5" r="2"/></svg>
      </div>
      <span class="navbar-brand-name">LAKBAY</span>
    </a>

    <div class="navbar-links">
      <a href="<?= $base ?>index.php"                      class="<?= $activePage==='home'    ?'active':'' ?>">Home</a>
      <a href="<?= $base ?>pages/explore/index.php"        class="<?= $activePage==='explore' ?'active':'' ?>">Explore</a>
      <a href="<?= $base ?>pages/explore/index.php?quiz=1" class="<?= $activePage==='trail'   ?'active':'' ?>">Find My Trail</a>
      <a href="<?= $base ?>pages/landing/guidelines.php"   class="<?= $activePage==='guide'   ?'active':'' ?>">Guidelines</a>
    </div>

    <div class="navbar-actions">
      <?php if($isLoggedIn): ?>
        <a href="<?= $dashHref ?>" class="navbar-avatar" title="Dashboard" aria-label="My Dashboard">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        </a>
        <a href="<?= $base ?>pages/modals/logout.php" class="btn btn-primary btn-sm">Log Out</a>
      <?php else: ?>
        <a href="<?= $base ?>pages/modals/login.php" class="navbar-avatar" title="Login" aria-label="Login">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        </a>
        <a href="<?= $base ?>pages/modals/login.php" class="btn btn-primary btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Login
        </a>
      <?php endif; ?>
    </div>

    <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>
</nav>

<div class="mobile-nav" id="mobileNav" role="dialog" aria-modal="true" aria-label="Mobile navigation">
  <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
  <div class="mobile-nav-drawer">
    <button class="mobile-nav-close" id="mobileNavClose" aria-label="Close menu">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <nav class="mobile-nav-links">
      <a href="<?= $base ?>index.php"                      class="<?= $activePage==='home'    ?'active':'' ?>">Home</a>
      <a href="<?= $base ?>pages/explore/index.php"        class="<?= $activePage==='explore' ?'active':'' ?>">Explore Mountains</a>
      <a href="<?= $base ?>pages/explore/index.php?quiz=1" class="<?= $activePage==='trail'   ?'active':'' ?>">Find My Trail</a>
      <a href="<?= $base ?>pages/landing/guidelines.php"   class="<?= $activePage==='guide'   ?'active':'' ?>">Guidelines</a>
    </nav>
    <hr class="divider">
    <?php if($isLoggedIn): ?>
      <a href="<?= $dashHref ?>" class="btn btn-outline btn-block" style="margin-bottom:0.5rem;">My Dashboard</a>
      <a href="<?= $base ?>pages/modals/logout.php" class="btn btn-primary btn-block">Log Out</a>
    <?php else: ?>
      <a href="<?= $base ?>pages/modals/login.php"  class="btn btn-primary btn-block" style="margin-bottom:0.5rem;">Login</a>
      <a href="<?= $base ?>pages/modals/signup.php" class="btn btn-outline btn-block">Create Account</a>
    <?php endif; ?>
  </div>
</div>

<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script>
(function(){
  var btn=document.getElementById('hamburgerBtn'),nav=document.getElementById('mobileNav'),cls=document.getElementById('mobileNavClose'),ovl=document.getElementById('mobileNavOverlay');
  function open(){ nav.classList.add('open'); btn.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
  function shut(){ nav.classList.remove('open'); btn.setAttribute('aria-expanded','false'); document.body.style.overflow=''; }
  if(btn) btn.addEventListener('click',open);
  if(cls) cls.addEventListener('click',shut);
  if(ovl) ovl.addEventListener('click',shut);
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') shut(); });
})();
function showToast(msg,type){
  type=type||'success';
  var icons={success:'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px;flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>',error:'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'};
  var t=document.createElement('div');
  t.className='toast toast-'+type;
  t.innerHTML=(icons[type]||'')+'<span>'+msg+'</span>';
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(function(){ t.style.cssText+='opacity:0;transition:opacity 0.3s;'; setTimeout(function(){ t.remove(); },300); },3500);
}
</script>
