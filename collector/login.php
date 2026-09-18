<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['collector_id'])) {
    header('Location: /disbasura/collector/dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM collectors WHERE username=?");
    $stmt->execute([$username]);
    $c = $stmt->fetch();
    if ($c && $c['password'] && password_verify($password, $c['password'])) {
        $_SESSION['collector_id']   = $c['id'];
        $_SESSION['collector_name'] = $c['full_name'];
        $_SESSION['collector_sitio']= $c['sitio'];
        header('Location: /disbasura/collector/dashboard.php'); exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Collector Login — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg">

  <div class="auth-blob auth-blob-1"></div>
  <div class="auth-blob auth-blob-2"></div>
  <div class="auth-blob auth-blob-3"></div>
  <div class="auth-blob auth-blob-4"></div>

  <svg class="auth-bg-svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <polygon points="60,100 120,66 180,100 180,168 120,202 60,168"   fill="none" stroke="rgba(255,255,255,.12)" stroke-width="1.5"/>
    <polygon points="900,50 980,6 1060,50 1060,138 980,182 900,138"  fill="none" stroke="rgba(255,255,255,.1)" stroke-width="1.5"/>
    <polygon points="980,200 1060,156 1140,200 1140,288 1060,332 980,288" fill="none" stroke="rgba(0,220,160,.15)" stroke-width="1.5"/>
    <polygon points="30,350 110,306 190,350 190,438 110,482 30,438"  fill="none" stroke="rgba(255,255,255,.08)" stroke-width="1"/>
    <polygon points="850,450 950,394 1050,450 1050,562 950,618 850,562" fill="none" stroke="rgba(0,200,160,.1)" stroke-width="1.5"/>
    <line x1="180" y1="134" x2="900" y2="94"  stroke="rgba(0,220,160,.1)" stroke-width="1"/>
    <line x1="900" y1="94"  x2="980" y2="244" stroke="rgba(0,220,160,.12)" stroke-width="1"/>
    <line x1="110" y1="482" x2="200" y2="606" stroke="rgba(0,220,160,.1)" stroke-width="1"/>
    <circle cx="180"  cy="134" r="4"   fill="rgba(0,255,160,.5)"/>
    <circle cx="980"  cy="244" r="4"   fill="rgba(0,220,180,.45)"/>
    <circle cx="750"  cy="150" r="2.5" fill="rgba(255,255,255,.5)"/>
    <circle cx="1100" cy="500" r="3"   fill="rgba(200,255,220,.4)"/>
  </svg>

  <div class="auth-card-wrap">
    <div class="auth-card">

      <div class="auth-brand">
        <svg class="auth-brand-icon" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="ringGradC" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#5dd96b"/>
              <stop offset="50%" stop-color="#22a94a"/>
              <stop offset="100%" stop-color="#0d6e30"/>
            </linearGradient>
            <linearGradient id="leafGradC" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#7de87a"/>
              <stop offset="100%" stop-color="#1a8a38"/>
            </linearGradient>
          </defs>
          <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#ringGradC)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
          <path d="M36 64 A28 28 0 0 1 8 36" stroke="url(#ringGradC)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="8,44 4,34 14,36" fill="#22a94a"/>
          <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#ringGradC)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M8 36 A28 28 0 0 1 36 8" stroke="url(#ringGradC)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
          <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#leafGradC)"/>
          <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#leafGradC)"/>
          <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
          <path d="M36 44 Q30 38 26 30" stroke="rgba(255,255,255,.4)" stroke-width="1" fill="none" stroke-linecap="round"/>
          <path d="M36 44 Q42 38 46 30" stroke="rgba(255,255,255,.4)" stroke-width="1" fill="none" stroke-linecap="round"/>
        </svg>
        <div class="auth-brand-text">
          <h1>DisBasura</h1>
          <p>Waste Management System</p>
        </div>
      </div>

      <span class="auth-portal-label collector">🚛 Collector Portal</span>

      <h2>Log In</h2>

      <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>Username</label>
          <span class="field-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
          </span>
          <input type="text" name="username" placeholder="Username or Email" required autofocus autocomplete="username"/>
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
          <span style="font-size:.8rem;color:rgba(20,60,40,.5)">Contact admin to reset</span>
        </div>
        <button type="submit" class="btn-primary">Sign In</button>
      </form>

      <div class="auth-footer">
        <a href="/disbasura/login.php">← Resident Login</a>
        &nbsp;·&nbsp; <a href="/disbasura/admin/login.php">Admin Login</a>
      </div>

    </div>
    <div class="auth-credit">UC · DisBasura Capstone Project · 2026</div>
  </div>

</div>
</body>
</html>
