<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY — Quiz</title>
<link rel="stylesheet" href="shared.css">
<style>
/* ── QUIZ PAGE ── */
.quiz-hero {
  background: linear-gradient(135deg, var(--forest) 0%, #2a4a2a 60%, var(--moss) 100%);
  padding: 60px 0 80px;
  position: relative;
  overflow: hidden;
}
.quiz-hero::before {
  content: '⛰';
  position: absolute;
  right: -30px; top: -40px;
  font-size: 200px;
  opacity: 0.05;
  pointer-events: none;
}
.quiz-hero-label { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold); margin-bottom: 12px; font-weight: 600; }
.quiz-hero-title { font-family: 'Playfair Display', serif; font-size: clamp(28px,5vw,44px); font-weight: 700; color: var(--white); margin-bottom: 12px; }
.quiz-hero-sub { font-size: 15px; color: var(--mist); line-height: 1.6; max-width: 500px; }

/* ── QUIZ BOX ── */
.quiz-container { max-width: 680px; margin: -40px auto 0; padding: 0 24px 60px; position: relative; z-index: 10; }
.quiz-card {
  background: var(--white);
  border-radius: 28px;
  box-shadow: 0 20px 60px rgba(26,46,26,0.2);
  overflow: hidden;
}
.quiz-progress-bar {
  height: 5px;
  background: rgba(90,122,90,0.15);
  position: relative;
}
.quiz-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--sage), var(--forest));
  transition: width .5s cubic-bezier(.4,0,.2,1);
}
.quiz-inner { padding: 36px 40px; }
@media(max-width:480px){ .quiz-inner { padding: 24px 20px; } }
.quiz-qnum { font-size: 11px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--stone); margin-bottom: 8px; }
.quiz-question { font-family: 'Playfair Display', serif; font-size: clamp(18px,3vw,24px); font-weight: 600; color: var(--forest); margin-bottom: 28px; line-height: 1.35; }
.quiz-options { display: flex; flex-direction: column; gap: 12px; }
.quiz-option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: var(--radius-sm);
  border: 1.5px solid rgba(90,122,90,0.15);
  background: var(--cream);
  cursor: pointer;
  transition: all .2s;
  font-size: 14px;
  font-weight: 500;
  color: var(--forest);
}
.quiz-option:hover { border-color: var(--sage); background: var(--sky); }
.quiz-option.selected { border-color: var(--forest); background: var(--forest); color: var(--cream); }
.quiz-option .opt-icon { font-size: 22px; flex-shrink: 0; }
.quiz-option .opt-text { flex: 1; }
.quiz-option .opt-score { font-size: 11px; opacity: 0.6; font-family: 'DM Mono', monospace; }
.quiz-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 40px 32px;
  border-top: 1px solid rgba(90,122,90,0.1);
}
@media(max-width:480px){ .quiz-nav { padding: 16px 20px 24px; } }

/* ── RESULTS ── */
.result-hero {
  background: linear-gradient(135deg, var(--forest), var(--moss));
  padding: 40px;
  text-align: center;
}
.result-badge {
  display: inline-block;
  background: var(--glass);
  backdrop-filter: blur(8px);
  border: 1px solid var(--glass-border);
  padding: 8px 24px;
  border-radius: 50px;
  font-size: 13px;
  font-weight: 600;
  color: var(--gold);
  margin-bottom: 16px;
}
.result-title { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: white; margin-bottom: 8px; }
.result-sub { font-size: 14px; color: var(--mist); }
.result-body { padding: 32px 40px; }
@media(max-width:480px){ .result-body { padding: 24px 20px; } }
.result-level {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--sky);
  border-radius: var(--radius-sm);
  padding: 20px;
  margin-bottom: 28px;
}
.level-icon { font-size: 40px; }
.level-name { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 600; color: var(--forest); }
.level-desc { font-size: 13px; color: var(--stone); margin-top: 4px; line-height: 1.5; }
.rec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px; }
@media(max-width:480px){ .rec-grid { grid-template-columns: 1fr; } }
.rec-card {
  border-radius: var(--radius-sm);
  border: 1.5px solid rgba(90,122,90,0.12);
  overflow: hidden;
  cursor: pointer;
  transition: transform .2s;
}
.rec-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.rec-card-img { height: 110px; background-size: cover; background-position: center; position: relative; }
.rec-card-img::after { content:''; position:absolute; inset:0; background:linear-gradient(transparent,rgba(10,20,10,0.6)); }
.rec-card-label { position: absolute; bottom: 8px; left: 10px; font-size: 12px; font-weight: 700; color: white; z-index: 1; }
.rec-card-body { padding: 12px; background: var(--white); }
.rec-card-name { font-weight: 700; font-size: 13px; color: var(--forest); margin-bottom: 2px; }
.rec-card-meta { font-size: 11px; color: var(--stone); }
.save-btn {
  display: flex; align-items: center; gap: 8px;
  background: none; border: 1.5px solid rgba(90,122,90,0.2);
  border-radius: 50px; padding: 6px 14px;
  font-size: 12px; font-weight: 600; color: var(--sage);
  cursor: pointer; transition: .15s;
}
.save-btn:hover { background: var(--forest); color: var(--cream); border-color: var(--forest); }
</style>
</head>
<body>

<!-- DESKTOP NAV -->
<nav class="desktop-nav">
  <a href="explore.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#1a2e1a" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#1a2e1a" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      Explore
    </a>
    <a href="bookings.php" class="tab-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      Bookings
    </a>
    <a href="quiz.php" class="tab-link quiz-tab active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Quiz
    </a>
    <a href="messages.php" class="tab-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Messages
    </a>
  </div>
  <a href="hikerProfile.php" class="user-btn">J</a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <span>Explore</span>
    </a>
    <a href="bookings.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <span>Bookings</span>
    </a>
    <a href="quiz.php" class="mob-nav-item quiz-center active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    </a>
    <a href="messages.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span>Messages</span>
    </a>
    <a href="profile.php" class="mob-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Profile</span>
    </a>
  </div>
</nav>

<!-- HERO -->
<div class="quiz-hero" id="quizHero">
  <div class="container">
    <div class="quiz-hero-label">⭐ Skill Assessment</div>
    <div class="quiz-hero-title">Find Your Perfect Mountain</div>
    <div class="quiz-hero-sub">Answer 6 quick questions and we'll match you with trails suited to your experience, fitness, and goals.</div>
  </div>
</div>

<!-- QUIZ CARD -->
<div class="quiz-container" id="quizContainer">
  <div class="quiz-card" id="quizCard">
    <div class="quiz-progress-bar">
      <div class="quiz-progress-fill" id="quizProgress" style="width:0%"></div>
    </div>
    <div class="quiz-inner" id="quizInner">
      <!-- Rendered by JS -->
    </div>
    <div class="quiz-nav" id="quizNav">
      <button class="btn btn-outline" id="quizBack" onclick="prevQ()" style="visibility:hidden;">← Back</button>
      <span style="font-size:12px;color:var(--stone);font-family:'DM Mono',monospace;" id="quizCounter">1 / 6</span>
      <button class="btn btn-primary" id="quizNext" onclick="nextQ()" disabled>Next →</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const questions = [
  {
    q: "How many mountains have you climbed before?",
    icon: "🏔️",
    opts: [
      {icon:"😊",text:"None — this is my first!",score:0},
      {icon:"🥾",text:"1–3 mountains",score:1},
      {icon:"🧗",text:"4–10 mountains",score:2},
      {icon:"⛰️",text:"More than 10",score:3}
    ]
  },
  {
    q: "How long can you comfortably hike without resting?",
    icon: "⏱️",
    opts: [
      {icon:"🚶",text:"Less than 1 hour",score:0},
      {icon:"🚶‍♂️",text:"1–2 hours",score:1},
      {icon:"🏃",text:"3–4 hours",score:2},
      {icon:"🏃‍♀️",text:"5+ hours no problem",score:3}
    ]
  },
  {
    q: "What's your fitness level?",
    icon: "💪",
    opts: [
      {icon:"😴",text:"Couch potato — I'm new to this",score:0},
      {icon:"🚴",text:"Light exercise, walks occasionally",score:1},
      {icon:"🏋️",text:"Regular exercise, decent stamina",score:2},
      {icon:"🔥",text:"Athletic — I train regularly",score:3}
    ]
  },
  {
    q: "Are you comfortable with steep or technical terrain?",
    icon: "🪨",
    opts: [
      {icon:"😰",text:"No, I prefer flat or gentle slopes",score:0},
      {icon:"🙂",text:"Mild inclines are fine",score:1},
      {icon:"💪",text:"I can handle steep sections",score:2},
      {icon:"🧗",text:"Technical rock scrambles? Bring it!",score:3}
    ]
  },
  {
    q: "What type of hike appeals to you?",
    icon: "🌿",
    opts: [
      {icon:"🌅",text:"Scenic views, mostly easy walking",score:0},
      {icon:"🌲",text:"Forest trails with moderate challenge",score:1},
      {icon:"🌊",text:"Multi-terrain adventure with a reward",score:2},
      {icon:"🏕️",text:"Overnight expedition, the full experience",score:3}
    ]
  },
  {
    q: "How do you handle altitude and weather changes?",
    icon: "🌤️",
    opts: [
      {icon:"😮",text:"I haven't experienced this yet",score:0},
      {icon:"🤔",text:"Mild discomfort but manageable",score:1},
      {icon:"😎",text:"Generally fine, I adapt quickly",score:2},
      {icon:"🦾",text:"No issues at all — I'm experienced",score:3}
    ]
  }
];

const mountains = [
  {name:"Mt. Talamitam",diff:"easy",elevation:"630m",time:"3–4 hrs",img:"https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=400&q=60",score:[0,1]},
  {name:"Mt. Batulao",diff:"moderate",elevation:"811m",time:"4–5 hrs",img:"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=60",score:[1,2]},
  {name:"Mt. Lantik",diff:"moderate",elevation:"710m",time:"4–5 hrs",img:"https://images.unsplash.com/photo-1501854140801-50d01698950b?w=400&q=60",score:[1,2]},
  {name:"Mt. Apayang",diff:"hard",elevation:"980m",time:"6–7 hrs",img:"https://images.unsplash.com/photo-1519681393784-d120267933ba?w=400&q=60",score:[2,3]}
];

const levels = {
  beginner: {icon:"🌱",name:"Beginner Explorer",desc:"You're just starting your hiking journey! We recommend gentle, scenic trails with easy terrain. Great views ahead!"},
  intermediate: {icon:"🌿",name:"Intermediate Adventurer",desc:"You have some experience and decent fitness. Moderate trails with varied terrain and rewarding summits await you."},
  advanced: {icon:"🏔️",name:"Experienced Mountaineer",desc:"You're no stranger to the trails. Challenging ascents, technical terrain, and multi-day expeditions are your playground."}
};

let currentQ = 0;
let answers = [];
let scores = [];

function renderQ(i) {
  const q = questions[i];
  const pct = (i/questions.length)*100;
  document.getElementById('quizProgress').style.width = pct+'%';
  document.getElementById('quizCounter').textContent = `${i+1} / ${questions.length}`;
  document.getElementById('quizBack').style.visibility = i>0?'visible':'hidden';
  document.getElementById('quizNext').disabled = answers[i]===undefined;
  document.getElementById('quizNext').textContent = i===questions.length-1 ? 'See Results →' : 'Next →';

  document.getElementById('quizInner').innerHTML = `
    <div class="quiz-qnum">Question ${i+1} of ${questions.length}</div>
    <div class="quiz-question">${q.icon} ${q.q}</div>
    <div class="quiz-options">
      ${q.opts.map((o,j)=>`
        <div class="quiz-option ${answers[i]===j?'selected':''}" onclick="selectOpt(${j},${o.score})">
          <div class="opt-icon">${o.icon}</div>
          <div class="opt-text">${o.text}</div>
        </div>
      `).join('')}
    </div>
  `;
}

function selectOpt(j, score) {
  answers[currentQ] = j;
  scores[currentQ] = score;
  renderQ(currentQ);
}

function nextQ() {
  if(answers[currentQ]===undefined) return;
  if(currentQ === questions.length-1) { showResults(); return; }
  currentQ++;
  renderQ(currentQ);
}

function prevQ() {
  if(currentQ===0) return;
  currentQ--;
  renderQ(currentQ);
}

function showResults() {
  const total = scores.reduce((a,b)=>a+b,0);
  const max = questions.length * 3;
  const pct = total/max;
  let level = pct < 0.33 ? 'beginner' : pct < 0.67 ? 'intermediate' : 'advanced';
  const lvl = levels[level];

  const recs = mountains.filter(m=>{
    if(level==='beginner') return m.score.includes(0)||m.score.includes(1);
    if(level==='intermediate') return m.score.includes(1)||m.score.includes(2);
    return m.score.includes(2)||m.score.includes(3);
  });

  document.getElementById('quizHero').style.display='none';
  document.getElementById('quizContainer').style.marginTop='24px';
  document.getElementById('quizCard').innerHTML = `
    <div class="result-hero">
      <div class="result-badge">🎉 Results Ready</div>
      <div class="result-title">${lvl.icon} ${lvl.name}</div>
      <div class="result-sub">Score: ${total}/${max} · ${Math.round(pct*100)}% proficiency</div>
    </div>
    <div class="result-body">
      <div class="result-level">
        <div class="level-icon">${lvl.icon}</div>
        <div>
          <div class="level-name">${lvl.name}</div>
          <div class="level-desc">${lvl.desc}</div>
        </div>
      </div>
      <div style="font-size:12px;font-weight:600;color:var(--sage);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;">🗻 Recommended for You</div>
      <div class="rec-grid">
        ${recs.map(m=>`
          <div class="rec-card" onclick="window.location='explore.php'">
            <div class="rec-card-img" style="background-image:url('${m.img}')">
              <div class="rec-card-label">${m.name}</div>
            </div>
            <div class="rec-card-body">
              <div class="rec-card-name">${m.name}</div>
              <div class="rec-card-meta">⛰ ${m.elevation} · ⏱ ${m.time}</div>
              <div style="margin-top:6px;"><span class="badge badge-${m.diff}">${m.diff}</span></div>
            </div>
          </div>
        `).join('')}
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="save-btn" onclick="saveResults('${level}')">🔖 Save recommendations</button>
        <button class="btn btn-outline btn-sm" onclick="location.reload()">Retake Quiz</button>
        <a href="explore.php" class="btn btn-primary btn-sm">Explore All →</a>
      </div>
    </div>
  `;
}

function saveResults(level) {
  localStorage.setItem('quizLevel', level);
  showToast('Recommendations saved to your profile! 🔖');
}

function showToast(msg) {
  const t=document.getElementById('toast');
  t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2800);
}

renderQ(0);
</script>
</body>
</html>
