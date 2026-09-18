<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();

$month  = $_GET['month'] ?? date('Y-m');
$sitio  = $_GET['sitio'] ?? '';
$sitios = get_sitios();

$where  = "WHERE s.status='completed'";
$params = [];
if ($month) {
    $where .= " AND DATE_FORMAT(s.completed_at,'%Y-%m')=?";
    $params[] = $month;
}
if ($sitio) {
    $where .= " AND s.sitio=?";
    $params[] = $sitio;
}

$stmt = $db->prepare("
    SELECT s.*,
           c.full_name AS collector_name,
           ROUND(AVG(f.rating),1) AS avg_rating,
           COUNT(f.id) AS feedback_count
    FROM schedules s
    LEFT JOIN collectors c ON s.collector_id=c.id
    LEFT JOIN feedback f   ON f.schedule_id=s.id
    $where
    GROUP BY s.id
    ORDER BY s.completed_at DESC
");
$stmt->execute($params);
$history = $stmt->fetchAll();

$total   = count($history);
$unread  = get_unread_count($_SESSION['admin_id']);
render_admin_header('history', $unread, 'Collection History — DisBasura');
?>
<div class="page-header-row">
  <div class="page-header">
    <h1>📋 Collection History</h1>
    <p>All completed garbage collections — <?= $total ?> record<?= $total!=1?'s':'' ?></p>
  </div>
</div>

<!-- Filters -->
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:center">
  <form method="GET" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center">
    <div class="field" style="margin:0">
      <input type="month" name="month" value="<?= e($month) ?>"
             style="border:1.5px solid var(--border);border-radius:8px;padding:.5rem .85rem;font-family:inherit;font-size:.85rem"/>
    </div>
    <div class="field" style="margin:0">
      <select name="sitio" style="border:1.5px solid var(--border);border-radius:8px;padding:.5rem .85rem;font-family:inherit;font-size:.85rem">
        <option value="">All Sitios</option>
        <?php foreach($sitios as $s): ?>
        <option value="<?= e($s) ?>" <?= $sitio===$s?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-add" style="height:38px">Filter</button>
    <a href="/disbasura/admin/history.php" style="font-size:.83rem;color:var(--text-light);padding:.5rem .75rem;border:1.5px solid var(--border);border-radius:8px;text-decoration:none">Clear</a>
  </form>
</div>

<?php if($history): ?>
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--border-light)">
  <thead>
    <tr style="background:var(--bg-page);border-bottom:2px solid var(--border-light)">
      <?php foreach(['#','Sitio','Waste Type','Scheduled','Completed','Collector','Rating','Collector Proof','Resident Proof'] as $h): ?>
      <th style="padding:.85rem 1.1rem;text-align:left;font-size:.8rem;color:var(--text-mid);font-weight:600"><?= $h ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach($history as $i=>$r): ?>
  <tr style="border-bottom:1px solid var(--border-light)">
    <td style="padding:.85rem 1.1rem;color:var(--text-light);font-size:.8rem"><?= $i+1 ?></td>
    <td style="padding:.85rem 1.1rem;font-weight:600;font-size:.88rem"><?= e($r['sitio']) ?></td>
    <td style="padding:.85rem 1.1rem;font-size:.85rem"><?= e($r['waste_type']) ?></td>
    <td style="padding:.85rem 1.1rem;font-size:.82rem;color:var(--text-mid)"><?= fmt_date($r['scheduled_at']) ?></td>
    <td style="padding:.85rem 1.1rem;font-size:.82rem;color:var(--green-main);font-weight:600"><?= fmt_date($r['completed_at']) ?></td>
    <td style="padding:.85rem 1.1rem;font-size:.85rem"><?= e($r['collector_name'] ?? '—') ?></td>
    <td style="padding:.85rem 1.1rem">
      <?php if($r['feedback_count']>0): ?>
      <div style="display:flex;align-items:center;gap:.3rem">
        <span style="color:#f5a623;font-size:.95rem"><?= str_repeat('★', round($r['avg_rating'])).str_repeat('☆', 5-round($r['avg_rating'])) ?></span>
        <span style="font-size:.75rem;color:var(--text-light)">(<?= $r['feedback_count'] ?>)</span>
      </div>
      <?php else: ?>
      <span style="font-size:.78rem;color:var(--text-light)">No ratings</span>
      <?php endif; ?>
    </td>
    <td style="padding:.85rem 1.1rem">
      <?php if($r['proof_photo']): ?>
      <a href="/disbasura/uploads/<?= e($r['proof_photo']) ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:600;color:var(--green-main);text-decoration:none;padding:.3rem .7rem;background:var(--green-pale);border-radius:20px;border:1px solid #b6dfc7">
        📷 View
      </a>
      <?php else: ?><span style="font-size:.78rem;color:var(--text-light)">—</span><?php endif; ?>
    </td>
    <td style="padding:.85rem 1.1rem">
      <?php if(!empty($r['resident_proof_photo'])): ?>
      <a href="/disbasura/uploads/<?= e($r['resident_proof_photo']) ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:600;color:#1a5276;text-decoration:none;padding:.3rem .7rem;background:#eaf3fb;border-radius:20px;border:1px solid #a9cce3">
        🏠 View
      </a>
      <?php else: ?><span style="font-size:.78rem;color:var(--text-light)">—</span><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:16px;border:2px dashed var(--border);padding:4rem;text-align:center">
  <div style="font-size:3rem;margin-bottom:1rem">📋</div>
  <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;margin-bottom:.5rem">No completed collections found</h3>
  <p style="color:var(--text-light);font-size:.85rem">Try a different month or clear the filters.</p>
</div>
<?php endif; ?>
<?php render_admin_footer(); ?>
