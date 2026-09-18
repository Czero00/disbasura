<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$cities = get_cities();
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $sitio     = trim($_POST['sitio']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');

    if (!$full_name || !$username || !$email || !$password || !$sitio) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers and underscores.';
    } elseif (ctype_digit($username)) {
        $error = 'Username cannot be numbers only.';
    } else {
        $db = get_db();
        if ($db->prepare("SELECT id FROM users WHERE username=?")->execute([$username]) && $db->query("SELECT id FROM users WHERE username='$username'")->fetch()) {
            $error = 'Username already taken.';
        } elseif ($db->query("SELECT id FROM users WHERE email='".addslashes($email)."'")->fetch()) {
            $error = 'Email already registered.';
        } else {
            $db->prepare("INSERT INTO users (full_name,username,email,password,role,sitio,phone) VALUES (?,?,?,?,?,?,?)")
               ->execute([$full_name,$username,$email,password_hash($password,PASSWORD_BCRYPT),'resident',$sitio,$phone]);
            header('Location: /disbasura/login.php?registered=1'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg">
  <div class="auth-logo">
    <svg viewBox="0 0 260 220" fill="none" width="70" height="60">
      <path d="M108 30 Q130 18 152 30" stroke="#3cb371" stroke-width="4" fill="none" stroke-linecap="round"/>
      <polygon points="152,24 162,30 152,36" fill="#3cb371"/>
      <path d="M200 78 Q212 110 200 142" stroke="#3cb371" stroke-width="4" fill="none" stroke-linecap="round"/>
      <polygon points="194,142 200,154 206,142" fill="#3cb371"/>
      <path d="M152 188 Q130 200 108 188" stroke="#3cb371" stroke-width="4" fill="none" stroke-linecap="round"/>
      <polygon points="108,194 98,188 108,182" fill="#3cb371"/>
      <path d="M60 142 Q48 110 60 78" stroke="#3cb371" stroke-width="4" fill="none" stroke-linecap="round"/>
      <polygon points="66,78 60,66 54,78" fill="#3cb371"/>
      <rect x="120" y="62" width="20" height="10" rx="4" fill="none" stroke="#3cb371" stroke-width="2.5"/>
      <rect x="98" y="72" width="64" height="10" rx="4" fill="none" stroke="#3cb371" stroke-width="2.5"/>
      <rect x="104" y="84" width="52" height="52" rx="4" fill="none" stroke="#3cb371" stroke-width="2.5"/>
    </svg>
    <h1>DisBasura</h1><p>Create your account</p>
  </div>
  <div class="auth-card">
    <h2>Create Account</h2>
    <p class="sub">Join your sitio's garbage collection system</p>
    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field"><label>Full Name *</label><input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name']??'') ?>" required/></div>
        <div class="field"><label>Username *</label><input type="text" name="username" id="usernameInput" value="<?= htmlspecialchars($_POST['username']??'') ?>" required autocomplete="off"/>
          <div id="usernameMsg" style="font-size:.72rem;margin-top:.25rem"></div>
        </div>
      </div>
      <div class="field"><label>Email *</label><input type="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>" required/></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field"><label>Password *</label><input type="password" name="password" required/></div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone']??'') ?>" placeholder="09XX XXX XXXX"/></div>
      </div>
      <div class="field"><label>City *</label>
        <select id="citySelect" required>
          <option value="">Select city</option>
          <?php foreach ($cities as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Barangay *</label>
        <select id="barangaySelect" required disabled>
          <option value="">Select city first</option>
        </select>
      </div>
      <div class="field"><label>Sitio *</label>
        <select name="sitio" id="sitioSelect" required disabled>
          <option value="">Select barangay first</option>
        </select>
      </div>
      <button type="submit" class="btn-primary">Create Account</button>
    </form>
    <div class="auth-footer"><a href="/disbasura/login.php">← Already have an account? Sign in</a></div>
  </div>
  <div style="text-align:center;font-size:.72rem;color:#8baa96;margin-top:1rem;padding-bottom:1rem;line-height:1.7">
    <strong style="color:#6b9e7e">UC</strong> · Developed by <strong>DisBasura Capstone Project</strong> · 2026
  </div>
</div>
<script>
const citySel = document.getElementById('citySelect');
const brgySel = document.getElementById('barangaySelect');
const sitioSel = document.getElementById('sitioSelect');

citySel.addEventListener('change', function(){
  brgySel.innerHTML = '<option value="">Loading…</option>';
  brgySel.disabled = true;
  sitioSel.innerHTML = '<option value="">Select barangay first</option>';
  sitioSel.disabled = true;
  if (!this.value) { brgySel.innerHTML = '<option value="">Select city first</option>'; return; }
  fetch('/disbasura/api/geo.php?type=barangays&city_id=' + this.value)
    .then(r => r.json())
    .then(rows => {
      brgySel.innerHTML = '<option value="">Select barangay</option>' +
        rows.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
      brgySel.disabled = false;
    });
});

brgySel.addEventListener('change', function(){
  sitioSel.innerHTML = '<option value="">Loading…</option>';
  sitioSel.disabled = true;
  if (!this.value) { sitioSel.innerHTML = '<option value="">Select barangay first</option>'; return; }
  fetch('/disbasura/api/geo.php?type=sitios&barangay_id=' + this.value)
    .then(r => r.json())
    .then(rows => {
      if (!rows.length) { sitioSel.innerHTML = '<option value="">No sitios in this barangay yet</option>'; return; }
      sitioSel.innerHTML = '<option value="">Select sitio</option>' +
        rows.map(s => `<option value="${s.name}">${s.name}</option>`).join('');
      sitioSel.disabled = false;
    });
});
</script>
<script>
let usernameTimer;
document.getElementById('usernameInput').addEventListener('input', function(){
  clearTimeout(usernameTimer);
  const val = this.value.trim();
  const msg = document.getElementById('usernameMsg');
  if(!val){ msg.textContent=''; return; }
  usernameTimer = setTimeout(()=>{
    fetch('/disbasura/api/check-username.php?username='+encodeURIComponent(val))
      .then(r=>r.json())
      .then(d=>{
        msg.textContent = d.available ? '✅ '+d.message : '❌ '+(d.error||'Taken');
        msg.style.color = d.available ? 'var(--green-main)' : 'var(--red)';
      });
  }, 400);
});
</script>
</body>
</html>
