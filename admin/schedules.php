<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
$sitio_filter = $_GET['sitio'] ?? '';
$sitios_list  = get_sitios();
$collectors   = $db->query("SELECT * FROM collectors ORDER BY full_name")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_schedule'])) {
        $db->prepare("INSERT INTO schedules (sitio,scheduled_at,waste_type,collector_id) VALUES (?,?,?,?)")
           ->execute([$_POST['sitio'],$_POST['scheduled_at'],$_POST['waste_type'],$_POST['collector_id']?:null]);
        try { $d=new DateTime(str_replace('T',' ',$_POST['scheduled_at'])); $f=$d->format('M d, Y \a\t h:i A'); } catch(Exception $e){ $f=$_POST['scheduled_at']; }
        notify_all_sitio($db,$_POST['sitio'],"📅 New collection schedule for {$_POST['sitio']} on $f — {$_POST['waste_type']}. Please prepare your trash!");
        $db->query("COMMIT");
    } elseif (isset($_POST['edit_schedule'])) {
        $db->prepare("UPDATE schedules SET sitio=?,scheduled_at=?,waste_type=?,collector_id=? WHERE id=?")
           ->execute([$_POST['sitio'],$_POST['scheduled_at'],$_POST['waste_type'],$_POST['collector_id']?:null,$_POST['id']]);
    } elseif (isset($_POST['delete_schedule'])) {
        $db->prepare("DELETE FROM schedules WHERE id=?")->execute([$_POST['id']]);
    }
    header('Location: /disbasura/admin/schedules.php'.($sitio_filter?"?sitio=".urlencode($sitio_filter):'')); exit;
}
$q = "SELECT s.*,c.full_name as collector_name FROM schedules s LEFT JOIN collectors c ON s.collector_id=c.id";
if ($sitio_filter) { $stmt=$db->prepare($q." WHERE s.sitio=? ORDER BY s.scheduled_at DESC"); $stmt->execute([$sitio_filter]); }
else { $stmt=$db->query($q." ORDER BY s.scheduled_at DESC"); }
$schedules = $stmt->fetchAll();
$unread = get_unread_count($_SESSION['admin_id']);
render_admin_header('schedules',$unread,'Schedules — DisBasura Admin');
?>
<div class="page-header-row">
  <div class="page-header"><h1>Collection Schedules</h1><p>Manage all garbage collection schedules</p></div>
  <button class="btn-add" onclick="document.getElementById('addSchedModal').style.display='flex'">+ Add Schedule</button>
</div>
<!-- Sitio filter -->
<div class="filter-tabs">
  <a href="/disbasura/admin/schedules.php" class="tab <?= !$sitio_filter?'active':'' ?>">All</a>
  <?php foreach($sitios_list as $s): ?>
  <a href="?sitio=<?= urlencode($s) ?>" class="tab <?= $sitio_filter===$s?'active':'' ?>"><?= htmlspecialchars($s) ?></a>
  <?php endforeach; ?>
</div>
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--border-light)">
  <thead><tr style="background:var(--bg-page);border-bottom:2px solid var(--border-light)">
    <th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600">Sitio</th>
    <th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600">Date & Time</th>
    <th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600">Waste Type</th>
    <th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600">Collector</th>
    <th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600">Status</th>
    <th style="padding:1rem 1.25rem;text-align:left;font-size:.82rem;color:var(--text-mid);font-weight:600">Actions</th>
  </tr></thead>
  <tbody>
  <?php foreach($schedules as $s): ?>
  <tr style="border-bottom:1px solid var(--border-light)">
    <td style="padding:1rem 1.25rem;font-weight:600"><?= htmlspecialchars($s['sitio']) ?></td>
    <td style="padding:1rem 1.25rem;color:var(--text-mid)"><?= fmt_date($s['scheduled_at']) ?></td>
    <td style="padding:1rem 1.25rem"><?= htmlspecialchars($s['waste_type']) ?></td>
    <td style="padding:1rem 1.25rem;color:var(--text-mid)"><?= htmlspecialchars($s['collector_name'] ?? '—') ?></td>
    <td style="padding:1rem 1.25rem"><span class="badge <?= $s['status'] ?>"><?= $s['status'] ?></span></td>
    <td style="padding:1rem 1.25rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <button class="btn-approve" style="font-size:.78rem;padding:.35rem .8rem" onclick="openEditSched(<?= htmljsonrow($s) ?>)">Edit</button>
      <form method="POST" onsubmit="return confirm('Delete this schedule?')"><input type="hidden" name="delete_schedule" value="1"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" class="btn-reject" style="font-size:.78rem;padding:.35rem .8rem">Delete</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<!-- Add Modal -->
<div id="addSchedModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:480px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">Add Schedule</h3>
    <form method="POST"><input type="hidden" name="add_schedule" value="1">
      <div class="field"><label>Sitio</label><select name="sitio" required><option value="">Select sitio</option><?php foreach($sitios_list as $st): ?><option><?= htmlspecialchars($st) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Date & Time</label><input type="datetime-local" name="scheduled_at" required/></div>
      <div class="field"><label>Waste Type</label><select name="waste_type"><option>Biodegradable</option><option>Non-Biodegradable</option><option>Recyclable</option><option>Mixed</option></select></div>
      <div class="field"><label>Collector</label><select name="collector_id"><option value="">None</option><?php foreach($collectors as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('addSchedModal').style.display='none'">Cancel</button><button type="submit" class="btn-save">Add Schedule</button></div>
    </form>
  </div>
</div>
<!-- Edit Modal -->
<div id="editSchedModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:480px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">Edit Schedule</h3>
    <form method="POST" id="editSchedForm"><input type="hidden" name="edit_schedule" value="1"><input type="hidden" name="id" id="es_id">
      <div class="field"><label>Sitio</label><select name="sitio" id="es_sitio" required><?php foreach($sitios_list as $st): ?><option><?= htmlspecialchars($st) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Date & Time</label><input type="datetime-local" name="scheduled_at" id="es_date" required/></div>
      <div class="field"><label>Waste Type</label><select name="waste_type" id="es_wtype"><option>Biodegradable</option><option>Non-Biodegradable</option><option>Recyclable</option><option>Mixed</option></select></div>
      <div class="field"><label>Collector</label><select name="collector_id" id="es_col"><option value="">None</option><?php foreach($collectors as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('editSchedModal').style.display='none'">Cancel</button><button type="submit" class="btn-save">Save</button></div>
    </form>
  </div>
</div>
<script>
function htmljsonrow(s){ return ''; }
function openEditSched(id,sitio,scheduled_at,waste_type,collector_id){
  document.getElementById('es_id').value=id;
  document.getElementById('es_sitio').value=sitio;
  document.getElementById('es_date').value=scheduled_at.replace(' ','T').substring(0,16);
  document.getElementById('es_wtype').value=waste_type;
  document.getElementById('es_col').value=collector_id||'';
  document.getElementById('editSchedModal').style.display='flex';
}
</script>
<?php
// redefine helper to inline JSON for each row
function htmljsonrow($s){
    return htmlspecialchars(
        $s['id'].",'".addslashes($s['sitio'])."','".addslashes($s['scheduled_at'])."','".addslashes($s['waste_type'])."','".addslashes($s['collector_id'] ?? '')."'",
        ENT_QUOTES
    );
}
render_admin_footer(); ?>
