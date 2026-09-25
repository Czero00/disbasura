<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/sms.php';
if ($_SESSION['role'] === 'leader') { header('Location: /disbasura/leader/dashboard.php'); exit; }

$db    = get_db();
$uid   = $_SESSION['user_id'];
$sitio = $_SESSION['sitio'] ?? '';
$prefs = get_preferences($uid);
$lang  = get_lang($uid);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_request'])) {
        $rid = (int)$_POST['rid'];
        $db->prepare("DELETE FROM requests WHERE id=? AND resident_id=? AND status='pending'")->execute([$rid,$uid]);
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
    if (isset($_POST['submit_resident_proof'])) {
        $sid = (int)$_POST['schedule_id'];
        // Verify this schedule belongs to resident's sitio
        $sched = $db->prepare("SELECT * FROM schedules WHERE id=? AND sitio=?");
        $sched->execute([$sid, $sitio]);
        $sched = $sched->fetch();
        if ($sched && isset($_FILES['resident_proof']) && $_FILES['resident_proof']['error']===UPLOAD_ERR_OK) {
            $fname = save_upload($_FILES['resident_proof'], "res_proof_{$sid}_{$uid}");
            if ($fname) {
                $db->prepare("UPDATE schedules SET resident_proof_photo=? WHERE id=?")->execute([$fname, $sid]);
                notify_all_admins($db, "📸 Resident submitted proof photo for schedule in {$sched['sitio']} — {$sched['waste_type']}.");
                notify_user($db, $uid, "✅ Your proof photo for {$sched['sitio']} ({$sched['waste_type']}) has been submitted to the admin.");
            }
        }
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
    if (isset($_POST['submit_request_proof'])) {
        $rid = (int)$_POST['request_id'];
        // Ensure column exists
        try { $db->exec("ALTER TABLE requests ADD COLUMN IF NOT EXISTS resident_proof_photo VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}
        $req = $db->prepare("SELECT * FROM requests WHERE id=? AND resident_id=?");
        $req->execute([$rid, $uid]);
        $req = $req->fetch();
        if ($req && isset($_FILES['request_proof']) && $_FILES['request_proof']['error']===UPLOAD_ERR_OK) {
            $fname = save_upload($_FILES['request_proof'], "reqres_proof_{$rid}_{$uid}");
            if ($fname) {
                $db->prepare("UPDATE requests SET resident_proof_photo=? WHERE id=?")->execute([$fname, $rid]);
                notify_all_admins($db, "📸 Resident submitted proof for pickup request #{$rid} in {$req['sitio']} — {$req['waste_type']}. Collector: ".($req['collector_id'] ? "assigned" : "unassigned").".");
                notify_user($db, $uid, "✅ Your proof photo for pickup request in {$req['sitio']} has been submitted to the admin.");
            }
        }
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
    if (isset($_POST['save_lang'])) {
        save_preferences($uid, $prefs['dark_mode'], $_POST['language'] ?? 'en');
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
    if (isset($_POST['toggle_dark'])) {
        save_preferences($uid, $prefs['dark_mode'] ? 0 : 1, $prefs['language']);
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
    if (isset($_POST['save_prefs'])) {
        save_preferences($uid, isset($_POST['dark_mode'])?1:0, $_POST['language']??'en');
        header('Location: /disbasura/resident/dashboard.php'); exit;
    }
}

// Data
// Auto-migrate: ensure optional columns exist
foreach ([
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS proof_photo VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS completed_at DATETIME DEFAULT NULL",
    "ALTER TABLE requests ADD COLUMN IF NOT EXISTS resident_proof_photo VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE schedules ADD COLUMN IF NOT EXISTS resident_proof_photo VARCHAR(500) DEFAULT NULL",
] as $sql) { try { $db->exec($sql); } catch(Exception $e){} }
// Fix ENUM if 'assigned' is missing
try { $db->exec("ALTER TABLE requests MODIFY COLUMN status ENUM('pending','approved','rejected','completed','assigned') NOT NULL DEFAULT 'pending'"); } catch(Exception $e){}
try { $db->exec("UPDATE requests SET status='assigned' WHERE collector_id IS NOT NULL AND (status='' OR status IS NULL)"); } catch(Exception $e){}

$my_requests = $db->prepare("SELECT r.*,c.full_name as collector_name FROM requests r LEFT JOIN collectors c ON r.collector_id=c.id WHERE r.resident_id=? ORDER BY r.created_at DESC");
$my_requests->execute([$uid]); $my_requests = $my_requests->fetchAll();

// Weekly schedule — show all sitios, highlight own
$upcoming_schedules = $db->prepare("SELECT s.*,c.full_name AS collector_name FROM schedules s LEFT JOIN collectors c ON s.collector_id=c.id WHERE s.sitio=? AND DATE(s.scheduled_at)>=CURDATE() ORDER BY s.scheduled_at ASC");
$upcoming_schedules->execute([$sitio]); $upcoming_schedules = $upcoming_schedules->fetchAll();

// Recent one-time schedules for resident's sitio (from schedules table — admin-created)
$schedules = $db->prepare("SELECT s.*,c.full_name as collector_name FROM schedules s LEFT JOIN collectors c ON s.collector_id=c.id WHERE s.sitio=? AND DATE(s.scheduled_at)<CURDATE() AND DATE(s.scheduled_at)>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) ORDER BY s.scheduled_at DESC LIMIT 15");
$schedules->execute([$sitio]); $schedules = $schedules->fetchAll();

$my_dispute_ids = $db->prepare("SELECT schedule_id FROM disputes WHERE user_id=?");
$my_dispute_ids->execute([$uid]); $my_dispute_ids = array_column($my_dispute_ids->fetchAll(),'schedule_id');

$my_feedback_ids = $db->prepare("SELECT schedule_id FROM feedback WHERE user_id=?");
$my_feedback_ids->execute([$uid]); $my_feedback_ids = array_column($my_feedback_ids->fetchAll(),'schedule_id');

$unread = get_unread_count($uid);

$announcements = $db->prepare("SELECT * FROM announcements WHERE sitio IS NULL OR sitio=? ORDER BY created_at DESC LIMIT 5");
$announcements->execute([$sitio]); $announcements = $announcements->fetchAll();

$dark = $prefs['dark_mode'] ? 'dark' : '';
?>
<!DOCTYPE html>
<html lang="en" class="<?= $dark ?>">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= $lang['dashboard'] ?> — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
  <style>
    /* ── Dark mode vars ── */
    html.dark{--bg-page:#0d1f14;--bg-card:#152b1e;--bg-sidebar:#0f2318;--text-dark:#e2f0e8;--text-mid:#8baa96;--border:#243d2c;--border-light:#1e3328}
    html.dark body{background:var(--bg-page);color:var(--text-dark)}
    html.dark .res-sidebar{background:var(--bg-sidebar)!important;border-color:var(--border)!important}
    html.dark .res-main{background:var(--bg-page)!important}
    html.dark .panel,html.dark .stat-card{background:var(--bg-card)!important;border-color:var(--border)!important}
    html.dark table th,html.dark table td{border-color:var(--border)!important;color:var(--text-dark)}
    html.dark .sched-row-own{background:rgba(45,134,83,.18)!important}
    html.dark input,html.dark select,html.dark textarea{background:#152b1e!important;border-color:var(--border)!important;color:var(--text-dark)!important}
    html.dark .sidebar-nav a{color:#8baa96}
    html.dark .sidebar-nav a:hover,html.dark .sidebar-nav a.active{background:rgba(45,134,83,.25);color:#e2f0e8}

    /* ── Layout ── */
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#f0f6f3;min-height:100vh;font-family:'Plus Jakarta Sans','Inter',sans-serif}
    .res-layout{display:flex;min-height:100vh}

    /* ── Sidebar ── */
    .res-sidebar{width:240px;flex-shrink:0;background:#fff;border-right:1px solid #e4ede8;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-brand{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid #e4ede8}
    .sidebar-brand-inner{display:flex;align-items:center;gap:.65rem}
    .sidebar-logo{width:36px;height:36px;background:linear-gradient(135deg,#1e5c38,#2d8653);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
    .sidebar-brand h2{font-size:1rem;font-weight:800;color:#1a3a2a;line-height:1.1}
    .sidebar-brand p{font-size:.7rem;color:#7aab8a;margin-top:.1rem}
    .sidebar-user{padding:.85rem 1.25rem;border-bottom:1px solid #e4ede8}
    .sidebar-user a{display:flex;align-items:center;gap:.65rem;text-decoration:none;border-radius:10px;padding:.35rem .5rem;margin:-.35rem -.5rem;transition:background .16s}
    .sidebar-user a:hover{background:rgba(30,107,60,.07);text-decoration:none}
    html.dark .sidebar-user a:hover{background:rgba(45,134,83,.15)}
    .sidebar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1e5c38,#2d8653);display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:800;color:#fff;flex-shrink:0}
    .sidebar-user-info{margin-left:.65rem;overflow:hidden}
    .sidebar-user-info strong{font-size:.82rem;font-weight:700;color:#1a3a2a;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sidebar-user-info span{font-size:.72rem;color:#7aab8a}
    .sidebar-nav{flex:1;padding:.75rem .75rem}
    .sidebar-nav a{display:flex;align-items:center;gap:.65rem;padding:.62rem .85rem;border-radius:10px;text-decoration:none;font-size:.84rem;font-weight:600;color:#4a7a5a;margin-bottom:.2rem;transition:all .16s;position:relative}
    .sidebar-nav a:hover{background:#f0faf4;color:#1a3a2a}
    .sidebar-nav a.active{background:#e8f5ee;color:#1a3a2a}
    .sidebar-nav a .nav-icon{font-size:1rem;width:22px;text-align:center;flex-shrink:0}
    .notif-badge{position:absolute;right:.75rem;background:#e74c3c;color:#fff;border-radius:20px;font-size:.62rem;padding:.1rem .4rem;font-weight:800;line-height:1.4}
    .sidebar-bottom{padding:.65rem .85rem;border-top:1px solid #e4ede8}
    .sb-bottom-row{display:flex;align-items:center;gap:.4rem;margin-bottom:.45rem}
    .sb-icon-btn{display:inline-flex;align-items:center;justify-content:center;gap:.3rem;padding:.4rem .7rem;border:1.5px solid #d4e6db;border-radius:8px;background:transparent;cursor:pointer;font-size:.75rem;font-weight:600;color:#4a7a5a;font-family:inherit;transition:all .16s;white-space:nowrap;flex:1}
    .sb-icon-btn:hover{background:#f0faf4;border-color:#2d8653;color:#1a3a2a}
    html.dark .sb-icon-btn{border-color:rgba(255,255,255,.15);color:#8baa96}
    html.dark .sb-icon-btn:hover{background:rgba(45,134,83,.15);border-color:rgba(76,175,128,.4);color:#e2f0e8}
    .sb-lang-select{flex:0 0 auto;border:1.5px solid #d4e6db;border-radius:8px;padding:.4rem .45rem;font-size:.75rem;background:transparent;color:#4a7a5a;font-family:inherit;cursor:pointer;max-width:72px}
    html.dark .sb-lang-select{border-color:rgba(255,255,255,.15);background:transparent;color:#8baa96}
    .signout-btn{display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.48rem;border:1.5px solid rgba(224,82,82,.3);border-radius:8px;background:transparent;cursor:pointer;font-size:.78rem;font-weight:600;color:#c0392b;font-family:inherit;text-decoration:none;transition:all .16s}
    .signout-btn:hover{background:#fdecea;border-color:var(--red);text-decoration:none}

    /* ── Main content ── */
    .res-main{flex:1;padding:1.75rem 2rem;overflow-y:auto;min-width:0}
    .page-greeting h1{font-size:1.35rem;font-weight:800;color:#1a3a2a;margin-bottom:.2rem}
    html.dark .page-greeting h1{color:var(--text-dark)}
    .page-greeting p{font-size:.83rem;color:#7aab8a}

    /* ── Panels ── */
    .panel{background:#fff;border-radius:14px;border:1px solid #e4ede8;margin-bottom:1.25rem;overflow:hidden}
    html.dark .panel{background:var(--bg-card);border-color:var(--border)}
    .panel-hd{padding:.85rem 1.25rem;border-bottom:1px solid #e4ede8;display:flex;align-items:center;justify-content:space-between}
    html.dark .panel-hd{border-color:var(--border)}
    .panel-hd h3{font-size:.9rem;font-weight:700;color:#1a3a2a}
    html.dark .panel-hd h3{color:var(--text-dark)}

    /* ── Schedule table ── */
    .sched-table{width:100%;border-collapse:collapse;font-size:.82rem}
    .sched-table th{padding:.55rem .85rem;text-align:left;color:#7aab8a;font-weight:600;border-bottom:2px solid #e4ede8;white-space:nowrap}
    .sched-table td{padding:.6rem .85rem;border-bottom:1px solid #f0f5f2;vertical-align:middle}
    html.dark .sched-table th{color:#5a8a6a;border-color:var(--border)}
    html.dark .sched-table td{border-color:var(--border);color:var(--text-dark)}
    .sched-row-own{background:#f2faf5}
    .sched-row-own td:first-child{font-weight:700}
    .today-pill{font-size:.68rem;background:#1e6b3c;color:#fff;border-radius:20px;padding:.1rem .45rem;margin-left:.35rem;vertical-align:middle}
    .own-pill{font-size:.68rem;background:#e8f5ee;color:#1e6b3c;border:1px solid #b6d9c3;border-radius:20px;padding:.1rem .45rem;margin-left:.35rem;vertical-align:middle;font-weight:700}

    /* ── Request items ── */
    .req-item{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid #f0f5f2;gap:.75rem;flex-wrap:wrap}
    html.dark .req-item{border-color:var(--border)}
    .req-item:last-child{border-bottom:none}
    .req-meta strong{font-size:.85rem;font-weight:700;color:#1a3a2a;display:block}
    html.dark .req-meta strong{color:var(--text-dark)}
    .req-meta span{font-size:.75rem;color:#7aab8a}

    /* ── Announcement ── */
    .ann-item{padding:.7rem 1.25rem;border-bottom:1px solid #f0f5f2}
    html.dark .ann-item{border-color:var(--border)}
    .ann-item:last-child{border-bottom:none}

    /* Responsive */
    /* Ham btn — hidden on desktop, shown on mobile via media query */
    .res-ham-btn{display:flex;flex-direction:column;justify-content:center;gap:4px;width:32px;height:32px;padding:6px;background:#2d8653;border:none;border-radius:8px;cursor:pointer;box-shadow:0 2px 7px rgba(45,134,83,.22);flex-shrink:0}
    .res-ham-btn span{display:block;width:100%;height:2.5px;background:#fff;border-radius:2px}
    /* Resident overlay */
    .res-mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:199;cursor:pointer}
    .res-mob-overlay.show{display:block}
    @media(max-width:768px){
      .res-ham-btn{display:flex!important}
      .res-sidebar{position:fixed!important;left:-260px;top:0;height:100vh!important;z-index:200;transition:left .3s cubic-bezier(.4,0,.2,1);box-shadow:none;overflow-y:auto;width:240px}
      .res-sidebar.open{left:0!important;box-shadow:6px 0 30px rgba(0,0,0,.4)}
      .res-main{width:100%!important;padding:1.25rem}
    }

    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    .fade-up{animation:fadeUp .3s ease forwards}
    .star-btn{background:none;border:none;font-size:1.4rem;cursor:pointer;color:#d4e6db;transition:color .15s;padding:0 .1rem}
    .star-btn:hover,.star-btn.active{color:#f5a623}

    /* ── Weekly schedule grouped cards ── */
    .day-group{margin-bottom:.85rem;border-radius:12px;overflow:hidden;border:1px solid #e4ede8}
    html.dark .day-group{border-color:var(--border)}
    .day-group-header{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.1rem;background:#f5faf7;border-bottom:1px solid #e4ede8}
    html.dark .day-group-header{background:rgba(30,83,56,.25);border-color:var(--border)}
    .day-group-header.is-today{background:linear-gradient(90deg,#e8f5ee,#f0faf5);border-bottom-color:#b6d9c3}
    html.dark .day-group-header.is-today{background:rgba(45,134,83,.2)}
    .day-badge{display:flex;flex-direction:column;align-items:center;justify-content:center;width:46px;height:46px;border-radius:10px;background:#fff;border:1.5px solid #d4e6db;flex-shrink:0}
    html.dark .day-badge{background:rgba(255,255,255,.07);border-color:var(--border)}
    .day-badge.today-badge{background:#1e6b3c;border-color:#1e6b3c}
    .day-badge .day-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;color:#1a3a2a;line-height:1}
    html.dark .day-badge .day-num{color:var(--text-dark)}
    .day-badge.today-badge .day-num{color:#fff}
    .day-badge .day-mon{font-size:.6rem;font-weight:700;text-transform:uppercase;color:#7aab8a;letter-spacing:.04em;margin-top:.1rem}
    .day-badge.today-badge .day-mon{color:rgba(255,255,255,.75)}
    .day-header-info h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;color:#1a3a2a;margin-bottom:.05rem}
    html.dark .day-header-info h4{color:var(--text-dark)}
    .day-header-info p{font-size:.74rem;color:#7aab8a}
    .sched-entry{display:flex;align-items:center;justify-content:space-between;padding:.6rem 1.1rem;border-bottom:1px solid #f0f5f2;gap:.5rem;flex-wrap:wrap}
    html.dark .sched-entry{border-color:var(--border)}
    .sched-entry:last-child{border-bottom:none}
    .sched-entry.mine{background:#f2faf5}
    html.dark .sched-entry.mine{background:rgba(45,134,83,.1)}
    .sched-entry-left{display:flex;align-items:center;gap:.55rem;flex:1;min-width:0}
    .sched-time-chip{display:flex;align-items:center;justify-content:center;padding:.22rem .55rem;background:#e8f5ee;color:#1e6b3c;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;flex-shrink:0}
    html.dark .sched-time-chip{background:rgba(45,134,83,.25);color:#7ee8b0}
    .sched-sitio{font-size:.84rem;font-weight:600;color:#1a3a2a}
    html.dark .sched-sitio{color:var(--text-dark)}
    .sched-entry-right{display:flex;align-items:center;gap:.45rem;flex-shrink:0}
  </style>
</head>
<body>
<div class="res-layout">

  <!-- ══ SIDEBAR ══ -->
  <aside class="res-sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="sidebar-brand-inner">
        <svg viewBox="0 0 72 72" fill="none" width="36" height="36" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
          <defs>
            <linearGradient id="resRingG" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#5dd96b"/>
              <stop offset="50%"  stop-color="#22a94a"/>
              <stop offset="100%" stop-color="#0d6e30"/>
            </linearGradient>
            <linearGradient id="resLeafG" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#7de87a"/>
              <stop offset="100%" stop-color="#1a8a38"/>
            </linearGradient>
          </defs>
          <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#resRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
          <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#resRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
          <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#resRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#resRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
          <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#resLeafG)"/>
          <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#resLeafG)"/>
          <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
        </svg>
        <div>
          <h2>DisBasura</h2>
          <p>Resident Portal</p>
        </div>
      </div>
    </div>

    <!-- User info -->
    <div class="sidebar-user">
      <a href="/disbasura/resident/profile.php" style="display:flex;align-items:center;gap:.65rem;text-decoration:none;flex:1;min-width:0">
        <?php
          // Show profile photo if set
          $res_profile_photo = null;
          try {
            $rpp = $db->prepare("SELECT profile_photo FROM users WHERE id=?");
            $rpp->execute([$uid]);
            $res_profile_photo = $rpp->fetchColumn();
          } catch(Exception $e) {}
        ?>
        <?php if ($res_profile_photo): ?>
          <img src="/disbasura/uploads/<?= e($res_profile_photo) ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.25);flex-shrink:0"/>
        <?php else: ?>
          <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="sidebar-user-info">
          <strong><?= e($_SESSION['full_name']) ?></strong>
          <span>📍 <?= e($sitio) ?></span>
        </div>
      </a>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <a href="/disbasura/resident/dashboard.php" class="active">
        <span class="nav-icon">🏠</span> <?= $lang['dashboard'] ?>
      </a>
      <a href="/disbasura/resident/submit-request.php" style="position:relative">
        <span class="nav-icon">🚛</span> <?= $lang['submit_request'] ?>
        <?php
          $pending_req_count = count(array_filter($my_requests, fn($r) => in_array($r['status'],['pending','assigned','approved'])));
          if($pending_req_count > 0):
        ?><span class="notif-badge"><?= $pending_req_count > 99 ? '99+' : $pending_req_count ?></span><?php endif; ?>
      </a>
      <a href="/disbasura/resident/tracker.php">
        <span class="nav-icon">📍</span> <?= $lang['live_tracker'] ?>
      </a>
      <a href="/disbasura/resident/notifications.php" style="position:relative">
        <span class="nav-icon">🔔</span> <?= $lang['notifications'] ?>
        <?php if($unread>0): ?><span class="notif-badge"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
      </a>
    </nav>

    <!-- Bottom controls -->
    <div class="sidebar-bottom">
      <!-- Row: dark mode + language side by side -->
      <div class="sb-bottom-row">
        <form method="POST" style="display:contents">
          <input type="hidden" name="toggle_dark" value="1">
          <button type="submit" class="sb-icon-btn">
            <?= $prefs['dark_mode'] ? '☀️ Light' : '🌙 Dark' ?>
          </button>
        </form>
        <form method="POST" style="display:contents">
          <input type="hidden" name="save_lang" value="1">
          <select name="language" onchange="this.form.submit()" class="sb-lang-select">
            <option value="en"  <?= $prefs['language']==='en' ?'selected':'' ?>>🇬🇧 EN</option>
            <option value="fil" <?= $prefs['language']==='fil'?'selected':'' ?>>🇵🇭 FIL</option>
          </select>
        </form>
      </div>
      <!-- Sign out -->
      <a href="/disbasura/logout.php?role=resident" class="signout-btn">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <?= $lang['sign_out'] ?>
      </a>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="res-main">

    <!-- Greeting -->
    <div class="page-greeting fade-up" style="margin-bottom:1.5rem">
      <h1><?= $lang['welcome'] ?>, <?= e($_SESSION['full_name']) ?> 👋</h1>
      <p>📍 <?= e($sitio) ?></p>
    </div>

    <!-- Announcements -->
    <?php if($announcements): ?>
    <div class="panel fade-up" style="border-left:4px solid #f5a623;margin-bottom:1.25rem">
      <div class="panel-hd"><h3>📢 <?= $lang['announcements'] ?></h3></div>
      <?php foreach($announcements as $ann): ?>
      <div class="ann-item">
        <strong style="font-size:.85rem"><?= e($ann['title']) ?></strong>
        <p style="font-size:.8rem;color:#7aab8a;margin-top:.15rem"><?= e($ann['message']) ?></p>
        <span style="font-size:.7rem;color:#aac4b2"><?= fmt_date($ann['created_at']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Collection Schedule: only dates created by the admin for this sitio -->
    <div class="panel fade-up">
      <div class="panel-hd">
        <h3>&#128197; <?= e($lang['my_schedule']) ?></h3>
        <span style="font-size:.75rem;color:#7aab8a">Schedules set by your admin</span>
      </div>
      <?php if ($upcoming_schedules): ?>
      <div style="overflow-x:auto;padding:.25rem .85rem .85rem">
        <table class="sched-table">
          <thead><tr><th>Date &amp; Time</th><th>Waste Type</th><th>Collector</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($upcoming_schedules as $s): ?>
          <tr>
            <td><?= fmt_date($s['scheduled_at']) ?></td>
            <td><?= e($s['waste_type']) ?></td>
            <td style="color:#7aab8a"><?= e($s['collector_name'] ?? 'Not assigned') ?></td>
            <td><span class="badge <?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div style="padding:2rem;text-align:center;color:#7aab8a;font-size:.85rem">No collection schedules have been set for your sitio yet.</div>
      <?php endif; ?>
    </div>
    <!-- ── One-time Schedules for this sitio (admin-assigned) ── -->
    <?php if($schedules): ?>
    <div class="panel fade-up">
      <div class="panel-hd">
        <h3>🗓 <?= $lang['recent_schedules'] ?> <?= e($sitio) ?></h3>
      </div>
      <div style="overflow-x:auto">
        <table class="sched-table">
          <thead><tr>
            <th>Date & Time</th>
            <th>Type</th>
            <th>Collector</th>
            <th>Status</th>
            <th></th>
          </tr></thead>
          <tbody>
          <?php foreach($schedules as $s): ?>
          <tr>
            <td><?= fmt_date($s['scheduled_at']) ?></td>
            <td><?= e($s['waste_type']) ?></td>
            <td style="color:#7aab8a"><?= e($s['collector_name'] ?? '—') ?></td>
            <td><span class="badge <?= $s['status'] ?>"><?= e($s['status']) ?></span></td>
            <td style="white-space:nowrap;vertical-align:middle">
              <div style="display:flex;flex-wrap:wrap;gap:.3rem;align-items:center">
              <?php if($s['status']==='completed'): ?>
                <?php if(!in_array($s['id'],$my_feedback_ids)): ?>
                <button onclick="openFeedback(<?= $s['id'] ?>)" style="font-size:.73rem;background:#fff3e0;color:#f5a623;border:1px solid #fdd9a0;border-radius:20px;padding:.25rem .65rem;cursor:pointer;font-family:inherit;font-weight:700">⭐ Rate</button>
                <?php else: ?><span style="font-size:.72rem;color:#f5a623;font-weight:700;background:#fff3e0;padding:.2rem .6rem;border-radius:20px;border:1px solid #fdd9a0">⭐ Rated</span><?php endif; ?>
                <?php if(!in_array($s['id'],$my_dispute_ids)): ?>
                <a href="/disbasura/resident/dispute.php?sid=<?= $s['id'] ?>" style="font-size:.73rem;background:#fdecea;color:#c0392b;border:1px solid #f5c6c6;border-radius:20px;padding:.25rem .65rem;text-decoration:none;font-weight:700">⚠️ Dispute</a>
                <?php endif; ?>
              <?php endif; ?>
              <?php
                // Allow proof for completed, unverified, assigned, scheduled
                $proof_ok = in_array($s['status'], ['completed','unverified','assigned','scheduled']);
                $has_proof = !empty($s['resident_proof_photo']);
              ?>
              <?php if($proof_ok && !$has_proof): ?>
              <button onclick="openResProof(<?= $s['id'] ?>,'<?= htmlspecialchars($s['waste_type'],ENT_QUOTES) ?> — <?= htmlspecialchars($s['sitio'] ?? $sitio,ENT_QUOTES) ?>')"
                style="font-size:.75rem;background:#1e6b3c;color:#fff;border:none;border-radius:20px;padding:.3rem .8rem;cursor:pointer;font-family:inherit;font-weight:700;display:inline-flex;align-items:center;gap:.3rem">
                📷 Submit Proof
              </button>
              <?php elseif($has_proof): ?>
              <span style="font-size:.72rem;color:#1e6b3c;font-weight:700;background:#e8f5ee;padding:.25rem .65rem;border-radius:20px;border:1px solid #b6d9c3">📷 Proof Sent</span>
              <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── My Pickup Requests ── -->
    <div class="panel fade-up">
      <div class="panel-hd">
        <h3><?= $lang['my_requests'] ?></h3>
        <a href="/disbasura/resident/submit-request.php" style="font-size:.78rem;color:#1e6b3c;font-weight:700;text-decoration:none">+ <?= $lang['new_request'] ?></a>
      </div>
      <?php if($my_requests): foreach($my_requests as $r): ?>
      <div class="req-item">
        <div class="req-meta" style="flex:1;min-width:0">
          <strong><?= e($r['waste_type']) ?></strong>
          <span>📍 <?= e($r['sitio']) ?> · 📅 <?= fmt_date($r['preferred_date']) ?></span>
          <?php if($r['collector_name']): ?><span>🚛 <?= e($r['collector_name']) ?></span><?php endif; ?>
          <?php if(!empty($r['note'])): ?><span>💬 <?= e($r['note']) ?></span><?php endif; ?>
        </div>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;flex-shrink:0">
          <span class="badge <?= $r['status'] ?>"><?= e($r['status']) ?></span>
          <?php if($r['status']==='pending'): ?>
          <form method="POST" onsubmit="return confirm('Cancel this request?')" style="display:inline">
            <input type="hidden" name="cancel_request" value="1">
            <input type="hidden" name="rid" value="<?= $r['id'] ?>">
            <button type="submit" class="btn-reject" style="font-size:.72rem;padding:.25rem .6rem"><?= $lang['cancel'] ?></button>
          </form>
          <?php endif; ?>
          <?php if($r['status']==='completed'): ?>
            <?php $has_req_proof = !empty($r['resident_proof_photo']); ?>
            <?php if(!$has_req_proof): ?>
            <button onclick="openReqProof(<?= $r['id'] ?>,'<?= e($r['waste_type']) ?> — <?= e($r['sitio']) ?>')"
              style="font-size:.75rem;background:#1e6b3c;color:#fff;border:none;border-radius:20px;padding:.3rem .8rem;cursor:pointer;font-family:inherit;font-weight:700;display:inline-flex;align-items:center;gap:.3rem">
              📷 Submit Proof
            </button>
            <?php else: ?>
            <span style="font-size:.72rem;color:#1e6b3c;font-weight:700;background:#e8f5ee;padding:.25rem .65rem;border-radius:20px;border:1px solid #b6d9c3">📷 Proof Sent</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div style="padding:2rem;text-align:center;color:#7aab8a;font-size:.85rem"><?= $lang['no_requests'] ?> <a href="/disbasura/resident/submit-request.php" style="color:#1e6b3c"><?= $lang['submit_request'] ?>?</a></div>
      <?php endif; ?>
    </div>

  </main>
</div><!-- /.res-layout -->
<!-- Mobile overlay + hamburger at root level -->
<div class="res-mob-overlay" id="resMobOverlay"></div>
<button class="res-ham-btn" id="resHamBtn" aria-label="Open menu">
  <span></span><span></span><span></span>
</button>

<!-- Resident Proof Modal -->
<div id="resProofModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(5px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:1.75rem;width:100%;max-width:440px;box-shadow:0 30px 80px rgba(0,0,0,.3)">
    <div style="text-align:center;margin-bottom:1.1rem">
      <div style="width:56px;height:56px;border-radius:50%;background:#e8f5ee;display:flex;align-items:center;justify-content:center;margin:0 auto .65rem;font-size:1.7rem">📷</div>
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800">Submit Proof Photo</h3>
      <p id="resProofLabel" style="font-size:.8rem;color:#7aab8a;margin-top:.2rem"></p>
    </div>
    <div style="background:#f5faf7;border-radius:10px;padding:.7rem .9rem;margin-bottom:1rem;font-size:.8rem;color:#4a7a5a;line-height:1.5">
      📌 Take a photo showing your garbage was or was <strong>not</strong> collected. This helps the barangay verify if collectors are doing their job.
    </div>
    <form method="POST" enctype="multipart/form-data" id="resProofForm">
      <input type="hidden" name="submit_resident_proof" value="1">
      <input type="hidden" name="schedule_id" id="resProofSchedId">
      <div id="resDropZone" onclick="document.getElementById('resProofFile').click()" style="border:2px dashed #b6d9c3;border-radius:12px;padding:1.5rem;text-align:center;cursor:pointer;margin-bottom:.75rem;transition:all .15s" onmouseenter="this.style.background='#f0faf4';this.style.borderColor='#1e6b3c'" onmouseleave="this.style.background='transparent';this.style.borderColor='#b6d9c3'">
        <div style="font-size:2rem;margin-bottom:.4rem">📸</div>
        <p style="font-size:.83rem;color:#7aab8a;font-weight:600">Tap to take photo or choose from gallery</p>
        <p style="font-size:.74rem;color:#aac4b2;margin-top:.15rem">JPG, PNG, WEBP accepted</p>
        <input type="file" name="resident_proof" id="resProofFile" accept="image/*" capture="environment" required style="display:none" onchange="previewResProof(this)">
      </div>
      <img id="resProofPreview" src="" alt="Preview" style="display:none;width:100%;border-radius:10px;max-height:220px;object-fit:cover;margin-bottom:.75rem;border:1.5px solid #b6d9c3"/>
      <div style="display:flex;gap:.65rem">
        <button type="button" onclick="closeResProof()" style="flex:1;padding:.78rem;border:1.5px solid #d4e6db;border-radius:10px;background:#fff;cursor:pointer;font-family:inherit;font-size:.88rem;font-weight:600;color:#4a7a5a">Cancel</button>
        <button type="submit" id="resProofSubmitBtn" style="flex:2;padding:.78rem;background:#1e6b3c;color:#fff;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Submit to Admin
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:400px;box-shadow:0 30px 80px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;margin-bottom:.5rem">⭐ Rate this Collection</h3>
    <p style="font-size:.83rem;color:#7aab8a;margin-bottom:1.25rem">How was the garbage collection service?</p>
    <input type="hidden" id="fb_schedule_id" value="">
    <div style="display:flex;gap:.25rem;justify-content:center;margin-bottom:1.25rem" id="starRow">
      <?php for($i=1;$i<=5;$i++): ?>
      <button class="star-btn" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)">★</button>
      <?php endfor; ?>
    </div>
    <div style="text-align:center;font-size:.8rem;color:#7aab8a;margin-bottom:1rem" id="ratingLabel">Tap a star to rate</div>
    <textarea id="fb_comment" rows="3" placeholder='e.g. "Truck came on time!"'
      style="width:100%;border:1.5px solid #d4e6db;border-radius:8px;padding:.75rem 1rem;font-family:inherit;font-size:.88rem;resize:none;margin-bottom:.75rem"></textarea>
    <div style="display:flex;gap:.65rem">
      <button onclick="closeFeedback()" style="flex:1;padding:.75rem;border:1.5px solid #d4e6db;border-radius:8px;background:#fff;cursor:pointer;font-family:inherit">Cancel</button>
      <button onclick="submitFeedback()" style="flex:1;padding:.75rem;background:#1e6b3c;color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;cursor:pointer"><?= $lang['submit_feedback'] ?></button>
    </div>
  </div>
</div>

<script>
function openResProof(sid, label) {
  document.getElementById('resProofSchedId').value = sid;
  document.getElementById('resProofLabel').textContent = label;
  document.getElementById('resProofPreview').style.display = 'none';
  document.getElementById('resDropZone').style.display = 'block';
  document.getElementById('resProofFile').value = '';
  const btn = document.getElementById('resProofSubmitBtn');
  btn.innerHTML = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Submit to Admin';
  btn.disabled = false;
  document.getElementById('resProofModal').style.display = 'flex';
}
function closeResProof() { document.getElementById('resProofModal').style.display = 'none'; }
function previewResProof(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('resProofPreview');
      img.src = e.target.result;
      img.style.display = 'block';
      document.getElementById('resDropZone').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
document.getElementById('resProofForm').addEventListener('submit', function(){
  var btn = document.getElementById('resProofSubmitBtn');
  btn.innerHTML = '⏳ Uploading…';
  btn.disabled = true;
});
document.getElementById('resProofModal').addEventListener('click', function(e){ if(e.target===this)closeResProof(); });
let currentRating = 0;
const labels = ['','Poor 😞','Fair 😐','Good 🙂','Great 😊','Excellent! 🌟'];
function openFeedback(sid){
  document.getElementById('fb_schedule_id').value=sid;
  currentRating=0;
  document.querySelectorAll('.star-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('ratingLabel').textContent='Tap a star to rate';
  document.getElementById('fb_comment').value='';
  document.getElementById('feedbackModal').style.display='flex';
}
function closeFeedback(){ document.getElementById('feedbackModal').style.display='none'; }
function setRating(val){
  currentRating=val;
  document.querySelectorAll('.star-btn').forEach(b=>b.classList.toggle('active',parseInt(b.dataset.val)<=val));
  document.getElementById('ratingLabel').textContent=labels[val];
}
function submitFeedback(){
  if(!currentRating){ alert('Please select a star rating.'); return; }
  const sid=document.getElementById('fb_schedule_id').value;
  const comment=document.getElementById('fb_comment').value;
  fetch('/disbasura/api/feedback.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({schedule_id:parseInt(sid),rating:currentRating,comment})
  }).then(r=>r.json()).then(d=>{
    if(d.ok){ closeFeedback(); location.reload(); }
    else alert(d.error||'Failed to submit.');
  }).catch(()=>alert('Network error.'));
}
document.getElementById('feedbackModal').addEventListener('click',function(e){ if(e.target===this)closeFeedback(); });
</script>

<!-- Resident Request Proof Modal -->
<div id="reqProofModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(5px);z-index:400;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:1.75rem;width:100%;max-width:440px;box-shadow:0 30px 80px rgba(0,0,0,.3)">
    <div style="text-align:center;margin-bottom:1.1rem">
      <div style="width:56px;height:56px;border-radius:50%;background:#e8f5ee;display:flex;align-items:center;justify-content:center;margin:0 auto .65rem;font-size:1.7rem">📷</div>
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800">Submit Proof for Pickup</h3>
      <p id="reqProofLabel" style="font-size:.8rem;color:#7aab8a;margin-top:.2rem"></p>
    </div>
    <div style="background:#f5faf7;border-radius:10px;padding:.7rem .9rem;margin-bottom:1rem;font-size:.8rem;color:#4a7a5a;line-height:1.5">
      📌 Take a photo showing your garbage was collected (or not). This helps the admin verify if the collector completed the job properly.
    </div>
    <form method="POST" enctype="multipart/form-data" id="reqProofForm">
      <input type="hidden" name="submit_request_proof" value="1">
      <input type="hidden" name="request_id" id="reqProofId">
      <div id="reqProofDropZone" onclick="document.getElementById('reqProofFile').click()" style="border:2px dashed #b6d9c3;border-radius:12px;padding:1.5rem;text-align:center;cursor:pointer;margin-bottom:.75rem;transition:all .15s" onmouseenter="this.style.background='#f0faf4';this.style.borderColor='#1e6b3c'" onmouseleave="this.style.background='transparent';this.style.borderColor='#b6d9c3'">
        <div style="font-size:2rem;margin-bottom:.4rem">📸</div>
        <p style="font-size:.83rem;color:#7aab8a;font-weight:600">Tap to take photo or choose from gallery</p>
        <p style="font-size:.74rem;color:#aac4b2;margin-top:.15rem">JPG, PNG, WEBP accepted</p>
        <input type="file" name="request_proof" id="reqProofFile" accept="image/*" capture="environment" required style="display:none" onchange="previewReqResProof(this)">
      </div>
      <img id="reqResProofPreview" src="" alt="Preview" style="display:none;width:100%;border-radius:10px;max-height:220px;object-fit:cover;margin-bottom:.75rem;border:1.5px solid #b6d9c3"/>
      <div style="display:flex;gap:.65rem">
        <button type="button" onclick="closeReqProof()" style="flex:1;padding:.78rem;border:1.5px solid #d4e6db;border-radius:10px;background:#fff;cursor:pointer;font-family:inherit;font-size:.88rem;font-weight:600;color:#4a7a5a">Cancel</button>
        <button type="submit" id="reqProofSubmitBtn2" style="flex:2;padding:.78rem;background:#1e6b3c;color:#fff;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Submit to Admin
        </button>
      </div>
    </form>
  </div>
</div>
<script>
function openReqProof(rid, label) {
  document.getElementById('reqProofId').value = rid;
  document.getElementById('reqProofLabel').textContent = label;
  document.getElementById('reqResProofPreview').style.display = 'none';
  document.getElementById('reqProofDropZone').style.display = 'block';
  document.getElementById('reqProofFile').value = '';
  var btn = document.getElementById('reqProofSubmitBtn2');
  btn.innerHTML = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Submit to Admin';
  btn.disabled = false;
  document.getElementById('reqProofModal').style.display = 'flex';
}
function closeReqProof() { document.getElementById('reqProofModal').style.display = 'none'; }
function previewReqResProof(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('reqResProofPreview');
      img.src = e.target.result;
      img.style.display = 'block';
      document.getElementById('reqProofDropZone').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
document.getElementById('reqProofForm').addEventListener('submit', function(){
  var btn = document.getElementById('reqProofSubmitBtn2');
  btn.innerHTML = '⏳ Uploading…'; btn.disabled = true;
});
document.getElementById('reqProofModal').addEventListener('click', function(e){ if(e.target===this)closeReqProof(); });
</script>
<script>
// ── Resident mobile sidebar ──────────────────────
var _rSb  = document.querySelector(".res-sidebar");
var _rHam = document.getElementById("resHamBtn");
var _rOv  = document.getElementById("resMobOverlay");
var _rTools = document.createElement("div");
_rTools.className = "sidebar-layout-tools";
var _rBrand = _rSb ? _rSb.querySelector(".sidebar-brand") : null;
function putResToggleOutside(){ if(_rHam){_rTools.appendChild(_rHam);document.querySelector(".res-main")?.prepend(_rTools);} }
function putResToggleInside(){ if(_rBrand && _rHam)_rBrand.appendChild(_rHam); }
if(window.innerWidth <= 768) putResToggleOutside(); else putResToggleInside();
function openResSb()  { if(_rSb)_rSb.classList.add("open");  if(_rOv)_rOv.classList.add("show"); putResToggleInside(); }
function closeResSb() { if(_rSb)_rSb.classList.remove("open"); if(_rOv)_rOv.classList.remove("show"); putResToggleOutside(); }
if(_rHam) _rHam.onclick = function(){
  if(window.innerWidth > 768){ var hidden=document.getElementById("appRoot").classList.toggle("sidebar-collapsed"); hidden?putResToggleOutside():putResToggleInside(); return; }
  _rSb&&_rSb.classList.contains("open") ? closeResSb() : openResSb();
};
if(_rOv)  _rOv.onclick  = closeResSb;
document.addEventListener("keydown", function(e){ if(e.key==="Escape") closeResSb(); });
</script>
</body>
</html>
