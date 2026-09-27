<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sid = (int)($_GET['sid'] ?? 0);
$db = get_db();
$uid = (int)$_SESSION['user_id'];
$disputeHasUserId = (bool)$db->query("SHOW COLUMNS FROM disputes LIKE 'user_id'")->fetch();
$backUrl = $_SESSION['role'] === 'leader' ? '/disbasura/leader/schedules.php' : '/disbasura/resident/dashboard.php';
$schedStmt = $db->prepare('SELECT * FROM schedules WHERE id=?');
$schedStmt->execute([$sid]);
$sched = $schedStmt->fetch();
$in_sitio = $sched && $sched['sitio'] === ($_SESSION['sitio'] ?? '');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sched && $in_sitio) {
    $note = trim($_POST['note'] ?? '');
    if ($note === '') {
        $error = 'Please describe what happened so the admin can review your dispute.';
    } else {
        $existingQuery = $disputeHasUserId
            ? $db->prepare('SELECT id FROM disputes WHERE schedule_id=? AND user_id=?')
            : $db->prepare('SELECT id FROM disputes WHERE schedule_id=?');
        $existingQuery->execute($disputeHasUserId ? [$sid, $uid] : [$sid]);
        if (!$existingQuery->fetch()) {
            $fname = null;
            if (isset($_FILES['dispute_photo']) && ($_FILES['dispute_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $fname = save_upload($_FILES['dispute_photo'], "dispute_{$sid}_{$uid}");
            }
            if ($disputeHasUserId) {
                $db->prepare('INSERT INTO disputes (schedule_id,user_id,proof_photo,description) VALUES (?,?,?,?)')->execute([$sid,$uid,$fname,$note]);
            } else {
                $db->prepare('INSERT INTO disputes (schedule_id,proof_photo,description) VALUES (?,?,?)')->execute([$sid,$fname,$note]);
            }
            $db->prepare("UPDATE schedules SET status='disputed' WHERE id=?")->execute([$sid]);
            notify_all_admins($db, "Dispute filed by {$_SESSION['full_name']} ({$_SESSION['role']}) for {$sched['sitio']} schedule. Review needed.");
            header('Location: '.$backUrl); exit;
        }
        header('Location: '.$backUrl); exit;
    }
}
$existingQuery = $disputeHasUserId
    ? $db->prepare('SELECT * FROM disputes WHERE schedule_id=? AND user_id=?')
    : $db->prepare('SELECT * FROM disputes WHERE schedule_id=?');
$existingQuery->execute($disputeHasUserId ? [$sid, $uid] : [$sid]);
$existing_dispute = $existingQuery->fetch();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>File a Dispute — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"/><link rel="stylesheet" href="/disbasura/assets/css/resident-workflows.css"/></head>
<body class="workflow-page"><div class="workflow-shell">
  <header class="workflow-top"><a class="workflow-brand" href="<?= $backUrl ?>"><span class="workflow-brand-mark">🍃</span><span><strong>DisBasura</strong><small>GARBAGE MANAGEMENT SYSTEM</small></span></a><a class="workflow-back" href="<?= $backUrl ?>">← Back</a></header>
  <main class="workflow-card">
    <span class="workflow-kicker">⚠ &nbsp;Schedule feedback</span>
    <h1 class="workflow-title">File a Dispute</h1>
    <p class="workflow-subtitle">Tell the barangay about a collection problem. Your report will be sent to the admin for review.</p>
    <?php if ($error): ?><div class="workflow-alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($existing_dispute): ?>
      <div class="workflow-alert">You have already filed a dispute for this schedule. It is currently <strong><?= e(ucfirst($existing_dispute['status'] ?? 'pending')) ?></strong> and awaiting review.</div>
      <?php if (!empty($existing_dispute['description'])): ?><div class="workflow-context"><span class="workflow-context-icon">📝</span><span><small>Your report</small><strong><?= e($existing_dispute['description']) ?></strong></span></div><?php endif; ?>
    <?php elseif ($sched && $in_sitio): ?>
      <div class="workflow-context"><span class="workflow-context-icon">🗓️</span><span><small>Collection schedule</small><strong><?= e($sched['sitio']) ?> · <?= e($sched['waste_type'] ?? 'Waste collection') ?><br><?= e(fmt_date($sched['scheduled_at'])) ?></strong></span></div>
      <form method="POST" enctype="multipart/form-data">
        <div class="workflow-field"><label class="workflow-label" for="dispute_photo">Photo evidence <span style="color:#91a0b2;font-weight:600">(optional)</span></label><input class="workflow-control" id="dispute_photo" type="file" name="dispute_photo" accept="image/png,image/jpeg,image/webp,image/gif"/><p class="workflow-hint">Attach a clear photo that helps explain the collection issue.</p><img id="photoPreview" alt="Selected photo preview" style="display:none;margin-top:12px;width:100%;max-height:240px;object-fit:cover;border-radius:14px;border:1px solid #dfe7ef"></div>
        <div class="workflow-field"><label class="workflow-label" for="note">Describe the issue <span class="workflow-required">*</span></label><textarea class="workflow-control workflow-textarea" id="note" name="note" maxlength="2000" required placeholder="For example: the scheduled collection was missed, or the collected items were left behind."><?= e($_POST['note'] ?? '') ?></textarea><p class="workflow-hint">Include what happened and any details that can help the admin investigate.</p></div>
        <div class="workflow-actions"><button class="workflow-submit" type="submit">Submit Dispute &nbsp;→</button><a class="workflow-cancel" href="<?= $backUrl ?>">Cancel</a></div>
      </form>
    <?php else: ?>
      <div class="workflow-alert error">This schedule could not be found, or it is not for your sitio.</div>
    <?php endif; ?>
    <p class="workflow-footer">Need to return? <a href="<?= $backUrl ?>">Go back to your panel</a></p>
  </main>
</div><script>document.getElementById('dispute_photo')?.addEventListener('change',function(){const img=document.getElementById('photoPreview');if(this.files&&this.files[0]){img.src=URL.createObjectURL(this.files[0]);img.style.display='block';}else{img.style.display='none';}});</script></body></html>
