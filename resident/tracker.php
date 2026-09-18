<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] === 'leader') { header('Location: /disbasura/leader/tracker.php'); exit; }

$db     = get_db();
$uid    = $_SESSION['user_id'];
$sitio  = $_SESSION['sitio'] ?? '';
$prefs  = get_preferences($uid);
$lang   = get_lang($uid);
$unread = get_unread_count($uid);
$dark   = $prefs['dark_mode'] ? 'dark' : '';
?>
<!DOCTYPE html>
<html lang="en" class="<?= $dark ?>">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= $lang['live_tracker'] ?> — DisBasura</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    html.dark{--bg-page:#0d1f14;--bg-card:#152b1e;--bg-sidebar:#0f2318;--text-dark:#e2f0e8;--text-mid:#8baa96;--border:#243d2c;--border-light:#1e3328}
    html.dark body{background:var(--bg-page);color:var(--text-dark)}
    html.dark .res-sidebar{background:var(--bg-sidebar)!important;border-color:var(--border)!important}
    html.dark .res-main{background:var(--bg-page)!important}
    html.dark .sidebar-nav a{color:#8baa96}
    html.dark .sidebar-nav a:hover,html.dark .sidebar-nav a.active{background:rgba(45,134,83,.25);color:#e2f0e8}
    body{margin:0;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg-page,#f4f9f6)}
    .res-layout{display:flex;min-height:100vh}
    .res-sidebar{width:240px;flex-shrink:0;background:#fff;border-right:1px solid #e4ede8;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-brand{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid #e4ede8}
    .sidebar-brand-inner{display:flex;align-items:center;gap:.65rem}
    .sidebar-brand h2{font-size:1rem;font-weight:800;color:#1a3a2a;line-height:1.1;margin:0}
    .sidebar-brand p{font-size:.7rem;color:#7aab8a;margin-top:.1rem}
    .sidebar-user{padding:.85rem 1.25rem;border-bottom:1px solid #e4ede8}
    .sidebar-user a{display:flex;align-items:center;gap:.65rem;text-decoration:none}
    .sidebar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1e5c38,#2d8653);display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:800;color:#fff;flex-shrink:0}
    .sidebar-user-info strong{font-size:.82rem;font-weight:700;color:#1a3a2a;display:block}
    .sidebar-user-info span{font-size:.72rem;color:#7aab8a}
    .sidebar-nav{flex:1;padding:.75rem}
    .sidebar-nav a{display:flex;align-items:center;gap:.65rem;padding:.62rem .85rem;border-radius:10px;text-decoration:none;font-size:.84rem;font-weight:600;color:#4a7a5a;margin-bottom:.2rem;position:relative}
    .sidebar-nav a:hover{background:#f0faf4;color:#1a3a2a}
    .sidebar-nav a.active{background:#e8f5ee;color:#1a3a2a}
    .sidebar-nav a .nav-icon{font-size:1rem;width:22px;text-align:center;flex-shrink:0}
    .sidebar-bottom{padding:.65rem .85rem;border-top:1px solid #e4ede8}
    .signout-btn{display:flex;align-items:center;gap:.5rem;padding:.6rem .85rem;font-size:.82rem;font-weight:600;color:#c0392b;text-decoration:none;border-radius:10px}
    .signout-btn:hover{background:#fdecea}
    .res-main{flex:1;padding:1.75rem 2rem;min-width:0}
    .page-greeting h1{font-size:1.3rem;font-weight:800;color:#1a3a2a;margin:0 0 .25rem}
    .page-greeting p{font-size:.85rem;color:#7aab8a;margin:0}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
    #map{width:100%;height:520px;border-radius:16px;border:1px solid #e4ede8;overflow:hidden;margin:1.25rem 0}
    .truck-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}
    .truck-card{background:#fff;border-radius:12px;border:1px solid #e4ede8;padding:1.1rem;display:flex;align-items:center;gap:.85rem}
    .truck-icon{width:44px;height:44px;border-radius:50%;background:#e8f5ee;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
  </style>
</head>
<body>
<div class="res-layout">
  <aside class="res-sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-brand-inner">
        <svg viewBox="0 0 72 72" fill="none" width="36" height="36" xmlns="http://www.w3.org/2000/svg">
          <defs><linearGradient id="resRingG3" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse"><stop offset="0%" stop-color="#5dd96b"/><stop offset="50%" stop-color="#22a94a"/><stop offset="100%" stop-color="#0d6e30"/></linearGradient></defs>
          <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#resRingG3)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M36 64 A28 28 0 0 1 8 36" stroke="url(#resRingG3)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#resRingG3)" stroke-width="6" fill="none" stroke-linecap="round"/>
          <path d="M8 36 A28 28 0 0 1 36 8" stroke="url(#resRingG3)" stroke-width="6" fill="none" stroke-linecap="round"/>
        </svg>
        <div><h2>DisBasura</h2><p>Resident Portal</p></div>
      </div>
    </div>
    <div class="sidebar-user">
      <a href="/disbasura/resident/profile.php">
        <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
        <div class="sidebar-user-info"><strong><?= e($_SESSION['full_name']) ?></strong><span>📍 <?= e($sitio) ?></span></div>
      </a>
    </div>
    <nav class="sidebar-nav">
      <a href="/disbasura/resident/dashboard.php"><span class="nav-icon">🏠</span> <?= $lang['dashboard'] ?></a>
      <a href="/disbasura/resident/submit-request.php"><span class="nav-icon">🚛</span> <?= $lang['submit_request'] ?></a>
      <a href="/disbasura/resident/tracker.php" class="active"><span class="nav-icon">📍</span> <?= $lang['live_tracker'] ?></a>
      <a href="/disbasura/resident/notifications.php" style="position:relative"><span class="nav-icon">🔔</span> <?= $lang['notifications'] ?>
        <?php if($unread>0): ?><span class="notif-badge"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
      </a>
    </nav>
    <div class="sidebar-bottom">
      <a href="/disbasura/logout.php?role=resident" class="signout-btn">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <?= $lang['sign_out'] ?>
      </a>
    </div>
  </aside>

  <main class="res-main">
    <div class="page-greeting" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div><h1>📍 <?= $lang['live_tracker'] ?></h1><p>See where collector trucks are right now</p></div>
      <span style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:#7aab8a">
        <span style="width:9px;height:9px;border-radius:50%;background:#2d8653;display:inline-block;animation:pulse 1.5s infinite"></span>Auto-refreshes every 15s
      </span>
    </div>

    <div id="map"></div>

    <div class="truck-list" id="truckList">
      <div class="empty-state" style="grid-column:1/-1;padding:2rem;text-align:center;color:#7aab8a">Loading truck locations…</div>
    </div>
  </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([10.7, 122.9], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap contributors'}).addTo(map);
const markers = {};
const truckIcon = L.divIcon({className:'', html:'<div style="background:#2d8653;color:#fff;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.25)">🚛</div>', iconSize:[36,36], iconAnchor:[18,18]});

function formatPHT(raw){ if(!raw) return 'unknown'; const d=new Date(raw.replace(' ','T')); return d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true}); }

function refreshTrucks(){
  fetch('/disbasura/api/tracker-public.php')
    .then(r=>r.json())
    .then(trucks=>{
      const list = document.getElementById('truckList');
      if (!trucks.length){
        list.innerHTML = '<div class="empty-state" style="grid-column:1/-1;padding:2rem;text-align:center;color:#7aab8a">No collectors are currently sharing their location.</div>';
        return;
      }
      list.innerHTML = trucks.map(t => `
        <div class="truck-card">
          <div class="truck-icon">🚛</div>
          <div>
            <strong style="display:block;font-size:.88rem;font-weight:700">${t.full_name}</strong>
            <span style="display:block;font-size:.75rem;color:#7aab8a">📍 ${t.sitio}</span>
            <span style="display:block;font-size:.72rem;color:#2d8653">Last ping: ${formatPHT(t.truck_updated_at)}</span>
          </div>
        </div>`).join('');

      trucks.forEach(t => {
        if (!t.truck_lat || !t.truck_lng) return;
        const latlng = [t.truck_lat, t.truck_lng];
        const popup = `<strong>${t.full_name}</strong><br>📍 ${t.sitio}<br><small>Updated: ${formatPHT(t.truck_updated_at)}</small>`;
        if (markers[t.id]) { markers[t.id].setLatLng(latlng).bindPopup(popup); }
        else { markers[t.id] = L.marker(latlng, {icon: truckIcon}).addTo(map).bindPopup(popup); }
      });
      const pts = Object.values(markers).map(m => m.getLatLng());
      if (pts.length > 0) map.fitBounds(L.latLngBounds(pts), {padding:[40,40]});
    });
}
refreshTrucks();
setInterval(refreshTrucks, 15000);
</script>
</body>
</html>
