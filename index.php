<?php
// LAKBAY Landing Page - Database Connected Version
require_once __DIR__ . '/config/db.php';

// $pdo is already available from db.php - no need for Database class
// The connection is already established

// Fetch mountain count
$mountainQuery = "SELECT COUNT(*) as total FROM mountains WHERE status = 'Open'";
$mountainStmt = $pdo->prepare($mountainQuery);
$mountainStmt->execute();
$mountainCount = $mountainStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Fetch total hikers from users table (role = 'hiker')
$hikerQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'hiker'";
$hikerStmt = $pdo->prepare($hikerQuery);
$hikerStmt->execute();
$hikerCount = $hikerStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Fetch all mountains for display
$mountainsQuery = "SELECT id, name, difficulty, elevation, duration, rating, image, location, fee FROM mountains WHERE status = 'Open' ORDER BY rating DESC";
$mountainsStmt = $pdo->prepare($mountainsQuery);
$mountainsStmt->execute();
$mountains = $mountainsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating across all mountains
$avgRatingQuery = "SELECT AVG(rating) as avg_rating FROM mountains WHERE status = 'Open'";
$avgRatingStmt = $pdo->prepare($avgRatingQuery);
$avgRatingStmt->execute();
$avgRating = round($avgRatingStmt->fetch(PDO::FETCH_ASSOC)['avg_rating'], 1);

// Get recent hikers (limit 3 for testimonials)
$recentHikersQuery = "SELECT name FROM users WHERE role = 'hiker' ORDER BY created_at DESC LIMIT 3";
$recentHikersStmt = $pdo->prepare($recentHikersQuery);
$recentHikersStmt->execute();
$recentHikers = $recentHikersStmt->fetchAll(PDO::FETCH_ASSOC);

// Testimonials data
$testimonials = [
    ['name' => 'Maria Reyes', 'role' => 'First-time hiker', 'text' => 'LAKBAY made it so easy to find the right mountain for my skill level. The quiz was spot-on and the guide booking was seamless!', 'initial' => 'MR'],
    ['name' => 'James Tan', 'role' => 'Experienced mountaineer', 'text' => 'The real-time weather advisories saved our group from a sudden storm. Highly recommended for anyone planning a hike in Batangas.', 'initial' => 'JT'],
    ['name' => 'Carmen Diaz', 'role' => 'Weekend adventurer', 'text' => 'Booked a guide through LAKBAY and had an incredible experience. Our guide was knowledgeable and made the hike unforgettable.', 'initial' => 'CD']
];

// If we have real hikers, use their names for testimonials
if (count($recentHikers) >= 3) {
    $testimonials[0]['name'] = $recentHikers[0]['name'];
    $testimonials[1]['name'] = $recentHikers[1]['name'];
    $testimonials[2]['name'] = $recentHikers[2]['name'];
}

// Fetch approved system reviews
$stmt = $pdo->prepare("
    SELECT * FROM system_reviews 
    WHERE status = 'approved' 
    ORDER BY created_at DESC 
    LIMIT 6
");
$stmt->execute();
$systemReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY — Your Gateway to the Mountains of Nasugbu</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --espresso: #100600;
      --espresso-light: #2c1a0a;
      --gold: #c6a43b;
      --gold-light: #e0c268;
      --cream: #faf8f2;
      --stone: #5a5e5a;
      --sage: #6a8e6a;
      --mist: #e2e6e0;
      --white: #ffffff;
      --glass: rgba(255, 255, 245, 0.25);
      --glass-dark: rgba(16, 6, 0, 0.08);
      --glass-border: rgba(255, 255, 245, 0.4);
      --shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
      --shadow-lg: 0 30px 60px rgba(0, 0, 0, 0.12);
      --shadow-hover: 0 35px 70px rgba(0, 0, 0, 0.18);
      --radius: 28px;
      --radius-sm: 18px;
      --transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background: var(--cream);
      color: var(--espresso);
      overflow-x: hidden;
      line-height: 1.5;
      overflow-y: scroll;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    body::-webkit-scrollbar { display: none; }

    /* ─── NAVBAR ─── */
    .navbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 1000;
      padding: 18px 40px;
      background: rgba(250, 248, 243, 0.85);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 245, 0.3);
      transition: var(--transition);
    }
    .navbar:hover {
      background: rgba(250, 248, 243, 0.96);
      backdrop-filter: blur(24px);
      border-bottom-color: rgba(198, 164, 59, 0.3);
    }
    .navbar-inner {
      max-width: 1440px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: 'Playfair Display', serif;
      font-size: 26px;
      font-weight: 700;
      color: var(--espresso);
      text-decoration: none;
      transition: var(--transition);
    }
    .logo:hover { transform: scale(1.02); color: var(--gold); }
    .logo svg { width: 34px; height: 34px; transition: var(--transition); }
    .logo:hover svg path { fill: var(--gold); }
    .nav-actions { display: flex; gap: 14px; }
    .btn-login, .btn-signup {
      padding: 10px 26px;
      border-radius: 50px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      backdrop-filter: blur(4px);
    }
    .btn-login {
      background: rgba(250, 248, 243, 0.7);
      border: 1.5px solid var(--espresso);
      color: var(--espresso);
    }
    .btn-login:hover {
      background: var(--espresso);
      color: var(--cream);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(16, 6, 0, 0.15);
    }
    .btn-signup {
      background: var(--espresso);
      border: 1.5px solid var(--espresso);
      color: var(--cream);
    }
    .btn-signup:hover {
      background: var(--espresso-light);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(16, 6, 0, 0.25);
    }

    /* ─── HERO ─── */
    .hero {
      min-height: 100vh;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      overflow: hidden;
    }
    .hero-bg {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      object-fit: cover; z-index: 0;
      filter: brightness(0.65) contrast(1.1);
    }
    .hero-overlay {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      background: linear-gradient(135deg, rgba(16,6,0,0.55) 0%, rgba(26,46,26,0.6) 100%);
      z-index: 1;
    }
    .fog-layer {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      background: repeating-linear-gradient(90deg, transparent, rgba(255,255,255,0.08) 5%, rgba(200,210,200,0.05) 15%, transparent 25%);
      pointer-events: none; z-index: 2;
      animation: fogMove 20s ease-in-out infinite;
    }
    .fog-layer-2 {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      background: repeating-linear-gradient(120deg, transparent, rgba(230,240,230,0.06) 8%, rgba(255,255,245,0.04) 18%, transparent 30%);
      pointer-events: none; z-index: 2;
      animation: fogMoveReverse 25s ease-in-out infinite;
    }
    .fog-layer-3 {
      position: absolute; bottom: 0; left: 0;
      width: 100%; height: 60%;
      background: linear-gradient(to top, rgba(200,210,200,0.12), transparent);
      pointer-events: none; z-index: 2;
      animation: fogRise 8s ease-in-out infinite alternate;
    }
    @keyframes fogMove { 0%{transform:translateX(-5%) scaleX(1);opacity:.4} 50%{transform:translateX(5%) scaleX(1.05);opacity:.7} 100%{transform:translateX(-5%) scaleX(1);opacity:.4} }
    @keyframes fogMoveReverse { 0%{transform:translateX(8%) scaleX(1.1);opacity:.3} 50%{transform:translateX(-8%) scaleX(.95);opacity:.6} 100%{transform:translateX(8%) scaleX(1.1);opacity:.3} }
    @keyframes fogRise { 0%{transform:translateY(10%);opacity:.2} 100%{transform:translateY(-10%);opacity:.5} }
    .hero-content {
      position: relative; z-index: 3;
      max-width: 900px; margin: 0 auto;
      padding: 120px 24px 100px;
      animation: fadeInUp 0.8s ease;
    }
    .hero-tagline {
      font-size: 14px; letter-spacing: 4px; text-transform: uppercase;
      color: var(--gold); font-weight: 600; margin-bottom: 24px;
      display: inline-block; padding: 6px 18px; border-radius: 50px;
      background: rgba(0,0,0,0.2); backdrop-filter: blur(4px);
    }
    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(52px, 8vw, 88px); font-weight: 700;
      line-height: 1.1; margin-bottom: 24px; color: var(--white);
      text-shadow: 0 2px 20px rgba(0,0,0,0.3);
    }
    .animated-quote {
      font-size: 22px; font-style: italic;
      color: rgba(255,255,245,0.95); margin-bottom: 28px;
      border-left: 3px solid var(--gold); padding-left: 24px;
      max-width: 600px; margin-left: auto; margin-right: auto;
      animation: pulse 2.5s infinite;
    }
    @keyframes pulse { 0%,100%{border-left-color:var(--gold);opacity:.95} 50%{border-left-color:var(--gold-light);opacity:1} }
    .hero-description {
      font-size: 17px; color: rgba(255,255,245,0.85);
      line-height: 1.7; margin-bottom: 40px;
      max-width: 600px; margin-left: auto; margin-right: auto;
    }
    .hero-buttons { display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }
    .btn-primary, .btn-secondary {
      padding: 14px 36px; border-radius: 50px; font-size: 15px; font-weight: 600;
      text-decoration: none; transition: var(--transition);
      display: inline-flex; align-items: center; gap: 10px;
    }
    .btn-primary { background: var(--espresso); color: var(--cream); border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .btn-primary:hover { background: var(--espresso-light); transform: translateY(-3px) scale(1.02); box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
    .btn-secondary { background: rgba(255,255,245,0.18); backdrop-filter: blur(12px); border: 1.5px solid rgba(255,255,245,0.5); color: var(--white); }
    .btn-secondary:hover { background: rgba(255,255,245,0.3); transform: translateY(-3px) scale(1.02); }
    .hero-stats { display: flex; justify-content: center; gap: 50px; margin-top: 60px; flex-wrap: wrap; }
    .stat-glass {
      background: rgba(255,255,245,0.12); backdrop-filter: blur(10px);
      border-radius: 60px; padding: 12px 28px;
      border: 1px solid rgba(255,255,245,0.25); transition: var(--transition);
    }
    .stat-glass:hover { background: rgba(255,255,245,0.22); transform: translateY(-3px); }
    .stat-number { font-size: 28px; font-weight: 700; color: var(--gold); }
    .stat-label { font-size: 12px; color: rgba(255,255,245,0.8); margin-left: 8px; }

    /* ─── SHARED SECTION UTILITIES ─── */
    .section { padding: 90px 40px; position: relative; }
    .container { max-width: 1400px; margin: 0 auto; }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(30px, 4vw, 42px); font-weight: 700;
      margin-bottom: 16px; text-align: center;
    }
    .section-subtitle {
      text-align: center; color: var(--stone);
      margin-bottom: 56px; font-size: 17px;
      max-width: 700px; margin-left: auto; margin-right: auto;
    }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }

    /* ─── EVERYTHING YOU NEED ─── */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 24px;
      margin: 40px 0;
    }
    .feature-card {
      background: rgba(255,255,245,0.65);
      backdrop-filter: blur(16px);
      border-radius: var(--radius-sm);
      padding: 32px 20px;
      text-align: center;
      border: 1px solid rgba(255,255,245,0.5);
      box-shadow: 0 8px 20px rgba(0,0,0,0.05);
      transition: var(--transition);
      cursor: pointer;
      position: relative; overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute; top:-50%; left:-50%;
      width: 200%; height: 200%;
      background: radial-gradient(circle, rgba(198,164,59,0.08), transparent);
      opacity: 0; transition: opacity 0.5s;
    }
    .feature-card:hover::before { opacity: 1; }
    .feature-card:hover {
      transform: translateY(-10px) scale(1.02);
      background: rgba(255,255,245,0.85);
      box-shadow: var(--shadow-hover);
      border-color: var(--gold-light);
    }
    .feature-icon {
      width: 64px; height: 64px;
      background: rgba(198,164,59,0.15);
      backdrop-filter: blur(4px);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px; transition: var(--transition);
    }
    .feature-card:hover .feature-icon { background: rgba(198,164,59,0.3); transform: scale(1.1); }
    .feature-card h3 { font-size: 15px; margin-bottom: 10px; font-weight: 700; }
    .feature-card p { color: var(--stone); font-size: 12.5px; line-height: 1.6; }

    /* ─── MOUNTAINS CAROUSEL ─── */
    .mountains-section {
      background: var(--espresso);
      padding: 90px 0;
      position: relative;
      overflow: hidden;
    }
    .mountains-section::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; bottom: 0;
      background: radial-gradient(ellipse at 50% 0%, rgba(198,164,59,0.12), transparent 60%);
      pointer-events: none;
    }
    .mountains-header {
      text-align: center;
      padding: 0 40px;
      margin-bottom: 56px;
    }
    .mountains-header .section-title { color: var(--cream); }
    .mountains-header .section-subtitle { color: rgba(250,248,242,0.65); margin-bottom: 0; }

    .carousel-wrapper {
      position: relative;
      width: 100%;
    }
    .carousel-track-container {
      overflow: hidden;
      padding: 20px 40px 40px;
    }
    .carousel-track {
      display: flex;
      gap: 28px;
      transition: transform 0.55s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      will-change: transform;
    }
    .mtn-card {
      flex: 0 0 calc((100% - 56px) / 3);
      min-width: 0;
      background: rgba(255,255,255,0.06);
      backdrop-filter: blur(12px);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(0,0,0,0.35);
      transition: var(--transition);
      cursor: pointer;
      border: 1px solid rgba(255,255,245,0.1);
    }
    .mtn-card:hover {
      transform: translateY(-12px) scale(1.02);
      box-shadow: 0 24px 60px rgba(0,0,0,0.55);
      border-color: var(--gold);
      background: rgba(255,255,255,0.1);
    }
    .mtn-img {
      height: 240px;
      background-size: cover;
      background-position: center;
      position: relative;
      transition: transform 0.5s ease;
    }
    .mtn-card:hover .mtn-img { transform: scale(1.04); }
    .mtn-img-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(16,6,0,0.7) 0%, transparent 55%);
    }
    .mtn-badge {
      position: absolute; top: 16px; right: 16px;
      padding: 6px 16px; border-radius: 50px;
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      backdrop-filter: blur(4px); transition: var(--transition);
    }
    .mtn-card:hover .mtn-badge { transform: scale(1.05); }
    .badge-easy { background: rgba(217,234,211,0.92); color: #2a6b2a; }
    .badge-moderate { background: rgba(255,224,181,0.92); color: #8a5a2a; }
    .badge-hard { background: rgba(255,207,194,0.92); color: #a23b1a; }
    .mtn-info { padding: 24px; }
    .mtn-name {
      font-family: 'Playfair Display', serif;
      font-size: 22px; font-weight: 700;
      margin-bottom: 12px; color: var(--cream);
    }
    .mtn-meta-icons {
      display: flex; gap: 24px;
      margin: 16px 0; padding: 12px 0;
      border-top: 1px solid rgba(255,255,245,0.1);
      border-bottom: 1px solid rgba(255,255,245,0.1);
    }
    .meta-item {
      display: flex; align-items: center; gap: 8px;
      font-size: 13px; color: rgba(250,248,242,0.65);
    }
    .meta-item svg { width: 16px; height: 16px; stroke: var(--gold); stroke-width: 1.8; }
    .rating-wrapper {
      display: flex; align-items: center;
      justify-content: space-between; margin-top: 12px;
    }
    .rating { display: flex; align-items: center; gap: 6px; font-weight: 600; color: var(--gold); }
    .rating svg { width: 16px; height: 16px; fill: var(--gold); stroke: none; }
    .view-btn {
      background: transparent;
      border: 1.5px solid rgba(255,255,245,0.4);
      padding: 6px 16px; border-radius: 50px;
      font-size: 12px; font-weight: 600;
      color: rgba(255,255,245,0.8);
      transition: var(--transition);
    }
    .mtn-card:hover .view-btn { background: var(--gold); color: var(--espresso); border-color: var(--gold); }

    /* Carousel Controls */
    .carousel-controls {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      margin-top: 8px;
      padding-bottom: 12px;
    }
    .carousel-btn {
      width: 48px; height: 48px;
      border-radius: 50%;
      background: rgba(255,255,245,0.1);
      border: 1.5px solid rgba(255,255,245,0.2);
      color: var(--cream);
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: var(--transition);
      flex-shrink: 0;
    }
    .carousel-btn:hover { background: var(--gold); border-color: var(--gold); color: var(--espresso); transform: scale(1.1); }
    .carousel-btn:disabled { opacity: 0.3; cursor: default; transform: none; }
    .carousel-dots {
      display: flex; gap: 8px; align-items: center;
    }
    .carousel-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: rgba(255,255,245,0.25);
      transition: all 0.3s ease; cursor: pointer;
    }
    .carousel-dot.active { background: var(--gold); width: 24px; border-radius: 4px; }

    /* ─── GUIDE / CTA SECTION ─── */
    .guide-section { padding: 90px 40px; }
    .guide-inner {
      max-width: 1400px; margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 28px;
      align-items: stretch;
    }
    .cta-glass {
      background: rgba(16,6,0,0.88);
      backdrop-filter: blur(20px);
      border-radius: var(--radius);
      padding: 56px 44px;
      color: var(--cream);
      border: 1px solid rgba(255,255,245,0.15);
      transition: var(--transition);
      position: relative; overflow: hidden;
      display: flex; flex-direction: column; justify-content: center;
    }
    .cta-glass::before {
      content: '';
      position: absolute; top:-50%; left:-50%;
      width: 200%; height: 200%;
      background: radial-gradient(circle, rgba(198,164,59,0.1), transparent);
      animation: rotateGlow 12s linear infinite;
    }
    @keyframes rotateGlow { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    .cta-glass:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: var(--gold); }
    .cta-glass h2 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(26px, 2.8vw, 36px);
      margin-bottom: 12px; position: relative; z-index: 1;
    }
    .cta-glass p { opacity: 0.85; position: relative; z-index: 1; font-size: 15px; line-height: 1.7; }
    .cta-buttons { margin-top: 32px; display: flex; gap: 14px; flex-wrap: wrap; position: relative; z-index: 1; }
    .btn-outline-light {
      background: rgba(255,255,245,0.12); backdrop-filter: blur(8px);
      border: 1.5px solid var(--cream); color: var(--cream);
      padding: 12px 28px; border-radius: 50px;
      text-decoration: none; font-weight: 600; font-size: 14px;
      transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-outline-light:hover { background: var(--cream); color: var(--espresso); transform: translateY(-3px); }
    .cta-trust { margin-top: 24px; font-size: 13px; opacity: 0.6; position: relative; z-index: 1; }

    .guide-register-banner {
      background: linear-gradient(135deg, rgba(198,164,59,0.14), rgba(106,142,106,0.12));
      border: 1.5px solid rgba(198,164,59,0.4);
      border-radius: var(--radius);
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 28px;
    }
    .guide-register-banner .banner-icon {
      font-size: 40px; line-height: 1;
    }
    .guide-register-banner .banner-text h4 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(22px, 2.5vw, 30px);
      font-weight: 700; color: var(--espresso); margin-bottom: 12px;
    }
    .guide-register-banner .banner-text p {
      font-size: 15px; color: var(--stone); line-height: 1.7;
    }
    .btn-register-guide {
      background: var(--espresso); color: var(--cream);
      padding: 14px 32px; border-radius: 50px;
      font-size: 15px; font-weight: 600; text-decoration: none;
      transition: var(--transition); display: inline-flex;
      align-items: center; gap: 10px; align-self: flex-start;
    }
    .btn-register-guide:hover { background: var(--gold); color: var(--espresso); transform: translateY(-3px); box-shadow: 0 8px 24px rgba(198,164,59,0.3); }

    /* ─── ADVISORIES ─── */
    .guidelines-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px; margin-top: 40px;
    }
    .guideline-card {
      background: rgba(255,255,245,0.55);
      backdrop-filter: blur(14px);
      border-radius: var(--radius);
      padding: 32px;
      border: 1px solid rgba(255,255,245,0.5);
      box-shadow: 0 8px 20px rgba(0,0,0,0.05);
      transition: var(--transition);
    }
    .guideline-card:hover { transform: translateY(-8px); background: rgba(255,255,245,0.75); box-shadow: var(--shadow-hover); border-color: var(--gold-light); }
    .guideline-card h3 { font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .guideline-list { list-style: none; }
    .guideline-list li {
      padding: 10px 0; display: flex; align-items: center; gap: 12px;
      font-size: 14px; border-bottom: 1px solid rgba(16,6,0,0.06);
      transition: var(--transition);
    }
    .guideline-list li:hover { transform: translateX(6px); }

    /* ─── WHY LAKBAY ─── */
    .why-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 28px; margin-top: 40px;
    }
    .why-card {
      background: rgba(255,255,255,0.75);
      backdrop-filter: blur(12px);
      border-radius: var(--radius-sm);
      padding: 36px 28px;
      text-align: center;
      border: 1px solid rgba(255,255,245,0.6);
      box-shadow: var(--shadow);
      transition: var(--transition);
      position: relative; overflow: hidden;
      cursor: pointer;
    }
    .why-card:hover { transform: translateY(-10px); background: rgba(255,255,255,0.9); box-shadow: var(--shadow-hover); border-color: var(--gold); }
    .why-card .feature-icon { margin: 0 auto 24px; }
    .why-card h3 { font-size: 18px; margin-bottom: 14px; font-weight: 700; line-height: 1.3; }
    .why-card p { color: var(--stone); font-size: 13.5px; line-height: 1.7; }

    /* ─── TESTIMONIALS ─── */
    .testimonials-section { padding: 90px 40px; background: rgba(16,6,0,0.03); }
    .testimonials-inner { max-width: 1400px; margin: 0 auto; }

    .testimonials-carousel {
      position: relative;
      overflow: hidden;
      margin-top: 8px;
    }
    .testimonials-track {
      display: flex;
      gap: 28px;
      transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    .testimonial-card {
      flex: 0 0 calc((100% - 56px) / 3);
      min-width: 0;
      background: var(--white);
      border-radius: 24px;
      padding: 36px 32px;
      border: 1px solid rgba(16,6,0,0.07);
      box-shadow: 0 4px 24px rgba(0,0,0,0.06);
      transition: var(--transition);
      display: flex; flex-direction: column; gap: 16px;
      position: relative;
    }
    .testimonial-card::before {
      content: '"';
      position: absolute; top: 20px; right: 28px;
      font-family: 'Playfair Display', serif;
      font-size: 96px; line-height: 1;
      color: rgba(198,164,59,0.12);
      pointer-events: none;
    }
    .testimonial-card:hover { transform: translateY(-8px); box-shadow: 0 16px 48px rgba(0,0,0,0.12); border-color: var(--gold-light); }
    .testimonial-rating { font-size: 16px; color: var(--gold); letter-spacing: 2px; }
    .testimonial-title { font-size: 15px; font-weight: 700; color: var(--espresso); }
    .testimonial-text {
      font-style: italic; font-size: 14px; line-height: 1.75;
      color: var(--stone); flex: 1;
    }
    .testimonial-author {
      display: flex; align-items: center; gap: 14px;
      padding-top: 16px;
      border-top: 1px solid rgba(16,6,0,0.07);
    }
    .testimonial-author img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
    .author-avatar {
      width: 44px; height: 44px; min-width: 44px;
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: var(--espresso); font-weight: 700; font-size: 15px;
    }
    .author-name { font-size: 14px; font-weight: 700; color: var(--espresso); }
    .author-role { font-size: 12px; color: var(--stone); margin-top: 2px; }
    .author-verified { font-size: 11px; color: var(--sage); font-weight: 600; margin-top: 2px; }

    .testimonials-controls {
      display: flex; align-items: center;
      justify-content: center; gap: 20px;
      margin-top: 36px;
    }
    .t-btn {
      width: 48px; height: 48px; border-radius: 50%;
      background: var(--white);
      border: 1.5px solid rgba(16,6,0,0.15);
      color: var(--espresso); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: var(--transition);
    }
    .t-btn:hover { background: var(--espresso); color: var(--cream); border-color: var(--espresso); transform: scale(1.1); }
    .t-btn:disabled { opacity: 0.3; cursor: default; transform: none; }
    .t-dots { display: flex; gap: 8px; align-items: center; }
    .t-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: rgba(16,6,0,0.2); transition: all 0.3s ease; cursor: pointer;
    }
    .t-dot.active { background: var(--espresso); width: 24px; border-radius: 4px; }

    /* ─── FINAL CTA ─── */
    .final-cta-section { padding: 90px 40px; }
    .final-cta-inner {
      max-width: 800px; margin: 0 auto;
      text-align: center;
      background: linear-gradient(135deg, rgba(198,164,59,0.1), rgba(106,142,106,0.08));
      border: 1px solid rgba(198,164,59,0.25);
      border-radius: var(--radius);
      padding: 72px 56px;
    }
    .final-cta-inner .section-title { margin-bottom: 12px; }
    .final-cta-inner .section-subtitle { margin-bottom: 40px; }
    .final-cta-buttons { display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }
    .btn-cta-primary {
      padding: 16px 40px; border-radius: 50px;
      background: var(--espresso); color: var(--cream);
      font-size: 16px; font-weight: 600; text-decoration: none;
      transition: var(--transition); border: none;
    }
    .btn-cta-primary:hover { background: var(--espresso-light); transform: translateY(-3px); box-shadow: 0 8px 28px rgba(16,6,0,0.3); }
    .btn-cta-secondary {
      padding: 16px 40px; border-radius: 50px;
      background: transparent; color: var(--espresso);
      font-size: 16px; font-weight: 600; text-decoration: none;
      transition: var(--transition);
      border: 1.5px solid rgba(16,6,0,0.3);
    }
    .btn-cta-secondary:hover { background: rgba(16,6,0,0.06); transform: translateY(-3px); border-color: var(--espresso); }

    /* ─── FOOTER ─── */
    .footer { background: var(--espresso); color: var(--cream); padding: 60px 40px 30px; }
    .footer-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 40px; max-width: 1400px; margin: 0 auto;
    }
    .footer-col h4 { font-family: 'Playfair Display', serif; margin-bottom: 20px; font-size: 18px; }
    .footer-col p, .footer-col a { color: rgba(255,255,245,0.7); text-decoration: none; font-size: 14px; line-height: 1.8; transition: var(--transition); }
    .footer-col a:hover { color: var(--gold); transform: translateX(4px); display: inline-block; }
    .copyright { text-align: center; padding-top: 40px; margin-top: 40px; border-top: 1px solid rgba(255,255,245,0.1); font-size: 12px; color: rgba(255,255,245,0.5); }

    /* ─── RESPONSIVE ─── */
    @media (max-width: 1100px) {
      .features-grid { grid-template-columns: repeat(3, 1fr); }
      .why-grid { grid-template-columns: repeat(2, 1fr); }
      .guidelines-grid { grid-template-columns: 1fr 1fr; }
      .mtn-card { flex: 0 0 calc((100% - 28px) / 2); }
      .testimonial-card { flex: 0 0 calc((100% - 28px) / 2); }
      .guide-inner { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
      .navbar { padding: 14px 20px; }
      .section { padding: 64px 20px; }
      .guide-section { padding: 64px 20px; }
      .testimonials-section { padding: 64px 20px; }
      .final-cta-section { padding: 64px 20px; }
      .mountains-section { padding: 64px 0; }
      .hero-content { padding: 100px 20px 80px; }
      .hero-stats { gap: 20px; }
      .stat-glass { padding: 8px 20px; }
      .section-title { font-size: 30px; }

      /* Features: 2 col on tablet */
      .features-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
      /* Why: 1 col on mobile */
      .why-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
      /* Guidelines: 1 col */
      .guidelines-grid { grid-template-columns: 1fr; }

      /* Carousel: 1 card */
      .mtn-card { flex: 0 0 calc(100% - 16px); }
      .testimonial-card { flex: 0 0 calc(100% - 16px); }
      .carousel-track-container { padding: 20px 20px 30px; }

      /* CTA */
      .final-cta-inner { padding: 48px 28px; }
      .footer-grid { grid-template-columns: 1fr; gap: 28px; }
      .mountains-header { padding: 0 20px; }
      .cta-glass { padding: 40px 28px; }
      .guide-register-banner { padding: 36px 28px; }
    }

    @media (max-width: 480px) {
      .features-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
      .feature-card { padding: 22px 14px; }
      .why-grid { grid-template-columns: 1fr; }
      .btn-cta-primary, .btn-cta-secondary { padding: 14px 28px; font-size: 14px; }
    }
  </style>
</head>
<body>

<!-- ─── NAVIGATION ─── -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="logo">
      <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
      LAKBAY
    </a>
    <div class="nav-actions">
      <a href="login-and-signup/login.php" class="btn-login">Log In</a>
      <a href="login-and-signup/login.php?action=signup" class="btn-signup">Sign Up</a>
    </div>
  </div>
</nav>

<!-- ─── 1. HERO ─── -->
<section class="hero">
  <img class="hero-bg" src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1600&q=80" alt="Mountain landscape">
  <div class="hero-overlay"></div>
  <div class="fog-layer"></div>
  <div class="fog-layer-2"></div>
  <div class="fog-layer-3"></div>
  <div class="hero-content">
    <div class="hero-tagline">SILENCE BENEATH STEPS</div>
    <h1 class="hero-title">LAKBAY</h1>
    <div class="animated-quote">"Not all who wander are lost"</div>
    <p class="hero-description">Your trusted companion for discovering the perfect mountain trails in Nasugbu, Batangas. Smart recommendations, verified guides, and real-time safety — all in one platform.</p>
    <div class="hero-buttons">
      <a href="login-and-signup/login.php?redirect=quiz" class="btn-primary" id="findTrailBtn">Find My Trail</a>
      <a href="#" class="btn-secondary" id="exploreMountainsBtn">Explore Nasugbu Mountains →</a>
    </div>
    <div class="hero-stats">
      <div class="stat-glass"><span class="stat-number"><?php echo $mountainCount; ?></span><span class="stat-label">Mountains</span></div>
      <div class="stat-glass"><span class="stat-number"><?php echo $hikerCount; ?>+</span><span class="stat-label">Happy Hikers</span></div>
      <div class="stat-glass"><span class="stat-number"><?php echo $avgRating; ?></span><span class="stat-label">★ Avg Rating</span></div>
    </div>
  </div>
</section>

<!-- ─── 2. EVERYTHING YOU NEED ─── -->
<section class="section">
  <div class="container">
    <h2 class="section-title">Everything You Need for Your Hike</h2>
    <p class="section-subtitle">LAKBAY provides comprehensive tools and information for a safe and enjoyable hiking experience.</p>
    <div class="features-grid">
      <div class="feature-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="14" r="2"/></svg></div>
        <h3>Trail Discovery</h3>
        <p>Explore detailed information about <?php echo $mountainCount; ?> stunning mountains in Nasugbu, Batangas.</p>
      </div>
      <div class="feature-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
        <h3>Personalized Quiz</h3>
        <p>Take our trail-matching quiz to find the perfect mountain for your experience level.</p>
      </div>
      <div class="feature-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg></div>
        <h3>Verified Guides</h3>
        <p>Connect with experienced local guides for a safe and authentic hiking experience.</p>
      </div>
      <div class="feature-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg></div>
        <h3>Real-time Updates</h3>
        <p>Get live weather advisories and trail condition updates before your hike.</p>
      </div>
      <div class="feature-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
        <h3>Easy Booking</h3>
        <p>Book guides and manage your hiking schedule all in one seamless platform.</p>
      </div>
    </div>
  </div>
</section>

<!-- ─── 3. OUR MOUNTAINS CAROUSEL ─── -->
<section class="mountains-section" id="mountains-section">
  <div class="mountains-header">
    <h2 class="section-title">Our Mountains</h2>
    <p class="section-subtitle">Discover Batangas' most treasured peaks — each with unique character and breathtaking views</p>
  </div>

  <div class="carousel-wrapper" id="mountainCarousel">
    <div class="carousel-track-container">
      <div class="carousel-track" id="mountainTrack">
        <?php foreach ($mountains as $mountain):
          $difficulty = strtolower(htmlspecialchars($mountain['difficulty']));
          $badgeClass = 'easy';
          if (strpos($difficulty, 'moderate') !== false) $badgeClass = 'moderate';
          if (strpos($difficulty, 'hard') !== false || strpos($difficulty, 'difficult') !== false) $badgeClass = 'hard';
        ?>
        <div class="mtn-card" onclick="redirectToExplore()">
          <div class="mtn-img" style="background-image: url('<?php echo htmlspecialchars($mountain['image']); ?>')">
            <div class="mtn-img-overlay"></div>
            <span class="mtn-badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($mountain['difficulty']); ?></span>
          </div>
          <div class="mtn-info">
            <div class="mtn-name"><?php echo htmlspecialchars($mountain['name']); ?></div>
            <div class="mtn-meta-icons">
              <div class="meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="14" r="1.5"/></svg><span><?php echo htmlspecialchars($mountain['elevation']); ?></span></div>
              <div class="meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span><?php echo htmlspecialchars($mountain['duration']); ?></span></div>
            </div>
            <div class="rating-wrapper">
              <div class="rating"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span><?php echo number_format($mountain['rating'], 1); ?></span></div>
              <div class="view-btn">View Details</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="carousel-controls">
      <button class="carousel-btn" id="mtnPrev" aria-label="Previous">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <div class="carousel-dots" id="mtnDots"></div>
      <button class="carousel-btn" id="mtnNext" aria-label="Next">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
  </div>
</section>

<!-- ─── 4. FIND A GUIDE + REGISTER AS GUIDE ─── -->
<section class="guide-section">
  <div class="guide-inner">
    <div class="cta-glass">
      <h2>Find a Local Guide</h2>
      <p>Certified local guides who know every trail like the back of their hand. Book with confidence and hike with peace of mind.</p>
      <div class="cta-buttons">
        <a href="javascript:void(0);" onclick="redirectToLogin()" class="btn-outline-light">Browse Verified Guides →</a>
        <a href="javascript:void(0);" onclick="redirectToLogin()" class="btn-outline-light">Book Your Hike</a>
      </div>
      <p class="cta-trust">✓ Verified Guides &nbsp; ✓ Best Rates &nbsp; ✓ 24/7 Support</p>
    </div>

    <div class="guide-register-banner">
      <div class="banner-icon">🧭</div>
      <div class="banner-text">
        <h4>Are you a local guide?</h4>
        <p>Share your expertise with hikers. Register as an LAKBAY-verified guide and start earning from your passion for the mountains.</p>
      </div>
      <a href="guide-registration.php" class="btn-register-guide">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        Register as a Guide
      </a>
    </div>
  </div>
</section>

<!-- ─── 5. ADVISORIES & PROTOCOLS ─── -->
<section class="section" style="background: rgba(198,164,59,0.04);">
  <div class="container">
    <h2 class="section-title">Advisories & Protocols</h2>
    <p class="section-subtitle">Important guidelines for responsible and safe hiking in Nasugbu</p>
    <div class="guidelines-grid">
      <div class="guideline-card">
        <h3><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2c5e2c" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg> Environmental Protection</h3>
        <ul class="guideline-list">
          <li>✓ Practice "Leave No Trace" — pack out all trash</li>
          <li>✓ Stay on designated trails to prevent erosion</li>
          <li>✓ Respect wildlife and natural habitats</li>
          <li>✓ Use eco-friendly products only</li>
        </ul>
      </div>
      <div class="guideline-card">
        <h3><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg> Safety Protocols</h3>
        <ul class="guideline-list">
          <li>✓ Register at barangay hall before hiking</li>
          <li>✓ Hire local guides for first-time visits</li>
          <li>✓ Bring sufficient water and sun protection</li>
          <li>✓ Check weather conditions before departure</li>
        </ul>
      </div>
      <div class="guideline-card">
        <h3><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8a5a2a" stroke-width="1.5"><path d="M3 12h18M12 3v18"/></svg> Trail Regulations</h3>
        <ul class="guideline-list">
          <li>✓ Follow posted signs and trail markers</li>
          <li>✓ No littering — violators will be fined</li>
          <li>✓ Respect private properties along the trail</li>
          <li>✓ Follow group size limitations per trail</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ─── 6. WHY LAKBAY ─── -->
<section class="section">
  <div class="container">
    <h2 class="section-title">Why Use LAKBAY?</h2>
    <p class="section-subtitle">Your trusted companion for exploring Nasugbu's mountains responsibly</p>
    <div class="why-grid">
      <div class="why-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="14" r="2"/></svg></div>
        <h3>Comprehensive Trail Information</h3>
        <p>Detailed guides including difficulty levels, duration, fees, and environmental protocols for all <?php echo $mountainCount; ?> mountains.</p>
      </div>
      <div class="why-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg></div>
        <h3>Safety First Approach</h3>
        <p>Real-time weather updates, hazard warnings, and a verified guide system to keep you protected on the trail.</p>
      </div>
      <div class="why-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
        <h3>Easy Booking System</h3>
        <p>Book guides, manage your hiking schedule, and receive confirmations — all in one seamless platform.</p>
      </div>
      <div class="why-card" onclick="redirectToLogin()">
        <div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg></div>
        <h3>Environmental Stewardship</h3>
        <p>Promoting responsible hiking with Leave No Trace principles to preserve Nasugbu's beauty for future generations.</p>
      </div>
    </div>
  </div>
</section>

<!-- ─── 7. WHAT HIKERS SAY ─── -->
<section class="testimonials-section">
  <div class="testimonials-inner">
    <h2 class="section-title">What Hikers Say</h2>
    <p class="section-subtitle">Real stories from adventurers who found their perfect trail with LAKBAY</p>

    <div class="testimonials-carousel">
      <div class="testimonials-track" id="testimonialsTrack">
        <?php foreach ($systemReviews as $review): ?>
        <div class="testimonial-card">
          <div class="testimonial-rating"><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></div>
          <?php if ($review['title']): ?>
          <h4 class="testimonial-title"><?php echo htmlspecialchars($review['title']); ?></h4>
          <?php endif; ?>
          <p class="testimonial-text"><?php echo htmlspecialchars($review['comment']); ?></p>
          <div class="testimonial-author">
            <?php if ($review['user_avatar']): ?>
            <img src="<?php echo htmlspecialchars($review['user_avatar']); ?>" alt="<?php echo htmlspecialchars($review['user_name']); ?>">
            <?php else: ?>
            <div class="author-avatar"><?php echo substr($review['user_name'], 0, 1); ?></div>
            <?php endif; ?>
            <div>
              <div class="author-name"><?php echo htmlspecialchars($review['user_name']); ?></div>
              <div class="author-verified">✓ Verified Hiker</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($systemReviews)): // Fallback testimonials if no reviews yet ?>
        <?php foreach ($testimonials as $t): ?>
        <div class="testimonial-card">
          <div class="testimonial-rating">★★★★★</div>
          <p class="testimonial-text"><?php echo htmlspecialchars($t['text']); ?></p>
          <div class="testimonial-author">
            <div class="author-avatar"><?php echo htmlspecialchars($t['initial']); ?></div>
            <div>
              <div class="author-name"><?php echo htmlspecialchars($t['name']); ?></div>
              <div class="author-role"><?php echo htmlspecialchars($t['role']); ?></div>
              <div class="author-verified">✓ Verified Hiker</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="testimonials-controls">
      <button class="t-btn" id="tPrev" aria-label="Previous">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <div class="t-dots" id="tDots"></div>
      <button class="t-btn" id="tNext" aria-label="Next">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
  </div>
</section>

<!-- ─── 8. START YOUR JOURNEY (FINAL CTA) ─── -->
<section class="final-cta-section">
  <div class="final-cta-inner">
    <h2 class="section-title">Start Your Hiking Journey Today</h2>
    <p class="section-subtitle">Join <?php echo number_format($hikerCount); ?>+ hikers who trust LAKBAY for their mountain adventures</p>
    <div class="final-cta-buttons">
      <a href="login-and-signup/login.php?action=signup" class="btn-cta-primary">Sign Up — It's Free</a>
      <a href="#" class="btn-cta-secondary" id="finalExploreBtn">Explore Mountains</a>
    </div>
  </div>
</section>

<!-- ─── FOOTER ─── -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-col">
      <h4>About LAKBAY</h4>
      <p>Your trusted guide to the mountains of Nasugbu, Batangas. We connect adventurers with safe, memorable hiking experiences.</p>
    </div>
    <div class="footer-col">
      <h4>Contact Us</h4>
      <p>Tourism Office, Nasugbu, Batangas, Philippines</p>
      <p>2nd Floor, Municipal Hall of Nasugbu, Batangas</p>
      <p>Email: tourismoffice@lakbaynasugbu.com</p>
      <p>Hours: Monday–Friday: 8 AM – 5 PM</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <p><a href="javascript:void(0);" onclick="redirectToLogin()">Explore Mountains</a></p>
      <p><a href="javascript:void(0);" onclick="redirectToLogin()">Take the Quiz</a></p>
      <p><a href="javascript:void(0);" onclick="redirectToLogin()">Book a Guide</a></p>
    </div>
  </div>
  <div class="copyright">© <?php echo date('Y'); ?> LAKBAY — Nasugbu Tourism. All rights reserved.</div>
</footer>

<script>
  /* ─── Redirects ─── */
  function redirectToLogin() { window.location.href = 'login-and-signup/login.php'; }
  function redirectToExplore() { window.location.href = 'login-and-signup/login.php?redirect=explore'; }

  /* ─── Hero buttons ─── */
  document.getElementById('findTrailBtn')?.addEventListener('click', e => {
    e.preventDefault();
    window.location.href = 'login-and-signup/login.php?redirect=quiz';
  });
  const scrollToMountains = e => {
    e.preventDefault();
    document.getElementById('mountains-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  document.getElementById('exploreMountainsBtn')?.addEventListener('click', scrollToMountains);
  document.getElementById('finalExploreBtn')?.addEventListener('click', scrollToMountains);

  /* ─── Generic Carousel factory ─── */
  function initCarousel({ trackId, dotsId, prevId, nextId, cardSelector, itemsVisible }) {
    const track = document.getElementById(trackId);
    const dotsContainer = document.getElementById(dotsId);
    const prevBtn = document.getElementById(prevId);
    const nextBtn = document.getElementById(nextId);
    if (!track) return;

    const cards = track.querySelectorAll(cardSelector);
    const total = cards.length;
    let current = 0;

    function getVisible() {
      if (typeof itemsVisible === 'function') return itemsVisible();
      return itemsVisible;
    }

    function maxIndex() { return Math.max(0, total - getVisible()); }

    function buildDots() {
      if (!dotsContainer) return;
      dotsContainer.innerHTML = '';
      const pages = maxIndex() + 1;
      for (let i = 0; i < pages; i++) {
        const d = document.createElement('div');
        d.className = 'carousel-dot' + (i === current ? ' active' : '');
        d.addEventListener('click', () => goTo(i));
        dotsContainer.appendChild(d);
      }
    }

    function updateDots() {
      if (!dotsContainer) return;
      dotsContainer.querySelectorAll('.carousel-dot').forEach((d, i) => {
        d.classList.toggle('active', i === current);
      });
    }

    function goTo(idx) {
      current = Math.max(0, Math.min(idx, maxIndex()));
      const cardWidth = cards[0].getBoundingClientRect().width;
      const gap = 28;
      track.style.transform = `translateX(-${current * (cardWidth + gap)}px)`;
      if (prevBtn) prevBtn.disabled = current === 0;
      if (nextBtn) nextBtn.disabled = current >= maxIndex();
      updateDots();
    }

    prevBtn?.addEventListener('click', () => goTo(current - 1));
    nextBtn?.addEventListener('click', () => goTo(current + 1));

    // Reset on resize
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        buildDots();
        goTo(Math.min(current, maxIndex()));
      }, 150);
    });

    buildDots();
    goTo(0);
  }

  // Mountains carousel
  initCarousel({
    trackId: 'mountainTrack',
    dotsId: 'mtnDots',
    prevId: 'mtnPrev',
    nextId: 'mtnNext',
    cardSelector: '.mtn-card',
    itemsVisible: () => window.innerWidth <= 768 ? 1 : window.innerWidth <= 1100 ? 2 : 3
  });

  // Testimonials carousel — reuse t-dot class
  (function() {
    const track = document.getElementById('testimonialsTrack');
    const dotsContainer = document.getElementById('tDots');
    const prevBtn = document.getElementById('tPrev');
    const nextBtn = document.getElementById('tNext');
    if (!track) return;

    const cards = track.querySelectorAll('.testimonial-card');
    const total = cards.length;
    let current = 0;

    function getVisible() { return window.innerWidth <= 768 ? 1 : window.innerWidth <= 1100 ? 2 : 3; }
    function maxIndex() { return Math.max(0, total - getVisible()); }

    function buildDots() {
      if (!dotsContainer) return;
      dotsContainer.innerHTML = '';
      for (let i = 0; i <= maxIndex(); i++) {
        const d = document.createElement('div');
        d.className = 't-dot' + (i === current ? ' active' : '');
        d.addEventListener('click', () => goTo(i));
        dotsContainer.appendChild(d);
      }
    }

    function updateDots() {
      dotsContainer?.querySelectorAll('.t-dot').forEach((d, i) => d.classList.toggle('active', i === current));
    }

    function goTo(idx) {
      current = Math.max(0, Math.min(idx, maxIndex()));
      const cardWidth = cards[0].getBoundingClientRect().width;
      const gap = 28;
      track.style.transform = `translateX(-${current * (cardWidth + gap)}px)`;
      if (prevBtn) prevBtn.disabled = current === 0;
      if (nextBtn) nextBtn.disabled = current >= maxIndex();
      updateDots();
    }

    prevBtn?.addEventListener('click', () => goTo(current - 1));
    nextBtn?.addEventListener('click', () => goTo(current + 1));

    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => { buildDots(); goTo(Math.min(current, maxIndex())); }, 150);
    });

    buildDots();
    goTo(0);
  })();
</script>
</body>
</html>