<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$error   = '';
$success = '';
$show_code = ''; // show code on screen (XAMPP has no mail server)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $db    = get_db();
    $stmt  = $db->prepare("SELECT * FROM users WHERE email=? AND role IN ('resident','leader')");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $stmt = $db->prepare("SELECT * FROM administrators WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        $error = 'No account found with that email address.';
    } else {
        $code    = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTime('now', new DateTimeZone('Asia/Manila')))
                    ->modify('+10 minutes')
                    ->format('Y-m-d H:i:s');

        $db->prepare("DELETE FROM reset_codes WHERE email=?")->execute([$email]);
        $db->prepare("INSERT INTO reset_codes (email,code,expires_at) VALUES (?,?,?)")
           ->execute([$email, $code, $expires]);

        // Try to send email — if it fails, show code on screen
        $sent = false;
        if (file_exists(__DIR__ . '/includes/mailer.php')) {
            require_once __DIR__ . '/includes/mailer.php';
            try { $sent = @send_reset_email($email, $code); } catch (Exception $e) { $sent = false; }
        }

        if ($sent) {
            header('Location: /disbasura/verify-code.php?email=' . urlencode($email)); exit;
        } else {
            // Email failed (no SMTP on XAMPP) — show code directly on screen
            $show_code = $code;
            $success   = 'Email could not be sent (no mail server). Your reset code is shown below — use it within 10 minutes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Forgot Password — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg">
  <div class="auth-card-wrap">
    <div class="auth-card">

      <!-- Brand -->
      <div class="auth-brand">
        <svg viewBox="0 0 72 72" fill="none" width="52" height="52">
          <defs>
            <linearGradient id="fpRingG" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#5dd96b"/>
              <stop offset="50%"  stop-color="#22a94a"/>
              <stop offset="100%" stop-color="#0d6e30"/>
            </linearGradient>
            <linearGradient id="fpLeafG" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#7de87a"/>
              <stop offset="100%" stop-color="#1a8a38"/>
            </linearGradient>
          </defs>
          <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#fpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
          <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#fpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
          <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#fpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#fpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#fpLeafG)"/>
          <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#fpLeafG)"/>
          <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
        </svg>
        <div class="auth-brand-text">
          <h1>DisBasura</h1>
          <p>Waste Management System</p>
        </div>
      </div>

      <h2>Forgot Password?</h2>

      <?php if (!$show_code): ?>
        <p class="sub">Enter your registered email and we'll give you a 6-digit reset code.</p>
      <?php endif; ?>

      <?php if ($error):   ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <?php if ($show_code): ?>
        <!-- Show reset code directly on screen -->
        <div style="background:rgba(255,255,255,.15);border:2px dashed rgba(255,255,255,.4);border-radius:14px;padding:1.5rem;text-align:center;margin:1rem 0">
          <div style="font-size:.78rem;color:rgba(20,60,40,.65);font-weight:600;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em">Your Reset Code</div>
          <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:2.4rem;font-weight:900;letter-spacing:.5rem;color:#0d2a1e;line-height:1"><?= htmlspecialchars($show_code) ?></div>
          <div style="font-size:.74rem;color:rgba(20,60,40,.5);margin-top:.5rem">Valid for 10 minutes</div>
        </div>
        <a href="/disbasura/verify-code.php?email=<?= urlencode($_POST['email'] ?? '') ?>" class="btn-primary" style="display:block;text-align:center;text-decoration:none;margin-top:.5rem">
          Enter This Code →
        </a>
      <?php else: ?>
        <form method="POST">
          <div class="field">
            <label>Email Address</label>
            <span class="field-icon">
              <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
            </span>
            <input type="email" name="email" placeholder="you@email.com" required autocomplete="email"/>
          </div>
          <button type="submit" class="btn-primary">Get Reset Code</button>
        </form>
      <?php endif; ?>

      <div class="auth-footer" style="margin-top:.9rem">
        <a href="/disbasura/login.php">← Back to Sign In</a>
      </div>

    </div>
  </div>
</div>
</body>
</html>
