<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] !== 'leader') { header('Location: /disbasura/resident/dashboard.php'); exit; }

$db  = get_db();
$uid = $_SESSION['user_id'];

// Ensure profile_photo column exists (same column resident/collector/admin already use)
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}

$user  = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
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
                $success = 'Profile updated.';
                log_activity($uid, 'Updated Profile', 'Sitio leader updated name/username', 'leader');
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
            log_activity($uid, 'Changed Password', 'Sitio leader changed their password', 'leader');
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

    header('Location: /disbasura/leader/profile.php' . ($error ? '?err='.urlencode($error) : ($success ? '?ok=1' : ''))); exit;
}

if (isset($_GET['ok']))  $success = 'Changes saved.';
if (isset($_GET['err'])) $error   = htmlspecialchars($_GET['err']);

$sitio     = $_SESSION['sitio'] ?? '';
$full_name = htmlspecialchars($_SESSION['full_name']);
$initial   = strtoupper(substr($_SESSION['full_name'], 0, 1));
$photo     = !empty($user['profile_photo']) ? '/disbasura/uploads/' . $user['profile_photo'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Profile — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
<style>
  .field{margin-bottom:1rem}
  .field label{display:block;font-size:.82rem;font-weight:600;color:var(--text-mid,#5a7a68);margin-bottom:.35rem}
  .field input{width:100%;padding:.72rem 1rem;border:1.5px solid #d4e6db;border-radius:9px;font-family:inherit;font-size:.9rem;color:#1a3a2a;background:#f8fcfa;outline:none}
  .field input:focus{border-color:#1e6b3c;box-shadow:0 0 0 3px rgba(30,107,60,.1);background:#fff}
  .btn-save{padding:.65rem 1.5rem;background:#1e6b3c;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer}
  .btn-save:hover{background:#2d8653}
</style>
</head>
<body>
<div class="app active" id="appRoot">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <svg viewBox="0 0 72 72" fill="none" width="34" height="34" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="ldrRingG2" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#5dd96b"/><stop offset="50%" stop-color="#22a94a"/><stop offset="100%" stop-color="#0d6e30"/>
          </linearGradient>
          <linearGradient id="ldrLeafG2" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#7de87a"/><stop offset="100%" stop-color="#1a8a38"/>
          </linearGradient>
        </defs>
        <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#ldrRingG2)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
        <path d="M36 64 A28 28 0 0 1 8 36" stroke="url(#ldrRingG2)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="8,44 4,34 14,36" fill="#22a94a"/>
        <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#ldrRingG2)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M8 36 A28 28 0 0 1 36 8" stroke="url(#ldrRingG2)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#ldrLeafG2)"/>
        <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#ldrLeafG2)"/>
        <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
      </svg>
      <div class="sidebar-brand-text"><h2>DisBasura</h2><span>Sitio Leader</span></div>
    </div>
    <nav class="sidebar-nav">
      <a class="nav-item" href="/disbasura/leader/dashboard.php" data-tip="Dashboard"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span class="nav-label">Dashboard</span></a>
      <a class="nav-item" href="/disbasura/leader/requests.php" data-tip="Sitio Requests"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span class="nav-label">Sitio Requests</span></a>
      <a class="nav-item" href="/disbasura/leader/submit-request.php" data-tip="Submit for Resident"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/></svg><span class="nav-label">Submit for Resident</span></a>
      <a class="nav-item" href="/disbasura/leader/notifications.php" data-tip="Notifications"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><span class="nav-label">Notifications</span></a>
      <a class="nav-item active" href="/disbasura/leader/profile.php" data-tip="My Profile"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="nav-label">My Profile</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="avatar" style="<?= $photo ? '' : 'background:var(--orange)' ?>">
          <?php if ($photo): ?><img src="<?= $photo ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover"/><?php else: ?><?= $initial ?><?php endif; ?>
        </div>
        <div class="sidebar-user-info"><strong><?= $full_name ?></strong><span><?= htmlspecialchars($sitio) ?></span></div>
      </div>
      <a href="/disbasura/logout.php" class="signout-btn"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="nav-label">Sign Out</span></a>
    </div>
  </aside>
  <main class="main">
    <div class="topbar">
      <span style="background:#fff3e0;color:var(--orange);border:1px solid #ffe0b2;border-radius:20px;padding:.25rem .85rem;font-size:.78rem;font-weight:600">⭐ Sitio Leader — <?= htmlspecialchars($sitio) ?></span>
    </div>
    <div class="page-content active">
      <div class="page-header"><h1>My Profile</h1><p>Update your photo, name, and password</p></div>

      <?php if ($error):   ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;max-width:800px">
        <div class="panel" style="grid-column:1/-1;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
          <?php if ($photo): ?>
            <img src="<?= $photo ?>" alt="Photo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #1e6b3c"/>
          <?php else: ?>
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1e5c38,#2d8653);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff"><?= $initial ?></div>
          <?php endif; ?>
          <div style="flex:1;min-width:180px">
            <div style="font-size:1.1rem;font-weight:800;color:#1a3a2a"><?= e($user['full_name']) ?></div>
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

        <div class="panel">
          <div class="panel-header"><h3>Account Info</h3></div>
          <form method="POST">
            <input type="hidden" name="save_info" value="1">
            <div class="field"><label>Full Name</label><input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required/></div>
            <div class="field"><label>Username</label><input type="text" name="username" value="<?= e($user['username']) ?>" required autocomplete="off"/></div>
            <button type="submit" class="btn-save">Save Changes</button>
          </form>
        </div>

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
    </div>
  </main>
</div>
</body>
</html>
