<?php
$pageTitle  = 'LAKBAY — Discover Hiking Trails in Nasugbu';
$activePage = 'home';
$isLoggedIn = false;
$base       = '';          // root level: no prefix needed
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';
?>

<!-- ================= HERO ================= -->
<section class="hero" aria-label="Hero">
  <div class="hero-bg" id="heroBg"></div>
  <div class="hero-overlay"></div>
  <div class="hero-content fade-in">
    <div class="hero-badge">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>
      Nasugbu, Batangas
    </div>
    <h1 class="hero-title">Discover <span>Hiking Trails</span><br>in Nasugbu</h1>
    <p class="hero-subtitle">Explore Mt. Batulao, Mt. Apayang, Mt. Lantik, and Mt. Talamitam with guided trails, real-time updates, and a community of fellow adventurers.</p>
    <div class="hero-actions">
      <a href="pages/explore/index.php" class="btn btn-white btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/></svg>
        Explore Mountains
      </a>
      <a href="pages/explore/index.php?quiz=1" class="btn btn-outline-white btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Find My Trail
      </a>
    </div>
  </div>
  <div class="hero-scroll" aria-hidden="true">
    <div class="hero-scroll-inner"><div class="hero-scroll-dot"></div></div>
  </div>
</section>

<!-- ================= STATS ================= -->
<div class="stats-bar">
  <div class="stats-grid container">
    <div class="stat-item"><div class="stat-number">4</div><div class="stat-label">Mountains</div></div>
    <div class="stat-item"><div class="stat-number">50+</div><div class="stat-label">Local Guides</div></div>
    <div class="stat-item"><div class="stat-number">1,000+</div><div class="stat-label">Happy Hikers</div></div>
    <div class="stat-item"><div class="stat-number">4.8</div><div class="stat-label">Average Rating</div></div>
  </div>
</div>

<!-- ================= FEATURES ================= -->
<section class="section">
  <div class="container">
    <div class="section-heading">
      <h2>Everything You Need for Your Hike</h2>
      <p>LAKBAY provides comprehensive tools and information for a safe and enjoyable hiking experience.</p>
    </div>
    <div class="grid-3">
      <?php
      $features = [
        ['<path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/>','Trail Discovery','Explore detailed information about four stunning mountains in Nasugbu, Batangas.'],
        ['<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>','Personalized Quiz','Take our trail-matching quiz to find the perfect mountain for your experience level.'],
        ['<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>','Verified Guides','Connect with experienced local guides for a safe and authentic hiking experience.'],
        ['<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>','Real-time Updates','Get live weather advisories and trail condition updates before your hike.'],
        ['<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>','Interactive Maps','Access detailed trail maps with route information and key landmarks.'],
        ['<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>','Community Feed','Share experiences and connect with fellow hiking enthusiasts in Nasugbu.'],
      ];
      foreach($features as [$ic,$ti,$de]): ?>
      <div class="card feature-card card--flat">
        <div class="card-body">
          <div class="feature-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ic ?></svg></div>
          <h3><?= $ti ?></h3>
          <p><?= $de ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= MOUNTAINS PREVIEW ================= -->
<section class="section section--alt">
  <div class="container">
    <div class="section-heading">
      <h2>Our Mountains</h2>
      <p>Four distinct trails for every level of adventurer in the heart of Nasugbu.</p>
    </div>
    <div class="grid-4">
      <?php foreach($mountains as $m): ?>
      <a href="pages/explore/mountain.php?id=<?= $m['id'] ?>" class="card mountain-card" aria-label="<?= htmlspecialchars($m['name']) ?>">
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
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><?= $m['elevation'] ?></span>
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= $m['duration'] ?></span>
          </div>
          <?= renderStars($m['rating']) ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:2.5rem;">
      <a href="pages/explore/index.php" class="btn btn-primary btn-lg">View All Mountains</a>
    </div>
  </div>
</section>

<!-- ================= ADVISORIES ================= -->
<section class="section">
  <div class="container">
    <div class="section-heading">
      <h2>Advisories &amp; Protocols</h2>
      <p>Important guidelines for responsible and safe hiking in Nasugbu.</p>
    </div>
    <div class="grid-3">
      <?php
      $advisories = [
        ['<path d="M2 22 16 8"/><path d="M3.47 12.53 5 11l1.53 1.53a3.5 3.5 0 0 0 4.94 0L13 11l1.5 1.5a3.5 3.5 0 0 0 4.95 0L21 11"/>','Environmental Protection',['Practice "Leave No Trace" — pack out all trash','Stay on designated trails to prevent erosion','Respect wildlife and natural habitats','Use eco-friendly products only']],
        ['<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>','Safety Protocols',['Register at barangay hall before hiking','Hire local guides for first-time visits','Bring sufficient water and sun protection','Check weather conditions before departure']],
        ['<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>','Trail Regulations',['Follow posted signs and trail markers','No littering — violators will be fined','Respect private properties along the trail','Follow group size limitations per trail']],
      ];
      foreach($advisories as [$ic,$ti,$items]): ?>
      <div class="card advisory-card card--flat">
        <div class="card-body">
          <div class="advisory-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ic ?></svg></div>
          <h3><?= $ti ?></h3>
          <ul class="advisory-list"><?php foreach($items as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= WHY LAKBAY ================= -->
<section class="section section--alt">
  <div class="container">
    <div class="section-heading">
      <h2>Why Use LAKBAY?</h2>
      <p>Your trusted companion for exploring Nasugbu's mountains responsibly.</p>
    </div>
    <div class="grid-2">
      <?php
      $why=[
        ['var(--teal)','<path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/>','Comprehensive Trail Information','Detailed guides including difficulty levels, duration, fees, and environmental protocols for all four mountains.'],
        ['var(--moss)','<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>','Safety First Approach','Real-time weather updates, hazard warnings, and a verified guide system to keep you protected on the trail.'],
        ['var(--sage)','<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>','Easy Booking System','Book guides, manage your hiking schedule, and receive confirmations — all in one seamless platform.'],
        ['#3a7ca5','<path d="M2 22 16 8"/><circle cx="12" cy="20" r="2"/>','Environmental Stewardship','Promoting responsible hiking with Leave No Trace principles to preserve Nasugbu\'s beauty for future generations.'],
      ];
      foreach($why as [$col,$ic,$ti,$de]): ?>
      <div class="why-card" style="background:<?= $col ?>;">
        <div style="display:flex;gap:1rem;align-items:flex-start;">
          <div class="why-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ic ?></svg></div>
          <div><h3><?= $ti ?></h3><p><?= $de ?></p></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= CTA ================= -->
<div class="cta-section">
  <div class="container">
    <h2>Ready to Start Your Adventure?</h2>
    <p>Join hundreds of hikers who trust LAKBAY for their trail discoveries in Nasugbu.</p>
    <div class="cta-actions">
      <a href="pages/modals/signup.php" class="btn btn-white btn-lg">Create Free Account</a>
      <a href="pages/explore/index.php" class="btn btn-outline-white btn-lg">Browse Trails</a>
    </div>
  </div>
</div>

<script>window.addEventListener('load',function(){ var b=document.getElementById('heroBg'); if(b) b.classList.add('loaded'); });</script>

<?php include_once $base.'includes/footer.php'; ?>
