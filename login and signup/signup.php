<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAKBAY — Create Account | Nasugbu Trails</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
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
      --success: #27ae60;
    }

    html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: var(--forest); overflow-x: hidden; }

    .page-wrapper { display: flex; min-height: 100vh; }

    /* ── RIGHT VISUAL PANEL (reversed from login) ── */
    .visual-panel {
      flex: 1.1;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 48px;
      order: 2;
    }
    .visual-bg {
      position: absolute;
      inset: 0;
      background-image: url('https://images.unsplash.com/photo-1519681393784-d120267933ba?w=1200&q=80');
      background-size: cover;
      background-position: center 40%;
    }
    .visual-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        20deg,
        rgba(13,5,0,0.15) 0%,
        rgba(13,5,0,0.2) 30%,
        rgba(13,5,0,0.72) 75%,
        rgba(13,5,0,0.92) 100%
      );
    }
    .visual-content { position: relative; z-index: 2; }

    .brand-lockup {
      position: absolute;
      top: 48px;
      left: 48px;
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 3;
    }
    .brand-name {
      font-family: 'Cormorant Garamond', serif;
      font-size: 26px;
      font-weight: 600;
      color: var(--white);
      letter-spacing: 6px;
      text-transform: uppercase;
    }

    .visual-headline {
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(32px, 3.5vw, 52px);
      font-weight: 300;
      line-height: 1.15;
      color: var(--white);
      margin-bottom: 20px;
    }
    .visual-headline em { font-style: italic; color: var(--gold-light); }
    .visual-sub { font-size: 13px; font-weight: 300; color: rgba(255,255,255,0.6); line-height: 1.7; max-width: 340px; margin-bottom: 36px; }

    /* Trail cards - Updated with Nasugbu mountains */
    .trail-cards {
      display: flex;
      flex-direction: column;
      gap: 10px;
      position: absolute;
      top: 100px;
      right: 40px;
      z-index: 3;
    }
    .trail-card {
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 14px;
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      animation: slideIn 0.6s ease both;
    }
    .trail-card:nth-child(1) { animation-delay: 0.1s; }
    .trail-card:nth-child(2) { animation-delay: 0.3s; }
    .trail-card:nth-child(3) { animation-delay: 0.5s; }
    .trail-card:nth-child(4) { animation-delay: 0.7s; }
    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }
    .tc-icon {
      width: 36px;
      height: 36px;
      background: rgba(201,168,76,0.25);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .tc-icon svg {
      width: 20px;
      height: 20px;
      stroke: var(--gold-light);
      stroke-width: 1.5;
      fill: none;
    }
    .tc-info { font-size: 11px; }
    .tc-name { color: white; font-weight: 600; margin-bottom: 2px; }
    .tc-detail { color: rgba(255,255,255,0.55); }

    .visual-badges {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .vbadge {
      background: rgba(201,168,76,0.2);
      border: 1px solid rgba(201,168,76,0.4);
      border-radius: 100px;
      padding: 6px 14px;
      font-size: 11px;
      color: var(--gold-light);
      letter-spacing: 0.5px;
    }

    /* ── LEFT FORM PANEL ── */
    .form-panel {
      flex: 1;
      min-width: 440px;
      background: var(--cream);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 52px;
      position: relative;
      overflow: hidden;
      order: 1;
    }
    .form-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(ellipse at 10% 0%, rgba(201,168,76,0.07) 0%, transparent 55%),
        radial-gradient(ellipse at 90% 100%, rgba(13,5,0,0.04) 0%, transparent 50%);
      pointer-events: none;
    }

    .form-inner { position: relative; z-index: 1; max-width: 400px; width: 100%; margin: 0 auto; }

    /* Progress bar */
    .progress-bar {
      display: flex;
      align-items: center;
      gap: 0;
      margin-bottom: 36px;
    }
    .step-dot {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: rgba(13,5,0,0.08);
      border: 2px solid rgba(13,5,0,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      color: var(--stone);
      transition: all 0.3s;
      flex-shrink: 0;
      z-index: 1;
    }
    .step-dot.active { background: var(--forest); border-color: var(--forest); color: white; }
    .step-dot.done { background: var(--gold); border-color: var(--gold); color: white; }
    .step-dot.done::after { content: '✓'; }
    .step-dot.done span { display: none; }
    .step-line { flex: 1; height: 2px; background: rgba(13,5,0,0.1); transition: background 0.4s; }
    .step-line.done { background: var(--gold); }
    .step-labels { display: flex; justify-content: space-between; margin-top: 8px; margin-bottom: 28px; }
    .step-label { font-size: 10px; color: var(--sage); font-weight: 500; text-align: center; flex: 1; letter-spacing: 0.5px; text-transform: uppercase; }
    .step-label:first-child { text-align: left; }
    .step-label:last-child { text-align: right; }
    .step-label.active { color: var(--forest); font-weight: 600; }

    .form-eyebrow { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); margin-bottom: 10px; }
    .form-title { font-family: 'Cormorant Garamond', serif; font-size: 36px; font-weight: 600; color: var(--forest); line-height: 1.1; margin-bottom: 6px; }
    .form-subtitle { font-size: 13px; color: var(--stone); margin-bottom: 28px; line-height: 1.6; }
    .form-subtitle a { color: var(--forest); font-weight: 600; text-decoration: none; border-bottom: 1.5px solid var(--gold); }

    /* Steps */
    .step { display: none; animation: fadeStep 0.3s ease; }
    .step.active { display: block; }
    @keyframes fadeStep { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .field-group { margin-bottom: 18px; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }
    .field-label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--moss); margin-bottom: 7px; }

    .field-wrapper { position: relative; }
    .field-wrapper svg.field-icon {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      width: 16px; height: 16px; stroke: var(--sage); stroke-width: 1.8; fill: none;
      pointer-events: none; transition: stroke 0.2s;
    }
    .field-wrapper:focus-within svg.field-icon { stroke: var(--gold); }

    .field-input {
      width: 100%;
      background: var(--white);
      border: 1.5px solid rgba(13,5,0,0.12);
      border-radius: 14px;
      padding: 13px 14px 13px 44px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--forest);
      transition: all 0.25s;
      outline: none;
    }
    .field-input:focus { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,168,76,0.1); }
    .field-input::placeholder { color: var(--sage); }
    .field-input.valid { border-color: var(--success); }
    .field-input.error { border-color: var(--danger); }

    .field-input.no-icon { padding-left: 14px; }

    .eye-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; color: var(--sage); transition: color 0.2s; }
    .eye-toggle:hover { color: var(--forest); }
    .eye-toggle svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 1.8; fill: none; }

    .field-error { font-size: 11px; color: var(--danger); margin-top: 5px; display: none; }
    .field-error.show { display: block; }
    .field-success { font-size: 11px; color: var(--success); margin-top: 5px; display: none; }
    .field-success.show { display: block; }

    /* Username availability indicator */
    .username-status {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 11px;
      font-weight: 600;
      display: none;
    }
    .username-status.checking { display: block; color: var(--sage); }
    .username-status.available { display: block; color: var(--success); }
    .username-status.taken { display: block; color: var(--danger); }

    /* Password strength */
    .pwd-strength-bar {
      height: 4px;
      border-radius: 100px;
      background: rgba(13,5,0,0.08);
      margin-top: 10px;
      overflow: hidden;
    }
    .pwd-strength-fill {
      height: 100%;
      border-radius: 100px;
      width: 0%;
      transition: width 0.4s, background 0.4s;
    }

    .pwd-rules {
      margin-top: 12px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px 12px;
    }
    .pwd-rule {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      color: var(--sage);
      transition: color 0.2s;
    }
    .pwd-rule.met { color: var(--success); }
    .pwd-rule-icon {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background: rgba(13,5,0,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: background 0.2s;
      font-size: 8px;
    }
    .pwd-rule.met .pwd-rule-icon { background: var(--success); color: white; }

    /* Hiking level selector - ICON based (no emojis) */
    .level-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 4px;
    }
    .level-card {
      border: 2px solid rgba(13,5,0,0.1);
      border-radius: 14px;
      padding: 14px 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      background: var(--white);
    }
    .level-card:hover { border-color: var(--gold); transform: translateY(-2px); }
    .level-card.selected { border-color: var(--forest); background: var(--forest); }
    .level-card.selected .level-icon svg { stroke: white; }
    .level-card.selected .level-name, 
    .level-card.selected .level-desc { color: white; }
    .level-icon {
      width: 32px;
      height: 32px;
      margin: 0 auto 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .level-icon svg {
      width: 28px;
      height: 28px;
      stroke: var(--forest);
      stroke-width: 1.5;
      fill: none;
      transition: stroke 0.2s;
    }
    .level-card.selected .level-icon svg { stroke: white; }
    .level-name { font-size: 12px; font-weight: 700; color: var(--forest); display: block; margin-bottom: 2px; }
    .level-desc { font-size: 10px; color: var(--stone); display: block; }

    /* Region selector */
    .region-select {
      width: 100%;
      background: var(--white);
      border: 1.5px solid rgba(13,5,0,0.12);
      border-radius: 14px;
      padding: 13px 14px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--forest);
      outline: none;
      transition: all 0.25s;
      appearance: none;
      cursor: pointer;
    }
    .region-select:focus { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,168,76,0.1); }

    /* Terms */
    .terms-check {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 24px;
      cursor: pointer;
    }
    .custom-check {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(13,5,0,0.2);
      border-radius: 5px;
      flex-shrink: 0;
      margin-top: 1px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      background: white;
    }
    .terms-check input { display: none; }
    .terms-check input:checked ~ .custom-check { background: var(--forest); border-color: var(--forest); }
    .terms-check input:checked ~ .custom-check::after { content: '✓'; font-size: 11px; color: white; font-weight: 700; }
    .terms-text { font-size: 12px; color: var(--stone); line-height: 1.5; }
    .terms-text a { color: var(--forest); font-weight: 600; text-decoration: none; border-bottom: 1px solid var(--gold); }

    /* Buttons */
    .btn-row { display: flex; gap: 12px; }
    .btn-back { 
      background: transparent; border: 2px solid rgba(13,5,0,0.15); border-radius: 14px;
      padding: 14px 24px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600;
      color: var(--stone); cursor: pointer; transition: all 0.2s;
    }
    .btn-back:hover { border-color: var(--forest); color: var(--forest); transform: translateX(-2px); }
    .btn-next {
      flex: 1; background: var(--forest); color: white; border: none; border-radius: 14px;
      padding: 15px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600;
      cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden;
    }
    .btn-next::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(135deg, transparent, rgba(201,168,76,0.15));
      opacity: 0; transition: opacity 0.3s;
    }
    .btn-next:hover { background: var(--bark); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(13,5,0,0.2); }
    .btn-next:hover::after { opacity: 1; }
    .btn-next:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

    .btn-loader { display: none; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .btn-text { display: block; }

    /* Success screen - ICON based (no emojis) */
    .success-screen {
      text-align: center;
      padding: 20px 0;
      display: none;
    }
    .success-screen.show { display: block; }
    .success-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--forest), var(--bark));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1);
    }
    @keyframes popIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .success-icon svg { width: 36px; height: 36px; stroke: white; stroke-width: 2; fill: none; }

    /* Mountain icon for welcome title */
    .welcome-icon {
      display: inline-flex;
      vertical-align: middle;
      margin-left: 8px;
    }
    .welcome-icon svg {
      width: 28px;
      height: 28px;
      stroke: var(--gold);
      stroke-width: 1.8;
      fill: none;
    }

    /* Alert */
    .alert { padding: 12px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 18px; display: none; align-items: center; gap: 10px; }
    .alert.show { display: flex; }
    .alert-error { background: rgba(192,57,43,0.08); color: var(--danger); border: 1px solid rgba(192,57,43,0.2); }
    .alert svg { width: 16px; height: 16px; stroke: currentColor; fill: none; flex-shrink: 0; }

    /* Mobile responsive */
    @media (max-width: 960px) {
      .visual-panel { display: none; }
      .form-panel {
        min-width: unset;
        padding: 40px 28px;
        background: var(--forest);
        min-height: 100vh;
        justify-content: center;
        order: 1;
      }
      .form-panel::before {
        background-image: url('https://images.unsplash.com/photo-1519681393784-d120267933ba?w=800&q=60');
        background-size: cover;
        background-position: center;
        opacity: 0.07;
      }
      .form-title, .form-eyebrow, .form-subtitle { color: var(--mist) !important; }
      .form-subtitle { color: rgba(232,226,216,0.6) !important; }
      .step-dot:not(.active):not(.done) { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); color: rgba(255,255,255,0.4); }
      .step-line { background: rgba(255,255,255,0.1); }
      .step-label { color: rgba(255,255,255,0.35); }
      .step-label.active { color: var(--mist); }
      .field-label { color: rgba(255,255,255,0.55) !important; }
      .field-input { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.12); color: white; }
      .field-input::placeholder { color: rgba(255,255,255,0.3); }
      .field-input:focus { background: rgba(255,255,255,0.12); }
      .region-select { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.12); color: white; }
      .level-card { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.12); }
      .level-card.selected { background: rgba(255,255,255,0.95); border-color: white; }
      .level-card.selected .level-name { color: var(--forest); }
      .level-card.selected .level-desc { color: var(--moss); }
      .level-card.selected .level-icon svg { stroke: var(--forest); }
      .level-name { color: var(--mist); }
      .level-desc { color: rgba(255,255,255,0.4); }
      .level-icon svg { stroke: var(--mist); }
      .btn-back { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.6); }
      .terms-check { filter: invert(1) hue-rotate(180deg) brightness(0.85); }
      .custom-check { background: transparent; }
      .terms-text { color: rgba(255,255,255,0.55); }
      .terms-text a { color: white; }
      .pwd-rule { color: rgba(255,255,255,0.4); }
      .pwd-rule.met { color: var(--success); }
      .pwd-strength-bar { background: rgba(255,255,255,0.1); }
    }
    @media (max-width: 440px) {
      .form-panel { padding: 32px 20px; }
      .field-row { grid-template-columns: 1fr; gap: 0; }
      .pwd-rules { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="page-wrapper">

  <!-- FORM PANEL -->
  <div class="form-panel">
    <div class="form-inner">

      <!-- Progress -->
      <div class="progress-bar">
        <div class="step-dot active" id="dot1"><span>1</span></div>
        <div class="step-line" id="line1"></div>
        <div class="step-dot" id="dot2"><span>2</span></div>
        <div class="step-line" id="line2"></div>
        <div class="step-dot" id="dot3"><span>3</span></div>
      </div>
      <div class="step-labels">
        <span class="step-label active" id="lbl1">Account</span>
        <span class="step-label" id="lbl2">Profile</span>
        <span class="step-label" id="lbl3">Preferences</span>
      </div>

      <div class="alert alert-error" id="signupAlert">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="signupAlertMsg">Please fix the errors above.</span>
      </div>

      <!-- STEP 1: Account -->
      <div class="step active" id="step1">
        <p class="form-eyebrow">Step 1 of 3</p>
        <h2 class="form-title">Create your<br>account</h2>
        <p class="form-subtitle">Already a hiker? <a href="login.php">Sign in →</a></p>

        <div class="field-group">
          <label class="field-label">Email address</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" id="regEmail" class="field-input" placeholder="you@example.com" oninput="clearFieldErr('emailErr')">
          </div>
          <p class="field-error" id="emailErr">Please enter a valid email address.</p>
        </div>

        <div class="field-group">
          <label class="field-label">Username</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="regUsername" class="field-input" placeholder="trailblazer_ph" oninput="checkUsername(this.value)" maxlength="20">
            <span class="username-status" id="usernameStatus"></span>
          </div>
          <p class="field-error" id="usernameErr">Username is required (3–20 chars, letters/numbers/_).</p>
        </div>

        <div class="field-group">
          <label class="field-label">Password</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="regPassword" class="field-input" placeholder="At least 8 characters" oninput="checkPwd(this.value)">
            <button type="button" class="eye-toggle" onclick="togglePwd('regPassword', this)">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="pwd-strength-bar"><div class="pwd-strength-fill" id="pwdStrengthFill"></div></div>
          <div class="pwd-rules">
            <div class="pwd-rule" id="rule-len"><div class="pwd-rule-icon">✕</div>Min 8 characters</div>
            <div class="pwd-rule" id="rule-upper"><div class="pwd-rule-icon">✕</div>1 uppercase letter</div>
            <div class="pwd-rule" id="rule-num"><div class="pwd-rule-icon">✕</div>1 number</div>
            <div class="pwd-rule" id="rule-special"><div class="pwd-rule-icon">✕</div>1 special character</div>
          </div>
          <p class="field-error" id="passErr">Password must meet all requirements.</p>
        </div>

        <div class="field-group">
          <label class="field-label">Re-enter Password</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="regConfirm" class="field-input" placeholder="Repeat your password" oninput="checkPasswordMatch()">
            <button type="button" class="eye-toggle" onclick="togglePwd('regConfirm', this)">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <p class="field-error" id="confirmErr">Passwords do not match.</p>
        </div>

        <button class="btn-next" onclick="goStep2()">Continue →</button>
      </div>

      <!-- STEP 2: Profile -->
      <div class="step" id="step2">
        <p class="form-eyebrow">Step 2 of 3</p>
        <h2 class="form-title">About<br>yourself</h2>
        <p class="form-subtitle">Tell us a bit so we can personalize your experience.</p>

        <div class="field-row">
          <div class="field-group" style="margin-bottom:0">
            <label class="field-label">First Name</label>
            <div class="field-wrapper">
              <svg class="field-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" id="regFirst" class="field-input" placeholder="Maria" oninput="clearFieldErr('firstErr')">
            </div>
            <p class="field-error" id="firstErr">Required.</p>
          </div>
          <div class="field-group" style="margin-bottom:0">
            <label class="field-label">Last Name</label>
            <div class="field-wrapper">
              <svg class="field-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" id="regLast" class="field-input" placeholder="Santos" oninput="clearFieldErr('lastErr')">
            </div>
            <p class="field-error" id="lastErr">Required.</p>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">Mobile number</label>
          <div class="field-wrapper">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.38 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.48 6.48l.97-.97a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <input type="tel" id="regMobile" class="field-input" placeholder="+63 912 345 6789" oninput="clearFieldErr('mobileErr')">
          </div>
          <p class="field-error" id="mobileErr">Please enter a valid mobile number.</p>
        </div>

        <div class="field-group">
          <label class="field-label">Home Region</label>
          <select id="regRegion" class="region-select">
            <option value="">Select your region...</option>
            <option>Metro Manila (NCR)</option>
            <option>Ilocos Region (I)</option>
            <option>Cagayan Valley (II)</option>
            <option>Central Luzon (III)</option>
            <option>CALABARZON (IV-A)</option>
            <option>MIMAROPA (IV-B)</option>
            <option>Bicol Region (V)</option>
            <option>Western Visayas (VI)</option>
            <option>Central Visayas (VII)</option>
            <option>Eastern Visayas (VIII)</option>
            <option>Zamboanga Peninsula (IX)</option>
            <option>Northern Mindanao (X)</option>
            <option>Davao Region (XI)</option>
            <option>SOCCSKSARGEN (XII)</option>
            <option>Caraga (XIII)</option>
            <option>BARMM</option>
            <option>CAR (Cordillera)</option>
          </select>
        </div>

        <div class="btn-row">
          <button class="btn-back" onclick="goStep(1)">← Back</button>
          <button class="btn-next" onclick="goStep3()">Continue →</button>
        </div>
      </div>

      <!-- STEP 3: Preferences -->
      <div class="step" id="step3">
        <p class="form-eyebrow">Step 3 of 3</p>
        <h2 class="form-title">Your hiking<br>level</h2>
        <p class="form-subtitle">We'll match you with the right trails.</p>

        <div class="field-group">
          <label class="field-label">Experience Level</label>
          <div class="level-grid">
            <div class="level-card" onclick="selectLevel('beginner', this)">
              <div class="level-icon">
                <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0-3-3m3 3 3-3M5 3h14M5 21h14"/><path d="M7 7h2M15 7h2M9 11h6"/></svg>
              </div>
              <span class="level-name">Beginner</span>
              <span class="level-desc">0–5 hikes</span>
            </div>
            <div class="level-card" onclick="selectLevel('intermediate', this)">
              <div class="level-icon">
                <svg viewBox="0 0 24 24"><path d="M12 3v6m0 0-3-3m3 3 3-3"/><path d="M12 21v-6m0 0 3 3m-3-3-3 3"/><path d="M5 12h14"/><circle cx="12" cy="12" r="9"/></svg>
              </div>
              <span class="level-name">Intermediate</span>
              <span class="level-desc">6–20 hikes</span>
            </div>
            <div class="level-card" onclick="selectLevel('advanced', this)">
              <div class="level-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
              </div>
              <span class="level-name">Advanced</span>
              <span class="level-desc">20+ hikes</span>
            </div>
          </div>
          <p class="field-error" id="levelErr">Please select your hiking level.</p>
        </div>

        <label class="terms-check">
          <input type="checkbox" id="termsCheck">
          <div class="custom-check"></div>
          <span class="terms-text">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>, and consent to receiving safety alerts.</span>
        </label>

        <div class="btn-row">
          <button class="btn-back" onclick="goStep(2)">← Back</button>
          <button class="btn-next" onclick="handleSignup()" id="signupBtn">
            <span class="btn-text">Create Account</span>
            <div class="btn-loader" id="signupLoader"></div>
          </button>
        </div>
      </div>

      <!-- SUCCESS -->
      <div class="success-screen" id="successScreen">
        <div class="success-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 class="form-title" style="text-align:center; margin-bottom:12px;">
          Welcome to Lakbay
          <span class="welcome-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
          </span>
        </h2>
        <p class="form-subtitle" style="text-align:center; margin-bottom:28px;">Your account is ready. Time to discover your next summit.</p>
        <button class="btn-next" onclick="window.location.href='profile.php'">Go to my profile →</button>
      </div>

    </div>
  </div>

  <!-- RIGHT VISUAL PANEL - Updated with Nasugbu mountains: Apayang, Batulao, Lantik, Talamitam -->
  <div class="visual-panel">
    <div class="visual-bg"></div>
    <div class="visual-overlay"></div>

    <div class="brand-lockup">
      <svg width="38" height="38" viewBox="0 0 44 44" fill="none">
        <path d="M4 38L14 16L22 29L30 11L40 38H4Z" fill="white" opacity="0.9"/>
        <path d="M22 29L30 11L40 38H22V29Z" fill="white" opacity="0.3"/>
      </svg>
      <span class="brand-name">Lakbay</span>
    </div>

    <div class="trail-cards">
      <div class="trail-card">
        <div class="tc-icon">
          <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <div class="tc-info">
          <div class="tc-name">Mt. Apayang</div>
          <div class="tc-detail">Nasugbu · Beginner · 400m</div>
        </div>
      </div>
      <div class="trail-card">
        <div class="tc-icon">
          <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0-3-3m3 3 3-3M5 3h14M5 21h14"/></svg>
        </div>
        <div class="tc-info">
          <div class="tc-name">Mt. Batulao</div>
          <div class="tc-detail">Nasugbu · Intermediate · 811m</div>
        </div>
      </div>
      <div class="trail-card">
        <div class="tc-icon">
          <svg viewBox="0 0 24 24"><path d="M12 3v6m0 0-3-3m3 3 3-3M5 12h14"/></svg>
        </div>
        <div class="tc-info">
          <div class="tc-name">Mt. Lantik</div>
          <div class="tc-detail">Nasugbu · Advanced · 600m</div>
        </div>
      </div>
      <div class="trail-card">
        <div class="tc-icon">
          <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5"/></svg>
        </div>
        <div class="tc-info">
          <div class="tc-name">Mt. Talamitam</div>
          <div class="tc-detail">Nasugbu · Intermediate · 630m</div>
        </div>
      </div>
    </div>

    <div class="visual-content">
      <h1 class="visual-headline">
        Tuklasin ang mga<br>
        <em>bundok ng Nasugbu</em><br>
        kasama kami.
      </h1>
      <p class="visual-sub">
        Mula Apayang hanggang Talamitam — bawat akyat ay kwento. Samahan ang komunidad ng mga tagahanga ng bundok sa Nasugbu.
      </p>
      <div class="visual-badges">
        <div class="vbadge">🗺 13 trails sa Nasugbu</div>
        <div class="vbadge">🛡 Safety-first</div>
        <div class="vbadge">🤝 Lokal na gabay</div>
        <div class="vbadge">📡 Live tracking</div>
      </div>
    </div>
  </div>
</div>

<script>
  let selectedLevel = '';
  let pwdValid = false;

  function goStep(n) {
    document.querySelectorAll('.step').forEach((s,i) => s.classList.toggle('active', i+1 === n));
    updateProgress(n);
    document.getElementById('signupAlert').classList.remove('show');
  }

  function updateProgress(activeStep) {
    for(let i=1;i<=3;i++){
      const dot = document.getElementById('dot'+i);
      const lbl = document.getElementById('lbl'+i);
      dot.className = 'step-dot ' + (i < activeStep ? 'done' : i === activeStep ? 'active' : '');
      dot.innerHTML = i < activeStep ? '' : `<span>${i}</span>`;
      lbl.className = 'step-label' + (i === activeStep ? ' active' : '');
    }
    for(let i=1;i<=2;i++){
      document.getElementById('line'+i).className = 'step-line' + (i < activeStep ? ' done' : '');
    }
  }

  function checkPasswordMatch() {
    const password = document.getElementById('regPassword').value;
    const confirm = document.getElementById('regConfirm').value;
    const confirmErr = document.getElementById('confirmErr');
    if (confirm.length > 0 && password !== confirm) {
      confirmErr.classList.add('show');
    } else if (confirm.length > 0 && password === confirm) {
      confirmErr.classList.remove('show');
    } else {
      confirmErr.classList.remove('show');
    }
  }

  function goStep2() {
    const email = document.getElementById('regEmail').value.trim();
    const uname = document.getElementById('regUsername').value.trim();
    const pass = document.getElementById('regPassword').value;
    const confirm = document.getElementById('regConfirm').value;
    let valid = true;
    if(!email || !/\S+@\S+\.\S+/.test(email)) { showFieldErr('emailErr', 'Enter a valid email.'); valid = false; } else { clearFieldErr('emailErr'); }
    if(!uname || uname.length < 3 || !/^[a-zA-Z0-9_]+$/.test(uname)) { showFieldErr('usernameErr', 'Username: 3–20 chars, letters/numbers/_ only.'); valid = false; } else { clearFieldErr('usernameErr'); }
    if(!pwdValid) { showFieldErr('passErr', 'Password must meet all 4 requirements.'); valid = false; } else { clearFieldErr('passErr'); }
    if(pass !== confirm) { showFieldErr('confirmErr', 'Passwords do not match.'); valid = false; } else { clearFieldErr('confirmErr'); }
    if(valid) goStep(2);
  }

  function goStep3() {
    const first = document.getElementById('regFirst').value.trim();
    const last = document.getElementById('regLast').value.trim();
    const mobile = document.getElementById('regMobile').value.trim();
    let valid = true;
    if(!first) { showFieldErr('firstErr', 'First name is required.'); valid = false; } else { clearFieldErr('firstErr'); }
    if(!last) { showFieldErr('lastErr', 'Last name is required.'); valid = false; } else { clearFieldErr('lastErr'); }
    if(!mobile || mobile.length < 8) { showFieldErr('mobileErr', 'Enter a valid mobile number.'); valid = false; } else { clearFieldErr('mobileErr'); }
    if(valid) goStep(3);
  }

  function handleSignup() {
    if(!selectedLevel) { showFieldErr('levelErr', 'Please select your hiking level.'); return; }
    if(!document.getElementById('termsCheck').checked) {
      showAlert('Please accept the Terms of Service to continue.');
      return;
    }
    const btn = document.getElementById('signupBtn');
    btn.querySelector('.btn-text').style.display = 'none';
    btn.querySelector('.btn-loader').style.display = 'block';
    btn.disabled = true;
    setTimeout(() => {
      const profile = {
        fullName: document.getElementById('regFirst').value.trim() + ' ' + document.getElementById('regLast').value.trim(),
        email: document.getElementById('regEmail').value.trim(),
        mobile: document.getElementById('regMobile').value.trim(),
        username: document.getElementById('regUsername').value.trim(),
        homeRegion: document.getElementById('regRegion').value,
        hikingLevel: selectedLevel
      };
      localStorage.setItem('lakbay_profile', JSON.stringify(profile));
      document.querySelectorAll('.step').forEach(s => s.style.display = 'none');
      document.querySelector('.progress-bar').style.display = 'none';
      document.querySelector('.step-labels').style.display = 'none';
      document.getElementById('successScreen').classList.add('show');
    }, 1500);
  }

  function showAlert(msg) {
    document.getElementById('signupAlertMsg').textContent = msg;
    document.getElementById('signupAlert').classList.add('show');
    document.getElementById('signupAlert').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function showFieldErr(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.classList.add('show');
  }
  function clearFieldErr(id) { document.getElementById(id).classList.remove('show'); }

  function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    btn.querySelector('svg').innerHTML = isPass
      ? '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
      : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }

  function checkPwd(val) {
    clearFieldErr('passErr');
    const rules = {
      'rule-len': val.length >= 8,
      'rule-upper': /[A-Z]/.test(val),
      'rule-num': /[0-9]/.test(val),
      'rule-special': /[^a-zA-Z0-9]/.test(val)
    };
    let met = 0;
    for(const [id, pass] of Object.entries(rules)) {
      const el = document.getElementById(id);
      el.classList.toggle('met', pass);
      el.querySelector('.pwd-rule-icon').textContent = pass ? '✓' : '✕';
      if(pass) met++;
    }
    pwdValid = met === 4;
    const fill = document.getElementById('pwdStrengthFill');
    const colors = ['#c0392b','#e67e22','#f1c40f','#27ae60'];
    fill.style.width = (met/4*100) + '%';
    fill.style.background = colors[met-1] || '#c0392b';
    if(!val) { fill.style.width = '0'; }
    checkPasswordMatch();
  }

  let usernameTimer;
  function checkUsername(val) {
    clearFieldErr('usernameErr');
    const status = document.getElementById('usernameStatus');
    clearTimeout(usernameTimer);
    if(!val) { status.className = 'username-status'; return; }
    if(val.length < 3 || !/^[a-zA-Z0-9_]+$/.test(val)) { status.className = 'username-status'; return; }
    status.className = 'username-status checking';
    status.textContent = 'Checking…';
    usernameTimer = setTimeout(() => {
      const taken = ['admin','lakbay','hiking','test','user'];
      if(taken.includes(val.toLowerCase())) {
        status.className = 'username-status taken';
        status.textContent = '✕ Taken';
      } else {
        status.className = 'username-status available';
        status.textContent = '✓ Available';
      }
    }, 600);
  }

  function selectLevel(level, el) {
    selectedLevel = level;
    document.querySelectorAll('.level-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    clearFieldErr('levelErr');
  }
</script>
</body>
</html>