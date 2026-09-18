<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/base_admin.php';
$db = get_db();
$trucks = $db->query("SELECT id,full_name,sitio,status,truck_lat,truck_lng,truck_updated_at FROM collectors WHERE truck_lat IS NOT NULL ORDER BY truck_updated_at DESC")->fetchAll();
$unread = get_unread_count($_SESSION['admin_id']);
render_admin_header('tracker',$unread,'Live Truck Tracker — DisBasura Admin');
?>
<div class="page-header-row">
  <div class="page-header"><h1>🚛 Live Truck Tracker</h1><p>Real-time GPS locations of active collectors</p></div>
  <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--text-light)">
    <span style="width:9px;height:9px;border-radius:50%;background:var(--green-main);display:inline-block;animation:pulse 1.5s infinite"></span>Auto-refreshes every 15s
  </div>
</div>
<style>
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
#map{width:100%;height:500px;border-radius:16px;border:1px solid var(--border-light);overflow:hidden;margin-bottom:1.5rem}
.truck-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem}
.truck-card{background:#fff;border-radius:12px;border:1px solid var(--border-light);padding:1.1rem;display:flex;align-items:center;gap:.85rem}
.truck-icon{width:44px;height:44px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div id="map"></div>
<div class="truck-list" id="truckList">
  <?php if($trucks): foreach($trucks as $t): ?>
  <div class="truck-card" id="card-<?= $t['id'] ?>">
    <div class="truck-icon">🚛</div>
    <div>
      <strong style="display:block;font-size:.88rem;font-weight:700"><?= htmlspecialchars($t['full_name']) ?></strong>
      <span style="display:block;font-size:.75rem;color:var(--text-light)">📍 <?= htmlspecialchars($t['sitio']) ?></span>
      <span style="display:block;font-size:.75rem;color:var(--text-light)"><?= $t['status']==='available'?'✅ On Duty':($t['status']==='sick'?'🤒 Sick':'⛔ Off Duty') ?></span>
      <?php if($t['truck_updated_at']): ?><span style="display:block;font-size:.72rem;color:var(--green-main)" data-ping="<?= $t['truck_updated_at'] ?>">Last ping: <?= htmlspecialchars($t['truck_updated_at']) ?></span><?php endif; ?>
    </div>
  </div>
  <?php endforeach; else: ?>
  <div class="empty-state" style="grid-column:1/-1;padding:3rem"><p>No collectors are currently sharing their location.</p></div>
  <?php endif; ?>
</div>
<script>
const map=L.map('map').setView([10.7,122.9],13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors'}).addTo(map);
const markers={};
const truckIcon=L.divIcon({className:'',html:'<div style="background:#2d8653;color:#fff;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.25)">🚛</div>',iconSize:[36,36],iconAnchor:[18,18]});
function formatPHT(raw){if(!raw)return'unknown';const d=new Date(raw.replace(' ','T'));return d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});}
function refreshTrucks(){
  fetch('/disbasura/api/tracker.php')
    .then(r=>r.json())
    .then(trucks=>{
      trucks.forEach(t=>{
        if(!t.truck_lat||!t.truck_lng)return;
        const latlng=[t.truck_lat,t.truck_lng];
        if(markers[t.id]){markers[t.id].setLatLng(latlng).bindPopup('<strong>'+t.full_name+'</strong><br>📍 '+t.sitio+'<br><small>Updated: '+formatPHT(t.truck_updated_at)+'</small>');}
        else{markers[t.id]=L.marker(latlng,{icon:truckIcon}).addTo(map).bindPopup('<strong>'+t.full_name+'</strong><br>📍 '+t.sitio+'<br><small>Updated: '+formatPHT(t.truck_updated_at)+'</small>');}
      });
      const pts=Object.values(markers).map(m=>m.getLatLng());
      if(pts.length>0)map.fitBounds(L.latLngBounds(pts),{padding:[40,40]});
      document.querySelectorAll('[data-ping]').forEach(el=>{el.textContent='Last ping: '+formatPHT(el.getAttribute('data-ping'));});
    });
}
refreshTrucks();
setInterval(refreshTrucks,15000);
</script>
<?php render_admin_footer(); ?>
