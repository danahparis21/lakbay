<?php
// hiker frontend/explore.php - LAKBAY Explore Page
require_once __DIR__ . '/../config/db.php';

session_start();

// Fetch current user details if logged in
$currentUser = null;
$userInitial = 'J';
$currentUserId = null;

if (isset($_SESSION['user_id'])) {
    $currentUserId = $_SESSION['user_id'];
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
}

// Get saved mountain IDs for current user
$savedMountainIds = [];
if ($currentUserId) {
    $stmt = $pdo->prepare("SELECT mountain_id FROM saved_mountains WHERE user_id = ?");
    $stmt->execute([$currentUserId]);
    $savedMountainIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function safeJsonDecode($value, $default = []) {
    if (empty($value)) return $default;
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

function getUserInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $part) if (!empty($part)) $initials .= strtoupper(substr($part, 0, 1));
    return substr($initials, 0, 2);
}

// --- Mountains ---
// Check if mountains table exists and has data
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM mountains");
    $mountainCount = $stmt->fetchColumn();
    
    if ($mountainCount == 0) {
        // Insert sample mountains if none exist
        $pdo->exec("
            INSERT INTO mountains (name, description, rating, location, image, jumpOff, duration, fee, elevation, crowdLevel, weatherAdvisory, peakTimes, rules, envReminders, hazards, status, difficulty) VALUES
            ('Mt. Batulao', 'Known for its iconic rolling hills and stunning panoramic views of Taal Volcano. Perfect for beginners and intermediate hikers.', 4.7, 'Nasugbu, Batangas', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=80', 'Barangay Evercrest, Nasugbu', '4-5 hours', 500, '811 MASL', 'High', 'Clear skies expected. Temperature: 24-28°C.', 'Weekends 6AM-9AM', '[\"Register at barangay hall\",\"No littering\"]', '[\"Bring water\",\"Pack out trash\"]', '[\"Slippery when wet\"]', 'Open', 'Easy'),
            ('Mt. Talamitam', 'A gentle peak with scenic grassland summit. Great for beginners and family hikes.', 4.5, 'Nasugbu, Batangas', 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=80', 'Barangay Kaysuyo, Nasugbu', '3-4 hours', 500, '630 MASL', 'Medium', 'Sunny with scattered clouds', 'November to May is peak season', '[\"Register at jump-off\"]', '[\"Pack your trash\"]', '[\"Steep sections\"]', 'Open', 'Moderate')
        ");
    }
} catch (PDOException $e) {
    // Table might not exist - create it or handle gracefully
}

$stmt = $pdo->query("SELECT * FROM mountains WHERE status = 'Open' ORDER BY rating DESC");
$dbMountains = $stmt->fetchAll();

// If still no mountains, use sample data
if (empty($dbMountains)) {
    $dbMountains = [
        [
            'id' => 1,
            'name' => 'Mt. Batulao',
            'description' => 'Known for its iconic rolling hills and stunning panoramic views of Taal Volcano. Perfect for beginners and intermediate hikers.',
            'rating' => 4.7,
            'location' => 'Nasugbu, Batangas',
            'elevation' => '811 MASL',
            'duration' => '4-5 hours',
            'difficulty' => 'Easy',
            'crowdLevel' => 'High',
            'image' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=80',
            'fee' => 500,
            'jumpOff' => 'Barangay Evercrest, Nasugbu',
            'rules' => '["Register at barangay hall","No littering"]',
            'envReminders' => '["Bring water","Pack out trash"]',
            'hazards' => '["Slippery when wet"]',
            'weatherAdvisory' => 'Clear skies expected',
            'peakTimes' => 'Weekends 6AM-9AM'
        ],
        [
            'id' => 2,
            'name' => 'Mt. Talamitam',
            'description' => 'A gentle peak with scenic grassland summit. Great for beginners and family hikes.',
            'rating' => 4.5,
            'location' => 'Nasugbu, Batangas',
            'elevation' => '630 MASL',
            'duration' => '3-4 hours',
            'difficulty' => 'Moderate',
            'crowdLevel' => 'Medium',
            'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=80',
            'fee' => 500,
            'jumpOff' => 'Barangay Kaysuyo, Nasugbu',
            'rules' => '["Register at jump-off"]',
            'envReminders' => '["Pack your trash"]',
            'hazards' => '["Steep sections"]',
            'weatherAdvisory' => 'Sunny with scattered clouds',
            'peakTimes' => 'November to May is peak season'
        ]
    ];
}

$stmt = $pdo->query("
    SELECT r.*, u.name as user_name 
    FROM reviews r JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved' ORDER BY r.created_at DESC
");
$dbReviews = $stmt->fetchAll();

$reviewsByMountain = [];
foreach ($dbReviews as $review) {
    $mid = $review['mountain_id'];
    $media = [];
    if (!empty($review['media'])) {
        $decoded = json_decode($review['media'], true);
        if (is_array($decoded)) $media = $decoded;
    }
    $reviewsByMountain[$mid][] = [
        'id'       => $review['id'],
        'author'   => $review['user_name'],
        'initials' => getUserInitials($review['user_name']),
        'stars'    => intval($review['rating']),
        'date'     => date('M d, Y', strtotime($review['created_at'])),
        'text'     => $review['comment'],
        'media'    => $media,
    ];
}

$mountains = [];
foreach ($dbMountains as $dbMtn) {
    $rules       = safeJsonDecode($dbMtn['rules']);
    $envRem      = safeJsonDecode($dbMtn['envReminders']);
    $hazards     = safeJsonDecode($dbMtn['hazards']);
    $tips        = array_merge($rules, $envRem);
    $advisories  = $hazards;
    if (!empty($dbMtn['weatherAdvisory'])) $advisories[] = $dbMtn['weatherAdvisory'];

    $mountainReviews = $reviewsByMountain[$dbMtn['id']] ?? [];
    $reviewsCount    = count($mountainReviews);
    $avgRating       = $reviewsCount > 0
        ? round(array_sum(array_column($mountainReviews, 'stars')) / $reviewsCount, 1)
        : floatval($dbMtn['rating']);

    if (empty($mountainReviews)) {
        $mountainReviews = [[
            'author' => 'Mountain Explorer', 'initials' => 'ME',
            'stars' => 4, 'date' => date('M d, Y'),
            'text' => $dbMtn['description'], 'media' => []
        ]];
        $reviewsCount = 1;
    }

    $mountains[] = [
        'id'           => $dbMtn['id'],
        'name'         => $dbMtn['name'],
        'location'     => $dbMtn['location'],
        'elevation'    => $dbMtn['elevation'],
        'time'         => $dbMtn['duration'],
        'difficulty'   => strtolower($dbMtn['difficulty']),
        'rating'       => $avgRating,
        'reviewsCount' => $reviewsCount,
        'crowd'        => isset($dbMtn['crowdLevel']) ? (strtolower($dbMtn['crowdLevel']) === 'high' ? 'high' : (strtolower($dbMtn['crowdLevel']) === 'low' ? 'low' : 'med')) : 'med',
        'image'        => $dbMtn['image'],
        'desc'         => $dbMtn['description'],
        'fees'         => "Registration Fee: ₱" . number_format($dbMtn['fee'], 2) . "<br>Jump-off: " . ($dbMtn['jumpOff'] ?? 'N/A'),
        'tips'         => $tips,
        'advisories'   => $advisories,
        'peakTimes'    => $dbMtn['peakTimes'] ?? '',
        'reviewsList'  => $mountainReviews,
    ];
}

// --- Guide reviews ---
$stmt = $pdo->query("
    SELECT gr.*, u.name as user_name 
    FROM guide_reviews gr JOIN users u ON gr.user_id = u.id
    WHERE gr.status = 'approved' ORDER BY gr.created_at DESC
");
$dbGuideReviews = $stmt->fetchAll();
$guideReviewsByGuide = [];
foreach ($dbGuideReviews as $rev) {
    $guideReviewsByGuide[$rev['guide_id']][] = [
        'id'         => $rev['id'],
        'author'     => $rev['user_name'],
        'initials'   => getUserInitials($rev['user_name']),
        'rating'     => intval($rev['rating']),
        'date'       => date('M d, Y', strtotime($rev['created_at'])),
        'comment'    => $rev['comment'],
        'isVerified' => boolval($rev['is_verified_purchase']),
    ];
}

// --- Guides ---
$stmt = $pdo->query("
    SELECT g.*, u.name as guide_name, u.avatar, u.phone, u.email, u.hiking_level, u.home_region
    FROM guides g JOIN users u ON g.user_id = u.id WHERE g.is_available = 1
");
$dbGuides = $stmt->fetchAll();

$guides = [];
foreach ($dbGuides as $dbGuide) {
    $stmt2 = $pdo->prepare("
        SELECT m.id, m.name, m.difficulty, m.location 
        FROM guide_mountains gm JOIN mountains m ON gm.mountain_id = m.id 
        WHERE gm.guide_id = ?
    ");
    $stmt2->execute([$dbGuide['id']]);
    $guideMountains = $stmt2->fetchAll();

    $guideRevs  = $guideReviewsByGuide[$dbGuide['id']] ?? [];
    $avgRating  = !empty($guideRevs)
        ? round(array_sum(array_column($guideRevs, 'rating')) / count($guideRevs), 1)
        : floatval($dbGuide['rating']);

    $guides[] = [
        'id'               => $dbGuide['id'],
        'user_id'          => $dbGuide['user_id'],
        'name'             => $dbGuide['guide_name'],
        'initials'         => getUserInitials($dbGuide['guide_name']),
        'avatar'           => $dbGuide['avatar'] ?? null,
        'email'            => $dbGuide['email'] ?? null,
        'phone'            => $dbGuide['phone'] ?? null,
        'hiking_level'     => $dbGuide['hiking_level'] ?? 'Expert',
        'home_region'      => $dbGuide['home_region'] ?? 'Philippines',
        'specialization'   => $dbGuide['specialization'],
        'years_experience' => intval($dbGuide['years_experience']),
        'rating'           => $avgRating,
        'total_trips'      => intval($dbGuide['total_trips']),
        'bio'              => $dbGuide['bio'],
        'is_available'     => boolval($dbGuide['is_available']),
        'mountains'        => $guideMountains,
        'reviews'          => $guideRevs,
        'reviews_count'    => count($guideRevs),
    ];
}

if (empty($guides)) {
    $guides = [
        ['id'=>1,'user_id'=>3,'name'=>'Maria Guide','initials'=>'MG','avatar'=>null,'email'=>'guide@lakbay.com','phone'=>null,'hiking_level'=>'Expert','home_region'=>'Batangas','specialization'=>'Mountain Trekking','years_experience'=>5,'rating'=>4.8,'total_trips'=>120,'bio'=>'Experienced mountain guide specializing in Mt. Batulao and surrounding peaks.','is_available'=>true,'mountains'=>[['id'=>1,'name'=>'Mt. Batulao','difficulty'=>'Easy','location'=>'Nasugbu, Batangas']],'reviews'=>[],'reviews_count'=>0],
        ['id'=>2,'user_id'=>5,'name'=>'John Dela Cruz','initials'=>'JD','avatar'=>null,'email'=>'john@lakbay.com','phone'=>'+639123456789','hiking_level'=>'Expert','home_region'=>'Cavite','specialization'=>'Rock Climbing','years_experience'=>3,'rating'=>4.5,'total_trips'=>45,'bio'=>'Certified rock climbing instructor focused on safety and technique.','is_available'=>true,'mountains'=>[['id'=>2,'name'=>'Mt. Talamitam','difficulty'=>'Moderate','location'=>'Nasugbu, Batangas']],'reviews'=>[],'reviews_count'=>0],
    ];
}

$featuredMountain = !empty($mountains) ? $mountains[0] : null;
$mountainsJson    = json_encode($mountains);
$guidesJson       = json_encode($guides);

// Handle AJAX requests for saving/unsaving mountains
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login to save mountains']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $mountain_id = intval($data['mountain_id'] ?? 0);
    
    if ($action === 'save') {
        $stmt = $pdo->prepare("SELECT id FROM saved_mountains WHERE user_id = ? AND mountain_id = ?");
        $stmt->execute([$user_id, $mountain_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO saved_mountains (user_id, mountain_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $mountain_id]);
            echo json_encode(['success' => true, 'saved' => true, 'message' => 'Mountain saved to your favorites']);
        } else {
            echo json_encode(['success' => true, 'saved' => true, 'message' => 'Already saved']);
        }
    } elseif ($action === 'unsave') {
        $stmt = $pdo->prepare("DELETE FROM saved_mountains WHERE user_id = ? AND mountain_id = ?");
        $stmt->execute([$user_id, $mountain_id]);
        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Mountain removed from favorites']);
    } elseif ($action === 'check') {
        $stmt = $pdo->prepare("SELECT id FROM saved_mountains WHERE user_id = ? AND mountain_id = ?");
        $stmt->execute([$user_id, $mountain_id]);
        $isSaved = $stmt->fetch() ? true : false;
        echo json_encode(['success' => true, 'saved' => $isSaved]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY — Explore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── RESET & TOKENS ── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:      #100600;
  --ink-mid:  #2c1a0a;
  --gold:     #c6a43b;
  --cream:    #faf8f2;
  --cream-2:  #f0ece0;
  --stone:    #6b6560;
  --mist:     #e4e0d6;
  --white:    #ffffff;
  --g-easy:   #d9ead3; --t-easy:  #2a6b2a;
  --g-mod:    #ffe0b5; --t-mod:   #8a5a2a;
  --g-hard:   #ffcfc2; --t-hard:  #a23b1a;
  --nav-h:    74px;
  --r:        20px;
  --r-sm:     12px;
  --sh:       0 2px 16px rgba(16,6,0,.06);
  --sh-md:    0 6px 28px rgba(16,6,0,.10);
  --sh-lg:    0 16px 48px rgba(16,6,0,.14);
  --ease:     cubic-bezier(.22,1,.36,1);
  --side-pad: 40px;
}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--cream);color:var(--ink);overflow-x:hidden;}
body::-webkit-scrollbar{width:4px;}
body::-webkit-scrollbar-thumb{background:var(--mist);border-radius:4px;}
a{text-decoration:none;color:inherit;}
button{font-family:inherit;cursor:pointer;}
svg{display:block;flex-shrink:0;}

/* ── NAV ── */
.desktop-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
  background: rgba(250,248,243,0.88);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(16,6,0,0.06);
  height: 74px; display: flex; align-items: center;
  padding: 0 48px; gap: 40px;
  transition: var(--ease);
}
.desktop-nav:hover { background: rgba(250,248,243,0.96); backdrop-filter: blur(24px); }
.brand {
  font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700;
  color: var(--ink); text-decoration: none; display: flex; align-items: center; gap: 8px;
  transition: var(--ease);
}
.brand:hover { transform: scale(1.02); color: var(--gold); }
.brand svg { width: 32px; height: 32px; }
.tabs { display: flex; gap: 8px; flex: 1; justify-content: center; }
.tab-link {
  display: flex; align-items: center; gap: 8px; padding: 10px 24px;
  border-radius: 60px; font-size: 14px; font-weight: 600; color: var(--stone);
  text-decoration: none; transition: var(--ease);
}
.tab-link:hover { background: rgba(198,164,59,0.12); color: var(--ink); transform: translateY(-2px); }
.tab-link.active { background: #100600; color: var(--cream); box-shadow: 0 4px 12px rgba(16,6,0,0.2); }
.tab-link svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }
.user-btn {
  width: 44px; height: 44px; border-radius: 50%; background: #100600;
  color: var(--cream); font-weight: 700; font-size: 15px;
  display: flex; align-items: center; justify-content: center; text-decoration: none;
  transition: var(--ease);
  background-size: cover;
  background-position: center;
}
.user-btn:hover { transform: scale(1.05); background: var(--gold); color: var(--ink); }

/* ── MOBILE NAV ── */
.mobile-nav {
  display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
  background: rgba(250,248,243,0.96); backdrop-filter: blur(20px);
  border-top: 1px solid rgba(16,6,0,0.06);
}
.mobile-nav-inner {
  display: flex; align-items: center; justify-content: space-around;
  padding: 10px 0 max(10px, env(safe-area-inset-bottom));
}
.mob-nav-item {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 600; color: var(--stone);
  text-decoration: none; padding: 6px 12px;
  transition: var(--ease);
}
.mob-nav-item.active { color: #100600; transform: translateY(-2px); }
.mob-nav-item svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 1.8; fill: none; }
.mob-nav-item .avatar-small {
  width: 22px; height: 22px; border-radius: 50%; background-size: cover; background-position: center;
}
.mob-nav-item.quiz-center {
  width: 56px; height: 56px; border-radius: 50%; background: #100600;
  color: var(--cream); padding: 0; display: flex; align-items: center; justify-content: center;
  margin-top: -20px; box-shadow: 0 4px 16px rgba(26,46,26,0.3);
}

@media(max-width:768px){
  .desktop-nav{display:none;}
  .mobile-nav{display:block;}
  :root{--side-pad:18px; --nav-h: 0px;}
}

/* ── HERO ── */
.hero{
  margin-top: var(--nav-h);
  position:relative;height:420px;
  background:linear-gradient(160deg,#100600 0%,#2c1a0a 60%,#3d2b1a 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:0 var(--side-pad);overflow: visible;
}
.hero-bg{
  position:absolute;inset:0;
  background:url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1600&q=80') center/cover;
  opacity:.25;
}
.hero-vignette{
  position:absolute;inset:0;
  background:linear-gradient(to bottom,transparent 40%,rgba(250,248,242,1) 100%);
}
.hero-inner{position:relative;z-index:2;text-align:center;width:100%;max-width:560px;}
.hero-eyebrow{
  font-family:'DM Mono',monospace;
  font-size:10px;font-weight:500;letter-spacing:3px;text-transform:uppercase;
  color:var(--gold);margin-bottom:12px;
}
.hero-title{
  font-family:'Playfair Display',serif;
  font-size:clamp(26px,5vw,42px);font-weight:700;
  color:var(--white);line-height:1.15;margin-bottom:28px;
}

/* ── SEARCH ── */
.search-wrap{position:relative;width:100%;max-width:480px;margin:0 auto;}
.search-bar{
  display:flex;align-items:center;gap:10px;
  background:rgba(255,255,255,.97);border-radius:60px;
  padding:6px 6px 6px 18px;
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  transition:.2s;
}
.search-bar:focus-within{box-shadow:0 8px 40px rgba(0,0,0,.3),0 0 0 3px rgba(198,164,59,.3);}
.search-bar-icon{width:15px;height:15px;stroke:var(--stone);stroke-width:2;fill:none;flex-shrink:0;}
.search-bar input{
  flex:1;border:none;background:transparent;
  font-size:13.5px;font-family:'DM Sans',sans-serif;
  color:var(--ink);outline:none;
}
.search-bar input::placeholder{color:#aaa;}
.search-btn{
  background:var(--ink);color:var(--cream);border:none;
  border-radius:60px;padding:10px 20px;
  font-size:12.5px;font-weight:600;
  transition:.2s;white-space:nowrap;
}
.search-btn:hover{background:var(--ink-mid);}
.search-dropdown{
  position:absolute;top:calc(100% + 6px);left:0;right:0;
  background:var(--white);border-radius:var(--r);
  box-shadow:var(--sh-lg);border:1px solid rgba(16,6,0,.07);
  display:none;z-index:300;overflow:hidden;
}
.search-dropdown.open{display:block;}
.search-hit{
  display:flex;align-items:center;gap:12px;
  padding:10px 16px;cursor:pointer;border-bottom:1px solid rgba(16,6,0,.04);
  transition:.15s;
}
.search-hit:last-child{border-bottom:none;}
.search-hit:hover{background:var(--cream);}
.search-hit-thumb{
  width:40px;height:40px;border-radius:10px;
  background-size:cover;background-position:center;flex-shrink:0;
}
.search-hit-name{font-size:13.5px;font-weight:600;}
.search-hit-sub{font-size:11.5px;color:var(--stone);margin-top:1px;}
.search-badge{
  font-size:10.5px;font-weight:700;padding:3px 10px;border-radius:60px;
  margin-left:auto;white-space:nowrap;
}
.badge-easy{background:var(--g-easy);color:var(--t-easy);}
.badge-moderate{background:var(--g-mod);color:var(--t-mod);}
.badge-hard{background:var(--g-hard);color:var(--t-hard);}
.search-empty{padding:20px;text-align:center;font-size:13px;color:var(--stone);}

/* ── PAGE CONTENT ── */
.content{max-width:1280px;margin:0 auto;padding:36px var(--side-pad) 100px;}

/* ── SECTION HEADER ── */
.sec-hd{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:18px;gap:12px;}
.sec-eyebrow{font-family:'DM Mono',monospace;font-size:10px;font-weight:500;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:4px;}
.sec-title{font-family:'Playfair Display',serif;font-size:clamp(18px,2.5vw,23px);font-weight:700;color:var(--ink);}

/* ── QUIZ BANNER ── */
.quiz-banner{
  display:flex;align-items:center;justify-content:space-between;gap:20px;
  background:var(--ink);border-radius:var(--r);padding:24px 28px;
  margin-bottom:36px;border:1px solid rgba(198,164,59,.12);
}
.quiz-banner h3{font-family:'Playfair Display',serif;font-size:18px;color:var(--white);margin-bottom:4px;}
.quiz-banner p{font-size:12.5px;color:rgba(255,255,255,.5);line-height:1.5;max-width:340px;}
.quiz-btn{
  flex-shrink:0;display:inline-flex;align-items:center;gap:8px;
  padding:11px 22px;border-radius:60px;
  font-size:12.5px;font-weight:600;
  border:1.5px solid rgba(198,164,59,.45);
  background:rgba(198,164,59,.1);
  color:var(--gold);transition:.2s;white-space:nowrap;
}
.quiz-btn:hover{background:rgba(198,164,59,.2);border-color:var(--gold);}
.quiz-btn svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;}
@media(max-width:600px){
  .quiz-banner{flex-direction:column;text-align:center;padding:20px;}
  .quiz-banner p{max-width:100%;}
}

/* ── FEATURED CARD ── */
.featured-card{
  display:grid;grid-template-columns:300px 1fr;
  background:var(--white);border-radius:var(--r);
  overflow:hidden;box-shadow:var(--sh-md);
  margin-bottom:40px;cursor:pointer;
  border:1px solid rgba(16,6,0,.05);
  transition:.3s var(--ease);
}
.featured-card:hover{box-shadow:var(--sh-lg);transform:translateY(-3px);}
.featured-img-col{position:relative;overflow:hidden;}
.featured-img{
  width:100%;height:100%;min-height:260px;
  background-size:cover;background-position:center;
  transition:transform .5s var(--ease);
}
.featured-card:hover .featured-img{transform:scale(1.04);}
.featured-img-grad{
  position:absolute;inset:0;
  background:linear-gradient(160deg,transparent 35%,rgba(16,6,0,.72));
  display:flex;flex-direction:column;justify-content:space-between;padding:14px;
}
.featured-tag{
  align-self:flex-start;
  display:flex;align-items:center;gap:6px;
  background:rgba(0,0,0,.55);backdrop-filter:blur(8px);
  border:1px solid rgba(198,164,59,.4);
  padding:5px 12px;border-radius:60px;
  font-size:10px;font-weight:700;color:var(--gold);
  letter-spacing:1.5px;text-transform:uppercase;
}
.featured-tag svg{width:11px;height:11px;stroke:currentColor;stroke-width:2.5;fill:none;}
.featured-img-stats{display:flex;gap:6px;flex-wrap:wrap;}
.featured-img-stat{
  background:rgba(0,0,0,.5);backdrop-filter:blur(8px);
  padding:5px 10px;border-radius:8px;
}
.featured-img-stat-val{font-size:13px;font-weight:700;color:var(--gold);font-family:'DM Mono',monospace;line-height:1;}
.featured-img-stat-lbl{font-size:9px;color:rgba(255,255,255,.65);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
.featured-body{padding:24px 28px;display:flex;flex-direction:column;}
.featured-name{
  font-family:'Playfair Display',serif;
  font-size:22px;font-weight:700;color:var(--ink);
  margin-bottom:4px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.featured-pill{
  font-size:11px;font-weight:600;padding:3px 11px;border-radius:60px;
  background:rgba(198,164,59,.12);color:var(--gold);
}
.featured-loc{font-size:12px;color:var(--stone);margin-bottom:12px;display:flex;align-items:center;gap:4px;}
.featured-loc svg{width:12px;height:12px;stroke:currentColor;stroke-width:2;fill:none;}
.featured-desc{font-size:13px;color:var(--stone);line-height:1.7;margin-bottom:16px;flex:1;}
.featured-divider{height:1px;background:var(--mist);margin-bottom:16px;}
.featured-quick-row{display:flex;gap:16px;margin-bottom:16px;}
.featured-qs-val{font-size:17px;font-weight:700;color:var(--ink);font-family:'DM Mono',monospace;}
.featured-qs-lbl{font-size:10px;color:var(--stone);text-transform:uppercase;letter-spacing:.5px;margin-top:1px;}
.mini-reviews{background:var(--cream-2);border-radius:var(--r-sm);padding:14px 16px;margin-bottom:16px;overflow:hidden;position:relative;}
.mini-track{display:flex;transition:transform .4s var(--ease);}
.mini-slide{min-width:100%;}
.mini-text{font-size:12.5px;font-style:italic;line-height:1.6;color:var(--ink);margin-bottom:6px;}
.mini-author{font-size:11.5px;font-weight:600;color:var(--gold);}
.mini-dots{display:flex;gap:5px;margin-top:10px;}
.mini-dot{width:5px;height:5px;border-radius:3px;background:var(--mist);cursor:pointer;transition:.3s;}
.mini-dot.on{width:18px;background:var(--gold);}
.featured-btns{display:flex;gap:8px;}
.featured-btns button,.featured-btns a{
  flex:1;padding:11px 16px;border-radius:60px;
  font-size:12.5px;font-weight:600;cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  font-family:'DM Sans',sans-serif;transition:.2s;text-decoration:none;
}
.btn-primary-sm{background:var(--ink);color:var(--cream);border:none;}
.btn-primary-sm:hover{background:var(--ink-mid);}
.btn-ghost-sm{background:transparent;color:var(--ink);border:2px solid var(--mist);}
.btn-ghost-sm:hover{border-color:var(--ink);}
@media(max-width:860px){
  .featured-card{grid-template-columns:1fr;}
  .featured-img{min-height:200px;height:200px;}
}

/* ── GUIDES CAROUSEL ── */
.guides-section{margin-bottom:40px;}
.carousel-hd{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:16px;gap:12px;}
.carousel-arrows{display:flex;gap:6px;}
.c-arrow{
  width:34px;height:34px;border-radius:50%;
  background:var(--white);border:1.5px solid var(--mist);
  color:var(--ink);display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:.2s;
}
.c-arrow svg{width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none;}
.c-arrow:hover{background:var(--ink);color:var(--cream);border-color:var(--ink);}
@media(max-width:768px){.carousel-arrows{display:none;}}
.guides-track-outer{overflow:hidden;margin:0 -4px;padding:4px;}
.guides-track{display:flex;gap:14px;overflow-x:auto;scroll-behavior:smooth;scroll-snap-type:x mandatory;scrollbar-width:none;padding-bottom:2px;}
.guides-track::-webkit-scrollbar{display:none;}
.guide-card{
  scroll-snap-align:start;flex-shrink:0;width:180px;
  background:var(--white);border-radius:var(--r);
  padding:20px 16px;text-align:center;
  box-shadow:var(--sh);border:1px solid rgba(16,6,0,.05);
  cursor:pointer;transition:.25s var(--ease);
}
.guide-card:hover{transform:translateY(-5px);box-shadow:var(--sh-md);}
.guide-avatar{
  width:58px;height:58px;border-radius:50%;
  background:var(--ink);color:var(--cream);
  font-size:20px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 12px;transition:.25s;
  background-size:cover;background-position:center;
}
.guide-card:hover .guide-avatar{background-color:var(--gold);color:var(--ink);}
.guide-name{font-size:13px;font-weight:600;margin-bottom:3px;line-height:1.3;}
.guide-spec{font-size:11px;color:var(--stone);margin-bottom:8px;line-height:1.4;}
.guide-rating{font-size:12.5px;font-weight:700;color:var(--gold);}
.guide-trips{font-size:10.5px;color:var(--stone);margin-top:1px;}
.guide-avail{
  font-size:10px;font-weight:600;margin-top:8px;
  padding:3px 10px;border-radius:60px;display:inline-block;
}
.avail-yes{background:var(--g-easy);color:var(--t-easy);}
.avail-no{background:var(--g-mod);color:var(--t-mod);}

/* ── FILTER ROW ── */
.filter-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:20px;}
.chip{
  padding:7px 18px;border-radius:60px;font-size:12.5px;font-weight:500;
  background:var(--white);border:1.5px solid var(--mist);
  cursor:pointer;color:var(--stone);transition:.2s;
}
.chip:hover{border-color:var(--ink);color:var(--ink);}
.chip.active{background:var(--ink);color:var(--cream);border-color:var(--ink);font-weight:600;}
.filter-select{
  padding:7px 14px;border-radius:60px;font-size:12.5px;font-weight:500;
  border:1.5px solid var(--mist);background:var(--white);
  color:var(--ink);font-family:'DM Sans',sans-serif;transition:.2s;
}
.filter-select:focus{outline:none;border-color:var(--ink);}

/* ── MOUNTAIN GRID ── */
.mtn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:60px;}
@media(min-width:1100px){.mtn-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:560px){.mtn-grid{grid-template-columns:1fr;}}
.mtn-card{
  background:var(--white);border-radius:var(--r);overflow:hidden;
  box-shadow:var(--sh);border:1px solid rgba(16,6,0,.05);
  cursor:pointer;transition:.25s var(--ease);display:flex;flex-direction:column;
}
.mtn-card:hover{transform:translateY(-5px);box-shadow:var(--sh-md);}
.mtn-img-wrap{position:relative;overflow:hidden;height:190px;}
.mtn-img{width:100%;height:100%;background-size:cover;background-position:center;transition:transform .5s var(--ease);}
.mtn-card:hover .mtn-img{transform:scale(1.05);}
.mtn-img-overlay{position:absolute;inset:0;background:linear-gradient(transparent 50%,rgba(16,6,0,.55));}
.mtn-badges{position:absolute;top:10px;left:10px;display:flex;gap:5px;}
.badge{padding:4px 11px;border-radius:60px;font-size:10.5px;font-weight:700;}
.mtn-crowd{
  position:absolute;top:10px;right:10px;
  background:rgba(0,0,0,.6);backdrop-filter:blur(6px);
  padding:3px 10px;border-radius:60px;font-size:10.5px;font-weight:600;color:var(--white);
  display:flex;align-items:center;gap:4px;
}
.mtn-crowd svg{width:10px;height:10px;stroke:currentColor;stroke-width:2;fill:none;}
.mtn-body{padding:16px;flex:1;display:flex;flex-direction:column;}
.mtn-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;margin-bottom:3px;line-height:1.2;}
.mtn-loc{font-size:11.5px;color:var(--stone);margin-bottom:12px;display:flex;align-items:center;gap:4px;}
.mtn-loc svg{width:11px;height:11px;stroke:currentColor;stroke-width:2;fill:none;}
.mtn-stats{display:flex;gap:16px;padding:10px 0;border-top:1px solid var(--mist);border-bottom:1px solid var(--mist);margin-bottom:12px;}
.mtn-stat-val{font-size:14px;font-weight:700;font-family:'DM Mono',monospace;color:var(--ink);}
.mtn-stat-lbl{font-size:9.5px;color:var(--stone);text-transform:uppercase;letter-spacing:.5px;margin-top:1px;}
.mtn-footer{display:flex;align-items:center;justify-content:space-between;margin-top:auto;}
.mtn-rating{font-size:13px;font-weight:700;color:var(--gold);}
.mtn-rev-count{font-size:10.5px;color:var(--stone);margin-top:1px;}
.view-btn{
  background:var(--ink);color:var(--cream);border:none;
  padding:8px 18px;border-radius:60px;font-size:11.5px;font-weight:600;
  transition:.2s;font-family:'DM Sans',sans-serif;
}
.view-btn:hover{background:var(--gold);color:var(--ink);}
.empty-state{grid-column:1/-1;text-align:center;padding:60px;color:var(--stone);}
.empty-state h3{font-family:'Playfair Display',serif;font-size:20px;margin-bottom:6px;color:var(--ink);}

/* ── MODALS ── */
.overlay{
  display:none;position:fixed;inset:0;
  background:rgba(16,6,0,.65);backdrop-filter:blur(14px);
  z-index:500;align-items:center;justify-content:center;padding:16px;
}
.overlay.open{display:flex;}
.mtn-modal-box{
  background:var(--cream);border-radius:24px;
  width:100%;max-width:960px;
  height:min(92vh,720px);
  overflow:hidden;display:flex;flex-direction:column;
  box-shadow:0 32px 80px rgba(0,0,0,.28);
  animation:modalIn .28s var(--ease);
}
@keyframes modalIn{from{opacity:0;transform:scale(.97) translateY(12px);}to{opacity:1;transform:none;}}
.mtn-modal-hero{position:relative;height:200px;flex-shrink:0;overflow:hidden;}
.mtn-modal-hero-img{width:100%;height:100%;background-size:cover;background-position:center;}
.mtn-modal-hero-grad{
  position:absolute;inset:0;
  background:linear-gradient(transparent 20%,rgba(16,6,0,0.85));
  display:flex;flex-direction:column;justify-content:flex-end;padding:24px 32px;
}
.mtn-modal-name{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--white);}
.mtn-modal-loc{font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;display:flex;align-items:center;gap:5px;}
.mtn-modal-loc svg{width:11px;height:11px;stroke:currentColor;stroke-width:2;fill:none;}
.modal-close{
  position:absolute;top:14px;right:14px;
  width:36px;height:36px;border-radius:50%;
  background:rgba(0,0,0,.45);backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.15);color:var(--white);font-size:15px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;
}
.modal-close:hover{background:rgba(0,0,0,.75);}
.mtn-modal-stats{
  display:flex;border-bottom:1px solid var(--mist);flex-shrink:0;background:var(--white);
}
.mtn-mstat{
  flex:1;padding:13px 10px;text-align:center;
  border-right:1px solid var(--mist);
}
.mtn-mstat:last-child{border-right:none;}
.mtn-mstat-val{font-size:16px;font-weight:700;color:var(--ink);font-family:'DM Mono',monospace;}
.mtn-mstat-lbl{font-size:9.5px;color:var(--stone);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
.mtn-modal-tabs{display:flex;padding:0 20px;border-bottom:1px solid var(--mist);flex-shrink:0;background:var(--white);overflow-x:auto;}
.mtn-modal-tabs::-webkit-scrollbar{display:none;}
.mtn-mtab{
  padding:12px 18px;font-size:12.5px;font-weight:600;
  color:var(--stone);cursor:pointer;border-bottom:2px solid transparent;
  transition:.2s;white-space:nowrap;
}
.mtn-mtab:hover{color:var(--ink);}
.mtn-mtab.active{color:var(--ink);border-color:var(--ink);}
.mtn-modal-body{overflow-y:auto;flex:1;padding:20px 24px;}
.mtn-modal-body::-webkit-scrollbar{width:4px;}
.mtn-modal-body::-webkit-scrollbar-thumb{background:var(--mist);border-radius:4px;}
.tab-pane{display:none;}
.tab-pane.active{display:block;}
.modal-desc{font-size:13.5px;line-height:1.75;color:var(--stone);margin-bottom:18px;}
.fees-box{background:var(--white);border-radius:var(--r-sm);padding:16px 18px;border:1px solid var(--mist);}
.fees-title{font-family:'DM Mono',monospace;font-size:9.5px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--stone);margin-bottom:10px;}
.fees-body{font-size:13px;line-height:1.9;color:var(--ink);}
.rev-filter-bar{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px;}
.rev-chip{
  padding:5px 14px;border-radius:60px;font-size:11.5px;font-weight:600;
  background:var(--white);border:1.5px solid var(--mist);
  cursor:pointer;color:var(--stone);transition:.15s;
}
.rev-chip:hover{border-color:var(--ink);color:var(--ink);}
.rev-chip.active{background:var(--ink);color:var(--cream);border-color:var(--ink);}
.revs-list{display:flex;flex-direction:column;gap:12px;}
.rev-item{background:var(--white);border-radius:var(--r-sm);padding:15px;border:1px solid rgba(16,6,0,.05);}
.rev-hd{display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap;}
.rev-av{width:38px;height:38px;border-radius:50%;background:var(--ink);color:var(--cream);font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.rev-name{font-size:13.5px;font-weight:600;}
.rev-stars{color:var(--gold);font-size:12px;margin-top:2px;}
.rev-date{font-size:10.5px;color:var(--stone);margin-left:auto;font-family:'DM Mono',monospace;}
.rev-text{font-size:13px;line-height:1.65;color:var(--stone);}
.rev-media{display:flex;gap:7px;margin-top:10px;flex-wrap:wrap;}
.rev-thumb{width:64px;height:64px;border-radius:9px;background-size:cover;background-position:center;cursor:pointer;border:2px solid var(--mist);transition:.2s;}
.rev-thumb:hover{transform:scale(1.04);border-color:var(--gold);}
.no-revs{padding:28px;text-align:center;color:var(--stone);font-size:13px;background:var(--white);border-radius:var(--r-sm);}
.tip-item{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--mist);font-size:13px;line-height:1.6;color:var(--stone);}
.tip-item:last-child{border-bottom:none;}
.tip-icon{color:var(--gold);flex-shrink:0;margin-top:2px;}
.tip-icon svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;}
.adv-item{padding:12px 14px;border-radius:var(--r-sm);margin-bottom:8px;border-left:3px solid var(--gold);background:rgba(198,164,59,.06);font-size:13px;line-height:1.6;}
.mtn-modal-footer{
  display:flex;gap:10px;padding:14px 20px;
  border-top:1px solid var(--mist);background:var(--white);flex-shrink:0;
}
.m-action{
  flex:1;padding:12px 18px;border-radius:60px;
  font-size:13px;font-weight:600;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:7px;
  font-family:'DM Sans',sans-serif;transition:.2s;text-decoration:none;
}
.m-action svg{width:15px;height:15px;stroke:currentColor;stroke-width:2;fill:none;}
.m-save{background:transparent;color:var(--ink);border:2px solid var(--mist);}
.m-save:hover{border-color:var(--ink);background:rgba(16,6,0,.03);}
.m-book{background:var(--ink);color:var(--cream);border:none;}
.m-book:hover{background:var(--ink-mid);}
@media(max-width:560px){.mtn-modal-footer{flex-direction:column;gap:7px;}}

/* Guide Modal */
.guide-modal-box{
  background:var(--cream);border-radius:24px;
  width:100%;max-width:680px;
  height:min(90vh,680px);overflow:hidden;
  display:flex;flex-direction:column;
  box-shadow:0 32px 80px rgba(0,0,0,.28);
  animation:modalIn .28s var(--ease);
}
.guide-modal-cover{
  position:relative;height:140px;flex-shrink:0;
  background:linear-gradient(135deg,var(--ink) 0%,var(--ink-mid) 100%);
}
.guide-cover-img{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.35;}
.guide-modal-close{
  position:absolute;top:14px;right:14px;
  width:36px;height:36px;border-radius:50%;
  background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.15);
  color:var(--white);font-size:14px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;
  z-index:10;
}
.guide-modal-close:hover{background:rgba(0,0,0,.7);}
.guide-avatar-wrap{
  position:absolute;
  top:100px;
  left:32px;
  z-index:20;
}
.guide-modal-av{
  width:90px;height:90px;border-radius:50%;
  background:var(--ink);color:var(--cream);
  font-size:32px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  border:5px solid var(--cream);box-shadow:var(--sh-lg);
  background-size:cover;background-position:center;
}
.guide-modal-body{overflow-y:auto;flex:1;padding:52px 28px 24px;}
.guide-modal-body::-webkit-scrollbar{width:4px;}
.guide-modal-body::-webkit-scrollbar-thumb{background:var(--mist);border-radius:4px;}
.guide-modal-name{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;margin-bottom:8px;}
.guide-pills{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:14px;}
.gpill{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 12px;border-radius:60px;font-size:10.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.4px;
}
.gpill svg{width:11px;height:11px;stroke:currentColor;stroke-width:2;fill:none;}
.gpill-spec{background:rgba(198,164,59,.1);color:var(--gold);border:1px solid rgba(198,164,59,.25);}
.gpill-exp{background:rgba(16,6,0,.06);color:var(--ink);border:1px solid rgba(16,6,0,.1);}
.gpill-avail{background:var(--g-easy);color:var(--t-easy);}
.gpill-busy{background:var(--g-mod);color:var(--t-mod);}
.guide-rating-row{display:flex;align-items:center;gap:8px;margin-bottom:18px;}
.guide-stars-big{color:var(--gold);font-size:15px;}
.guide-rating-val{font-size:20px;font-weight:700;}
.guide-rating-ct{font-size:12px;color:var(--stone);}
.guide-stats-row{display:flex;gap:10px;margin-bottom:20px;}
.guide-stat{
  flex:1;background:var(--white);border-radius:var(--r-sm);padding:14px;
  text-align:center;border:1px solid rgba(16,6,0,.05);transition:.2s;
}
.guide-stat:hover{transform:translateY(-2px);box-shadow:var(--sh);}
.guide-stat-val{font-size:20px;font-weight:700;color:var(--gold);font-family:'DM Mono',monospace;}
.guide-stat-lbl{font-size:9.5px;color:var(--stone);text-transform:uppercase;letter-spacing:1px;margin-top:3px;}
.guide-section{margin-bottom:20px;}
.guide-section-title{
  font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;
  color:var(--stone);margin-bottom:10px;
  display:flex;align-items:center;gap:8px;
  font-family:'DM Mono',monospace;
}
.guide-section-title::after{content:'';flex:1;height:1px;background:var(--mist);}
.guide-bio{font-size:13px;line-height:1.75;color:var(--stone);}
.guide-contact-grid{display:flex;flex-direction:column;gap:7px;}
.contact-row{
  display:flex;align-items:center;gap:10px;
  font-size:13px;color:var(--ink);
  padding:10px 14px;background:var(--white);
  border-radius:var(--r-sm);border:1px solid rgba(16,6,0,.05);transition:.2s;
}
.contact-row:hover{border-color:var(--gold);}
.contact-row svg{width:14px;height:14px;stroke:var(--gold);stroke-width:2;fill:none;flex-shrink:0;}
.mtn-tags{display:flex;gap:7px;flex-wrap:wrap;}
.mtn-tag-pill{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 14px;border-radius:60px;
  background:var(--white);border:1px solid rgba(16,6,0,.08);
  font-size:12px;font-weight:600;cursor:pointer;transition:.2s;
}
.mtn-tag-pill svg{width:12px;height:12px;stroke:currentColor;stroke-width:2;fill:none;color:var(--stone);}
.mtn-tag-pill:hover{border-color:var(--gold);color:var(--gold);}
.guide-modal-tabs{display:flex;gap:0;border-bottom:1px solid var(--mist);margin-bottom:16px;overflow-x:auto;}
.guide-modal-tabs::-webkit-scrollbar{display:none;}
.g-mtab{padding:10px 18px;font-size:12.5px;font-weight:600;color:var(--stone);cursor:pointer;border-bottom:2px solid transparent;transition:.2s;white-space:nowrap;}
.g-mtab:hover{color:var(--ink);}
.g-mtab.active{color:var(--ink);border-color:var(--ink);}
.g-tab-pane{display:none;}
.g-tab-pane.active{display:block;}
.g-revs-list{display:flex;flex-direction:column;gap:10px;}
.g-rev-item{background:var(--white);border-radius:var(--r-sm);padding:14px;border:1px solid rgba(16,6,0,.05);}
.g-rev-hd{display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;}
.g-rev-av{width:36px;height:36px;border-radius:50%;background:var(--ink);color:var(--cream);font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.g-rev-name{font-size:13px;font-weight:600;}
.g-rev-stars{color:var(--gold);font-size:11px;margin-top:2px;}
.g-rev-date{font-size:10.5px;color:var(--stone);margin-left:auto;font-family:'DM Mono',monospace;}
.g-rev-comment{font-size:12.5px;line-height:1.65;color:var(--stone);font-style:italic;}
.guide-details-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.gd-item{background:var(--white);border-radius:var(--r-sm);padding:13px;border:1px solid rgba(16,6,0,.05);}
.gd-lbl{font-size:9.5px;color:var(--stone);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;font-family:'DM Mono',monospace;}
.gd-val{font-size:13px;font-weight:600;color:var(--ink);}
.guide-modal-footer{
  display:flex;gap:8px;padding:12px 20px;
  border-top:1px solid var(--mist);background:var(--white);flex-shrink:0;
}
.g-action{
  flex:1;padding:11px 16px;border-radius:60px;
  font-size:12.5px;font-weight:600;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;
  font-family:'DM Sans',sans-serif;transition:.2s;
}
.g-action svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;}
.g-msg{background:transparent;color:var(--ink);border:2px solid var(--mist);}
.g-msg:hover{border-color:var(--ink);background:rgba(16,6,0,.03);}
.g-book{background:var(--ink);color:var(--cream);border:none;}
.g-book:hover{background:var(--ink-mid);}
.toast{
  position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
  background:var(--ink);color:var(--cream);
  padding:11px 26px;border-radius:60px;font-size:13px;font-weight:500;
  opacity:0;transition:opacity .3s;pointer-events:none;z-index:9999;
  white-space:nowrap;box-shadow:0 8px 24px rgba(0,0,0,.22);
}
.toast.show{opacity:1;}
.icon{display:inline-flex;align-items:center;justify-content:center;}
</style>
</head>
<body>

<!-- ── DESKTOP NAV ── -->
<nav class="desktop-nav">
  <a href="../index.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link active">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore
    </a>
    <a href="bookings.php" class="tab-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings
    </a>
    <a href="quiz.php" class="tab-link">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz
    </a>
    <a href="messages.php" class="tab-link">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages
    </a>
  </div>
  <a href="hikerProfile.php" class="user-btn" style="<?= $currentUser && $currentUser['avatar'] ? 'background-image: url(' . htmlspecialchars($currentUser['avatar']) . '); background-size: cover; background-position: center;' : '' ?>">
    <?= $currentUser && !$currentUser['avatar'] ? htmlspecialchars($userInitial) : ($currentUser && $currentUser['avatar'] ? '' : 'J') ?>
  </a>
</nav>

<!-- ── MOBILE NAV ── -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item active"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span></a>
    <a href="bookings.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span></a>
    <a href="quiz.php" class="mob-nav-item quiz-center"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></a>
    <a href="messages.php" class="mob-nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="hikerProfile.php" class="mob-nav-item">
      <?php if ($currentUser && $currentUser['avatar']): ?>
        <div class="avatar-small" style="background-image: url('<?= htmlspecialchars($currentUser['avatar']) ?>');"></div>
      <?php else: ?>
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <?php endif; ?>
      <span>Profile</span>
    </a>
  </div>
</nav>

<!-- ── HERO ── -->
<div class="hero">
  <div class="hero-bg"></div>
  <div class="hero-vignette"></div>
  <div class="hero-inner">
    <div class="hero-eyebrow">Wilderness Intelligence</div>
    <h1 class="hero-title">Find Your Next Peak</h1>
    <div class="search-wrap">
      <div class="search-bar">
        <svg class="search-bar-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" id="searchInput" placeholder="Search mountains, locations…" autocomplete="off">
        <button class="search-btn" onclick="doSearch()">Search</button>
      </div>
      <div class="search-dropdown" id="searchDropdown"></div>
    </div>
  </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="content">

  <!-- Quiz Banner -->
  <div class="quiz-banner">
    <div>
      <h3>Not sure where to start?</h3>
      <p>Take our 2-minute quiz and get mountain recommendations matched to your fitness level.</p>
    </div>
    <a href="quiz.php" class="quiz-btn">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Take the Quiz
    </a>
  </div>

  <!-- Featured Mountain -->
  <?php if ($featuredMountain && !empty($mountains)): ?>
  <div class="sec-hd">
    <div><div class="sec-eyebrow">Most Visited</div><div class="sec-title">Top Pick This Season</div></div>
  </div>
  <div class="featured-card" id="featuredCard">
    <div class="featured-img-col">
      <div class="featured-img" style="background-image:url('<?= htmlspecialchars($featuredMountain['image']) ?>')"></div>
      <div class="featured-img-grad">
        <div class="featured-tag">
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Most Visited
        </div>
        <div class="featured-img-stats">
          <div class="featured-img-stat">
            <div class="featured-img-stat-val"><?= htmlspecialchars($featuredMountain['elevation']) ?></div>
            <div class="featured-img-stat-lbl">Elevation</div>
          </div>
          <div class="featured-img-stat">
            <div class="featured-img-stat-val"><?= htmlspecialchars($featuredMountain['time']) ?></div>
            <div class="featured-img-stat-lbl">Duration</div>
          </div>
          <div class="featured-img-stat">
            <div class="featured-img-stat-val">★ <?= $featuredMountain['rating'] ?></div>
            <div class="featured-img-stat-lbl">Rating</div>
          </div>
        </div>
      </div>
    </div>
    <div class="featured-body">
      <div class="featured-name">
        <?= htmlspecialchars($featuredMountain['name']) ?>
        <span class="featured-pill">★ <?= $featuredMountain['rating'] ?></span>
      </div>
      <div class="featured-loc">
        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <?= htmlspecialchars($featuredMountain['location']) ?>
      </div>
      <p class="featured-desc"><?= htmlspecialchars(substr($featuredMountain['desc'], 0, 220)) ?>…</p>
      <div class="featured-divider"></div>
      <div class="featured-quick-row">
        <div><div class="featured-qs-val"><?= $featuredMountain['reviewsCount'] ?></div><div class="featured-qs-lbl">Reviews</div></div>
        <?php if (!empty($featuredMountain['peakTimes'])): ?>
        <div><div class="featured-qs-val" style="font-size:12px;font-family:'DM Sans',sans-serif;"><?= htmlspecialchars(substr($featuredMountain['peakTimes'],0,18)) ?></div><div class="featured-qs-lbl">Peak Times</div></div>
        <?php endif; ?>
      </div>
      <div class="mini-reviews">
        <div class="mini-track" id="miniTrack">
          <?php foreach (array_slice($featuredMountain['reviewsList'],0,3) as $rev): ?>
          <div class="mini-slide">
            <div class="mini-text">"<?= htmlspecialchars(substr($rev['text'],0,120)) ?>…"</div>
            <div class="mini-author">— <?= htmlspecialchars($rev['author']) ?> <?= str_repeat('★',$rev['stars']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mini-dots" id="miniDots"></div>
      </div>
      <div class="featured-btns">
        <button class="btn-primary-sm" onclick="openMtnModal(mountains[0])">View Details</button>
        <button class="btn-ghost-sm" onclick="scrollToAll()">Browse All →</button>
      </div>
    </div>
  </div>
  <?php elseif (empty($mountains)): ?>
  <div class="quiz-banner" style="background:var(--gold);">
    <div>
      <h3 style="color:var(--ink);">No mountains found</h3>
      <p style="color:var(--ink);">Please check your database connection or add some mountains to get started.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Guides Carousel -->
  <div class="guides-section">
    <div class="carousel-hd">
      <div><div class="sec-eyebrow">Expert Guides</div><div class="sec-title">Tour Guides</div></div>
      <div class="carousel-arrows">
        <button class="c-arrow" onclick="scrollGuides(-1)" title="Previous">
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button class="c-arrow" onclick="scrollGuides(1)" title="Next">
          <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>
    <div class="guides-track-outer">
      <div class="guides-track" id="guidesTrack"></div>
    </div>
  </div>

  <!-- All Mountains -->
  <div id="allMtnSection">
    <div class="sec-hd">
      <div><div class="sec-eyebrow">Directory</div><div class="sec-title">All Mountains</div></div>
    </div>
    <div class="filter-row">
      <div class="chip active" onclick="filterBy('all',this)">All</div>
      <div class="chip" onclick="filterBy('easy',this)">Easy</div>
      <div class="chip" onclick="filterBy('moderate',this)">Moderate</div>
      <div class="chip" onclick="filterBy('hard',this)">Hard</div>
      <select class="filter-select" onchange="sortBy(this.value)">
        <option value="">Sort by</option>
        <option value="rating">Rating</option>
        <option value="elevation">Elevation</option>
        <option value="time">Duration</option>
      </select>
    </div>
    <div class="mtn-grid" id="mtnGrid"></div>
  </div>
</div>

<!-- MOUNTAIN DETAIL MODAL -->
<div class="overlay" id="mtnOverlay">
  <div class="mtn-modal-box">
    <div class="mtn-modal-hero">
      <div class="mtn-modal-hero-img" id="mHeroImg"></div>
      <div class="mtn-modal-hero-grad">
        <div>
          <div class="mtn-modal-name" id="mName"></div>
          <div class="mtn-modal-loc">
            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span id="mLoc"></span>
          </div>
        </div>
      </div>
      <button class="modal-close" onclick="closeMtnModal()">✕</button>
    </div>

    <div class="mtn-modal-stats" id="mStats"></div>

    <div class="mtn-modal-tabs">
      <div class="mtn-mtab active" onclick="mTab('overview',this)">Overview</div>
      <div class="mtn-mtab" onclick="mTab('reviews',this)">Reviews <span id="mRevBadge"></span></div>
      <div class="mtn-mtab" onclick="mTab('tips',this)">Tips</div>
      <div class="mtn-mtab" onclick="mTab('advisories',this)">Advisories</div>
    </div>

    <div class="mtn-modal-body">
      <div class="tab-pane active" id="tp-overview">
        <p class="modal-desc" id="mDesc"></p>
        <div class="fees-box">
          <div class="fees-title">Fees &amp; Requirements</div>
          <div class="fees-body" id="mFees"></div>
        </div>
      </div>
      <div class="tab-pane" id="tp-reviews">
        <div class="rev-filter-bar" id="mRevFilter"></div>
        <div class="revs-list" id="mRevList"></div>
      </div>
      <div class="tab-pane" id="tp-tips"><div id="mTips"></div></div>
      <div class="tab-pane" id="tp-advisories"><div id="mAdv"></div></div>
    </div>

    <div class="mtn-modal-footer">
      <button class="m-action m-save" onclick="saveMtn()">
        <svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Save
      </button>
      <button class="m-action m-book" onclick="bookMtn()">
        <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        Book a Tour
      </button>
    </div>
  </div>
</div>

<!-- GUIDE DETAIL MODAL -->
<div class="overlay" id="guideOverlay">
  <div class="guide-modal-box">
    <div class="guide-modal-cover">
      <div class="guide-cover-img" id="gCoverImg"></div>
      <button class="guide-modal-close" onclick="closeGuideModal()">✕</button>
      <div class="guide-avatar-wrap">
        <div class="guide-modal-av" id="gModalAv"></div>
      </div>
    </div>

    <div class="guide-modal-body">
      <div class="guide-modal-name" id="gModalName"></div>
      <div class="guide-pills" id="gPills"></div>
      <div class="guide-rating-row">
        <div class="guide-stars-big" id="gStars"></div>
        <div class="guide-rating-val" id="gRatingVal"></div>
        <div class="guide-rating-ct" id="gRatingCt"></div>
      </div>

      <div class="guide-stats-row">
        <div class="guide-stat"><div class="guide-stat-val" id="gTrips">—</div><div class="guide-stat-lbl">Trips Led</div></div>
        <div class="guide-stat"><div class="guide-stat-val" id="gMtnCount">—</div><div class="guide-stat-lbl">Mountains</div></div>
        <div class="guide-stat"><div class="guide-stat-val">100%</div><div class="guide-stat-lbl">Safety</div></div>
      </div>

      <div class="guide-section">
        <div class="guide-section-title">About</div>
        <p class="guide-bio" id="gBio"></p>
      </div>

      <div class="guide-section">
        <div class="guide-section-title">Contact</div>
        <div class="guide-contact-grid" id="gContact"></div>
      </div>

      <div class="guide-section">
        <div class="guide-section-title">Mountains I Guide</div>
        <div class="mtn-tags" id="gMtns"></div>
      </div>

      <div class="guide-modal-tabs">
        <div class="g-mtab active" onclick="gTab('reviews',this)">Reviews (<span id="gRevCt">0</span>)</div>
        <div class="g-mtab" onclick="gTab('details',this)">Details</div>
      </div>

      <div class="g-tab-pane active" id="gtp-reviews">
        <div class="g-revs-list" id="gRevList"></div>
      </div>
      <div class="g-tab-pane" id="gtp-details">
        <div class="guide-details-grid" id="gDetails"></div>
      </div>
    </div>

    <div class="guide-modal-footer">
      <button class="g-action g-msg" onclick="msgGuide()">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Message
      </button>
      <button class="g-action g-book" onclick="bookGuide()">
        <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        Book This Guide
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── DATA from PHP ──
const mountains = <?= json_encode($mountains) ?>;
const guides    = <?= json_encode($guides) ?>;
const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
const currentUser = <?= json_encode($currentUser) ?>;

// Debug log to see if mountains are loading
console.log('Mountains loaded:', mountains.length);
console.log('Guides loaded:', guides.length);

// ── STATE ──
let activeMtn    = null;
let activeGuide  = null;
let activeFilter = 'all';
let activeSort   = '';
let activeRevF   = 'all';
let miniIdx      = 0;
let miniTimer    = null;

// ── UTILS ──
function esc(s){ if(!s) return ''; return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function showToast(msg){ const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),2600); }
function scrollToAll(){ document.getElementById('allMtnSection').scrollIntoView({behavior:'smooth',block:'start'}); }

// ── SEARCH ──
const searchInput    = document.getElementById('searchInput');
const searchDropdown = document.getElementById('searchDropdown');

searchInput.addEventListener('input', ()=>{
  if (!mountains || mountains.length === 0) return;
  const q = searchInput.value.trim().toLowerCase();
  if(!q){ searchDropdown.classList.remove('open'); return; }
  const hits = mountains.filter(m=>m.name.toLowerCase().includes(q)||m.location.toLowerCase().includes(q));
  if(!hits.length){
    searchDropdown.innerHTML=`<div class="search-empty">No mountains found for "<strong>${esc(q)}</strong>"</div>`;
  } else {
    const dLabel={easy:'Easy',moderate:'Moderate',hard:'Hard'};
    searchDropdown.innerHTML=hits.map(m=>`
      <div class="search-hit" onclick="pickSearch(${m.id})">
        <div class="search-hit-thumb" style="background-image:url('${m.image}')"></div>
        <div><div class="search-hit-name">${esc(m.name)}</div><div class="search-hit-sub">${esc(m.location)} · ★ ${m.rating}</div></div>
        <span class="search-badge badge-${m.difficulty}">${dLabel[m.difficulty]||m.difficulty}</span>
      </div>`).join('');
  }
  searchDropdown.classList.add('open');
});

function pickSearch(id){
  const m=mountains.find(x=>x.id===id);
  if(m) {
    searchDropdown.classList.remove('open');
    searchInput.value=m.name;
    openMtnModal(m);
  }
}
function doSearch(){
  if (!mountains || mountains.length === 0) return;
  const q=searchInput.value.trim().toLowerCase();
  searchDropdown.classList.remove('open');
  if(!q){ renderGrid(); return; }
  const hits=mountains.filter(m=>m.name.toLowerCase().includes(q)||m.location.toLowerCase().includes(q));
  renderGrid(hits);
  scrollToAll();
}
searchInput.addEventListener('keydown', e=>{ if(e.key==='Enter') doSearch(); });
document.addEventListener('click', e=>{ if(!e.target.closest('.search-wrap')) searchDropdown.classList.remove('open'); });

// ── MINI REVIEW CAROUSEL ──
function initMiniCarousel(){
  const slides=document.querySelectorAll('.mini-slide');
  const dots=document.getElementById('miniDots');
  if(!slides.length||!dots) return;
  dots.innerHTML=[...slides].map((_,i)=>`<div class="mini-dot${i===0?' on':''}" onclick="jumpMini(${i})"></div>`).join('');
  clearInterval(miniTimer);
  miniIdx=0;
  if(slides.length>1) miniTimer=setInterval(()=>{ miniIdx=(miniIdx+1)%slides.length; updateMini(slides.length); },4500);
}
function jumpMini(i){ miniIdx=i; updateMini(document.querySelectorAll('.mini-slide').length); }
function updateMini(total){
  const tr=document.getElementById('miniTrack');
  if(tr) tr.style.transform=`translateX(-${miniIdx*100}%)`;
  document.querySelectorAll('.mini-dot').forEach((d,i)=>d.classList.toggle('on',i===miniIdx));
}

// ── GUIDES ──
function renderGuides(){
  const track=document.getElementById('guidesTrack');
  if(!track) return;
  if(!guides || guides.length === 0){ track.innerHTML='<div style="padding:32px;color:var(--stone);">No guides available.</div>'; return; }
  track.innerHTML=guides.map((g,i)=>`
    <div class="guide-card" onclick="openGuideModal(guides[${i}])">
      <div class="guide-avatar" style="${g.avatar?`background-image:url('${g.avatar}');background-size:cover;`:`background-color:${['#1a4d3a','#2c6e4f','#8a5a2a','#5a3a1a'][(g.name?.length || 0)%4]};`}">${g.avatar?'':esc(g.initials)}</div>
      <div class="guide-name">${esc(g.name)}</div>
      <div class="guide-spec">${esc(g.specialization)}</div>
      <div class="guide-rating">★ ${g.rating}</div>
      <div class="guide-trips">${g.total_trips} trips</div>
      <span class="guide-avail ${g.is_available?'avail-yes':'avail-no'}">${g.is_available?'Available':'Busy'}</span>
    </div>`).join('');
}
function scrollGuides(dir){ document.getElementById('guidesTrack').scrollBy({left:dir*200,behavior:'smooth'}); }

// ── MOUNTAIN GRID ──
const crowdLabel={low:'Low',med:'Medium',high:'High'};
const crowdIcon ={low:'<svg width="8" height="8" viewBox="0 0 24 24" fill="#2a6b2a"><circle cx="12" cy="12" r="10"/></svg>',med:'<svg width="8" height="8" viewBox="0 0 24 24" fill="#8a5a2a"><circle cx="12" cy="12" r="10"/></svg>',high:'<svg width="8" height="8" viewBox="0 0 24 24" fill="#a23b1a"><circle cx="12" cy="12" r="10"/></svg>'};
const diffLabel ={easy:'Easy',moderate:'Moderate',hard:'Hard'};

function renderGrid(list){
  const grid=document.getElementById('mtnGrid');
  if(!grid) return;
  
  // If no mountains data, show error
  if (!mountains || mountains.length === 0) {
    grid.innerHTML='<div class="empty-state"><h3>No mountains available</h3><p>Please check your database connection.</p></div>';
    return;
  }
  
  let dataToRender = list;
  if(!dataToRender){
    dataToRender = mountains.filter(m => activeFilter === 'all' || m.difficulty === activeFilter);
    if(activeSort === 'rating') dataToRender = [...dataToRender].sort((a,b)=>b.rating - a.rating);
    if(activeSort === 'elevation') dataToRender = [...dataToRender].sort((a,b)=>parseInt(b.elevation) - parseInt(a.elevation));
    if(activeSort === 'time') dataToRender = [...dataToRender].sort((a,b)=>parseInt(b.time) - parseInt(a.time));
  }
  
  if(!dataToRender.length){
    grid.innerHTML='<div class="empty-state"><h3>No mountains found</h3><p>Try a different filter or search term.</p></div>';
    return;
  }
  
  grid.innerHTML=dataToRender.map((m,idx)=>`
    <div class="mtn-card" onclick="openMtnModal(mountains.find(x=>x.id===${m.id}))">
      <div class="mtn-img-wrap">
        <div class="mtn-img" style="background-image:url('${m.image}')"></div>
        <div class="mtn-img-overlay"></div>
        <div class="mtn-badges"><span class="badge badge-${m.difficulty}">${diffLabel[m.difficulty]||m.difficulty}</span></div>
        <div class="mtn-crowd">${crowdIcon[m.crowd]||''} ${crowdLabel[m.crowd]||''}</div>
      </div>
      <div class="mtn-body">
        <div class="mtn-name">${esc(m.name)}</div>
        <div class="mtn-loc">
          <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          ${esc(m.location)}
        </div>
        <div class="mtn-stats">
          <div><div class="mtn-stat-val">${esc(m.elevation)}</div><div class="mtn-stat-lbl">Elevation</div></div>
          <div><div class="mtn-stat-val">${esc(m.time)}</div><div class="mtn-stat-lbl">Duration</div></div>
        </div>
        <div class="mtn-footer">
          <div><div class="mtn-rating">★ ${m.rating}</div><div class="mtn-rev-count">${m.reviewsCount} reviews</div></div>
          <button class="view-btn">Details</button>
        </div>
      </div>
    </div>`).join('');
}

function filterBy(f,el){ activeFilter=f; document.querySelectorAll('.chip').forEach(c=>c.classList.remove('active')); el.classList.add('active'); renderGrid(); }
function sortBy(v){ activeSort=v; renderGrid(); }

// ── MOUNTAIN MODAL ──
function openMtnModal(m){
  if(!m) return;
  activeMtn=m; activeRevF='all';
  document.getElementById('mHeroImg').style.backgroundImage=`url('${m.image}')`;
  document.getElementById('mName').textContent=m.name;
  document.getElementById('mLoc').textContent=m.location;
  document.getElementById('mStats').innerHTML=[
    {val:m.elevation,lbl:'Elevation'},
    {val:m.time,     lbl:'Duration'},
    {val:'★ '+m.rating,lbl:'Rating'},
    {val:m.reviewsCount,lbl:'Reviews'},
  ].map(s=>`<div class="mtn-mstat"><div class="mtn-mstat-val">${s.val}</div><div class="mtn-mstat-lbl">${s.lbl}</div></div>`).join('');
  document.getElementById('mDesc').textContent=m.desc;
  document.getElementById('mFees').innerHTML=m.fees;
  document.getElementById('mRevBadge').textContent='('+m.reviewsList.length+')';
  document.getElementById('mTips').innerHTML=(m.tips||[]).map(t=>`<div class="tip-item"><span class="tip-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span><span>${esc(t)}</span></div>`).join('')||'<p style="color:var(--stone);font-size:13px;">No tips yet.</p>';
  document.getElementById('mAdv').innerHTML=(m.advisories||[]).map(a=>`<div class="adv-item">${esc(a)}</div>`).join('')||'<p style="color:var(--stone);font-size:13px;">No advisories.</p>';
  renderRevFilter(m); renderRevList(m,'all');
  mTab('overview',document.querySelector('.mtn-mtab'));
  document.getElementById('mtnOverlay').classList.add('open');
  document.body.style.overflow='hidden';
  if(isLoggedIn) checkSavedStatus(m.id);
}
function closeMtnModal(){ document.getElementById('mtnOverlay').classList.remove('open'); document.body.style.overflow=''; }

function mTab(name,el){
  document.querySelectorAll('.mtn-mtab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tp-'+name).classList.add('active');
}

function renderRevFilter(m){
  const bar=document.getElementById('mRevFilter');
  if(!bar) return;
  const rats=['all',5,4,3,2];
  bar.innerHTML=rats.map(r=>{
    const ct=r==='all'?m.reviewsList.length:m.reviewsList.filter(x=>x.stars===r).length;
    const lbl=r==='all'?'All':'★ '+r;
    return `<div class="rev-chip${activeRevF==r?' active':''}" onclick="setRevF('${r}')">${lbl} (${ct})</div>`;
  }).join('');
}
function setRevF(r){ activeRevF=r; renderRevFilter(activeMtn); renderRevList(activeMtn,r); }
function renderRevList(m,filter){
  const list=document.getElementById('mRevList');
  if(!list) return;
  let revs=[...(m.reviewsList||[])];
  if(filter!=='all') revs=revs.filter(r=>r.stars===parseInt(filter));
  if(!revs.length){ list.innerHTML='<div class="no-revs">No reviews with this rating.</div>'; return; }
  list.innerHTML=revs.map(r=>{
    const stars='★'.repeat(r.stars)+'☆'.repeat(5-r.stars);
    const media=r.media?.length?`<div class="rev-media">${r.media.map(u=>`<div class="rev-thumb" style="background-image:url('${u}')" onclick="event.stopPropagation();window.open('${u}','_blank')"></div>`).join('')}</div>`:'';
    return `<div class="rev-item">
      <div class="rev-hd">
        <div class="rev-av">${esc(r.initials)}</div>
        <div><div class="rev-name">${esc(r.author)}</div><div class="rev-stars">${stars}</div></div>
        <div class="rev-date">${esc(r.date)}</div>
      </div>
      <div class="rev-text">${esc(r.text)}</div>${media}
    </div>`;
  }).join('');
}

async function saveMtn() {
    if (!activeMtn) return;
    
    if (!isLoggedIn) {
        showToast('Please login to save mountains 🔒');
        setTimeout(() => { window.location.href = 'login.php'; }, 1500);
        return;
    }
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'save',
                mountain_id: activeMtn.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message);
            const saveBtn = document.querySelector('.m-action.m-save');
            if (saveBtn) {
                if (data.saved) {
                    saveBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" fill="var(--gold)"/></svg> Saved';
                    saveBtn.style.borderColor = 'var(--gold)';
                    saveBtn.style.color = 'var(--gold)';
                } else {
                    saveBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Save';
                    saveBtn.style.borderColor = '';
                    saveBtn.style.color = '';
                }
            }
        } else {
            showToast(data.message || 'Something went wrong');
        }
    } catch (error) {
        console.error('Error saving mountain:', error);
        showToast('Failed to save. Please try again.');
    }
}

async function checkSavedStatus(mountainId) {
    if (!isLoggedIn) return;
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'check',
                mountain_id: mountainId
            })
        });
        
        const data = await response.json();
        const saveBtn = document.querySelector('.m-action.m-save');
        if (saveBtn) {
            if (data.saved) {
                saveBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" fill="var(--gold)"/></svg> Saved';
                saveBtn.style.borderColor = 'var(--gold)';
                saveBtn.style.color = 'var(--gold)';
            } else {
                saveBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Save';
                saveBtn.style.borderColor = '';
                saveBtn.style.color = '';
            }
        }
    } catch (error) {
        console.error('Error checking saved status:', error);
    }
}

function bookMtn(){ if(activeMtn){ localStorage.setItem('bookingMtn',JSON.stringify(activeMtn)); window.location.href='bookings.php'; } }

// ── GUIDE MODAL ──
const covers=['https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&q=60','https://images.unsplash.com/photo-1454496522485-0a69b2730d75?w=1200&q=60','https://images.unsplash.com/photo-1465919292275-c60ad29da028?w=1200&q=60'];

function openGuideModal(g){
  activeGuide=g;
  document.getElementById('gCoverImg').style.backgroundImage=`url('${g.cover||covers[g.id%covers.length]}')`;
  const av=document.getElementById('gModalAv');
  if(av) {
    if(g.avatar){ av.style.backgroundImage=`url('${g.avatar}')`;av.textContent=''; }
    else{ av.style.backgroundImage='';av.textContent=g.initials; av.style.backgroundColor=['#1a4d3a','#2c6e4f','#8a5a2a','#5a3a1a'][g.name.length%4]; }
  }
  document.getElementById('gModalName').textContent=g.name;
  document.getElementById('gPills').innerHTML=`
    <span class="gpill gpill-spec"><svg viewBox="0 0 24 24"><path d="M4 22h16M6 4l6 8 6-8"/></svg>${esc(g.specialization)}</span>
    <span class="gpill gpill-exp"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${g.years_experience} yrs exp</span>
    <span class="gpill ${g.is_available?'gpill-avail':'gpill-busy'}">${g.is_available?'Available':'Busy'}</span>`;
  const fullS=Math.floor(g.rating); let sh='';
  for(let i=0;i<fullS;i++) sh+='★';
  if(g.rating-fullS>=.5) sh+='½';
  document.getElementById('gStars').textContent=sh;
  document.getElementById('gRatingVal').textContent=g.rating;
  document.getElementById('gRatingCt').textContent='('+g.total_trips+' trips)';
  document.getElementById('gTrips').textContent=g.total_trips;
  document.getElementById('gMtnCount').textContent=g.mountains.length;
  document.getElementById('gBio').textContent=g.bio||'No bio yet.';
  const ci=[];
  if(g.email) ci.push(`<div class="contact-row"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>${esc(g.email)}</div>`);
  if(g.phone) ci.push(`<div class="contact-row"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>${esc(g.phone)}</div>`);
  if(g.home_region) ci.push(`<div class="contact-row"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>${esc(g.home_region)}</div>`);
  document.getElementById('gContact').innerHTML=ci.join('')||'<p style="font-size:13px;color:var(--stone);">Contact info not available.</p>';
  document.getElementById('gMtns').innerHTML=g.mountains.length
    ? g.mountains.map(m=>`<span class="mtn-tag-pill" onclick="openMtnFromGuide(${m.id})"><svg viewBox="0 0 24 24"><path d="M4 22h16M6 4l6 8 6-8"/></svg>${esc(m.name)}</span>`).join('')
    : '<p style="font-size:13px;color:var(--stone);">No specific mountain assignments.</p>';
  document.getElementById('gRevCt').textContent=g.reviews_count;
  document.getElementById('gRevList').innerHTML=g.reviews.length
    ? g.reviews.slice(0,4).map(r=>`<div class="g-rev-item">
        <div class="g-rev-hd">
          <div class="g-rev-av">${esc(r.initials)}</div>
          <div><div class="g-rev-name">${esc(r.author)}</div><div class="g-rev-stars">${'★'.repeat(r.rating)+'☆'.repeat(5-r.rating)}</div></div>
          <div class="g-rev-date">${esc(r.date)}</div>
        </div>
        <div class="g-rev-comment">"${esc(r.comment)}"</div>
      </div>`).join('')
    : '<div style="padding:24px;text-align:center;color:var(--stone);font-size:13px;">No reviews yet.</div>';
  document.getElementById('gDetails').innerHTML=[
    {lbl:'Specialization',val:g.specialization},
    {lbl:'Experience',    val:g.years_experience+' years'},
    {lbl:'Hiking Level',  val:g.hiking_level},
    {lbl:'Home Region',   val:g.home_region},
    {lbl:'Languages',     val:'English, Tagalog'},
    {lbl:'Certifications',val:'First Aid Certified'},
  ].map(d=>`<div class="gd-item"><div class="gd-lbl">${d.lbl}</div><div class="gd-val">${esc(d.val)}</div></div>`).join('');
  gTab('reviews',document.querySelector('.g-mtab'));
  document.getElementById('guideOverlay').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeGuideModal(){ document.getElementById('guideOverlay').classList.remove('open'); document.body.style.overflow=''; }
function gTab(name,el){
  document.querySelectorAll('.g-mtab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.g-tab-pane').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('gtp-'+name).classList.add('active');
}
function openMtnFromGuide(id){ closeGuideModal(); const m=mountains.find(x=>x.id===id); if(m) openMtnModal(m); }
function msgGuide(){ if(!activeGuide) return; localStorage.setItem('messageGuide',JSON.stringify({id:activeGuide.user_id,name:activeGuide.name})); window.location.href='messages.php'; }
function bookGuide(){ if(!activeGuide) return; localStorage.setItem('bookingGuide',JSON.stringify(activeGuide)); window.location.href='bookings.php?guide_id='+activeGuide.id; }

// Backdrop close
document.getElementById('mtnOverlay').addEventListener('click',e=>{ if(e.target===document.getElementById('mtnOverlay')) closeMtnModal(); });
document.getElementById('guideOverlay').addEventListener('click',e=>{ if(e.target===document.getElementById('guideOverlay')) closeGuideModal(); });

// Featured card click
const featuredCard = document.getElementById('featuredCard');
if(featuredCard && mountains && mountains.length > 0) {
  featuredCard.addEventListener('click', e=>{
    if(!e.target.closest('button')) openMtnModal(mountains[0]);
  });
}

// Wait for DOM to be fully loaded before initializing
document.addEventListener('DOMContentLoaded', function() {
  if (mountains && mountains.length > 0) {
    renderGuides();
    renderGrid();
    initMiniCarousel();
    console.log('Page initialized with', mountains.length, 'mountains');
  } else {
    console.error('No mountains data available. Check database connection.');
    const grid = document.getElementById('mtnGrid');
    if (grid) {
      grid.innerHTML='<div class="empty-state"><h3>Unable to load mountains</h3><p>Please check your database connection and make sure the mountains table exists.</p></div>';
    }
  }
});
</script>
</body>
</html>