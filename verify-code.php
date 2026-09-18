<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
$email = $_GET['email'] ?? $_POST['email'] ?? '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM reset_codes WHERE email=? AND code=? AND used=0");
    $stmt->execute([$email, $code]);
    $rec  = $stmt->fetch();
    if (!$rec) { $error = 'Invalid code. Please try again.'; }
    elseif (new DateTime('now',new DateTimeZone('Asia/Manila')) > new DateTime($rec['expires_at'])) { $error = 'Code has expired. Please request a new one.'; }
    else {
        $db->prepare("UPDATE reset_codes SET used=1 WHERE email=? AND code=?")->execute([$email,$code]);
        $_SESSION['reset_email'] = $email;
        header('Location: /disbasura/reset-password.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Verify Code — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg">
  <div class="auth-logo"><h1>DisBasura</h1><p>Enter your code</p></div>
  <div class="auth-card">
    <h2>Check Your Email</h2>
    <p class="sub">We sent a 6-digit code to <strong><?= htmlspecialchars($email) ?></strong></p>
    <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>"/>
      <div class="field"><label>Reset Code</label><input type="text" name="code" placeholder="000000" maxlength="6" required style="font-size:1.5rem;letter-spacing:.5rem;text-align:center"/></div>
      <button type="submit" class="btn-primary">Verify Code</button>
    </form>
    <div class="auth-footer"><a href="/disbasura/forgot-password.php">← Request new code</a></div>
  </div>
</div>
</body>
</html>
