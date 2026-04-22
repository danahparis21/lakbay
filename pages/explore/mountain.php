<?php
$base = '../../';
include_once $base.'includes/data.php';
$id = (int)($_GET['id'] ?? 1);
$mountain = null;
foreach($mountains as $m){ if($m['id']===$id){ $mountain=$m; break; } }
if(!$mountain){ header('Location: index.php'); exit; }
$pageTitle  = htmlspecialchars($mountain['name']).' — LAKBAY';
$activePage = 'explore';
$isLoggedIn = false;
include_once $base.'includes/header.php';
?>
<div class="page-wrap">
  <div class="mountain-hero">
    <img src="<?= $mountain['image'] ?>" alt="<?= htmlspecialchars($mountain['name']) ?>">
    <div class="mountain-hero-overlay"></div>
    <div class="mountain-hero-content">
      <div class="mountain-hero-inner">
        <a href="index.php" class="btn btn-outline-white btn-sm" style="margin-bottom:1rem;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Back to Explore
        </a>
        <h1><?= htmlspecialchars($mountain['name']) ?></h1>
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:0.5rem;">
          <?= getDifficultyBadge($mountain['difficulty']) ?>
          <?= getCrowdBadge($mountain['crowdLevel']) ?>
          <?= renderStars($mountain['rating']) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="container" style="padding-top:2.5rem;padding-bottom:3rem;">
    <div style="display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start;" class="detail-grid">

      <!-- LEFT -->
      <div>
        <!-- Quick Stats -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;" class="stats-mini-grid">
          <?php
          $qs=[
            ['<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>','Elevation',$mountain['elevation']],
            ['<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>','Duration',$mountain['duration']],
            ['<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>','Trail Fee','₱'.$mountain['fee']],
            ['<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>','Jump-off',explode(',',$mountain['jumpOff'])[0]],
          ];
          foreach($qs as [$ic,$lb,$vl]): ?>
          <div class="card card--flat">
            <div class="card-body" style="text-align:center;padding:1.1rem 0.75rem;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2" style="width:22px;height:22px;margin:0 auto 0.4rem;"><?= $ic ?></svg>
              <div style="font-weight:700;font-size:1rem;color:var(--navy);"><?= htmlspecialchars($vl) ?></div>
              <div style="font-size:0.75rem;color:var(--teal);margin-top:0.1rem;"><?= $lb ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Tabs -->
        <div class="tabs" role="tablist">
          <button class="tab-btn active" onclick="switchTab('overview',this)" role="tab">Overview</button>
          <button class="tab-btn" onclick="switchTab('rules',this)" role="tab">Rules &amp; Protocols</button>
          <button class="tab-btn" onclick="switchTab('hazards',this)" role="tab">Hazards</button>
          <button class="tab-btn" onclick="switchTab('reviews',this)" role="tab">Reviews (<?= count($mountain['reviews']) ?>)</button>
        </div>

        <div class="tab-panel active" id="tab-overview">
          <div class="card card--flat" style="margin-bottom:1.25rem;"><div class="card-body">
            <h4 style="margin-bottom:0.75rem;">About <?= htmlspecialchars($mountain['name']) ?></h4>
            <p><?= htmlspecialchars($mountain['description']) ?></p>
          </div></div>
          <div class="card card--flat" style="margin-bottom:1.25rem;"><div class="card-body">
            <h4 style="margin-bottom:0.75rem;">Environmental Reminders</h4>
            <ul class="advisory-list"><?php foreach($mountain['envReminders'] as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?></ul>
          </div></div>
          <div class="card card--flat"><div class="card-body">
            <h4 style="margin-bottom:0.5rem;">Peak Times</h4>
            <p><?= htmlspecialchars($mountain['peakTimes']) ?></p>
          </div></div>
        </div>

        <div class="tab-panel" id="tab-rules">
          <div class="card card--flat"><div class="card-body">
            <h4 style="margin-bottom:0.75rem;">Trail Rules &amp; Regulations</h4>
            <ul class="advisory-list"><?php foreach($mountain['rules'] as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?></ul>
          </div></div>
        </div>

        <div class="tab-panel" id="tab-hazards">
          <div class="card card--flat"><div class="card-body">
            <h4 style="margin-bottom:0.75rem;">Known Hazards</h4>
            <?php foreach($mountain['hazards'] as $h): ?>
            <div class="alert alert-warning" style="margin-bottom:0.65rem;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <?= htmlspecialchars($h) ?>
            </div>
            <?php endforeach; ?>
          </div></div>
        </div>

        <div class="tab-panel" id="tab-reviews">
          <?php foreach($mountain['reviews'] as $rev): ?>
          <div class="card card--flat" style="margin-bottom:1rem;"><div class="card-body">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
              <strong style="color:var(--navy);"><?= htmlspecialchars($rev['name']) ?></strong>
              <span style="font-size:0.8rem;color:var(--teal);"><?= $rev['date'] ?></span>
            </div>
            <?= renderStars($rev['rating']) ?>
            <p style="margin-top:0.5rem;font-size:0.92rem;"><?= htmlspecialchars($rev['comment']) ?></p>
          </div></div>
          <?php endforeach; ?>
          <div class="card card--flat"><div class="card-body">
            <h4 style="margin-bottom:0.75rem;">Write a Review</h4>
            <p style="font-size:0.88rem;color:var(--teal);"><a href="../modals/login.php" style="color:var(--teal);text-decoration:underline;">Login</a> to leave a review.</p>
          </div></div>
        </div>
      </div>

      <!-- RIGHT SIDEBAR -->
      <div style="display:flex;flex-direction:column;gap:1.25rem;position:sticky;top:80px;">
        <div class="weather-widget">
          <h4>Weather Advisory</h4>
          <div class="weather-temp">26°C</div>
          <div class="weather-desc" style="margin-top:0.5rem;font-size:0.88rem;"><?= htmlspecialchars($mountain['weatherAdvisory']) ?></div>
          <div class="weather-meta"><span>UV: High</span><span>Wind: Moderate</span></div>
        </div>

        <div class="card">
          <div class="card-header"><h4>Available Guides</h4></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:1rem;">
            <?php
            $mGuides = array_filter($guides, fn($g)=>in_array($id,$g['mountains']));
            foreach(array_slice($mGuides,0,3) as $g): ?>
            <div class="guide-card">
              <div class="guide-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
              </div>
              <div class="guide-info" style="flex:1;min-width:0;">
                <h4 style="font-size:0.9rem;"><?= htmlspecialchars($g['name']) ?></h4>
                <p>&#8369;<?= $g['rate'] ?>/group · Max <?= $g['maxHikers'] ?></p>
                <?= renderStars($g['rating']) ?>
              </div>
            </div>
            <?php endforeach; ?>
            <a href="../modals/login.php" class="btn btn-primary btn-block">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Book a Guide
            </a>
          </div>
        </div>

        <div class="card card--flat">
          <div class="card-body">
            <h4 style="margin-bottom:0.75rem;">Jump-off Point</h4>
            <p style="font-size:0.88rem;margin-bottom:0.75rem;"><?= htmlspecialchars($mountain['jumpOff']) ?></p>
            <div class="map-placeholder" style="height:130px;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
              <p>Map view available in full app</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.detail-grid{grid-template-columns:1fr!important;}.stats-mini-grid{grid-template-columns:repeat(2,1fr)!important;}}</style>
<script>
function switchTab(id,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-'+id).classList.add('active');
}
</script>
<?php include_once $base.'includes/footer.php'; ?>
