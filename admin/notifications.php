<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
$unread_before = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$unread_before->execute([$_SESSION['admin_id']]);
$unread_before = (int)$unread_before->fetchColumn();
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$notifs->execute([$_SESSION['admin_id']]);
$notifs = $notifs->fetchAll();
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['admin_id']]);
render_admin_header('notifications',0,'Notifications — DisBasura Admin');
?>
<div class="page-header"><h1>Notifications</h1><p><?= $unread_before ?> unread notification<?= $unread_before!=1?'s':'' ?></p></div>
<?php if($notifs): foreach($notifs as $n): ?>
<div style="background:#fff;border-radius:12px;border:1px solid var(--border-light);padding:1rem 1.25rem;margin-bottom:.75rem;<?= !$n['is_read']?'border-left:3px solid var(--green-main)':'' ?>">
  <div style="font-size:.88rem"><?= htmlspecialchars($n['message']) ?></div>
  <div style="font-size:.75rem;color:var(--text-light);margin-top:.3rem"><?= fmt_date($n['created_at']) ?></div>
</div>
<?php endforeach; else: ?>
<div class="empty-state" style="padding:4rem"><p>No notifications yet.</p></div>
<?php endif; ?>
<?php render_admin_footer(); ?>
