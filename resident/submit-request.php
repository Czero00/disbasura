<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sitios = get_sitios();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();
    $db->prepare("INSERT INTO requests (resident_id,submitted_by,sitio,location,waste_type,preferred_date,note) VALUES (?,?,?,?,?,?,?)")
       ->execute([$_SESSION['user_id'],$_SESSION['user_id'],$_POST['sitio'],$_POST['location']??'',$_POST['waste_type'],$_POST['preferred_date'],$_POST['note']??'']);
    header('Location: /disbasura/resident/dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Submit Request — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/auth.css"/>
</head>
<body>
<div class="auth-bg" style="padding:2rem">
  <div class="auth-card" style="max-width:520px">
    <h2>Request Pickup</h2>
    <p class="sub">Submit a special garbage pickup request</p>
    <form method="POST">
      <div class="field"><label>Sitio *</label><select name="sitio" required><option value="">Select sitio</option><?php foreach($sitios as $s): ?><option <?= ($_SESSION['sitio']??'')===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Location / Address</label><input type="text" name="location" placeholder="Specific address or landmark"/></div>
      <div class="field"><label>Waste Type *</label><select name="waste_type" required><option>Biodegradable</option><option>Non-Biodegradable</option><option>Recyclable</option><option>Mixed</option></select></div>
      <div class="field"><label>Preferred Date *</label><input type="datetime-local" name="preferred_date" required/></div>
      <div class="field"><label>Note</label><textarea name="note" rows="3" placeholder="Any special instructions…" style="width:100%;border:1.5px solid var(--border);border-radius:var(--radius-md);padding:.8rem 1rem;font-family:inherit;font-size:.92rem"></textarea></div>
      <button type="submit" class="btn-primary">Submit Request</button>
    </form>
    <div class="auth-footer"><a href="/disbasura/resident/dashboard.php">← Back to dashboard</a></div>
  </div>
</div>
</body>
</html>
