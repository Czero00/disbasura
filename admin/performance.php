<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();

$collectors = $db->query("
    SELECT
        c.*,
        COUNT(DISTINCT s.id)                                          AS total_assigned,
        SUM(CASE WHEN s.status='completed' THEN 1 ELSE 0 END)        AS total_completed,
        SUM(CASE WHEN s.status='unverified' THEN 1 ELSE 0 END)       AS total_missed,
        COUNT(DISTINCT d.id)                                          AS total_disputes,
        ROUND(AVG(f.rating),1)                                        AS avg_rating,
        COUNT(DISTINCT f.id)                                          AS total_feedback,
        CASE WHEN COUNT(DISTINCT s.id)>0
             THEN ROUND(SUM(CASE WHEN s.status='completed' THEN 1 ELSE 0 END)/COUNT(DISTINCT s.id)*100,1)
             ELSE 0 END                                               AS completion_rate
    FROM collectors c
    LEFT JOIN schedules s  ON s.collector_id=c.id
    LEFT JOIN disputes d   ON d.schedule_id=s.id
    LEFT JOIN feedback f   ON f.schedule_id=s.id
    GROUP BY c.id
    ORDER BY total_completed DESC
")->fetchAll();

$unread = get_unread_count($_SESSION['admin_id']);
render_admin_header('performance', $unread, 'Collector Performance — DisBasura');
?>
<div class="page-header">
  <h1>📊 Collector Performance Dashboard</h1>
  <p>Track each collector's completion rate, ratings, and dispute history</p>
</div>

<?php if($collectors): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;margin-top:1.5rem">
<?php foreach($collectors as $c):
    $rate  = (float)$c['completion_rate'];
    $color = $rate >= 80 ? 'var(--green-main)' : ($rate >= 50 ? 'var(--orange)' : 'var(--red)');
    $stars = round($c['avg_rating'] ?? 0);
?>
<div style="background:#fff;border-radius:16px;border:1px solid var(--border-light);box-shadow:var(--shadow-sm);overflow:hidden">
  <!-- Card header -->
  <div style="background:linear-gradient(135deg,#1a4a2e,#2d8653);padding:1.25rem 1.5rem;display:flex;align-items:center;gap:.85rem">
    <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;color:#fff;flex-shrink:0">
      <?= strtoupper(substr($c['full_name'],0,1)) ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.95rem;font-weight:800;color:#fff;margin-bottom:.15rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($c['full_name']) ?></div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.6)">📍 <?= e($c['sitio']) ?></div>
    </div>
    <span style="padding:.25rem .75rem;border-radius:20px;font-size:.7rem;font-weight:700;<?= $c['status']==='available'?'background:rgba(76,175,128,.25);color:#7ee8b0':($c['status']==='sick'?'background:rgba(245,166,35,.25);color:#fdd087':'background:rgba(224,82,82,.25);color:#f5a0a0') ?>">
      <?= $c['status']==='available'?'✅ Available':($c['status']==='sick'?'🤒 Sick':'⛔ Off') ?>
    </span>
  </div>

  <!-- Completion rate bar -->
  <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-light)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
      <span style="font-size:.78rem;font-weight:600;color:var(--text-mid)">Completion Rate</span>
      <span style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:<?= $color ?>"><?= $rate ?>%</span>
    </div>
    <div style="height:8px;background:var(--border-light);border-radius:20px;overflow:hidden">
      <div style="height:100%;width:<?= min($rate,100) ?>%;background:<?= $color ?>;border-radius:20px;transition:width .5s ease"></div>
    </div>
  </div>

  <!-- Stats grid -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid var(--border-light)">
    <?php
    $stats = [
        ['Total', $c['total_assigned'], '#1a4a2e'],
        ['Done', $c['total_completed'], 'var(--green-main)'],
        ['Missed', $c['total_missed'], 'var(--red)'],
    ];
    foreach($stats as $idx=>[$label,$val,$col]):
    ?>
    <div style="padding:.9rem .75rem;text-align:center;<?= $idx<2?'border-right:1px solid var(--border-light)':'' ?>">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.5rem;font-weight:800;color:<?= $col ?>"><?= (int)$val ?></div>
      <div style="font-size:.7rem;color:var(--text-light);font-weight:600"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Rating & disputes -->
  <div style="padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
    <div>
      <div style="display:flex;align-items:center;gap:.3rem;margin-bottom:.2rem">
        <?php for($i=1;$i<=5;$i++): ?>
        <span style="font-size:1rem;color:<?= $i<=$stars?'#f5a623':'#d4e6db' ?>"><?= $i<=$stars?'★':'☆' ?></span>
        <?php endfor; ?>
        <span style="font-size:.75rem;color:var(--text-light);margin-left:.25rem">
          <?= $c['avg_rating'] ? $c['avg_rating'].' / 5.0' : 'No ratings' ?>
          <?= $c['total_feedback'] ? ' ('.$c['total_feedback'].' reviews)' : '' ?>
        </span>
      </div>
      <div style="font-size:.75rem;color:var(--text-light)">
        <?= (int)$c['total_disputes'] ?> dispute<?= $c['total_disputes']!=1?'s':'' ?> filed
      </div>
    </div>
    <a href="/disbasura/admin/history.php?collector=<?= $c['id'] ?>" style="font-size:.75rem;color:var(--green-main);font-weight:600;text-decoration:none;padding:.35rem .85rem;background:var(--green-pale);border-radius:20px;border:1px solid #b6dfc7">View History →</a>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:16px;border:2px dashed var(--border);padding:4rem;text-align:center;margin-top:1.5rem">
  <div style="font-size:3rem;margin-bottom:1rem">📊</div>
  <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700">No collectors added yet</h3>
</div>
<?php endif; ?>
<?php render_admin_footer(); ?>
