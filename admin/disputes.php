<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();

$disputeHasUserId = (bool)$db->query("SHOW COLUMNS FROM disputes LIKE 'user_id'")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $did     = (int)($_POST['did'] ?? 0);
    $verdict = $_POST['verdict'] ?? '';
    $disputeStmt = $db->prepare("SELECT * FROM disputes WHERE id=? AND status='pending'");
    $disputeStmt->execute([$did]);
    $dispute = $disputeStmt->fetch();
    if ($dispute) {
        $resolution = $verdict === 'confirm' ? 'confirm' : 'reopen';
        $db->prepare("UPDATE disputes SET status='resolved',resolution=?,admin_id=? WHERE id=?")
            ->execute([$resolution,$_SESSION['admin_id'],$did]);
        if ($verdict === 'confirm') {
            $db->prepare("UPDATE schedules SET status='completed' WHERE id=?")->execute([$dispute['schedule_id']]);
            if (!empty($dispute['user_id'])) {
                notify_user($db,$dispute['user_id'],"Your dispute was reviewed. Admin confirmed the collection was completed.");
            }
        } else {
            $db->prepare("UPDATE schedules SET status='scheduled',proof_photo=NULL WHERE id=?")->execute([$dispute['schedule_id']]);
            if (!empty($dispute['user_id'])) {
                notify_user($db,$dispute['user_id'],"Your dispute was reviewed. The schedule has been re-opened; collection will be done again.");
            }
        }
        log_activity($_SESSION['admin_id'], 'Resolved Dispute', "Dispute #$did — verdict: $verdict", 'admin', 'disputes', $did);
    }
    header('Location: /disbasura/admin/disputes.php'); exit;
}
$residentJoin = $disputeHasUserId ? 'LEFT JOIN users u ON d.user_id=u.id' : '';
$residentName = $disputeHasUserId ? 'u.full_name' : "'Unknown resident'";
$disputes = $db->query("SELECT d.*,$residentName AS resident_name,s.sitio,s.scheduled_at,s.waste_type,s.proof_photo AS sched_proof_photo,s.collector_id,c.full_name AS collector_name FROM disputes d $residentJoin JOIN schedules s ON d.schedule_id=s.id LEFT JOIN collectors c ON s.collector_id=c.id WHERE d.status='pending' ORDER BY d.created_at DESC")->fetchAll();
$unread = get_unread_admin_count($_SESSION['admin_id']);
render_admin_header('disputes',$unread,'Disputes — DisBasura Admin');
?>
<div class="page-header"><h1>⚠️ Disputed Collections</h1><p>Review resident disputes and make a verdict</p></div>
<?php if($disputes): foreach($disputes as $d): ?>
<div style="background:#fff;border-radius:16px;border:1px solid var(--border-light);box-shadow:var(--shadow-sm);padding:1.5rem;margin-bottom:1.25rem">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border-light)">
    <div>
      <strong style="font-size:1rem"><?= htmlspecialchars($d['sitio']) ?> — <?= htmlspecialchars($d['waste_type']) ?></strong>
      <div style="font-size:.82rem;color:var(--text-light)">Scheduled: <?= fmt_date($d['scheduled_at']) ?></div>
      <div style="font-size:.82rem;color:var(--text-mid)">👤 Resident: <strong><?= htmlspecialchars($d['resident_name'] ?? 'Unknown resident') ?></strong></div>
      <div style="font-size:.82rem;color:var(--text-mid)">🚛 Collector: <strong><?= htmlspecialchars($d['collector_name']??'Unassigned') ?></strong></div>
      <div style="font-size:.82rem;color:var(--orange)">Filed: <?= fmt_date($d['created_at']) ?></div>
    </div>
    <span class="badge" style="background:#fff3e0;color:var(--orange);border:1px solid #fdd9a0">⚠️ Pending Review</span>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
    <div style="border-radius:12px;overflow:hidden;border:1px solid var(--border-light)">
      <div style="padding:.55rem .85rem;font-size:.78rem;font-weight:700;background:var(--green-pale);color:var(--green-main)">🚛 Collector's Proof (Claims Done)</div>
      <?php if($d['sched_proof_photo']): ?><img src="/disbasura/uploads/<?= htmlspecialchars($d['sched_proof_photo']) ?>" style="width:100%;max-height:220px;object-fit:cover;display:block"/><?php else: ?><div style="padding:2rem;text-align:center;background:var(--bg-page);color:var(--text-light);font-size:.82rem">No photo submitted</div><?php endif; ?>
    </div>
    <div style="border-radius:12px;overflow:hidden;border:1px solid var(--border-light)">
      <div style="padding:.55rem .85rem;font-size:.78rem;font-weight:700;background:#fff3e0;color:var(--orange)">👤 Resident's Photo (Claims NOT Done)</div>
      <?php if($d['proof_photo']): ?><img src="/disbasura/uploads/<?= htmlspecialchars($d['proof_photo']) ?>" style="width:100%;max-height:220px;object-fit:cover;display:block"/><?php else: ?><div style="padding:2rem;text-align:center;background:var(--bg-page);color:var(--text-light);font-size:.82rem">No photo submitted</div><?php endif; ?>
    </div>
  </div>
  <?php if($d['description']): ?><div style="background:var(--bg-page);border-radius:8px;padding:.75rem 1rem;font-size:.83rem;color:var(--text-mid);margin-bottom:1.25rem">💬 Resident's note: "<?= htmlspecialchars($d['description']) ?>"</div><?php endif; ?>
  <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
    <span style="font-size:.82rem;font-weight:600;color:var(--text-mid)">Your Verdict:</span>
    <form method="POST" style="display:inline"><input type="hidden" name="did" value="<?= $d['id'] ?>"><button type="submit" name="verdict" value="confirm" class="btn-approve" onclick="return confirm('Confirm the collector completed this?')" style="display:inline-flex;align-items:center;gap:.4rem">✅ Collector was Honest — Confirm Done</button></form>
    <form method="POST" style="display:inline"><input type="hidden" name="did" value="<?= $d['id'] ?>"><button type="submit" name="verdict" value="reopen" class="btn-reject" onclick="return confirm('Re-open this schedule? The collector must collect again.')" style="display:inline-flex;align-items:center;gap:.4rem;margin-left:.5rem">🔁 Re-open — Collector Must Redo</button></form>
  </div>
</div>
<?php endforeach; else: ?>
<div class="empty-state" style="padding:4rem"><p>No pending disputes. All collections are verified! ✅</p></div>
<?php endif; ?>
<?php render_admin_footer(); ?>
