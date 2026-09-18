<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] !== 'leader') { header('Location: /disbasura/'); exit; }
$db=$get_db=get_db(); $sitio=$_SESSION['sitio']??'';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $rid=(int)$_POST['rid'];
    $req=$db->query("SELECT * FROM requests WHERE id=$rid AND sitio='".addslashes($sitio)."'")->fetch();
    if ($req) {
        if (isset($_POST['approve'])) { $db->prepare("UPDATE requests SET status='approved' WHERE id=?")->execute([$rid]); notify_user($db,$req['resident_id'],"✅ Your pickup request has been approved by your Sitio Leader!"); }
        elseif (isset($_POST['reject'])) { $db->prepare("UPDATE requests SET status='rejected' WHERE id=?")->execute([$rid]); notify_user($db,$req['resident_id'],"❌ Your pickup request was rejected by your Sitio Leader."); }
    }
    header('Location: /disbasura/leader/requests.php'); exit;
}
$status_filter=$_GET['status']??'';
if($status_filter){ $stmt=$db->prepare("SELECT r.*,u.full_name FROM requests r JOIN users u ON r.resident_id=u.id WHERE r.sitio=? AND r.status=? ORDER BY r.created_at DESC"); $stmt->execute([$sitio,$status_filter]); }
else { $stmt=$db->prepare("SELECT r.*,u.full_name FROM requests r JOIN users u ON r.resident_id=u.id WHERE r.sitio=? ORDER BY r.created_at DESC"); $stmt->execute([$sitio]); }
$requests=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Sitio Requests — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"/><link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/></head>
<body style="background:var(--bg-page);padding:1.5rem">
<div style="max-width:800px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <a href="/disbasura/leader/dashboard.php" style="color:var(--text-mid);text-decoration:none">←</a>
    <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.3rem;font-weight:800">Sitio Requests</h1>
  </div>
  <div class="filter-tabs"><?php foreach([''=> 'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$lbl): ?><a href="<?= $v?'?status='.$v:'/disbasura/leader/requests.php' ?>" class="tab <?= $status_filter===$v?'active':'' ?>"><?= $lbl ?></a><?php endforeach; ?></div>
  <?php if($requests): foreach($requests as $r): ?>
  <div class="panel" style="margin-bottom:1rem">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div><strong><?= htmlspecialchars($r['full_name']) ?></strong><div style="font-size:.82rem;color:var(--text-mid)"><?= htmlspecialchars($r['waste_type']) ?> · <?= fmt_date($r['preferred_date']) ?></div></div>
      <span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span>
    </div>
    <?php if($r['status']==='pending'): ?>
    <div style="display:flex;gap:.5rem;margin-top:.75rem">
      <form method="POST"><input type="hidden" name="rid" value="<?= $r['id'] ?>"><button name="approve" class="btn-approve" style="font-size:.8rem">✅ Approve</button></form>
      <form method="POST"><input type="hidden" name="rid" value="<?= $r['id'] ?>"><button name="reject" class="btn-reject" style="font-size:.8rem">❌ Reject</button></form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; else: ?><div class="empty-state" style="padding:3rem"><p>No requests found.</p></div><?php endif; ?>
</div></body></html>
