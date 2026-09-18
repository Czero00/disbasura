<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$db = get_db(); $uid = $_SESSION['user_id'];
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$notifs->execute([$uid]); $notifs=$notifs->fetchAll();
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Notifications — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
</head>
<body style="background:var(--bg-page);padding:1.5rem">
<div style="max-width:700px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem">
    <a href="/disbasura/resident/dashboard.php" style="color:var(--text-mid);text-decoration:none">←</a>
    <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.3rem;font-weight:800">Notifications</h1>
  </div>
  <?php if($notifs): foreach($notifs as $n): ?>
  <div style="background:#fff;border-radius:12px;border:1px solid var(--border-light);padding:1rem 1.25rem;margin-bottom:.75rem;<?= !$n['is_read']?'border-left:3px solid var(--green-main)':'' ?>">
    <div style="font-size:.88rem"><?= htmlspecialchars($n['message']) ?></div>
    <div style="font-size:.75rem;color:var(--text-light);margin-top:.3rem"><?= fmt_date($n['created_at']) ?></div>
  </div>
  <?php endforeach; else: ?><div style="text-align:center;padding:3rem;color:var(--text-light)">No notifications yet.</div><?php endif; ?>
</div>
</body>
</html>
