<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/contact_helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/base_admin.php';

$db = get_db();
ensure_contact_messages_table($db);
$aid = (int)$_SESSION['admin_id'];
$unread = get_unread_admin_count($aid);
$error = '';
if (empty($_SESSION['contact_admin_csrf'])) $_SESSION['contact_admin_csrf'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact_reply'])) {
    $token = (string)($_POST['csrf_token'] ?? '');
    $messageId = (int)($_POST['message_id'] ?? 0);
    $reply = trim((string)($_POST['reply'] ?? ''));
    if (!hash_equals($_SESSION['contact_admin_csrf'], $token)) {
        $error = 'Your page session expired. Refresh and try again.';
    } elseif ($reply === '' || strlen($reply) > 5000) {
        $error = 'Enter a reply up to 5,000 characters.';
    } else {
        $stmt = $db->prepare('SELECT id,full_name,email FROM contact_messages WHERE id=? AND email_verified=1 LIMIT 1');
        $stmt->execute([$messageId]);
        $recipient = $stmt->fetch();
        if (!$recipient) {
            $error = 'That verified contact message could not be found.';
        } elseif (!send_contact_reply_email($recipient['email'], $recipient['full_name'], $reply)) {
            $error = 'The reply could not be sent. Check the Gmail SMTP configuration and try again.';
        } else {
            $db->prepare('UPDATE contact_messages SET admin_reply=?,replied_at=NOW() WHERE id=?')
                ->execute([$reply, $messageId]);
            $_SESSION['contact_admin_csrf'] = bin2hex(random_bytes(32));
            header('Location: /disbasura/admin/contact-messages.php?sent=1');
            exit;
        }
    }
}

$messages = $db->query('SELECT * FROM contact_messages WHERE email_verified=1 ORDER BY created_at DESC')->fetchAll();
render_admin_header('contacts', $unread, 'Contact Inbox — DisBasura Admin');
?>
<div class="page-header"><h1>Contact Inbox</h1><p>Review verified messages from the landing page and reply to residents by email.</p></div>
<?php if (!empty($_GET['sent'])): ?><div style="margin:0 0 1rem;padding:.85rem 1rem;border-radius:10px;background:#e9f8ef;color:#167347">Your reply was sent to the sender’s Gmail address.</div><?php endif; ?>
<?php if ($error !== ''): ?><div role="alert" style="margin:0 0 1rem;padding:.85rem 1rem;border-radius:10px;background:#fff0f0;color:#b42318"><?= e($error) ?></div><?php endif; ?>
<?php if ($messages): foreach ($messages as $message): ?>
<article style="background:var(--bg-card,#fff);border:1px solid var(--border-light,#e4eee8);border-radius:16px;padding:1.25rem;margin-bottom:1rem;box-shadow:var(--shadow-sm,0 2px 8px rgba(0,0,0,.04))">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div><h2 style="font-size:1rem;color:var(--text-dark)"><?= e($message['full_name']) ?></h2><a href="mailto:<?= e($message['email']) ?>" style="color:var(--green-main)"><?= e($message['email']) ?></a><?php if ($message['area']): ?><div style="margin-top:.2rem;color:var(--text-mid)"><?= e($message['area']) ?></div><?php endif; ?></div>
    <div style="font-size:.78rem;color:var(--text-light)"><?= e(fmt_date($message['created_at'])) ?><br><span style="color:#15845c">Gmail verified</span></div>
  </div>
  <div style="white-space:pre-wrap;overflow-wrap:anywhere;margin:1rem 0;padding:1rem;border-radius:11px;background:var(--bg-page,#f4f8f6);color:var(--text-dark)"><?= e($message['message']) ?></div>
  <?php if (!empty($message['admin_reply'])): ?><div style="margin:.5rem 0 1rem;padding:.85rem 1rem;border-left:3px solid var(--green-main);background:var(--green-pale);border-radius:0 10px 10px 0"><strong>Last reply · <?= e(fmt_date($message['replied_at'])) ?></strong><div style="white-space:pre-wrap;margin-top:.35rem"><?= e($message['admin_reply']) ?></div></div><?php endif; ?>
  <form method="post" style="display:grid;gap:.65rem">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['contact_admin_csrf']) ?>">
    <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
    <label for="reply-<?= (int)$message['id'] ?>" style="font-weight:700;color:var(--text-dark)"><?= empty($message['replied_at']) ? 'Write a reply' : 'Send a follow-up' ?></label>
    <textarea id="reply-<?= (int)$message['id'] ?>" name="reply" rows="3" maxlength="5000" required placeholder="Write a helpful reply…" style="width:100%;padding:.8rem;border:1px solid var(--border);border-radius:10px;font:inherit;resize:vertical"></textarea>
    <button type="submit" name="send_contact_reply" value="1" style="justify-self:start;border:0;border-radius:9px;padding:.7rem 1rem;background:var(--green-main);color:#fff;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer">Send email reply</button>
  </form>
</article>
<?php endforeach; else: ?>
<div class="empty-state" style="padding:4rem"><p>No verified contact messages yet.</p></div>
<?php endif; ?>
<?php render_admin_footer(); ?>
