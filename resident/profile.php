<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$db  = get_db();
$uid = $_SESSION['user_id'];

// Ensure profile_photo column exists
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}

$user  = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
$prefs = get_preferences($uid);
$lang  = get_lang($uid);
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_info'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        if (!$full_name || !$username) {
            $error = 'Name and username are required.';
        } else {
            $dup = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $dup->execute([$username, $uid]);
            if ($dup->fetch()) {
                $error = 'That username is already taken.';
            } else {
                $db->prepare("UPDATE users SET full_name=?, username=? WHERE id=?")
                   ->execute([$full_name, $username, $uid]);
                $_SESSION['full_name'] = $full_name;
                $_SESSION['username']  = $username;
                $success = 'Profile updated.';
                log_activity($uid, 'Updated Profile', 'Resident updated name/username', 'resident');
                $user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
            }
        }
    }

    if (isset($_POST['save_password'])) {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $conf) {
            $error = 'Passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")
               ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            $success = 'Password changed.';
            log_activity($uid, 'Changed Password', 'Resident changed their password', 'resident');
        }
    }

    if (isset($_POST['save_photo']) && isset($_FILES['profile_photo'])) {
        $fname = save_upload($_FILES['profile_photo'], "avatar_user_{$uid}");
        if ($fname) {
            if (!empty($user['profile_photo'])) @unlink(__DIR__ . '/../uploads/' . $user['profile_photo']);
            $db->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$fname, $uid]);
            $success = 'Profile photo updated.';
            $user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
        } else {
            $error = 'Invalid image. Use JPG, PNG, or WEBP.';
        }
    }

    if (isset($_POST['remove_photo'])) {
        if (!empty($user['profile_photo'])) @unlink(__DIR__ . '/../uploads/' . $user['profile_photo']);
        $db->prepare("UPDATE users SET profile_photo=NULL WHERE id=?")->execute([$uid]);
        $success = 'Photo removed.';
        $user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
    }

    header('Location: /disbasura/resident/profile.php' . ($error ? '?err='.urlencode($error) : ($success ? '?ok=1' : ''))); exit;
}

if (isset($_GET['ok']))  $success = 'Changes saved.';
if (isset($_GET['err'])) $error   = htmlspecialchars($_GET['err']);

$unread  = get_unread_count($uid);
$initial = strtoupper(substr($user['full_name'], 0, 1));
$photo   = !empty($user['profile_photo']) ? '/disbasura/uploads/' . $user['profile_photo'] : null;
$dark    = $prefs['dark_mode'] ? 'dark' : '';
?>
<!DOCTYPE html>
<html lang="en" class="<?= $dark ?>">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Profile — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
  <style>
    html.dark{--bg-page:#0d1f14;--bg-card:#152b1e;--text-dark:#e2f0e8;--text-mid:#8baa96;--border:#243d2c;--border-light:#1e3328}
    html.dark body{background:var(--bg-page);color:var(--text-dark)}
    html.dark .res-sidebar{background:#0f2318!important;border-color:var(--border)!important}
    html.dark .panel{background:var(--bg-card)!important;border-color:var(--border)!important}
    html.dark input{background:#152b1e!important;border-color:var(--border)!important;color:var(--text-dark)!important}
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#f0f6f3;min-height:100vh;font-family:'Plus Jakarta Sans','Inter',sans-serif}
    .res-layout{display:flex;min-height:100vh}
    .res-sidebar{width:240px;flex-shrink:0;background:linear-gradient(180deg,#0f2d1e,#0d2b1e);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .res-main{flex:1;padding:1.75rem 2rem;overflow-y:auto;min-width:0}
    .sidebar-brand{padding:1.25rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:.65rem}
    .sidebar-brand h2{font-size:1rem;font-weight:800;color:#fff}
    .sidebar-brand p{font-size:.7rem;color:#7aab8a;margin-top:.1rem}
    .sidebar-user{padding:.85rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:.65rem}
    .sidebar-user strong{font-size:.82rem;font-weight:700;color:#fff;display:block}
    .sidebar-user span{font-size:.72rem;color:#7aab8a}
    .sidebar-nav{flex:1;padding:.75rem 0}
    .sidebar-nav a{display:flex;align-items:center;gap:.65rem;padding:.66rem 1.1rem;font-size:.84rem;font-weight:600;color:#7ab594;border-radius:9px;margin:.1rem .5rem;text-decoration:none;transition:all .18s}
    .sidebar-nav a:hover{background:rgba(255,255,255,.08);color:#fff;text-decoration:none}
    .sidebar-nav a.active{background:rgba(45,134,83,.3);color:#fff;border-left:3px solid #4caf80}
    .sidebar-nav a .nav-icon{font-size:1rem;width:22px;text-align:center;flex-shrink:0}
    .sidebar-bottom{padding:.75rem;border-top:1px solid rgba(255,255,255,.07)}
    .signout-link{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.55rem;border:1.5px solid rgba(255,255,255,.12);border-radius:8px;background:transparent;color:rgba(255,255,255,.5);font-size:.8rem;font-weight:600;text-decoration:none;transition:all .18s}
    .signout-link:hover{background:rgba(224,82,82,.18);border-color:rgba(224,82,82,.4);color:#f5a0a0;text-decoration:none}
    .sidebar-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
    .panel{background:#fff;border-radius:14px;border:1px solid #e4ede8;margin-bottom:1.25rem;padding:1.5rem}
    .panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid #e4ede8}
    html.dark .panel-header{border-bottom-color:var(--border)}
    .panel-header h3{font-size:.95rem;font-weight:700;color:#1a3a2a}
    html.dark .panel-header h3{color:var(--text-dark)}
    .field{margin-bottom:1rem}
    .field label{display:block;font-size:.82rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem}
    .field input{width:100%;padding:.72rem 1rem;border:1.5px solid #d4e6db;border-radius:9px;font-family:inherit;font-size:.9rem;color:#1a3a2a;background:#f8fcfa;outline:none;transition:all .18s}
    .field input:focus{border-color:#1e6b3c;box-shadow:0 0 0 3px rgba(30,107,60,.1);background:#fff}
    .btn-save{padding:.65rem 1.5rem;background:#1e6b3c;color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .18s}
    .btn-save:hover{background:#2d8653;transform:translateY(-1px)}
    @media(max-width:680px){.res-sidebar{display:none}.res-main{padding:1.25rem}}
  </style>
</head>
<body>
<div class="res-layout">
  <aside class="res-sidebar">
    <div class="sidebar-brand">
      <svg viewBox="0 0 72 72" fill="none" width="34" height="34">
        <defs>
          <linearGradient id="rpRingG" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#5dd96b"/>
            <stop offset="50%"  stop-color="#22a94a"/>
            <stop offset="100%" stop-color="#0d6e30"/>
          </linearGradient>
          <linearGradient id="rpLeafG" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#7de87a"/>
            <stop offset="100%" stop-color="#1a8a38"/>
          </linearGradient>
        </defs>
        <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#rpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
        <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#rpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
        <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#rpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#rpRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#rpLeafG)"/>
        <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#rpLeafG)"/>
        <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
      </svg>
      <div><h2>DisBasura</h2><p>Resident Portal</p></div>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar" style="<?= $photo ? '' : 'background:linear-gradient(135deg,#1e5c38,#2d8653)' ?>">
        <?php if ($photo): ?><img src="<?= $photo ?>" style="width:100%;height:100%;object-fit:cover"/><?php else: ?><?= $initial ?><?php endif; ?>
      </div>
      <div><strong><?= e($_SESSION['full_name']) ?></strong><span><?= e($user['sitio'] ?? '') ?></span></div>
    </div>
    <nav class="sidebar-nav">
      <a href="/disbasura/resident/dashboard.php"><span class="nav-icon">🏠</span> <?= $lang['dashboard'] ?></a>
      <a href="/disbasura/resident/submit-request.php"><span class="nav-icon">🚛</span> <?= $lang['submit_request'] ?></a>
      <a href="/disbasura/resident/notifications.php"><span class="nav-icon">🔔</span> <?= $lang['notifications'] ?></a>
      <a href="/disbasura/resident/profile.php" class="active"><span class="nav-icon">👤</span> My Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="/disbasura/logout.php?role=resident" class="signout-link">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <?= $lang['sign_out'] ?>
      </a>
    </div>
  </aside>

  <main class="res-main">
    <div style="margin-bottom:1.5rem">
      <h1 style="font-size:1.35rem;font-weight:800;color:#1a3a2a;margin-bottom:.2rem">My Profile</h1>
      <p style="font-size:.83rem;color:#7aab8a">Update your photo, name, and password</p>
    </div>

    <?php if ($error):   ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;max-width:800px">

      <!-- Photo card -->
      <div class="panel" style="grid-column:1/-1;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
        <div style="position:relative;flex-shrink:0">
          <?php if ($photo): ?>
            <img src="<?= $photo ?>" alt="Photo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #1e6b3c;box-shadow:0 4px 14px rgba(30,107,60,.25)"/>
          <?php else: ?>
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1e5c38,#2d8653);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;border:3px solid rgba(30,107,60,.25)">
              <?= $initial ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:180px">
          <div style="font-size:1.1rem;font-weight:800;color:#1a3a2a;margin-bottom:.15rem"><?= e($user['full_name']) ?></div>
          <div style="font-size:.8rem;color:#7aab8a">@<?= e($user['username']) ?></div>
          <div style="margin-top:.85rem;display:flex;gap:.5rem;flex-wrap:wrap">
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="save_photo" value="1">
              <label style="display:inline-flex;align-items:center;gap:.35rem;padding:.48rem .95rem;background:#1e6b3c;color:#fff;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer">
                📷 Change Photo
                <input type="file" name="profile_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
              </label>
            </form>
            <?php if ($photo): ?>
            <form method="POST" onsubmit="return confirm('Remove photo?')">
              <input type="hidden" name="remove_photo" value="1">
              <button type="submit" style="padding:.48rem .95rem;border:1.5px solid #e74c3c;background:transparent;color:#e74c3c;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer">Remove</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Info card -->
      <div class="panel">
        <div class="panel-header"><h3>Account Info</h3></div>
        <form method="POST">
          <input type="hidden" name="save_info" value="1">
          <div class="field"><label>Full Name</label><input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required/></div>
          <div class="field"><label>Username</label><input type="text" name="username" value="<?= e($user['username']) ?>" required autocomplete="off"/></div>
          <button type="submit" class="btn-save">Save Changes</button>
        </form>
      </div>

      <!-- Password card -->
      <div class="panel">
        <div class="panel-header"><h3>Change Password</h3></div>
        <form method="POST">
          <input type="hidden" name="save_password" value="1">
          <div class="field"><label>Current Password</label><input type="password" name="current_password" required/></div>
          <div class="field"><label>New Password</label><input type="password" name="new_password" required minlength="6"/></div>
          <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" required/></div>
          <button type="submit" class="btn-save">Change Password</button>
        </form>
      </div>

    </div>
  </main>
</div>
</body>
</html>
