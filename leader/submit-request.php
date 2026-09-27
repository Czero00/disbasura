<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if (($_SESSION['role'] ?? '') !== 'leader') { header('Location: /disbasura/'); exit; }
$db = get_db();
$sitio = $_SESSION['sitio'] ?? '';
$residentStmt = $db->prepare("SELECT id,full_name FROM users WHERE sitio=? AND role IN ('resident','leader') ORDER BY full_name");
$residentStmt->execute([$sitio]);
$residents = $residentStmt->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resId = (int)($_POST['resident_id'] ?? 0);
    $residentIds = array_map('intval', array_column($residents, 'id'));
    $location = trim($_POST['location'] ?? '');
    $wasteType = trim($_POST['waste_type'] ?? '');
    $preferred = trim($_POST['preferred_date'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $allowedWaste = ['Biodegradable', 'Non-Biodegradable', 'Recyclable / Bulk', 'Hazardous'];
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $preferred);
    if (!in_array($resId, $residentIds, true) || !in_array($wasteType, $allowedWaste, true) || !$location || !$date || $date <= new DateTime()) {
        $error = 'Please choose a resident, complete the required fields, and select a future pickup time.';
    } else {
        $db->prepare('INSERT INTO requests (resident_id,submitted_by,sitio,location,waste_type,preferred_date,note) VALUES (?,?,?,?,?,?,?)')
           ->execute([$resId,(int)$_SESSION['user_id'],$sitio,$location,$wasteType,$date->format('Y-m-d H:i:s'),$note]);
        notify_user($db,$resId,'Your Sitio Leader has submitted a pickup request on your behalf.');
        header('Location: /disbasura/leader/dashboard.php'); exit;
    }
}
$selectedResident = (int)($_POST['resident_id'] ?? 0);
$selectedWaste = $_POST['waste_type'] ?? 'Biodegradable';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Pickup Request for Resident — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"/><link rel="stylesheet" href="/disbasura/assets/css/resident-workflows.css"/>
<style>.request-waste-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.request-waste-choice{position:relative;cursor:pointer}.request-waste-choice input{position:absolute;opacity:0}.request-waste-tile{min-height:70px;display:flex;align-items:center;gap:11px;padding:10px 12px;border:2px solid #dfe7ef;border-radius:15px;background:#fff;transition:.18s}.request-waste-choice input:checked+.request-waste-tile{border-color:#00a478;background:#effbf6}.request-waste-choice input:focus-visible+.request-waste-tile{outline:3px solid rgba(0,155,112,.2)}.request-waste-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:#e7edf2;font-size:18px}.request-waste-choice input:checked+.request-waste-tile .request-waste-icon{background:#08a77a;color:white}.request-waste-tile strong{font-size:12px;color:#182235}@media(max-width:420px){.request-waste-grid{grid-template-columns:1fr}}</style>
</head><body class="workflow-page"><div class="workflow-shell">
  <header class="workflow-top"><a class="workflow-brand" href="/disbasura/leader/dashboard.php"><span class="workflow-brand-mark">🍃</span><span><strong>DisBasura</strong><small>GARBAGE MANAGEMENT SYSTEM</small></span></a><a class="workflow-back" href="/disbasura/leader/dashboard.php">← Dashboard</a></header>
  <main class="workflow-card">
    <span class="workflow-kicker">🚛 &nbsp;Resident service</span>
    <h1 class="workflow-title">Request a Pickup</h1>
    <p class="workflow-subtitle">Submit a special collection request on behalf of a resident in your sitio.</p>
    <?php if ($error): ?><div class="workflow-alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$residents): ?><div class="workflow-empty">There are no resident accounts registered in <?= e($sitio ?: 'your sitio') ?> yet. Add a resident account before submitting a request.</div><?php else: ?>
    <form method="POST">
      <div class="workflow-context"><span class="workflow-context-icon">📍</span><span><small>Requesting for sitio</small><strong><?= e($sitio ?: 'Sitio not set') ?></strong></span></div>
      <div class="workflow-field"><label class="workflow-label" for="resident_id">Resident <span class="workflow-required">*</span></label><select class="workflow-control" id="resident_id" name="resident_id" required><option value="">Select a resident</option><?php foreach($residents as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $selectedResident===(int)$r['id']?'selected':'' ?>><?= e($r['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="workflow-field"><label class="workflow-label" for="location">Specific address / landmark <span class="workflow-required">*</span></label><input class="workflow-control" id="location" name="location" maxlength="500" required value="<?= e($_POST['location'] ?? '') ?>" placeholder="House number, street, or nearby landmark"/></div>
      <fieldset class="workflow-field" style="padding:0;border:0;margin-left:0;margin-right:0"><legend class="workflow-label">Waste category <span class="workflow-required">*</span></legend><div class="request-waste-grid">
        <?php foreach ([['Biodegradable','🍃'],['Non-Biodegradable','▤'],['Recyclable / Bulk','♻'],['Hazardous','⚠']] as [$type,$icon]): ?><label class="request-waste-choice"><input type="radio" name="waste_type" value="<?= e($type) ?>" <?= $selectedWaste===$type?'checked':'' ?> required/><span class="request-waste-tile"><span class="request-waste-icon"><?= $icon ?></span><strong><?= e($type) ?></strong></span></label><?php endforeach; ?>
      </div></fieldset>
      <div class="workflow-field"><label class="workflow-label" for="preferred_date">Preferred pickup date &amp; time <span class="workflow-required">*</span></label><input class="workflow-control" id="preferred_date" type="datetime-local" name="preferred_date" value="<?= e($_POST['preferred_date'] ?? '') ?>" required/></div>
      <div class="workflow-field"><label class="workflow-label" for="note">Instructions / notes <span style="color:#91a0b2;font-weight:600">(optional)</span></label><textarea class="workflow-control workflow-textarea" id="note" name="note" maxlength="2000" placeholder="Add any useful collection details."><?= e($_POST['note'] ?? '') ?></textarea></div>
      <div class="workflow-actions"><button class="workflow-submit" type="submit">Submit Request for Resident &nbsp;→</button><a class="workflow-cancel" href="/disbasura/leader/dashboard.php">Cancel</a></div>
    </form>
    <?php endif; ?><p class="workflow-footer">The resident will be notified when you submit this request.</p>
  </main>
</div><script>(()=>{const field=document.getElementById('preferred_date');if(field){const now=new Date();now.setMinutes(now.getMinutes()-now.getTimezoneOffset());field.min=now.toISOString().slice(0,16);}})();</script></body></html>
