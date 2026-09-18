<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db      = get_db();
$sitios  = get_sitios();
$aid     = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $sitio   = $_POST['sitio'] ?: null;
        if ($title && $message) {
            $db->prepare("INSERT INTO announcements (title,message,sitio,created_by) VALUES (?,?,?,?)")
               ->execute([$title, $message, $sitio, $aid]);
            // Notify residents
            $msg = "📢 New Announcement: $title — $message";
            if ($sitio) notify_all_sitio($db, $sitio, $msg);
            else {
                foreach($sitios as $s) notify_all_sitio($db, $s, $msg);
            }
            log_activity($aid, 'Posted Announcement', "Title: $title | Sitio: ".($sitio ?? 'All'));
        }
    } elseif (isset($_POST['delete'])) {
        $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$_POST['id']]);
        log_activity($aid, 'Deleted Announcement', "ID: ".$_POST['id']);
    }
    header('Location: /disbasura/admin/announcements.php'); exit;
}

$announcements = $db->query("SELECT a.*,ad.full_name FROM announcements a JOIN administrators ad ON a.created_by=ad.id ORDER BY a.created_at DESC")->fetchAll();
$unread = get_unread_count($aid);
render_admin_header('announcements', $unread, 'Announcements — DisBasura');
?>
<div class="page-header-row">
  <div class="page-header">
    <h1>📢 Announcements</h1>
    <p>Post announcements to residents by sitio or to all sitios</p>
  </div>
  <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">+ New Announcement</button>
</div>

<?php if($announcements): foreach($announcements as $a): ?>
<div style="background:#fff;border-radius:14px;border:1px solid var(--border-light);box-shadow:var(--shadow-sm);padding:1.25rem 1.5rem;margin-bottom:1rem">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.4rem;flex-wrap:wrap">
        <strong style="font-size:.95rem;font-family:'Plus Jakarta Sans',sans-serif"><?= e($a['title']) ?></strong>
        <span style="font-size:.72rem;font-weight:700;padding:.2rem .65rem;border-radius:20px;<?= $a['sitio'] ? 'background:var(--green-pale);color:var(--green-main)' : 'background:#e3f2fd;color:#1565c0' ?>">
          <?= $a['sitio'] ? '📍 '.e($a['sitio']) : '🌐 All Sitios' ?>
        </span>
      </div>
      <p style="font-size:.88rem;color:var(--text-mid);line-height:1.6;margin-bottom:.5rem"><?= e($a['message']) ?></p>
      <div style="font-size:.75rem;color:var(--text-light)">Posted by <?= e($a['full_name']) ?> · <?= fmt_date($a['created_at']) ?></div>
    </div>
    <form method="POST" onsubmit="return confirm('Delete this announcement?')">
      <input type="hidden" name="delete" value="1">
      <input type="hidden" name="id" value="<?= $a['id'] ?>">
      <button type="submit" class="btn-reject" style="font-size:.78rem;padding:.35rem .8rem">🗑️ Delete</button>
    </form>
  </div>
</div>
<?php endforeach; else: ?>
<div style="background:#fff;border-radius:16px;border:2px dashed var(--border);padding:4rem;text-align:center;margin-top:1rem">
  <div style="font-size:3rem;margin-bottom:1rem">📢</div>
  <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;margin-bottom:.5rem">No announcements yet</h3>
  <p style="color:var(--text-light);font-size:.85rem">Post one to notify residents about schedule changes or holidays.</p>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:500px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">📢 New Announcement</h3>
    <form method="POST">
      <input type="hidden" name="add" value="1">
      <div class="field"><label>Title *</label><input type="text" name="title" placeholder="e.g. No collection this Friday" required/></div>
      <div class="field"><label>Message *</label>
        <textarea name="message" rows="3" placeholder="Write your announcement here..." required
          style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:.75rem 1rem;font-family:inherit;font-size:.92rem;resize:vertical"></textarea>
      </div>
      <div class="field"><label>Send To</label>
        <select name="sitio">
          <option value="">📍 All Sitios</option>
          <?php foreach($sitios as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-save">📢 Post Announcement</button>
      </div>
    </form>
  </div>
</div>
<script>document.getElementById('addModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});</script>
<?php render_admin_footer(); ?>
