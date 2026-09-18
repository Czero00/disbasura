<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
require_once __DIR__ . '/../includes/smart_dispatch.php';
$db = get_db();
// Auto-migrate: ensure optional columns exist
foreach ([
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS proof_photo VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS completed_at DATETIME DEFAULT NULL",
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS resident_proof_photo VARCHAR(500) DEFAULT NULL",
] as $sql) { try { $db->exec($sql); } catch(Exception $e){} }

// Fix ENUM — add 'assigned' status if missing
try {
    $db->exec("ALTER TABLE requests MODIFY COLUMN status ENUM('pending','approved','rejected','completed','assigned') NOT NULL DEFAULT 'pending'");
} catch(Exception $e){}

// Fix existing requests that have collector_id set but status is empty/null — set them to 'assigned'
try {
    $db->exec("UPDATE requests SET status='assigned' WHERE collector_id IS NOT NULL AND (status='' OR status IS NULL)");
} catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int)($_POST['id'] ?? 0);
    if (isset($_POST['approve'])) {
        $req = $db->prepare("SELECT * FROM requests WHERE id=?")->execute([$rid]);
        $req = $db->query("SELECT * FROM requests WHERE id=$rid")->fetch();
        $db->prepare("UPDATE requests SET status='approved' WHERE id=?")->execute([$rid]);
        log_activity($_SESSION['admin_id'],'Approved Request','Request #'.$rid.' - '.$req['sitio']); notify_user($db,$req['resident_id'],"✅ Your pickup request has been approved by admin!");
    } elseif (isset($_POST['reject'])) {
        $req = $db->query("SELECT * FROM requests WHERE id=$rid")->fetch();
        $db->prepare("UPDATE requests SET status='rejected' WHERE id=?")->execute([$rid]);
        log_activity($_SESSION['admin_id'],'Rejected Request','Request #'.$rid.' - '.$req['sitio']); notify_user($db,$req['resident_id'],"❌ Your pickup request was rejected by admin.");
    } elseif (isset($_POST['assign'])) {
        $col_id = (int)$_POST['collector_id'];
        if ($col_id) {
            $req = $db->query("SELECT * FROM requests WHERE id=$rid")->fetch();
            $col = $db->query("SELECT * FROM collectors WHERE id=$col_id")->fetch();

            // Was this the AI Smart Dispatch module's top pick, or did the admin override it?
            $recs = get_smart_dispatch_recommendations($db, $req);
            $topPick = $recs[0]['collector']['id'] ?? null;
            $dispatchNote = $topPick === null
                ? 'no AI recommendation available'
                : (((int)$topPick === $col_id)
                    ? 'followed AI Smart Dispatch recommendation'
                    : 'overrode AI Smart Dispatch recommendation');

            $db->prepare("UPDATE requests SET collector_id=?,ai_suggested_id=?,status='assigned' WHERE id=?")->execute([$col_id,$topPick,$rid]);
            // Notify resident
            notify_user($db,$req['resident_id'],"🚛 A collector (".$col['full_name'].") has been assigned to your pickup request for ".$req['sitio']."!");
            // Notify collector via notifications table (linked to collector's user account if exists)
            $col_user = $db->prepare("SELECT id FROM users WHERE username=?");
            $col_user->execute([$col['username'] ?? '']);
            $cu = $col_user->fetch();
            if ($cu) notify_user($db,$cu['id'],"📋 You have been assigned a new pickup request from ".$req['sitio']." — ".($req['waste_type']).".");
            log_activity($_SESSION['admin_id'],'Assigned Collector','Request #'.$rid.' → '.$col['full_name'].' ('.$dispatchNote.')');
        }
    } elseif (isset($_POST['complete'])) {
        $req = $db->query("SELECT * FROM requests WHERE id=$rid")->fetch();
        $db->prepare("UPDATE requests SET status='completed' WHERE id=?")->execute([$rid]);
        notify_user($db,$req['resident_id'],"✅ Your pickup request has been marked as completed by admin.");
        log_activity($_SESSION['admin_id'],'Completed Request','Request #'.$rid.' - '.$req['sitio']);
    }
    header('Location: /disbasura/admin/requests.php'); exit;
}
$status_filter = $_GET['status'] ?? '';
$collectors    = $db->query("SELECT * FROM collectors ORDER BY full_name")->fetchAll();
$q = "SELECT r.*,u.full_name,s.full_name as submitted_by_name,c.full_name as collector_name FROM requests r JOIN users u ON r.resident_id=u.id LEFT JOIN users s ON r.submitted_by=s.id LEFT JOIN collectors c ON r.collector_id=c.id";
if ($status_filter) { $stmt=$db->prepare($q." WHERE r.status=? ORDER BY r.created_at DESC"); $stmt->execute([$status_filter]); }
else { $stmt=$db->query($q." ORDER BY r.created_at DESC"); }
$requests = $stmt->fetchAll();
$unread = get_unread_count($_SESSION['admin_id']);
render_admin_header('requests',$unread,'Requests — DisBasura Admin');
?>
<div class="page-header"><h1>Pickup Requests</h1><p>Review and manage resident pickup requests</p></div>
<div class="filter-tabs">
  <?php foreach([''=> 'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','completed'=>'Completed','assigned'=>'Assigned'] as $v=>$lbl): ?>
  <a href="<?= $v?'?status='.$v:'/disbasura/admin/requests.php' ?>" class="tab <?= $status_filter===$v?'active':'' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>
<?php if($requests): foreach($requests as $r): ?>
<div class="panel" style="margin-bottom:1rem">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:.75rem">
    <div>
      <strong style="font-size:.95rem"><?= htmlspecialchars($r['full_name']) ?></strong>
      <?php if($r['submitted_by_name']): ?><span style="font-size:.78rem;color:var(--text-light)"> (via <?= htmlspecialchars($r['submitted_by_name']) ?>)</span><?php endif; ?>
      <div style="font-size:.82rem;color:var(--text-mid);margin-top:.2rem">📍 <?= htmlspecialchars($r['sitio']) ?> · <?= htmlspecialchars($r['waste_type']) ?></div>
      <div style="font-size:.78rem;color:var(--text-light)">📅 Preferred: <?= fmt_date($r['preferred_date']) ?></div>
      <?php if($r['note']): ?><div style="font-size:.78rem;color:var(--text-mid);margin-top:.2rem">💬 <?= htmlspecialchars($r['note']) ?></div><?php endif; ?>
      <?php if($r['location']): ?><div style="font-size:.78rem;color:var(--text-mid)">🏠 <?= htmlspecialchars($r['location']) ?></div><?php endif; ?>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem">
      <span class="badge <?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span>
      <?php if($r['collector_name']): ?>
      <span style="font-size:.72rem;background:#e8f5ee;color:#1e6b3c;border-radius:20px;padding:.2rem .65rem;font-weight:600">🚛 <?= htmlspecialchars($r['collector_name']) ?></span>
      <?php endif; ?>
      <?php if(!empty($r['proof_photo'])): ?>
      <a href="/disbasura/uploads/<?= htmlspecialchars($r['proof_photo']) ?>" target="_blank" style="font-size:.72rem;background:#e8f5ee;color:#1e6b3c;border-radius:20px;padding:.2rem .65rem;font-weight:600;text-decoration:none">📷 Collector Proof</a>
      <?php endif; ?>
      <?php if(!empty($r['resident_proof_photo'])): ?>
      <a href="/disbasura/uploads/<?= htmlspecialchars($r['resident_proof_photo']) ?>" target="_blank" style="font-size:.72rem;background:#eaf3fb;color:#1a5276;border-radius:20px;padding:.2rem .65rem;font-weight:600;text-decoration:none">🏠 Resident Proof</a>
      <?php endif; ?>
    </div>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
    <?php if($r['status']==='pending'): ?>
    <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button type="submit" name="approve" class="btn-approve" style="font-size:.8rem;padding:.4rem .9rem">✅ Approve</button></form>
    <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button type="submit" name="reject" class="btn-reject" style="font-size:.8rem;padding:.4rem .9rem">❌ Reject</button></form>
    <?php endif; ?>
    <?php if(in_array($r['status'],['approved','pending'])):
        $recs = get_smart_dispatch_recommendations($db, $r);
        $topRec = $recs[0] ?? null;
    ?>
    <?php if($topRec): ?>
    <div style="width:100%;background:#f1f9f4;border:1px solid #cfe9d9;border-radius:10px;padding:.55rem .75rem;margin:.25rem 0;font-size:.78rem">
      <strong style="color:#1e6b3c">🤖 AI Smart Dispatch:</strong>
      recommends <strong><?= htmlspecialchars($topRec['collector']['full_name']) ?></strong>
      (<?= $topRec['score'] ?>/100 — <?= htmlspecialchars($topRec['reason']) ?>)
      <?php if(count($recs) > 1): ?>
        <span style="color:var(--text-light)"> · next best:
        <?php $altList = array_slice($recs,1,2); foreach($altList as $i => $alt): ?>
          <?= htmlspecialchars($alt['collector']['full_name']) ?> (<?= $alt['score'] ?>)<?= $i < count($altList)-1 ? ', ' : '' ?>
        <?php endforeach; ?>
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <form method="POST" style="display:inline-flex;gap:.5rem;align-items:center">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <select name="collector_id" style="font-size:.8rem;border:1.5px solid var(--border);border-radius:8px;padding:.35rem .6rem">
        <option value="">Assign Collector…</option>
        <?php foreach($collectors as $c):
            $isTop = $topRec && (int)$topRec['collector']['id'] === (int)$c['id'];
            $selected = $r['collector_id'] ? ($r['collector_id']==$c['id']) : $isTop;
        ?>
        <option value="<?= $c['id'] ?>" <?= $selected?'selected':'' ?>><?= $isTop ? '⭐ ' : '' ?><?= htmlspecialchars($c['full_name']) ?> (<?= $c['status'] ?>)<?= $isTop ? ' — AI recommended' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" name="assign" class="btn-approve" style="font-size:.8rem;padding:.4rem .9rem">🚛 Assign</button>
    </form>
    <?php endif; ?>
    <?php if($r['status']==='assigned'): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Mark this request as completed?')">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <button type="submit" name="complete" style="font-size:.8rem;padding:.4rem .9rem;background:#1e6b3c;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600">✅ Mark Complete</button>
    </form>
    <?php endif; ?>
    <?php if($r['created_at']): ?><span style="font-size:.72rem;color:var(--text-light);margin-left:auto">Submitted: <?= fmt_date($r['created_at']) ?></span><?php endif; ?>
  </div>
</div>
<?php endforeach; else: ?>
<div class="empty-state" style="padding:4rem"><p>No requests found.</p></div>
<?php endif; ?>
<?php render_admin_footer(); ?>
