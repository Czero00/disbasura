<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['reset_email'])) { header('Location: /disbasura/forgot-password.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw  = trim($_POST['password'] ?? '');
    $con = trim($_POST['confirm']  ?? '');
    if (strlen($pw) < 6)      $error = 'Password must be at least 6 characters.';
    elseif ($pw !== $con)     $error = 'Passwords do not match.';
    else {
        $db   = get_db();
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        $s = $db->prepare("UPDATE users SET password=? WHERE email=?");
        $s->execute([$hash, $_SESSION['reset_email']]);
        if ($s->rowCount() === 0) {
            $db->prepare("UPDATE administrators SET password=? WHERE email=?")
               ->execute([$hash, $_SESSION['reset_email']]);
        }
        unset($_SESSION['reset_email']);
        header('Location: /disbasura/login.php?reset=1'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reset Password — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg">
  <div class="auth-logo"><h1>DisBasura</h1><p>Set new password</p></div>
  <div class="auth-card">
    <h2>Reset Password</h2>
    <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="field"><label>New Password</label><input type="password" name="password" placeholder="At least 6 characters" required/></div>
      <div class="field"><label>Confirm Password</label><input type="password" name="confirm" required/></div>
      <button type="submit" class="btn-primary">Reset Password</button>
    </form>
  </div>
</div>
</body>
</html>
