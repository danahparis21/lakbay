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
    SELECT gr.*, u.name as user_name, u.avatar as user_avatar
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
        'avatar'     => $rev['user_avatar'] ?? null,
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
    
    // Check for get_heatmap_data action FIRST (uses $_POST, not JSON)
    if (isset($_POST['action']) && $_POST['action'] === 'get_heatmap_data') {
        $mountain_id = $_POST['mountain_id'] ?? 0;
        
        if (!$mountain_id) {
            echo json_encode(['success' => false, 'message' => 'Mountain ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM mountains WHERE id = ?");
        $stmt->execute([$mountain_id]);
        $mountain = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $trailCoordinates = [];
        $stmt = $pdo->prepare("SELECT lat, lon, ele, idx FROM tracks WHERE mountain_id = ? OR fileId = ? ORDER BY idx ASC");
        $fileIdMap = [1 => 'BATULAO', 2 => 'APAYANG', 3 => 'LANTIK', 4 => 'TALAMITAM'];
        $fileId = $fileIdMap[$mountain_id] ?? null;
        $stmt->execute([$mountain_id, $fileId]);
        $trackPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($trackPoints as $p) {
            $trailCoordinates[] = [(float)$p['lon'], (float)$p['lat']];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, name, type, latitude, longitude, elevation, description 
            FROM trail_waypoints 
            WHERE mountain_id = ? AND is_active = 1
            ORDER BY order_index ASC
        ");
        $stmt->execute([$mountain_id]);
        $waypoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'trail' => $trailCoordinates,
            'waypoints' => $waypoints,
            'mountain' => [
                'name' => $mountain['name'],
                'lat' => (float)($mountain['start_point_lat'] ?? ($trailCoordinates[0][1] ?? 14.0583)),
                'lng' => (float)($mountain['start_point_lng'] ?? ($trailCoordinates[0][0] ?? 120.8320)),
                'description' => $mountain['description'],
                'difficulty' => $mountain['difficulty'],
                'elevation' => $mountain['elevation']
            ]
        ]);
        exit;
    }
    
    // Only proceed with JSON actions if not heatmap request
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
<link rel="stylesheet" href="shared.css">
 <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
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

/* ── MAP + WEATHER PANEL ── */
.map-weather-section{margin-bottom:40px;}
.map-weather-grid{
  display:grid;
  grid-template-columns:1fr 360px;
  gap:20px;
  align-items:start;
}
@media(max-width:900px){.map-weather-grid{grid-template-columns:1fr;}}

/* Map Card */
.map-card{
  background:var(--white);border-radius:var(--r);
  overflow:hidden;box-shadow:var(--sh-md);
  border:1px solid rgba(16,6,0,.05);
  display:flex;flex-direction:column;
}
.map-card-header{
  padding:16px 20px;
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  border-bottom:1px solid var(--mist);
}
.map-header-left{}
.map-hint{font-size:11.5px;color:var(--stone);margin-top:3px;}
#exploreMap{
  height:420px;width:100%;
}
@media(max-width:600px){#exploreMap{height:300px;}}

/* Leaflet marker customisation */
.lk-marker{
  width:38px;height:38px;border-radius:50%;
  background:var(--ink);border:3px solid var(--gold);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:.2s;
  box-shadow:0 4px 16px rgba(16,6,0,.35);
  color:var(--cream);font-size:16px;
}
.lk-marker.hovered{background:var(--gold);color:var(--ink);transform:scale(1.15);}
.lk-popup .leaflet-popup-content-wrapper{
  background:var(--ink);color:var(--cream);
  border-radius:14px;padding:0;overflow:hidden;
  box-shadow:0 8px 32px rgba(16,6,0,.35);
  border:1px solid rgba(198,164,59,.25);
  min-width:200px;
}
.lk-popup .leaflet-popup-tip{background:var(--ink);}
.lk-popup .leaflet-popup-content{margin:0;}
.popup-inner{padding:14px 16px;}
.popup-img{height:90px;background-size:cover;background-position:center;width:100%;}
.popup-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:var(--white);margin-bottom:4px;}
.popup-row{display:flex;align-items:center;gap:8px;font-size:11px;color:rgba(255,255,255,.65);margin-bottom:3px;}
.popup-badge{
  font-size:9.5px;font-weight:700;padding:2px 8px;border-radius:60px;display:inline-block;margin-right:4px;
}
.popup-crowd{display:flex;align-items:center;gap:5px;font-size:11px;margin-top:6px;}
.popup-crowd-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.crowd-low .popup-crowd-dot{background:#4caf50;}
.crowd-med .popup-crowd-dot{background:#ff9800;}
.crowd-high .popup-crowd-dot{background:#f44336;}
.crowd-low .popup-crowd-label{color:#4caf50;}
.crowd-med .popup-crowd-label{color:#ff9800;}
.crowd-high .popup-crowd-label{color:#f44336;}
.popup-btn{
  display:block;width:100%;padding:9px;
  background:rgba(198,164,59,.15);border:none;border-top:1px solid rgba(255,255,255,.07);
  color:var(--gold);font-size:11.5px;font-weight:600;font-family:'DM Sans',sans-serif;
  cursor:pointer;text-align:center;transition:.2s;letter-spacing:.3px;
}
.popup-btn:hover{background:rgba(198,164,59,.3);}

/* Weather Panel */
.weather-panel{
  background:var(--white);border-radius:var(--r);
  box-shadow:var(--sh-md);border:1px solid rgba(16,6,0,.05);
  overflow:hidden;display:flex;flex-direction:column;
}
.weather-panel-header{
  padding:16px 20px;border-bottom:1px solid var(--mist);
}
.weather-tabs-row{
  display:flex;gap:0;overflow-x:auto;border-bottom:1px solid var(--mist);
  scrollbar-width:none;
}
.weather-tabs-row::-webkit-scrollbar{display:none;}
.wtab{
  padding:9px 14px;font-size:11.5px;font-weight:600;
  color:var(--stone);cursor:pointer;border-bottom:2px solid transparent;
  transition:.15s;white-space:nowrap;flex-shrink:0;
}
.wtab:hover{color:var(--ink);}
.wtab.active{color:var(--ink);border-color:var(--ink);}

.weather-content-wrap{padding:16px 18px;flex:1;}

/* Current weather block */
.weather-current-block{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:14px;
}
.weather-temp-main{font-size:40px;font-weight:700;font-family:'DM Mono',monospace;line-height:1;}
.weather-icon-main{font-size:44px;line-height:1;}
.weather-desc-row{font-size:12.5px;color:var(--stone);margin-top:4px;}
.weather-advice-banner{
  padding:10px 14px;border-radius:var(--r-sm);
  font-size:12px;font-weight:600;margin-bottom:14px;
  display:flex;align-items:center;gap:8px;
  line-height:1.4;
}
.advice-great{background:rgba(76,175,80,.1);color:#2a7a2a;border:1px solid rgba(76,175,80,.2);}
.advice-ok{background:rgba(255,152,0,.1);color:#8a5a00;border:1px solid rgba(255,152,0,.2);}
.advice-bad{background:rgba(244,67,54,.1);color:#9a1a1a;border:1px solid rgba(244,67,54,.2);}
.advice-caution{background:rgba(33,150,243,.1);color:#0a4a7a;border:1px solid rgba(33,150,243,.2);}

.weather-details-row{
  display:flex;gap:8px;margin-bottom:14px;
}
.weather-detail-chip{
  flex:1;background:var(--cream);border-radius:var(--r-sm);
  padding:10px;text-align:center;
  border:1px solid rgba(16,6,0,.05);
}
.weather-chip-val{font-size:14px;font-weight:700;font-family:'DM Mono',monospace;color:var(--ink);}
.weather-chip-lbl{font-size:9.5px;color:var(--stone);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}

/* Forecast row */
.forecast-label{font-size:9.5px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--stone);margin-bottom:8px;font-family:'DM Mono',monospace;}
.forecast-row{display:flex;gap:6px;}
.forecast-day-chip{
  flex:1;background:var(--cream);border-radius:10px;
  padding:8px 4px;text-align:center;
  border:1px solid rgba(16,6,0,.05);
}
.fc-day-name{font-size:9.5px;font-weight:700;color:var(--stone);text-transform:uppercase;margin-bottom:4px;}
.fc-icon{font-size:18px;margin-bottom:4px;}
.fc-temps{font-size:10px;font-weight:600;}
.fc-high{color:var(--ink);}
.fc-low{color:var(--stone);}

.weather-loading-state{
  padding:40px 20px;text-align:center;color:var(--stone);font-size:13px;
}
.weather-loading-state .spin{
  display:inline-block;font-size:24px;
  animation:spin .8s linear infinite;margin-bottom:10px;
}
@keyframes spin{to{transform:rotate(360deg);}}

/* Crowd inline badge on map popup */
.crowd-status-chip{
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 10px;border-radius:60px;font-size:10px;font-weight:700;
}
.crowd-low-chip{background:rgba(76,175,80,.12);color:#2a7a2a;}
.crowd-med-chip{background:rgba(255,152,0,.12);color:#8a5a00;}
.crowd-high-chip{background:rgba(244,67,54,.12);color:#9a1a1a;}

/* Map panel improvements */
.map-card {
    box-shadow: var(--sh-lg);
}

#exploreMap {
    height: 450px;
    border-radius: 0 0 var(--r) var(--r);
}

.lk-marker {
    transition: all 0.2s ease;
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(16,6,0,.35);
    font-weight: 700;
}
.lk-marker:hover {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}
.weather-panel-header {
    background: linear-gradient(135deg, var(--cream) 0%, var(--white) 100%);
}
.forecast-compact {
    transition: all 0.2s ease;
}
.forecast-compact:hover {
    transform: translateY(-2px);
    box-shadow: var(--sh);
}

.weather-tabs-row {
    display: flex;
    gap: 0;
    overflow-x: auto;
    border-bottom: 1px solid var(--mist);
    scrollbar-width: thin;
}

/* ── REDESIGNED MOUNTAIN MODAL ── */
.overlay {
  z-index: 2000 !important;
  backdrop-filter: blur(12px);
  background: rgba(16,6,0,0.75);
}

.mtn-modal-box {
  max-width: 1100px;
  width: 90%;
  height: 85vh;
  max-height: 800px;
  background: var(--cream);
  border-radius: 32px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(0,0,0,0.3);
  position: relative;
  animation: modalIn 0.3s var(--ease);
}

.modal-close {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.2);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 100;
  transition: all 0.2s;
}
.modal-close:hover {
  background: rgba(0,0,0,0.8);
  transform: scale(1.05);
}

.mtn-modal-hero {
  position: relative;
  height: 280px;
  flex-shrink: 0;
  overflow: hidden;
}
.mtn-modal-hero-img {
  width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
  transition: transform 0.5s var(--ease);
}
.mtn-modal-box:hover .mtn-modal-hero-img {
  transform: scale(1.03);
}
.mtn-modal-hero-grad {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 30%, rgba(16,6,0,0.85) 100%);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 24px 32px;
}
.mtn-modal-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: rgba(255,255,255,0.6);
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.mtn-modal-name-section {
  margin-bottom: 16px;
}
.mtn-modal-name {
  font-family: 'Playfair Display', serif;
  font-size: 32px;
  font-weight: 700;
  color: white;
  margin-bottom: 6px;
  letter-spacing: -0.5px;
}
.mtn-modal-location {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: rgba(255,255,255,0.7);
}
.mtn-modal-quick-stats {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}
.quick-stat {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,255,255,0.12);
  backdrop-filter: blur(8px);
  padding: 6px 14px;
  border-radius: 40px;
  font-size: 12px;
  color: white;
  font-weight: 500;
}
.quick-stat svg {
  width: 14px;
  height: 14px;
  stroke: var(--gold);
}
.overlay {
  z-index: 2000 !important;
  backdrop-filter: blur(12px);
  background: rgba(16,6,0,0.75);
}

.split-modal {
  display: flex;
  flex-direction: row;
  max-width: 1200px;
  width: 90%;
  height: 80vh;
  max-height: 700px;
  background: var(--cream);
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(0,0,0,0.3);
  position: relative;
  animation: modalIn 0.3s var(--ease);
}

@media (max-width: 800px) {
  .split-modal {
    flex-direction: column;
    height: 85vh;
  }
}

/* LEFT SIDE - IMAGE */
.modal-left {
  flex: 1.2;
  position: relative;
  overflow: hidden;
  background: var(--ink);
}

.modal-hero-image {
  width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
  transition: transform 0.5s var(--ease);
}

.split-modal:hover .modal-hero-image {
  transform: scale(1.05);
}

.modal-image-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px;
  background: linear-gradient(to top, rgba(16,6,0,0.85) 0%, transparent 100%);
}

.modal-badge {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 12px;
}

.modal-quick-info {
  display: flex;
  gap: 16px;
}

.quick-info-item {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(8px);
  padding: 6px 12px;
  border-radius: 30px;
  font-size: 12px;
  color: white;
  font-weight: 500;
}

.quick-info-item svg {
  stroke: var(--gold);
}

/* RIGHT SIDE - CONTENT */
.modal-right {
  flex: 1.8;
  display: flex;
  flex-direction: column;
  background: var(--cream);
  overflow: hidden;
}

.modal-header-info {
  padding: 24px 28px 16px;
  border-bottom: 1px solid var(--mist);
}

.modal-mountain-name {
  font-family: 'Playfair Display', serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 6px;
  letter-spacing: -0.3px;
}

.modal-mountain-location {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--stone);
  margin-bottom: 10px;
}

.modal-rating-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.rating-stars {
  color: var(--gold);
  font-size: 13px;
  letter-spacing: 2px;
}

.rating-value {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.rating-count {
  font-size: 12px;
  color: var(--stone);
}

/* Tabs */
.modal-tabs {
  display: flex;
  gap: 0;
  padding: 0 28px;
  border-bottom: 1px solid var(--mist);
  background: var(--cream);
}

.modal-tab {
  padding: 12px 0;
  margin-right: 24px;
  font-size: 13px;
  font-weight: 600;
  color: var(--stone);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
}

.modal-tab:hover {
  color: var(--ink);
}

.modal-tab.active {
  color: var(--ink);
  border-bottom-color: var(--gold);
}

/* Tab Content */
.modal-tab-content {
  flex: 1;
  overflow: hidden;
  padding: 20px 28px;
}

.tab-pane {
  display: none;
  height: 100%;
  overflow-y: auto;
}

.tab-pane.active {
  display: block;
}

.tab-pane::-webkit-scrollbar {
  width: 4px;
}

.tab-pane::-webkit-scrollbar-track {
  background: var(--mist);
  border-radius: 4px;
}

.tab-pane::-webkit-scrollbar-thumb {
  background: var(--gold);
  border-radius: 4px;
}

/* Overview Tab */
.overview-scroll {
  height: 100%;
  overflow-y: auto;
  padding-right: 8px;
}

.modal-description {
  font-size: 14px;
  line-height: 1.7;
  color: var(--stone);
  margin-bottom: 20px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.info-grid-item {
  background: var(--white);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
  border: 1px solid var(--mist);
  transition: all 0.2s;
}

.info-grid-item:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh);
}

.info-grid-icon {
  font-size: 24px;
  margin-bottom: 6px;
}

.info-grid-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--stone);
  margin-bottom: 4px;
}

.info-grid-value {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.weather-card-modal {
  background: linear-gradient(135deg, #2c5f2d, #1a3a1a);
  border-radius: 20px;
  padding: 16px;
  margin-bottom: 20px;
  color: white;
}

.weather-card-header {
  font-size: 12px;
  font-weight: 600;
  opacity: 0.8;
  margin-bottom: 10px;
}

.weather-card-body {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.weather-temp {
  font-size: 28px;
  font-weight: 700;
  font-family: 'DM Mono', monospace;
}

.weather-advice {
  font-size: 11px;
  max-width: 60%;
  text-align: right;
  line-height: 1.4;
}

.fees-card {
  background: var(--white);
  border-radius: 20px;
  padding: 16px;
  border: 1px solid var(--mist);
}

.fees-card h4 {
  font-size: 12px;
  font-weight: 700;
  color: var(--gold);
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.fees-card p {
  font-size: 13px;
  line-height: 1.8;
  color: var(--stone);
}

/* Trail Tab */
.trail-scroll {
  height: 100%;
  overflow-y: auto;
  padding-right: 8px;
}

.trail-stats-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.trail-stat-card {
  flex: 1;
  background: var(--white);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
  border: 1px solid var(--mist);
}

.trail-stat-icon {
  font-size: 20px;
  display: block;
  margin-bottom: 6px;
}

.trail-stat-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--stone);
  display: block;
  margin-bottom: 4px;
}

.trail-stat-value {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  font-family: 'DM Mono', monospace;
}

.trail-map-container {
  margin-bottom: 20px;
}

.waypoints-section h4 {
  font-size: 12px;
  font-weight: 700;
  color: var(--gold);
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.waypoints-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.waypoint-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: var(--white);
  border-radius: 12px;
  border: 1px solid var(--mist);
  transition: all 0.2s;
}

.waypoint-item:hover {
  transform: translateX(4px);
  border-color: var(--gold);
}

.waypoint-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(198,164,59,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.waypoint-info {
  flex: 1;
}

.waypoint-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.waypoint-type {
  font-size: 10px;
  color: var(--stone);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.waypoint-elevation {
  font-size: 11px;
  font-family: 'DM Mono', monospace;
  color: var(--gold);
}

/* Reviews Tab */
.reviews-scroll {
  height: 100%;
  overflow-y: auto;
  padding-right: 8px;
}

.reviews-summary-header {
  margin-bottom: 16px;
}

.rev-filter-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.rev-chip {
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 11px;
  font-weight: 600;
  background: var(--white);
  border: 1px solid var(--mist);
  cursor: pointer;
  transition: all 0.2s;
}

.rev-chip:hover {
  border-color: var(--gold);
  color: var(--gold);
}

.rev-chip.active {
  background: var(--ink);
  color: var(--cream);
  border-color: var(--ink);
}

.revs-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rev-item {
  background: var(--white);
  border-radius: 20px;
  padding: 16px;
  border: 1px solid var(--mist);
  transition: all 0.2s;
}

.rev-item:hover {
  box-shadow: var(--sh);
}

.rev-hd {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.rev-av {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--ink);
  color: var(--cream);
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.rev-name {
  font-size: 14px;
  font-weight: 600;
}

.rev-stars {
  color: var(--gold);
  font-size: 11px;
  margin-top: 2px;
}

.rev-date {
  font-size: 10px;
  color: var(--stone);
  margin-left: auto;
  font-family: 'DM Mono', monospace;
}

.rev-text {
  font-size: 13px;
  line-height: 1.65;
  color: var(--stone);
}

.rev-media {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}

.rev-thumb {
  width: 65px;
  height: 65px;
  border-radius: 12px;
  background-size: cover;
  background-position: center;
  cursor: pointer;
  border: 2px solid var(--mist);
  transition: all 0.2s;
}

.rev-thumb:hover {
  transform: scale(1.05);
  border-color: var(--gold);
}

.no-revs {
  padding: 40px;
  text-align: center;
  color: var(--stone);
  font-size: 13px;
  background: var(--white);
  border-radius: 20px;
}

/* Tips Tab */
.tips-scroll {
  height: 100%;
  overflow-y: auto;
  padding-right: 8px;
}

.tips-section, .advisories-section {
  margin-bottom: 24px;
}

.tips-section h3, .advisories-section h3 {
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 14px;
  padding-bottom: 8px;
  border-bottom: 2px solid var(--gold);
  display: inline-block;
}

.tip-item {
  display: flex;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--mist);
  font-size: 13px;
  line-height: 1.6;
  color: var(--stone);
}

.tip-item:last-child {
  border-bottom: none;
}

.tip-icon {
  color: var(--gold);
  flex-shrink: 0;
  font-size: 16px;
}

.adv-item {
  padding: 12px 16px;
  border-radius: 14px;
  margin-bottom: 10px;
  border-left: 3px solid var(--gold);
  background: rgba(198,164,59,0.06);
  font-size: 13px;
  line-height: 1.6;
}

/* Footer */
.modal-footer-actions {
  display: flex;
  gap: 12px;
  padding: 16px 28px;
  border-top: 1px solid var(--mist);
  background: var(--cream);
}

.action-btn {
  flex: 1;
  padding: 12px 20px;
  border-radius: 60px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s;
  font-family: 'DM Sans', sans-serif;
}

.action-save {
  background: transparent;
  color: var(--ink);
  border: 2px solid var(--mist);
}

.action-save:hover {
  border-color: var(--gold);
  background: rgba(198,164,59,0.05);
}

.action-book {
  background: var(--ink);
  color: var(--cream);
  border: none;
}

.action-book:hover {
  background: var(--gold);
  color: var(--ink);
}

.modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.2);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 100;
  transition: all 0.2s;
}

.modal-close:hover {
  background: rgba(0,0,0,0.8);
  transform: scale(1.05);
}

</style>
</head>
<body>


<?php
// Set current page for navbar highlighting
$currentPage = 'explore'; // Change per page: 'explore', 'bookings', 'quiz', 'messages', 'hikerProfile'
?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

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

  <!-- ── MAP + WEATHER SECTION ── -->
  <div class="map-weather-section">
    <div class="sec-hd">
      <div>
        <div class="sec-eyebrow">Live Overview</div>
        <div class="sec-title">Mountains of Nasugbu</div>
      </div>
    </div>

    <div class="map-weather-grid">
      <!-- Leaflet Map -->
      <div class="map-card">
        <div class="map-card-header">
          <div class="map-header-left">
            <div style="font-size:13px;font-weight:600;color:var(--ink);">Interactive Trail Map</div>
            <div class="map-hint">Click a pin to explore details &amp; open mountain info</div>
          </div>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div id="exploreMap"></div>
      </div>

      <!-- Weather Panel -->
      <div class="weather-panel">
        <div class="weather-panel-header">
          <div class="sec-eyebrow" style="margin-bottom:3px;">Real-time Conditions</div>
          <div style="font-size:13px;font-weight:600;color:var(--ink);">Mountain Weather</div>
        </div>
        <div class="weather-tabs-row" id="weatherTabsRow">
          <?php foreach ($mountains as $i => $mtn): ?>
          <div class="wtab <?= $i===0?'active':'' ?>" onclick="selectWeatherTab(<?= $mtn['id'] ?>, this)" data-id="<?= $mtn['id'] ?>">
            <?= htmlspecialchars(str_replace(['Mt.','Mountain','Mountain Trilogy'],['','Mtns.','Trilogy'], $mtn['name'])) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="weather-content-wrap" id="weatherContentWrap">
          <div class="weather-loading-state">
            <div class="spin">⛅</div>
            <div>Loading weather…</div>
          </div>
        </div>
      </div>
    </div>
  </div>

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

<!-- MOUNTAIN DETAIL MODAL - SPLIT SCREEN DESIGN -->
<div class="overlay" id="mtnOverlay">
  <div class="mtn-modal-box split-modal">
    <button class="modal-close" onclick="closeMtnModal()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
    
    <!-- LEFT: Hero Image Side -->
    <div class="modal-left">
      <div class="modal-hero-image" id="mHeroImg"></div>
      <div class="modal-image-overlay">
        <div class="modal-badge" id="mDifficultyBadge"></div>
        <div class="modal-quick-info">
          <div class="quick-info-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="mQuickTime"></span>
          </div>
          <div class="quick-info-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2L2 22h20L12 2z"/></svg>
            <span id="mQuickElevation"></span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- RIGHT: Content Side -->
    <div class="modal-right">
      <div class="modal-header-info">
        <h1 class="modal-mountain-name" id="mName"></h1>
        <div class="modal-mountain-location">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <span id="mLoc"></span>
        </div>
        <div class="modal-rating-row">
          <div class="rating-stars" id="mRatingStars"></div>
          <span class="rating-value" id="mRatingValue"></span>
          <span class="rating-count">(<span id="mRevCount">0</span> reviews)</span>
        </div>
      </div>

      <!-- Tabs -->
      <div class="modal-tabs">
        <button class="modal-tab active" data-tab="overview">Overview</button>
        <button class="modal-tab" data-tab="trail">Trail Map</button>
        <button class="modal-tab" data-tab="reviews">Reviews</button>
        <button class="modal-tab" data-tab="tips">Tips & Safety</button>
      </div>

      <!-- Tab Content -->
      <div class="modal-tab-content">
        <!-- Overview Tab -->
        <div class="tab-pane active" id="tp-overview">
          <div class="overview-scroll">
            <p class="modal-description" id="mDesc"></p>
            
            <div class="info-grid">
              <div class="info-grid-item">
                <div class="info-grid-icon">⏱️</div>
                <div class="info-grid-label">Duration</div>
                <div class="info-grid-value" id="mDuration"></div>
              </div>
              <div class="info-grid-item">
                <div class="info-grid-icon">🏔️</div>
                <div class="info-grid-label">Elevation</div>
                <div class="info-grid-value" id="mElevation"></div>
              </div>
              <div class="info-grid-item">
                <div class="info-grid-icon">👥</div>
                <div class="info-grid-label">Crowd Level</div>
                <div class="info-grid-value" id="mCrowd"></div>
              </div>
              <div class="info-grid-item">
                <div class="info-grid-icon">💧</div>
                <div class="info-grid-label">Best Season</div>
                <div class="info-grid-value" id="mPeakTimes"></div>
              </div>
            </div>

            <div class="weather-card-modal" id="mWeatherMini">
              <div class="weather-card-header">🌤️ Current Weather</div>
              <div class="weather-card-body">
                <div class="weather-temp" id="mWeatherTemp">--°C</div>
                <div class="weather-advice" id="mWeatherAdvice">Loading...</div>
              </div>
            </div>

            <div class="fees-card">
              <h4>📋 Fees & Requirements</h4>
              <div id="mFees"></div>
            </div>
          </div>
        </div>

        <!-- Trail Map Tab -->
        <div class="tab-pane" id="tp-trail">
          <div class="trail-scroll">
            <div class="trail-stats-row">
              <div class="trail-stat-card">
                <span class="trail-stat-icon">📏</span>
                <span class="trail-stat-label">Trail Length</span>
                <span class="trail-stat-value" id="trailLength">-- km</span>
              </div>
              <div class="trail-stat-card">
                <span class="trail-stat-icon">⏱️</span>
                <span class="trail-stat-label">Est. Duration</span>
                <span class="trail-stat-value" id="trailEstDuration">-- hrs</span>
              </div>
              <div class="trail-stat-card">
                <span class="trail-stat-icon">📈</span>
                <span class="trail-stat-label">Difficulty</span>
                <span class="trail-stat-value" id="trailDifficulty">--</span>
              </div>
            </div>
            <div class="trail-map-container">
              <div id="trailMap" style="height: 280px; width: 100%; border-radius: 16px;"></div>
            </div>
            <div class="waypoints-section">
              <h4>📍 Trail Waypoints</h4>
              <div id="waypointsContainer" class="waypoints-list"></div>
            </div>
          </div>
        </div>

        <!-- Reviews Tab -->
        <div class="tab-pane" id="tp-reviews">
          <div class="reviews-scroll">
            <div class="reviews-summary-header" id="reviewsSummaryHeader"></div>
            <div class="rev-filter-bar" id="mRevFilter"></div>
            <div class="revs-list" id="mRevList"></div>
          </div>
        </div>

        <!-- Tips & Safety Tab -->
        <div class="tab-pane" id="tp-tips">
          <div class="tips-scroll">
            <div class="tips-section">
              <h3>💡 Hiking Tips</h3>
              <div id="mTips"></div>
            </div>
            <div class="advisories-section">
              <h3>⚠️ Safety Advisories</h3>
              <div id="mAdv"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="modal-footer-actions">
        <button class="action-btn action-save" onclick="saveMtn()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          Save
        </button>
        <button class="action-btn action-book" onclick="bookMtn()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          Book a Hike
        </button>
      </div>
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
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── DATA from PHP ──
const mountains = <?= json_encode($mountains) ?>;
const guides    = <?= json_encode($guides) ?>;
const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
const currentUser = <?= json_encode($currentUser) ?>;

// Debug log to see if mountains are loading
console.log('Mountains loaded:', mountains.length);
console.log('Guides loaded:', guides.length);
console.log('Guides data sample:', guides[0]);

// ── STATE ──
let activeMtn    = null;
let activeGuide  = null;
let activeFilter = 'all';
let activeSort   = '';
let activeRevF   = 'all';
let miniIdx      = 0;
let miniTimer    = null;

// Helper function to get avatar background style
function getAvatarStyle(avatar, initials, colorIndex) {
    if (avatar) {
        return `background-image:url('/lakbay/${avatar}');background-size:cover;background-position:center;`;
    }
    const colors = ['#1a4d3a','#2c6e4f','#8a5a2a','#5a3a1a'];
    return `background:${colors[colorIndex % colors.length]};`;
}

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

function renderGuides(){
  const track=document.getElementById('guidesTrack');
  if(!track) return;
  if(!guides || guides.length === 0){ track.innerHTML='<div style="padding:32px;color:var(--stone);">No guides available.</div>'; return; }
  track.innerHTML=guides.map((g,i)=>{
    let avatarStyle = '';
    if (g.avatar) {
      // Use direct path from root - remove /lakbay prefix
      const avatarUrl = '/' + g.avatar;
      avatarStyle = `background-image:url('${avatarUrl}');background-size:cover;background-position:center;`;
    } else {
      const colors = ['#1a4d3a','#2c6e4f','#8a5a2a','#5a3a1a'];
      avatarStyle = `background:${colors[i % colors.length]};`;
    }
    return `
    <div class="guide-card" onclick="openGuideModal(guides[${i}])">
      <div class="guide-avatar" style="${avatarStyle}">${g.avatar ? '' : esc(g.initials)}</div>
      <div class="guide-name">${esc(g.name)}</div>
      <div class="guide-spec">${esc(g.specialization)}</div>
      <div class="guide-rating">★ ${g.rating}</div>
      <div class="guide-trips">${g.total_trips} trips</div>
      <span class="guide-avail ${g.is_available?'avail-yes':'avail-no'}">${g.is_available?'Available':'Busy'}</span>
    </div>`;
  }).join('');
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

// ── REDESIGNED MOUNTAIN MODAL ──
let trailMap = null;
let trailLayer = null;
let waypointMarkers = [];

function openMtnModal(m){
  if(!m) return;
  activeMtn = m;
  activeRevF = 'all';
  
  // Update left side
  document.getElementById('mHeroImg').style.backgroundImage = `url('${m.image}')`;
  document.getElementById('mDifficultyBadge').innerHTML = m.difficulty.charAt(0).toUpperCase() + m.difficulty.slice(1);
  document.getElementById('mDifficultyBadge').style.background = 
    m.difficulty === 'easy' ? '#2a6b2a' : (m.difficulty === 'moderate' ? '#8a5a2a' : '#a23b1a');
  document.getElementById('mQuickTime').textContent = m.time;
  document.getElementById('mQuickElevation').textContent = m.elevation;
  
  // Update right side header
  document.getElementById('mName').textContent = m.name;
  document.getElementById('mLoc').textContent = m.location;
  
  // Update rating stars
  const fullStars = Math.floor(m.rating);
  const hasHalf = m.rating - fullStars >= 0.5;
  let starsHtml = '';
  for(let i = 0; i < fullStars; i++) starsHtml += '★';
  if(hasHalf) starsHtml += '½';
  for(let i = 0; i < 5 - fullStars - (hasHalf ? 1 : 0); i++) starsHtml += '☆';
  document.getElementById('mRatingStars').innerHTML = starsHtml;
  document.getElementById('mRatingValue').textContent = m.rating;
  document.getElementById('mRevCount').textContent = m.reviewsCount;
  
  document.getElementById('mDuration').textContent = m.time;
  document.getElementById('mElevation').textContent = m.elevation;
  const crowdText = m.crowd === 'low' ? '😌 Low' : (m.crowd === 'med' ? '👥 Moderate' : '⚠️ High');
  document.getElementById('mCrowd').innerHTML = crowdText;
  document.getElementById('mPeakTimes').textContent = m.peakTimes || 'December to May';
  
  document.getElementById('mDesc').textContent = m.desc;
  document.getElementById('mFees').innerHTML = m.fees;
  
  // Tips & Advisories
  document.getElementById('mTips').innerHTML = (m.tips || []).map(t => `<div class="tip-item"><span class="tip-icon">💡</span><span>${esc(t)}</span></div>`).join('') || '<p style="color:var(--stone);font-size:13px;">No tips yet.</p>';
  document.getElementById('mAdv').innerHTML = (m.advisories || []).map(a => `<div class="adv-item">⚠️ ${esc(a)}</div>`).join('') || '<p style="color:var(--stone);font-size:13px;">No advisories.</p>';
  
  // Reviews
  renderRevFilter(m);
  renderRevList(m, 'all');
  
  // Load weather for this mountain
  loadWeatherForModal(m);
  
  // Load trail data
  loadTrailForModal(m.id);
  
  // Set active tab
  document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelector('.modal-tab[data-tab="overview"]').classList.add('active');
  document.getElementById('tp-overview').classList.add('active');
  
  // Open modal
  document.getElementById('mtnOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  if(isLoggedIn) checkSavedStatus(m.id);
}
async function loadWeatherForModal(mountain) {
  const mountainCoord = mountainCoords.find(m => m.id === mountain.id);
  
  if (mountainCoord) {
    try {
      const weather = await fetchWeather(mountainCoord.lat, mountainCoord.lng);
      if (weather) {
        const icon = getWeatherIcon(weather.current.code);
        const advice = getHikingAdvice(weather.current.code, weather.current.temp);
        const tempElem = document.getElementById('mWeatherTemp');
        const adviceElem = document.getElementById('mWeatherAdvice');
        const weatherCard = document.querySelector('#mWeatherMini .weather-card-header');
        
        if (tempElem) tempElem.textContent = `${weather.current.temp}°C`;
        if (adviceElem) adviceElem.textContent = advice.msg;
        if (weatherCard) {
          weatherCard.innerHTML = `${icon} Current Weather`;
        }
      } else {
        const adviceElem = document.getElementById('mWeatherAdvice');
        if (adviceElem) adviceElem.textContent = 'Weather data unavailable';
      }
    } catch (error) {
      console.error('Weather fetch error:', error);
      const adviceElem = document.getElementById('mWeatherAdvice');
      if (adviceElem) adviceElem.textContent = 'Unable to load weather';
    }
  }
}

async function loadTrailForModal(mountainId) {
  try {
    // Show loading state
    const trailLengthElem = document.getElementById('trailLength');
    const trailEstDurationElem = document.getElementById('trailEstDuration');
    const trailDifficultyElem = document.getElementById('trailDifficulty');
    const waypointsContainer = document.getElementById('waypointsContainer');
    
    if (trailLengthElem) trailLengthElem.textContent = 'Loading...';
    if (trailEstDurationElem) trailEstDurationElem.textContent = 'Loading...';
    if (trailDifficultyElem) trailDifficultyElem.innerHTML = 'Loading...';
    if (waypointsContainer) waypointsContainer.innerHTML = '<p style="color:var(--stone);font-size:13px;">Loading trail data...</p>';
    
    // Use the same page with POST request (like admin does)
    const response = await fetch(window.location.href, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams({
        action: 'get_heatmap_data',
        mountain_id: mountainId
      })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.success && data.trail && data.trail.length > 0) {
      // Update trail stats
      const mountain = mountains.find(m => m.id === mountainId);
      if (trailLengthElem) trailLengthElem.textContent = mountain?.time || '--';
      if (trailEstDurationElem) trailEstDurationElem.textContent = mountain?.time || '--';
      if (trailDifficultyElem) {
        const difficulty = mountain?.difficulty || 'moderate';
        const diffBg = difficulty === 'easy' ? '#d9ead3' : (difficulty === 'moderate' ? '#ffe0b5' : '#ffcfc2');
        const diffColor = difficulty === 'easy' ? '#2a6b2a' : (difficulty === 'moderate' ? '#8a5a2a' : '#a23b1a');
        trailDifficultyElem.innerHTML = `<span style="background:${diffBg}; color:${diffColor}; padding:4px 12px; border-radius:30px; font-weight:600;">${difficulty}</span>`;
      }
      
      // Initialize trail map
      if (trailMap) {
        trailMap.remove();
        trailMap = null;
      }
      
      const trailMapContainer = document.getElementById('trailMap');
      if (trailMapContainer) {
        trailMap = L.map('trailMap').setView([data.mountain.lat, data.mountain.lng], 13);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
          subdomains: 'abcd'
        }).addTo(trailMap);
        
        // Draw trail
        if (data.trail && data.trail.length > 0) {
          const trailCoords = data.trail.map(c => [c[1], c[0]]);
          trailLayer = L.polyline(trailCoords, {
            color: '#c6a43b',
            weight: 5,
            opacity: 0.9,
            lineCap: 'round'
          }).addTo(trailMap);
          trailMap.fitBounds(L.latLngBounds(trailCoords).pad(0.1));
        }
      }
      
      // Add waypoints
      if (waypointsContainer) {
        waypointsContainer.innerHTML = '';
        
        if (data.waypoints && data.waypoints.length > 0) {
          data.waypoints.forEach(wp => {
            const waypointDiv = document.createElement('div');
            waypointDiv.className = 'waypoint-item';
            waypointDiv.innerHTML = `
              <div class="waypoint-icon">${wp.type === 'summit' ? '⛰️' : (wp.type === 'water' ? '💧' : '📍')}</div>
              <div class="waypoint-info">
                <div class="waypoint-name">${wp.name}</div>
                <div class="waypoint-type">${wp.type}</div>
              </div>
              <div class="waypoint-elevation">${wp.elevation ? wp.elevation + 'm' : ''}</div>
            `;
            waypointsContainer.appendChild(waypointDiv);
            
            // Add marker to map if trailMap exists
            if (trailMap) {
              const marker = L.marker([parseFloat(wp.latitude), parseFloat(wp.longitude)], {
                icon: L.divIcon({
                  html: `<div style="background:${wp.type === 'summit' ? '#c6a43b' : '#100600'}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">${wp.type === 'summit' ? '⛰️' : (wp.type === 'water' ? '💧' : '📍')}</div>`,
                  iconSize: [28, 28]
                })
              }).bindPopup(`<strong>${wp.name}</strong><br>${wp.type}${wp.elevation ? ` · ${wp.elevation}m` : ''}`).addTo(trailMap);
              waypointMarkers.push(marker);
            }
          });
        } else {
          waypointsContainer.innerHTML = '<p style="color:var(--stone);font-size:13px;">No waypoints available for this trail.</p>';
        }
      }
    } else {
      if (trailLengthElem) trailLengthElem.innerHTML = 'Not available';
      if (trailEstDurationElem) trailEstDurationElem.innerHTML = 'Not available';
      if (trailDifficultyElem) trailDifficultyElem.innerHTML = 'Not available';
      if (waypointsContainer) waypointsContainer.innerHTML = '<p style="color:var(--stone);font-size:13px;">Trail data not available for this mountain.</p>';
    }
  } catch (error) {
    console.error('Error loading trail data:', error);
    const trailLengthElem = document.getElementById('trailLength');
    const trailEstDurationElem = document.getElementById('trailEstDuration');
    const trailDifficultyElem = document.getElementById('trailDifficulty');
    const waypointsContainer = document.getElementById('waypointsContainer');
    
    if (trailLengthElem) trailLengthElem.innerHTML = 'Unavailable';
    if (trailEstDurationElem) trailEstDurationElem.innerHTML = 'Unavailable';
    if (trailDifficultyElem) trailDifficultyElem.innerHTML = 'Unavailable';
    if (waypointsContainer) waypointsContainer.innerHTML = '<p style="color:var(--stone);font-size:13px;">Trail information unavailable at this time.</p>';
  }
}

// Update tab switching - FIXED for modal-tab class
document.querySelectorAll('.modal-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const tabName = tab.getAttribute('data-tab');
    document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById(`tp-${tabName}`).classList.add('active');
    
    // Refresh trail map if needed
    if (tabName === 'trail' && trailMap) {
      setTimeout(() => trailMap.invalidateSize(), 100);
    }
  });
});

function closeMtnModal() {
  document.getElementById('mtnOverlay').classList.remove('open');
  document.body.style.overflow = '';
  if (trailMap) {
    trailMap.remove();
    trailMap = null;
  }
}

// Update bookMtn function text
function bookMtn() { 
  if(activeMtn){ 
    localStorage.setItem('bookingMtn', JSON.stringify(activeMtn)); 
    window.location.href = 'bookings.php'; 
  } 
}

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

// Make sure rev-chip clicks work
function setRevF(r) { 
  activeRevF = r; 
  renderRevFilter(activeMtn); 
  renderRevList(activeMtn, r); 
}
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


// ── MAP + WEATHER ──
// Mountain coords from DB via PHP
const mountainCoords = <?php
  $coords = [];
  foreach ($dbMountains as $m) {
    // Map difficulty to display format
    $difficultyDisplay = $m['difficulty'] ?? 'Moderate';
    // Special case for Trilogy
    if(strpos($m['name'], 'Trilogy') !== false) {
        $difficultyDisplay = 'Difficult';
    }
    // For "Easy to Moderate" - keep as is
    if($difficultyDisplay === 'Easy to Moderate') {
        $difficultyDisplay = 'Easy to Moderate';
    }
    
    $coords[] = [
      'id'         => (int)$m['id'],
      'name'       => $m['name'],
      'lat'        => floatval($m['start_point_lat'] ?? 14.0583),
      'lng'        => floatval($m['start_point_lng'] ?? 120.8320),
      'elevation'  => $m['elevation'] ?? '—',
      'difficulty' => strtolower($difficultyDisplay),
      'difficulty_display' => $difficultyDisplay,
      'crowd'      => strtolower($m['crowdLevel'] ?? 'medium'),
      'image'      => $m['image'] ?? '',
      'duration'   => $m['duration'] ?? '—',
      'rating'     => floatval($m['rating'] ?? 4.0),
      'is_trilogy' => strpos($m['name'], 'Trilogy') !== false,
    ];
  }
  echo json_encode($coords);
?>;

// Weather state
const mountainWeatherCache = {};
let activeWeatherMtnId = mountainCoords.length > 0 ? mountainCoords[0].id : null;
let exploreMap = null;
let mapMarkers = {};

function getWeatherIcon(code){
  if(code===0) return '☀️';
  if(code>=1&&code<=2) return '🌤️';
  if(code===3) return '☁️';
  if(code>=45&&code<=48) return '🌫️';
  if(code>=51&&code<=55) return '🌦️';
  if(code>=61&&code<=65) return '🌧️';
  if(code>=80&&code<=82) return '🌧️';
  if(code>=95) return '⛈️';
  return '🌡️';
}
function getWeatherDesc(code){
  const d={0:'Clear sky',1:'Mainly clear',2:'Partly cloudy',3:'Overcast',45:'Foggy',48:'Foggy',51:'Light drizzle',53:'Moderate drizzle',55:'Dense drizzle',61:'Light rain',63:'Moderate rain',65:'Heavy rain',80:'Rain showers',81:'Heavy showers',82:'Violent showers',95:'Thunderstorm',96:'Thunderstorm',99:'Thunderstorm'};
  return d[code]||'Variable';
}
function getHikingAdvice(code, temp){
  if(code>=95) return {cls:'advice-bad', msg:'⛈️ Typhoon / Thunderstorm — Do NOT hike. Follow local advisories.'};
  if(code>=80) return {cls:'advice-bad', msg:'🌧️ Heavy rain expected — Trail conditions may be hazardous.'};
  if(code>=61) return {cls:'advice-caution', msg:'🌧️ Rain forecast — Bring rain gear and check with guides before going.'};
  if(code>=45) return {cls:'advice-ok', msg:'🌫️ Foggy conditions — Visibility may be low on ridges.'};
  if(temp >= 32) return {cls:'advice-ok', msg:'🥵 Very hot today — Start very early and bring extra water.'};
  if(code===0 && temp >= 20 && temp <= 30) return {cls:'advice-great', msg:'✅ Perfect conditions — A great day to hit the trail!'};
  if(code<=3) return {cls:'advice-great', msg:'🌤️ Good hiking weather — Conditions look favourable today.'};
  return {cls:'advice-ok', msg:'🌥️ Check trail conditions before heading out.'};
}
function getCrowdLabel(crowd){
  const c = (crowd||'').toLowerCase();
  if(c==='high'||c==='very high') return {chip:'crowd-high-chip',label:'High Crowd',dot:'#f44336'};
  if(c==='medium'||c==='moderate'||c==='med') return {chip:'crowd-med-chip',label:'Moderate Crowd',dot:'#ff9800'};
  return {chip:'crowd-low-chip',label:'Low Crowd',dot:'#4caf50'};
}

async function fetchWeather(lat, lng){
  const url=`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current_weather=true&daily=temperature_2m_max,temperature_2m_min,weathercode&timezone=Asia%2FManila&windspeed_unit=kmh`;
  try{
    const r = await fetch(url);
    const d = await r.json();
    return {
      current: { temp: Math.round(d.current_weather.temperature), wind: Math.round(d.current_weather.windspeed), code: d.current_weather.weathercode },
      daily: { times: d.daily.time.slice(1,6), maxTemps: d.daily.temperature_2m_max.slice(1,6).map(t=>Math.round(t)), minTemps: d.daily.temperature_2m_min.slice(1,6).map(t=>Math.round(t)), codes: d.daily.weathercode.slice(1,6) }
    };
  } catch(e){ return null; }
}

function renderWeatherPanel(mtnId){
  const mtn = mountainCoords.find(m=>m.id===mtnId);
  const data = mountainWeatherCache[mtnId];
  const wrap = document.getElementById('weatherContentWrap');
  if(!wrap) return;
  if(!data){
    wrap.innerHTML=`<div class="weather-loading-state"><div class="spin">⛅</div><div>Loading weather…</div></div>`;
    return;
  }
  const {current, daily} = data;
  const icon = getWeatherIcon(current.code);
  const desc = getWeatherDesc(current.code);
  const advice = getHikingAdvice(current.code, current.temp);
  const crowd = getCrowdLabel(mtn.crowd);
  const tempColor = current.temp>=32?'#ef4444':current.temp>=27?'#f97316':current.temp>=22?'#eab308':'#06b6d4';
  
  // Get tomorrow's forecast (just 1 day ahead)
  const tomorrow = daily.times[0];
  const tomorrowMax = daily.maxTemps[0];
  const tomorrowMin = daily.minTemps[0];
  const tomorrowIcon = getWeatherIcon(daily.codes[0]);
  const dayName = tomorrow ? new Date(tomorrow+'T00:00:00').toLocaleDateString('en-PH',{weekday:'short'}) : 'Tomorrow';
  
  // Set difficulty background color
  let diffBg = '#ffe0b5'; // default moderate
  let diffColor = '#8a5a2a';
  const diffDisplay = mtn.difficulty_display || mtn.difficulty;
  if(diffDisplay === 'Easy' || diffDisplay === 'easy') {
    diffBg = '#d9ead3';
    diffColor = '#2a6b2a';
  } else if(diffDisplay === 'Difficult' || diffDisplay === 'hard') {
    diffBg = '#ffcfc2';
    diffColor = '#a23b1a';
  } else if(diffDisplay === 'Easy to Moderate') {
    diffBg = '#e8f5e9';
    diffColor = '#4caf50';
  }

  wrap.innerHTML = `
    <div class="weather-current-block">
      <div>
        <div class="weather-temp-main" style="color:${tempColor}">${current.temp}°C</div>
        <div class="weather-desc-row">${desc} · 💨 ${current.wind} km/h</div>
      </div>
      <div class="weather-icon-main">${icon}</div>
    </div>
    <div class="weather-advice-banner ${advice.cls}">${advice.msg}</div>
    <div class="weather-details-row">
      <div class="weather-detail-chip">
        <div class="weather-chip-val">${mtn.elevation}</div>
        <div class="weather-chip-lbl">Elevation</div>
      </div>
      <div class="weather-detail-chip">
        <div class="weather-chip-val" style="background:${diffBg}; color:${diffColor}; padding:4px 8px; border-radius:30px; font-weight:600;">${diffDisplay}</div>
        <div class="weather-chip-lbl">Difficulty</div>
      </div>
      <div class="weather-detail-chip">
        <div class="weather-chip-val"><span class="crowd-status-chip ${crowd.chip}">${crowd.label}</span></div>
        <div class="weather-chip-lbl">Trail Crowd</div>
      </div>
    </div>
    <div class="forecast-compact" style="background:var(--cream); border-radius:12px; padding:12px; margin-top:8px;">
      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
          <div style="font-size:11px; color:var(--stone);">⛅ ${dayName}</div>
          <div style="font-size:20px; margin:4px 0;">${tomorrowIcon}</div>
          <div><span style="font-weight:700;">${tomorrowMax}°</span> <span style="color:var(--stone);">/${tomorrowMin}°</span></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:11px; color:var(--stone);">${current.wind} km/h wind</div>
        </div>
      </div>
    </div>
  `;
}
async function selectWeatherTab(mtnId, el){
  // Update tabs
  document.querySelectorAll('.wtab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  activeWeatherMtnId = mtnId;
  // Highlight map marker
  Object.keys(mapMarkers).forEach(id=>{
    const el2 = mapMarkers[id]._icon?.querySelector('.lk-marker');
    if(el2) el2.classList.toggle('hovered', parseInt(id)===mtnId);
  });
  if(!mountainWeatherCache[mtnId]){
    renderWeatherPanel(mtnId);
    const mtn = mountainCoords.find(m=>m.id===mtnId);
    if(mtn){
      const data = await fetchWeather(mtn.lat, mtn.lng);
      if(data) mountainWeatherCache[mtnId] = data;
    }
  }
  renderWeatherPanel(mtnId);
}
function initExploreMap(){
  if(typeof L === 'undefined') return;
  
  // Use Satellite/Imagery layer
  exploreMap = L.map('exploreMap',{
    center:[14.08, 120.76],
    zoom:13,
    zoomControl:true,
    attributionControl:false
  });

  L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri',
    maxZoom: 19
  }).addTo(exploreMap);
  
  // Add semi-transparent overlay
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19,
    opacity: 0.3
  }).addTo(exploreMap);

  // First, find the Trilogy mountain
  const trilogyMtn = mountainCoords.find(m => m.is_trilogy || m.name.includes('Trilogy'));
  
  // Find the three peaks
  const lantik = mountainCoords.find(m => m.name.includes('Lantik'));
  const talamitam = mountainCoords.find(m => m.name.includes('Talamitam'));
  const apayang = mountainCoords.find(m => m.name.includes('Apayang'));
  
  // Calculate bounds for the Trilogy area
  const peakLats = [];
  const peakLngs = [];
  if (lantik) { peakLats.push(lantik.lat); peakLngs.push(lantik.lng); }
  if (talamitam) { peakLats.push(talamitam.lat); peakLngs.push(talamitam.lng); }
  if (apayang) { peakLats.push(apayang.lat); peakLngs.push(apayang.lng); }
  
  // Calculate center of the three peaks
  const centerLat = peakLats.reduce((a,b) => a + b, 0) / peakLats.length;
  const centerLng = peakLngs.reduce((a,b) => a + b, 0) / peakLngs.length;
  
  // Calculate radius to cover all peaks (in meters)
  let maxDistance = 0;
  peakLats.forEach((lat, i) => {
    const distance = Math.sqrt(Math.pow(lat - centerLat, 2) + Math.pow(peakLngs[i] - centerLng, 2)) * 111000;
    if (distance > maxDistance) maxDistance = distance;
  });
  const radius = maxDistance + 300; // Add 300m buffer
  
  // Add Trilogy area circle (behind all markers)
  if (trilogyMtn && lantik && talamitam && apayang) {
    const trilogyCircle = L.circle([centerLat, centerLng], {
      color: '#c6a43b',
      fillColor: '#c6a43b',
      fillOpacity: 0.15,
      radius: radius,
      weight: 3,
      opacity: 0.6,
      className: 'trilogy-area'
    }).addTo(exploreMap);
    
    // Make the circle clickable - opens Trilogy modal
    trilogyCircle.on('click', () => {
      openMtnById(trilogyMtn.id);
      const tab = document.querySelector(`.wtab[data-id="${trilogyMtn.id}"]`);
      if(tab) selectWeatherTab(trilogyMtn.id, tab);
    });
    
    // Add hover effect for the circle
    trilogyCircle.on('mouseover', () => {
      trilogyCircle.setStyle({ fillOpacity: 0.3, weight: 4 });
      document.body.style.cursor = 'pointer';
    });
    trilogyCircle.on('mouseout', () => {
      trilogyCircle.setStyle({ fillOpacity: 0.15, weight: 3 });
      document.body.style.cursor = 'default';
    });
    
    // Add label for Trilogy area
    const trilogyLabel = L.marker([centerLat, centerLng], {
      icon: L.divIcon({
        html: `<div style="background: rgba(198,164,59,0.9); color: white; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; white-space: nowrap; border: 1px solid var(--gold); backdrop-filter: blur(4px);">⛰ TRILOGY AREA</div>`,
        className: '',
        iconSize: [120, 24],
        iconAnchor: [60, 12]
      })
    }).addTo(exploreMap);
    
    mapMarkers[trilogyMtn.id] = trilogyLabel;
  }
  
  // Now add individual markers for all mountains
  mountainCoords.forEach(mtn => {
    // Skip adding a separate marker for Trilogy since we have the circle
    if (mtn.is_trilogy || mtn.name.includes('Trilogy')) return;
    
    // Get appropriate icon color based on difficulty
    let markerColor = 'var(--ink)';
    if (mtn.difficulty === 'easy') markerColor = '#2a6b2a';
    else if (mtn.difficulty === 'moderate') markerColor = '#8a5a2a';
    else if (mtn.difficulty === 'hard') markerColor = '#a23b1a';
    
    // SVG mountain icon instead of emoji
    const svgIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 22h20L12 2z"/><path d="M12 2v20"/><path d="M2 22h20"/></svg>`;
    
    const icon = L.divIcon({
      html: `<div class="lk-marker" style="background: ${markerColor}; border: 3px solid var(--gold); width: 36px; height: 36px; font-size: 14px; display: flex; align-items: center; justify-content: center;">${svgIcon}</div>`,
      className: '',
      iconSize: [36, 36],
      iconAnchor: [18, 36],
      popupAnchor: [0, -40]
    });

    const crowdInfo = getCrowdLabel(mtn.crowd);
    const diffBg = mtn.difficulty==='easy'?'var(--g-easy);color:var(--t-easy)':mtn.difficulty==='moderate'?'var(--g-mod);color:var(--t-mod)':'var(--g-hard);color:var(--t-hard)';

    const popup = L.popup({className:'lk-popup', maxWidth:260, minWidth:220})
      .setContent(`
        <div class="popup-img" style="background-image:url('${mtn.image}'); height:100px;"></div>
        <div class="popup-inner">
          <div class="popup-name" style="font-size:15px;">${mtn.name}</div>
          <div class="popup-row">
            <span class="popup-badge" style="background:${diffBg}">${mtn.difficulty_display || mtn.difficulty}</span>
            ★ ${mtn.rating.toFixed(1)}
          </div>
          <div class="popup-row">📍 ${mtn.elevation} · ⏱ ${mtn.duration}</div>
          <div class="popup-crowd">
            <span class="popup-crowd-dot" style="background:${crowdInfo.dot}"></span>
            <span class="popup-crowd-label">${crowdInfo.label}</span>
          </div>
        </div>
        <button class="popup-btn" onclick="openMtnById(${mtn.id})">View Details →</button>
      `);

    const marker = L.marker([mtn.lat, mtn.lng], {icon}).addTo(exploreMap).bindPopup(popup);
    marker.on('click', ()=>{
      const tab = document.querySelector(`.wtab[data-id="${mtn.id}"]`);
      if(tab) selectWeatherTab(mtn.id, tab);
    });
    mapMarkers[mtn.id] = marker;
  });
  
  // Add Mt. Batulao marker separately
  const batulao = mountainCoords.find(m => m.name.includes('Batulao'));
  if (batulao) {
    const svgIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 22h20L12 2z"/><path d="M12 2v20"/><path d="M2 22h20"/></svg>`;
    
    const iconBatulao = L.divIcon({
      html: `<div class="lk-marker" style="background: #2a6b2a; border: 3px solid var(--gold); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">${svgIcon}</div>`,
      className: '',
      iconSize: [36, 36],
      iconAnchor: [18, 36],
      popupAnchor: [0, -40]
    });
    
    const crowdInfo = getCrowdLabel(batulao.crowd);
    const popup = L.popup({className:'lk-popup', maxWidth:260})
      .setContent(`
        <div class="popup-img" style="background-image:url('${batulao.image}'); height:100px;"></div>
        <div class="popup-inner">
          <div class="popup-name">${batulao.name}</div>
          <div class="popup-row"><span class="popup-badge" style="background:var(--g-easy);color:var(--t-easy)">Easy</span> ★ ${batulao.rating.toFixed(1)}</div>
          <div class="popup-row">📍 ${batulao.elevation} · ⏱ ${batulao.duration}</div>
          <div class="popup-crowd"><span class="popup-crowd-dot" style="background:${crowdInfo.dot}"></span> ${crowdInfo.label}</div>
        </div>
        <button class="popup-btn" onclick="openMtnById(${batulao.id})">View Details →</button>
      `);
      
    const marker = L.marker([batulao.lat, batulao.lng], {icon: iconBatulao}).addTo(exploreMap).bindPopup(popup);
    marker.on('click', () => {
      const tab = document.querySelector(`.wtab[data-id="${batulao.id}"]`);
      if(tab) selectWeatherTab(batulao.id, tab);
    });
    mapMarkers[batulao.id] = marker;
  }
  
  // Fit bounds to show all markers
  const allMarkers = Object.values(mapMarkers).filter(m => m.getLatLng);
  if (allMarkers.length > 0) {
    const bounds = L.latLngBounds(allMarkers.map(m => m.getLatLng()));
    exploreMap.fitBounds(bounds, { padding: [50, 50] });
  }
}
function openMtnById(id){
  const m = mountains.find(x=>x.id===id);
  if(m){ 
    // Close any leaflet popups
    if(exploreMap) exploreMap.closePopup();
    openMtnModal(m);
  }
}

// Load all weather on page load, starting with the first mountain
async function loadAllWeather(){
  for(const mtn of mountainCoords){
    const data = await fetchWeather(mtn.lat, mtn.lng);
    if(data) mountainWeatherCache[mtn.id] = data;
    // Render immediately if this is the active one
    if(mtn.id === activeWeatherMtnId) renderWeatherPanel(mtn.id);
  }
}


// ── GUIDE MODAL ──
const covers=['https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&q=60','https://images.unsplash.com/photo-1454496522485-0a69b2730d75?w=1200&q=60','https://images.unsplash.com/photo-1465919292275-c60ad29da028?w=1200&q=60'];
function openGuideModal(g){
  activeGuide=g;
  document.getElementById('gCoverImg').style.backgroundImage=`url('${g.cover||covers[g.id%covers.length]}')`;
  const av=document.getElementById('gModalAv');
  if(av) {
    if(g.avatar){ 
      const avatarUrl = '/' + g.avatar;
      av.style.backgroundImage=`url('${avatarUrl}')`;
      av.style.backgroundSize='cover';
      av.style.backgroundPosition='center';
      av.textContent=''; 
    } else { 
      av.style.backgroundImage='';
      av.textContent=g.initials; 
      av.style.backgroundColor=['#1a4d3a','#2c6e4f','#8a5a2a','#5a3a1a'][g.name.length%4]; 
    }
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
  
 // Render reviews with avatars
const revListHtml = g.reviews.length
  ? g.reviews.slice(0,4).map((r, idx) => {
      let revAvatarStyle = '';
      if (r.avatar) {
        const avatarUrl = '/' + r.avatar;
        revAvatarStyle = `background-image:url('${avatarUrl}');background-size:cover;background-position:center;`;
      } else {
        const colors = ['#1a4d3a','#2c6e4f','#8a5a2a','#5a3a1a'];
        revAvatarStyle = `background:${colors[idx % colors.length]};`;
      }
      return `<div class="g-rev-item">
        <div class="g-rev-hd">
          <div class="g-rev-av" style="${revAvatarStyle}">${r.avatar ? '' : esc(r.initials)}</div>
          <div><div class="g-rev-name">${esc(r.author)}</div><div class="g-rev-stars">${'★'.repeat(r.rating)+'☆'.repeat(5-r.rating)}</div></div>
          <div class="g-rev-date">${esc(r.date)}</div>
        </div>
        <div class="g-rev-comment">"${esc(r.comment)}"</div>
      </div>`;
    }).join('')
  : '<div style="padding:24px;text-align:center;color:var(--stone);font-size:13px;">No reviews yet.</div>';
  document.getElementById('gRevList').innerHTML = revListHtml;
  
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

function msgGuide(){ 
    if(!activeGuide) return; 
    // Pass guide ID and name as URL parameters to messages.php
    window.location.href = 'messages.php?guide=' + activeGuide.user_id + '&guide_name=' + encodeURIComponent(activeGuide.name); 
}

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
  // Init map & weather
  initExploreMap();
  loadAllWeather();
});
</script>
</body>
</html>