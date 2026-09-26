<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
$data = [
    'total_schedules'    => $db->query("SELECT COUNT(*) FROM schedules")->fetchColumn(),
    'total_requests'     => $db->query("SELECT COUNT(*) FROM requests")->fetchColumn(),
    'total_collectors'   => $db->query("SELECT COUNT(*) FROM collectors")->fetchColumn(),
    'total_residents'    => $db->query("SELECT COUNT(*) FROM users WHERE role IN ('resident','leader')")->fetchColumn(),
    'schedules_by_sitio' => $db->query("SELECT sitio,COUNT(*) as count FROM schedules GROUP BY sitio")->fetchAll(),
    'requests_by_status' => $db->query("SELECT status,COUNT(*) as count FROM requests GROUP BY status")->fetchAll(),
    'waste_distribution' => $db->query("SELECT waste_type,COUNT(*) as count FROM schedules GROUP BY waste_type")->fetchAll(),
    'dispute_total'      => $db->query("SELECT COUNT(*) FROM disputes")->fetchColumn(),
    'dispute_by_status'  => $db->query("SELECT status,COUNT(*) AS count FROM disputes GROUP BY status ORDER BY status")->fetchAll(),
    'dispute_by_sitio'   => $db->query("SELECT s.sitio,COUNT(d.id) AS total, SUM(d.status='pending') AS pending, SUM(d.status='resolved') AS resolved, SUM(d.status='rejected') AS rejected FROM disputes d JOIN schedules s ON s.id=d.schedule_id GROUP BY s.sitio ORDER BY total DESC, s.sitio")->fetchAll(),
    'dispute_by_verdict' => $db->query("SELECT COALESCE(NULLIF(resolution,''),'Pending review') AS verdict,COUNT(*) AS count FROM disputes GROUP BY verdict ORDER BY count DESC")->fetchAll(),
];
$unread = get_unread_admin_count($_SESSION['admin_id']);
render_admin_header('reports',$unread,'Reports — DisBasura Admin');
?>
<div class="page-header"><h1>System Reports</h1><p>Overview of system statistics</p></div>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon green"></div><div><div class="stat-val"><?= $data['total_schedules'] ?></div><div class="stat-label">Total Schedules</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"></div><div><div class="stat-val"><?= $data['total_requests'] ?></div><div class="stat-label">Total Requests</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"></div><div><div class="stat-val"><?= $data['total_collectors'] ?></div><div class="stat-label">Collectors</div></div></div>
  <div class="stat-card"><div class="stat-icon teal"></div><div><div class="stat-val"><?= $data['total_residents'] ?></div><div class="stat-label">Residents</div></div></div>
</div>
<div class="two-col" style="margin-top:1.5rem">
  <div class="panel"><div class="panel-header"><h3>Schedules by Sitio</h3></div>
    <?php foreach($data['schedules_by_sitio'] as $r): ?>
    <div class="request-item"><div class="request-item-info"><strong><?= htmlspecialchars($r['sitio']) ?></strong></div><span class="badge scheduled"><?= $r['count'] ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="panel"><div class="panel-header"><h3>Requests by Status</h3></div>
    <?php foreach($data['requests_by_status'] as $r): ?>
    <div class="request-item"><div class="request-item-info"><strong><?= htmlspecialchars($r['status']) ?></strong></div><span class="badge <?= $r['status'] ?>"><?= $r['count'] ?></span></div>
    <?php endforeach; ?>
  </div>
</div>
<div class="panel" style="margin-top:1.5rem"><div class="panel-header"><h3>Waste Type Distribution</h3></div>
  <div style="display:flex;flex-wrap:wrap;gap:1rem;padding-top:.5rem">
  <?php foreach($data['waste_distribution'] as $r): ?>
  <div style="background:var(--green-pale);border-radius:12px;padding:.75rem 1.25rem;text-align:center"><div style="font-size:1.5rem;font-weight:800;color:var(--green-main)"><?= $r['count'] ?></div><div style="font-size:.78rem;color:var(--text-mid)"><?= htmlspecialchars($r['waste_type']) ?></div></div>
  <?php endforeach; ?>
  </div>
</div>
<section style="margin-top:2rem">
  <div class="page-header"><h2 style="font-size:1.25rem">Dispute Reports</h2><p>Historical dispute volume, status, and admin verdicts.</p></div>
  <div class="stats-grid" style="margin-top:1rem">
    <div class="stat-card"><div class="stat-icon orange"></div><div><div class="stat-val"><?= (int)$data['dispute_total'] ?></div><div class="stat-label">Total Disputes</div></div></div>
    <?php foreach (['pending'=>'Pending','resolved'=>'Resolved','rejected'=>'Rejected'] as $status=>$label): $count=0; foreach($data['dispute_by_status'] as $row) if($row['status']===$status) $count=(int)$row['count']; ?>
    <div class="stat-card"><div class="stat-icon <?= $status==='pending'?'orange':'green' ?>"></div><div><div class="stat-val"><?= $count ?></div><div class="stat-label"><?= $label ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="two-col" style="margin-top:1rem">
    <div class="panel"><div class="panel-header"><h3>Disputes by Sitio</h3></div>
      <?php if ($data['dispute_by_sitio']): foreach ($data['dispute_by_sitio'] as $row): ?>
      <div class="request-item"><div class="request-item-info"><strong><?= htmlspecialchars($row['sitio']) ?></strong><span><?= (int)$row['pending'] ?> pending · <?= (int)$row['resolved'] ?> resolved · <?= (int)$row['rejected'] ?> rejected</span></div><span class="badge"><?= (int)$row['total'] ?></span></div>
      <?php endforeach; else: ?><p class="empty-state" style="padding:1.5rem">No disputes recorded.</p><?php endif; ?>
    </div>
    <div class="panel"><div class="panel-header"><h3>Admin Verdicts</h3></div>
      <?php if ($data['dispute_by_verdict']): foreach ($data['dispute_by_verdict'] as $row): ?>
      <div class="request-item"><div class="request-item-info"><strong><?= htmlspecialchars(ucfirst($row['verdict'])) ?></strong></div><span class="badge"><?= (int)$row['count'] ?></span></div>
      <?php endforeach; else: ?><p class="empty-state" style="padding:1.5rem">No dispute verdicts recorded.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php render_admin_footer(); ?>
