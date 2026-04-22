<?php
// ============================================================
// PROCESS FORM FIRST — before ANY output
// ============================================================
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guideId  = trim($_POST['guide_id']  ?? '');
    $hikeDate = trim($_POST['hike_date'] ?? '');
    $hikers   = (int)($_POST['hikers']   ?? 1);
    $contact  = trim($_POST['contact']   ?? '');

    if ($guideId && $hikeDate && $hikers && $contact) {
        $success = true;
        // In a real app: save to DB here
    }
    // If validation fails, fall through to show form with errors
}

// ============================================================
// Now safe to output HTML
// ============================================================
$pageTitle  = 'Book a Guide — LAKBAY';
$activePage = '';
$isLoggedIn = true;
$userRole   = 'hiker';
$base       = '../../';
include_once $base . 'includes/header.php';
include_once $base . 'includes/data.php';

$mountainId = (int)($_GET['mountain'] ?? 1);
$mountain   = null;
foreach ($mountains as $m) {
    if ($m['id'] === $mountainId) { $mountain = $m; break; }
}
if (!$mountain) $mountain = $mountains[0];

// Guide fees lookup for JS
$guideFeeMap = [];
foreach ($guides as $g) $guideFeeMap[$g['id']] = $g['rate'];
?>

<div class="page-wrap">
  <div class="container" style="max-width:760px;padding-top:2.5rem;padding-bottom:3rem;">

    <a href="<?= $base ?>pages/explore/mountain.php?id=<?= $mountain['id'] ?>" class="auth-back" style="display:inline-flex;align-items:center;gap:.4rem;color:#254A5A;font-size:.88rem;font-weight:500;text-decoration:none;margin-bottom:1.5rem;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px;display:block;"><polyline points="15 18 9 12 15 6"/></svg>
      Back to <?= htmlspecialchars($mountain['name']) ?>
    </a>

    <div class="dashboard-page-header">
      <h1>Book a Guide</h1>
      <p>Complete your booking for <strong><?= htmlspecialchars($mountain['name']) ?></strong></p>
    </div>

    <!-- Steps indicator -->
    <div class="steps" style="margin-bottom:2rem;">
      <div class="step done"><div class="step-num">&#10003;</div><span class="step-label">Choose Mountain</span></div>
      <div class="step-connector"></div>
      <div class="step <?= $success?'done':'active' ?>"><div class="step-num"><?= $success?'&#10003;':'2' ?></div><span class="step-label">Select Guide &amp; Date</span></div>
      <div class="step-connector"></div>
      <div class="step <?= $success?'active':'' ?>"><div class="step-num">3</div><span class="step-label">Confirm Booking</span></div>
    </div>

    <?php if ($success): ?>
    <!-- SUCCESS STATE -->
    <div class="card">
      <div class="card-body" style="text-align:center;padding:3rem 2rem;">
        <div style="width:72px;height:72px;background:var(--moss);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" style="width:36px;height:36px;display:block;"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 style="color:var(--navy);margin-bottom:.75rem;">Booking Submitted!</h2>
        <p style="max-width:400px;margin:0 auto 2rem;">Your booking request has been sent. The guide and mountain manager will confirm within 24 hours.</p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
          <a href="<?= $base ?>pages/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
          <a href="<?= $base ?>pages/trail-history.php" class="btn btn-outline">View My Bookings</a>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- BOOKING FORM -->
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;" class="booking-grid">

        <!-- Left: Guide + Details -->
        <div>
          <!-- Guide Selection -->
          <div class="card card--flat" style="margin-bottom:1.25rem;">
            <div class="card-header"><h4>Select a Guide</h4></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem;">
              <?php
              $mGuides = array_filter($guides, fn($g) => in_array($mountainId, $g['mountains']));
              foreach ($mGuides as $g): ?>
              <label style="display:flex;gap:1rem;padding:1rem;border:1.5px solid var(--sky);border-radius:var(--radius);cursor:pointer;transition:all .2s;" class="guide-sel-opt">
                <input type="radio" name="guide_id" value="<?= $g['id'] ?>"
                       style="accent-color:var(--teal);margin-top:.25rem;flex-shrink:0;" required>
                <div class="guide-avatar">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                </div>
                <div style="flex:1;">
                  <div style="font-weight:600;color:var(--navy);"><?= htmlspecialchars($g['name']) ?></div>
                  <div style="font-size:.85rem;color:var(--teal);margin:.15rem 0;"><?= htmlspecialchars($g['bio']) ?></div>
                  <div style="font-size:.82rem;color:var(--teal);">&#8369;<?= $g['rate'] ?>/group &nbsp;·&nbsp; Max <?= $g['maxHikers'] ?> hikers &nbsp;·&nbsp; <?= $g['totalHikes'] ?> hikes</div>
                  <?= renderStars($g['rating']) ?>
                </div>
              </label>
              <?php endforeach; ?>
              <?php if (empty($mGuides)): ?>
              <p style="color:var(--teal);font-size:.9rem;">No guides assigned to this mountain yet.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Trip Details -->
          <div class="card card--flat">
            <div class="card-header"><h4>Trip Details</h4></div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label" for="hike_date">Preferred Date</label>
                <input class="form-control" type="date" id="hike_date" name="hike_date"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="hikers">Number of Hikers</label>
                <input class="form-control" type="number" id="hikers" name="hikers"
                       min="1" max="15" value="1" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="contact">Contact Number</label>
                <input class="form-control" type="tel" id="contact" name="contact"
                       placeholder="09XX-XXX-XXXX" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="details">Additional Notes</label>
                <textarea class="form-control" id="details" name="details" rows="3"
                          placeholder="First-time hikers, special requirements, etc."></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Summary -->
        <div style="position:sticky;top:80px;">
          <div class="card">
            <div class="card-header"><h4>Booking Summary</h4></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem;">
              <img src="<?= $mountain['image'] ?>" alt=""
                   style="width:100%;height:120px;object-fit:cover;border-radius:var(--radius);">
              <div>
                <div style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($mountain['name']) ?></div>
                <div style="font-size:.85rem;color:var(--teal);"><?= $mountain['location'] ?></div>
              </div>
              <hr class="divider">
              <div style="display:flex;justify-content:space-between;font-size:.88rem;">
                <span style="color:var(--teal);">Trail Fee (per person)</span>
                <span>&#8369;<?= $mountain['fee'] ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:.88rem;">
                <span style="color:var(--teal);">Guide Fee (group)</span>
                <span id="guideFeeVal">—</span>
              </div>
              <hr class="divider">
              <div style="display:flex;justify-content:space-between;font-weight:700;color:var(--navy);">
                <span>Estimated Total</span>
                <span id="totalVal">—</span>
              </div>
              <div class="alert alert-info" style="font-size:.82rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Payment collected on-site at jump-off point.
              </div>
              <button type="submit" class="btn btn-primary btn-block btn-lg">
                Submit Booking
              </button>
            </div>
          </div>
        </div>

      </div><!-- /booking-grid -->
    </form>
    <?php endif; ?>

  </div>
</div>

<style>
.guide-sel-opt:has(input:checked){ border-color:var(--teal)!important; background:rgba(37,74,90,.05); }
@media(max-width:768px){ .booking-grid{ grid-template-columns:1fr!important; } }
</style>

<script>
var guideFees  = <?= json_encode($guideFeeMap) ?>;
var trailFee   = <?= (int)$mountain['fee'] ?>;

document.querySelectorAll('input[name="guide_id"]').forEach(function(r){
  r.addEventListener('change', updateTotal);
});
document.getElementById('hikers').addEventListener('input', updateTotal);

function updateTotal(){
  var sel = document.querySelector('input[name="guide_id"]:checked');
  if (!sel) return;
  var gf  = guideFees[sel.value] || 0;
  var nh  = parseInt(document.getElementById('hikers').value) || 1;
  document.getElementById('guideFeeVal').textContent = '&#8369;' + gf;
  document.getElementById('totalVal').textContent    = '&#8369;' + (trailFee * nh + gf);
}
</script>

<?php include_once $base . 'includes/footer.php'; ?>
