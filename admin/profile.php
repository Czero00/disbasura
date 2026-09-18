<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';

$db  = get_db();
$uid = $_SESSION['admin_id'];

// Ensure profile_photo column exists
try { $db->exec("ALTER TABLE administrators ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}

$user = $db->query("SELECT * FROM administrators WHERE id=$uid")->fetch();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Change name / username / email ──────────────────────────
    if (isset($_POST['save_info'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if (!$full_name || !$username || !$email) {
            $error = 'Name, username and email are required.';
        } else {
            // Check username / email uniqueness (exclude self)
            $dup = $db->prepare("SELECT id FROM administrators WHERE (username=? OR email=?) AND id!=?");
            $dup->execute([$username, $email, $uid]);
            if ($dup->fetch()) {
                $error = 'That username or email is already taken.';
            } else {
                $db->prepare("UPDATE administrators SET full_name=?, username=?, email=? WHERE id=?")
                   ->execute([$full_name, $username, $email, $uid]);
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['admin_user'] = $username;
                $success = 'Profile updated successfully.';
                log_activity($uid, 'Updated Profile', 'Admin updated name/username/email');
                $user = $db->query("SELECT * FROM administrators WHERE id=$uid")->fetch();
            }
        }
    }

    // ── Change password ─────────────────────────────────────────
    if (isset($_POST['save_password'])) {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $conf) {
            $error = 'New passwords do not match.';
        } else {
            $db->prepare("UPDATE administrators SET password=? WHERE id=?")
               ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            $success = 'Password changed successfully.';
            log_activity($uid, 'Changed Password', 'Admin changed their password');
        }
    }

    // ── Upload profile photo ─────────────────────────────────────
    if (isset($_POST['save_photo']) && isset($_FILES['profile_photo'])) {
        $fname = save_upload($_FILES['profile_photo'], "avatar_admin_{$uid}");
        if ($fname) {
            // Delete old photo file if exists
            if (!empty($user['profile_photo'])) {
                @unlink(__DIR__ . '/../uploads/' . $user['profile_photo']);
            }
            $db->prepare("UPDATE administrators SET profile_photo=? WHERE id=?")->execute([$fname, $uid]);
            $success = 'Profile photo updated.';
            $user = $db->query("SELECT * FROM administrators WHERE id=$uid")->fetch();
        } else {
            $error = 'Invalid image file. Use JPG, PNG, or WEBP.';
        }
    }

    // ── Remove photo ─────────────────────────────────────────────
    if (isset($_POST['remove_photo'])) {
        if (!empty($user['profile_photo'])) {
            @unlink(__DIR__ . '/../uploads/' . $user['profile_photo']);
        }
        $db->prepare("UPDATE administrators SET profile_photo=NULL WHERE id=?")->execute([$uid]);
        $success = 'Profile photo removed.';
        $user = $db->query("SELECT * FROM administrators WHERE id=$uid")->fetch();
    }

    if (!headers_sent()) {
        $qs = $success ? '?ok=1' : ($error ? '?err='.urlencode($error) : '');
        header("Location: /disbasura/admin/profile.php$qs"); exit;
    }
}

if (isset($_GET['ok']))  $success = 'Changes saved.';
if (isset($_GET['err'])) $error   = htmlspecialchars($_GET['err']);

$unread = get_unread_count($uid);
$initial = strtoupper(substr($user['full_name'], 0, 1));
$photo   = !empty($user['profile_photo']) ? '/disbasura/uploads/' . $user['profile_photo'] : null;

render_admin_header('', $unread, 'My Profile — DisBasura Admin');
?>

<div class="page-header"><h1>My Profile</h1><p>Manage your account information</p></div>

<?php if ($error):   ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:860px">

  <!-- ── Photo card ── -->
  <div class="panel" style="grid-column:1/-1;display:flex;align-items:center;gap:1.75rem;flex-wrap:wrap">
    <!-- Avatar -->
    <div style="position:relative;flex-shrink:0">
      <?php if ($photo): ?>
        <img src="<?= $photo ?>" alt="Photo" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--green-main);box-shadow:0 4px 14px rgba(45,134,83,.25)"/>
      <?php else: ?>
        <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--green-mid),var(--green-main));display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:2.2rem;font-weight:800;color:#fff;border:3px solid rgba(45,134,83,.3)">
          <?= $initial ?>
        </div>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:200px">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.2rem;font-weight:800;color:var(--text-dark);margin-bottom:.2rem"><?= e($user['full_name']) ?></div>
      <div style="font-size:.82rem;color:var(--text-mid)">@<?= e($user['username']) ?> · <?= e($user['email']) ?></div>
      <div style="margin-top:1rem;display:flex;gap:.65rem;flex-wrap:wrap">
        <form method="POST" enctype="multipart/form-data" style="display:inline-flex;align-items:center;gap:.5rem">
          <input type="hidden" name="save_photo" value="1">
          <label style="display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;background:var(--green-main);color:#fff;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload Photo
            <input type="file" name="profile_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
          </label>
        </form>
        <?php if ($photo): ?>
        <form method="POST" onsubmit="return confirm('Remove photo?')">
          <input type="hidden" name="remove_photo" value="1">
          <button type="submit" style="padding:.5rem 1rem;border:1.5px solid var(--red);background:transparent;color:var(--red);border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer">Remove</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Info card ── -->
  <div class="panel">
    <div class="panel-header"><h3>Account Information</h3></div>
    <form method="POST" style="margin-top:1rem">
      <input type="hidden" name="save_info" value="1">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required/>
      </div>
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" value="<?= e($user['username']) ?>" required autocomplete="off"/>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($user['email']) ?>" required/>
      </div>
      <button type="submit" class="btn-save" style="width:auto;padding:.65rem 1.5rem">Save Changes</button>
    </form>
  </div>

  <!-- ── Password card ── -->
  <div class="panel">
    <div class="panel-header"><h3>Change Password</h3></div>
    <form method="POST" style="margin-top:1rem">
      <input type="hidden" name="save_password" value="1">
      <div class="field">
        <label>Current Password</label>
        <input type="password" name="current_password" required autocomplete="current-password"/>
      </div>
      <div class="field">
        <label>New Password</label>
        <input type="password" name="new_password" required minlength="6" autocomplete="new-password"/>
      </div>
      <div class="field">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required autocomplete="new-password"/>
      </div>
      <button type="submit" class="btn-save" style="width:auto;padding:.65rem 1.5rem">Change Password</button>
    </form>
  </div>

</div>

<?php render_admin_footer(); ?>
