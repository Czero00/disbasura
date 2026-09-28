<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/payment_helpers.php';
if (($_SESSION['role'] ?? '') !== 'leader') { header('Location: /disbasura/'); exit; }
$db = get_db();
$sitio = (string)($_SESSION['sitio'] ?? '');
$uid = (int)$_SESSION['user_id'];

// Existing installations need the extra handoff state in the requests ENUM.
try { $db->exec("ALTER TABLE requests MODIFY COLUMN status ENUM('pending','leader_approved','approved','rejected','completed','assigned') NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int)($_POST['rid'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM requests WHERE id=? AND sitio=? AND status='pending'");
    $stmt->execute([$rid, $sitio]);
    $req = $stmt->fetch();
    if ($req && isset($_POST['approve'])) {
        $db->prepare("UPDATE requests SET status='leader_approved' WHERE id=? AND sitio=? AND status='pending'")->execute([$rid, $sitio]);
        notify_user($db, (int)$req['resident_id'], 'Your pickup request was reviewed by your Sitio Leader and forwarded to Admin.');
        notify_all_admins($db, "Sitio Leader approved special pickup request #{$rid} in {$sitio}; it is ready for Admin review.", 'Request forwarded for Admin review');
        log_activity($uid, 'Forwarded Pickup Request', 'Request #'.$rid.' from '.$sitio.' to Admin', 'leader');
    } elseif ($req && isset($_POST['reject'])) {
        $db->prepare("UPDATE requests SET status='rejected' WHERE id=? AND sitio=? AND status='pending'")->execute([$rid, $sitio]);
        notify_user($db, (int)$req['resident_id'], 'Your pickup request was declined by your Sitio Leader.');
        log_activity($uid, 'Rejected Pickup Request', 'Request #'.$rid.' in '.$sitio, 'leader');
    }
    header('Location: /disbasura/leader/requests.php'); exit;
}

$statusFilter = (string)($_GET['status'] ?? '');
$sql = "SELECT r.*,u.full_name FROM requests r JOIN users u ON r.resident_id=u.id WHERE r.sitio=?";
$params = [$sitio];
if (in_array($statusFilter, ['pending','leader_approved','approved','rejected','completed','assigned'], true)) {
    $sql .= ' AND r.status=?'; $params[] = $statusFilter;
}
$sql .= ' ORDER BY r.created_at DESC';
$stmt = $db->prepare($sql); $stmt->execute($params); $requests = $stmt->fetchAll();
$labels = [''=>'All','pending'=>'Needs review','leader_approved'=>'Sent to Admin','approved'=>'Admin approved','rejected'=>'Rejected','completed'=>'Completed','assigned'=>'Assigned'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Sitio Requests — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"/><link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/></head>
<body style="background:var(--bg-page);padding:1.5rem"><div style="max-width:900px;margin:0 auto">
<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap"><a href="/disbasura/leader/dashboard.php" style="color:var(--text-mid);text-decoration:none">← Dashboard</a><h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.3rem;font-weight:800">Sitio Requests</h1><a href="/disbasura/leader/payments.php" class="btn-approve" style="margin-left:auto;text-decoration:none">Review Payments</a></div>
<div class="filter-tabs"><?php foreach($labels as $v=>$label): ?><a href="<?= $v ? '?status='.e($v) : '/disbasura/leader/requests.php' ?>" class="tab <?= $statusFilter===$v?'active':'' ?>"><?= e($label) ?></a><?php endforeach; ?></div>
<?php if($requests): foreach($requests as $r): ?><div class="panel" style="margin-bottom:1rem"><div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem"><div><strong><?= e($r['full_name']) ?></strong><div style="font-size:.82rem;color:var(--text-mid)"><?= e($r['waste_type']) ?> · <?= e(fmt_date($r['preferred_date'])) ?></div><div style="font-size:.82rem;color:var(--text-mid)"><?= e($r['location'] ?? '') ?></div></div><span class="badge <?= e($r['status']) ?>"><?= e($labels[$r['status']] ?? $r['status']) ?></span></div>
<?php if($r['status']==='pending'): ?><div style="display:flex;gap:.5rem;margin-top:.75rem"><form method="POST"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>"><button name="approve" class="btn-approve" style="font-size:.8rem">Approve and send to Admin</button></form><form method="POST" onsubmit="return confirm('Decline this pickup request?')"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>"><button name="reject" class="btn-reject" style="font-size:.8rem">Decline</button></form></div><?php endif; ?></div><?php endforeach; else: ?><div class="empty-state" style="padding:3rem"><p>No requests found.</p></div><?php endif; ?>
</div></body></html>
