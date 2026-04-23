<?php
// ============================================================
// PROCESS FORM FIRST — before ANY output (including header.php)
// ============================================================
require_once '../../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if ($email && $pass) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
    session_start();
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    $map = [
        'hiker'   => '../../pages/dashboard.php',
        'guide'   => '../../pages/dashboard-guide.php',
        'manager' => '../../pages/dashboard-manager.php',
        'admin'   => '../../pages/dashboard-admin.php',
    ];
    $dest = $map[$user['role']] ?? '../../pages/dashboard.php';

    $roleLabels = [
        'hiker'   => 'Hiker',
        'guide'   => 'Tour Guide',
        'manager' => 'Mountain Manager',
        'admin'   => 'Tourism Admin',
    ];
    $roleMessages = [
        'hiker'   => 'Preparing your trail exploration...',
        'guide'   => 'Loading your guide dashboard and assigned trails...',
        'manager' => 'Fetching your mountain management tools...',
        'admin'   => 'Setting up your admin control panel...',
    ];

    $firstName   = explode(' ', $user['name'])[0];
    $roleLabel   = $roleLabels[$user['role']]   ?? 'User';
    $roleMessage = $roleMessages[$user['role']] ?? 'Loading your dashboard...';
    $roleClass   = 'role-' . $user['role'];

    // Show popup then redirect
    echo "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1'>
      <title>Welcome — LAKBAY</title>
      <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter','Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#D4E1E7,#98BBD7,#254A5A);}
        .popup{background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(8,37,53,0.18);padding:2.5rem 2rem;max-width:380px;width:90%;text-align:center;animation:popIn .4s cubic-bezier(.34,1.56,.64,1) both;}
        @keyframes popIn{from{opacity:0;transform:scale(.85) translateY(20px);}to{opacity:1;transform:scale(1) translateY(0);}}
        .avatar{width:72px;height:72px;border-radius:50%;background:#254A5A;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;animation:avatarPop .5s .15s cubic-bezier(.34,1.56,.64,1) both;}
        @keyframes avatarPop{from{opacity:0;transform:scale(0);}to{opacity:1;transform:scale(1);}}
        .avatar svg{width:34px;height:34px;stroke:#fff;fill:none;stroke-width:2;}
        .role-badge{display:inline-block;padding:.3rem .85rem;border-radius:20px;font-size:.78rem;font-weight:600;margin-bottom:1rem;letter-spacing:.03em;}
        .role-hiker{background:#E1F5EE;color:#0F6E56;}
        .role-admin{background:#EEEDFE;color:#3C3489;}
        .role-manager{background:#FAEEDA;color:#854F0B;}
        .role-guide{background:#E6F1FB;color:#0C447C;}
        h2{font-size:1.5rem;font-weight:600;color:#082535;margin-bottom:.35rem;}
        h2 span{color:#254A5A;}
        p{font-size:.9rem;color:#6b8a9a;margin-bottom:1.5rem;line-height:1.6;}
        .progress-bar{height:4px;background:#D4E1E7;border-radius:4px;overflow:hidden;margin-bottom:1.25rem;}
        .progress-fill{height:100%;background:#254A5A;border-radius:4px;animation:fill 2.5s linear forwards;}
        @keyframes fill{from{width:0%;}to{width:100%;}}
        .dots{display:flex;justify-content:center;gap:6px;}
        .dot{width:7px;height:7px;border-radius:50%;background:#254A5A;opacity:.25;animation:pulse 1.2s ease-in-out infinite;}
        .dot:nth-child(2){animation-delay:.2s;}
        .dot:nth-child(3){animation-delay:.4s;}
        @keyframes pulse{0%,100%{opacity:.25;}50%{opacity:1;}}
      </style>
    </head>
    <body>
      <div class='popup'>
        <div class='avatar'>
          <svg viewBox='0 0 24 24'><path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/><circle cx='12' cy='7' r='4'/></svg>
        </div>
        <span class='role-badge $roleClass'>$roleLabel</span>
        <h2>Welcome back, <span>$firstName!</span></h2>
        <p>$roleMessage</p>
        <div class='progress-bar'><div class='progress-fill'></div></div>
        <div class='dots'><div class='dot'></div><div class='dot'></div><div class='dot'></div></div>
      </div>
      <script>setTimeout(()=>{ window.location.href='$dest'; }, 2600);</script>
    </body>
    </html>";
    exit;

        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}

// ============================================================
// Now safe to output HTML
// ============================================================
$pageTitle  = 'Login — LAKBAY';
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
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/><circle cx="19" cy="5" r="2"/></svg>
      </div>
      <h2 class="auth-title">Welcome Back</h2>
      <p class="auth-sub">Sign in to your LAKBAY account</p>
    </div>

    <?php if ($error): ?>
    <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="fg">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               placeholder="your.email@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>

      <div class="fg">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password"
               required autocomplete="current-password">
      </div>

      <button type="submit" class="auth-btn">Sign In</button>
    </form>

    <p class="auth-foot">
      Don't have an account? <a href="signup.php">Create one</a>
    </p>
  </div>
</div>

<?php include_once $base . 'includes/footer.php'; ?>