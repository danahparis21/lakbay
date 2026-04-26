<?php
// login.php - Lakbay Login Page with Backend Integration
require_once '../config/db.php';

$error = '';
$success_redirect = false;
$redirect_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    
    if ($email && $pass) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($pass, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_avatar'] = $user['avatar'] ?? '';
            $_SESSION['created_at'] = $user['created_at'];
            
            // Update last login timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Determine redirect URL based on role
            $roleMap = [
                'hiker'   => '../hiker-frontend/explore.php',
                'guide'   => '../tourguide-frontend/guide-dashboard.php',
                'manager' => '../../pages/dashboard-manager.php',
                'admin'   => '../../pages/dashboard-admin.php',
            ];
            $redirect_url = $roleMap[$user['role']] ?? '../hiker-frontend/explore.php';
            $success_redirect = true;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Sign In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --forest: #0d0500;
      --bark: #2a1500;
      --moss: #4a3520;
      --stone: #7a6a58;
      --sage: #a89880;
      --mist: #e8e2d8;
      --cream: #faf7f2;
      --gold: #c9a84c;
      --gold-light: #e8c96a;
      --white: #ffffff;
      --danger: #c0392b;
      --success: #2ecc71;
    }

    html, body {
      height: 100%;
      font-family: 'DM Sans', sans-serif;
      background: var(--forest);
      overflow-x: hidden;
    }

    .page-wrapper {
      display: flex;
      min-height: 100vh;
    }

    /* ── LEFT PANEL ── */
    .visual-panel {
      flex: 1.2;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 48px;
    }

    .visual-bg {
      position: absolute;
      inset: 0;
      background-image: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&q=80');
      background-size: cover;
      background-position: center 30%;
      transition: transform 8s ease;
    }
    .visual-bg:hover { transform: scale(1.03); }

    .visual-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        160deg,
        rgba(13,5,0,0.15) 0%,
        rgba(13,5,0,0.25) 30%,
        rgba(13,5,0,0.75) 75%,
        rgba(13,5,0,0.92) 100%
      );
    }

    .visual-content {
      position: relative;
      z-index: 2;
    }

    .brand-lockup {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 40px;
    }
    .brand-logo svg {
      width: 44px;
      height: 44px;
    }
    .brand-name {
      font-family: 'Cormorant Garamond', serif;
      font-size: 28px;
      font-weight: 600;
      color: var(--white);
      letter-spacing: 6px;
      text-transform: uppercase;
    }

    .visual-headline {
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(36px, 4vw, 56px);
      font-weight: 300;
      line-height: 1.15;
      color: var(--white);
      margin-bottom: 20px;
    }
    .visual-headline em {
      font-style: italic;
      color: var(--gold-light);
    }

    .visual-sub {
      font-size: 14px;
      font-weight: 300;
      color: rgba(255,255,255,0.65);
      line-height: 1.7;
      max-width: 360px;
      margin-bottom: 40px;
    }

    .visual-stats {
      display: flex;
      gap: 32px;
    }
    .vstat {
      text-align: center;
    }
    .vstat-num {
      font-family: 'DM Mono', monospace;
      font-size: 22px;
      font-weight: 500;
      color: var(--gold-light);
    }
    .vstat-label {
      font-size: 10px;
      color: rgba(255,255,255,0.5);
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-top: 4px;
    }

    /* Floating mountain chips */
    .floating-chips {
      position: absolute;
      top: 48px;
      right: 48px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      z-index: 3;
    }
    .chip {
      background: rgba(255,255,255,0.12);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 100px;
      padding: 8px 16px;
      font-size: 11px;
      color: rgba(255,255,255,0.85);
      display: flex;
      align-items: center;
      gap: 8px;
      animation: floatChip 3s ease-in-out infinite;
    }
    .chip:nth-child(2) { animation-delay: 1s; }
    .chip:nth-child(3) { animation-delay: 2s; }
    .chip-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--gold-light);
    }
    @keyframes floatChip {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }

    /* ── RIGHT PANEL ── */
    .form-panel {
      flex: 1;
      min-width: 420px;
      background: var(--cream);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 52px;
      position: relative;
      overflow: hidden;
    }

    /* Subtle texture overlay */
    .form-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: 
        radial-gradient(ellipse at 80% 0%, rgba(201,168,76,0.06) 0%, transparent 60%),
        radial-gradient(ellipse at 20% 100%, rgba(13,5,0,0.04) 0%, transparent 50%);
      pointer-events: none;
    }

    .form-inner {
      position: relative;
      z-index: 1;
      max-width: 380px;
      width: 100%;
      margin: 0 auto;
    }

    .form-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 10px;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 12px;
    }

    .form-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 40px;
      font-weight: 600;
      color: var(--forest);
      line-height: 1.1;
      margin-bottom: 8px;
    }

    .form-subtitle {
      font-size: 13px;
      color: var(--stone);
      margin-bottom: 40px;
      line-height: 1.6;
    }
    .form-subtitle a {
      color: var(--forest);
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1.5px solid var(--gold);
    }

    .field-group {
      margin-bottom: 20px;
    }

    .field-label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--moss);
      margin-bottom: 8px;
    }

    .field-wrapper {
      position: relative;
    }
    .field-icon {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      stroke: var(--sage);
      stroke-width: 1.8;
      fill: none;
      pointer-events: none;
      transition: stroke 0.2s;
    }

    .field-input {
      width: 100%;
      background: var(--white);
      border: 1.5px solid rgba(13,5,0,0.12);
      border-radius: 14px;
      padding: 14px 16px 14px 46px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--forest);
      transition: all 0.25s;
      outline: none;
    }
    .field-input:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 4px rgba(201,168,76,0.12);
    }
    .field-input:focus + .field-focus-line { width: 100%; }
    .field-input::placeholder { color: var(--sage); }

    .field-wrapper:focus-within .field-icon { stroke: var(--gold); }

    .eye-toggle {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      padding: 4px;
      color: var(--sage);
      transition: color 0.2s;
    }
    .eye-toggle:hover { color: var(--forest); }
    .eye-toggle svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 1.8; fill: none; }

    .field-error {
      font-size: 11px;
      color: var(--danger);
      margin-top: 6px;
      display: none;
    }
    .field-error.show { display: block; }

    .forgot-row {
      display: flex;
      justify-content: flex-end;
      margin-top: -8px;
      margin-bottom: 28px;
    }
    .forgot-link {
      font-size: 12px;
      color: var(--stone);
      text-decoration: none;
      transition: color 0.2s;
    }
    .forgot-link:hover { color: var(--forest); }

    .btn-primary-full {
      width: 100%;
      background: var(--forest);
      color: var(--white);
      border: none;
      border-radius: 14px;
      padding: 16px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 0.5px;
      cursor: pointer;
      transition: all 0.25s;
      position: relative;
      overflow: hidden;
    }
    .btn-primary-full::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, transparent 0%, rgba(201,168,76,0.15) 100%);
      opacity: 0;
      transition: opacity 0.3s;
    }
    .btn-primary-full:hover { 
      background: var(--bark); 
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(13,5,0,0.2);
    }
    .btn-primary-full:hover::after { opacity: 1; }
    .btn-primary-full:active { transform: translateY(0); }
    .btn-primary-full.loading {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .btn-loader {
      display: none;
      width: 18px;
      height: 18px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      margin: 0 auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .btn-text { display: block; }

    .divider {
      display: flex;
      align-items: center;
      gap: 16px;
      margin: 28px 0;
    }
    .divider-line { flex: 1; height: 1px; background: rgba(13,5,0,0.1); }
    .divider-text { font-size: 11px; color: var(--sage); letter-spacing: 1px; text-transform: uppercase; }

    .social-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .social-btn {
      background: var(--white);
      border: 1.5px solid rgba(13,5,0,0.1);
      border-radius: 12px;
      padding: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 12px;
      font-weight: 500;
      color: var(--forest);
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }
    .social-btn:hover { border-color: var(--gold); background: rgba(201,168,76,0.04); }
    .social-btn svg { width: 16px; height: 16px; }

    /* Alert */
    .alert {
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 13px;
      margin-bottom: 20px;
      display: none;
      align-items: center;
      gap: 10px;
    }
    .alert.show { display: flex; }
    .alert-error { background: rgba(192,57,43,0.08); color: var(--danger); border: 1px solid rgba(192,57,43,0.2); }
    .alert-success { background: rgba(46,204,113,0.08); color: var(--success); border: 1px solid rgba(46,204,113,0.2); }
    .alert svg { width: 16px; height: 16px; stroke: currentColor; fill: none; flex-shrink: 0; }

    /* Success popup styles */
    .popup-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.8);
      backdrop-filter: blur(8px);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
    }
    .popup-overlay.show { display: flex; }
    .welcome-popup {
      background: var(--white);
      border-radius: 32px;
      padding: 40px;
      max-width: 400px;
      width: 90%;
      text-align: center;
      animation: popIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes popIn {
      from { opacity: 0; transform: scale(0.85) translateY(20px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .welcome-avatar {
      width: 80px;
      height: 80px;
      background: var(--forest);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      animation: avatarPop 0.5s 0.15s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes avatarPop {
      from { opacity: 0; transform: scale(0); }
      to { opacity: 1; transform: scale(1); }
    }
    .welcome-avatar svg {
      width: 40px;
      height: 40px;
      stroke: var(--white);
      fill: none;
      stroke-width: 2;
    }
    .role-badge {
      display: inline-block;
      padding: 4px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 16px;
    }
    .role-hiker { background: #E1F5EE; color: #0F6E56; }
    .role-admin { background: #EEEDFE; color: #3C3489; }
    .role-manager { background: #FAEEDA; color: #854F0B; }
    .role-guide { background: #E6F1FB; color: #0C447C; }
    .welcome-popup h2 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 28px;
      margin-bottom: 12px;
      color: var(--forest);
    }
    .welcome-popup p {
      font-size: 14px;
      color: var(--stone);
      margin-bottom: 20px;
    }
    .progress-bar {
      height: 4px;
      background: #D4E1E7;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 20px;
    }
    .progress-fill {
      height: 100%;
      background: var(--forest);
      border-radius: 4px;
      animation: fill 2.5s linear forwards;
    }
    @keyframes fill { from { width: 0%; } to { width: 100%; } }

    /* Mobile responsive */
    @media (max-width: 900px) {
      .visual-panel { display: none; }
      .form-panel {
        min-width: unset;
        padding: 40px 28px;
        background: var(--forest);
        min-height: 100vh;
        justify-content: center;
      }
      .form-panel::before {
        background-image: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&q=60');
        background-size: cover;
        background-position: center;
        opacity: 0.08;
      }
      .form-title, .form-subtitle, .form-eyebrow, .form-subtitle a, .field-label { color: var(--mist) !important; }
      .form-subtitle { color: rgba(232,226,216,0.6) !important; }
      .field-input { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.12); color: var(--white); }
      .field-input::placeholder { color: rgba(255,255,255,0.3); }
      .field-input:focus { background: rgba(255,255,255,0.12); border-color: var(--gold); }
      .field-label { color: rgba(255,255,255,0.6) !important; }
      .forgot-link { color: rgba(255,255,255,0.5); }
      .social-btn { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.12); color: var(--white); }
      .social-btn:hover { background: rgba(255,255,255,0.14); }
      .divider-line { background: rgba(255,255,255,0.1); }
      .divider-text { color: rgba(255,255,255,0.3); }
      .welcome-popup { background: var(--forest); color: var(--white); }
      .welcome-popup h2, .welcome-popup p { color: var(--white); }
    }

    @media (max-width: 420px) {
      .form-panel { padding: 32px 20px; }
    }
    .social-row {
  display: grid;
  gap: 12px;
  justify-content: center;
}
.social-btn.disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background: var(--white);
  border: 1.5px solid rgba(13,5,0,0.1);
  border-radius: 12px;
  padding: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 500;
  color: var(--forest);
  text-decoration: none;
  position: relative;
}
.social-btn.disabled:hover {
  border-color: rgba(13,5,0,0.1);
  background: var(--white);
  transform: none;
}

.coming-soon-tooltip {
  position: absolute;
  bottom: -32px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--forest);
  color: var(--gold);
  font-size: 9px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s;
  font-family: 'DM Mono', monospace;
  z-index: 10;
}
.social-btn.disabled:hover .coming-soon-tooltip {
  opacity: 1;
}

/* Dark mode support for tooltip */
@media (max-width: 900px) {
  .social-btn.disabled {
    background: rgba(255,255,255,0.08);
    border-color: rgba(255,255,255,0.12);
    color: var(--white);
  }
  .social-btn.disabled:hover {
    background: rgba(255,255,255,0.08);
  }
  .coming-soon-tooltip {
    background: var(--white);
    color: var(--forest);
  }
}

/* Back button */
.back-button {
  position: absolute;
  top: 24px;
  left: 24px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 500;
  color: var(--stone);
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  z-index: 10;
}
.back-button:hover {
  color: var(--forest);
  transform: translateX(-2px);
}
.back-button svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
}

/* Dark mode for back button on mobile */
@media (max-width: 900px) {
  .back-button {
    top: 20px;
    left: 20px;
    color: rgba(255,255,255,0.6);
  }
  .back-button:hover {
    color: var(--white);
  }
}

  </style>
</head>
<body>

<div class="page-wrapper">

  <!-- LEFT VISUAL PANEL -->
  <div class="visual-panel">
    <div class="visual-bg"></div>
    <div class="visual-overlay"></div>

    <div class="floating-chips">
      <div class="chip"><div class="chip-dot"></div> Mt. Batulao — 4.8 ★</div>
      <div class="chip"><div class="chip-dot"></div> 12 trails nearby</div>
      <div class="chip"><div class="chip-dot"></div> Weather: Clear skies</div>
    </div>

    <div class="visual-content">
      <div class="brand-lockup">
        <div class="brand-logo">
          <svg viewBox="0 0 44 44" fill="none">
            <path d="M4 38L14 16L22 29L30 11L40 38H4Z" fill="white" opacity="0.9"/>
            <path d="M22 29L30 11L40 38H22V29Z" fill="white" opacity="0.3"/>
          </svg>
        </div>
        <span class="brand-name">Lakbay</span>
      </div>

      <h1 class="visual-headline">
        Every peak tells<br>
        <em>a story.</em><br>
        What's yours?
      </h1>

      <p class="visual-sub">
        Join thousands of Filipino hikers discovering trails, connecting with guides, and conquering new summits — safely.
      </p>

      <div class="visual-stats">
        <div class="vstat"><div class="vstat-num">240+</div><div class="vstat-label">Mountains</div></div>
        <div class="vstat"><div class="vstat-num">18K</div><div class="vstat-label">Hikers</div></div>
        <div class="vstat"><div class="vstat-num">96%</div><div class="vstat-label">Safe trips</div></div>
      </div>
    </div>
  </div>

  <!-- RIGHT FORM PANEL -->
  <div class="form-panel">
    <a href="../index.php" class="back-button">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Back to Home
    </a>
    <div class="form-inner">

      <p class="form-eyebrow">Welcome back</p>
      <h2 class="form-title">Sign in to<br>your journey</h2>
      <p class="form-subtitle">
        No account yet? <a href="signup.php">Create one free →</a>
      </p>

      <div class="alert alert-error" id="loginAlert">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="loginAlertMsg">Invalid credentials. Please try again.</span>
      </div>

      <form method="POST" action="" id="loginForm">
        <div class="field-group">
          <label class="field-label" for="loginEmail">Email address</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" id="loginEmail" name="email" class="field-input" placeholder="you@example.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <p class="field-error" id="emailError">Please enter a valid email.</p>
        </div>

        <div class="field-group">
          <label class="field-label" for="loginPassword">Password</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="loginPassword" name="password" class="field-input" placeholder="Enter your password" autocomplete="current-password">
            <button type="button" class="eye-toggle" onclick="togglePwd('loginPassword', this)">
              <svg viewBox="0 0 24 24" id="eyeLogin"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <p class="field-error" id="passError">Password is required.</p>
        </div>

        <div class="forgot-row">
          <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-primary-full" id="loginBtn">
          <span class="btn-text">Sign In</span>
          <div class="btn-loader" id="loginLoader"></div>
        </button>
      </form>

      <div class="divider">
  <div class="divider-line"></div>
  <span class="divider-text">or continue with</span>
  <div class="divider-line"></div>
</div>

<div class="social-row" style="grid-template-columns: 1fr;">
  <div class="social-btn disabled" style="position:relative; opacity:0.6; cursor:not-allowed;">
    <svg viewBox="0 0 24 24" fill="none">
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
      <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
    Google
    <span class="coming-soon-tooltip">Coming soon</span>
  </div>
</div>
    </div>
  </div>
</div>

<!-- Welcome Popup (shown after successful login) -->
<div class="popup-overlay" id="welcomePopup">
  <div class="welcome-popup">
    <div class="welcome-avatar">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div id="roleBadge" class="role-badge"></div>
    <h2>Welcome back, <span id="userName"></span>!</h2>
    <p id="welcomeMessage"></p>
    <div class="progress-bar"><div class="progress-fill"></div></div>
  </div>
</div>

<script>
  // Handle PHP error display (if any)
  <?php if ($error): ?>
  document.addEventListener('DOMContentLoaded', function() {
    showAlert('<?= addslashes($error) ?>', 'error');
  });
  <?php endif; ?>

  <?php if ($success_redirect && $redirect_url): ?>
  // Show welcome popup and redirect
  document.addEventListener('DOMContentLoaded', function() {
    <?php
    $user = $user ?? null;
    if ($user):
      $roleLabels = [
        'hiker' => 'Hiker',
        'guide' => 'Tour Guide',
        'manager' => 'Mountain Manager',
        'admin' => 'Tourism Admin',
      ];
      $roleMessages = [
        'hiker' => 'Preparing your trail exploration...',
        'guide' => 'Loading your guide dashboard and assigned trails...',
        'manager' => 'Fetching your mountain management tools...',
        'admin' => 'Setting up your admin control panel...',
      ];
      $firstName = explode(' ', $user['name'])[0];
      $roleLabel = $roleLabels[$user['role']] ?? 'User';
      $roleMessage = $roleMessages[$user['role']] ?? 'Loading your dashboard...';
      $roleClass = 'role-' . $user['role'];
    ?>
    document.getElementById('roleBadge').textContent = '<?= $roleLabel ?>';
    document.getElementById('roleBadge').className = 'role-badge role-<?= $user['role'] ?>';
    document.getElementById('userName').textContent = '<?= $firstName ?>';
    document.getElementById('welcomeMessage').textContent = '<?= $roleMessage ?>';
    document.getElementById('welcomePopup').classList.add('show');
    
    setTimeout(function() {
      window.location.href = '<?= $redirect_url ?>';
    }, 2600);
    <?php endif; ?>
  });
  <?php endif; ?>

  function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    const svg = btn.querySelector('svg');
    if (isPass) {
      svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
      svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
  }

  function showAlert(msg, type = 'error') {
    const alertBox = document.getElementById('loginAlert');
    const alertMsg = document.getElementById('loginAlertMsg');
    alertMsg.textContent = msg;
    alertBox.className = `alert alert-${type} show`;
    setTimeout(() => {
      alertBox.classList.remove('show');
    }, 5000);
  }

  function clearError(id) {
    document.getElementById(id).classList.remove('show');
  }

  function showError(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.classList.add('show');
  }

  // Client-side validation before form submission
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('loginEmail').value.trim();
    const pass = document.getElementById('loginPassword').value;
    let valid = true;

    clearError('emailError');
    clearError('passError');
    document.getElementById('loginAlert').classList.remove('show');

    if (!email || !/\S+@\S+\.\S+/.test(email)) {
      showError('emailError', 'Please enter a valid email address.');
      valid = false;
    }
    if (!pass) {
      showError('passError', 'Password is required.');
      valid = false;
    }

    if (!valid) {
      e.preventDefault();
      return false;
    }

    // Show loading state
    const btn = document.getElementById('loginBtn');
    const loader = document.getElementById('loginLoader');
    btn.classList.add('loading');
    btn.querySelector('.btn-text').style.display = 'none';
    loader.style.display = 'block';
  });

  function socialLogin(provider) {
    showAlert(`${provider} login coming soon!`, 'error');
  }
</script>

</body>
</html>