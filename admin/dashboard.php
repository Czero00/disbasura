<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';

$db = get_db();
$stats = [
    'total_schedules'  => $db->query("SELECT COUNT(*) FROM schedules")->fetchColumn(),
    'pending_requests' => $db->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn(),
    'total_collectors' => $db->query("SELECT COUNT(*) FROM collectors")->fetchColumn(),
    'total_residents'  => $db->query("SELECT COUNT(*) FROM users WHERE role IN ('resident','leader')")->fetchColumn(),
];
$recent_requests = $db->query("SELECT r.*,u.full_name FROM requests r JOIN users u ON r.resident_id=u.id ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
$today_schedules = $db->query("SELECT s.*,c.full_name as collector_name FROM schedules s LEFT JOIN collectors c ON s.collector_id=c.id WHERE DATE(s.scheduled_at)=CURDATE()")->fetchAll();
$sitios_list = get_sitios();
$days_cycle  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$sitio_days  = [];
foreach ($sitios_list as $i => $s) { $sitio_days[$s] = $days_cycle[$i % count($days_cycle)]; }
$weekly_rows = $db->query("SELECT w.*, c.full_name as collector_name FROM weekly_schedule w LEFT JOIN collectors c ON w.collector_id=c.id")->fetchAll();
$collectors  = $db->query("SELECT * FROM collectors ORDER BY full_name")->fetchAll();
$sitio_schedule = [];
foreach ($weekly_rows as $row) {
    $s = $row['sitio'];
    if (isset($sitio_days[$s]) && $row['day_name'] === $sitio_days[$s]) {
        $sitio_schedule[$s] = $row;
    }
}
$today_day = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('l');
$unread    = get_unread_count($_SESSION['admin_id']);

// Handle add/delete sitio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_sitio'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            try {
                $db->prepare("INSERT INTO sitios (name) VALUES (?)")->execute([$name]);
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                foreach ($days as $d) {
                    $db->prepare("INSERT IGNORE INTO weekly_schedule (day_name,sitio,collection_time,waste_type) VALUES (?,?,'07:00','Mixed')")->execute([$d,$name]);
                }
            } catch (Exception $e) {}
        }
        header('Location: /disbasura/admin/dashboard.php'); exit;
    }
    if (isset($_POST['delete_sitio'])) {
        $name = trim($_POST['name'] ?? '');
        $db->prepare("DELETE FROM sitios WHERE name=?")->execute([$name]);
        $db->prepare("DELETE FROM weekly_schedule WHERE sitio=?")->execute([$name]);
        header('Location: /disbasura/admin/dashboard.php'); exit;
    }
    if (isset($_POST['update_weekly'])) {
        $sitio   = $_POST['sitio'] ?? '';
        $day     = $_POST['day_name'] ?? '';
        $status  = $_POST['status'] ?? 'pending';
        $ctime   = $_POST['collection_time'] ?? '07:00';
        $wtype   = $_POST['waste_type'] ?? 'Mixed';
        $col_id  = $_POST['collector_id'] ?: null;
        $db->prepare("UPDATE weekly_schedule SET status=?,collection_time=?,waste_type=?,collector_id=?,updated_at=? WHERE day_name=? AND sitio=?")
           ->execute([$status,$ctime,$wtype,$col_id,now_pht(),$day,$sitio]);
        header('Location: /disbasura/admin/dashboard.php'); exit;
    }
}

render_admin_header('dashboard', $unread, 'Dashboard — DisBasura Admin');
?>
<div class="page-header"><h1>Admin Dashboard</h1><p>System overview and management</p></div>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon green"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div><div class="stat-val"><?= $stats['total_schedules'] ?></div><div class="stat-label">Total Schedules</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div><div class="stat-val"><?= $stats['pending_requests'] ?></div><div class="stat-label">Pending Requests</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><div><div class="stat-val"><?= $stats['total_collectors'] ?></div><div class="stat-label">Collectors</div></div></div>
  <div class="stat-card"><div class="stat-icon teal"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div><div class="stat-val"><?= $stats['total_residents'] ?></div><div class="stat-label">Residents</div></div></div>
</div>

<!-- Weekly Schedule -->
<div class="weekly-section">
  <div class="weekly-section-title" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
    📅 Weekly Barangay Collection Schedule
    <span style="font-size:.75rem;font-weight:500;color:var(--text-light);margin-left:.5rem">Click any card to update</span>
    <button onclick="document.getElementById('addSitioModal').style.display='flex'" style="margin-left:auto;padding:.3rem .9rem;background:var(--green-main);color:#fff;border:none;border-radius:20px;font-size:.75rem;font-weight:700;cursor:pointer">+ Add Sitio</button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.85rem">
    <?php foreach ($sitios_list as $sitio):
        $day    = $sitio_days[$sitio] ?? '';
        $info   = $sitio_schedule[$sitio] ?? [];
        $status = $info['status'] ?? 'pending';
        $is_today = ($day === $today_day);
        $sitio_label = strpos($sitio,' - ') !== false ? explode(' - ',$sitio)[1] : $sitio;
        $ctime  = $info['collection_time'] ?? '07:00';
        $wtype  = $info['waste_type'] ?? 'Mixed';
        $col_id = $info['collector_id'] ?? '';
        $col_name = $info['collector_name'] ?? '';
    ?>
    <div class="sched-card status-<?= $status ?> <?= $is_today ? 'is-today' : '' ?>"
         style="background:#fff;border-radius:16px;border:1.5px solid var(--border-light);box-shadow:var(--shadow-sm);overflow:hidden;cursor:pointer;position:relative;<?= $is_today ? 'border-color:var(--green-main);box-shadow:0 0 0 2px rgba(45,134,83,.15);' : '' ?>"
         onclick="openSchedEdit('<?= htmlspecialchars($sitio,ENT_QUOTES) ?>','<?= $day ?>','<?= $status ?>','<?= $ctime ?>','<?= $wtype ?>','<?= $col_id ?>')">
      <?php if ($is_today): ?><div style="position:absolute;top:0;right:0;background:var(--green-main);color:#fff;font-size:.65rem;font-weight:800;padding:.2rem .6rem;border-radius:0 16px 0 8px">Today</div><?php endif; ?>
      <div style="padding:.9rem 1rem .6rem;display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem">
        <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:.22rem .7rem;border-radius:20px;background:#e8f5ee;color:var(--green-main)"><?= $day ?></span>
        <span style="font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:20px;<?= $status==='received'?'background:var(--green-pale);color:var(--green-main)':($status==='missed'?'background:#fff3e0;color:var(--orange)':'background:#f0f4f2;color:var(--text-light)') ?>">
          <?= $status==='received'?'✅ Received':($status==='missed'?'⚠️ Missed':'⏳ Pending') ?>
        </span>
      </div>
      <div style="padding:0 1rem .75rem">
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--text-dark);margin-bottom:.3rem"><?= htmlspecialchars($sitio_label) ?></div>
        <div style="font-size:.78rem;color:var(--text-light)">🕐 <?= htmlspecialchars($ctime) ?> · <?= htmlspecialchars($wtype) ?></div>
      </div>
      <div style="padding:.6rem 1rem;border-top:1px solid var(--border-light);background:#fafcfb;display:flex;align-items:center;justify-content:space-between" onclick="event.stopPropagation()">
        <span style="font-size:.75rem;color:var(--text-light)"><?= $col_name ? '🚛 '.htmlspecialchars($col_name) : '<em>No collector</em>' ?></span>
        <button class="edit-sched-btn" style="font-size:.72rem;font-weight:700;color:var(--green-main);border:1px solid #b6dfc7;border-radius:20px;padding:.2rem .65rem;background:var(--green-pale);cursor:pointer"
          onclick="openSchedEdit('<?= htmlspecialchars($sitio,ENT_QUOTES) ?>','<?= $day ?>','<?= $status ?>','<?= $ctime ?>','<?= $wtype ?>','<?= $col_id ?>')">✏️ Edit</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Edit Weekly Modal -->
<div id="schedEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.25rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:440px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 id="schedEditTitle" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">Update Schedule</h3>
    <form method="POST">
      <input type="hidden" name="update_weekly" value="1">
      <input type="hidden" name="sitio" id="edit_sitio_val">
      <input type="hidden" name="day_name" id="edit_day_val">
      <div class="field"><label>Status</label>
        <div style="display:flex;gap:.6rem;margin-top:.25rem">
          <?php foreach(['received'=>'✅ Received','missed'=>'⚠️ Missed','pending'=>'⏳ Pending'] as $v=>$lbl): ?>
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="status" value="<?= $v ?>" id="sr_<?= $v ?>" style="display:none">
            <div class="sched-radio" data-val="<?= $v ?>" style="border:1.5px solid var(--border);border-radius:8px;padding:.55rem .5rem;text-align:center;font-size:.8rem;font-weight:600;color:var(--text-mid);transition:all .16s;background:#fff"><?= $lbl ?></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field"><label>Collection Time</label><input type="time" name="collection_time" id="edit_time" required/></div>
        <div class="field"><label>Waste Type</label>
          <select name="waste_type" id="edit_waste_type">
            <?php foreach(['Biodegradable','Non-Biodegradable','Recyclable','Mixed'] as $wt): ?>
            <option><?= $wt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field"><label>Assigned Collector</label>
        <select name="collector_id" id="edit_collector_id">
          <option value="">None assigned</option>
          <?php foreach($collectors as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> — <?= htmlspecialchars($c['sitio']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeSchedEdit()">Cancel</button>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Sitio Modal -->
<div id="addSitioModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:600;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:16px;padding:2rem;width:100%;max-width:400px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;margin-bottom:1.25rem">➕ Add New Sitio</h3>
    <form method="POST"><input type="hidden" name="add_sitio" value="1">
      <div class="field"><label>Sitio Name</label><input type="text" name="name" placeholder="e.g. Sitio 7 - Magallanes" required/></div>
      <div style="display:flex;gap:.65rem;margin-top:1rem">
        <button type="button" onclick="document.getElementById('addSitioModal').style.display='none'" style="flex:1;padding:.75rem;border:1.5px solid var(--border);border-radius:8px;background:#fff;cursor:pointer;font-family:inherit">Cancel</button>
        <button type="submit" style="flex:1;padding:.75rem;background:var(--green-main);color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;cursor:pointer">Add Sitio</button>
      </div>
    </form>
    <div style="margin-top:1.5rem;border-top:1px solid var(--border-light);padding-top:1.25rem">
      <div style="font-size:.78rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem">Existing Sitios</div>
      <?php foreach($sitios_list as $s): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border-light)">
        <span style="font-size:.82rem"><?= htmlspecialchars($s) ?></span>
        <form method="POST" onsubmit="return confirm('Delete sitio <?= htmlspecialchars($s,ENT_QUOTES) ?>?')"><input type="hidden" name="delete_sitio" value="1"><input type="hidden" name="name" value="<?= htmlspecialchars($s,ENT_QUOTES) ?>"><button type="submit" style="border:none;background:none;color:var(--red);cursor:pointer;font-size:.75rem;font-weight:700;padding:.2rem .5rem">✕</button></form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Bottom panels -->
<div class="two-col" style="margin-top:1.5rem">
  <div class="panel">
    <div class="panel-header"><h3>Recent Requests</h3><a class="manage-link" href="/disbasura/admin/requests.php">Manage</a></div>
    <?php if($recent_requests): foreach($recent_requests as $r): ?>
    <div class="request-item">
      <div class="request-item-info"><strong><?= htmlspecialchars($r['full_name']) ?></strong><span><?= htmlspecialchars($r['sitio']) ?> · <?= htmlspecialchars($r['waste_type']) ?></span></div>
      <span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span>
    </div>
    <?php endforeach; else: ?><div class="empty-state"><p>No requests yet</p></div><?php endif; ?>
  </div>
  <div class="panel">
    <div class="panel-header"><h3>Today's Schedules</h3><a class="manage-link" href="/disbasura/admin/schedules.php">Manage</a></div>
    <?php if($today_schedules): foreach($today_schedules as $s): ?>
    <div class="request-item">
      <div class="request-item-info"><strong><?= htmlspecialchars($s['sitio']) ?></strong><span><?= fmt_date($s['scheduled_at']) ?> · <?= htmlspecialchars($s['waste_type']) ?></span></div>
      <span class="badge <?= $s['status'] ?>"><?= $s['status'] ?></span>
    </div>
    <?php endforeach; else: ?><div class="empty-state"><p>No schedules today</p></div><?php endif; ?>
  </div>
</div>

<script>
function openSchedEdit(sitio,day,status,time,wasteType,collectorId){
  document.getElementById('edit_sitio_val').value=sitio;
  document.getElementById('edit_day_val').value=day;
  document.getElementById('schedEditTitle').textContent='✏️ '+sitio.split(' - ').pop()+' — '+day;
  document.getElementById('edit_time').value=time||'07:00';
  document.getElementById('edit_waste_type').value=wasteType||'Mixed';
  document.getElementById('edit_collector_id').value=collectorId||'';
  ['received','missed','pending'].forEach(v=>{
    const r=document.getElementById('sr_'+v);
    if(r) r.checked=(v===status);
    const d=document.querySelector('[data-val="'+v+'"]');
    if(d){
      d.style.borderColor=v===status?(v==='missed'?'var(--orange)':v==='pending'?'#b0c8bb':'var(--green-main)'):'var(--border)';
      d.style.background=v===status?(v==='missed'?'#fff3e0':v==='pending'?'#f4f9f6':'var(--green-pale)'):'#fff';
      d.style.color=v===status?(v==='missed'?'var(--orange)':v==='pending'?'var(--text-light)':'var(--green-main)'):'var(--text-mid)';
    }
  });
  document.querySelectorAll('.sched-radio').forEach(el=>{
    el.addEventListener('click',function(){
      document.querySelectorAll('.sched-radio').forEach(x=>{x.style.borderColor='var(--border)';x.style.background='#fff';x.style.color='var(--text-mid)';});
      this.style.borderColor=this.dataset.val==='missed'?'var(--orange)':this.dataset.val==='pending'?'#b0c8bb':'var(--green-main)';
      this.style.background=this.dataset.val==='missed'?'#fff3e0':this.dataset.val==='pending'?'#f4f9f6':'var(--green-pale)';
      this.style.color=this.dataset.val==='missed'?'var(--orange)':this.dataset.val==='pending'?'var(--text-light)':'var(--green-main)';
    });
  });
  document.getElementById('schedEditModal').style.display='flex';
}
function closeSchedEdit(){ document.getElementById('schedEditModal').style.display='none'; }
document.getElementById('schedEditModal').addEventListener('click',function(e){ if(e.target===this)closeSchedEdit(); });
</script>
<?php render_admin_footer(); ?>
