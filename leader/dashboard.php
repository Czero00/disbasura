<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] !== 'leader') { header('Location: /disbasura/resident/dashboard.php'); exit; }
$db = get_db(); $sitio = $_SESSION['sitio']??''; $uid=$_SESSION['user_id'];
$residents     = $db->prepare("SELECT * FROM users WHERE sitio=? AND role IN ('resident','leader') ORDER BY full_name"); $residents->execute([$sitio]); $residents=$residents->fetchAll();
$sitio_requests= $db->prepare("SELECT r.*,u.full_name,c.full_name as collector_name FROM requests r JOIN users u ON r.resident_id=u.id LEFT JOIN collectors c ON r.collector_id=c.id WHERE r.sitio=? ORDER BY r.created_at DESC"); $sitio_requests->execute([$sitio]); $sitio_requests=$sitio_requests->fetchAll();
$schedules     = $db->prepare("SELECT * FROM schedules WHERE sitio=? AND DATE(scheduled_at)>=CURDATE() ORDER BY scheduled_at ASC LIMIT 5"); $schedules->execute([$sitio]); $schedules=$schedules->fetchAll();
$unread        = get_unread_count($uid);
$pending_count = $db->prepare("SELECT COUNT(*) FROM requests WHERE sitio=? AND status='pending'"); $pending_count->execute([$sitio]); $pending_count=(int)$pending_count->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Leader Dashboard — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
</head>
<body>
<?php
// Inline leader sidebar
$full_name = htmlspecialchars($_SESSION['full_name']);
$initial   = strtoupper(substr($_SESSION['full_name'],0,1));
?>
<div class="app active" id="appRoot">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <svg viewBox="0 0 72 72" fill="none" width="34" height="34" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="ldrRingG" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#5dd96b"/>
            <stop offset="50%"  stop-color="#22a94a"/>
            <stop offset="100%" stop-color="#0d6e30"/>
          </linearGradient>
          <linearGradient id="ldrLeafG" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#7de87a"/>
            <stop offset="100%" stop-color="#1a8a38"/>
          </linearGradient>
        </defs>
        <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#ldrRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
        <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#ldrRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
        <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#ldrRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#ldrRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#ldrLeafG)"/>
        <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#ldrLeafG)"/>
        <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
      </svg>
      <div class="sidebar-brand-text"><h2>DisBasura</h2><span>Sitio Leader</span></div>
    </div>
    <nav class="sidebar-nav">
      <a class="nav-item active" href="/disbasura/leader/dashboard.php" data-tip="Dashboard"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span class="nav-label">Dashboard</span></a>
      <a class="nav-item" href="/disbasura/leader/requests.php" data-tip="Sitio Requests"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span class="nav-label">Sitio Requests</span></a>
      <a class="nav-item" href="/disbasura/leader/submit-request.php" data-tip="Submit for Resident"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/></svg><span class="nav-label">Submit for Resident</span></a>
      <a class="nav-item" href="/disbasura/leader/notifications.php" data-tip="Notifications"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><span class="nav-label">Notifications</span></a>
      <a class="nav-item" href="/disbasura/leader/schedules.php" data-tip="Sitio Schedule"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="nav-label">Sitio Schedule</span></a>
      <a class="nav-item" href="/disbasura/leader/tracker.php" data-tip="Live Tracker"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg><span class="nav-label">Live Tracker</span></a>
      <a class="nav-item" href="/disbasura/leader/profile.php" data-tip="My Profile"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="nav-label">My Profile</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="avatar" style="background:var(--orange)"><?= $initial ?></div>
        <div class="sidebar-user-info"><strong><?= $full_name ?></strong><span><?= htmlspecialchars($sitio) ?></span></div>
      </div>
      <a href="/disbasura/logout.php?role=resident" class="signout-btn"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="nav-label">Sign Out</span></a>
    </div>
  </aside>
  <main class="main">
    <div class="topbar">
      <span style="background:#fff3e0;color:var(--orange);border:1px solid #ffe0b2;border-radius:20px;padding:.25rem .85rem;font-size:.78rem;font-weight:600">⭐ Sitio Leader — <?= htmlspecialchars($sitio) ?></span>
      <a class="notif-btn" href="/disbasura/leader/notifications.php" style="margin-left:auto"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg></a>
    </div>
    <div class="page-content active">
      <div class="page-header"><h1>Sitio Leader Dashboard</h1><p><?= htmlspecialchars($sitio) ?> — Managing your community</p></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon green"></div><div><div class="stat-val"><?= count($residents) ?></div><div class="stat-label">Residents in Sitio</div></div></div>
        <div class="stat-card"><div class="stat-icon orange"></div><div><div class="stat-val"><?= $pending_count ?></div><div class="stat-label">Pending Requests</div></div></div>
        <div class="stat-card"><div class="stat-icon blue"></div><div><div class="stat-val"><?= count($schedules) ?></div><div class="stat-label">Upcoming Schedules</div></div></div>
        <div class="stat-card"><div class="stat-icon teal"></div><div><div class="stat-val"><?= count($sitio_requests) ?></div><div class="stat-label">Total Requests</div></div></div>
      </div>
      <div class="two-col">
        <div class="panel">
          <div class="panel-header"><h3>Requests in <?= htmlspecialchars($sitio) ?></h3><a class="manage-link" href="/disbasura/leader/requests.php">View All</a></div>
          <?php if($sitio_requests): foreach(array_slice($sitio_requests,0,5) as $r): ?>
          <div class="request-item"><div class="request-item-info"><strong><?= htmlspecialchars($r['full_name']) ?></strong><span><?= htmlspecialchars($r['waste_type']) ?> · <?= fmt_date($r['preferred_date']) ?></span></div><span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span></div>
          <?php endforeach; else: ?><div class="empty-state"><p>No requests yet.</p></div><?php endif; ?>
        </div>
        <div class="panel">
          <div class="panel-header"><h3>Upcoming Schedules</h3></div>
          <?php if($schedules): foreach($schedules as $s): ?>
          <div class="request-item"><div class="request-item-info"><strong><?= htmlspecialchars($s['waste_type']) ?></strong><span><?= fmt_date($s['scheduled_at']) ?></span></div><span class="badge scheduled">scheduled</span></div>
          <?php endforeach; else: ?><div class="empty-state"><p>No upcoming schedules.</p></div><?php endif; ?>
        </div>
      </div>
    </div>
    <footer class="site-footer">
      <div class="footer-logo">DisBasura</div><br>
      Smart Sitio-Based Garbage Collection System · <strong>UC</strong><br>
      Developed by <strong>DisBasura Capstone Project</strong> · 2026
    </footer>
  </main>
</div>
<script src="/disbasura/assets/js/sidebar-toggle.js"></script>
</body>
</html>
