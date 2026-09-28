<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/payment_helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
ensure_pickup_payment_tables($db);
$message = '';
$setupNotice = (string)($_SESSION['payment_setup_notice'] ?? '');
unset($_SESSION['payment_setup_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $fee = max(0, (float)($_POST['fee'] ?? 0));
        $qr = isset($_POST['allow_qrph']) ? 1 : 0;
        $cash = isset($_POST['allow_cash']) ? 1 : 0;
        $settings = get_pickup_payment_settings($db);
        $qrImage = null;
        if (!empty($_FILES['qr_image']['name']) && ($_FILES['qr_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $qrImage = save_upload($_FILES['qr_image'], 'payment_qr');
        }
        $db->prepare('UPDATE pickup_payment_settings SET enabled=?,fee=?,allow_qrph=?,allow_cash=?,merchant_name=?,instructions=?,qr_image=? WHERE id=1')
            ->execute([isset($_POST['enabled']) ? 1 : 0, $fee, $qr, $cash, trim($_POST['merchant_name'] ?? ''), trim($_POST['instructions'] ?? ''), $qrImage]);
        $_SESSION['payment_settings_saved'] = 1;
        header('Location: /disbasura/admin/payments.php'); exit;
    }
}
$settings = get_pickup_payment_settings($db);
$paymentsActive = !empty($settings['enabled']) && (float)$settings['fee'] > 0 && (!empty($settings['allow_qrph']) || !empty($settings['allow_cash']));
$settingsSaved = !empty($_SESSION['payment_settings_saved']);
unset($_SESSION['payment_settings_saved']);
$unread = get_unread_admin_count($_SESSION['admin_id']);
render_admin_header('payments', $unread, 'Payments — DisBasura Admin');
?>
<style>
.sp-head{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem}.sp-head h1{margin:0}.sp-head p{margin:.35rem 0 0;color:var(--text-mid)}.sp-card{padding:1.35rem!important;border:1px solid var(--border-light)!important}.sp-settings-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}.sp-settings-head h2{margin:0;font-size:1.05rem}.sp-settings-head p{margin:.35rem 0 0;color:var(--text-mid);font-size:.83rem}.sp-state{padding:.38rem .72rem;border-radius:99px;background:<?= $paymentsActive?'#e7f6ed':'#f0f2f4' ?>;color:<?= $paymentsActive?'#18804b':'#687584' ?>;font-size:.73rem;font-weight:800;white-space:nowrap}.sp-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin:1.1rem 0}.sp-stat{padding:.8rem .95rem;border:1px solid var(--border-light);border-radius:13px;background:var(--bg-page)}.sp-stat small{display:block;margin-bottom:.25rem;color:var(--text-light);font-size:.67rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.sp-stat strong{color:var(--text-light);font-size:.86rem;font-weight:600;opacity:.72}.sp-editor{border-top:1px solid var(--border-light);padding-top:.85rem}.sp-editor summary{display:flex;justify-content:space-between;cursor:pointer;list-style:none;color:var(--green-main);font-size:.83rem;font-weight:800}.sp-editor summary::-webkit-details-marker{display:none}.sp-editor summary:after{content:'＋'}.sp-editor[open] summary:after{content:'−'}.sp-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem}.sp-field{display:grid;gap:.4rem;color:var(--text-mid);font-size:.77rem;font-weight:700}.sp-field input:not([type=checkbox]):not([type=file]),.sp-field textarea{width:100%;box-sizing:border-box;padding:.7rem .8rem;border:1px solid var(--border);border-radius:10px;background:var(--bg-card);color:var(--text-dark);font:inherit;font-weight:500;outline:none}.sp-field input::placeholder,.sp-field textarea::placeholder{color:var(--text-light);opacity:.58}.sp-field input:focus,.sp-field textarea:focus{border-color:var(--green-main);box-shadow:0 0 0 3px rgba(45,134,83,.12)}.sp-field textarea{resize:vertical}.sp-checks{display:flex;gap:1rem;flex-wrap:wrap}.sp-check{display:flex;align-items:center;gap:.45rem;color:var(--text-dark);font-weight:700}.sp-form-actions{grid-column:1/-1;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.sp-saved{margin:0 0 1rem;padding:.75rem .95rem;border-radius:12px;background:#e9f7ef;color:#176b3a;font-size:.84rem;font-weight:700}.sp-sub-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin:1.5rem 0 .8rem}.sp-sub-head h2{margin:0;font-size:1.15rem}.sp-tabs{display:flex;gap:.4rem;flex-wrap:wrap}.sp-tab{padding:.4rem .7rem;border:1px solid var(--border-light);border-radius:99px;color:var(--text-mid);text-decoration:none;font-size:.73rem;font-weight:750}.sp-tab.active{background:var(--green-main);border-color:var(--green-main);color:#fff}.sp-payment{margin-bottom:.75rem;padding:1.05rem 1.15rem!important;border:1px solid var(--border-light)!important}.sp-payment-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.sp-resident{color:var(--text-dark);font-weight:800}.sp-muted{color:var(--text-mid);font-size:.79rem;line-height:1.55}.sp-badge{padding:.34rem .68rem;border-radius:99px;font-size:.68rem;font-weight:850;text-transform:uppercase;white-space:nowrap}.sp-badge.pending{background:#fff4d9;color:#916300}.sp-badge.paid{background:#e4f5eb;color:#187344}.sp-badge.rejected{background:#ffebea;color:#ae3c34}.sp-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.75rem}.sp-actions input{flex:1;min-width:200px;padding:.55rem .7rem;border:1px solid var(--border);border-radius:9px;background:var(--bg-card);color:var(--text-dark);font:inherit;font-size:.8rem}.sp-empty{padding:2rem 1rem;text-align:center;color:var(--text-mid)}@media(max-width:680px){.sp-overview{grid-template-columns:1fr}.sp-form{grid-template-columns:1fr}.sp-settings-head{align-items:flex-start}.sp-payment-top{flex-direction:column-reverse;gap:.5rem}.sp-sub-head{align-items:flex-start;flex-direction:column}.sp-card{padding:1rem!important}}
</style>
<div class="sp-head"><div><h1>Special Pickup Payments</h1><p>Configure optional special-pickup fees. Sitio Leaders review resident payments for their own sitio.</p></div></div>
<?php if ($settingsSaved): ?><div class="sp-saved" role="status">✓ Settings saved. The editor is closed. Select “Edit payment settings” if you need to make changes.</div><?php endif; ?>
<?php if ($setupNotice !== ''): ?><div class="sp-saved" role="status"><?= e($setupNotice) ?> After saving, return to Requests to approve the pickup.</div><?php endif; ?>
<?php if ($message): ?><div class="sp-saved" role="status"><?= e($message) ?></div><?php endif; ?>
<section class="panel sp-card">
  <div class="sp-settings-head"><div><h2>Payment setup</h2><p>Only applies to special pickup requests. Regular collection schedules remain free.</p></div><span class="sp-state"><?= $paymentsActive?'● Enabled':'○ Disabled' ?></span></div>
  <div class="sp-overview"><div class="sp-stat"><small>Special pickup fee</small><strong>Enter a fee amount</strong></div><div class="sp-stat"><small>Accepted payment methods</small><strong>Choose one or more</strong></div><div class="sp-stat"><small>Payment recipient</small><strong>Enter an account name</strong></div></div>
  <details class="sp-editor" <?= empty($settings['enabled']) && !$settingsSaved?'open':'' ?>><summary><?= empty($settings['enabled'])?'Configure payment settings':'Edit payment settings' ?></summary>
    <form method="POST" enctype="multipart/form-data" class="sp-form">
      <label class="sp-field" style="grid-column:1/-1"><span class="sp-check"><input type="checkbox" name="enabled" value="1"> Enable special pickup payment</span></label>
      <label class="sp-field">Fee amount (₱)<input type="number" name="fee" min="0" step="0.01" placeholder="e.g. 50.00"></label>
      <label class="sp-field">Recipient / account name<input type="text" name="merchant_name" maxlength="160" placeholder="e.g. Barangay Treasurer"></label>
      <div class="sp-field" style="grid-column:1/-1"><span>Available payment methods</span><div class="sp-checks"><label class="sp-check"><input type="checkbox" name="allow_qrph" value="1"> QR Ph / e-wallet (manual review)</label><label class="sp-check"><input type="checkbox" name="allow_cash" value="1"> Cash at barangay office</label></div><small style="font-weight:500">Select the method or methods your barangay accepts.</small></div>
      <label class="sp-field" style="grid-column:1/-1">Instructions for residents<textarea name="instructions" rows="3" maxlength="2000" placeholder="e.g. Pay at the barangay hall, then upload your receipt."></textarea></label>
      <label class="sp-field">QR image (optional)<input type="file" name="qr_image" accept="image/png,image/jpeg,image/webp"><small>Example: upload your official barangay payment QR.</small></label>
      <div class="sp-field">QR image preview<span style="font-weight:500;color:var(--text-light);opacity:.72">Your uploaded QR will appear here after you save.</span></div>
      <div class="sp-form-actions"><button class="btn-approve" type="submit" name="save_settings" style="padding:.68rem 1rem">Save Payment Settings</button><span class="sp-muted">The editor will close after saving.</span></div>
    </form>
  </details>
</section>
<div class="panel sp-empty">Payment submissions and receipt images are visible only to the Sitio Leader assigned to the resident’s sitio, in <a href="/disbasura/leader/payments.php">Review Payments</a>.</div>
<?php render_admin_footer(); ?>
