<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
// Fix collectors.status ENUM if 'sick' or 'unavailable' are missing
try {
    $db->exec("ALTER TABLE collectors MODIFY COLUMN status ENUM('available','sick','unavailable') NOT NULL DEFAULT 'available'");
} catch(Exception $e){}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_collector'])) {
        $pw = $_POST['password'] ? password_hash($_POST['password'],PASSWORD_BCRYPT) : null;
        $un = $_POST['username'] ?: null;
        $db->prepare("INSERT INTO collectors (full_name,sitio,phone,username,password) VALUES (?,?,?,?,?)")
           ->execute([$_POST['full_name'],$_POST['sitio'],$_POST['phone'],$un,$pw]);
    } elseif (isset($_POST['edit_collector'])) {
        $cid=(int)$_POST['id'];
        $db->prepare("UPDATE collectors SET full_name=?,sitio=?,phone=? WHERE id=?")
           ->execute([$_POST['full_name'],$_POST['sitio'],$_POST['phone'],$cid]);
        if($_POST['username']) $db->prepare("UPDATE collectors SET username=? WHERE id=?")->execute([$_POST['username'],$cid]);
        if($_POST['password']) $db->prepare("UPDATE collectors SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_BCRYPT),$cid]);
    } elseif (isset($_POST['delete_collector'])) {
        $db->prepare("DELETE FROM collectors WHERE id=?")->execute([$_POST['id']]);
    } elseif (isset($_POST['update_status'])) {
        $db->prepare("UPDATE collectors SET status=? WHERE id=?")->execute([$_POST['status'],$_POST['id']]);
    }
    header('Location: /disbasura/admin/collectors.php'); exit;
}
$sitios_list = get_sitios();
$collectors  = $db->query("SELECT c.*,COUNT(DISTINCT s.id) as schedule_count,COUNT(DISTINCT r.id) as request_count,SUM(CASE WHEN s.status='completed' THEN 1 ELSE 0 END) as completed_count FROM collectors c LEFT JOIN schedules s ON s.collector_id=c.id LEFT JOIN requests r ON r.collector_id=c.id GROUP BY c.id ORDER BY c.full_name")->fetchAll();
$unread = get_unread_count($_SESSION['admin_id']);
render_admin_header('collectors',$unread,'Collectors — DisBasura Admin');
?>
<div class="page-header-row">
  <div class="page-header"><h1>Garbage Collectors</h1><p>Manage collector accounts and availability</p></div>
  <button class="btn-add" onclick="document.getElementById('addColModal').style.display='flex'">+ Add Collector</button>
</div>
<?php if($collectors): ?>
<div class="collectors-grid">
  <?php foreach($collectors as $c): ?>
  <div class="collector-card <?= ($c['status']&&$c['status']!='available')?'card-unavailable':'' ?>">
    <div class="collector-avatar <?= ($c['status']&&$c['status']!='available')?'avatar-off':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    </div>
    <div class="collector-info">
      <div class="collector-name-row">
        <strong><?= htmlspecialchars($c['full_name']) ?></strong>
        <span class="status-badge status-<?= $c['status']??'available' ?>">
          <?= $c['status']==='sick'?'🤒 Sick':($c['status']==='unavailable'?'⛔ Off':'✅ Available') ?>
        </span>
      </div>
      <span>📍 <?= htmlspecialchars($c['sitio']) ?></span>
      <span>📞 <?= htmlspecialchars($c['phone']??'No phone') ?></span>
      <?php if($c['username']): ?><span class="collector-login-tag">🔑 Login: <code><?= htmlspecialchars($c['username']) ?></code></span><?php else: ?><span class="collector-login-tag no-login">No login account</span><?php endif; ?>
      <div class="collector-stats">
        <span class="c-stat"><?= $c['schedule_count'] ?> schedules</span>
        <span class="c-stat"><?= $c['request_count'] ?> requests</span>
        <span class="c-stat completed-tag"><?= (int)$c['completed_count'] ?> completed</span>
      </div>
      <div class="status-controls">
        <span class="status-label">Status:</span>
        <form method="POST" style="display:inline">
          <input type="hidden" name="update_status" value="1"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button type="submit" name="status" value="available" class="status-btn s-green <?= (!$c['status']||$c['status']==='available')?'s-active':'' ?>">Available</button>
          <button type="submit" name="status" value="sick" class="status-btn s-orange <?= $c['status']==='sick'?'s-active':'' ?>">Sick</button>
          <button type="submit" name="status" value="unavailable" class="status-btn s-red <?= $c['status']==='unavailable'?'s-active':'' ?>">Off</button>
        </form>
      </div>
      <div style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <button type="button" class="btn-save" style="font-size:.78rem;padding:.35rem .9rem"
          onclick="openEditCol(<?= $c['id'] ?>,'<?= addslashes(htmlspecialchars($c['full_name'])) ?>','<?= addslashes(htmlspecialchars($c['sitio'])) ?>','<?= addslashes($c['phone']??'') ?>','<?= addslashes($c['username']??'') ?>')">✏️ Edit</button>
        <form method="POST" onsubmit="return confirm('Remove this collector?')"><input type="hidden" name="delete_collector" value="1"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" class="btn-reject" style="font-size:.78rem;padding:.35rem .8rem">Remove</button></form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state" style="padding:4rem"><p>No collectors yet. Add one to get started.</p></div>
<?php endif; ?>

<!-- Add Modal -->
<div id="addColModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div class="modal" style="max-width:500px">
    <h3>Add Collector</h3>
    <form method="POST"><input type="hidden" name="add_collector" value="1">
      <div class="field"><label>Full Name</label><input type="text" name="full_name" required/></div>
      <div class="field"><label>Sitio</label><select name="sitio" required><option value="">Select sitio</option><?php foreach($sitios_list as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Phone</label><input type="tel" name="phone" placeholder="09XX XXX XXXX"/></div>
      <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0">
      <div class="field"><label>Username (optional)</label><input type="text" name="username" autocomplete="off"/></div>
      <div class="field"><label>Password (optional)</label><input type="password" name="password" autocomplete="new-password"/></div>
      <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('addColModal').style.display='none'">Cancel</button><button type="submit" class="btn-save">Add Collector</button></div>
    </form>
  </div>
</div>
<!-- Edit Modal -->
<div id="editColModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div class="modal" style="max-width:500px">
    <h3>Edit Collector</h3>
    <form method="POST" id="editColForm"><input type="hidden" name="edit_collector" value="1"><input type="hidden" name="id" id="ec_id">
      <div class="field"><label>Full Name</label><input type="text" name="full_name" id="ec_name" required/></div>
      <div class="field"><label>Sitio</label><select name="sitio" id="ec_sitio" required><?php foreach($sitios_list as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Phone</label><input type="tel" name="phone" id="ec_phone"/></div>
      <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0">
      <div class="field"><label>Username</label><input type="text" name="username" id="ec_username" autocomplete="off" placeholder="Leave blank to keep current"/></div>
      <div class="field"><label>New Password</label><input type="password" name="password" id="ec_pw" autocomplete="new-password" placeholder="Leave blank to keep current"/></div>
      <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('editColModal').style.display='none'">Cancel</button><button type="submit" class="btn-save">Save Changes</button></div>
    </form>
  </div>
</div>
<script>
function openEditCol(id,name,sitio,phone,username){
  document.getElementById('ec_id').value=id;
  document.getElementById('ec_name').value=name;
  document.getElementById('ec_sitio').value=sitio;
  document.getElementById('ec_phone').value=phone;
  document.getElementById('ec_username').value='';
  document.getElementById('ec_pw').value='';
  document.getElementById('editColModal').style.display='flex';
}
</script>
<?php render_admin_footer(); ?>
