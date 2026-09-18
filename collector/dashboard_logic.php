<?php
/**
 * collector/dashboard_logic.php
 * All PHP logic — DB queries, POST handlers, data preparation.
 * Included at the top of collector/dashboard.php
 */

require_once __DIR__ . '/../config/sms.php';
require_once __DIR__ . '/../middleware/collector_auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$cid = $_SESSION['collector_id'];
$db  = get_db();

// ── DB migrations (run once, no-op after) ────────────────────
try { $db->exec("ALTER TABLE collectors MODIFY COLUMN status ENUM('available','sick','unavailable') NOT NULL DEFAULT 'available'"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE collectors ADD COLUMN IF NOT EXISTS dark_mode TINYINT NOT NULL DEFAULT 0"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE collectors ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}
foreach ([
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS proof_photo         VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS completed_at        DATETIME     DEFAULT NULL",
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS resident_proof_photo VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE schedules ADD COLUMN IF NOT EXISTS resident_proof_photo VARCHAR(500) DEFAULT NULL",
] as $sql) { try { $db->exec($sql); } catch(Exception $e){} }
try { $db->exec("ALTER TABLE requests MODIFY COLUMN status ENUM('pending','approved','rejected','completed','assigned') NOT NULL DEFAULT 'pending'"); } catch(Exception $e){}
try { $db->exec("UPDATE requests SET status='assigned' WHERE collector_id IS NOT NULL AND (status='' OR status IS NULL)"); } catch(Exception $e){}

// ── Load collector ────────────────────────────────────────────
$collector = $db->query("SELECT * FROM collectors WHERE id=$cid")->fetch();
if (!$collector) { session_destroy(); header('Location: /disbasura/collector/login.php'); exit; }

// ── Handle mark_read redirect ─────────────────────────────────
if (isset($_GET['mark_read'])) {
    $col_user_q = $db->prepare("SELECT id FROM users WHERE username=?");
    $col_user_q->execute([$collector['username'] ?? '']);
    $cu = $col_user_q->fetch();
    if ($cu) $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$cu['id']]);
    header('Location: /disbasura/collector/dashboard.php'); exit;
}

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Set availability status
    if (isset($_POST['set_status'])) {
        $new_status = $_POST['status'] ?? 'available';
        $db->prepare("UPDATE collectors SET status=? WHERE id=?")->execute([$new_status, $cid]);
        header('Location: /disbasura/collector/dashboard.php'); exit;
    }

    // Toggle dark mode
    if (isset($_POST['toggle_dark'])) {
        $new_dark = ($collector['dark_mode'] ?? 0) ? 0 : 1;
        $db->prepare("UPDATE collectors SET dark_mode=? WHERE id=?")->execute([$new_dark, $cid]);
        header('Location: /disbasura/collector/dashboard.php'); exit;
    }

    // Mark schedule complete with proof photo
    if (isset($_POST['complete_schedule'])) {
        $sid = (int)$_POST['schedule_id'];
        if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
            $fname = save_upload($_FILES['proof_photo'], "proof_{$sid}_{$cid}");
            if ($fname) {
                $db->prepare("UPDATE schedules SET status='completed', proof_photo=?, completed_at=? WHERE id=? AND collector_id=?")
                   ->execute([$fname, now_pht(), $sid, $cid]);
                $sched = $db->query("SELECT * FROM schedules WHERE id=$sid")->fetch();
                if ($sched) {
                    notify_all_sitio($db, $sched['sitio'], "✅ Garbage collection for {$sched['sitio']} has been completed! If your trash was NOT collected, please submit a dispute.");
                    notify_all_admins($db, "📸 Collector submitted photo proof for {$sched['sitio']} schedule.");
                }
            }
        }
        header('Location: /disbasura/collector/dashboard.php'); exit;
    }

    // Mark pickup request complete with proof photo
    if (isset($_POST['complete_request'])) {
        $rid = (int)$_POST['request_id'];
        $req = $db->query("SELECT * FROM requests WHERE id=$rid AND collector_id=$cid")->fetch();
        if ($req) {
            $fname = null;
            if (isset($_FILES['req_proof_photo']) && $_FILES['req_proof_photo']['error'] === UPLOAD_ERR_OK) {
                $fname = save_upload($_FILES['req_proof_photo'], "reqproof_{$rid}_{$cid}");
            }
            $db->prepare("UPDATE requests SET status='completed', proof_photo=?, completed_at=? WHERE id=?")
               ->execute([$fname, now_pht(), $rid]);
            notify_user($db, $req['resident_id'], "✅ Your pickup request has been completed by the collector!");
            notify_all_admins($db, "🚛 Collector completed pickup request #$rid for {$req['sitio']}.");
        }
        header('Location: /disbasura/collector/dashboard.php'); exit;
    }
}

// ── Fetch schedules ───────────────────────────────────────────
$schedules = $db->query("SELECT * FROM schedules WHERE collector_id=$cid ORDER BY scheduled_at DESC LIMIT 20")->fetchAll();
$total     = count($schedules);
$completed = count(array_filter($schedules, fn($s) => $s['status'] === 'completed'));
$pending   = count(array_filter($schedules, fn($s) => in_array($s['status'], ['scheduled','assigned'])));

// ── Fetch pickup requests ─────────────────────────────────────
try {
    $req_stmt = $db->prepare("
        SELECT r.id, r.resident_id, r.sitio, r.location, r.waste_type,
               r.preferred_date, r.note, r.status, r.collector_id, r.created_at,
               IFNULL(r.proof_photo,  '')  AS proof_photo,
               IFNULL(r.completed_at, '')  AS completed_at,
               u.full_name AS resident_name,
               u.phone     AS resident_phone
        FROM requests r
        JOIN users u ON r.resident_id = u.id
        WHERE r.collector_id = ?
          AND r.status IN ('assigned','pending','approved','completed')
        ORDER BY
          CASE r.status
            WHEN 'assigned'  THEN 1
            WHEN 'pending'   THEN 2
            WHEN 'approved'  THEN 3
            ELSE 4
          END,
          r.preferred_date ASC
    ");
    $req_stmt->execute([$cid]);
    $requests = $req_stmt->fetchAll();
} catch (Exception $e) {
    $req_stmt = $db->prepare("
        SELECT r.*, u.full_name AS resident_name, u.phone AS resident_phone
        FROM requests r
        JOIN users u ON r.resident_id = u.id
        WHERE r.collector_id = ?
          AND r.status IN ('assigned','pending','approved','completed')
        ORDER BY r.preferred_date ASC
    ");
    $req_stmt->execute([$cid]);
    $requests = $req_stmt->fetchAll();
}

$active_requests = array_filter($requests, fn($r) => in_array($r['status'], ['assigned','pending','approved']));
$done_requests   = array_filter($requests, fn($r) => $r['status'] === 'completed');

// ── Fetch notifications ───────────────────────────────────────
$col_user_stmt = $db->prepare("SELECT id FROM users WHERE username=?");
$col_user_stmt->execute([$collector['username'] ?? '']);
$col_user     = $col_user_stmt->fetch();
$col_uid      = $col_user ? $col_user['id'] : 0;
$notifs       = [];
$notif_unread = 0;

if ($col_uid) {
    $ns = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
    $ns->execute([$col_uid]);
    $notifs       = $ns->fetchAll();
    $notif_unread = count(array_filter($notifs, fn($n) => !$n['is_read']));
}
