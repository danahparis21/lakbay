<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY — Explore Mountains | Wide Desktop Layout + Enhanced Reviews</title>
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
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
    --shadow: 0 12px 32px rgba(0,0,0,0.08);
    --shadow-lg: 0 20px 40px rgba(0,0,0,0.12);
    --shadow-hover: 0 25px 50px rgba(0,0,0,0.15);
    --radius: 28px;
    --radius-sm: 18px;
    --transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
  }

  body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: linear-gradient(145deg, var(--cream) 0%, #f5f2ea 100%);
    color: var(--espresso);
    overflow-x: hidden;
  }

  body::-webkit-scrollbar { display: none; }
  body { scrollbar-width: none; }

  /* Glass Morphism Base */
  .glass {
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  /* Navigation */
  .desktop-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    background: rgba(250,248,243,0.88);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(16,6,0,0.06);
    height: 74px; display: flex; align-items: center;
    padding: 0 48px; gap: 40px;
    transition: var(--transition);
  }
  .desktop-nav:hover { background: rgba(250,248,243,0.96); backdrop-filter: blur(24px); }
  .brand {
    font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700;
    color: var(--espresso); text-decoration: none; display: flex; align-items: center; gap: 8px;
    transition: var(--transition);
  }
  .brand:hover { transform: scale(1.02); color: var(--gold); }
  .brand svg { width: 32px; height: 32px; }
  .tabs { display: flex; gap: 8px; flex: 1; justify-content: center; }
  .tab-link {
    display: flex; align-items: center; gap: 8px; padding: 10px 24px;
    border-radius: 60px; font-size: 14px; font-weight: 600; color: var(--stone);
    text-decoration: none; transition: var(--transition);
  }
  .tab-link:hover { background: rgba(198,164,59,0.12); color: var(--espresso); transform: translateY(-2px); }
  .tab-link.active { background: #100600; color: var(--cream); box-shadow: 0 4px 12px rgba(16,6,0,0.2); }
  .tab-link svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }
  .user-btn {
    width: 44px; height: 44px; border-radius: 50%; background: #100600;
    color: var(--cream); font-weight: 700; font-size: 15px;
    display: flex; align-items: center; justify-content: center; text-decoration: none;
    transition: var(--transition);
  }
  .user-btn:hover { transform: scale(1.05); background: var(--gold); color: var(--espresso); }

  .mobile-nav { display: none; }
  @media (max-width: 768px) {
    .desktop-nav { display: none; }
    .mobile-nav {
      display: block; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
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
      transition: var(--transition);
    }
    .mob-nav-item.active { color: #100600; transform: translateY(-2px); }
    .mob-nav-item svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 1.8; fill: none; }
    .mob-nav-item.quiz-center {
      width: 56px; height: 56px; border-radius: 50%; background: #100600;
      color: var(--cream); padding: 0; display: flex; align-items: center; justify-content: center;
      margin-top: -20px; box-shadow: 0 4px 16px rgba(26,46,26,0.3);
    }
  }

  /* Hero Section */
  .explore-hero {
    position: relative;
    height: 540px;
    background: linear-gradient(135deg, #100600 0%, #2C1A0E 50%, #3d2b1a 100%);
    overflow: hidden;
    display: flex;
    align-items: flex-end;
    padding: 60px 60px;
    margin-top: 74px;
  }
  .explore-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1600&q=80') center/cover;
    opacity: 0.35;
    transition: transform 0.4s ease;
  }
  .explore-hero:hover::before { transform: scale(1.02); }
  .explore-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 200px;
    background: linear-gradient(transparent, var(--cream));
  }
  .hero-content {
    position: relative; z-index: 2; width: 100%;
    max-width: 800px;
    animation: fadeInUp 0.8s ease;
  }
  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .hero-tag {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 20px; border-radius: 60px;
    font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.9);
    letter-spacing: 2px; text-transform: uppercase; margin-bottom: 24px;
  }
  .hero-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(36px, 6vw, 64px); font-weight: 700;
    color: var(--white); line-height: 1.15;
    max-width: 700px; margin-bottom: 28px;
  }
  .hero-search { display: flex; gap: 16px; max-width: 560px; }
  .hero-search .inp {
    flex: 1; background: rgba(255,255,255,0.95); border: none;
    border-radius: 60px; padding: 16px 24px; font-size: 15px;
    transition: var(--transition);
  }
  .hero-search .inp:focus { outline: none; box-shadow: 0 0 0 3px rgba(198,164,59,0.3); }
  .hero-search .btn {
    background: #100600; color: var(--cream); border: none;
    padding: 16px 32px; border-radius: 60px; font-weight: 600;
    cursor: pointer; transition: var(--transition);
  }
  .hero-search .btn:hover { background: #2c1a0a; transform: translateY(-2px); }

  .mob-search-bar {
    padding: 16px; background: var(--cream);
    position: sticky; top: 0; z-index: 50;
    border-bottom: 1px solid rgba(16,6,0,0.06);
  }
  .mob-search-row {
    display: flex; align-items: center; gap: 12px;
    background: rgba(255,255,255,0.8); backdrop-filter: blur(12px);
    border: 1.5px solid rgba(16,6,0,0.08);
    border-radius: 60px; padding: 12px 20px;
  }
  .mob-search-row:focus-within { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(198,164,59,0.2); }
  .mob-search-row input {
    flex: 1; border: none; background: transparent;
    font-size: 14px; outline: none;
  }

  /* Main Content - Wider Layout */
  .explore-content { padding: 60px 0 80px; }
  .container { 
    max-width: 1800px; 
    margin: 0 auto; 
    padding: 0 48px; 
  }
  @media (max-width: 1400px) { .container { padding: 0 32px; } }
  @media (max-width: 768px) { .container { padding: 0 20px; } }

  .section-row {
    display: flex; align-items: baseline;
    justify-content: space-between; margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
  }
  .section-label {
    font-size: 12px; letter-spacing: 3px; text-transform: uppercase;
    color: var(--gold); font-weight: 700;
  }
  .section-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(28px, 4vw, 38px); font-weight: 700; color: var(--espresso);
  }

  /* Quiz Banner */
  .quiz-banner {
    background: linear-gradient(135deg, rgba(16,6,0,0.95) 0%, rgba(44,26,10,0.9) 100%);
    backdrop-filter: blur(16px);
    border-radius: var(--radius);
    padding: 40px 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 32px;
    margin-bottom: 60px;
    transition: var(--transition);
    border: 1px solid rgba(198,168,76,0.2);
  }
  .quiz-banner:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
  .quiz-banner-text h3 {
    font-family: 'Playfair Display', serif;
    font-size: 26px; color: white; margin-bottom: 10px;
  }
  .quiz-banner-text p {
    font-size: 14px; color: rgba(255,255,255,0.7);
  }
  .btn-quiz-glass {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 14px 32px; border-radius: 60px;
    font-size: 14px; font-weight: 700;
    border: 1.5px solid rgba(198,168,76,0.6);
    background: rgba(198,168,76,0.15);
    backdrop-filter: blur(12px);
    color: var(--gold); text-decoration: none;
    transition: var(--transition);
  }
  .btn-quiz-glass:hover {
    background: rgba(198,168,76,0.3);
    border-color: var(--gold);
    transform: translateY(-2px);
  }

  /* Featured Mountain */
  .featured-highlight {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 0;
    background: rgba(255,255,248,0.9);
    backdrop-filter: blur(8px);
    border-radius: 36px;
    overflow: hidden;
    margin-bottom: 70px;
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
    cursor: pointer;
    border: 1px solid rgba(255,255,255,0.4);
  }
  .featured-highlight:hover {
    transform: translateY(-8px);
    box-shadow: 0 30px 60px rgba(0,0,0,0.2);
  }
  .featured-img-section {
    position: relative;
    overflow: hidden;
  }
  .featured-img {
    height: 100%;
    min-height: 520px;
    background-size: cover;
    background-position: center;
    transition: transform 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
  }
  .featured-highlight:hover .featured-img { transform: scale(1.05); }
  .featured-img-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(transparent 30%, rgba(16,6,0,0.8));
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 40px;
  }
  .featured-badge {
    position: absolute;
    top: 24px;
    left: 24px;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(12px);
    padding: 10px 24px;
    border-radius: 60px;
    color: var(--gold);
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: pulseGlow 2s infinite;
  }
  @keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(198,164,59,0.4); }
    50% { box-shadow: 0 0 0 15px rgba(198,164,59,0); }
  }
  .featured-stats-badge {
    display: flex;
    gap: 24px;
    margin-top: 16px;
  }
  .featured-stat-item {
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(12px);
    padding: 10px 20px;
    border-radius: 60px;
    text-align: center;
  }
  .featured-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--gold);
  }
  .featured-stat-label {
    font-size: 10px;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
  }
  .featured-content {
    padding: 48px;
    background: rgba(255,255,248,0.85);
    backdrop-filter: blur(8px);
  }
  .featured-title {
    font-family: 'Playfair Display', serif;
    font-size: 40px;
    font-weight: 700;
    color: var(--espresso);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .featured-rating {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(198,164,59,0.15);
    padding: 6px 16px;
    border-radius: 60px;
    font-size: 15px;
    font-weight: 600;
    color: var(--gold);
  }
  .featured-desc {
    color: var(--stone);
    line-height: 1.75;
    margin: 24px 0;
    font-size: 15px;
  }
  .featured-stats-grid {
    display: flex;
    gap: 40px;
    margin: 30px 0;
    padding: 24px 0;
    border-top: 1px solid rgba(16,6,0,0.08);
    border-bottom: 1px solid rgba(16,6,0,0.08);
  }
  .stat-grid-item {
    text-align: center;
    transition: var(--transition);
  }
  .stat-grid-item:hover { transform: translateY(-3px); }
  .stat-grid-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--gold);
    font-family: monospace;
  }
  .stat-grid-label {
    font-size: 11px;
    color: var(--stone);
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .review-scroll {
    background: rgba(226,230,224,0.7);
    backdrop-filter: blur(8px);
    border-radius: 24px;
    padding: 24px;
    margin: 24px 0;
    position: relative;
    overflow: hidden;
  }
  .review-slide {
    display: flex;
    transition: transform 0.5s ease-out;
  }
  .review-card {
    min-width: 100%;
    padding: 0 12px;
  }
  .review-text {
    font-style: italic;
    font-size: 15px;
    line-height: 1.7;
    color: var(--espresso);
    margin-bottom: 14px;
  }
  .review-author {
    font-weight: 700;
    color: var(--gold);
  }
  .review-nav {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 18px;
  }
  .review-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--stone);
    cursor: pointer;
    transition: all 0.3s;
  }
  .review-dot.active {
    background: var(--gold);
    width: 28px;
    border-radius: 6px;
  }
  .view-all-mountains {
    background: #100600;
    color: white;
    border: none;
    padding: 16px 36px;
    border-radius: 60px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    justify-content: center;
    margin-top: 16px;
    font-size: 15px;
  }
  .view-all-mountains:hover {
    background: #2c1a0a;
    transform: translateY(-2px);
    gap: 16px;
  }

  /* Guide Carousel */
  .guide-carousel {
    position: relative;
    margin-bottom: 60px;
  }
  .guide-scroll {
    display: flex;
    gap: 28px;
    overflow-x: auto;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 16px;
  }
  .guide-scroll::-webkit-scrollbar { display: none; }
  .guide-scroll > * { scroll-snap-align: start; flex-shrink: 0; width: 260px; }
  .guide-card {
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px);
    border-radius: var(--radius);
    padding: 28px 20px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: var(--transition);
    cursor: pointer;
    border: 1px solid rgba(255,255,255,0.4);
  }
  .guide-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); background: rgba(255,255,255,0.95); }
  .guide-avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #100600;
    color: var(--cream);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    margin: 0 auto 16px;
    transition: var(--transition);
  }
  .guide-card:hover .guide-avatar-lg {
    background: var(--gold);
    color: var(--espresso);
    transform: scale(1.05);
  }
  .carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #100600;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: var(--transition);
    font-size: 28px;
    font-weight: bold;
  }
  .carousel-btn:hover {
    background: var(--gold);
    transform: translateY(-50%) scale(1.08);
  }
  .carousel-left { left: -24px; }
  .carousel-right { right: -24px; }
  @media (max-width: 768px) { .carousel-btn { display: none; } }

  /* Mountain Card - WIDER DESKTOP LAYOUT */
  .mtn-card {
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(8px);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: var(--transition);
    cursor: pointer;
    border: 1px solid rgba(255,255,255,0.4);
    display: flex;
    flex-direction: column;
    height: 100%;
  }
  .mtn-card:hover { 
    transform: translateY(-10px) scale(1.02); 
    box-shadow: var(--shadow-hover); 
    background: rgba(255,255,255,0.98);
  }
  .mtn-card-img {
    height: 260px;
    background-size: cover;
    background-position: center;
    position: relative;
    transition: transform 0.5s ease;
  }
  .mtn-card:hover .mtn-card-img { transform: scale(1.04); }
  .mtn-card-img-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(transparent 45%, rgba(16,6,0,0.75));
  }
  .mtn-card-badges { position: absolute; top: 16px; left: 16px; display: flex; gap: 8px; }
  .badge { padding: 6px 14px; border-radius: 60px; font-size: 11px; font-weight: 700; text-transform: uppercase; backdrop-filter: blur(4px); }
  .badge-easy { background: #d9ead3; color: #2a6b2a; }
  .badge-moderate { background: #ffe0b5; color: #8a5a2a; }
  .badge-hard { background: #ffcfc2; color: #a23b1a; }
  .mtn-card-crowd {
    position: absolute; top: 16px; right: 16px;
    background: rgba(0,0,0,0.65); backdrop-filter: blur(8px);
    padding: 5px 14px; border-radius: 60px;
    font-size: 11px; font-weight: 700; color: white;
  }
  .mtn-card-body { padding: 24px; flex: 1; display: flex; flex-direction: column; }
  .mtn-card-name {
    font-family: 'Playfair Display', serif;
    font-size: 24px; font-weight: 700; margin-bottom: 10px;
    display: flex; align-items: center; justify-content: space-between;
  }
  .mtn-card-loc { font-size: 13px; color: var(--stone); margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
  .mtn-card-stats { display: flex; gap: 32px; margin: 16px 0 20px; }
  .mtn-stat { display: flex; flex-direction: column; gap: 4px; }
  .mtn-stat-val { font-size: 18px; font-weight: 700; font-family: monospace; color: var(--espresso); }
  .mtn-stat-lbl { font-size: 10px; color: var(--stone); text-transform: uppercase; letter-spacing: 0.5px; }
  .mtn-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(16,6,0,0.06); }
  .mtn-card-rating { display: flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 700; color: var(--gold); }
  .btn-primary.btn-sm { 
    background: #100600; color: white; border: none; 
    padding: 10px 24px; border-radius: 60px; font-size: 13px; 
    font-weight: 600; cursor: pointer; transition: var(--transition); 
  }
  .btn-primary.btn-sm:hover { 
    background: var(--gold); color: var(--espresso); 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(198,164,59,0.3);
  }

  /* Grid - Wider on Desktop */
  .mtn-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 36px;
    margin-bottom: 60px;
  }
  @media (min-width: 1600px) {
    .mtn-grid { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 1200px) {
    .mtn-grid { grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px; }
  }
  @media (max-width: 768px) {
    .mtn-grid { grid-template-columns: 1fr; gap: 24px; }
  }

  /* Filter Section */
  .filter-section { margin-bottom: 36px; }
  .filter-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; }
  .chip {
    padding: 10px 24px; border-radius: 60px; font-size: 13px; font-weight: 600;
    background: rgba(255,255,255,0.7); backdrop-filter: blur(8px);
    border: 1.5px solid rgba(16,6,0,0.1); cursor: pointer;
    transition: var(--transition);
  }
  .chip:hover { background: white; transform: translateY(-2px); border-color: var(--gold); }
  .chip.active { background: #100600; color: white; border-color: #100600; }
  .filter-select {
    padding: 10px 20px; border-radius: 60px; font-size: 13px; font-weight: 600;
    border: 1.5px solid rgba(16,6,0,0.1);
    background: rgba(255,255,255,0.7); backdrop-filter: blur(8px);
    cursor: pointer; transition: var(--transition);
  }
  .filter-select:hover { background: white; }

  /* Modal */
  .modal-bg {
    display: none; position: fixed; inset: 0;
    background: rgba(16,6,0,0.7); backdrop-filter: blur(16px);
    z-index: 1000; align-items: center; justify-content: center; padding: 24px;
  }
  .modal-bg.open { display: flex; }
  .modal {
    background: rgba(255,255,248,0.96);
    backdrop-filter: blur(20px);
    border-radius: 36px;
    max-width: 1000px;
    width: 100%;
    max-height: 88vh;
    overflow: hidden;
    animation: modalFadeIn 0.3s;
    display: flex;
    flex-direction: column;
  }
  @keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
  }
  .mtn-modal-img {
    height: 300px;
    background-size: cover;
    background-position: center;
    position: relative;
  }
  .mtn-modal-img-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(transparent 30%, rgba(16,6,0,0.85));
    display: flex;
    align-items: flex-end;
    padding: 28px 32px;
  }
  .mtn-modal-img-title {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    font-weight: 700;
    color: white;
  }
  .mtn-close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    z-index: 10;
    font-size: 18px;
  }
  .mtn-close-btn:hover { background: rgba(0,0,0,0.8); transform: scale(1.05); }
  .mtn-modal-scroll { 
    padding: 28px 32px; 
    overflow-y: auto; 
    flex: 1;
    max-height: calc(88vh - 300px - 85px);
  }
  .mtn-info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
  .mtn-info-item { background: rgba(226,230,224,0.7); backdrop-filter: blur(4px); border-radius: 20px; padding: 18px; text-align: center; }
  .mtn-info-val { font-size: 24px; font-weight: 700; }
  .mtn-info-lbl { font-size: 11px; color: var(--stone); margin-top: 5px; text-transform: uppercase; letter-spacing: 1px; }
  .tabs-inline { display: flex; gap: 8px; border-bottom: 2px solid rgba(16,6,0,0.08); margin-bottom: 24px; flex-wrap: wrap; }
  .tab-inline { padding: 12px 28px; font-size: 14px; font-weight: 600; color: var(--stone); cursor: pointer; border-bottom: 2px solid transparent; transition: var(--transition); }
  .tab-inline.active { color: var(--espresso); border-color: var(--espresso); }
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

  /* Persistent Action Buttons */
  .persistent-actions {
    display: flex;
    gap: 20px;
    padding: 20px 32px;
    border-top: 1px solid rgba(16,6,0,0.08);
    background: rgba(245,242,235,0.96);
    backdrop-filter: blur(16px);
    flex-shrink: 0;
  }
  .action-btn {
    flex: 1;
    padding: 14px 24px;
    border-radius: 60px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    text-decoration: none;
  }
  .action-btn.save {
    background: transparent;
    border: 2px solid #100600;
    color: #100600;
  }
  .action-btn.save:hover {
    background: #100600;
    color: white;
    transform: translateY(-2px);
    gap: 14px;
  }
  .action-btn.book {
    background: #100600;
    color: white;
    border: none;
  }
  .action-btn.book:hover {
    background: var(--gold);
    color: var(--espresso);
    transform: translateY(-2px);
    gap: 16px;
  }
  .action-btn svg {
    width: 18px;
    height: 18px;
  }

  /* Enhanced Reviews Section */
  .reviews-filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(16,6,0,0.08);
  }
  .review-filter-chip {
    padding: 8px 22px;
    border-radius: 60px;
    font-size: 13px;
    font-weight: 600;
    background: rgba(255,255,255,0.7);
    border: 1.5px solid rgba(16,6,0,0.1);
    cursor: pointer;
    transition: var(--transition);
  }
  .review-filter-chip:hover { background: white; transform: translateY(-2px); }
  .review-filter-chip.active {
    background: #100600;
    color: white;
    border-color: #100600;
  }
  .reviews-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
    max-height: 420px;
    overflow-y: auto;
    padding-right: 12px;
  }
  .reviews-list::-webkit-scrollbar { width: 4px; }
  .reviews-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); border-radius: 4px; }
  .reviews-list::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 4px; }
  
  .review-item-full {
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(4px);
    border-radius: 24px;
    padding: 22px;
    border: 1px solid rgba(255,255,255,0.6);
    transition: var(--transition);
  }
  .review-item-full:hover { transform: translateX(4px); background: rgba(255,255,255,0.95); }
  .review-header-full {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }
  .review-avatar-full {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #100600;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 20px;
  }
  .review-meta-full { flex: 1; }
  .review-name-full { font-weight: 700; font-size: 16px; }
  .review-stars-full { color: var(--gold); font-size: 14px; margin-top: 4px; letter-spacing: 2px; }
  .review-date-full { font-size: 11px; color: var(--stone); }
  .review-text-full { font-size: 14px; line-height: 1.7; color: var(--espresso); margin-bottom: 16px; }
  .review-media-full {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 12px;
  }
  .media-thumb-full {
    width: 85px;
    height: 85px;
    border-radius: 14px;
    background-size: cover;
    background-position: center;
    cursor: pointer;
    border: 2px solid rgba(255,255,255,0.8);
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .media-thumb-full:hover { transform: scale(1.08); border-color: var(--gold); }
  
  .view-all-reviews-btn {
    width: 100%;
    padding: 14px;
    border-radius: 60px;
    background: transparent;
    border: 2px solid #100600;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    margin-top: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }
  .view-all-reviews-btn:hover {
    background: #100600;
    color: white;
    gap: 16px;
  }
  
  .reviews-modal {
    max-width: 850px;
    max-height: 85vh;
  }
  .reviews-modal-body {
    max-height: 65vh;
    overflow-y: auto;
    padding: 28px;
  }
  .reviews-modal-body .review-item-full {
    margin-bottom: 20px;
  }

  .sunrise-icon {
    display: inline-block;
    width: 22px;
    height: 22px;
    background: currentColor;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M4 18h2M20 18h2M12 16v-4M7 11l2 2M17 11l-2 2M5 22h14'/%3E%3Ccircle cx='12' cy='9' r='3'/%3E%3C/svg%3E") no-repeat center;
    mask-size: contain;
    background-color: var(--gold);
  }

  .toast {
    position: fixed;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    background: #100600;
    color: white;
    padding: 14px 32px;
    border-radius: 60px;
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    z-index: 1300;
    white-space: nowrap;
  }
  .toast.show { opacity: 1; }

  @media (max-width: 968px) {
    .featured-highlight { grid-template-columns: 1fr; }
    .featured-img { min-height: 360px; }
    .featured-content { padding: 32px; }
    .featured-title { font-size: 32px; }
    .mtn-card-name { font-size: 20px; }
    .persistent-actions { padding: 16px 24px; }
    .action-btn { padding: 12px 20px; font-size: 13px; }
  }
  @media (max-width: 640px) {
    .featured-title { font-size: 26px; }
    .hero-title { font-size: 32px; }
    .explore-hero { padding: 40px 24px; }
    .container { padding: 0 16px; }
    .mtn-modal-img-title { font-size: 24px; }
    .mtn-modal-scroll { padding: 20px; }
    .persistent-actions { flex-direction: column; gap: 12px; }
  }
</style>
</head>
<body>

<!-- Navigation -->
<nav class="desktop-nav">
  <a href="explore.php" class="brand"><svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>LAKBAY</a>
  <div class="tabs">
    <a href="explore.php" class="tab-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore</a>
    <a href="bookings.php" class="tab-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings</a>
    <a href="quiz.php" class="tab-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz</a>
    <a href="messages.php" class="tab-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages</a>
  </div>
  <a href="hikerProfile.php" class="user-btn">J</a>
</nav>

<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span></a>
    <a href="bookings.php" class="mob-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span></a>
    <a href="quiz.php" class="mob-nav-item quiz-center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></a>
    <a href="messages.php" class="mob-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="hikerProfile.php" class="mob-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
  </div>
</nav>

<!-- Hero Section -->
<div class="explore-hero">
  <div class="hero-content">
    <div class="hero-tag"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 18h2M20 18h2M12 16v-4M7 11l2 2M17 11l-2 2M5 22h14"/><circle cx="12" cy="9" r="3"/></svg> SILENCE BENEATH STEPS</div>
    <div class="hero-title">Discover Your Next Peak Adventure</div>
    <div class="hero-search">
      <input class="inp" type="text" id="heroSearch" placeholder="Search mountains, locations...">
      <button class="btn" onclick="applySearch()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Search</button>
    </div>
  </div>
</div>

<div class="mob-search-bar" style="display:none;" id="mobSearchBar">
  <div class="mob-search-row">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input type="text" placeholder="Search mountains..." id="mobSearchInp">
  </div>
</div>

<div class="explore-content">
  <div class="container">
    <!-- Quiz Banner -->
    <div class="quiz-banner">
      <div class="quiz-banner-text"><h3>Find Your Perfect Trail</h3><p>Take our 2-minute experience quiz and get personalized mountain recommendations matched to your skill level.</p></div>
      <div class="quiz-banner-action"><a href="quiz.php" class="btn-quiz-glass"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Take the Quiz</a></div>
    </div>

    <!-- Featured Mountain -->
    <div class="featured-highlight" id="featuredMountainBtn">
      <div class="featured-img-section">
        <div class="featured-img" style="background-image:url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800&q=80')"></div>
        <div class="featured-img-overlay">
          <div class="featured-stats-badge">
            <div class="featured-stat-item"><div class="featured-stat-value">811m</div><div class="featured-stat-label">Elevation</div></div>
            <div class="featured-stat-item"><div class="featured-stat-value">4-5 hrs</div><div class="featured-stat-label">Duration</div></div>
            <div class="featured-stat-item"><div class="featured-stat-value">★ 4.8</div><div class="featured-stat-label">Rating</div></div>
          </div>
        </div>
        <div class="featured-badge"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> MOST VISITED</div>
      </div>
      <div class="featured-content">
        <div class="featured-title">Mt. Batulao <span class="featured-rating">★ 4.8 (124 reviews)</span></div>
        <p class="featured-desc">Known for its open ridges and stunning views of Taal Volcano. Perfect for beginners and intermediate hikers seeking an unforgettable sunrise experience. The trail offers a perfect balance of challenge and reward.</p>
        <div class="featured-stats-grid">
          <div class="stat-grid-item"><div class="stat-grid-value">15+</div><div class="stat-grid-label">Trail Markers</div></div>
          <div class="stat-grid-item"><div class="stat-grid-value">3</div><div class="stat-grid-label">Viewpoints</div></div>
          <div class="stat-grid-item"><div class="stat-grid-value"><span class="sunrise-icon"></span></div><div class="stat-grid-label">Sunrise Spot</div></div>
        </div>
        <div class="review-scroll" id="reviewScroll">
          <div class="review-slide" id="reviewSlide">
            <div class="review-card"><div class="review-text">"Amazing ridge walk! Views of Taal are worth every step. The guide was super helpful and very knowledgeable about the trail."</div><div class="review-author">— Kai R. ★★★★★</div></div>
            <div class="review-card"><div class="review-text">"Perfect trail for first-timers. The sunrise view from the ridge is absolutely magical! Will definitely come back."</div><div class="review-author">— Dana M. ★★★★☆</div></div>
            <div class="review-card"><div class="review-text">"Third time here and it never gets old. The trail conditions are much better now. Highly recommended!"</div><div class="review-author">— Paolo S. ★★★★★</div></div>
          </div>
          <div class="review-nav" id="reviewNav"></div>
        </div>
        <button class="view-all-mountains" id="viewAllBtn">View All Mountains →</button>
      </div>
    </div>

    <!-- Tour Guides -->
    <div class="section-row"><div><div class="section-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/></svg> EXPERT GUIDES</div><div class="section-title">Tour Guides</div></div></div>
    <div class="guide-carousel">
      <div class="carousel-btn carousel-left" onclick="scrollGuides(-1)">‹</div>
      <div class="guide-scroll" id="guideScroll"></div>
      <div class="carousel-btn carousel-right" onclick="scrollGuides(1)">›</div>
    </div>

    <!-- All Mountains -->
    <div id="all-mountains">
      <div class="section-row"><div><div class="section-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 22h16M6 4l6 8 6-8"/></svg> DIRECTORY</div><div class="section-title">All Mountains</div></div></div>
      <div class="filter-section"><div class="filter-row">
        <div class="chip active" onclick="filterMtns('all', this)">All</div>
        <div class="chip" onclick="filterMtns('easy', this)">Easy</div>
        <div class="chip" onclick="filterMtns('moderate', this)">Moderate</div>
        <div class="chip" onclick="filterMtns('hard', this)">Hard</div>
        <select class="filter-select" onchange="sortMtns(this.value)"><option value="">Sort by</option><option value="rating">Rating</option><option value="elevation">Elevation</option><option value="time">Time</option></select>
      </div></div>
      <div class="mtn-grid" id="allMtns"></div>
    </div>
  </div>
</div>

<!-- Mountain Modal -->
<div class="modal-bg mtn-modal" id="mtnModal">
  <div class="modal">
    <div class="mtn-modal-img" id="modalImg">
      <button class="mtn-close-btn" onclick="closeMtnModal()">✕</button>
      <div class="mtn-modal-img-overlay">
        <div class="mtn-modal-img-title" id="modalMtnName"></div>
      </div>
    </div>
    <div class="mtn-modal-scroll">
      <div class="mtn-info-grid" id="modalInfoGrid"></div>
      <div class="tabs-inline">
        <div class="tab-inline active" onclick="switchTab('overview',this)">Overview</div>
        <div class="tab-inline" onclick="switchTab('reviews',this)">Reviews <span id="reviewCountBadge"></span></div>
        <div class="tab-inline" onclick="switchTab('tips',this)">Tips</div>
        <div class="tab-inline" onclick="switchTab('advisories',this)">Advisories</div>
      </div>
      
      <div class="tab-panel active" id="tab-overview">
        <p id="modalDesc" style="font-size:15px;color:var(--stone);line-height:1.75;"></p>
        <div style="margin-top:24px;background:rgba(226,230,224,0.5);border-radius:20px;padding:20px;">
          <div style="font-size:12px;font-weight:700;margin-bottom:12px;text-transform:uppercase;letter-spacing:2px;">Fees & Requirements</div>
          <div id="modalFees" style="font-size:14px;line-height:1.8;"></div>
        </div>
      </div>
      
      <div class="tab-panel" id="tab-reviews">
        <div class="reviews-filter-bar" id="reviewFilterBar"></div>
        <div id="reviewsListContainer"></div>
        <button class="view-all-reviews-btn" id="viewAllReviewsBtn" onclick="openAllReviewsModal()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v16h16V4H4z M8 9h8M8 13h6M8 17h4"/></svg>
          View All Reviews
        </button>
      </div>
      
      <div class="tab-panel" id="tab-tips"><div id="modalTips"></div></div>
      <div class="tab-panel" id="tab-advisories"><div id="modalAdvisories"></div></div>
    </div>
    
    <div class="persistent-actions">
      <button class="action-btn save" onclick="saveMtn()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Save Mountain
      </button>
      <a href="javascript:void(0)" class="action-btn book" onclick="bookMountain()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        Book a Tour Now
      </a>
    </div>
  </div>
</div>

<!-- All Reviews Modal -->
<div class="modal-bg" id="allReviewsModal">
  <div class="modal reviews-modal">
    <div class="mtn-modal-img" style="height:140px; background: linear-gradient(135deg, #100600, #2c1a0a);">
      <button class="mtn-close-btn" onclick="closeAllReviewsModal()">✕</button>
      <div class="mtn-modal-img-overlay" style="align-items: center; justify-content: center;">
        <div class="mtn-modal-img-title" style="font-size: 28px;">All Reviews</div>
      </div>
    </div>
    <div class="reviews-modal-body" id="allReviewsBody"></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Complete Mountain Data
const mountains = [
  {
    id: 1, name: "Mt. Batulao", location: "Nasugbu, Batangas", elevation: "811m", time: "4-5 hrs", difficulty: "moderate",
    rating: 4.8, reviewsCount: 124, crowd: "med",
    image: "https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600&q=80",
    desc: "Mt. Batulao is one of the most popular mountains in Luzon, known for its open ridges and stunning views of Taal Volcano. Ideal for beginners and intermediate hikers looking for an accessible but rewarding adventure.",
    fees: "Registration: ₱150/head<br>Environmental fee: ₱120<br>Guide fee (day): ₱900 (1–5 pax)<br>Camping fee: +₱50<br>Parking: ₱100",
    tips: ["Bring at least 2L of water", "Start early (5–6 AM) to avoid heat", "Wear trail shoes", "Carry energy bars", "Download offline map"],
    advisories: ["⚠️ Trail slippery after rain", "ℹ️ No water refill stations", "✅ No active closures"],
    reviewsList: [
      { id: 1, author: "Kai Rivera", initials: "KR", stars: 5, date: "Apr 12, 2025", text: "Amazing ridge walk! Views of Taal are worth every step. The guide was super helpful.", media: ["https://images.unsplash.com/photo-1551632811-561732d1e306?w=200&q=60"] },
      { id: 2, author: "Dana Mercado", initials: "DM", stars: 4, date: "Apr 5, 2025", text: "Perfect trail for first-timers. The sunrise view is absolutely magical!", media: [] },
      { id: 3, author: "Paolo Santos", initials: "PS", stars: 5, date: "Mar 28, 2025", text: "Third time here and it never gets old. Highly recommended!", media: ["https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=200&q=60"] },
      { id: 4, author: "Leah Gomez", initials: "LG", stars: 5, date: "Mar 20, 2025", text: "Camping at the ridge is unforgettable!", media: [] },
      { id: 5, author: "Marcus Tan", initials: "MT", stars: 4, date: "Mar 10, 2025", text: "Great cardio workout. Summit view makes it all worth it.", media: [] }
    ]
  },
  {
    id: 2, name: "Mt. Talamitam", location: "Nasugbu, Batangas", elevation: "630m", time: "3-4 hrs", difficulty: "easy",
    rating: 4.6, reviewsCount: 89, crowd: "low",
    image: "https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=80",
    desc: "A gentle peak often recommended for beginners. Features a scenic grassland summit with panoramic views.",
    fees: "Registration: ₱100/head<br>Guide fee: ₱700<br>Parking: ₱80",
    tips: ["Great for beginners", "Bring a picnic blanket", "Morning fog makes beautiful photos"],
    advisories: ["✅ Trail is open", "ℹ️ Bring your own food"],
    reviewsList: [
      { id: 1, author: "Marco L.", initials: "ML", stars: 5, date: "Apr 10, 2025", text: "Best first hike ever! Easy trail, friendly locals.", media: ["https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=200&q=60"] },
      { id: 2, author: "Sofia G.", initials: "SG", stars: 4, date: "Apr 1, 2025", text: "Perfect for a quick day hike. Very rewarding.", media: [] }
    ]
  },
  {
    id: 3, name: "Mt. Apayang", location: "Batangas", elevation: "980m", time: "6-7 hrs", difficulty: "hard",
    rating: 4.9, reviewsCount: 56, crowd: "low",
    image: "https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&q=80",
    desc: "Challenging jungle trek leading to a hidden crater lake. A true gem for seasoned hikers.",
    fees: "Registration: ₱200/head<br>Guide mandatory: ₱1,200<br>Camping: ₱100",
    tips: ["Experienced hikers only", "Bring trekking poles", "Start early", "Waterproof gear"],
    advisories: ["⚠️ Guide mandatory", "⚠️ River crossings", "ℹ️ No signal"],
    reviewsList: [
      { id: 1, author: "Alex T.", initials: "AT", stars: 5, date: "Apr 8, 2025", text: "Challenging but incredibly rewarding. The crater lake is breathtaking.", media: ["https://images.unsplash.com/photo-1519681393784-d120267933ba?w=200&q=60"] },
      { id: 2, author: "Rina C.", initials: "RC", stars: 5, date: "Mar 22, 2025", text: "Our guide was amazing. Jungle section is intense but beautiful.", media: [] }
    ]
  },
  {
    id: 4, name: "Mt. Lantik", location: "Alfonso, Cavite", elevation: "710m", time: "4-5 hrs", difficulty: "moderate",
    rating: 4.7, reviewsCount: 73, crowd: "high",
    image: "https://images.unsplash.com/photo-1501854140801-50d01698950b?w=600&q=80",
    desc: "Known for its pine forest trail and sea of clouds. A favorite among photographers.",
    fees: "Registration: ₱130/head<br>Environmental: ₱100<br>Guide: ₱900",
    tips: ["Best Oct-Feb for sea of clouds", "Bring a jacket", "Go on weekdays"],
    advisories: ["⚠️ High footfall weekends", "✅ No weather advisories"],
    reviewsList: [
      { id: 1, author: "Jess V.", initials: "JV", stars: 4, date: "Apr 14, 2025", text: "Sea of clouds was magical! Trail is well-marked.", media: ["https://images.unsplash.com/photo-1501854140801-50d01698950b?w=200&q=60"] },
      { id: 2, author: "Ben S.", initials: "BS", stars: 5, date: "Apr 2, 2025", text: "Pine forest smells amazing. Arrive before 5am.", media: [] }
    ]
  }
];

const guides = [
  { name: "John Dela Cruz", initials: "JD", mountains: ["Apayang"], rating: 4.9, bookings: 42 },
  { name: "Maya Reyes", initials: "MR", mountains: ["Batulao", "Talamitam"], rating: 4.8, bookings: 35 },
  { name: "Rico Cabanlit", initials: "RC", mountains: ["Lantik"], rating: 5.0, bookings: 58 },
  { name: "Elena Llorente", initials: "EL", mountains: ["Batulao"], rating: 4.7, bookings: 28 }
];

let currentFilter = 'all';
let currentMtn = null;
let currentSort = '';
let currentReviewFilter = 'all';

function buildMtnCard(m, isTopVisited = false) {
  const card = document.createElement('div'); card.className = 'mtn-card'; card.onclick = () => openMtnModal(m);
  const diffLabel = { easy: 'Easy', moderate: 'Moderate', hard: 'Hard' }[m.difficulty];
  const crowdIcon = m.crowd === 'low' ? '🟢 Low' : (m.crowd === 'med' ? '🟡 Medium' : '🔴 High');
  const visitedIcon = isTopVisited ? '<span class="sunrise-icon" style="margin-left:8px;"></span>' : '';
  card.innerHTML = `<div class="mtn-card-img" style="background-image:url('${m.image}')"><div class="mtn-card-img-overlay"></div><div class="mtn-card-badges"><span class="badge badge-${m.difficulty}">${diffLabel}</span></div><div class="mtn-card-crowd">${crowdIcon}</div></div><div class="mtn-card-body"><div class="mtn-card-name">${m.name}${visitedIcon}</div><div class="mtn-card-loc"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>${m.location}</div><div class="mtn-card-stats"><div class="mtn-stat"><div class="mtn-stat-val">${m.elevation}</div><div class="mtn-stat-lbl">Elevation</div></div><div class="mtn-stat"><div class="mtn-stat-val">${m.time}</div><div class="mtn-stat-lbl">Duration</div></div></div><div class="mtn-card-footer"><div class="mtn-card-rating">★ ${m.rating}</div><button class="btn-primary btn-sm">View Details</button></div></div>`;
  return card;
}

function renderGuides() {
  const el = document.getElementById('guideScroll'); el.innerHTML = '';
  guides.forEach(g => {
    const card = document.createElement('div'); card.className = 'guide-card';
    card.innerHTML = `<div class="guide-avatar-lg">${g.initials}</div><div class="guide-card-name">${g.name}</div><div class="guide-card-mtns">${g.mountains.join(' · ')}</div><div class="guide-card-rating">★ ${g.rating}</div><div class="guide-card-bookings">${g.bookings} trips</div>`;
    el.appendChild(card);
  });
}

function renderAllMtns() {
  const el = document.getElementById('allMtns'); el.innerHTML = '';
  let filtered = mountains.filter(m => currentFilter === 'all' || m.difficulty === currentFilter);
  if (currentSort === 'rating') filtered.sort((a, b) => b.rating - a.rating);
  else if (currentSort === 'elevation') filtered.sort((a, b) => parseInt(b.elevation) - parseInt(a.elevation));
  else if (currentSort === 'time') filtered.sort((a, b) => parseInt(b.time) - parseInt(a.time));
  filtered.forEach((m, idx) => el.appendChild(buildMtnCard(m, idx === 0)));
}

function filterMtns(f, el) { currentFilter = f; document.querySelectorAll('.chip').forEach(c => c.classList.remove('active')); el.classList.add('active'); renderAllMtns(); }
function sortMtns(by) { currentSort = by; renderAllMtns(); }

function renderReviewsWithFilter(mountain, filterRating = 'all') {
  const container = document.getElementById('reviewsListContainer');
  if (!container) return;
  let filteredReviews = [...mountain.reviewsList];
  if (filterRating !== 'all') filteredReviews = filteredReviews.filter(r => r.stars === parseInt(filterRating));
  if (filteredReviews.length === 0) {
    container.innerHTML = '<div style="padding:40px;text-align:center;background:rgba(226,230,224,0.3);border-radius:24px;">No reviews with this rating yet.</div>';
    return;
  }
  let html = '<div class="reviews-list">';
  filteredReviews.forEach(rev => {
    const starHtml = '★'.repeat(rev.stars) + '☆'.repeat(5 - rev.stars);
    let mediaHtml = '';
    if (rev.media && rev.media.length) {
      mediaHtml = `<div class="review-media-full">${rev.media.map(url => `<div class="media-thumb-full" style="background-image:url('${url}')" onclick="event.stopPropagation(); window.open('${url}','_blank')"></div>`).join('')}</div>`;
    }
    html += `<div class="review-item-full"><div class="review-header-full"><div class="review-avatar-full">${rev.initials}</div><div class="review-meta-full"><div class="review-name-full">${rev.author}</div><div class="review-stars-full">${starHtml}</div></div><div class="review-date-full">${rev.date}</div></div><div class="review-text-full">${rev.text}</div>${mediaHtml}</div>`;
  });
  html += '</div>';
  container.innerHTML = html;
}

function renderReviewFilterBar(mountain) {
  const bar = document.getElementById('reviewFilterBar');
  if (!bar) return;
  const ratings = ['all', 5, 4, 3];
  bar.innerHTML = ratings.map(r => {
    const label = r === 'all' ? 'All Reviews' : `★ ${r} Stars`;
    const count = r === 'all' ? mountain.reviewsList.length : mountain.reviewsList.filter(rev => rev.stars === r).length;
    return `<div class="review-filter-chip ${currentReviewFilter === r.toString() ? 'active' : ''}" data-rating="${r}" onclick="setReviewFilter('${r}')">${label} (${count})</div>`;
  }).join('');
}

function setReviewFilter(rating) {
  currentReviewFilter = rating.toString();
  renderReviewFilterBar(currentMtn);
  renderReviewsWithFilter(currentMtn, currentReviewFilter);
}

function openMtnModal(m) {
  currentMtn = m;
  currentReviewFilter = 'all';
  document.getElementById('modalImg').style.backgroundImage = `url('${m.image}')`;
  document.getElementById('modalMtnName').textContent = m.name;
  document.getElementById('modalDesc').textContent = m.desc;
  document.getElementById('modalFees').innerHTML = m.fees;
  document.getElementById('modalInfoGrid').innerHTML = `<div class="mtn-info-item"><div class="mtn-info-val">${m.elevation}</div><div class="mtn-info-lbl">Elevation</div></div><div class="mtn-info-item"><div class="mtn-info-val">${m.time}</div><div class="mtn-info-lbl">Duration</div></div><div class="mtn-info-item"><div class="mtn-info-val">★ ${m.rating}</div><div class="mtn-info-lbl">Rating</div></div>`;
  document.getElementById('modalTips').innerHTML = m.tips.map(t => `<div style="display:flex;gap:12px;margin-bottom:12px;"><span style="color:var(--gold);font-size:18px;">•</span><span>${t}</span></div>`).join('');
  document.getElementById('modalAdvisories').innerHTML = m.advisories.map(a => `<div style="background:rgba(255,248,225,0.8);border-radius:16px;padding:14px;margin-bottom:10px;border-left:3px solid var(--gold);">${a}</div>`).join('');
  document.getElementById('reviewCountBadge').innerHTML = `(${m.reviewsList.length})`;
  renderReviewFilterBar(m);
  renderReviewsWithFilter(m, 'all');
  document.getElementById('mtnModal').classList.add('open');
}

function closeMtnModal() { document.getElementById('mtnModal').classList.remove('open'); }
function switchTab(name, el) {
  document.querySelectorAll('.tab-inline').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById(`tab-${name}`).classList.add('active');
}
function saveMtn() { showToast(`${currentMtn.name} saved to favorites! 📌`); }
function bookMountain() { if (currentMtn) { localStorage.setItem('bookingMtn', JSON.stringify(currentMtn)); window.location.href = 'bookings.php'; } }
function openAllReviewsModal() {
  if (!currentMtn) return;
  const modal = document.getElementById('allReviewsModal');
  const body = document.getElementById('allReviewsBody');
  let html = '';
  currentMtn.reviewsList.forEach(rev => {
    const starHtml = '★'.repeat(rev.stars) + '☆'.repeat(5 - rev.stars);
    let mediaHtml = '';
    if (rev.media && rev.media.length) {
      mediaHtml = `<div class="review-media-full">${rev.media.map(url => `<div class="media-thumb-full" style="background-image:url('${url}')" onclick="event.stopPropagation(); window.open('${url}','_blank')"></div>`).join('')}</div>`;
    }
    html += `<div class="review-item-full"><div class="review-header-full"><div class="review-avatar-full">${rev.initials}</div><div class="review-meta-full"><div class="review-name-full">${rev.author}</div><div class="review-stars-full">${starHtml}</div></div><div class="review-date-full">${rev.date}</div></div><div class="review-text-full">${rev.text}</div>${mediaHtml}</div>`;
  });
  body.innerHTML = html;
  modal.classList.add('open');
}
function closeAllReviewsModal() { document.getElementById('allReviewsModal').classList.remove('open'); }
function showToast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 2500); }
function applySearch() { const q = document.getElementById('heroSearch').value.toLowerCase(); if (!q) { renderAllMtns(); return; } const el = document.getElementById('allMtns'); el.innerHTML = ''; mountains.filter(m => m.name.toLowerCase().includes(q) || m.location.toLowerCase().includes(q)).forEach(m => el.appendChild(buildMtnCard(m))); }
function scrollToAllMountains() { document.getElementById('all-mountains').scrollIntoView({ behavior: 'smooth' }); }
function scrollGuides(dir) { const container = document.getElementById('guideScroll'); const scrollAmount = 280; container.scrollBy({ left: dir * scrollAmount, behavior: 'smooth' }); }

let reviewIndex = 0, reviewInterval;
function initReviewCarousel() {
  const slides = document.querySelectorAll('.review-card');
  const nav = document.getElementById('reviewNav');
  if (!slides.length) return;
  for (let i = 0; i < slides.length; i++) {
    const dot = document.createElement('div'); dot.className = 'review-dot' + (i === 0 ? ' active' : '');
    dot.onclick = () => { reviewIndex = i; updateReviewSlide(); resetInterval(); };
    nav.appendChild(dot);
  }
  reviewInterval = setInterval(() => { reviewIndex = (reviewIndex + 1) % slides.length; updateReviewSlide(); }, 5000);
}
function updateReviewSlide() { const slide = document.getElementById('reviewSlide'); if (slide) { slide.style.transform = `translateX(-${reviewIndex * 100}%)`; document.querySelectorAll('.review-dot').forEach((dot, i) => { dot.classList.toggle('active', i === reviewIndex); }); } }
function resetInterval() { if (reviewInterval) clearInterval(reviewInterval); reviewInterval = setInterval(() => { reviewIndex = (reviewIndex + 1) % document.querySelectorAll('.review-card').length; updateReviewSlide(); }, 5000); }

document.getElementById('mtnModal').addEventListener('click', e => { if (e.target === document.getElementById('mtnModal')) closeMtnModal(); });
document.getElementById('allReviewsModal').addEventListener('click', e => { if (e.target === document.getElementById('allReviewsModal')) closeAllReviewsModal(); });
document.getElementById('featuredMountainBtn').addEventListener('click', (e) => { if (e.target.id !== 'viewAllBtn' && !e.target.closest('#viewAllBtn')) openMtnModal(mountains[0]); });
document.getElementById('viewAllBtn').addEventListener('click', (e) => { e.stopPropagation(); scrollToAllMountains(); });

if (window.innerWidth <= 768) document.getElementById('mobSearchBar').style.display = 'block';
document.getElementById('mobSearchInp')?.addEventListener('input', (e) => { const q = e.target.value.toLowerCase(); const el = document.getElementById('allMtns'); el.innerHTML = ''; mountains.filter(m => m.name.toLowerCase().includes(q)).forEach(m => el.appendChild(buildMtnCard(m))); });

renderGuides(); renderAllMtns(); initReviewCarousel();
</script>
</body>
</html>