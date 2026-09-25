<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db  = get_db();
$aid = $_SESSION['admin_id'];

$activityColumns = array_column($db->query('SHOW COLUMNS FROM activity_log')->fetchAll(), 'Field');
$hasActorType = in_array('actor_type', $activityColumns, true);

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;
$total  = $db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$pages  = ceil($total / $limit);

// actor_id resolves against a different table depending on actor_type
// (administrators / users / collectors) — Table 21 notes this is a
// logical, not enforced, FK for exactly that reason.
$actorColumn = null;
$actorEntity = null;
foreach (['actor_id' => null, 'admin_id' => 'admin', 'user_id' => 'user', 'collector_id' => 'collector'] as $column => $entity) {
    if (in_array($column, $activityColumns, true)) {
        $actorColumn = $column;
        $actorEntity = $entity;
        break;
    }
}
$actionColumn = in_array('action_type', $activityColumns, true) ? 'action_type' : (in_array('action', $activityColumns, true) ? 'action' : (in_array('activity', $activityColumns, true) ? 'activity' : null));
$detailColumn = in_array('details', $activityColumns, true) ? 'details' : (in_array('description', $activityColumns, true) ? 'description' : null);
$timeColumn = in_array('created_at', $activityColumns, true) ? 'created_at' : (in_array('timestamp', $activityColumns, true) ? 'timestamp' : null);
$orderColumn = $timeColumn ?: (in_array('id', $activityColumns, true) ? 'id' : $activityColumns[0]);
$actorTypeSelect = $hasActorType ? 'l.actor_type' : "'admin' AS actor_type";
$actorJoins = '';
$actorName = "'Unknown'";
if ($actorColumn && $hasActorType) {
    $actorJoins = "LEFT JOIN administrators a ON l.actor_type='admin' AND l.$actorColumn=a.id
       LEFT JOIN users u ON l.actor_type IN ('resident','leader') AND l.$actorColumn=u.id
       LEFT JOIN collectors c ON l.actor_type='collector' AND l.$actorColumn=c.id";
    $actorName = "COALESCE(a.full_name, u.full_name, c.full_name, 'Unknown')";
} elseif ($actorColumn && $actorEntity === 'admin') {
    $actorJoins = "LEFT JOIN administrators a ON l.$actorColumn=a.id";
    $actorName = "COALESCE(a.full_name, 'Unknown')";
} elseif ($actorColumn && $actorEntity === 'user') {
    $actorJoins = "LEFT JOIN users u ON l.$actorColumn=u.id";
    $actorName = "COALESCE(u.full_name, 'Unknown')";
} elseif ($actorColumn && $actorEntity === 'collector') {
    $actorJoins = "LEFT JOIN collectors c ON l.$actorColumn=c.id";
    $actorName = "COALESCE(c.full_name, 'Unknown')";
} elseif ($actorColumn) {
    $actorJoins = "LEFT JOIN administrators a ON l.$actorColumn=a.id LEFT JOIN users u ON l.$actorColumn=u.id LEFT JOIN collectors c ON l.$actorColumn=c.id";
    $actorName = "COALESCE(a.full_name, u.full_name, c.full_name, 'Unknown')";
}
$actionSelect = $actionColumn ? "l.$actionColumn AS action_type" : "'Activity' AS action_type";
$detailSelect = $detailColumn ? "l.$detailColumn AS details" : "'' AS details";
$timeSelect = $timeColumn ? "l.$timeColumn AS created_at" : 'NULL AS created_at';
$logs = $db->query("
    SELECT l.*, $actionSelect, $detailSelect, $timeSelect, $actorTypeSelect, $actorName AS actor_name
    FROM activity_log l
    $actorJoins
    ORDER BY l.$orderColumn DESC LIMIT $limit OFFSET $offset
")->fetchAll();
$unread = get_unread_admin_count($aid);
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
