<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
$cebuCityStmt = $db->prepare('SELECT id FROM cities WHERE name = ? LIMIT 1');
$cebuCityStmt->execute(['Cebu City']);
$cebuCityId = (int)$cebuCityStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_barangay'])) {
        $name = trim($_POST['barangay_name'] ?? '');
        $cityId = $cebuCityId;
        if ($name && $cityId) {
            try {
                $db->prepare("INSERT INTO barangays (city_id,name) VALUES (?,?)")->execute([$cityId,$name]);
                $_SESSION['flash'] = "✅ Barangay '$name' added.";
                log_activity($_SESSION['admin_id'], 'Added Barangay', $name);
            } catch (Exception $e) { $_SESSION['flash_err'] = "Barangay '$name' already exists in that city."; }
        }
        header('Location: /disbasura/admin/sitios.php'); exit;
    }
    if (isset($_POST['delete_barangay'])) {
        $barangayId = (int)($_POST['barangay_id'] ?? 0);
        $barangayStmt = $db->prepare("SELECT b.id, b.name FROM barangays b JOIN cities c ON c.id = b.city_id WHERE b.id = ? AND c.id = ? AND LOWER(b.name) <> 'unassigned' LIMIT 1");
        $barangayStmt->execute([$barangayId, $cebuCityId]);
        $barangay = $barangayStmt->fetch();

        if (!$barangay) {
            $_SESSION['flash_err'] = 'Barangay not found in Cebu City.';
        } else {
            $sitioCountStmt = $db->prepare('SELECT COUNT(*) FROM sitios WHERE barangay_id = ?');
            $sitioCountStmt->execute([$barangayId]);
            if ((int)$sitioCountStmt->fetchColumn() > 0) {
                $_SESSION['flash_err'] = "Cannot delete '{$barangay['name']}' while it has sitios. Remove or move its sitios first.";
            } else {
                $db->prepare('DELETE FROM barangays WHERE id = ?')->execute([$barangayId]);
                log_activity($_SESSION['admin_id'], 'Deleted Barangay', $barangay['name']);
                $_SESSION['flash'] = "Barangay '{$barangay['name']}' deleted.";
            }
        }
        header('Location: /disbasura/admin/sitios.php'); exit;
    }
    if (isset($_POST['add_sitio'])) {
        $name = trim($_POST['name'] ?? '');
        $barangayId = (int)($_POST['barangay_id'] ?? 0);
        $validCebuBarangays = $cebuCityId ? array_filter(get_barangays($cebuCityId), static fn($b) => strcasecmp($b['name'], 'Unassigned') !== 0) : [];
        $validBarangayIds = array_map('intval', array_column($validCebuBarangays, 'id'));
        if ($name && in_array($barangayId, $validBarangayIds, true)) {
            try {
                $db->prepare("INSERT INTO sitios (name,barangay_id) VALUES (?,?)")->execute([$name,$barangayId]);
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                foreach ($days as $d) {
                    $db->prepare("INSERT IGNORE INTO weekly_schedule (day_name,sitio,collection_time,waste_type) VALUES (?,?,'07:00:00','Mixed')")->execute([$d,$name]);
                }
                $_SESSION['flash'] = "✅ Sitio '$name' added successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_err'] = "Sitio '$name' already exists.";
            }
        } elseif ($name) {
            $_SESSION['flash_err'] = 'Choose a barangay in Cebu City before adding a sitio.';
        }
        header('Location: /disbasura/admin/sitios.php'); exit;
    }
    if (isset($_POST['delete_sitio'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $db->prepare("DELETE FROM weekly_schedule WHERE sitio=?")->execute([$name]);
            $db->prepare("DELETE FROM sitios WHERE name=?")->execute([$name]);
            $_SESSION['flash'] = "🗑️ Sitio '$name' deleted.";
        }
        header('Location: /disbasura/admin/sitios.php'); exit;
    }
    if (isset($_POST['edit_sitio'])) {
        $old = trim($_POST['old_name'] ?? '');
        $new = trim($_POST['new_name'] ?? '');
        if ($old && $new && $old !== $new) {
            $db->prepare("UPDATE sitios SET name=? WHERE name=?")->execute([$new,$old]);
            $db->prepare("UPDATE weekly_schedule SET sitio=? WHERE sitio=?")->execute([$new,$old]);
            $db->prepare("UPDATE users SET sitio=? WHERE sitio=?")->execute([$new,$old]);
            $db->prepare("UPDATE collectors SET sitio=? WHERE sitio=?")->execute([$new,$old]);
            $db->prepare("UPDATE schedules SET sitio=? WHERE sitio=?")->execute([$new,$old]);
            $db->prepare("UPDATE requests SET sitio=? WHERE sitio=?")->execute([$new,$old]);
            $_SESSION['flash'] = "✅ Sitio renamed to '$new'.";
        }
        header('Location: /disbasura/admin/sitios.php'); exit;
    }
}

$flash     = $_SESSION['flash'] ?? null;     unset($_SESSION['flash']);
$flash_err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$sitioStmt = $db->prepare("SELECT s.*, b.name AS barangay_name, c.name AS city_name, COUNT(DISTINCT u.id) as resident_count, COUNT(DISTINCT co.id) as collector_count
                          FROM sitios s
                          LEFT JOIN barangays b ON s.barangay_id=b.id
                          LEFT JOIN cities c ON b.city_id=c.id
                          LEFT JOIN users u ON u.sitio=s.name AND u.role IN ('resident','leader')
                          LEFT JOIN collectors co ON co.sitio=s.name
                          WHERE c.id = ?
                          GROUP BY s.id ORDER BY b.name, s.name");
$sitioStmt->execute([$cebuCityId]);
$sitios = $sitioStmt->fetchAll();
$barangays = $cebuCityId ? array_values(array_filter(get_barangays($cebuCityId), static fn($b) => strcasecmp($b['name'], 'Unassigned') !== 0)) : [];
$unread    = get_unread_admin_count($_SESSION['admin_id']);

render_admin_header('sitios', $unread, 'Sitios — DisBasura Admin');
?>
<div class="page-header-row">
  <div class="page-header">
    <h1>📍 Manage Locations</h1>
    <p>Cebu City / Barangays / Sitios. Add each barangay, then create its sitios for residents and schedules.</p>
  </div>
  <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">+ Add Sitio</button>
</div>

<?php if($flash): ?><div class="alert-success" style="margin-bottom:1.25rem"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if($flash_err): ?><div class="alert-error" style="margin-bottom:1.25rem"><?= htmlspecialchars($flash_err) ?></div><?php endif; ?>

<!-- ── Cities & Barangays panel ── -->
<div class="panel" style="margin-bottom:1.5rem">
  <div class="panel-header"><h3>Barangays in Cebu City</h3></div>
  <form method="POST" style="display:flex;gap:.5rem;margin:1rem 0 .85rem">
    <input type="hidden" name="add_barangay" value="1">
    <input type="text" name="barangay_name" placeholder="New barangay name" required style="flex:1;border:1.5px solid var(--border);border-radius:8px;padding:.6rem .75rem;font-family:inherit"/>
    <button type="submit" style="padding:.5rem 1rem;background:var(--green-main);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer">+ Add Barangay</button>
  </form>
  <div style="font-size:.82rem;color:var(--text-mid)">
    <?php foreach($barangays as $b): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid var(--border-light)">
        <span><?= e($b['name']) ?></span>
        <form method="POST" onsubmit="return confirm('Delete this barangay? Barangays with sitios cannot be deleted.')">
          <input type="hidden" name="barangay_id" value="<?= (int)$b['id'] ?>">
          <button type="submit" name="delete_barangay" class="btn-reject" style="font-size:.75rem;padding:.3rem .7rem">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if(!$barangays): ?><em>No barangays yet. Add a barangay to Cebu City before creating sitios.</em><?php endif; ?>
  </div>
</div>
<?php if($sitios): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
  <?php foreach($sitios as $s): ?>
  <div style="background:#fff;border-radius:16px;border:1px solid var(--border-light);box-shadow:var(--shadow-sm);overflow:hidden;transition:transform .18s,box-shadow .18s" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">
    <div style="padding:1.1rem 1.25rem;border-bottom:1px solid var(--border-light)">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--text-dark);margin-bottom:.25rem"><?= htmlspecialchars($s['name']) ?></div>
            <div style="font-size:.72rem;color:var(--green-main);font-weight:600;margin-bottom:.15rem"><?= e($s['barangay_name'] ?? 'Barangay not set') ?></div>
      <div style="font-size:.75rem;color:var(--text-light)">Added <?= date('M d, Y', strtotime($s['created_at'])) ?></div>
    </div>
    <div style="padding:.85rem 1.25rem;display:flex;gap:1.25rem">
      <div style="text-align:center">
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:800;color:var(--green-main)"><?= $s['resident_count'] ?></div>
        <div style="font-size:.72rem;color:var(--text-light)">Residents</div>
      </div>
      <div style="text-align:center">
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:800;color:var(--blue)"><?= $s['collector_count'] ?></div>
        <div style="font-size:.72rem;color:var(--text-light)">Collectors</div>
      </div>
    </div>
    <div style="padding:.75rem 1.25rem;border-top:1px solid var(--border-light);background:#fafcfb;display:flex;gap:.5rem">
      <button onclick="openEdit('<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>')" style="flex:1;padding:.45rem;background:var(--green-pale);color:var(--green-main);border:1.5px solid #b6dfc7;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit">✏️ Rename</button>
      <form method="POST" onsubmit="return confirm('Delete sitio \'<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>\'? This will NOT delete residents or collectors.')" style="flex:1">
        <input type="hidden" name="delete_sitio" value="1">
        <input type="hidden" name="name" value="<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>">
        <button type="submit" style="width:100%;padding:.45rem;background:#fdecea;color:var(--red);border:1.5px solid #f5c6c6;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit">🗑️ Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:16px;border:2px dashed var(--border);padding:4rem;text-align:center">
  <div style="font-size:3rem;margin-bottom:1rem">📍</div>
  <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:.5rem">No Sitios Yet</h3>
  <p style="color:var(--text-light);margin-bottom:1.5rem">Add your first sitio to start assigning residents and schedules.</p>
  <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">+ Add First Sitio</button>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:420px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">📍 Add New Sitio</h3>
    <form method="POST">
      <input type="hidden" name="add_sitio" value="1">
      <div class="field"><label>Sitio Name *</label><input type="text" name="name" placeholder="e.g. Sitio 1 - Poblacion" required autofocus/></div>
      <div class="field"><label>Barangay *</label>
        <select name="barangay_id" required>
          <option value="">Select a Cebu City barangay</option>
          <?php foreach($barangays as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <p style="font-size:.78rem;color:var(--text-light);margin-bottom:1.25rem">A weekly schedule (Mon–Sun) will be auto-created for this sitio.</p>
      <div style="display:flex;gap:.65rem">
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="flex:1;padding:.75rem;border:1.5px solid var(--border);border-radius:8px;background:#fff;cursor:pointer;font-family:inherit">Cancel</button>
        <button type="submit" style="flex:1;padding:.75rem;background:var(--green-main);color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;cursor:pointer">Add Sitio</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit/Rename Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:420px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:1.25rem">✏️ Rename Sitio</h3>
    <form method="POST">
      <input type="hidden" name="edit_sitio" value="1">
      <input type="hidden" name="old_name" id="editOldName">
      <div class="field"><label>New Name *</label><input type="text" name="new_name" id="editNewName" required/></div>
      <p style="font-size:.78rem;color:var(--text-light);margin-bottom:1.25rem">All residents, collectors and schedules assigned to this sitio will be updated automatically.</p>
      <div style="display:flex;gap:.65rem">
        <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="flex:1;padding:.75rem;border:1.5px solid var(--border);border-radius:8px;background:#fff;cursor:pointer;font-family:inherit">Cancel</button>
        <button type="submit" style="flex:1;padding:.75rem;background:var(--green-main);color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;cursor:pointer">Save Name</button>
      </div>
    </form>
  </div>
</div>
<script>
function openEdit(name){
  document.getElementById('editOldName').value=name;
  document.getElementById('editNewName').value=name;
  document.getElementById('editModal').style.display='flex';
}
document.getElementById('addModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});
</script>
<?php render_admin_footer(); ?>
