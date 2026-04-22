<?php
// ============================================================
// PROCESS FORM FIRST — before ANY output
// ============================================================
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']     ?? '');
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $conf  = trim($_POST['confirm']  ?? '');

    if (!$name || !$email || !$pass || !$conf) {
        $error = 'All fields are required.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $conf) {
        $error = 'Passwords do not match.';
    } else {
        // Registration success — go to hiker dashboard
        header('Location: ../dashboard.php');
        exit;
    }
}

// ============================================================
// Now safe to output HTML
// ============================================================
$pageTitle  = 'Create Account — LAKBAY';
$activePage = '';
$isLoggedIn = false;
$base       = '../../';
include_once $base . 'includes/header.php';
?>

<style>
.auth-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#D4E1E7,#98BBD7,#254A5A);padding:6rem 1rem 2rem;}
.auth-box{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(8,37,53,0.18);width:100%;max-width:440px;padding:2.5rem 2rem;}
.auth-back{display:inline-flex;align-items:center;gap:.4rem;color:#254A5A;font-size:.88rem;font-weight:500;text-decoration:none;margin-bottom:1.5rem;}
.auth-back:hover{color:#082535;}
.auth-back svg{width:15px;height:15px;display:block;}
.auth-icon-wrap{text-align:center;margin-bottom:1.5rem;}
.auth-icon{width:64px;height:64px;background:#254A5A;border-radius:18px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;}
.auth-icon svg{width:32px;height:32px;stroke:#fff;fill:none;display:block;}
.auth-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:#082535;margin:0 0 .25rem;}
.auth-sub{color:#254A5A;font-size:.9rem;margin:0;}
.auth-error{background:rgba(231,76,60,.08);border-left:3px solid #e74c3c;color:#7a1212;border-radius:8px;padding:.75rem 1rem;font-size:.88rem;margin-bottom:1rem;}
.auth-form{display:flex;flex-direction:column;gap:1rem;margin-top:1.5rem;}
.auth-form .fg{display:flex;flex-direction:column;gap:.35rem;}
.auth-form label{font-size:.87rem;font-weight:600;color:#082535;}
.auth-form input{width:100%;padding:.75rem 1rem;border:1.5px solid #D4E1E7;border-radius:8px;font-size:.95rem;color:#082535;background:#fff;box-sizing:border-box;font-family:inherit;transition:border-color .2s;}
.auth-form input:focus{outline:none;border-color:#254A5A;box-shadow:0 0 0 3px rgba(37,74,90,.1);}
.auth-form input::placeholder{color:#a0b4be;}
.auth-hint{font-size:.76rem;color:#809983;margin-top:.2rem;}
.auth-btn{width:100%;padding:.9rem;background:#254A5A;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;font-family:inherit;transition:background .2s;margin-top:.25rem;}
.auth-btn:hover{background:#082535;}
.auth-foot{text-align:center;margin-top:1.25rem;font-size:.88rem;color:#254A5A;}
.auth-foot a{color:#254A5A;font-weight:700;text-decoration:underline;}
.auth-foot a:hover{color:#082535;}
</style>

<div class="auth-wrap">
  <div class="auth-box">

    <a href="<?= $base ?>index.php" class="auth-back">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Home
    </a>

    <div class="auth-icon-wrap">
      <div class="auth-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <h2 class="auth-title">Join LAKBAY</h2>
      <p class="auth-sub">Create your account and start exploring</p>
    </div>

    <?php if ($error): ?>
    <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="fg">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name"
               placeholder="Juan Dela Cruz"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               required autocomplete="name">
      </div>

      <div class="fg">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               placeholder="you@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>

      <div class="fg">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="At least 6 characters"
               required autocomplete="new-password">
        <span class="auth-hint">Minimum 6 characters</span>
      </div>

      <div class="fg">
        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm"
               placeholder="Repeat your password"
               required autocomplete="new-password">
      </div>

      <button type="submit" class="auth-btn">Create Account</button>
    </form>

    <p class="auth-foot">
      Already have an account? <a href="login.php">Sign in</a>
    </p>
  </div>
</div>

<?php include_once $base . 'includes/footer.php'; ?>
