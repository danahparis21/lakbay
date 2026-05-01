<?php
// reset-password.php
require_once '../config/db.php';

$error = '';
$success = false;
$token = $_GET['token'] ?? '';
$validToken = false;
$userId = null;

// Verify token
if ($token) {
    $stmt = $pdo->prepare("
        SELECT user_id, expires_at 
        FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if ($reset) {
        $validToken = true;
        $userId = $reset['user_id'];
    } else {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $userId]);
        
        // Delete used token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LAKBAY</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #0d0500 0%, #1a0a00 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-card {
            background: white;
            border-radius: 24px;
            max-width: 450px;
            width: 100%;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #7a6a58;
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 24px;
        }
        .back-link:hover { color: #0d0500; }
        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            font-weight: 600;
            color: #0d0500;
            margin-bottom: 12px;
        }
        .subtitle {
            color: #7a6a58;
            font-size: 14px;
            margin-bottom: 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #4a3520;
            margin-bottom: 8px;
        }
        input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e8e2d8;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.2s;
            outline: none;
        }
        input:focus {
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
        }
        button {
            width: 100%;
            background: #0d0500;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        button:hover {
            background: #2a1500;
            transform: translateY(-1px);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: rgba(192,57,43,0.08);
            color: #c0392b;
            border: 1px solid rgba(192,57,43,0.2);
        }
        .alert-success {
            background: rgba(46,204,113,0.08);
            color: #2ecc71;
            border: 1px solid rgba(46,204,113,0.2);
        }
        .success-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 16px;
        }
        .login-now {
            display: inline-block;
            margin-top: 16px;
            color: #c9a84c;
            text-decoration: none;
            font-weight: 500;
        }
        @media (max-width: 500px) {
            .reset-card { padding: 28px 20px; }
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <a href="login.php" class="back-link">← Back to Login</a>
        
        <?php if ($success): ?>
            <div class="success-icon">✅</div>
            <h1>Password reset!</h1>
            <p class="subtitle">Your password has been successfully changed.</p>
            <a href="login.php" class="login-now">← Login with your new password</a>
        <?php elseif ($validToken): ?>
            <h1>Create new password</h1>
            <p class="subtitle">Enter your new password below.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="••••••••" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>
                <button type="submit">Reset Password</button>
            </form>
        <?php else: ?>
            <h1>Invalid link</h1>
            <p class="subtitle"><?= htmlspecialchars($error) ?></p>
            <a href="forgot-password.php" style="color: #c9a84c; text-decoration: none;">Request new reset link →</a>
        <?php endif; ?>
    </div>
</body>
</html>