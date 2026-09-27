<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sitios = get_sitios();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sitio = trim($_POST['sitio'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $wasteType = trim($_POST['waste_type'] ?? '');
    $preferredDate = trim($_POST['preferred_date'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $allowedWaste = ['Biodegradable', 'Non-Biodegradable', 'Recyclable / Bulk', 'Hazardous'];
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $preferredDate);
    if (!in_array($sitio, $sitios, true) || !in_array($wasteType, $allowedWaste, true) || !$location || !$date || $date <= new DateTime()) {
        $error = 'Please check the required fields and choose a future pickup date and time.';
    } else {
        $db = get_db();
        $db->prepare("INSERT INTO requests (resident_id,submitted_by,sitio,location,waste_type,preferred_date,note) VALUES (?,?,?,?,?,?,?)")
           ->execute([$_SESSION['user_id'],$_SESSION['user_id'],$sitio,$location,$wasteType,$date->format('Y-m-d H:i:s'),$note]);
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
}
$selectedSitio = $_POST['sitio'] ?? '';
$selectedWaste = $_POST['waste_type'] ?? 'Biodegradable';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Request Special Pickup — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<style>
:root{--pickup-green:#009b70;--pickup-dark:#073f32;--pickup-ink:#172133;--pickup-muted:#71819a;--pickup-line:#dfe7ef}input,select,textarea,button{font-family:inherit}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(ellipse at 20% 20%,#116246 0%,transparent 46%),linear-gradient(125deg,#07533e,#032c29 75%);color:var(--pickup-ink);font-family:Inter,'Plus Jakarta Sans',system-ui,-apple-system,'Segoe UI',sans-serif}
.pickup-shell{width:min(100% - 32px,720px);margin:0 auto;padding:40px 0 64px}.pickup-top{display:flex;align-items:center;justify-content:space-between;margin:0 5px 20px;color:#fff}.pickup-brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff}.pickup-mark{width:50px;height:50px;display:grid;place-items:center;border:1px solid #18a77e;border-radius:17px;background:rgba(8,167,122,.18);color:#35d5a2;font-size:25px}.pickup-brand strong{display:block;font-size:20px;letter-spacing:-.6px}.pickup-brand small{display:block;margin-top:2px;color:#70e5bf;font-size:10px;font-weight:800;letter-spacing:.6px}.back-link{padding:11px 16px;border-radius:14px;background:rgba(255,255,255,.1);color:#d5f3e8;text-decoration:none;font-size:14px;font-weight:700;transition:background .2s}.back-link:hover{background:rgba(255,255,255,.18)}
.pickup-card{padding:40px;border:1px solid rgba(255,255,255,.7);border-radius:30px;background:#f5f8f7;box-shadow:0 28px 90px rgba(0,0,0,.22)}.eyebrow-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:17px}.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border-radius:99px;background:#d8f8eb;color:#087350;font-size:12px;font-weight:850;letter-spacing:.45px}.verified{color:#8b9bb0;font-size:13px;font-weight:700;white-space:nowrap}.verified b{color:#009b70}.pickup-card h1{margin:0;color:#141e31;font-size:clamp(28px,5vw,38px);line-height:1.15;letter-spacing:-1.5px}.intro{margin:7px 0 29px;color:var(--pickup-muted);font-size:15px;line-height:1.6}.alert{margin:0 0 20px;padding:12px 14px;border-radius:12px;background:#fff0ee;color:#a53c32;font-size:14px}
.field{margin-top:21px}.field-label{display:block;margin-bottom:9px;color:#3b4a60;font-size:12px;font-weight:850;letter-spacing:.55px;text-transform:uppercase}.required{color:#e34852}.input-wrap{position:relative}.field-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:17px;pointer-events:none}.control{width:100%;height:59px;padding:0 17px 0 50px;border:1px solid var(--pickup-line);border-radius:19px;background:#fbfcfd;color:#202b3c;font:600 15px inherit;outline:none;transition:border-color .18s,box-shadow .18s}.control:focus,.notes:focus{border-color:#08a77a;box-shadow:0 0 0 4px rgba(0,155,112,.1)}.control::placeholder,.notes::placeholder{color:#98a8bf}.waste-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.waste-option{position:relative;cursor:pointer}.waste-option input{position:absolute;opacity:0;pointer-events:none}.waste-tile{height:84px;display:flex;align-items:center;gap:13px;padding:12px 15px;border:2px solid var(--pickup-line);border-radius:19px;background:#fbfcfd;transition:.18s}.waste-option:hover .waste-tile{border-color:#98ceb9;transform:translateY(-1px)}.waste-option input:checked+.waste-tile{border-color:#00a478;background:#effbf6}.waste-option input:focus-visible+.waste-tile{outline:3px solid rgba(0,155,112,.25)}.waste-icon{width:44px;height:44px;display:grid;place-items:center;flex:none;border-radius:14px;background:#e6ebf2;color:#3b536c;font-size:20px}.waste-option input:checked+.waste-tile .waste-icon{background:#08a77a;color:#fff}.waste-copy strong{display:block;font-size:13px;color:#182235}.waste-copy small{display:block;margin-top:4px;color:#7e8da2;font-size:11px}.notes{display:block;width:100%;min-height:104px;padding:16px;border:1px solid var(--pickup-line);border-radius:19px;background:#fbfcfd;color:#202b3c;font:500 14px inherit;resize:vertical;outline:none}.hint{margin:7px 0 0;color:#8594a8;font-size:12px}.form-actions{display:flex;align-items:center;gap:12px;margin-top:26px}.submit-btn{flex:1;min-height:54px;border:0;border-radius:16px;background:linear-gradient(100deg,#079b70,#07875f);color:#fff;font:800 15px inherit;cursor:pointer;box-shadow:0 10px 22px rgba(0,139,99,.2);transition:transform .18s,filter .18s}.submit-btn:hover{transform:translateY(-1px);filter:brightness(1.05)}.cancel-link{padding:17px 18px;border:1px solid var(--pickup-line);border-radius:16px;background:#fff;color:#53647a;text-decoration:none;font-size:14px;font-weight:750;white-space:nowrap}.fine-print{margin:15px 0 0;text-align:center;color:#8997a9;font-size:11px}
@media(max-width:600px){.pickup-shell{width:min(100% - 22px,720px);padding:18px 0 34px}.pickup-top{margin:0 2px 14px}.pickup-mark{width:43px;height:43px;border-radius:14px}.pickup-brand strong{font-size:17px}.pickup-brand small{font-size:8px}.back-link{padding:10px 12px;font-size:12px}.pickup-card{padding:25px 19px;border-radius:24px}.eyebrow-row{align-items:flex-start}.verified{font-size:11px}.intro{font-size:14px}.waste-grid{gap:9px}.waste-tile{height:78px;padding:9px;gap:9px;border-radius:15px}.waste-icon{width:37px;height:37px;border-radius:12px}.waste-copy strong{font-size:11px}.waste-copy small{font-size:10px}.form-actions{flex-direction:column}.submit-btn,.cancel-link{width:100%;text-align:center}.cancel-link{padding:15px}}
.field-icon{left:12px;font-size:15px}.control#sitio{padding-left:58px;color:#202b3c}.control#sitio:invalid{color:#98a8bf}#preferred_date.control{padding-left:62px}
@media(max-width:380px){.waste-grid{grid-template-columns:1fr}.waste-tile{height:68px}.verified{display:none}}
</style>
</head>
<body>
<div class="pickup-shell">
  <header class="pickup-top">
    <a class="pickup-brand" href="/disbasura/resident/dashboard.php"><span class="pickup-mark">🍃</span><span><strong>DisBasura</strong><small>GARBAGE MANAGEMENT SYSTEM</small></span></a>
    <a class="back-link" href="/disbasura/resident/dashboard.php">← Dashboard</a>
  </header>
  <main class="pickup-card">
    <div class="eyebrow-row"><span class="eyebrow">🚛 &nbsp;SPECIAL COLLECTION</span><span class="verified"><b>◈</b> Barangay Verified</span></div>
    <h1>Request Special Pickup</h1>
    <p class="intro">Schedule a targeted garbage collection for bulk or non-regular waste items.</p>
    <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="POST" id="pickupForm">
      <div class="field"><label class="field-label" for="sitio">Sitio / Zone location <span class="required">*</span></label><div class="input-wrap"><span class="field-icon">📍</span><select class="control" id="sitio" name="sitio" required><option value="" <?= $selectedSitio===''?'selected':'' ?>>Select Your Sitio</option><?php foreach($sitios as $s): ?><option value="<?= e($s) ?>" <?= $selectedSitio===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div></div>
      <div class="field"><label class="field-label" for="location">Specific address / landmark <span class="required">*</span></label><div class="input-wrap"><span class="field-icon">⌂</span><input class="control" id="location" type="text" name="location" value="<?= e($_POST['location'] ?? '') ?>" placeholder="e.g. House #12, near basketball court" maxlength="500" required/></div></div>
      <fieldset class="field" style="padding:0;border:0;margin-left:0;margin-right:0"><legend class="field-label">Waste category <span class="required">*</span></legend><div class="waste-grid">
        <?php foreach ([['Biodegradable','Organic / Food waste','🍃'],['Non-Biodegradable','Plastics, Wrappers','▤'],['Recyclable / Bulk','Bottles, Furniture','♻'],['Hazardous','Electronics, Chemicals','⚠']] as [$type,$desc,$icon]): ?>
        <label class="waste-option"><input type="radio" name="waste_type" value="<?= e($type) ?>" <?= $selectedWaste===$type?'checked':'' ?> required/><span class="waste-tile"><span class="waste-icon"><?= $icon ?></span><span class="waste-copy"><strong><?= e($type) ?></strong><small><?= e($desc) ?></small></span></span></label>
        <?php endforeach; ?>
      </div></fieldset>
      <div class="field"><label class="field-label" for="preferred_date">Preferred pickup date &amp; time <span class="required">*</span></label><div class="input-wrap"><span class="field-icon">▣</span><input class="control" id="preferred_date" type="datetime-local" name="preferred_date" value="<?= e($_POST['preferred_date'] ?? '') ?>" required/></div></div>
      <div class="field"><label class="field-label" for="note">Special instructions / notes <span style="color:#98a8bf;font-weight:600">(optional)</span></label><textarea class="notes" id="note" name="note" maxlength="2000" placeholder="e.g. Please call upon arrival. Garbage bags are near the front gate."><?= e($_POST['note'] ?? '') ?></textarea><p class="hint">Add useful details to help the collection team find and handle your items.</p></div>
      <div class="form-actions"><button class="submit-btn" type="submit">Submit Pickup Request &nbsp;→</button><a class="cancel-link" href="/disbasura/resident/dashboard.php">Cancel</a></div>
      <p class="fine-print">Your request will be sent to the barangay for review.</p>
    </form>
  </main>
</div>
<script>
(()=>{const field=document.getElementById('preferred_date');const now=new Date();now.setMinutes(now.getMinutes()-now.getTimezoneOffset());field.min=now.toISOString().slice(0,16);})();
</script>
</body>
</html>
