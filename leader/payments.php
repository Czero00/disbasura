<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/payment_helpers.php';
if (($_SESSION['role'] ?? '') !== 'leader') { header('Location: /disbasura/'); exit; }
$db = get_db();
ensure_pickup_payment_tables($db);
$sitio = (string)($_SESSION['sitio'] ?? '');
$uid = (int)$_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_payment'])) {
    $pid = (int)($_POST['payment_id'] ?? 0);
    $decision = ($_POST['decision'] ?? '') === 'paid' ? 'paid' : 'rejected';
    $stmt = $db->prepare("SELECT p.*,r.sitio FROM pickup_payments p JOIN requests r ON r.id=p.request_id WHERE p.id=? AND r.sitio=? AND p.status='pending'");
    $stmt->execute([$pid, $sitio]);
    $payment = $stmt->fetch();
    if ($payment) {
        $db->prepare("UPDATE pickup_payments SET status=?,admin_note=?,reviewed_by=?,reviewed_by_role='leader',reviewed_at=NOW() WHERE id=? AND status='pending'")
            ->execute([$decision, trim($_POST['leader_note'] ?? ''), $uid, $pid]);
        $note = trim($_POST['leader_note'] ?? '');
        $text = $decision === 'paid'
            ? 'Your special pickup payment was verified by your Sitio Leader. Admin can now continue processing your request.'
            : 'Your special pickup payment was not verified. Please correct the payment and submit it again.'.($note !== '' ? ' Note: '.$note : '');
        notify_user($db, (int)$payment['resident_id'], $text);
        log_activity($uid, $decision === 'paid' ? 'Verified Pickup Payment' : 'Rejected Pickup Payment', 'Payment #'.$pid.' for request #'.$payment['request_id'].' in '.$sitio, 'leader');
        $message = $decision === 'paid' ? 'Payment verified and resident notified.' : 'Payment rejected and resident notified.';
    } else {
        $message = 'That payment is no longer pending or is outside your sitio.';
    }
}

$stmt = $db->prepare("SELECT p.*,u.full_name,r.waste_type,r.preferred_date FROM pickup_payments p JOIN users u ON u.id=p.resident_id JOIN requests r ON r.id=p.request_id WHERE r.sitio=? ORDER BY (p.status='pending') DESC,p.created_at DESC");
$stmt->execute([$sitio]); $payments = $stmt->fetchAll();
$pending = count(array_filter($payments, static fn($p) => $p['status'] === 'pending'));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sitio Payments — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"><link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"><style>
body{background:var(--bg-page);padding:1.5rem}.wrap{max-width:950px;margin:auto}.head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem}.head h1{font-size:1.4rem;margin:0}.back{color:var(--text-mid);text-decoration:none}.notice{padding:.8rem 1rem;margin-bottom:1rem;border-radius:12px;background:#e9f7ef;color:#176b3a}.card{margin-bottom:1rem;padding:1.1rem!important}.top{display:flex;justify-content:space-between;gap:1rem}.muted{color:var(--text-mid);font-size:.83rem;line-height:1.6}.badge{white-space:nowrap}.actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:.8rem}.actions input{flex:1;min-width:200px;padding:.6rem;border:1px solid var(--border);border-radius:9px;font:inherit}.empty{text-align:center;padding:2.5rem;color:var(--text-mid)}
</style></head><body><main class="wrap">
<div class="head"><a class="back" href="/disbasura/leader/dashboard.php">← Dashboard</a><h1>Payment Review · <?= e($sitio) ?></h1><a class="btn-approve" style="margin-left:auto;text-decoration:none" href="/disbasura/leader/requests.php">View Requests</a></div>
<?php if($message): ?><div class="notice" role="status"><?= e($message) ?></div><?php endif; ?>
<p class="muted">You can review payments only for residents in your assigned sitio. Pending submissions: <strong><?= $pending ?></strong>.</p>
<?php if(!$payments): ?><div class="panel empty">No payments submitted for this sitio yet.</div><?php else: foreach($payments as $p): ?><article class="panel card"><div class="top"><div><strong><?= e($p['full_name']) ?></strong> · Request #<?= (int)$p['request_id'] ?><div class="muted"><?= e($p['waste_type']) ?> · <?= e(fmt_date($p['preferred_date'])) ?> · <strong>₱<?= number_format((float)$p['amount'],2) ?></strong><br><?= $p['method']==='qrph'?'QR Ph / e-wallet':'Cash at barangay office' ?><?= $p['reference_number'] ? ' · Reference: '.e($p['reference_number']) : '' ?><br>Submitted <?= e($p['created_at']) ?></div>
<?php if($p['proof_photo']): ?><a href="/disbasura/uploads/<?= e($p['proof_photo']) ?>" target="_blank" rel="noopener" style="color:var(--green-main);font-weight:700">View payment proof</a><?php endif; ?>
<?php if($p['admin_note']): ?><div class="muted">Previous review note: <?= e($p['admin_note']) ?></div><?php endif; ?></div><span class="badge <?= e($p['status']) ?>"><?= strtoupper(e($p['status'])) ?></span></div>
<?php if($p['status']==='pending'): ?><form method="POST" class="actions"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="review_payment" value="1"><input name="leader_note" maxlength="500" placeholder="Optional note to resident"><button class="btn-approve" name="decision" value="paid">Mark Paid</button><button class="btn-reject" name="decision" value="rejected">Reject</button></form><?php endif; ?></article><?php endforeach; endif; ?>
</main></body></html>
