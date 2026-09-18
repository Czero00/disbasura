<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db  = get_db();
$aid = $_SESSION['admin_id'];

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;
$total  = $db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$pages  = ceil($total / $limit);

// actor_id resolves against a different table depending on actor_type
// (administrators / users / collectors) — Table 21 notes this is a
// logical, not enforced, FK for exactly that reason.
$logs = $db->query("
    SELECT l.*, COALESCE(a.full_name, u.full_name, c.full_name, 'Unknown') AS actor_name
    FROM activity_log l
    LEFT JOIN administrators a ON l.actor_type='admin' AND l.actor_id=a.id
    LEFT JOIN users u          ON l.actor_type IN ('resident','leader') AND l.actor_id=u.id
    LEFT JOIN collectors c     ON l.actor_type='collector' AND l.actor_id=c.id
    ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset
")->fetchAll();
$unread = get_unread_count($aid);
render_admin_header('activity', $unread, 'Activity Log — DisBasura');

$action_icons = [
    'Login'             => '🔑',
    'Posted'            => '📢',
    'Deleted'           => '🗑️',
    'Added'             => '➕',
    'Updated'           => '✏️',
    'Approved'          => '✅',
    'Rejected'          => '❌',
    'Assigned'          => '🚛',
    'Created'           => '📋',
    'Renamed'           => '✏️',
    'Resolved'          => '⚖️',
];
function get_icon(string $action, array $icons): string {
    foreach($icons as $key => $icon) {
        if (stripos($action, $key) !== false) return $icon;
    }
    return '📝';
}
?>
<div class="page-header">
  <h1>📝 Admin Activity Log</h1>
  <p>Every action taken by the administrator — <?= number_format($total) ?> total records</p>
</div>

<?php if($logs): ?>
<div style="background:#fff;border-radius:16px;border:1px solid var(--border-light);overflow:hidden;box-shadow:var(--shadow-sm)">
  <?php foreach($logs as $i => $log):
    $icon = get_icon($log['action_type'], $action_icons);
    $is_even = $i % 2 === 0;
  ?>
  <div style="display:flex;align-items:flex-start;gap:1rem;padding:.95rem 1.4rem;<?= !$is_even ? 'background:var(--bg-page)' : '' ?>;border-bottom:1px solid var(--border-light)">
    <div style="width:34px;height:34px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0"><?= $icon ?></div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
        <strong style="font-size:.88rem;font-weight:700"><?= e($log['action_type']) ?></strong>
        <span style="font-size:.75rem;color:var(--text-light)"><?= fmt_date($log['created_at']) ?></span>
      </div>
      <?php if($log['details']): ?>
      <div style="font-size:.8rem;color:var(--text-mid);margin-top:.2rem"><?= e($log['details']) ?></div>
      <?php endif; ?>
      <div style="font-size:.73rem;color:var(--text-light);margin-top:.15rem">by <?= e($log['actor_name']) ?> <span style="text-transform:capitalize">(<?= e($log['actor_type']) ?>)</span></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if($pages > 1): ?>
<div style="display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap">
  <?php for($p=1;$p<=$pages;$p++): ?>
  <a href="?page=<?= $p ?>" style="padding:.45rem .9rem;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;<?= $p===$page ? 'background:var(--green-main);color:#fff' : 'background:#fff;color:var(--text-mid);border:1.5px solid var(--border)' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div style="background:#fff;border-radius:16px;border:2px dashed var(--border);padding:4rem;text-align:center;margin-top:1rem">
  <div style="font-size:3rem;margin-bottom:1rem">📝</div>
  <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700">No activity logged yet</h3>
  <p style="color:var(--text-light);font-size:.85rem">Actions you take will appear here automatically.</p>
</div>
<?php endif; ?>
<?php render_admin_footer(); ?>
