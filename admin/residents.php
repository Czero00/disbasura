<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['uid']??0);
    if (isset($_POST['promote'])) {
        $user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
        $db->prepare("UPDATE users SET role='resident' WHERE sitio=? AND role='leader'")->execute([$user['sitio']]);
        $db->prepare("UPDATE users SET role='leader' WHERE id=?")->execute([$uid]);
        notify_user($db,$uid,"🎖️ You have been appointed as Sitio Leader for {$user['sitio']}!");
    } elseif (isset($_POST['demote'])) {
        $db->prepare("UPDATE users SET role='resident' WHERE id=?")->execute([$uid]);
    }
    header('Location: /disbasura/admin/residents.php'); exit;
}
$residents = $db->query("SELECT * FROM users WHERE role IN ('resident','leader') ORDER BY sitio,full_name")->fetchAll();
$unread = get_unread_count($_SESSION['admin_id']);
render_admin_header('residents',$unread,'Residents — DisBasura Admin');
?>
<div class="page-header"><h1>Manage Residents</h1><p>View all residents and assign Sitio Leaders</p></div>
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--border-light)">
  <thead><tr style="background:var(--bg-page);border-bottom:2px solid var(--border-light)">
    <?php foreach(['Name','Username','Email','Sitio','Role','Action'] as $h): ?><th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600"><?= $h ?></th><?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach($residents as $r): ?>
  <tr style="border-bottom:1px solid var(--border-light)">
    <td style="padding:1rem 1.25rem;font-weight:600"><?= htmlspecialchars($r['full_name']) ?></td>
    <td style="padding:1rem 1.25rem;color:var(--text-mid)"><?= htmlspecialchars($r['username']) ?></td>
    <td style="padding:1rem 1.25rem;color:var(--text-mid)"><?= htmlspecialchars($r['email']) ?></td>
    <td style="padding:1rem 1.25rem"><?= htmlspecialchars($r['sitio']??'—') ?></td>
    <td style="padding:1rem 1.25rem"><span class="badge <?= $r['role']==='leader'?'approved':'pending' ?>"><?= $r['role']==='leader'?'⭐ Leader':'Resident' ?></span></td>
    <td style="padding:1rem 1.25rem">
      <?php if($r['role']==='leader'): ?>
      <form method="POST"><input type="hidden" name="uid" value="<?= $r['id'] ?>"><button type="submit" name="demote" class="btn-reject" style="font-size:.78rem;padding:.35rem .8rem">Remove Leader</button></form>
      <?php else: ?>
      <form method="POST" onsubmit="return confirm('Assign <?= htmlspecialchars($r['full_name'],ENT_QUOTES) ?> as Sitio Leader for <?= htmlspecialchars($r['sitio']??'',ENT_QUOTES) ?>?')"><input type="hidden" name="uid" value="<?= $r['id'] ?>"><button type="submit" name="promote" class="btn-approve" style="font-size:.78rem;padding:.35rem .8rem">⭐ Make Leader</button></form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php render_admin_footer(); ?>
