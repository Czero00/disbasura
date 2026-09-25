<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] !== 'leader') { header('Location: /disbasura/'); exit; }

$db    = get_db();
$uid   = $_SESSION['user_id'];
$sitio = $_SESSION['sitio'] ?? '';

$schedules = $db->prepare(
    "SELECT s.*, c.full_name AS collector_name FROM schedules s
     LEFT JOIN collectors c ON s.collector_id = c.id
     WHERE s.sitio = ? ORDER BY s.scheduled_at DESC LIMIT 30"
);
$schedules->execute([$sitio]);
$schedules = $schedules->fetchAll();

$disputeHasUserId = (bool)$db->query("SHOW COLUMNS FROM disputes LIKE 'user_id'")->fetch();
if ($disputeHasUserId) {
    $my_dispute_ids = $db->prepare("SELECT schedule_id FROM disputes WHERE user_id=?");
    $my_dispute_ids->execute([$uid]);
} else {
    // Legacy dispute tables cannot distinguish which resident filed a dispute.
    $my_dispute_ids = $db->query('SELECT schedule_id FROM disputes');
}
$my_dispute_ids = array_column($my_dispute_ids->fetchAll(), 'schedule_id');

$feedbackColumns = array_column($db->query('SHOW COLUMNS FROM feedback')->fetchAll(), 'Field');
$feedbackUserColumn = null;
foreach (['user_id', 'rated_by', 'resident_id'] as $candidate) {
    if (in_array($candidate, $feedbackColumns, true)) { $feedbackUserColumn = $candidate; break; }
}
if ($feedbackUserColumn) {
    $my_feedback_ids = $db->prepare("SELECT schedule_id FROM feedback WHERE `$feedbackUserColumn`=?");
    $my_feedback_ids->execute([$uid]);
} else {
    $my_feedback_ids = $db->query('SELECT schedule_id FROM feedback');
}
$my_feedback_ids = array_column($my_feedback_ids->fetchAll(), 'schedule_id');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Sitio Schedule — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"/><link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/></head>
<body style="background:var(--bg-page);padding:1.5rem">
<div style="max-width:800px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <a href="/disbasura/leader/dashboard.php" style="color:var(--text-mid);text-decoration:none">←</a>
    <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.3rem;font-weight:800;margin:0">📅 Sitio Schedule</h1>
  </div>
  <p style="font-size:.85rem;color:var(--text-mid);margin:0 0 1rem">Collections for <?= htmlspecialchars($sitio) ?> — rate completed pickups or file a dispute</p>

  <?php if ($schedules): foreach ($schedules as $s): ?>
  <div class="panel" style="margin-bottom:1rem">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div>
        <strong><?= htmlspecialchars($s['waste_type']) ?></strong>
        <div style="font-size:.82rem;color:var(--text-mid)"><?= fmt_date($s['scheduled_at']) ?> · <?= htmlspecialchars($s['collector_name'] ?? '—') ?></div>
      </div>
      <span class="badge <?= $s['status'] ?>"><?= htmlspecialchars($s['status']) ?></span>
    </div>
    <?php if ($s['status'] === 'completed'): ?>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem">
      <?php if (!in_array($s['id'], $my_feedback_ids)): ?>
        <button onclick="openFeedback(<?= $s['id'] ?>)" style="font-size:.73rem;background:#fff3e0;color:#f5a623;border:1px solid #fdd9a0;border-radius:20px;padding:.3rem .75rem;cursor:pointer;font-family:inherit;font-weight:700">⭐ Rate</button>
      <?php else: ?>
        <span style="font-size:.72rem;color:#f5a623;font-weight:700;background:#fff3e0;padding:.25rem .7rem;border-radius:20px;border:1px solid #fdd9a0">⭐ Rated</span>
      <?php endif; ?>
      <?php if (!in_array($s['id'], $my_dispute_ids)): ?>
        <a href="/disbasura/resident/dispute.php?sid=<?= $s['id'] ?>" style="font-size:.73rem;background:#fdecea;color:#c0392b;border:1px solid #f5c6c6;border-radius:20px;padding:.3rem .75rem;text-decoration:none;font-weight:700">⚠️ Dispute</a>
      <?php else: ?>
        <span style="font-size:.72rem;color:#c0392b;font-weight:700;background:#fdecea;padding:.25rem .7rem;border-radius:20px;border:1px solid #f5c6c6">⚠️ Disputed</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; else: ?><div class="empty-state" style="padding:3rem;text-align:center"><p>No schedules recorded yet for this sitio.</p></div><?php endif; ?>
</div>

<!-- Rate modal (same pattern/API as resident/dashboard.php's feedback modal) -->
<div id="feedbackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:400px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;margin-bottom:.5rem">⭐ Rate this Collection</h3>
    <p style="font-size:.83rem;color:#7aab8a;margin-bottom:1.25rem">How was the garbage collection service?</p>
    <input type="hidden" id="fb_schedule_id" value="">
    <div style="display:flex;gap:.25rem;justify-content:center;margin-bottom:1.25rem" id="starRow">
      <?php for ($i=1; $i<=5; $i++): ?>
      <button class="star-btn" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)">★</button>
      <?php endfor; ?>
    </div>
    <div style="text-align:center;font-size:.8rem;color:#7aab8a;margin-bottom:1rem" id="ratingLabel">Tap a star to rate</div>
    <textarea id="fb_comment" rows="3" placeholder='e.g. "Truck came on time!"'
      style="width:100%;border:1.5px solid #d4e6db;border-radius:8px;padding:.75rem 1rem;font-family:inherit;font-size:.88rem;resize:none;margin-bottom:.75rem"></textarea>
    <div style="display:flex;gap:.65rem">
      <button onclick="closeFeedback()" style="flex:1;padding:.75rem;border:1.5px solid #d4e6db;border-radius:8px;background:#fff;cursor:pointer;font-family:inherit">Cancel</button>
      <button onclick="submitFeedback()" style="flex:1;padding:.75rem;background:#1e6b3c;color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;cursor:pointer">Submit Feedback</button>
    </div>
  </div>
</div>
<style>.star-btn{background:none;border:none;font-size:1.9rem;color:#e0e0e0;cursor:pointer;transition:color .12s}.star-btn.active{color:#f5a623}</style>
<script>
let currentRating = 0;
const labels = ['','Poor 😞','Fair 😐','Good 🙂','Great 😊','Excellent! 🌟'];
function openFeedback(sid){
  document.getElementById('fb_schedule_id').value = sid;
  currentRating = 0;
  document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('ratingLabel').textContent = 'Tap a star to rate';
  document.getElementById('fb_comment').value = '';
  document.getElementById('feedbackModal').style.display = 'flex';
}
function closeFeedback(){ document.getElementById('feedbackModal').style.display = 'none'; }
function setRating(val){
  currentRating = val;
  document.querySelectorAll('.star-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.val) <= val));
  document.getElementById('ratingLabel').textContent = labels[val];
}
function submitFeedback(){
  if (!currentRating){ alert('Please select a star rating.'); return; }
  const sid = document.getElementById('fb_schedule_id').value;
  const comment = document.getElementById('fb_comment').value;
  fetch('/disbasura/api/feedback.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({schedule_id: parseInt(sid), rating: currentRating, comment})
  }).then(r => r.json()).then(d => {
    if (d.ok) { closeFeedback(); location.reload(); }
    else alert(d.error || 'Failed to submit.');
  }).catch(() => alert('Network error.'));
}
document.getElementById('feedbackModal').addEventListener('click', function(e){ if (e.target === this) closeFeedback(); });
</script>
</body></html>
