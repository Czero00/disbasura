<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sid = (int)($_GET['sid'] ?? 0);
$db  = get_db();
$uid = $_SESSION['user_id'];
$disputeHasUserId = (bool)$db->query("SHOW COLUMNS FROM disputes LIKE 'user_id'")->fetch();
$backUrl = $_SESSION['role'] === 'leader' ? '/disbasura/leader/schedules.php' : '/disbasura/resident/dashboard.php';
$sched = $db->query("SELECT * FROM schedules WHERE id=$sid")->fetch();
$in_sitio = $sched && $sched['sitio'] === ($_SESSION['sitio']??'');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sched && $in_sitio) {
    $existingQuery = $disputeHasUserId
        ? $db->prepare('SELECT id FROM disputes WHERE schedule_id=? AND user_id=?')
        : $db->prepare('SELECT id FROM disputes WHERE schedule_id=?');
    $existingQuery->execute($disputeHasUserId ? [$sid, $uid] : [$sid]);
    $existing = $existingQuery->fetch();
    if (!$existing) {
        $fname = null;
        if (isset($_FILES['dispute_photo'])) $fname = save_upload($_FILES['dispute_photo'],"dispute_{$sid}_{$uid}");
        $note = $_POST['note'] ?? '';
        if ($disputeHasUserId) {
            $db->prepare("INSERT INTO disputes (schedule_id,user_id,proof_photo,description) VALUES (?,?,?,?)")->execute([$sid,$uid,$fname,$note]);
        } else {
            $db->prepare("INSERT INTO disputes (schedule_id,proof_photo,description) VALUES (?,?,?)")->execute([$sid,$fname,$note]);
        }
        $db->prepare("UPDATE schedules SET status='disputed' WHERE id=?")->execute([$sid]);
        notify_all_admins($db,"⚠️ Dispute filed by {$_SESSION['full_name']} ({$_SESSION['role']}) for {$sched['sitio']} schedule. Review needed.");
    }
    header('Location: '.$backUrl); exit;
}
$existingQuery = $disputeHasUserId
    ? $db->prepare('SELECT * FROM disputes WHERE schedule_id=? AND user_id=?')
    : $db->prepare('SELECT * FROM disputes WHERE schedule_id=?');
$existingQuery->execute($disputeHasUserId ? [$sid, $uid] : [$sid]);
$existing_dispute = $existingQuery->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>File Dispute — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg" style="padding:2rem">
  <div class="auth-card" style="max-width:520px">
    <h2>⚠️ File a Dispute</h2>
    <?php if($existing_dispute): ?>
    <div style="background:#fff3e0;border:1px solid #fdd9a0;border-radius:8px;padding:.75rem 1rem;font-size:.87rem;color:var(--orange)">You have already filed a dispute for this schedule. It is pending admin review.</div>
    <?php elseif($sched&&$in_sitio): ?>
    <p class="sub">Schedule: <?= htmlspecialchars($sched['sitio']) ?> — <?= fmt_date($sched['scheduled_at']) ?></p>
    <form method="POST" enctype="multipart/form-data">
      <div class="field"><label>Photo Evidence (optional)</label><input type="file" name="dispute_photo" accept="image/*"/></div>
      <div class="field"><label>Note</label><textarea name="note" rows="3" placeholder="Describe the issue…" style="width:100%;border:1.5px solid var(--border);border-radius:var(--radius-md);padding:.8rem 1rem;font-family:inherit;font-size:.92rem"></textarea></div>
      <button type="submit" class="btn-primary">Submit Dispute</button>
    </form>
    <?php else: ?><p>Invalid schedule or you are not in this sitio.</p><?php endif; ?>
    <div class="auth-footer"><a href="<?= $backUrl ?>">← Back</a></div>
  </div>
</div>
</body>
</html>
