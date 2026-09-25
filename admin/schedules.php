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
        $sitio = trim($_POST['sitio'] ?? '');
        $wasteType = trim($_POST['waste_type'] ?? 'Mixed');
        $collectorId = (int)($_POST['collector_id'] ?? 0) ?: null;
        $scheduleMode = $_POST['schedule_mode'] ?? 'once';
        $allowedDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

        if (!in_array($sitio, $sitios_list, true)) {
            $_SESSION['flash_err'] = 'Select a valid sitio.';
        } elseif ($scheduleMode === 'monthly') {
            $monthInput = trim($_POST['month'] ?? '');
            $month = DateTime::createFromFormat('!Y-m', $monthInput);
            $daysInput = is_array($_POST['weekdays'] ?? null) ? $_POST['weekdays'] : [];
            $weekdays = array_values(array_unique(array_intersect($daysInput, $allowedDays)));
            $timeInput = trim($_POST['collection_time'] ?? '');
            $time = DateTime::createFromFormat('!H:i', $timeInput);
            if (!$month || $month->format('Y-m') !== $monthInput || $monthInput < date('Y-m') || !$weekdays || !$time || $time->format('H:i') !== $timeInput) {
                $_SESSION['flash_err'] = 'Choose a valid month, collection time, and at least one weekday.';
            } else {
                try {
                    $db->beginTransaction();
                    $insert = $db->prepare('INSERT INTO schedules (sitio,scheduled_at,waste_type,collector_id) VALUES (?,?,?,?)');
                    $count = 0;
                    $day = new DateTime($month->format('Y-m-01'));
                    $monthKey = $month->format('Y-m');
                    while ($day->format('Y-m') === $monthKey) {
                        if ($day->format('Y-m-d') >= date('Y-m-d') && in_array($day->format('l'), $weekdays, true)) {
                            $scheduledAt = $day->format('Y-m-d') . ' ' . $timeInput . ':00';
                            $exists = $db->prepare('SELECT id FROM schedules WHERE sitio = ? AND DATE(scheduled_at) = ? LIMIT 1');
                            $exists->execute([$sitio, $day->format('Y-m-d')]);
                            if ($exists->fetchColumn()) {
                                $day->modify('+1 day');
                                continue;
                            }
                            $insert->execute([$sitio, $scheduledAt, $wasteType, $collectorId]);
                            $count++;
                        }
                        $day->modify('+1 day');
                    }
                    if ($count === 0) {
                        $db->rollBack();
                        $_SESSION['flash_err'] = 'No new collection dates were created. The selected days may already be scheduled or have passed.';
                    } else {
                        $db->commit();
                        $dayNames = implode(', ', $weekdays);
                        notify_all_sitio($db, $sitio, "Collection schedule for {$sitio}: {$dayNames} during " . $month->format('F Y') . '. Please prepare your trash on collection days.');
                        $_SESSION['flash'] = "Created {$count} collection dates for {$sitio} in " . $month->format('F Y') . '.';
                    }
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $_SESSION['flash_err'] = 'Could not create the monthly collection schedule.';
                }
            }
        } else {
            $dateInput = trim($_POST['scheduled_at'] ?? '');
            $date = DateTime::createFromFormat('Y-m-d\\TH:i', $dateInput);
            if (!$date || $date->format('Y-m-d\\TH:i') !== $dateInput) {
                $_SESSION['flash_err'] = 'Choose a valid schedule date and time.';
            } else {
                $db->prepare('INSERT INTO schedules (sitio,scheduled_at,waste_type,collector_id) VALUES (?,?,?,?)')
                   ->execute([$sitio, $date->format('Y-m-d H:i:s'), $wasteType, $collectorId]);
                notify_all_sitio($db, $sitio, 'A new collection is scheduled for ' . $date->format('M d, Y \\a\\t h:i A') . " ({$wasteType}). Please prepare your trash!");
                $_SESSION['flash'] = 'Collection schedule added.';
            }
        }
    } elseif (isset($_POST['edit_schedule'])) {
        $db->prepare("UPDATE schedules SET sitio=?,scheduled_at=?,waste_type=?,collector_id=? WHERE id=?")
           ->execute([$_POST['sitio'],$_POST['scheduled_at'],$_POST['waste_type'],$_POST['collector_id']?:null,$_POST['id']]);
    } elseif (isset($_POST['delete_schedule'])) {
        $db->prepare("DELETE FROM schedules WHERE id=?")->execute([$_POST['id']]);
    }
    header('Location: /disbasura/admin/schedules.php'.($sitio_filter?"?sitio=".urlencode($sitio_filter):'')); exit;
}
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$flash_err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$q = "SELECT s.*,c.full_name as collector_name FROM schedules s LEFT JOIN collectors c ON s.collector_id=c.id";
if ($sitio_filter) { $stmt=$db->prepare($q." WHERE s.sitio=? ORDER BY s.scheduled_at DESC"); $stmt->execute([$sitio_filter]); }
else { $stmt=$db->query($q." ORDER BY s.scheduled_at DESC"); }
$schedules = $stmt->fetchAll();
$unread = get_unread_admin_count($_SESSION['admin_id']);
render_admin_header('schedules',$unread,'Schedules — DisBasura Admin');
?>
<div class="page-header-row">
  <div class="page-header"><h1>Collection Schedules</h1><p>Manage all garbage collection schedules</p></div>
  <button class="btn-add" onclick="document.getElementById('addSchedModal').style.display='flex'">+ Add Schedule</button>
</div>
<?php if($flash): ?><div class="alert-success" style="margin-bottom:1rem"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if($flash_err): ?><div class="alert-error" style="margin-bottom:1rem"><?= htmlspecialchars($flash_err) ?></div><?php endif; ?>
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
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:480px;max-height:calc(100vh - 3rem);overflow-y:auto;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">Add Schedule</h3>
    <form method="POST" id="addScheduleForm"><input type="hidden" name="add_schedule" value="1">
      <div class="field"><label>Sitio</label><select name="sitio" required><option value="">Select sitio</option><?php foreach($sitios_list as $st): ?><option><?= htmlspecialchars($st) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Schedule Type</label><select name="schedule_mode" id="scheduleMode"><option value="once">One-time schedule</option><option value="monthly">Repeat on selected weekdays for a month</option></select></div>
      <div id="onceScheduleFields"><div class="field"><label>Date & Time</label><input type="datetime-local" name="scheduled_at" data-schedule-field="once" required/></div></div>
      <div id="monthlyScheduleFields" hidden>
        <div class="field"><label>Month</label><input type="month" name="month" value="<?= date('Y-m') ?>" min="<?= date('Y-m') ?>" data-schedule-field="monthly" disabled/></div>
        <div class="field"><label>Collection Time</label><input type="time" name="collection_time" value="07:00" data-schedule-field="monthly" disabled/></div>
        <div class="field"><label>Collection Days</label>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem .8rem">
            <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $weekday): ?>
            <label style="display:flex;align-items:center;gap:.4rem;font-size:.84rem"><input type="checkbox" name="weekdays[]" value="<?= $weekday ?>" data-schedule-field="monthly" disabled/> <?= $weekday ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <p style="font-size:.78rem;color:var(--text-light);margin:-.2rem 0 1rem">A schedule will be created for each selected weekday in the chosen month.</p>
      </div>
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
const scheduleMode = document.getElementById('scheduleMode');
const onceFields = document.getElementById('onceScheduleFields');
const monthlyFields = document.getElementById('monthlyScheduleFields');
function updateScheduleMode() {
  const monthly = scheduleMode.value === 'monthly';
  onceFields.hidden = monthly;
  monthlyFields.hidden = !monthly;
  document.querySelectorAll('[data-schedule-field="once"]').forEach(field => { field.disabled = monthly; });
  document.querySelectorAll('[data-schedule-field="monthly"]').forEach(field => { field.disabled = !monthly; });
}
scheduleMode.addEventListener('change', updateScheduleMode);
updateScheduleMode();
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
