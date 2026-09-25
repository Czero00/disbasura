<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$db = get_db();
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

    $validBarangayIds = array_map('intval', array_column($barangays, 'id'));
    $allowedSitios = in_array($barangayId, $validBarangayIds, true) ? get_sitios_by_barangay($barangayId) : [];
    $allowedSitioNames = array_column($allowedSitios, 'name');

    if (!$full_name || !$username || !$email || !$password || !$barangayId || !$sitio) {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($barangayId, $validBarangayIds, true)) {
        $error = 'Please choose a valid Cebu City barangay.';
    } elseif (!in_array($sitio, $allowedSitioNames, true)) {
        $error = 'Please choose a sitio in the selected barangay.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers and underscores.';
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
    html, body { min-height: 100%; height: auto; overflow-x: hidden; overflow-y: auto; }
    .auth-bg { min-height: 100vh; height: auto; flex-direction: column; justify-content: center; gap: 1rem; padding: 2.5rem 1rem; overflow: visible; }
    .auth-logo, .auth-card { position: relative; z-index: 1; }
    .auth-logo { text-align: center; margin: 0 auto; }
    .auth-logo svg { display: block; width: 48px; height: 42px; margin: 0 auto .25rem; }
    .auth-logo h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.8rem; font-weight: 800; color: #fff; letter-spacing: -.5px; }
    .auth-logo p { color: rgba(255,255,255,.78); font-size: .9rem; }
    .auth-card { width: min(100%, 560px); padding: 2rem 2.25rem; background: rgba(255,255,255,.97); border: 1px solid rgba(255,255,255,.65); border-radius: 22px; box-shadow: 0 22px 65px rgba(0,0,0,.24); backdrop-filter: blur(12px); }
    .auth-card h2 { color: #14231d; font-size: 1.6rem; margin-bottom: .35rem; }
    .auth-card .sub { color: #63776d; font-size: .88rem; margin-bottom: 1.2rem; }
    .auth-card form > div[style*="grid"] { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    .auth-card .field { margin-bottom: .75rem; min-width: 0; }
    .auth-card .field label { display: block; color: #34483e; font-size: .78rem; font-weight: 700; margin: 0 0 .35rem .15rem; }
    .auth-card .field input, .auth-card .field select { width: 100%; min-height: 44px; padding: .68rem .9rem; border: 1px solid #d5e2db; border-radius: 10px; background: #fff; color: #17251f; font-size: .88rem; backdrop-filter: none; }
    .auth-card .field input:focus, .auth-card .field select:focus { border-color: #16845e; box-shadow: 0 0 0 3px rgba(22,132,94,.12); }
    .auth-card .field select:disabled { background: #f1f5f3; color: #7c8b84; cursor: not-allowed; }
    .auth-card .alert-error { color: #a52935; }
    .auth-card .btn-primary { margin-top: .15rem; border-radius: 10px; padding: .8rem 1rem; box-shadow: none; }
    .auth-card .btn-primary::after { display: none; }
    .auth-card .auth-footer { color: #65766e; margin-top: 1rem; }
    .auth-card .auth-footer a { color: #087b57; }
    @media (max-width: 520px) { .auth-bg { padding: 1.25rem .75rem; gap: .75rem; } .auth-card { padding: 1.4rem 1.1rem; border-radius: 18px; } .auth-card form > div[style*="grid"] { grid-template-columns: 1fr; gap: 0; } .auth-card h2 { font-size: 1.4rem; } }
  </style>
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
    <p class="sub">Cebu City service area — choose your barangay and sitio to join.</p>
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
  </div>
  <div style="text-align:center;font-size:.72rem;color:#8baa96;margin-top:1rem;padding-bottom:1rem;line-height:1.7">
    <strong style="color:#6b9e7e">UC</strong> · Developed by <strong>DisBasura Capstone Project</strong> · 2026
  </div>
</div>
<script>
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
