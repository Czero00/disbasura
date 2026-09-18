<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] !== 'leader') { header('Location: /disbasura/'); exit; }
$db=$get_db=get_db(); $sitio=$_SESSION['sitio']??'';
$residents=$db->prepare("SELECT * FROM users WHERE sitio=? AND role IN ('resident','leader') ORDER BY full_name"); $residents->execute([$sitio]); $residents=$residents->fetchAll();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $res_id=(int)$_POST['resident_id'];
    $db->prepare("INSERT INTO requests (resident_id,submitted_by,sitio,location,waste_type,preferred_date,note) VALUES (?,?,?,?,?,?,?)")
       ->execute([$res_id,$_SESSION['user_id'],$sitio,$_POST['location']??'',$_POST['waste_type'],$_POST['preferred_date'],$_POST['note']??'']);
    notify_user($db,$res_id,"📋 Your Sitio Leader has submitted a pickup request on your behalf.");
    header('Location: /disbasura/leader/dashboard.php'); exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Submit Request — DisBasura</title><link rel="stylesheet" href="/disbasura/assets/css/base.css"/><link rel="stylesheet" href="/disbasura/assets/css/auth.css"/></head>
<body><div class="auth-bg" style="padding:2rem">
  <div class="auth-card" style="max-width:520px">
    <h2>Submit Request for Resident</h2>
    <form method="POST">
      <div class="field"><label>Resident *</label><select name="resident_id" required><option value="">Select resident</option><?php foreach($residents as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Location</label><input type="text" name="location" placeholder="Address or landmark"/></div>
      <div class="field"><label>Waste Type *</label><select name="waste_type" required><option>Biodegradable</option><option>Non-Biodegradable</option><option>Recyclable</option><option>Mixed</option></select></div>
      <div class="field"><label>Preferred Date *</label><input type="datetime-local" name="preferred_date" required/></div>
      <div class="field"><label>Note</label><textarea name="note" rows="3" style="width:100%;border:1.5px solid var(--border);border-radius:var(--radius-md);padding:.8rem 1rem;font-family:inherit;font-size:.92rem"></textarea></div>
      <button type="submit" class="btn-primary">Submit Request</button>
    </form>
    <div class="auth-footer"><a href="/disbasura/leader/dashboard.php">← Back</a></div>
  </div>
</div></body></html>
