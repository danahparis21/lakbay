<?php
// hiker frontend/quiz.php - LAKBAY Skill Assessment Quiz
require_once __DIR__ . '/../config/db.php';

session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUser = null;
$userInitial = 'J';

// Fetch current user details
$stmt = $pdo->prepare("SELECT id, name, email, avatar, hiking_level, home_region FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$currentUser = $stmt->fetch();

if ($currentUser) {
    $nameParts = explode(' ', trim($currentUser['name']));
    $userInitial = '';
    foreach ($nameParts as $part) {
        if (!empty($part)) {
            $userInitial .= strtoupper(substr($part, 0, 1));
        }
    }
    $userInitial = substr($userInitial, 0, 2);
}

// --- Mountains from Database ---
// Check if mountains table exists and has data
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM mountains");
    $mountainCount = $stmt->fetchColumn();
    
    if ($mountainCount == 0) {
        // Insert sample mountains if none exist
        $pdo->exec("
            INSERT INTO mountains (name, description, rating, location, image, jumpOff, duration, fee, elevation, crowdLevel, weatherAdvisory, peakTimes, rules, envReminders, hazards, status, difficulty) VALUES
            ('Mt. Batulao', 'Known for its iconic rolling hills and stunning panoramic views of Taal Volcano. Perfect for beginners and intermediate hikers.', 4.7, 'Nasugbu, Batangas', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=80', 'Barangay Evercrest, Nasugbu', '4-5 hours', 500, '811 MASL', 'High', 'Clear skies expected. Temperature: 24-28°C.', 'Weekends 6AM-9AM', '[\"Register at barangay hall\",\"No littering\"]', '[\"Bring water\",\"Pack out trash\"]', '[\"Slippery when wet\"]', 'Open', 'Easy'),
            ('Mt. Talamitam', 'A gentle peak with scenic grassland summit. Great for beginners and family hikes.', 4.5, 'Nasugbu, Batangas', 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=80', 'Barangay Kaysuyo, Nasugbu', '3-4 hours', 500, '630 MASL', 'Medium', 'Sunny with scattered clouds', 'November to May is peak season', '[\"Register at jump-off\"]', '[\"Pack your trash\"]', '[\"Steep sections\"]', 'Open', 'Moderate'),
            ('Mt. Pulag', 'Highest peak in Luzon, famous for its sea of clouds and stunning sunrise.', 4.9, 'Kabayan, Benguet', 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&q=80', 'Ambangeg Trail', '5-6 hours', 1500, '2922 MASL', 'Very High', 'Cold temperatures, occasional rain', 'Peak season Dec-May', '[\"Register at DENR\",\"Hire local guide\"]', '[\"Bring warm clothes\",\"Leave no trace\"]', '[\"Altitude sickness risk\"]', 'Open', 'Hard')
        ");
        // Refresh count after insert
        $mountainCount = 3;
    }
} catch (PDOException $e) {
    // Table might not exist - create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mountains (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            rating DECIMAL(3,2),
            location VARCHAR(255),
            image VARCHAR(500),
            jumpOff VARCHAR(255),
            duration VARCHAR(100),
            fee DECIMAL(10,2),
            elevation VARCHAR(50),
            crowdLevel VARCHAR(50),
            weatherAdvisory TEXT,
            peakTimes TEXT,
            rules JSON,
            envReminders JSON,
            hazards JSON,
            status VARCHAR(50),
            difficulty VARCHAR(50)
        )
    ");
}

// Fetch mountains from database
$stmt = $pdo->query("SELECT * FROM mountains WHERE status = 'Open' ORDER BY rating DESC");
$dbMountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map difficulty to level scores
$difficultyScoreMap = [
    'Easy' => [0, 1],
    'Moderate' => [1, 2],
    'Hard' => [2, 3],
    'Expert' => [3],
];

// Prepare mountains array for JavaScript
$mountainsFromDb = [];
foreach ($dbMountains as $mountain) {
    $difficulty = $mountain['difficulty'] ?? 'Moderate';
    $mountainsFromDb[] = [
        'name' => $mountain['name'],
        'diff' => strtolower($difficulty),
        'elevation' => $mountain['elevation'] ?? 'N/A',
        'time' => $mountain['duration'] ?? '3-4 hrs',
        'img' => $mountain['image'] ?? 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=500&q=80',
        'score' => $difficultyScoreMap[$difficulty] ?? [1, 2]
    ];
}

// Handle AJAX request to save quiz results and update hiking level
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['level']) && isset($data['totalScore']) && isset($data['maxScore'])) {
        $newLevel = $data['level'];
        $totalScore = (int)$data['totalScore'];
        $maxScore = (int)$data['maxScore'];
        
        try {
            // Update user's hiking_level based on quiz result
            $stmt = $pdo->prepare("UPDATE users SET hiking_level = ? WHERE id = ?");
            $stmt->execute([$newLevel, $currentUserId]);
            
            $_SESSION['hiking_level'] = $newLevel;
            
            echo json_encode(['success' => true, 'message' => 'Profile updated with new hiking level!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY — Quiz</title>
<link rel="stylesheet" href="shared.css">
<style>
/* ── QUIZ PAGE STYLES ── */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --forest: #1a3a1a;
  --moss: #2c5e2c;
  --sage: #6a8e6a;
  --gold: #c6a43b;
  --stone: #5a5e5a;
  --mist: #e2e6e0;
  --cream: #faf8f2;
  --sky: #ecf3e9;
  --white: #ffffff;
  --glass: rgba(255, 255, 245, 0.2);
  --glass-border: rgba(255, 255, 240, 0.35);
  --shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
  --radius-sm: 20px;
  --btn-special: #100600;
  --nav-h: 74px;
}
@media(max-width:768px){ :root { --nav-h: 0px; } }

body {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  background: var(--cream);
  color: var(--forest);
  overflow-x: hidden;
}

/* MODAL STYLES */
.modal-overlay {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(12px);
  z-index: 9999;
  display: grid;
  place-items: center;
  opacity: 0;
  visibility: hidden;
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
  margin: 0;
  padding: 0;
}

.modal-overlay.active {
  opacity: 1;
  visibility: visible;
}

.modal-content {
  background: linear-gradient(145deg, #1e2a1a, #0a1a0a);
  border-radius: 40px;
  max-width: 680px; /* Match the quiz container width */
  width: 92%;
  padding: 56px 40px;
  text-align: center;
  border: 1px solid rgba(198, 164, 59, 0.3);
  box-shadow: 0 40px 120px rgba(0,0,0,0.8);
  transform: translateY(40px) scale(0.95);
  transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  margin: auto;
}

.modal-overlay.active .modal-content {
  transform: translateY(0) scale(1);
}

.modal-icon {
  width: 100px;
  height: 100px;
  margin: 0 auto 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(198, 164, 59, 0.1);
  border-radius: 50%;
  color: var(--gold);
}

.modal-icon svg, .result-modal-icon svg {
  width: 80px !important;
  height: 80px !important;
  stroke: currentColor;
  stroke-width: 1.5;
}

.modal-icon svg {
  animation: float 3s ease-in-out infinite;
}

.modal-title {
  font-family: 'Playfair Display', serif;
  font-size: 32px;
  font-weight: 800;
  color: var(--gold);
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}

.modal-subtitle {
  font-size: 20px;
  font-weight: 600;
  color: white;
  margin-bottom: 20px;
  opacity: 0.9;
}

.modal-desc {
  font-size: 16px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.7;
  margin-bottom: 36px;
}

.modal-btn {
  background: linear-gradient(135deg, var(--gold), #d4af37);
  border: none;
  padding: 16px 40px;
  border-radius: 50px;
  font-weight: 700;
  font-size: 18px;
  color: #0a1a0a;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 10px 20px rgba(198, 164, 59, 0.2);
}

.modal-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 30px rgba(198, 164, 59, 0.4);
}

.result-modal-icon {
  width: 120px;
  height: 120px;
  margin: 0 auto 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gold);
  animation: popIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.result-modal-icon svg {
  width: 80px;
  height: 80px;
  stroke: var(--gold);
}

.result-level-name {
  font-size: 36px;
  font-weight: 900;
  background: linear-gradient(135deg, #fff, var(--gold));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  margin-bottom: 16px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

@keyframes popIn {
  0% { transform: scale(0); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

/* QUIZ HERO */
.quiz-hero {
  background: url('https://images.unsplash.com/photo-1519681393784-d120267933ba?w=1600&q=80') center/cover no-repeat;
  padding: 60px 0 80px;
  position: relative;
  overflow: hidden;
  margin-top: var(--nav-h);
}
.quiz-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(107deg, rgba(16,6,0,0.55) 0%, rgba(26,46,26,0.6) 100%);
  z-index: 0;
}
.quiz-hero .container {
  position: relative;
  z-index: 2;
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  justify-content: flex-start;
}
@media (max-width: 768px) {
  .quiz-hero .container {
    justify-content: center;
  }
}
.hero-glass-card {
  max-width: 650px;
  width: 100%;
  background: rgba(255, 255, 245, 0.18);
  backdrop-filter: blur(14px);
  border-radius: 48px;
  border: 1px solid rgba(255,245,210,0.45);
  padding: 36px 40px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.3);
  text-align: left;
}
@media (max-width: 768px) {
  .hero-glass-card {
    text-align: center;
    margin: 0 auto;
  }
  .quiz-hero-sub {
    margin: 0 auto;
  }
}
.quiz-hero-label {
  font-size: 11px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}
.quiz-hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(28px,5vw,44px);
  font-weight: 700;
  color: var(--white);
  margin-bottom: 12px;
}
.quiz-hero-sub {
  font-size: 15px;
  color: rgba(255,250,235,0.92);
  line-height: 1.6;
  max-width: 500px;
}
@media(max-width:680px){
  .hero-glass-card { margin: 0 20px; padding: 28px 24px; }
  .quiz-hero { padding: 60px 0 80px; }
}

/* QUIZ CONTAINER */
.quiz-container {
  max-width: 680px;
  margin: -40px auto 0;
  padding: 0 24px 60px;
  position: relative;
  z-index: 12;
}
.quiz-card {
  background: rgba(255, 255, 248, 0.94);
  backdrop-filter: blur(10px);
  border-radius: 28px;
  box-shadow: 0 20px 60px rgba(26,46,26,0.2);
  overflow: hidden;
  border: 1px solid rgba(255,245,200,0.5);
}
.quiz-progress-bar {
  height: 5px;
  background: rgba(90,122,90,0.15);
  position: relative;
}
.quiz-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--gold), #d4af37);
  transition: width .5s cubic-bezier(.4,0,.2,1);
}
.quiz-inner { padding: 36px 40px; }
@media(max-width:480px){ .quiz-inner { padding: 24px 20px; } }
.quiz-qnum { font-size: 11px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--stone); margin-bottom: 8px; }
.quiz-question {
  font-family: 'Playfair Display', serif;
  font-size: clamp(18px,3vw,24px);
  font-weight: 600;
  color: var(--forest);
  margin-bottom: 28px;
  line-height: 1.35;
  display: flex;
  align-items: center;
  gap: 12px;
}
.quiz-question .question-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.quiz-options { display: flex; flex-direction: column; gap: 12px; }
.quiz-option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: 60px;
  border: 1.5px solid rgba(90,122,90,0.2);
  background: rgba(250,248,242,0.8);
  cursor: pointer;
  transition: all .2s;
  font-size: 14px;
  font-weight: 500;
  color: var(--forest);
}
.quiz-option:hover { border-color: var(--sage); background: var(--sky); }
.quiz-option.selected { border-color: var(--btn-special); background: var(--btn-special); color: var(--cream); box-shadow: 0 4px 12px rgba(16,6,0,0.3); }
.quiz-option .opt-icon { width: 28px; height: 28px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; }
.quiz-option .opt-text { flex: 1; }
.quiz-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 40px 32px;
  border-top: 1px solid rgba(90,122,90,0.1);
}
@media(max-width:480px){ .quiz-nav { padding: 16px 20px 24px; } }

/* BUTTONS */
.btn {
  border: none;
  font-weight: 600;
  padding: 10px 24px;
  border-radius: 44px;
  font-size: 14px;
  cursor: pointer;
  transition: 0.2s;
  font-family: 'Inter', system-ui, sans-serif;
}
.btn-primary {
  background: #100600;
  color: white;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.btn-primary:hover:not(:disabled) {
  background: #2c1a0a;
  transform: translateY(-1px);
}
.btn-primary:disabled {
  background: #8b7a6b;
  cursor: not-allowed;
  opacity: 0.6;
}
.btn-outline {
  background: transparent;
  border: 1.5px solid #100600;
  color: #100600;
}
.btn-outline:hover {
  background: #10060010;
  border-color: #3a2a1a;
}
.btn-sm {
  padding: 8px 20px;
  font-size: 13px;
}

/* RESULTS */
.result-hero {
  background: linear-gradient(135deg, #1e2a1a, #0f2a0f);
  padding: 40px;
  text-align: center;
}
.result-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,255,240,0.18);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255,235,170,0.5);
  padding: 8px 24px;
  border-radius: 50px;
  font-size: 13px;
  font-weight: 600;
  color: var(--gold);
  margin-bottom: 16px;
}
.result-title { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: white; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
.result-sub { font-size: 14px; color: var(--mist); }
.result-body { padding: 32px 40px; background: rgba(255, 255, 245, 0.96); }
@media(max-width:480px){ .result-body { padding: 24px 20px; } }
.result-level {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--sky);
  border-radius: 28px;
  padding: 20px;
  margin-bottom: 28px;
}
.level-icon { width: 48px; height: 48px; flex-shrink: 0; }
.level-name { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 600; color: var(--forest); }
.level-desc { font-size: 13px; color: var(--stone); margin-top: 4px; line-height: 1.5; }
.rec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px; }
@media(max-width:480px){ .rec-grid { grid-template-columns: 1fr; } }
.rec-card {
  border-radius: 20px;
  border: 1.5px solid rgba(90,122,90,0.12);
  overflow: hidden;
  cursor: pointer;
  transition: transform .2s;
  background: white;
}
.rec-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.rec-card-img { height: 110px; background-size: cover; background-position: center; position: relative; }
.rec-card-img::after { content:''; position:absolute; inset:0; background:linear-gradient(transparent,rgba(10,20,10,0.6)); }
.rec-card-label { position: absolute; bottom: 8px; left: 10px; font-size: 12px; font-weight: 700; color: white; z-index: 1; }
.rec-card-body { padding: 12px; background: var(--white); }
.rec-card-name { font-weight: 700; font-size: 13px; color: var(--forest); margin-bottom: 2px; }
.rec-card-meta { font-size: 11px; color: var(--stone); margin: 4px 0 6px; display: flex; gap: 10px; align-items: center; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 40px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.badge-easy { background: #d9ead3; color: #2a6b2a; }
.badge-moderate { background: #ffe0b5; color: #8a5a2a; }
.badge-hard { background: #ffcfc2; color: #a23b1a; }
.save-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: none;
  border: 1.5px solid #100600;
  border-radius: 50px;
  padding: 8px 18px;
  font-size: 12px;
  font-weight: 600;
  color: #100600;
  cursor: pointer;
  transition: .15s;
}
.save-btn:hover { background: #100600; color: var(--cream); border-color: #100600; }
.toast {
  position: fixed;
  bottom: 32px;
  left: 50%;
  transform: translateX(-50%) translateY(20px);
  background: #100600e6;
  backdrop-filter: blur(12px);
  color: #f5e7d9;
  padding: 12px 28px;
  border-radius: 60px;
  font-size: 14px;
  font-weight: 500;
  opacity: 0;
  transition: 0.25s;
  pointer-events: none;
  z-index: 999;
  white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.container { max-width: 1280px; margin: 0 auto; padding: 0 24px; width: 100%; }

.quiz-option.selected .opt-icon svg { stroke: var(--cream); }
</style>
</head>
<body>

<!-- DESKTOP NAV -->
<nav class="desktop-nav">
  <a href="../index.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore</a>
    <a href="bookings.php" class="tab-link"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings</a>
    <a href="quiz.php" class="tab-link active"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz</a>
    <a href="messages.php" class="tab-link"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages</a>
  </div>
  <a href="hikerProfile.php" class="user-btn"><?php echo htmlspecialchars($userInitial); ?></a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span></a>
    <a href="bookings.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span></a>
    <a href="quiz.php" class="mob-nav-item quiz-center active"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></a>
    <a href="messages.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="hikerProfile.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
  </div>
</nav>

<!-- INTRO MODAL -->
<div class="modal-overlay" id="introModal">
  <div class="modal-content">
    <div class="modal-icon">
      <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.2"><path d="M12 2L2 7L12 12L22 7L12 2Z"/><path d="M2 17L12 22L22 17"/><path d="M2 12L12 17L22 12"/><path d="M12 2V12"/></svg>
    </div>
    <div class="modal-title">Welcome to Lakbay Quiz!</div>
    <div class="modal-subtitle">Find Your Perfect Mountain Match</div>
    <div class="modal-desc">This quick assessment will help us understand your hiking experience, fitness level, and preferences. Answer 6 questions honestly to get personalized mountain recommendations and discover your hiking level!</div>
    <button class="modal-btn" onclick="startQuiz()">Begin Quiz →</button>
  </div>
</div>

<!-- HERO -->
<div class="quiz-hero" id="quizHero">
  <div class="container">
    <div class="hero-glass-card">
      <div class="quiz-hero-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Skill Assessment</div>
      <div class="quiz-hero-title">Find Your Perfect Mountain</div>
      <div class="quiz-hero-sub">Answer 6 quick questions and we'll match you with trails suited to your experience, fitness, and goals.</div>
    </div>
  </div>
</div>

<!-- QUIZ CARD -->
<div class="quiz-container" id="quizContainer" style="display: none;">
  <div class="quiz-card" id="quizCard">
    <div class="quiz-progress-bar"><div class="quiz-progress-fill" id="quizProgress" style="width:0%"></div></div>
    <div class="quiz-inner" id="quizInner"></div>
    <div class="quiz-nav" id="quizNav">
      <button class="btn btn-outline" id="quizBack" onclick="prevQ()" style="visibility:hidden;">← Back</button>
      <span style="font-size:12px;color:var(--stone);font-family:'DM Mono',monospace;" id="quizCounter">1 / 6</span>
      <button class="btn btn-primary" id="quizNext" onclick="nextQ()" disabled>Next →</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Mountains from PHP database
const mountainsFromDb = <?php echo json_encode($mountainsFromDb); ?>;

// Fallback mountains if database empty
const fallbackMountains = [
  {name:"Mt. Talamitam",diff:"easy",elevation:"630m",time:"3–4 hrs",img:"https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=500&q=80",score:[0,1]},
  {name:"Mt. Batulao",diff:"moderate",elevation:"811m",time:"4–5 hrs",img:"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=500&q=80",score:[1,2]},
  {name:"Mt. Pulag",diff:"hard",elevation:"2922m",time:"5–6 hrs",img:"https://images.unsplash.com/photo-1519681393784-d120267933ba?w=500&q=80",score:[2,3]}
];

const mountains = mountainsFromDb.length > 0 ? mountainsFromDb : fallbackMountains;

const iconMap = {
  mountainQ: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20L12 4L20 20H4Z" stroke="currentColor" fill="none"/><path d="M12 4L8 12L12 16L16 12L12 4Z" stroke="currentColor" fill="none"/></svg>',
  clockQ: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  fitnessQ: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L15 9H22L16 14L19 22L12 17.5L5 22L8 14L2 9H9L12 2Z"/></svg>',
  terrainQ: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 20L7 10L12 15L17 7L22 20H2Z"/><circle cx="7" cy="10" r="2"/><circle cx="17" cy="7" r="2"/></svg>',
  hikeTypeQ: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L2 7L12 12L22 7L12 2Z"/><path d="M2 17L12 22L22 17"/><path d="M2 12L12 17L22 12"/></svg>',
  weatherQ: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2V4M4 12H2M6.5 6.5L5 5M17.5 6.5L19 5M22 12H20M18.5 17.5L20 19M5.5 17.5L4 19M12 20V22M16 12C16 14.209 14.209 16 12 16C9.791 16 8 14.209 8 12C8 9.791 9.791 8 12 8C14.209 8 16 9.791 16 12Z"/></svg>',
  seedling: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 8V20M12 8C10 8 7 6 7 3C9 3 12 5 12 8Z"/><path d="M12 8C14 8 17 6 17 3C15 3 12 5 12 8Z"/><path d="M4 20H20"/></svg>',
  boot: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 16H6V12L8 8H16L18 12V16Z"/><path d="M6 16L4 20M18 16L20 20"/></svg>',
  climbing: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2L8 10L4 16L12 22L20 16L16 10L12 2Z"/><path d="M12 2L12 10L8 16"/></svg>',
  peak: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20L12 4L20 20"/><line x1="8" y1="14" x2="12" y2="8"/><line x1="12" y1="20" x2="12" y2="14"/></svg>',
  walk: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="18" r="2"/><path d="M12 16V8M8 12L12 8L16 12"/><path d="M6 20L4 22M18 20L20 22"/></svg>',
  runner: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="16" cy="5" r="2"/><path d="M12 13L14 9L19 10L21 14"/><path d="M7 12L10 10L13 13L9 17L5 15L7 12Z"/></svg>',
  lightning: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polygon points="13 2 3 14 11 14 9 22 19 10 11 10 13 2"/></svg>',
  couch: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="10" width="14" height="8" rx="2"/><path d="M5 10V6H19V10"/><path d="M9 18V20M15 18V20"/></svg>',
  bike: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path d="M13 6L9 15L12 18L17 11"/><path d="M13 9L16 6H20"/></svg>',
  weight: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="6" y="12" width="12" height="8" rx="2"/><path d="M12 8V12"/><path d="M8 4L10 8M16 4L14 8"/></svg>',
  flame: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2C12 4 10 6 10 8C10 10.5 12 11 12 14C12 16 10 17 10 19C10 20.5 11 22 12 22"/><path d="M18 15C18 12 14 12 14 9C14 6 15 4 17 2"/></svg>',
  gentle: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 20L7 10L10 15L14 8L16 12L22 20"/><circle cx="7" cy="10" r="2"/></svg>',
  incline: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 18L12 9L21 18"/><path d="M9 15L12 12L15 15"/></svg>',
  steep: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 20L12 5L19 20"/><path d="M9 14L12 9L15 14"/></svg>',
  scramble: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2L8 10L4 16L12 22L20 16L16 10L12 2Z"/><path d="M12 10L16 16M12 10L8 16"/></svg>',
  sunrise: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2V6M4 20H20M6 12L8 10M18 12L16 10M12 12L16 20H8L12 12Z"/><path d="M2 20H22"/></svg>',
  forest: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 20L10 8L14 20"/><path d="M12 20L16 8L20 20"/><path d="M8 20L12 12L16 20"/></svg>',
  adventure: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2L3 12L5 14L12 22L19 14L21 12L12 2Z"/><circle cx="12" cy="12" r="2"/></svg>',
  tent: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20L12 4L20 20"/><polygon points="12 11 6 20 18 20 12 11"/></svg>',
  fog: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 18H22M4 14H20M6 10H18"/><circle cx="12" cy="6" r="4"/></svg>',
  wind: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 12H20M2 8H18M2 16H22"/><path d="M18 6C18 3.5 16 2 14 2C11.5 2 10 4 10 6"/></svg>',
  shield: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2L3 6V12C3 17.5 12 22 12 22C12 22 21 17.5 21 12V6L12 2Z"/><path d="M12 8V12M12 16H12.01"/></svg>',
  leaf: '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2C9 8 4 12 4 16C4 18 8 20 12 20C16 20 20 18 20 16C20 12 15 8 12 2Z"/><path d="M12 20V22"/></svg>',
  mountainBadge: '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="16" r="1.5"/></svg>',
  bookmarkIcon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>',
  trophy: '<svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#c6a43b" stroke-width="1.5"><path d="M6 9H4C2.89543 9 2 9.89543 2 11V13C2 14.1046 2.89543 15 4 15H6"/><path d="M18 9H20C21.1046 9 22 9.89543 22 11V13C22 14.1046 21.1046 15 20 15H18"/><path d="M7 2H17V9C17 11.7614 14.7614 14 12 14C9.23858 14 7 11.7614 7 9V2Z"/><path d="M12 14V20"/><path d="M8 20H16"/><path d="M12 4V8"/></svg>'
};

const questions = [
  { q: "How many mountains have you climbed before?", icon: iconMap.mountainQ,
    opts: [{icon:iconMap.seedling, text:"None — this is my first!",score:0},{icon:iconMap.boot,text:"1–3 mountains",score:1},{icon:iconMap.climbing,text:"4–10 mountains",score:2},{icon:iconMap.peak,text:"More than 10",score:3}] },
  { q: "How long can you comfortably hike without resting?", icon: iconMap.clockQ,
    opts: [{icon:iconMap.walk,text:"Less than 1 hour",score:0},{icon:iconMap.boot,text:"1–2 hours",score:1},{icon:iconMap.runner,text:"3–4 hours",score:2},{icon:iconMap.lightning,text:"5+ hours no problem",score:3}] },
  { q: "What's your fitness level?", icon: iconMap.fitnessQ,
    opts: [{icon:iconMap.couch,text:"Couch potato — I'm new to this",score:0},{icon:iconMap.bike,text:"Light exercise, walks occasionally",score:1},{icon:iconMap.weight,text:"Regular exercise, decent stamina",score:2},{icon:iconMap.flame,text:"Athletic — I train regularly",score:3}] },
  { q: "Are you comfortable with steep or technical terrain?", icon: iconMap.terrainQ,
    opts: [{icon:iconMap.gentle,text:"No, I prefer flat or gentle slopes",score:0},{icon:iconMap.incline,text:"Mild inclines are fine",score:1},{icon:iconMap.steep,text:"I can handle steep sections",score:2},{icon:iconMap.scramble,text:"Technical rock scrambles? Bring it!",score:3}] },
  { q: "What type of hike appeals to you?", icon: iconMap.hikeTypeQ,
    opts: [{icon:iconMap.sunrise,text:"Scenic views, mostly easy walking",score:0},{icon:iconMap.forest,text:"Forest trails with moderate challenge",score:1},{icon:iconMap.adventure,text:"Multi-terrain adventure with a reward",score:2},{icon:iconMap.tent,text:"Overnight expedition, the full experience",score:3}] },
  { q: "How do you handle altitude and weather changes?", icon: iconMap.weatherQ,
    opts: [{icon:iconMap.fog,text:"I haven't experienced this yet",score:0},{icon:iconMap.wind,text:"Mild discomfort but manageable",score:1},{icon:iconMap.sunrise,text:"Generally fine, I adapt quickly",score:2},{icon:iconMap.shield,text:"No issues at all — I'm experienced",score:3}] }
];

const levels = {
  beginner: {icon:iconMap.seedling,name:"Beginner Explorer",desc:"You're just starting your hiking journey! We recommend gentle, scenic trails with easy terrain. Great views ahead!"},
  intermediate:{icon:iconMap.leaf,name:"Intermediate Adventurer",desc:"You have some experience and decent fitness. Moderate trails with varied terrain and rewarding summits await you."},
  advanced: {icon:iconMap.mountainBadge,name:"Experienced Mountaineer",desc:"You're no stranger to the trails. Challenging ascents, technical terrain, and multi-day expeditions are your playground."}
};

let currentQ = 0;
let answers = [];
let scores = [];

function startQuiz() {
  document.getElementById('introModal').classList.remove('active');
  document.getElementById('quizContainer').style.display = 'block';
  renderQ(0);
  // Scroll to quiz
  document.getElementById('quizContainer').scrollIntoView({ behavior: 'smooth' });
}

function renderQ(i) {
  const q = questions[i];
  const pct = ((i+1)/questions.length)*100;
  document.getElementById('quizProgress').style.width = pct+'%';
  document.getElementById('quizCounter').textContent = `${i+1} / ${questions.length}`;
  document.getElementById('quizBack').style.visibility = i>0?'visible':'hidden';
  const nextBtn = document.getElementById('quizNext');
  nextBtn.disabled = answers[i]===undefined;
  nextBtn.textContent = i===questions.length-1 ? 'See Results →' : 'Next →';

  let optsHtml = '';
  q.opts.forEach((opt, idx) => {
    const isSelected = answers[i]===idx;
    optsHtml += `
      <div class="quiz-option ${isSelected ? 'selected' : ''}" onclick="selectOpt(${idx}, ${opt.score})">
        <div class="opt-icon">${opt.icon}</div>
        <div class="opt-text">${opt.text}</div>
      </div>
    `;
  });
  document.getElementById('quizInner').innerHTML = `
    <div class="quiz-qnum">Question ${i+1} of ${questions.length}</div>
    <div class="quiz-question"><span class="question-icon">${q.icon}</span> ${q.q}</div>
    <div class="quiz-options">${optsHtml}</div>
  `;
}

function selectOpt(j, scoreVal) {
  answers[currentQ] = j;
  scores[currentQ] = scoreVal;
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

async function saveResultsToDatabase(levelKey, totalScore, maxScore) {
  try {
    const response = await fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify({level: levelKey,totalScore: totalScore,maxScore: maxScore})
    });
    const data = await response.json();
    if (data.success) showToast(data.message);
    else showToast('Error saving results: ' + data.message);
  } catch (error) {
    console.error('Error:', error);
    showToast('Could not save results to profile.');
  }
}

function showResultModal(levelKey, levelName, totalScore, maxScore) {
  const modalHtml = `
    <div class="modal-overlay" id="resultModal">
      <div class="modal-content">
        <div class="result-modal-icon">${levels[levelKey].icon}</div>
        <div class="result-level-name">${levelName}</div>
        <div class="modal-desc" style="font-size: 18px; margin-top: 10px;">${levels[levelKey].desc}</div>
        <button class="modal-btn" onclick="closeResultModalAndShowRecommendations()">See Your Recommendations →</button>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  
  const modal = document.getElementById('resultModal');
  
  // Close on click outside
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeResultModalAndShowRecommendations();
  });

  setTimeout(() => {
    if(modal) modal.classList.add('active');
  }, 50);
}

function closeResultModalAndShowRecommendations() {
  const modal = document.getElementById('resultModal');
  if(modal) modal.remove();
  displayRecommendations();
}

function displayRecommendations() {
  const total = scores.reduce((a,b)=>a+b,0);
  const max = questions.length * 3;
  const pct = total/max;
  let levelKey = pct < 0.33 ? 'beginner' : pct < 0.67 ? 'intermediate' : 'advanced';
  const lvl = levels[levelKey];
  
  let recommendations = mountains.filter(m => {
    if(levelKey==='beginner') return m.score.includes(0)||m.score.includes(1);
    if(levelKey==='intermediate') return m.score.includes(1)||m.score.includes(2);
    return m.score.includes(2)||m.score.includes(3);
  });
  
  if(recommendations.length === 0) recommendations = mountains.slice(0,3);
  
  const recHtml = recommendations.map(m => `
    <div class="rec-card" onclick="window.location='explore.php'">
      <div class="rec-card-img" style="background-image:url('${m.img}')">
        <div class="rec-card-label">${m.name}</div>
      </div>
      <div class="rec-card-body">
        <div class="rec-card-name">${m.name}</div>
        <div class="rec-card-meta"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20L12 4L20 20H4Z"/></svg> ${m.elevation} · <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ${m.time}</div>
        <div><span class="badge badge-${m.diff}">${m.diff}</span></div>
      </div>
    </div>
  `).join('');
  
  document.getElementById('quizHero').style.display = 'none';
  document.getElementById('quizContainer').style.marginTop = '24px';
  document.getElementById('quizCard').innerHTML = `
    <div class="result-hero">
      <div class="result-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Results Ready</div>
      <div class="result-title"><span class="level-icon">${lvl.icon}</span> ${lvl.name}</div>
      
    </div>
    <div class="result-body">
      <div class="result-level">
        <div class="level-icon">${lvl.icon}</div>
        <div>
          <div class="level-name">${lvl.name}</div>
          <div class="level-desc">${lvl.desc}</div>
        </div>
      </div>
      <div style="font-size:12px;font-weight:600;color:var(--sage);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;display:flex;align-items:center;gap:8px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20L12 4L20 20H4Z"/></svg> Recommended for You</div>
      <div class="rec-grid">${recHtml}</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="save-btn" onclick="saveResults('${levelKey}', ${total}, ${max})">${iconMap.bookmarkIcon} Save to Profile & Update Level</button>
        <button class="btn btn-outline btn-sm" onclick="location.reload()">Retake Quiz</button>
        <a href="explore.php" class="btn btn-primary btn-sm">Explore All →</a>
      </div>
    </div>
  `;
}

function showResults() {
  const total = scores.reduce((a,b)=>a+b,0);
  const max = questions.length * 3;
  const pct = total/max;
  let levelKey = pct < 0.33 ? 'beginner' : pct < 0.67 ? 'intermediate' : 'advanced';
  let levelName = levels[levelKey].name;
  
  // Show celebratory modal
  showResultModal(levelKey, levelName, total, max);
}

function saveResults(levelKey, totalScore, maxScore) {
  saveResultsToDatabase(levelKey, totalScore, maxScore);
}

function showToast(msg) {
  const t=document.getElementById('toast');
  t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2800);
}

// Show intro modal on page load
window.addEventListener('DOMContentLoaded', () => {
  const introModal = document.getElementById('introModal');
  introModal.classList.add('active');
  
  // Close on click outside
  introModal.addEventListener('click', (e) => {
    if (e.target === introModal) startQuiz();
  });
});
</script>
</body>
</html>