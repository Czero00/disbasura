<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$db = get_db();
$registrationEnabled = (bool)$db->query('SELECT id FROM administrators LIMIT 1')->fetchColumn();
$cityStmt = $db->prepare('SELECT id FROM cities WHERE name = ? LIMIT 1');
$cityStmt->execute(['Cebu City']);
$cebuCityId = (int)$cityStmt->fetchColumn();
$barangays = $cebuCityId
    ? array_values(array_filter(get_barangays($cebuCityId), static fn($b) => strcasecmp($b['name'], 'Unassigned') !== 0))
    : [];
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $sitio     = trim($_POST['sitio']     ?? '');
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $phone     = trim($_POST['phone']     ?? '');
    // Require residents to mark their login username with the resident code.
    if (preg_match('/^RT-/i', $username)) $username = 'RT-' . substr($username, 3);

    $validBarangayIds = array_map('intval', array_column($barangays, 'id'));
    $allowedSitios = in_array($barangayId, $validBarangayIds, true) ? get_sitios_by_barangay($barangayId) : [];
    $allowedSitioNames = array_column($allowedSitios, 'name');

    if (!$registrationEnabled) {
        $error = 'Resident registration is disabled until the administrator completes first-time setup.';
    } elseif (!$full_name || !$username || !$email || !$password || !$barangayId || !$sitio) {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($barangayId, $validBarangayIds, true)) {
        $error = 'Please choose a valid Cebu City barangay.';
    } elseif (!in_array($sitio, $allowedSitioNames, true)) {
        $error = 'Please choose a sitio in the selected barangay.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!preg_match('/^RT-[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Resident usernames must start with RT- (example: RT-maria).';
    } elseif (ctype_digit($username)) {
        $error = 'Username cannot be numbers only.';
    } else {
        $db = get_db();
        $usernameCheck = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $usernameCheck->execute([$username]);
        $emailCheck = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $emailCheck->execute([$email]);
        if ($usernameCheck->fetch()) {
            $error = 'Username already taken.';
        } elseif ($emailCheck->fetch()) {
            $error = 'Email already registered.';
        } else {
            $db->prepare("INSERT INTO users (full_name,username,email,password,role,sitio,phone) VALUES (?,?,?,?,?,?,?)")
               ->execute([$full_name,$username,$email,password_hash($password,PASSWORD_BCRYPT),'resident',$sitio,$phone]);
            header('Location: /disbasura/?registered=1&show_login=1#login'); exit;
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
  <style>
    html, body { min-height:100%; height:auto; overflow-x:hidden; overflow-y:auto; font-family:'Plus Jakarta Sans',sans-serif; }
    .auth-bg { min-height:100vh; height:auto; padding:24px 16px; overflow:visible; align-items:flex-start; justify-content:center; background:linear-gradient(180deg,#484c58 0%,#484c58 9%,#001b20 22%,#001b20 100%); }
    .auth-blob { opacity:.35; }
    .auth-bg-svg { opacity:.12; }
    .auth-card { position:relative; z-index:2; width:min(100%,640px); max-height:calc(100vh - 48px); overflow-y:auto; padding:40px 40px 28px; background:#fbfbfc; border:0; border-right:7px solid #04b780; border-radius:29px; box-shadow:0 25px 75px rgba(0,0,0,.28); color:#111a30; scrollbar-color:#04b780 #eef0f2; }
    .signup-close { position:absolute; z-index:5; top:30px; right:46px; display:grid; place-items:center; width:42px; height:42px; border-radius:50%; background:#f0f3f7; color:#607b98; font-size:23px; text-decoration:none; cursor:pointer; transition:background .18s,transform .18s; }
    .signup-close:hover { background:#e4eaf1; transform:rotate(90deg); }
    .auth-logo { position:relative; z-index:1; margin:0 auto 30px; text-align:center; color:#111a30; }
    .auth-logo svg { display:block; width:60px; height:60px; margin:0 auto 13px; padding:12px; border-radius:18px; background:linear-gradient(145deg,#10c993,#00825e); box-shadow:0 6px 13px #00825e35; }
    .auth-logo h1 { font-size:1.45rem; font-weight:800; letter-spacing:-.5px; color:#111a30; }
    .auth-logo p { margin-top:3px; color:#8a9bb4; font-size:.9rem; }
    .auth-card h2 { color:#111a30; font-size:1.85rem; letter-spacing:-.8px; margin-bottom:.25rem; }
    .auth-card .sub { color:#71819b; font-size:.9rem; margin-bottom:1.65rem; }
    .auth-card form > div[style*="grid"] { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1.1rem; }
    .auth-card .field { margin-bottom:.8rem; min-width:0; }
    .auth-card .field label { display:block; color:#34445e; font-size:.83rem; font-weight:700; margin:0 0 .38rem .1rem; }
    .auth-card .field input, .auth-card .field select { width:100%; min-height:52px; padding:.75rem 1rem; border:1px solid #d8e2ed; border-radius:14px; background:#fff; color:#25334b; font-size:.95rem; backdrop-filter:none; }
    .auth-card .field input::placeholder { color:#9aa6b8; }
    .auth-card .field input:focus, .auth-card .field select:focus { border-color:#10a879; box-shadow:0 0 0 3px rgba(22,132,94,.12); }
    .auth-card .field select:disabled { background:#f1f5f3; color:#7c8b84; cursor:not-allowed; }
    .auth-card .alert-error { color:#a52935; }
    .auth-card .btn-primary { margin-top:.2rem; border-radius:13px; padding:.95rem 1rem; min-height:58px; box-shadow:0 9px 18px #07845f35; font-size:1rem; }
    .auth-card .btn-primary::after { display:none; }
    .auth-card .auth-footer { color:#71819b; margin-top:1.4rem; text-align:center; }
    .auth-card .auth-footer a { color:#009d70; font-weight:700; }
    .password-field { position:relative; }
    .password-field input { padding-right:3.1rem!important; }
    .show-password { position:absolute; right:.65rem; bottom:.54rem; display:grid; place-items:center; width:34px; height:34px; border:0; background:transparent; color:#8a9db5; cursor:pointer; }
    .show-password svg { width:20px; height:20px; }
    @media (max-width:600px) { .auth-bg { padding:14px 10px; } .auth-card { max-height:calc(100vh - 28px); padding:34px 22px 24px; border-right-width:5px; border-radius:23px; } .signup-close { right:34px; top:17px; } .auth-logo { margin-bottom:24px; } .auth-card form > div[style*="grid"] { grid-template-columns:1fr; gap:0; } .auth-card h2 { font-size:1.55rem; } }
  </style>
</head>
<body>
<div class="auth-bg">
  <div class="auth-card">
    <a class="signup-close" href="/disbasura/" aria-label="Back to landing page">&times;</a>
    <div class="auth-logo">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 4c-7.6.3-12 2.7-12 7.1 0 2.5 1.8 4.1 4.2 4.1C17 15.2 20.6 10.5 20 4Z" fill="#fff"/><path d="M4 20c1-5.1 4-8.1 9-9.7" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/></svg>
      <h1>DisBasura</h1><p>Create your account</p>
    </div>
    <h2>Create Account</h2>
    <p class="sub">Cebu City service area — choose your barangay and sitio to join.</p>
    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!$registrationEnabled): ?>
      <p class="sub">Resident sign-up is closed until an administrator completes the one-time setup.</p>
      <a class="btn-primary" style="display:block;text-align:center;text-decoration:none" href="/disbasura/admin/login.php">Go to Admin Setup</a>
    <?php else: ?>
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field"><label>Full Name *</label><input type="text" name="full_name" placeholder="Maria Santos" value="<?= htmlspecialchars($_POST['full_name']??'') ?>" required/></div>
        <div class="field"><label>Username * (must start with RT-)</label><input type="text" name="username" id="usernameInput" placeholder="RT-maria" value="<?= htmlspecialchars($_POST['username']??'') ?>" required autocomplete="username"/>
          <div id="usernameMsg" style="font-size:.72rem;margin-top:.25rem"></div>
        </div>
      </div>
      <div class="field"><label>Email *</label><input type="email" name="email" placeholder="maria@example.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required autocomplete="email"/></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field"><label>Password *</label><div class="password-field"><input id="signupPassword" type="password" name="password" placeholder="At least 6 characters" required autocomplete="new-password"/><button class="show-password" type="button" aria-label="Show password" aria-pressed="false" data-show-signup-password><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone']??'') ?>" placeholder="09XX XXX XXXX"/></div>
      </div>
      <div class="field"><label for="barangaySelect">Barangay *</label>
        <select name="barangay_id" id="barangaySelect" required>
          <option value="">Select your barangay</option>
          <?php foreach ($barangays as $barangay): ?>
          <option value="<?= (int)$barangay['id'] ?>" <?= ((int)($_POST['barangay_id'] ?? 0) === (int)$barangay['id']) ? 'selected' : '' ?>><?= htmlspecialchars($barangay['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="sitioSelect">Sitio *</label>
        <select name="sitio" id="sitioSelect" data-selected="<?= htmlspecialchars($_POST['sitio'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required disabled>
          <option value="">Select barangay first</option>
        </select>
      </div>
      <button type="submit" class="btn-primary">Create Account</button>
    </form>
    <div class="auth-footer"><a href="/disbasura/?show_login=1#login">← Already have an account? Sign in</a></div>
    <?php endif; ?>
  </div>
  <div style="display:none;text-align:center;font-size:.72rem;color:#8baa96;margin-top:1rem;padding-bottom:1rem;line-height:1.7">
    <strong style="color:#6b9e7e">UC</strong> · Developed by <strong>DisBasura Capstone Project</strong> · 2026
  </div>
</div>
<?php if ($registrationEnabled): ?><script>
const barangaySelect = document.getElementById('barangaySelect');
const sitioSelect = document.getElementById('sitioSelect');
const previouslySelectedSitio = sitioSelect.dataset.selected;

function loadSitios(barangayId, selectedSitio = '') {
  sitioSelect.replaceChildren(new Option(barangayId ? 'Loading sitios…' : 'Select barangay first', ''));
  sitioSelect.disabled = true;
  if (!barangayId) return;

  fetch('/disbasura/api/geo.php?type=sitios&barangay_id=' + encodeURIComponent(barangayId))
    .then(response => {
      if (!response.ok) throw new Error('Unable to load sitios');
      return response.json();
    })
    .then(rows => {
      sitioSelect.replaceChildren(new Option(rows.length ? 'Select your sitio' : 'No sitios in this barangay yet', ''));
      rows.forEach(sitio => {
        const option = new Option(sitio.name, sitio.name);
        option.selected = sitio.name === selectedSitio;
        sitioSelect.add(option);
      });
      sitioSelect.disabled = rows.length === 0;
    })
    .catch(() => {
      sitioSelect.replaceChildren(new Option('Could not load sitios. Please try again.', ''));
      sitioSelect.disabled = true;
    });
}

barangaySelect.addEventListener('change', () => loadSitios(barangaySelect.value));
if (barangaySelect.value) loadSitios(barangaySelect.value, previouslySelectedSitio);

// Let users check their password while they fill out the sign-up form.
const signupPasswordToggle = document.querySelector('[data-show-signup-password]');
signupPasswordToggle.addEventListener('click', () => {
  const password = document.getElementById('signupPassword');
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password';
  signupPasswordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
  signupPasswordToggle.setAttribute('aria-pressed', String(visible));
});

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
<?php endif; ?>
</body>
</html>
