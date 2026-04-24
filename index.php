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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>LAKBAY — Your Gateway to the Mountains of Nasugbu</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* HIDE SCROLLBAR BUT KEEP SCROLLING FUNCTIONALITY */
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
    body::-webkit-scrollbar {
      display: none;
    }

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

    /* Navigation */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      padding: 18px 40px;
      background: rgba(250, 248, 243, 0.85);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 245, 0.3);
      transition: var(--transition);
    }
    .navbar:hover { background: rgba(250, 248, 243, 0.96); backdrop-filter: blur(24px); border-bottom-color: rgba(198, 164, 59, 0.3); }

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

    /* Hero Section with Fog Animation */
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
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: 0;
      filter: brightness(0.65) contrast(1.1);
    }
    .hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, rgba(16, 6, 0, 0.55) 0%, rgba(26, 46, 26, 0.6) 100%);
      z-index: 1;
    }
    
    /* Fog/Cloud Animation Layers */
    .fog-layer {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: repeating-linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.08) 5%,
        rgba(200, 210, 200, 0.05) 15%,
        transparent 25%
      );
      pointer-events: none;
      z-index: 2;
      animation: fogMove 20s ease-in-out infinite;
    }
    .fog-layer-2 {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: repeating-linear-gradient(
        120deg,
        transparent,
        rgba(230, 240, 230, 0.06) 8%,
        rgba(255, 255, 245, 0.04) 18%,
        transparent 30%
      );
      pointer-events: none;
      z-index: 2;
      animation: fogMoveReverse 25s ease-in-out infinite;
    }
    .fog-layer-3 {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 60%;
      background: linear-gradient(to top, rgba(200, 210, 200, 0.12), transparent);
      pointer-events: none;
      z-index: 2;
      animation: fogRise 8s ease-in-out infinite alternate;
    }
    @keyframes fogMove {
      0% { transform: translateX(-5%) scaleX(1); opacity: 0.4; }
      50% { transform: translateX(5%) scaleX(1.05); opacity: 0.7; }
      100% { transform: translateX(-5%) scaleX(1); opacity: 0.4; }
    }
    @keyframes fogMoveReverse {
      0% { transform: translateX(8%) scaleX(1.1); opacity: 0.3; }
      50% { transform: translateX(-8%) scaleX(0.95); opacity: 0.6; }
      100% { transform: translateX(8%) scaleX(1.1); opacity: 0.3; }
    }
    @keyframes fogRise {
      0% { transform: translateY(10%); opacity: 0.2; }
      100% { transform: translateY(-10%); opacity: 0.5; }
    }

    .hero-content {
      position: relative;
      z-index: 3;
      max-width: 900px;
      margin: 0 auto;
      padding: 120px 24px 100px;
      animation: fadeInUp 0.8s ease;
    }
    .hero-tagline {
      font-size: 14px;
      letter-spacing: 4px;
      text-transform: uppercase;
      color: var(--gold);
      font-weight: 600;
      margin-bottom: 24px;
      backdrop-filter: blur(4px);
      display: inline-block;
      padding: 6px 18px;
      border-radius: 50px;
      background: rgba(0,0,0,0.2);
    }
    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(52px, 8vw, 88px);
      font-weight: 700;
      line-height: 1.1;
      margin-bottom: 24px;
      color: var(--white);
      text-shadow: 0 2px 20px rgba(0,0,0,0.3);
    }
    .animated-quote {
      font-size: 22px;
      font-style: italic;
      color: rgba(255,255,245,0.95);
      margin-bottom: 28px;
      border-left: 3px solid var(--gold);
      padding-left: 24px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
      animation: pulse 2.5s infinite;
    }
    @keyframes pulse {
      0%, 100% { border-left-color: var(--gold); opacity: 0.95; }
      50% { border-left-color: var(--gold-light); opacity: 1; }
    }
    .hero-description {
      font-size: 17px;
      color: rgba(255,255,245,0.85);
      line-height: 1.7;
      margin-bottom: 40px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }
    .hero-buttons { display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }
    .btn-primary, .btn-secondary {
      padding: 14px 36px;
      border-radius: 50px;
      font-size: 15px;
      font-weight: 600;
      text-decoration: none;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    .btn-primary {
      background: var(--espresso);
      color: var(--cream);
      border: none;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .btn-primary:hover {
      background: var(--espresso-light);
      transform: translateY(-3px) scale(1.02);
      box-shadow: 0 8px 30px rgba(0,0,0,0.4);
    }
    .btn-secondary {
      background: rgba(255,255,245,0.18);
      backdrop-filter: blur(12px);
      border: 1.5px solid rgba(255,255,245,0.5);
      color: var(--white);
    }
    .btn-secondary:hover {
      background: rgba(255,255,245,0.3);
      transform: translateY(-3px) scale(1.02);
      backdrop-filter: blur(16px);
    }
    .hero-stats {
      display: flex;
      justify-content: center;
      gap: 50px;
      margin-top: 60px;
      flex-wrap: wrap;
    }
    .stat-glass {
      background: rgba(255,255,245,0.12);
      backdrop-filter: blur(10px);
      border-radius: 60px;
      padding: 12px 28px;
      border: 1px solid rgba(255,255,245,0.25);
      transition: var(--transition);
    }
    .stat-glass:hover {
      background: rgba(255,255,245,0.22);
      transform: translateY(-3px);
      backdrop-filter: blur(14px);
    }
    .stat-number { font-size: 28px; font-weight: 700; color: var(--gold); }
    .stat-label { font-size: 12px; color: rgba(255,255,245,0.8); margin-left: 8px; }

    /* Section Styles */
    .section { padding: 80px 40px; position: relative; }
    .container { max-width: 1400px; margin: 0 auto; }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 16px;
      text-align: center;
    }
    .section-subtitle {
      text-align: center;
      color: var(--stone);
      margin-bottom: 56px;
      font-size: 17px;
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
    }

    /* Floating Decorative Elements */
    .floating-icon {
      position: absolute;
      opacity: 0.08;
      pointer-events: none;
      z-index: 0;
    }
    .float-1 { top: 10%; left: 5%; width: 80px; animation: float 8s ease-in-out infinite; }
    .float-2 { bottom: 15%; right: 8%; width: 100px; animation: float 10s ease-in-out infinite reverse; }
    .float-3 { top: 30%; right: 12%; width: 60px; animation: float 6s ease-in-out infinite 1s; }
    .float-4 { bottom: 20%; left: 10%; width: 70px; animation: float 7s ease-in-out infinite 0.5s; }
    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(5deg); }
    }

    /* Features Grid */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 30px;
      margin: 40px 0;
    }
    .feature-card {
      background: rgba(255, 255, 245, 0.65);
      backdrop-filter: blur(16px);
      border-radius: var(--radius-sm);
      padding: 32px 24px;
      text-align: center;
      border: 1px solid rgba(255, 255, 245, 0.5);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
      transition: var(--transition);
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(198,164,59,0.08), transparent);
      opacity: 0;
      transition: opacity 0.5s;
    }
    .feature-card:hover::before { opacity: 1; }
    .feature-card:hover {
      transform: translateY(-10px) scale(1.02);
      background: rgba(255, 255, 245, 0.85);
      backdrop-filter: blur(20px);
      box-shadow: var(--shadow-hover);
      border-color: var(--gold-light);
    }
    .feature-icon {
      width: 64px;
      height: 64px;
      background: rgba(198, 164, 59, 0.15);
      backdrop-filter: blur(4px);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      transition: var(--transition);
    }
    .feature-card:hover .feature-icon {
      background: rgba(198, 164, 59, 0.3);
      transform: scale(1.1);
    }
    .feature-card h3 { font-size: 18px; margin-bottom: 12px; font-weight: 700; }
    .feature-card p { color: var(--stone); font-size: 13px; line-height: 1.6; }

    /* Guidelines Grid */
    .guidelines-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }
    .guideline-card {
      background: rgba(255, 255, 245, 0.55);
      backdrop-filter: blur(14px);
      border-radius: var(--radius);
      padding: 30px;
      border: 1px solid rgba(255, 255, 245, 0.5);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
      transition: var(--transition);
    }
    .guideline-card:hover {
      transform: translateY(-8px);
      background: rgba(255, 255, 245, 0.75);
      backdrop-filter: blur(20px);
      box-shadow: var(--shadow-hover);
      border-color: var(--gold-light);
    }
    .guideline-card h3 {
      font-size: 22px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .guideline-list { list-style: none; }
    .guideline-list li {
      padding: 10px 0;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      border-bottom: 1px solid rgba(16, 6, 0, 0.06);
      transition: var(--transition);
    }
    .guideline-list li:hover { transform: translateX(6px); color: var(--espresso); }

    /* Why LAKBAY Grid - Improved Spacing */
    .why-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }
    .why-card {
      background: rgba(255, 255, 255, 0.75);
      backdrop-filter: blur(12px);
      border-radius: var(--radius-sm);
      padding: 32px;
      text-align: center;
      border: 1px solid rgba(255, 255, 245, 0.6);
      box-shadow: var(--shadow);
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }
    .why-card:hover {
      transform: translateY(-10px);
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(16px);
      box-shadow: var(--shadow-hover);
      border-color: var(--gold);
    }
    .why-card .feature-icon { margin: 0 auto 24px; }
    .why-card h3 { 
      font-size: 20px; 
      margin-bottom: 20px; 
      font-weight: 700;
      line-height: 1.3;
    }
    .why-card p { 
      color: var(--stone); 
      font-size: 14px; 
      line-height: 1.7;
      margin-top: 8px;
    }

    /* Mountains Grid */
    .mountains-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 35px;
      margin: 40px 0;
    }
    .mtn-card {
      background: rgba(255, 255, 255, 0.88);
      backdrop-filter: blur(12px);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: var(--transition);
      cursor: pointer;
      border: 1px solid rgba(255, 255, 245, 0.5);
    }
    .mtn-card:hover {
      transform: translateY(-12px) scale(1.02);
      box-shadow: var(--shadow-hover);
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(16px);
      border-color: var(--gold);
    }
    .mtn-img {
      height: 220px;
      background-size: cover;
      background-position: center;
      position: relative;
      transition: transform 0.5s ease;
    }
    .mtn-card:hover .mtn-img { transform: scale(1.03); }
    .mtn-badge {
      position: absolute;
      top: 16px;
      right: 16px;
      padding: 6px 16px;
      border-radius: 50px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      backdrop-filter: blur(4px);
      transition: var(--transition);
    }
    .mtn-card:hover .mtn-badge { transform: scale(1.05); }
    .badge-easy { background: rgba(217, 234, 211, 0.95); color: #2a6b2a; }
    .badge-moderate { background: rgba(255, 224, 181, 0.95); color: #8a5a2a; }
    .badge-hard { background: rgba(255, 207, 194, 0.95); color: #a23b1a; }
    .mtn-info { padding: 24px; }
    .mtn-name {
      font-family: 'Playfair Display', serif;
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 12px;
      color: var(--espresso);
    }
    .mtn-meta-icons {
      display: flex;
      gap: 24px;
      margin: 16px 0;
      padding: 12px 0;
      border-top: 1px solid rgba(16, 6, 0, 0.08);
      border-bottom: 1px solid rgba(16, 6, 0, 0.08);
    }
    .meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--stone);
    }
    .meta-item svg {
      width: 16px;
      height: 16px;
      stroke: var(--gold);
      stroke-width: 1.8;
    }
    .rating-wrapper {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 12px;
    }
    .rating {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      color: var(--gold);
    }
    .rating svg {
      width: 16px;
      height: 16px;
      fill: var(--gold);
      stroke: none;
    }
    .view-btn {
      background: transparent;
      border: 1.5px solid var(--espresso);
      padding: 6px 16px;
      border-radius: 50px;
      font-size: 12px;
      font-weight: 600;
      color: var(--espresso);
      transition: var(--transition);
    }
    .mtn-card:hover .view-btn {
      background: var(--espresso);
      color: var(--cream);
    }

    /* CTA Glass Section */
    .cta-glass {
      background: rgba(16, 6, 0, 0.88);
      backdrop-filter: blur(20px);
      border-radius: var(--radius);
      padding: 60px 40px;
      text-align: center;
      color: var(--cream);
      margin: 40px 0;
      border: 1px solid rgba(255, 255, 245, 0.25);
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }
    .cta-glass::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(198,164,59,0.1), transparent);
      animation: rotateGlow 12s linear infinite;
    }
    @keyframes rotateGlow {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .cta-glass:hover {
      transform: scale(1.01);
      background: rgba(16, 6, 0, 0.94);
      backdrop-filter: blur(24px);
      box-shadow: var(--shadow-hover);
      border-color: var(--gold);
    }
    .cta-glass h2 { font-family: 'Playfair Display', serif; font-size: 38px; margin-bottom: 20px; position: relative; z-index: 1; }
    .cta-glass p { position: relative; z-index: 1; }
    .btn-outline-light {
      background: rgba(255, 255, 245, 0.12);
      backdrop-filter: blur(8px);
      border: 1.5px solid var(--cream);
      color: var(--cream);
      padding: 12px 32px;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin: 0 8px;
      position: relative;
      z-index: 1;
    }
    .btn-outline-light:hover {
      background: var(--cream);
      color: var(--espresso);
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      backdrop-filter: blur(0);
    }

    /* Footer */
    .footer {
      background: var(--espresso);
      color: var(--cream);
      padding: 60px 40px 30px;
      margin-top: 60px;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 40px;
      max-width: 1400px;
      margin: 0 auto;
    }
    .footer-col h4 {
      font-family: 'Playfair Display', serif;
      margin-bottom: 20px;
      font-size: 18px;
    }
    .footer-col p, .footer-col a {
      color: rgba(255, 255, 245, 0.7);
      text-decoration: none;
      font-size: 14px;
      line-height: 1.8;
      transition: var(--transition);
    }
    .footer-col a:hover { color: var(--gold); transform: translateX(4px); display: inline-block; }
    .copyright {
      text-align: center;
      padding-top: 40px;
      margin-top: 40px;
      border-top: 1px solid rgba(255, 255, 245, 0.1);
      font-size: 12px;
      color: rgba(255, 255, 245, 0.5);
    }

    /* Testimonial Section - New Element */
    .testimonial-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }
    .testimonial-card {
      background: rgba(255, 255, 245, 0.6);
      backdrop-filter: blur(12px);
      border-radius: var(--radius-sm);
      padding: 28px;
      border: 1px solid rgba(255, 255, 245, 0.5);
      transition: var(--transition);
    }
    .testimonial-card:hover {
      transform: translateY(-5px);
      background: rgba(255, 255, 245, 0.8);
      backdrop-filter: blur(16px);
      border-color: var(--gold);
    }
    .testimonial-text {
      font-style: italic;
      font-size: 14px;
      line-height: 1.7;
      color: var(--espresso);
      margin-bottom: 20px;
    }
    .testimonial-author {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .author-avatar {
      width: 40px;
      height: 40px;
      background: var(--gold);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--espresso);
      font-weight: 700;
    }
    .author-info h4 { font-size: 14px; font-weight: 700; }
    .author-info p { font-size: 11px; color: var(--stone); }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
      .navbar { padding: 14px 20px; }
      .hero-content { padding: 100px 20px 80px; }
      .section { padding: 60px 20px; }
      .hero-stats { gap: 20px; }
      .stat-glass { padding: 8px 20px; }
      .section-title { font-size: 32px; }
      .cta-glass h2 { font-size: 28px; }
      .cta-glass { padding: 40px 24px; }
      .btn-outline-light { margin: 8px; }
    }
  </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="logo">
      <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
      LAKBAY
    </a>
    <div class="nav-actions">
      <a href="login and signup/login.php" class="btn-login">Log In</a>
      <a href="login and signup/login.php?action=signup" class="btn-signup">Sign Up</a>
    </div>
  </div>
</nav>

<!-- Hero Section with Fog/Cloud Animation -->
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
      <a href="login and signup/login.php?redirect=quiz" class="btn-primary" id="findTrailBtn">Find My Trail</a>
      <a href="#" class="btn-secondary" id="exploreMountainsBtn">Explore Nasugbu Mountains →</a>
    </div>
    <div class="hero-stats">
      <div class="stat-glass"><span class="stat-number"><?php echo $mountainCount; ?></span><span class="stat-label">Mountains</span></div>
      <div class="stat-glass"><span class="stat-number"><?php echo $hikerCount; ?>+</span><span class="stat-label">Happy Hikers</span></div>
      <div class="stat-glass"><span class="stat-number"><?php echo $avgRating; ?></span><span class="stat-label">★ Avg Rating</span></div>
    </div>
  </div>
</section>

<!-- Everything You Need Section -->
<section class="section">
  <div class="container">
    <h2 class="section-title">Everything You Need for Your Hike</h2>
    <p class="section-subtitle">LAKBAY provides comprehensive tools and information for a safe and enjoyable hiking experience.</p>
    <div class="features-grid">
      <div class="feature-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="14" r="2"/></svg></div><h3>Trail Discovery</h3><p>Explore detailed information about <?php echo $mountainCount; ?> stunning mountains in Nasugbu, Batangas.</p></div>
      <div class="feature-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div><h3>Personalized Quiz</h3><p>Take our trail-matching quiz to find the perfect mountain for your experience level.</p></div>
      <div class="feature-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg></div><h3>Verified Guides</h3><p>Connect with experienced local guides for a safe and authentic hiking experience.</p></div>
      <div class="feature-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg></div><h3>Real-time Updates</h3><p>Get live weather advisories and trail condition updates before your hike.</p></div>
      <div class="feature-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div><h3>Easy Booking</h3><p>Book guides and manage your hiking schedule all in one seamless platform.</p></div>
    </div>
  </div>
</section>

<!-- Advisories & Protocols -->
<section class="section" style="background: rgba(198, 164, 59, 0.04);">
  <div class="container">
    <h2 class="section-title">Advisories & Protocols</h2>
    <p class="section-subtitle">Important guidelines for responsible and safe hiking in Nasugbu</p>
    <div class="guidelines-grid">
      <div class="guideline-card"><h3><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2c5e2c" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg> Environmental Protection</h3><ul class="guideline-list"><li>✓ Practice "Leave No Trace" — pack out all trash</li><li>✓ Stay on designated trails to prevent erosion</li><li>✓ Respect wildlife and natural habitats</li><li>✓ Use eco-friendly products only</li></ul></div>
      <div class="guideline-card"><h3><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg> Safety Protocols</h3><ul class="guideline-list"><li>✓ Register at barangay hall before hiking</li><li>✓ Hire local guides for first-time visits</li><li>✓ Bring sufficient water and sun protection</li><li>✓ Check weather conditions before departure</li></ul></div>
      <div class="guideline-card"><h3><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8a5a2a" stroke-width="1.5"><path d="M3 12h18M12 3v18"/></svg> Trail Regulations</h3><ul class="guideline-list"><li>✓ Follow posted signs and trail markers</li><li>✓ No littering — violators will be fined</li><li>✓ Respect private properties along the trail</li><li>✓ Follow group size limitations per trail</li></ul></div>
    </div>
  </div>
</section>

<!-- Testimonials Section - Dynamic from Database -->
<section class="section">
  <div class="container">
    <h2 class="section-title">What Hikers Say</h2>
    <p class="section-subtitle">Real stories from adventurers who found their perfect trail with LAKBAY</p>
    <div class="testimonial-grid">
      <?php foreach ($testimonials as $t): ?>
      <div class="testimonial-card">
        <div class="testimonial-text">"<?php echo htmlspecialchars($t['text']); ?>"</div>
        <div class="testimonial-author">
          <div class="author-avatar"><?php echo htmlspecialchars($t['initial']); ?></div>
          <div class="author-info">
            <h4><?php echo htmlspecialchars($t['name']); ?></h4>
            <p><?php echo htmlspecialchars($t['role']); ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Why Use LAKBAY - Improved Spacing -->
<section class="section" style="background: rgba(108, 122, 108, 0.04);">
  <div class="container">
    <h2 class="section-title">Why Use LAKBAY?</h2>
    <p class="section-subtitle">Your trusted companion for exploring Nasugbu's mountains responsibly</p>
    <div class="why-grid">
      <div class="why-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="14" r="2"/></svg></div><h3>Comprehensive Trail<br>Information</h3><p>Detailed guides including difficulty levels, duration, fees, and environmental protocols for all <?php echo $mountainCount; ?> mountains.</p></div>
      <div class="why-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg></div><h3>Safety First<br>Approach</h3><p>Real-time weather updates, hazard warnings, and a verified guide system to keep you protected on the trail.</p></div>
      <div class="why-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div><h3>Easy Booking<br>System</h3><p>Book guides, manage your hiking schedule, and receive confirmations — all in one seamless platform.</p></div>
      <div class="why-card" onclick="redirectToLogin()"><div class="feature-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#100600" stroke-width="1.5"><path d="M12 2L3 6v6c0 5.5 9 10 9 10s9-4.5 9-10V6l-9-4z"/></svg></div><h3>Environmental<br>Stewardship</h3><p>Promoting responsible hiking with Leave No Trace principles to preserve Nasugbu's beauty for future generations.</p></div>
    </div>
  </div>
</section>

<!-- Featured Mountains - Dynamic from Database -->
<section class="section" id="mountains-section">
  <div class="container">
    <h2 class="section-title">Our Mountains</h2>
    <p class="section-subtitle">Discover Batangas' most treasured peaks — each with unique character and breathtaking views</p>
    <div class="mountains-grid">
      <?php foreach ($mountains as $mountain): 
        $difficulty = strtolower(htmlspecialchars($mountain['difficulty']));
        $badgeClass = 'easy';
        if (strpos($difficulty, 'moderate') !== false) $badgeClass = 'moderate';
        if (strpos($difficulty, 'hard') !== false || strpos($difficulty, 'difficult') !== false) $badgeClass = 'hard';
      ?>
      <div class="mtn-card" onclick="redirectToExplore()">
        <div class="mtn-img" style="background-image: url('<?php echo htmlspecialchars($mountain['image']); ?>')">
          <span class="mtn-badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($mountain['difficulty']); ?></span>
        </div>
        <div class="mtn-info">
          <div class="mtn-name"><?php echo htmlspecialchars($mountain['name']); ?></div>
          <div class="mtn-meta-icons">
            <div class="meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20L12 4L20 20H4Z"/><circle cx="12" cy="14" r="1.5"/></svg><span><?php echo htmlspecialchars($mountain['elevation']); ?></span></div>
            <div class="meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span><?php echo htmlspecialchars($mountain['duration']); ?></span></div>
          </div>
          <div class="rating-wrapper">
            <div class="rating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span><?php echo number_format($mountain['rating'], 1); ?></span></div>
            <div class="view-btn">View Details</div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Guide + Booking CTA -->
<section class="section">
  <div class="container">
    <div class="cta-glass"><h2>Find a Local Guide</h2><p style="margin-top: 12px; opacity: 0.9;">Certified local guides who know every trail like the back of their hand</p><div style="margin-top: 32px;">
      <a href="javascript:void(0);" onclick="redirectToLogin()" class="btn-outline-light">Browse Verified Guides →</a>
      <a href="javascript:void(0);" onclick="redirectToLogin()" class="btn-outline-light" style="margin-left: 16px;">Book Your Hike Easily</a>
    </div><p style="margin-top: 28px; font-size: 14px; opacity: 0.7;">✓ Verified Guides ✓ Best Rates ✓ 24/7 Support</p></div>
  </div>
</section>

<!-- Final CTA -->
<section class="section">
  <div class="container" style="text-align: center;">
    <h2 class="section-title">Start Your Hiking Journey Today</h2>
    <p class="section-subtitle">Join <?php echo number_format($hikerCount); ?>+ hikers who trust LAKBAY for their mountain adventures</p>
    <div class="hero-buttons" style="justify-content: center;">
      <a href="login and signup/login.php?action=signup" class="btn-primary">Sign Up →</a>
      <a href="#" class="btn-secondary" id="finalExploreBtn" style="background: rgba(16,6,0,0.1); color: var(--espresso); border-color: var(--espresso);">Explore Mountains</a>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-col"><h4>About LAKBAY</h4>
    <p>Your trusted guide to the mountains of Nasugbu, Batangas. We connect adventurers with safe, memorable hiking experiences.</p></div>
    <div class="footer-col"><h4>Contact Us</h4><p>Tourism Office, Nasugbu, Batangas, Philippines</p><p>2nd Floor, Municipal Hall of Nasugbu, Batangas</p><p>Email: tourismoffice@lakbaynasugbu.com</p><p>Hours: Monday-Friday: 8 AM - 5 PM</p></div>
    <div class="footer-col"><h4>Quick Links</h4>
    <p><a href="javascript:void(0);" onclick="redirectToLogin()">Explore Mountains</a></p>
    <p><a href="javascript:void(0);" onclick="redirectToLogin()">Take the Quiz</a></p>
    <p><a href="javascript:void(0);" onclick="redirectToLogin()">Book a Guide</a></p>
  </div>
  </div>
  <div class="copyright">© <?php echo date('Y'); ?> LAKBAY — Nasugbu Tourism. All rights reserved.</div>
</footer>

<script>
  // Redirect to login page for protected content
  function redirectToLogin() {
    window.location.href = 'login and signup/login.php';
  }
  
  // Redirect to explore page but check login first (for mountain cards)
  function redirectToExplore() {
    window.location.href = 'login and signup/login.php?redirect=explore';
  }
  
  // Find Trail button
  document.getElementById('findTrailBtn')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    window.location.href = 'login and signup/login.php?redirect=quiz'; 
  });
  
  // Scroll to mountains section when any Explore Mountains button is clicked
  const scrollToMountains = (e) => {
    e.preventDefault();
    const mountainsSection = document.getElementById('mountains-section');
    if (mountainsSection) {
      mountainsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };
  
  document.getElementById('exploreMountainsBtn')?.addEventListener('click', scrollToMountains);
  document.getElementById('finalExploreBtn')?.addEventListener('click', scrollToMountains);
</script>
</body>
</html>