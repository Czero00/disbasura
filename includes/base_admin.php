<?php
function render_admin_header(string $active = '', int $unread = 0, string $title = 'DisBasura Admin'): void {
    $nav = [
        'dashboard'     => ['Dashboard',      '/disbasura/admin/dashboard.php',
            '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
        'schedules'     => ['Schedules',      '/disbasura/admin/schedules.php',
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        'requests'      => ['Requests',       '/disbasura/admin/requests.php',
            '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>'],
        'collectors'    => ['Collectors',     '/disbasura/admin/collectors.php',
            '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>'],
        'performance'   => ['Performance',    '/disbasura/admin/performance.php',
            '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
        'residents'     => ['Residents',      '/disbasura/admin/residents.php',
            '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
        'sitios'        => ['Sitios',         '/disbasura/admin/sitios.php',
            '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>'],
        'announcements' => ['Announcements',  '/disbasura/admin/announcements.php',
            '<path d="M22 17H2a3 3 0 000 6h20"/><path d="M2 17l10-9 10 9"/>'],
        'history'       => ['History',        '/disbasura/admin/history.php',
            '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
        'disputes'      => ['Disputes',       '/disbasura/admin/disputes.php',
            '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>'],
        'tracker'       => ['Truck Tracker',  '/disbasura/admin/tracker.php',
            '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/>'],
        'activity'      => ['Activity Log',   '/disbasura/admin/activity-log.php',
            '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'reports'       => ['Reports',        '/disbasura/admin/reports.php',
            '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
        'notifications' => ['Notifications',  '/disbasura/admin/notifications.php',
            '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>'],
    ];

    // ── Per-page notification counts ─────────────────────────────
    $db  = get_db();
    $pageCounts = [];
    try {
        $pageCounts['requests']      = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();
        $pageCounts['disputes']      = (int)$db->query("SELECT COUNT(*) FROM disputes WHERE status='pending'")->fetchColumn();
        $pageCounts['notifications'] = $unread;
        $pageCounts['collectors']    = (int)$db->query("SELECT COUNT(*) FROM collectors WHERE status IN ('sick','unavailable')")->fetchColumn();
        $pageCounts['schedules']     = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status='unverified'")->fetchColumn();
        $pageCounts['residents']     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='resident' AND DATE(created_at)=CURDATE()")->fetchColumn();
    } catch (Exception $e) {}

    $full_name = e($_SESSION['admin_name'] ?? 'Admin');
    $initial   = strtoupper(substr($full_name, 0, 1));
    $ud        = $unread > 0 ? 'flex' : 'none';

    $admin_photo = '';
    try {
        $ap = get_db()->prepare("SELECT profile_photo FROM administrators WHERE id=?");
        $ap->execute([$_SESSION['admin_id'] ?? 0]);
        $admin_photo = (string)($ap->fetchColumn() ?: '');
    } catch(Exception $e) {}
    $avatar_html = $admin_photo
        ? '<img src="/disbasura/uploads/'.e($admin_photo).'" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.25);flex-shrink:0"/>'
        : '<div class="avatar" style="flex-shrink:0">'.$initial.'</div>';

    $prefs = function_exists('get_preferences') ? get_preferences($_SESSION['admin_id'] ?? 0) : ['dark_mode'=>0];
    $dark  = $prefs['dark_mode'] ? 'data-dark="1"' : '';

    echo '<!DOCTYPE html>
<html lang="en" '.($prefs['dark_mode']?'class="dark"':'').'>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>'.e($title).'</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
  <style>
    /* ── Hamburger — position:fixed top-left, same as resident panel ── */
    .mob-toggle{display:none;flex-direction:column;justify-content:center;gap:5px;position:fixed;top:12px;left:12px;z-index:1000;width:40px;height:40px;padding:7px;background:#2d8653;border:none;border-radius:10px;cursor:pointer;box-shadow:0 3px 10px rgba(45,134,83,.45)}
    .mob-toggle span{display:block;width:100%;height:2.5px;background:#fff;border-radius:2px;transition:all .25s ease}
    /* ── Overlay ── */
    .mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:998;cursor:pointer}
    .mob-overlay.show{display:block!important}
    /* ── Mobile breakpoint ── */
    @media(max-width:768px){
      .mob-toggle{display:flex!important}
      .topbar{padding:.65rem 1rem .65rem 62px!important;gap:.65rem}
      .sidebar{position:fixed!important;left:-270px;top:0;height:100vh;z-index:999;transition:left .3s cubic-bezier(.4,0,.2,1);box-shadow:none;overflow-y:auto}
      .sidebar.open{left:0!important;box-shadow:6px 0 30px rgba(0,0,0,.4)}
      .main{width:100%!important;margin-left:0!important}
      .page-content{padding:1rem!important}
      .stats-grid{grid-template-columns:1fr 1fr!important}
      .two-col,.collectors-grid,.charts-grid,.reports-stats{grid-template-columns:1fr!important}
    }
    .nav-badge{background:var(--red);color:#fff;border-radius:20px;padding:.1rem .45rem;font-size:.65rem;font-weight:800;margin-left:auto;min-width:18px;text-align:center}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
    html.dark{--bg-page:#0f1a14;--bg-card:#1a2e22;--text-dark:#e8f5ee;--text-mid:#8baa96;--text-light:#5a7a65;--border:#2d4a36;--border-light:#243d2c;--shadow-sm:0 2px 8px rgba(0,0,0,.4)}
    html.dark .sidebar{background:linear-gradient(180deg,#0a1f13 0%,#0f2d1e 100%)}
    html.dark .main{background:var(--bg-page)}
    html.dark .panel,html.dark .stat-card,html.dark .auth-card{background:var(--bg-card);border-color:var(--border)}
    html.dark input,html.dark select,html.dark textarea{background:#1a2e22;border-color:var(--border);color:var(--text-dark)}
    html.dark .topbar{background:var(--bg-card);border-color:var(--border)}
    html.dark table{background:var(--bg-card)}
    html.dark tr{border-color:var(--border) !important}
    html.dark .sched-card{background:var(--bg-card) !important;border-color:var(--border) !important}
    html.dark .modal,html.dark [style*="background:#fff"]{background:var(--bg-card) !important;color:var(--text-dark) !important}
    .dark-toggle{display:flex;align-items:center;gap:.5rem;padding:.45rem .85rem;border-radius:8px;border:1.5px solid var(--border);background:transparent;cursor:pointer;font-size:.78rem;font-weight:600;color:var(--text-mid);font-family:inherit;transition:all .18s;margin-top:.5rem;width:100%}
    .dark-toggle:hover{border-color:var(--green-main);color:var(--green-main)}
  </style>
</head>
<body>
<div class="app active" id="appRoot">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <svg viewBox="0 0 72 72" fill="none" width="34" height="34" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="sbRingG" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#5dd96b"/>
            <stop offset="50%"  stop-color="#22a94a"/>
            <stop offset="100%" stop-color="#0d6e30"/>
          </linearGradient>
          <linearGradient id="sbLeafG" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#7de87a"/>
            <stop offset="100%" stop-color="#1a8a38"/>
          </linearGradient>
        </defs>
        <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#sbRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
        <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#sbRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
        <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#sbRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#sbRingG)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#sbLeafG)"/>
        <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#sbLeafG)"/>
        <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
      </svg>
      <div class="sidebar-brand-text"><h2>DisBasura</h2><span>Admin Panel</span></div>
    </div>
    <nav class="sidebar-nav">';

    foreach ($nav as $key => [$label, $url, $icon]) {
        $cls   = $key === $active ? ' active' : '';
        $cnt   = $pageCounts[$key] ?? 0;
        $badge = $cnt > 0 ? "<span class='nav-badge'>".($cnt > 99 ? '99+' : $cnt)."</span>" : '';
        echo "<a class='nav-item$cls' href='$url'><svg fill='none' stroke='currentColor' stroke-width='2' viewBox='0 0 24 24' width='18' height='18'>$icon</svg><span class='nav-label'>$label</span>$badge</a>\n";
    }

    $dark_icon  = $prefs['dark_mode'] ? '☀️' : '🌙';
    $dark_label = $prefs['dark_mode'] ? 'Light Mode' : 'Dark Mode';

    echo '    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <a href="/disbasura/admin/profile.php" style="display:flex;align-items:center;gap:.75rem;text-decoration:none;flex:1;min-width:0">
          '.$avatar_html.'
          <div class="sidebar-user-info"><strong>'.$full_name.'</strong><span>Administrator</span></div>
        </a>
      </div>
      <div style="display:flex;align-items:center;gap:.4rem;margin-top:.6rem">
        <button onclick="toggleDark()" id="darkBtn"
          style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;padding:.42rem .55rem;border:1.5px solid var(--border);border-radius:8px;background:transparent;cursor:pointer;font-size:.74rem;font-weight:600;color:var(--text-mid);font-family:inherit;transition:all .18s;white-space:nowrap">
          '.$dark_icon.' <span class="nav-label">'.($prefs['dark_mode'] ? 'Light' : 'Dark').'</span>
        </button>
        <a href="/disbasura/logout.php?role=admin"
          style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;padding:.42rem .55rem;border:1.5px solid rgba(224,82,82,.3);border-radius:8px;background:transparent;font-size:.74rem;font-weight:600;color:var(--red);text-decoration:none;transition:all .18s;white-space:nowrap">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span class="nav-label">Sign Out</span>
        </a>
      </div>
    </div>
  </aside>
  <main class="main" id="mainArea">
    <div class="topbar">
      <button class="mob-toggle" id="mobToggle" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <div style="margin-left:auto;position:relative;display:inline-flex">
        <a class="notif-btn" href="/disbasura/admin/notifications.php" title="Notifications" style="color:var(--text-mid)">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
          </svg>
        </a>
        <span id="topBadge" style="display:'.$ud.';position:absolute;top:-5px;right:-5px;background:var(--red);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.62rem;font-weight:800;align-items:center;justify-content:center;border:2px solid var(--bg-page)">'.$unread.'</span>
      </div>
    </div>
    <div class="page-content active">';
}

function render_admin_footer(): void {
    echo '    </div>
    <footer class="site-footer">
      <div class="footer-logo">
        <svg viewBox="0 0 260 220" fill="none" width="18" height="15">
          <rect x="104" y="84" width="52" height="52" rx="4" fill="none" stroke="var(--green-main)" stroke-width="4"/>
          <rect x="98"  y="72" width="64" height="10" rx="4" fill="none" stroke="var(--green-main)" stroke-width="4"/>
          <rect x="120" y="62" width="20" height="10" rx="4" fill="none" stroke="var(--green-main)" stroke-width="4"/>
        </svg>
        DisBasura
      </div>
      <div style="margin-top:.25rem">
        Smart Sitio-Based Garbage Collection System &nbsp;·&nbsp; <strong>UC Main — Cebu City</strong><br>
        Developed by <strong>DisBasura Capstone Project</strong> &nbsp;·&nbsp; 2026
      </div>
    </footer>
  </main>
  <!-- Mobile overlay — INSIDE appRoot so z-index works correctly -->
  <div class="mob-overlay" id="mobOverlay" onclick="closeSidebar()"></div>
</div>
<script>
// ── Mobile sidebar ──
(function() {
  var _sb = document.getElementById("sidebar");
  var _ov = document.getElementById("mobOverlay");

  function initMobileMenu() {
    if (window.innerWidth <= 768) {
      if (_sb) { _sb.classList.remove("open"); _sb.style.left = "-270px"; }
      if (_ov) { _ov.classList.remove("active"); _ov.style.display = "none"; }
    } else {
      if (_sb) { _sb.classList.add("open"); _sb.style.left = "0"; }
    }
  }

  initMobileMenu();
  window.addEventListener("resize", initMobileMenu);

  window.openSidebar = function() {
    if (_sb) { _sb.classList.add("open"); _sb.style.left = "0"; }
    if (_ov) { _ov.classList.add("active"); _ov.style.display = "block"; }
  };
  window.closeSidebar = function() {
    if (_sb) { _sb.classList.remove("open"); _sb.style.left = "-270px"; }
    if (_ov) { _ov.classList.remove("active"); _ov.style.display = "none"; }
  };
  window.toggleSidebar = function() {
    if (_sb && _sb.classList.contains("open")) { closeSidebar(); } else { openSidebar(); }
  };

  document.addEventListener("keydown", function(e){ if(e.key === "Escape") closeSidebar(); });
})();

// Dark mode
function toggleDark(){
  fetch("/disbasura/api/preferences.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({toggle_dark:1})})
    .then(()=>location.reload());
}
// Notification poll
(function poll(){
  setTimeout(function(){
    fetch("/disbasura/api/notifications.php").then(r=>r.json()).then(d=>{
      const b=document.getElementById("topBadge");
      if(b){b.textContent=d.count;b.style.display=d.count>0?"flex":"none";}
    }).catch(()=>{});
    poll();
  },30000);
})();
// Auto-flag check every 5 minutes
(function flagCheck(){
  setTimeout(function(){
    fetch("/disbasura/api/auto-flag.php").catch(()=>{});
    flagCheck();
  },300000);
})();
</script>
</body>
</html>';
}