<?php
require_once __DIR__ . '/../middleware/collector_auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$db  = get_db();
$cid = $_SESSION['collector_id'];

// Ensure profile_photo column on collectors
try { $db->exec("ALTER TABLE collectors ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}

$collector = $db->query("SELECT * FROM collectors WHERE id=$cid")->fetch();
$error = $success = '';
$isDark = (int)($collector['dark_mode'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_info'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        if (!$full_name || !$username) {
            $error = 'Name and username are required.';
        } else {
            $dup = $db->prepare("SELECT id FROM collectors WHERE username=? AND id!=?");
            $dup->execute([$username, $cid]);
            if ($dup->fetch()) {
                $error = 'Username already taken.';
            } else {
                $db->prepare("UPDATE collectors SET full_name=?, username=? WHERE id=?")
                   ->execute([$full_name, $username, $cid]);
                $_SESSION['collector_name'] = $full_name;
                $success = 'Profile updated.';
                $linked = $db->prepare("SELECT id FROM users WHERE username=?"); $linked->execute([$username]); $lu = $linked->fetch();
                if ($lu) log_activity((int)$lu['id'], 'Updated Profile', 'Collector updated name/username', 'collector');
                $collector = $db->query("SELECT * FROM collectors WHERE id=$cid")->fetch();
            }
        }
    }

    if (isset($_POST['save_password'])) {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $collector['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $conf) {
            $error = 'Passwords do not match.';
        } else {
            $db->prepare("UPDATE collectors SET password=? WHERE id=?")
               ->execute([password_hash($new, PASSWORD_BCRYPT), $cid]);
            $success = 'Password changed.';
            $linked = $db->prepare("SELECT id FROM users WHERE username=?"); $linked->execute([$collector['username']]); $lu = $linked->fetch();
            if ($lu) log_activity((int)$lu['id'], 'Changed Password', 'Collector changed their password', 'collector');
        }
    }

    if (isset($_POST['save_photo']) && isset($_FILES['profile_photo'])) {
        $fname = save_upload($_FILES['profile_photo'], "avatar_col_{$cid}");
        if ($fname) {
            if (!empty($collector['profile_photo'])) @unlink(__DIR__ . '/../uploads/' . $collector['profile_photo']);
            $db->prepare("UPDATE collectors SET profile_photo=? WHERE id=?")->execute([$fname, $cid]);
            $success = 'Photo updated.';
            $collector = $db->query("SELECT * FROM collectors WHERE id=$cid")->fetch();
        } else {
            $error = 'Invalid image. Use JPG, PNG, or WEBP.';
        }
    }

    if (isset($_POST['remove_photo'])) {
        if (!empty($collector['profile_photo'])) @unlink(__DIR__ . '/../uploads/' . $collector['profile_photo']);
        $db->prepare("UPDATE collectors SET profile_photo=NULL WHERE id=?")->execute([$cid]);
        $success = 'Photo removed.';
        $collector = $db->query("SELECT * FROM collectors WHERE id=$cid")->fetch();
    }

    header('Location: /disbasura/collector/profile.php' . ($error ? '?err='.urlencode($error) : ($success ? '?ok=1' : ''))); exit;
}

if (isset($_GET['ok']))  $success = 'Changes saved.';
if (isset($_GET['err'])) $error   = htmlspecialchars($_GET['err']);

$initial = strtoupper(substr($collector['full_name'], 0, 1));
$photo   = !empty($collector['profile_photo']) ? '/disbasura/uploads/' . $collector['profile_photo'] : null;
?>
<!DOCTYPE html>
<html lang="en" class="<?= $isDark ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Profile — DisBasura Collector</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/collector.css"/>
</head>
<body>

<!-- Background -->
<svg class="bg-pattern" viewBox="0 0 1200 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
  <polygon points="80,60 140,26 200,60 200,128 140,162 80,128"   fill="none" stroke="rgba(76,175,128,.18)" stroke-width="1.5"/>
  <polygon points="960,30 1040,-14 1120,30 1120,118 1040,162 960,118" fill="none" stroke="rgba(76,175,128,.15)" stroke-width="1.5"/>
  <line x1="200" y1="128" x2="960" y2="74" stroke="rgba(76,175,128,.1)" stroke-width="1"/>
  <circle cx="200" cy="128" r="3.5" fill="rgba(76,175,128,.45)"/>
</svg>

<!-- Navbar -->
<nav class="col-navbar">
  <a href="/disbasura/collector/dashboard.php" class="col-nav-brand">
    <svg viewBox="0 0 72 72" fill="none" width="34" height="34">
      <defs>
        <linearGradient id="cpRingG" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#5dd96b"/><stop offset="50%" stop-color="#22a94a"/><stop offset="100%" stop-color="#0d6e30"/>
        </linearGradient>
        <linearGradient id="cpLeafG" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#7de87a"/><stop offset="100%" stop-color="#1a8a38"/>
        </linearGradient>
      </defs>
      <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#cpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
      <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#cpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
      <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#cpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#cpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#cpLeafG)"/>
      <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#cpLeafG)"/>
      <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
    </svg>
    <div><div class="col-nav-brand-text">DisBasura</div><div class="col-nav-brand-sub">Collector Portal</div></div>
  </a>
  <a href="/disbasura/logout.php?role=collector" class="col-signout">
    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
    Sign Out
  </a>
</nav>

<div class="col-shell" style="padding-top:5rem">

  <div style="margin-bottom:1.5rem">
    <a href="/disbasura/collector/dashboard.php" style="font-size:.82rem;color:rgba(255,255,255,.5);text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;margin-bottom:.75rem">← Back to Dashboard</a>
    <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.5rem;font-weight:800;color:#fff">My Profile</h1>
    <p style="font-size:.83rem;color:rgba(255,255,255,.45)">Update your photo, name, and password</p>
  </div>

  <?php if ($error):   ?><div class="alert-error" style="margin-bottom:1rem"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert-success" style="margin-bottom:1rem">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Photo card -->
  <div class="c-card avail-card" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <div style="flex-shrink:0">
      <?php if ($photo): ?>
        <img src="<?= $photo ?>" alt="Photo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--green-main);box-shadow:0 4px 14px rgba(45,134,83,.3)"/>
      <?php else: ?>
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1e5c38,#2d8653);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;border:3px solid rgba(76,175,128,.3)">
          <?= $initial ?>
        </div>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:180px">
      <div style="font-size:1.1rem;font-weight:800;color:var(--text-dark);margin-bottom:.15rem"><?= e($collector['full_name']) ?></div>
      <div style="font-size:.8rem;color:var(--text-light)">@<?= e($collector['username'] ?? '') ?> · <?= e($collector['sitio']) ?></div>
      <div style="margin-top:.85rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="save_photo" value="1">
          <label style="display:inline-flex;align-items:center;gap:.35rem;padding:.5rem 1rem;background:var(--green-main);color:#fff;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer">
            📷 Change Photo
            <input type="file" name="profile_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
          </label>
        </form>
        <?php if ($photo): ?>
        <form method="POST" onsubmit="return confirm('Remove photo?')">
          <input type="hidden" name="remove_photo" value="1">
          <button type="submit" style="padding:.5rem 1rem;border:1.5px solid var(--red);background:transparent;color:var(--red);border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer">Remove</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <!-- Info -->
    <div class="c-card avail-card">
      <div class="avail-label">Account Info</div>
      <form method="POST">
        <input type="hidden" name="save_info" value="1">
        <div style="margin-bottom:.85rem">
          <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem">Full Name</label>
          <input type="text" name="full_name" value="<?= e($collector['full_name']) ?>" required style="width:100%;padding:.7rem 1rem;border:1.5px solid var(--border-light);border-radius:9px;font-family:inherit;font-size:.9rem;color:var(--text-dark);background:#fff;outline:none;transition:border-color .18s"/>
        </div>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem">Username</label>
          <input type="text" name="username" value="<?= e($collector['username'] ?? '') ?>" required autocomplete="off" style="width:100%;padding:.7rem 1rem;border:1.5px solid var(--border-light);border-radius:9px;font-family:inherit;font-size:.9rem;color:var(--text-dark);background:#fff;outline:none;transition:border-color .18s"/>
        </div>
        <button type="submit" class="btn-complete" style="border-radius:8px;padding:.65rem 1.25rem">Save Changes</button>
      </form>
    </div>
    <!-- Password -->
    <div class="c-card avail-card">
      <div class="avail-label">Change Password</div>
      <form method="POST">
        <input type="hidden" name="save_password" value="1">
        <div style="margin-bottom:.85rem">
          <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem">Current Password</label>
          <input type="password" name="current_password" required style="width:100%;padding:.7rem 1rem;border:1.5px solid var(--border-light);border-radius:9px;font-family:inherit;font-size:.9rem;background:#fff;outline:none"/>
        </div>
        <div style="margin-bottom:.85rem">
          <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem">New Password</label>
          <input type="password" name="new_password" required minlength="6" style="width:100%;padding:.7rem 1rem;border:1.5px solid var(--border-light);border-radius:9px;font-family:inherit;font-size:.9rem;background:#fff;outline:none"/>
        </div>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem">Confirm Password</label>
          <input type="password" name="confirm_password" required style="width:100%;padding:.7rem 1rem;border:1.5px solid var(--border-light);border-radius:9px;font-family:inherit;font-size:.9rem;background:#fff;outline:none"/>
        </div>
        <button type="submit" class="btn-complete" style="border-radius:8px;padding:.65rem 1.25rem">Change Password</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
