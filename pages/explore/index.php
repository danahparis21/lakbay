<?php
$pageTitle  = 'Explore Mountains — LAKBAY';
$activePage = 'explore';
$isLoggedIn = false;
$base       = '../../';
include_once $base.'includes/header.php';
include_once $base.'includes/data.php';

$showQuiz = isset($_GET['quiz']);
?>

<!-- ===== EXPLORE HEADER ===== -->
<div class="explore-header page-wrap">
  <h1>Explore Mountains</h1>
  <p>Discover the perfect hiking destination in Nasugbu, Batangas</p>
  <div class="explore-search">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input class="form-control" type="text" id="searchInput" placeholder="Search Mt. Batulao, Apayang, Lantik, Talamitam…" aria-label="Search mountains">
  </div>
</div>

<section class="section section--alt" style="padding-top:2.5rem;">
  <div class="container">

    <?php if($showQuiz): ?>
    <!-- FIND MY TRAIL QUIZ -->
    <div class="card" id="quizCard" style="max-width:640px;margin:0 auto 3rem;" role="region" aria-label="Trail finder quiz">
      <div class="card-header"><h3>Find My Trail</h3><p style="margin:0.25rem 0 0;font-size:0.88rem;color:var(--teal);">Answer a few questions and we'll match you to the perfect mountain.</p></div>
      <div class="card-body">
        <form id="quizForm" onsubmit="runQuiz(event)">
          <div class="form-group">
            <label class="form-label">What is your hiking experience level?</label>
            <div style="display:flex;flex-direction:column;gap:0.5rem;" role="radiogroup">
              <?php foreach(['Never hiked before','A few easy hikes','Moderate experience','Very experienced'] as $i=>$opt): ?>
              <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;padding:0.65rem 1rem;border:1.5px solid var(--sky);border-radius:var(--radius);transition:var(--transition);" class="quiz-opt" data-group="exp">
                <input type="radio" name="experience" value="<?= $i ?>" required style="accent-color:var(--teal);"> <?= $opt ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">How long do you want to hike?</label>
            <div style="display:flex;flex-direction:column;gap:0.5rem;" role="radiogroup">
              <?php foreach(['Under 2 hours','2–3 hours','3–5 hours','All day'] as $i=>$opt): ?>
              <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;padding:0.65rem 1rem;border:1.5px solid var(--sky);border-radius:var(--radius);transition:var(--transition);" class="quiz-opt" data-group="dur">
                <input type="radio" name="duration" value="<?= $i ?>" required style="accent-color:var(--teal);"> <?= $opt ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">What scenery do you prefer?</label>
            <div style="display:flex;flex-direction:column;gap:0.5rem;" role="radiogroup">
              <?php foreach(['Pine forest &amp; solitude','360° panoramic summit views','Rolling grassland ridges','Mixed forest trail'] as $i=>$opt): ?>
              <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;padding:0.65rem 1rem;border:1.5px solid var(--sky);border-radius:var(--radius);transition:var(--transition);" class="quiz-opt" data-group="scen">
                <input type="radio" name="scenery" value="<?= $i ?>" required style="accent-color:var(--teal);"> <?= $opt ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:0.5rem;">Find My Trail</button>
        </form>
        <div id="quizResult" style="display:none;"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-bar" role="group" aria-label="Filter by difficulty">
      <label>Difficulty:</label>
      <div class="filter-chips">
        <button class="chip active" onclick="filterMountains('all',this)">All</button>
        <button class="chip" onclick="filterMountains('Beginner',this)">Beginner</button>
        <button class="chip" onclick="filterMountains('Intermediate',this)">Intermediate</button>
        <button class="chip" onclick="filterMountains('Advanced',this)">Advanced</button>
      </div>
    </div>

    <!-- MOUNTAINS GRID -->
    <div class="grid-4" id="mountainsGrid">
      <?php foreach($mountains as $m): ?>
      <a href="mountain.php?id=<?= $m['id'] ?>"
         class="card mountain-card"
         data-difficulty="<?= $m['difficulty'] ?>"
         data-name="<?= strtolower($m['name']) ?>"
         aria-label="<?= htmlspecialchars($m['name']) ?>, <?= $m['difficulty'] ?>, <?= $m['elevation'] ?>">
        <div class="mountain-card-img">
          <img src="<?= $m['image'] ?>" alt="<?= htmlspecialchars($m['name']) ?>" loading="lazy">
          <div class="mountain-card-img-overlay"></div>
          <div class="mountain-card-img-content">
            <h3><?= htmlspecialchars($m['name']) ?></h3>
            <?= getDifficultyBadge($m['difficulty']) ?>
          </div>
        </div>
        <div class="card-body">
          <p style="font-size:0.88rem;color:var(--teal);margin-bottom:0.75rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($m['description']) ?></p>
          <div class="mountain-meta">
            <span>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              <?= $m['elevation'] ?>
            </span>
            <span>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?= $m['duration'] ?>
            </span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.5rem;">
            <?= renderStars($m['rating']) ?>
            <span style="font-size:0.9rem;font-weight:600;color:var(--teal);">&#8369;<?= $m['fee'] ?></span>
          </div>
          <div style="margin-top:0.5rem;"><?= getCrowdBadge($m['crowdLevel']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <div id="noResults" style="display:none;" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <p>No mountains found matching your search.</p>
    </div>
  </div>
</section>

<script>
document.getElementById('searchInput').addEventListener('input', function(){
  var q = this.value.toLowerCase();
  var cards = document.querySelectorAll('#mountainsGrid .mountain-card');
  var visible = 0;
  cards.forEach(function(c){
    var show = c.dataset.name.includes(q);
    c.style.display = show ? '' : 'none';
    if(show) visible++;
  });
  document.getElementById('noResults').style.display = visible===0 ? 'block':'none';
});

function filterMountains(f, el){
  document.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('#mountainsGrid .mountain-card').forEach(function(c){
    c.style.display = (f==='all' || c.dataset.difficulty===f) ? '' : 'none';
  });
}

document.querySelectorAll('.quiz-opt').forEach(function(label){
  var inp = label.querySelector('input');
  if(inp) inp.addEventListener('change', function(){
    var group = label.dataset.group;
    document.querySelectorAll('[data-group="'+group+'"]').forEach(function(l){
      l.style.borderColor='var(--sky)'; l.style.background='';
    });
    label.style.borderColor='var(--teal)';
    label.style.background='rgba(37,74,90,0.06)';
  });
});

function runQuiz(e){
  e.preventDefault();
  var f = e.target;
  var exp  = parseInt(f.experience.value);
  var dur  = parseInt(f.duration.value);
  var scen = parseInt(f.scenery.value);
  var scores = [0,0,0,0];
  if(exp<=1){ scores[1]+=3; scores[2]+=3; } else if(exp===2){ scores[0]+=3; scores[3]+=3; } else { scores[0]+=2; scores[3]+=3; }
  if(dur<=1){ scores[1]+=2; scores[2]+=2; } else if(dur===2){ scores[1]+=1; scores[2]+=1; scores[0]+=1; } else { scores[0]+=2; scores[3]+=2; }
  if(scen===0){ scores[2]+=3; } else if(scen===1){ scores[3]+=3; } else if(scen===2){ scores[0]+=3; } else { scores[1]+=2; scores[2]+=1; }
  var best  = scores.indexOf(Math.max.apply(null,scores));
  var names = ['Mt. Batulao','Mt. Apayang','Mt. Lantik','Mt. Talamitam'];
  var ids   = [1,2,3,4];
  var descs = [
    'Batulao\'s iconic rolling ridges are made for your experience level — a satisfying challenge with incredible views.',
    'Apayang\'s pine-forested, beginner-friendly trail perfectly matches your pace and time.',
    'Lantik\'s quiet, off-beaten-path trail is your ideal escape for solitude and nature.',
    'Talamitam\'s panoramic summit and rolling hills will be an unforgettable adventure for you.'
  ];
  document.getElementById('quizForm').style.display='none';
  var res = document.getElementById('quizResult');
  res.style.display='block';
  res.innerHTML = '<div class="alert alert-success" style="flex-direction:column;gap:0.5rem;">'
    +'<strong style="font-size:1.1rem;">Your Perfect Trail: '+names[best]+'</strong>'
    +'<p style="margin:0.25rem 0 0;font-size:0.92rem;color:var(--navy);">'+descs[best]+'</p>'
    +'<div style="display:flex;gap:0.75rem;margin-top:1rem;">'
    +'<a href="mountain.php?id='+ids[best]+'" class="btn btn-primary btn-sm">View Mountain</a>'
    +'<button onclick="document.getElementById(\'quizForm\').style.display=\'\';document.getElementById(\'quizResult\').style.display=\'none\';" class="btn btn-outline btn-sm">Retake Quiz</button>'
    +'</div></div>';
}
</script>

<?php include_once $base.'includes/footer.php'; ?>
