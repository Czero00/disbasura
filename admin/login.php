<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/contact_validation.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: /disbasura/admin/dashboard.php'); exit;
}

$db = get_db();
try { $db->exec("ALTER TABLE administrators ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL"); } catch(Exception $e){}
$no_admin = !$db->query("SELECT id FROM administrators LIMIT 1")->fetch();
$isSetupRoute = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'setup.php';
if ($no_admin && !$isSetupRoute) { header('Location: /disbasura/admin/setup.php'); exit; }
if (!$no_admin && $isSetupRoute) { header('Location: /disbasura/admin/login.php'); exit; }
$error = '';
$setupCsrf = $_SESSION['admin_setup_csrf'] ??= bin2hex(random_bytes(32));
if (isset($_GET['cancel_setup'])) unset($_SESSION['admin_setup_pending']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['setup_csrf'] ?? '');
    if (isset($_POST['verify_admin_email'])) {
        $pending = $_SESSION['admin_setup_pending'] ?? null;
        $code = trim((string)($_POST['verification_code'] ?? ''));
        if (!hash_equals($setupCsrf, $postedCsrf)) {
            $error = 'Your setup session expired. Refresh the page and try again.';
        } elseif (!$no_admin) {
            unset($_SESSION['admin_setup_pending']);
            $error = 'The first admin account has already been created. Please sign in.';
        } elseif (!$pending || (int)$pending['expires_at'] < time()) {
            unset($_SESSION['admin_setup_pending']);
            $error = 'The verification code expired. Start the setup again.';
        } elseif ((int)$pending['attempts'] >= 5) {
            unset($_SESSION['admin_setup_pending']);
            $error = 'Too many incorrect codes. Start the setup again.';
        } elseif (!password_verify($code, $pending['code_hash'])) {
            $_SESSION['admin_setup_pending']['attempts']++;
            $error = 'That code did not match. Check your Gmail and try again.';
        } else {
            $lock = (int)$db->query("SELECT GET_LOCK('disbasura_first_admin_setup', 5)")->fetchColumn();
            if ($lock !== 1) {
                $error = 'Admin setup is busy. Please try again.';
            } else {
                try {
                    $no_admin = !$db->query('SELECT id FROM administrators LIMIT 1')->fetch();
                    if (!$no_admin) {
                        $error = 'The first admin account has already been created. Please sign in.';
                    } elseif (contact_email_exists($db, $pending['email']) || contact_phone_exists($db, $pending['phone'])) {
                        $error = 'That email or phone number is already used by another account.';
                    } else {
                        $nameCheck = $db->prepare('SELECT id FROM administrators WHERE username=? LIMIT 1');
                        $nameCheck->execute([$pending['username']]);
                        if ($nameCheck->fetchColumn()) {
                            $error = 'That admin username is already in use. Start the setup again.';
                        } else {
                            $db->prepare('INSERT INTO administrators (full_name,username,email,phone,password) VALUES (?,?,?,?,?)')
                                ->execute([$pending['full_name'], $pending['username'], $pending['email'], $pending['phone'], $pending['password_hash']]);
                            unset($_SESSION['admin_setup_pending']);
                            $_SESSION['admin_setup_csrf'] = bin2hex(random_bytes(32));
                            header('Location: /disbasura/admin/login.php?setup=complete');
                            exit;
                        }
                    }
                } finally {
                    $db->query("SELECT RELEASE_LOCK('disbasura_first_admin_setup')");
                }
            }
        }
    } elseif ($no_admin && isset($_POST['start_admin_setup'])) {
        $fn = trim((string)($_POST['full_name'] ?? ''));
        $un = trim((string)($_POST['username'] ?? ''));
        $em = normalize_contact_email((string)($_POST['email'] ?? '')) ?? '';
        $pw = (string)($_POST['password'] ?? '');
        $phone = normalize_ph_mobile((string)($_POST['phone'] ?? '')) ?? '';
        if (preg_match('/^AD-/i', $un)) $un = 'AD-' . substr($un, 3);
        if (!hash_equals($setupCsrf, $postedCsrf)) {
            $error = 'Your setup session expired. Refresh the page and try again.';
        } elseif (!$fn || strlen($fn) > 100 || !preg_match('/^AD-[a-zA-Z0-9_]+$/', $un)) {
            $error = 'Enter your name and an admin username beginning with AD-.';
        } elseif (!$em || !preg_match('/@gmail\.com$/i', $em)) {
            $error = 'Use a valid Gmail address. We will send a code to verify that you can receive email there.';
        } elseif (!$phone) {
            $error = 'Enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
        } elseif (strlen($pw) < 10) {
            $error = 'Choose a password with at least 10 characters.';
        } elseif (contact_email_exists($db, $em) || contact_phone_exists($db, $phone)) {
            $error = 'That email or phone number is already used by another account.';
        } else {
            $code = (string)random_int(100000, 999999);
            if (!send_admin_setup_verification_email($em, $fn, $code)) {
                $error = 'We could not send the verification code. The system Gmail sender must be configured first.';
            } else {
                $_SESSION['admin_setup_pending'] = [
                    'full_name' => $fn,
                    'username' => $un,
                    'email' => $em,
                    'phone' => $phone,
                    'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                    'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                    'expires_at' => time() + 600,
                    'attempts' => 0,
                ];
                header('Location: /disbasura/admin/setup.php?setup=verify');
                exit;
            }
        }
    } elseif ($no_admin) {
        $error = 'Complete the one-time admin setup form.';
    } else {
        // The login form accepts either the admin username or registered email.
        // The login form accepts either the admin username or registered email.
        $identity = trim($_POST['username'] ?? '');
        $u = $db->prepare("SELECT * FROM administrators WHERE username=? OR email=? LIMIT 1");
        $u->execute([$identity, $identity]); $u = $u->fetch();
        if ($u && password_verify($_POST['password'], $u['password'])) {
            $_SESSION['admin_id']   = $u['id'];
            $_SESSION['admin_name'] = $u['full_name'];
            $_SESSION['admin_user'] = $u['username'];
            $_SESSION['admin_role'] = 'admin';
            header('Location: /disbasura/admin/dashboard.php'); exit;
        }
        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin Login — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
  <style>
    /* Small motion cues make the admin setup and sign-in panel easier to follow. */
    .auth-card { animation: adminCardIn .55s cubic-bezier(.2,.8,.2,1) both; transition: transform .22s ease, box-shadow .22s ease; }
    .auth-card:hover { transform: translateY(-3px); box-shadow: 0 26px 70px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.3); }
    .auth-card .field input { transition: border-color .18s ease, box-shadow .18s ease, background .18s ease; }
    .auth-card .field input:focus { border-color: rgba(28,150,86,.8); box-shadow: 0 0 0 3px rgba(28,150,86,.15); }
    @keyframes adminCardIn { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @media (prefers-reduced-motion: reduce) { .auth-card { animation: none; transition: none; } }
  </style>
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
    <line x1="950" y1="506" x2="1060" y2="244" stroke="rgba(0,220,160,.1)" stroke-width="1"/>
    <circle cx="180"  cy="134" r="4"   fill="rgba(0,255,160,.5)"/>
    <circle cx="980"  cy="244" r="4"   fill="rgba(0,220,180,.45)"/>
    <circle cx="950"  cy="506" r="3.5" fill="rgba(0,200,200,.5)"/>
    <circle cx="750"  cy="150" r="2.5" fill="rgba(255,255,255,.5)"/>
    <circle cx="1100" cy="500" r="3"   fill="rgba(200,255,220,.4)"/>
  </svg>

  <div class="auth-card-wrap">
    <div class="auth-card">

      <div class="auth-brand">
        <svg class="auth-brand-icon" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="ringGradA" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#5dd96b"/>
              <stop offset="50%" stop-color="#22a94a"/>
              <stop offset="100%" stop-color="#0d6e30"/>
            </linearGradient>
            <linearGradient id="leafGradA" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#7de87a"/>
              <stop offset="100%" stop-color="#1a8a38"/>
            </linearGradient>
          </defs>
          <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#ringGradA)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
          <path d="M36 64 A28 28 0 0 1 8 36" stroke="url(#ringGradA)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="8,44 4,34 14,36" fill="#22a94a"/>
          <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#ringGradA)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M8 36 A28 28 0 0 1 36 8" stroke="url(#ringGradA)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
          <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#leafGradA)"/>
          <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#leafGradA)"/>
          <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
          <path d="M36 44 Q30 38 26 30" stroke="rgba(255,255,255,.4)" stroke-width="1" fill="none" stroke-linecap="round"/>
          <path d="M36 44 Q42 38 46 30" stroke="rgba(255,255,255,.4)" stroke-width="1" fill="none" stroke-linecap="round"/>
        </svg>
        <div class="auth-brand-text">
          <h1>DisBasura</h1>
          <p>Waste Management System</p>
        </div>
      </div>

      <span class="auth-portal-label admin">🔒 Admin Panel</span>

      <?php if($no_admin): ?>
        <h2>First Time Setup</h2>
        <div class="auth-notice">⚙️ <strong>Welcome!</strong> Create your admin account to get started. This only appears once.</div>
        <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (!empty($_SESSION['admin_setup_pending'])): $pendingAdmin = $_SESSION['admin_setup_pending']; ?>
          <p style="margin:0 0 1rem;color:#637368;font-size:.9rem">A 6-digit code was sent to <strong><?= htmlspecialchars($pendingAdmin['email'], ENT_QUOTES, 'UTF-8') ?></strong>. It expires in 10 minutes.</p>
          <form method="POST">
            <input type="hidden" name="setup_csrf" value="<?= htmlspecialchars($setupCsrf, ENT_QUOTES, 'UTF-8') ?>"/>
            <div class="field"><input type="text" name="verification_code" placeholder="6-digit Gmail code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required/></div>
            <button type="submit" name="verify_admin_email" value="1" class="btn-primary">Verify Gmail &amp; Create Admin</button>
          </form>
          <p style="margin-top:1rem;text-align:center;font-size:.82rem"><a href="/disbasura/admin/setup.php?cancel_setup=1">Use another email or start again</a></p>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="setup_csrf" value="<?= htmlspecialchars($setupCsrf, ENT_QUOTES, 'UTF-8') ?>"/>
          <input type="hidden" name="start_admin_setup" value="1"/>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:.75rem">
            <div class="field" style="margin-bottom:0">
              <input type="text" name="full_name" placeholder="Full Name" class="no-icon" required/>
            </div>
            <div class="field" style="margin-bottom:0">
              <input type="text" name="username" placeholder="Username (AD-rey)" class="no-icon" required/>
            </div>
          </div>
          <div class="field">
            <span class="field-icon">
              <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
            </span>
            <input type="email" name="email" placeholder="Admin Gmail address" autocomplete="email" required/>
          </div>
          <div class="field"><label for="adminSetupPhone">Mobile number *</label><input id="adminSetupPhone" type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '+63', ENT_QUOTES, 'UTF-8') ?>" placeholder="+63 9XXXXXXXXX" inputmode="tel" oninput="this.value=this.value.replace(/[^0-9+() .-]/g,'')" maxlength="13" required autocomplete="tel"/><small>Enter your 10-digit mobile number after +63.</small></div>
          <div class="field">
            <span class="field-icon">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1.5" fill="currentColor"/></svg>
            </span>
            <input type="password" name="password" placeholder="Password (10+ characters)" minlength="10" autocomplete="new-password" required/>
          </div>
          <button type="submit" class="btn-primary">Verify Gmail &amp; Continue</button>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <h2>Log In</h2>
        <?php if (($_GET['setup'] ?? '') === 'complete'): ?><div class="auth-notice">Admin account created successfully. Gmail ownership was verified. Sign in to continue.</div><?php endif; ?>
        <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
          <div class="field">
            <label>Username</label>
            <span class="field-icon">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </span>
            <input type="text" name="username" placeholder="Username or Email" required autocomplete="username"/>
          </div>
          <div class="field">
            <label>Password</label>
            <span class="field-icon">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1.5" fill="currentColor"/></svg>
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
      <?php endif; ?>

      <div class="auth-footer">
        <a href="/disbasura/login.php">← Resident Login</a>
        &nbsp;·&nbsp; <a href="/disbasura/collector/login.php">Collector Login</a>
      </div>

    </div>
    <div class="auth-credit">UC · DisBasura Capstone Project · 2026</div>
  </div>

</div>
</body>
</html>
