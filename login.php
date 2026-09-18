<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['resident','leader'])) {
    header('Location: /disbasura/'.($_SESSION['role']==='leader'?'leader':'resident').'/dashboard.php'); exit;
}

$error = '';
$registered = $_GET['registered'] ?? '';
$reset      = $_GET['reset'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username=? AND role IN ('resident','leader')");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['sitio']     = $user['sitio'] ?? '';
        header('Location: /disbasura/'.($user['role']==='leader'?'leader':'resident').'/dashboard.php'); exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Resident Login — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg">

  <!-- Glow blobs -->
  <div class="auth-blob auth-blob-1"></div>
  <div class="auth-blob auth-blob-2"></div>
  <div class="auth-blob auth-blob-3"></div>
  <div class="auth-blob auth-blob-4"></div>

  <!-- Background: hexagons + network lines -->
  <svg class="auth-bg-svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <!-- Hexagons -->
    <polygon points="60,100 120,66 180,100 180,168 120,202 60,168"   fill="none" stroke="rgba(255,255,255,.12)" stroke-width="1.5"/>
    <polygon points="900,50 980,6 1060,50 1060,138 980,182 900,138"  fill="none" stroke="rgba(255,255,255,.1)" stroke-width="1.5"/>
    <polygon points="980,200 1060,156 1140,200 1140,288 1060,332 980,288" fill="none" stroke="rgba(0,220,160,.15)" stroke-width="1.5"/>
    <polygon points="30,350 110,306 190,350 190,438 110,482 30,438"  fill="none" stroke="rgba(255,255,255,.08)" stroke-width="1"/>
    <polygon points="850,450 950,394 1050,450 1050,562 950,618 850,562" fill="none" stroke="rgba(0,200,160,.1)" stroke-width="1.5"/>
    <polygon points="100,550 200,494 300,550 300,662 200,718 100,662" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="1"/>
    <polygon points="1050,600 1130,554 1200,590 1200,680 1130,720 1060,680" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="1"/>
    <!-- Network lines -->
    <line x1="180" y1="134" x2="60"  y2="350" stroke="rgba(0,220,160,.15)" stroke-width="1"/>
    <line x1="180" y1="134" x2="900" y2="94"  stroke="rgba(0,220,160,.1)"  stroke-width="1"/>
    <line x1="900" y1="94"  x2="980" y2="244" stroke="rgba(0,220,160,.12)" stroke-width="1"/>
    <line x1="110" y1="482" x2="200" y2="606" stroke="rgba(0,220,160,.1)"  stroke-width="1"/>
    <line x1="950" y1="506" x2="1060" y2="244" stroke="rgba(0,220,160,.1)" stroke-width="1"/>
    <line x1="0"   y1="600" x2="300" y2="606" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
    <line x1="1200" y1="300" x2="1050" y2="506" stroke="rgba(255,255,255,.07)" stroke-width="1"/>
    <!-- Glow dots -->
    <circle cx="180"  cy="134" r="4" fill="rgba(0,255,160,.5)"/>
    <circle cx="980"  cy="244" r="4" fill="rgba(0,220,180,.45)"/>
    <circle cx="110"  cy="482" r="3" fill="rgba(0,255,160,.4)"/>
    <circle cx="950"  cy="506" r="3.5" fill="rgba(0,200,200,.5)"/>
    <circle cx="200"  cy="606" r="3" fill="rgba(0,255,160,.35)"/>
    <circle cx="1060" cy="244" r="3" fill="rgba(0,220,160,.4)"/>
    <!-- Sparkles -->
    <circle cx="750" cy="150" r="2.5" fill="rgba(255,255,255,.5)"/>
    <circle cx="400" cy="680" r="2"   fill="rgba(255,255,255,.4)"/>
    <circle cx="1100" cy="500" r="3"  fill="rgba(200,255,220,.4)"/>
    <circle cx="200"  cy="250" r="2"  fill="rgba(255,255,255,.3)"/>
  </svg>

  <!-- Card -->
  <div class="auth-card-wrap">
    <div class="auth-card">

      <!-- Logo -->
      <div class="auth-brand">
        <svg class="auth-brand-icon" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="ringGrad" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#5dd96b"/>
              <stop offset="50%" stop-color="#22a94a"/>
              <stop offset="100%" stop-color="#0d6e30"/>
            </linearGradient>
            <linearGradient id="leafGrad" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#7de87a"/>
              <stop offset="100%" stop-color="#1a8a38"/>
            </linearGradient>
          </defs>
          <!-- Outer recycling ring — top-right arc with arrow -->
          <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#ringGrad)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <!-- Arrow head top-right -->
          <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
          <!-- Bottom-left arc with arrow -->
          <path d="M36 64 A28 28 0 0 1 8 36" stroke="url(#ringGrad)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <!-- Arrow head bottom-left -->
          <polygon points="8,44 4,34 14,36" fill="#22a94a"/>
          <!-- Right-to-bottom arc -->
          <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#ringGrad)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <!-- Left-to-top arc -->
          <path d="M8 36 A28 28 0 0 1 36 8" stroke="url(#ringGrad)" stroke-width="6" fill="none" stroke-linecap="round"/>

          <!-- Plant stem -->
          <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
          <!-- Main left leaf -->
          <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#leafGrad)"/>
          <!-- Main right leaf -->
          <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#leafGrad)"/>
          <!-- Top small leaf -->
          <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
          <!-- Leaf veins -->
          <path d="M36 44 Q30 38 26 30" stroke="rgba(255,255,255,.4)" stroke-width="1" fill="none" stroke-linecap="round"/>
          <path d="M36 44 Q42 38 46 30" stroke="rgba(255,255,255,.4)" stroke-width="1" fill="none" stroke-linecap="round"/>
        </svg>
        <div class="auth-brand-text">
          <h1>DisBasura</h1>
          <p>Waste Management System</p>
        </div>
      </div>

      <!-- Portal label -->
      <span class="auth-portal-label resident">🏠 Resident Portal</span>

      <h2>Log In</h2>

      <?php if($registered): ?><div class="alert-success">✅ Account created! You can now sign in.</div><?php endif; ?>
      <?php if($reset): ?><div class="alert-success">✅ Password reset successful!</div><?php endif; ?>
      <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>Username</label>
          <span class="field-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
          </span>
          <input type="text" name="username" placeholder="Username or Email" required autocomplete="username"/>
        </div>
        <div class="field">
          <label>Password</label>
          <span class="field-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
              <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
            </svg>
          </span>
          <input type="password" name="password" placeholder="Password" required/>
        </div>
        <div class="auth-row">
          <label class="auth-remember">
            <input type="checkbox" name="remember"/> Remember Me
          </label>
          <a href="/disbasura/forgot-password.php" class="auth-forgot">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-primary">Sign In</button>
      </form>

      <div class="auth-footer">
        No account? <a href="/disbasura/register.php">Create one</a>
        &nbsp;·&nbsp; <a href="/disbasura/admin/login.php">Admin</a>
        &nbsp;·&nbsp; <a href="/disbasura/collector/login.php">Collector</a>
      </div>

    </div>
    <div class="auth-credit">UC · DisBasura Capstone Project · 2026</div>
  </div>

</div>
</body>
</html>
